<?php

namespace ValheimServerUI\Responder;

use Amp\Http\Server\FormParser\Form;
use Amp\Http\Server\HttpServer;
use Amp\Http\Server\Request;
use Amp\Http\Server\Response;
use Amp\Http\Server\ServerObserver;
use Amp\Http\Server\Session\Session;
use Amp\Websocket\Client;
use Amp\Websocket\Server\ClientHandler;
use Amp\Websocket\Server\Gateway;
use Amp\Websocket\Server\WebsocketServerObserver;
use Revolt\EventLoop;
use ValheimServerUI\PeriodicRestarter;
use ValheimServerUI\Permission;
use ValheimServerUI\ServerManager;
use ValheimServerUI\ServerState;
use ValheimServerUI\ServiceStatus;
use ValheimServerUI\Tpl;
use ValheimServerUI\ValheimSocket;
use function Amp\Http\Server\FormParser\parseForm;
use function Amp\Http\Server\redirectTo;
use function ValheimServerUI\json_response;
use function ValheimServerUI\requestCallable;

class Server implements ClientHandler, WebsocketServerObserver {
	private int $cpuNumber;
	private int $maxMemory = 0;
	private array $memoryUsed = [];
	private array $loadAvgs = [];
	private string $sysstatWatcher;
	/** @var Client[] */
	private array $clients = [];

	public function __construct(public ServerState $state) {
	}

	public function show(Request $request, Tpl $tpl, ServerManager $serverManager, PeriodicRestarter $restarter) {
		$serverManager->stateWatchers[__CLASS__] = $this->broadcastServiceStateUpdate(...);

		$tpl->load(__DIR__ . "/../../templates/Server.php");
		$tpl->set("cpuNumber", $this->cpuNumber);
		$tpl->set("maxMemory", $this->maxMemory);
		$tpl->set("memoryUsed", $this->memoryUsed);
		$tpl->set("loadAvgs", $this->loadAvgs);
		$tpl->set("processId", $this->state->serverConfig->getProcessId());
		$tpl->set("maintenanceActive", $this->state->maintenance->getMaintenanceActive());
		$tpl->set("maintenanceStartTime", $this->state->maintenance->getStartTime());
		$tpl->set("serviceState", $serverManager->status());
		$tpl->set("automaticRestartInterval", round($restarter->restartInterval / 3600, 3));
		$tpl->set("automaticRestartNext", $restarter->nextCheck);
		$tpl->set("logHistory", $serverManager->readLog());
		return $tpl->render();
	}

	public function handleHandshake(Gateway $gateway, Request $request, Response $response): Response {
		return requestCallable(function (Request $request) use ($response) {
			return $response;
		}, Permission::View_Server)($request);
	}

	public function handleClient(Gateway $gateway, Client $client, Request $request, Response $response): void {
		$this->clients[$client->getId()] = $client;
		while ($message = $client->receive()) {
			// nothing to handle
		}
		unset($this->clients[$client->getId()]);
	}

	private function broadcastServiceStateUpdate(ServiceStatus $serviceStatus) {
		$json = \json_encode(["serviceState" => $serviceStatus]);
		foreach ($this->clients as $client) {
			$client->send($json);
		}
	}

	public function onStart(HttpServer $server, Gateway $gateway): void {
		$this->cpuNumber = \Amp\Cluster\countCpuCores();
		$this->sysstatWatcher = EventLoop::repeat(5, function () {
			$this->loadAvgs[\time()] = $loadAvg = \sys_getloadavg();
			if (($firstEntry = \key($this->loadAvgs)) < \time() - 1800) {
				unset($this->loadAvgs[$firstEntry]);
			}

			$os = (\stripos(\PHP_OS, "WIN") === 0) ? "win" : \strtolower(\PHP_OS);

			switch ($os) {
				case "win":
					$this->maxMemory = "(Get-CIMInstance Win32_PhysicalMemory | Measure-Object -Property capacity -Sum).Sum";
					$freeMem = "(Get-CIMInstance Win32_OperatingSystem | Select FreePhysicalMemory).FreePhysicalMemory";
					break;
				case "linux":
				case "darwin":
					$meminfo = array_combine(...array_map(null, ...array_map(fn($l) => array_slice(preg_split("(:?\s+)", trim($l)), 0, 2), file("/proc/meminfo"))));
					$freeMem = $meminfo["MemFree"] * 1024;
					$this->maxMemory = $meminfo["MemTotal"] * 1024;
					break;
				default:
					$freeMem = 0;
					break;
			}
			$this->memoryUsed[\time()] = $this->maxMemory - $freeMem;
			if (($firstEntry = \key($this->memoryUsed)) < time() - 1800) {
				unset($this->memoryUsed[$firstEntry]);
			}

			$json = \json_encode(["memory" => array_values($this->memoryUsed), "loadAvg" => array_values($this->loadAvgs)]);
			foreach ($this->clients as $client) {
				$client->send($json);
			}
		});
	}

	public function onStop(HttpServer $server, Gateway $gateway): void {
		EventLoop::cancel($this->sysstatWatcher);
	}

	public function restartServerSafe(ServerManager $serverManager): Response {
		$serverManager->gracefulRestart();
		return redirectTo("/server");
	}

	public function restartServerHard(ServerManager $serverManager): Response {
		$serverManager->hardRestart();
		return redirectTo("/server");
	}

	public function enableMaintenance(ServerManager $serverManager): Response {
		$serverManager->enableMaintenance();
		return redirectTo("/server");
	}

	public function disableMaintenance(ServerManager $serverManager): Response {
		$serverManager->disableMaintenance();
		return redirectTo("/server");
	}

	public function updateRestartInterval(Request $request, PeriodicRestarter $restarter): Response {
		$restarter->updateRestartInterval((parseForm($request)->getValue("restartinterval") ?? 0) * 3600);
		return redirectTo("/server");
	}

	public function saveWorld(ValheimSocket $socket): Response {
		$start = time();
		$socket->saveWorld();
		return json_response(["duration" => time() - $start]);
	}
}

<?php

namespace ValheimServerUI;

use Amp\ByteStream\StreamException;
use Amp\Deferred;
use Amp\Future;
use Amp\Socket\ConnectException;
use Amp\Socket\Socket;
use Google\Protobuf\Internal\Message;
use Monolog\Logger;
use Revolt\EventLoop;
use ValheimServerUI\Proto\IngameMessage;
use ValheimServerUI\Proto\Maintenance;
use ValheimServerUI\Proto\ModList;
use ValheimServerUI\Proto\PlayerList;
use ValheimServerUI\Proto\ServerConfig;
use function Amp\coroutine;
use function Amp\delay;
use function Amp\Socket\connect;

class ValheimSocket {
	/** @var array{0: Deferred, 1: ?Message, 2: string} */
	private array $pendingCommands = [];
	/** @var array{0: callable, 1: ?Message}[] */
	private array $callbacks = [];
	private int $commandId = 0;
	private ?Socket $socket = null;
	public $resetConnection;

	public function __construct(private Logger $logger, string $socket, private ServerState $state) {
		$socketCommands = new SocketCommands($this->state);
		foreach ((new \ReflectionClass($socketCommands))->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
			if ($method->getName()[0] != "_") { // ignore magic methods
				$param = $method->getParameters()[0] ?? null;
				$this->callbacks[$method->getName()] = [$method->getClosure($socketCommands), $param ? new ($param->getType()->getName()) : null];
			}
		}

		$this->resetConnection = $socketCommands->_resetConnection(...);
		coroutine(fn() => $this->watch($socket));
	}

	private function watch(string $address): void {
		$firstConnection = true;
		$this->logger->info("Establishing connection to valheim server at $address...");

		while (true) {
			$attempts = 1;
			while (true) {
				try {
					$this->socket = connect($address);
					$this->logger->warning(($firstConnection ? "C" : "Rec") . "onnected to $address after $attempts attempts");
					$firstConnection = false;
					break;
				} catch (ConnectException $e) {
					$this->logger->warning("Could not " . ($firstConnection ? "" : "re") . "connect to $address ... backing off for 1 second (Attempt $attempts) [{$e->getMessage()}]");
					++$attempts;
				}

				delay(1, false);
			}

			($this->resetConnection)();
			$this->state->connectionReady->apply(fn() => $this->socket->unreference());
			$this->state->active = true;

			$parser = $this->parser();
			while (null !== $data = $this->socket->read()) {
				$parser->send($data);
			}
			$this->state->active = false;
			$unreachable = new ValheimSocketUnreachableException;
			$pending = $this->pendingCommands;
			$this->pendingCommands = [];
			foreach ($pending as [$deferred, $msg, $timeoutWatcher]) {
				$deferred->error($unreachable);
				EventLoop::cancel($timeoutWatcher);
			}
		}
	}

	private function parser(): \Generator {
		$buf = yield;
		while (true) {
			while (\strlen($buf) < 4) {
				$buf .= yield;
			}
			$len = unpack("V", $buf)[1];
			while (\strlen($buf) < $len + 4) {
				$buf .= yield;
			}
			try {
				$this->parsePacket(substr($buf, 4, $len));
			} catch (\Throwable $e) {
				$this->logger->error("Got exception while parsing " . bin2hex(substr($buf, 4, $len)) . ": $e");
			}
			$buf = substr($buf, 4 + $len);
		}
	}

	private function parsePacket($packet): void {
		[1 => $key, 2 => $dataLen] = unpack("V2", $packet);
		$data = substr($packet, 8, $dataLen);
		if ($key && isset($this->pendingCommands[$key])) {
			[$deferred, $message, $timeoutWatcher] = $this->pendingCommands[$key];
			if ($message !== null) {
				/** @var Message $message */
				$message->mergeFromString($data);
			}
			unset($this->pendingCommands[$key]);
			EventLoop::cancel($timeoutWatcher);
			$deferred->complete($message);
		} else {
			$packet = substr($packet, 8 + $dataLen);
			[1 => $payloadLen] = unpack("V", $packet);
			[$callback, $message] = $this->callbacks[$data] ?? [null, null];
			if ($message) {
				$payload = substr($packet, 4, $payloadLen);
				$message = clone $message;
				$message->mergeFromString($payload);
				$callback($message);
			} elseif ($callback) {
				$callback();
			}
		}
	}

	private function commandTimeout($commandId): void {
		[$deferred] = $this->pendingCommands[$commandId];
		unset($this->pendingCommands[$commandId]);
		$deferred->error(new ValheimSocketUnreachableException);
	}

	/**
	 * @throws ValheimSocketUnreachableException
	 */
	private function cmd($command, ?Message $out = null, ?Message $message = null, int $timeout = 2) {
		$commandId = ++$this->commandId;
		$timeoutWatcher = EventLoop::delay($timeout, fn() => $this->commandTimeout($commandId));
		EventLoop::unreference($timeoutWatcher);
		[$deferred] = $this->pendingCommands[$commandId] = [new Deferred, $out, $timeoutWatcher];
		$data = \pack("VV", $commandId, \strlen($command)) . $command;
		if ($message !== null) {
			$raw = $message->serializeToString();
			$data .= pack("V", \strlen($raw)) . $raw;
		} else {
			$data .= "\0\0\0\0";
		}
		try {
			if (!$this->socket) {
				throw new ValheimSocketUnreachableException;
			}
			$this->socket->write(\pack("V", \strlen($data)) . $data);
		} catch (StreamException) {
			throw new ValheimSocketUnreachableException;
		}
		return $deferred->getFuture()->await();
	}

	public function getPlayerList(): PlayerList {
		return $this->cmd("GetPlayerList", new PlayerList);
	}

    public function getModList(): ModList {
		return $this->cmd("GetModList", new ModList);
	}

	public function sendIngameMessage(IngameMessage $message) {
		return $this->cmd("SendIngameMessage", null, $message);
	}

	public function saveWorld() {
		return $this->cmd("SaveWorld", timeout: 600);
	}

	public function kickPlayer(IngameMessage $message) {
		return $this->cmd("KickPlayer", null, $message);
	}
}

class SocketCommands {
	public ?Deferred $ready = null;

	public function __construct(private ServerState $state) {
		$this->_resetConnection();
		$this->state->readyFuture = $this->state->connectionReady;
	}

	public function _resetConnection() {
		$this->ready ??= new Deferred;
		$this->state->connectionReady = $this->ready->getFuture();
		coroutine(function () {
			foreach ($this->state->connectionLossWatchers as $watcher) {
				$watcher();
			}
		});
	}

	public function playerList(PlayerList $playerList) {
		$this->state->players = iterator_to_array($playerList->getPlayerList());
	}

    public function modList(ModList $modList) {
		$this->state->mods = iterator_to_array($modList->getModList());
	}

	public function ServerConfig(ServerConfig $config) {
		$this->state->serverConfig = $config;
	}

	public function MaintenanceMessage(Maintenance $maintenance) {
		$this->state->maintenance = $maintenance;
		if (($maintenance->getStartTime() == 0 || $maintenance->getMaintenanceActive()) && $deferred = $this->state->waitingForMaintenance) {
			$this->state->waitingForMaintenance = null;
			$deferred->complete($maintenance->getMaintenanceActive());
		}
	}

	public function Ready() {
		$this->ready?->complete(null);
		$this->ready = null;
		$this->state->ready = true;
	}
}
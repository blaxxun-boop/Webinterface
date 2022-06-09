<?php

namespace ValheimServerUI;

use Amp\Dbus\Dbus;
use Amp\Parser\Parser;
use Amp\Pipeline\AsyncGenerator;
use Amp\Process\Process;
use Amp\Socket\SocketException;
use Monolog\Logger;
use function Amp\ByteStream\buffer;
use function Amp\coroutine;
use function Amp\Socket\connect;

class SystemdServerManager extends ServerManager {
	public $systemctl = "systemctl";

	private ?ServiceStatus $serviceStatus = null;

	public function __construct(ServerState $state, ValheimSocket $socket, private Logger $logger, private $cgroup)
	{
		parent::__construct($state, $socket);

		$dbus_socket = "";
		$dbus_user = 0;

		if (preg_match('(^/system.slice/(?<service>.*\.service)$)', $this->cgroup, $serviceInfo)) {
			$this->service = $serviceInfo["service"];
			if (posix_getuid() != 0) {
				$this->systemctl = "sudo systemctl";
			}
			$dbus_socket = "/var/run/dbus/system_bus_socket";
		} elseif (preg_match('(^/user\.slice/user-(?P<uid>\d+)\.slice/user@\d+\.service/(.*\.slice/)?(?P<service>.*\.service)$)', $this->cgroup, $serviceInfo)) {
			if (posix_getuid() == $serviceInfo["uid"]) {
				$this->systemctl = "XDG_RUNTIME_DIR='/var/run/user/{$serviceInfo["uid"]}' systemctl --user";
			} else {
				$this->systemctl = "sudo -u '#{$serviceInfo["uid"]}' XDG_RUNTIME_DIR='/var/run/user/{$serviceInfo["uid"]}' systemctl --user";
			}
			$this->service = $serviceInfo["service"];
			$dbus_socket = "/var/run/user/{$serviceInfo["uid"]}/bus";
			$dbus_user = $serviceInfo["uid"];
		} else {
			$this->logger->warning("System service setup is weird. Not continuing for cgroup slice {$this->cgroup}");
		}

		if (file_exists($dbus_socket)) {
			coroutine(fn() => $this->monitorDbus($dbus_socket, $dbus_user));
		} elseif ($dbus_user > 0) {
			$this->logger->warning("Cannot monitor systemd via dbus, is dbus-user-session package not installed? Live updates are disabled.");
		}
	}

	private function monitorDbus($dbus_socket, $dbus_user) {
		try {
			$dbusSocket = connect("unix://$dbus_socket");
			$dbusSocket->unreference();
			$dbus = new Dbus($dbusSocket, asUser: $dbus_user);
			$subscribe = new \Amp\Dbus\Message\MethodCall;
			$subscribe->path = "/org/freedesktop/systemd1";
			$subscribe->destination = "org.freedesktop.systemd1";
			$subscribe->interface = "org.freedesktop.systemd1.Manager";
			$subscribe->method = "Subscribe";
			$dbus->sendAndWaitForReply($subscribe);

			$dbusSocket = connect("unix://$dbus_socket");
			$dbusSocket->unreference();
			$monitoringDbus = new Dbus($dbusSocket, asUser: $dbus_user);
			$monitoredPath = "/org/freedesktop/systemd1/unit/" . \Amp\Dbus\bus_label_escape($this->service);

			$becomeMonitor = new \Amp\Dbus\Message\MethodCall;
			$becomeMonitor->path = "/org/freedesktop/DBus";
			$becomeMonitor->destination = "org.freedesktop.DBus";
			$becomeMonitor->interface = "org.freedesktop.DBus.Monitoring";
			$becomeMonitor->method = "BecomeMonitor";
			$becomeMonitor->signature = "asu";
			$becomeMonitor->data = [["eavesdrop=true,type='signal',path='$monitoredPath'"], 0];
			if (!($monitoringDbus->sendAndWaitForReply($becomeMonitor) instanceof \Amp\Dbus\Message\MethodReturn)) {
				$this->logger->info("Not listening on dbus: not successfully registered");
				return;
			}

			$this->serviceStatus = $this->status();

			while ($message = $monitoringDbus->read()) {
				$updated = false;
				if ($message instanceof \Amp\Dbus\Message\Signal && $message->path === $monitoredPath && $message->interface == "org.freedesktop.DBus.Properties" && $message->signal == "PropertiesChanged") {
					if (isset($message->data[1]["ActiveState"])) {
						$this->serviceStatus->state = $message->data[1]["ActiveState"]->data;
						$updated = true;
					}
					if (isset($message->data[1]["StateChangeTimestamp"])) {
						$this->serviceStatus->stateChange = floor($message->data[1]["StateChangeTimestamp"]->data / 1000000);
						$updated = true;
					}
				}
				if ($updated) {
					coroutine(function () {
						foreach ($this->stateWatchers as $watcher) {
							$watcher($this->serviceStatus);
						}
					});
				}
			}
		} catch (SocketException $e) {
			$this->logger->info("Not listening on dbus: " . $e->getMessage() . ". Live updates are disabled.");
		}

		$this->serviceStatus = null;
	}

	protected function restart() {
		$process = new Process("{$this->systemctl} restart {$this->service}");
		$process->start();
		if ($process->join() != 0) {
			$this->logger->error("Got error while restarting {$this->service}: " . buffer($process->getStderr()));
		}
	}

	public function status(): ServiceStatus {
		if ($this->serviceStatus) {
			return $this->serviceStatus;
		}

		$process = new Process("{$this->systemctl} show {$this->service} --property StateChangeTimestamp --property ActiveState");
		$process->start();
		if ($process->join() != 0) {
			$this->logger->error("Got error while fetching {$this->service}: " . buffer($process->getStderr()));
		}

		$output = trim(buffer($process->getStdout()));
		$properties = [];
		foreach (explode("\n", $output) as $propline) {
			[$key, $properties[$key]] = explode("=", $propline, 2);
		}

		$status = new ServiceStatus;
		$status->state = $properties["ActiveState"];
		$status->stateChange = strtotime($properties["StateChangeTimestamp"]);

		return $status;
	}

	public function logIterator(): \Traversable {
		return new AsyncGenerator(function () {
			$process = new Process(str_replace("systemctl", "journalctl", $this->systemctl) . " -o json -u {$this->service} --follow");
			$process->start();
			$process->getStdout()->unreference();

			$parser = $this->formatLog($out);

			while ($process->isRunning()) {
				$parser->push($process->getStdout()->read());
				yield implode(array_reverse($out));
				$out = [];
			}

			throw new \Exception("'{$process->getCommand()}' aborted with exit code {$process->join()}, stderr:\n" . buffer($process->getStderr()));
		});
	}

	public function readLog(): string {
		$process = new Process(str_replace("systemctl", "journalctl", $this->systemctl) . " -o json -e -u {$this->service} --no-pager");
		$process->start();
		$parser = $this->formatLog($out);
		$parser->push(buffer($process->getStdout()));
		if ($process->join() != 0) {
			$this->logger->error("Got error while fetching {$this->service}: " . buffer($process->getStderr()));
		}
		return implode(array_reverse($out));
	}

	public function formatLog(&$out): Parser {
		$out = [];
		return new Parser((function () use (&$out) {
			while ($jsonLine = yield "\n") {
				$formattedJson = "";
				$line = json_decode($jsonLine);
				if (isset($line->MESSAGE, $line->__REALTIME_TIMESTAMP)) {
					$formattedJson .= date("Y-m-d H:i:s", $line->__REALTIME_TIMESTAMP / 1000000);
					$formattedJson .= "\t";
					$formattedJson .= is_array($line->MESSAGE) ? implode(array_map("chr", $line->MESSAGE)) : $line->MESSAGE;
					$formattedJson .= "\n";
					$out[] = $formattedJson;
				}
			}
		})());
	}
}
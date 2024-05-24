<?php

namespace ValheimServerUI;

use Amp\DeferredFuture;
use Amp\Future;
use Monolog\Logger;
use function Amp\File\deleteFile;
use function Amp\File\write;

abstract class ServerManager {
	public bool $activeHardRestart = false;
	public ?Future $pendingRestart = null;

	public array $stateWatchers = [];

	protected function __construct(protected ServerState $state, protected ValheimSocket $socket) {
	}

	public static function init(ServerState $state, ValheimSocket $socket, Logger $logger, \SQLite3 $db): self {
		$pid = $state->serverConfig->getProcessId();
		if (!$pid) {
			if ($defaultCgroup = $db->querySingle("SELECT value FROM keys WHERE key = 'defaultCgroup'")) {
				return new SystemdServerManager($state, $socket, $logger, $defaultCgroup);
			}
			$state->connectionReady->await();
			$pid = $state->serverConfig->getProcessId();
		}
		$cgroups = file_get_contents("/proc/$pid/cgroup");
		if (preg_match('(^\d+:name=systemd:\K/.*\.service$)m', $cgroups, $cgroup) || preg_match('(^\d+::\K/.*\.slice/.*\.service$)m', $cgroups, $cgroup)) {
			$stmt = $db->prepare("INSERT OR REPLACE INTO keys (key, value) VALUES ('defaultCgroup', :cgroup)");
			$stmt->bindValue("cgroup", $cgroup[0]);
			$stmt->execute();
			return new SystemdServerManager($state, $socket, $logger, $cgroup[0]);
		}

		return new class($state, $socket) extends ServerManager {
			protected function restart() {}
			public function status(): ServiceStatus { return new ServiceStatus; }
			public function logIterator(): iterable { return []; }
			public function readLog(): string { return ""; }
		};
	}

	abstract protected function restart();

	public function hardRestart(): Future {
		if (!$this->activeHardRestart) {
			$this->activeHardRestart = true;
			$this->pendingRestart = \Amp\async(function() {
				$this->restart();
				$this->pendingRestart = null;
				$this->activeHardRestart = false;
			});
		}
		return $this->pendingRestart;
	}

	public function gracefulRestart(): Future {
		if (!$this->pendingRestart) {
			$this->pendingRestart = \Amp\async(function () {
				if (!$this->state->maintenance->getMaintenanceActive()) {
					$future = ($this->state->waitingForMaintenance ??= new DeferredFuture)->getFuture();
					$this->enableMaintenance();
					if (!$future->await()) {
						return;
					}
				}

				$this->restart();
				($this->socket->resetConnection)();

				$this->state->connectionReady->await();
				$this->disableMaintenance();

				$this->pendingRestart = null;
			});
		}
		return $this->pendingRestart;
	}

	public function enableMaintenance() {
		write($this->state->serverConfig->getConfigPath() . "/maintenance", "");
	}

	public function disableMaintenance() {
		deleteFile($this->state->serverConfig->getConfigPath() . "/maintenance");
	}

	abstract public function status(): ServiceStatus;

	abstract public function readLog(): string;

	abstract public function logIterator(): iterable;
}
<?php

namespace ValheimServerUI;

use Revolt\EventLoop;
use ValheimServerUI\Proto\WebinterfacePlayer;

class PeriodicRestarter {
	public int $nextCheck;
	public int $restartInterval;
	private string $watcher;
	private \SQLite3Stmt $updateInterval;

	const SHORT_INTERVAL = 300;

	public function __construct(private \SQLite3 $db, private ServerManager $serverManager, private ValheimSocket $socket) {
		$this->restartInterval = $db->querySingle("SELECT value FROM keys WHERE key = 'periodic_restart_interval'") ?? 0;
		$this->nextCheck = max(time() + self::SHORT_INTERVAL, $db->querySingle("SELECT value FROM keys WHERE key = 'periodic_restart_next_check'") ?? (time() + $this->restartInterval));
		$this->updateInterval = $db->prepare("INSERT OR REPLACE INTO keys (key, value) VALUES ('periodic_restart_next_check', :time)");
		if ($this->restartInterval) {
			$this->setupWatcher();
		}
	}

	public function updateRestartInterval($interval) {
		$stmt = $this->db->prepare("INSERT OR REPLACE INTO keys (key, value) VALUES ('periodic_restart_interval', :interval)");
		$stmt->bindValue("interval", $interval);
		$stmt->execute();

		if ($this->restartInterval) {
			EventLoop::cancel($this->watcher);
		}
		$this->restartInterval = $interval;
		$this->nextCheck = time() + $interval;
		if ($interval) {
			$this->setupWatcher();
		}
	}

	private function setupWatcher() {
		$this->watcher = EventLoop::delay($this->nextCheck - time(), function () {
			try {
				if (array_filter(iterator_to_array($this->socket->getPlayerList()->getPlayerList()), fn(WebinterfacePlayer $p) => $p->getStatistics()->getLastTouch() == 0)) {
					$this->nextCheck = time() + self::SHORT_INTERVAL;
				} else {
					$this->socket->saveWorld();
					$this->serverManager->hardRestart();
					$this->nextCheck = time() + $this->restartInterval;
				}
			} catch (ValheimSocketUnreachableException) {
				// it's down, so don't schedule a restart soon
				$this->nextCheck = time() + $this->restartInterval;
			}
			$this->setupWatcher();
		});
		EventLoop::unreference($this->watcher);

		$this->updateInterval->bindValue("time", $this->nextCheck);
		$this->updateInterval->execute();
	}
}
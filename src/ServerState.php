<?php

namespace ValheimServerUI;

use Amp\DeferredFuture;
use Amp\Future;
use ValheimServerUI\Proto\Maintenance;
use ValheimServerUI\Proto\ServerConfig;
use ValheimServerUI\Proto\WebinterfacePlayer;

class ServerState {
	public bool $active = true;
	/** @var WebinterfacePlayer[] */
	public array $players = [];
	public ServerConfig $serverConfig;
	public Maintenance $maintenance;
	public ?DeferredFuture $waitingForMaintenance = null;

	public bool $ready = false;
	public Future $readyFuture;
	public Future $connectionReady;
	/** @var callable[] */
	public array $connectionLossWatchers = [];

	public function __construct() {
		$this->maintenance = new Maintenance;
	}
}
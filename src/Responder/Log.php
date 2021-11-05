<?php

namespace ValheimServerUI\Responder;

use Amp\Http\Server\HttpServer;
use Amp\Http\Server\Request;
use Amp\Http\Server\Response;
use Amp\Websocket\Client;
use Amp\Websocket\Server\ClientHandler;
use Amp\Websocket\Server\Gateway;
use Amp\Websocket\Server\WebsocketServerObserver;
use ValheimServerUI\Permission;
use ValheimServerUI\ServerManager;
use ValheimServerUI\Tpl;
use function Amp\coroutine;
use function ValheimServerUI\requestCallable;

class Log implements ClientHandler, WebsocketServerObserver {
	public function __construct(private ServerManager $serverManager) {}

	public function onStart(HttpServer $server, Gateway $gateway): void {
		coroutine(function () use ($gateway) {
			foreach ($this->serverManager->logIterator() as $logLine) {
				$gateway->broadcast($logLine);
			}
		});
	}

	public function onStop(HttpServer $server, Gateway $gateway): void {}

	public function handleHandshake(Gateway $gateway, Request $request, Response $response): Response {
		return requestCallable(function (Request $request) use ($response) {
			return $response;
		}, Permission::View_Server)($request);
	}

	public function handleClient(Gateway $gateway, Client $client, Request $request, Response $response): void {
		while ($message = $client->receive()) {
			// nothing to handle
		}
	}
}
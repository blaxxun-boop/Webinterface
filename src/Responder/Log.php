<?php

namespace ValheimServerUI\Responder;

use Amp\Http\Server\Request;
use Amp\Http\Server\Response;
use Amp\Websocket\Server\Rfc6455Acceptor;
use Amp\Websocket\Server\WebsocketAcceptor;
use Amp\Websocket\Server\WebsocketClientGateway;
use Amp\Websocket\Server\WebsocketClientHandler;
use Amp\Websocket\Server\WebsocketGateway;
use Amp\Websocket\WebsocketClient;
use ValheimServerUI\Permission;
use ValheimServerUI\ServerManager;
use function ValheimServerUI\requestCallable;

class Log implements WebsocketAcceptor, WebsocketClientHandler {
	private WebsocketAcceptor $acceptor;
	private WebsocketGateway $gateway;

	public function __construct(private ServerManager $serverManager) {
		$this->acceptor = new Rfc6455Acceptor;
		$this->gateway = new WebsocketClientGateway;
		\Amp\async(function() {
			foreach ($this->serverManager->logIterator() as $logLine) {
				$this->gateway->broadcastText($logLine)->ignore();
			}
		});
	}

	public function handleHandshake(Request $request): Response {
		return requestCallable(function (Request $request) {
			return $this->acceptor->handleHandshake($request);
		}, Permission::View_Server)($request);
	}

	public function handleClient(WebsocketClient $client, Request $request, Response $response): void {
		$this->gateway->addClient($client);

		while ($message = $client->receive()) {
			// nothing to handle
		}
	}
}
<?php

namespace ValheimServerUI\Responder;

use Amp\Http\Server\Request;
use Google\Protobuf\Internal\Message;
use ValheimServerUI\Proto\IngameMessage;
use ValheimServerUI\Proto\PlayerList;
use ValheimServerUI\ServerState;
use ValheimServerUI\Tpl;
use ValheimServerUI\ValheimSocket;
use function Amp\Http\Server\FormParser\parseForm;
use function Amp\Http\Server\redirectTo;

class OnlinePlayers {
	public function __construct(public ValheimSocket $socket) {
	}

	public function show(Request $request, Tpl $tpl) {
		$tpl->load(__DIR__ . "/../../templates/OnlinePlayers.php");

		/** @var \ValheimServerUI\Proto\WebinterfacePlayer[] $players */
		$players = iterator_to_array($this->socket->getPlayerList()->getPlayerList());

		$tpl->set('players', array_filter($players, fn($player) => $player->getStatistics()->getLastTouch() == 0));

		return $tpl->render();
	}

	public function sendMessage(Request $request) {
		$form = parseForm($request);
		$steamIds = $form->getValueArray("steamId");
		$message = new IngameMessage();
		$message->setSteamId($steamIds);
		$message->setMessage($form->getValue("message"));

		$this->socket->sendIngameMessage($message);

		return redirectTo("/players/online");
	}

	public function kick(Request $request) {
		$form = parseForm($request);
		$steamIds = $form->getValueArray("steamId");
		$message = new IngameMessage();
		$message->setSteamId($steamIds);

		if ($usermsg = $form->getValue("message")) {
			$message->setMessage($usermsg);
		}

		$this->socket->kickPlayer($message);

		return redirectTo("/players/online");
	}
}

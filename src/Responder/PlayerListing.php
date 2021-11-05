<?php

namespace ValheimServerUI\Responder;

use Amp\Http\Server\Request;
use ValheimServerUI\Proto\PlayerList;
use ValheimServerUI\ServerState;
use ValheimServerUI\Tpl;
use ValheimServerUI\ValheimSocket;

class PlayerListing {
	public function __construct(public ValheimSocket $socket) {}

	public function show(Request $request, Tpl $tpl) {
		$tpl->load(__DIR__ . "/../../templates/PlayerListing.php");

		$playerList = $this->socket->getPlayerList();

		$tpl->set('table', $playerList);

		return $tpl->render();
	}
}

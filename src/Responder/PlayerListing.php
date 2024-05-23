<?php

namespace ValheimServerUI\Responder;

use Amp\Http\Server\Request;
use ValheimServerUI\Proto\PlayerList;
use ValheimServerUI\ServerState;
use ValheimServerUI\Tpl;
use ValheimServerUI\ValheimSocket;
use function Amp\Http\Server\FormParser\parseForm;
use function Amp\Http\Server\redirectTo;

class PlayerListing {
	public function __construct(public ValheimSocket $socket) {}

	public function addStat(Request $request, \SQLite3 $db) {
		$form = parseForm($request);
		if ($stat = $form->getValue("stat")) {
			$stmt = $db->prepare("INSERT OR IGNORE INTO activePlayerStats (stat) VALUES (:stat)");
			$stmt->bindValue("stat", $stat);
			$stmt->execute();
		}

		return redirectTo("/players");
	}

	public function removeStat(Request $request, \SQLite3 $db) {
		$form = parseForm($request);
		if ($stat = $form->getValue("stat")) {
			$stmt = $db->prepare("DELETE FROM activePlayerStats WHERE stat = :stat");
			$stmt->bindValue("stat", $stat);
			$stmt->execute();
		}

		return redirectTo("/players");
	}

	public function show(Request $request, Tpl $tpl, \SQLite3 $db) {
		$tpl->load(__DIR__ . "/../../templates/PlayerListing.php");

		$playerList = $this->socket->getPlayerList();

		$stats = [];
		$result = $db->query("SELECT stat FROM activePlayerStats");
		while ($row = $result->fetchArray()) {
			$stats[] = $row['stat'];
		}

		$tpl->set('table', $playerList);
		$tpl->set('stats', $stats);

		return $tpl->render();
	}
}

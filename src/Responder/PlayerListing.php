<?php

namespace ValheimServerUI\Responder;

use Amp\Http\Server\FormParser\Form;
use Amp\Http\Server\Request;
use ValheimServerUI\Permission;
use ValheimServerUI\PermissionSet;
use ValheimServerUI\Tpl;
use ValheimServerUI\ValheimSocket;
use function Amp\Http\Server\redirectTo;

class PlayerListing {
	public function __construct(public ValheimSocket $socket) {}

	public function addStat(Request $request, \SQLite3 $db) {
		$form = Form::fromRequest($request);
		if ($stat = $form->getValue("stat")) {
			$stmt = $db->prepare("INSERT OR IGNORE INTO activePlayerStats (stat) VALUES (:stat)");
			$stmt->bindValue("stat", $stat);
			$stmt->execute();
		}

		return redirectTo("/players");
	}

	public function removeStat(Request $request, \SQLite3 $db) {
		$form = Form::fromRequest($request);
		if ($stat = $form->getValue("stat")) {
			$stmt = $db->prepare("DELETE FROM activePlayerStats WHERE stat = :stat");
			$stmt->bindValue("stat", $stat);
			$stmt->execute();
		}

		return redirectTo("/players");
	}

	public function show(Request $request, Tpl $tpl, \SQLite3 $db, PermissionSet $permissions) {
		$tpl->load(__DIR__ . "/../../templates/PlayerListing.php");

		$playerList = $this->socket->getPlayerList();

		$stats = [];
		$result = $db->query("SELECT stat FROM activePlayerStats");
		while ($row = $result->fetchArray()) {
			$stats[] = $row['stat'];
		}

		$tpl->set('table', $playerList);
		$tpl->set('stats', $stats);
		$tpl->set('canManage', $permissions->allows(Permission::Manage_Stats));

		return $tpl->render();
	}
}

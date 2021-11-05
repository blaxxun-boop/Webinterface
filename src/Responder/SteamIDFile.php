<?php

namespace ValheimServerUI\Responder;

use Amp\File\FilesystemException;
use Amp\Http\Server\FormParser\Form;
use Amp\Http\Server\Request;
use Amp\Http\Server\Response;
use Amp\Http\Server\Router;
use Amp\Http\Server\Session\Session;
use Amp\Http\Status;
use ValheimServerUI\Permission;
use ValheimServerUI\PermissionSet;
use ValheimServerUI\Proto\ModList;
use ValheimServerUI\Proto\WebinterfaceMod;
use ValheimServerUI\ServerState;
use ValheimServerUI\Tpl;
use ValheimServerUI\ValheimSocket;
use function Amp\Http\Server\FormParser\parseForm;
use function Amp\Http\Server\redirectTo;
use function ValheimServerUI\json_response;

class SteamIDFile {
	private const LISTFILES = ["permittedlist.txt", "adminlist.txt", "bannedlist.txt"];

	public function __construct(public ValheimSocket $socket, public ServerState $state) {
	}

	public function show(Request $request, Tpl $tpl, \SQLite3 $db, PermissionSet $permissions) {
		$tpl->load(__DIR__ . "/../../templates/SteamIDLists.php");

		$default = array_fill_keys(self::LISTFILES, false) + ["webinterface" => ""];

		$savePath = $this->state->serverConfig->getSavePath();
		$steamIds = [];
		foreach (self::LISTFILES as $listfile) {
			foreach (explode("\n", \Amp\file\read($savePath . "/" . $listfile)) as $line) {
				if ($line && !str_starts_with($line, "//")) {
					$steamIds[$line][$listfile] = true;
					$steamIds[$line] += $default;
				}
			}
		}

		$stmt = $db->prepare('SELECT username FROM user WHERE steam_id = :steamid');
		foreach ($steamIds as $steamId => &$data)
		{
            $stmt->bindValue("steamid", $steamId);
            $result = $stmt->execute();
			if ($user = $result->fetchArray()) {
				$data["webinterface"] = $user["username"];
			}
		}

		$tpl->set("steamIds", $steamIds);
		$tpl->set('canManage', $permissions->allows(Permission::Manage_Lists));

		return $tpl->render();
	}

	public function writeListFiles(Request $request, Tpl $tpl, \SQLite3 $db, PermissionSet $permissions) {
		$form = parseForm($request);
		$savePath = $this->state->serverConfig->getSavePath();
		$saveLists = [];
		foreach (self::LISTFILES as $listfile) {
			$saveLists[$listfile] = $form->getValueArray($listfile);
		}
		if ($id = $form->getValue("newEntryID")) {
			foreach ($form->getValueArray("newEntry") as $listfile) {
				$saveLists[$listfile][] = $id;
			}
		}
		foreach (self::LISTFILES as $listfile) {
			try {
				\Amp\File\write($savePath . "/" . $listfile, implode("\n", $saveLists[$listfile]));
			} catch (FilesystemException $e) {
				$tpl->set("error", $e->getMessage());
			}
		}

		return $this->show($request, $tpl, $db, $permissions);
	}
}

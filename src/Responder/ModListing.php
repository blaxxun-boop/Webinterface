<?php

namespace ValheimServerUI\Responder;

use Amp\Http\Server\Request;
use ValheimServerUI\Permission;
use ValheimServerUI\PermissionSet;
use ValheimServerUI\Proto\ModList;
use ValheimServerUI\ServerState;
use ValheimServerUI\Tpl;
use ValheimServerUI\ValheimSocket;

class ModListing {
	public function __construct(public ValheimSocket $socket) {}

	public function show(Request $request, Tpl $tpl, PermissionSet $permissions) {
		$tpl->load(__DIR__ . "/../../templates/ModListing.php");

		$modList = $this->socket->getModList();

		$tpl->set('table', $modList);
		$tpl->set('canManage', $permissions->allows(Permission::Manage_Mods));

		return $tpl->render();
	}
}

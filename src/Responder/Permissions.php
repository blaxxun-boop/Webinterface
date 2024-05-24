<?php

namespace ValheimServerUI\Responder;

use Amp\Http\Server\FormParser\Form;
use Amp\Http\Server\Request;
use Amp\Http\Server\Router;
use ValheimServerUI\Permission;
use ValheimServerUI\Tpl;
use ValheimServerUI\ValheimSocket;
use function Amp\Http\Server\redirectTo;

class Permissions {
	public function __construct(public ValheimSocket $socket) {
	}

	public function show(Request $request, Tpl $tpl, \SQLite3 $db) {
		$tpl->load(__DIR__ . "/../../templates/Permissions.php");
		$args = $request->getAttribute(Router::class);
		$currentGroup = $args["group_id"] ?? 0;

		$groupList = [];
		$result = $db->query("SELECT * FROM permissionGroup");
		while ($group = $result->fetchArray()) {
			$groupList[$group["group_id"]] = $group;
		}

		if (!isset($groupList[$currentGroup])) {
			$currentGroup = 0;
		}

		$tpl->set('groups', $groupList);
		$tpl->set('group_id', $currentGroup);

		$permissionList = [];
		$stmt = $db->prepare('SELECT * FROM permission WHERE group_id = :group_id');
		$stmt->bindValue("group_id", $currentGroup);
		$result = $stmt->execute();
		$reflectionPermissions = new \ReflectionEnum(Permission::class);
		while ($permission = $result->fetchArray()) {
			if ($reflectionPermissions->hasCase($permission["permission"])) {
				$permissionList[] = \constant(Permission::class . "::" . $permission["permission"]);
			}
		}

		$tpl->set('permissions', $permissionList);

		return $tpl->render();
	}

	public function createGroup(Request $request, \SQLite3 $db) {
		$form = Form::fromRequest($request);
		$stmt = $db->prepare('INSERT INTO permissionGroup (groupname) VALUES (:groupname)');
		$stmt->bindValue("groupname", $form->getValue("groupname"));
		$stmt->execute();

		return redirectTo("/permissions/" . $db->lastInsertRowID());
	}

	public function updateGroup(Request $request, Tpl $tpl, \SQLite3 $db) {
		$form = Form::fromRequest($request);
		$args = $request->getAttribute(Router::class);
		$group_id = $args["group_id"] ?? 0;

		$stmt = $db->prepare('UPDATE permissionGroup SET groupname = :groupname WHERE group_id = :group_id');
		$stmt->bindValue("groupname", $form->getValue("groupname"));
		$stmt->bindValue("group_id", $group_id);
		$stmt->execute();

		if ($group_id != 1) {
			$insertStmt = $db->prepare('INSERT OR IGNORE INTO permission (group_id, permission) VALUES (:group_id, :permission)');
			$deleteStmt = $db->prepare('DELETE FROM permission WHERE group_id = :group_id AND permission = :permission');
			foreach (Permission::cases() as $permission) {
				if ($permission != Permission::Admin) {
					$stmt = $form->getValue($permission->name) == "yes" ? $insertStmt : $deleteStmt;
					$stmt->bindValue("group_id", $group_id);
					$stmt->bindValue("permission", $permission->name);
					$stmt->execute();
				}
			}
		}

		return $this->show($request, $tpl, $db);
	}

	public function deleteGroup(Request $request, \SQLite3 $db) {
		$form = Form::fromRequest($request);
		$group_id = $form->getValue("group_id");
		if ($group_id > 1) {
			$stmt = $db->prepare('UPDATE user SET group_id = 0 WHERE group_id = :group_id');
			$stmt->bindValue("group_id", $group_id);
			$stmt->execute();

			$stmt = $db->prepare('DELETE FROM permissionGroup WHERE group_id = :group_id');
			$stmt->bindValue("group_id", $group_id);
			$stmt->execute();
		}

		return redirectTo("/permissions");
	}
}

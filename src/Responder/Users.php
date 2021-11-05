<?php

namespace ValheimServerUI\Responder;

use Amp\Http\Server\Request;
use Amp\Http\Server\Router;
use ValheimServerUI\Permission;
use ValheimServerUI\PermissionSet;
use ValheimServerUI\Proto\ModList;
use ValheimServerUI\ServerState;
use ValheimServerUI\Tpl;
use ValheimServerUI\ValheimSocket;
use function Amp\Http\Server\FormParser\parseForm;
use function ValheimServerUI\json_response;

class Users {
	public function __construct(public ValheimSocket $socket) {
	}

	public function show(Request $request, Tpl $tpl, \SQLite3 $db, PermissionSet $permissions) {
		$tpl->load(__DIR__ . "/../../templates/Users.php");

		$userList = [];
		$result = $db->query("SELECT * FROM user");
		while ($user = $result->fetchArray()) {
			$userList[$user["id"]] = $user;
		}

		$groupList = [];
		$result = $db->query("SELECT * FROM permissionGroup");
		while ($group = $result->fetchArray()) {
			$groupList[$group["group_id"]] = $group;
		}

		$tpl->set('userList', $userList);
		$tpl->set('groupList', $groupList);
		$tpl->set('canManage', $permissions->allows(Permission::Manage_Users));

		return $tpl->render();
	}

	public function createUser(Request $request, \SQLite3 $db) {
		$form = parseForm($request);
		$username = $form->getValue("username") ?? "";
		$group_id = $form->getValue("group_id") ?? 0;
		$steam_id = preg_replace("([^0-9])", "", $form->getValue("steam_id") ?? "");

		if ($username == "") {
			return json_response(["error" => "Username is empty"]);
		}

		$pwd = base64_encode(random_bytes(12));
		$stmt = $db->prepare('INSERT INTO user (username, password, group_id, steam_id, forcePasswordChange) VALUES (:username, :password, :group_id, :steam_id, true)');
		$stmt->bindValue("username", $username);
		$stmt->bindValue("password", \password_hash($pwd, \PASSWORD_DEFAULT));
		$stmt->bindValue("group_id", $group_id);
		$stmt->bindValue("steam_id", $steam_id);
		$stmt->execute();

		if (!$db->changes()) {
			return json_response(["error" => "User $username already exists"]);
		}

		return json_response(["password" => $pwd]);
	}

	public function resetPassword(Request $request, \SQLite3 $db) {
		$args = $request->getAttribute(Router::class);
		$user_id = $args["user_id"] ?? 0;

		$pwd = base64_encode(random_bytes(12));
		$stmt = $db->prepare('UPDATE user SET password = :password, forcePasswordChange = true WHERE id = :id');
		$stmt->bindValue("password", \password_hash($pwd, \PASSWORD_DEFAULT));
		$stmt->bindValue("id", $user_id);
		$stmt->execute();

		return json_response(["password" => $pwd]);
	}

	public function deleteUser(Request $request, \SQLite3 $db) {
		$args = $request->getAttribute(Router::class);
		$user_id = $args["user_id"] ?? 0;

		$stmt = $db->prepare('DELETE FROM user WHERE id = :id');
		$stmt->bindValue("id", $user_id);
		$stmt->execute();

		return json_response([]);
	}

	public function setGroup(Request $request, \SQLite3 $db) {
		$args = $request->getAttribute(Router::class);
		$user_id = $args["user_id"] ?? 0;
		$form = parseForm($request);
		$group_id = $form->getValue("group_id") ?? 0;

		$stmt = $db->prepare('SELECT * FROM permissionGroup WHERE group_id = :group_id');
		$stmt->bindValue("group_id", $group_id);
		$result = $stmt->execute();
		if ($result->fetchArray()) {
			$stmt = $db->prepare('UPDATE user SET group_id = :group_id WHERE id = :id');
			$stmt->bindValue("group_id", $group_id);
			$stmt->bindValue("id", $user_id);
			$stmt->execute();
		} else {
			return json_response(["error" => "Unknown group_id $group_id"]);
		}

		return json_response([]);
	}

	public function updateSteamId(Request $request, \SQLite3 $db) {
		$args = $request->getAttribute(Router::class);
		$user_id = $args["user_id"] ?? 0;
		$form = parseForm($request);
		$steam_id = preg_replace("([^0-9])", "", $form->getValue("steam_id") ?? "");

		$stmt = $db->prepare('UPDATE user SET steam_id = :steam_id WHERE id = :id');
		$stmt->bindValue("steam_id", $steam_id);
		$stmt->bindValue("id", $user_id);
		$stmt->execute();

		return json_response([]);
	}
}

<?php

namespace ValheimServerUI\Responder;

use Amp\Http\Server\FormParser\Form;
use Amp\Http\Server\Request;
use Amp\Http\Server\Session\Session;
use ValheimServerUI\ServerState;
use ValheimServerUI\Tpl;
use function Amp\Http\Server\FormParser\parseForm;
use function Amp\Http\Server\redirectTo;
use function ValheimServerUI\json_response;

class Settings {
	public function __construct(public ServerState $state) {
	}

	public function show(Request $request, Tpl $tpl) {

		$tpl->load(__DIR__ . "/../../templates/Settings.php");
		return $tpl->render();
	}

	public function changePassword(Request $request, Tpl $tpl, \SQLite3 $db, Session $session) {
		$form = parseForm($request);

		$stmt = $db->prepare('SELECT * FROM user WHERE id = :user_id');
		$stmt->bindValue("user_id", $session->get("user_id"));
		$result = $stmt->execute();
		if (!$user = $result->fetchArray()) {
			return json_response(["error" => "Could not get user?!"]);
		}

		if (!\password_verify($form->getValue("oldPassword"), $user["password"])) {
			return json_response(["error" => "Current password is not correct"]);
		}

		$password = $form->getValue("newPassword");
		$passwordCheck = $form->getValue("passwordCheck");
		if ($password == "" || $password !== $passwordCheck) {
			return json_response(["error" => "New passwords do not match"]);
		}

		$stmt = $db->prepare('UPDATE user SET password = :password, forcePasswordChange = false WHERE id = :user_id');
		$stmt->bindValue("password", \password_hash($password, \PASSWORD_DEFAULT));
		$stmt->bindValue("user_id", $user["id"]);
		$stmt->execute();

		return json_response([]);
	}
}

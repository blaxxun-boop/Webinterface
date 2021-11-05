<?php

namespace ValheimServerUI\Responder;

use Amp\Http\Server\FormParser\Form;
use Amp\Http\Server\Request;
use Amp\Http\Server\Session\Session;
use ValheimServerUI\ServerState;
use ValheimServerUI\Tpl;
use function Amp\Http\Server\FormParser\parseForm;
use function Amp\Http\Server\redirectTo;

class Login {
	public function __construct(public ServerState $state) {
	}

	public function show(Request $request, Tpl $tpl) {

		$tpl->load(__DIR__ . "/../../templates/Login.php");
		$tpl->set("failedLogin", false);
		return $tpl->render();
	}

	public function loginUser(Request $request, Tpl $tpl, \SQLite3 $db, Session $session) {
		$form = parseForm($request);

		$stmt = $db->prepare('SELECT * FROM user WHERE username = :username');
		$stmt->bindValue("username", $form->getValue("username"));
		$result = $stmt->execute();
		if ($user = $result->fetchArray()) {
			if (\password_verify($form->getValue("password"), $user["password"])) {
				$session->open();
				if ($user["forcePasswordChange"]) {
					$session->set("password_change_user_id", $user["id"]);
				} else {
					$session->set("user_id", $user["id"]);
				}
				$session->set("username", $user["username"]);
				$session->save();

				$stmt = $db->prepare('UPDATE user SET last_login = :logintime WHERE id = :user_id');
				$stmt->bindValue("logintime", time());
				$stmt->bindValue("user_id", $user["id"]);
				$stmt->execute();

				if ($user["forcePasswordChange"]) {
					return redirectTo("/ChangePassword");
				}
				return redirectTo("/");
			}
		}

		$tpl->load(__DIR__ . "/../../templates/Login.php");
		$tpl->set("failedLogin", true);
		return $tpl->render();
	}

	public function changePassword(Request $request, Tpl $tpl, \SQLite3 $db, Session $session) {
		$form = parseForm($request);

		if (!$session->get("password_change_user_id")) {
			return redirectTo("/");
		}

		$stmt = $db->prepare('SELECT * FROM user WHERE id = :user_id');
		$stmt->bindValue("user_id", $session->get("password_change_user_id"));
		$result = $stmt->execute();
		if ($user = $result->fetchArray()) {
			$password = $form->getValue("password");
			$passwordCheck = $form->getValue("passwordCheck");
			if ($password != "" && $password === $passwordCheck)
			{
				$stmt = $db->prepare('UPDATE user SET password = :password, forcePasswordChange = false WHERE id = :user_id');
            	$stmt->bindValue("password", \password_hash($password, \PASSWORD_DEFAULT));
				$stmt->bindValue("user_id", $user["id"]);
				$stmt->execute();

				$session->open();
				$session->set("user_id", $user["id"]);
				$session->unset("password_change_user_id");
				$session->save();
			}
			else
			{
				$tpl->load(__DIR__ . "/../../templates/ChangePassword.php");
				$tpl->set("passwordMismatch", $password !== null);
				return $tpl->render();
			}
		}

		return redirectTo("/");
	}
}

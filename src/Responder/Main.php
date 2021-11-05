<?php

namespace ValheimServerUI\Responder;

use Amp\Http\Server\FormParser\Form;
use Amp\Http\Server\Request;
use Amp\Http\Server\Session\Session;
use ValheimServerUI\PermissionSet;
use ValheimServerUI\ServerState;
use ValheimServerUI\Tpl;
use function Amp\Http\Server\FormParser\parseForm;
use function Amp\Http\Server\redirectTo;

class Main {
	public function __construct(public ServerState $state) {}

	public function show(Request $request, Tpl $tpl, \SQLite3 $db, PermissionSet $permissions) {
        $results = $db->query('SELECT COUNT(1) FROM user');
        [$rows] = $results->fetchArray();
        if ($rows == 0) {
		    $tpl->load(__DIR__ . "/../../templates/FirstSetup.php");
    		return $tpl->render();
        }
        
		$tpl->load(__DIR__ . "/../../templates/Main.php");

		$table = [
			'Information' => [
				['title' => 'Server Dashboard',          'href' => 'server'],
				['title' => 'Player Overview',          'href' => 'players'],
			],

			'Management' => [
				['title' => 'SteamID Management',        'href' => 'steamidlists'],
				['title' => 'Player Management',        'href' => 'players/online'],
				['title' => 'Mod Management',   		'href' => 'modlist'],
			],

			'Admin' => [
				['title' => 'User Management',              'href' => 'users'],
				['title' => 'Permission Management',        'href' => 'permissions'],

			],
		];
		foreach ($table as &$row) {
			$row = array_filter($row, fn($entry) => $permissions->allowsRoute($entry["href"]));
		}

		$tpl->set('table', array_filter($table));

		return $tpl->render();
	}

    public function createAdmin(Request $request, \SQLite3 $db, Session $session) {
        $form = parseForm($request);

        $results = $db->query('SELECT COUNT(1) FROM user');
        [$rows] = $results->fetchArray();
        if ($rows == 0 && $form->getValue("username") != "" && $form->getValue("password") != "")
        {
            $stmt = $db->prepare('INSERT INTO user (username, password, group_id, last_login) VALUES (:username, :password, 1, ' . time() . ')');
            $stmt->bindValue("username", $form->getValue("username"));
            $stmt->bindValue("password", \password_hash($form->getValue("password"), \PASSWORD_DEFAULT));
            $stmt->execute();

			$user_id = $db->lastInsertRowID();

			$session->open();
            $session->set("user_id", $user_id);
            $session->set("username", $form->getValue("username"));
            $session->save();
        }
        return redirectTo("/");
    }
}

<?php

namespace ValheimServerUI\Responder;

use Amp\File\FilesystemException;
use Amp\Http\Server\FormParser\Form;
use Amp\Http\Server\Request;
use Amp\Http\Server\Response;
use Amp\Http\Server\Router;
use Amp\Http\Server\Session\Session;
use Amp\Http\Status;
use ValheimServerUI\Proto\ModList;
use ValheimServerUI\Proto\WebinterfaceMod;
use ValheimServerUI\ServerState;
use ValheimServerUI\Tpl;
use ValheimServerUI\ValheimSocket;
use function Amp\Http\Server\FormParser\parseForm;
use function Amp\Http\Server\redirectTo;
use function ValheimServerUI\json_response;

class ConfigFile {
	public function __construct(public ValheimSocket $socket, public ServerState $state) {
	}

	public function fetchConfigFilePath($guid) {
		$serverConfig = $this->state->serverConfig;
		$modList = $this->socket->getModList();
		$configFile = "";
		foreach ($modList->getModList() as $mod) {
			/** @var WebinterfaceMod $mod */
			if ($mod->getGuid() == $guid) {
				$configFile = $mod->getConfigPath() ?? "";
			}
		}
		if ($configFile != "") {
			$configFilePath = $serverConfig->getConfigPath() . "/" . $configFile;
			return $configFilePath;
		}

		return "";
	}

	public function fetchFileContent(Request $request) {
		$args = $request->getAttribute(Router::class);
		$guid = $args["guid"] ?? 0;

		try {
			$configFile = $this->fetchConfigFilePath($guid);
			if ($configFile != "") {
				return new Response(stringOrStream: \Amp\File\read($configFile));
			}
		} catch (FilesystemException $e) {
			if (!str_contains($e->getMessage(), "No such file or directory")) {
				throw $e;
			}
		}

		return new Response(Status::NOT_FOUND);
	}

	public function writeConfigFile(Request $request) {
		$args = $request->getAttribute(Router::class);
		$guid = $args["guid"] ?? 0;
		$form = parseForm($request);
		$content = $form->getValue("content");

		$configFile = $this->fetchConfigFilePath($guid);
		if ($configFile != "") {
			try {
				\Amp\File\write($configFile, $content);
				return json_response([]);
			} catch (FilesystemException $e) {
				return json_response(["error" => $e->getMessage()]);
			}
		}

		return json_response(["error" => "No config file for mod $guid"], Status::NOT_FOUND);
	}
}

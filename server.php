<?php

namespace ValheimServerUI;

use Amp\ByteStream\ResourceOutputStream;
use Amp\CancellationTokenSource;
use Amp\File\Sync\AsyncFileKeyedMutex;
use Amp\Http\Server\ErrorHandler;
use Amp\Http\Server\HttpServer;
use Amp\Http\Server\Middleware;
use Amp\Http\Server\Request;
use Amp\Http\Server\RequestHandler\CallableRequestHandler;
use Amp\Http\Server\Response;
use Amp\Http\Server\Router;
use Amp\Http\Server\Session\Driver;
use Amp\Http\Server\Session\FileStorage;
use Amp\Http\Server\Session\Session;
use Amp\Http\Server\Session\SessionMiddleware;
use Amp\Http\Server\StaticContent\DocumentRoot;
use Amp\Http\Status;
use Amp\Log\ConsoleFormatter;
use Amp\Log\StreamHandler;
use Amp\Serialization\NativeSerializer;
use Amp\Socket;
use Amp\Websocket\Server\Websocket;
use League\CLImate\CLImate;
use Monolog\Logger;
use Psr\Log\LogLevel;
use ValheimServerUI\Proto\ServerConfig;
use function Amp\coroutine;

if (is_dir(__DIR__ . "/proto-out")) {
	include __DIR__ . "/protoc/protoc-regen.php";
}

require __DIR__ . '/vendor/autoload.php';

$climate = new CLImate();
$args = $climate->arguments;
$args->add([
	'port' => [
		'prefix'       => 'p',
		'longPrefix'   => 'port',
		'description'  => 'The port to listen on (ignored if --listen is specified)',
		'castTo'       => 'int',
		'defaultValue' => 80,
	],
	'listen' => [
		'prefix'      => 'l',
		'longPrefix'  => 'listen',
		'description' => 'Address to listen on, defaults to 0.0.0.0:<port> and [::]:<port>',
	],
	'log-level' => [
		'prefix'       => 'v',
		'longPrefix'   => 'verbosity',
		'description'  => 'Verbosity level (debug, info, warning or error)',
		'defaultValue' => 'debug',
	],
	'socket-address' => [
		'prefix'      => 's',
		'longPrefix'  => 'socket',
		'description' => 'Address, including port to look at for a valheim server',
		'required'    => true,
	],
	'certificate' => [
		'prefix'      => 'c',
		'longPrefix'  => 'certificate',
		'description' => 'Path to a .pem certificate file with (or without, then provide --key) key information for TLS encryption',
	],
	'skip-db-cache' => [
		'longPrefix'  => 'skip-db-cache',
		'description' => 'Start without reading cached server configuration from database',
	],
	'key' => [
		'prefix'      => 'k',
		'longPrefix'  => 'key',
		'description' => 'Path to a .pem key file for TLS encryption',
	],
	'baseurl' => [
		'prefix'      => 'b',
		'longPrefix'  => 'base',
		'description' => 'Basepath for URLs',
	],
	'help' => [
		'longPrefix'  => 'help',
		'description' => 'Prints a usage statement',
		'noValue'     => true,
	],
]);
try {
	$args->parse();
} catch (\InvalidArgumentException $e) {
	if (!$args->get("help")) {
		echo "Error: {$e->getMessage()}";
		$climate->usage();
		exit(1);
	}
}

$logLevels = (new \ReflectionClass(LogLevel::class))->getConstants();
if (!in_array($args->get("log-level"), $logLevels)) {
	echo "Error: Invalid log level {$args->get("log-level")}";
	$climate->usage();
	exit(1);
}

if ($args->get("help")) {
	$climate->usage();
	exit(0);
}

$logHandler = new StreamHandler(new ResourceOutputStream(\STDOUT));
$formatter = new ConsoleFormatter;
$formatter->includeStacktraces();
$logHandler->setFormatter($formatter);
$logHandler->setLevel($args->get("log-level"));
$logger = new Logger('server');
$logger->pushHandler($logHandler);

$db = new \SQLite3("database.sqlite");
$db->exec(file_get_contents("database.sql"));

$serverState = new ServerState;

const CONFIG_DB_KEYS = ['serverName', 'pluginsPath', 'patchersPath', 'configPath', 'savePath'];

$serverConfig = new ServerConfig;
$neededDefaults = 5;
$defaults = $db->query("SELECT key, value FROM keys WHERE key IN ('" . implode("', '", CONFIG_DB_KEYS) . "')");
while ($row = $defaults->fetchArray()) {
	$serverConfig->{"set" . ucfirst($row["key"])}($row["value"]);
	--$neededDefaults;
}

$socketPath = $args->get("socket-address");
$valheimSocket = new ValheimSocket($logger, $socketPath, $serverState);

$injector = new \Auryn\Injector(new \Auryn\CachingReflector);

$firstConnection = true;
$connectionInit = function () use (&$serverManager, $serverState, $valheimSocket, $logger, $injector, $db, &$firstConnection) {
	$serverState->readyFuture->await();
	if ($firstConnection) {
		$logger->info("Successfully read server state from Valheim server - " . ($serverManager ? "re" : "") . "initializing webserver");
		$firstConnection = false;
	}

	$existingStateWatchers = $serverManager->stateWatchers ?? [];
	$serverManager = ServerManager::init($serverState, $valheimSocket, $logger, $db);
	$serverManager->stateWatchers = $existingStateWatchers;
	$injector->share($serverManager);

	$stmt = $db->prepare("INSERT OR REPLACE INTO keys (key, value) VALUES (:key, :val)");
	foreach (CONFIG_DB_KEYS as $key) {
		$stmt->bindValue("key", $key);
		$stmt->bindValue("val", $serverState->serverConfig->{"get" . ucfirst($key)}());
		$stmt->execute();
	}
};

$serverState->connectionLossWatchers[] = fn() => coroutine($connectionInit);

if ($neededDefaults > 0) {
	$cancelSignal = new CancellationTokenSource;
	if (\Amp\Future\any([coroutine(fn() => \Amp\trapSignal([\SIGINT, \SIGTERM], true, $cancelSignal->getToken())), $serverState->readyFuture]) !== null) {
		exit;
	}
	$cancelSignal->cancel();
	$connectionInit();
} else {
	$serverState->serverConfig = $serverConfig;
	$logger->info("Initialized '{$serverConfig->getServerName()}' with existing server state");
	$serverManager = ServerManager::init($serverState, $valheimSocket, $logger, $db);
}

$serverContext = new Socket\BindContext;

if ($args->get("certificate") != "") {
	$serverContext = $serverContext->withTlsContext((new Socket\ServerTlsContext)->withDefaultCertificate(new Socket\Certificate($args->get("certificate"), $args->get("key"))));
}

$listenAddresses = $args->defined("listen") ? $args->getArray("listen") : ["0.0.0.0:" . $args->get("port"), "[::]:" . $args->get("port")];
$servers = array_map(fn($s) => Socket\Server::listen($s, $serverContext), $listenAddresses);

$basePath = $args->get("baseurl");
if ($basePath != "" && $basePath[0] != "/") {
	$basePath = "/$basePath";
}

$injector->share(new PeriodicRestarter($db, $serverManager, $valheimSocket));
$injector->share($valheimSocket);
$injector->share($serverState);
$injector->share($db);
$injector->share($serverManager);
$injector->alias(ServerManager::class, get_class($serverManager));
$injector->delegate(Tpl::class, function () use ($serverState, $basePath) {
	$tpl = new Tpl;
	$tpl->set("serverState", $serverState);
	$tpl->set("basePath", $basePath);
	$tpl->set("starttime", microtime(true));
	return $tpl;
});

$routePermissionMap = [];

function requestCallable(callable $callable, ?Permission $permission = null) {
	return function (Request $request) use ($callable, $permission) {
		global $injector, $db, $routePermissionMap, $basePath;
		/** @var Session $session */
		$session = $request->getAttribute(Session::class);
		$session->read();

		if (!$permissions = PermissionSet::read($db, $session->get("user_id"), $routePermissionMap)) {
			$session->open();
			$session->destroy();

			$permissions = PermissionSet::read($db, null, $routePermissionMap);
		}

		$tpl = $injector->make(Tpl::class);
		$tpl->set("username", $session->get("username"));
		$tpl->set("allowsRoute", $permissions->allowsRoute(...));

		if ($permission && !$permissions->allows($permission)) {
			$tpl->load(__DIR__ . "/templates/ErrorPage.php");
			$tpl->set("title", "403 Forbidden");
			$tpl->set("message", "This operation is not permitted.");
			return $tpl->render(Status::FORBIDDEN);
		}

		try {
			/** @var Response $response */
			$response = $injector->execute($callable, [":request" => $request, ":tpl" => $tpl, ":session" => $session, ":permissions" => $permissions]);
			if (($location = (string) $response->getHeader("location")) && $location[0] == "/") {
				$response->setHeader("location", "$basePath$location");
			}
			return $response;
		} catch (ValheimSocketUnreachableException) {
			$tpl->load(__DIR__ . "/templates/ErrorUnreachable.php");
			return $tpl->render(Status::INTERNAL_SERVER_ERROR);
		}
	};
}

$router = new Router;
$router->setFallback(new DocumentRoot(__DIR__ . "/public"));

function addRoute(string $method, string $uri, callable $callable, ?Permission $permission = null) {
	global $router, $routePermissionMap;

	if ($method == "GET" && $permission) {
		$routePermissionMap[ltrim($uri, "/")] = $permission;
	}

	$router->addRoute($method, $uri, new CallableRequestHandler(requestCallable($callable, $permission)));
}

function json_response($data, int $status = Status::OK) {
	return new Response(isset($data["error"]) ? 418 : $status, ["Content-Type" => "application/json"], json_encode($data));
}

$main = $injector->make(Responder\Main::class);
addRoute("GET", "/", $main->show(...));
addRoute("POST", "/AdminCreation", $main->createAdmin(...));

$login = $injector->make(Responder\Login::class);
addRoute("GET", "/Login", $login->show(...));
addRoute("POST", "/Login", $login->loginUser(...));
addRoute("GET", "/ChangePassword", $login->changePassword(...));
addRoute("POST", "/ChangePassword", $login->changePassword(...));

$logout = $injector->make(Responder\Logout::class);
addRoute("GET", "/Logout", $logout->logoutUser(...));

$playerListing = $injector->make(Responder\PlayerListing::class);
addRoute("GET", "/players", $playerListing->show(...), Permission::View_Players);

$onlinePlayers = $injector->make(Responder\OnlinePlayers::class);
addRoute("GET", "/players/online", $onlinePlayers->show(...), Permission::Manage_Players);
addRoute("POST", "/players/sendmessage", $onlinePlayers->sendMessage(...), Permission::Manage_Players);
addRoute("POST", "/players/kick", $onlinePlayers->kick(...), Permission::Manage_Players);

$modListing = $injector->make(Responder\ModListing::class);
addRoute("GET", "/modlist", $modListing->show(...), Permission::View_Mods);

$permissions = $injector->make(Responder\Permissions::class);
addRoute("GET", "/permissions", $permissions->show(...), Permission::Admin);
addRoute("POST", "/permissions/new", $permissions->createGroup(...), Permission::Admin);
addRoute("POST", "/permissions/delete", $permissions->deleteGroup(...), Permission::Admin);
addRoute("GET", "/permissions/{group_id}", $permissions->show(...), Permission::Admin);
addRoute("POST", "/permissions/{group_id}", $permissions->updateGroup(...), Permission::Admin);

$users = $injector->make(Responder\Users::class);
addRoute("GET", "/users", $users->show(...), Permission::View_Users);
addRoute("PUT", "/users", $users->createUser(...), Permission::Manage_Users);
addRoute("DELETE", "/users/{user_id}", $users->deleteUser(...), Permission::Manage_Users);
addRoute("POST", "/users/{user_id}/resetpassword", $users->resetPassword(...), Permission::Manage_Users);
addRoute("PUT", "/users/{user_id}/group", $users->setGroup(...), Permission::Manage_Users);
addRoute("PUT", "/users/{user_id}/steamID", $users->updateSteamId(...), Permission::Manage_Users);

$configFile = $injector->make(Responder\ConfigFile::class);
addRoute("GET", "/ConfigFile/{guid}", $configFile->fetchFileContent(...), Permission::Manage_Mods);
addRoute("PUT", "/ConfigFile/{guid}", $configFile->writeConfigFile(...), Permission::Manage_Mods);

$steamIdFiles = $injector->make(Responder\SteamIDFile::class);
addRoute("GET", "/steamidlists", $steamIdFiles->show(...), Permission::View_Lists);
addRoute("POST", "/steamidlists", $steamIdFiles->writeListFiles(...), Permission::Manage_Lists);

$settings = $injector->make(Responder\Settings::class);
addRoute("GET", "/settings", $settings->show(...));
addRoute("POST", "/settings/ChangePassword", $settings->changePassword(...));

$serverStats = $injector->make(Responder\Server::class);
addRoute("GET", "/server", $serverStats->show(...), Permission::View_Server);
addRoute("POST", "/server/restart/safe", $serverStats->restartServerSafe(...), Permission::Manage_Server);
addRoute("POST", "/server/restart/hard", $serverStats->restartServerHard(...), Permission::Manage_Server);
addRoute("POST", "/server/restart/setInterval", $serverStats->updateRestartInterval(...), Permission::Manage_Server);
addRoute("POST", "/server/maintenance/enable", $serverStats->enableMaintenance(...), Permission::Manage_Server);
addRoute("POST", "/server/maintenance/disable", $serverStats->disableMaintenance(...), Permission::Manage_Server);
addRoute("POST", "/server/save", $serverStats->saveWorld(...), Permission::Manage_Server);
$router->addRoute("GET", "/server/livestats", new Websocket($serverStats));

$logs = $injector->make(Responder\Log::class);
$router->addRoute("GET", "/logs/live", new Websocket($logs));

$sessions = new SessionMiddleware(new Driver(new AsyncFileKeyedMutex(__DIR__ . "/sessions/%s.lock"), new FileStorage(__DIR__ . "/sessions", "sess-", serializer: new NativeSerializer, sessionLifetime: 86400)));
$server = new HttpServer($servers, Middleware\stack($router, $sessions), $logger);

$server->setErrorHandler(new class implements ErrorHandler {
	public function handleError(int $statusCode, string $reason = null, Request $request = null): Response {
		return requestCallable(function (Request $request, Tpl $tpl) use ($statusCode, $reason) {
			$tpl->load(__DIR__ . "/templates/ErrorPage.php");
			$tpl->set("title", "$statusCode $reason");
			$tpl->set("message", $statusCode === Status::NOT_FOUND ? "Page not found" : "Internal server error");
			return $tpl->render($statusCode);
		})($request);
	}
});

$server->start();

if (!$db->query("SELECT id FROM user LIMIT 1")->fetchArray()) {
	$logger->warning("Server has successfully started. Open the webinterface in the browser to create your first admin account.");
}

$signal = \Amp\trapSignal([\SIGINT, \SIGTERM]);
$logger->info(\sprintf("Received signal %d, stopping HTTP server", $signal));
$server->stop();
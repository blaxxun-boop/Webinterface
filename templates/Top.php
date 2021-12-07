<?php

/**
 * @var callable $allowsRoute
 * @var \ValheimServerUI\ServerState $serverState
 */
$TOP_NAVIGATION = [
	"Server" => [
		"Server Dashboard" => "server",
	],
	"Players" => [
		"Overview" => "players",
		"Manage" => "players/online",
	],
	"Lists" => [
		"SteamID Lists" => "steamidlists",
		"Mod List" => "modlist",
	],
	"Admin" => [
		"Users" => "users",
		"Permissions" => "permissions",
	],
];

?><body class="nojs"<?= isset($body_id) ? ' id="' . $body_id . '"' : '' ?>>
<!-- HEADER -->
<header id="header">
    <div id="header-inner">
        <!-- MOBILE MENU BUTTON -->
        <input class="nodisplay" id="mobile-menu-switch-checkbox" type="checkbox">
        <label class="mobileOnly" id="mobile-menu-switch" for="mobile-menu-switch-checkbox">
            <i class="fa fa-fw fa-navicon fa-lg"></i>
        </label>
        <!-- /MOBILE MENU BUTTON -->

        <!-- MENU -->
        <nav id="menu" role="navigation">
			<!-- LOGO -->
			<a href="./" id="logo"><?=$serverState->serverConfig->getServerName()?></a>
			<!-- /LOGO -->

			<?php if (empty($skipNav)): ?>
				<?php foreach ($TOP_NAVIGATION as $key => $section): $section = array_filter($section, $allowsRoute(...)); ?>
					<?php if ($section): ?>
						<span class="menu-drop">
							<span class="menu-drop-item"><?=$key?></span>

							<ul class="menu-drop-list">
								<?php foreach ($section as $name => $uri): ?>
									<li class="menu-drop-list-item"><a class="menu-drop-list-item-link" href="<?=$uri?>"><?=$name?></a></li>
								<?php endforeach; ?>
							</ul>
						</span>
					<?php endif; ?>
				<?php endforeach; ?>

				<span class="menu-drop menu-drop-right">
					<?php if (empty($username)): ?>
						<a class="menu-drop-item" href="Login">Login</a>
					<?php else: ?>
						<span class="menu-drop-item"><?=$username?></span>

						<ul class="menu-drop-list">
							<li class="menu-drop-list-item"><a class="menu-drop-list-item-link" href="settings">Settings</a></li>
							<li class="menu-drop-list-item"><a class="menu-drop-list-item-link" href="Logout">Logout</a></li>
						</ul>
					<?php endif; ?>
				</span>
            <?php endif; ?>
            <div class="clear"></div>
        </nav>
        <!-- /MENU -->

    </div>
</header>

<div id="mainHolder">

## Requirements

- [ServerCharacters](https://valheim.thunderstore.io/package/Smoothbrain/ServerCharacters) running as a mod
- PHP 8.1
- sqlite PHP extension
- posix PHP extension
- pcntl PHP extension
- mbstring PHP extension
- optional: openssl PHP extension for https
- optional: dbus-user-session for user systemd services

On a typical Ubuntu installation all dependencies are installed via:
```
sudo apt-get install composer php8.1-cli php8.1-mbstring php8.1-sqlite3 dbus-user-session
```

## Installation

Use [composer](https://getcomposer.org/download/) to install dependencies, i.e. execute `php8.1 $(which composer) install` in the repository root. Then execute `php8.1 protoc/protoc-regen.php` in the repository root to regenerate the used protobuf definitions.

## Running the webinterface

First find the port your web API listens on by checking your ServerCharacters config.

The command to run the server is:
```
php8.1 server.php -l <address the webinterface listens on> -s <address the Valheim server listens on>
```
As an example, to have the webinterface reachable under port 9999 of your server, with Valheim listening on port 5982: `php8.1 server.php -l 0.0.0.0:9999 -s 127.0.0.1:5982`.

Once the webinterface is running, open it in your browser to create the first admin account.

## Setting up systemd

Currently the restart and log functionality of the webinterface only supports Valheim running as a systemd service. The webinterface will figure out how to access the Valheim service, but it needs to have permissions to do so.

If the webinterface is running as root, then this works out of the box.

If the webinterface does not run as root, it must either have unrestricted `sudo` access. Or the Valheim service and the webinterface must run under the same user, and the Valheim service run as a systemd **user** service.

Let's show setting up the valheim server and webinterface as user services, by assuming:
 - the Valheim servers user id is _1001_ and the user name is _testuser_
 - the webinterface is stored at /home/testuser/webinterface
 - the valheim installation resides under /home/testuser/valheim

Example service configurations:

`/home/testuser/.config/systemd/user/valheim.service`
```
[Unit]
Description=Valheim
Wants=sockets.target
After=sockets.target

[Service]
Type=simple
Restart=on-failure
RestartSec=10
WorkingDirectory=/home/testuser/valheim
ExecStart=/home/testuser/valheim/start_server_bepinex.sh

[Install]
WantedBy=default.target
```

`/home/testuser/.config/systemd/user/webinterface.service`
```
[Unit]
Description=Valheim webinterface
After=valheim.service

[Service]
Type=simple
Restart=on-failure
RestartSec=10
WorkingDirectory=/home/testuser/webinterface
ExecStart=php8.1 server.php -l 0.0.0.0:9999 -s 127.0.0.1:5982

[Install]
WantedBy=default.target
```

To enable it, ensure the `dbus-user-session` apt package is installed. Then start a systemd session for user `1001`:
```
systemctl start user@1001.service
loginctl enable-linger testuser
sudo -u testuser XDG_RUNTIME_DIR=/run/user/1001 systemctl enable --user valheim.service webinterface.service
```

To start the webinterface and valheim use:
```
sudo -u testuser XDG_RUNTIME_DIR=/run/user/1001 systemctl start --user valheim.service webinterface.service
```

## HTTPS

The webinterface provides a `--certificate /path/to/fullchain.pem` option, as well as a `--key /path/to/private.key` (if the key is not contained in the .pem) option.

Specifying these options is enough to enable https.

## Using as endpoint of a reverse proxy

Required apache modules: proxy_http, proxy_wstunnel

Given that the webinterface makes use of websockets, make sure to also proxy the websockets connections. With apache this amounts to:
```
ProxyPass / http://localhost:9999
RewriteEngine on
RewriteCond %{HTTP:Upgrade} websocket [NC]
RewriteCond %{HTTP:Connection} upgrade [NC]
RewriteRule ^/(.*) "ws://localhost:9999/$1" [P,L]
```

If it is desired to have the interface hosted on a subpath of the domain root, e.g. under `/webinterface`, `--base webinterface` must be passed as option to the server and the rules will look like:
```
ProxyPass /webinterface http://localhost:9999
RewriteEngine on
RewriteCond %{HTTP:Upgrade} websocket [NC]
RewriteCond %{HTTP:Connection} upgrade [NC]
RewriteRule ^/webinterface/(.*) "ws://localhost:9999/$1" [P,L]
```


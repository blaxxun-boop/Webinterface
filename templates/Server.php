<?php
/**
 * @var int $cpuNumber
 * @var int $maxMemory
 * @var int[] $memoryUsed
 * @var int[][] $loadAvgs
 * @var bool $maintenanceActive
 * @var int $maintenanceStartTime
 * @var \ValheimServerUI\ServiceStatus $serviceState
 * @var float $automaticRestartInterval
 * @var int $automaticRestartNext
 * @var string $logHistory
 */
?><!DOCTYPE html>
<html id="html_main">
<?php include __DIR__ . '/Head.php'; ?>
<?php include __DIR__ . '/Top.php'; ?>

<div id="main" role="main">
    <div class="page-main" style="display: flex; gap: 50px; flex-wrap: wrap;">
		<style>
			.bigcard { width: auto; text-align: center; }
			svg { display: block; }
			.legend { width: 2em; height: 2px; display: inline-block; margin-bottom: 4px; }
		</style>
		<div class="bigcard">
			<h1>System Load</h1>
			<svg id="sysstat_loadavg"></svg>
			<div style="background: #3020ca;" class="legend"></div> <span style="color: #3020ca">1 min average</span>
			<div style="background: #ca3020;" class="legend"></div> <span style="color: #ca3020">5 min average</span>
			<div style="background: #baca30;" class="legend"></div> <span style="color: #baca30">15 min average</span>
		</div>
		<div class="bigcard">
			<h1>System Memory</h1>
			<svg id="sysstat_memory"></svg>
			<div style="background: #3020ca;" class="legend"></div> <span style="color: #3020ca">Used memory</span>
		</div>
		<div class="bigcard">
			<h1>System Status</h1>
			<?php
				$img = ["active" => "on", "activating" => "starting", "deactivating" => "starting"][$serviceState->state] ?? "off";
				echo "Server status: <img id='serviceStateImg' src='img/$img.svg' title='{$serviceState->state}' width='16' height='16'>";
				$formatter = new Wookieb\RelativeDate\Formatters\BasicFormatter();
				$calculator = Wookieb\RelativeDate\Calculators\TimeAgoDateDiffCalculator::full();
				echo "<br><span id='serviceStateChange' data-timestamp='{$serviceState->stateChange}' title='" . date("Y-m-d H:i:s", $serviceState->stateChange) . "'>since " . $formatter->format($calculator->compute((new DateTime)->setTimestamp($serviceState->stateChange), new DateTime()));
				echo "</span>";
				echo "<br>";
				$img = $automaticRestartInterval ? "on" : "off";
				echo "Automated restarts: <img src='img/$img.svg' title='" . ($automaticRestartInterval ? "Server restarts every $automaticRestartInterval hours" : "Server does not restart automatically") . "' width='16' height='16'>"
			?>
			<?=$maintenanceActive ? "<br><br>Maintenance active" : ""?>
			<?php if (!$maintenanceActive && $maintenanceStartTime != 0): ?>
				<br>
				Maintenance begins: <?=date("H:i:s", $maintenanceStartTime)?>
			<?php endif; ?>
		</div>
		<div class="bigcard" style="width: 100%; min-height: 250px; max-width: 592px; display: flex; flex-direction: column; ">
			<h1>System Actions</h1>

			<form method="post" style="flex-grow: 1;">
				<fieldset style="height: 100%; box-sizing: border-box;">
					<legend style="text-align: left;">
						<select id="sysactionselector">
							<option value="sysaction_saferestart">Safe restart</option>
							<option value="sysaction_hardrestart">Hard restart</option>
							<option value="sysaction_disablemaint">Disable maintenance</option>
							<option value="sysaction_enablemaint">Enable maintenance</option>
							<option value="sysaction_schedulerestart">Schedule automated restarts</option>
							<option value="sysaction_saveworld">Save the world</option>
						</select>
					</legend>
					<div id="sysaction_saferestart">
						Enables maintenance mode, waits until everything is saved and then restarts. Disables maintenance mode automatically afterwards.
						<br><br>
						<input type="submit" value="Safe restart" formaction="server/restart/safe">
					</div>
					<div id="sysaction_hardrestart">
						Restarts the server immediately. Will probably lead to loss of data.
						<br><br>
						<input type="submit" value="Hard restart" formaction="server/restart/hard">
					</div>
					<div id="sysaction_disablemaint">
						Disables the maintenance mode.
						<br><br>
						<input type="submit" value="Disable maintenance" formaction="server/maintenance/disable">
					</div>
					<div id="sysaction_enablemaint">
						Enables the maintenance.
						<br><br>
						<input type="submit" value="Enable maintenance" formaction="server/maintenance/enable">
					</div>
					<div id="sysaction_schedulerestart">
						Schedules an automated restart to be executed periodically.<br>
						Only restarts if no players are online, otherwise the restart is retried every 5 minutes.
						<?php if ($automaticRestartInterval): ?>
						<br>
						The next restart check is scheduled for <?=date("Y-m-d H:i:s", $automaticRestartNext)?>.
						<?php endif; ?>
						<br><br>
						Every <input type="text" size="5" value="<?=$automaticRestartInterval?>" name="restartinterval"> hours <input type="submit" value="Set interval" formaction="server/restart/setInterval">
						<br>
						<small>Use 0 to disable automated restarting</small><br>
						<small>Decimal numerals are allowed</small>
					</div>
					<div id="sysaction_saveworld">
						Immediately saves the world and all characters that are currently connected.
						<br><br>
						<input type="submit" value="Save" onclick="saveWorld(); return false;">
					</div>
					<script>
						document.addEventListener('DOMContentLoaded', function () {
							const sysactionselector = document.getElementById("sysactionselector");
							(sysactionselector.onchange = function () {
								document.querySelectorAll('[id^=\'sysaction_\']').forEach(e => e.style.display = 'none');
								document.getElementById(selectboxValue(sysactionselector)).style.display = "block";
							})();
						});
					</script>
				</fieldset>
			</form>
		</div>
		<div class="bigcard" style="width: 100%;">
			<h1>Logs</h1>
			<div id="loglines" style="white-space: pre-wrap; text-align: left;"><?=htmlspecialchars($logHistory)?></div>
		</div>
    </div>
</div>

<div id="dialogWorldSaved" style="display: none">
	World save done in <span id="dialogWorldSavedSeconds"></span> seconds.
</div>
<script>
	function saveWorld() {
		displayGlobalLoadingDialog("Saving the world ... This might take a while, but navigating away from this tab or closing it will not interrupt the save process.");
		fetchOrErrorDialog("server/save", { method: "POST" }, "Got error while saving world", data => {
			displayGlobalOverlay("dialogWorldSaved");
			document.getElementById("dialogWorldSavedSeconds").innerText = data.duration;
		});
	}

	(function() {
		function calculateRelativeTime(timestamp) {
			let timeDiff = timestamp - Math.floor(Date.now() / 1000);
			const timeFormat = new Intl.RelativeTimeFormat('en-US');
			if (timeDiff > -60) {
				return "a few seconds ago";
			} else if (timeDiff > -3600) {
				return timeFormat.format(Math.ceil(timeDiff / 60), 'minutes');
			} else if (timeDiff > -86400) {
				return timeFormat.format(Math.ceil(timeDiff / 3600), 'hours');
			} else {
				return timeFormat.format(Math.ceil(timeDiff / 86400), 'days');
			}
		}

		function displayServiceState(state) {
			let img = document.getElementById('serviceStateImg');
			img.title = state.state;
			img.src = `img/${{active: "on", activating: "starting", deactivating: "starting"}[state.state] || "off"}.svg`;

			let time = document.getElementById('serviceStateChange');
			time.title = new Date(state.stateChange * 1000).toLocaleString("en-ca-u-hc-h24").replace(/,/, "");
			time.innerText = "since " + calculateRelativeTime(state.stateChange);
			time.setAttribute("data-timestamp", state.stateChange)
		}

		setInterval(function () {
			let time = document.getElementById('serviceStateChange');
			time.innerText = "since " + calculateRelativeTime(time.getAttribute("data-timestamp"));
		}, 1000);

		function renderGraph(svg, maxValue, inputs, numberRenderer) {
			var svgWidth = 560;
			var svgHeight = 250;
			svg.setAttribute("width", svgWidth);
			svg.setAttribute("height", svgHeight);
			var heightOffset = 5;
			var contentOffset = 60;
			var contentWidth = svgWidth - contentOffset;
			var contentHeight = svgHeight - 19 - heightOffset;
			svg.setAttribute("viewBox", `0 0 ${svgWidth} ${svgHeight}`);
			var html = `
				<style>text { font: 13px sans-serif; fill: #666; }</style>
				<text x="${contentOffset + 3}" y="${svgHeight - 4}">30 min ago</text>
				<text x="${contentOffset + contentWidth / 2}" y="${svgHeight - 4}" text-anchor="middle">15 min ago</text>
				<text x="${svgWidth - 3}" y="${svgHeight - 4}" text-anchor="end">now</text>
				<line x1="${contentOffset}" x2="${svgWidth}" y1="${contentHeight + heightOffset}" y2="${contentHeight + heightOffset}" stroke="black" stroke-width=".5" />
				<line x1="${contentOffset}" x2="${contentOffset}" y1="${heightOffset}" y2="${contentHeight + heightOffset}" stroke="black" stroke-width=".5" />
				<text x="${contentOffset - 3}" y="13" text-anchor="end">${numberRenderer(maxValue)}</text>
`;
			for (var i = 1; i < 5; ++i) {
				html += `<text x="${contentOffset - 3}" y="${contentHeight - contentHeight / 5 * i + 6.5 + heightOffset}"  text-anchor="end">${numberRenderer(i * maxValue / 5)}</text>`;
			}
			var granulatity = 360;
			for (var input of inputs) {
				var points = [];
				i = granulatity;
				for (var val of input.data.reverse()) {
					points.push(`${contentOffset + --i/(granulatity - 1) * (contentWidth - 0.5)} ${(1 - val / maxValue) * contentHeight + heightOffset}`);
				}
				html += `<path d="M ${points.join(" L ")}" stroke="${input.color}" fill="none" stroke-width="1.5" />`;
			}
			svg.innerHTML = html;
		}

		function renderGraphs(inputs) {
			var remappedAvgs = inputs.loadAvg.length ? inputs.loadAvg[0].map((_, i) => inputs.loadAvg.map(e => e[i])) : [[], [], []];
			renderGraph(document.getElementById("sysstat_loadavg"), Math.max(<?=$cpuNumber?>, ...inputs.loadAvg.flat()), [
				{ data: remappedAvgs[0], color: "#3020ca" },
				{ data: remappedAvgs[1], color: "#ca3020" },
				{ data: remappedAvgs[2], color: "#baca30" },
			], function (number) {
				return Number.parseFloat(number).toFixed(2);
			});
			renderGraph(document.getElementById("sysstat_memory"), <?=$maxMemory?>, [
				{ data: inputs.memory, color: "#3020ca" },
			], function (number) {
				var magnitude = number < 1 ? 0 : Math.floor(Math.log2(number) / 10);
				return parseFloat(Number.parseFloat(number / Math.pow(1024, magnitude)).toPrecision(3)) + " " + ['Bytes', 'KiB', 'MiB', 'GiB', 'TiB'][magnitude];
			});
		}

		renderGraphs({ memory: <?=json_encode(array_values($memoryUsed))?>, loadAvg: <?=json_encode(array_values($loadAvgs))?>});

		var reconnectInterval;
		function connectWs() {
			var url = new URL('server/livestats', window.location.href);
			url.protocol = url.protocol.replace('http', 'ws');
			var ws = new WebSocket(url.href);
			ws.onmessage = function (msg) {
				let data = JSON.parse(msg.data)
				if (data.loadAvg || data.memory) {
					renderGraphs(data);
				}
				if (data.serviceState) {
					displayServiceState(data.serviceState);
				}
			};
			ws.onclose = function () {
				if (reconnectInterval) {
					clearInterval(reconnectInterval);
					reconnectInterval = null;
				}
				reconnectInterval = setInterval(connectWs, 1000);
			};
			ws.onopen = function () {
				clearInterval(reconnectInterval);
				reconnectInterval = null;
			};
		}
		reconnectInterval = setInterval(connectWs, 1000);
		connectWs();
	})();

	(function() {
		var reconnectInterval;
		let log = document.getElementById("loglines");
		function connectWs() {
			var url = new URL('logs/live', window.location.href);
			url.protocol = url.protocol.replace('http', 'ws');
			var ws = new WebSocket(url.href);
			ws.onmessage = function (msg) {
				log.innerText = msg.data + log.innerText;
			};
			ws.onclose = function () {
				if (reconnectInterval) {
					clearInterval(reconnectInterval);
					reconnectInterval = null;
				}
				reconnectInterval = setInterval(connectWs, 1000);
			};
			ws.onopen = function () {
				clearInterval(reconnectInterval);
				reconnectInterval = null;
			};
		}
		reconnectInterval = setInterval(connectWs, 1000);
		connectWs();
	})();
</script>

<div class="clear"></div>
<?php include __DIR__ . '/Footer.php'; ?>
</html>

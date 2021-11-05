<?php
/** @var float $starttime */
?>
		</div>

		<footer id="footer" role="contentinfo"><div>
			<div id="footer-time">
				Generated in <?=round((microtime(1) - $starttime) * 1000, 1)?> ms
			</div>
		</div></footer>

		<div id="globalOverlay">
			<div id="globalDialogContainer">
				<div id="globalDialogClose">
					Close
				</div>
				<div id="globalDialog">

				</div>
			</div>
		</div>

		<div id="globalLoadingDialog" style="display: none">
			<div id="globalLoadingDialogText"></div>
			... Loading ...
		</div>

		<div id="globalErrorDialog" style="display: none">
			<div id="globalErrorDialogDescription"></div>
			<br>
			<br>
			<div id="globalErrorDialogMessage"></div>
		</div>

		<script type="text/javascript">
			var globalOverlayId = null;
			var globalOverlayRefreshOnDismiss = false;
			document.querySelectorAll('.nojs').forEach(el => el.classList.remove('nojs'));
			document.getElementById('globalDialog').onclick = function (e) { e.stopPropagation(); };
			document.getElementById('globalDialogClose').onclick = document.getElementById('globalOverlay').onclick = function () {
				if (globalOverlayRefreshOnDismiss) {
					window.location.reload();
					document.getElementById('globalDialog').style.display = 'none';
					return;
				}

				document.getElementById('globalOverlay').style.display = 'none';
				if (globalOverlayId) {
					document.getElementById(globalOverlayId).append(...document.getElementById('globalDialog').childNodes);
				}
				globalOverlayId = null;
			};

			function displayGlobalOverlay(id) {
				if (globalOverlayId) {
					document.getElementById(globalOverlayId).append(...document.getElementById('globalDialog').childNodes);
				}
				globalOverlayId = id;
				document.getElementById('globalDialog').append(...document.getElementById(id).childNodes);
				document.getElementById('globalOverlay').setAttribute('data-activeDialog', id);
				document.getElementById('globalOverlay').style.display = "block";
			}

			function displayGlobalErrorDialog(text, errorline) {
				displayGlobalOverlay("globalErrorDialog");
				document.getElementById("globalErrorDialogDescription").innerText = text;
				document.getElementById("globalErrorDialogMessage").innerText = errorline;
			}

			function displayGlobalLoadingDialog(text) {
				displayGlobalOverlay("globalLoadingDialog");
				document.getElementById("globalLoadingDialogText").innerText = text;
			}

			function fetchOrErrorDialog(url, data, error, callback, raw = false) {
				fetch(url, data).then(r => raw ? r.status >= 400 ? Promise.resolve({ error: r.statusText }) : r.text() : r.json()).then(data => {
					if (data.error) {
						displayGlobalErrorDialog(error, data.error);
					} else {
						callback(data);
					}
				}).catch(e => displayGlobalErrorDialog(error, e));
			}

			function selectboxValue(select) {
				return select.options[select.selectedIndex].value;
			}
		</script>
	</body>

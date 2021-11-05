<?php
/**
 * @var string[][] $userList
 * @var string[][] $groupList
 * @var bool $canManage
 */
?><!DOCTYPE html>
<html id="html_main">
<?php include __DIR__ . '/Head.php'; ?>
<?php include __DIR__ . '/Top.php'; ?>

<div id="main" role="main">
	<div class="page-main">
		<table class="fancyTable sortable">
			<thead>
				<tr>
					<th class="textLeft">ID</th>
					<th class="textLeft">Name</th>
					<th class="textLeft">Permission</th>
					<th class="textRight">Last login</th>
					<th class="textRight">Steam ID</th>
					<th class="textRight nosort"></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($userList as $user): ?>
					<tr data-userid="<?=$user["id"]?>">
						<td><?=$user["id"]?></td>
						<td class="users_username"><?=htmlspecialchars($user["username"])?></td>
						<td>
							<?php if ($canManage): ?>
								<select onchange="setGroup(<?=$user["id"]?>, selectboxValue(this))">
									<?php foreach ($groupList as $group): ?>
										<option value='<?=$group["group_id"]?>' <?=$group["group_id"] == $user["group_id"] ? "selected" : "" ?>><?=htmlspecialchars($group["groupname"])?></option>
									<?php endforeach; ?>
								</select>
							<?php else: ?>
								<?=htmlspecialchars($groupList[$user["group_id"]]["groupname"])?>
							<?php endif; ?>
						</td>
						<?php
						if ($user["last_login"] == 0)
						{
							echo "<td class='textRight' data-num='0'>never</td>";
						}
						else
						{
							$formatter = new Wookieb\RelativeDate\Formatters\BasicFormatter();
							$calculator = Wookieb\RelativeDate\Calculators\TimeAgoDateDiffCalculator::full();
							echo "<td class='textRight' data-num='{$user["last_login"]}'>" . $formatter->format($calculator->compute((new DateTime)->setTimestamp($user["last_login"]), new DateTime())) . "</td>";
						}
						echo "<td class='textRight'><span class='user_steamID'>{$user["steam_id"]}</span>", $canManage ? " <img src='img/editicon.svg' alt='Edit ID' title='Edit ID' height='8' width='8' style='cursor: pointer' onclick='steamEditDialog({$user["id"]})'>" : "", "</td>";
						if ($canManage)
						{
							echo "<td class='textRight'><input type='button' value='Reset password' onclick='resetPassword({$user["id"]})'> ";
							echo "<input type='button' value='Delete' onclick='confirm(\"Confirm deletion of user \" + document.querySelector(\"tr[data-userid=\\\"{$user["id"]}\\\"] .users_username\").textContent) && deleteUser({$user["id"]})'></td>";
						}
						echo "</tr>";
						?>
					</tr>
				<?php endforeach; ?>
				<?php if ($canManage): ?>
					<tr class="sortfixed">
						<td></td>
						<td><input type="text" placeholder="New user" name="newUserUsername" id="newUserUsername"></td>
						<td>
							<select name="newUserGroup" id="newUserGroup">
								<?php foreach ($groupList as $group): ?>
									<option value='<?=$group["group_id"]?>' <?=$group["group_id"] == 0 ? "selected" : "" ?>><?=htmlspecialchars($group["groupname"])?></option>
								<?php endforeach; ?>
							</select>
						</td>
						<td></td>
						<td class='textRight'><input type="text" placeholder="Steam ID" name="newUserSteamId" id="newUserSteamId"></td>
						<td class='textRight'><input type='button' value='Create' onclick='createUser(document.getElementById("newUserUsername").value, selectboxValue(document.getElementById("newUserGroup")), document.getElementById("newUserSteamId").value)'></td>
					</tr>
				<?php endif; ?>
			</tbody>
		</table>
		<div id="dialogResetPassword" style="display: none">
			Successfully reset password of user <span id="dialogResetPasswordUser"></span>.<br>
			<br>
			New password is: <span id="dialogResetPasswordPassword"></span>
		</div>
		<div id="dialogDeleteUser" style="display: none">
			Deleted user <span id="dialogDeleteUserUser"></span>.
		</div>
		<div id="dialogUpdateGroup" style="display: none">
			Updated group for user <span id="dialogUpdateGroupUser"></span>.
		</div>
		<div id="dialogCreateUser" style="display: none">
			Created user <span id="dialogCreateUserUser"></span>.<br>
			<br>
			Password is: <span id="dialogCreateUserPassword"></span>
		</div>
		<div id="dialogUpdatedSteamID" style="display: none">
			Updated Steam ID for user <span id="dialogUpdatedSteamIDUser"></span>.
		</div>
		<div id="dialogUpdateSteamID" style="display: none">
			Change Steam ID for user <span id="dialogUpdateSteamIDUser"></span>
			<br>
			<br>
			<input type="text" id="dialogUpdateSteamIDinput"> <input type="submit" value="Save" onclick="steamIDUpdate()">
		</div>
		<script>
			var activeSteamIDUserId;
			function steamEditDialog(user_id) {
				activeSteamIDUserId = user_id;
				var name = document.querySelector("tr[data-userid='" + user_id + "'] .users_username").textContent;
				var steamId = document.querySelector("tr[data-userid='" + user_id + "'] .user_steamID").textContent;
				displayGlobalOverlay("dialogUpdateSteamID");
				document.getElementById("dialogUpdateSteamIDUser").innerText = name;
				document.getElementById("dialogUpdateSteamIDinput").value = steamId;
			}

			function steamIDUpdate() {
				var name = document.querySelector("tr[data-userid='" + activeSteamIDUserId + "'] .users_username").textContent;
				var steamId = document.getElementById("dialogUpdateSteamIDinput").value;
				displayGlobalLoadingDialog("Updating Steam ID for user " + name + " ...");
				var form = new FormData();
				form.append("steam_id", steamId);
				fetchOrErrorDialog("users/" + activeSteamIDUserId + "/steamID", { method: "PUT", body: form }, "Got error while updating Steam ID for user " + name, data => {
					displayGlobalOverlay("dialogUpdatedSteamID");
					document.getElementById("dialogUpdatedSteamIDUser").innerText = name;
					document.querySelector("tr[data-userid='" + activeSteamIDUserId + "'] .user_steamID").innerText = steamId;
				});
			}

			function resetPassword(user_id) {
				var name = document.querySelector("tr[data-userid='" + user_id + "'] .users_username").textContent;
				displayGlobalLoadingDialog("Requesting new password for user " + name + " ...");
				fetchOrErrorDialog("users/" + user_id + "/resetpassword", { method: "POST" }, "Got error while requesting new password for user " + name, data => {
					displayGlobalOverlay("dialogResetPassword");
					document.getElementById("dialogResetPasswordUser").innerText = name;
					document.getElementById("dialogResetPasswordPassword").innerText = data.password;
				});
			}

			function deleteUser(user_id) {
				var name = document.querySelector("tr[data-userid='" + user_id + "'] .users_username").textContent;
				displayGlobalLoadingDialog("Deleting user " + name + " ...");
				fetchOrErrorDialog("users/" + user_id, { method: "DELETE" }, "Got error while deleting user " + name, data => {
					displayGlobalOverlay("dialogDeleteUser");
					document.getElementById("dialogDeleteUserUser").innerText = name;

					globalOverlayRefreshOnDismiss = true;
				});
			}

			function setGroup(user_id, group_id) {
				var name = document.querySelector("tr[data-userid='" + user_id + "'] .users_username").textContent;
				displayGlobalLoadingDialog("Updating group of user " + name + " ...");
				var form = new FormData();
				form.append("group_id", group_id);
				fetchOrErrorDialog("users/" + user_id + "/group", { method: "PUT", body: form }, "Got error while updating group for user " + name, data => {
					displayGlobalOverlay("dialogUpdateGroup");
					document.getElementById("dialogUpdateGroupUser").innerText = name;
				});
			}

			function createUser(name, group_id, steam_id) {
				displayGlobalLoadingDialog("Creating user " + name + " ...");
				var form = new FormData();
				form.append("username", name);
				form.append("group_id", group_id);
				form.append("steam_id", steam_id);
				fetchOrErrorDialog("users", { method: "PUT", body: form }, "Got error while creating user " + name, data => {
					displayGlobalOverlay("dialogCreateUser");
					document.getElementById("dialogCreateUserUser").innerText = name;
					document.getElementById("dialogCreateUserPassword").innerText = data.password;

					globalOverlayRefreshOnDismiss = true;
				});
			}
		</script>
	</div>
</div>

<div class="clear"></div>
<?php include __DIR__ . '/Footer.php'; ?>
</html>

<!DOCTYPE html>
<html id="html_main">
<?php include __DIR__ . '/Head.php'; ?>
<?php include __DIR__ . '/Top.php'; ?>
<div id="main" role="main">
    <div class="page-main">
		<div>
			<label for="oldPassword">Old password</label><br>
			<input type="password" id="oldPassword" name="oldPassword"><br><br>
			<label for="newPassword">New password</label><br>
			<input type="password" id="newPassword" name="newPassword"><br><br>
			<label for="passwordCheck">Retype password</label><br>
			<input type="password" id="passwordCheck" name="passwordCheck">
			<br><br>
			<input type="button" value="Change" onclick="changePassword(document.getElementById('oldPassword'), document.getElementById('newPassword'), document.getElementById('passwordCheck'))">
		</div>
		<div id="dialogUpdatedPassword" style="display: none">
			Successfully changed password.
		</div>
		<script>
			function changePassword(oldPass, newPass, checkPass) {
				displayGlobalLoadingDialog("Changing password ...");
				var form = new FormData();
				form.append("oldPassword", oldPass.value);
				form.append("newPassword", newPass.value);
				form.append("passwordCheck", checkPass.value);
				oldPass.value = newPass.value = checkPass.value = "";
				fetchOrErrorDialog("settings/ChangePassword", { method: "POST", body: form }, "Got error while changing password", data => {
					displayGlobalOverlay("dialogUpdatedPassword");
				});
			}
		</script>
    </div>
</div>

<div class="clear"></div>
<?php include __DIR__ . '/Footer.php'; ?>
</html>

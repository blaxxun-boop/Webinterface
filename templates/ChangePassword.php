<?php
/** @var bool $passwordMismatch */
?><!DOCTYPE html>
<html id="html_main">
<?php include __DIR__ . '/Head.php'; ?>
<?php $skipNav = true; include __DIR__ . '/Top.php'; ?>
<div id="main" role="main">
    <div class="page-main">
        <div class="page-centered">
            <div class="title-page-centered">Please change your password.</div>
			<?php if ($passwordMismatch): ?>
				<div class="error" style="color: darkred; text-align: center; padding-bottom: 5px">Passwords did not match.</div>
			<?php endif; ?>
            <form action="ChangePassword" method="post">
                <div style="width: min-content; margin: auto">
                    <label for="username">New password</label><br>
					<input type="password" id="password" name="password"><br><br>
                    <label for="password">Retype password</label><br>
                    <input type="password" id="passwordCheck" name="passwordCheck">
                </div>
                <br>
                <input class="page-centered-button" type="submit" value="Change password">
            </form>
			<br><br>Your account has been flagged for a password change, because an admin reset your password or this is your first login.
        </div>
    </div>
</div>

<div class="clear"></div>
<?php include __DIR__ . '/Footer.php'; ?>
</html>

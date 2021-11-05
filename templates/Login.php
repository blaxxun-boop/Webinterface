<?php
/** @var bool $failedLogin */
?><!DOCTYPE html>
<html id="html_main">
<?php include __DIR__ . '/Head.php'; ?>
<?php include __DIR__ . '/Top.php'; ?>
<div id="main" role="main">
    <div class="page-main">
        <div class="page-centered">
            <div class="title-page-centered">Login to an existing account.</div>
			<?php if ($failedLogin): ?>
				<div class="error" style="color: darkred; text-align: center; padding-bottom: 5px">Login failed.</div>
			<?php endif; ?>
            <form action="" method="post">
                <div style="width: min-content; margin: auto">
                    <label for="username">Username</label><br>
                    <input type="text" id="username" name="username"><br><br>
                    <label for="password">Password</label><br>
                    <input type="password" id="password" name="password">
                </div>
                <br>
                <input class="page-centered-button" type="submit" value="Login">
            </form>
            <br><br>If you don't have an account yet, please ask an admin or moderator to create an account for you.
        </div>
    </div>
</div>

<div class="clear"></div>
<?php include __DIR__ . '/Footer.php'; ?>
</html>

<!DOCTYPE html>
<html id="html_main">
<?php include __DIR__ . '/Head.php'; ?>
<?php $skipNav = true; include __DIR__ . '/Top.php'; ?>
<div id="main" role="main">
    <div class="page-main">
        <div class="page-centered">
            <div class="title-page-centered">Initial setup, please create your first admin account.</div>
            <form action="AdminCreation" method="post">
                <div style="width: min-content; margin: auto">
                    <label for="username">Username</label><br>
                    <input type="text" id="username" name="username"><br><br>
                    <label for="password">Password</label><br>
                    <input type="password" id="password" name="password">
                </div>
                <br>
                <input class="page-centered-button" type="submit" value="Create Account">
            </form>
        </div>
    </div>
</div>

<div class="clear"></div>
<?php include __DIR__ . '/Footer.php'; ?>
</html>

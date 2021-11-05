<?php
/**
 * @var string $title
 * @var string $message
 */
?><!DOCTYPE html>
<html>
    <?php include __DIR__ . '/Head.php'; ?>
    <?php include __DIR__ . '/Top.php'; ?>
        <div id="main" data-role="main">
            <h1><?=htmlspecialchars($title)?></h1>

            <div class="bigcard"><?=$message?></div>
        </div>
	<?php include __DIR__ . '/Footer.php'; ?>
</html>

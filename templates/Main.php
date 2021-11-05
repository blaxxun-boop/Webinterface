<?php
/** @var string[][][] $table */
?><!DOCTYPE html>
<html id="html_main">
<?php include __DIR__ . '/Head.php'; ?>
<?php include __DIR__ . '/Top.php'; ?>

<div id="main" role="main">
    <div class="page-main">
		<?php if (!$table): ?>
		<div class="page-centered">
			<div class="title-page-centered">Anonymous access not allowed</div>
			<a href="Login" class="page-centered-button">Login</a>
		</div>
		<?php endif; ?>
        <?php foreach ($table as $title => $column): ?>
            <div class="page-main-column">
                <h2 class="page-main-column-title"><?= htmlspecialchars($title) ?></h2>

                <?php foreach ($column as $a): ?>
                    <div class="page-main-cell">
                        <a class="page-main-cell-a"
                           href="<?= htmlspecialchars($a['href']) ?>"><?= htmlentities($a['title']) ?></a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<div class="clear"></div>
<?php include __DIR__ . '/Footer.php'; ?>
</html>

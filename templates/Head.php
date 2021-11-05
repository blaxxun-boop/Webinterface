<?php
/** @var string $basePath */
?><head>
    <title><?php if (isset($title)): ?><?= htmlspecialchars($title) ?> &ndash; <?php endif; ?>Valheim Server</title>

	<base href="<?=$basePath == "" ? "" : $basePath ?>/" />
    <meta charset="utf8">
    <meta name="robots" content="all">
    <meta name="viewport" content="width=450,user-scalable=no">
    <meta http-equiv="content-language" content="de">
    <meta name="description" content="<?= htmlspecialchars(isset($metaDesc) ? $metaDesc : "Valheim server controller") ?>">

    <link rel="shortcut icon" href="favicon.ico" type="image/x-icon"/>
    <?php if (isset($canonical)): ?>
        <link rel="canonical" href="<?= $canonical ?>">
    <?php endif; ?>

    <link rel="stylesheet" href="css/main.css">
    <link rel="stylesheet" href="css/forms.css">
    <link rel="stylesheet" href="css/screen.css">
    <link rel="stylesheet" href="css/header.css">
    <link rel="stylesheet" href="css/mobile.css">

	<script src="js/sor-table.js" type="text/javascript"></script>
</head>

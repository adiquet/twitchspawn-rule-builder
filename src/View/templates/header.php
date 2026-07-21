<?php
/** @var string $pageTitle */
declare(strict_types=1);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= tsl_e($pageTitle ?? 'TwitchSpawn Ruleset Builder') ?></title>
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="page">
  <header class="site-header">
    <a href="index.php"><strong>TwitchSpawn Ruleset Builder</strong></a>
    <nav>
      <a href="builder.php">Build new</a>
      <a href="import.php">Import / fix a file</a>
      <button type="button" id="playbook-toggle" class="btn small">📖 Playbook</button>
    </nav>
  </header>

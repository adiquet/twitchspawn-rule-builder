<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

use TSL\Db\RulesetRepository;
use TSL\Support\Filenames;

$slug = isset($_GET['slug']) ? trim((string) $_GET['slug']) : '';
$token = isset($_GET['token']) ? trim((string) $_GET['token']) : '';
$fork = isset($_GET['fork']);

if ($slug === '') {
    $pageTitle = 'Load a saved ruleset';
    require TSL_TEMPLATES_DIR . '/header.php';
    ?>
    <h1>Load a saved ruleset</h1>
    <p class="muted">Paste the link you were given when you saved, or just the slug from the end of it.</p>
    <form method="get" action="r.php">
      <input type="text" name="slug" placeholder="slug or full r.php?slug=... link" style="width:100%;max-width:480px">
      <button class="btn" type="submit">Open</button>
    </form>
    <?php
    require TSL_TEMPLATES_DIR . '/footer.php';
    exit;
}

// Tolerate a pasted full URL in the slug field.
if (str_contains($slug, 'slug=')) {
    parse_str((string) parse_url($slug, PHP_URL_QUERY), $q);
    $slug = (string) ($q['slug'] ?? $slug);
    $token = $token ?: (string) ($q['token'] ?? '');
}

try {
    $repo = new RulesetRepository();
} catch (\RuntimeException $e) {
    http_response_code(500);
    echo 'Server is not configured yet (missing config/config.php).';
    exit;
}

$row = $repo->findBySlug($slug);
if (!$row) {
    $pageTitle = 'Not found';
    require TSL_TEMPLATES_DIR . '/header.php';
    echo '<h1>Not found</h1><p class="muted">No saved ruleset matches that link.</p>';
    require TSL_TEMPLATES_DIR . '/footer.php';
    exit;
}

$isEditMode = $token !== '' && $repo->verifyEditToken($row, $token);
$rules = json_decode($row['rules_json'], true) ?: [];

if ($isEditMode || $fork) {
    $initialState = [
        'profile' => $row['mc_profile'],
        'mcVersionLabel' => $row['mc_version_label'],
        'mcNick' => $row['mc_nick'],
        'title' => $row['title'],
        'rules' => $rules,
        'rulesetSlug' => $isEditMode ? $row['slug'] : null,
        'editToken' => $isEditMode ? $token : null,
    ];
    $pageTitle = ($isEditMode ? 'Edit' : 'Fork') . ' ruleset — TwitchSpawn Ruleset Builder';
    require TSL_TEMPLATES_DIR . '/header.php';
    echo '<h1>' . ($isEditMode ? 'Edit this ruleset' : 'Fork this ruleset (saving makes a new copy)') . '</h1>';
    require TSL_TEMPLATES_DIR . '/app_shell.php';
    require TSL_TEMPLATES_DIR . '/footer.php';
    exit;
}

// Read-only view mode.
$repo->incrementViewCount((int) $row['id']);
$pageTitle = ($row['title'] ?: 'Saved ruleset') . ' — TwitchSpawn Ruleset Builder';
require TSL_TEMPLATES_DIR . '/header.php';
?>
<h1><?= tsl_e($row['title'] ?: 'Saved ruleset') ?></h1>
<table class="summary-table">
  <tr><th>Minecraft version</th><td><?= tsl_e($row['mc_version_label']) ?></td></tr>
  <tr><th>File name</th><td><?= tsl_e(Filenames::rulesFilename($row['mc_nick'])) ?></td></tr>
  <tr><th>Rules</th><td><?= count($rules) ?></td></tr>
</table>
<pre><?= tsl_e($row['raw_tsl']) ?></pre>
<div class="save-row">
  <a class="btn primary" href="download.php?slug=<?= urlencode($row['slug']) ?>">Download .tsl</a>
  <a class="btn" href="r.php?slug=<?= urlencode($row['slug']) ?>&fork=1">Fork a copy to edit</a>
</div>
<p class="muted small">Don't have the edit link anymore? Fork a copy above — it opens this ruleset in the builder as a brand-new (unsaved) ruleset.</p>
<?php
require TSL_TEMPLATES_DIR . '/footer.php';

<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

use TSL\Grammar\GrammarDefinition;
use TSL\Grammar\Parser;
use TSL\Grammar\Rule;
use TSL\Grammar\Validator;
use TSL\Support\Csrf;

const MAX_IMPORT_BYTES = 262144; // 256KB — real rulesets are small text files.

$pageTitle = 'Import & fix a ruleset — TwitchSpawn Ruleset Builder';
$errors = [];
$warnings = [];
$parsedRules = null;
$submittedProfile = 'B';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Your session expired — reload this page and try again.';
    } else {
        $submittedProfile = $_POST['mcProfile'] ?? 'B';
        if (!isset(GrammarDefinition::PROFILES[$submittedProfile])) {
            $submittedProfile = 'B';
        }

        $text = null;
        if (!empty($_FILES['file']['name'])) {
            $file = $_FILES['file'];
            if ($file['error'] !== UPLOAD_ERR_OK) {
                $errors[] = 'File upload failed.';
            } elseif ($file['size'] > MAX_IMPORT_BYTES) {
                $errors[] = 'File is too large (max 256KB).';
            } elseif (!preg_match('/\.(tsl|txt)$/i', $file['name'])) {
                $errors[] = 'Only .tsl or .txt files are accepted.';
            } else {
                $contents = file_get_contents($file['tmp_name']);
                if ($contents === false || !mb_check_encoding($contents, 'UTF-8') || str_contains($contents, "\0")) {
                    $errors[] = "That file doesn't look like plain text.";
                } else {
                    $text = $contents;
                }
            }
        } else {
            $pasted = (string) ($_POST['pasted'] ?? '');
            if (strlen($pasted) > MAX_IMPORT_BYTES) {
                $errors[] = 'Pasted text is too large (max 256KB).';
            } elseif (trim($pasted) === '') {
                $errors[] = 'Paste some TSL text or choose a file.';
            } else {
                $text = $pasted;
            }
        }

        if ($text !== null) {
            $result = Parser::parse($text, $submittedProfile);
            $validatorWarnings = Validator::validate($result->rules, $submittedProfile);
            $errors = array_merge($errors, array_map(fn ($e) => $e, $result->errors));
            $warnings = array_merge($result->warnings, $validatorWarnings);
            $parsedRules = $result->rules;
        }
    }
}

require TSL_TEMPLATES_DIR . '/header.php';
?>
<h1>Import &amp; fix a ruleset</h1>
<p class="muted">Paste your existing rules.&lt;nick&gt;.tsl content, or upload the file. We'll flag anything that won't
  parse and anything that parses fine but will silently never fire (like a condition that doesn't apply to its event),
  then load the rest into the editable builder so you can fix it there.</p>

<form method="post" enctype="multipart/form-data" class="import-box">
  <input type="hidden" name="csrf_token" value="<?= tsl_e(\TSL\Support\Csrf::token()) ?>">
  <div class="field-row">
    <label>Minecraft version</label>
    <select name="mcProfile">
      <?php foreach (GrammarDefinition::PROFILES as $key => $def): ?>
        <option value="<?= tsl_e($key) ?>" <?= $submittedProfile === $key ? 'selected' : '' ?>><?= tsl_e($def['label']) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="field-row">
    <label>Paste your ruleset</label>
    <textarea name="pasted" placeholder="DROP minecraft:stick 2&#10; ON Donation&#10; WITH amount IN RANGE [0,20]"></textarea>
  </div>
  <div class="field-row">
    <label>...or upload a .tsl file</label>
    <input type="file" name="file" accept=".tsl,.txt">
  </div>
  <button class="btn primary" type="submit">Analyze</button>
</form>

<?php if ($_SERVER['REQUEST_METHOD'] === 'POST'): ?>
  <h2>Results</h2>
  <p>
    <?php if ($parsedRules !== null): ?>
      Parsed <strong><?= count($parsedRules) ?></strong> rule(s) —
      <?= count($errors) ?> couldn't be parsed, <?= count($warnings) ?> warning(s) on the rest.
    <?php endif; ?>
  </p>

  <?php if (!empty($errors)): ?>
    <div class="import-errors">
      <h3>Couldn't parse (<?= count($errors) ?>)</h3>
      <?php foreach ($errors as $e): ?>
        <div class="import-error-card">
          <?php if (is_string($e)): ?>
            <p><?= tsl_e($e) ?></p>
          <?php else: ?>
            <p><strong><?= tsl_e($e->code) ?></strong> <?php if ($e->line): ?>(near line <?= (int) $e->line ?>)<?php endif; ?></p>
            <p><?= tsl_e($e->message) ?></p>
            <?php if ($e->snippet): ?><pre><?= tsl_e($e->snippet) ?></pre><?php endif; ?>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
      <p class="muted small">These rules were left out of the editable builder below — fix them in your original file and re-import, or add the equivalent rule fresh in the builder.</p>
    </div>
  <?php endif; ?>

  <?php if (!empty($warnings)): ?>
    <div class="import-warnings">
      <h3>Parsed, but worth checking (<?= count($warnings) ?>)</h3>
      <?php foreach ($warnings as $w): ?>
        <div class="warning-item">
          ⚠ <?php if ($w->ruleIndex !== null): ?>Rule <?= $w->ruleIndex + 1 ?>: <?php endif; ?><?= tsl_e($w->message) ?>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <?php if ($parsedRules !== null && count($parsedRules) > 0): ?>
    <h3>Edit the imported rules</h3>
    <?php
    $initialState = [
        'profile' => $submittedProfile,
        'mcVersionLabel' => GrammarDefinition::PROFILES[$submittedProfile]['versions'][0],
        'mcNick' => '',
        'title' => '',
        'rules' => array_map(fn (Rule $r) => $r->toArray(), $parsedRules),
        'rulesetSlug' => null,
        'editToken' => null,
    ];
    require TSL_TEMPLATES_DIR . '/app_shell.php';
    ?>
  <?php endif; ?>
<?php endif; ?>

<?php require TSL_TEMPLATES_DIR . '/footer.php'; ?>

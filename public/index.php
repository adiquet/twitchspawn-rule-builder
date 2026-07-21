<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

$pageTitle = 'TwitchSpawn Ruleset Builder';
require TSL_TEMPLATES_DIR . '/header.php';
?>
<h1>Build TwitchSpawn rules without hand-writing TSL</h1>
<p class="muted">
  TwitchSpawn rulesets are written in a small custom language (TSL) that's easy to get subtly wrong —
  a missing bracket, an unwrapped multi-word value, or a predicate that silently never fires.
  This tool builds the file for you, starting with your Minecraft version.
</p>

<div class="landing-links">
  <a class="landing-card" href="builder.php">
    <h3>Build a new ruleset</h3>
    <p class="muted">Pick your Minecraft version and add rules with a guided form. Get a live preview and a downloadable/shareable file.</p>
  </a>
  <a class="landing-card" href="import.php">
    <h3>Import &amp; fix an existing file</h3>
    <p class="muted">Paste or upload a .tsl file you already have. We'll flag syntax mistakes and rules that silently won't fire, and load it into the editable builder.</p>
  </a>
  <a class="landing-card" href="r.php">
    <h3>Load a saved ruleset</h3>
    <p class="muted">Have a view or edit link from a previous save? Open r.php?slug=... directly, or paste the link your browser gave you.</p>
  </a>
</div>

<?php require TSL_TEMPLATES_DIR . '/footer.php'; ?>

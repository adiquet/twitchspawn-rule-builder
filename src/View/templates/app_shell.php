<?php
declare(strict_types=1);

use TSL\Support\Csrf;

/**
 * Shared builder app mount point, used by builder.php (new), import.php
 * (post-parse), and r.php (edit mode). Expects $initialState (array|null)
 * matching the JS STATE shape.
 * @var array|null $initialState
 */
$csrfToken = Csrf::token();
?>
<div id="app"
     data-grammar-url="grammar.php"
     data-generate-url="api_generate.php"
     data-csrf-token="<?= tsl_e($csrfToken) ?>">
  <p class="muted">Loading…</p>
</div>
<?php if ($initialState !== null): ?>
<script>window.TSL_INITIAL_STATE = <?= json_encode($initialState) ?>;</script>
<?php endif; ?>
<script src="assets/js/builder.js"></script>

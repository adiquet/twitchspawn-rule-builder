<?php declare(strict_types=1); ?>
</div>

<div class="playbook-backdrop" id="playbook-backdrop"></div>
<aside class="playbook-panel" id="playbook-panel" aria-hidden="true">
  <button type="button" class="icon-btn close-btn" id="playbook-close" aria-label="Close playbook">✕</button>
  <h2>Playbook</h2>
  <p class="muted small">A quick reference for TSL (TwitchSpawn Language) rules. This tool builds the syntax
    for you, but it helps to know the shape of what you're building.</p>

  <h3>Rule shape</h3>
  <pre><code>ACTION [params]
 ON Event Name
 WITH property comparator value
 WITH another_property comparator value</code></pre>
  <ul class="small">
    <li>Every <code>WITH</code> must match (they're AND'd together) — leave them off to match every
      occurrence of the event.</li>
    <li>Rules are checked top-to-bottom; the <strong>first</strong> one that matches wins and the rest are
      skipped.</li>
    <li>Keywords aren't case-sensitive (<code>on</code>/<code>ON</code>/<code>On</code> all work), but rule
      blocks must be separated by a truly blank line — a comment-only line does <strong>not</strong> count as
      a separator, a common gotcha in hand-written files.</li>
  </ul>

  <h3>Actions</h3>
  <ul class="small">
    <li><code>DROP</code> — drops an item in the direction the streamer is facing.</li>
    <li><code>SUMMON</code> — summons an entity near the streamer.</li>
    <li><code>EXECUTE</code> — runs one or more Minecraft commands as the streamer.</li>
    <li><code>THROW</code> — drops the item from a slot (destroys nothing).</li>
    <li><code>CLEAR</code> — destroys the item in a slot.</li>
    <li><code>SHUFFLE</code> — shuffles items within an inventory/slot range.</li>
    <li><code>CHANGE</code> — replaces the item in a slot with another item.</li>
    <li><code>EITHER ... OR ...</code> — randomly runs one of several actions.</li>
    <li><code>BOTH ... AND ...</code> — runs several actions in sequence.</li>
    <li><code>FOR n TIMES</code> — repeats an action.</li>
    <li><code>WAIT</code> — pauses before continuing (pair with BOTH/FOR).</li>
    <li><code>REFLECT</code> — mirrors an action to other players.</li>
    <li><code>OS_RUN</code> — runs a shell script locally or on the streamer's machine.</li>
    <li><code>NOTHING</code> — does nothing (optionally still shows a message).</li>
  </ul>

  <h3>Comparators</h3>
  <ul class="small">
    <li><code>= &gt; &lt; &gt;= &lt;=</code> — numeric comparisons.</li>
    <li><code>IS</code> — exact match.</li>
    <li><code>PREFIX</code> / <code>POSTFIX</code> / <code>CONTAINS</code> — string matching.</li>
    <li><code>IN RANGE [min,max]</code> — inclusive range. No spaces inside the brackets —
      <code>[100,200]</code> is valid, <code>[100, 200]</code> is not.</li>
  </ul>

  <h3>Chat/title messages (DISPLAYING)</h3>
  <p class="small">Must be a JSON text-component array wrapped in <code>[ ]</code>, e.g.
    <code>DISPLAYING [{text:"Something happened!", color:"gold"}]</code>. Values with spaces get wrapped in
    <code>%...%</code> automatically by this tool. On 1.14+ you can also suppress the notification entirely
    with <code>DISPLAYING NOTHING</code>.</p>

  <h3>Common mistakes this tool catches</h3>
  <ul class="small">
    <li>Unbalanced <code>{ }</code> / <code>[ ]</code> in a DISPLAYING message or item NBT data.</li>
    <li>A predicate property that doesn't apply to the chosen event (e.g. <code>amount</code> on
      <code>Twitch Follow</code>) — parses fine but silently never fires in-game.</li>
    <li><code>EITHER</code> branch chances that don't add up to 100%.</li>
    <li>Using a Profile-B-only event or <code>DISPLAYING NOTHING</code> while targeting 1.12.2.</li>
  </ul>

  <h3>Full documentation</h3>
  <p class="small">This playbook is just the basics — for the complete TwitchSpawn reference (item/entity IDs,
    NBT syntax, placeholders, full mod setup), see the
    <a href="https://igoodie.gitbook.io/twitchspawn/" target="_blank" rel="noopener">official TwitchSpawn GitBook</a>.</p>
</aside>

<script>
(function () {
  var toggle = document.getElementById('playbook-toggle');
  var panel = document.getElementById('playbook-panel');
  var backdrop = document.getElementById('playbook-backdrop');
  var closeBtn = document.getElementById('playbook-close');
  if (!toggle || !panel) return;

  function open() {
    panel.classList.add('open');
    backdrop.classList.add('open');
    panel.setAttribute('aria-hidden', 'false');
  }
  function close() {
    panel.classList.remove('open');
    backdrop.classList.remove('open');
    panel.setAttribute('aria-hidden', 'true');
  }

  toggle.addEventListener('click', function () {
    if (panel.classList.contains('open')) { close(); } else { open(); }
  });
  closeBtn.addEventListener('click', close);
  backdrop.addEventListener('click', close);
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') close();
  });
})();
</script>
</body>
</html>

/* TwitchSpawn Ruleset Builder — vanilla JS, re-render-from-state approach. */
(function () {
  'use strict';

  const APP_ROOT = document.querySelector('#app');
  const GRAMMAR_URL = APP_ROOT.dataset.grammarUrl || 'grammar.php';
  const GENERATE_URL = APP_ROOT.dataset.generateUrl || 'api_generate.php';

  let GRAMMAR = null;

  /** Global state. Pre-seeded by the page (window.TSL_INITIAL_STATE) for edit/import flows. */
  const STATE = window.TSL_INITIAL_STATE || { profile: null, mcVersionLabel: null, mcNick: '', title: '', rules: [] };

  function emptyAction(actionName) {
    const def = GRAMMAR.actions[actionName];
    const params = {};
    (def.params || []).forEach((p) => { params[p.replace('?', '')] = ''; });
    return { action: actionName, params, displaying: null, instantly: false, children: def.wraps === 'none' ? [] : [{ chance: null, action: emptyAction(firstSimpleAction()) }] };
  }

  function firstSimpleAction() {
    return Object.keys(GRAMMAR.actions).find((a) => GRAMMAR.actions[a].wraps === 'none') || 'DROP';
  }

  function emptyRule() {
    const firstEvent = Object.keys(GRAMMAR.events).find((e) => GRAMMAR.events[e].profiles.includes(STATE.profile)) || Object.keys(GRAMMAR.events)[0];
    return { action: emptyAction(firstSimpleAction()), event: firstEvent, predicates: [], note: '' };
  }

  function getAtPath(path) {
    let cur = STATE;
    for (const key of path) cur = cur[key];
    return cur;
  }
  function setAtPath(path, value) {
    let cur = STATE;
    for (let i = 0; i < path.length - 1; i++) cur = cur[path[i]];
    cur[path[path.length - 1]] = value;
  }

  function el(tag, attrs, children) {
    const node = document.createElement(tag);
    Object.entries(attrs || {}).forEach(([k, v]) => {
      if (k === 'class') node.className = v;
      else if (k.startsWith('on') && typeof v === 'function') node.addEventListener(k.slice(2), v);
      else if (v !== null && v !== undefined) node.setAttribute(k, v);
    });
    (children || []).forEach((c) => {
      if (c === null || c === undefined) return;
      node.appendChild(typeof c === 'string' ? document.createTextNode(c) : c);
    });
    return node;
  }

  function select(options, current, onChange, attrs) {
    const node = el('select', attrs || {}, options.map((opt) => {
      const value = typeof opt === 'string' ? opt : opt.value;
      const label = typeof opt === 'string' ? opt : opt.label;
      return el('option', { value, selected: value === current ? 'selected' : null }, [label]);
    }));
    node.addEventListener('change', (e) => onChange(e.target.value));
    if (current !== undefined) node.value = current;
    return node;
  }

  function textInput(value, onChange, attrs) {
    const node = el('input', Object.assign({ type: 'text', value: value || '' }, attrs || {}));
    node.addEventListener('input', (e) => onChange(e.target.value));
    return node;
  }

  function textareaInput(value, onChange, attrs) {
    const node = el('textarea', Object.assign({ rows: 4 }, attrs || {}), []);
    node.value = value || '';
    node.addEventListener('input', (e) => onChange(e.target.value));
    return node;
  }

  // ---------- Minecraft entity/item ID suggestions ----------
  // Full vanilla ID lists (~1000+ items) are fetched once with the rest of the grammar and
  // filtered client-side by the selected version, so switching versions needs no round-trip.
  // These are suggestions only (via <datalist>, not a locked <select>) — TwitchSpawn rulesets
  // routinely reference modded entity/item IDs the vanilla list has no way to know about.

  function bucketRank(bucket) {
    const order = GRAMMAR.minecraftIds.bucketOrder;
    return Object.prototype.hasOwnProperty.call(order, bucket) ? order[bucket] : order['1.18.x'];
  }

  function entitySuggestions() {
    const ids = GRAMMAR.minecraftIds;
    if (STATE.profile === 'A') return ids.entitiesA.map(([id, name]) => ({ id, name }));
    const maxRank = bucketRank(STATE.mcVersionLabel);
    return ids.entitiesB.filter(([, , bucket]) => bucketRank(bucket) <= maxRank).map(([id, name]) => ({ id, name }));
  }

  function itemSuggestions() {
    const ids = GRAMMAR.minecraftIds;
    if (STATE.profile === 'A') return ids.itemsA.map(([id, name, legend]) => ({ id, name, legend }));
    const maxRank = bucketRank(STATE.mcVersionLabel);
    return ids.itemsB.filter(([, , bucket]) => bucketRank(bucket) <= maxRank).map(([id, name]) => ({ id, name, legend: null }));
  }

  let itemLegendMap = null;
  function itemLegendFor(id) {
    if (STATE.profile !== 'A') return null;
    if (!itemLegendMap) {
      itemLegendMap = new Map();
      GRAMMAR.minecraftIds.itemsA.forEach(([itemId, , legend]) => { if (legend) itemLegendMap.set(itemId, legend); });
    }
    return itemLegendMap.get((id || '').trim()) || null;
  }

  /** Builds (or reuses) the two shared <datalist> elements so every entity_id/item_id field can
   *  reference the same option list via the `list` attribute instead of duplicating ~1000 <option>
   *  nodes per rule card. Rebuilt whenever render() runs (i.e. on version change, not on keystrokes). */
  function buildSharedDatalists() {
    itemLegendMap = null; // invalidated whenever profile/version may have changed
    const entityList = el('datalist', { id: 'mc-entities-datalist' },
      entitySuggestions().map((s) => el('option', { value: s.id }, [s.name])));
    const itemList = el('datalist', { id: 'mc-items-datalist' },
      itemSuggestions().map((s) => el('option', { value: s.id }, [s.name])));
    return [entityList, itemList];
  }

  // ---------- Rendering ----------

  function render() {
    APP_ROOT.innerHTML = '';
    if (!STATE.profile) {
      APP_ROOT.appendChild(renderVersionPicker());
      return;
    }
    APP_ROOT.appendChild(renderBuilder());
    scheduleGenerate();
  }

  function renderVersionPicker() {
    const wrap = el('div', { class: 'version-picker' }, [
      el('h2', {}, ['What Minecraft version are you using?']),
      el('p', { class: 'muted' }, ['This decides which TwitchSpawn events and action syntax are available — the rest of the form adapts automatically.']),
    ]);
    const grid = el('div', { class: 'version-grid' }, []);
    Object.entries(GRAMMAR.profiles).forEach(([key, def]) => {
      grid.appendChild(el('button', {
        type: 'button',
        class: 'version-card',
        onclick: () => { STATE.profile = key; STATE.mcVersionLabel = def.versions[0]; render(); },
      }, [
        el('strong', {}, [def.label]),
      ]));
    });
    wrap.appendChild(grid);
    return wrap;
  }

  function renderBuilder() {
    const wrap = el('div', { class: 'builder' });

    const header = el('div', { class: 'builder-header' }, [
      el('div', {}, [
        el('label', {}, ['Minecraft version: ']),
        select(Object.entries(GRAMMAR.profiles).flatMap(([key, def]) => def.versions.map((v) => ({ value: key + '|' + v, label: v }))),
          STATE.profile + '|' + STATE.mcVersionLabel,
          (v) => { const [profile, label] = v.split('|'); STATE.profile = profile; STATE.mcVersionLabel = label; render(); }),
      ]),
      el('div', {}, [
        el('label', {}, ['Minecraft nick (for rules.<nick>.tsl): ']),
        textInput(STATE.mcNick, (v) => { STATE.mcNick = v; scheduleGenerate(); }, { placeholder: 'leave blank for rules.default.tsl' }),
      ]),
      el('div', {}, [
        el('label', {}, ['Title (optional, for your own reference): ']),
        textInput(STATE.title, (v) => { STATE.title = v; }, { placeholder: 'e.g. My Donation Ruleset' }),
      ]),
    ]);
    wrap.appendChild(header);

    const rulesWrap = el('div', { class: 'rules', id: 'rules-wrap' });
    STATE.rules.forEach((rule, i) => rulesWrap.appendChild(renderRuleCard(rule, i)));
    wrap.appendChild(rulesWrap);

    wrap.appendChild(el('button', {
      type: 'button', class: 'btn add-rule',
      onclick: () => { STATE.rules.push(emptyRule()); render(); },
    }, ['+ Add rule']));

    wrap.appendChild(el('div', { class: 'preview-panel' }, [
      el('h3', {}, ['Preview']),
      el('pre', { id: 'tsl-preview' }, ['Generating…']),
      el('div', { id: 'warnings-panel', class: 'warnings' }),
      el('div', { class: 'save-row' }, [
        el('button', { type: 'button', class: 'btn primary', onclick: doSave }, ['Save & get a shareable link']),
        el('button', { type: 'button', class: 'btn', onclick: doDownload }, ['Download .tsl']),
      ]),
      el('div', { id: 'save-result' }),
    ]));

    buildSharedDatalists().forEach((dl) => wrap.appendChild(dl));

    return wrap;
  }

  function renderRuleCard(rule, index) {
    const availableEvents = Object.keys(GRAMMAR.events).filter((e) => GRAMMAR.events[e].profiles.includes(STATE.profile));
    const card = el('div', { class: 'rule-card' }, [
      el('div', { class: 'rule-card-header' }, [
        el('strong', {}, ['Rule ' + (index + 1)]),
        el('div', { class: 'rule-card-controls' }, [
          el('button', { type: 'button', class: 'icon-btn', title: 'Move up', disabled: index === 0 ? 'disabled' : null, onclick: () => moveRule(index, -1) }, ['↑']),
          el('button', { type: 'button', class: 'icon-btn', title: 'Move down', disabled: index === STATE.rules.length - 1 ? 'disabled' : null, onclick: () => moveRule(index, 1) }, ['↓']),
          el('button', { type: 'button', class: 'icon-btn danger', title: 'Delete rule', onclick: () => { STATE.rules.splice(index, 1); render(); } }, ['✕']),
        ]),
      ]),
      el('p', { class: 'muted small' }, ['Rules are checked top-to-bottom — the first one whose conditions match wins. Order matters.']),
      el('div', { class: 'field-row' }, [
        el('label', {}, ['Note (optional — saved as a comment above this rule, so you can tell rules apart later)']),
        textInput(rule.note || '', (v) => { rule.note = v; scheduleGenerate(); }, { placeholder: 'e.g. Big donation reward', style: 'width: 100%; max-width: 420px;' }),
      ]),
      el('div', { class: 'field-row' }, [
        el('label', {}, ['Action']),
        renderActionEditor(rule.action, ['rules', index, 'action'], 0),
      ]),
      el('div', { class: 'field-row' }, [
        el('label', {}, ['Event']),
        select(availableEvents, rule.event, (v) => { rule.event = v; render(); }),
      ]),
      renderPredicatesEditor(rule, index),
    ]);
    return card;
  }

  function moveRule(index, delta) {
    const target = index + delta;
    if (target < 0 || target >= STATE.rules.length) return;
    const tmp = STATE.rules[index];
    STATE.rules[index] = STATE.rules[target];
    STATE.rules[target] = tmp;
    render();
  }

  function renderPredicatesEditor(rule, ruleIndex) {
    const props = GRAMMAR.events[rule.event] ? GRAMMAR.events[rule.event].properties : [];
    const wrap = el('div', { class: 'predicates' }, [
      el('label', {}, ['WITH conditions (all must match — leave empty to match every occurrence of this event)']),
    ]);
    rule.predicates.forEach((pred, pIndex) => {
      wrap.appendChild(renderPredicateRow(rule, pred, pIndex, props));
    });
    wrap.appendChild(el('button', {
      type: 'button', class: 'btn small',
      onclick: () => { rule.predicates.push({ property: props[0] || 'actor', comparator: '=', value: '' }); render(); },
    }, ['+ Add condition']));
    return wrap;
  }

  function renderPredicateRow(rule, pred, index, props) {
    const propDef = GRAMMAR.properties[pred.property];
    const type = propDef ? propDef.type : 'string';
    const comparators = Object.entries(GRAMMAR.comparators)
      .filter(([, def]) => def.types.includes(type))
      .map(([token]) => token);
    if (!comparators.includes(pred.comparator) && comparators.length) pred.comparator = comparators[0];

    const isRange = pred.comparator === 'IN RANGE';
    let valueField;
    if (isRange) {
      const parts = (pred.value.match(/^\[(.*),(.*)\]$/) || ['', '', '']).slice(1);
      valueField = el('span', { class: 'range-inputs' }, [
        textInput(parts[0], (v) => { pred.value = `[${v},${parts[1]}]`; scheduleGenerate(); }, { placeholder: 'min', size: 6 }),
        ' to ',
        textInput(parts[1], (v) => { pred.value = `[${parts[0]},${v}]`; scheduleGenerate(); }, { placeholder: 'max', size: 6 }),
      ]);
    } else {
      valueField = textInput(pred.value, (v) => { pred.value = v; scheduleGenerate(); }, { placeholder: 'value' });
    }

    return el('div', { class: 'predicate-row' }, [
      select(props, pred.property, (v) => { pred.property = v; render(); }),
      select(comparators, pred.comparator, (v) => { pred.comparator = v; render(); }),
      valueField,
      el('button', { type: 'button', class: 'icon-btn danger', onclick: () => { rule.predicates.splice(index, 1); render(); } }, ['✕']),
    ]);
  }

  /** Caps meta-action (EITHER/BOTH/FOR/REFLECT) recursion at MAX_META_DEPTH levels, per the v1 scope
   *  decision — deeper real-world files still work via import, the parser itself has no such limit. */
  const MAX_META_DEPTH = 2;
  function renderActionEditor(action, path, metaDepth) {
    const wrap = el('div', { class: 'action-editor' });
    const allowMeta = metaDepth < MAX_META_DEPTH;
    const actionNames = Object.keys(GRAMMAR.actions).filter((a) => allowMeta || !GRAMMAR.actions[a].wraps || GRAMMAR.actions[a].wraps === 'none');

    wrap.appendChild(select(actionNames, action.action, (v) => {
      const newAction = emptyAction(v);
      setAtPath(path, newAction);
      render();
    }));

    const def = GRAMMAR.actions[action.action];
    if (!def) return wrap;

    wrap.appendChild(el('p', { class: 'muted small' }, [def.summary]));
    wrap.appendChild(renderParamFields(action, path, def));

    if (def.wraps === 'single') {
      wrap.appendChild(el('div', { class: 'nested-action' }, [
        el('label', {}, [action.action === 'FOR' ? 'Action to repeat:' : 'Action to reflect:']),
        renderActionEditor(action.children[0].action, path.concat(['children', 0, 'action']), metaDepth + 1),
      ]));
    } else if (def.wraps === 'multiple') {
      const branchesWrap = el('div', { class: 'branches' });
      action.children.forEach((child, i) => {
        const branch = el('div', { class: 'branch' }, [
          el('div', { class: 'branch-header' }, [
            el('strong', {}, [action.action === 'EITHER' ? `Branch ${i + 1}` : `Step ${i + 1}`]),
            action.action === 'EITHER' ? el('label', { class: 'inline' }, [
              'Chance %: ',
              textInput(child.chance === null ? '' : String(child.chance), (v) => {
                child.chance = v === '' ? null : parseFloat(v);
                scheduleGenerate();
              }, { size: 4, placeholder: 'even' }),
            ]) : null,
            action.children.length > 1 ? el('button', {
              type: 'button', class: 'icon-btn danger',
              onclick: () => { action.children.splice(i, 1); render(); },
            }, ['✕']) : null,
          ]),
          renderActionEditor(child.action, path.concat(['children', i, 'action']), metaDepth + 1),
        ]);
        branchesWrap.appendChild(branch);
      });
      wrap.appendChild(branchesWrap);
      wrap.appendChild(el('button', {
        type: 'button', class: 'btn small',
        onclick: () => { action.children.push({ chance: null, action: emptyAction(firstSimpleAction()) }); render(); },
      }, [action.action === 'EITHER' ? '+ Add branch' : '+ Add step']));

      if (action.action === 'BOTH') {
        wrap.appendChild(el('label', { class: 'inline' }, [
          el('input', {
            type: 'checkbox', checked: action.instantly ? 'checked' : null,
            onchange: (e) => { action.instantly = e.target.checked; scheduleGenerate(); },
          }),
          ' INSTANTLY (show one combined message instead of one per step)',
        ]));
      }
    }

    wrap.appendChild(renderDisplayingEditor(action));
    return wrap;
  }

  const PARAM_LABELS = {
    item_id: 'Item ID (e.g. minecraft:diamond)', amount: 'Amount', metadata: 'Metadata (1.12.2 only, e.g. dye color)',
    entity_id: 'Entity ID (e.g. minecraft:zombie)', coords: 'Coordinates (x y z, ~ for relative — blank for streamer’s position)', nbt: 'NBT data (optional)',
    commands: 'Command(s), one per line', slot: 'Slot', inventoryOrRange: 'Inventory name or "slot min max"',
    into_item_id: 'Replace with item ID', count: 'Repeat count', targets: 'Target(s): a name, %name, name%, an integer, or *',
    script: 'Script, one line per command', target: 'LOCAL or REMOTE', shell: 'CMD / POWERSHELL / BASH',
  };

  function renderParamFields(action, path, def) {
    const wrap = el('div', { class: 'param-fields' });
    const paramNames = (def.params || []).map((p) => p.replace('?', ''));
    paramNames.forEach((name) => {
      if (name === 'unit') return; // handled together with amount below
      const label = PARAM_LABELS[name] || name;
      if (name === 'commands' || name === 'script') {
        const textarea = el('textarea', { rows: 8 }, []);
        textarea.value = (action.params[name] || []).join('\n');
        textarea.addEventListener('input', (e) => {
          action.params[name] = e.target.value.split('\n').map((s) => s.trim()).filter(Boolean);
          scheduleGenerate();
        });
        wrap.appendChild(el('div', { class: 'field-row' }, [el('label', {}, [label]), textarea]));
        return;
      }
      if (action.action === 'OS_RUN' && name === 'shell') {
        wrap.appendChild(el('div', { class: 'field-row' }, [el('label', {}, [label]),
          select(GRAMMAR.osRunShells, action.params.shell, (v) => { action.params.shell = v; scheduleGenerate(); })]));
        return;
      }
      if (action.action === 'OS_RUN' && name === 'target') {
        wrap.appendChild(el('div', { class: 'field-row' }, [el('label', {}, [label]),
          select(GRAMMAR.osRunTargets, action.params.target, (v) => { action.params.target = v; scheduleGenerate(); })]));
        return;
      }
      if ((action.action === 'THROW' || action.action === 'CLEAR' || action.action === 'CHANGE') && name === 'slot') {
        wrap.appendChild(el('div', { class: 'field-row' }, [el('label', {}, [label]),
          select(GRAMMAR.slotNames.concat(['slot N FROM inventory']), action.params.slot, (v) => { action.params.slot = v; scheduleGenerate(); }),
          el('span', { class: 'muted small' }, [' or type "slot 3 FROM inventory" directly → ']),
          textInput(action.params.slot, (v) => { action.params.slot = v; scheduleGenerate(); }, { placeholder: 'custom slot syntax' })]));
        return;
      }
      if (action.action === 'WAIT' && name === 'amount') {
        wrap.appendChild(el('div', { class: 'field-row' }, [el('label', {}, ['Wait amount + unit']),
          textInput(action.params.amount, (v) => { action.params.amount = v; scheduleGenerate(); }, { size: 6 }),
          select(GRAMMAR.waitUnits, action.params.unit, (v) => { action.params.unit = v; scheduleGenerate(); })]));
        return;
      }
      if (name === 'entity_id') {
        wrap.appendChild(el('div', { class: 'field-row' }, [
          el('label', {}, [label]),
          textInput(action.params[name], (v) => { action.params[name] = v; scheduleGenerate(); },
            { placeholder: 'start typing to search…', list: 'mc-entities-datalist' }),
        ]));
        return;
      }
      if (name === 'item_id' || name === 'into_item_id') {
        const hint = el('div', { class: 'muted small metadata-hint' }, []);
        const updateHint = (v) => {
          const legend = itemLegendFor(v);
          hint.textContent = legend ? `Metadata: ${legend}` : '';
        };
        const input = textInput(action.params[name], (v) => { action.params[name] = v; scheduleGenerate(); updateHint(v); },
          { placeholder: 'start typing to search…', list: 'mc-items-datalist' });
        updateHint(action.params[name]);
        wrap.appendChild(el('div', { class: 'field-row' }, [el('label', {}, [label]), input, hint]));
        return;
      }
      if (name === 'nbt') {
        const isEmbedded = action.action === 'DROP' || action.action === 'CHANGE';
        // Stored/generated as a single TSL token, so line breaks the user types are collapsed to
        // spaces — the bigger box is for comfortable editing, not for a genuinely multi-line value.
        const input = textareaInput(action.params[name], (v) => { action.params[name] = v.replace(/\r\n|\r|\n/g, ' '); scheduleGenerate(); },
          { placeholder: 'e.g. {Enchantments:[{id:"minecraft:sharpness",lvl:5}]}' });
        wrap.appendChild(el('div', { class: 'field-row' }, [
          el('label', {}, [label]),
          input,
          el('div', { class: 'muted small nbt-hint' }, [
            isEmbedded
              ? 'Appended directly after the item ID (include the surrounding { }). '
              : 'A separate value placed after the coordinates (include the surrounding { }). ',
            'Syntax reference: ',
            el('a', { href: 'https://minecraft.fandom.com/wiki/NBT_format', target: '_blank', rel: 'noopener noreferrer' }, ['Minecraft NBT format']),
            '.',
          ]),
        ]));
        return;
      }
      wrap.appendChild(el('div', { class: 'field-row' }, [
        el('label', {}, [label]),
        textInput(action.params[name], (v) => { action.params[name] = v; scheduleGenerate(); }, { placeholder: def.params.includes(name + '?') ? '(optional)' : '' }),
      ]));
    });
    return wrap;
  }

  function renderDisplayingEditor(action) {
    const mode = action.displaying ? action.displaying.mode : 'default';
    const nothingAllowed = STATE.profile === 'B';
    const modes = [{ value: 'default', label: 'Default notification' }, { value: 'text', label: 'Custom message (JSON text array)' }]
      .concat(nothingAllowed ? [{ value: 'nothing', label: 'Suppress notification (DISPLAYING NOTHING)' }] : []);
    const wrap = el('div', { class: 'field-row displaying' }, [
      el('label', {}, ['Chat/title message']),
      select(modes, mode, (v) => {
        if (v === 'default') action.displaying = null;
        else if (v === 'nothing') action.displaying = { mode: 'nothing', value: null };
        else action.displaying = { mode: 'text', value: action.displaying && action.displaying.value ? action.displaying.value : '' };
        render();
      }),
    ]);
    if (mode === 'text') {
      // Stored/generated as a single TSL token, so line breaks the user types are collapsed to
      // spaces — the bigger box is for comfortable editing, not for a genuinely multi-line value.
      wrap.appendChild(textareaInput(action.displaying.value, (v) => {
        action.displaying.value = v.replace(/\r\n|\r|\n/g, ' ');
        scheduleGenerate();
      }, { placeholder: '[{text:"Something happened!", color:"gold"}]' }));
    }
    return wrap;
  }

  // ---------- Preview / save / download ----------

  let generateTimer = null;
  function scheduleGenerate() {
    clearTimeout(generateTimer);
    generateTimer = setTimeout(runGenerate, 300);
  }

  function runGenerate() {
    const pre = document.getElementById('tsl-preview');
    const warningsPanel = document.getElementById('warnings-panel');
    if (!pre) return;
    fetch(GENERATE_URL, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ mcProfile: STATE.profile, rules: STATE.rules }),
    })
      .then((r) => r.json())
      .then((data) => {
        pre.textContent = data.tsl || '(empty ruleset)';
        const hasIssues = (data.warnings || []).length > 0;
        pre.classList.toggle('has-issues', hasIssues);
        pre.classList.toggle('no-issues', !hasIssues);
        warningsPanel.innerHTML = '';
        (data.warnings || []).forEach((w) => {
          warningsPanel.appendChild(el('div', { class: 'warning-item' }, [`⚠ ${w.message}`]));
        });
      })
      .catch(() => {
        pre.textContent = 'Could not generate preview.';
        pre.classList.add('has-issues');
        pre.classList.remove('no-issues');
      });
  }

  function doDownload() {
    const form = el('form', { method: 'POST', action: 'download_adhoc.php', target: '_blank' }, [
      el('input', { type: 'hidden', name: 'payload', value: JSON.stringify({ mcProfile: STATE.profile, mcNick: STATE.mcNick, rules: STATE.rules }) }),
      el('input', { type: 'hidden', name: 'csrf_token', value: APP_ROOT.dataset.csrfToken || '' }),
    ]);
    document.body.appendChild(form);
    form.submit();
    form.remove();
  }

  function doSave() {
    const resultBox = document.getElementById('save-result');
    resultBox.textContent = 'Saving…';
    fetch('save.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        mcProfile: STATE.profile,
        mcVersionLabel: STATE.mcVersionLabel,
        mcNick: STATE.mcNick,
        title: STATE.title,
        rules: STATE.rules,
        csrfToken: APP_ROOT.dataset.csrfToken || '',
        rulesetSlug: STATE.rulesetSlug || null,
        editToken: STATE.editToken || null,
      }),
    })
      .then((r) => r.json())
      .then((data) => {
        if (data.error) {
          resultBox.innerHTML = '';
          resultBox.appendChild(el('div', { class: 'error-box' }, [data.error]));
          return;
        }
        resultBox.innerHTML = '';
        resultBox.appendChild(el('div', { class: 'save-success' }, [
          el('p', {}, ['Saved! Bookmark both of these links now — the edit link can’t be recovered if you lose it.']),
          el('p', {}, [el('strong', {}, ['View link: ']), el('a', { href: data.viewUrl }, [data.viewUrl])]),
          el('p', {}, [el('strong', {}, ['Edit link: ']), el('a', { href: data.editUrl }, [data.editUrl])]),
        ]));
      })
      .catch(() => { resultBox.textContent = 'Save failed.'; });
  }

  // ---------- Boot ----------

  fetch(GRAMMAR_URL)
    .then((r) => r.json())
    .then((g) => { GRAMMAR = g; render(); })
    .catch(() => { APP_ROOT.textContent = 'Could not load grammar definition.'; });
})();

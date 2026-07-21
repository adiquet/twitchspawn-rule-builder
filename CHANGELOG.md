# Changelog

All notable changes to this project. Format loosely follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

Git itself was only set up on **2026-07-20**, so everything through that date — including the
"2026-07-19" and "Unreleased" entries below — landed in a single baseline commit reconstructed
from conversation history, not tracked incrementally as it happened. Treat those sections as a
summary, not a literal commit-by-commit history. From the next change onward, each entry here
gets its own commit.

## [Unreleased]

### Changed
- **Playbook panel stays open while you edit.** It was a modal at first — a backdrop dimmed and
  blocked the page, and clicking outside the panel closed it. Now it's a docked reference panel:
  no backdrop, clicking into form fields behind it doesn't close it, and on screens wide enough
  (≥760px) the page content stays clear of it so nothing hides behind it. Only closes via its ✕
  or Escape. (`src/View/templates/footer.php`, `public/assets/css/style.css`)
- **Playbook panel: main content no longer shrinks needlessly.** The first pass reserved space
  for the panel by shrinking the page's normal 960px centered column, which left a large dead
  gap on wide monitors (the column was already narrower than the screen, and the reservation
  compounded that instead of accounting for it). Now the page drops its reading-width cap while
  the panel's open and fills the actual available width up to it. (`public/assets/css/style.css`)

### Added
- **Playbook panel.** A "📖 Playbook" button in the header (every page) slides in a right-hand
  reference panel: rule shape, the blank-line-vs-comment-only-line gotcha, a one-line summary of
  every action and comparator, how `DISPLAYING` messages work, a list of mistakes this tool
  catches for you, and a link out to the full
  [official TwitchSpawn GitBook](https://igoodie.gitbook.io/twitchspawn/) for anything deeper
  (item/entity IDs, NBT syntax, placeholders). Closes via its own ✕, clicking the backdrop, or
  Escape. (`src/View/templates/header.php`, `footer.php`, `public/assets/css/style.css`)

### Fixed
- **Bracket-mismatch warnings now suggest a fix.** `DISPLAYING_UNBALANCED_BRACKETS` and
  `NBT_UNBALANCED_BRACKETS` used to just say *what* kind of bracket problem was found. They
  now also show the text right before the problem and a corrected version of the whole value,
  so you can see exactly where the missing `}`/`]` needs to go instead of hunting for it.
  (`src/Grammar/Validator.php`)
- **Preview box turns red/green.** The generated `.tsl` preview text is red while the current
  ruleset has any warnings, and green once it's clean — updates live as you edit, on the
  builder, saved-ruleset edit, and import pages alike (they all share the same preview panel).
  (`public/assets/js/builder.js`, `public/assets/css/style.css`)

## [2026-07-19]

### Fixed
- **TSL keywords are actually case-insensitive.** Originally treated a miscased `On` (etc.) as
  a hard parse error, reasoning from the docs' ALL-CAPS convention. Confirmed via in-game
  testing that TwitchSpawn's real parser accepts any case for keywords — `on`/`On`/`oN` all
  work. Replaced the case-error with a single centralized normalization pass
  (`Parser::normalizeKeywordCase()`) that canonicalizes every reserved keyword to uppercase
  right after tokenizing, plus case-insensitive event-name resolution
  (`GrammarDefinition::resolveEventName()`). Added a roundtrip fixture
  (`case_insensitive_keywords__B.tsl`) covering it.

### Changed
- **Bigger, resizable text fields.** The `EXECUTE`/`OS_RUN` commands textarea grew from 3 to 8
  rows, and the `DISPLAYING` chat/title message field changed from a single-line `<input>` to a
  resizable textarea. A generic `textarea { width: 100%; resize: vertical; }` rule in
  `style.css` covers both (and any future textarea) at once. Since `DISPLAYING`'s value has to
  stay a single TSL token under the hood, line breaks typed into the box are collapsed to
  spaces on save rather than preserved literally.

## Initial build

Everything below was already in place before change tracking started; grouped by area, not by
the order it was actually built in.

### Added
- **TSL grammar engine** (`src/Grammar/`): `Tokenizer` (line classification, `%...%` grouping,
  `\%` escaping, leading-space continuation), `Parser` (structural errors with line numbers),
  `Validator` (semantic warnings — invalid predicate property for an event, profile mismatches,
  unbalanced brackets, `EITHER` chance sums not adding to 100, etc.), `Generator` (structured
  rules → `.tsl` text). `GrammarDefinition` is the single source of truth for events,
  predicates, comparators, and actions — the tokenizer, parser, validator, generator, and the
  frontend dropdowns (via `grammar.php`) all read from it so they can't drift apart.
- **Two grammar profiles**: Profile A (Minecraft 1.12.2) and Profile B (1.14.x–1.18.x), covering
  the one real syntax fork in TSL (extra `[metadata]` param on `DROP`/`CHANGE` in A, 4 extra
  events and `DISPLAYING NOTHING` support in B).
- **Builder UI** (`public/builder.php`, `assets/js/builder.js`): version picker, repeatable rule
  rows (action + params + `DISPLAYING` + `ON` event + `WITH` predicates), debounced live preview
  via `api_generate.php`, one level of meta-action nesting (`EITHER`/`BOTH`/`FOR`/`REFLECT`).
- **Import/fix flow** (`public/import.php`, `api_parse.php`): paste or upload an existing
  `.tsl`, get parsed into the same structured rule model, errors shown as line-numbered cards
  and warnings as inline badges on the pre-seeded builder UI.
- **No-login save/load**: random slug (view URL) + separately shown edit token
  (`src/Slug/SlugGenerator.php`, `EditSecret.php`), `save.php`, `r.php?slug=&token=`.
- **Download**: `download.php` / `download_adhoc.php` stream `rules.<nick>.tsl`.
- **Persistence**: MySQL via PDO + prepared statements (`src/Db/Database.php`,
  `RulesetRepository.php`), schema in `db/schema.sql` (`rulesets` + `ruleset_revisions` for
  recovering from a lost edit link).
- **Security**: CSRF token on state-changing POSTs (`src/Support/Csrf.php`), HTML-escaped
  output throughout, upload size/extension checks on import, sanitized download filenames
  (`src/Support/Filenames.php`).
- **Two deploy layouts**, auto-detected at runtime by `_bootstrap.php`: sibling layout (own
  document root) and flattened layout (subfolder of an existing site, e.g.
  `gamelikeus.com/twitchspawn`), each documented in `README.md`.
- **Docker dev stack**: `php:8.2` + Apache + MySQL 8.0 via `docker-compose.yml`, app on
  `localhost:8090`.
- **Test suite** (`tests/run.php`): zero-dependency PHP CLI runner over `tests/fixtures/`
  (roundtrip / errors / warnings), one fixture per documented error/warning code, run via
  `docker compose exec web php tests/run.php`.

# TwitchSpawn Ruleset Builder

A guided web form for building [TwitchSpawn](https://igoodie.gitbook.io/twitchspawn/) rulesets (TSL —
TwitchSpawn Language) without hand-writing the syntax, plus an import/fix flow for existing `.tsl` files.

## Local development (Docker)

Requires Docker Desktop (already used for everything here — no XAMPP/WAMP needed).

```
docker compose up --build
```

- App: http://localhost:8090
- MySQL: localhost:3307 (user `twitchspawn_builder` / pass `localdev`, db `twitchspawn_builder`) — schema is
  loaded automatically from `db/schema.sql` on first run via MySQL's `docker-entrypoint-initdb.d`.
- `config/config.php` (gitignored) is already set up for this compose stack. Don't reuse it in production —
  copy `config/config.sample.php` instead and fill in real credentials.

If you change `db/schema.sql` after the MySQL volume already exists, re-apply it manually (the init script
only runs on a fresh volume):

```
docker compose exec db mysql -utwitchspawn_builder -plocaldev twitchspawn_builder < db/schema.sql
```

## Running the grammar test suite

The tokenizer/parser/generator/validator have zero DB dependency and their own CLI test runner:

```
docker compose run --rm web php tests/run.php
```

(Or, without compose: `docker run --rm -v "${PWD}:/app" -w /app php:8.2-cli php tests/run.php`.)

## Deploying to Plesk

The app supports two on-disk layouts, auto-detected at runtime by `public/_bootstrap.php` (or the
subfolder build's `_bootstrap.php` — same file, same detection logic either way):

- **Sibling layout** — `public/` is its own folder and the domain's document root points directly at it,
  with `src/`, `config/`, `db/` as siblings outside the web-servable path. This is what local Docker dev
  uses, and it's the right choice if you control the domain's document root setting.
- **Flattened layout** — everything lives in one folder (what was `public/*` at the top, `src/`, `config/`,
  `db/` nested inside it), used when the app has to sit in a **subfolder of an existing site** (e.g.
  `gamelikeus.com/twitchspawn`) where you can't set a separate document root for that one path. The nested
  `src/`, `config/`, `db/` folders each carry a `.htaccess` (`Require all denied`) so they're unreachable by
  URL even though they're physically inside the web-servable tree — PHP's own `require`/`include` calls
  read them at the filesystem level and aren't affected by that.

### Option A — own domain/subdomain (sibling layout)

1. Point the document root at this project's `public/` folder.
2. Plesk → Databases → create a database + user, then Databases → phpMyAdmin → Import → `db/schema.sql`.
3. Copy `config/config.sample.php` to `config/config.php` and fill in the real DB host/name/user/pass.
4. Confirm the PHP version Plesk is running (`phpinfo()`) — this code targets PHP 7.4+ syntax.

### Option B — subfolder of an existing site, e.g. gamelikeus.com/twitchspawn (flattened layout)

1. Build the flattened package (or ask for a fresh one if the project has changed since):
   - Copy `public/*` to the root of a new folder.
   - Copy `src/`, `config/` (**without** `config.php`, `config.sample.php` only), and `db/` into that same
     folder as direct children, each keeping its `.htaccess`.
2. Upload that folder's contents into `httpdocs/twitchspawn/` via Plesk File Manager (or however you deploy)
   — no document root change needed, this is a plain subfolder of the site's existing docroot.
3. Plesk → Databases → create a database + user, then Databases → phpMyAdmin → Import → `db/schema.sql`
   (found at `twitchspawn/db/schema.sql`).
4. In File Manager, inside `twitchspawn/config/`, duplicate `config.sample.php` → rename to `config.php` →
   fill in the real DB credentials.
5. Visit `https://gamelikeus.com/twitchspawn/` — confirm it loads, then confirm
   `https://gamelikeus.com/twitchspawn/config/config.php` and `.../db/schema.sql` both return 403/Forbidden
   (proves the `.htaccess` protection is actually active — if either loads, don't use the site until fixed).
6. Confirm the PHP version Plesk is running (`phpinfo()`) — this code targets PHP 7.4+ syntax.

Both layouts were tested locally before shipping (Docker for the sibling layout including a full
save/view/edit/download round trip against real MySQL; `php -S` plus an Apache subfolder alias for the
flattened layout, confirming asset links, page includes, and the generated view/edit link URLs all resolve
correctly under a `/twitchspawn`-style path prefix).

## Project layout

- `public/` — web-servable entry points (pages + `assets/`).
- `src/Grammar/` — the TSL grammar: `GrammarDefinition` (events/predicates/comparators/actions/profiles,
  the single source of truth), `Tokenizer`, `Parser`, `Validator`, `Generator`.
- `src/Db/`, `src/Slug/`, `src/Support/` — persistence, the no-login slug/edit-token scheme, small helpers.
- `src/View/templates/` — PHP view partials shared across pages.
- `tests/` — `run.php` (CLI runner) + `fixtures/` (round-trip examples, one fixture per documented
  error/warning code).

## Known v1 scope limits

- The builder UI caps meta-action nesting (`EITHER`/`BOTH`/`FOR`/`REFLECT`) at one level deep. The parser
  itself has no such limit — importing a deeper real-world file still works, it just can't be built fresh
  through the nested form past that depth.
- NBT data and Minecraft commands are treated as opaque text, not deeply validated — those are their own
  large grammars tied to Minecraft's own version-specific registries. `DROP`/`CHANGE` get a dedicated NBT
  field (appended directly after the item ID, e.g. `minecraft:diamond_sword{Enchantments:[...]}`) and
  `SUMMON` already had one; none of them check the NBT content itself, only that it's structurally a
  `%...%`-safe token. `DISPLAYING` values get one shallow check — the preview/import flow warns if the
  text isn't wrapped in `[ ]` (a hard requirement TwitchSpawn enforces, easy to miss by hand) — but the
  JSON-ish content inside isn't validated beyond that.
- No accounts: saved rulesets are reachable only via their slug (view) and edit token (edit) links. Losing
  the edit link means forking a copy from the view page and re-saving, not recovering the original.

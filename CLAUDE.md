# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

A GLPI 11 plugin (PHP ≥8.2) called `directlabelprinter`. It adds a "Imprimir Etiqueta" (print label) action — a GLPI massive action, which also covers a single selected item — to a whitelisted set of asset itemtypes (`src/AssetTypes.php::WHITELIST`: Computer, Monitor, NetworkEquipment, Printer, Phone, Peripheral, Rack, ConsumableItem). Printing is fully self-sufficient: the plugin owns its own layout/print-server data, builds the label PDF itself with TCPDF, and talks directly to a small companion service (`print_service.py`, external to this repo) over HTTP using an `X-API-Key` header — there is no external REST API, no JWT auth flow, and no bearer-token refresh logic anywhere in this plugin.

The plugin currently lives inside a full GLPI 11 core checkout at the repo root's grandparent directory (`plugins/directlabelprinter`), so GLPI core classes (`CommonDBTM`, `Html`, `Session`, `Toolbox`, `MassiveAction`, `Migration`, `DBConnection`, etc.) are available globally without namespace imports beyond `use`.

## Commands

- **SFTP sync (VS Code SFTP plugin)** — before the VS Code SFTP extension can push/pull files to the GLPI test server, open a Cloudflare Access TCP tunnel on the local SFTP port:
  ```
  cloudflared access tcp --hostname glpi11ssh.luffyslair.tec.br --listener 127.0.0.1:2222
  ```
  Leave this running in a terminal while syncing; the SFTP plugin connects to `127.0.0.1:2222`, which `cloudflared` forwards to the real server over Cloudflare Access.
- `composer install` — installs dev tooling (`glpi-project/tools`), used for phpstan/psalm/cs-fixer config.
- The `Makefile` just does `include ../../PluginsMakefile.mk`, which is GLPI core's shared plugin makefile. That file is **not present** in this checkout (GLPI core sources tree, not the GLPI monorepo tools repo), so `make` targets will not work until that file is available from a full `glpi-project/tools`-managed GLPI install. Don't assume `make` targets work — check for the include file first.
- Static analysis / lint configs exist (`phpstan.neon` at `level: max` using `glpi-project/phpstan-glpi`; `psalm.xml` with taint analysis; `.php-cs-fixer.php` using the `@PER-CS` ruleset) but there is no `vendor/` checked in — run `composer install` first, then invoke `vendor/bin/phpstan analyse`, `vendor/bin/psalm`, `vendor/bin/php-cs-fixer fix` directly (analysing `src/`, `hook.php`, `setup.php`).
- `phpunit.xml` points at a `tests/` directory (bootstrap `tests/bootstrap.php`, suffix `*Test.php`); check whether it's populated before assuming there's nothing to run.
- No PHP CLI is available in the sandbox this repo is typically edited from — verify PHP syntax by careful reading, or run `php -l` on a machine that has PHP, rather than assuming a clean edit compiles.

## Architecture

### Plugin bootstrap
- `setup.php` — declares `PLUGIN_DIRECTLABELPRINTER_VERSION` / min-max GLPI version. `plugin_init_directlabelprinter()` registers `$PLUGIN_HOOKS`: `csrf_compliant`, `Hooks::USE_MASSIVE_ACTION` unconditionally, and — only when the plugin is installed+active — `menu_toadd['setup' => Menu::class]`, `secured_fields` (masks `printservers.api_key` in the UI), `post_item_form` (wires `plugin_directlabelprinter_display_print_button` onto every whitelisted itemtype's form), and `add_javascript` (loads `js/components.js` on every page — see "Static assets" below for why the hook value has no `public/` prefix even though the file physically lives under `public/js/`).
- `hook.php` — `plugin_directlabelprinter_install()` creates 4 tables (`printservers`, `layouts`, `layout_itemtype`, `userprefs`) via raw SQL, and contains an upgrade path (`elseif (!$DB->fieldExists($layouts_table, 'elements'))`) that drops and recreates the pre-redesign `layouts` table shape, plus defensive dropping of the legacy `glpi_plugin_directlabelprinter_auth` table. `plugin_directlabelprinter_uninstall()` drops all of the above. **`PLUGIN_DIRECTLABELPRINTER_VERSION` must match between `setup.php` and `hook.php`, and must be bumped whenever `install()`'s migration logic changes** — GLPI skips re-running `install()` on an existing install if the DB-recorded version already equals the constant.
- `plugin_directlabelprinter_MassiveActions($itemtype)` registers the `print_label` massive action for whitelisted itemtypes.
- `plugin_directlabelprinter_display_print_button(array $params)` (the `post_item_form` callback) renders the single-item print button + an inline `<script>` (wrapped in `DOMContentLoaded`, since this HTML is part of the initial page load) calling `window.directLabelPrinter.openPrintModal(...)`. All `json_encode()` calls feeding that inline script are passed `JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT` — required because item names are attacker-influenced (asset "name" field) and are being embedded directly into an inline `<script>` block; omitting these flags is a stored-XSS hole. Returns early (no button rendered) if the itemtype has no default layout configured (see `LayoutItemtype::getDefaultLayoutId()`).

### Static assets (`public/js/`)
GLPI 11 only serves plugin static files (anything not under `ajax/`, `front/`, or `report/`) from `<plugin>/public/` — see core's `src/Glpi/Http/RequestRouterTrait::getTargetFile()`. Both plugin JS files live under `public/js/`:
- `public/js/components.js` — defines `window.directLabelPrinter` (`openPrintModal`, `closeModal`, and the 3 `fetch()`-based AJAX handlers below). Loaded on every page via the `add_javascript` hook in `setup.php` (hook value `'js/components.js'`, **not** `'public/js/components.js'` — GLPI's `Html::script()` + router already prepend `/public` for plugin resources, so including it in the hook value would double it and 404).
- `public/js/layout_editor.js` — the GridStack-based drag/drop layout editor used only on `Layout::showForm()`.
- `Layout::showForm()` loads two more assets: `{root_doc}/lib/gridstack.min.js` / `.css` (GLPI core's own vendored copy, already under core's `public/`, so **do not** add an extra `/public` segment — GLPI's router adds one automatically for any non-`/js/*.js`, non-plugin path) and `{root_doc}/plugins/directlabelprinter/js/layout_editor.js` (resolves through the plugin-resource router to `public/js/layout_editor.js`).
- If you add a new plugin JS/CSS/image asset, it must live under `public/` or GLPI's router will 404 it — this bit the plugin badly during the initial redesign and is worth re-checking any time a "file not loading" bug shows up.

### AJAX endpoints (`ajax/`) and CSRF
`ajax/print.php`, `ajax/printserver_test.php`, `ajax/printserver_fetch_printers.php` are classic `include("../../../inc/includes.php")` scripts, each guarded by a permission check (`(new $itemtype())->can(0, READ)` / `PrintServer::canView()`).

CSRF is validated **entirely by GLPI 11's kernel**, not by this plugin's PHP: `Glpi\Kernel\Listener\ControllerListener\CheckCsrfListener` runs before any plugin script executes and, for a request carrying `X-Requested-With: XMLHttpRequest`, validates the token from the `X-Glpi-Csrf-Token` **header** (and does not consume it, so repeated AJAX calls in one page load are fine). Because of this:
- All 3 `fetch()` calls in `public/js/components.js` send `'X-Requested-With': 'XMLHttpRequest'` and `'X-Glpi-Csrf-Token': getAjaxCsrfToken()` (a GLPI core global — reads the `<meta property="glpi:csrf_token">` tag present on every authenticated page). None of them put the CSRF token in the request body/JSON anymore.
- **Do not add a `Session::checkCSRF()` call back into these 3 PHP endpoints.** The kernel already validated the header before the script runs; a plugin-level check reading from the POST body/JSON would find no token there (it's in the header now) and would spuriously reject valid requests.
- If you add a new AJAX endpoint that needs CSRF protection, follow the same pattern: send the two headers from JS, don't check CSRF again in PHP.

### Massive action + business logic (`src/DirectLabelPrinterActions.php`)
`DirectLabelPrinterActions extends CommonDBTM` purely to plug into GLPI's massive-action framework:
- `showMassiveActionsSubForm()` — for `print_label`, loads layouts/print-servers for the itemtype and emits an inline `<script>` calling `window.directLabelPrinter.openPrintModal(...)`. **This script runs immediately, with no `DOMContentLoaded` wrapper** — unlike the single-item button in `hook.php`, this markup is injected via AJAX into an already-loaded page, so `DOMContentLoaded` has already fired by the time it's inserted; wrapping it in that listener would make the modal silently never open. If you touch this method, keep it unwrapped.
- `resolveItemsForPrint(string $itemtype, array $ids)` — resolves massive-action-style `[['id' => X], ...]` into `{id, titulo, url, ref}` for the print modal / PDF builder. Checks `$item_obj->getFromDB($id) && $item_obj->can($id, READ)` per item — the per-item `can()` check matters because callers (e.g. `ajax/print.php`) only do a class-level `can(0, READ)` check, which does not by itself prevent a user with READ in one entity from requesting item IDs belonging to another entity they can't see.
- `processMassiveActionsForOneItemtype()` — just marks each item `ACTION_OK`; the actual print HTTP call happens client-side via `ajax/print.php`, not here.

### Layouts (`src/Layout.php`, `src/LayoutItemtype.php`)
`Layout extends CommonDBTM` stores label geometry (`width_mm`/`height_mm`/`font_choice`/optional custom TTF `Document`) and a JSON `elements` array (text/QR-code placements, edited via the GridStack-based `public/js/layout_editor.js`). `LayoutItemtype` is the join table associating a layout with one or more itemtypes, with an **independent** `is_default` flag per itemtype (`syncForLayout()` already enforces "only one default layout per itemtype, across all layouts" at the data layer). `Layout::showForm()`'s itemtype checklist reflects this: each whitelisted itemtype gets its own pair of checkboxes — `_itemtypes[$itemtype]` (is this layout used for this itemtype at all) and `_default_itemtypes[$itemtype]` (is it *the* default for this itemtype) — so one layout can simultaneously be the default for Computer and Monitor, etc. `syncItemtypesFromInput()` (called from `post_addItem()`/`post_updateItem()`) reads both arrays and calls `LayoutItemtype::syncForLayout()`.

### Print servers (`src/PrintServer.php`, `src/PrintServiceClient.php`)
`PrintServer extends CommonDBTM` stores a name/URL/encrypted API key (via GLPI's `GLPIKey`)/default printer name for a `print_service.py` instance. `PrintServiceClient` is the HTTP client: `test()`, `listPrinters()`, `printPdf()` — all authenticate via an `X-API-Key` header, no OAuth/JWT/bearer-token flow anywhere.

### PDF generation (`src/Pdf/`)
`LabelPdfBuilder` renders a `Layout`'s `elements` (text fields sourced from resolved item data, QR codes) into label-sized PDF pages via TCPDF, one page per item. `LabelElementMath` holds the mm↔pt/positioning math shared with the JS editor's mm↔px conventions.

### User preference (`src/UserPref.php`)
Remembers each user's last-used print server (`glpi_plugin_directlabelprinter_userprefs`, one row per user) so the print modal can default `dlp-server-select` without asking every time.

### `front/` controllers
Classic pre-GLPI11 style front scripts (`include("../../../inc/includes.php")`): `layout.php`/`layout.form.php` and `printserver.php`/`printserver.form.php` — standard GLPI `CommonDBTM` search/form pairs, nothing itemtype-specific beyond the usual `check()`/`display()` pattern.

### `tudo_old/` — abandoned alternate implementation
A parallel, never-wired-in attempt at a config page built on GLPI 11's `Glpi\Controller\AbstractController` + `#[Route]` attributes + Twig templates. **Not loaded by `setup.php`/`hook.php` and not part of the active plugin** — treat it as reference/scratch, not code to extend, unless explicitly asked to resurrect that approach.

### Database
Four tables, all created in `plugin_directlabelprinter_install()` and dropped in `_uninstall()`:
- `glpi_plugin_directlabelprinter_printservers` — one row per print server (`name`, `url`, encrypted `api_key`, `default_printer_name`).
- `glpi_plugin_directlabelprinter_layouts` — one row per label layout (`width_mm`, `height_mm`, `font_choice`, optional `custom_font_documents_id`, JSON `elements`).
- `glpi_plugin_directlabelprinter_layout_itemtype` — join table: `(plugin_directlabelprinter_layouts_id, itemtype)` unique, plus `is_default` (per-itemtype default flag, see Layouts above).
- `glpi_plugin_directlabelprinter_userprefs` — one row per user (`users_id` unique), remembered preferred print server.
- A legacy `glpi_plugin_directlabelprinter_auth` table (pre-redesign JWT credentials) is defensively dropped if present, both in `install()`'s upgrade branch and in `uninstall()`.

### Non-obvious gotchas
- New plugin static assets must live under `public/` (see "Static assets" above) — this is the single most common way to reintroduce a 404 in this plugin.
- Never reintroduce a plugin-level `Session::checkCSRF()` in the 3 `ajax/*.php` endpoints — the GLPI 11 kernel already validates CSRF from the `X-Glpi-Csrf-Token` header before the script runs (see "AJAX endpoints" above).
- `showMassiveActionsSubForm()`'s inline `<script>` must stay unwrapped by `DOMContentLoaded` (see Massive action section above) — the single-item button's script in `hook.php`, by contrast, correctly keeps the `DOMContentLoaded` wrapper since it's part of the initial page HTML.
- Any `json_encode()` call whose output is embedded directly into an inline `<script>` block (the `openPrintModal(...)` call sites in `hook.php` and `DirectLabelPrinterActions.php`) must pass `JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT` — item names/comments are user-controlled data.
- All strings/UI text in PHP and JS are heavily Portuguese (pt-BR) even though `__()`/gettext wrapping is used for translation — keep new user-facing strings wrapped in `__('...', 'directlabelprinter')` consistent with the existing code.

## Versioning and changelog

See `docs/versioning.md` for the full semver bump criteria (this plugin has no external consumer, so MAJOR follows classic semver from the GLPI admin's point of view — see that file's "Adaptação" section for concrete examples).

- **Commit messages**: always English, imperative, conventional-commit style (see `/commit`).
- **CHANGELOG.md entries and PR titles/descriptions**: always Portuguese (pt-BR), consistent with this plugin's UI strings.
- **Translation note**: a changelog/PR entry is never a literal copy or mechanical translation of the commit title — write it fresh in Portuguese from what the change actually does. Category headers (`Added`/`Changed`/`Fixed`/`Security`) stay in English per the Keep a Changelog convention; only the entry text itself is Portuguese.

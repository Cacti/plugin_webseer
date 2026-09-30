# GitHub Copilot Instructions

## Priority Guidelines

When generating code for this repository:

1. **Version Compatibility**: This is a Cacti plugin (`webseer`, "Service Monitor", version 3.3) targeting Cacti 1.2.24+
2. **Context Files**: Prioritize patterns and standards defined in this file (`.github/copilot-instructions.md`)
3. **Codebase Patterns**: When context files don't provide specific guidance, scan the codebase for established patterns
4. **Architectural Consistency**: Maintain plugin-based architecture extending Cacti core
5. **Code Quality**: Prioritize security, maintainability, and compatibility in all generated code

## Technology Stack

### Core Technologies
- **PHP**: 7.4+ baseline (targeting Cacti 1.2.x compatibility); avoid PHP 8.0+-only syntax unless the surrounding code already uses it
- **Platform**: Cacti Plugin Architecture (Cacti 1.2.24+) — web/service availability monitoring
- **Database**: MySQL/MariaDB via Cacti's DB abstraction layer

### Key Dependencies
- Cacti core framework (`api_plugin_*`, `db_*`)
- `classes/` supporting PHP classes; `ca-bundle.crt` for TLS verification of monitored endpoints

## Project Structure

```
webseer/                  # Repository root (install to plugins/webseer/ in Cacti)
├── classes/                # Supporting PHP classes (cURL, mxlookup)
├── includes/                  # Library/helper files, require_once'd from the entry points
│   ├── database.php             # Schema management: table defs + create/upgrade/drop helpers
│   ├── functions.php            # Shared plugin functions
│   ├── arrays.php               # Shared option/label arrays
│   └── constants.php            # Shared constants
├── locales/                      # Internationalization files
├── tests/                          # Test suite
├── ca-bundle.crt                     # CA bundle for HTTPS endpoint verification
├── poller_webseer.php                  # Background poller entry point (CLI)
├── remote.php                            # Remote poller support endpoint
├── webseer.php                             # Main viewer/administration UI
├── webseer_process.php                       # Check execution logic
├── webseer_proxies.php                         # Proxy administration
├── webseer_servers.php                           # Monitored server/service administration
├── INFO                                            # Plugin metadata (name, version, compat)
├── README.md
└── setup.php                                         # Plugin install/uninstall/upgrade hooks
```

## Naming Conventions

### Function Names
- **Plugin lifecycle/hook-registration functions** MUST be prefixed `plugin_webseer_`: `plugin_webseer_draw_navigation_text()`, `plugin_webseer_config_arrays()`, `plugin_webseer_poller_bottom()`.
- **Other functions** use the `webseer_` prefix: `webseer_replicate_out()`.
- Match the existing prefix used by the function you are editing; do not introduce a third naming scheme.

### Database Tables
All plugin tables are prefixed `plugin_webseer_`:

```
plugin_webseer_contacts, plugin_webseer_processes, plugin_webseer_proxies,
plugin_webseer_servers, plugin_webseer_servers_log, plugin_webseer_urls,
plugin_webseer_urls_log, plugin_webseer_url_log
```

## Code Style

### Indentation and Formatting
- **Tabs**: Use tabs (not spaces) for indentation throughout all PHP files.
- **Braces**: Opening brace on the same line for functions and control structures.
- **Spacing**: Space after control structure keywords (`if`, `foreach`, `while`).

### File Headers
ALL PHP files MUST include the standard GPL v2 license header used throughout this repository (see `setup.php`), crediting "The Cacti Group".

## Security Standards

### SQL Query Security
Use prepared statements (`db_execute_prepared()`, `db_fetch_row_prepared()`, etc.) for ALL queries with variables:

```php
// CORRECT
db_fetch_row_prepared('SELECT * FROM plugin_webseer_servers WHERE id = ?', array($id));

// WRONG
db_fetch_row("SELECT * FROM plugin_webseer_servers WHERE id = $id");
```

### Input Validation
Use `get_request_var()` / `get_filter_request_var()` for ALL user input, never raw `$_REQUEST`/`$_GET`/`$_POST`.

`get_filter_request_var()` (and its `gfrv()` shorthand, where available) called with only the
`$name` argument (no regex/filter as the 2nd/3rd argument) already validates the value as numeric
and returns it as a **string** -- it does not return an int, and it halts execution if the request
value is not numeric. Because of this, do NOT cast its output to `(int)` when the result is only
used for string output (e.g. `print`/`echo`, string concatenation, embedding in HTML/JS); the cast
is redundant. Only cast when the value is genuinely used in an integer/numeric context (e.g.
arithmetic, strict `===` comparisons).

### Output Escaping
Use `html_escape()` / `htmlspecialchars()` for ALL output of DB/user values in HTML context.

### TLS/Endpoint Verification
When performing HTTPS checks against monitored endpoints, use the bundled `ca-bundle.crt` for certificate verification rather than disabling TLS verification.

### Deserialization Safety
All `unserialize()` calls must use `array('allowed_classes' => false)`.

## Database Operations

All schema management lives in `includes/database.php` (the thold model), not in `setup.php`. `setup.php`'s
install/uninstall/upgrade paths `require_once($config['base_path'] . '/plugins/webseer/includes/database.php')`
and delegate to `plugin_webseer_setup_table()`, `webseer_upgrade_tables()`, and `plugin_webseer_drop_tables()`.
Each of the seven `plugin_webseer_*` tables is defined once in a `webseer_*_table_data()` helper and created
via `api_plugin_db_table_create('webseer', ...)` - preserve each table's engine (`plugin_webseer_processes`
is `MEMORY`, the rest `InnoDB`). On upgrade, `webseer_upgrade_tables()` refreshes each existing table via
`db_update_table()` (create fallback when missing), which replaces the old hand-written `ALTER TABLE ... ADD
COLUMN` migrations; only the historical `plugin_webseer_url_log` -> `plugin_webseer_urls_log` rename (which
`db_update_table()` can not express) is kept as a guarded pre-step. `plugin_webseer_upgrade()` also updates
the full `plugin_config` row (`version`, `name`, `author`, `webpage`) on a version change. Never write raw
`CREATE TABLE`/`ALTER TABLE` for a plugin-owned table.

## Internationalization

ALL user-facing strings MUST use `__()` with the `'webseer'` text domain.

## Plugin Architecture

### Plugin Hooks
Register hooks in `plugin_webseer_install()` (`setup.php`):

```php
api_plugin_register_hook('webseer', 'draw_navigation_text', 'plugin_webseer_draw_navigation_text', 'setup.php');
api_plugin_register_hook('webseer', 'config_arrays',        'plugin_webseer_config_arrays',        'setup.php');
api_plugin_register_hook('webseer', 'poller_bottom',        'plugin_webseer_poller_bottom',        'setup.php');
api_plugin_register_hook('webseer', 'replicate_out',        'webseer_replicate_out',               'setup.php');

api_plugin_register_realm('webseer', 'webseer.php,webseer_servers.php,webseer_proxies.php', __('Web Service Check Admin', 'webseer'), 1);
```

### Remote Poller Replication
`webseer_replicate_out()` supports syncing server/proxy definitions to remote pollers; keep new replicated fields consistent with this hook.

## Testing

Tests live in `tests/`. Use Pest PHP or PHPUnit; run `php -l` lint checks before committing.

## Best Practices

1. Always use prepared statements for anything involving variable input.
2. Verify TLS certificates against the bundled CA bundle rather than disabling verification.
3. Guard `unserialize()` calls with `allowed_classes => false`.
4. Wrap all user-facing strings with `__('text', 'webseer')`.

## Common Pitfalls to Avoid

```php
// WRONG - disabling TLS verification
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

// CORRECT - verify against the bundled CA bundle
curl_setopt($ch, CURLOPT_CAINFO, dirname(__FILE__) . '/ca-bundle.crt');
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
```

## Version Control

Document all changes in `CHANGELOG.md`; use descriptive commit messages referencing issue/PR numbers when applicable.

## CI & Dependency Baselines

- Do not commit a `composer.json` or `composer.lock` in this plugin's own repo root — the shared CI workflow installs Pest/dev dependencies into Cacti's own Composer-managed vendor tree (checked out alongside the plugin). Use Cacti's `composer.json`, not a plugin-local one.
- Do not add a plugin-local `.phpstan.neon`/`phpstan.neon` or `.php-cs-fixer.php`/`.php-cs-fixer.dist.php` — lint/static-analysis steps run against Cacti's own config from the Cacti core checkout, targeting this plugin's directory. Use the Cacti version, not a plugin-local config.
- Prefer Cacti's `cacti_count()`/`cacti_sizeof()` wrappers over the raw `count()`/`sizeof()` builtins in new or edited code.

## Internationalization (i18n)

- Translatable strings are managed with GNU gettext via `locales/build_gettext.sh`. `locales/po/cacti.pot` is the source template; Weblate owns syncing the per-language `.po`/`.mo` files from it.
- **Never commit the per-language `.po` or compiled `.mo` files** (`locales/po/*.po`, `locales/LC_MESSAGES/*.mo`) in a plugin PR. Weblate is the sole owner of those catalogs, and regenerating them here produces spurious diffs and merge conflicts. `locales/po/cacti.pot` is the ONLY translation artifact a PR may add or modify.
- When a pull request adds or changes a string wrapped in `__()`/`__n()`/`__esc()`/`__x()`/`__xn()`/`__gettext()`, run `locales/build_gettext.sh` before pushing and stage `locales/po/cacti.pot` only. `build_gettext.sh` also rewrites the `.po`/`.mo` files as a side effect; revert those before committing (`git checkout -- locales/po/*.po locales/LC_MESSAGES`), or run only the `xgettext` step that targets `cacti.pot`.

## References

- [Cacti main repo](https://github.com/Cacti/cacti/tree/1.2.x)
- [Cacti Documentation](https://www.github.com/Cacti/documentation)
- `README.md` for feature descriptions
- `CHANGELOG.md` for version history

## Security & Quality Conventions

These conventions apply across the Cacti plugin fleet and should be followed whenever touching
existing code or adding new code, not just in dedicated cleanup passes:

- **No hardcoded third-party hosts.** Never hardcode a third-party IP address, hostname, or URL
  in plugin code (even for tooling/download helpers). Expose it as a plugin setting instead, with
  secure-by-default values (e.g. an SSL-verification setting that defaults to verify-on).
- **Prepared statements over `db_qstr()`.** Build dynamic `WHERE` clauses using the
  `$sql_where`/`$sql_params` prepared-statement pattern, not string concatenation via `db_qstr()`.
- **Use `html_escape_request_var()`.** Prefer it over the `html_escape(get_request_var(...))` call
  chain.
- **Harden `unserialize()`.** Always pass `['allowed_classes' => false]` as the second argument.
- **i18n text domain.** Every `__()`/`__esc()` call must include this plugin's text domain as the
  final argument, except when deliberately comparing against a literal, untranslated Cacti-core
  label.
- **File inclusion uses `require`/`require_once`.** Always use `require`/`require_once` (never
  `include`/`include_once`) so a missing dependency fails fast and loudly. Keep library/helper files
  under `includes/` (e.g. `database.php`, `functions.php`, `arrays.php`, `constants.php`) and
  reference them from that path; entry points (the `webseer*.php` pages, `poller_webseer.php`,
  `remote.php`, `setup.php`) stay in the plugin root. The one deliberate exception is a genuinely
  optional cross-plugin include already guarded by an enablement check (e.g. the maint plugin).
- **Plugin schema management.** Keep every schema function (table definitions, create, upgrade,
  drop) in `includes/database.php` (the thold model), required from `setup.php`. Create with
  `api_plugin_db_table_create()`; refresh an existing plugin table with `db_update_table($table, $data)`
  from the SAME definition (create fallback when missing). Prefer this over
  `api_plugin_db_add_column()`/`api_plugin_db_drop_*`/raw `CREATE TABLE`/`ALTER`; a true table/column
  rename that `db_update_table()` can not express stays a guarded pre-step. Both
  `api_plugin_db_table_create()` and `db_update_table()` are idempotent.
- **Plugin upgrade bookkeeping.** On a version change, update the FULL `plugin_config` row
  (`version`, `name`, `author`, `webpage`) from the INFO file, not just the version column.
- **PHPDoc shape.** Every function gets a PHPDoc block: a one-line description, a blank comment
  line, `@param` lines, a blank comment line, then `@return`. Infer parameter/return types from
  actual usage; don't change the function's real type-hints in the same pass (let static analysis
  flag mismatches separately). Skip vendored third-party library files.

## File manifest & upgrade pruning

The plugin ships a root `manifest.json` with three arrays: `tombstones` (files/directories older versions shipped that have since moved or been removed), `expected` (the top-level files and directories that ship today, directories written with a trailing `/`), and `whitelist` (paths holding user data that must never be touched). Keep `expected` current: CI runs `tests/bin/validate-manifest.php`, which fails on any drift between `expected` and the real top-level tree (it ignores `tests/`, `.git*`, and whitelisted paths). Custom customer CSS/theme files belong in `expected`, and stylesheets live in `css/` (not `themes/`). On upgrade, `plugin_webseer_prune_files()` deletes the tombstoned paths and the dev-only `tests/` tree, leaves `whitelist` and `.git*` alone, and logs (without removing) any top-level entry the manifest does not account for. As a safety measure it refuses any tombstone that resolves outside the plugin directory (a tampered manifest.json) and logs a warning for any file or directory it cannot remove. When you move or delete a shipped file, add its old path to `tombstones` and update `expected` in the same change.

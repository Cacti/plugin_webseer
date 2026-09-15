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
├── classes/                # Supporting PHP classes
├── includes/                  # Shared includes
├── locales/                      # Internationalization files
├── tests/                          # Test suite
├── ca-bundle.crt                      # CA bundle for HTTPS endpoint verification
├── poller_webseer.php                    # Background poller entry point (CLI)
├── remote.php                              # Remote poller support endpoint
├── webseer.php                               # Main viewer/administration UI
├── webseer_process.php                         # Check execution logic
├── webseer_proxies.php                           # Proxy administration
├── webseer_servers.php                             # Monitored server/service administration
├── INFO                                              # Plugin metadata (name, version, compat)
├── README.md
└── setup.php                                           # Plugin install/uninstall/upgrade hooks
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

### Output Escaping
Use `html_escape()` / `htmlspecialchars()` for ALL output of DB/user values in HTML context.

### TLS/Endpoint Verification
When performing HTTPS checks against monitored endpoints, use the bundled `ca-bundle.crt` for certificate verification rather than disabling TLS verification.

### Deserialization Safety
All `unserialize()` calls must use `array('allowed_classes' => false)`.

## Database Operations

Use Cacti's `db_*`/`db_*_prepared()` functions; keep schema creation/upgrades in `setup.php`'s install/upgrade lifecycle.

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

## References

- [Cacti main repo](https://github.com/Cacti/cacti/tree/1.2.x)
- [Cacti Documentation](https://www.github.com/Cacti/documentation)
- `README.md` for feature descriptions
- `CHANGELOG.md` for version history

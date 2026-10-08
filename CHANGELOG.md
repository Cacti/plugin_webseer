# ChangeLog

--- 3.3 ---

* security: Replace the confirmation-page Cancel buttons' inline `onClick='cactiReturnTo()'` with the CSP-safe `cactiReturnTo` class so the pages no longer trip Cacti's Content-Security-Policy script-src-attr directive; see the README for the one-time `applySkin()` snippet needed when running Cacti below 1.2.31 (1.2.31+ binds the class automatically)
* compat: Lower the minimum Cacti compatibility floor from 1.2.32 to 1.2.29
* issue: Re-introduce the `plugin_webseer_contacts` `user_id_type` UNIQUE key (on `user_id`, `type`) that Cacti 1.2.29-1.2.31's `db_update_table()` silently dropped - those releases rebuild a table's indexes only from its `keys` definition and ignore `unique_keys`. The repair runs automatically on upgrade when running one of those releases and is a no-op on 1.2.32+. WARNING: if duplicate `(user_id, type)` contact rows were inserted while the constraint was absent, re-adding the key fails; the upgrade logs the affected table and leaves the recorded version unchanged so it retries on the next request. To recover, de-duplicate `plugin_webseer_contacts` (keep a single row per `user_id`/`type`) and re-run the upgrade. Sites already upgraded to 1.2.32+ that lost the key on an earlier 1.2.29-1.2.31 run are not auto-repaired
* feature: Add a root `manifest.json` and an upgrade-time file prune that removes dev-only bundled files (e.g. `tests/`) on a version change, refuses any path resolving outside the plugin directory, and logs anything it cannot remove
* refactor: Move all schema management into includes/database.php (the thold model) and manage the seven plugin_webseer_* tables through Cacti's plugin table API - create with api_plugin_db_table_create() and refresh existing tables with db_update_table() from a single shared definition, replacing the raw CREATE TABLE/version-gated ALTER TABLE migrations (the historical plugin_webseer_url_log -> plugin_webseer_urls_log rename is kept as a guarded pre-step); the upgrade path now also updates the full plugin_config row. Also switches every file inclusion from include/include_once to require/require_once
* dev: Measure CI coverage with xdebug instead of pcov so the plugin's own sources are instrumented (pcov auto-scopes to the Composer root and skipped cacti/plugins/, leaving the patch-coverage gate with nothing to measure)
* dev: Enforce patch coverage of changed lines in CI and remove the inert COMPOSER_ROOT_VERSION env from the Pest step
* issue: Fix bug where SERVERS/URLS refresh from a master server could pass a false base64_decode() result into unserialize(), and where refresh_urls() shared refresh_servers()'s bug of not narrowing db_fetch_row()/post() results before use

* issue: Fix bug where the 'gzip' compression option was passed to the cURL class as a literal string instead of the expected WEBSEER_COMPRESSION_GZIP constant, silently disabling gzip compression on 12 call sites

* issue: Fix bug where the bulk-actions forms for Service Checks and Servers did not validate the drp_action request value against the known action set before using it to index the actions-menu array

* issue: Fix cURL class's proxy_port property being set but never read (correct properties are proxy_http_port/proxy_https_port)

--- 3.2 ---

* security: Add a version-safe CSP nonce (`plugin_webseer_csp_nonce()`) to every inline `<script>` tag so pages stay compatible with Cacti's Content-Security-Policy nonce enforcement, while falling back cleanly on older Cacti releases that lack the `CactiSecureHeaders` class
* security: Identify remote webseer servers by their real connection address, not a forwarded header

* issue: Correct issue with enable/disable service checks and servers

* issue: Correct issues with page refresh

* issue: Correct issues with tooltip text not being appropriate

* issue: Separate the CHANGELOG from the README

* issue: Properly assign background colors for errors in logs

* feature: Add pagination to the Log history of both servers and service check

* feature: Show Log History in it's own tab

* feature: Add Notification List support

--- 3.1 ---

* issue#41: Maintenance schedule check add

* issue#42: Search string fix

* issue#44: Webseer and CURL

* issue#46: Unable to delete proxy server

* issue: When creating a service check after save, return to that service check

* feature: Convert images to Glyphs

* feature: Support PHP 8.x

* feature: Minimum Cacti version 1.2.24

* feature: Remove webseer_edit.php and webseer_servers_edit.php

* feature: Prompt for Removal, Enable, Disable, and Duplicate Actions

--- 3.0 ---

* issue#18: Some sites require compression to properly redirect

* issue#26: Undefined variable 'key' error in webseer_proxies.php

* issue#27: Notification email sent twice with different info

* issue#28: Notification email not sent

* issue#31: Undefined variable 'del' error in webseer.php

* issue#34: Undefined index 'search' when saving Webseer Server

* feature: PHP 7.2 compatibility

--- 2.0 ---

* issue#10: Check shows as Down even though site is up if there is no search
  string

* issue#12: Add proxy support to URL's

* issue#16: WebSeer not notifying users in dropdown list

* issue#19: Enabled does not display correctly when viewing and editing a server

* issue#20: Redirection results in permanently Moved errors with proxy use

* issue#21: Long processing time for DELETE queries

* issue#22: Make additional room on page for long URL's

--- 1.1 ---

* feature: Changes to facilitate i18n by contributors

--- 1.0 ---

* issue#4: Resolving issues with Servers display

* issue#5: Remote incorrectly assumes communications from the CLI

* issue#6: Resolving issues with Servers display

* issue#9: Warning message about empty needle in file

* feature: Refactor database schema to match Cacti standards

* feature: Add duplication of Service Check

* feature: Automatically register Server

* feature: Internationalization of GUI

* feature: Enhance GUI to comform to modern Cacti

--- 0.1 ---

* Initial public release

-----------------------------------------------
Copyright (c) 2004-2026 - The Cacti Group, Inc.


<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * Integration coverage for the plugin lifecycle in setup.php:
 * plugin_webseer_install()/plugin_webseer_uninstall() side effects, and the
 * plugin_webseer_upgrade() version-gated schema migration path.
 *
 * db_execute()/db_execute_prepared() calls and hook/realm registrations are
 * captured via the fixtures/call logs declared in tests/bootstrap-unit.php.
 */

require_once dirname(__DIR__, 2) . '/setup.php';
// Define webseer_upgrade_tables() from the real checkout so
// plugin_webseer_upgrade() runs while base_path is sandboxed below.
require_once dirname(__DIR__, 2) . '/includes/database.php';

// tests/Pest.php's beforeEach isn't reliably discovered under this plugin's CI
// invocation (pest run from the cacti root via --configuration=plugins/...),
// so register it here too to guarantee a clean call log between tests in this file.
beforeEach(function () {
	webseer_test_reset_db_mocks();

	// Sandbox base_path so the version-drift branch runs
	// plugin_webseer_prune_files() against a throwaway tree with no
	// manifest.json (prune no-ops), never the real checkout. The temp tree
	// carries a copy of the real INFO (so plugin_webseer_version() still
	// matches) and an empty includes/database.php the upgrade's top-level
	// require_once can load harmlessly.
	$GLOBALS['__webseer_base_restore'] = $GLOBALS['config']['base_path'];
	$base = sys_get_temp_dir() . '/webseer-itest-' . uniqid();
	mkdir($base . '/plugins/webseer/includes', 0777, true);
	copy(dirname(__DIR__, 2) . '/INFO', $base . '/plugins/webseer/INFO');
	file_put_contents($base . '/plugins/webseer/includes/database.php', "<?php\n");
	$GLOBALS['config']['base_path'] = $base;
});

afterEach(function () {
	if (isset($GLOBALS['__webseer_base_restore'])) {
		$GLOBALS['config']['base_path'] = $GLOBALS['__webseer_base_restore'];
	}
});

it('registers its hooks and admin realm on install', function () {
	plugin_webseer_install();

	$hooks = array_column($GLOBALS['__test_hook_calls'], 'hook');

	expect($hooks)->toContain('draw_navigation_text');
	expect($hooks)->toContain('config_arrays');
	expect($hooks)->toContain('poller_bottom');
	expect($hooks)->toContain('replicate_out');
	expect($GLOBALS['__test_realm_calls'])->toHaveCount(1);
	expect($GLOBALS['__test_realm_calls'][0]['files'])->toBe('webseer.php,webseer_servers.php,webseer_proxies.php');
});

it('creates every plugin table on install', function () {
	plugin_webseer_install();

	$created = array_column(
		array_filter($GLOBALS['__test_db_calls'], fn ($call) => $call['fn'] === 'api_plugin_db_table_create'),
		'sql'
	);

	foreach ([
		'plugin_webseer_servers',
		'plugin_webseer_servers_log',
		'plugin_webseer_urls',
		'plugin_webseer_urls_log',
		'plugin_webseer_processes',
		'plugin_webseer_contacts',
		'plugin_webseer_proxies',
	] as $table) {
		expect($created)->toContain($table);
	}
});

it('drops every plugin table on uninstall', function () {
	plugin_webseer_uninstall();

	$dropped = array_column(
		array_filter($GLOBALS['__test_db_calls'], fn ($call) => $call['fn'] === 'api_plugin_drop_table'),
		'sql'
	);

	foreach ([
		'plugin_webseer_servers',
		'plugin_webseer_servers_log',
		'plugin_webseer_urls',
		'plugin_webseer_urls_log',
		'plugin_webseer_proxies',
		'plugin_webseer_processes',
		'plugin_webseer_contacts',
	] as $table) {
		expect($dropped)->toContain($table);
	}
});

it('does not migrate the schema when the installed version already matches', function () {
	webseer_test_mock_db('db_fetch_cell', 'plugin_config', plugin_webseer_version()['version']);

	plugin_webseer_upgrade();

	$writes = array_filter($GLOBALS['__test_db_calls'], fn ($call) => $call['fn'] !== 'db_fetch_cell');

	expect($writes)->toBe([]);
});

it('provisions missing tables via the plugin table API on upgrade', function () {
	webseer_test_mock_db('db_fetch_cell', 'plugin_config', '1.0');

	plugin_webseer_upgrade();

	$created = array_column(
		array_filter($GLOBALS['__test_db_calls'], fn ($call) => $call['fn'] === 'api_plugin_db_table_create'),
		'sql'
	);

	expect($created)->toContain('plugin_webseer_contacts');
	expect($created)->toContain('plugin_webseer_proxies');
});

it('refreshes existing tables via db_update_table instead of recreating them on upgrade', function () {
	webseer_test_mock_db('db_fetch_cell', 'plugin_config', '2.5');
	webseer_test_mock_db('db_table_exists', 'plugin_webseer_', true);

	plugin_webseer_upgrade();

	$created = array_column(
		array_filter($GLOBALS['__test_db_calls'], fn ($call) => $call['fn'] === 'api_plugin_db_table_create'),
		'sql'
	);
	$updated = array_column(
		array_filter($GLOBALS['__test_db_calls'], fn ($call) => $call['fn'] === 'db_update_table'),
		'sql'
	);

	expect($created)->toBe([]);
	expect($updated)->toContain('plugin_webseer_contacts');
	expect($updated)->toContain('plugin_webseer_proxies');
});

it('records the new version against plugin_config after a migration', function () {
	webseer_test_mock_db('db_fetch_cell', 'plugin_config', '1.0');

	plugin_webseer_upgrade();

	$version_updates = array_filter(
		$GLOBALS['__test_db_calls'],
		fn ($call) => $call['fn'] === 'db_execute_prepared' && strpos($call['sql'], 'UPDATE plugin_config') !== false
	);

	expect($version_updates)->not->toBeEmpty();
});

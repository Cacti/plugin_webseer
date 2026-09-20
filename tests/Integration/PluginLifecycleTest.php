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

// tests/Pest.php's beforeEach isn't reliably discovered under this plugin's CI
// invocation (pest run from the cacti root via --configuration=plugins/...),
// so register it here too to guarantee a clean call log between tests in this file.
beforeEach(function () {
	webseer_test_reset_db_mocks();
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

	$sql = implode("\n", array_column($GLOBALS['__test_db_calls'], 'sql'));

	foreach ([
		'plugin_webseer_servers',
		'plugin_webseer_servers_log',
		'plugin_webseer_urls',
		'plugin_webseer_urls_log',
		'plugin_webseer_processes',
		'plugin_webseer_contacts',
		'plugin_webseer_proxies',
	] as $table) {
		expect($sql)->toContain("`$table`");
	}
});

it('drops every plugin table on uninstall', function () {
	plugin_webseer_uninstall();

	$sql = implode("\n", array_column($GLOBALS['__test_db_calls'], 'sql'));

	foreach ([
		'plugin_webseer_servers',
		'plugin_webseer_servers_log',
		'plugin_webseer_urls',
		'plugin_webseer_urls_log',
		'plugin_webseer_proxies',
		'plugin_webseer_processes',
		'plugin_webseer_contacts',
	] as $table) {
		expect($sql)->toContain('DROP TABLE IF EXISTS ' . $table);
	}
});

it('does not migrate the schema when the installed version already matches', function () {
	webseer_test_mock_db('db_fetch_cell', 'plugin_config', plugin_webseer_version()['version']);

	plugin_webseer_upgrade();

	$writes = array_filter($GLOBALS['__test_db_calls'], fn ($call) => $call['fn'] !== 'db_fetch_cell');

	expect($writes)->toBe([]);
});

it('creates the contacts table when upgrading from a pre-1.1 install', function () {
	webseer_test_mock_db('db_fetch_cell', 'plugin_config', '1.0');

	plugin_webseer_upgrade();

	$sql = implode("\n", array_column($GLOBALS['__test_db_calls'], 'sql'));

	expect($sql)->toContain('CREATE TABLE IF NOT EXISTS `plugin_webseer_contacts`');
	expect($sql)->toContain('CREATE TABLE `plugin_webseer_proxies`');
});

it('does not recreate already-migrated tables when upgrading from a post-2.0 install', function () {
	webseer_test_mock_db('db_fetch_cell', 'plugin_config', '2.5');

	plugin_webseer_upgrade();

	$sql = implode("\n", array_column($GLOBALS['__test_db_calls'], 'sql'));

	expect($sql)->not->toContain('CREATE TABLE IF NOT EXISTS `plugin_webseer_contacts`');
	expect($sql)->not->toContain('CREATE TABLE `plugin_webseer_proxies`');
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

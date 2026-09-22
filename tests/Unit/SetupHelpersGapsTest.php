<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

/*
 * Unit coverage for plugin_webseer_check_config(), plugin_webseer_setup_table(),
 * plugin_webseer_poller_bottom(), plugin_webseer_config_arrays(), and
 * webseer_replicate_out() in setup.php.
 */

beforeAll(function () {
	require_once __DIR__ . '/../../setup.php';

	$stubLibraryPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'webseer-test-lib-stub';

	if (!is_dir($stubLibraryPath)) {
		mkdir($stubLibraryPath, 0777, true);
	}

	file_put_contents($stubLibraryPath . '/database.php', "<?php\n");

	$GLOBALS['config']['library_path'] = $stubLibraryPath;
});

beforeEach(function () {
	webseer_test_reset_db_mocks();
	$GLOBALS['__test_exec_calls'] = [];
});

it('always reports success and updates plugin_config to the current version', function () {
	$info = plugin_webseer_version();

	expect(plugin_webseer_check_config())->toBeTrue();

	$updates = array_filter($GLOBALS['__test_db_calls'], function ($call) {
		return $call['fn'] === 'db_execute_prepared' && stripos($call['sql'], 'UPDATE plugin_config') !== false;
	});

	expect($updates)->not->toBeEmpty();
});

it('creates every webseer table via raw CREATE TABLE statements', function () {
	plugin_webseer_setup_table();

	$creates = array_filter($GLOBALS['__test_db_calls'], function ($call) {
		return $call['fn'] === 'db_execute' && stripos($call['sql'], 'CREATE TABLE') !== false;
	});

	expect($creates)->toHaveCount(7);
});

it('dispatches the background poller with the php binary from config', function () {
	plugin_webseer_poller_bottom();

	expect($GLOBALS['__test_exec_calls'])->toHaveCount(1);
	expect($GLOBALS['__test_exec_calls'][0]['command'])->toBe('php');
	expect($GLOBALS['__test_exec_calls'][0]['args'])->toContain('poller_webseer.php');
});

it('adds the Service Checks menu entry and skips the version check off relevant pages', function () {
	global $menu;

	$menu = [__('Management') => []];
	webseer_test_set_current_page('graphs.php');

	plugin_webseer_config_arrays();

	expect($menu[__('Management')])->toHaveKey('plugins/webseer/webseer.php');

	$updates = array_filter($GLOBALS['__test_db_calls'], function ($call) {
		return $call['fn'] === 'db_execute_prepared' && stripos($call['sql'], 'UPDATE plugin_config') !== false;
	});

	expect($updates)->toBeEmpty();
});

it('runs the version check when on a relevant page', function () {
	global $menu;

	$menu = [__('Management') => []];
	webseer_test_set_current_page('webseer.php');

	plugin_webseer_config_arrays();

	$updates = array_filter($GLOBALS['__test_db_calls'], function ($call) {
		return $call['fn'] === 'db_execute_prepared' && stripos($call['sql'], 'UPDATE plugin_config') !== false;
	});

	expect($updates)->not->toBeEmpty();
});

it('does not replicate when the class is not "all"', function () {
	$data = ['remote_poller_id' => 1, 'rcnn_id' => 2, 'class' => 'poller'];

	$result = webseer_replicate_out($data);

	expect($result)->toBe($data);
});

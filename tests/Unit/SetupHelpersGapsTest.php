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

	$updates = array_values(array_filter($GLOBALS['__test_db_calls'], function ($call) {
		return $call['fn'] === 'db_execute_prepared'
			&& stripos($call['sql'], 'UPDATE plugin_config SET') !== false
			&& stripos($call['sql'], 'name = ?') !== false;
	}));

	expect($updates)->toHaveCount(1);
	expect($updates[0]['params'])->toBe([
		$info['version'],
		$info['longname'],
		$info['author'],
		$info['homepage'],
		$info['name'],
	]);
});

it('creates every webseer table via raw CREATE TABLE statements', function () {
	plugin_webseer_setup_table();

	$createdTables = array_values(array_map(function ($call) {
		preg_match('/CREATE TABLE(?: IF NOT EXISTS)? `([^`]+)`/i', $call['sql'], $matches);
		return $matches[1] ?? null;
	}, array_filter($GLOBALS['__test_db_calls'], function ($call) {
		return $call['fn'] === 'db_execute' && stripos($call['sql'], 'CREATE TABLE') !== false;
	})));

	expect($createdTables)->toEqualCanonicalizing([
		'plugin_webseer_servers',
		'plugin_webseer_servers_log',
		'plugin_webseer_urls',
		'plugin_webseer_urls_log',
		'plugin_webseer_processes',
		'plugin_webseer_contacts',
		'plugin_webseer_proxies',
	]);
});

it('dispatches the background poller with the php binary from config', function () {
	$GLOBALS['__test_config_options']['path_php_binary'] = '/usr/bin/php';

	plugin_webseer_poller_bottom();

	expect($GLOBALS['__test_exec_calls'])->toHaveCount(1);
	expect($GLOBALS['__test_exec_calls'][0]['command'])->toBe('/usr/bin/php');
	expect($GLOBALS['__test_exec_calls'][0]['args'])->toContain('poller_webseer.php');
});

it('falls back to a bare "php" command when path_php_binary is not configured', function () {
	$GLOBALS['__test_config_options']['path_php_binary'] = '';

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
	expect($GLOBALS['__test_replicate_out_table_calls'] ?? [])->toBeEmpty();
});

it('replicates every owned table when the class is "all"', function () {
	$data = ['remote_poller_id' => 1, 'rcnn_id' => 2, 'class' => 'all'];
	$GLOBALS['__test_replicate_out_table_calls'] = [];

	$result = webseer_replicate_out($data);

	expect($result)->toBe($data);

	$tables = array_column($GLOBALS['__test_replicate_out_table_calls'], 'table');

	expect($tables)->toEqualCanonicalizing([
		'plugin_webseer_contacts',
		'plugin_webseer_proxies',
		'plugin_webseer_servers',
		'plugin_webseer_urls',
	]);

	foreach ($GLOBALS['__test_replicate_out_table_calls'] as $call) {
		expect($call['rcnn_id'])->toBe(2);
		expect($call['remote_poller_id'])->toBe(1);
	}
});

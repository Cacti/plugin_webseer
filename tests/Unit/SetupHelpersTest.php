<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * Behavioral coverage for plugin_webseer_draw_navigation_text() and
 * plugin_webseer_version() in setup.php: breadcrumb registration for the
 * plugin's admin pages, and the INFO-file-backed version accessor used by
 * the installer/upgrader.
 */

require_once dirname(__DIR__, 2) . '/setup.php';

it('registers breadcrumb entries for every webseer admin page', function () {
	$nav = plugin_webseer_draw_navigation_text([]);

	expect($nav)->toHaveKeys([
		'webseer.php:',
		'webseer.php:edit',
		'webseer.php:save',
		'webseer_servers.php:',
		'webseer_servers.php:edit',
		'webseer_servers.php:save',
		'webseer_proxies.php:',
		'webseer_proxies.php:edit',
		'webseer_proxies.php:save',
	]);
});

it('preserves navigation entries passed in from other plugins', function () {
	$nav = plugin_webseer_draw_navigation_text(['host.php:' => ['title' => 'Devices']]);

	expect($nav)->toHaveKey('host.php:');
	expect($nav['host.php:'])->toBe(['title' => 'Devices']);
});

it('maps each breadcrumb entry back to the index page', function () {
	$nav = plugin_webseer_draw_navigation_text([]);

	foreach ($nav as $entry) {
		expect($entry['mapping'])->toBe('index.php:');
	}
});

it('reads the plugin name and version from the INFO file', function () {
	$info = plugin_webseer_version();

	expect($info)->toHaveKey('name');
	expect($info)->toHaveKey('version');
	expect($info['name'])->toBe('webseer');
});

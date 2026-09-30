<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 |                                                                         |
 | This program is distributed in the hope that it will be useful,         |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of          |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the           |
 | GNU General Public License for more details.                            |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

/**
 * Return the CSP nonce attribute for inline <script> tags, safely across
 * Cacti versions. Newer Cacti releases enforce a Content-Security-Policy that
 * requires a per-request nonce on parser-inserted scripts; older releases lack
 * the CactiSecureHeaders class, so this returns an empty string there.
 *
 * @return string The nonce attribute when supported, otherwise empty string.
 */
function plugin_webseer_csp_nonce(): string {
	if (class_exists('CactiSecureHeaders')) {
		return CactiSecureHeaders::getNonceAttribute();
	}

	return '';
}

require_once(__DIR__ . '/includes/constants.php');
require_once(__DIR__ . '/includes/arrays.php');

/**
 * Registers this plugin's Cacti hooks (navigation breadcrumbs, config
 * arrays/menu, poller_bottom, data source replication) and its
 * webseer.php/webseer_servers.php/webseer_proxies.php realm, then
 * creates the plugin's database tables. Invoked by the Cacti plugin
 * framework when the plugin is installed/enabled.
 *
 * @return void
 */
function plugin_webseer_install() {
	global $config;

	api_plugin_register_hook('webseer', 'draw_navigation_text', 'plugin_webseer_draw_navigation_text', 'setup.php');
	api_plugin_register_hook('webseer', 'config_arrays',        'plugin_webseer_config_arrays',        'setup.php');
	api_plugin_register_hook('webseer', 'poller_bottom',        'plugin_webseer_poller_bottom',        'setup.php');
	api_plugin_register_hook('webseer', 'replicate_out',        'webseer_replicate_out',               'setup.php');

	api_plugin_register_realm('webseer', 'webseer.php,webseer_servers.php,webseer_proxies.php', __('Web Service Check Admin', 'webseer'), 1);

	require_once($config['base_path'] . '/plugins/webseer/includes/database.php');

	plugin_webseer_setup_table();
}

/**
 * Drops all of this plugin's database tables. Invoked by the Cacti
 * plugin framework when the plugin is uninstalled.
 *
 * @return void
 */
function plugin_webseer_uninstall() {
	global $config;

	require_once($config['base_path'] . '/plugins/webseer/includes/database.php');

	plugin_webseer_drop_tables();
}

/**
 * Here we will check to ensure everything is configured
 *
 * Runs any pending database schema upgrades for this plugin. Invoked by
 * plugin_webseer_config_arrays() (the 'config_arrays' hook) only when
 * the current page is index.php, plugins.php, or webseer.php - not on
 * every page load.
 *
 * @return bool Always true.
 */
function plugin_webseer_check_config() {
	// Here we will check to ensure everything is configured
	plugin_webseer_upgrade();

	return true;
}

/**
 * Here we will upgrade to the newest version
 *
 * Applies version-gated schema migrations (adding the contacts and
 * proxies tables, renaming the URL log table, adding compression/
 * notify-format/poller-id columns) and updates the recorded plugin
 * version/realm file list, based on comparing the installed version
 * against the current INFO file version. If webseer_upgrade_tables()
 * reports a failed compensating UNIQUE-key repair, the recorded version is
 * left unchanged so the repair retries on a later request. Called from
 * plugin_webseer_check_config(), which is itself only invoked when the
 * current page is index.php, plugins.php, or webseer.php.
 *
 * @return bool Always true.
 *
 * @global array $config Cacti global configuration array (declared but
 *                       not directly used here).
 */
function plugin_webseer_upgrade() {
	// Here we will upgrade to the newest version
	global $config;

	require_once($config['base_path'] . '/plugins/webseer/includes/database.php');

	$info = plugin_webseer_version();

	if (!isset($info['version'], $info['longname'], $info['author'], $info['homepage'], $info['name'])) {
		cacti_log('ERROR: webseer plugin INFO file is missing required fields, skipping upgrade check', false, 'WEBSEER');

		return true;
	}

	$new  = $info['version'];
	$old  = db_fetch_cell('SELECT version FROM plugin_config WHERE directory="webseer"');

	if ($new != $old) {
		// Refresh the schema from the shared definition in includes/database.php:
		// create any missing tables, db_update_table() diff the rest. The historical
		// plugin_webseer_url_log -> plugin_webseer_urls_log rename (and the column
		// additions that were previously hand-written ALTERs) are handled there.
		if (!webseer_upgrade_tables()) {
			// A compensating repair (re-adding a UNIQUE key that Cacti
			// 1.2.29-1.2.31 dropped) failed - almost always because of
			// pre-existing duplicate rows. Leave the stored version unchanged
			// so the upgrade retries on the next request once the operator
			// de-duplicates; the actionable details are already in the Cacti log.
			return true;
		}

		db_execute_prepared('UPDATE plugin_config SET
			version = ?, name = ?, author = ?, webpage = ?
			WHERE directory = ?',
			[
				$info['version'],
				$info['longname'],
				$info['author'],
				$info['homepage'],
				$info['name']
			]
		);

		db_execute_prepared('UPDATE plugin_realms
			SET file = ?
			WHERE file LIKE "%webseer.php%"',
			['webseer.php,webseer_servers.php,webseer_proxies.php']);

		api_plugin_register_hook('webseer', 'replicate_out', 'webseer_replicate_out', 'setup.php', '1');

		plugin_webseer_prune_files();
	}

	return true;
}

/**
 * Reads this plugin's version/author metadata from its INFO file.
 * Invoked by the Cacti plugin framework to display plugin information,
 * and called directly by plugin_webseer_upgrade() and
 * poller_webseer.php's display_version().
 *
 * @return array The plugin's INFO file 'info' section (name, version,
 *               author, etc.).
 *
 * @global array $config Cacti global configuration array; used to
 *                       locate the plugin's INFO file.
 */
function plugin_webseer_version() {
	global $config;
	$info = parse_ini_file($config['base_path'] . '/plugins/webseer/INFO', true);

	return isset($info['info']) && is_array($info['info']) ? $info['info'] : [];
}

/**
 * Launches a background poller_webseer.php process to run the
 * configured service checks. Invoked by the Cacti plugin framework via
 * the 'poller_bottom' hook at the end of each poller cycle.
 *
 * @return void
 *
 * @global array $config Cacti global configuration array; used to
 *                       resolve the PHP binary and this plugin's poller
 *                       script path.
 */
function plugin_webseer_poller_bottom() {
	global $config;

	require_once($config['library_path'] . '/database.php');

	$command_string = trim(read_config_option('path_php_binary'));

	// If its not set, just assume its in the path
	if (trim($command_string) == '') {
		$command_string = 'php';
	}
	$extra_args = ' -q ' . $config['base_path'] . '/plugins/webseer/poller_webseer.php';

	exec_background($command_string, $extra_args);
}

/**
 * Adds this plugin's 'Service Checks' entry to the Management menu, and
 * triggers a schema-upgrade check when the currently displayed page is
 * exactly index.php, plugins.php, or webseer.php (not the servers or
 * proxies pages). Invoked by the Cacti plugin framework via the
 * 'config_arrays' hook.
 *
 * @return void
 *
 * @global array $menu                       Cacti's registered admin
 *                                           menu; a 'Service Checks'
 *                                           entry is added under
 *                                           'Management'.
 * @global array $user_auth_realms           Reserved/declared for parity
 *                                           with other hook
 *                                           implementations; not used
 *                                           directly here.
 * @global array $user_auth_realm_filenames  Reserved/declared for parity
 *                                           with other hook
 *                                           implementations; not used
 *                                           directly here.
 */
function plugin_webseer_config_arrays() {
	global $menu, $user_auth_realms, $user_auth_realm_filenames;

	$menu[__('Management')]['plugins/webseer/webseer.php'] = __('Service Checks', 'webseer');

	$files = ['index.php', 'plugins.php', 'webseer.php'];

	if (in_array(get_current_page(), $files, true)) {
		plugin_webseer_check_config();
	}
}

/**
 * Adds this plugin's page breadcrumb/navigation entries (service check
 * list/edit/save, server list/edit/save, proxy list/edit/save). Invoked
 * by the Cacti plugin framework via the 'draw_navigation_text' hook.
 *
 * @param array $nav Cacti's registered navigation text entries.
 *
 * @return array The $nav array with this plugin's entries added.
 */
function plugin_webseer_draw_navigation_text($nav) {
	$nav['webseer.php:'] = [
		'title'   => __('WebSeer Service Checks', 'webseer'),
		'mapping' => 'index.php:',
		'url'     => 'webseer.php',
		'level'   => '1'
	];

	$nav['webseer.php:edit'] = [
		'title'   => __('Service Check Edit', 'webseer'),
		'mapping' => 'index.php:',
		'url'     => 'webseer.php',
		'level'   => '1'
	];

	$nav['webseer.php:save'] = [
		'title'   => __('Service Check Save', 'webseer'),
		'mapping' => 'index.php:',
		'url'     => 'webseer.php',
		'level'   => '1'
	];

	$nav['webseer_servers.php:'] = [
		'title'   => __('WebSeer Servers', 'webseer'),
		'mapping' => 'index.php:',
		'url'     => 'webseer_servers.php',
		'level'   => '1'
	];

	$nav['webseer_servers.php:edit'] = [
		'title'   => __('Server Edit', 'webseer'),
		'mapping' => 'index.php:',
		'url'     => 'webseer.php',
		'level'   => '1'
	];

	$nav['webseer_servers.php:save'] = [
		'title'   => __('Save Server', 'webseer'),
		'mapping' => 'index.php:',
		'url'     => 'webseer.php',
		'level'   => '1'
	];

	$nav['webseer_proxies.php:'] = [
		'title'   => __('WebSeer Proxies', 'webseer'),
		'mapping' => 'index.php:',
		'url'     => 'webseer.php',
		'level'   => '1'
	];

	$nav['webseer_proxies.php:edit'] = [
		'title'   => __('Proxie Edit', 'webseer'),
		'mapping' => 'index.php:',
		'url'     => 'webseer.php',
		'level'   => '1'
	];

	$nav['webseer_proxies.php:save'] = [
		'title'   => __('Save Proxy', 'webseer'),
		'mapping' => 'index.php:',
		'url'     => 'webseer.php',
		'level'   => '1'
	];

	return $nav;
}

/**
 * Replicates this plugin's configuration tables (contacts, proxies,
 * servers, service check URLs) out to a remote data collector poller.
 * Invoked by the Cacti plugin framework via the 'replicate_out' hook
 * during data collector replication.
 *
 * @param array $data Replication context, including 'remote_poller_id',
 *                    'rcnn_id' (remote connection id), and 'class'
 *                    (replication scope, e.g. 'all').
 *
 * @return array The $data array, passed through unchanged for hook chaining.
 */
function webseer_replicate_out($data) {
	$remote_poller_id = $data['remote_poller_id'];
	$rcnn_id          = $data['rcnn_id'];
	$class            = $data['class'];

	cacti_log('INFO: Replicating for the WebSeer Plugin', false, 'REPLICATE');

	$tables = [
		'plugin_webseer_contacts',
		'plugin_webseer_proxies',
		'plugin_webseer_servers',
		'plugin_webseer_urls'
	];

	if ($class == 'all') {
		foreach ($tables as $table) {
			$tdata = db_fetch_assoc('SELECT * FROM ' . $table);
			replicate_out_table($rcnn_id, $tdata, $table, $remote_poller_id);
		}
	}

	return $data;
}

/**
 * Removes files and directories that a previous version of this plugin
 * shipped but that have since moved or been deleted, using the tombstone
 * and whitelist lists in manifest.json. Whitelisted (user-data) paths and
 * any VCS metadata (.git*) are never touched; the dev-only tests/ tree is
 * removed. Any path that resolves outside the plugin directory (a tampered
 * manifest.json) is refused, and any file/directory that cannot be removed
 * (e.g. read-only) is reported to the Cacti log. Any top-level entry that is
 * neither expected nor a tombstone nor whitelisted is logged to the Cacti
 * log and left in place. Called on a plugin version change.
 *
 * @return void
 *
 * @global array $config Cacti global configuration array; used to resolve
 *                       the plugin directory.
 */
function plugin_webseer_prune_files(): void {
	global $config;

	$plugin_dir    = $config['base_path'] . '/plugins/webseer';
	$manifest_path = $plugin_dir . '/manifest.json';

	if (!is_readable($manifest_path)) {
		return;
	}

	$manifest = json_decode((string) file_get_contents($manifest_path), true);

	if (!is_array($manifest)) {
		cacti_log('WARNING: webseer manifest.json could not be parsed; skipping file prune', false, 'WEBSEER');

		return;
	}

	$tombstones = isset($manifest['tombstones']) && is_array($manifest['tombstones']) ? $manifest['tombstones'] : [];
	$expected   = isset($manifest['expected'])   && is_array($manifest['expected'])   ? $manifest['expected']   : [];
	$whitelist  = isset($manifest['whitelist'])  && is_array($manifest['whitelist'])  ? $manifest['whitelist']  : [];

	$protected = function (string $rel) use ($whitelist): bool {
		if (strncmp($rel, '.git', 4) === 0 || strncmp($rel, '.md', 3) === 0) {
			return true;
		}

		foreach ($whitelist as $entry) {
			$entry = trim((string) $entry, '/');

			if ($entry !== '' && ($rel === $entry || strncmp($rel, $entry . '/', strlen($entry) + 1) === 0)) {
				return true;
			}
		}

		return false;
	};

	// Security: resolve the plugin directory so a tampered manifest.json
	// cannot steer the prune outside of it.
	$plugin_real = realpath($plugin_dir);

	// Remove tombstoned (moved/deleted) paths plus the dev-only tests/
	// tree and the phpunit.xml test configuration.
	$remove   = $tombstones;
	$remove[] = 'tests/';
	$remove[] = 'phpunit.xml';

	foreach ($remove as $rel) {
		$rel = trim((string) $rel, '/');

		if ($rel === '' || $protected($rel)) {
			continue;
		}

		$path = $plugin_dir . '/' . $rel;

		if (!is_link($path) && !file_exists($path)) {
			continue;
		}

		// Refuse any path that, after resolving symlinks and ../ segments,
		// escapes the plugin directory (protects user data from a tampered
		// manifest.json).
		$anchor = is_link($path) ? dirname($path) : $path;
		$real   = realpath($anchor);

		if ($real === false || ($real !== $plugin_real && strncmp($real, $plugin_real . DIRECTORY_SEPARATOR, strlen((string) $plugin_real) + 1) !== 0)) {
			cacti_log(sprintf('WARNING: webseer prune refused to remove %s: path resolves outside the plugin directory (tampered manifest.json?)', $rel), false, 'WEBSEER');

			continue;
		}

		if (is_dir($path) && !is_link($path)) {
			$removed = plugin_webseer_rmtree($path);
		} else {
			$removed = @unlink($path);
		}

		if (!$removed) {
			cacti_log(sprintf('WARNING: webseer upgrade could not remove %s (check file/directory permissions)', $rel), false, 'WEBSEER');
		}
	}

	// Surface any top-level entry the manifest does not account for.
	$known = [];

	foreach (array_merge($expected, $tombstones) as $entry) {
		$top = explode('/', trim((string) $entry, '/'))[0];

		if ($top !== '') {
			$known[$top] = true;
		}
	}

	$entries = scandir($plugin_dir);

	foreach (($entries !== false ? $entries : []) as $entry) {
		if ($entry === '.' || $entry === '..' || $entry === 'tests' || $entry === 'phpunit.xml' || $protected($entry) || isset($known[$entry])) {
			continue;
		}

		cacti_log(sprintf('WARNING: webseer upgrade found a file/directory not described in manifest.json: %s (left in place)', $entry), false, 'WEBSEER');
	}
}

/**
 * Recursively deletes a directory and its contents. Symlinks are removed
 * without being followed. Helper for plugin_webseer_prune_files().
 *
 * @param string $dir Absolute path to the directory to remove.
 *
 * @return bool True if the directory and everything under it was removed;
 *              false if any entry could not be deleted.
 */
function plugin_webseer_rmtree(string $dir): bool {
	$entries = scandir($dir);
	$ok      = true;

	foreach (($entries !== false ? $entries : []) as $entry) {
		if ($entry === '.' || $entry === '..') {
			continue;
		}

		$path = $dir . '/' . $entry;

		if (is_dir($path) && !is_link($path)) {
			if (!plugin_webseer_rmtree($path)) {
				$ok = false;
			}
		} elseif (!@unlink($path)) {
			$ok = false;
		}
	}

	if (!@rmdir($dir)) {
		$ok = false;
	}

	return $ok;
}

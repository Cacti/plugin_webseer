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
 * The plugin_webseer_servers table definition (peer WebSeer server
 * definitions), shared by the create and upgrade paths so both stay in
 * sync from a single definition.
 *
 * @return array<string, mixed> The table definition array.
 */
function webseer_servers_table_data(): array {
	$data              = [];
	$data['columns'][] = ['name' => 'id', 'type' => 'int(11)', 'unsigned' => true, 'NULL' => false, 'auto_increment' => true];
	$data['columns'][] = ['name' => 'enabled', 'type' => 'char(2)', 'NULL' => false, 'default' => 'on'];
	$data['columns'][] = ['name' => 'name', 'type' => 'varchar(64)', 'NULL' => false];
	$data['columns'][] = ['name' => 'ip', 'type' => 'varchar(120)', 'NULL' => false];
	$data['columns'][] = ['name' => 'location', 'type' => 'varchar(64)', 'NULL' => false];
	$data['columns'][] = ['name' => 'lastcheck', 'type' => 'timestamp', 'NULL' => false, 'default' => '0000-00-00'];
	$data['columns'][] = ['name' => 'compression', 'type' => 'int(3)', 'NULL' => false, 'default' => 0];
	$data['columns'][] = ['name' => 'isme', 'type' => 'int(11)', 'unsigned' => true, 'NULL' => false, 'default' => 0];
	$data['columns'][] = ['name' => 'master', 'type' => 'int(11)', 'unsigned' => true, 'NULL' => false, 'default' => 0];
	$data['columns'][] = ['name' => 'url', 'type' => 'varchar(256)', 'NULL' => false];
	$data['primary']   = ['id'];
	$data['keys'][]    = ['name' => 'location', 'columns' => ['location', 'lastcheck']];
	$data['keys'][]    = ['name' => 'isme', 'columns' => ['isme']];
	$data['keys'][]    = ['name' => 'master', 'columns' => ['master']];
	$data['type']      = 'InnoDB';
	$data['comment']   = 'Holds WebSeer Server Definitions';

	return $data;
}

/**
 * The plugin_webseer_servers_log table definition (peer-server service
 * check results), shared by the create and upgrade paths.
 *
 * @return array<string, mixed> The table definition array.
 */
function webseer_servers_log_table_data(): array {
	$data              = [];
	$data['columns'][] = ['name' => 'id', 'type' => 'int(11)', 'unsigned' => true, 'NULL' => false, 'auto_increment' => true];
	$data['columns'][] = ['name' => 'server', 'type' => 'int(11)', 'unsigned' => true, 'NULL' => false, 'default' => 0];
	$data['columns'][] = ['name' => 'url_id', 'type' => 'int(11)', 'unsigned' => true, 'NULL' => false, 'default' => 0];
	$data['columns'][] = ['name' => 'lastcheck', 'type' => 'timestamp', 'NULL' => false, 'default' => '0000-00-00'];
	$data['columns'][] = ['name' => 'compression', 'type' => 'int(3)', 'unsigned' => true, 'NULL' => false, 'default' => 0];
	$data['columns'][] = ['name' => 'result', 'type' => 'int(11)', 'unsigned' => true, 'NULL' => false, 'default' => 0];
	$data['columns'][] = ['name' => 'http_code', 'type' => 'int(11)', 'unsigned' => true, 'NULL' => true, 'default' => null];
	$data['columns'][] = ['name' => 'error', 'type' => 'varchar(256)', 'NULL' => true, 'default' => null];
	$data['columns'][] = ['name' => 'total_time', 'type' => 'double', 'NULL' => true, 'default' => null];
	$data['columns'][] = ['name' => 'namelookup_time', 'type' => 'double', 'NULL' => true, 'default' => null];
	$data['columns'][] = ['name' => 'connect_time', 'type' => 'double', 'NULL' => true, 'default' => null];
	$data['columns'][] = ['name' => 'redirect_time', 'type' => 'double', 'NULL' => true, 'default' => null];
	$data['columns'][] = ['name' => 'redirect_count', 'type' => 'int(11)', 'unsigned' => true, 'NULL' => true, 'default' => null];
	$data['columns'][] = ['name' => 'size_download', 'type' => 'int(11)', 'unsigned' => true, 'NULL' => true, 'default' => null];
	$data['columns'][] = ['name' => 'speed_download', 'type' => 'int(11)', 'unsigned' => true, 'NULL' => true, 'default' => null];
	$data['primary']   = ['id'];
	$data['keys'][]    = ['name' => 'url_id', 'columns' => ['url_id']];
	$data['keys'][]    = ['name' => 'lastcheck', 'columns' => ['lastcheck']];
	$data['keys'][]    = ['name' => 'result', 'columns' => ['result']];
	$data['type']      = 'InnoDB';
	$data['comment']   = 'Holds WebSeer Service Check Results';

	return $data;
}

/**
 * The plugin_webseer_urls table definition (service check definitions),
 * shared by the create and upgrade paths.
 *
 * @return array<string, mixed> The table definition array.
 */
function webseer_urls_table_data(): array {
	$data              = [];
	$data['columns'][] = ['name' => 'id', 'type' => 'int(11)', 'unsigned' => true, 'NULL' => false, 'auto_increment' => true];
	$data['columns'][] = ['name' => 'poller_id', 'type' => 'int(11)', 'unsigned' => true, 'NULL' => false, 'default' => 1];
	$data['columns'][] = ['name' => 'enabled', 'type' => 'char(2)', 'NULL' => false, 'default' => 'on'];
	$data['columns'][] = ['name' => 'type', 'type' => 'varchar(32)', 'NULL' => false, 'default' => 'http'];
	$data['columns'][] = ['name' => 'display_name', 'type' => 'varchar(64)', 'NULL' => false, 'default' => ''];
	$data['columns'][] = ['name' => 'url', 'type' => 'varchar(256)', 'NULL' => false];
	$data['columns'][] = ['name' => 'ip', 'type' => 'varchar(120)', 'NULL' => false, 'default' => ''];
	$data['columns'][] = ['name' => 'search', 'type' => 'varchar(1024)', 'NULL' => false];
	$data['columns'][] = ['name' => 'search_maint', 'type' => 'varchar(1024)', 'NULL' => false];
	$data['columns'][] = ['name' => 'search_failed', 'type' => 'varchar(1024)', 'NULL' => false];
	$data['columns'][] = ['name' => 'requiresauth', 'type' => 'char(2)', 'NULL' => false, 'default' => ''];
	$data['columns'][] = ['name' => 'proxy_server', 'type' => 'int(11)', 'unsigned' => true, 'NULL' => false, 'default' => 0];
	$data['columns'][] = ['name' => 'checkcert', 'type' => 'char(2)', 'NULL' => false, 'default' => 'on'];
	$data['columns'][] = ['name' => 'notify_list', 'type' => 'int(10)', 'unsigned' => true, 'NULL' => false, 'default' => 0];
	$data['columns'][] = ['name' => 'notify_accounts', 'type' => 'varchar(256)', 'NULL' => false];
	$data['columns'][] = ['name' => 'notify_extra', 'type' => 'varchar(256)', 'NULL' => false];
	$data['columns'][] = ['name' => 'notify_format', 'type' => 'int(3)', 'unsigned' => true, 'NULL' => false, 'default' => 0];
	$data['columns'][] = ['name' => 'result', 'type' => 'int(11)', 'unsigned' => true, 'NULL' => false, 'default' => 0];
	$data['columns'][] = ['name' => 'downtrigger', 'type' => 'int(11)', 'unsigned' => true, 'NULL' => false, 'default' => 3];
	$data['columns'][] = ['name' => 'timeout_trigger', 'type' => 'int(11)', 'unsigned' => true, 'NULL' => false, 'default' => 4];
	$data['columns'][] = ['name' => 'failures', 'type' => 'int(11)', 'unsigned' => true, 'NULL' => false, 'default' => 0];
	$data['columns'][] = ['name' => 'triggered', 'type' => 'int(11)', 'unsigned' => true, 'NULL' => false, 'default' => 0];
	$data['columns'][] = ['name' => 'lastcheck', 'type' => 'timestamp', 'NULL' => false, 'default' => '0000-00-00'];
	$data['columns'][] = ['name' => 'compression', 'type' => 'int(3)', 'unsigned' => true, 'NULL' => false, 'default' => 0];
	$data['columns'][] = ['name' => 'error', 'type' => 'varchar(256)', 'NULL' => true, 'default' => null];
	$data['columns'][] = ['name' => 'http_code', 'type' => 'int(11)', 'unsigned' => true, 'NULL' => true, 'default' => null];
	$data['columns'][] = ['name' => 'total_time', 'type' => 'double', 'NULL' => true, 'default' => null];
	$data['columns'][] = ['name' => 'namelookup_time', 'type' => 'double', 'NULL' => true, 'default' => null];
	$data['columns'][] = ['name' => 'connect_time', 'type' => 'double', 'NULL' => true, 'default' => null];
	$data['columns'][] = ['name' => 'redirect_time', 'type' => 'double', 'NULL' => true, 'default' => null];
	$data['columns'][] = ['name' => 'speed_download', 'type' => 'int(11)', 'unsigned' => true, 'NULL' => true, 'default' => null];
	$data['columns'][] = ['name' => 'size_download', 'type' => 'int(11)', 'unsigned' => true, 'NULL' => true, 'default' => null];
	$data['columns'][] = ['name' => 'redirect_count', 'type' => 'int(11)', 'unsigned' => true, 'NULL' => true, 'default' => null];
	$data['columns'][] = ['name' => 'debug', 'type' => 'longblob', 'NULL' => true, 'default' => null];
	$data['primary']   = ['id'];
	$data['keys'][]    = ['name' => 'lastcheck', 'columns' => ['lastcheck']];
	$data['keys'][]    = ['name' => 'triggered', 'columns' => ['triggered']];
	$data['keys'][]    = ['name' => 'result', 'columns' => ['result']];
	$data['keys'][]    = ['name' => 'enabled', 'columns' => ['enabled']];
	$data['type']      = 'InnoDB';
	$data['comment']   = 'Holds WebSeer Service Check Definitions';

	return $data;
}

/**
 * The plugin_webseer_urls_log table definition (service check logs),
 * shared by the create and upgrade paths.
 *
 * @return array<string, mixed> The table definition array.
 */
function webseer_urls_log_table_data(): array {
	$data              = [];
	$data['columns'][] = ['name' => 'id', 'type' => 'int(11)', 'unsigned' => true, 'NULL' => false, 'auto_increment' => true];
	$data['columns'][] = ['name' => 'url_id', 'type' => 'int(11)', 'unsigned' => true, 'NULL' => false, 'default' => 0];
	$data['columns'][] = ['name' => 'lastcheck', 'type' => 'timestamp', 'NULL' => false, 'default' => '0000-00-00'];
	$data['columns'][] = ['name' => 'compression', 'type' => 'int(3)', 'unsigned' => true, 'NULL' => false, 'default' => 0];
	$data['columns'][] = ['name' => 'result', 'type' => 'int(11)', 'unsigned' => true, 'NULL' => false, 'default' => 0];
	$data['columns'][] = ['name' => 'http_code', 'type' => 'int(11)', 'unsigned' => true, 'NULL' => true, 'default' => null];
	$data['columns'][] = ['name' => 'error', 'type' => 'varchar(256)', 'NULL' => true, 'default' => null];
	$data['columns'][] = ['name' => 'total_time', 'type' => 'double', 'NULL' => true, 'default' => null];
	$data['columns'][] = ['name' => 'namelookup_time', 'type' => 'double', 'NULL' => true, 'default' => null];
	$data['columns'][] = ['name' => 'connect_time', 'type' => 'double', 'NULL' => true, 'default' => null];
	$data['columns'][] = ['name' => 'redirect_time', 'type' => 'double', 'unsigned' => true, 'NULL' => true, 'default' => null];
	$data['columns'][] = ['name' => 'redirect_count', 'type' => 'int(11)', 'unsigned' => true, 'NULL' => true, 'default' => null];
	$data['columns'][] = ['name' => 'size_download', 'type' => 'int(11)', 'unsigned' => true, 'NULL' => true, 'default' => null];
	$data['columns'][] = ['name' => 'speed_download', 'type' => 'int(11)', 'unsigned' => true, 'NULL' => true, 'default' => null];
	$data['primary']   = ['id'];
	$data['keys'][]    = ['name' => 'url_id', 'columns' => ['url_id']];
	$data['keys'][]    = ['name' => 'lastcheck', 'columns' => ['lastcheck']];
	$data['keys'][]    = ['name' => 'result', 'columns' => ['result']];
	$data['type']      = 'InnoDB';
	$data['comment']   = 'Holds WebSeer Service Check Logs';

	return $data;
}

/**
 * The plugin_webseer_processes table definition (running check-process
 * tracking, MEMORY engine), shared by the create and upgrade paths.
 *
 * @return array<string, mixed> The table definition array.
 */
function webseer_processes_table_data(): array {
	$data              = [];
	$data['columns'][] = ['name' => 'id', 'type' => 'bigint', 'unsigned' => true, 'NULL' => false, 'auto_increment' => true];
	$data['columns'][] = ['name' => 'poller_id', 'type' => 'int(11)', 'unsigned' => true, 'NULL' => false, 'default' => 1];
	$data['columns'][] = ['name' => 'url_id', 'type' => 'int(11)', 'unsigned' => true, 'NULL' => false];
	$data['columns'][] = ['name' => 'pid', 'type' => 'int(11)', 'unsigned' => true, 'NULL' => false];
	$data['columns'][] = ['name' => 'time', 'type' => 'timestamp', 'NULL' => true, 'default' => 'CURRENT_TIMESTAMP'];
	$data['primary']   = ['id'];
	$data['keys'][]    = ['name' => 'pid', 'columns' => ['pid']];
	$data['keys'][]    = ['name' => 'url_id', 'columns' => ['url_id']];
	$data['keys'][]    = ['name' => 'time', 'columns' => ['time']];
	$data['type']      = 'MEMORY';
	$data['comment']   = 'Holds running process information';

	return $data;
}

/**
 * The plugin_webseer_contacts table definition (notification contacts),
 * shared by the create and upgrade paths.
 *
 * @return array<string, mixed> The table definition array.
 */
function webseer_contacts_table_data(): array {
	$data                  = [];
	$data['columns'][]     = ['name' => 'id', 'type' => 'int(12)', 'NULL' => false, 'auto_increment' => true];
	$data['columns'][]     = ['name' => 'user_id', 'type' => 'int(12)', 'NULL' => false];
	$data['columns'][]     = ['name' => 'type', 'type' => 'varchar(32)', 'NULL' => false];
	$data['columns'][]     = ['name' => 'data', 'type' => 'text', 'NULL' => false];
	$data['primary']       = ['id'];
	$data['unique_keys'][] = ['name' => 'user_id_type', 'columns' => ['user_id', 'type']];
	$data['keys'][]        = ['name' => 'type', 'columns' => ['type']];
	$data['keys'][]        = ['name' => 'user_id', 'columns' => ['user_id']];
	$data['type']          = 'InnoDB';
	$data['comment']       = 'Table of WebSeer contacts';

	return $data;
}

/**
 * The plugin_webseer_proxies table definition (HTTP proxy connections),
 * shared by the create and upgrade paths.
 *
 * @return array<string, mixed> The table definition array.
 */
function webseer_proxies_table_data(): array {
	$data              = [];
	$data['columns'][] = ['name' => 'id', 'type' => 'int(11)', 'unsigned' => true, 'NULL' => false, 'auto_increment' => true];
	$data['columns'][] = ['name' => 'name', 'type' => 'varchar(30)', 'NULL' => true, 'default' => ''];
	$data['columns'][] = ['name' => 'hostname', 'type' => 'varchar(64)', 'NULL' => true, 'default' => ''];
	$data['columns'][] = ['name' => 'http_port', 'type' => 'mediumint(8)', 'unsigned' => true, 'NULL' => true, 'default' => 80];
	$data['columns'][] = ['name' => 'https_port', 'type' => 'mediumint(8)', 'unsigned' => true, 'NULL' => true, 'default' => 443];
	$data['columns'][] = ['name' => 'username', 'type' => 'varchar(40)', 'NULL' => true, 'default' => ''];
	$data['columns'][] = ['name' => 'password', 'type' => 'varchar(60)', 'NULL' => true, 'default' => ''];
	$data['primary']   = ['id'];
	$data['keys'][]    = ['name' => 'hostname', 'columns' => ['hostname']];
	$data['keys'][]    = ['name' => 'name', 'columns' => ['name']];
	$data['type']      = 'InnoDB';
	$data['comment']   = 'Holds Proxy Information for Connections';

	return $data;
}

/**
 * Returns the plugin's complete table map (table name => definition), the
 * single source of truth consumed by both the create path
 * (api_plugin_db_table_create()) and the upgrade path (db_update_table()).
 *
 * @return array<string, array<string, mixed>> Table name keyed definitions.
 */
function webseer_table_map(): array {
	return [
		'plugin_webseer_servers'     => webseer_servers_table_data(),
		'plugin_webseer_servers_log' => webseer_servers_log_table_data(),
		'plugin_webseer_urls'        => webseer_urls_table_data(),
		'plugin_webseer_urls_log'    => webseer_urls_log_table_data(),
		'plugin_webseer_processes'   => webseer_processes_table_data(),
		'plugin_webseer_contacts'    => webseer_contacts_table_data(),
		'plugin_webseer_proxies'     => webseer_proxies_table_data(),
	];
}

/**
 * Creates all of this plugin's database tables through Cacti's tracked
 * plugin table API. Called from plugin_webseer_install() during
 * installation, and re-run (safely, as a no-op for already-applied
 * changes) from webseer_upgrade_tables() during upgrades.
 *
 * @return void
 */
function plugin_webseer_setup_table() {
	foreach (webseer_table_map() as $table => $data) {
		api_plugin_db_table_create('webseer', $table, $data);
	}
}

/**
 * Refreshes this plugin's tables to their current definition on upgrade:
 * db_update_table() diffs the live schema against each definition and
 * issues the exact ALTER when the table already exists, otherwise the
 * table is created outright. The historical plugin_webseer_url_log ->
 * plugin_webseer_urls_log rename (which db_update_table() can not express)
 * is applied as a guarded pre-step first. Called from
 * plugin_webseer_upgrade() when the stored version changes.
 *
 * @return void
 */
function webseer_upgrade_tables() {
	// db_update_table() can not rename, so carry the historical URL-log
	// table rename (and its data) forward before the schema refresh.
	if (db_table_exists('plugin_webseer_url_log') && !db_table_exists('plugin_webseer_urls_log')) {
		db_execute('RENAME TABLE `plugin_webseer_url_log` TO `plugin_webseer_urls_log`');
	}

	foreach (webseer_table_map() as $table => $data) {
		if (db_table_exists($table)) {
			db_update_table($table, $data);
		} else {
			api_plugin_db_table_create('webseer', $table, $data);
		}
	}

	webseer_reintroduce_unique_keys();
}

/**
 * Cacti 1.2.29 through 1.2.31 shipped a db_update_table() that silently
 * dropped UNIQUE keys declared via a table definition's 'unique_keys' (it
 * rebuilds indexes only from 'keys'). Re-add this plugin's unique keys when
 * running on one of those releases; 1.2.32+ preserves them, so this is a
 * no-op there.
 *
 * @return void
 */
function webseer_reintroduce_unique_keys(): void {
	$running = trim((string) db_fetch_cell('SELECT cacti FROM version LIMIT 1'));

	if ($running === '' ||
		!cacti_version_compare($running, '1.2.29', '>=') ||
		!cacti_version_compare($running, '1.2.32', '<')) {
		return;
	}

	$unique_keys = [
		['table' => 'plugin_webseer_contacts', 'name' => 'user_id_type', 'columns' => ['user_id', 'type']],
	];

	foreach ($unique_keys as $uk) {
		if (!db_index_exists($uk['table'], $uk['name'])) {
			db_execute('ALTER TABLE `' . $uk['table'] . '` ADD UNIQUE KEY `' .
				$uk['name'] . '` (`' . implode('`,`', $uk['columns']) . '`)');
		}
	}
}

/**
 * Drops all of this plugin's database tables. Called from
 * plugin_webseer_uninstall() when the plugin is removed.
 *
 * @return void
 */
function plugin_webseer_drop_tables() {
	foreach (array_keys(webseer_table_map()) as $table) {
		api_plugin_drop_table($table);
	}
}

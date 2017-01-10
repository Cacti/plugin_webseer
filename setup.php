<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2010-2017 The Cacti Group                                 |
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
 | Cacti: The Complete RRDTool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

function plugin_webseer_install () {
	api_plugin_register_hook('webseer', 'draw_navigation_text', 'plugin_webseer_draw_navigation_text', 'setup.php');
	api_plugin_register_hook('webseer', 'config_arrays', 'plugin_webseer_config_arrays', 'setup.php');
	api_plugin_register_hook('webseer', 'poller_bottom', 'plugin_webseer_poller_bottom', 'setup.php');
	api_plugin_register_hook('webseer', 'user_admin_edit', 'plugin_webseer_user_admin_edit', 'setup.php');
	api_plugin_register_hook('webseer', 'user_admin_setup_sql_save', 'plugin_webseer_user_admin_setup_sql_save', 'setup.php');

	api_plugin_register_realm('webseer', 'websser.php,webseer_edit.php,webseer_servers.php,webseer_servers_edit.php', 'Web Service Check Admin', 1);

	plugin_webseer_setup_table();
}

function plugin_webseer_uninstall () {
	db_execute('DROP TABLE IF EXISTS plugin_webseer_servers');
	db_execute('DROP TABLE IF EXISTS plugin_webseer_servers_log');
	db_execute('DROP TABLE IF EXISTS plugin_webseer_urls');
	db_execute('DROP TABLE IF EXISTS plugin_webseer_url_log');
	db_execute('DROP TABLE IF EXISTS plugin_webseer_processes');
}

function plugin_webseer_version() {
	global $config;
	$info = parse_ini_file($config['base_path'] . '/plugins/webseer/INFO', true);
	return $info['info'];
}

function plugin_webseer_setup_table() {
	db_execute("CREATE TABLE IF NOT EXISTS `plugin_webseer_servers` (
		`id` int(5) NOT NULL auto_increment,
		`enabled` int(1) NOT NULL default '1',
		`name` varchar(128) NOT NULL,
		`ip` varchar(64) NOT NULL,
		`location` varchar(64) NOT NULL,
		`lastcheck` int(16) NOT NULL default '0',
		`isme` int(1) NOT NULL default '0',
		`master` int(1) NOT NULL default '0',
		`url` varchar(256) NOT NULL,
		PRIMARY KEY  (`id`),
		KEY `location` (`location`,`lastcheck`),
		KEY `isme` (`isme`),
		KEY `master` (`master`)) ENGINE=InnoDB
		COMMENT='Holds WebSeer Server Definitions'");

	db_execute("CREATE TABLE IF NOT EXISTS `plugin_webseer_servers_log` (
		`id` int(12) NOT NULL auto_increment,
		`server` int(6) NOT NULL default '0',
		`url_id` int(12) NOT NULL default '0',
		`lastcheck` int(16) NOT NULL default '0',
		`result` int(2) NOT NULL default '0',
		`http_code` varchar(4) NOT NULL,
		`error` varchar(256) NOT NULL,
		`total_time` varchar(12) NOT NULL,
		`namelookup_time` varchar(12) NOT NULL,
		`connect_time` varchar(12) NOT NULL,
		`redirect_time` varchar(12) NOT NULL,
		`redirect_count` varchar(12) NOT NULL,
		`size_download` varchar(12) NOT NULL,
		`speed_download` varchar(12) NOT NULL,
		PRIMARY KEY  (`id`),
		KEY `url_id` (`url_id`),
		KEY `lastcheck` (`lastcheck`),
		KEY `result` (`result`)) 
		ENGINE=InnoDB
		COMMENT='Holds WebSeer Service Check Results'");

	db_execute("CREATE TABLE IF NOT EXISTS `plugin_webseer_urls` (
		`id` int(12) NOT NULL auto_increment,
		`enabled` varchar(3) NOT NULL default 'on',
		`type` varchar(32) NOT NULL default 'http',
		`display_name` varchar(256) NOT NULL default '',
		`url` varchar(256) NOT NULL,
		`ip` varchar(256) NOT NULL default '',
		`search` varchar(256) NOT NULL,
		`search_maint` varchar(256) NOT NULL,
		`search_failed` varchar(256) NOT NULL,
		`requiresauth` varchar(3) NOT NULL default 'off',
		`checkcert` varchar(3) NOT NULL default 'on',
		`notify_accounts` varchar(256) NOT NULL,
		`notify_extra` varchar(256) NOT NULL,
		`result` int(2) NOT NULL default '0',
		`downtrigger` int(3) NOT NULL default '3',
		`timeout_trigger` int(2) NOT NULL default '4',
		`failures` int(2) NOT NULL default '0',
		`triggered` int(1) NOT NULL default '0',
		`lastcheck` int(16) NOT NULL default '0',
		`error` varchar(256) NOT NULL,
		`http_code` varchar(4) NOT NULL,
		`total_time` varchar(12) NOT NULL,
		`namelookup_time` varchar(12) NOT NULL,
		`connect_time` varchar(12) NOT NULL,
		`redirect_time` varchar(12) NOT NULL,
		`speed_download` varchar(12) NOT NULL,
		`size_download` varchar(12) NOT NULL,
		`redirect_count` varchar(12) NOT NULL,
		`debug` longtext NOT NULL,
		PRIMARY KEY  (`id`),
		KEY `lastcheck` (`lastcheck`),
		KEY `triggered` (`triggered`),
		KEY `result` (`result`),
		KEY `enabled` (`enabled`)) 
		ENGINE=InnoDB
		COMMENT='Holds WebSeer Service Check Definitions'");

	db_execute("CREATE TABLE IF NOT EXISTS `plugin_webseer_url_log` (
		`id` int(12) NOT NULL auto_increment,
		`url_id` int(12) NOT NULL default '0',
		`lastcheck` int(16) NOT NULL default '0',
		`result` int(2) NOT NULL default '0',
		`http_code` varchar(4) NOT NULL,
		`error` varchar(256) NOT NULL,
		`total_time` varchar(12) NOT NULL,
		`namelookup_time` varchar(12) NOT NULL,
		`connect_time` varchar(12) NOT NULL,
		`redirect_time` varchar(12) NOT NULL,
		`redirect_count` varchar(12) NOT NULL,
		`size_download` varchar(12) NOT NULL,
		`speed_download` varchar(12) NOT NULL,
		PRIMARY KEY  (`id`),
		KEY `url_id` (`url_id`),
		KEY `lastcheck` (`lastcheck`),
		KEY `result` (`result`)) 
		ENGINE=InnoDB
		COMMENT='Holds WebSeer Service Check Logs'");

	db_execute("CREATE TABLE IF NOT EXISTS `plugin_webseer_processes` (
		`id` int(20) NOT NULL auto_increment,
		`url` int(12) NOT NULL,
		`time` int(20) NOT NULL,
		PRIMARY KEY  (`id`),
		KEY `url` (`url`),
		KEY `time` (`time`)) 
		ENGINE=MEMORY
		COMMENT='Holds running process information'");
}

function plugin_webseer_poller_bottom() {
	global $config;

	include_once($config["library_path"] . "/database.php");

	$command_string = trim(read_config_option("path_php_binary"));

	// If its not set, just assume its in the path
	if (trim($command_string) == '')
		$command_string = "php";
	$extra_args = ' -q ' . $config['base_path'] . '/plugins/webseer/poller_webseer.php';

	exec_background($command_string, $extra_args);
}

function plugin_webseer_config_arrays() {
	global $menu, $user_auth_realms, $user_auth_realm_filenames;

	$menu[__('Management')]['plugins/webseer/webseer.php'] = __('Web Service Checks');

	$user_auth_realms[423]='Manage Webseer';
	$user_auth_realm_filenames['webseer_edit.php'] = 423;
	$user_auth_realm_filenames['webseer.php'] = 423;
	$user_auth_realm_filenames['webseer_servers.php'] = 423;
	$user_auth_realm_filenames['webseer_servers_edit.php'] = 423;
}

function plugin_webseer_draw_navigation_text($nav) {
	$nav['webseer.php:'] = array('title' => 'Webseer', 'mapping' => 'index.php:', 'url' => 'webseer.php', 'level' => '1');
	$nav['webseer_servers.php:'] = array('title' => 'Webseer', 'mapping' => 'index.php:', 'url' => 'webseer_servers.php', 'level' => '1');
	$nav['webseer_edit.php:'] = array('title' => 'Webseer', 'mapping' => 'index.php:', 'url' => 'webseer.php', 'level' => '1');
	$nav['webseer_edit.php:edit'] = array('title' => 'Webseer', 'mapping' => 'index.php:', 'url' => 'webseer.php', 'level' => '1');
	$nav['webseer_edit.php:save'] = array('title' => 'Webseer', 'mapping' => 'index.php:', 'url' => 'webseer.php', 'level' => '1');
	$nav['webseer_servers_edit.php:'] = array('title' => 'Webseer', 'mapping' => 'index.php:', 'url' => 'webseer.php', 'level' => '1');
	$nav['webseer_servers_edit.php:edit'] = array('title' => 'Webseer', 'mapping' => 'index.php:', 'url' => 'webseer.php', 'level' => '1');
	$nav['webseer_servers_edit.php:save'] = array('title' => 'Webseer', 'mapping' => 'index.php:', 'url' => 'webseer.php', 'level' => '1');

	return $nav;
}

function plugin_webseer_user_admin_edit($user) {
	global $fields_user_user_edit_host;

	$value = '';

	if ($user != 0) {
		$value = db_fetch_cell("SELECT data FROM plugin_thold_contacts WHERE user_id = $user AND type = 'text'");
	}

	$fields_user_user_edit_host['text'] = array(
		'method' => 'textbox',
		'value' => $value,
		'friendly_name' => 'Text Email Address',
		'form_id' => '|arg1:id|',
		'default' => '',
		'max_length' => 255
	);

	return $user;
}

function plugin_webseer_user_admin_setup_sql_save($save) {
	if (is_error_message()) {
		return $save;
	}

	if (isset_request_var('text')) {
		$text = form_input_validate(get_nfilter_request_var('text'), 'text', '', true, 3);

		if ($save['id'] == 0) {
			$save['id'] = sql_save($save, 'user_auth');
		}

		$cid = db_fetch_cell("SELECT id FROM plugin_thold_contacts WHERE type = 'text' AND user_id = " . $save['id'], false);

		if ($cid) {
			db_execute("REPLACE INTO plugin_thold_contacts (id, user_id, type, data) VALUES ($cid, " . $save['id'] . ", 'text', '$text')");
		}else{
			db_execute("REPLACE INTO plugin_thold_contacts (user_id, type, data) VALUES (" . $save['id'] . ", 'text', '$text')");
		}
	}

	return $save;
}


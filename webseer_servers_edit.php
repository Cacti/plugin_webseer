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

chdir('../../');

include('./include/auth.php');
include_once($config['base_path'] . '/lib/functions.php');
include_once($config['base_path'] . '/plugins/webseer/functions.php');

if (isset_request_var('action') && get_nfilter_request_var('action') == 'save') {
	$action = 'save';
} else {
	$action = 'edit';
}

switch ($action) {
	case 'save':
		webseer_save_server();
		break;
	case 'edit':
	default:
		top_header();
		webseer_edit_server();
		bottom_footer();
		break;
}

function webseer_save_server() {
	/* ================= input validation ================= */
	get_filter_request_var('id');
	/* ==================================================== */

	if (isset_request_var('id')) {
		$save['id'] = get_request_var('id');
	} else {
		$save['id'] = 0;
	}

	if (isset_request_var('enabled')) {
		$save['enabled'] = 'on';
	} else {
		$save['enabled'] = '';
	}

	if (isset_request_var('master')) {
		$save['master'] = '1';
	} else {
		$save['master'] = '0';
	}

	if (isset_request_var('isme')) {
		$save['isme'] = '1';
	} else {
		$save['isme'] = '0';
	}

	$save['name']     = get_nfilter_request_var('name');
	$save['ip']       = get_nfilter_request_var('ip');
	$save['url']      = get_nfilter_request_var('url');
	$save['location'] = get_nfilter_request_var('location');

	$id = sql_save($save, 'plugin_webseer_servers', 'id');

	if (is_error_message()) {
		header('Location: webseer_servers_edit.php?header=false&action=edit&id=' . (empty($id) ? get_request_var('id') : $id));
		exit;
	}
	if ($save['id'] == 0) {
		plugin_webseer_add_remote_server($id, $save);
	} else {
		plugin_webseer_update_remote_server($save);
	}

	if ($save['isme'] == 1) {
		plugin_webseer_refresh_urls();
	}

	header('Location: webseer_servers.php?header=false');
	exit;
}

function webseer_edit_server() {
	/* ================= input validation ================= */
	get_filter_request_var('id');
	/* ==================================================== */

	$server = array();
	if (!isempty_request_var('id')) {
		$server = db_fetch_row_prepared('SELECT * FROM plugin_webseer_servers WHERE id = ?', array(get_request_var('id')), FALSE);
		$header_label = __('Query [edit: %s]', $server['ip'], 'webseer');
	}else{
		$header_label = __('Query [new]', 'webseer');
	}

	$server_edit = array(
		'enabled' => array(
			'method' => 'checkbox',
			'friendly_name' => __('Enable Server', 'webseer'),
			'description' => __('Uncheck this box to disabled this server from checking urls.', 'webseer'),
			'value' => (isset($server['enabled']) && $server['enabled'] == 1 ? 'on' : ''),
			'default' => '',
			),
		'master' => array(
			'method' => 'checkbox',
			'friendly_name' => __('Master Server', 'webseer'),
			'description' => __('Sets this server to the Master server.  The Master server handles all Email operations', 'webseer'),
			'value' => (isset($server['master']) && $server['master'] == 1 ? 'on' : ''),
			'default' => '',
			),
		'isme' => array(
			'method' => 'checkbox',
			'friendly_name' => __('Is this server the local server?', 'webseer'),
			'description' => __('Check this box if the current server you are connected to is this entry.', 'webseer'),
			'value' => (isset($server['isme']) && $server['isme'] == 1 ? 'on' : ''),
			'default' => '',
			),
		'name' => array(
			'method' => 'textbox',
			'friendly_name' => __('Name', 'webseer'),
			'description' => __('Display Name of this server', 'webseer'),
			'value' => '|arg1:name|',
			'max_length' => '256',
			),
		'ip' => array(
			'method' => 'textbox',
			'friendly_name' => __('IP Address', 'webseer'),
			'description' => __('IP Address to connect to this server', 'webseer'),
			'value' => '|arg1:ip|',
			'max_length' => '256',
			),
		'location' => array(
			'method' => 'textbox',
			'friendly_name' => __('Location', 'webseer'),
			'description' => __('Location of this server', 'webseer'),
			'value' => '|arg1:location|',
			'max_length' => '256',
			),
		'url' => array(
			'method' => 'textbox',
			'friendly_name' => __('URL', 'webseer'),
			'description' => __('This is the URL to connect to remote.php on this server.', 'webseer'),
			'value' => '|arg1:url|',
			'max_length' => '256',
			),
		'id' => array(
			'method' => 'hidden_zero',
			'value' => '|arg1:id|'
			),
		);

	form_start('webseer_servers_edit.php');
	html_start_box($header_label, '100%', '', '3', 'center', '');
	draw_edit_form(array(
		'config' => array('form_name' => 'chk'),
		'fields' => inject_form_variables($server_edit, $server)
		)
	);

	html_end_box();

	form_save_button('webseer_servers.php', 'return');
}

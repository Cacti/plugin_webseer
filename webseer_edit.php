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
	webseer_save_url();
	break;
case 'edit':
default:
	top_header();
	webseer_edit_url();
	bottom_footer();
	break;
}

function webseer_save_url() {
	/* ================= input validation ================= */
	get_filter_request_var('id');
	get_filter_request_var('downtrigger');
	get_filter_request_var('timeout_trigger');
	/* ==================================================== */

	if (isset_request_var('id')) {
		$save['id'] = get_request_var('id');
	} else {
		$save['id'] = 0;
	}

	if (isset_request_var('enabled')) {
		$save['enabled'] = 'on';
	} else {
		$save['enabled'] = 'off';
	}

	if (isset_request_var('requiresauth')) {
		$save['requiresauth'] = 'on';
	} else {
		$save['requiresauth'] = 'off';
	}

	if (isset_request_var('checkcert')) {
		$save['checkcert'] = 'on';
	} else {
		$save['checkcert'] = 'off';
	}

	if (isset_request_var('notify_accounts')) {
		if (is_array(get_request_var('notify_accounts'))) {
			foreach (get_request_var('notify_accounts') as $na) {
				input_validate_input_number($na);
			}
			$save['notify_accounts'] = implode(',', get_request_var('notify_accounts'));
		} else {
			set_request_var('notify_accounts', '');
		}
	} else {
		$save['notify_accounts'] = '';
	}

	$save['display_name']    = get_nfilter_request_var('display_name');
	$save['url']             = get_nfilter_request_var('url');
	$save['ip']              = get_nfilter_request_var('ip');
	$save['search']          = get_nfilter_request_var('search');
	$save['search_maint']    = get_nfilter_request_var('search_maint');
	$save['search_failed']   = get_nfilter_request_var('search_failed');

	$save['notify_extra']    = get_nfilter_request_var('notify_extra');
	$save['downtrigger']     = get_filter_request_var('downtrigger');
	$save['timeout_trigger'] = get_filter_request_var('timeout_trigger');

	$id = sql_save($save, 'plugin_webseer_urls', 'id');

	plugin_webseer_remove_old_users();

	if (is_error_message()) {
		header('Location: webseer_edit.php?header=falseaction=edit&id=' . (empty($id) ? get_request_var('id') : $id));
		exit;
	}
	if ($save['id'] == 0) {
		plugin_webseer_add_remote_hosts ($id, $save);
	} else {
		plugin_webseer_update_remote_hosts ($save);
	}

	header('Location: webseer.php?header=false');
	exit;
}

function webseer_edit_url () {
	// THOLD IS REQUIRED
	$send_notification_array = array();
	$users = db_fetch_assoc("SELECT plugin_thold_contacts.id, plugin_thold_contacts.data, 
		plugin_thold_contacts.type, user_auth.full_name 
		FROM plugin_thold_contacts, user_auth 
		WHERE user_auth.id = plugin_thold_contacts.user_id 
		AND plugin_thold_contacts.data != '' 
		ORDER BY user_auth.full_name ASC, plugin_thold_contacts.type ASC");

	if (!empty($users)) {
		foreach ($users as $user) {
			$send_notification_array[$user['id']] = $user['full_name'] . ' - ' . ucfirst($user['type']);
		}
	}

	/* ================= input validation ================= */
	get_filter_request_var('id');
	/* ==================================================== */

	$url = array();
	if (!isempty_request_var('id')) {
		$url = db_fetch_row('SELECT * FROM plugin_webseer_urls WHERE id=' . get_request_var('id'), FALSE);
		$header_label = __('Query [edit: %s]', $url['url']);
		$url['notify_accounts'] = explode(',', $url['notify_accounts']);
	}else{
		$header_label = __('Query [new]');
		$url['notify_accounts'] = array();
	}

	$sql = 'SELECT id FROM plugin_thold_contacts 
		WHERE id = ' . (!empty($url['notify_accounts']) ? implode($url['notify_accounts'], ' OR id = ') : 0);

	$url_edit = array(
		'enabled' => array(
			'method' => 'checkbox',
			'friendly_name' => __('Enable URL'),
			'description' => __('Uncheck this box to disabled this url from being checked.'),
			'value' => '|arg1:enabled|',
			'default' => 'on',
			),
		'display_name' => array(
			'method' => 'textbox',
			'friendly_name' => __('Display Name'),
			'description' => __('The name that is displayed for this web site in alerts.'),
			'value' => '|arg1:display_name|',
			'max_length' => '256',
			),
		'url' => array(
			'method' => 'textbox',
			'friendly_name' => __('URL'),
			'description' => __('The URL to Monitor'),
			'value' => '|arg1:url|',
			'max_length' => '256',
			),
		'ip' => array(
			'method' => 'textbox',
			'friendly_name' => __('IP Address'),
			'description' => __('Enter an IP address to connect to.  Leaving blank will use DNS Resolution instead.'),
			'value' => '|arg1:ip|',
			'max_length' => '128',
			),
		'search' => array(
			'method' => 'textbox',
			'friendly_name' => __('Search String'),
			'description' => __('This is the string to search for.'),
			'value' => '|arg1:search|',
			'max_length' => '256',
			),
		'search_maint' => array(
			'method' => 'textbox',
			'friendly_name' => __('Search String - Maintenance Page'),
			'description' => __('This is the string to search for on the Maintenance Page.  It will check for this string if the above string is not found.'),
			'value' => '|arg1:search_maint|',
			'max_length' => '256',
			),
		'search_failed' => array(
			'method' => 'textbox',
			'friendly_name' => __('Search String - Failed'),
			'description' => __('This is the string to search for a known failure.  It will only alert if this string is found, ignoring any timeout issues and the search strings above.'),
			'value' => '|arg1:search_failed|',
			'max_length' => '256',
			),
		'requiresauth' => array(
			'method' => 'checkbox',
			'friendly_name' => __('Requires Authenication'),
			'description' => __('Check this box if the site will normally return a 401 Error as it requires a username and password.'),
			'value' => '|arg1:requiresauth|',
			'default' => '',
			),
		'checkcert' => array(
			'method' => 'checkbox',
			'friendly_name' => __('Check Certificate'),
			'description' => __('If using SSL, check this box if you want to validate the certificate. Default on, turn off if you the site uses a self signed certificate.'),
			'value' => '|arg1:checkcert|',
			'default' => 'on',
			),
		'downtrigger' => array(
			'friendly_name' => __('Trigger'),
			'method' => 'drop_array',
			'array' => array(
				1  => __('%d Minute', 1), 
				2  => __('%d Minutes', 2), 
				3  => __('%d Minutes', 3), 
				4  => __('%d Minutes', 4), 
				5  => __('%d Minutes', 5), 
				6  => __('%d Minutes', 6), 
				7  => __('%d Minutes', 7), 
				8  => __('%d Minutes', 8), 
				9  => __('%d Minutes', 9), 
				10 => __('%d Minutes', 10)
			),
			'default' => 3,
			'description' => __('How many minutes the URL must be down before it will send an alert.  After an alert is sent, in order for a "Site Recovering" email to be send, it must also be up this number of minutes.'),
			'value' => '|arg1:downtrigger|',
		),
		'timeout_trigger' => array(
			'friendly_name' => __('Time Out'),
			'method' => 'drop_array',
			'array' => array(
				3  => __('%d Seconds', 3), 
				4  => __('%d Seconds', 4), 
				5  => __('%d Seconds', 5), 
				6  => __('%d Seconds', 6), 
				7  => __('%d Seconds', 7), 
				8  => __('%d Seconds', 8), 
				9  => __('%d Seconds', 9), 
				10 => __('%d Seconds', 10)
			),
			'default' => 4,
			'description' => __('How many seconds to allow the page to timeout before reporting it as down.'),
			'value' => '|arg1:timeout_trigger|',
		),
		'notify_accounts' => array(
			'friendly_name' => __('Notify accounts'),
			'method' => 'drop_multi',
			'description' => __('This is a listing of accounts that will be notified when this website goes down.'),
			'array' => $send_notification_array,
			'sql' => $sql,
			),
		'notify_extra' => array(
			'friendly_name' => __('Extra Alert Emails'),
			'method' => 'textarea',
			'textarea_rows' => 3,
			'textarea_cols' => 50,
			'description' => __('You may specify here extra e-mails to receive alerts for this URL (comma separated)'),
			'value' => '|arg1:notify_extra|',
			),
		'id' => array(
			'method' => 'hidden_zero',
			'value' => '|arg1:id|'
			),
		);

	form_start('webseer_edit.php');

	html_start_box($header_label, '100%', '', '3', 'center', '');

	draw_edit_form(
		array(
			'config' => array('form_name' => 'chk'),
			'fields' => inject_form_variables($url_edit, $url)
		)
	);

	html_end_box();

	form_save_button('webseer.php', 'return');
}


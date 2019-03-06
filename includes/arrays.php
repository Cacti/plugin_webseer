<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2019 The Cacti Group                                 |
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

include_once(__DIR__ . '/constants.php');

global	$webseer_actions_proxy, $webseer_actions_url, $webseer_actions_server,
	$webseer_proxy_fields, $webseer_server_fields, $webseer_url_fields,
	$webseer_notify_accounts, $httperrors, $httpcompressions, $webseer_seconds,
	$webseer_minutes;

$httperrors = array(
	  0 => 'Unable to Connect',
	100 => 'Continue',
	101 => 'Switching Protocols',
	200 => 'OK',
	201 => 'Created',
	202 => 'Accepted',
	203 => 'Non-Authoritative Information',
	204 => 'No Content',
	205 => 'Reset Content',
	206 => 'Partial Content',
	300 => 'Multiple Choices',
	301 => 'Moved Permanently',
	302 => 'Found',
	303 => 'See Other',
	304 => 'Not Modified',
	305 => 'Use Proxy',
	306 => '(Unused)',
	307 => 'Temporary Redirect',
	400 => 'Bad Request',
	401 => 'Unauthorized',
	402 => 'Payment Required',
	403 => 'Forbidden',
	404 => 'Not Found',
	405 => 'Method Not Allowed',
	406 => 'Not Acceptable',
	407 => 'Proxy Authentication Required',
	408 => 'Request Timeout',
	409 => 'Conflict',
	410 => 'Gone',
	411 => 'Length Required',
	412 => 'Precondition Failed',
	413 => 'Request Entity Too Large',
	414 => 'Request-URI Too Long',
	415 => 'Unsupported Media Type',
	416 => 'Requested Range Not Satisfiable',
	417 => 'Expectation Failed',
	500 => 'Internal Server Error',
	501 => 'Not Implemented',
	502 => 'Bad Gateway',
	503 => 'Service Unavailable',
	504 => 'Gateway Timeout',
	505 => 'HTTP Version Not Supported',
);

$httpcompressions = array(
	0  => '',
	1  => 'aes128gcm',
	2  => 'br',
	3  => 'compress',
	4  => 'deflate',
	5  => 'exi',
	6  => 'gzip',
	7  => 'identity',
	8  => 'pack200-gzip',
	9  => 'x-compress',
	10 => 'x-gzip',
	11 => 'zstd',
);

$webseer_minutes = array(
	1  => __('%d Minute', 1, 'webseer'),
	2  => __('%d Minutes', 2, 'webseer'),
	3  => __('%d Minutes', 3, 'webseer'),
	4  => __('%d Minutes', 4, 'webseer'),
	5  => __('%d Minutes', 5, 'webseer'),
	6  => __('%d Minutes', 6, 'webseer'),
	7  => __('%d Minutes', 7, 'webseer'),
	8  => __('%d Minutes', 8, 'webseer'),
	9  => __('%d Minutes', 9, 'webseer'),
	10 => __('%d Minutes', 10, 'webseer'),
);

$webseer_seconds = array(
	3  => __('%d Seconds', 3, 'webseer'),
	4  => __('%d Seconds', 4, 'webseer'),
	5  => __('%d Seconds', 5, 'webseer'),
	6  => __('%d Seconds', 6, 'webseer'),
	7  => __('%d Seconds', 7, 'webseer'),
	8  => __('%d Seconds', 8, 'webseer'),
	9  => __('%d Seconds', 9, 'webseer'),
	10 => __('%d Seconds', 10, 'webseer'),
);

$webseer_notify_formats = array(
	WEBSEER_FORMAT_HTML  => 'html',
	WEBSEER_FORMAT_PLAIN => 'plain',
);

if (db_table_exists('plugin_webseer_contacts')) {
	$webseer_contact_users = db_fetch_assoc("SELECT pwc.id, pwc.data, pwc.type, ua.full_name
		FROM plugin_webseer_contacts AS pwc
		LEFT JOIN user_auth AS ua
		ON ua.id=pwc.user_id
		WHERE pwc.data != ''");
} else {
	$webseer_contact_users = array();
}

$webseer_notify_accounts = array();
if (!empty($webseer_contact_users)) {
	foreach ($webseer_contact_users as $webseer_contact_user) {
		$webseer_notify_accounts[$webseer_contact_user['id']] = $webseer_contact_user['full_name'] . ' - ' . ucfirst($webseer_contact_user['type']);
	}
}

/**** Actions ****/

$webseer_actions_proxy = array(
	WEBSEER_ACTION_PROXY_DELETE => __('Delete', 'webseer'),
);

$webseer_actions_url = array(
	WEBSEER_ACTION_URL_DELETE    => __('Delete', 'webseer'),
	WEBSEER_ACTION_URL_DISABLE   => __('Disable', 'webseer'),
	WEBSEER_ACTION_URL_ENABLE    => __('Enable', 'webseer'),
	WEBSEER_ACTION_URL_DUPLICATE => __('Duplicate', 'webseer'),
);

$webseer_actions_server = array(
	WEBSEER_ACTION_SERVER_DELETE    => __('Delete', 'webseer'),
	WEBSEER_ACTION_SERVER_DISABLE   => __('Disable', 'webseer'),
	WEBSEER_ACTION_SERVER_ENABLE    => __('Enable', 'webseer'),
);

/**** Form Fields ****/

$webseer_proxy_fields = array(
	'name' => array(
		'method' => 'textbox',
		'friendly_name' => __('Name'),
		'description' => __('A Useful Name for this Proxy.'),
		'value' => '|arg1:name|',
		'max_length' => '40',
		'size' => '40',
		'default' => __('New Proxy')
	),
	'hostname' => array(
		'method' => 'textbox',
		'friendly_name' => __('Hostname'),
		'description' => __('The Proxy Hostname.'),
		'value' => '|arg1:hostname|',
		'max_length' => '64',
		'size' => '40',
		'default' => ''
	),
	'http_port' => array(
		'method' => 'textbox',
		'friendly_name' => __('HTTP Port'),
		'description' => __('The HTTP Proxy Port.'),
		'value' => '|arg1:http_port|',
		'max_length' => '5',
		'size' => '5',
		'default' => '80'
	),
	'https_port' => array(
		'method' => 'textbox',
		'friendly_name' => __('HTTPS Port'),
		'description' => __('The HTTPS Proxy Port.'),
		'value' => '|arg1:https_port|',
		'max_length' => '5',
		'size' => '5',
		'default' => '443'
	),
	'username' => array(
		'method' => 'textbox',
		'friendly_name' => __('User Name'),
		'description' => __('The user to use to authenticate with the Proxy if any.'),
		'value' => '|arg1:username|',
		'max_length' => '64',
		'size' => '40',
		'default' => ''
	),
	'password' => array(
		'method' => 'textbox_password',
		'friendly_name' => __('Password'),
		'description' => __('The user password to use to authenticate with the Proxy if any.'),
		'value' => '|arg1:password|',
		'max_length' => '40',
		'size' => '40',
		'default' => ''
	),
	'id' => array(
		'method' => 'hidden_zero',
		'value' => '|arg1:id|'
	),
	'save_component_proxy' => array(
		'method' => 'hidden',
		'value' => '1'
	)
);

$webseer_server_fields = array(
	'general_spacer' => array(
		'method' => 'spacer',
		'friendly_name' => __('General Settings', 'webseer')
	),
	'name' => array(
		'method' => 'textbox',
		'friendly_name' => __('Name', 'webseer'),
		'description' => __('Display Name of this server', 'webseer'),
		'value' => '|arg1:name|',
		'max_length' => '256',
	),
	'enabled' => array(
		'method' => 'checkbox',
		'friendly_name' => __('Enable Server', 'webseer'),
		'description' => __('Uncheck this box to disabled this server from checking urls.', 'webseer'),
		'value' => '|arg1:enabled|',
		'default' => '',
	),
	'isme' => array(
		'method' => 'checkbox',
		'friendly_name' => __('Is this server the local server?', 'webseer'),
		'description' => __('Check this box if the current server you are connected to is this entry.', 'webseer'),
		'value' => '|arg1:isme|',
		'default' => '',
	),
	'master' => array(
		'method' => 'checkbox',
		'friendly_name' => __('Master Server', 'webseer'),
		'description' => __('Sets this server to the Master server.  The Master server handles all Email operations', 'webseer'),
		'value' => '|arg1:master|',
		'default' => '',
	),
	'ip' => array(
		'method' => 'textbox',
		'friendly_name' => __('IP Address', 'webseer'),
		'description' => __('IP Address to connect to this server', 'webseer'),
		'value' => '|arg1:ip|',
		'max_length' => '256',
	),
	'url' => array(
		'method' => 'textbox',
		'friendly_name' => __('URL', 'webseer'),
		'description' => __('This is the URL to connect to remote.php on this server.', 'webseer'),
		'value' => '|arg1:url|',
		'max_length' => '256',
	),
	'location' => array(
		'method' => 'textbox',
		'friendly_name' => __('Location', 'webseer'),
		'description' => __('Location of this server', 'webseer'),
		'value' => '|arg1:location|',
		'max_length' => '256',
	),
	'id' => array(
		'method' => 'hidden_zero',
		'value' => '|arg1:id|'
	),
);

$webseer_url_fields = array(
	'general_spacer' => array(
		'method' => 'spacer',
		'friendly_name' => __('General Settings', 'webseer')
	),
	'display_name' => array(
		'method' => 'textbox',
		'friendly_name' => __('Service Check Name', 'webseer'),
		'description' => __('The name that is displayed for this Service Check, and is included in any Alert notifications.', 'webseer'),
		'value' => '|arg1:display_name|',
		'max_length' => '256',
	),
	'enabled' => array(
		'method' => 'checkbox',
		'friendly_name' => __('Enable Service Check', 'webseer'),
		'description' => __('Uncheck this box to disabled this url from being checked.', 'webseer'),
		'value' => '|arg1:enabled|',
		'default' => 'on',
	),
	'url' => array(
		'method' => 'textarea',
		'friendly_name' => __('URL', 'webseer'),
		'description' => __('The URL to Monitor', 'webseer'),
		'value' => '|arg1:url|',
		'textarea_rows' => '3',
		'textarea_cols' => '80',
	),
	'ip' => array(
		'method' => 'textbox',
		'friendly_name' => __('IP Address', 'webseer'),
		'description' => __('Enter an IP address to connect to.  Leaving blank will use DNS Resolution instead.', 'webseer'),
		'value' => '|arg1:ip|',
		'max_length' => '40',
		'size' => '30'
	),
	'proxy_server' => array(
		'method' => 'drop_sql',
		'friendly_name' => __('Proxy Server', 'webseer'),
		'description' => __('If this connection text requires a proxy, select it here.  Otherwise choose \'None\'.', 'webseer'),
		'value' => '|arg1:proxy_server|',
		'none_value' => __('None', 'webseer'),
		'default' => '0',
		'sql' => 'SELECT id, name FROM plugin_webseer_proxies ORDER by name'
	),
	'compression' => array(
		'friendly_name' => __('Compression', 'webseer'),
		'method' => 'drop_array',
		'array' => $httpcompressions,
		'default' => 0,
		'description' => __('What compression does the server require?  Most servers should not need this, but some will not redirect properly without it.', 'webseer'),
		'value' => '|arg1:compression|',
	),
	'checks_spacer' => array(
		'method' => 'spacer',
		'friendly_name' => __('Available Checks', 'webseer')
	),
	'requiresauth' => array(
		'method' => 'checkbox',
		'friendly_name' => __('Requires Authentication', 'webseer'),
		'description' => __('Check this box if the site will normally return a 401 Error as it requires a username and password.', 'webseer'),
		'value' => '|arg1:requiresauth|',
		'default' => '',
	),
	'checkcert' => array(
		'method' => 'checkbox',
		'friendly_name' => __('Check Certificate', 'webseer'),
		'description' => __('If using SSL, check this box if you want to validate the certificate. Default on, turn off if you the site uses a self-signed certificate.', 'webseer'),
		'value' => '|arg1:checkcert|',
		'default' => '',
	),
	'timings_spacer' => array(
		'method' => 'spacer',
		'friendly_name' => __('Notification Timing', 'webseer')
	),
	'downtrigger' => array(
		'friendly_name' => __('Trigger', 'webseer'),
		'method' => 'drop_array',
		'array' => $webseer_minutes,
		'default' => 3,
		'description' => __('How many minutes the URL must be down before it will send an alert.  After an alert is sent, in order for a \'Site Recovering\' Email to be send, it must also be up this number of minutes.', 'webseer'),
		'value' => '|arg1:downtrigger|',
	),
	'timeout_trigger' => array(
		'friendly_name' => __('Time Out', 'webseer'),
		'method' => 'drop_array',
		'array' => $webseer_seconds,
		'default' => 4,
		'description' => __('How many seconds to allow the page to timeout before reporting it as down.', 'webseer'),
		'value' => '|arg1:timeout_trigger|',
	),
	'verifications_spacer' => array(
		'method' => 'spacer',
		'friendly_name' => __('Verification Strings', 'webseer')
	),
	'search' => array(
		'method' => 'textarea',
		'friendly_name' => __('Response Search String', 'webseer'),
		'description' => __('This is the string to search for in the URL response for a live and working Web Service.', 'webseer'),
		'value' => '|arg1:search|',
		'textarea_rows' => '3',
		'textarea_cols' => '80',
	),
	'search_maint' => array(
		'method' => 'textarea',
		'friendly_name' => __('Response Search String - Maintenance Page', 'webseer'),
		'description' => __('This is the string to search for on the Maintenance Page.  The Service Check will check for this string if the above string is not found.  If found, it means that the Web Service is under maintenance.', 'webseer'),
		'value' => '|arg1:search_maint|',
		'textarea_rows' => '3',
		'textarea_cols' => '80',
	),
	'search_failed' => array(
		'method' => 'textarea',
		'friendly_name' => __('Response Search String - Failed', 'webseer'),
		'description' => __('This is the string to search for a known failure in the Web Service response.  The Service Check will only alert if this string is found, ignoring any timeout issues and the search strings above.', 'webseer'),
		'value' => '|arg1:search_failed|',
		'textarea_rows' => '3',
		'textarea_cols' => '80',
	),
	'notification_spacer' => array(
		'method' => 'spacer',
		'friendly_name' => __('Notification Settings', 'webseer')
	),
	'notify_format' => array(
		'friendly_name' => __('Notify Format', 'webseer'),
		'method' => 'drop_array',
		'description' => __('This is the format to use when sending the notification email', 'webseer'),
		'array' => $webseer_notify_formats,
		'value' => '|arg1:notify_format|',
	),
	'notify_accounts' => array(
		'friendly_name' => __('Notify accounts', 'webseer'),
		'method' => 'drop_multi',
		'description' => __('This is a listing of accounts that will be notified when this website goes down.', 'webseer'),
		'array' => $webseer_notify_accounts,
		'value' => '|arg1:notify_accounts|',
	),
	'notify_extra' => array(
		'friendly_name' => __('Extra Alert Emails', 'webseer'),
		'method' => 'textarea',
		'textarea_rows' => 3,
		'textarea_cols' => 50,
		'description' => __('You may specify here extra Emails to receive alerts for this URL (comma separated)', 'webseer'),
		'value' => '|arg1:notify_extra|',
	),
	'id' => array(
		'method' => 'hidden_zero',
		'value' => '|arg1:id|'
	),
);

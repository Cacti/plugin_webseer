<?php

$proxy_source  = file_get_contents(dirname(__DIR__, 2) . '/webseer_proxies.php');
$url_source    = file_get_contents(dirname(__DIR__, 2) . '/webseer.php');
$server_source = file_get_contents(dirname(__DIR__, 2) . '/webseer_servers.php');
$remote_source = file_get_contents(dirname(__DIR__, 2) . '/remote.php');

$checks = [
	$proxy_source !== false && strpos($proxy_source, '<li>\' . html_escape(db_fetch_cell_prepared(\'SELECT name FROM plugin_webseer_proxies WHERE id = ?\', array($matches[1]))) . \'</li>') !== false,
	$proxy_source !== false && strpos($proxy_source, "(name LIKE ' . db_qstr('%' . get_request_var('filter') . '%') . ' OR hostname LIKE ' . db_qstr('%' . get_request_var('filter') . '%') . ')") !== false,
	$url_source !== false && strpos($url_source, '<li>\' . html_escape(db_fetch_cell_prepared(\'SELECT display_name FROM plugin_webseer_urls WHERE id = ?\', array($matches[1]))) . \'</li>') !== false,
	$server_source !== false && strpos($server_source, '<li>\' . html_escape(db_fetch_cell_prepared(\'SELECT name FROM plugin_webseer_servers WHERE id = ?\', array($matches[1]))) . \'</li>') !== false,
	$remote_source !== false && strpos($remote_source, "db_fetch_row_prepared('SELECT * FROM plugin_webseer_servers WHERE ip = ?', array(\$ip))") !== false,
];

foreach ($checks as $passed) {
	if (!$passed) {
		fwrite(STDERR, "Webseer security wiring check failed\n");
		exit(1);
	}
}

print "OK\n";

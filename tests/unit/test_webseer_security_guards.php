<?php

$files = [
	'webseer_proxies.php' => [
		"html_escape(db_fetch_cell_prepared('SELECT name FROM plugin_webseer_proxies WHERE id = ?', array(\$matches[1])))",
		"html_escape(get_nfilter_request_var('drp_action'))",
		"(name LIKE ' . db_qstr('%' . get_request_var('filter') . '%') . ' OR hostname LIKE ' . db_qstr('%' . get_request_var('filter') . '%') . ')'",
	],
	'webseer.php' => [
		"html_escape(db_fetch_cell_prepared('SELECT display_name FROM plugin_webseer_urls WHERE id = ?', array(\$matches[1])))",
		"html_escape(get_nfilter_request_var('drp_action'))",
	],
	'webseer_servers.php' => [
		"html_escape(db_fetch_cell_prepared('SELECT name FROM plugin_webseer_servers WHERE id = ?', array(\$matches[1])))",
		"html_escape(get_nfilter_request_var('drp_action'))",
	],
	'remote.php' => [
		"db_fetch_row_prepared('SELECT * FROM plugin_webseer_servers WHERE ip = ?', array(\$ip))",
	],
];

foreach ($files as $file => $needles) {
	$source = file_get_contents(dirname(__DIR__, 2) . '/' . $file);

	if ($source === false) {
		fwrite(STDERR, "Unable to read $file\n");
		exit(1);
	}

	foreach ($needles as $needle) {
		if (strpos($source, $needle) === false) {
			fwrite(STDERR, "Missing expected guard in $file\n");
			exit(1);
		}
	}
}

print "OK\n";

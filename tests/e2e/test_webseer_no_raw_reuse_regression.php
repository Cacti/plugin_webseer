<?php

$files = [
	'webseer_proxies.php',
	'webseer.php',
	'webseer_servers.php',
	'remote.php',
];

$legacy_needles = [
	"db_fetch_row(\"SELECT * FROM plugin_webseer_servers WHERE ip = '\$ip'\")",
	"<input type='hidden' name='drp_action' value='\" . get_nfilter_request_var('drp_action') . \"'>",
	"<li>' . db_fetch_cell_prepared('SELECT name FROM plugin_webseer_proxies WHERE id = ?', array(\$matches[1])) . '</li>'",
	"<li>' . db_fetch_cell_prepared('SELECT display_name FROM plugin_webseer_urls WHERE id = ?', array(\$matches[1])) . '</li>'",
	"<li>' . db_fetch_cell_prepared('SELECT name FROM plugin_webseer_servers WHERE id = ?', array(\$matches[1])) . '</li>'",
	' name LIKE "%\' . get_request_var(\'filter\') . \'%" OR hostname LIKE "%\' . get_request_var(\'filter\') . \'%"',
];

foreach ($files as $file) {
	$source = file_get_contents(dirname(__DIR__, 2) . '/' . $file);

	if ($source === false) {
		fwrite(STDERR, "Unable to read $file\n");
		exit(1);
	}

	foreach ($legacy_needles as $needle) {
		if (strpos($source, $needle) !== false) {
			fwrite(STDERR, "Found legacy insecure pattern in $file\n");
			exit(1);
		}
	}
}

print "OK\n";

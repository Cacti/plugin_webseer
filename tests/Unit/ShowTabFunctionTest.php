<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * Behavioral coverage for webseer_show_tab() in includes/functions.php:
 * the tab bar rendered at the top of webseer.php/webseer_servers.php/
 * webseer_proxies.php, including the "Log History" tab substitution.
 */

require_once dirname(__DIR__, 2) . '/includes/functions.php';

beforeEach(function () {
	webseer_test_set_request(['action' => '']);
});

it('renders the three static tabs', function () {
	ob_start();
	webseer_show_tab('webseer.php');
	$output = ob_get_clean();

	expect($output)->toContain("href='/cacti/plugins/webseer/webseer.php'");
	expect($output)->toContain("href='/cacti/plugins/webseer/webseer_servers.php'");
	expect($output)->toContain("href='/cacti/plugins/webseer/webseer_proxies.php'");
});

it('marks the current tab as selected', function () {
	ob_start();
	webseer_show_tab('webseer_servers.php');
	$output = ob_get_clean();

	expect($output)->toContain("class='pic selected' href='/cacti/plugins/webseer/webseer_servers.php'");
	expect($output)->toContain("class='pic' href='/cacti/plugins/webseer/webseer.php'");
});

it('substitutes a Log History tab for the checks page during history actions', function () {
	webseer_test_set_request(['action' => 'history', 'id' => '7']);

	ob_start();
	webseer_show_tab('webseer.php');
	$output = ob_get_clean();

	expect($output)->toContain("href='/cacti/plugins/webseer/webseer.php?action=history&id=7'");
});

it('substitutes a Log History tab for the servers page during history actions', function () {
	webseer_test_set_request(['action' => 'history', 'id' => '3']);

	ob_start();
	webseer_show_tab('webseer_servers.php');
	$output = ob_get_clean();

	expect($output)->toContain("href='/cacti/plugins/webseer/webseer_servers.php?action=history&id=3'");
});

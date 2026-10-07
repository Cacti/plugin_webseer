<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * Regression coverage for the CSP migration of the confirmation-page Cancel
 * buttons. The three entry points below are exempt from the patch-coverage
 * gate, so without this source-level assertion CI would not catch a missing
 * `cactiReturnTo` class or a reintroduced inline `onClick='cactiReturnTo()'`
 * handler (which trips Cacti's Content-Security-Policy script-src-attr
 * directive).
 *
 * Each entry's value is the number of Cancel buttons converted in that file;
 * the total must stay at eight.
 */

$cancel_button_files = array(
	'webseer.php'         => 4,
	'webseer_servers.php' => 3,
	'webseer_proxies.php' => 1,
);

it('has removed every inline cactiReturnTo onClick handler', function () use ($cancel_button_files) {
	foreach (array_keys($cancel_button_files) as $file) {
		$source = plugin_test_read_source($file);

		expect($source)->not->toMatch('/onClick\s*=\s*[\'"]cactiReturnTo\(/i', "{$file} still contains an inline cactiReturnTo onClick handler");
	}
});

it('binds every Cancel button through the CSP-safe cactiReturnTo class', function () use ($cancel_button_files) {
	$total = 0;

	foreach ($cancel_button_files as $file => $expected) {
		$source = plugin_test_read_source($file);

		$count = preg_match_all('/class\s*=\s*[\'"]cactiReturnTo[\'"]/i', $source);

		expect($count)->toBe($expected, "{$file} should expose {$expected} cactiReturnTo class button(s), found {$count}");

		$total += $count;
	}

	expect($total)->toBe(8);
});

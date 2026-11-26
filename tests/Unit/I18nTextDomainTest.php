<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+

/*
 * Every translation helper call in the plugin's own PHP files must pass the
 * 'webseer' text domain, so its strings are looked up in this plugin's catalog
 * and not silently in Cacti's core one. The scan itself lives in
 * tests/bin/check-i18n-domain.php; it uses PHP's tokenizer and needs no Cacti
 * checkout.
 */

require_once __DIR__ . '/../bin/check-i18n-domain.php';

it('passes the webseer text domain to every translation helper call', function () {
	/*
	 * Calls that already lacked the domain before this check existed, as
	 * file => number of calls. They are listed so the check passes without
	 * widening the change that introduced it. The count must match exactly:
	 * fixing a call without lowering its count here fails the test, so the
	 * list can only shrink. Do not add entries for new code.
	 */
	$known = [
		// option labels
		'includes/arrays.php' => 4,
		// 'Cacti Master' label
		'setup.php' => 1,
		// delete confirmation
		'webseer.php' => 8,
		// proxy list and edit labels, delete confirmation
		'webseer_proxies.php' => 2,
		// delete confirmation
		'webseer_servers.php' => 6,
	];

	$found = [];

	foreach (i18n_domain_offenders(dirname(__DIR__, 2), 'webseer') as $offender) {
		$found[$offender['file']][] = $offender['file'] . ':' . $offender['line'] . ' ' . $offender['call'] . ': ' . $offender['reason'];
	}

	$unexpected = [];

	foreach ($found as $file => $lines) {
		if (count($lines) !== ($known[$file] ?? 0)) {
			$unexpected = array_merge($unexpected, $lines);
		}
	}

	foreach ($known as $file => $count) {
		if (!isset($found[$file])) {
			$unexpected[] = $file . ': expected ' . $count . ' known calls without the text domain, found none; remove it from the list';
		}
	}

	expect($unexpected)->toBe([]);
});

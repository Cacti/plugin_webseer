<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * Behavioral coverage for the constants declared in includes/constants.php:
 * action, compression, and notification-format identifiers used throughout
 * the plugin's UI and remote-server protocol.
 */

require_once dirname(__DIR__, 2) . '/includes/constants.php';

it('defines distinct proxy action identifiers', function () {
	expect(WEBSEER_ACTION_PROXY_DELETE)->toBe(1);
});

it('defines distinct url action identifiers', function () {
	$actions = [
		WEBSEER_ACTION_URL_DELETE,
		WEBSEER_ACTION_URL_DISABLE,
		WEBSEER_ACTION_URL_ENABLE,
		WEBSEER_ACTION_URL_DUPLICATE,
	];

	expect($actions)->toBe(array_unique($actions));
});

it('defines distinct server action identifiers', function () {
	$actions = [
		WEBSEER_ACTION_SERVER_DELETE,
		WEBSEER_ACTION_SERVER_DISABLE,
		WEBSEER_ACTION_SERVER_ENABLE,
	];

	expect($actions)->toBe(array_unique($actions));
});

it('defines the expected compression identifiers', function () {
	expect(WEBSEER_COMPRESSION_NONE)->toBe(0);
	expect(WEBSEER_COMPRESSION_GZIP)->toBe(6);
});

it('defines distinct notify format identifiers', function () {
	expect(WEBSEER_FORMAT_HTML)->not->toBe(WEBSEER_FORMAT_PLAIN);
});

<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * Behavioral coverage for the plugin_webseer_check_debug() and
 * plugin_webseer_debug() selective-debug helpers in includes/functions.php.
 *
 * cacti_log() calls are captured in $GLOBALS['__test_log_calls'] by the
 * stub declared in tests/bootstrap-unit.php.
 */

require_once dirname(__DIR__, 2) . '/includes/functions.php';

beforeEach(function () {
	$GLOBALS['__test_log_calls'] = [];
});

it('leaves debug disabled when selective_plugin_debug does not match webseer', function () {
	global $debug;
	$debug = false;

	webseer_test_set_config_option('selective_plugin_debug', 'thold, mactrack');

	plugin_webseer_check_debug();

	expect($debug)->toBeFalse();
});

it('enables debug when selective_plugin_debug lists webseer', function () {
	global $debug;
	$debug = false;

	webseer_test_set_config_option('selective_plugin_debug', 'thold, webseer, mactrack');

	plugin_webseer_check_debug();

	expect($debug)->toBeTrue();
});

it('does not re-evaluate selective_plugin_debug once debug is already on', function () {
	global $debug;
	$debug = true;

	webseer_test_set_config_option('selective_plugin_debug', '');

	plugin_webseer_check_debug();

	expect($debug)->toBeTrue();
});

it('does not log anything when debug is disabled', function () {
	global $debug;
	$debug = false;

	plugin_webseer_debug('hello');

	expect($GLOBALS['__test_log_calls'])->toBe([]);
});

it('logs a plain message when debug is enabled and no host context is given', function () {
	global $debug;
	$debug = true;

	plugin_webseer_debug('hello');

	expect($GLOBALS['__test_log_calls'])->toHaveCount(1);
	expect($GLOBALS['__test_log_calls'][0]['message'])->toBe('DEBUG: hello');
	expect($GLOBALS['__test_log_calls'][0]['log_type'])->toBe('WEBSEER');
});

it('prefixes the message with host id and debug type when host context is given', function () {
	global $debug;
	$debug = true;

	plugin_webseer_debug('checking', ['id' => 42, 'debug_type' => 'Server']);

	expect($GLOBALS['__test_log_calls'][0]['message'])->toBe('DEBUG: [Server 42] checking');
});

<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * Verify setup.php defines required plugin hooks and info function.
 */

$source = plugin_test_read_source('setup.php');

$infoFile = parse_ini_file(__DIR__ . '/../../INFO', true);
if (!is_array($infoFile) || !isset($infoFile['info']) || !is_array($infoFile['info'])) {
	throw new RuntimeException('Unable to parse the INFO section');
}
$info = $infoFile['info'];

it('defines plugin_webseer_install function', function () use ($source) {
	expect($source)->toContain('function plugin_webseer_install');
});

it('defines plugin_webseer_version function', function () use ($source) {
	expect($source)->toContain('function plugin_webseer_version');
});

it('defines plugin_webseer_uninstall function', function () use ($source) {
	expect($source)->toContain('function plugin_webseer_uninstall');
});

it('declares a plugin name in INFO', function () use ($info) {
	expect($info)->toHaveKey('name');
});

it('declares a plugin version in INFO', function () use ($info) {
	expect($info)->toHaveKey('version');
});

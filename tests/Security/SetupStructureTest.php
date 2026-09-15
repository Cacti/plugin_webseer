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

$setupPath = realpath(__DIR__ . '/../../setup.php');
	if ($setupPath === false) {
		throw new RuntimeException('Unable to resolve setup.php');
	}

	$source = file_get_contents($setupPath);
	if ($source === false) {
		throw new RuntimeException('Unable to read setup.php');
	}

	$info = parse_ini_file(__DIR__ . '/../../INFO');
	if (!is_array($info)) {
		throw new RuntimeException('Unable to parse INFO');
	}

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

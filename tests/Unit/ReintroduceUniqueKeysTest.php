<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 |                                                                         |
 | This program is distributed in the hope that it will be useful,         |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of          |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the           |
 | GNU General Public License for more details.                            |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

/*
 * Unit coverage for webseer_reintroduce_unique_keys() in includes/database.php:
 * the compensating re-add of UNIQUE keys that Cacti 1.2.29-1.2.31 dropped via
 * db_update_table(), and its success/failure reporting.
 */

beforeAll(function () {
	require_once __DIR__ . '/../../setup.php';
	require_once __DIR__ . '/../../includes/database.php';
});

beforeEach(function () {
	webseer_test_reset_db_mocks();
	$GLOBALS['__test_db_calls'] = [];
	$GLOBALS['__test_cacti_log'] = [];
});

/**
 * Collects the db_add_index() calls the bootstrap recorded during a test.
 * Each entry is shaped as the stub records it:
 * ['fn' => 'db_add_index', 'sql' => '<table>.<key>',
 *  'params' => ['type' => <string>, 'columns' => <string[]>]].
 *
 * @return array<int, array{fn: string, sql: string, params: array}> The
 *         matching call records, re-indexed from zero.
 */
function webseer_uk_added_indexes(): array {
	return array_values(array_filter($GLOBALS['__test_db_calls'], function ($call) {
		return $call['fn'] === 'db_add_index';
	}));
}

it('re-adds the contacts unique key when running Cacti 1.2.30', function () {
	webseer_test_mock_db('db_fetch_cell', 'SELECT cacti FROM version', '1.2.30');

	expect(webseer_reintroduce_unique_keys())->toBeTrue();

	$added = webseer_uk_added_indexes();
	expect($added)->toHaveCount(1);
	expect($added[0]['sql'])->toContain('plugin_webseer_contacts')
		->and($added[0]['sql'])->toContain('user_id_type')
		->and($added[0]['params']['type'])->toBe('UNIQUE')
		->and($added[0]['params']['columns'])->toBe(['user_id', 'type']);
});

it('does nothing on Cacti 1.2.32 and later', function () {
	webseer_test_mock_db('db_fetch_cell', 'SELECT cacti FROM version', '1.2.32');

	expect(webseer_reintroduce_unique_keys())->toBeTrue();
	expect(webseer_uk_added_indexes())->toHaveCount(0);
});

it('does nothing on Cacti releases before 1.2.29', function () {
	webseer_test_mock_db('db_fetch_cell', 'SELECT cacti FROM version', '1.2.28');

	expect(webseer_reintroduce_unique_keys())->toBeTrue();
	expect(webseer_uk_added_indexes())->toHaveCount(0);
});

it('does not re-add a unique key that already exists', function () {
	webseer_test_mock_db('db_fetch_cell', 'SELECT cacti FROM version', '1.2.30');
	webseer_test_mock_db('db_index_exists', 'plugin_webseer_contacts', true);

	expect(webseer_reintroduce_unique_keys())->toBeTrue();
	expect(webseer_uk_added_indexes())->toHaveCount(0);
});

it('reports failure and logs remediation when the unique key cannot be re-added', function () {
	webseer_test_mock_db('db_fetch_cell', 'SELECT cacti FROM version', '1.2.30');
	webseer_test_mock_db('db_add_index', 'plugin_webseer_contacts', false);

	expect(webseer_reintroduce_unique_keys())->toBeFalse();

	$logged = implode("\n", $GLOBALS['__test_cacti_log']);
	expect($logged)->toContain('could not re-add UNIQUE key')
		->and($logged)->toContain('de-duplicate');
});

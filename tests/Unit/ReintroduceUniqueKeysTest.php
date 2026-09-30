<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

/*
 * Unit coverage for webseer_reintroduce_unique_keys() in includes/database.php:
 * the compensating re-add of UNIQUE keys that Cacti 1.2.29-1.2.31 dropped via
 * db_update_table().
 */

beforeAll(function () {
	require_once __DIR__ . '/../../setup.php';
	require_once __DIR__ . '/../../includes/database.php';
});

beforeEach(function () {
	webseer_test_reset_db_mocks();
	$GLOBALS['__test_db_calls'] = [];
});

function webseer_uk_added_indexes(): array {
	return array_values(array_filter($GLOBALS['__test_db_calls'], function ($call) {
		return $call['fn'] === 'db_execute' && stripos($call['sql'], 'ADD UNIQUE KEY') !== false;
	}));
}

it('re-adds the contacts unique key when running Cacti 1.2.30', function () {
	webseer_test_mock_db('db_fetch_cell', 'SELECT cacti FROM version', '1.2.30');

	webseer_reintroduce_unique_keys();

	$added = webseer_uk_added_indexes();
	expect($added)->toHaveCount(1);
	expect($added[0]['sql'])->toContain('plugin_webseer_contacts')
		->and($added[0]['sql'])->toContain('user_id_type')
		->and($added[0]['sql'])->toContain('`user_id`,`type`');
});

it('does nothing on Cacti 1.2.32 and later', function () {
	webseer_test_mock_db('db_fetch_cell', 'SELECT cacti FROM version', '1.2.32');

	webseer_reintroduce_unique_keys();

	expect(webseer_uk_added_indexes())->toHaveCount(0);
});

it('does nothing on Cacti releases before 1.2.29', function () {
	webseer_test_mock_db('db_fetch_cell', 'SELECT cacti FROM version', '1.2.28');

	webseer_reintroduce_unique_keys();

	expect(webseer_uk_added_indexes())->toHaveCount(0);
});

it('does not re-add a unique key that already exists', function () {
	webseer_test_mock_db('db_fetch_cell', 'SELECT cacti FROM version', '1.2.30');
	webseer_test_mock_db('db_index_exists', 'plugin_webseer_contacts', true);

	webseer_reintroduce_unique_keys();

	expect(webseer_uk_added_indexes())->toHaveCount(0);
});

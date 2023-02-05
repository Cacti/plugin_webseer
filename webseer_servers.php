<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2023 The Cacti Group                                 |
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

chdir('../../');
include_once('./include/auth.php');
include_once($config['base_path'] . '/plugins/webseer/includes/functions.php');

set_default_action();

switch (get_request_var('action')) {
case 'save':
	form_save();

	break;
case 'actions':
	form_actions();

	break;
case 'enable':
	$id = get_request_var('id');

	if ($id > 0) {
		db_execute_prepared('UPDATE plugin_webseer_servers SET enabled = "on" WHERE id = ?', array($id));
		plugin_webseer_enable_remote_hosts($id, true);
	}

	header('Location: webseer_servers.php?header=false');
	exit;

	break;
case 'disable':
	$id = get_request_var('id');

	if ($id > 0) {
		db_execute_prepared('UPDATE plugin_webseer_servers SET enabled = "" WHERE id = ?', array($id));
		plugin_webseer_enable_remote_hosts($id, false);
	}

	header('Location: webseer_servers.php?header=false');
	exit;

	break;
case 'history':
	webseer_show_history();

	break;
case 'edit':
	top_header();
	webseer_edit_server();
	bottom_footer();

	break;
default:
	list_servers();

	break;
}

exit();

function form_actions() {
	global $webseer_actions_server;

	/* if we are to save this form, instead of display it */
	if (isset_request_var('selected_items')) {
		$selected_items = sanitize_unserialize_selected_items(get_nfilter_request_var('selected_items'));
		$action         = get_nfilter_request_var('drp_action');

		if ($selected_items != false) {
			if (cacti_sizeof($selected_items)) {
				foreach($selected_items as $host) {
					$hosts[] = $host;
				}
			}

			if (cacti_sizeof($hosts)) {

				if ($action == WEBSEER_ACTION_SERVER_DELETE) { // delete
					foreach ($hosts as $host) {
						db_execute_prepared('DELETE FROM plugin_webseer_servers WHERE id = ?', array($host));
						db_execute_prepared('DELETE FROM plugin_webseer_servers_log WHERE server= ?', array($host));
						plugin_webseer_delete_remote_server($host);
					}
				} elseif ($action == WEBSEER_ACTION_SERVER_DISABLE) { // disable
					foreach ($hosts as $host) {
						db_execute_prepared('UPDATE plugin_webseer_servers SET enabled = "" WHERE id = ?', array($host));
						plugin_webseer_enable_remote_hosts($host, false);
					}
				} elseif ($action == WEBSEER_ACTION_SERVER_ENABLE) { // enable
					foreach ($hosts as $host) {
						db_execute_prepared('UPDATE plugin_webseer_servers SET enabled = "on" WHERE id = ?', array($host));
						plugin_webseer_enable_remote_hosts($host, true);
					}
				}
			}
		}

		header('Location: webseer_servers.php?header=false');
		exit;
	}

	/* setup some variables */
	$server_list  = '';
	$server_array = array();

	/* loop through each of the graphs selected on the previous page and get more info about them */
	foreach ($_POST as $var => $val) {
		if (preg_match('/^chk_([0-9]+)$/', $var, $matches)) {
			/* ================= input validation ================= */
			input_validate_input_number($matches[1]);
			/* ==================================================== */

			$server_list .= '<li>' . db_fetch_cell_prepared('SELECT name FROM plugin_webseer_servers WHERE id = ?', array($matches[1])) . '</li>';
			$server_array[] = $matches[1];
		}
	}

	top_header();

	form_start('webseer_servers.php');

	html_start_box($webseer_actions_server[get_nfilter_request_var('drp_action')], '60%', '', '3', 'center', '');

	$action = get_nfilter_request_var('drp_action');

	if (cacti_sizeof($server_array)) {
		if ($action == WEBSEER_ACTION_SERVER_DELETE) { // delete
			print "	<tr>
					<td class='topBoxAlt'>
						<p>" . __n('Click \'Continue\' to Delete the following Server.', 'Click \'Continue\' to Delete following Servers.', cacti_sizeof($server_array)) . "</p>
						<div class='itemlist'><ul>$server_list</ul></div>
					</td>
				</tr>";

			$save_html = "<input type='button' value='" . __esc('Cancel') . "' onClick='cactiReturnTo()'>&nbsp;<input type='submit' value='" . __esc('Continue') . "' title='" . __esc_n('Delete Server', 'Delete Servers', cacti_sizeof($server_array)) . "'>";
		} elseif ($action == WEBSEER_ACTION_SERVER_DISABLE) {
			print "	<tr>
					<td class='topBoxAlt'>
						<p>" . __n('Click \'Continue\' to Disable the following Server.', 'Click \'Continue\' to Disable following Servers.', cacti_sizeof($server_array)) . "</p>
						<div class='itemlist'><ul>$server_list</ul></div>
					</td>
				</tr>";

			$save_html = "<input type='button' value='" . __esc('Cancel') . "' onClick='cactiReturnTo()'>&nbsp;<input type='submit' value='" . __esc('Continue') . "' title='" . __esc_n('Disable Server', 'Disable Servers', cacti_sizeof($server_array)) . "'>";
		} elseif ($action == WEBSEER_ACTION_SERVER_ENABLE) {
			print "	<tr>
					<td class='topBoxAlt'>
						<p>" . __n('Click \'Continue\' to Enable the following Server.', 'Click \'Continue\' to Enable following Servers.', cacti_sizeof($server_array)) . "</p>
						<div class='itemlist'><ul>$server_list</ul></div>
					</td>
				</tr>";

			$save_html = "<input type='button' value='" . __esc('Cancel') . "' onClick='cactiReturnTo()'>&nbsp;<input type='submit' value='" . __esc('Continue') . "' title='" . __esc_n('Enable Server', 'Enable Servers', cacti_sizeof($server_array)) . "'>";
		}
	} else {
		raise_message(40);
		header('Location: webseer_servers.php');
		exit;
	}

	print "<tr>
		<td class='saveRow'>
			<input type='hidden' name='action' value='actions'>
			<input type='hidden' name='selected_items' value='" . (isset($server_array) ? serialize($server_array) : '') . "'>
			<input type='hidden' name='drp_action' value='" . get_nfilter_request_var('drp_action') . "'>
			$save_html
		</td>
	</tr>";

	html_end_box();

	form_end();

	bottom_footer();
}

function do_webseer() {
	$hosts = array();
	foreach ($_REQUEST as $var => $val) {
		if (preg_match('/^chk_(.*)$/', $var, $matches)) {
			$host = $matches[1];
			input_validate_input_number($host);
			$hosts[] = $host;
		}
	}

	switch (get_nfilter_request_var('drp_action')) {
		case WEBSEER_ACTION_SERVER_DELETE: // Delete
			foreach ($hosts as $host) {
				db_execute_prepared('DELETE FROM plugin_webseer_servers WHERE id = ?', array($host));
				db_execute_prepared('DELETE FROM plugin_webseer_servers_log WHERE server= ?', array($host));
				plugin_webseer_delete_remote_server($host);
			}

			break;
		case WEBSEER_ACTION_SERVER_DISABLE: // Disabled
			foreach ($hosts as $host) {
				db_execute_prepared("UPDATE plugin_webseer_servers SET enabled = '' WHERE id = ?", array($host));
				plugin_webseer_enable_remote_server($host, false);
			}

			break;
		case WEBSEER_ACTION_SERVER_ENABLE: // Enabled
			foreach ($hosts as $host) {
				db_execute_prepared("UPDATE plugin_webseer_servers SET enabled = 'on' WHERE id = ?", array($host));
				plugin_webseer_enable_remote_server($host, true);
			}

			break;
	}

	header('Location:webseer_servers.php?header=false');
	exit;
}

/**
 *  This is a generic funtion for this page that makes sure that
 *  we have a good request.  We want to protect against people who
 *  like to create issues with Cacti.
*/
function webseer_request_validation() {
	global $title, $rows_selector, $config, $reset_multi;

	/* ================= input validation and session storage ================= */
	$filters = array(
		'rows' => array(
			'filter' => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-1'
			),
		'page' => array(
			'filter' => FILTER_VALIDATE_INT,
			'default' => '1'
			),
		'refresh' => array(
			'filter' => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '20',
			),
		'sort_column' => array(
			'filter' => FILTER_CALLBACK,
			'default' => 'name',
			'options' => array('options' => 'sanitize_search_string')
			),
		'sort_direction' => array(
			'filter' => FILTER_CALLBACK,
			'default' => 'ASC',
			'options' => array('options' => 'sanitize_search_string')
			),
		'state' => array(
			'filter' => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-1'
			)
        );

	validate_store_request_vars($filters, 'sess_webseer');
	/* ================= input validation ================= */
}

function webseer_log_request_validation() {
	global $title, $rows_selector, $config, $reset_multi;

	/* ================= input validation and session storage ================= */
	$filters = array(
		'id' => array(
			'filter' => FILTER_VALIDATE_INT,
			'default' => '-1'
		),
		'rows' => array(
			'filter' => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-1'
		),
		'page' => array(
			'filter' => FILTER_VALIDATE_INT,
			'default' => '1'
		),
		'filter' => array(
			'filter' => FILTER_CALLBACK,
			'default' => '',
			'options' => array('options' => 'sanitize_search_string')
		),
		'sort_column' => array(
			'filter' => FILTER_CALLBACK,
			'default' => 'lastcheck',
			'options' => array('options' => 'sanitize_search_string')
		),
		'sort_direction' => array(
			'filter' => FILTER_CALLBACK,
			'default' => 'DESC',
			'options' => array('options' => 'sanitize_search_string')
		),
	);

	validate_store_request_vars($filters, 'sess_weseer_server_log');
	/* ================= input validation ================= */
}

function webseer_show_history() {
	global $config, $httperrors;

	webseer_log_request_validation();

	if (isset_request_var('id')) {
		$id = get_filter_request_var('id');
	} else {
		header('Location: webseer_servers.php?header=false');
		exit;
	}

	$refresh['seconds'] = 9999999;
	$refresh['page']    = 'webseer_servers.php?action=history&id=' . get_filter_request_var('id') . '&header=false';
	$refresh['logout']  = 'false';

	set_page_refresh($refresh);

	top_header();

	if (get_request_var('rows') == '-1') {
		$rows = read_config_option('num_rows_table');
	} else {
		$rows = get_request_var('rows');
	}

	$sql_where    = '';
	$sql_params[] = get_filter_request_var('id');

	if (get_request_var('filter') != '') {
		$sql_where .= 'AND wl.lastcheck LIKE ?';
		$sql_params[] = '%' . get_request_var('filter') . '%';
	}

	$sql_order = get_order_string();
	$sql_limit = ' LIMIT ' . ($rows*(get_request_var('page')-1)) . ',' . $rows;

	$result = db_fetch_assoc_prepared("SELECT wl.*, wu.url
		FROM plugin_webseer_servers_log AS wl
		INNER JOIN plugin_webseer_urls wu
		ON wl.url_id = wu.id
		WHERE wu.id = ?
		$sql_where
		$sql_order
		$sql_limit",
		$sql_params);

	$total_rows = db_fetch_cell_prepared("SELECT COUNT(*)
		FROM plugin_webseer_servers_log AS wl
		WHERE wl.url_id = ?
		$sql_where",
		$sql_params);

	$nav = html_nav_bar('webseer_servers.php?action=history', MAX_DISPLAY_PAGES, get_request_var('page'), $rows, $total_rows, 8, __('Servers', 'webseer'), 'page', 'main');

	webseer_show_tab('webseer_servers.php');

	webseer_log_filter();

	print $nav;

	html_start_box('', '100%', '', '4', 'center', '');

	$display_text = array(
		'lastcheck' => array(
			'display' => __('Date', 'webseer')
		),
		'url' => array(
			'display' => __('URL', 'webseer'),
		),
		'result' => array(
			'display' => __('Error', 'webseer'),
		),
		'http_code' => array(
			'display' => __('HTTP Code', 'webseer'),
			'align'   => 'right',
			'sort'    => 'DESC'
		),
		'namelookup_time' => array(
			'display' => __('DNS', 'webseer'),
			'align'   => 'right',
			'sort'    => 'DESC'
		),
		'connect_time' => array(
			'display' => __('Connect', 'webseer'),
			'align'   => 'right',
			'sort'    => 'DESC'
		),
		'redirect_time' => array(
			'display' => __('Redirect', 'webseer'),
			'align'   => 'right',
			'sort'    => 'DESC'
		),
		'total_time' => array(
			'display' => __('Total', 'webseer'),
			'align'   => 'right',
			'sort'    => 'DESC'
		),
	);

	html_header_sort($display_text, get_request_var('sort_column'), get_request_var('sort_direction'), 1, 'webseer_servers.php?action=history&id=' . get_request_var('id'), 'main');

	if (cacti_count($result)) {
		foreach ($result as $row) {
            if ($row['result'] == 0) {
                $style = "color:rgba(10,10,10,0.8);background-color:rgba(242, 25, 36, 0.6);";
            } else {
                $style = "color:rgba(10,10,10,0.8);background-color:rgba(204, 255, 204, 0.6)";
            }

            print "<tr class='tableRow selectable' style='$style' id='line" . $row['id'] . "'>";

			form_selectable_cell($row['lastcheck'], $row['id']);
			form_selectable_cell("<a class='linkEditMain' href='" . $row['url'] . "' target=_new><b>" . $row['url'] . '</b></a>', $row['id']);
			form_selectable_cell(($row['result'] == 1 ? __('Up', 'webseer') : __('Down', 'webseer')), $row['id']);
			form_selectable_cell($httperrors[$row['http_code']], $row['id'], '', 'right');
			form_selectable_cell(round($row['namelookup_time'], 4), $row['id'], '', ($row['namelookup_time'] > 4 ? 'background-color: red;text-align:right;' : ($row['namelookup_time'] > 1 ? 'background-color: yellow;text-align:right;':'text-align:right')));
			form_selectable_cell(round($row['connect_time'], 4), $row['id'], '', ($row['connect_time'] > 4 ? 'background-color: red;text-align:right;' : ($row['connect_time'] > 1 ? 'background-color: yellow;text-align:right;':'text-align:right')));
			form_selectable_cell(round($row['redirect_time'], 4), $row['id'], '', ($row['redirect_time'] > 4 ? 'background-color: red;text-align:right;' : ($row['redirect_time'] > 1 ? 'background-color: yellow;text-align:right;':'text-align:right')));
			form_selectable_cell(round($row['total_time'], 4), $row['id'], '', ($row['total_time'] > 4 ? 'background-color: red;text-align:right' : ($row['total_time'] > 1 ? 'background-color: yellow;text-align:right;':'text-align:right')));
			form_end_row();
		}
	} else {
		form_alternate_row();
		print '<td colspan=12><i>' . __('No Events in History', 'webseer') . '</i></td></tr>';
	}

	html_end_box(false);

	if (cacti_sizeof($result)) {
		print $nav;
	}
}

function list_servers() {
	global $webseer_actions_server, $item_rows, $config, $hostid;

	webseer_request_validation();

	$statefilter='';
	if (isset_request_var('state')) {
		if (get_request_var('state') == '-1') {
			$statefilter = '';
		} else {
			if (get_request_var('state') == '2') { $statefilter = '(enabled="" OR enabled="0")'; }
			if (get_request_var('state') == '1') { $statefilter = '(enabled="on" OR enabled="1")'; }
			if (get_request_var('state') == '3') { $statefilter = 'result!=1'; }
		}
	}

	$refresh['seconds'] = get_request_var('refresh');
	$refresh['page']    = 'webseer_servers.php?header=false';
	$refresh['logout']  = 'false';

	set_page_refresh($refresh);

	top_header();

	webseer_show_tab('webseer_servers.php');

	webseer_filter();

	if (get_request_var('rows') == '-1') {
		$rows = read_config_option('num_rows_table');
	} else {
		$rows = get_request_var('rows');
	}

	$sql_where = '';

	if ($statefilter != '') {
		$sql_where .= ($sql_where == '' ? 'WHERE ' : ' AND ') . $statefilter;
	}

	$sql_order = get_order_string();
	$sql_limit = ' LIMIT ' . ($rows*(get_request_var('page')-1)) . ',' . $rows;

	$result = db_fetch_assoc("SELECT *
		FROM plugin_webseer_servers
		$sql_where
		$sql_order
		$sql_limit");

	$total_rows = db_fetch_cell("SELECT count(*) FROM plugin_webseer_servers $sql_where");

	$nav = html_nav_bar('webseer_servers.php', MAX_DISPLAY_PAGES, get_request_var('page'), $rows, $total_rows, 8, __('Servers', 'webseer'), 'page', 'main');

	form_start('webseer_servers.php', 'chk');

	print $nav;

	html_start_box('', '100%', '', '4', 'center', '');

	$display_text = array(
		'nosort'    => array(__('Actions', 'webseer'), 'ASC'),
		'name'      => array(__('Name', 'webseer'), 'ASC'),
		'ip'        => array(__('IP Address', 'webseer'), 'ASC'),
		'enabled'   => array(__('Enabled', 'webseer'), 'ASC'),
		'location'  => array(__('Location', 'webseer'), 'ASC'),
		'lastcheck' => array(__('Last Check', 'webseer'), 'ASC'),
		'master'    => array(__('Master', 'webseer'), 'ASC'),
		'isme'      => array(__('This Host', 'webseer'), 'ASC')
	);

	html_header_sort_checkbox($display_text, get_request_var('sort_column'), get_request_var('sort_direction'), false);

	if (cacti_sizeof($result)) {
		foreach ($result as $row) {
			if ($row['enabled'] == '') {
				$style = "color:rgba(10,10,10,0.8);background-color:rgba(205, 207, 196, 0.6)";
            } elseif ($row['isme'] == 0 && $row['lastcheck'] < time() - 10) {
                $style = "color:rgba(10,10,10,0.8);background-color:rgba(242, 25, 36, 0.6);";
            } else {
                $style = "color:rgba(10,10,10,0.8);background-color:rgba(204, 255, 204, 0.6)";
            }

            print "<tr class='tableRow selectable' style='$style' id='line" . $row['id'] . "'>";

			print "<td width='1%' class='nowrap left'>
				<a class='pic' href='" . html_escape($config['url_path'] . 'plugins/webseer/webseer_servers.php?action=edit&id=' . $row['id']) . "' title='" . __esc('Edit Server', 'webseer') . "'>
					<i class='tholdGlyphEdit fas fa-wrench'></i>
				</a>";

			if ($row['enabled'] == '') {
				print "<a class='pic' href='" . html_escape($config['url_path'] . 'plugins/webseer/webseer_servers.php?action=enable&id=' . $row['id']) . "' title='" . __esc('Enable Server', 'webseer') . "'>
					<i class='tholdGlyphEnable fas fa-play-circle'></i>
				</a>";
			} else {
				print "<a class='pic' href='" . html_escape($config['url_path'] . 'plugins/webseer/webseer_servers.php?action=disable&id=' . $row['id']) . "' title='" . __esc('Disable Server', 'webseer') . "'>
					<i class='tholdGlyphDisable fas fa-stop-circle'></i>
				</a>";
			}

			print "<a class='pic' href='" . html_escape($config['url_path'] . 'plugins/webseer/webseer_servers.php?action=history&id=' . $row['id']) . "' title='" . __esc('View Server History', 'webseer') . "'>
					<i class='tholdGlyphLog fas fa-exclamation-triangle'></i>
				</a>
			</td>";

			form_selectable_cell($row['name'], $row['id'], '', '', html_escape($row['url']));
			form_selectable_cell($row['ip'], $row['id']);
			form_selectable_cell($row['enabled'] == '' || $row['enabled'] == '0' ? __('No', 'webseer'): __('Yes', 'webseer'), $row['id']);
			form_selectable_cell($row['location'], $row['id']);
			form_selectable_cell($row['lastcheck'], $row['id']);
			form_selectable_cell($row['master'] == 1 ? __('Yes', 'webseer'):__('No', 'webseer'), $row['id']);
			form_selectable_cell($row['isme'] == 1 ? __('Yes', 'webseer'):__('No', 'webseer'), $row['id']);
			form_checkbox_cell($row['name'], $row['id']);
			form_end_row();
		}
	} else {
		form_alternate_row();
		print '<td colspan="10"><i>' . __('No Servers Found', 'webseer') . '</i></td></tr>';
	}

	html_end_box(false);

	if (cacti_sizeof($result) > 0) {
		print $nav;
	}

	draw_actions_dropdown($webseer_actions_server);

	form_end();

	?>
	<script type='text/javascript'>
	$(function() {
		$('#webseer2_child').find('.cactiTooltipHint').each(function() {
			title = $(this).attr('title');

			if (title != undefined && title.indexOf('/') >= 0) {
				$(this).click(function() {
					window.open(title, 'webseer');
				});
			}
		});
	});

	</script>
	<?php

	bottom_footer();
}

function form_save() {
	/* ================= input validation ================= */
	get_filter_request_var('id');
	/* ==================================================== */

	if (isset_request_var('id')) {
		$save['id'] = get_request_var('id');
	} else {
		$save['id'] = 0;
	}

	if (isset_request_var('enabled')) {
		$save['enabled'] = 'on';
	} else {
		$save['enabled'] = '';
	}

	if (isset_request_var('master')) {
		$save['master'] = '1';
	} else {
		$save['master'] = '0';
	}

	if (isset_request_var('isme')) {
		$save['isme'] = '1';
	} else {
		$save['isme'] = '0';
	}

	$save['name']     = get_nfilter_request_var('name');
	$save['ip']       = get_nfilter_request_var('ip');
	$save['url']      = get_nfilter_request_var('url');
	$save['location'] = get_nfilter_request_var('location');

	$id = sql_save($save, 'plugin_webseer_servers', 'id');

	if (!is_error_message()) {
		if ($save['id'] == 0) {
			plugin_webseer_add_remote_server($id, $save);
		} else {
			plugin_webseer_update_remote_server($save);
		}

		if ($save['isme'] == 1) {
			plugin_webseer_refresh_urls();
		}

		if ($id) {
			raise_message(1);
		} else {
			raise_message(2);
		}
	}
	header('Location: webseer_servers.php?action=edit&id=' . $save['id'] . '&header=false');
	exit;
}

function webseer_edit_server() {
	global $webseer_server_fields;

	/* ================= input validation ================= */
	get_filter_request_var('id');
	/* ==================================================== */

	$server = array();
	if (!isempty_request_var('id')) {
		$server = db_fetch_row_prepared('SELECT * FROM plugin_webseer_servers WHERE id = ?', array(get_request_var('id')), false);
		$header_label = __('Query [edit: %s]', $server['ip'], 'webseer');
	} else {
		$header_label = __('Query [new]', 'webseer');
                $server['isme'] = '';
                $server['master'] = '';
	}

	$server['isme']   = $server['isme'] ? 'on' : '';
	$server['master'] = $server['master'] ? 'on' : '';

	form_start('webseer_servers.php');
	html_start_box($header_label, '100%', '', '3', 'center', '');
	draw_edit_form(array(
		'config' => array('form_name' => 'chk'),
		'fields' => inject_form_variables($webseer_server_fields, $server)
		)
	);

	html_end_box();

	form_save_button('webseer_servers.php', 'return');
}

function webseer_filter() {
	global $item_rows;

	?>
	<script type='text/javascript'>

	function applyFilter() {
		strURL  = 'webseer_servers.php?header=false&state=' + $('#state').val();
		strURL += '&refresh=' + $('#refresh').val();
		strURL += '&rows=' + $('#rows').val();
		loadPageNoHeader(strURL);
	}

	function clearFilter() {
		strURL = 'webseer_servers.php?clear=1&header=false';
		loadPageNoHeader(strURL);
	}

	$(function() {
		$('#refresh, #state, #rows').change(function() {
			applyFilter();
		});

		$('#clear').click(function() {
			clearFilter();
		});

		$('#webseer').submit(function(event) {
			event.preventDefault();
			applyFilter();
		});
	});

	</script>
	<?php

	html_start_box(__('Webseer Servers', 'webseer') , '100%', '', '3', 'center', 'webseer_servers.php?action=edit');
	?>
	<tr class='even noprint'>
		<td class='noprint'>
			<form id='webseer' action='webseer_servers.php' method='post'>
			<table class='filterTable'>
				<tr class='noprint'>
					<td>
						<?php print __('State', 'webseer');?>
					</td>
					<td>
						<select id='state'>
							<option value='-1'><?php print __('Any', 'webseer');?></option>
							<?php
							foreach (array('2' => __('Disabled', 'webseer'), '1' => __('Enabled', 'webseer'), 3 => __('Triggered', 'webseer')) as $key => $value) {
								print "<option value='" . $key . "'" . ($key == get_request_var('state') ? ' selected' : '') . '>' . $value . '</option>';
							}
							?>
						</select>
					</td>
					<td>
						<?php print __('Refresh', 'webseer');?>
					</td>
					<td>
						<select id='refresh'>
							<?php
							foreach (array(20 => __('%d Seconds', 20, 'webseer'), 30 => __('%d Seconds', 30, 'webseer'), 45 => __('%d Seconds', 45, 'webseer'), 60 => __('%d Minute', 1, 'webseer'), 120 => __('%d Minutes', 2, 'webseer'), 300 => __('%d Minutes', 5, 'webseer')) as $r => $row) {
								print "<option value='" . $r . "'" . (isset_request_var('refresh') && $r == get_request_var('refresh') ? ' selected' : '') . '>' . $row . '</option>';
							}
							?>
						</select>
					</td>
					<td>
						<?php print __('Servers', 'webseer');?>
					</td>
					<td>
						<select id='rows'>
							<?php
							print "<option value='-1'" . (get_request_var('rows') == '-1' ? ' selected':'') . ">" . __('Default', 'webseer') . "</option>";
							if (cacti_sizeof($item_rows)) {
								foreach ($item_rows as $key => $value) {
									print "<option value='" . $key . "'"; if (get_request_var('rows') == $key) { print ' selected'; } print '>' . htmlspecialchars($value) . "</option>";
								}
							}
							?>
						</select>
					</td>
					<td>
						<span class='nowrap'>
							<input type='button' id='clear' alt='' value='<?php print __esc('Clear', 'webseer');?>'>
						</span>
					</td>
				</tr>
			</table>
			<input type='hidden' name='search' value='search'>
			</form>
		</td>
	</tr>
	<?php
	html_end_box();
}

function webseer_log_filter() {
	global $item_rows;

	?>
	<script type='text/javascript'>

	refreshMSeconds=99999999;

	function applyFilter() {
		strURL  = 'webseer_servers.php?action=history&header=false&id=<?php print get_request_var('id');?>';
		strURL += '&filter=' + $('#filter').val();
		strURL += '&rows=' + $('#rows').val();
		refreshMSeconds=99999999;
		loadPageNoHeader(strURL);
	}

	function clearFilter() {
		strURL = 'webseer_servers.php?action=history&clear=1&header=false';
		loadPageNoHeader(strURL);
	}

	$(function() {
		$('#rows').change(function() {
			applyFilter();
		});

		$('#clear').click(function() {
			clearFilter();
		});

		$('#webseer').submit(function(event) {
			event.preventDefault();
			applyFilter();
		});
	});

	</script>
	<?php

	html_start_box(__('Webseer Server History', 'webseer') , '100%', '', '3', 'center', '');
	?>
	<tr class='even noprint'>
		<td class='noprint'>
			<form id='webseer' action='webseer_servers.php?action=history'>
			<table class='filterTable'>
				<tr class='noprint'>
					<td>
						<?php print __('Date Search', 'webseer');?>
					</td>
					<td>
						<input type='text' class='ui-state-default ui-corner-all' id='filter' size='30' value='<?php print html_escape_request_var('filter');?>'>
					</td>
					<td>
						<?php print __('Entries', 'webseer');?>
					</td>
					<td>
						<select id='rows'>
							<?php
							print "<option value='-1'" . (get_request_var('rows') == '-1' ? ' selected':'') . ">" . __('Default', 'webseer') . "</option>";
							if (cacti_sizeof($item_rows)) {
								foreach ($item_rows as $key => $value) {
									print "<option value='" . $key . "'"; if (get_request_var('rows') == $key) { print ' selected'; } print '>' . htmlspecialchars($value) . "</option>";
								}
							}
							?>
						</select>
					</td>
					<td>
						<span class='nowrap'>
							<input type='submit' id='go' alt='' value='<?php print __esc('Go', 'webseer');?>'>
							<input type='button' id='clear' alt='' value='<?php print __esc('Clear', 'webseer');?>'>
						</span>
					</td>
				</tr>
			</table>
			</form>
		</td>
	</tr>
	<?php
	html_end_box();
}


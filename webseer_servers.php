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

/* global colors */
$webseer_bgcolors = array(
	'red'    => 'FF6044',
	'yellow' => 'FAFD9E',
	'orange' => 'FF7D00',
	'green'  => 'CCFFCC',
	'grey'   => 'CDCFC4'
);

set_default_action();

switch (get_request_var('action')) {
case 'save':
	form_save();

	break;
case 'actions':
	form_actions();

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

			if (cacti_sizeof($urls)) {
				if ($action == WEBSEER_ACTION_SERVER_DELETE) { // delete
					foreach ($hosts as $host) {
						db_execute_prepared('DELETE FROM plugin_webseer_servers WHERE id = ?', array($host));
						db_execute_prepared('DELETE FROM plugin_webseer_servers_log WHERE server= ?', array($host));
						plugin_webseer_delete_remote_server($host);
					}
				} elseif ($action == WEBSEER_ACTION_SERVER_DISABLE) { // disable
					foreach ($urls as $id) {
						db_execute_prepared('UPDATE plugin_webseer_urls SET enabled = "" WHERE id = ?', array($id));
						plugin_webseer_enable_remote_hosts($id, false);
					}
				} elseif ($action == WEBSEER_ACTION_SERVER_ENABLE) { // enable
					foreach ($urls as $id) {
						db_execute_prepared('UPDATE plugin_webseer_urls SET enabled = "on" WHERE id = ?', array($id));
						plugin_webseer_enable_remote_hosts($id, true);
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

	form_start('webseer.php');

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
	global $title, $colors, $rows_selector, $config, $reset_multi;

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

function webseer_show_history() {
	global $config, $webseer_bgcolors, $httperrors;

	if (isset_request_var('id')) {
		$id = get_filter_request_var('id');
	} else {
		header('Location: webseer.php?header=false');
		exit;
	}

	$result = db_fetch_assoc_prepared('SELECT plugin_webseer_urls_log.*, plugin_webseer_urls.url
		FROM plugin_webseer_urls_log,plugin_webseer_urls
		WHERE plugin_webseer_urls.id = ?
		AND plugin_webseer_urls_log.url_id = plugin_webseer_urls.id
		ORDER BY plugin_webseer_urls_log.lastcheck DESC',
		array($id));

	top_header();

	webseer_show_tab('webseer_servers.php');

	html_start_box('', '100%', '', '4', 'center', '');

	$display_text = array(
		__('Date', 'webseer'),
		__('URL', 'webseer'),
		__('Error', 'webseer'),
		__('HTTP Code', 'webseer'),
		__('DNS', 'webseer'),
		__('Connect', 'webseer'),
		__('Redirect', 'webseer'),
		__('Total', 'webseer')
	);

	html_header($display_text);

	$c = 0;
	$i = 0;

	if (cacti_count($result)) {
		foreach ($result as $row) {
			$c++;

			if ($row['result'] == 0) {
				$alertstat='yes';
				$bgcolor='red';
			} else {
				$alertstat='no';
				$bgcolor='green';
			}

			form_alternate_row('line' . $row['id'], true);
			form_selectable_cell($row['lastcheck'], $row['id']);
			form_selectable_cell("<a class='linkEditMain' href='" . $row['url'] . "' target=_new><b>" . $row['url'] . '</b></a>', $row['id']);
			form_selectable_cell(($row['result'] == 1 ? __('Up', 'webseer') : __('Down', 'webseer')), $row['id']);
			form_selectable_cell($httperrors[$row['http_code']], $row['id'], '', '', $row['error']);
			form_selectable_cell(round($row['namelookup_time'], 4), $row['id'], '', ($row['namelookup_time'] > 4 ? 'background-color: red' : ($row['namelookup_time'] > 1 ? 'background-color: yellow':'')));
			form_selectable_cell(round($row['connect_time'], 4), $row['id'], '', ($row['connect_time'] > 4 ? 'background-color: red' : ($row['connect_time'] > 1 ? 'background-color: yellow':'')));
			form_selectable_cell(round($row['redirect_time'], 4), $row['id'], '', ($row['redirect_time'] > 4 ? 'background-color: red' : ($row['redirect_time'] > 1 ? 'background-color: yellow':'')));
			form_selectable_cell(round($row['total_time'], 4), $row['id'], '', ($row['total_time'] > 4 ? 'background-color: red' : ($row['total_time'] > 1 ? 'background-color: yellow':'')));
			form_end_row();
		}
	} else {
		form_alternate_row();
		print '<td colspan=12><i>' . __('No Events in History', 'webseer') . '</i></td></tr>';
	}

	html_end_box(false);
}

function list_servers() {
	global $colors, $webseer_actions_server, $webseer_bgcolors, $item_rows, $config, $hostid;

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

	top_header();

	webseer_show_tab('webseer_servers.php');

	webseer_filter();

	if (get_request_var('rows') == '-1') {
		$rows = read_config_option('num_rows_table');
	}else{
		$rows = get_request_var('rows');
	}

	$sql_where = '';

	if($statefilter != '') {
		$sql_where .= ($sql_where == '' ? 'WHERE ' : ' AND ') . $statefilter;
	}

	$sql_order = get_order_string();
	$sql_limit = ' LIMIT ' . ($rows*(get_request_var('page')-1)) . ',' . $rows;

	$result = db_fetch_assoc("SELECT *
		FROM plugin_webseer_servers
		$sql_where
		$sql_order
		$sql_limit");

	$total_rows = cacti_count(db_fetch_assoc("SELECT id FROM plugin_webseer_servers $sql_where"));

	$nav = html_nav_bar('webseer_servers.php', MAX_DISPLAY_PAGES, get_request_var('page'), $rows, $total_rows, 8, __('Servers', 'webseer'), 'page', 'main');

	form_start('webseer_servers.php', 'chk');

	print $nav;

	$header_color = '';
	if (isset($colors['header'])) {
		$header_color = $colors['header'];
	}
	html_start_box('', '100%', $header_color, '4', 'center', '');

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
			if ($row['isme'] == 0 && $row['lastcheck'] < time() - 180) {
				$alertstat = 'yes';
				$bgcolor   = 'red';
			} else {
				$alertstat = 'no';
				$bgcolor   = 'green';
			};

			form_alternate_row('line' . $row['id'], true);

			print "<td width='1%' class='nowrap left'>
				<a class='pic' href='" . html_escape($config['url_path'] . 'plugins/webseer/webseer_servers.php?action=edit&id=' . $row['id']) . "' title='" . __esc('Edit Server', 'webseer') . "'>
					<i class='tholdGlyphEdit fas fa-wrench'></i>
				</a>";

			if ($row['enabled'] == '') {
				print "<a class='pic' href='" . html_escape($config['url_path'] . 'plugins/webseer/webseer_servers.php?drp_action=' . WEBSEER_ACTION_SERVER_ENABLE .'&chk_' . $row['id'] . '=1') . "' title='" . __esc('Enable Server', 'webseer') . "'>
					<i class='tholdGlyphEnable fas fa-play-circle'></i>
				</a>";
			} else {
				print "<a class='pic' href='" . html_escape($config['url_path'] . 'plugins/webseer/webseer_servers.php?drp_action=' . WEBSEER_ACTION_SERVER_DISABLE . '&chk_' . $row['id'] . '=1') . "' title='" . __esc('Disable Server', 'webseer') . "'>
					<i class='tholdGlyphDisable fas fa-stop-circle'></i>
				</a>";
			}

			print "<a class='pic' href='" . html_escape($config['url_path'] . 'plugins/webseer/webseer_servers.php?view_history=1&id=' . $row['id']) . "' title='" . __esc('View History', 'webseer') . "'>
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
		$server = db_fetch_row_prepared('SELECT * FROM plugin_webseer_servers WHERE id = ?', array(get_request_var('id')), FALSE);
		$header_label = __('Query [edit: %s]', $server['ip'], 'webseer');
	}else{
		$header_label = __('Query [new]', 'webseer');
	}

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

	$refresh['seconds'] = get_request_var('refresh');
	$refresh['page']    = 'webseer_servers.php?header=false';
	$refresh['logout']  = 'false';

	set_page_refresh($refresh);

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

	html_start_box(__('Webseer Server Management', 'webseer') , '100%', '', '3', 'center', 'webseer_servers.php?action=edit');
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
								echo "<option value='" . $key . "'" . ($key == get_request_var('state') ? ' selected' : '') . '>' . $value . '</option>';
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
								echo "<option value='" . $r . "'" . (isset_request_var('refresh') && $r == get_request_var('refresh') ? ' selected' : '') . '>' . $row . '</option>';
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
							print "<option value='-1'" . (get_request_var('rows') == $key ? ' selected':'') . ">" . __('Default', 'webseer') . "</option>";
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


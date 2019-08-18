<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2019 The Cacti Group                                 |
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

if (isset_request_var('drp_action')) {
	do_webseer();
} else {
	if (isset_request_var('view_history')) {
		webseer_show_history();
	} else {
		list_urls();
	}
}

function do_webseer() {
	$hosts = array();
	foreach ($_REQUEST as $var => $val) {
		if (preg_match('/^chk_(.*)$/', $var, $matches)) {
			$del = $matches[1];
			input_validate_input_number($del);
			$hosts[$del] = $del;
		}
	}

	switch (get_nfilter_request_var('drp_action')) {
		case WEBSEER_ACTION_SERVER_DELETE: // Delete
			foreach ($hosts as $del => $rra) {
				db_execute_prepared('DELETE FROM plugin_webseer_servers WHERE id = ?', array($del));
				db_execute_prepared('DELETE FROM plugin_webseer_servers_log WHERE server= ?', array($del));
				plugin_webseer_delete_remote_server($del);
			}
			break;
		case WEBSEER_ACTION_SERVER_DISABLE: // Disabled
			foreach ($hosts as $del => $rra) {
				db_execute_prepared("UPDATE plugin_webseer_servers SET enabled = '' WHERE id = ?", array($del));
				plugin_webseer_enable_remote_server($del, false);
			}
			break;
		case WEBSEER_ACTION_SERVER_ENABLE: // Enabled
			foreach ($hosts as $del => $rra) {
				db_execute_prepared("UPDATE plugin_webseer_servers SET enabled = 'on' WHERE id = ?", array($del));
				plugin_webseer_enable_remote_server($del, true);
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

	$c=0;
	$i=0;
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

function list_urls () {
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

	html_header_sort_checkbox($display_text, get_request_var('sort_column'), get_request_var('sort_direction'));

	if (cacti_sizeof($result)) {
		foreach ($result as $row) {
			if ($row['isme'] == 0 && $row['lastcheck'] < time() - 180) {
				$alertstat='yes';
				$bgcolor='red';
			} else {
				$alertstat='no';
				$bgcolor='green';
			};

			form_alternate_row('line' . $row['id'], true);

			print "<td width='1%' style='padding:0px;white-space:nowrap'>
				<a class='pic' href='" . htmlspecialchars($config['url_path'] . 'plugins/webseer/webseer_servers_edit.php?action=edit&id=' . $row['id']) . "'>
					<img src='" . $config['url_path'] . "plugins/webseer/images/edit_object.png' alt='' title='" . __esc('Edit Site', 'webseer') . "'>
				</a>";

			if ($row['enabled'] == '' || $row['enabled'] == '0') {
				print "<a class='pic' href='" . htmlspecialchars($config['url_path'] . 'plugins/webseer/webseer_servers.php?drp_action=' . WEBSEER_ACTION_SERVER_ENABLE . '&chk_' . $row['id']) . "=1'>
					<img src='" . $config['url_path'] . "plugins/webseer/images/enable_object.png' alt='' title='" . __esc('Enable Site', 'webseer') . "'>
				</a>";
			} else {
				print "<a class='pic' href='" . htmlspecialchars($config['url_path'] . 'plugins/webseer/webseer_servers.php?drp_action=' . WEBSEER_ACTION_SERVER_DISABLE . '&chk_' . $row['id']) . "=1'>
					<img src='" . $config['url_path'] . "plugins/webseer/images/disable_object.png' alt='' title='" . __esc('Disable Site', 'webseer') . "'>
				</a>";
			}

			print "<a class='pic' href='" . htmlspecialchars($config['url_path'] . 'plugins/webseer/webseer_servers.php?view_history=1&id=' . $row['id']) . "'><img src='" . $config['url_path'] . "plugins/webseer/images/view_history.gif' alt='' title='" . __esc('View History', 'webseer') . "'></td>";

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

	html_start_box(__('Webseer Server Management', 'webseer') , '100%', '', '3', 'center', 'webseer_servers_edit.php?action=edit');
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
						<?php print __('Sites', 'webseer');?>
					</td>
					<td>
						<select id='rows'>
							<?php
							print "<option value='-1'" . (get_request_var('rows') == $key ? ' selected':'') . ">" . __('Default', 'webseer') . "</option>\n";
							if (cacti_sizeof($item_rows)) {
								foreach ($item_rows as $key => $value) {
									print "<option value='" . $key . "'"; if (get_request_var('rows') == $key) { print ' selected'; } print '>' . htmlspecialchars($value) . "</option>\n";
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


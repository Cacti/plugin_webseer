<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2010-2017 The Cacti Group                                 |
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
 | Cacti: The Complete RRDTool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

chdir('../../');
include_once('./include/auth.php');
include_once($config['base_path'] . '/plugins/webseer/functions.php');

global $refresh;

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
	while (list($var,$val) = each($_REQUEST)) {
		if (preg_match('/^chk_(.*)$/', $var, $matches)) {
			$del = $matches[1];
			input_validate_input_number($del);
			$hosts[$del] = $del;
		}
	}

	switch (get_nfilter_request_var('drp_action')) {
		case 1:	// Delete
			foreach ($hosts as $del => $rra) {
				db_execute_prepared('DELETE FROM plugin_webseer_urls WHERE id = ?', array($del));
				db_execute_prepared('DELETE FROM plugin_webseer_url_log WHERE url_id = ?', array($del));
				plugin_webseer_delete_remote_hosts($del);
			}
			break;
		case 2:	// Disabled
			foreach ($hosts as $del => $rra) {
				db_execute_prepared('UPDATE plugin_webseer_urls SET enabled = "off" WHERE id = ?', array($del));
				plugin_webseer_enable_remote_hosts($del, false);
			}
			break;
		case 3:	// Enabled
			foreach ($hosts as $del => $rra) {
				db_execute_prepared('UPDATE plugin_webseer_urls SET enabled = "on" WHERE id = ?', array($del));
				plugin_webseer_enable_remote_hosts($del, true);
			}
			break;
	}

	Header('Location:webseer.php?header=false');

	exit;
}

/** 
 *  This is a generic funtion for this page that makes sure that
 *  we have a good request.  We want to protect against people who
 *  like to create issues with Cacti.
*/
function webseer_request_validation() {
	/* ================= input validation and session storage ================= */
	$filters = array(
		'rows' => array(
			'filter' => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => read_config_option('num_rows_table')
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
			'default' => 'result',
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

	validate_store_request_vars($filters, 'sess_webseerurl');
	/* ================= input validation ================= */
}

function webseer_show_history () {
	global $config, $webseer_bgcolors;

	if (isset_request_var('id')) {
		$id = get_filter_request_var('id');
	} else {
		Location('webseer.php?header=false');
		exit;
	}

	$sql = "SELECT plugin_webseer_url_log.*, plugin_webseer_urls.url 
		FROM plugin_webseer_url_log,plugin_webseer_urls 
		WHERE plugin_webseer_urls.id = $id 
		AND plugin_webseer_url_log.url_id = plugin_webseer_urls.id 
		ORDER BY plugin_webseer_url_log.lastcheck DESC";

	$result = db_fetch_assoc($sql);

	top_header();

	webseer_show_tab('webseer.php');

	html_start_box('', '100%', '', '4', 'center', '');

	$display_text = array(__('Date'), __('URL'), __('Error'), __('HTTP Code'), __('DNS'), __('Connect'), __('Redirect'), __('Total'), __('Result'));

	html_header($display_text);

	$c=0;
	$i=0;
	if (count($result)) {
		foreach ($result as $row) {
			$c++;

			if ($row['result'] == 0) {
				$alertstat='yes';
				$bgcolor='red';
			} else {
				$alertstat='no';
				$bgcolor='green';
			};
			form_alternate_row_color($webseer_bgcolors[$bgcolor], $webseer_bgcolors[$bgcolor], $i, 'line' . $row['id']); $i++;
			form_selectable_cell(date('F j, Y - h:i:s', $row['lastcheck']), $row['id']);
			form_selectable_cell("<a class='linkEditMain' href='" . $row['url'] . "' target=_new>" . $row['url'] . '</a>', $row['id']);
			form_selectable_cell($row['error'], $row['id']);
			form_selectable_cell($row['http_code'], $row['id']);
			form_selectable_cell(round($row['namelookup_time'], 4), $row['id'], '', ($row['namelookup_time'] > 4 ? 'background-color: red' : ($row['namelookup_time'] > 1 ? 'background-color: yellow':'')));
			form_selectable_cell(round($row['connect_time'], 4), $row['id'], '', ($row['connect_time'] > 4 ? 'background-color: red' : ($row['connect_time'] > 1 ? 'background-color: yellow':'')));
			form_selectable_cell(round($row['redirect_time'], 4), $row['id'], '', ($row['redirect_time'] > 4 ? 'background-color: red' : ($row['redirect_time'] > 1 ? 'background-color: yellow':'')));
			form_selectable_cell(round($row['total_time'], 4), $row['id'], '', ($row['total_time'] > 4 ? 'background-color: red' : ($row['total_time'] > 1 ? 'background-color: yellow':'')));
			form_selectable_cell(($row['result'] == 1 ? __('Up') : __('Down')), $row['id']);
			form_end_row();
		}
	} else {
		form_alternate_row();
		print '<td colspan=12><i>' . __('No Events in History') . '</i></td></tr>';
	}
	html_header(array('','','','','','','','',''));

	html_end_box(false);
}

function list_urls () {
	global $webseer_bgcolors, $config, $hostid, $refresh, $item_rows;

	$ds_actions = array(
		1 => __('Delete'), 
		2 => __('Disable'), 
		3 => __('Enable')
	);

	webseer_request_validation();

	$statefilter='';
	if (isset_request_var('state')) {
		if (get_request_var('state') == '-1') {
			$statefilter = '';
		} else {
			if (get_request_var('state') == '2') { $statefilter = "plugin_webseer_urls.enabled='off'"; }
			if (get_request_var('state') == '1') { $statefilter = "plugin_webseer_urls.enabled='on'"; }
			if (get_request_var('state') == '3') { $statefilter = 'plugin_webseer_urls.result!=1'; }
		}
	}

	if (get_request_var('rows') == '-1') {
		$rows = read_config_option('num_rows_table');
	}else{
		$rows = get_request_var('rows');
	}

	if (isset_request_var('refresh') && get_request_var('refresh') != '' && get_request_var('refresh') != 0) {
		$refresh['seconds'] = get_request_var('refresh');
		$refresh['page'] = 'webseer.php?header=false';
	}

	top_header();

	webseer_show_tab('webseer.php');

	$sql_where = '';
	$sort = get_request_var('sort_column');
	$limit = ' LIMIT ' . ($rows*(get_request_var('page')-1)) . ",$rows";

	if($statefilter != '') {
		$sql_where .= (!strlen($sql_where) ? 'WHERE ' : ' AND ') . $statefilter;
	}

	$sql = "SELECT * FROM plugin_webseer_urls
		$sql_where
		ORDER BY $sort " . get_request_var('sort_direction') .
		$limit;
	$result = db_fetch_assoc($sql);

	?>
	<script type='text/javascript'>
	var refreshIsLogout=false;
	var refreshPage='<?php print $refresh['page'];?>';
	var refreshMSeconds=<?php print $refresh['seconds']*1000;?>;

	function applyFilter() {
		strURL  = 'webseer.php?header=false&state=' + $('#state').val();
		strURL += '&refresh=' + $('#refresh').val();
		strURL += '&rows=' + $('#rows').val();
		loadPageNoHeader(strURL);
	}

	function clearFilter() {
		strURL = 'webseer.php?clear=1&header=false';
		loadPageNoHeader(strURL);
	}

	$(function(data) {
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

	html_start_box(__('Webseer Site Management') , '100%', '', '3', 'center', 'webseer_edit.php?action=edit');
	?>
	<tr class='even noprint'>
		<form id='webseer' action='webseer.php' method='post'>
		<input type='hidden' name='search' value='search'>
		<td class='noprint'>
			<table class='filterTable'>
				<tr class='noprint'>
					<td>
						<?php print __('State');?>
					</td>
					<td>
						<select id='state'>
							<option value='-1'><?php print __('Any');?></option>
							<?php
							foreach (array('2' => 'Disabled','1' => 'Enabled','3' => 'Triggered') as $key => $row) {
								echo "<option value='" . $key . "'" . (isset_request_var('state') && $key == get_request_var('state') ? ' selected' : '') . '>' . $row . '</option>';
							}
							?>
						</select>
					</td>
					<td>
						<?php print __('Refresh');?>
					</td>
					<td>
						<select id='refresh'>
							<?php
							foreach (array(0 => '', 20 => __('%d Seconds', 20), 30 => __('%d Seconds', 30), 45 => __('%d Seconds', 45), 60 => __('%d Minute', 1), 120 => __('%d Minutes', 2), 300 => __('%d Minutes', 5)) as $r => $row) {
								echo "<option value='" . $r . "'" . (isset_request_var('refresh') && $r == get_request_var('refresh') ? ' selected' : '') . '>' . $row . '</option>';
							}
							?>
						</select>
					</td>
					<td>
						<?php print __('Checks');?>
					</td>
					<td>
						<select id='rows'>
							<?php
							if (sizeof($item_rows)) {
							foreach ($item_rows as $key => $value) {
								print "<option value='" . $key . "'"; if (get_request_var('rows') == $key) { print ' selected'; } print '>' . htmlspecialchars($value) . "</option>\n";
							}
							}
							?>
						</select>
					</td>
					<td>
						<input type='button' id='clear' value='<?php print __('Clear');?>'>
					</td>
				</tr>
			</table>
		</td>
		</form>
	</tr>
	<?php

	html_end_box();

	$total_rows = count(db_fetch_assoc("SELECT id FROM plugin_webseer_urls $sql_where"));

	$nav = html_nav_bar('webseer.php', MAX_DISPLAY_PAGES, get_request_var('page'), $rows, $total_rows, 14, __('Checks'), 'page', 'main');

	form_start('webseer.php', 'chk');

	print $nav;

	html_start_box('', '100%', '', '4', 'center', '');

	$display_text = array(
		'nosort'          => array(__('Actions'), ''),
		'url'             => array(__('URL'), 'ASC'),
		'error'           => array(__('Error'), 'ASC'),
		'requireauth'     => array(__('Auth'), 'ASC'),
		'http_code'       => array(__('Http Code'), 'ASC'),
		'namelookup_time' => array(__('DNS'), 'ASC'),
		'connect_time'    => array(__('Connect'), 'ASC'),
		'redirect_time'   => array(__('Redirect'), 'ASC'),
		'total_time'      => array(__('Total'), 'ASC'),
		'timeout_trigger' => array(__('Timeout'), 'ASC'),
		'lastcheck'       => array(__('Last Check'), 'ASC'),
		'result'          => array(__('Result'), 'ASC'),
		'enabled'         => array(__('Enabled'), 'ASC')
	);

	html_header_sort_checkbox($display_text, get_request_var('sort_column'), get_request_var('sort_direction'));

	$i = 0;
	if (sizeof($result)) {
		foreach ($result as $row) {
			$i++;
			if ($row['result'] == 0 && $row['lastcheck'] > 0) {
				$alertstat = 'yes';
				$bgcolor   = 'red';
			} else {
				$alertstat = 'no';
				$bgcolor   = 'green';
			};

			form_alternate_row('line' . $row['id'], true);
			print "<td width='1%' style='white-space:nowrap'><a class='linkEditMain' href='webseer_edit.php?action=edit&id=" . $row['id'] . "'><img src='" . $config['url_path'] . "plugins/webseer/images/edit_object.png' border=0 alt='' title='" . __('Edit Site') . "'></a>";
			if ($row['enabled'] == 'off') {
				print "<a href='webseer.php?drp_action=3&chk_" . $row['id'] . "=1'><img src='" . $config['url_path'] . "plugins/webseer/images/enable_object.png' border=0 alt='' title='" . __('Enable Site') . "'></a>";
			} else {
				print "<a href='webseer.php?drp_action=2&chk_" . $row['id'] . "=1'><img src='" . $config['url_path'] . "plugins/webseer/images/disable_object.png' border=0 alt='' title='" . __('Disable Site') . "'></a>";
			}

			print "<a href='webseer.php?view_history=1&id=" . $row['id'] . "'><img src='" . $config['url_path'] . "plugins/webseer/images/view_history.gif' border=0 alt='' title='" . __('View History') . "'></td>";

			if ($row['type'] == 'http') {
				form_selectable_cell("<a class='linkEditMain' href='" . $row['url'] . "' target=_new>" . $row['url'] . '</a>', $row['id']);
			} else if ($row['type'] == 'dns') {
				form_selectable_cell(__('DNS: Server %s - A Record for %s', $row['url'], $row['search']), $row['id']);
			}
			form_selectable_cell($row['error'], $row['id'], '', ($row['error'] != '' ? 'background-color: red' : ''));
			form_selectable_cell((($row['requiresauth'] == 'off') ? '': __('Enabled')), $row['id']);
			form_selectable_cell($row['http_code'], $row['id']);
			form_selectable_cell(round($row['namelookup_time'], 4), $row['id'], '', ($row['namelookup_time'] > 4 ? 'background-color: red' : ($row['namelookup_time'] > 1 ? 'background-color: yellow':'')));
			form_selectable_cell(round($row['connect_time'], 4), $row['id'], '', ($row['connect_time'] > 4 ? 'background-color: red' : ($row['connect_time'] > 1 ? 'background-color: yellow':'')));
			form_selectable_cell(round($row['redirect_time'], 4), $row['id'], '', ($row['redirect_time'] > 4 ? 'background-color: red' : ($row['redirect_time'] > 1 ? 'background-color: yellow':'')));
			form_selectable_cell(round($row['total_time'], 4), $row['id'], '', ($row['total_time'] > 4 ? 'background-color: red' : ($row['total_time'] > 1 ? 'background-color: yellow':'')));
			form_selectable_cell($row['timeout_trigger'], $row['id']);
			form_selectable_cell(($row['lastcheck'] > 0 ? date('h:i:s', $row['lastcheck']) : ''), $row['id']);
			form_selectable_cell(($row['result'] == 1 ? __('Up') : __('Down')), $row['id']);
			form_selectable_cell(($row['enabled'] == 'on' ? __('Enabled') : __('Disabled')), $row['id']);
			form_checkbox_cell($row['url'], $row['id']);
			form_end_row();
		}
	} else {
		form_alternate_row();
		print '<td colspan=14><center>' . __('No Servers Found') . '</center></td></tr>';
	}

	html_end_box(false);

	if (sizeof($result)) {
		print $nav;
	}

	draw_actions_dropdown($ds_actions);

	form_end();

	bottom_footer();
}

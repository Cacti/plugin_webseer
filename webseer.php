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
	$urls = array();
	foreach ($_REQUEST as $var => $val) {
		if (preg_match('/^chk_(.*)$/', $var, $matches)) {
			$id = $matches[1];
			input_validate_input_number($id);
			$urls[$id] = $id;
		}
	}

	switch (get_nfilter_request_var('drp_action')) {
	case WEBSEER_ACTION_URL_DELETE: // Delete
		foreach ($urls as $id) {
			db_execute_prepared('DELETE FROM plugin_webseer_urls WHERE id = ?', array($id));
			db_execute_prepared('DELETE FROM plugin_webseer_urls_log WHERE url_id = ?', array($id));
			plugin_webseer_delete_remote_hosts($id);
		}

		break;
	case WEBSEER_ACTION_URL_DISABLE: // Disable
		foreach ($urls as $id) {
			db_execute_prepared('UPDATE plugin_webseer_urls SET enabled = "" WHERE id = ?', array($id));
			plugin_webseer_enable_remote_hosts($id, false);
		}

		break;
	case WEBSEER_ACTION_URL_ENABLE: // Enable
		foreach ($urls as $id) {
			db_execute_prepared('UPDATE plugin_webseer_urls SET enabled = "on" WHERE id = ?', array($id));
			plugin_webseer_enable_remote_hosts($id, true);
		}

		break;
	case WEBSEER_ACTION_URL_DUPLICATE: // Duplicate
		$newid = 1;
		foreach ($urls as $id) {
			$save = db_fetch_row_prepared('SELECT * FROM plugin_webseer_urls WHERE id = ?', array($id));
			$save['id']              = 0;
			$save['display_name']    = 'New Service Check (' . $newid . ')';
			$save['lastcheck']       = '0000-00-00';
			$save['result']          = 0;
			$save['triggered']       = 0;
			$save['enabled']         = '';
			$save['failures']        = 0;
			$save['error']           = '';
			$save['http_code']       = 0;
			$save['total_time']      = 0;
			$save['namelookup_time'] = 0;
			$save['connect_time']    = 0;
			$save['redirect_time']   = 0;
			$save['speed_download']  = 0;
			$save['size_download']   = 0;
			$save['redirect_count']  = 0;
			$save['debug']           = '';

			sql_save($save, 'plugin_webseer_urls');

			$newid++;
		}

		break;
	}

	header('Location: webseer.php?header=false');
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
		'rfilter' => array(
			'filter' => FILTER_VALIDATE_IS_REGEX,
			'default' => '',
			'pageset' => true
			),
		'sort_column' => array(
			'filter' => FILTER_CALLBACK,
			'default' => 'display_name',
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

function webseer_show_history() {
	global $config, $webseer_bgcolors, $httperrors, $httpcompressions;

	if (isset_request_var('id')) {
		$id = get_filter_request_var('id');
	} else {
		Location('webseer.php?header=false');
		exit;
	}

	$result = db_fetch_assoc_prepared("SELECT pwul.*, pwu.url
		FROM plugin_webseer_urls_log AS pwul
		INNER JOIN plugin_webseer_urls AS pwu
		ON pwul.url_id=pwu.id
		WHERE pwu.id = ?
		ORDER BY pwul.lastcheck DESC", array($id));

	top_header();

	webseer_show_tab('webseer.php');

	html_start_box('', '100%', '', '4', 'center', '');

	$display_text = array(
		__('Date', 'webseer'),
		__('URL', 'webseer'),
		__('Compression', 'webseer'),
		__('HTTP Code', 'webseer'),
		__('DNS', 'webseer'),
		__('Connect', 'webseer'),
		__('Redirect', 'webseer'),
		__('Total', 'webseer'),
		__('Status', 'webseer')
	);

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
			}

			form_alternate_row_color($webseer_bgcolors[$bgcolor], $webseer_bgcolors[$bgcolor], $i, 'line' . $row['id']); $i++;
			form_selectable_cell($row['lastcheck'], $row['id']);
			form_selectable_cell("<a class='linkEditMain' href='" . $row['url'] . "' target=_new>" . $row['url'] . '</a>', $row['id']);
			form_selectable_cell($httpcompressions[$row['compression']], $row['id']);
			form_selectable_cell($httperrors[$row['http_code']], $row['id'], '', '', $row['error']);
			form_selectable_cell(round($row['namelookup_time'], 4), $row['id'], '', ($row['namelookup_time'] > 4 ? 'background-color: red' : ($row['namelookup_time'] > 1 ? 'background-color: yellow':'')));
			form_selectable_cell(round($row['connect_time'], 4), $row['id'], '', ($row['connect_time'] > 4 ? 'background-color: red' : ($row['connect_time'] > 1 ? 'background-color: yellow':'')));
			form_selectable_cell(round($row['redirect_time'], 4), $row['id'], '', ($row['redirect_time'] > 4 ? 'background-color: red' : ($row['redirect_time'] > 1 ? 'background-color: yellow':'')));
			form_selectable_cell(round($row['total_time'], 4), $row['id'], '', ($row['total_time'] > 4 ? 'background-color: red' : ($row['total_time'] > 1 ? 'background-color: yellow':'')));
			form_selectable_cell(($row['result'] == 1 ? __('Up', 'webseer') : __('Down', 'webseer')), $row['id']);
			form_end_row();
		}
	} else {
		form_alternate_row();
		print '<td colspan=12><i>' . __('No Events in History', 'webseer') . '</i></td></tr>';
	}

	html_end_box(false);
}

function list_urls() {
	global $webseer_bgcolors, $webseer_actions_url, $httperrors, $config, $hostid, $refresh, $httpcompressions;

	/* ================= input validation and session storage ================= */
	$filters = array(
		'rows' => array(
			'filter' => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-1'
			),
		'state' => array(
			'filter' => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-1'
			),
		'refresh' => array(
			'filter' => FILTER_VALIDATE_INT,
			'default' => '20'
			),
		'sort_column' => array(
			'filter' => FILTER_CALLBACK,
			'default' => 'display_name',
			'options' => array('options' => 'sanitize_search_string')
			),
		'sort_direction' => array(
			'filter' => FILTER_CALLBACK,
			'default' => 'ASC',
			'options' => array('options' => 'sanitize_search_string')
			)
	);

	validate_store_request_vars($filters, 'sess_wbsu');
	/* ================= input validation ================= */

	webseer_request_validation();

	$statefilter='';
	if (isset_request_var('state')) {
		if (get_request_var('state') == '-1') {
			$statefilter = '';
		} else {
			if (get_request_var('state') == '2') { $statefilter = "plugin_webseer_urls.enabled=''"; }
			if (get_request_var('state') == '1') { $statefilter = "plugin_webseer_urls.enabled='on'"; }
			if (get_request_var('state') == '3') { $statefilter = 'plugin_webseer_urls.result!=1'; }
		}
	}

	top_header();

	webseer_show_tab('webseer.php');

	webseer_filter();

	if (get_request_var('rows') == '-1') {
		$rows = read_config_option('num_rows_table');
	}else{
		$rows = get_request_var('rows');
	}

	$sql_where = '';

	if ($statefilter != '') {
		$sql_where .= ($sql_where != '' ? ' AND ' : 'WHERE ') . $statefilter;
	}

	$sql_order = get_order_string();
	$sql_limit = ' LIMIT ' . ($rows*(get_request_var('page')-1)) . ',' . $rows;

	if (get_request_var('rfilter') != '') {
		$sql_where .= ($sql_where == '' ? 'WHERE ' : ' AND ') .
			'display_name RLIKE \'' . get_request_var('rfilter') . '\' OR ' .
			'url RLIKE \'' . get_request_var('rfilter') . '\' OR ' .
			'search RLIKE \'' . get_request_var('rfilter') . '\' OR ' .
			'search_maint RLIKE \'' . get_request_var('rfilter') . '\' OR ' .
			'search_failed RLIKE \'' . get_request_var('rfilter') . '\'';
	}

	$result = db_fetch_assoc("SELECT *
		FROM plugin_webseer_urls
		$sql_where
		$sql_order
		$sql_limit");

	$total_rows = db_fetch_cell("SELECT COUNT(id)
		FROM plugin_webseer_urls
		$sql_where");

	$display_text = array(
		'nosort'          => array('display' => __('Actions', 'webseer'),     'sort' => '',    'align' => 'left'),
		'display_name'    => array('display' => __('Name', 'webseer'),        'sort' => 'ASC', 'align' => 'left'),
		'result'          => array('display' => __('Status', 'webseer'),      'sort' => 'ASC', 'align' => 'right'),
		'enabled'         => array('display' => __('Enabled', 'webseer'),     'sort' => 'ASC', 'align' => 'right'),
		'compression'     => array('display' => __('Compression', 'webseer'), 'sort' => 'ASC', 'align' => 'right'),
		'http_code'       => array('display' => __('HTTP Code', 'webseer'),   'sort' => 'ASC', 'align' => 'right'),
		'requireauth'     => array('display' => __('Auth', 'webseer'),        'sort' => 'ASC', 'align' => 'right'),
		'namelookup_time' => array('display' => __('DNS', 'webseer'),         'sort' => 'ASC', 'align' => 'right'),
		'connect_time'    => array('display' => __('Connect', 'webseer'),     'sort' => 'ASC', 'align' => 'right'),
		'redirect_time'   => array('display' => __('Redirect', 'webseer'),    'sort' => 'ASC', 'align' => 'right'),
		'total_time'      => array('display' => __('Total', 'webseer'),       'sort' => 'ASC', 'align' => 'right'),
		'timeout_trigger' => array('display' => __('Timeout', 'webseer'),     'sort' => 'ASC', 'align' => 'right'),
		'lastcheck'       => array('display' => __('Last Check', 'webseer'),  'sort' => 'ASC', 'align' => 'right')
	);

	$columns = cacti_sizeof($display_text);

	$nav = html_nav_bar('webseer.php', MAX_DISPLAY_PAGES, get_request_var('page'), $rows, $total_rows, $columns, __('Checks', 'webseer'), 'page', 'main');

	form_start('webseer.php', 'chk');

	print $nav;

	html_start_box('', '100%', '', '4', 'center', '');

	html_header_sort_checkbox($display_text, get_request_var('sort_column'), get_request_var('sort_direction'));

	$i = 0;
	if (cacti_sizeof($result)) {
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
			print "<td width='1%' style='padding:0px;white-space:nowrap'>
				<a class='pic' href='" . htmlspecialchars($config['url_path'] . 'plugins/webseer/webseer_edit.php?action=edit&id=' . $row['id']) . "'>
					<img src='" . $config['url_path'] . "plugins/webseer/images/edit_object.png' alt='' title='" . __esc('Edit Site', 'webseer') . "'>
				</a>";

			if ($row['enabled'] == '') {
				print "<a class='pic' href='" . htmlspecialchars($config['url_path'] . 'plugins/webseer/webseer.php?drp_action=' . WEBSEER_ACTION_URL_ENABLE .'&chk_' . $row['id']) . "=1'>
					<img src='" . $config['url_path'] . "plugins/webseer/images/enable_object.png' alt='' title='" . __esc('Enable Site', 'webseer') . "'>
				</a>";
			} else {
				print "<a class='pic' href='" . htmlspecialchars($config['url_path'] . 'plugins/webseer/webseer.php?drp_action=' . WEBSEER_ACTION_URL_DISABLE . '&chk_' . $row['id']) . "=1'>
					<img src='" . $config['url_path'] . "plugins/webseer/images/disable_object.png' alt='' title='" . __esc('Disable Site', 'webseer') . "'>
				</a>";
			}

			print "<a class='pic' href='" . htmlspecialchars($config['url_path'] . 'plugins/webseer/webseer.php?view_history=1&id=' . $row['id']) . "'>
					<img src='" . $config['url_path'] . "plugins/webseer/images/view_history.gif' alt='' title='" . __esc('View History', 'webseer') . "'>
				</a>
			</td>";

			$url='';
			if ($row['type'] == 'http') {
				$url = $row['url'];
			} else if ($row['type'] == 'dns') {
				$url = __('DNS: Server %s - A Record for %s', $row['url'], $row['search'], 'webseer');
			}

			if (trim($url) == '') {
				form_selectable_cell($row['display_name'], $row['id']);
			} else {
				form_selectable_cell($row['display_name'], $row['id'], '', '', html_escape($url));
			}

			if ($row['lastcheck'] == '0000-00-00 00:00:00') {
				form_selectable_cell(__('N/A', 'webseer'), $row['id'], '', 'right');
			}else{
				form_selectable_cell(($row['result'] == 1 ? __('Up', 'webseer') : __('Down', 'webseer')), $row['id'], '', 'right');
			}

			form_selectable_cell(($row['enabled'] == 'on' ? __('Enabled', 'webseer') : __('Disabled', 'webseer')), $row['id'], '', 'right');
			form_selectable_cell($httpcompressions[$row['compression']], $row['id'], '', 'right');
			form_selectable_cell(!empty($row['http_code']) ? $httperrors[$row['http_code']]:__('Error', 'webseer'), $row['id'], '', $row['error'] != '' ? 'deviceDown right':'right', $row['error']);
			form_selectable_cell((($row['requiresauth'] == '') ? __('Disabled', 'webseer'): __('Enabled', 'webseer')), $row['id'], '', 'right');

			form_selectable_cell(round($row['namelookup_time'], 4), $row['id'], '', ($row['namelookup_time'] > 4 ? 'deviceDown right' : ($row['namelookup_time'] > 1 ? 'deviceRecovering right':'right')));
			form_selectable_cell(round($row['connect_time'], 4), $row['id'], '', ($row['connect_time'] > 4 ? 'deviceDown right' : ($row['connect_time'] > 1 ? 'deviceRecovering right':'right')));
			form_selectable_cell(round($row['redirect_time'], 4), $row['id'], '', ($row['redirect_time'] > 4 ? 'deviceDown right' : ($row['redirect_time'] > 1 ? 'deviceRecovering right':'right')));
			form_selectable_cell(round($row['total_time'], 4), $row['id'], '', ($row['total_time'] > 4 ? 'deviceDown right' : ($row['total_time'] > 1 ? 'deviceRecovering right':'right')));
			form_selectable_cell($row['timeout_trigger'], $row['id'], '', 'right');
			form_selectable_cell((strtotime($row['lastcheck']) > 0 ? substr($row['lastcheck'],5) : ''), $row['id'], '', 'right');

			form_checkbox_cell($row['url'], $row['id']);
			form_end_row();
		}
	} else {
		form_alternate_row();
		print '<td colspan=14><center>' . __('No Servers Found', 'webseer') . '</center></td></tr>';
	}

	html_end_box(false);

	if (cacti_sizeof($result)) {
		print $nav;
	}

	draw_actions_dropdown($webseer_actions_url);

	form_end();

	?>
	<script type='text/javascript'>
	$(function() {
		$('#webseer2_child').find('.cactiTooltipHint').each(function() {
			var title = $(this).attr('title');

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

	$refresh['page']    = 'webseer.php?header=false';
	$refresh['seconds'] = get_request_var('refresh');
	$refresh['logout']  = 'false';

	set_page_refresh($refresh);

	?>
	<script type='text/javascript'>
	function applyFilter() {
		strURL  = 'webseer.php?header=false&state=' + $('#state').val();
		strURL += '&refresh=' + $('#refresh').val();
		strURL += '&rfilter=' + $('#rfilter').val();
		strURL += '&rows=' + $('#rows').val();
		loadPageNoHeader(strURL);
	}

	function clearFilter() {
		strURL = 'webseer.php?clear=1&header=false';
		loadPageNoHeader(strURL);
	}

	$(function() {
		$('#refresh, #state, #rows, #rfilter').change(function() {
			applyFilter();
		});

		$('#go').click(function() {
			applyFilter();
		});

		$('#clear').click(function() {
			clearFilter();
		});

		$('#form_webseer').submit(function(event) {
			event.preventDefault();
			applyFilter();
		});
	});

	</script>
	<?php

	html_start_box(__('Webseer Site Management', 'webseer') , '100%', '', '3', 'center', 'webseer_edit.php?action=edit');
	?>
	<tr class='even noprint'>
		<form id='form_webseer' action='webseer.php'>
		<input type='hidden' name='search' value='search'>
		<td class='noprint'>
			<table class='filterTable'>
				<tr class='noprint'>
					<td>
						<?php print __('Search', 'webseer');?>
					</td>
					<td>
						<input type='text' id='rfilter' size='30' value='<?php print get_request_var('rfilter');?>'>
					</td>
					<td>
						<?php print __('State', 'webseer');?>
					</td>
					<td>
						<select id='state'>
							<option value='-1'><?php print __('Any', 'webseer');?></option>
							<?php
							foreach (array('2' => 'Disabled', '1' => 'Enabled', '3' => 'Triggered') as $key => $row) {
								echo "<option value='" . $key . "'" . (isset_request_var('state') && $key == get_request_var('state') ? ' selected' : '') . '>' . $row . '</option>';
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
						<?php print __('Checks', 'webseer');?>
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
							<input type='button' id='go' value='<?php print __esc('Go', 'webseer');?>'>
							<input type='button' id='clear' value='<?php print __esc('Clear', 'webseer');?>'>
						</span>
					</td>
				</tr>
			</table>
		</td>
		</form>
	</tr>
	<?php

	html_end_box();
}


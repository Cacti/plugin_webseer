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

global $refresh;

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
case 'edit':
	top_header();
	webseer_edit_url();
	bottom_footer();

	break;
case 'history':
	webseer_show_history();

	break;
default:
	list_urls();

	break;
}

exit;

function form_actions() {
	global $webseer_actions_url;

	/* if we are to save this form, instead of display it */
	if (isset_request_var('selected_items')) {
		$selected_items = sanitize_unserialize_selected_items(get_nfilter_request_var('selected_items'));
		$action         = get_nfilter_request_var('drp_action');

		if ($selected_items != false) {
			if (cacti_sizeof($selected_items)) {
				foreach($selected_items as $url) {
					$urls[] = $url;
				}
			}

			if (cacti_sizeof($urls)) {
				if ($action == WEBSEER_ACTION_URL_DELETE) { // delete
					foreach ($urls as $id) {
						db_execute_prepared('DELETE FROM plugin_webseer_urls WHERE id = ?', array($id));
						db_execute_prepared('DELETE FROM plugin_webseer_urls_log WHERE url_id = ?', array($id));
						plugin_webseer_delete_remote_hosts($id);
					}
				} elseif ($action == WEBSEER_ACTION_URL_DISABLE) { // disable
					foreach ($urls as $id) {
						db_execute_prepared('UPDATE plugin_webseer_urls SET enabled = "" WHERE id = ?', array($id));
						plugin_webseer_enable_remote_hosts($id, false);
					}
				} elseif ($action == WEBSEER_ACTION_URL_ENABLE) { // enable
					foreach ($urls as $id) {
						db_execute_prepared('UPDATE plugin_webseer_urls SET enabled = "on" WHERE id = ?', array($id));
						plugin_webseer_enable_remote_hosts($id, true);
					}
				} elseif ($action == WEBSEER_ACTION_URL_DUPLICATE) { // duplicate
					foreach($urls as $url) {
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

							$id = sql_save($save, 'plugin_webseer_urls');

							$newid++;
						}
					}
				}
			}
		}

		header('Location: webseer.php?header=false');

		exit;
	}

	/* setup some variables */
	$url_list  = '';
	$url_array = array();

	/* loop through each of the graphs selected on the previous page and get more info about them */
	foreach ($_POST as $var => $val) {
		if (preg_match('/^chk_([0-9]+)$/', $var, $matches)) {
			/* ================= input validation ================= */
			input_validate_input_number($matches[1]);
			/* ==================================================== */

			$url_list .= '<li>' . db_fetch_cell_prepared('SELECT display_name FROM plugin_webseer_urls WHERE id = ?', array($matches[1])) . '</li>';
			$url_array[] = $matches[1];
		}
	}

	top_header();

	form_start('webseer.php');

	html_start_box($webseer_actions_url[get_nfilter_request_var('drp_action')], '60%', '', '3', 'center', '');

	$action = get_nfilter_request_var('drp_action');

	if (cacti_sizeof($url_array)) {
		if ($action == WEBSEER_ACTION_URL_DELETE) { // delete
			print "	<tr>
					<td class='topBoxAlt'>
						<p>" . __n('Click \'Continue\' to Delete the following URL.', 'Click \'Continue\' to Delete following URLs.', cacti_sizeof($url_array)) . "</p>
						<div class='itemlist'><ul>$url_list</ul></div>
					</td>
				</tr>\n";

			$save_html = "<input type='button' value='" . __esc('Cancel') . "' onClick='cactiReturnTo()'>&nbsp;<input type='submit' value='" . __esc('Continue') . "' title='" . __esc_n('Delete URL', 'Delete URLs', cacti_sizeof($url_array)) . "'>";
		} elseif ($action == WEBSEER_ACTION_URL_DISABLE) {
			print "	<tr>
					<td class='topBoxAlt'>
						<p>" . __n('Click \'Continue\' to Disable the following URL.', 'Click \'Continue\' to Disable following URLs.', cacti_sizeof($url_array)) . "</p>
						<div class='itemlist'><ul>$url_list</ul></div>
					</td>
				</tr>\n";

			$save_html = "<input type='button' value='" . __esc('Cancel') . "' onClick='cactiReturnTo()'>&nbsp;<input type='submit' value='" . __esc('Continue') . "' title='" . __esc_n('Disable URL', 'Disable URLs', cacti_sizeof($url_array)) . "'>";
		} elseif ($action == WEBSEER_ACTION_URL_ENABLE) {
			print "	<tr>
					<td class='topBoxAlt'>
						<p>" . __n('Click \'Continue\' to Enable the following URL.', 'Click \'Continue\' to Enable following URLs.', cacti_sizeof($url_array)) . "</p>
						<div class='itemlist'><ul>$url_list</ul></div>
					</td>
				</tr>\n";

			$save_html = "<input type='button' value='" . __esc('Cancel') . "' onClick='cactiReturnTo()'>&nbsp;<input type='submit' value='" . __esc('Continue') . "' title='" . __esc_n('Enable URL', 'Enable URLs', cacti_sizeof($url_array)) . "'>";
		} elseif ($action == WEBSEER_ACTION_URL_DUPLICATE) {
			print "	<tr>
					<td class='topBoxAlt'>
						<p>" . __n('Click \'Continue\' to Duplicate the following URL.', 'Click \'Continue\' to Duplicate following URLs.', cacti_sizeof($url_array)) . "</p>
						<div class='itemlist'><ul>$url_list</ul></div>
					</td>
				</tr>\n";

			$save_html = "<input type='button' value='" . __esc('Cancel') . "' onClick='cactiReturnTo()'>&nbsp;<input type='submit' value='" . __esc('Continue') . "' title='" . __esc_n('Duplicate URL', 'Duplicate URLs', cacti_sizeof($url_array)) . "'>";
		}
	} else {
		raise_message(40);
		header('Location: webseer.php');
		exit;
	}

	print "<tr>
		<td class='saveRow'>
			<input type='hidden' name='action' value='actions'>
			<input type='hidden' name='selected_items' value='" . (isset($url_array) ? serialize($url_array) : '') . "'>
			<input type='hidden' name='drp_action' value='" . get_nfilter_request_var('drp_action') . "'>
			$save_html
		</td>
	</tr>\n";

	html_end_box();

	form_end();

	bottom_footer();
}

function form_save() {
	/* ================= input validation ================= */
	get_filter_request_var('id');
	get_filter_request_var('downtrigger');
	get_filter_request_var('timeout_trigger');
	get_filter_request_var('compression');
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

	if (isset_request_var('requiresauth')) {
		$save['requiresauth'] = 'on';
	} else {
		$save['requiresauth'] = '';
	}

	if (isset_request_var('checkcert')) {
		$save['checkcert'] = 'on';
	} else {
		$save['checkcert'] = '';
	}

	if (isset_request_var('notify_accounts')) {
		if (is_array(get_nfilter_request_var('notify_accounts'))) {
			foreach (get_nfilter_request_var('notify_accounts') as $na) {
				input_validate_input_number($na);
			}
			$save['notify_accounts'] = implode(',', get_nfilter_request_var('notify_accounts'));
		} else {
			set_request_var('notify_accounts', '');
		}
	} else {
		$save['notify_accounts'] = '';
	}

	$save['proxy_server']    = get_nfilter_request_var('proxy_server');
	$save['display_name']    = get_nfilter_request_var('display_name');
	$save['url']             = get_nfilter_request_var('url');
	$save['ip']              = get_nfilter_request_var('ip');
	$save['search']          = get_nfilter_request_var('search');
	$save['search_maint']    = get_nfilter_request_var('search_maint');
	$save['search_failed']   = get_nfilter_request_var('search_failed');
	$save['compression']     = get_nfilter_request_var('compression');
	$save['notify_extra']    = get_nfilter_request_var('notify_extra');
	$save['downtrigger']     = get_filter_request_var('downtrigger');
	$save['timeout_trigger'] = get_filter_request_var('timeout_trigger');

	$id = sql_save($save, 'plugin_webseer_urls', 'id');

	plugin_webseer_remove_old_users();

	if (!is_error_message()) {
		if ($save['id'] == 0) {
			plugin_webseer_add_remote_hosts($id, $save);
		} else {
			plugin_webseer_update_remote_hosts($save);
		}

		if ($id) {
			raise_message(1);
		} else {
			raise_message(2);
		}
	}

	header('Location: webseer.php?action=edit&id=' . $id . '&header=false');
	exit;
}

function webseer_edit_url() {
	global $webseer_url_fields;

	/* ================= input validation ================= */
	get_filter_request_var('id');
	/* ==================================================== */

	$url = array();
	if (!isempty_request_var('id')) {
		$url = db_fetch_row_prepared('SELECT * FROM plugin_webseer_urls WHERE id = ?', array(get_request_var('id')), false);
		$header_label = __('Query [edit: %s]', $url['url'], 'webseer');
	} else {
		$header_label = __('Query [new]', 'webseer');
	}

	form_start('webseer.php');

	html_start_box($header_label, '100%', '', '3', 'center', '');

	draw_edit_form(
		array(
			'config' => array('form_name' => 'chk'),
			'fields' => inject_form_variables($webseer_url_fields, $url)
		)
	);

	html_end_box();

	form_save_button('webseer.php', 'return');

	?>
	<script type='text/javascript'>

	$(function() {
		var msWidth = 100;
		$('#notify_accounts option').each(function() {
			if ($(this).textWidth() > msWidth) {
				msWidth = $(this).textWidth();
			}
			$('#notify_accounts').css('width', msWidth+80+'px');
		});

		$('#notify_accounts').hide().multiselect({
			noneSelectedText: '<?php print __('No Users Selected', 'webseer');?>',
			selectedText: function(numChecked, numTotal, checkedItems) {
				myReturn = numChecked + ' <?php print __('Users Selected', 'webseer');?>';
				$.each(checkedItems, function(index, value) {
					if (value.value == '0') {
						myReturn='<?php print __('All Users Selected', 'webseer');?>';
						return false;
					}
				});
				return myReturn;
			},
			checkAllText: '<?php print __('All', 'webseer');?>',
			uncheckAllText: '<?php print __('None', 'webseer');?>',
			uncheckall: function() {
				$(this).multiselect('widget').find(':checkbox:first').each(function() {
					$(this).prop('checked', true);
				});
			},
			open: function(event, ui) {
				$("input[type='search']:first").focus();
			},
			click: function(event, ui) {
				checked=$(this).multiselect('widget').find('input:checked').length;

				if (ui.value == 0) {
					if (ui.checked == true) {
						$('#notify_accounts').multiselect('uncheckAll');
						$(this).multiselect('widget').find(':checkbox:first').each(function() {
							$(this).prop('checked', true);
						});
					}
				} else if (checked == 0) {
					$(this).multiselect('widget').find(':checkbox:first').each(function() {
						$(this).click();
					});
				} else if ($(this).multiselect('widget').find('input:checked:first').val() == '0') {
					if (checked > 0) {
						$(this).multiselect('widget').find(':checkbox:first').each(function() {
							$(this).click();
							$(this).prop('disable', true);
						});
					}
				}
			}
		}).multiselectfilter({
			label: '<?php print __('Search', 'webseer');?>',
			width: msWidth
		});
	});

	</script>
	<?php
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
			'default' => read_config_option('log_refresh_interval')
			),
		'rfilter' => array(
			'filter' => FILTER_VALIDATE_IS_REGEX,
			'default' => '',
			'pageset' => true,
			'options' => array('options' => 'sanitize_search_string')
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
			'default' => read_config_option('log_refresh_interval')
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

	$statefilter = '';
	if (isset_request_var('state')) {
		if (get_request_var('state') == '-1') {
			$statefilter = '';
		} elseif (get_request_var('state') == '2') {
			$statefilter = "plugin_webseer_urls.enabled = ''";
		} elseif (get_request_var('state') == '1') {
			$statefilter = "plugin_webseer_urls.enabled = 'on'";
		} elseif (get_request_var('state') == '3') {
			$statefilter = 'plugin_webseer_urls.result != 1';
		}
	}

	top_header();

	webseer_show_tab('webseer.php');

	webseer_filter();

	if (get_request_var('rows') == '-1') {
		$rows = read_config_option('num_rows_table');
	} else {
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
		'nosort' => array(
			'display' => __('Actions', 'webseer'),
			'sort'    => '',
			'align'   => 'left'
		),
		'display_name' => array(
			'display' => __('Name', 'webseer'),
			'sort'    => 'ASC',
			'align'   => 'left'
		),
		'result' => array(
			'display' => __('Status', 'webseer'),
			'sort'    => 'ASC',
			'align'   => 'right'
		),
		'enabled' => array(
			'display' => __('Enabled', 'webseer'),
			'sort'    => 'ASC',
			'align'   => 'right'
		),
		'compression' => array(
			'display' => __('Compression', 'webseer'),
			'sort'    => 'ASC',
			'align'   => 'right'
		),
		'http_code' => array(
			'display' => __('HTTP Code', 'webseer'),
			'sort'    => 'ASC',
			'align'   => 'right'
		),
		'requireauth' => array(
			'display' => __('Auth', 'webseer'),
			'sort'    => 'ASC',
			'align'   => 'right'
		),
		'namelookup_time' => array(
			'display' => __('DNS', 'webseer'),
			'sort'    => 'ASC',
			'align'   => 'right'
		),
		'connect_time' => array(
			'display' => __('Connect', 'webseer'),
			'sort'    => 'ASC',
			'align'   => 'right'
		),
		'redirect_time' => array(
			'display' => __('Redirect', 'webseer'),
			'sort'    => 'ASC',
			'align'   => 'right'
		),
		'total_time' => array(
			'display' => __('Total', 'webseer'),
			'sort'    => 'ASC',
			'align'   => 'right'
		),
		'timeout_trigger' => array(
			'display' => __('Timeout', 'webseer'),
			'sort'    => 'ASC',
			'align'   => 'right'
		),
		'lastcheck' => array(
			'display' => __('Last Check', 'webseer'),
			'sort'    => 'ASC',
			'align'   => 'right'
		)
	);

	$columns = cacti_sizeof($display_text);

	$nav = html_nav_bar('webseer.php', MAX_DISPLAY_PAGES, get_request_var('page'), $rows, $total_rows, $columns, __('Checks', 'webseer'), 'page', 'main');

	form_start('webseer.php', 'chk');

	print $nav;

	html_start_box('', '100%', '', '4', 'center', '');

	html_header_sort_checkbox($display_text, get_request_var('sort_column'), get_request_var('sort_direction'), false);

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
				<a class='pic' href='" . html_escape($config['url_path'] . 'plugins/webseer/webseer.php?action=edit&id=' . $row['id']) . "' title='" . __esc('Edit Site', 'webseer') . "'>
					<i class='tholdGlyphEdit fas fa-wrench'></i>
				</a>";

			if ($row['enabled'] == '') {
				print "<a class='pic' href='" . html_escape($config['url_path'] . 'plugins/webseer/webseer.php?drp_action=' . WEBSEER_ACTION_URL_ENABLE .'&chk_' . $row['id'] . '=1') . "' title='" . __esc('Enable Site', 'webseer') . "'>
					<i class='tholdGlyphEnable fas fa-play-circle'></i>
				</a>";
			} else {
				print "<a class='pic' href='" . html_escape($config['url_path'] . 'plugins/webseer/webseer.php?drp_action=' . WEBSEER_ACTION_URL_DISABLE . '&chk_' . $row['id'] . '=1') . "' title='" . __esc('Disable Site', 'webseer') . "'>
					<i class='tholdGlyphDisable fas fa-stop-circle'></i>
				</a>";
			}

			print "<a class='pic' href='" . html_escape($config['url_path'] . 'plugins/webseer/webseer.php?action=history=1&id=' . $row['id']) . "' title='" . __esc('View History', 'webseer') . "'>
					<i class='tholdGlyphLog fas fa-exclamation-triangle'></i>
				</a>
			</td>";

			$url = '';
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
			} else {
				form_selectable_cell(($row['result'] == 1 ? __('Up', 'webseer') : __('Down', 'webseer')), $row['id'], '', 'right');
			}

			form_selectable_cell(($row['enabled'] == 'on' ? __('Enabled', 'webseer') : __('Disabled', 'webseer')), $row['id'], '', 'right');
			form_selectable_cell($httpcompressions[$row['compression']], $row['id'], '', 'right');
			form_selectable_cell(!empty($row['http_code']) ? $httperrors[$row['http_code']]:__('Error', 'webseer'), $row['id'], '', $row['error'] != '' ? 'deviceDown right':'right', $row['error']);
			form_selectable_cell((($row['requiresauth'] == '') ? __('Disabled', 'webseer'): __('Enabled', 'webseer')), $row['id'], '', 'right');

			form_selectable_cell(round(webseer_checknull($row['namelookup_time']), 4), $row['id'], '', ($row['namelookup_time'] > 4 ? 'deviceDown right' : ($row['namelookup_time'] > 1 ? 'deviceRecovering right':'right')));
			form_selectable_cell(round(webseer_checknull($row['connect_time']), 4), $row['id'], '', ($row['connect_time'] > 4 ? 'deviceDown right' : ($row['connect_time'] > 1 ? 'deviceRecovering right':'right')));
			form_selectable_cell(round(webseer_checknull($row['redirect_time']), 4), $row['id'], '', ($row['redirect_time'] > 4 ? 'deviceDown right' : ($row['redirect_time'] > 1 ? 'deviceRecovering right':'right')));
			form_selectable_cell(round(webseer_checknull($row['total_time']), 4), $row['id'], '', ($row['total_time'] > 4 ? 'deviceDown right' : ($row['total_time'] > 1 ? 'deviceRecovering right':'right')));
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

function webseer_checknull($value) {
	if ($value == NULL) {
		return '0';
	} else {
		return $value;
	}
}

function webseer_filter() {
	global $item_rows, $page_refresh_interval;

	$refresh['page']    = 'webseer.php?header=false';
	$refresh['seconds'] = get_request_var('refresh');
	$refresh['logout']  = 'false';

	set_page_refresh($refresh);

	?>
	<script type='text/javascript'>
	function applyFilter() {
		strURL  = 'webseer.php?header=false&state=' + $('#state').val();
		strURL += '&refresh=' + $('#refresh').val();
		strURL += '&rfilter=' + base64_encode($('#rfilter').val());
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

	html_start_box(__('Webseer Site Management', 'webseer') , '100%', '', '3', 'center', 'webseer.php?action=edit');
	?>
	<tr class='even noprint'>
		<td class='noprint'>
			<form id='form_webseer' action='webseer.php'>
			<input type='hidden' name='search' value='search'>
			<table class='filterTable'>
				<tr class='noprint'>
					<td>
						<?php print __('Search', 'webseer');?>
					</td>
					<td>
						<input type='text' class='ui-state-default ui-corner-all' id='rfilter' size='30' value='<?php print html_escape_request_var('rfilter');?>'>
					</td>
					<td>
						<?php print __('State', 'webseer');?>
					</td>
					<td>
						<select id='state'>
							<option value='-1'><?php print __('Any', 'webseer');?></option>
							<?php
							foreach (array('2' => 'Disabled', '1' => 'Enabled', '3' => 'Triggered') as $key => $row) {
								print "<option value='" . $key . "'" . (isset_request_var('state') && $key == get_request_var('state') ? ' selected' : '') . '>' . $row . '</option>';
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
							foreach($page_refresh_interval AS $seconds => $display_text) {
								print "<option value='" . $seconds . "'";
								if (get_request_var('refresh') == $seconds) {
									print ' selected';
								}
								print '>' . $display_text . "</option>\n";
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
		</form>
		</td>
	</tr>
	<?php

	html_end_box();

}

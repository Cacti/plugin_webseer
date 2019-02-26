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

include('./include/auth.php');
include_once($config['base_path'] . '/lib/functions.php');
include_once($config['base_path'] . '/plugins/webseer/includes/functions.php');

if (isset_request_var('action') && get_nfilter_request_var('action') == 'save') {
	$action = 'save';
} else {
	$action = 'edit';
}

switch ($action) {
case 'save':
	webseer_save_url();
	break;
case 'edit':
default:
	plugin_webseer_update_contacts();
	top_header();
	webseer_edit_url();
	bottom_footer();
	break;
}

function webseer_save_url() {
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
	}else{
		$save['enabled'] = '';
	}

	if (isset_request_var('requiresauth')) {
		$save['requiresauth'] = 'on';
	}else{
		$save['requiresauth'] = '';
	}

	if (isset_request_var('checkcert')) {
		$save['checkcert'] = 'on';
	}else{
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
			plugin_webseer_add_remote_hosts ($id, $save);
		} else {
			plugin_webseer_update_remote_hosts ($save);
		}
		if ($id) {
			raise_message(1);
		} else {
			raise_message(2);
		}
	}

	header('Location: webseer_edit.php?action=edit&id=' . $save['id'] . '&header=false');
	exit;
}

function webseer_edit_url () {
	global $webseer_url_fields;

	/* ================= input validation ================= */
	get_filter_request_var('id');
	/* ==================================================== */

	$url = array();
	if (!isempty_request_var('id')) {
		$url = db_fetch_row_prepared('SELECT * FROM plugin_webseer_urls WHERE id = ?', array(get_request_var('id')), false);
		$header_label = __('Query [edit: %s]', $url['url'], 'webseer');
	}else{
		$header_label = __('Query [new]', 'webseer');
	}

	form_start('webseer_edit.php');

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
				}else if (checked == 0) {
					$(this).multiselect('widget').find(':checkbox:first').each(function() {
						$(this).click();
					});
				}else if ($(this).multiselect('widget').find('input:checked:first').val() == '0') {
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


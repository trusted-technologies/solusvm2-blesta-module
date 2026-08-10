<?php
/**
 * en_us language for the SolusVM 2 module
 */
// Basics
$lang['Solusvm2.name'] = 'SolusVM 2';
$lang['Solusvm2.description'] = 'Provisions virtual servers on a SolusVM 2 master.';
$lang['Solusvm2.module_row'] = 'Server';
$lang['Solusvm2.module_row_plural'] = 'Servers';
$lang['Solusvm2.module_group'] = 'Server Group';
$lang['Solusvm2.order_options.first'] = 'First non-full server';
$lang['Solusvm2.please_select'] = '-- Please Select --';
$lang['Solusvm2.please_select_none'] = '-- None --';

// Manage
$lang['Solusvm2.manage.module_rows_title'] = 'Servers';
$lang['Solusvm2.manage.module_rows_heading.server_label'] = 'Server Label';
$lang['Solusvm2.manage.module_rows_heading.host'] = 'Host';
$lang['Solusvm2.manage.module_rows_heading.options'] = 'Options';
$lang['Solusvm2.manage.module_rows.edit'] = 'Edit';
$lang['Solusvm2.manage.module_rows.delete'] = 'Delete';
$lang['Solusvm2.manage.module_rows.confirm_delete'] = 'Are you sure you want to delete this server?';
$lang['Solusvm2.manage.module_rows_no_results'] = 'There are no servers.';
$lang['Solusvm2.manage.module_groups_title'] = 'Server Groups';
$lang['Solusvm2.manage.module_groups_heading.name'] = 'Group Name';
$lang['Solusvm2.manage.module_groups_heading.servers'] = 'Servers';
$lang['Solusvm2.manage.module_groups_heading.options'] = 'Options';
$lang['Solusvm2.manage.module_groups.edit'] = 'Edit';
$lang['Solusvm2.manage.module_groups.delete'] = 'Delete';
$lang['Solusvm2.manage.module_groups.confirm_delete'] = 'Are you sure you want to delete this server group?';
$lang['Solusvm2.manage.module_groups_no_results'] = 'There are no server groups.';
$lang['Solusvm2.add_module_row'] = 'Add Server';
$lang['Solusvm2.add_module_group'] = 'Add Server Group';
$lang['Solusvm2.back_to_manage'] = 'Back to Manage';

// Row meta
$lang['Solusvm2.add_row.box_title'] = 'Add SolusVM 2 Server';
$lang['Solusvm2.add_row.basic_title'] = 'Basic Settings';
$lang['Solusvm2.add_row.add_btn'] = 'Add Server';
$lang['Solusvm2.edit_row.box_title'] = 'Edit SolusVM 2 Server';
$lang['Solusvm2.edit_row.basic_title'] = 'Basic Settings';
$lang['Solusvm2.edit_row.edit_btn'] = 'Edit Server';
$lang['Solusvm2.row_meta.server_name'] = 'Server Label';
$lang['Solusvm2.row_meta.host'] = 'Host';
$lang['Solusvm2.row_meta.host_placeholder'] = 'e.g. solusvm2.example.com';
$lang['Solusvm2.row_meta.api_token'] = 'API Token';
$lang['Solusvm2.row_meta.api_token_note'] = 'Generate the token in SolusVM 2 under Access > API Tokens. The token user must have the Admin role.';

// Package fields
$lang['Solusvm2.package_fields.plan'] = 'Plan';
$lang['Solusvm2.package_fields.tooltip.plan'] = 'The SolusVM 2 plan (tariff) to assign to new servers.';
$lang['Solusvm2.package_fields.location'] = 'Location';
$lang['Solusvm2.package_fields.set_os'] = 'Operating System';
$lang['Solusvm2.package_fields.admin_set_os'] = 'Set in package';
$lang['Solusvm2.package_fields.client_set_os'] = 'Client chooses';
$lang['Solusvm2.package_fields.os'] = 'Operating System';
$lang['Solusvm2.package_fields.application'] = 'Application';
$lang['Solusvm2.package_fields.tooltip.application'] = 'Install an application instead of a plain OS. Overrides the OS selection.';
$lang['Solusvm2.package_fields.set_application'] = 'Applications';
$lang['Solusvm2.package_fields.no_set_application'] = 'Do not use applications';
$lang['Solusvm2.package_fields.admin_set_application'] = 'Set in package';
$lang['Solusvm2.package_fields.client_set_application'] = 'Client chooses';
$lang['Solusvm2.package_fields.user_data'] = 'User Data (cloud-init)';
$lang['Solusvm2.package_fields.backup_enabled'] = 'Enable Backups';
$lang['Solusvm2.package_fields.snapshot_enabled'] = 'Enable Snapshots';
$lang['Solusvm2.package_fields.role'] = 'User Role';
$lang['Solusvm2.package_fields.limit_group'] = 'Limit Group';
$lang['Solusvm2.package_fields.ssh_key'] = 'SSH Key';

// Service fields
$lang['Solusvm2.service_fields.hostname'] = 'Hostname';
$lang['Solusvm2.service_fields.tooltip.hostname'] = 'The hostname of the server, e.g. server.example.com';
$lang['Solusvm2.service_fields.os'] = 'Operating System';
$lang['Solusvm2.service_fields.application'] = 'Application';
$lang['Solusvm2.service_fields.server_id'] = 'SolusVM 2 Server ID';
$lang['Solusvm2.service_fields.tooltip.server_id'] = 'Optional: the ID of an existing SolusVM 2 server to attach to this service. Leave empty to create a new server.';

// Service info
$lang['Solusvm2.service_info.solusvm2_main_ip_address'] = 'Main IP Address';

// Errors
$lang['Solusvm2.!error.curl_required'] = 'The cURL extension is required for this module.';
$lang['Solusvm2.!error.api.internal'] = 'An internal error occurred, or the server did not respond to the request.';
$lang['Solusvm2.!error.server_name.empty'] = 'Please enter a server label.';
$lang['Solusvm2.!error.host.format'] = 'The host name appears to be invalid.';
$lang['Solusvm2.!error.api_token.empty'] = 'Please enter an API token.';
$lang['Solusvm2.!error.meta[plan].empty'] = 'Please select a plan.';
$lang['Solusvm2.!error.meta[set_os].format'] = 'Please select who sets the operating system.';
$lang['Solusvm2.!error.meta[set_application].format'] = 'Please select how the application is set.';
$lang['Solusvm2.!error.meta[os].empty'] = 'Please select an operating system.';
$lang['Solusvm2.!error.solusvm2_hostname.format'] = 'Please enter a valid hostname (e.g. host.domain.com).';
$lang['Solusvm2.!error.solusvm2_server_id.format'] = 'The server ID must be a number.';
$lang['Solusvm2.!error.solusvm2_os.valid'] = 'Please select a valid operating system or application.';
$lang['Solusvm2.!error.solusvm2_application.valid'] = 'Please select a valid application.';
$lang['Solusvm2.!error.solusvm2_confirm.valid'] = 'Please confirm the reinstallation.';

// Success
$lang['Solusvm2.!success.boot'] = 'The server has been started.';
$lang['Solusvm2.!success.shutdown'] = 'The server is shutting down.';
$lang['Solusvm2.!success.poweroff'] = 'The server has been powered off.';
$lang['Solusvm2.!success.reboot'] = 'The server is rebooting.';
$lang['Solusvm2.!success.hostname'] = 'The hostname has been updated.';
$lang['Solusvm2.!success.password'] = 'The root password has been reset.';
$lang['Solusvm2.!success.reinstall'] = 'The server is being reinstalled.';

// Actions
$lang['Solusvm2.!actions.boot'] = 'Boot';
$lang['Solusvm2.!actions.reboot'] = 'Reboot';
$lang['Solusvm2.!actions.shutdown'] = 'Shutdown';
$lang['Solusvm2.!actions.poweroff'] = 'Power Off';
$lang['Solusvm2.!actions.reinstall'] = 'Reinstall';
$lang['Solusvm2.!actions.hostname'] = 'Change Hostname';
$lang['Solusvm2.!actions.password'] = 'Root Password';
$lang['Solusvm2.!bytes.value'] = '%1$s %2$s';

// Tab titles
$lang['Solusvm2.tab_actions'] = 'Actions';
$lang['Solusvm2.tab_stats'] = 'Stats';
$lang['Solusvm2.tab_console'] = 'Console';
$lang['Solusvm2.tab_client_actions'] = 'Actions';
$lang['Solusvm2.tab_client_stats'] = 'Stats';
$lang['Solusvm2.tab_client_console'] = 'Console';

// Admin Actions tab
$lang['Solusvm2.tab_actions.heading_actions'] = 'Actions';
$lang['Solusvm2.tab_actions.server_status'] = 'Server Status:';
$lang['Solusvm2.tab_actions.status_running'] = 'Online';
$lang['Solusvm2.tab_actions.status_stopped'] = 'Offline';
$lang['Solusvm2.tab_actions.status_processing'] = 'Processing';
$lang['Solusvm2.tab_actions.status_installing'] = 'Installing';
$lang['Solusvm2.tab_actions.status_unknown'] = 'Unknown';
$lang['Solusvm2.tab_actions.status_suspended'] = 'Suspended';
$lang['Solusvm2.tab_actions.heading_reinstall'] = 'Reinstall';
$lang['Solusvm2.tab_actions.tab_os'] = 'Operating System';
$lang['Solusvm2.tab_actions.tab_application'] = 'Application';
$lang['Solusvm2.tab_actions.field_application'] = 'Application';
$lang['Solusvm2.tab_actions.field_os'] = 'Operating System';
$lang['Solusvm2.tab_actions.field_confirm'] = 'I confirm that I want to reinstall this server. All data will be lost.';
$lang['Solusvm2.tab_actions.field_reinstall_submit'] = 'Reinstall';
$lang['Solusvm2.tab_actions.heading_hostname'] = 'Change Hostname';
$lang['Solusvm2.tab_actions.field_hostname'] = 'Hostname';
$lang['Solusvm2.tab_actions.field_hostname_submit'] = 'Change Hostname';
$lang['Solusvm2.tab_actions.heading_password'] = 'Root Password';
$lang['Solusvm2.tab_actions.field_current_password'] = 'Current Root Password';
$lang['Solusvm2.tab_actions.text_password_reset'] = 'Resetting generates a new random root password on the server.';
$lang['Solusvm2.tab_actions.field_password_submit'] = 'Reset Password';

// Client Actions tab
$lang['Solusvm2.tab_client_actions.heading_server_status'] = 'Server Status';
$lang['Solusvm2.tab_client_actions.heading_actions'] = 'Actions';
$lang['Solusvm2.tab_client_actions.status_running'] = 'Online';
$lang['Solusvm2.tab_client_actions.status_stopped'] = 'Offline';
$lang['Solusvm2.tab_client_actions.status_processing'] = 'Processing';
$lang['Solusvm2.tab_client_actions.status_installing'] = 'Installing';
$lang['Solusvm2.tab_client_actions.status_unknown'] = 'Unknown';
$lang['Solusvm2.tab_client_actions.status_suspended'] = 'Suspended';
$lang['Solusvm2.tab_client_actions.heading_reinstall'] = 'Reinstall';
$lang['Solusvm2.tab_client_actions.tab_os'] = 'Operating System';
$lang['Solusvm2.tab_client_actions.tab_application'] = 'Application';
$lang['Solusvm2.tab_client_actions.field_application'] = 'Application';
$lang['Solusvm2.tab_client_actions.field_os'] = 'Operating System';
$lang['Solusvm2.tab_client_actions.field_confirm'] = 'I confirm that I want to reinstall this server. All data will be lost.';
$lang['Solusvm2.tab_client_actions.field_reinstall_submit'] = 'Reinstall';
$lang['Solusvm2.tab_client_actions.heading_hostname'] = 'Change Hostname';
$lang['Solusvm2.tab_client_actions.field_hostname'] = 'Hostname';
$lang['Solusvm2.tab_client_actions.field_hostname_submit'] = 'Change Hostname';
$lang['Solusvm2.tab_client_actions.heading_password'] = 'Root Password';
$lang['Solusvm2.tab_client_actions.field_current_password'] = 'Current Root Password';
$lang['Solusvm2.tab_client_actions.text_password_reset'] = 'Resetting generates a new random root password on the server.';
$lang['Solusvm2.tab_client_actions.field_password_submit'] = 'Reset Password';

// Admin Stats tab
$lang['Solusvm2.tab_stats.heading_stats'] = 'Server Statistics';
$lang['Solusvm2.tab_stats.heading_server_status'] = 'Status';
$lang['Solusvm2.tab_stats.heading_ips'] = 'IP Addresses';
$lang['Solusvm2.tab_stats.heading_plan'] = 'Plan';
$lang['Solusvm2.tab_stats.heading_vcpu'] = 'vCPU';
$lang['Solusvm2.tab_stats.heading_memory'] = 'Memory';
$lang['Solusvm2.tab_stats.heading_disk'] = 'Disk';
$lang['Solusvm2.tab_stats.heading_traffic'] = 'Traffic (used / limit)';
$lang['Solusvm2.tab_stats.heading_traffic_in'] = 'Traffic In';
$lang['Solusvm2.tab_stats.heading_traffic_out'] = 'Traffic Out';
$lang['Solusvm2.tab_stats.unit_gb'] = 'GB';
$lang['Solusvm2.tab_stats.status_running'] = 'Online';
$lang['Solusvm2.tab_stats.status_stopped'] = 'Offline';
$lang['Solusvm2.tab_stats.status_processing'] = 'Processing';
$lang['Solusvm2.tab_stats.status_installing'] = 'Installing';
$lang['Solusvm2.tab_stats.status_unknown'] = 'Unknown';
$lang['Solusvm2.tab_stats.status_suspended'] = 'Suspended';

// Client Stats tab
$lang['Solusvm2.tab_client_stats.heading_stats'] = 'Server Statistics';
$lang['Solusvm2.tab_client_stats.heading_server_status'] = 'Status';
$lang['Solusvm2.tab_client_stats.heading_ips'] = 'IP Addresses';
$lang['Solusvm2.tab_client_stats.heading_plan'] = 'Plan';
$lang['Solusvm2.tab_client_stats.heading_vcpu'] = 'vCPU';
$lang['Solusvm2.tab_client_stats.heading_memory'] = 'Memory';
$lang['Solusvm2.tab_client_stats.heading_disk'] = 'Disk';
$lang['Solusvm2.tab_client_stats.heading_traffic'] = 'Traffic (used / limit)';
$lang['Solusvm2.tab_client_stats.heading_traffic_in'] = 'Traffic In';
$lang['Solusvm2.tab_client_stats.heading_traffic_out'] = 'Traffic Out';
$lang['Solusvm2.tab_client_stats.unit_gb'] = 'GB';
$lang['Solusvm2.tab_client_stats.status_running'] = 'Online';
$lang['Solusvm2.tab_client_stats.status_stopped'] = 'Offline';
$lang['Solusvm2.tab_client_stats.status_processing'] = 'Processing';
$lang['Solusvm2.tab_client_stats.status_installing'] = 'Installing';
$lang['Solusvm2.tab_client_stats.status_unknown'] = 'Unknown';
$lang['Solusvm2.tab_client_stats.status_suspended'] = 'Suspended';

// Admin Console tab
$lang['Solusvm2.tab_console.heading_console'] = 'Console';
$lang['Solusvm2.tab_console.vnc_password'] = 'VNC Password';
$lang['Solusvm2.tab_console.send_ctrlaltdel'] = 'Send CtrlAltDel';
$lang['Solusvm2.tab_console.text_console_unavailable'] = 'The console is currently unavailable.';

// Client Console tab
$lang['Solusvm2.tab_client_console.heading_console'] = 'Console';
$lang['Solusvm2.tab_client_console.vnc_password'] = 'VNC Password';
$lang['Solusvm2.tab_client_console.send_ctrlaltdel'] = 'Send CtrlAltDel';
$lang['Solusvm2.tab_client_console.text_console_unavailable'] = 'The console is currently unavailable.';

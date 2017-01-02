<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2007-2017 The Cacti Group                                 |
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

function plugin_routerconfigs_version () {
	global $config;
	$info = parse_ini_file($config['base_path'] . '/plugins/routerconfigs/INFO', true);
	return $info['info'];
}

function plugin_routerconfigs_install () {
	api_plugin_register_hook('routerconfigs', 'config_arrays',        'routerconfigs_config_arrays',        'setup.php');
	api_plugin_register_hook('routerconfigs', 'draw_navigation_text', 'routerconfigs_draw_navigation_text', 'setup.php');
	api_plugin_register_hook('routerconfigs', 'config_settings',      'routerconfigs_config_settings',      'setup.php');
	api_plugin_register_hook('routerconfigs', 'poller_bottom',        'routerconfigs_poller_bottom',        'setup.php');
	api_plugin_register_hook('routerconfigs', 'page_head',            'routerconfigs_page_head',            'setup.php');

	api_plugin_register_realm('routerconfigs', 'router-devices.php,router-accounts.php,router-backups.php,router-compare.php', 'Plugin -> Router Configs', 1);

	routerconfigs_setup_table_new();
}

function plugin_routerconfigs_uninstall () {
	/* Do any extra Uninstall stuff here */
}

function plugin_routerconfigs_upgrade() {
	/* Here we will upgrade to the newest version */
	routerconfigs_check_upgrade();

	return false;
}

function routerconfigs_check_upgrade() {
	global $config, $database_default;

	include_once($config['library_path'] . '/database.php');
	include_once($config['library_path'] . '/functions.php');

	// Let's only run this check if we are on a page that actually needs the data
	$files = array('plugins.php');
	if (isset($_SERVER['PHP_SELF']) && !in_array(basename($_SERVER['PHP_SELF']), $files)) {
		return;
	}

	$current = plugin_routerconfigs_version();
	$current = $current['version'];
	$old     = db_fetch_cell("SELECT version FROM plugin_config WHERE directory='routerconfigs'");

	if ($current != $old) {
		/* update realms for old versions */
		if ($old < '0.2') {
			api_plugin_register_realm('routerconfigs', 'router-devices.php,router-accounts.php,router-backups.php,router-compare.php', 'Plugin -> Router Configs', 1);

			/* get the realm id's and change from old to new */
			$user  = db_fetch_cell("SELECT id FROM plugin_realms WHERE file='router-devices.php'");
			if ($user >  0) {
				$users = db_fetch_assoc('SELECT user_id FROM user_auth_realm WHERE realm_id=86');

				if (sizeof($users)) {
					foreach($users as $u) {
						db_execute("INSERT INTO user_auth_realm
							(realm_id, user_id) VALUES ($user, " . $u['user_id'] . ')
							ON DUPLICATE KEY UPDATE realm_id=VALUES(realm_id)');

						db_execute('DELETE FROM user_auth_realm
							WHERE user_id=' . $u['user_id'] . "
							AND realm_id=$user");
					}
				}
			}
		}

		db_execute("UPDATE plugin_config 
			SET version='$current' 
			WHERE directory='routerconfigs'");
	}
}

function routerconfigs_check_dependencies() {
	global $plugins, $config;
	return true;
}

function routerconfigs_setup_table_new() {
	$data = array();
	$data['columns'][] = array('name' => 'id', 'type' => 'int(11)', 'NULL' => false, 'auto_increment' => true);
    $data['columns'][] = array('name' => 'name', 'type' => 'varchar(64)', 'NULL' => true);
    $data['columns'][] = array('name' => 'username', 'type' => 'varchar(64)', 'NULL' => true);
    $data['columns'][] = array('name' => 'password', 'type' => 'varchar(256)', 'NULL' => true);
    $data['columns'][] = array('name' => 'enablepw', 'type' => 'varchar(256)', 'NULL' => true);
    $data['primary'] = 'id';
    $data['type'] = 'InnoDB';
    $data['comment'] = 'Router Config Accounts';
    api_plugin_db_table_create ('routerconfigs', 'plugin_routerconfigs_accounts', $data);

	$data = array();
	$data['columns'][] = array('name' => 'id', 'type' => 'int(11)', 'NULL' => false, 'auto_increment' => true);
    $data['columns'][] = array('name' => 'btype', 'type' => 'int(18)', 'NULL' => true);
    $data['columns'][] = array('name' => 'device', 'type' => 'int(11)', 'NULL' => true);
    $data['columns'][] = array('name' => 'directory', 'type' => 'varchar(255)', 'NULL' => true);
    $data['columns'][] = array('name' => 'filename', 'type' => 'varchar(255)', 'NULL' => true);
    $data['columns'][] = array('name' => 'config', 'type' => 'longblob', 'NULL' => true);
    $data['columns'][] = array('name' => 'lastchange', 'type' => 'int(24)', 'NULL' => true);
    $data['columns'][] = array('name' => 'username', 'type' => 'varchar(64)', 'NULL' => true);
    $data['primary'] = 'id';
    $data['keys'][] = array('name' => 'btype', 'columns' => 'btype');
    $data['keys'][] = array('name' => 'device', 'columns' => 'device');
    $data['keys'][] = array('name' => 'directory', 'columns' => 'directory');
    $data['keys'][] = array('name' => 'lastchange', 'columns' => 'lastchange');
    $data['type'] = 'InnoDB';
    $data['comment'] = 'Router Config Backups';
    api_plugin_db_table_create ('routerconfigs', 'plugin_routerconfigs_backups', $data);

	$data = array();
	$data['columns'][] = array('name' => 'id', 'type' => 'int(11)', 'NULL' => false, 'auto_increment' => true);
    $data['columns'][] = array('name' => 'enabled', 'type' => 'varchar(2)', 'NULL' => true);
    $data['columns'][] = array('name' => 'ipaddress', 'type' => 'varchar(128)', 'NULL' => true);
    $data['columns'][] = array('name' => 'hostname', 'type' => 'varchar(255)', 'NULL' => true);
    $data['columns'][] = array('name' => 'directory', 'type' => 'varchar(255)', 'NULL' => true);
    $data['columns'][] = array('name' => 'account', 'type' => 'int(11)', 'NULL' => true);
    $data['columns'][] = array('name' => 'lastchange', 'type' => 'int(24)', 'NULL' => true);
    $data['columns'][] = array('name' => 'device', 'type' => 'int(11)', 'NULL' => true);
    $data['columns'][] = array('name' => 'username', 'type' => 'varchar(64)', 'NULL' => true);
    $data['columns'][] = array('name' => 'schedule', 'type' => 'int(6)', 'NULL' => true);
    $data['columns'][] = array('name' => 'lasterror', 'type' => 'varchar(255)', 'NULL' => true);
    $data['columns'][] = array('name' => 'lastbackup', 'type' => 'int(18)', 'NULL' => true);
    $data['columns'][] = array('name' => 'lastattempt', 'type' => 'int(18)', 'NULL' => true);
    $data['columns'][] = array('name' => 'devicetype', 'type' => 'int(11)', 'NULL' => true);
    $data['columns'][] = array('name' => 'debug', 'type' => 'blob', 'NULL' => true);
    $data['primary'] = 'id';
    $data['keys'][] = array('name' => 'enabled', 'columns' => 'enabled');
    $data['keys'][] = array('name' => 'schedule', 'columns' => 'schedule');
    $data['keys'][] = array('name' => 'ipaddress', 'columns' => 'ipaddress');
    $data['keys'][] = array('name' => 'account', 'columns' => 'account');
    $data['keys'][] = array('name' => 'lastbackup', 'columns' => 'lastbackup');
    $data['keys'][] = array('name' => 'lastattempt', 'columns' => 'lastattempt');
    $data['keys'][] = array('name' => 'devicetype', 'columns' => 'devicetype');
    $data['type'] = 'InnoDB';
    $data['comment'] = 'Router Config Devices';
    api_plugin_db_table_create ('routerconfigs', 'plugin_routerconfigs_devices', $data);

	$data = array();
	$data['columns'][] = array('name' => 'id', 'type' => 'int(11)', 'NULL' => false, 'auto_increment' => true);
    $data['columns'][] = array('name' => 'name', 'type' => 'varchar(64)', 'NULL' => true);
    $data['columns'][] = array('name' => 'username', 'type' => 'varchar(64)', 'NULL' => true);
    $data['columns'][] = array('name' => 'password', 'type' => 'varchar(256)', 'NULL' => true);
    $data['columns'][] = array('name' => 'copytftp', 'type' => 'varchar(64)', 'NULL' => true);
    $data['columns'][] = array('name' => 'version', 'type' => 'varchar(64)', 'NULL' => true);
    $data['columns'][] = array('name' => 'confirm', 'type' => 'varchar(64)', 'NULL' => true);
    $data['columns'][] = array('name' => 'forceconfirm', 'type' => 'int(1)', 'NULL' => true);
    $data['primary'] = 'id';
    $data['type'] = 'InnoDB';
    $data['comment'] = 'Router Config Device Types';
    api_plugin_db_table_create ('routerconfigs', 'plugin_routerconfigs_devicetypes', $data);

	db_execute("REPLACE INTO plugin_routerconfigs_devicetypes 
		(id, name, username, password, copytftp, version, confirm, forceconfirm) 
		VALUES 
		(1, 'Cisco IOS', 'username:', 'password:', 'copy run tftp', 'show version', 'y', 0),
		(2, 'Cisco CatOS', 'username:', 'password:', 'copy config tftp', '', 'y', 1)");
}

function routerconfigs_page_head () {
	global $config;

	if (strpos($_SERVER['PHP_SELF'], 'router-compare.php')) {
		print '<link rel="stylesheet" type="text/css" href="' . $config['url_path'] . "plugins/routerconfigs/diff.css\">\n";
	}
}

function routerconfigs_poller_bottom () {
	global $config;

	$running = read_config_option('plugin_routerconfigs_running');
	if ($running == 1) {
		return;
	}

	/* Check for the polling interval, only valid with the Multipoller patch */
	$poller_interval = read_config_option('poller_interval');
	if (!isset($poller_interval)) {
		$poller_interval = 300;
	}

	$h = date('G', time());
	$s = date('i', time()) * 60;

	if ($h == 0 && $s < $poller_interval) {
		$command_string = trim(read_config_option('path_php_binary'));

		if (trim($command_string) == '') {
			$command_string = 'php';
		}

		$extra_args = ' -q ' . $config['base_path'] . '/plugins/routerconfigs/router-download.php';

		exec_background($command_string, $extra_args);
	} else if ($s < $poller_interval){
		$t = time();

		$devices = db_fetch_assoc("SELECT * 
			FROM plugin_routerconfigs_devices 
			WHERE enabled = 'on' 
			AND ($t - (schedule * 86400)) - 3600 > lastbackup 
			AND $t - lastattempt > 1800", false);

		if (!empty($devices)) {
			$command_string = trim(read_config_option('path_php_binary'));

			if (trim($command_string) == '') {
				$command_string = 'php';
			}

			$extra_args = ' -q ' . $config['base_path'] . '/plugins/routerconfigs/router-redownload.php';

			exec_background($command_string, $extra_args);
		}
	}
}

function routerconfigs_config_settings () {
	global $tabs, $settings, $config;

	$temp = array(
		'routerconfigs_header' => array(
			'friendly_name' => 'Router Configs',
			'method' => 'spacer',
		),
		'routerconfigs_tftpserver' => array(
			'friendly_name' => 'TFTP Server IP',
			'description' => 'Must be an IP pointing to your Cacti server.',
			'method' => 'textbox',
			'max_length' => 255,
			'default' => gethostbyname(gethostname())
		),
		'routerconfigs_backup_path' => array(
			'friendly_name' => 'Backup Directory Path',
			'description' => 'The path to where your Configs will be backed up, it must be the path that the local TFTP Server writes to.',
			'method' => 'dirpath',
			'max_length' => 255,
			'size' => '50',
			'default' => $config['base_path'] . '/backups/'
		),
		'routerconfigs_email' => array(
			'friendly_name' => 'Email Address',
			'description' => 'Email address to send the nightly backup email to.  Commna deliminate any extra email addresses.',
			'method' => 'textbox',
			'size' => 40,
			'max_length' => 255,
			'default' => ''
		),
		'routerconfigs_from' => array(
			'friendly_name' => 'From Address',
			'description' => 'Email address the nightly backup will be sent from.',
			'method' => 'textbox',
			'size' => 40,
			'max_length' => 255,
			'default' => ''
		),
		'routerconfigs_retention' => array(
			'friendly_name' => 'Retention Period',
			'description' => 'The number of days to retain old backups.',
			'method' => 'drop_array',
			'default' => '30',
			'array' => array(
				'30'  => '1 Month',
				'60'  => '2 Months',
				'90'  => '3 Months',
				'120' => '4 Months',
				'180' => '6 Months',
				'365' => '1 Year'
			)
		)
	);

	$tabs['misc'] = 'Misc';

	if (isset($settings['misc'])) {
		$settings['misc'] = array_merge($settings['misc'], $temp);
	} else {
		$settings['misc'] = $temp;
	}
}

function routerconfigs_config_arrays () {
	global $menu;

	$menu[__('Utilities')]['plugins/routerconfigs/router-devices.php'] = __('Router Configs');
}

function routerconfigs_draw_navigation_text ($nav) {
	$nav['router-devices.php:']           = array('title' => 'Router Devices', 'mapping' => 'index.php:',                      'url' => 'router-devices.php', 'level' => '1');
	$nav['router-devices.php:edit']       = array('title' => '(edit)',         'mapping' => 'index.php:router-devices.php:',   'url' => 'router-devices.php', 'level' => '2');
	$nav['router-devices.php:actions']    = array('title' => '(actions)',      'mapping' => 'index.php:router-devices.php:',   'url' => 'router-devices.php', 'level' => '2');
	$nav['router-devices.php:viewconfig'] = array('title' => 'View Config',    'mapping' => 'index.php:router-devices.php:',   'url' => 'router-devices.php', 'level' => '2');
	$nav['router-devices.php:viewdebug']  = array('title' => 'View Debug',     'mapping' => 'index.php:router-devices.php:',   'url' => 'router-devices.php', 'level' => '2');

	$nav['router-backups.php:']           = array('title' => 'Router Backups', 'mapping' => 'index.php:',                      'url' => 'router-backups.php', 'level' => '1');
	$nav['router-backups.php:edit']       = array('title' => '(edit)',         'mapping' => 'index.php:router-backups.php:',   'url' => 'router-backups.php', 'level' => '2');
	$nav['router-backups.php:actions']    = array('title' => '(actions)',      'mapping' => 'index.php:router-backups.php:',   'url' => 'router-backups.php', 'level' => '2');
	$nav['router-backups.php:viewconfig'] = array('title' => 'View Config',    'mapping' => 'index.php:router-backups.php:',   'url' => 'router-backups.php', 'level' => '2');

	$nav['router-accounts.php:']          = array('title' => 'Router Accounts', 'mapping' => 'index.php:',                     'url' => 'router-accounts.php', 'level' => '1');
	$nav['router-accounts.php:edit']      = array('title' => '(edit)',          'mapping' => 'index.php:router-accounts.php:', 'url' => 'router-accounts.php', 'level' => '2');
	$nav['router-accounts.php:actions']   = array('title' => '(actions)',       'mapping' => 'index.php:router-accounts.php:', 'url' => 'router-accounts.php', 'level' => '2');

	$nav['router-compare.php:']           = array('title' => 'Router Compare',  'mapping' => 'index.php:',                     'url' => 'router-compare.php', 'level' => '1');

	return $nav;
}


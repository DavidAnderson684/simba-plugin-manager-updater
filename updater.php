<?php

if (!defined('ABSPATH')) die('No direct access.');

$possible_locations = array(
	__DIR__.'/class-udm-updater.php',
	__DIR__.'/vendor/davidanderson684/simba-plugin-manager-updater/class-udm-updater.php'
);

if (!class_exists('Updraft_Manager_Updater_1_2')) {
	foreach ($possible_locations as $location) {
		if (file_exists($location)) {
			require_once($location);
			break;
		}
	}
}

if(isset($simba_update_url) &&
	isset($simba_update_user_id) && 
	isset($simba_update_plugin_name) 
){ 
	new Updraft_Manager_Updater_1_2($simba_update_url, $simba_update_user_id, $simba_update_plugin_name); 
}else{
	add_action( 'admin_notices', 'simba_plugin_manager_updater_config_failed' );
	function simba_plugin_manager_updater_config_failed() {	
	?>
		<div class="notice notice-error is-dismissible">
			<p><?php _e( 'Simba update manager variables not set', 'updraftcentral-premium' ); ?></p>
		</div>
	<?php
	}
}
// $x->updater->debug = true;

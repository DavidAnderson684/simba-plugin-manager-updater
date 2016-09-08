<?php

if (!defined('ABSPATH')) die('No direct access.');

/* This file gives an example of how to use the updater class.
You will want to copy this file into your project, and adapt the parameters to suit. */

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

new Updraft_Manager_Updater_1_2('https://example.com/your/WP/mothership/siteurl', 1, 'plugin-dir/plugin-file.php');

// $x->updater->debug = true;

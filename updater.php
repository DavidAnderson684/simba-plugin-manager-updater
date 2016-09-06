<?php

if (!defined('ABSPATH')) die('No direct access.');

if (!class_exists('Updraft_Manager_Updater_1_1')) require_once(dirname(__FILE__).'/class-udm-updater.php');

new Updraft_Manager_Updater_1_1('https://example.com/your/WP/mothership/siteurl', 1, 'plugin-dir/plugin-file.php');

#$x->updater->debug = true;

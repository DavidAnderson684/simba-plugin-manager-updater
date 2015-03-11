<?php

if (!defined('ABSPATH')) die('No direct access.');

if (!class_exists('Updraft_Manager_Updater_1_0')) require_once(dirname(__FILE__).'/class-udm-updater.php');

$openinghours_updater = new Updraft_Manager_Updater_1_0('https://www.simbahosting.co.uk/s3', 1, 'woocommerce-opening-hours/opening-hours.php');

#$openinghours_updater->updater->debug = true;

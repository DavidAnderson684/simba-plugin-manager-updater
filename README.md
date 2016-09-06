# Simba Plugin Manager - Updater Class

This class is an updates checker and UI for WordPress plugins that are hosted using the Simba Plugin Manager plugin.

It is intended for plugins that require the supply of access credentials (a customer email address and password) to gain access to updates. For plugins that are available to everyone, you should instead use Yahnis Elsts' plugin update class without any modifications: https://github.com/YahnisElsts/plugin-update-checker

## How to use this class

### 1. Add Yahnis Elsts' plugin update class

Check out a copy of Yahnis' Elsts' plugin update class (https://github.com/YahnisElsts/plugin-update-checker). You can do this in two ways:

#### a. Via composer

Whilst in this component's directory (where the composer.json file is), run "composer install". This will then create a sub-directory "vendor", with the plugin update class in vendor/yahnis-elsts/plugin-update-checker. It will be looked for there. You can keep it up to date with "composer update".

#### b. Or, manually

If you prefer to download manually, then download from https://github.com/YahnisElsts/plugin-update-checker and you can place it in a subdirectory "puc", relative to where this class is housed, such that the plugin updater class is in puc/plugin-update-checker.php. It will be looked for there if the composer directory does not exist.

### 2. Include the class in your plugin

Your plugin's constructor is a good place to do this.

`include_once('path/to/your/plugin/updater/updater.php');`

### 3. Edit the updater/updater.php to point to where your plugins are hosted

updater/updater.php is a very short file. Find this line ...

`new Updraft_Manager_Updater_1_0('https://example.com/your/WP/mothership/homeurl', 1, 'plugin-dir/plugin-file.php');`

... and:

1. Change it to point to the home URL of your WordPress site that is hosting the plugin (i.e. that is running the Simba Plugin Manager)

2. Change the 1 to be the user ID of the user on your WordPress site who is hosting the plugin

3. Change the plugin path to be the path (relative to the WP plugin directory, by default wp-content/plugins) to your plugin's main file.

More information to come.

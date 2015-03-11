# Simba Plugin Manager - Updater Class

This class is an updates checker and UI for WordPress plugins that are hosted using the Simba Plugin Manager plugin.

## Using

### 1. Include the class in your plugin

Your plugin's constructor is a good place to do this.

`include_once('path/to/your/plugin/updater/updater.php');`

### 2. Edit the updater/updater.php to point to where your plugins are hosted

updater/updater.php is a very short file. Find this line ...

`new Updraft_Manager_Updater_1_0('https://example.com/your/WP/mothership/homeurl', 1, 'plugin-dir/plugin-file.php');`

... and:

1. Change it to point to the home URL of your WordPress site that is hosting the plugin (i.e. that is running the Simba Plugin Manager)

2. Change the 1 to be the user ID of the user on your WordPress site who is hosting the plugin

3. Change the plugin path to be the path (relative to the WP plugin directory, by default wp-content/plugins) to your plugin's main file.

More information to come.

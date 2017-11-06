This project follows semantic versioning principles.

# Upgrading from 1.0 to 1.1.

The 1.1 series supports installing and updating Yahnis Elsts' PluginUpdateChecker class (which is a dependency) via composer, as well as via the previous method of manually downloading it into a "puc" sub-directory. This introduces a subtle incompatibility with older releases, affecting sites which have two or more plugins installed.

In consequence, it has been necessary to bump the class version major/minor version numbers from 1.0 to 1.1. There are no API changes, but it is necessary just to alter the name of the class being accessed in your updater.php file (the file that loads the class) from Updraft_Manager_Updater_1_0 to Updraft_Manager_Updater_1_1.

# Upgrading from 1.1 to 1.2.

The 1.2 series supports installing the updater and its dependency via composer. This again means a potential change in directory structure.

In consequence, it has been necessary to bump the class version major/minor version numbers from 1.1 to 1.2. There are no API changes, but it is necessary just to alter the name of the class being accessed in your updater.php file (the file that loads the class) to Updraft_Manager_Updater_1_2.

# Upgrading from 1.2 to 1.3.

There are no API-breaking changes. You can use your code unmodified (beyond altering the class name that you instantiate from Updraft_Manager_Updater_1_2 to Updraft_Manager_Updater_1_3).

New feature (on by default): The 1.3 series allows the user to opt-in to automatic updates of the plugin, using a checkbox on the "Plugins" page. If you do not wish your user to have this facility, then call the method set_allow_auto_updates(false) after instantiating the Updraft_Manager_Updater_1_3 object.

# Upgrading from 1.3 to 1.4.

There are no API-breaking changes. You can use your code unmodified (beyond altering the class name that you instantiate from Updraft_Manager_Updater_1_3 to Updraft_Manager_Updater_1_4).

New feature: The udmupdater_wp_api_options filter has been added to allow easy modification of parameters to wp_remote_* calls. Note that you may also wish to use the puc_request_info_options-(slug) filter that the base updates checker class uses if you need to catch all calls.

# Upgrading from 1.4 to 1.5.

There are no API-breaking changes. You can use your code unmodified (beyond altering the class name that you instantiate from Updraft_Manager_Updater_1_4 to Updraft_Manager_Updater_1_5). The version bump is necessitated by a version bump in a dependency.

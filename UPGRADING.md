This project follows semantic versioning principles.

# Upgrading from 1.0 to 1.1.

The 1.1 series supports installing and updating Yahnis Elsts' PluginUpdateChecker class (which is a dependency) via composer, as well as via the previous method of manually downloading it into a "puc" sub-directory. This introduces a subtle incompatibility with older releases, affecting sites which have two or more plugins installed.

In consequence, it has been necessary to bump the class version major/minor version numbers from 1.0 to 1.1. There are no API changes, but it is necessary just to alter the name of the class being accessed in your updater.php file (the file that loads the class) from Updraft_Manager_Updater_1_0 to Updraft_Manager_Updater_1_1.

# Upgrading from 1.1 to 1.2.

The 1.2 series supports installing the updater and its dependency via composer. This again means a potential change in directory structure.

In consequence, it has been necessary to bump the class version major/minor version numbers from 1.1 to 1.2. There are no API changes, but it is necessary just to alter the name of the class being accessed in your updater.php file (the file that loads the class) to Updraft_Manager_Updater_1_2.

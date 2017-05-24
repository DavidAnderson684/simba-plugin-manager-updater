<?php

/*
Licence: MIT / GPLv2+
*/

if (!class_exists('Updraft_Manager_Updater_1_4')):
class Updraft_Manager_Updater_1_4 {

	public $version = '1.4.0';

	public $relative_plugin_file;
	public $slug;
	public $url;
	public $debug;

	public $user_addons;
	public $available_addons;
	public $remote_addons;

	public $muid;

	public $plug_updatechecker;

	private $allow_auto_updates = true;
	
	private $option_name;
	private $admin_notices = array();

	public function __construct($mothership, $muid = 1, $relative_plugin_file, $interval_hours = 24, $auto_backoff = true, $debug = false) {

		$this->relative_plugin_file = $relative_plugin_file;
		$this->slug = dirname($relative_plugin_file);
		$this->url = trailingslashit($mothership).'?muid='.$muid;
		$this->muid = $muid;
		$this->debug = $debug;
		$this->ourdir = dirname(__FILE__);

		# This needs to exact match PluginUpdateChecker's view
		$this->plugin_file = trailingslashit(WP_PLUGIN_DIR).$relative_plugin_file;

		if (!file_exists($this->plugin_file)) throw new Exception("Plugin file not found: ".$this->plugin_file);

		if (!function_exists('get_plugin_data')) require_once(ABSPATH.'wp-admin/includes/plugin.php');

		$this->plugin_data = get_plugin_data($this->plugin_file);

		add_action('wp_ajax_udmupdater_ajax', array($this, 'udmupdater_ajax'));

		# Prevent updates from wordpress.org showing in all circumstances. Run with lower than default priority, to allow later processes to add something.
		add_filter('site_transient_update_plugins', array($this, 'site_transient_update_plugins'), 9);

		// Expiry notices
		add_action(is_multisite() ? 'network_admin_menu' : 'admin_menu', array($this, 'admin_menu'));

		$this->option_name = $this->slug.'_updater_options';

		// Over-ride update mechanism for the plugin
		$puc_dir = file_exists($this->ourdir.'/vendor/yahnis-elsts/plugin-update-checker') ? $this->ourdir.'/vendor/yahnis-elsts/plugin-update-checker' : ( file_exists(dirname(dirname($this->ourdir)).'/yahnis-elsts/plugin-update-checker') ? dirname(dirname($this->ourdir)).'/yahnis-elsts/plugin-update-checker' : $this->ourdir.'/puc');
		
		if (is_readable($puc_dir.'/plugin-update-checker.php') || class_exists('PucFactory')) {

			$options = $this->get_option($this->option_name);
			$email = isset($options['email']) ? $options['email'] : '';
			if ($email) {

				// Load the file even if the PucFactory class is already around, as this may get us a later version / avoid a really old + incompatible one
				if (file_exists($puc_dir.'/plugin-update-checker.php')) include_once($puc_dir.'/plugin-update-checker.php');
				
				if ($auto_backoff) add_filter('puc_check_now-'.$this->slug, array($this, 'puc_check_now'), 10, 3);

				add_filter('puc_retain_fields-'.$this->slug, array($this, 'puc_retain_fields'));
// 				add_filter('puc_request_info_options-'.$this->slug, array($this, 'puc_request_info_options'));

				if (class_exists('PucFactory')) {
					$this->plug_updatechecker = PucFactory::buildUpdateChecker($this->url, WP_PLUGIN_DIR.'/'.$this->relative_plugin_file, $this->slug, $interval_hours);
					$this->plug_updatechecker->addQueryArgFilter(array($this, 'updater_queryargs_plugin'));
					if ($this->debug) $this->plug_updatechecker->debugMode = true;
				}
			}
		}

		add_action("after_plugin_row_$relative_plugin_file", array($this, 'after_plugin_row'), 10, 2 );
		add_action('load-plugins.php', array($this, 'load_plugins_php'));
		add_action('core_upgrade_preamble', array($this, 'core_upgrade_preamble'));
		
		add_filter('auto_update_plugin', array($this, 'auto_update_plugin'), 10, 2);
	}

	public function auto_update_plugin($update, $item) {

		if (!isset($item->slug) || $item->slug != $this->slug || !$this->allow_auto_updates) return $update;
		
		$options = $this->get_option($this->option_name);

		if (is_array($options) && !empty($options['auto_update'])) $update = true;
		
		if ($this->debug) error_log("udm_updater: ".$this->slug." auto update decision: ".$update);
		
		return $update;
	}
	
	public function udmupdater_ajax() {
		if (empty($_REQUEST['nonce']) || empty($_REQUEST['subaction']) || !wp_verify_nonce($_REQUEST['nonce'], 'udmupdater-ajax-nonce')) die('Security check.');

		// Make sure this request is meant for us
		if (empty($_REQUEST['userid']) || empty($_REQUEST['slug']) || $this->muid != $_REQUEST['userid'] || $_REQUEST['slug'] != $this->slug) return;

		if ('connect' == $_REQUEST['subaction'] && current_user_can('update_plugins')) {

			$options = $this->get_option($this->option_name);

			$result = wp_remote_post($this->url.'&udm_action=claimaddon&slug='.urlencode($this->slug).'&e='.urlencode($_POST['email']),
				apply_filters('udmupdater_wp_api_options', array(
					'timeout' => 10,
					'body' => array(
						'e' => $_POST['email'],
						'p' => base64_encode($_POST['password']),
						'sid' => $this->siteid(),
						'sn' => base64_encode(get_bloginfo('name')),
						'su' => base64_encode(home_url()),
						'slug' => $this->slug
					)
				), 'claimaddon')
			);

			if (is_array($result) && isset($result['body'])) {

				$decoded = json_decode($result['body'], true);
				if (empty($decoded)) {
					echo json_encode(array(
						'code' => 'INVALID',
						'data' => $result['body']
					));
				} else {
					echo $result['body'];
					if (isset($decoded['code']) && 'OK' == $decoded['code']) {
						$option = $this->get_option($this->option_name);
						if (!is_array($option)) $option = array();
						$option['email'] = $_POST['email'];
						$this->update_option($this->option_name, $option);
					}
				}

			} elseif (is_wp_error($result)) {
				echo __('Errors occurred:','udmupdater').'<br>';
				show_message($result);
			} else {
				echo __('Errors occurred:','udmupdater').' '.htmlspecialchars(serialize($result));
			}

			die;

		} elseif ('disconnect' == $_REQUEST['subaction'] && current_user_can('update_plugins')) {

			$options = $this->get_option($this->option_name);

			if (empty($options['email'])) {
				echo json_encode(array(
					'code' => 'INVALID',
					'data' => 'Not connected (no email found)'
				));
			} else {
				$result = wp_remote_post($this->url.'&udm_action=releaseaddon&slug='.urlencode($_POST['slug']).'&e='.urlencode($options['email']),
					apply_filters('udmupdater_wp_api_options', array(
						'timeout' => 10,
						'body' => array(
							'e' => $options['email'],
							'sid' => $this->siteid(),
							'slug' => $_POST['slug']
						)
					), 'releaseaddon')
				);

				if (is_array($result) && isset($result['body'])) {

					$decoded = json_decode($result['body'], true);
					if (empty($decoded)) {
						echo json_encode(array(
							'code' => 'INVALID',
							'data' => $result['body']
						));
					} else {
						echo $result['body'];
						if (isset($decoded['code']) && 'OK' == $decoded['code']) {
							$option = $this->get_option($this->option_name);
							if (!is_array($option)) $option = array();
							unset($option['email']);
							$this->update_option($this->option_name, $option);
						}
					}

				} elseif (is_wp_error($result)) {
					echo __('Errors occurred:','udmupdater').'<br>';
					show_message($result);
				} else {
					echo __('Errors occurred:','udmupdater').' '.htmlspecialchars(serialize($result));
				}
			}

			die();
		} elseif ('dismissexpiry' == $_REQUEST['subaction']) {

			$option = $this->get_option($this->option_name);
			if (!is_array($option)) $option = array();
			$option['dismissed_until'] = time() + 28*86400;
			$this->update_option($this->option_name, $option);
			
		} elseif ('change_auto_update' == $_REQUEST['subaction']) {
		
			$auto_update = empty($_REQUEST['auto_update']) ? false : true;
			
			$option = $this->get_option($this->option_name);
			if (!is_array($option)) $option = array();
			$option['auto_update'] = $auto_update;
			$this->update_option($this->option_name, $option);
			
			echo json_encode(array(
				'code' => $auto_update ? 'active' : 'inactive',
			));
		
		}
		
		die;
	}

	public function admin_menu() {
		global $pagenow;

		# Do we want to display a notice about the upcoming or past expiry of their subscription?
		if (!empty($this->plug_updatechecker) && !empty($this->plug_updatechecker->optionName) && current_user_can('update_plugins')) {
			#(!is_multisite() && 'options-general.php' == $pagenow) || (is_multisite() && 'settings.php' == $pagenow) ||
			if ('plugins.php' == $pagenow || 'update-core.php' == $pagenow || (('options-general.php' == $pagenow || 'admin.php' == $pagenow) && !empty($_REQUEST['page']) && $this->slug == $_REQUEST['page'])) {
				$do_expiry_check = true;
				$dismiss = '';
			} elseif (is_admin()) {

				$options = $this->get_option($this->option_name);

				$dismissed_until = empty($options['dismissed_until']) ? 0 : $options['dismissed_until'];

				if ($dismissed_until <= time()) {
					$do_expiry_check = true;
					$dismiss = '<div style="float:right; position: relative; top:-24px;" class="ud-'.esc_js($this->slug).'-expiry-dismiss"><a href="#" onclick="jQuery(\'.ud-'.esc_js($this->slug).'-expiry-dismiss\').parent().slideUp(); jQuery.post(ajaxurl, {action: \'udmupdater_ajax\', subaction: \'dismissexpiry\', userid: \''.esc_js($this->muid).'\', slug: \''.esc_js($this->slug).'\', nonce: \''.wp_create_nonce('udmupdater-ajax-nonce').'\' });">'.sprintf(__('Dismiss from main dashboard (for %s weeks)', 'udmupdater'), apply_filters('udmupdater_defaultdismiss', 4, $this->slug)).'</a></div>';
				}
			}
		}

		$oval = is_object($this->plug_updatechecker) ? get_site_option($this->plug_updatechecker->optionName, null) : null;
		$updateskey = 'x-spm-expiry';
		$supportkey = 'x-spm-support-expiry';

		$yourversionkey = 'x-spm-yourversion-tested';

		if (is_object($oval) && !empty($oval->update) && is_object($oval->update) && !empty($oval->update->$yourversionkey) && current_user_can('update_plugins') && true == apply_filters('udmanager_showcompatnotice', true, $this->slug) && (!defined('UDMANAGER_DISABLECOMPATNOTICE') || true != UDMANAGER_DISABLECOMPATNOTICE)) {

			// Prevent false-positives
			if (file_exists(dirname($this->plugin_file).'/readme.txt') && $fp = fopen(dirname($this->plugin_file).'/readme.txt', 'r')) {
				$file_data = fread($fp, 1024);
				if (preg_match("/^Tested up to: (\d+\.\d+).*(\r|\n)/", $file_data, $matches)) {
					$readme_says = $matches[1];
				}
				fclose($fp);
			}

			global $wp_version;
			include(ABSPATH.WPINC.'/version.php');
			$compare_wp_version = (preg_match('/^(\d+\.\d+)\..*$/', $wp_version, $wmatches)) ? $wmatches[1] : $wp_version;
			$compare_tested_version = $oval->update->$yourversionkey;
			if (!empty($readme_says) && version_compare($readme_says, $compare_tested_version, '>')) $compare_tested_version = $readme_says;
			#$compare_tested_version = (preg_match('/^(\d+\.\d+)\.*$/', $oval->update->$yourversionkey, $wmatches)) ? $wmatches[1] : $oval->update->$yourversionkey;

			if (version_compare($compare_wp_version, $compare_tested_version, '>')) {
				$this->admin_notices['yourversiontested'] = '<strong>'.__('Warning', 'udmupdater').':</strong> '.sprintf(__('The installed version of %s has not been tested on your version of WordPress (%s).', 'udmupdater'), htmlspecialchars($this->plugin_data['Name']), $wp_version).' '.sprintf(__('It has been tested up to version %s.', 'udmupdater'), $compare_tested_version).' '.__('You should update to make sure that you have a version that has been tested for compatibility.', 'udmupdater');
			}
		}

		if (!empty($do_expiry_check) && is_object($oval) && !empty($oval->update) && is_object($oval->update) && !empty($oval->update->$updateskey)) {

			if (preg_match('/(^|)expired_?(\d+)?(,|$)/', $oval->update->$updateskey, $matches)) {
				if (empty($matches[2])) {
					$this->admin_notices['updatesexpired'] = sprintf(__('Your paid access to %s updates for this site has expired. You will no longer receive updates.', 'udmupdater'), htmlspecialchars($this->plugin_data['Name'])).' '.__('To regain access to updates (including future features and compatibility with future WordPress releases) and support, please renew.', 'udmupdater').$dismiss;
				} else {
					$this->admin_notices['updatesexpired'] = sprintf(__('Your paid access to %s updates for %s add-ons on this site has expired.', 'udmupdater'), htmlspecialchars($this->plugin_data['Name']), $matches[2]).' '.__('To regain access to updates (including future features and compatibility with future WordPress releases) and support, please renew.', 'udmupdater').$dismiss;
				}
			}
			if (preg_match('/(^|,)soonpartial_(\d+)_(\d+)($|,)/', $oval->update->$updateskey, $matches)) {
				$this->admin_notices['updatesexpiringsoon'] = sprintf(__('Your paid access to %s updates for %s of the %s add-ons on this site will soon expire.', 'udmupdater'), htmlspecialchars($this->plugin_data['Name']), $matches[2], $matches[3]).' '.__('To retain your access, and maintain access to updates (including future features and compatibility with future WordPress releases) and support, please renew.', 'udmupdater').$dismiss;
			} elseif (preg_match('/(^|,)soon($|,)/', $oval->update->$updateskey)) {
				$this->admin_notices['updatesexpiringsoon'] = sprintf(__('Your paid access to %s updates for this site will soon expire.', 'udmupdater'), htmlspecialchars($this->plugin_data['Name'])).' '.__('To retain your access, and maintain access to updates (including future features and compatibility with future WordPress releases) and support, please renew.', 'udmupdater').''.$dismiss;
			}
		} elseif (!empty($do_expiry_check) && is_object($oval) && !empty($oval->update) && is_object($oval->update) && !empty($oval->update->$supportkey)) {
			if ('expired' == $oval->update->$supportkey) {
				$this->admin_notices['supportexpired'] = sprintf(__('Your paid access to %s support has expired.','udmupdater'), htmlspecialchars($this->plugin_data['Name'])).' '.__('To regain your access, please renew.', 'udmupdater').$dismiss;
			} elseif ('soon' == $oval->update->$supportkey) {
				$this->admin_notices['supportsoonexpiring'] = sprintf(__('Your paid access to %s support will soon expire.','udmupdater'), htmlspecialchars($this->plugin_data['Name'])).' '.__('To maintain your access to support, please renew.', 'udmupdater').$dismiss;
			}
		}

		add_action('all_admin_notices', array($this, 'admin_notices'));

		// Refresh, if specifically requested
		if (('options-general.php' == $pagenow) || (is_multisite() && 'settings.php' == $pagenow) && isset($_GET['udm_refresh'])) {
			if ($this->plug_updatechecker) $this->plug_updatechecker->checkForUpdates();
		}

	}

	public function puc_retain_fields($f) {
		if (!is_array($f)) return $f;
		if (!in_array('x-spm-yourversion-tested', $f)) $f[] = 'x-spm-yourversion-tested';
		if (!in_array('x-spm-expiry', $f)) $f[] = 'x-spm-expiry';
		if (!in_array('x-spm-support-expiry', $f)) $f[] = 'x-spm-support-expiry';
		return $f;
	}

	public function admin_notices() {
		foreach ($this->admin_notices as $key => $notice) {
			$notice = '<span style="font-size: 115%;">'.$notice.'</span>';
			if (is_numeric($key)) {
				$this->show_admin_warning($notice);
			} else {
				$this->show_admin_warning($notice, 'error');
			}
		}
	}

	public function core_upgrade_preamble() {
		if (!current_user_can('update_plugins')) return;
		if (!$this->is_connected()) $this->admin_notice_not_connected();
	}

	public function load_plugins_php() {
		if (!current_user_can('update_plugins')) return;
		$this->add_admin_notice_if_not_connected();
	}

	// Returns a boolean, depending on whether we already have a connection
	protected function is_connected() {
		$option = $this->get_option($this->option_name);
		if (!empty($option['email'])) return true;
		return false;
	}

	protected function add_admin_notice_if_not_connected() {
		if ($this->is_connected()) return;
		add_action('all_admin_notices', array($this, 'admin_notice_not_connected'));
	}

	public function admin_notice_not_connected() {
		echo '<div class="updated" id="udmupdater_not_connected">';
		$plugin_label = htmlspecialchars($this->plugin_data['Name']);
		echo apply_filters('udmupdater_updateradminnotice_header', '<h3>'.sprintf(__('Access to plugin updates (%s)', 'udmupdater'), $plugin_label).'</h3>', $this->plugin_data);
		$this->print_plugin_connector_box();
		echo '</div>';
		echo "<script>
		jQuery(document).ready(function() {
			jQuery('#udmupdater_not_connected').appendTo('.wrap p:first');
		});
		</script>";
	}

	public function after_plugin_row($file) {
		if (!current_user_can('update_plugins')) return;

// 		$active_class = ( is_plugin_active( $plugin_data['plugin'] ) ) ? ' active' : '';

		$wp_list_table = _get_list_table('WP_Plugins_List_Table');

		echo '<tr class="plugin-update-tr active" style="border-top: none;"><td colspan="' . esc_attr( $wp_list_table->get_column_count() ) . '" class="colspanchange">';
		
		$this->print_plugin_connector_box();

		echo '</td></tr>';
	}

	public function admin_footer() {
		?>
		<script>
			jQuery(document).ready(function($) {
				var nonce = '<?php echo esc_js(wp_create_nonce('udmupdater-ajax-nonce')); ?>';
				$('.udmupdater_userpassform_<?php echo esc_js($this->slug);?> .udmupdater-connect').click(function() {
					var button = this;
					var $box = $(this).closest('.udmupdater_userpassform');
					var email = $box.find('input[name="email"]').val();
					var password = $box.find('input[name="password"]').val();
					if (email == '' || password == '') {
						alert('<?php echo esc_js(
							apply_filters('udmupdater_need_credentials_message', 
								sprintf(
									__('You need to enter both an email address and a %s', 'udmupdater'),
									apply_filters('udmupdater_password_description', __('password', 'udmupdater'), $this->slug, $this->plugin_data)
								)
							)
						);?>');
						return false;
					}
					var sdata = {
						action: 'udmupdater_ajax',
						subaction: 'connect',
						nonce: nonce,
						userid: <?php echo $this->muid;?>,
						slug: '<?php echo esc_js($this->slug);?>',
						email: email,
						password: password
					}
					$(this).prop('disabled', true).html('<?php echo esc_js(__('Connecting...', 'udmupdater')); ?>');
					$.post(ajaxurl, sdata, function(response, data) {
						$(button).prop('disabled', false).html('<?php echo esc_js(__('Connect', 'udmupdater')); ?>');
						try {
							resp = $.parseJSON(response);
							if (resp.hasOwnProperty('code')) {
								console.log('Code: '+resp.code);
								if (resp.code == 'INVALID') {
									alert('<?php echo esc_js(__('The response from the remote site could not be decoded. (More information is recorded in the browser console).', 'udmupdater'));?>');
									console.log(resp);
								} else if (resp.code == 'BADAUTH') {
									if (resp.hasOwnProperty('data')) {
										alert(resp.msg);
									} else {
										alert('<?php echo esc_js(sprintf(
											__('Your email address and %s were not recognised.', 'udmupdater'), apply_filters('udmupdater_password_description', __('password', 'udmupdater'), $this->slug, $this->plugin_data)
										));?>');
										console.log(resp);
									}
								} else if (resp.code == 'OK') {
									alert('<?php echo esc_js(__('You have successfully connected for access to updates to this plugin.', 'udmupdater'));?>');
									$('.udmupdater_box_<?php echo esc_js($this->slug);?>').parent().slideUp();
								} else if (resp.code == 'ERR') {
									alert('<?php echo esc_js(__('Your login was accepted, but no available entitlement for this plugin was found.', 'udmupdater').' '.__('Has your licence expired, or have you used all your available licences elsewhere?', 'udmupdater'));?>');
									console.log(resp);
								}
							} else {
								alert('<?php echo esc_js(__('The response from the remote site could not be decoded. (More information is recorded in the browser console).', 'udmupdater'));?>');
								console.log('No response code found');
								console.log(resp);
							}
						} catch (e) {
							alert('<?php echo esc_js(__('The response from the remote site could not be decoded. (More information is recorded in the browser console).', 'udmupdater'));?>');
							console.log(e);
							console.log(response);
						}
					});
					return false;
				});

				$('#udmupdater_autoupdate_<?php echo esc_js($this->slug);?>').change(function() {
					
					var checked = $(this).is(':checked') ? 1 : 0;
					
					var sdata = {
						action: 'udmupdater_ajax',
						subaction: 'change_auto_update',
						nonce: nonce,
						auto_update: checked,
						userid: <?php echo $this->muid;?>,
						slug: '<?php echo esc_js($this->slug);?>'
					}
					
					var button = this;
					
					$(this).prop('disabled', true);
					
					$.post(ajaxurl, sdata, function(response, data) {
						$(button).prop('disabled', false);
						try {
							resp = $.parseJSON(response);
							if (resp.hasOwnProperty('code')) {
								if ('active' == resp.code) {
									alert('<?php echo esc_js(__('When updates to this plugin are available, they will be automatically installed.', 'udmupdater'));?>');
								} else {
									alert('<?php echo esc_js(__('When updates to this plugin are available, they will not be automatically installed.', 'udmupdater'));?>');
								}
							} else {
								alert('<?php echo esc_js(__('The response from the remote site could not be decoded. (More information is recorded in the browser console).', 'udmupdater'));?>');
								console.log('No response code found');
								console.log(resp);
							}
						} catch (e) {
							alert('<?php echo esc_js(__('The response from the remote site could not be decoded. (More information is recorded in the browser console).', 'udmupdater'));?>');
							console.log(e);
							console.log(response);
						}
					});
				});

				$('.udmupdater_userpassform_<?php echo esc_js($this->slug);?> .udmupdater-disconnect').click(function() {
					var button = this;
					var $box = $(this).closest('.udmupdater_userpassform');
					var sdata = {
						action: 'udmupdater_ajax',
						subaction: 'disconnect',
						nonce: nonce,
						userid: <?php echo $this->muid;?>,
						slug: '<?php echo esc_js($this->slug);?>'
					}
					$(this).prop('disabled', true).html('<?php echo esc_js(__('Disconnecting...', 'udmupdater')); ?>');
					$.post(ajaxurl, sdata, function(response, data) {
						$(button).prop('disabled', false).html('<?php echo esc_js(__('Disconnect', 'udmupdater')); ?>');
						try {
							resp = $.parseJSON(response);
							if (resp.hasOwnProperty('code')) {
							
								if ('BADAUTH' == resp.code && resp.hasOwnProperty('data') && 'invaliduser' == resp.data) {
								
									alert('<?php echo esc_js(__('Your email address was not recognised.', 'udmupdater'));?>');
								
								} else {
							
									alert('<?php echo esc_js(__('You have successfully disconnected access to updates to this plugin.', 'udmupdater'));?>');
									$('.udmupdater_box_<?php echo esc_js($this->slug);?>').parent().slideUp();
								
								}
							} else {
								alert('<?php echo esc_js(__('The response from the remote site could not be decoded. (More information is recorded in the browser console).', 'udmupdater'));?>');
								console.log('No response code found');
								console.log(resp);
							}
						} catch (e) {
							alert('<?php echo esc_js(__('The response from the remote site could not be decoded. (More information is recorded in the browser console).', 'udmupdater'));?>');
							console.log(e);
							console.log(response);
						}
					});
					return false;
				});

			});
		</script>
		<?php
	}
	
	public function set_allow_auto_updates($allow_auto_updates = true) {
		$this->allow_auto_updates = (bool)$allow_auto_updates;
	}

	public function get_allow_auto_updates() {
		return $this->allow_auto_updates;
	}

	protected function print_plugin_connector_box($type='inline') {

		// Are we already connected?

		$options = $this->get_option($this->option_name);
		$email = isset($options['email']) ? $options['email'] : '';
// 		$password = isset($options['password']) ? $options['password'] : '';

		if (empty($this->connector_footer_added)) {
			$this->connector_footer_added = true;
			add_action('admin_footer', array($this, 'admin_footer'));
		}

		$plugin_label = htmlspecialchars($this->plugin_data['Name']);
		if (!empty($this->plugin_data['PluginURI'])) $plugin_label = '<a href="'.esc_attr($this->plugin_data['PluginURI']).'">'.$plugin_label.'</a>';

		?>
		<div style="margin: 10px;  min-height: 36px;" class="udmupdater_box_<?php echo esc_attr($this->slug);?>">
			<?php if ($this->is_connected()) { ?>
			<div style="float: left; margin-right: 14px; margin-top: 4px;">
				<em><?php echo apply_filters('udmupdater_entercustomerlogin', sprintf(__('You are connected to receive updates for %s (login: %s)', 'udmupdater'), $plugin_label, htmlspecialchars($email)), $this->plugin_data, $this->slug); ?></em>: 
			</div>
			<div class="udmupdater_userpassform udmupdater_userpassform_<?php echo esc_attr($this->slug);?>" style="float:left;">
				<button class="button button-primary udmupdater-disconnect"><?php _e('Disconnect', 'udmupdater');?></button>
			</div>
			<?php } else { ?>
			<div style="float: left; margin-right: 14px; margin-top: 4px;">
				<em><?php echo apply_filters('udmupdater_entercustomerlogin', sprintf(__('Please enter your customer login to access updates for %s', 'udmupdater'), $plugin_label), $this->plugin_data); ?></em>: 
			</div>
			<div class="udmupdater_userpassform udmupdater_userpassform_<?php echo esc_attr($this->slug);?>" style="float:left;">
				<input type="text" style="width:180px;" placeholder="<?php echo esc_attr(__('Email', 'udmupdater')); ?>" name="email" value="">
				<input type="password" style="width:180px;" placeholder="<?php echo esc_attr(ucfirst(apply_filters('udmupdater_password_description', __('password', 'udmupdater'), $this->slug, $this->plugin_data))); ?>" name="password" value="">
				<button class="button button-primary udmupdater-connect"><?php _e('Connect', 'udmupdater');?></button>
			</div>
			<?php } ?>
			<?php if (apply_filters('udmupdater_autoupdate_form', $this->allow_auto_updates, $this->slug)) {
				$auto_update = empty($options['auto_update']) ? false : true;
				$checkbox_id = 'udmupdater_autoupdate_'.$this->slug;
				?>
				<div class="udmupdater_autoupdate" style="clear:left;">
					<input type="checkbox" id="<?php echo esc_attr($checkbox_id);?>" <?php if ($auto_update) echo 'checked="checked"';?>>
					<label for="<?php echo esc_attr($checkbox_id);?>"><?php echo apply_filters('udmupdater_entercustomerlogin', __('Automatically update as soon as an update becomes available (N.B. other plugins can over-ride this setting).', 'udmupdater'), $this->slug);?></label>
				</div>
			<?php } ?>
		</div>
		<?php
	}

	public function plugins_loaded() {
		load_plugin_textdomain('udmupdater', false, basename(dirname(__FILE__)).'/languages');
	}

	# We want to lessen the number of automatic checks if an update is already known to be available
	public function puc_check_now($shouldcheck, $lastcheck, $checkperiod) {

		// Skip checks immediately after a WP upgrade. This action has existed since WP 4.4. Since we're just trying to reduce server load spikes when WP core automatic security upgrades happen, that is adequate.
		if (did_action('pre_auto_update') && apply_filters('udmupdater_skip_after_auto_upgrade', true, $this->slug)) return false;

		global $wp_current_filter;
		if (true !== $shouldcheck || empty($this->plug_updatechecker) || 0 == $lastcheck || in_array('load-update-core.php', $wp_current_filter) || !defined('DOING_CRON')) return $shouldcheck;

		if (null === $this->plug_updatechecker->getUpdate()) return $shouldcheck;

		$days_since_check = max(round((time() - $lastcheck)/86400), 1);
		if ($days_since_check > 10000) return true;

		# Suppress checks on days 2, 4, 5, 7 and then every day except multiples of 7.
		if (2 == $days_since_check || 4 == $days_since_check || 5 == $days_since_check || 7 == $days_since_check || ($days_since_check >= 7 && $days_since_check % 7 != 0)) return false;

		return true;
	}

	public function updater_queryargs_plugin($args) {
		if (!is_array($args)) return $args;

		$options = $this->get_option($this->option_name);
		$email = isset($options['email']) ? $options['email'] : '';
		// The current protocol does not require (or recommend) sending the password
// 		$password = isset($options['password']) ? $options['password'] : '';

		$args['udm_action'] = 'updateinfo';
		$args['sid'] = $this->siteid();
		$args['su'] = urlencode(base64_encode(home_url()));
		$args['sn'] = urlencode(base64_encode(get_bloginfo('name')));
		$args['slug'] = urlencode($this->slug);
		$args['e'] = urlencode($email);
// 		$args['p'] = urlencode(base64_encode($password));

		// Some information on the server calling. This can be used - e.g. if they have an old version of PHP/WordPress, then this may affect what update version they should be offered
		include(ABSPATH.'wp-includes/version.php');
		global $wp_version;
		$sinfo = array(
			'wp' => $wp_version,
			'php' => phpversion(),
			'multi' => (is_multisite() ? 1 : 0),
			'mem' => ini_get('memory_limit'),
			'lang' => get_locale()
		);

		if (isset($this->plugin_data['Version'])) {
			$sinfo['pver'] = $this->plugin_data['Version'];
		}

		$args['si2'] = urlencode(base64_encode(json_encode($sinfo)));

		return $args;
	}

	// Funnelling through here allows for future flexibility
	public function get_option($option) {
		return get_site_option($option);
	}

	public function update_option($option, $val) {
		return update_site_option($option, $val);
	}

	public function show_admin_warning($message, $class = "updated") {
		echo '<div class="updraftmanagermessage '.$class.'">'."<p>$message</p></div>";
	}

	# Remove any existing updates detected
	public function site_transient_update_plugins($updates) {
		if (!is_object($updates) || empty($this->plugin_file)) return $updates;
		if (isset($updates, $updates->response, $updates->response[$this->plugin_file]))
			unset($updates->response[$this->plugin_file]);
		return $updates;
	}

	protected function siteid() {
		// This used to be keyed off the plugin slug - I see no reason for that
// 		$use_slug = $this->slug;
		$use_slug = 'updater';
		$sid = get_site_option('udmanager_'.$use_slug.'_sid');
		if (!is_string($sid)) {
			$sid = md5(rand().time().home_url());
			update_site_option('udmanager_'.$use_slug.'_sid', $sid);
		}
		return $sid;
	}

}
endif;

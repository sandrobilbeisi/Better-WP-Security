<?php

if ( ! class_exists( 'BWPS_Ban_Users' ) ) {

	class BWPS_Ban_Users {

		private static $instance = null;

		private
			$settings,
			$core,
			$page;

		private function __construct( $core ) {

			global $bwps_globals;

			$this->core = $core;
			$this->settings = get_site_option( 'bwps_ban_users' );

			add_action( $bwps_globals['plugin_hook'] . '_add_admin_meta_boxes', array( $this, 'add_admin_meta_boxes' ) ); //add meta boxes to admin page
			add_action( $bwps_globals['plugin_hook'] . '_page_top', array( $this, 'ban_users_intro' ) ); //add page intro and information
			add_action( 'admin_init', array( $this, 'initialize_admin' ) ); //initialize admin area
			add_action( 'admin_enqueue_scripts', array( $this, 'admin_script' ) ); //enqueue scripts for admin page
			add_filter( $bwps_globals['plugin_hook'] . '_wp_config_rules', array( $this, 'wp_config_rule' ) ); //build wp_config.php rules
			add_filter( $bwps_globals['plugin_hook'] . '_add_admin_sub_pages', array( $this, 'add_sub_page' ) ); //add to admin menu
			add_filter( $bwps_globals['plugin_hook'] . '_add_admin_tabs', array( $this, 'add_admin_tab' ) ); //add tab to menu
			add_filter( $bwps_globals['plugin_hook'] . '_add_dashboard_status', array( $this, 'dashboard_status' ) ); //add information for plugin status

			//manually save options on multisite
			if ( is_multisite() ) {
				add_action( 'network_admin_edit_bwps_away_mode', array( $this, 'save_network_options' ) ); //save multisite options
			}

		}

		/**
		 * Register subpage for Away Mode
		 *
		 * @param array $available_pages array of BWPS settings pages
		 */
		public function add_sub_page( $available_pages ) {

			global $bwps_globals;

			$this->page = $available_pages[0] . '-ban_users';

			$available_pages[] = add_submenu_page(
				$bwps_globals['plugin_hook'],
				__( 'Ban Users', 'better_wp_security' ),
				__( 'Ban Users', 'better_wp_security' ),
				$bwps_globals['plugin_access_lvl'],
				$available_pages[0] . '-ban_users',
				array( $this->core, 'render_page' )
			);

			return $available_pages;

		}

		public function add_admin_tab( $tabs ) {

			$tabs[ $this->page ] = __( 'Ban Users', 'better_wp_security' );

			return $tabs;

		}

		/**
		 * Add meta boxes to primary options pages
		 *
		 * @param array $available_pages array of available page_hooks
		 */
		public function add_admin_meta_boxes( $available_pages ) {

			//add metaboxes
			add_meta_box(
				'away_mode_options',
				__( 'Configure Banned User Settings', 'better_wp_security' ),
				array( $this, 'metabox_advanced_settings' ),
				'security_page_toplevel_page_bwps-ban_users',
				'advanced',
				'core'
			);

		}

		/**
		 * Add Away mode Javascript
		 *
		 * @return void
		 */
		public function admin_script() {

			global $bwps_globals;

			if ( strpos( get_current_screen()->id,'security_page_toplevel_page_bwps-ban_users' ) !== false ) {

				wp_enqueue_script( 'bwps_ban_users_js', $bwps_globals['plugin_url'] . 'modules/bwps-ban-users/js/admin-ban_users.js', 'jquery', $bwps_globals['plugin_build'] );

			}

		}

		/**
		 * Sets the status in the plugin dashboard
		 *
		 * @return void
		 */
		public function dashboard_status( $statuses ) {

			$link = 'admin.php?page=toplevel_page_bwps-ban_users';

			if ( $this->settings['enabled'] === 1 ) {
				$statuses['safe'][] = array(
					'text' => __( 'You are blocking known bad hosts and agents with the ban users tool.', 'better_wp_security' ),
					'link' => $link,
				);
			} else {
				$statuses['low'][] = array(
					'text' => __( 'You are not blocking any users that are known to be a problem. Consider turning on the Ban Users feature.', 'better_wp_security' ),
					'link' => $link,
				);
			}

			return $statuses;

		}

		/**
		 * Execute admin initializations
		 *
		 * @return void
		 */
		public function initialize_admin() {

			//Enabled section
			add_settings_section(
				'ban_users_enabled',
				__( 'Configure Ban Users', 'better_wp_security' ),
				array( $this, 'sandbox_general_options_callback' ),
				'security_page_toplevel_page_bwps-ban_users'
			);

			//primary settings section
			add_settings_section(
				'ban_users_settings',
				__( 'Configure Ban Users', 'better_wp_security' ),
				array( $this, 'sandbox_general_options_callback' ),
				'security_page_toplevel_page_bwps-ban_users'
			);

			//enabled field
			add_settings_field(
				'bwps_ban_users[enabled]',
				__( 'Enable Ban Users', 'better_wp_security' ),
				array( $this, 'ban_users_enabled' ),
				'security_page_toplevel_page_bwps-ban_users',
				'ban_users_enabled'
			);

			//host list field
			add_settings_field(
				'bwps_ban_users[host_list]',
				__( 'Ban Hosts', 'better_wp_security' ),
				array( $this, 'ban_users_host_list' ),
				'security_page_toplevel_page_bwps-ban_users',
				'ban_users_settings'
			);

			//agent _list field
			add_settings_field(
				'bwps_ban_users[agent_list]',
				__( 'Ban User Agents', 'better_wp_security' ),
				array( $this, 'ban_users_agent_list' ),
				'security_page_toplevel_page_bwps-ban_users',
				'ban_users_settings'
			);

			//agent _list field
			add_settings_field(
				'bwps_ban_users[white_list]',
				__( 'Whitelist Users', 'better_wp_security' ),
				array( $this, 'ban_users_white_list' ),
				'security_page_toplevel_page_bwps-ban_users',
				'ban_users_settings'
			);

			//Register the settings field for the entire module
			register_setting(
				'security_page_toplevel_page_bwps-ban_users',
				'bwps_ban_users',
				array( $this, 'sanitize_module_input' )
			);

		}

		/**
		 * Settings section callback
		 *
		 * Can be used for an introductory setction or other output. Currently is used by both settings sections.
		 *
		 * @return void
		 */
		public function sandbox_general_options_callback() {}

		/**
		 * echos Enabled Field
		 *
		 * @param  array $args field arguements
		 * @return void
		 */
		public function ban_users_enabled( $args ) {

			//disable the option if away mode is in the past
			if ( isset( $this->settings['enabled'] ) && $this->settings['enabled'] === 1 ) {
				$enabled = 1;
			} else {
				$enabled = 0;
			}

			$content = '<input type="checkbox" id="bwps_ban_users_enabled" name="bwps_ban_users[enabled]" value="1" ' . checked( 1, $enabled, false ) . '/>';
			$content .= '<label for="bwps_ban_users_enabled"> '  . __( 'Check this box to enable ban users', 'better_wp_security' ) . '</label>';

			echo $content;

		}

		/**
		 * echos Banned Hosts field
		 *
		 * @param  array $args field arguements
		 * @return void
		 */
		public function ban_users_host_list( $args ) {

			//disable the option if away mode is in the past
			if ( isset( $this->settings['host_list'] ) && strlen( $this->settings['host_list'] ) > 1 ) {
				$default = $this->settings['host_list'];
			} else {
				$default = '';
			}

			$content = '<textarea id="bwps_ban_users_host_list" name="bwps_ban_users[host_list]" rows="10" cols="50">' . $default . '</textarea>';
			$content .= '<p>' . __( 'Use the guidelines below to enter hosts that will not be allowed access to your site. Note you cannot ban yourself.', 'better_wp_security' ) . '</p>';
			$content .= '<ul><em>';
			$content .= '<li>' . __ ( 'You may ban users by individual IP address or IP address range.', 'better_wp_security' ) . '</li>';
			$content .= '<li>' . __ ( 'Individual IP addesses must be in IPV4 standard format (i.e. ###.###.###.###). Wildcards (*) are allowed to specify a range of ip addresses.', 'better_wp_security' ) . '</li>';
			$content .= '<li>' . __ ( 'If using a wildcard (*) you must start with the right-most number in the ip field. For example ###.###.###.* and ###.###.*.* are permitted but ###.###.*.### is not.', 'better_wp_security' ) . '</li>';
			$content .= '<li><a href="http://ip-lookup.net/domain-lookup.php" target="_blank">' . __( 'Lookup IP Address.', 'better_wp_security' ) . '</a></li>';
			$content .= '<li>' . __( 'Enter only 1 IP address or 1 IP address range per line.', 'better_wp_security' ) . '</li>';
			$content .= '</em></ul>';

			echo $content;

		}

		/**
		 * echos Banned Agents field
		 *
		 * @param  array $args field arguements
		 * @return void
		 */
		public function ban_users_agent_list( $args ) {

			//disable the option if away mode is in the past
			if ( isset( $this->settings['agent_list'] ) && strlen( $this->settings['agent_list'] ) > 1 ) {
				$default = $this->settings['agent_list'];
			} else {
				$default = '';
			}

			$content = '<textarea id="bwps_ban_users_agent_list" name="bwps_ban_users[agent_list]" rows="10" cols="50">' . $default . '</textarea>';
			$content .= '<p>' . __( 'Use the guidelines below to enter user agents that will not be allowed access to your site.', 'better_wp_security' ) . '</p>';
			$content .= '<ul><em>';
			$content .= '<li>' . __ ( 'Enter only 1 user agent per line.', 'better_wp_security' ) . '</li>';
			$content .= '</em></ul>';

			echo $content;

		}

		/**
		 * echos Banned white list field
		 *
		 * @param  array $args field arguements
		 * @return void
		 */
		public function ban_users_white_list( $args ) {

			//disable the option if away mode is in the past
			if ( isset( $this->settings['white_list'] ) && strlen( $this->settings['white_list'] ) > 1 ) {
				$default = $this->settings['white_list'];
			} else {
				$default = '';
			}

			$content = '<textarea id="bwps_ban_users_white_list" name="bwps_ban_users[white_list]" rows="10" cols="50">' . $default . '</textarea>';
			$content .= '<p>' . __( 'Use the guidelines below to enter hosts that will not be banned from your site. This will keep you from locking yourself out of any features if you should trigger a lockout. Please note this does not override away mode.', 'better_wp_security' ) . '</p>';
			$content .= '<ul><em>';
			$content .= '<li>' . __ ( 'You may white list users by individual IP address or IP address range.', 'better_wp_security' ) . '</li>';
			$content .= '<li>' . __ ( 'Individual IP addesses must be in IPV4 standard format (i.e. ###.###.###.###). Wildcards (*) are allowed to specify a range of ip addresses.', 'better_wp_security' ) . '</li>';
			$content .= '<li>' . __ ( 'If using a wildcard (*) you must start with the right-most number in the ip field. For example ###.###.###.* and ###.###.*.* are permitted but ###.###.*.### is not.', 'better_wp_security' ) . '</li>';
			$content .= '<li><a href="http://ip-lookup.net/domain-lookup.php" target="_blank">' . __( 'Lookup IP Address.', 'better_wp_security' ) . '</a></li>';
			$content .= '<li>' . __( 'Enter only 1 IP address or 1 IP address range per line.', 'better_wp_security' ) . '</li>';
			$content .= '</em></ul>';

			echo $content;

		}

		/**
		 * Build and echo the away mode description
		 *
		 * @return void
		 */
		public function ban_users_intro( $screen ) {

			if ( $screen === 'security_page_toplevel_page_bwps-ban_users' ) { //only display on away mode page

				$content = '<p>' . __( 'This feature allows you to ban hosts and user agents from your site completely using individual or groups of IP addresses as well as user agents without having to manage any configuration of your server. Any IP or user agent found in the lists below will not be allowed any access to your site.', 'better_wp_security' ) . '</p>';

				echo $content;

			}

		}

		/**
		 * Render the settings metabox
		 *
		 * @return void
		 */
		public function metabox_advanced_settings() {

			//set appropriate action for multisite or standard site
			if ( is_multisite() ) {
				$action = 'edit.php?action=bwps_ban_users';
			} else {
				$action = 'options.php';
			}

			printf( '<form name="%s" method="post" action="%s">', get_current_screen()->id, $action );

			$this->core->do_settings_sections( 'security_page_toplevel_page_bwps-ban_users', false );

			echo '<p>' . PHP_EOL;

			settings_fields( 'security_page_toplevel_page_bwps-ban_users' );

			echo '<input class="button-primary" name="submit" type="submit" value="' . __( 'Save Changes', 'better_wp_security' ) . '" />' . PHP_EOL;

			echo '</p>' . PHP_EOL;

			echo '</form>';

		}

		/**
		 * Sanitize and validate input
		 *
		 * @param  Array $input  array of input fields
		 * @return Array         Sanitized array
		 */
		public function sanitize_module_input( $input ) {

			$input['enabled'] = intval( $input['enabled'] == 1 ? 1 : 0 );

			$ips = $this->validate_bad_hosts( $input['host_list'], true );

			if ( is_wp_error( $ips ) ) {

				$input['enabled'] = 0; //disable ban users list

				$type = 'error';
				$message = '';

				if ( is_wp_error( $ips ) ) {

					foreach ( $ips->get_error_messages() as $error ) {
						$message .= $error . '<br />';
					}

				}

				$message .= sprintf( '<br /><br />%s', __( 'Note that the ban users feature has been disabled until the errors are corrected.', 'better_wp_security' ) );

			} else {

				$input['host_list'] = $ips;

				$type = 'updated';
				$message = __( 'Settings Updated', 'better_wp_security' );

			}

			add_settings_error(
				'bwps_admin_notices',
				esc_attr( 'settings_updated' ),
				$message,
				$type
			);

			return $input;

		}


		/**
		 * Prepare and save options in network settings
		 *
		 * @return void
		 */
		public function save_network_options() {

			if ( isset( $_POST['bwps_away_mode']['enabled'] ) ) {
				$settings['enabled'] = intval( $_POST['bwps_away_mode']['enabled'] == 1 ? 1 : 0 );
			}

			$settings['type'] = intval( $_POST['bwps_away_mode']['type'] ) === 1 ? 1 : 2;
			$settings['start'] = strtotime( $_POST['bwps_away_mode']['start']['date'] . ' ' . $_POST['bwps_away_mode']['start']['hour'] . ':' . $_POST['bwps_away_mode']['start']['minute'] . ' ' . $_POST['bwps_away_mode']['start']['sel'] );
			$settings['end'] = strtotime( $_POST['bwps_away_mode']['end']['date'] . ' ' . $_POST['bwps_away_mode']['end']['hour'] . ':' . $_POST['bwps_away_mode']['end']['minute'] . ' ' . $_POST['bwps_away_mode']['end']['sel'] );

			update_site_option( 'bwps_away_mode', $settings ); //we must manually save network options

			//send them back to the away mode options page
			wp_redirect( add_query_arg( array( 'page' => 'toplevel_page_bwps-away_mode', 'updated' => 'true' ), network_admin_url( 'admin.php' ) ) );
			exit();

		}

		/**
		 * Validates a host list
		 *
		 * @param string $hosts string of hosts to check
		 * @param bool $ban true for ban list, false for whitelist
		 * @return array array of good hosts or false
		 */
		public function validate_bad_hosts( $hosts, $ban ) {

			//validate list
			$banhosts = explode( PHP_EOL, $hosts );
			$list = array();
			$error_handler = null;

			if( ! empty( $banhosts ) ) {

				foreach( $banhosts as $host ) {

					$host = filter_var( $host, FILTER_SANITIZE_STRING );

					if ( strlen( $host ) > 0 ) {

						$ipParts = explode( '.', $host );
						$isIP = 0;
						$partcount = 1;
						$goodip = true;
						$foundwild = false;

						foreach ( $ipParts as $part ) {

							if ( $goodip == true ) {

								if ( ( is_numeric( trim( $part ) ) && trim( $part ) <= 255 && trim( $part ) >= 0 ) || trim( $part ) == '*' ) {
									$isIP++;
								}

								switch ( $partcount ) {

									case 1:

										if ( trim( $part ) == '*' ) {

											$goodip = false;

											if ( ! is_wp_error( $error_handler ) ) { //invalid ip
												$error_handler = new WP_Error();
											}

											$error_handler->add( 'error', __( filter_var( $host, FILTER_SANITIZE_STRING ) . ' is note a valid ip.', 'better_wp_security' ) );

										}

										break;

									case 2:

										if ( trim( $part ) == '*' ) {

											$foundwild = true;

										}

										break;

									default:

										if ( trim( $part ) != '*' ) {

											if ( $foundwild == true ) {

												$goodip = false;

												if ( ! is_wp_error( $error_handler ) ) { //invalid ip
													$error_handler = new WP_Error();
												}

												$error_handler->add( 'error', __( filter_var( $host, FILTER_SANITIZE_STRING ) . ' is note a valid ip.', 'better_wp_security' ) );

											}

										} else {

											$foundwild = true;

										}

										break;

								}

								$partcount++;

							}

						}

						if ( ip2long( trim( str_replace( '*', '0', $host ) ) ) == false ) { //invalid ip

							if ( ! is_wp_error( $error_handler ) ) {
								$error_handler = new WP_Error();
							}

							$error_handler->add( 'error', __( filter_var( $host, FILTER_SANITIZE_STRING ) . ' is not a valid ip.', 'better_wp_security' ) );

						} elseif ( strlen( $host > 4 && ! in_array( $host, $list ) ) ) {

							$list[] = trim( $host );

						}

					}

				}

			}

			if ( sizeof( $list ) > 1 ) {
				sort( $list );
				$list = array_unique( $list, SORT_STRING );
			}

			if ( is_wp_error( $error_handler ) ) {
				return $error_handler;
			} else {
				return $list;
			}

		}

		public function validate_bad_agents( $agents ) {



		}

		/**
		 * Start the Ban Users module
		 *
		 * @param  Bit51_BWPS_Core    $core     Instance of core plugin class
		 * @return BWPS_Away_Mode 			    The instance of the BWPS_Away_Mode class
		 */
		public static function start( $core ) {

			if ( ! isset( self::$instance ) || self::$instance === null ) {
				self::$instance = new self( $core );
			}

			return self::$instance;

		}

	}

}
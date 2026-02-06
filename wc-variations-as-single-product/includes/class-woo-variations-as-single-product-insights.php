<?php

class Woo_Variations_As_Single_Product_Insights {
	// Initiate variables
	private static $instance      = null;
	private $plugin_name          = 'Variations as Single Product for WooCommerce';
	private $plugin_slug          = 'wc-variations-as-single-product';
	private $plugin_prefix        = 'wvasp';
	private $plugin_file          = WC_VARIATIONS_AS_SINGLE_PRODUCT__FILE;
	private $plugin_base          = WC_VARIATIONS_AS_SINGLE_PRODUCT__BASE;
	private $api_url              = 'https://insight.storeplugin.net/';
	private $policy_url           = 'https://storeplugin.net/privacy-policy/';
	private $data_collect         = array(
		'server_info'   => 'Server informations(php, mysql, server, WordPress versions)',
		'plugins_list'  => 'Plugins List',
		'active_themes' => 'Active Themes',
		'site_info'     => 'Site URL, Name, Language, Locale',
		'admin_info'    => 'Your name and email address',
	);
	private $deactivation_reasons = array(
		'not_using'          => 'I am no longer using the plugin',
		'found_better'       => 'I found a better plugin',
		'temporary_deactive' => 'It\'s a temporary deactivation',
		'not_working'        => 'The plugin is not working as expected',
		'too_complex'        => 'The plugin is too complex to use',
		'other'              => 'Other',
	);

	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_notices', array( $this, 'show_optin_notice' ) );

		add_filter( 'plugin_action_links_' . $this->plugin_base, array( $this, 'customize_deactivate_link' ) );

		add_action( 'wp_ajax_' . $this->plugin_prefix . '_insights_optin', array( $this, 'handle_optin' ) );
		add_action( 'wp_ajax_' . $this->plugin_prefix . '_insights_optout', array( $this, 'handle_optout' ) );

		add_action( 'admin_footer', array( $this, 'add_deactivation_modal' ) );
		add_action( 'wp_ajax_' . $this->plugin_prefix . '_insights_deactivate', array( $this, 'handle_deactivation' ) );

		add_action( $this->plugin_prefix . '_insights_send_schedule', array( $this, 'send_insights_data' ) );

		register_activation_hook( $this->plugin_file, array( $this, 'plugin_activated' ) );
		register_deactivation_hook( $this->plugin_file, array( $this, 'clear_schedule_insights_data' ) );
	}

	// Show optin notice
	public function show_optin_notice() {
		if ( ! $this->should_show_notice() ) {
			return;
		}

		?>
		<div class="insights-notice-box updated <?php echo esc_attr( $this->plugin_prefix ); ?>-insights-notice">
			<?php // translators: %s is the plugin name ?>
			<p><strong><?php printf( esc_html__( 'Help us improve %s', 'wc-variations-as-single-product' ), esc_html( $this->plugin_name ) ); ?></strong></p>
			<p>
				<?php
				// translators: %s is the plugin name
				printf(
					esc_html__( 'Would you like to help us improve %1$s by sharing diagnostic and usage data? This information helps us make the plugin even more powerful.(%2$s)', 'wc-variations-as-single-product' ),
					esc_html( $this->plugin_name ),
					wp_kses_post( __( '<a class="sp-data-collect" href="#">what we collect</a>', 'wc-variations-as-single-product' ) )
				);
				?>
			</p>

			<p class="description" style="display:none;"><?php echo esc_html( implode( ', ', $this->data_collect ) ); ?>. <?php echo '<a href="' . esc_url( $this->policy_url ) . '" target="_blank">' . esc_html__( 'Read more', 'wc-variations-as-single-product' ) . '</a> ' . esc_html__( 'to understand how we collect and handle your data.', 'wc-variations-as-single-product' ); ?></p>
			<p>
				<button type="button" class="button button-primary insights-optin"><?php esc_html_e( 'Allow', 'wc-variations-as-single-product' ); ?></button>
				<button type="button" class="button insights-optout"><?php esc_html_e( 'No thanks', 'wc-variations-as-single-product' ); ?></button>
			</p>
		</div>
		<script type="text/javascript">
			jQuery(document).ready(function($) {
				// Control toggle of what data we collect
				$(".sp-data-collect").on("click", function(e) {
					e.preventDefault();
					$(this).parents(".insights-notice-box").find("p.description").slideToggle("fast");
				});

				var nonce = '<?php echo esc_js( wp_create_nonce( 'insights_nonce' ) ); ?>';
				var optinAction = '<?php echo esc_js( $this->plugin_prefix . '_insights_optin' ); ?>';
				var optoutAction = '<?php echo esc_js( $this->plugin_prefix . '_insights_optout' ); ?>';
				var pluginPrefix = '<?php echo esc_js( $this->plugin_prefix ); ?>';

				// Handle opt-in
				$("." + pluginPrefix + "-insights-notice .insights-optin").on("click", function() {
					$.ajax({
						url: ajaxurl,
						type: 'POST',
						data: {
							action: optinAction,
							nonce: nonce,
						},
						dataType: 'json',
						success: function (response) {
							//console.log(response);
							if (response.success) {
								$("." + pluginPrefix + "-insights-notice").fadeOut();
							}
						},
						error: function () {},
						complete: function () {}
					});
				});

				// Handle opt-out
				$("." + pluginPrefix + "-insights-notice .insights-optout").on("click", function() {
					$.ajax({
						url: ajaxurl,
						type: 'POST',
						data: {
							action: optoutAction,
							nonce: nonce,
						},
						dataType: 'json',
						success: function (response) {
							//console.log(response);
							if (response.success) {
								$("." + pluginPrefix + "-insights-notice").fadeOut();
							}
						},
						error: function () {},
						complete: function () {}
					});
				});
			});
		</script>
		<?php
	}

	private function should_show_notice() {
		$optin_status = get_option( $this->plugin_prefix . '_insights_optin', 'no' );
		$last_notice  = get_option( $this->plugin_prefix . '_insights_last_notice', 0 );

		if ( $optin_status === 'yes' ) {
			return false;
		}

		// Check if the last notice was shown more than 90 days ago
		if ( $last_notice && ( time() - $last_notice ) < 7776000 ) { // 7776000 seconds = 90 days
			return false;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return false;
		}

		return true;
	}

	// Handle opt-in
	public function handle_optin() {
		check_ajax_referer( 'insights_nonce', 'nonce' );

		$this->update_optin_option();

		$this->send_insights_data();

		$this->clear_schedule_insights_data();
		$this->schedule_insights_data();

		wp_send_json_success();
	}
	// Handle opt-out
	public function handle_optout() {
		check_ajax_referer( 'insights_nonce', 'nonce' );

		$this->update_optout_option();

		$this->send_insights_skip_request();

		$this->clear_schedule_insights_data();

		wp_send_json_success();
	}

	public function customize_deactivate_link( $action_links ) {
		if ( isset( $action_links['deactivate'] ) ) {
			$class                      = esc_attr( $this->plugin_slug ) . '-deactivate-link';
			$action_links['deactivate'] = str_replace( '<a', '<a class="' . $class . '"', $action_links['deactivate'] );
		}

		return $action_links;
	}


	public function plugin_activated() {
		delete_option( $this->plugin_prefix . '_insights_last_notice' );
		delete_option( $this->plugin_prefix . '_insights_last_tracking_time' );

		$this->send_insights_data();

		$this->clear_schedule_insights_data();
		$this->schedule_insights_data();
	}

	// Update opt-in option
	private function update_optin_option() {
		update_option( $this->plugin_prefix . '_insights_optin', 'yes' );
		update_option( $this->plugin_prefix . '_insights_last_notice', time() );

		// If user previously opted out, remove skip option
		$insights_skip = get_option( $this->plugin_prefix . '_insights_skip', 'no' );
		if ( $insights_skip === 'yes' ) {
			delete_option( $this->plugin_prefix . '_insights_skip' );
			update_option( $this->plugin_prefix . '_insights_previously_skip', 'yes' );
		}
	}
	// Update opt-out option
	private function update_optout_option() {
		update_option( $this->plugin_prefix . '_insights_optin', 'no' );
		update_option( $this->plugin_prefix . '_insights_skip', 'yes' );
		update_option( $this->plugin_prefix . '_insights_last_notice', time() );
	}

	// Clear schedule insights data
	public function clear_schedule_insights_data() {
		$schedule_hook = wp_unslash( $this->plugin_prefix . '_insights_send_schedule' );

		if ( wp_next_scheduled( $schedule_hook ) ) {
			wp_clear_scheduled_hook( $schedule_hook );
		}
	}

	// Schedule insights data
	public function schedule_insights_data() {
		$optin_status = get_option( $this->plugin_prefix . '_insights_optin', 'no' );
		if ( $optin_status !== 'yes' ) {
			return;
		}

		$schedule_hook = wp_unslash( $this->plugin_prefix . '_insights_send_schedule' );

		if ( ! wp_next_scheduled( $schedule_hook ) ) {
			wp_schedule_event( time(), 'weekly', $schedule_hook );
		}
	}

	// Send insights data
	public function send_insights_data() {
		$optin_status       = get_option( $this->plugin_prefix . '_insights_optin', 'no' );
		$last_tracking_time = get_option( $this->plugin_prefix . '_insights_last_tracking_time', 0 );

		if ( $optin_status !== 'yes' ) {
			return;
		}

		// Check if the last tracking time was more than a week ago
		if ( $last_tracking_time && ( time() - $last_tracking_time ) < 604800 ) { // 604800 seconds = 1 week
			return;
		}

		$data = $this->get_data();
		$this->send_data( $data );

		update_option( $this->plugin_prefix . '_insights_last_tracking_time', time() );
	}

	private function get_data() {
		global $wpdb;

		$previously_skip = get_option( $this->plugin_prefix . '_insights_previously_skip', 'no' );

		$data = array(
			'url'             => esc_url( home_url() ),
			'admin_email'     => get_option( 'admin_email' ),
			'site_info'       => $this->get_site_data(),
			'server_info'     => $this->get_server_data(),
			'plugins'         => $this->get_plugins(),
			'themes'          => $this->get_themes(),
			'admin_info'      => $this->get_admin_info(),
			'ip_address'      => $this->get_user_ip_address(),
			'is_local'        => $this->is_local_site(),
			'previously_skip' => $previously_skip === 'yes',
		);

		return $data;
	}

	private function get_server_data() {
		global $wpdb;

		$server_data = array();

		if ( isset( $_SERVER['SERVER_SOFTWARE'] ) && ! empty( $_SERVER['SERVER_SOFTWARE'] ) ) {
			$server_data['server_software'] = sanitize_text_field( wp_unslash( $_SERVER['SERVER_SOFTWARE'] ) );
		}

		if ( isset( $_SERVER['REQUEST_TIME'] ) && ! empty( $_SERVER['REQUEST_TIME'] ) ) {
			$server_data['server_time'] = wp_date( 'c', (int) $_SERVER['REQUEST_TIME'] );
		}

		$server_data['mysql_version'] = $wpdb->db_version();
		$server_data['php_version']   = defined( 'PHP_VERSION' ) ? PHP_VERSION : 'unknown';
		$server_data['curl_enable']   = function_exists( 'curl_init' ) ? 'Yes' : 'No';

		if ( function_exists( 'ini_get' ) ) {
			$server_data['php_max_execution_time'] = ini_get( 'max_execution_time' );
			$server_data['php_memory_limit']       = ini_get( 'memory_limit' );
			$server_data['php_max_upload_size']    = ini_get( 'upload_max_filesize' );
			$server_data['php_max_post_size']      = ini_get( 'post_max_size' );
			$server_data['php_max_input_time']     = ini_get( 'max_input_time' );
		}

		return $server_data;
	}

	private function get_site_data() {
		return array(
			'site_name'           => get_bloginfo( 'name' ),
			'language'            => get_bloginfo( 'language' ),
			'locale'              => get_locale(),
			'wp_version'          => get_bloginfo( 'version' ),
			'woocommerce_version' => defined( 'WC_VERSION' ) ? WC_VERSION : 'not_active',
			'wp_memory_limit'     => defined( 'WP_MEMORY_LIMIT' ) ? WP_MEMORY_LIMIT : '',
			'multisite'           => is_multisite() ? 'Yes' : 'No',
			'theme_slug'          => get_stylesheet(),
			'debug_mode'          => ( defined( 'WP_DEBUG' ) && WP_DEBUG ) ? 'Yes' : 'No',
		);
	}

	private function get_plugins() {
		if ( ! function_exists( 'get_plugins' ) ) {
			include ABSPATH . '/wp-admin/includes/plugin.php';
		}

		// Get all plugins
		$plugins = get_plugins();

		$active_plugins   = array();
		$inactive_plugins = array();

		foreach ( $plugins as $plugin_path => $plugin ) {
			if ( is_plugin_active( $plugin_path ) ) {
				$active_plugins[] = array(
					'name'       => $plugin['Name'],
					'version'    => $plugin['Version'],
					'plugin_uri' => $plugin['PluginURI'],
					'author'     => $plugin['Author'],
					'author_uri' => $plugin['AuthorURI'],
					'slug'       => strstr( $plugin_path, '/', true ),
				);
			} else {
				$inactive_plugins[] = array(
					'name'       => $plugin['Name'],
					'version'    => $plugin['Version'],
					'plugin_uri' => $plugin['PluginURI'],
					'author'     => $plugin['Author'],
					'author_uri' => $plugin['AuthorURI'],
					'slug'       => strstr( $plugin_path, '/', true ),
				);
			}
		}

		return array(
			'active_plugins'   => $active_plugins,
			'inactive_plugins' => $inactive_plugins,
		);
	}

	private function get_themes() {
		$active_theme = wp_get_theme();
		$parent_theme = $active_theme->parent();
		$all_themes   = wp_get_themes();

		$theme_info = array();

		foreach ( $all_themes as $theme_slug => $theme ) {
			$theme_info[] = array(
				'name'          => $theme->get( 'Name' ),
				'version'       => $theme->get( 'Version' ),
				'slug'          => $theme_slug,
				'ThemeURI'      => $theme->get( 'ThemeURI' ),
				'Author'        => $theme->get( 'Author' ),
				'AuthorURI'     => $theme->get( 'AuthorURI' ),
				'active'        => $active_theme->stylesheet === $theme_slug ? 'Yes' : 'No',
				'active_parent' => ( isset( $parent_theme->stylesheet ) && $parent_theme->stylesheet === $theme_slug ) ? 'Yes' : 'No',
			);
		}

		return $theme_info;
	}

	private function get_admin_info() {
		$users = get_users(
			array(
				'role'    => 'administrator',
				'orderby' => 'ID',
				'order'   => 'ASC',
				'number'  => 1,
				'paged'   => 1,
			)
		);

		$admin = ( is_array( $users ) && ! empty( $users ) ) ? $users[0] : false;

		if ( $admin ) {
			return array(
				'admin_email'  => $admin->user_email,
				'first_name'   => $admin->first_name ? $admin->first_name : $admin->display_name,
				'last_name'    => $admin->last_name,
				'display_name' => $admin->display_name,
			);
		}

		return array(); // Return an empty array if no admin is found
	}

	private function get_user_ip_address() {
		$response = wp_remote_get( 'https://icanhazip.com' );

		if ( is_wp_error( $response ) ) {
			return '';
		}

		$ip = trim( wp_remote_retrieve_body( $response ) );

		// validate the IP address
		if ( ! filter_var( $ip, FILTER_VALIDATE_IP ) ) {
			return '';
		}

		return $ip;
	}

	private function is_local_site() {
		// Check if the site URL contains 'localhost', '127.0.0.1', or any of the local extensions
		$site_url         = get_site_url();
		$local_extensions = array( '.test', '.testing', '.local', '.localhost' );

		if ( strpos( $site_url, 'localhost' ) !== false ||
			strpos( $site_url, '127.0.0.1' ) !== false ||
			array_reduce(
				$local_extensions,
				function ( $carry, $extension ) use ( $site_url ) {
					return $carry || ( strpos( $site_url, $extension ) !== false );
				},
				false
			)
		) {
			return true;
		}

		// Check if WP_LOCAL_DEV constant is defined and set to true
		if ( defined( 'WP_LOCAL_DEV' ) && WP_LOCAL_DEV ) {
			return true;
		}

		// Check if the server's IP address is local (127.0.0.1 or private network IPs)
		$server_ip = isset( $_SERVER['SERVER_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['SERVER_ADDR'] ) ) : '';
		if ( in_array( $server_ip, array( '127.0.0.1', '::1' ) ) || substr( $server_ip, 0, 3 ) === '10.' || substr( $server_ip, 0, 7 ) === '192.168' ) {
			return true;
		}

		return false;
	}


	private function send_data( $data, $path = 'insights' ) {
		$insights_url = trailingslashit( $this->api_url ) . $path;
		$response     = wp_remote_post(
			$insights_url,
			array(
				'body'    => array(
					'data'              => wp_json_encode( $data ),
					'plugin_identifier' => $this->plugin_prefix,
				),
				'timeout' => 30,
			)
		);
	}

	private function send_insights_skip_request() {
		$skip_url = trailingslashit( $this->api_url ) . 'skip';
		$response = wp_remote_post(
			$skip_url,
			array(
				'body'    => array(
					'skip'              => true,
					'plugin_identifier' => $this->plugin_prefix,
				),
				'timeout' => 30,
			)
		);
	}

	public function add_deactivation_modal() {
		$screen = get_current_screen();
		if ( $screen && $screen->id === 'plugins' ) {
			?>
			<div id="<?php echo esc_attr( $this->plugin_prefix ); ?>-deactivation-modal" style="display: none;">
				<div class="sp-deactivation-content">
					<h3><?php esc_html_e( 'We\'re sorry to see you go!', 'wc-variations-as-single-product' ); ?></h3>
					<p><strong><?php esc_html_e( 'Before you deactivate the plugin, would you mind telling us why?', 'wc-variations-as-single-product' ); ?></strong></p>
					<form id="<?php echo esc_attr( $this->plugin_prefix ); ?>-deactivation-form">
						<div class="sp-deactivation-reasons">
							<?php foreach ( $this->deactivation_reasons as $value => $label ) : ?>
								<label>
									<input type="radio" name="reason" value="<?php echo esc_attr( $value ); ?>" required>
									<?php echo esc_html( $label ); ?>
								</label>
							<?php endforeach; ?>
						</div>
						<textarea name="details" placeholder="<?php esc_attr_e( 'Additional details (optional)', 'wc-variations-as-single-product' ); ?>" style="display:none;"></textarea>
						<div class="sp-deactivation-buttons">
							<button type="submit" class="button button-primary sp-deactivation-submit"><?php esc_html_e( 'Submit & Deactivate', 'wc-variations-as-single-product' ); ?></button>
							<button type="button" class="button sp-deactivation-skip"><?php esc_html_e( 'Skip & Deactivate', 'wc-variations-as-single-product' ); ?></button>
						</div>
					</form>
				</div>
			</div>
			<style>
				#<?php echo esc_attr( $this->plugin_prefix ); ?>-deactivation-modal {
					position: fixed;
					top: 0;
					left: 0;
					right: 0;
					bottom: 0;
					background: rgba(0,0,0,0.7);
					z-index: 999999;
					display: flex;
					align-items: center;
					justify-content: center;
				}
				.sp-deactivation-content {
					background: #fff;
					padding: 0 30px 30px;
					border-radius: 5px;
					max-width: 480px;
					width: 90%;
				}
				.sp-deactivation-content h3 {
					box-shadow: 0px 0px 8px rgba(0,0,0,.1);
					margin: 0 -30px;
					padding: 20px 30px;
				}
				.sp-deactivation-content select,
				.sp-deactivation-content textarea {
					width: 100%;
					margin: 10px 0;
				}
				.sp-deactivation-content textarea {
					height: 60px;
					padding: 10px 15px;
				}
				.sp-deactivation-buttons {
					margin-top: 20px;
					text-align: right;
					display: flex;
					justify-content: space-between;
				}
				.sp-deactivation-buttons button {
					margin-left: 10px;
				}
				.button.sp-deactivation-skip {
					border: 0;
					color: #718096;
					background: transparent;
				}

				.sp-deactivation-reasons > label {
					display: block;
					padding: 5px 0;
				}
				.sp-deactivation-reasons > label input {
					margin-right: 8px;
				}
			</style>
			<script>
				jQuery(document).ready(function($) {
					var pluginPrefix = '<?php echo esc_js( $this->plugin_prefix ); ?>';
					var pluginSlug = '<?php echo esc_js( $this->plugin_slug ); ?>';

					$('.deactivate > .' + pluginSlug + '-deactivate-link').on('click', function(e) {
						e.preventDefault();
						$('#'+pluginPrefix+'-deactivation-modal').show();
					});

					// if radio button is checked and value is 'other', show textarea
					$('input[name="reason"]').on('change', function() {
						if ($(this).val() === 'other') {
							$(this).closest('form').find('textarea').show();
						} else {
							$(this).closest('form').find('textarea').hide();
						}
					});

					var deactivateLink = $('.deactivate > .' + pluginSlug + '-deactivate-link').attr('href');

					$('#' + pluginPrefix + '-deactivation-form .sp-deactivation-skip').on('click', function() {
						window.location.href = deactivateLink;
					});

					$('#' + pluginPrefix + '-deactivation-form').on('submit', function(e) {
						e.preventDefault();

						var deactiveAction = '<?php echo esc_js( $this->plugin_prefix . '_insights_deactivate' ); ?>';
						var nonce = '<?php echo esc_js( wp_create_nonce( 'insights_nonce' ) ); ?>';
						var reason = $(this).find('input[name="reason"]:checked').val();
						var details = $(this).find('textarea[name="details"]').val();
						
						$.ajax({
							url: ajaxurl,
							type: 'POST',
							data: {
								action: deactiveAction,
								nonce: nonce,
								reason: reason,
								details: details,
							},
							beforeSend: function() {
								$('#' + pluginPrefix + '-deactivation-modal .sp-deactivation-submit').text('Deactivating...').addClass('disabled');
							},
							success: function() {
								window.location.href = deactivateLink;
							}
						});
					});
				});
			</script>
			<?php
		}
	}

	public function handle_deactivation() {
		if ( isset( $_POST['nonce'] ) && ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['nonce'] ) ), 'insights_nonce' ) ) {
			wp_send_json_error( 'Nonce verification failed' );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Permission denied' );
		}

		$data = array(
			'reason'  => isset( $_POST['reason'] ) ? sanitize_text_field( wp_unslash( $_POST['reason'] ) ) : '',
			'details' => isset( $_POST['details'] ) ? sanitize_textarea_field( wp_unslash( $_POST['details'] ) ) : '',
			'data'    => $this->get_data(),
		);

		$this->send_data( $data, 'deactivation' );

		update_option( $this->plugin_prefix . '_insights_last_tracking_time', time() );

		wp_send_json_success();
	}
}

// Initialize the insights
Woo_Variations_As_Single_Product_Insights::instance();

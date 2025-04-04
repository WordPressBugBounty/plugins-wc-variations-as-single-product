<?php

/**
 * The file that defines the core plugin class
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the admin area.
 *
 * @link       https://storeplugin.net/plugins/variations-as-single-product-for-woocommerce
 * @since      1.0.0
 *
 * @package    Woo_Variations_As_Single_Product
 * @subpackage Woo_Variations_As_Single_Product/includes
 */

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @since      1.0.0
 * @package    Woo_Variations_As_Single_Product
 * @subpackage Woo_Variations_As_Single_Product/includes
 * @author     StorePlugin <contact@storeplugin.net>
 */
class Woo_Variations_As_Single_Product {

	/**
	 * The loader that's responsible for maintaining and registering all hooks that power
	 * the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      Woo_Variations_As_Single_Product_Loader    $loader    Maintains and registers all hooks for the plugin.
	 */
	protected $loader;

	/**
	 * The unique identifier of this plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $plugin_name    The string used to uniquely identify this plugin.
	 */
	protected $plugin_name;

	/**
	 * The current version of the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $version    The current version of the plugin.
	 */
	protected $version;

	/**
	 * Define the core functionality of the plugin.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {
		if ( defined( 'WC_VARIATIONS_AS_SINGLE_PRODUCT_VERSION' ) ) {
			$this->version = WC_VARIATIONS_AS_SINGLE_PRODUCT_VERSION;
		} else {
			$this->version = '1.0.0';
		}
		$this->plugin_name = 'wc-variations-as-single-product';

		$this->load_dependencies();
		$this->set_locale();
		$this->define_admin_hooks();
		$this->define_public_hooks();

	}

	/**
	 * Load the required dependencies for this plugin.
	 *
	 * Include the following files that make up the plugin:
	 *
	 * - Woo_Variations_As_Single_Product_Loader. Orchestrates the hooks of the plugin.
	 * - Woo_Variations_As_Single_Product_i18n. Defines internationalization functionality.
	 * - Woo_Variations_As_Single_Product_Admin. Defines all hooks for the admin area.
	 * - Woo_Variations_As_Single_Product_Public. Defines all hooks for the public side of the site.
	 *
	 * Create an instance of the loader which will be used to register the hooks
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function load_dependencies() {

		/**
		 * The class responsible for orchestrating the actions and filters of the
		 * core plugin.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-woo-variations-as-single-product-loader.php';

		/**
		 * The class responsible for defining internationalization functionality
		 * of the plugin.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-woo-variations-as-single-product-i18n.php';

		/**
		 * The class responsible for defining all actions that occur in the admin area.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/class-woo-variations-as-single-product-admin.php';

		/**
		 * The class responsible for defining all actions that occur in the public-facing
		 * side of the site.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'public/class-woo-variations-as-single-product-public.php';

		/**
		 * Exclude products from being displayed as variations.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-woo-variations-as-single-product-exclude-products.php';

		$this->loader = new Woo_Variations_As_Single_Product_Loader();

	}

	/**
	 * Define the locale for this plugin for internationalization.
	 *
	 * Uses the Woo_Variations_As_Single_Product_i18n class in order to set the domain and to register the hook
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function set_locale() {

		$plugin_i18n = new Woo_Variations_As_Single_Product_i18n();

		$this->loader->add_action( 'plugins_loaded', $plugin_i18n, 'load_plugin_textdomain' );

	}

	/**
	 * Register all of the hooks related to the admin area functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_admin_hooks() {

		$plugin_admin = new Woo_Variations_As_Single_Product_Admin( $this->get_plugin_name(), $this->get_version() );

		$pugin_exclude_product_instance = new Woo_Variations_As_Single_Product_Exclude_Products( $this->get_version() );

		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_styles' );
		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts' );

		$this->loader->add_action( 'admin_notices', $plugin_admin, 'missing_woocommerce_notice');
		$this->loader->add_action( 'admin_notices', $plugin_admin, 'settings_page_notice');
		$this->loader->add_filter( 'plugin_action_links_'. WC_VARIATIONS_AS_SINGLE_PRODUCT__BASE, $plugin_admin, 'settings_link', 10, 2);

		// Pro Update manual notice (TEMP)
		$this->loader->add_action( 'after_plugin_row_wc-variations-as-single-product-pro/wc-variations-as-single-product-pro.php', $plugin_admin, 'manual_update_notice_for_pro_plugin', 10, 2 );

		// WooCommerce settings tab
		$this->loader->add_filter( 'woocommerce_settings_tabs_array', $plugin_admin, 'add_settings_tab', 99 );
		$this->loader->add_action( 'woocommerce_settings_tabs_sp_variations_as_product', $plugin_admin, 'settings_tab' );
		$this->loader->add_action( 'woocommerce_update_options_sp_variations_as_product', $plugin_admin, 'save_settings' );

		// Settings for single variation product tab
		$this->loader->add_filter( 'woocommerce_product_data_tabs', $plugin_admin, 'add_single_variation_tab' );
		$this->loader->add_action( 'woocommerce_product_data_panels', $plugin_admin, 'display_single_variation_panel' );
		$this->loader->add_action( 'woocommerce_process_product_meta', $plugin_admin, 'save_single_variation_panel' );

		$this->loader->add_action( 'woocommerce_product_after_variable_attributes', $plugin_admin, 'product_variation_meta_fields', 10, 3 );
		$this->loader->add_action( 'woocommerce_save_product_variation', $plugin_admin, 'save_variation_settings_fields', 10, 2 );

		$this->loader->add_action( 'wvasp_terms_update_schedule', $plugin_admin, 'wvasp_terms_update' );

		//$this->loader->add_action( 'save_post', $plugin_admin, 'wvasp_update_on_product_update', 10, 3 );
		$this->loader->add_action( 'woocommerce_update_product', $plugin_admin, 'wvasp_update_on_product_update', 10, 2 );

		// Exclude Product Schedule on settings save
		$this->loader->add_action( 'wvasp_schedule_product_exclusion', $pugin_exclude_product_instance, 'trigger_variable_product_exclusion' );
		// Exclude Product on product update
		$this->loader->add_action( 'woocommerce_update_product', $pugin_exclude_product_instance, 'variable_product_exclusion_on_product_update', 10, 2 );
	}

	/**
	 * Register all of the hooks related to the public-facing functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_public_hooks() {

		$plugin_public = new Woo_Variations_As_Single_Product_Public( $this->get_plugin_name(), $this->get_version() );

		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_styles' );
		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_scripts' );

		// Modify the query to show variations as single products
		$this->loader->add_action( 'woocommerce_product_query', $plugin_public, 'variation_as_single_product', 20 );
		$this->loader->add_action( 'wc_product_query_args_filter', $plugin_public, 'variation_as_single_product', 20 );
		$this->loader->add_action( 'woocommerce_shortcode_products_query', $plugin_public, 'variation_as_single_product_shortcode', 20 );

		// Theme & Plugin support for premium version
		if ( defined( 'WC_VARIATIONS_AS_SINGLE_PRODUCT_PRO_VERSION' ) ) {
			$this->loader->add_filter( 'avf_avia_product_slider_defaults', $plugin_public, 'variation_as_single_product_shortcode', 10 ); //Enfold Theme
			$this->loader->add_filter( 'avia_product_slide_query', $plugin_public, 'variation_as_single_product_shortcode', 10 ); //Enfold Theme
			$this->loader->add_filter( 'jet-engine/listing/grid/posts-query-args', $plugin_public, 'variation_as_single_product_shortcode', 10 ); //JetEngine Plugin
			$this->loader->add_filter( 'breakdance_woocommerce_get_products_query', $plugin_public, 'variation_as_single_product_shortcode' , 10, 1 ); //Breakdance Builder
		}

		// General filter to use in different query args
		$this->loader->add_filter( 'wvasp_product_query_args_filter', $plugin_public, 'variation_as_single_product_shortcode' , 10, 1 ); //General Use

		
		//$this->loader->add_filter( 'woocommerce_product_variation_title', $plugin_public, 'modify_variation_title', 10, 2 );
		$this->loader->add_filter( 'the_title', $plugin_public, 'modify_variation_title', 10, 2 );

		// "WooCommerce Wholesale Prices Premium" plugin support
		$this->loader->add_filter( 'pre_get_posts',  $plugin_public, 'woocommerce_wholesale_prices_variation_support', 10, 2 );

		// Exclude products from being displayed as variations based on settings
		$this->loader->add_action( 'pre_get_posts',  $plugin_public, 'variation_exclusion_meta_query', 9999999, 2 );
	}

	/**
	 * Run the loader to execute all of the hooks with WordPress.
	 *
	 * @since    1.0.0
	 */
	public function run() {
		$this->loader->run();
	}

	/**
	 * The name of the plugin used to uniquely identify it within the context of
	 * WordPress and to define internationalization functionality.
	 *
	 * @since     1.0.0
	 * @return    string    The name of the plugin.
	 */
	public function get_plugin_name() {
		return $this->plugin_name;
	}

	/**
	 * The reference to the class that orchestrates the hooks with the plugin.
	 *
	 * @since     1.0.0
	 * @return    Woo_Variations_As_Single_Product_Loader    Orchestrates the hooks of the plugin.
	 */
	public function get_loader() {
		return $this->loader;
	}

	/**
	 * Retrieve the version number of the plugin.
	 *
	 * @since     1.0.0
	 * @return    string    The version number of the plugin.
	 */
	public function get_version() {
		return $this->version;
	}

}

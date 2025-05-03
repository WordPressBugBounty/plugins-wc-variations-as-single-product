<?php

/**
 * @link              https://storeplugin.net/plugins/variations-as-single-product-for-woocommerce
 * @since             1.0.0
 * @package           Woo_Variations_As_Single_Product
 *
 * @wordpress-plugin
 * Plugin Name:       Variations as Single Product for WooCommerce
 * Requires Plugins:  woocommerce
 * Plugin URI:        https://storeplugin.net/plugins/variations-as-single-product-for-woocommerce/
 * Description:       Show variations as individual products on the Shop page, Product Category page, and Search result page
 * Version:           4.0.2
 * Author:            StorePlugin
 * Author URI:        https://storeplugin.net
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       wc-variations-as-single-product
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Currently plugin version.
 */
define( 'WC_VARIATIONS_AS_SINGLE_PRODUCT_VERSION', '4.0.2' );

define( 'WC_VARIATIONS_AS_SINGLE_PRODUCT__FILE', __FILE__ );
define( 'WC_VARIATIONS_AS_SINGLE_PRODUCT__BASE', plugin_basename( __FILE__ ) );
define( 'WC_VARIATIONS_AS_SINGLE_PRODUCT__PATH', plugin_dir_path( __FILE__ ) );

/**
 * The code that runs during plugin activation.
 */
function activate_woo_variations_as_single_product() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-woo-variations-as-single-product-activator.php';
	Woo_Variations_As_Single_Product_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 */
function deactivate_woo_variations_as_single_product() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-woo-variations-as-single-product-deactivator.php';
	Woo_Variations_As_Single_Product_Deactivator::deactivate();
}

/**
 * Disable the plugin if the pro version is active & the version is greater than or equal to 4.0.0
 */
if ( defined( 'WC_VARIATIONS_AS_SINGLE_PRODUCT_PRO_VERSION' ) && version_compare( WC_VARIATIONS_AS_SINGLE_PRODUCT_PRO_VERSION, '4.0.0', '>=' ) ) {
	return;
}

register_activation_hook( __FILE__, 'activate_woo_variations_as_single_product' );
register_deactivation_hook( __FILE__, 'deactivate_woo_variations_as_single_product' );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-woo-variations-as-single-product.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_woo_variations_as_single_product() {

	$plugin = new Woo_Variations_As_Single_Product();
	$plugin->run();

}
run_woo_variations_as_single_product();

/**
 * Declare compatibility with WooCommerce HPOS
 *
 * @return void
 */
add_action( 'before_woocommerce_init', 'declare_hpos_compatibility' );
function declare_hpos_compatibility() {
	if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
	}
}
<?php

/**
 * Fired during plugin activation
 *
 * @link       https://storeplugin.net/plugins/variations-as-single-product-for-woocommerce
 * @since      1.0.0
 *
 * @package    Woo_Variations_As_Single_Product
 * @subpackage Woo_Variations_As_Single_Product/includes
 */

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @since      1.0.0
 * @package    Woo_Variations_As_Single_Product
 * @subpackage Woo_Variations_As_Single_Product/includes
 * @author     StorePlugin <contact@storeplugin.net>
 */
class Woo_Variations_As_Single_Product_Activator {

	/**
	 * Short Description. (use period)
	 *
	 * Long Description.
	 *
	 * @since    1.0.0
	 */
	public static function activate() {
		wp_clear_scheduled_hook( 'wvasp_terms_update_schedule' );
		wp_clear_scheduled_hook( 'wvasp_schedule_product_exclusion' );
	}

}

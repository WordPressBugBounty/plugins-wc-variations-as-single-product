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
		if ( ! wp_next_scheduled( 'wvasp_terms_update_schedule' ) ) {
			// Schedule the first occurrence immediately
			wp_schedule_single_event( time(), 'wvasp_terms_update_schedule' );
	
			// Schedule the event to run every 24 hours
			wp_schedule_event( time(), 'daily', 'wvasp_terms_update_schedule' );
		}

		if ( ! wp_next_scheduled( 'wvasp_schedule_product_exclusion' ) ) {
			// Schedule the first occurrence immediately
			wp_schedule_single_event( time(), 'wvasp_schedule_product_exclusion' );
	
			// Schedule the event to run every 24 hours
			wp_schedule_event( time(), 'daily', 'wvasp_schedule_product_exclusion' );
		}
	}

}

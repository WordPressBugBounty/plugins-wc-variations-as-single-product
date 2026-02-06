<?php
/**
 * Handle the taxonomy related functionality of the plugin.
 *
 * A class definition that modifies the taxonomy of the plugin in admin side.
 *
 * @link       https://storeplugin.net/plugins/variations-as-single-product-for-woocommerce
 * @since      4.0.0
 *
 * @package    Woo_Variations_As_Single_Product
 * @subpackage Woo_Variations_As_Single_Product/includes
 */
class Woo_Variations_As_Single_Product_Taxonomy {
	/**
	 * Constructor
	 */
	public function __construct() {}

	/**
	 * Update single product category and tag
	 */
	public function single_product_taxonomy_update( $product_id, $product ) {
		$this->single_product_category_update( $product ); // update variant category
		$this->single_product_tag_update( $product ); // update variant tag
		$this->single_product_brand_update( $product ); // update variant brand
	}

	/**
	 * Update single product variant category
	 *
	 * @param WC_Product $product
	 * @return void
	 */
	public function single_product_category_update( $product ) {
		// If product is not a variable product, return
		if ( 'variable' != $product->get_type() ) {
			return;
		}

		// Get all terms of the variable product
		$product_cat = wp_get_post_terms( $product->get_id(), 'product_cat', array( 'fields' => 'ids' ) );

		// Loop through all variations of the variable product
		foreach ( $product->get_children() as $variation_id ) {
			wp_set_post_terms( $variation_id, $product_cat, 'product_cat', false );
		}
	}

	/**
	 * Update single product variant tag
	 *
	 * @param WC_Product $product
	 * @return void
	 */
	public function single_product_tag_update( $product ) {
		// If product is not a variable product, return
		if ( 'variable' != $product->get_type() ) {
			return;
		}

		// Get all terms of the variable product
		$product_tag = wp_get_post_terms( $product->get_id(), 'product_tag', array( 'fields' => 'ids' ) );

		// Loop through all variations of the variable product
		foreach ( $product->get_children() as $variation_id ) {
			wp_set_post_terms( $variation_id, $product_tag, 'product_tag', false );
		}
	}

	/**
	 * Update single product variant brand
	 *
	 * @param WC_Product $product
	 * @return void
	 */
	public function single_product_brand_update( $product ) {
		// if 'product_brand' is not a taxonomy, return
		if ( ! taxonomy_exists( 'product_brand' ) ) {
			return;
		}

		// If product is not a variable product, return
		if ( 'variable' != $product->get_type() ) {
			return;
		}

		// Get all terms of the variable product
		$product_brand = wp_get_post_terms( $product->get_id(), 'product_brand', array( 'fields' => 'ids' ) );

		// Loop through all variations of the variable product
		foreach ( $product->get_children() as $variation_id ) {
			wp_set_post_terms( $variation_id, $product_brand, 'product_brand', false );
		}
	}
}

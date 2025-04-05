<?php
/**
 * Exclude products from being displayed as variations.
 * 
 * @package Woo_Variations_As_Single_Product
 * @since   3.4.5
 */
class Woo_Variations_As_Single_Product_Exclude_Products {
    /**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;
    
    public function __construct( $version ) {
        $this->version = $version;
    }

    /**
     * Remove exclude meta from all variable & variant products
     * 
     * @return void
     */
    public static function remove_exclude_meta_from_all_products() {
        global $wpdb;

        // Remove metadata '_wvasp_exclude' = 'yes' from all products
        $wpdb->query("
            DELETE pm
            FROM {$wpdb->postmeta} pm
            INNER JOIN {$wpdb->posts} p
            ON pm.post_id = p.ID
            WHERE pm.meta_key = '_wvasp_exclude'
            AND p.post_type IN ('product', 'product_variation')
        ");
    }

    /**
     * Remove exclude meta from a product and its variants
     * 
     * @param int $product_id
     * @return void
     */
    public function remove_exclude_meta_from_product( $product_id, $product = null ) {
        global $wpdb;

        if ( $product == null ) {
            $product = wc_get_product( $product_id );
        }

        // Get all variant product IDs
        $variation_ids = $product->get_children();

        // Remove metadata '_wvasp_exclude' for the product
        delete_post_meta($product_id, '_wvasp_exclude');

        // Remove metadata '_wvasp_exclude' for its children
        foreach ($variant_ids as $variant_id) {
            delete_post_meta($variant_id, '_wvasp_exclude');
        }
    }

    /**
     * Exclude product variation on settings save
     * 
     * @return void
     */
    public function single_product_variations_exclude( $product_id, $product, $settings ) {
        global $wpdb;

        $legacy_product_exclude = get_option( 'wvasp_legacy_product_exclude', 'no' );

        if ( $legacy_product_exclude == 'yes' ) {
            return; // Exit early if legacy excludation is enabled
        }

        $exclude_category_fields = $settings['exclude_category_fields'];
        $exclude_child_category_fields = $settings['exclude_child_category_fields'];
        $exclude_tag_fields = $settings['exclude_tag_fields'];

        $exclude_parent_products_forcefully = $settings['exclude_parent_products_forcefully'];

        //$product = wc_get_product( $product_id );
        $variation_ids = $product->get_children();
        $status = $product->get_status();

        $single_settings = [];
        $single_settings['get_single_exclude_varations'] = get_post_meta( $product->get_id(), '_wvasp_single_exclude_varations', true );
        $single_settings['get_single_hide_parent_product'] = get_post_meta( $product->get_id(), '_wvasp_single_hide_parent_product', true );

        // Exclude non published or excluded from being a variation product variants and return
        if ( $status != 'publish' || $single_settings['get_single_exclude_varations'] == 'yes' ) {
            $this->exclude_all_variants( $variation_ids );
            return; // Exit early if product is not published or excluded from being a variation
        }

        // Exclude parent product based on settings & individual variation settings
        $this->exclude_parent_variable_products( $product, $settings, $single_settings );

        // Exclude category
        if ( !empty( $exclude_category_fields ) ) {
            // Include child categories if not excluded
            if ( $exclude_child_category_fields !== 'yes' ) {
                $all_category_ids = [];
                foreach ( $exclude_category_fields as $category_id ) {
                    $all_category_ids = array_merge(
                        $all_category_ids,
                        get_term_children($category_id, 'product_cat') // Fetch child category IDs
                    );
                }
                $exclude_category_fields = array_unique(array_merge($exclude_category_fields, $all_category_ids));
            }

            $this->exclude_category_products( $product, $variation_ids, $settings );
        }

		// Exclude tag
		if ( ! empty( $exclude_tag_fields ) ) {
			$this->exclude_tag_products( $product, $variation_ids, $settings );
		}

        // Excluded based on single variation meta settings
        $this->exclude_variation_based_on_product_meta( $variation_ids );

        // Ignore parent product from being excluded if all variants are excluded
        $this->intact_parent_product( $product, $variation_ids );

        // Exclude parent product forcefully based on settings & individual variation settings
        if ( $exclude_parent_products_forcefully == 'yes' ) {
            $this->exclude_parent_variable_products_forcefully( $product, $settings, $single_settings );
        }
    }

    /**
     * Exclude all variants of a product
     * - Product is excluded from being a variation
     * - Product is not published
     * 
     * @param array $variation_ids
     * @return void
     */
    public function exclude_all_variants( $variation_ids ) {
        foreach ($variation_ids as $variation_id) {
            update_post_meta( $variation_id, '_wvasp_exclude', 'yes' );
        }
    }

    /**
     * Exclude parent product based on settings & individual variation settings
     * 
     * @param WC_Product $product
     * @param array $settings
     * @param array $single_settings
     * @return void
     */
    public function exclude_parent_variable_products( $product, $settings, $single_settings ){
        // If exclude parent products is not enabled on settings or single product, return
        if ( $settings['exclude_parent_products'] != 'yes' && $single_settings['get_single_hide_parent_product'] != 'yes' ) {
            return;
        }

        // If category is excluded, return
        if(!empty($settings['exclude_category_fields'])){
            // get current product categories
            $product_categories = wp_get_post_terms( $product->get_id(), 'product_cat', array( 'fields' => 'ids' ) );

            // if $exclude_category_fields and $product_categories have common values, return
            if( !empty( array_intersect( $settings['exclude_category_fields'], $product_categories ) ) ){
                return;
            }
        }

        // If tag is excluded, return
        if(!empty($settings['exclude_tag_fields'])){
            // get current product tags
            $product_tags = wp_get_post_terms( $product->get_id(), 'product_tag', array( 'fields' => 'ids' ) );

            // if $exclude_tag_fields and $product_tags have common values, return
            if( !empty( array_intersect( $settings['exclude_tag_fields'], $product_tags ) ) ){
                return;
            }
        }
        
        update_post_meta( $product->get_id(), '_wvasp_exclude', 'yes' );
    }

    /**
     * Exclude parent product forcefully based on settings
     * The parent will be excluded even if all variations are excluded
     * 
     * @param WC_Product $product
     * @param array $settings
     * @param array $single_settings
     * @return void
     */
    public function exclude_parent_variable_products_forcefully( $product, $settings ){
        if ( $single_settings['exclude_parent_products_forcefully'] != 'yes' ) {
            return; // return if exclude forcefully parent products is not enabled
        }
        
        update_post_meta( $product->get_id(), '_wvasp_exclude', 'yes' );
    }
    
    /**
     * Exclude product variations based on category
     * 
     * @param WC_Product $product
     * @param array $variation_ids
     * @param array $settings
     * @return void
     */
    public function exclude_category_products( $product, $variation_ids, $settings ) {
        // get current product categories
        $product_categories = wp_get_post_terms( $product->get_id(), 'product_cat', array( 'fields' => 'ids' ) );

        // if $exclude_category_fields and $product_categories have common values, exclude variations
        if( !empty( array_intersect( $settings['exclude_category_fields'], $product_categories ) ) ){
            // Loop through all variations
            foreach( $variation_ids as $variation_id ){
                update_post_meta( $variation_id, '_wvasp_exclude', 'yes' );
            }
        }
    }

    /**
     * Exclude product variations based on tag
     * 
     * @param WC_Product $product
     * @param array $variation_ids
     * @param array $settings
     * @return void
     */
    public function exclude_tag_products( $product, $variation_ids, $settings ) {
        // get current product tags
        $product_tags = wp_get_post_terms( $product->get_id(), 'product_tag', array( 'fields' => 'ids' ) );

        // if $exclude_tag_fields and $product_tags have common values, exclude variations
        if( !empty( array_intersect( $settings['exclude_tag_fields'], $product_tags ) ) ){
            // Loop through all variations
            foreach( $variation_ids as $variation_id ){
                update_post_meta( $variation_id, '_wvasp_exclude', 'yes' );
            }
        }
    }

    /**
     * Exclude product variations based on individual exclude settings on each variation
     * 
     * @param array $variation_ids
     * @return void
     */
    public function exclude_variation_based_on_product_meta( $variation_ids ) {
        foreach ($variation_ids as $variation_id) {
            // get single exclude variation meta
            $get_single_exclude_variation = get_post_meta( $variation_id, '_wvasp_single_exclude_variation', true );

            // if single exclude variation is enabled, exclude variation
            if( $get_single_exclude_variation == 'yes' ){
                update_post_meta( $variation_id, '_wvasp_exclude', 'yes' );
            }
        }
	}

    /**
     * Exclude parent product if all variations are excluded
     * 
     * @param WC_Product $product
     * @param array $variation_ids
     * @return void
     */
    public function intact_parent_product( $product, $variation_ids ) {
        $exclude_count = 0;
        foreach ( $variation_ids as $variation_id ) {
            $exclude = get_post_meta( $variation_id, '_wvasp_exclude', true );
            if ( $exclude == 'yes' ) {
                $exclude_count++;
            }
        }

        // With WPDB
        /*
        global $wpdb;
        $exclude_count = $wpdb->get_var(
            $wpdb->prepare(
            "
            SELECT COUNT(*)
            FROM {$wpdb->postmeta}
            WHERE meta_key = '_wvasp_exclude'
            AND meta_value = 'yes'
            AND post_id IN (" . implode(',', array_map('intval', $variation_ids)) . ")
            "
            )
        );
        */

        if ( $exclude_count == count( $variation_ids ) ) {
            delete_post_meta( $product->get_id(), '_wvasp_exclude' );
        }
    }

    /**
     * Exclude product variation on product update
     * 
     * @return void
     */
    public function variable_product_exclusion_on_product_update( $product_id, $product ) {
        global $wpdb;

        $legacy_product_exclude = get_option( 'wvasp_legacy_product_exclude', 'no' );

        if ( $legacy_product_exclude == 'yes' ) {
            return; // Exit early if legacy excludation is enabled
        }

        $product_type = $product->get_type();

        if ( $product_type == 'variable' ) {
            $settings = [];
            $settings['exclude_parent_products'] = get_option( 'wvasp_hide_parent_products', 'no' );
            $settings['exclude_parent_products_forcefully'] = get_option( 'wvasp_exclude_parent_products_forcefully', 'no' );
            $settings['exclude_category_fields'] = get_option( 'wvasp_exclude_category_fields', array() );
            $settings['exclude_child_category_fields'] = get_option( 'wvasp_exclude_child_category_fields', 'no' );
            $settings['exclude_tag_fields']     = get_option( 'wvasp_exclude_tag_fields', array() );

            // Remove exclude meta from this product and its variants
            $this->remove_exclude_meta_from_product( $product_id, $product );

            // Exclude product variations
            $this->single_product_variations_exclude( $product_id, $product, $settings );

            // Action hook to extend the exclusion functionality on product update
            do_action( 'wvasp_variable_product_exclusion_on_product_update', $product_id );
        }
    }
}



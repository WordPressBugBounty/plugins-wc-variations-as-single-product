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

    public function trigger_variable_product_exclusion() {
        $exclude_parent_products   = get_option( 'wvasp_hide_parent_products', 'no' );
        $exclude_category_fields   = get_option( 'wvasp_exclude_category_fields', array() );
		$exclude_tag_fields        = get_option( 'wvasp_exclude_tag_fields', array() );
        $legacy_product_exclude = get_option( 'wvasp_legacy_product_exclude', 'no' );

        if ( $legacy_product_exclude == 'yes' ) {
            return; // Exit early if legacy excludation is enabled
        }

        $this->remove_exclude_meta_from_all_products();

        if ( $exclude_parent_products == 'yes' ) {
            $this->exclude_parent_variable_products();
        }

        if ( !empty( $exclude_category_fields ) ) {
            $this->exclude_category_products($exclude_category_fields);
        }

		// Exclude tag
		if ( !empty($exclude_tag_fields) ) {
			$this->exclude_tag_products($exclude_tag_fields);
		}

        // Exclude non published product variants
        $this->exclude_non_published_product_variants();

        // Exclude single product
        $this->exclude_variation_based_on_product_meta();

        do_action( 'wvasp_variable_product_exclusion' );
    }

    /**
     * Remove exclude meta from all variable & variant products
     * 
     * @return void
     */
    public function remove_exclude_meta_from_all_products() {
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
	 * Hide parent product ---- Have to implement Batch Processing
	 * 
	 * @return void
	 */
	public function exclude_parent_variable_products () {
		global $wpdb;
        
		$args = array(
			'type' => 'variable',
			'limit' => -1,
			'return' => 'ids',
		);

		// Ignore Parent product ID from hidden for setected categories and tags ->
		// when "Exclude Product Categories" and "Exclude Product Tags" is enabled
		$exclude_category_fields = get_option( 'wvasp_exclude_category_fields', array() );
		$exclude_tag_fields		 = get_option( 'wvasp_exclude_tag_fields', array() );

		if ( !empty( $exclude_category_fields ) ) {
			$args['tax_query'][] = array(
				'taxonomy' => 'product_cat',
				'field'    => 'term_id',
				'terms'    => $exclude_category_fields,
				'operator' => 'NOT IN',
			);
		}

		if ( !empty( $exclude_tag_fields ) ) {
			$args['tax_query'][] = array(
				'taxonomy' => 'product_tag',
				'field'    => 'term_id',
				'terms'    => $exclude_tag_fields,
				'operator' => 'NOT IN',
			);
		}

		$variable_product_parent_ids = wc_get_products( $args );
		
		// Get product ID's based on Single Product meta '_wvasp_single_exclude_varations'
		$get_excluded_product_ids = $wpdb->get_col("
			SELECT pm.post_id 
			FROM {$wpdb->postmeta} pm
			INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
			WHERE p.post_type = 'product'
			AND pm.meta_key = '_wvasp_single_exclude_varations'
			AND pm.meta_value = 'yes'
		");

		// Exclude '_wvasp_single_exclude_varations' variable product from being excluded
		$variable_product_parent_ids = array_diff($variable_product_parent_ids, $get_excluded_product_ids);

		// Add metadata '_wvasp_exclude' = 'yes' to all $variable_product_parent_ids
		foreach ($variable_product_parent_ids as $variable_product_parent_id) {
			update_post_meta( $variable_product_parent_id, '_wvasp_exclude', 'yes' );
		}
	}

    /**
     * Exclude category products ---- Have to implement Batch Processing
     * 
     * @param array $exclude_category_fields
     * @return void
     */
    public function exclude_category_products( $exclude_category_fields ) {
        global $wpdb;

        // Check if child categories should be excluded
        $exclude_child_category_fields = get_option('wvasp_exclude_child_category_fields', 'no');

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

        // Prepare the query placeholders
        $exclude_category_fields_placeholder = implode(',', array_map('intval', $exclude_category_fields));

        // Query to fetch variation IDs
        $variation_ids = $wpdb->get_col(
            "
            SELECT v.ID
            FROM {$wpdb->posts} v
            INNER JOIN {$wpdb->posts} p ON v.post_parent = p.ID
            INNER JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
            INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
            WHERE v.post_type = 'product_variation'
            AND v.post_status = 'publish'
            AND p.post_type = 'product'
            AND p.post_status = 'publish'
            AND tt.taxonomy = 'product_cat'
            AND tt.term_id IN ({$exclude_category_fields_placeholder})
            "
        );

        $variation_ids = array_unique($variation_ids);

        if ( empty( $variation_ids ) ) {
            return; // No variations found, exit early
        }

        // Update metadata for all variations
        foreach ($variation_ids as $variation_id) {
            update_post_meta( $variation_id, '_wvasp_exclude', 'yes' );
        }
    }

    /**
     * Exclude tag products ---- Have to implement Batch Processing
     * 
     * @param array $exclude_tag_fields
     * @return void
     */
    public function exclude_tag_products($exclude_tag_fields) {
        global $wpdb;
    
        // Validate and sanitize $exclude_tag_fields
        if (empty($exclude_tag_fields) || !is_array($exclude_tag_fields)) {
            return; // Exit if no tags are provided
        }
    
        $exclude_tag_fields_placeholder = implode(',', array_map('intval', $exclude_tag_fields));
    
        // Query to fetch variation IDs based on product tags
        $variation_ids = $wpdb->get_col(
            "
            SELECT v.ID
            FROM {$wpdb->posts} v
            INNER JOIN {$wpdb->posts} p ON v.post_parent = p.ID
            INNER JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
            INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
            WHERE v.post_type = 'product_variation'
            AND v.post_status = 'publish'
            AND p.post_type = 'product'
            AND p.post_status = 'publish'
            AND tt.taxonomy = 'product_tag'
            AND tt.term_id IN ({$exclude_tag_fields_placeholder})
            "
        );

        $variation_ids = array_unique($variation_ids);
    
        if (empty($variation_ids)) {
            return; // No variations found, exit early
        }
    
        // Update metadata for all variations
        foreach ($variation_ids as $variation_id) {
            update_post_meta($variation_id, '_wvasp_exclude', 'yes');
        }
    }
    

    /**
	 * Exclude non published product variants
     * ---- Have to implement Batch Processing
     * ---- Add transient
	 * 
     * @return void
	 */
	public function exclude_non_published_product_variants () {
		global $wpdb;

		// Query to get all variation IDs where the parent product is not published
		$non_published_variation_ids = $wpdb->get_col("
			SELECT p.ID 
			FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->posts} parent_p ON p.post_parent = parent_p.ID
			WHERE p.post_type = 'product_variation'
			AND parent_p.post_status != 'publish'
		");

		// Add metadata '_wvasp_exclude' = 'yes' to all $non_published_variation_ids
		foreach ($non_published_variation_ids as $non_published_variation_id) {
			update_post_meta( $non_published_variation_id, '_wvasp_exclude', 'yes' );
		}
	}

    /**
	 * Exclude single product
	 * 
	 * @return array $exclude_ids
	 */
	public function exclude_variation_based_on_product_meta () {
		global $wpdb;
		$exclude_ids = array();
		
		// Get product ID's based on Single Product meta '_wvasp_single_exclude_varations'
		$get_excluded_product_ids = $wpdb->get_col("
			SELECT pm.post_id 
			FROM {$wpdb->postmeta} pm
			INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
			WHERE p.post_type = 'product'
			AND pm.meta_key = '_wvasp_single_exclude_varations'
			AND pm.meta_value = 'yes'
		");

		// Initialize array to store child IDs
		$get_excluded_product_child_ids = array();

		if (!empty($get_excluded_product_ids)) {
			// Prepare placeholders for the IN clause
			$placeholders = implode(',', array_fill(0, count($get_excluded_product_ids), '%d'));

			// Fetch all child (variation) IDs of the excluded parent products
			$get_excluded_product_child_ids = $wpdb->get_col($wpdb->prepare("
				SELECT p.ID 
				FROM {$wpdb->posts} p
				WHERE p.post_parent IN ($placeholders)
				AND p.post_type = 'product_variation'
			", ...$get_excluded_product_ids));
		}

		// Get variation ID's based on Single Product variation meta '_wvasp_single_exclude_variation'
		$get_excluded_variant_ids = $wpdb->get_col("
			SELECT pm.post_id 
			FROM {$wpdb->postmeta} pm
			INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
			WHERE p.post_type = 'product_variation'
			AND pm.meta_key = '_wvasp_single_exclude_variation'
			AND pm.meta_value = 'yes'
		");

		// Get all product ID's based on Single Product meta '_wvasp_single_hide_parent_product'
		$get_hide_parent_product_ids = $wpdb->get_col("
			SELECT pm.post_id 
			FROM {$wpdb->postmeta} pm
			INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
			WHERE p.post_type = 'product'
			AND pm.meta_key = '_wvasp_single_hide_parent_product'
			AND pm.meta_value = 'yes'
		");

		$exclude_ids = array_values( array_unique( array_merge( $get_excluded_product_child_ids, $get_excluded_variant_ids, $get_hide_parent_product_ids ) ) );

		// Exclude '_wvasp_single_exclude_varations' variable product from being excluded
		$exclude_ids = array_diff( $exclude_ids, $get_excluded_product_ids );

		// Add metadata '_wvasp_exclude' = 'yes' to all $exclude_ids
		foreach ($exclude_ids as $exclude_id) {
			update_post_meta( $exclude_id, '_wvasp_exclude', 'yes' );
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
            // Get all child product IDs
            $child_product_ids = $wpdb->get_col(
                $wpdb->prepare(
                    "
                    SELECT p.ID 
                    FROM {$wpdb->posts} p
                    WHERE p.post_parent = %d
                    AND p.post_type = 'product_variation'
                    ",
                    $product_id
                )
            );

            // Remove metadata '_wvasp_exclude' for the product
            delete_post_meta($product_id, '_wvasp_exclude');

            // Remove metadata '_wvasp_exclude' for its children
            foreach ($child_product_ids as $child_id) {
                delete_post_meta($child_id, '_wvasp_exclude');
            }

            // Get variation ID's based on individual exclude settings - '_wvasp_single_exclude_variation'
            $exclude_ids = $wpdb->get_col(
                $wpdb->prepare(
                    "
                    SELECT pm.post_id 
                    FROM {$wpdb->postmeta} pm
                    INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
                    WHERE p.post_type = 'product_variation'
                    AND pm.meta_key = '_wvasp_single_exclude_variation'
                    AND pm.meta_value = 'yes'
                    AND p.post_parent = %d
                    ",
                    $product_id
                )
            );

            // If parent product is hidden or global settings Hide Parent is active, add parent product ID to exclude_ids
            $get_hide_parent_product_meta = get_post_meta( $product_id, '_wvasp_single_hide_parent_product', true );
            $exclude_parent_products   = get_option( 'wvasp_hide_parent_products', 'no' );
            if( $get_hide_parent_product_meta == 'yes' || $exclude_parent_products == 'yes' ) {
                $exclude_ids[] = $product_id;
            }

            // Exclude variant if parent match with excluded category or tag
            $exclude_category_fields = get_option( 'wvasp_exclude_category_fields', array() );
            if ( !empty( $exclude_category_fields ) ) {
                $child_product_cat_ids = $child_product_ids;

                // PRO Freature: For custom category for variant
                foreach( $child_product_ids as $child_product_id ) {
                    $custom_variation_cat = get_post_meta( $child_product_id, '_wvasp_custom_single_variation_cat', true );
                    if( $custom_variation_cat == 'yes' ) {
                        $custom_variation_terms = wp_get_post_terms( $child_product_id, 'product_cat', array( 'fields' => 'ids' ) );
                        if( !empty($custom_variation_terms) && !array_intersect( $custom_variation_terms, $exclude_category_fields ) ) {
                            $child_product_cat_ids = array_diff( $child_product_cat_ids, array( $child_product_id ) );
                        }
                    }
                }

                $product_categories = wp_get_post_terms( $product_id, 'product_cat', array( 'fields' => 'ids' ) );
                if( !empty( $product_categories ) ) {
                    $intersected_categories = array_intersect( $exclude_category_fields, $product_categories );
                    if ( !empty( $intersected_categories ) ) {
                        $exclude_ids = array_unique( array_merge( $exclude_ids, $child_product_cat_ids ) );
                        $exclude_ids = array_diff( $exclude_ids, array( $product_id ) ); // All variant excluded
                    }
                }
            }

            // Exclude variant if parent match with excluded tag
            $exclude_tag_fields = get_option( 'wvasp_exclude_tag_fields', array() );
            if ( !empty( $exclude_tag_fields ) ) {
                $child_product_tag_ids = $child_product_ids;

                // PRO Freature: For custom tag for variant
                foreach( $child_product_ids as $child_product_id ) {
                    $custom_variation_tag = get_post_meta( $child_product_id, '_wvasp_custom_single_variation_tag', true );
                    if( $custom_variation_tag == 'yes' ) {
                        $custom_variation_terms = wp_get_post_terms( $child_product_id, 'product_tag', array( 'fields' => 'ids' ) );
                        if( !empty($custom_variation_terms) && !array_intersect( $custom_variation_terms, $exclude_tag_fields ) ) {
                            $child_product_tag_ids = array_diff( $child_product_tag_ids, array( $child_product_id ) );
                        }
                    }
                }

                $product_tags = wp_get_post_terms( $product_id, 'product_tag', array( 'fields' => 'ids' ) );
                if( !empty( $product_tags ) ) {
                    $intersected_tags = array_intersect( $exclude_tag_fields, $product_tags );
                    if ( !empty( $intersected_tags ) ) {
                        $exclude_ids = array_unique( array_merge( $exclude_ids, $child_product_tag_ids ) );
                        $exclude_ids = array_diff( $exclude_ids, array( $product_id ) ); // All variant excluded
                    }
                }
            }

            // if Main product is excluded from being showing variation with '_wvasp_single_exclude_varations'
            // Add all child to exclude and remove main product id from being excluded
            $get_meta_single_exclude_varations = get_post_meta( $product_id, '_wvasp_single_exclude_varations', true );
            if ( $get_meta_single_exclude_varations == 'yes' ) {
                $exclude_ids = array_unique( array_merge( $exclude_ids, $child_product_ids ) );
                $exclude_ids = array_diff( $exclude_ids, array( $product_id ) );
            }

            // Add metadata '_wvasp_exclude' = 'yes' to all $exclude_ids
            foreach ($exclude_ids as $exclude_id) {
                update_post_meta( $exclude_id, '_wvasp_exclude', 'yes' );
            }

            do_action( 'wvasp_variable_product_exclusion_on_product_update', $product_id );
        }
    }
}



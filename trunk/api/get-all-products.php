<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
function headlesswc_handle_products_request( WP_REST_Request $request ) {
    if (!headlesswc_is_domain_allowed()) {
        return new WP_REST_Response(
            array(
                'success' => false,
                'error' => 'Forbidden: Domain not whitelisted',
            ), 403
        );
    }
    
    $start_timer = microtime( true );
    $args = array(
        'post_type' => 'product',
        'post_status' => 'publish',
        'posts_per_page' => $request->get_param( 'limit' ) ? $request->get_param( 'limit' ) : 24,
        'paged' => $request->get_param( 'page' ) ? $request->get_param( 'page' ) : 1,
        's' => $request->get_param( 'search' ), // Search by product title
        'orderby' => $request->get_param( 'orderby' ) ? $request->get_param( 'orderby' ) : 'date',
        'order' => $request->get_param( 'order' ) ? $request->get_param( 'order' ) : 'DESC',
    );

    // Filter by category
    if ( $request->get_param( 'category' ) ) {
        $args['tax_query'] = array(
            array(
                'taxonomy' => 'product_cat',
                'field' => 'slug',
                'terms' => explode( ',', $request->get_param( 'category' ) ),
            ),
        );
    }

    // Filter by min and max price
    if ( $request->get_param( 'min_price' ) || $request->get_param( 'max_price' ) ) {
        $meta_query = array();
        if ( $request->get_param( 'min_price' ) ) {
            $meta_query[] = array(
                'key' => '_price',
                'value' => $request->get_param( 'min_price' ),
                'compare' => '>=',
                'type' => 'NUMERIC',
            );
        }
        if ( $request->get_param( 'max_price' ) ) {
            $meta_query[] = array(
                'key' => '_price',
                'value' => $request->get_param( 'max_price' ),
                'compare' => '<=',
                'type' => 'NUMERIC',
            );
        }
        $args['meta_query'] = $meta_query;
    }

    $query = new WP_Query( $args );
    $products = array();
    foreach ( $query->posts as $product ) {
        $product = new HWC_Product( wc_get_product( $product->ID ) );
        $product_data = $product->get_data();
        ksort( $product_data );
        $products[] = $product_data;
    }

    return new WP_REST_Response(
        array(
			'success' => true,
			'currentPage' => $args['paged'],
			'executionTime' => microtime( true ) - $start_timer,
			'totalPages' => $query->max_num_pages,
			'totalProducts' => $query->found_posts,
			'data' => $products,
        ), 200
    );
}

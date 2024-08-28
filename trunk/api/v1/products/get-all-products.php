<?php
function headlesswc_handle_products_request(WP_REST_Request $request) {
    $args = array(
        'post_type' => 'product',
        'post_status' => 'publish',
        'posts_per_page' => $request->get_param('limit') ? $request->get_param('limit') : 10,
        'paged' => $request->get_param('page') ? $request->get_param('page') : 1,
        's' => $request->get_param('search'), // Search by product title
        'orderby' => $request->get_param('orderby') ? $request->get_param('orderby') : 'date',
        'order' => $request->get_param('order') ? $request->get_param('order') : 'DESC',
    );

    // Filter by category
    if ( $request->get_param('category') ) {
        $args['tax_query'] = array(
            array(
                'taxonomy' => 'product_cat',
                'field' => 'slug',
                'terms' => explode( ',', $request->get_param('category') ),
            ),
        );
    }

    // Filter by min and max price
    if ( $request->get_param('min_price') || $request->get_param('max_price') ) {
        $meta_query = array();

        if ( $request->get_param('min_price') ) {
            $meta_query[] = array(
                'key' => '_price',
                'value' => $request->get_param('min_price'),
                'compare' => '>=',
                'type' => 'NUMERIC',
            );
        }

        if ( $request->get_param('max_price') ) {
            $meta_query[] = array(
                'key' => '_price',
                'value' => $request->get_param('max_price'),
                'compare' => '<=',
                'type' => 'NUMERIC',
            );
        }

        $args['meta_query'] = $meta_query;
    }

    // Fetch products
    $query = new WP_Query( $args );
    $products = $query->posts;
    $total_products = $query->found_posts;
    $total_pages = $query->max_num_pages;

    $product_data = array();

    foreach ( $products as $product ) {
        $wc_product = wc_get_product( $product->ID );
        $product_data[] = array(
            'id' => $wc_product->get_id(),
            'name' => $wc_product->get_name(),
            'fullImg' => wp_get_attachment_url($wc_product->get_image_id()),
            'permalink' => get_permalink( $wc_product->get_id() ),
            'slug' =>  get_post_field('post_name', $wc_product->get_id()),
            'price' => $wc_product->get_price(),
            'regularPrice' => $wc_product->get_regular_price(),
            'salePrice' => $wc_product->get_sale_price(),
            'isOnsale' => $wc_product->is_on_sale(),
            'stockStatus' => $wc_product->get_stock_status(),
            'shortDescription' => $wc_product->get_short_description(),
            'categories' => wp_get_post_terms( $wc_product->get_id(), 'product_cat', array('fields' => 'names') ),
            'tags' => wp_get_post_terms( $wc_product->get_id(), 'product_tag', array('fields' => 'names') ),
        );
    }

    // Return product data with pagination information
    return new WP_REST_Response( array(
        'success' => true,
        'totalProducts' => $total_products,
        'currency' => get_woocommerce_currency(),
        'currentPage' => $args['paged'],
        'totalPages' => $total_pages,
        'products' => $product_data,
    ), 200 );
}

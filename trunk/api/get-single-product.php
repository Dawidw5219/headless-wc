<?php
if (! defined('ABSPATH')) {
    exit;
}

function headlesswc_handle_product_request(WP_REST_Request $request)
{
    $start_timer = microtime(true);
    $identifier = $request->get_param('slug');
    if (empty($identifier)) {
        return headlesswc_error_response(
            'Nieprawidłowy identyfikator produktu',
            HeadlessWC_Error_Codes::INVALID_PRODUCTS
        );
    }

    if (is_numeric($identifier)) {
        $args = array(
            'p' => intval($identifier),
            'post_type' => 'product',
            'post_status' => 'publish',
            'posts_per_page' => 1,
        );
    } else {
        $args = array(
            'name' => sanitize_title($identifier),
            'post_type' => 'product',
            'post_status' => 'publish',
            'posts_per_page' => 1,
        );
    }

    $query = new WP_Query($args);
    $products = $query->posts;
    if (empty($products)) {
        return headlesswc_error_response(
            'Produkt nie został znaleziony',
            HeadlessWC_Error_Codes::PRODUCT_NOT_FOUND,
            404
        );
    }

    $product = new HWC_Product_Detailed(wc_get_product($products[0]->ID));
    $product_data = $product->get_data();
    ksort($product_data);

    return headlesswc_success_response($product_data + [
        'executionTime' => microtime(true) - $start_timer,
    ]);
}

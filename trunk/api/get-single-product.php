<?php
if (!defined('ABSPATH')) {
    exit;
}

function headlesswc_handle_product_request(WP_REST_Request $request)
{
    $startTimer = microtime(true);
    $identifier = $request->get_param('slug');
    if (empty($identifier)) {
        return new WP_REST_Response(array(
            'success' => false,
            'message' => 'Invalid product identifier',
        ), 400);
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
        return new WP_REST_Response(array(
            'success' => false,
            'message' => 'Product not found',
        ), 404);
    }

    $product = new HWC_Product(wc_get_product($products[0]->ID));
    $product_data = $product->get_detailed_data();
    ksort($product_data);

    return new WP_REST_Response(array(
        'success' => true,
        'execution_time' => microtime(true) - $startTimer,
        'product' => $product_data,
    ), 200);
}


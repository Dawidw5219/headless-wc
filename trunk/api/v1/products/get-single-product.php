<?php
function headlesswc_handle_product_request(WP_REST_Request $request)
{
    $startTimer = microtime(true);
    $identifier = $request->get_param('slug'); // This will now accept both ID and slug

    // Check if the identifier is provided and is not empty
    if (empty($identifier)) {
        return new WP_REST_Response(array(
            'success' => false,
            'message' => 'Invalid product identifier',
        ), 400);
    }

    // Determine if the identifier is numeric (ID) or a slug
    if (is_numeric($identifier)) {
        // Query to get the product by ID
        $args = array(
            'p' => intval($identifier),
            'post_type' => 'product',
            'post_status' => 'publish',
            'posts_per_page' => 1,
        );
    } else {
        // Query to get the product by slug
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

    $product = $products[0];
    $wc_product = wc_get_product($product->ID);
    $product_data = array(
        'id' => $wc_product->get_id(),
        'name' => $wc_product->get_name(),
        'fullImg' => wp_get_attachment_url($wc_product->get_image_id()),
        'images' => headlesswc_get_all_image_sizes($wc_product->get_image_id()),
        'permalink' => get_permalink($wc_product->get_id()),
        'slug' => get_post_field('post_name', $wc_product->get_id()),
        'price' => $wc_product->get_price(),
        'regularPrice' => $wc_product->get_regular_price(),
        'salePrice' => $wc_product->get_sale_price(),
        'isOnsale' => $wc_product->is_on_sale(),
        'stockStatus' => $wc_product->get_stock_status(),
        'excerpt' => $wc_product->get_short_description(),
        'content' => array(
            'rendered' => wp_kses_post($wc_product->get_description()),
            'plain' => wp_strip_all_tags($wc_product->get_description()),
        ),
        'categories' => wp_get_post_terms($wc_product->get_id(), 'product_cat', array('fields' => 'names')),
        'tags' => wp_get_post_terms($wc_product->get_id(), 'product_tag', array('fields' => 'names')),
    );

    return new WP_REST_Response(array(
        'success' => true,
        'product' => $product_data,
        'executionTime' => microtime(true) - $startTimer,
    ), 200);
}

/**
 * Get all available image sizes for an attachment.
 *
 * @param int $attachment_id The attachment ID.
 * @return array An associative array of image sizes with URLs.
 */
if (!function_exists('headlesswc_get_all_image_sizes')) {
    function headlesswc_get_all_image_sizes($attachment_id)
    {
        $sizes = array();
        $imageSizes = get_intermediate_image_sizes();
        foreach ($imageSizes as $size) {
            $image_src = wp_get_attachment_image_src($attachment_id, $size);
            if ($image_src) {
                $sizes[$size] = $image_src[0];
            }
        }
        $sizes['full'] = wp_get_attachment_url($attachment_id);
        return $sizes;
    }
}
<?php
if (! defined('ABSPATH')) {
    exit;
}

/**
 * Wspólna walidacja produktów koszyka dla create-cart.php i create-order.php
 */
function headlesswc_validate_cart_products($cart)
{
    $valid_products = [];
    $debug_info = [];

    if (empty($cart) || !is_array($cart)) {
        return ['products' => $valid_products, 'debug' => ['error' => 'Cart is empty or not array']];
    }

    foreach ($cart as $index => $product) {
        $product_id = 0;
        $quantity = isset($product['quantity']) ? intval($product['quantity']) : 1;
        $variation_id = isset($product['variation_id']) ? intval($product['variation_id']) : 0;
        $variation = isset($product['variation']) ? $product['variation'] : [];

        $item_debug = [
            'index' => $index,
            'input' => $product,
            'quantity' => $quantity,
            'search_method' => 'none'
        ];

        // Sprawdzamy czy produkt został zdefiniowany przez id
        if (isset($product['id']) && !empty($product['id'])) {
            $product_id = intval($product['id']);
            $item_debug['search_method'] = 'by_id';
            $item_debug['search_value'] = $product_id;
        }
        // Jeśli nie ma id, próbujemy znaleźć produkt po slug
        elseif (isset($product['slug']) && !empty($product['slug'])) {
            $item_debug['search_method'] = 'by_slug';
            $item_debug['original_slug'] = $product['slug'];
            $item_debug['sanitized_slug'] = sanitize_title($product['slug']);

            $query_args = array(
                'post_type' => 'product',
                'name' => sanitize_title($product['slug']),
                'posts_per_page' => 1,
                'post_status' => 'publish',
                'fields' => 'ids'
            );
            $products = get_posts($query_args);
            $item_debug['query_args'] = $query_args;
            $item_debug['found_products_count'] = count($products);
            $item_debug['found_products'] = $products;

            if (!empty($products)) {
                $product_id = $products[0];
                $item_debug['resolved_product_id'] = $product_id;
            }
        }

        $item_debug['product_id_after_search'] = $product_id;

        // Sprawdź czy ID produktu jest poprawne
        if ($product_id <= 0) {
            $item_debug['validation_failed'] = 'invalid_product_id';
            $debug_info[] = $item_debug;
            continue;
        }

        // Sprawdź czy ilość jest poprawna  
        if ($quantity <= 0) {
            $item_debug['validation_failed'] = 'invalid_quantity';
            $debug_info[] = $item_debug;
            continue;
        }

        // Sprawdź czy produkt istnieje
        $wc_product = wc_get_product($product_id);
        $item_debug['wc_product_exists'] = ($wc_product && $wc_product->exists());
        if (!$wc_product || !$wc_product->exists()) {
            $item_debug['validation_failed'] = 'product_not_exists';
            $debug_info[] = $item_debug;
            continue;
        }

        // Sprawdź czy produkt jest dostępny do zakupu
        $item_debug['is_purchasable'] = $wc_product->is_purchasable();
        $item_debug['product_status'] = $wc_product->get_status();
        $item_debug['product_type'] = $wc_product->get_type();
        $item_debug['is_virtual'] = $wc_product->is_virtual();
        $item_debug['managing_stock'] = $wc_product->managing_stock();
        $item_debug['is_in_stock'] = $wc_product->is_in_stock();
        $item_debug['stock_status'] = $wc_product->get_stock_status();
        $item_debug['catalog_visibility'] = $wc_product->get_catalog_visibility();

        // Sprawdź czy produkt jest dostępny do zakupu
        if (!$wc_product->is_purchasable()) {
            // Sprawdź czy to problem z wymaganym kontem
            $guest_checkout = get_option('woocommerce_enable_guest_checkout', 'yes');
            $must_be_logged_in = get_option('woocommerce_enable_checkout_login_reminder', 'no');

            $item_debug['validation_failed'] = 'not_purchasable';
            $item_debug['guest_checkout_enabled'] = $guest_checkout;
            $item_debug['must_be_logged_in'] = $must_be_logged_in;
            $item_debug['current_user_id'] = get_current_user_id();
            $item_debug['is_user_logged_in'] = is_user_logged_in();

            // Jeśli problem z wymaganym kontem, zwróć specjalny błąd
            if ($guest_checkout === 'no' && !is_user_logged_in()) {
                $item_debug['validation_failed'] = 'login_required';
            }

            $debug_info[] = $item_debug;
            continue;
        }

        // Produkt jest poprawny - dodaj z wszystkimi parametrami
        $valid_product = [
            'id' => $product_id,
            'quantity' => $quantity
        ];

        // Dodaj variation jeśli istnieje
        if ($variation_id > 0) {
            $valid_product['variation_id'] = $variation_id;
        }
        if (!empty($variation)) {
            $valid_product['variation'] = $variation;
        }

        $item_debug['validation_passed'] = true;
        $item_debug['final_product'] = $valid_product;
        $debug_info[] = $item_debug;
        $valid_products[] = $valid_product;
    }

    return ['products' => $valid_products, 'debug' => $debug_info];
}

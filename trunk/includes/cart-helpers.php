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

    if (empty($cart) || !is_array($cart)) {
        return $valid_products;
    }

    foreach ($cart as $product) {
        $product_id = 0;
        $quantity = isset($product['quantity']) ? intval($product['quantity']) : 1;
        $variation_id = isset($product['variation_id']) ? intval($product['variation_id']) : 0;
        $variation = isset($product['variation']) ? $product['variation'] : [];

        // Sprawdzamy czy produkt został zdefiniowany przez id
        if (isset($product['id']) && !empty($product['id'])) {
            $product_id = intval($product['id']);
        }
        // Jeśli nie ma id, próbujemy znaleźć produkt po slug
        elseif (isset($product['slug']) && !empty($product['slug'])) {
            $query_args = array(
                'post_type' => 'product',
                'name' => sanitize_title($product['slug']),
                'posts_per_page' => 1,
                'post_status' => 'publish',
                'fields' => 'ids'
            );
            $products = get_posts($query_args);
            if (!empty($products)) {
                $product_id = $products[0];
            }
        }

        // Sprawdź czy ID produktu jest poprawne
        if ($product_id <= 0) {
            continue;
        }

        // Sprawdź czy ilość jest poprawna  
        if ($quantity <= 0) {
            continue;
        }

        // Sprawdź czy produkt istnieje
        $wc_product = wc_get_product($product_id);
        if (!$wc_product || !$wc_product->exists()) {
            continue;
        }

        // Sprawdź czy produkt jest dostępny do zakupu
        if (!$wc_product->is_purchasable()) {
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

        $valid_products[] = $valid_product;
    }

    return $valid_products;
}

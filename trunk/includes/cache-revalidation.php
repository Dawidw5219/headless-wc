<?php
if (! defined('ABSPATH')) {
    exit;
}

/**
 * HeadlessWC Cache Revalidation
 * Automatyczne powiadamianie frontendu o zmianach produktów
 */

// Hook do nasłuchiwania zmian produktów
add_action('save_post', 'headlesswc_handle_product_update', 10, 3);
add_action('woocommerce_update_product', 'headlesswc_handle_woocommerce_product_update', 10, 1);
add_action('woocommerce_new_product', 'headlesswc_handle_woocommerce_product_update', 10, 1);

/**
 * Obsługa aktualizacji produktu przez save_post hook
 */
function headlesswc_handle_product_update($post_id, $post, $update)
{
    // Sprawdź czy to jest produkt
    if ($post->post_type !== 'product') {
        return;
    }

    // Sprawdź czy to nie jest autosave lub revision
    if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
        return;
    }

    // Wywołaj rewalidację cache
    headlesswc_trigger_cache_revalidation($post_id);
}

/**
 * Obsługa aktualizacji produktu przez WooCommerce hooks
 */
function headlesswc_handle_woocommerce_product_update($product_id)
{
    headlesswc_trigger_cache_revalidation($product_id);
}

/**
 * Wyzwól rewalidację cache na zewnętrznym URL
 */
function headlesswc_trigger_cache_revalidation($product_id)
{
    // Pobierz URL rewalidacji z ustawień
    $revalidation_url = get_option('headlesswc_cache_revalidation_url', '');

    // Jeśli URL jest pusty, nie rób nic
    if (empty(trim($revalidation_url))) {
        return;
    }

    // Pobierz produkt
    $product = wc_get_product($product_id);
    if (!$product) {
        return;
    }

    // Przygotuj parametry
    $params = array(
        'slug' => $product->get_slug(),
        'id' => $product_id,
        'action' => 'revalidate',
        'type' => 'product'
    );

    // Dodaj parametry do URL
    $request_url = add_query_arg($params, $revalidation_url);

    // Wykonaj asynchroniczny request (nie blokuj zapisywania produktu)
    wp_schedule_single_event(time(), 'headlesswc_cache_revalidation_request', array($request_url, $product_id));
}

/**
 * Hook do wykonania asynchronicznego requesta
 */
add_action('headlesswc_cache_revalidation_request', 'headlesswc_execute_cache_revalidation_request', 10, 2);

function headlesswc_execute_cache_revalidation_request($request_url, $product_id)
{
    // Wykonaj GET request z timeout 5 sekund
    $response = wp_remote_get($request_url, array(
        'timeout' => 5,
        'httpversion' => '1.1',
        'user-agent' => 'HeadlessWC Cache Revalidation',
        'headers' => array(
            'Accept' => 'application/json',
        )
    ));

    // Log rezultatu (opcjonalnie)
    if (is_wp_error($response)) {
        error_log('HeadlessWC Cache Revalidation Error for product ' . $product_id . ': ' . $response->get_error_message());
    } else {
        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code >= 200 && $response_code < 300) {
            error_log('HeadlessWC Cache Revalidation Success for product ' . $product_id . ' (HTTP ' . $response_code . ')');
        } else {
            error_log('HeadlessWC Cache Revalidation Failed for product ' . $product_id . ' (HTTP ' . $response_code . ')');
        }
    }
}

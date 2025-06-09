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

// Hook do wyświetlania alertów o błędach rewalidacji
add_action('admin_notices', 'headlesswc_show_cache_revalidation_errors');

// Hook do czyszczenia błędów
add_action('wp_ajax_headlesswc_dismiss_cache_error', 'headlesswc_dismiss_cache_error');

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

    // Sprawdź rezultat i obsłuż błędy
    if (is_wp_error($response)) {
        $error_message = $response->get_error_message();
        error_log('HeadlessWC Cache Revalidation Error for product ' . $product_id . ': ' . $error_message);
        headlesswc_save_cache_revalidation_error($product_id, 'Connection Error', $error_message, $request_url);
    } else {
        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code >= 200 && $response_code < 300) {
            error_log('HeadlessWC Cache Revalidation Success for product ' . $product_id . ' (HTTP ' . $response_code . ')');
            // Sukces - wyczyść poprzednie błędy dla tego URL
            headlesswc_clear_cache_revalidation_errors();
        } else {
            $response_body = wp_remote_retrieve_body($response);
            error_log('HeadlessWC Cache Revalidation Failed for product ' . $product_id . ' (HTTP ' . $response_code . ')');
            headlesswc_save_cache_revalidation_error($product_id, 'HTTP Error ' . $response_code, $response_body, $request_url);
        }
    }
}

/**
 * Zapisz błąd rewalidacji cache do opcji WordPress
 */
function headlesswc_save_cache_revalidation_error($product_id, $error_type, $error_message, $request_url)
{
    $product = wc_get_product($product_id);
    $product_name = $product ? $product->get_name() : 'Product #' . $product_id;

    $error_data = array(
        'timestamp' => current_time('mysql'),
        'product_id' => $product_id,
        'product_name' => $product_name,
        'error_type' => $error_type,
        'error_message' => $error_message,
        'request_url' => $request_url
    );

    update_option('headlesswc_cache_revalidation_error', $error_data);
}

/**
 * Wyczyść błędy rewalidacji cache
 */
function headlesswc_clear_cache_revalidation_errors()
{
    delete_option('headlesswc_cache_revalidation_error');
}

/**
 * Wyświetl alert o błędach rewalidacji cache w admin
 */
function headlesswc_show_cache_revalidation_errors()
{
    // Sprawdź czy jesteśmy w admin
    if (!is_admin()) {
        return;
    }

    // Pobierz ostatni błąd
    $error_data = get_option('headlesswc_cache_revalidation_error', false);

    if (!$error_data) {
        return;
    }

    // Sprawdź czy błąd nie jest starszy niż 24 godziny
    $error_time = strtotime($error_data['timestamp']);
    if (time() - $error_time > 24 * 60 * 60) {
        headlesswc_clear_cache_revalidation_errors();
        return;
    }

    $error_id = 'headlesswc_cache_error_' . md5($error_data['timestamp']);

?>
    <div class="notice notice-error is-dismissible" data-error-id="<?php echo esc_attr($error_id); ?>">
        <h4>🚨 HeadlessWC Cache Revalidation Error</h4>
        <p>
            <strong>Product:</strong> <?php echo esc_html($error_data['product_name']); ?> (ID: <?php echo esc_html($error_data['product_id']); ?>)<br>
            <strong>Error:</strong> <?php echo esc_html($error_data['error_type']); ?><br>
            <strong>Time:</strong> <?php echo esc_html($error_data['timestamp']); ?><br>
            <strong>URL:</strong> <code><?php echo esc_html($error_data['request_url']); ?></code>
        </p>
        <?php if (!empty($error_data['error_message'])): ?>
            <p><strong>Details:</strong> <code><?php echo esc_html($error_data['error_message']); ?></code></p>
        <?php endif; ?>
        <p>
            <em>Your frontend cache revalidation is not working. Please check your revalidation endpoint.</em>
            <a href="<?php echo admin_url('admin.php?page=headlesswc-settings'); ?>">Go to HeadlessWC Settings</a>
        </p>
    </div>

    <script>
        jQuery(document).ready(function($) {
            $('.notice[data-error-id="<?php echo esc_js($error_id); ?>"] .notice-dismiss').on('click', function() {
                $.post(ajaxurl, {
                    action: 'headlesswc_dismiss_cache_error',
                    nonce: '<?php echo wp_create_nonce('headlesswc_dismiss_cache_error'); ?>'
                });
            });
        });
    </script>
<?php
}

/**
 * AJAX handler dla dismissowania błędów
 */
function headlesswc_dismiss_cache_error()
{
    if (!check_ajax_referer('headlesswc_dismiss_cache_error', 'nonce', false)) {
        wp_die('Security check failed');
    }

    if (!current_user_can('manage_options')) {
        wp_die('Permission denied');
    }

    headlesswc_clear_cache_revalidation_errors();
    wp_die(); // End AJAX request
}

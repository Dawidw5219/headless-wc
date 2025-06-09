<?php
if (! defined('ABSPATH')) {
    exit;
}

/**
 * Centralne zabezpieczenie API dla wszystkich endpointów HeadlessWC
 * Automatycznie sprawdza uprawnienia bez potrzeby dodawania tego do każdego endpointa
 */

// Hook into REST API to check permissions for all HeadlessWC endpoints
add_filter('rest_pre_dispatch', 'headlesswc_check_api_permissions', 10, 3);

/**
 * Sprawdź uprawnienia dla wszystkich endpointów HeadlessWC
 * 
 * @param mixed $result
 * @param WP_REST_Server $server
 * @param WP_REST_Request $request
 * @return mixed
 */
function headlesswc_check_api_permissions($result, $server, $request)
{
    // Sprawdź czy to jest nasz endpoint HeadlessWC
    $route = $request->get_route();

    if (strpos($route, '/headless-wc/') === false) {
        // To nie nasz endpoint, pozwól przejść dalej
        return $result;
    }

    // To nasz endpoint - sprawdź uprawnienia domenowe
    if (!headlesswc_is_domain_allowed()) {
        return new WP_REST_Response(
            array(
                'success' => false,
                'error' => 'Forbidden: Domain not whitelisted',
                'code' => 'domain_not_allowed'
            ),
            403
        );
    }

    // Uprawnienia OK - pozwól przejść dalej
    return $result;
}

/**
 * Dodatkowe zabezpieczenie - sprawdź czy WooCommerce jest aktywny
 * dla wszystkich naszych endpointów
 */
add_filter('rest_pre_dispatch', 'headlesswc_check_woocommerce_active', 5, 3);

function headlesswc_check_woocommerce_active($result, $server, $request)
{
    // Sprawdź czy to jest nasz endpoint HeadlessWC
    $route = $request->get_route();

    if (strpos($route, '/headless-wc/') === false) {
        // To nie nasz endpoint, pozwól przejść dalej
        return $result;
    }

    // Sprawdź czy WooCommerce jest aktywny
    if (!class_exists('WooCommerce')) {
        return new WP_REST_Response(
            array(
                'success' => false,
                'error' => 'WooCommerce is required but not active',
                'code' => 'woocommerce_not_active'
            ),
            503
        );
    }

    // WooCommerce jest aktywny - pozwól przejść dalej
    return $result;
}

/**
 * Logowanie prób dostępu do API (opcjonalne - tylko gdy włączone w ustawieniach)
 */
add_action('rest_pre_dispatch', 'headlesswc_log_api_access', 15, 3);

function headlesswc_log_api_access($result, $server, $request)
{
    // Sprawdź czy to jest nasz endpoint HeadlessWC
    $route = $request->get_route();

    if (strpos($route, '/headless-wc/') === false) {
        return;
    }

    // Sprawdź czy logowanie jest włączone (można dodać do ustawień)
    if (get_option('headlesswc_enable_api_logging', 'no') !== 'yes') {
        return;
    }

    // Loguj próbę dostępu
    $log_data = array(
        'timestamp' => current_time('mysql'),
        'route' => $route,
        'method' => $request->get_method(),
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        'origin' => $_SERVER['HTTP_ORIGIN'] ?? $_SERVER['HTTP_REFERER'] ?? 'unknown'
    );

    // Zapisz log (można rozszerzyć o zapis do bazy danych)
    error_log('HeadlessWC API Access: ' . json_encode($log_data));
}

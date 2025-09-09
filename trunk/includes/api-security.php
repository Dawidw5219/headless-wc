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
        // Logowanie jest już obsługiwane w funkcji headlesswc_is_domain_allowed()
        return headlesswc_error_response(
            __('Access denied: Domain is not whitelisted', 'headless-wc'),
            HeadlessWC_Error_Codes::DOMAIN_NOT_ALLOWED,
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
        return headlesswc_error_response(
            __('WooCommerce is required but not active', 'headless-wc'),
            HeadlessWC_Error_Codes::WOOCOMMERCE_NOT_ACTIVE,
            503
        );
    }

    // WooCommerce jest aktywny - pozwól przejść dalej
    return $result;
}

/**
 * Globalne nagłówki CORS dla całego WP REST API (`/wp-json/...`)
 * Zasada:
 * - jeśli whitelist jest pusta → allow all (`*`) + brak credendials
 * - jeśli whitelist niepusta → tylko dozwolone domeny z Origin, z credendials
 */
add_filter('rest_send_cors_headers', 'headlesswc_send_global_cors_headers', 10, 2);

function headlesswc_send_global_cors_headers($headers, $request)
{
    // Ogranicz do core WP REST API i namespace wtyczki; nie dotykaj innych pluginów
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    $is_wp_core = strpos($uri, '/wp-json/wp/') !== false || rtrim($uri, '/') === '/wp-json';
    $is_plugin_ns = strpos($uri, '/wp-json/headless-wc/') !== false;
    if (! $is_wp_core && ! $is_plugin_ns) {
        return $headers;
    }

    // Domyślne nagłówki dopełniające
    $headers['Access-Control-Allow-Methods'] = 'GET, POST, PUT, PATCH, DELETE, OPTIONS';
    // Jeśli preflight podał listę nagłówków, przepuść ją; w innym wypadku użyj rozszerzonej listy domyślnej
    $requested_headers = $_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS'] ?? '';
    $headers['Access-Control-Allow-Headers'] = $requested_headers ? $requested_headers : 'Authorization, Content-Type, X-WP-Nonce, X-Requested-With, Origin, Accept, Referer, User-Agent, Cache-Control, Pragma';

    $whitelist_raw = get_option('headlesswc_domain_whitelist', '');
    $origin = function_exists('get_http_origin') ? get_http_origin() : ($_SERVER['HTTP_ORIGIN'] ?? '');

    // Pusta whitelist → pełne otwarcie; odbijaj Origin i zezwól na credentials jeśli podano Origin
    if (empty(trim($whitelist_raw))) {
        if (!empty($origin)) {
            $headers['Access-Control-Allow-Origin'] = $origin;
            $headers['Access-Control-Allow-Credentials'] = 'true';
        } else {
            $headers['Access-Control-Allow-Origin'] = '*';
            $headers['Access-Control-Allow-Credentials'] = 'false';
        }
        // Vary: Origin dla poprawnego cache przez CDN/browsers
        $headers['Vary'] = isset($headers['Vary']) && $headers['Vary'] ? $headers['Vary'] . ', Origin' : 'Origin';
        return $headers;
    }

    // Whitelist niepusta → zezwalaj tylko dla dozwolonych domen
    // Wykorzystujemy istniejącą logikę walidującą domenę (Origin/Referer/IP)
    if (!empty($origin) && headlesswc_is_domain_allowed()) {
        $headers['Access-Control-Allow-Origin'] = $origin;
        $headers['Access-Control-Allow-Credentials'] = 'true';
        $headers['Vary'] = isset($headers['Vary']) && $headers['Vary'] ? $headers['Vary'] . ', Origin' : 'Origin';
    }

    return $headers;
}

/**
 * Obsługa preflight OPTIONS dla całego `/wp-json/...`
 * Zwraca 200 z odpowiednimi nagłówkami CORS, bez ciała.
 */
add_action('init', 'headlesswc_handle_cors_preflight');

function headlesswc_handle_cors_preflight()
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'OPTIONS') {
        return;
    }

    $request_uri = $_SERVER['REQUEST_URI'] ?? '';
    // Tylko WP core i namespace wtyczki
    $is_wp_core = strpos($request_uri, '/wp-json/wp/') !== false || rtrim($request_uri, '/') === '/wp-json';
    $is_plugin_ns = strpos($request_uri, '/wp-json/headless-wc/') !== false;
    if (! $is_wp_core && ! $is_plugin_ns) {
        return;
    }

    // Zbuduj nagłówki wykorzystując tę samą funkcję co dla odpowiedzi REST
    $headers = headlesswc_send_global_cors_headers([], null);
    foreach ($headers as $name => $value) {
        header($name . ': ' . $value);
    }

    // Zwróć 200 i zakończ szybko
    if (function_exists('status_header')) {
        status_header(200);
    }
    header('Content-Length: 0');
    exit;
}

/**
 * Wymuszenie CORS także na etapie serwowania odpowiedzi REST,
 * aby mieć pewność, że nagłówki trafią do odpowiedzi w WP Core i naszej wtyczce.
 */
add_filter('rest_pre_serve_request', 'headlesswc_force_cors_headers', 0, 4);

function headlesswc_force_cors_headers($served, $result, $request, $server)
{
    $route = $request instanceof WP_REST_Request ? $request->get_route() : '';
    $is_wp_core = is_string($route) && strpos($route, '/wp/') === 0;
    $is_plugin_ns = is_string($route) && strpos($route, '/headless-wc/') === 0;
    if (! $is_wp_core && ! $is_plugin_ns) {
        return $served;
    }

    $whitelist_raw = get_option('headlesswc_domain_whitelist', '');
    $origin = function_exists('get_http_origin') ? get_http_origin() : ($_SERVER['HTTP_ORIGIN'] ?? '');

    // Dopełniające nagłówki
    header('Access-Control-Expose-Headers: X-WP-Total, X-WP-TotalPages, Link');
    $requested_headers = $_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS'] ?? '';
    header('Access-Control-Allow-Headers: ' . ($requested_headers ? $requested_headers : 'Authorization, Content-Type, X-WP-Nonce, X-Requested-With, Origin, Accept, Referer, User-Agent, Cache-Control, Pragma'));
    header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');

    if (empty(trim($whitelist_raw))) {
        if (!empty($origin)) {
            header('Access-Control-Allow-Origin: ' . $origin);
            header('Access-Control-Allow-Credentials: true');
        } else {
            header('Access-Control-Allow-Origin: *');
            header('Access-Control-Allow-Credentials: false');
        }
        header('Vary: Origin', false);
        return $served;
    }

    if (!empty($origin) && headlesswc_is_domain_allowed()) {
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Access-Control-Allow-Credentials: true');
        header('Vary: Origin', false);
    }

    return $served;
}

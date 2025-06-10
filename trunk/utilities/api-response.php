<?php
if (! defined('ABSPATH')) {
    exit;
}

/**
 * Standardowe funkcje do tworzenia responsów API
 */

/**
 * Tworzy response sukcesu
 * 
 * @param array $data Dane do zwrócenia
 * @param int $status_code HTTP status code (domyślnie 200)
 * @return WP_REST_Response
 */
function headlesswc_success_response($data = [], $status_code = 200)
{
    $response_data = array_merge(['success' => true], $data);
    return new WP_REST_Response($response_data, $status_code);
}

/**
 * Tworzy response błędu
 * 
 * @param string $message Wiadomość błędu dla użytkownika (po ludzku)
 * @param string $error_code Kod błędu dla programistów (snake_case)
 * @param int $status_code HTTP status code (domyślnie 400)
 * @param array $additional_data Dodatkowe dane do zwrócenia
 * @return WP_REST_Response
 */
function headlesswc_error_response($message, $error_code, $status_code = 400, $additional_data = [])
{
    $response_data = array_merge([
        'success' => false,
        'message' => $message,
        'error' => $error_code
    ], $additional_data);

    return new WP_REST_Response($response_data, $status_code);
}

/**
 * Standardowe kody błędów
 */
class HeadlessWC_Error_Codes
{
    const CART_EMPTY = 'cart_empty';
    const CART_INVALID = 'cart_invalid';
    const INVALID_PRODUCTS = 'invalid_products';
    const NO_VALID_PRODUCTS = 'no_valid_products';
    const REDIRECT_URL_REQUIRED = 'redirect_url_required';
    const INVALID_SHIPPING_METHOD = 'invalid_shipping_method';
    const INVALID_PAYMENT_METHOD = 'invalid_payment_method';
    const NO_PAYMENT_METHODS = 'no_payment_methods';
    const PRODUCT_NOT_FOUND = 'product_not_found';
    const ORDER_NOT_FOUND = 'order_not_found';
    const INVALID_ORDER_KEY = 'invalid_order_key';
    const MISSING_ORDER_DATA = 'missing_order_data';
    const UNEXPECTED_ERROR = 'unexpected_error';
    const DOMAIN_NOT_ALLOWED = 'domain_not_allowed';
    const WOOCOMMERCE_NOT_ACTIVE = 'woocommerce_not_active';
}

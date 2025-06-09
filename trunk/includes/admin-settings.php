<?php
if (! defined('ABSPATH')) {
    exit;
}

// Dodaj hook'i WooCommerce z późniejszym priorytetem aby WooCommerce był już załadowany
add_action('init', 'headlesswc_init_admin_settings', 20);

function headlesswc_init_admin_settings()
{
    // Sprawdź czy WooCommerce jest aktywny
    if (!class_exists('WooCommerce')) {
        return;
    }

    // Dodaj hook'i tylko gdy WooCommerce jest już załadowany
    add_filter('woocommerce_settings_tabs_array', 'headlesswc_add_settings_tab', 50);
    add_action('woocommerce_settings_tabs_headlesswc', 'headlesswc_settings_tab');
    add_action('woocommerce_update_options_headlesswc', 'headlesswc_update_settings');
}

/**
 * Dodaj zakładkę HeadlessWC do WooCommerce Settings
 */
function headlesswc_add_settings_tab($settings_tabs)
{
    $settings_tabs['headlesswc'] = __('HeadlessWC', 'headless-wc');
    return $settings_tabs;
}

/**
 * Wyświetl ustawienia HeadlessWC
 */
function headlesswc_settings_tab()
{
    woocommerce_admin_fields(headlesswc_get_settings());
}

/**
 * Zapisz ustawienia HeadlessWC
 */
function headlesswc_update_settings()
{
    woocommerce_update_options(headlesswc_get_settings());
}

/**
 * Pobierz wszystkie ustawienia HeadlessWC
 */
function headlesswc_get_settings()
{
    $settings = array(
        array(
            'name' => __('HeadlessWC Settings', 'headless-wc'),
            'type' => 'title',
            'desc' => __('Configure HeadlessWC for your headless eCommerce setup.', 'headless-wc'),
            'id'   => 'headlesswc_settings'
        ),

        array(
            'name' => __('Security Settings', 'headless-wc'),
            'type' => 'title',
            'desc' => __('Configure security settings for API access.', 'headless-wc'),
            'id'   => 'headlesswc_security_settings'
        ),

        array(
            'name'     => __('Domain Whitelist', 'headless-wc'),
            'type'     => 'textarea',
            'desc'     => __('Enter allowed domains one per line (e.g., example.com). Leave empty to allow all domains.', 'headless-wc'),
            'id'       => 'headlesswc_domain_whitelist',
            'css'      => 'height: 120px;',
            'default'  => '',
            'desc_tip' => __('Only domains listed here will be able to access HeadlessWC API endpoints. For security, add only your trusted frontend domains.', 'headless-wc'),
        ),

        array(
            'name' => __('COD (Cash on Delivery) Settings', 'headless-wc'),
            'type' => 'title',
            'desc' => __('Configure how COD orders are handled in headless mode.', 'headless-wc'),
            'id'   => 'headlesswc_cod_settings'
        ),

        array(
            'name'     => __('Auto-confirm COD Orders', 'headless-wc'),
            'type'     => 'checkbox',
            'desc'     => __('Automatically confirm COD orders and redirect users immediately', 'headless-wc'),
            'id'       => 'headlesswc_auto_confirm_cod',
            'default'  => 'yes',
            'desc_tip' => __('When enabled, COD orders will be automatically set to "processing" status and users will be redirected instantly without page loading delays.', 'headless-wc'),
        ),

        array(
            'name' => __('API Settings', 'headless-wc'),
            'type' => 'title',
            'desc' => __('Configure API behavior and responses.', 'headless-wc'),
            'id'   => 'headlesswc_api_settings'
        ),

        array(
            'name'     => __('Include Order Key in Redirects', 'headless-wc'),
            'type'     => 'checkbox',
            'desc'     => __('Always include order ID and order key in redirect URLs for secure order verification', 'headless-wc'),
            'id'       => 'headlesswc_include_order_key',
            'default'  => 'yes',
            'desc_tip' => __('When enabled, all redirect URLs will include ?order=123&key=wc_order_xyz for secure order verification in your frontend.', 'headless-wc'),
        ),

        array(
            'type' => 'sectionend',
            'id'   => 'headlesswc_settings'
        ),

        // Status section
        array(
            'name' => __('Status & Information', 'headless-wc'),
            'type' => 'title',
            'desc' => __('Current plugin status and information.', 'headless-wc'),
            'id'   => 'headlesswc_status_settings'
        ),

        array(
            'name' => __('Plugin Version', 'headless-wc'),
            'type' => 'text',
            'id'   => 'headlesswc_version_display',
            'default' => '1.1.7',
            'custom_attributes' => array('readonly' => 'readonly'),
            'desc' => __('Current HeadlessWC plugin version', 'headless-wc'),
        ),

        array(
            'name' => __('API Base URL', 'headless-wc'),
            'type' => 'text',
            'id'   => 'headlesswc_api_url_display',
            'default' => site_url('/wp-json/headless-wc/v1/'),
            'custom_attributes' => array('readonly' => 'readonly'),
            'desc' => __('Base URL for HeadlessWC API endpoints', 'headless-wc'),
        ),

        array(
            'type' => 'sectionend',
            'id'   => 'headlesswc_status_settings'
        )
    );

    return apply_filters('headlesswc_settings', $settings);
}

/**
 * Sprawdź czy domena jest dozwolona (zachowana funkcjonalność)
 */
function headlesswc_is_domain_allowed()
{
    $whitelist = get_option('headlesswc_domain_whitelist', '');

    // If whitelist is empty, allow all domains
    if (empty(trim($whitelist))) {
        return true;
    }

    $allowed_domains = array_filter(array_map('trim', explode("\n", $whitelist)));
    $request_origin = $_SERVER['HTTP_ORIGIN'] ?? $_SERVER['HTTP_REFERER'] ?? '';

    if (empty($request_origin)) {
        return false;
    }

    $request_domain = parse_url($request_origin, PHP_URL_HOST);

    foreach ($allowed_domains as $domain) {
        if ($request_domain === $domain || substr($request_domain, - (strlen($domain) + 1)) === '.' . $domain) {
            return true;
        }
    }

    return false;
}

/**
 * Sprawdź czy auto-potwierdzanie COD jest włączone
 */
function headlesswc_is_auto_confirm_cod_enabled()
{
    return get_option('headlesswc_auto_confirm_cod', 'yes') === 'yes';
}

/**
 * Sprawdź czy dołączanie klucza zamówienia jest włączone
 */
function headlesswc_is_include_order_key_enabled()
{
    return get_option('headlesswc_include_order_key', 'yes') === 'yes';
}

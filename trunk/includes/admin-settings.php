<?php
if (! defined('ABSPATH')) {
    exit;
}

add_action('admin_menu', 'headlesswc_add_settings_page');
add_action('admin_init', 'headlesswc_settings_init');

function headlesswc_add_settings_page() {
    add_menu_page(
        'HeadlessWC Settings',
        'HeadlessWC',
        'manage_options',
        'headlesswc-settings',
        'headlesswc_settings_page',
        'dashicons-rest-api',
        30
    );
}

function headlesswc_settings_init() {
    register_setting('headlesswc_settings', 'headlesswc_domain_whitelist');
    
    add_settings_section(
        'headlesswc_security_section',
        'Security Settings',
        'headlesswc_security_section_callback',
        'headlesswc_settings'
    );
    
    add_settings_field(
        'headlesswc_domain_whitelist',
        'Domain Whitelist',
        'headlesswc_domain_whitelist_callback',
        'headlesswc_settings',
        'headlesswc_security_section'
    );
}

function headlesswc_security_section_callback() {
    echo '<p>Configure security settings for HeadlessWC API access.</p>';
}

function headlesswc_domain_whitelist_callback() {
    $value = get_option('headlesswc_domain_whitelist', '');
    echo '<textarea name="headlesswc_domain_whitelist" rows="5" cols="50" class="large-text">' . esc_textarea($value) . '</textarea>';
    echo '<p class="description">Enter allowed domains one per line (e.g., example.com). Leave empty to allow all domains.</p>';
}

function headlesswc_settings_page() {
    ?>
    <div class="wrap">
        <h1>HeadlessWC Settings</h1>
        <form method="post" action="options.php">
            <?php
            settings_fields('headlesswc_settings');
            do_settings_sections('headlesswc_settings');
            submit_button();
            ?>
        </form>
    </div>
    <?php
}

function headlesswc_is_domain_allowed() {
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
        if ($request_domain === $domain || substr($request_domain, -(strlen($domain) + 1)) === '.' . $domain) {
            return true;
        }
    }
    
    return false;
}
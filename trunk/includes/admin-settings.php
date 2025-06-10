<?php
if (! defined('ABSPATH')) {
    exit;
}

// Dodaj submenu WooCommerce po załadowaniu WooCommerce
add_action('admin_menu', 'headlesswc_add_submenu', 60);

function headlesswc_add_submenu()
{
    // Sprawdź czy WooCommerce jest aktywny
    if (!class_exists('WooCommerce')) {
        return;
    }

    // Dodaj submenu do WooCommerce
    add_submenu_page(
        'woocommerce',
        __('HeadlessWC Settings', 'headless-wc'),
        __('HeadlessWC', 'headless-wc'),
        'manage_options',
        'headlesswc-settings',
        'headlesswc_settings_page'
    );
}

// Hook dla zapisywania ustawień
add_action('admin_init', 'headlesswc_register_settings');

function headlesswc_register_settings()
{
    register_setting('headlesswc_settings', 'headlesswc_domain_whitelist');
    register_setting('headlesswc_settings', 'headlesswc_cache_revalidation_url');

    add_settings_section(
        'headlesswc_security_section',
        __('Security Settings', 'headless-wc'),
        'headlesswc_security_section_callback',
        'headlesswc_settings'
    );

    add_settings_field(
        'headlesswc_domain_whitelist',
        __('Domain Whitelist', 'headless-wc'),
        'headlesswc_domain_whitelist_callback',
        'headlesswc_settings',
        'headlesswc_security_section'
    );

    add_settings_section(
        'headlesswc_cache_section',
        __('Cache & Performance Settings', 'headless-wc'),
        'headlesswc_cache_section_callback',
        'headlesswc_settings'
    );

    add_settings_field(
        'headlesswc_cache_revalidation_url',
        __('Cache Revalidation URL', 'headless-wc'),
        'headlesswc_cache_revalidation_url_callback',
        'headlesswc_settings',
        'headlesswc_cache_section'
    );
}

function headlesswc_security_section_callback()
{
    echo '<p>' . __('Configure security settings for HeadlessWC API access.', 'headless-wc') . '</p>';
}

function headlesswc_cache_section_callback()
{
    echo '<p>' . __('Configure cache management and performance optimization for your headless frontend.', 'headless-wc') . '</p>';
}

function headlesswc_domain_whitelist_callback()
{
    $value = get_option('headlesswc_domain_whitelist', '');
    echo '<textarea name="headlesswc_domain_whitelist" rows="5" cols="50" class="large-text">' . esc_textarea($value) . '</textarea>';
    echo '<p class="description">' . __('Enter allowed domains or IP addresses one per line (e.g., example.com or 83.25.131.249). Always specify the exact domains/IPs that will access this API for security. Leaving this field empty allows ALL domains - use only for testing!', 'headless-wc') . '</p>';
    echo '<p class="description"><strong>' . __('Supported formats:', 'headless-wc') . '</strong></p>';
    echo '<ul style="margin-left: 20px;">';
    echo '<li><code>example.com</code> - ' . __('allows subdomains like test.example.com', 'headless-wc') . '</li>';
    echo '<li><code>sub.example.com</code> - ' . __('allows only exact subdomain', 'headless-wc') . '</li>';
    echo '<li><code>192.168.1.100</code> - ' . __('allows specific IP address', 'headless-wc') . '</li>';
    echo '</ul>';
}

function headlesswc_cache_revalidation_url_callback()
{
    $value = get_option('headlesswc_cache_revalidation_url', '');
    echo '<input type="url" name="headlesswc_cache_revalidation_url" value="' . esc_attr($value) . '" class="large-text" placeholder="https://yourapp.com/api/revalidate" />';
    echo '<p class="description">' . __('Optional: URL endpoint for cache revalidation. When a product is updated, HeadlessWC will automatically call this URL with product details to trigger cache refresh in your frontend application.', 'headless-wc') . '</p>';
    echo '<p class="description"><strong>' . __('How it works:', 'headless-wc') . '</strong> ' . __('After any product change, a GET request will be sent to your URL with query parameters: <code>?slug=product-slug&id=123&type=product</code>', 'headless-wc') . '</p>';
    echo '<p class="description"><strong>' . __('Use case:', 'headless-wc') . '</strong> ' . __('Perfect for Next.js ISR (Incremental Static Regeneration), Gatsby, or any frontend that supports on-demand cache revalidation.', 'headless-wc') . '</p>';
    echo '<p class="description"><strong>' . __('Leave empty to disable this feature.', 'headless-wc') . '</strong></p>';
}

function headlesswc_settings_page()
{
?>
    <div class="wrap">
        <h1><?php echo __('HeadlessWC Settings', 'headless-wc'); ?></h1>
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

/**
 * Sprawdź czy domena jest dozwolona
 */
function headlesswc_is_domain_allowed()
{
    $whitelist = get_option('headlesswc_domain_whitelist', '');

    // If whitelist is empty, allow all domains (no restrictions)
    if (empty(trim($whitelist))) {
        return true;
    }

    // Get request domain
    $request_origin = $_SERVER['HTTP_ORIGIN'] ?? $_SERVER['HTTP_REFERER'] ?? '';

    // If no origin/referer header and whitelist is set, deny access
    if (empty($request_origin)) {
        return false;
    }

    $request_domain = parse_url($request_origin, PHP_URL_HOST);
    if (!$request_domain) {
        return false;
    }

    // Always allow WordPress site domain (exact match only)
    $site_url = get_site_url();
    $site_domain = parse_url($site_url, PHP_URL_HOST);
    if ($site_domain && $request_domain === $site_domain) {
        return true;
    }

    // Process whitelist domains
    $allowed_domains = array_filter(array_map('trim', explode("\n", $whitelist)));

    // Check if request domain matches any allowed domain
    foreach ($allowed_domains as $domain) {
        $domain = trim($domain);
        if (empty($domain)) {
            continue;
        }

        // Exact match
        if ($request_domain === $domain) {
            return true;
        }

        // If whitelist domain has no subdomain (only 2 parts: domain.com), allow subdomains
        $domain_parts = explode('.', $domain);
        if (count($domain_parts) <= 2) {
            if (substr($request_domain, - (strlen($domain) + 1)) === '.' . $domain) {
                return true;
            }
        }
    }

    return false;
}

/**
 * Auto-potwierdzanie COD jest zawsze włączone
 */
function headlesswc_is_auto_confirm_cod_enabled()
{
    return true; // Zawsze włączone
}

/**
 * Dołączanie klucza zamówienia jest zawsze włączone
 */
function headlesswc_is_include_order_key_enabled()
{
    return true; // Zawsze włączone
}

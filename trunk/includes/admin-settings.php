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
    echo '<li><code>192.168.1.100</code> - ' . __('allows specific IPv4 address', 'headless-wc') . '</li>';
    echo '<li><code>::1</code> - ' . __('allows specific IPv6 address', 'headless-wc') . '</li>';
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

    // Get request domain and IP
    $request_origin = $_SERVER['HTTP_ORIGIN'] ?? $_SERVER['HTTP_REFERER'] ?? '';
    $request_domain = '';
    if (!empty($request_origin)) {
        $request_domain = parse_url($request_origin, PHP_URL_HOST);
    }

    // Get client IP address
    $client_ip = headlesswc_get_client_ip();

    // If no origin/referer header and no IP, deny access
    if (empty($request_domain) && empty($client_ip)) {
        headlesswc_log_access_denied(
            'No domain or IP information available',
            $client_ip,
            $request_domain,
            $_SERVER['REQUEST_URI'] ?? ''
        );
        return false;
    }

    // Always allow WordPress site domain (exact match only)
    $site_url = get_site_url();
    $site_domain = parse_url($site_url, PHP_URL_HOST);
    if ($site_domain && $request_domain === $site_domain) {
        return true;
    }

    // Always allow localhost requests (development)
    if (headlesswc_is_localhost_request($request_domain, $client_ip)) {
        return true;
    }

    // Check whitelist entries (domains and IPs)
    $allowed_entries = array_filter(array_map('trim', explode("\n", $whitelist)));

    foreach ($allowed_entries as $entry) {
        $entry = trim($entry);
        if (empty($entry)) {
            continue;
        }

        // Check if entry is an IP address or localhost variant
        if (filter_var($entry, FILTER_VALIDATE_IP) || in_array($entry, ['localhost'])) {
            // It's an IP or localhost - check match
            if ($client_ip === $entry) {
                return true;
            }

            // Special handling for localhost variants
            if ($entry === 'localhost' && headlesswc_is_localhost_request($request_domain, $client_ip)) {
                return true;
            }

            // IPv4/IPv6 localhost cross-compatibility
            if (($entry === '127.0.0.1' && $client_ip === '::1') ||
                ($entry === '::1' && $client_ip === '127.0.0.1')
            ) {
                return true;
            }
        } else {
            // It's a domain - use existing domain logic
            if (!empty($request_domain)) {
                // Exact match
                if ($request_domain === $entry) {
                    return true;
                }

                // If whitelist domain has no subdomain (only 2 parts: domain.com), allow subdomains
                $domain_parts = explode('.', $entry);
                if (count($domain_parts) <= 2) {
                    if (substr($request_domain, - (strlen($entry) + 1)) === '.' . $entry) {
                        return true;
                    }
                }
            }
        }
    }

    // Loguj nieudaną próbę dostępu (ale nie localhost)
    if (!headlesswc_is_localhost_request($request_domain, $client_ip)) {
        headlesswc_log_access_denied(
            'Domain/IP not in whitelist',
            $client_ip,
            $request_domain,
            $_SERVER['REQUEST_URI'] ?? ''
        );
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

/**
 * Pobierz IP klienta z różnych nagłówków
 */
function headlesswc_get_client_ip()
{
    // Lista nagłówków do sprawdzenia (w kolejności priorytetów)
    $ip_headers = [
        'HTTP_X_REAL_IP',
        'HTTP_X_FORWARDED_FOR',
        'HTTP_CLIENT_IP',
        'REMOTE_ADDR'
    ];

    foreach ($ip_headers as $header) {
        if (!empty($_SERVER[$header])) {
            $ips = explode(',', $_SERVER[$header]);
            $ip = trim($ips[0]);

            // Sprawdź czy to poprawny IP (publiczny lub prywatny)
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
    }

    return '';
}

/**
 * Sprawdź czy request pochodzi z localhost
 */
function headlesswc_is_localhost_request($request_domain, $client_ip)
{
    // Lista wszystkich wariantów localhost
    $localhost_domains = ['localhost', '127.0.0.1', '::1'];
    $localhost_ips = ['127.0.0.1', '::1', '0.0.0.0'];

    // Sprawdź domenę
    if (!empty($request_domain) && in_array($request_domain, $localhost_domains)) {
        return true;
    }

    // Sprawdź IP
    if (!empty($client_ip)) {
        // Sprawdź czy to localhost IP
        if (in_array($client_ip, $localhost_ips)) {
            return true;
        }

        // Sprawdź czy to lokalny zakres IP (192.168.x.x, 10.x.x.x, 172.16-31.x.x)
        if (
            filter_var($client_ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_RES_RANGE) &&
            !filter_var($client_ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE)
        ) {
            return true;
        }
    }

    return false;
}

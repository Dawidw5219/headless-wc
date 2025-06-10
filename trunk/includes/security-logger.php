<?php
if (! defined('ABSPATH')) {
    exit;
}

/**
 * Stała do włączania/wyłączania logowania zabezpieczeń
 * Ustaw na false żeby całkowicie wyłączyć logowanie
 */
define('HEADLESSWC_ENABLE_SECURITY_LOGGING', true);

/**
 * Logger dla nieudanych prób dostępu do API (403)
 */

/**
 * Loguj nieudaną próbę dostępu
 * 
 * @param string $reason Powód odrzucenia dostępu
 * @param string $client_ip IP klienta
 * @param string $request_domain Domena z nagłówka Origin/Referer
 * @param string $route Endpoint do którego próbowano się dostać
 */
function headlesswc_log_access_denied($reason, $client_ip = '', $request_domain = '', $route = '')
{
    // Sprawdź czy logowanie jest włączone
    if (!headlesswc_is_security_logging_enabled()) {
        return;
    }

    $log_file = headlesswc_get_security_log_file();

    // Przygotuj dane do logu
    $timestamp = current_time('Y-m-d H:i:s');
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
    $referer = $_SERVER['HTTP_REFERER'] ?? '';
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

    // Format: [TIMESTAMP] REASON | IP: xxx.xxx.xxx.xxx | Domain: example.com | Route: /wp-json/headless-wc/v1/cart | UA: ... | Referer: ...
    $log_entry = sprintf(
        "[%s] %s | IP: %s | Domain: %s | Route: %s | UA: %s | Origin: %s | Referer: %s\n",
        $timestamp,
        $reason,
        $client_ip ?: 'Unknown',
        $request_domain ?: 'Unknown',
        $route ?: 'Unknown',
        $user_agent,
        $origin,
        $referer
    );

    // Zapisz do pliku
    error_log($log_entry, 3, $log_file);

    // Opcjonalnie wyślij też do standardowego logu WordPressa
    if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
        error_log('HeadlessWC Access Denied: ' . $reason . ' | IP: ' . $client_ip . ' | Domain: ' . $request_domain);
    }
}

/**
 * Sprawdź czy logowanie zabezpieczeń jest włączone
 */
function headlesswc_is_security_logging_enabled()
{
    // Używaj stałej do kontroli logowania
    return defined('HEADLESSWC_ENABLE_SECURITY_LOGGING') && HEADLESSWC_ENABLE_SECURITY_LOGGING === true;
}

/**
 * Pobierz ścieżkę do pliku logów zabezpieczeń
 */
function headlesswc_get_security_log_file()
{
    $upload_dir = wp_upload_dir();
    $log_dir = $upload_dir['basedir'] . '/headlesswc-logs';

    // Utwórz katalog jeśli nie istnieje
    if (!file_exists($log_dir)) {
        wp_mkdir_p($log_dir);

        // Dodaj .htaccess żeby chronić logi przed dostępem z przeglądarki
        $htaccess_content = "deny from all\n";
        file_put_contents($log_dir . '/.htaccess', $htaccess_content);

        // Dodaj index.php żeby chronić przed listowaniem katalogów
        file_put_contents($log_dir . '/index.php', '<?php // Silence is golden');
    }

    return $log_dir . '/security-' . date('Y-m') . '.log';
}

/**
 * Wyczyść stare logi (starsze niż 3 miesiące)
 */
function headlesswc_cleanup_old_security_logs()
{
    // Sprawdź czy logowanie jest włączone przed czyszczeniem
    if (!headlesswc_is_security_logging_enabled()) {
        return;
    }

    $upload_dir = wp_upload_dir();
    $log_dir = $upload_dir['basedir'] . '/headlesswc-logs';

    if (!is_dir($log_dir)) {
        return;
    }

    $files = glob($log_dir . '/security-*.log');
    $cutoff_time = time() - (3 * MONTH_IN_SECONDS); // 3 miesiące

    foreach ($files as $file) {
        if (filemtime($file) < $cutoff_time) {
            unlink($file);
        }
    }
}

/**
 * Hook do codziennego czyszczenia starych logów
 */
if (headlesswc_is_security_logging_enabled()) {
    add_action('wp_scheduled_delete', 'headlesswc_cleanup_old_security_logs');
}

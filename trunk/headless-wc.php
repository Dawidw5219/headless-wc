<?php

/**
 * Plugin Name: HeadlessWC: Ultimate eCommerce Decoupler
 * Text Domain: headless - wc
 * Domain Path: /languages
 * Description: Custom WC endpoints for headless checkout
 * Version: 1.1.7
 * Author: Dawid Wiewiórski
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Requires PHP: 7.4
 */

// Exit if accessed directly.
if (! defined('ABSPATH')) {
    exit;
}

define('HEADLESSWC_PATH', plugin_dir_path(__FILE__));
define('HEADLESSWC_BASENAME', plugin_basename(__FILE__));
require_once HEADLESSWC_PATH . 'vendor/autoload.php';
require_once HEADLESSWC_PATH . 'includes/check-plugin-requirements.php';
require_once HEADLESSWC_PATH . 'includes/redirect_after_order.php';
require_once HEADLESSWC_PATH . 'includes/admin-settings.php';
require_once HEADLESSWC_PATH . 'includes/api-routes.php';
require_once HEADLESSWC_PATH . 'api/create-cart.php';
require_once HEADLESSWC_PATH . 'api/create-order.php';
require_once HEADLESSWC_PATH . 'api/get-order-details.php';
require_once HEADLESSWC_PATH . 'api/get-all-products.php';
require_once HEADLESSWC_PATH . 'api/get-single-product.php';
require_once HEADLESSWC_PATH . 'classes/product.php';
require_once HEADLESSWC_PATH . 'classes/product-detailed.php';
require_once HEADLESSWC_PATH . 'classes/cart-product.php';
require_once HEADLESSWC_PATH . 'utilities/get-attributes-data.php';
require_once HEADLESSWC_PATH . 'utilities/get-attributes.php';
require_once HEADLESSWC_PATH . 'utilities/get-gallery-images.php';
require_once HEADLESSWC_PATH . 'utilities/get-image-sizes.php';
require_once HEADLESSWC_PATH . 'utilities/get-meta-data.php';
require_once HEADLESSWC_PATH . 'utilities/get-regular-price.php';
require_once HEADLESSWC_PATH . 'utilities/get-sale-price.php';
require_once HEADLESSWC_PATH . 'utilities/nvl.php';


add_action('plugins_loaded', 'headlesswc_check_plugin_requirements', 0);
add_action('template_redirect', 'headlesswc_redirect_after_order', 20);

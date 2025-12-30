<?php
/**
 * Plugin Name: Shopvision
 * Plugin URI: https://shopvision.ai
 * Description: AI-powered product visualization for WooCommerce.
 * Version: 3.5.9
 * Author: Tim Schouwink
 * Author URI: https://timschouwink.com
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: shopvision
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * WC requires at least: 8.0
 * WC tested up to: 9.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Plugin constants
define('MRV_VERSION', '3.5.9');
define('MRV_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('MRV_PLUGIN_URL', plugin_dir_url(__FILE__));
define('MRV_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Check if WooCommerce is active
 */
function mrv_check_woocommerce() {
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', function() {
            echo '<div class="error"><p>';
            echo esc_html__('Shopvision requires WooCommerce to be installed and active.', 'shopvision');
            echo '</p></div>';
        });
        return false;
    }
    return true;
}

/**
 * Autoloader for plugin classes
 */
spl_autoload_register(function ($class) {
    $prefix = 'MRV_';

    if (strpos($class, $prefix) !== 0) {
        return;
    }

    $class_name = str_replace($prefix, '', $class);
    $class_name = strtolower(str_replace('_', '-', $class_name));

    $paths = [
        MRV_PLUGIN_DIR . 'includes/class-mrv-' . $class_name . '.php',
        MRV_PLUGIN_DIR . 'includes/admin/class-mrv-' . $class_name . '.php',
        MRV_PLUGIN_DIR . 'includes/frontend/class-mrv-' . $class_name . '.php',
        MRV_PLUGIN_DIR . 'includes/api/class-mrv-' . $class_name . '.php',
    ];

    foreach ($paths as $path) {
        if (file_exists($path)) {
            require_once $path;
            return;
        }
    }
});

/**
 * Plugin activation hook
 */
function mrv_activate() {
    require_once MRV_PLUGIN_DIR . 'includes/class-mrv-activator.php';
    MRV_Activator::activate();
}
register_activation_hook(__FILE__, 'mrv_activate');

/**
 * Plugin deactivation hook
 */
function mrv_deactivate() {
    require_once MRV_PLUGIN_DIR . 'includes/class-mrv-deactivator.php';
    MRV_Deactivator::deactivate();
}
register_deactivation_hook(__FILE__, 'mrv_deactivate');

/**
 * Initialize the plugin
 */
function mrv_init() {
    if (!mrv_check_woocommerce()) {
        return;
    }

    // Load text domain for translations
    load_plugin_textdomain(
        'shopvision',
        false,
        dirname(plugin_basename(__FILE__)) . '/languages'
    );

    // Load plugin classes
    require_once MRV_PLUGIN_DIR . 'includes/class-mrv-loader.php';

    $loader = new MRV_Loader();
    $loader->run();
}
add_action('plugins_loaded', 'mrv_init');

/**
 * Declare HPOS compatibility
 */
add_action('before_woocommerce_init', function() {
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    }
});

<?php
/**
 * Plugin activator - handles activation tasks
 */

if (!defined('ABSPATH')) {
    exit;
}

class MRV_Activator {

    /**
     * Activate the plugin
     */
    public static function activate(): void {
        self::set_default_options();
        self::schedule_cleanup_cron();
        self::migrate_api_key_encryption();

        flush_rewrite_rules();
    }

    /**
     * Set default plugin options
     */
    private static function set_default_options(): void {
        if (get_option('mrv_api_key') === false) {
            add_option('mrv_api_key', '');
        }

        if (get_option('mrv_enabled_products') === false) {
            add_option('mrv_enabled_products', []);
        }

        if (get_option('mrv_button_text') === false) {
            add_option('mrv_button_text', 'Visualiseer in jouw kamer');
        }

        if (get_option('mrv_cleanup_hours') === false) {
            add_option('mrv_cleanup_hours', 24);
        }
    }

    /**
     * Schedule the cleanup cron job
     */
    private static function schedule_cleanup_cron(): void {
        if (!wp_next_scheduled('mrv_cleanup_images')) {
            wp_schedule_event(time(), 'hourly', 'mrv_cleanup_images');
        }
    }

    /**
     * Migrate plain text API key to encrypted format
     */
    private static function migrate_api_key_encryption(): void {
        // Load encryption class if not already loaded
        if (!class_exists('MRV_Encryption')) {
            require_once MRV_PLUGIN_DIR . 'includes/class-mrv-encryption.php';
        }

        MRV_Encryption::migrate_option('mrv_api_key');
    }
}

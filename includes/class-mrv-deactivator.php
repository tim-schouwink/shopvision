<?php
/**
 * Plugin deactivator - handles deactivation tasks
 */

if (!defined('ABSPATH')) {
    exit;
}

class MRV_Deactivator {

    /**
     * Deactivate the plugin
     */
    public static function deactivate(): void {
        self::unschedule_cleanup_cron();

        flush_rewrite_rules();
    }

    /**
     * Unschedule the cleanup cron job
     */
    private static function unschedule_cleanup_cron(): void {
        $timestamp = wp_next_scheduled('mrv_cleanup_images');

        if ($timestamp) {
            wp_unschedule_event($timestamp, 'mrv_cleanup_images');
        }
    }
}

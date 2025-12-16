<?php
/**
 * Conversion Tracker - Tracks orders from customers who used the visualizer
 */

if (!defined('ABSPATH')) {
    exit;
}

class MRV_Conversion_Tracker {

    /**
     * Cookie name for session tracking
     */
    public const COOKIE_NAME = 'mrv_session';

    /**
     * Cookie duration in days
     */
    public const COOKIE_DAYS = 30;

    /**
     * Initialize conversion tracker
     */
    public function __construct() {
        // Track conversions on order completion
        add_action('woocommerce_thankyou', [$this, 'track_conversion'], 10, 1);

        // Also track on order status change to completed (for manual orders)
        add_action('woocommerce_order_status_completed', [$this, 'track_conversion'], 10, 1);
    }

    /**
     * Set tracking cookie when visualization is created
     *
     * @param string $session_id The session ID from the visualization
     */
    public static function set_tracking_cookie(string $session_id): void {
        if (headers_sent()) {
            return;
        }

        $expires = time() + (self::COOKIE_DAYS * DAY_IN_SECONDS);

        setcookie(self::COOKIE_NAME, $session_id, [
            'expires'  => $expires,
            'path'     => '/',
            'secure'   => is_ssl(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        // Also set in $_COOKIE for immediate access in same request
        $_COOKIE[self::COOKIE_NAME] = $session_id;
    }

    /**
     * Track conversion when order is placed
     *
     * @param int $order_id WooCommerce order ID
     */
    public function track_conversion(int $order_id): void {
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        // Skip if already tracked
        if ($order->get_meta('_mrv_converted')) {
            return;
        }

        // Get session ID from cookie
        $session_id = $_COOKIE[self::COOKIE_NAME] ?? null;
        if (!$session_id) {
            return;
        }

        // Find visualizations with this session ID
        $generations = get_posts([
            'post_type'      => 'mrv_generation',
            'meta_key'       => '_mrv_session_id',
            'meta_value'     => sanitize_text_field($session_id),
            'posts_per_page' => -1,
            'fields'         => 'ids',
        ]);

        if (empty($generations)) {
            return;
        }

        // Update order meta
        $order->update_meta_data('_mrv_converted', 'yes');
        $order->update_meta_data('_mrv_session_id', $session_id);
        $order->update_meta_data('_mrv_generation_ids', $generations);
        $order->update_meta_data('_mrv_conversion_date', current_time('mysql'));
        $order->save();

        // Update generation meta
        foreach ($generations as $generation_id) {
            update_post_meta($generation_id, '_mrv_converted', 'yes');
            update_post_meta($generation_id, '_mrv_order_id', $order_id);
            update_post_meta($generation_id, '_mrv_conversion_date', current_time('mysql'));
        }

        // Clear transient cache
        $this->clear_analytics_cache();
    }

    /**
     * Clear analytics transient cache
     */
    public function clear_analytics_cache(): void {
        delete_transient('mrv_analytics_7days');
        delete_transient('mrv_analytics_30days');
        delete_transient('mrv_analytics_90days');
        delete_transient('mrv_analytics_all');
    }

    /**
     * Get analytics data for a period
     *
     * @param string $period Period: '7days', '30days', '90days', 'all'
     * @return array Analytics data
     */
    public static function get_analytics(string $period = '30days'): array {
        $cache_key = 'mrv_analytics_' . $period;
        $data = get_transient($cache_key);

        if (false !== $data) {
            return $data;
        }

        $data = self::calculate_analytics($period);
        set_transient($cache_key, $data, HOUR_IN_SECONDS);

        return $data;
    }

    /**
     * Calculate analytics data
     *
     * @param string $period Period: '7days', '30days', '90days', 'all'
     * @return array Analytics data
     */
    private static function calculate_analytics(string $period): array {
        $date_query = self::get_date_query($period);
        $prev_date_query = self::get_previous_date_query($period);

        // Current period stats
        $current = self::get_period_stats($date_query);

        // Previous period stats (for comparison)
        $previous = self::get_period_stats($prev_date_query);

        // Calculate changes
        $viz_change = self::calculate_change($current['visualizations'], $previous['visualizations']);
        $conv_change = self::calculate_change($current['conversions'], $previous['conversions']);
        $revenue_change = self::calculate_change($current['revenue'], $previous['revenue']);

        // Get recent conversions
        $recent_conversions = self::get_recent_conversions(5);

        // Get top products
        $top_products = self::get_top_products(5, $date_query);

        // Calculate days to convert
        $days_to_convert = self::get_average_days_to_convert($date_query);

        return [
            'visualizations'       => $current['visualizations'],
            'visualizations_change' => $viz_change,
            'conversions'          => $current['conversions'],
            'conversions_change'   => $conv_change,
            'conversion_rate'      => $current['visualizations'] > 0
                ? round(($current['conversions'] / $current['visualizations']) * 100, 1)
                : 0,
            'revenue'              => $current['revenue'],
            'revenue_change'       => $revenue_change,
            'avg_order_value'      => $current['conversions'] > 0
                ? round($current['revenue'] / $current['conversions'], 2)
                : 0,
            'days_to_convert'      => $days_to_convert,
            'recent_conversions'   => $recent_conversions,
            'top_products'         => $top_products,
            'currency_symbol'      => get_woocommerce_currency_symbol(),
        ];
    }

    /**
     * Get stats for a period
     *
     * @param array $date_query WordPress date query array
     * @return array Stats array
     */
    private static function get_period_stats(array $date_query): array {
        // Total visualizations
        $viz_query = new WP_Query([
            'post_type'      => 'mrv_generation',
            'date_query'     => $date_query,
            'posts_per_page' => -1,
            'fields'         => 'ids',
        ]);
        $visualizations = $viz_query->found_posts;

        // Conversions
        $conv_query = new WP_Query([
            'post_type'      => 'mrv_generation',
            'date_query'     => $date_query,
            'meta_key'       => '_mrv_converted',
            'meta_value'     => 'yes',
            'posts_per_page' => -1,
            'fields'         => 'ids',
        ]);
        $conversions = $conv_query->found_posts;

        // Revenue from converted orders
        $revenue = 0;
        if ($conversions > 0) {
            $order_ids = [];
            foreach ($conv_query->posts as $gen_id) {
                $order_id = get_post_meta($gen_id, '_mrv_order_id', true);
                if ($order_id) {
                    $order_ids[] = $order_id;
                }
            }

            $order_ids = array_unique($order_ids);

            foreach ($order_ids as $order_id) {
                $order = wc_get_order($order_id);
                if ($order && in_array($order->get_status(), ['completed', 'processing'])) {
                    $revenue += (float) $order->get_total();
                }
            }
        }

        return [
            'visualizations' => $visualizations,
            'conversions'    => $conversions,
            'revenue'        => $revenue,
        ];
    }

    /**
     * Get date query for period
     *
     * @param string $period Period
     * @return array WordPress date query array
     */
    private static function get_date_query(string $period): array {
        switch ($period) {
            case '7days':
                return [['after' => '7 days ago']];
            case '30days':
                return [['after' => '30 days ago']];
            case '90days':
                return [['after' => '90 days ago']];
            case 'all':
            default:
                return [];
        }
    }

    /**
     * Get previous period date query (for comparison)
     *
     * @param string $period Period
     * @return array WordPress date query array
     */
    private static function get_previous_date_query(string $period): array {
        switch ($period) {
            case '7days':
                return [['after' => '14 days ago', 'before' => '7 days ago']];
            case '30days':
                return [['after' => '60 days ago', 'before' => '30 days ago']];
            case '90days':
                return [['after' => '180 days ago', 'before' => '90 days ago']];
            case 'all':
            default:
                return [];
        }
    }

    /**
     * Calculate percentage change
     *
     * @param float $current Current value
     * @param float $previous Previous value
     * @return float Percentage change
     */
    private static function calculate_change(float $current, float $previous): float {
        if ($previous == 0) {
            return $current > 0 ? 100 : 0;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }

    /**
     * Get recent conversions
     *
     * @param int $limit Number of conversions to return
     * @return array Recent conversions
     */
    private static function get_recent_conversions(int $limit = 5): array {
        $orders = wc_get_orders([
            'meta_key'   => '_mrv_converted',
            'meta_value' => 'yes',
            'limit'      => $limit,
            'orderby'    => 'date',
            'order'      => 'DESC',
        ]);

        $conversions = [];
        foreach ($orders as $order) {
            $conversions[] = [
                'order_id'   => $order->get_id(),
                'order_number' => $order->get_order_number(),
                'total'      => $order->get_total(),
                'date'       => $order->get_date_created()->format('M j'),
                'edit_url'   => $order->get_edit_order_url(),
            ];
        }

        return $conversions;
    }

    /**
     * Get top visualized products
     *
     * @param int   $limit Number of products to return
     * @param array $date_query Date query for filtering
     * @return array Top products
     */
    private static function get_top_products(int $limit = 5, array $date_query = []): array {
        $generations = get_posts([
            'post_type'      => 'mrv_generation',
            'date_query'     => $date_query,
            'posts_per_page' => -1,
            'fields'         => 'ids',
        ]);

        $product_stats = [];

        foreach ($generations as $gen_id) {
            $product_ids = get_post_meta($gen_id, '_mrv_product_ids', true);
            $converted = get_post_meta($gen_id, '_mrv_converted', true) === 'yes';

            if (!is_array($product_ids)) {
                $product_ids = [$product_ids];
            }

            foreach ($product_ids as $product_id) {
                if (!isset($product_stats[$product_id])) {
                    $product_stats[$product_id] = [
                        'visualizations' => 0,
                        'conversions'    => 0,
                    ];
                }

                $product_stats[$product_id]['visualizations']++;
                if ($converted) {
                    $product_stats[$product_id]['conversions']++;
                }
            }
        }

        // Sort by visualizations
        uasort($product_stats, function ($a, $b) {
            return $b['visualizations'] - $a['visualizations'];
        });

        // Get top products with details
        $top_products = [];
        $count = 0;

        foreach ($product_stats as $product_id => $stats) {
            if ($count >= $limit) {
                break;
            }

            $product = wc_get_product($product_id);
            if (!$product) {
                continue;
            }

            $conv_rate = $stats['visualizations'] > 0
                ? round(($stats['conversions'] / $stats['visualizations']) * 100, 0)
                : 0;

            $top_products[] = [
                'id'              => $product_id,
                'name'            => $product->get_name(),
                'visualizations'  => $stats['visualizations'],
                'conversions'     => $stats['conversions'],
                'conversion_rate' => $conv_rate,
                'edit_url'        => get_edit_post_link($product_id),
            ];

            $count++;
        }

        return $top_products;
    }

    /**
     * Get average days to convert
     *
     * @param array $date_query Date query
     * @return float Average days
     */
    private static function get_average_days_to_convert(array $date_query): float {
        $generations = get_posts([
            'post_type'      => 'mrv_generation',
            'date_query'     => $date_query,
            'meta_key'       => '_mrv_converted',
            'meta_value'     => 'yes',
            'posts_per_page' => -1,
        ]);

        if (empty($generations)) {
            return 0;
        }

        $total_days = 0;
        $count = 0;

        foreach ($generations as $gen) {
            $created = strtotime($gen->post_date);
            $converted = get_post_meta($gen->ID, '_mrv_conversion_date', true);

            if ($converted) {
                $converted_time = strtotime($converted);
                $days = ($converted_time - $created) / DAY_IN_SECONDS;
                $total_days += max(0, $days);
                $count++;
            }
        }

        return $count > 0 ? round($total_days / $count, 1) : 0;
    }
}

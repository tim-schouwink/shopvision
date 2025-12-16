<?php
/**
 * Plugin loader class - registers all hooks and initializes components
 */

if (!defined('ABSPATH')) {
    exit;
}

class MRV_Loader {

    /**
     * Array of actions to register
     */
    protected array $actions = [];

    /**
     * Array of filters to register
     */
    protected array $filters = [];

    /**
     * Initialize the loader
     */
    public function __construct() {
        $this->load_dependencies();
        $this->define_admin_hooks();
        $this->define_frontend_hooks();
        $this->define_ajax_hooks();
        $this->define_cron_hooks();
    }

    /**
     * Load required dependencies
     */
    private function load_dependencies(): void {
        require_once MRV_PLUGIN_DIR . 'includes/class-mrv-post-types.php';
        require_once MRV_PLUGIN_DIR . 'includes/admin/class-mrv-admin.php';
        require_once MRV_PLUGIN_DIR . 'includes/admin/class-mrv-settings.php';
        require_once MRV_PLUGIN_DIR . 'includes/admin/class-mrv-generations-list.php';
        require_once MRV_PLUGIN_DIR . 'includes/frontend/class-mrv-frontend.php';
        require_once MRV_PLUGIN_DIR . 'includes/api/class-mrv-ajax-handler.php';
        require_once MRV_PLUGIN_DIR . 'includes/api/class-mrv-gemini-client.php';

        // Initialize post types (registers on 'init' hook)
        new MRV_Post_Types();
    }

    /**
     * Register admin-related hooks
     */
    private function define_admin_hooks(): void {
        $admin = new MRV_Admin();
        $settings = new MRV_Settings();

        $this->add_action('admin_menu', $settings, 'add_settings_page');
        $this->add_action('admin_init', $settings, 'register_settings');
        $this->add_action('admin_enqueue_scripts', $admin, 'enqueue_styles');
        $this->add_action('admin_enqueue_scripts', $admin, 'enqueue_scripts');
        $this->add_action('wp_ajax_mrv_search_products', $admin, 'ajax_search_products');
        $this->add_action('wp_ajax_mrv_get_all_products', $admin, 'ajax_get_all_products');
        $this->add_action('wp_ajax_mrv_test_api_key', $admin, 'ajax_test_api_key');
    }

    /**
     * Register frontend-related hooks
     */
    private function define_frontend_hooks(): void {
        $frontend = new MRV_Frontend();

        // Button placement based on setting
        $button_position = get_option('mrv_button_position', 'after_button');

        switch ($button_position) {
            case 'after_form':
                // Place button after the entire cart form
                $this->add_action('woocommerce_after_add_to_cart_form', $frontend, 'render_visualizer_button', 10);
                break;

            case 'shortcode':
                // Don't auto-place, only via shortcode
                break;

            case 'after_button':
            default:
                // Default: button after add to cart button (inside form)
                $this->add_action('woocommerce_after_add_to_cart_button', $frontend, 'render_visualizer_button', 20);
                break;
        }

        // Register shortcode (always available for flexibility)
        add_shortcode('shopvision', [$frontend, 'render_shortcode']);

        $this->add_action('wp_footer', $frontend, 'render_modal');
        $this->add_action('wp_enqueue_scripts', $frontend, 'enqueue_styles');
        $this->add_action('wp_enqueue_scripts', $frontend, 'enqueue_scripts');

        // Dynamic CSS variables output
        $this->add_action('wp_head', $frontend, 'output_dynamic_styles', 100);
    }

    /**
     * Register AJAX hooks
     */
    private function define_ajax_hooks(): void {
        $ajax = new MRV_Ajax_Handler();

        // Visualization processing
        $this->add_action('wp_ajax_mrv_process', $ajax, 'process_visualization');
        $this->add_action('wp_ajax_nopriv_mrv_process', $ajax, 'process_visualization');

        // Consent update
        $this->add_action('wp_ajax_mrv_update_consent', $ajax, 'update_consent');
        $this->add_action('wp_ajax_nopriv_mrv_update_consent', $ajax, 'update_consent');

        // Add to cart (fallback for when WooCommerce native AJAX is unavailable)
        $this->add_action('wp_ajax_mrv_add_to_cart', $ajax, 'add_to_cart');
        $this->add_action('wp_ajax_nopriv_mrv_add_to_cart', $ajax, 'add_to_cart');
    }

    /**
     * Register cron hooks
     */
    private function define_cron_hooks(): void {
        $this->add_action('mrv_cleanup_images', $this, 'cleanup_old_images');
    }

    /**
     * Add action to the collection
     */
    public function add_action(string $hook, object $component, string $callback, int $priority = 10, int $accepted_args = 1): void {
        $this->actions[] = [
            'hook'          => $hook,
            'component'     => $component,
            'callback'      => $callback,
            'priority'      => $priority,
            'accepted_args' => $accepted_args,
        ];
    }

    /**
     * Add filter to the collection
     */
    public function add_filter(string $hook, object $component, string $callback, int $priority = 10, int $accepted_args = 1): void {
        $this->filters[] = [
            'hook'          => $hook,
            'component'     => $component,
            'callback'      => $callback,
            'priority'      => $priority,
            'accepted_args' => $accepted_args,
        ];
    }

    /**
     * Register all hooks with WordPress
     */
    public function run(): void {
        foreach ($this->filters as $hook) {
            add_filter(
                $hook['hook'],
                [$hook['component'], $hook['callback']],
                $hook['priority'],
                $hook['accepted_args']
            );
        }

        foreach ($this->actions as $hook) {
            add_action(
                $hook['hook'],
                [$hook['component'], $hook['callback']],
                $hook['priority'],
                $hook['accepted_args']
            );
        }
    }

    /**
     * Cleanup old generated images
     *
     * Only cleans up generations with 'pending' status that have expired.
     * Approved and featured generations are kept forever.
     */
    public function cleanup_old_images(): void {
        $hours = (int) get_option('mrv_cleanup_hours', 24);

        if ($hours <= 0) {
            return;
        }

        // Find pending generations that have expired
        $expired_generations = get_posts([
            'post_type'      => MRV_Post_Types::POST_TYPE,
            'posts_per_page' => 50,
            'post_status'    => 'publish',
            'meta_query'     => [
                'relation' => 'AND',
                [
                    'key'   => '_mrv_consent_status',
                    'value' => 'pending',
                ],
                [
                    'key'     => '_mrv_expires_at',
                    'value'   => time(),
                    'compare' => '<',
                    'type'    => 'NUMERIC',
                ],
            ],
        ]);

        foreach ($expired_generations as $generation) {
            MRV_Post_Types::delete_generation($generation->ID);
        }

        // Also cleanup any rejected generations older than 7 days
        $rejected_cutoff = time() - (7 * DAY_IN_SECONDS);
        $rejected_generations = get_posts([
            'post_type'      => MRV_Post_Types::POST_TYPE,
            'posts_per_page' => 50,
            'post_status'    => 'publish',
            'meta_query'     => [
                'relation' => 'AND',
                [
                    'key'   => '_mrv_consent_status',
                    'value' => 'rejected',
                ],
                [
                    'key'     => '_mrv_consent_date',
                    'value'   => $rejected_cutoff,
                    'compare' => '<',
                    'type'    => 'NUMERIC',
                ],
            ],
        ]);

        foreach ($rejected_generations as $generation) {
            MRV_Post_Types::delete_generation($generation->ID);
        }
    }
}

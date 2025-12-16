<?php
/**
 * Custom Post Type & Taxonomy registration for Generations
 */

if (!defined('ABSPATH')) {
    exit;
}

class MRV_Post_Types {

    /**
     * Post type name
     */
    public const POST_TYPE = 'mrv_generation';

    /**
     * Taxonomy name
     */
    public const TAXONOMY = 'mrv_generation_status';

    /**
     * Initialize post types
     */
    public function __construct() {
        add_action('init', [$this, 'register_post_type']);
        add_action('init', [$this, 'register_taxonomy']);
        add_action('init', [$this, 'create_default_terms'], 20);
    }

    /**
     * Register the mrv_generation post type
     */
    public function register_post_type(): void {
        $labels = [
            'name'                  => _x('Generations', 'Post type general name', 'shopvision'),
            'singular_name'         => _x('Generation', 'Post type singular name', 'shopvision'),
            'menu_name'             => _x('Generations', 'Admin Menu text', 'shopvision'),
            'name_admin_bar'        => _x('Generation', 'Add New on Toolbar', 'shopvision'),
            'add_new'               => __('Add New', 'shopvision'),
            'add_new_item'          => __('Add New Generation', 'shopvision'),
            'new_item'              => __('New Generation', 'shopvision'),
            'edit_item'             => __('Edit Generation', 'shopvision'),
            'view_item'             => __('View Generation', 'shopvision'),
            'all_items'             => __('Generations', 'shopvision'),
            'search_items'          => __('Search Generations', 'shopvision'),
            'not_found'             => __('No generations found.', 'shopvision'),
            'not_found_in_trash'    => __('No generations found in trash.', 'shopvision'),
        ];

        $args = [
            'labels'              => $labels,
            'public'              => false,
            'publicly_queryable'  => false,
            'show_ui'             => true,
            'show_in_menu'        => false, // We add it manually as submenu
            'query_var'           => false,
            'rewrite'             => false,
            'capability_type'     => 'post',
            'capabilities'        => [
                'create_posts' => 'do_not_allow', // Disable "Add New"
            ],
            'map_meta_cap'        => true,
            'has_archive'         => false,
            'hierarchical'        => false,
            'supports'            => ['title'],
            'show_in_rest'        => true,
        ];

        register_post_type(self::POST_TYPE, $args);
    }

    /**
     * Register the mrv_generation_status taxonomy
     */
    public function register_taxonomy(): void {
        $labels = [
            'name'              => _x('Status', 'taxonomy general name', 'shopvision'),
            'singular_name'     => _x('Status', 'taxonomy singular name', 'shopvision'),
            'search_items'      => __('Search Statuses', 'shopvision'),
            'all_items'         => __('All Statuses', 'shopvision'),
            'edit_item'         => __('Edit Status', 'shopvision'),
            'update_item'       => __('Update Status', 'shopvision'),
            'add_new_item'      => __('Add New Status', 'shopvision'),
            'new_item_name'     => __('New Status Name', 'shopvision'),
            'menu_name'         => __('Status', 'shopvision'),
        ];

        $args = [
            'labels'            => $labels,
            'hierarchical'      => false,
            'public'            => false,
            'show_ui'           => true,
            'show_admin_column' => true,
            'query_var'         => false,
            'rewrite'           => false,
            'show_in_rest'      => true,
        ];

        register_taxonomy(self::TAXONOMY, self::POST_TYPE, $args);
    }

    /**
     * Create default taxonomy terms
     */
    public function create_default_terms(): void {
        // Only run once
        if (get_option('mrv_taxonomy_terms_created')) {
            return;
        }

        $terms = [
            'pending' => [
                'name'        => __('Pending', 'shopvision'),
                'description' => __('No consent given', 'shopvision'),
            ],
            'approved' => [
                'name'        => __('Approved', 'shopvision'),
                'description' => __('May be used on site', 'shopvision'),
            ],
            'featured' => [
                'name'        => __('Featured', 'shopvision'),
                'description' => __('Active in marketing/examples', 'shopvision'),
            ],
            'rejected' => [
                'name'        => __('Rejected', 'shopvision'),
                'description' => __('Explicitly no consent', 'shopvision'),
            ],
        ];

        foreach ($terms as $slug => $data) {
            if (!term_exists($slug, self::TAXONOMY)) {
                wp_insert_term(
                    $data['name'],
                    self::TAXONOMY,
                    [
                        'slug'        => $slug,
                        'description' => $data['description'],
                    ]
                );
            }
        }

        update_option('mrv_taxonomy_terms_created', true);
    }

    /**
     * Create a new generation post
     *
     * @param array $args Generation data
     * @return int|WP_Error Post ID on success, WP_Error on failure
     */
    public static function create_generation(array $args): int|WP_Error {
        $defaults = [
            'product_id'     => 0,      // Legacy single product support
            'product_ids'    => [],     // Multi-product support
            'image_path'     => '',
            'image_url'      => '',
            'consent_given'  => false,
            'session_id'     => '',
            'user_agent'     => '',
        ];

        $args = wp_parse_args($args, $defaults);

        // Normalize product IDs - support both single and array
        $product_ids = $args['product_ids'];
        if (empty($product_ids) && !empty($args['product_id'])) {
            $product_ids = [$args['product_id']];
        }
        if (!is_array($product_ids)) {
            $product_ids = [$product_ids];
        }
        $product_ids = array_map('absint', array_filter($product_ids));

        if (empty($product_ids)) {
            return new WP_Error('no_products', __('No products specified.', 'shopvision'));
        }

        // Get product names for title
        $product_names = [];
        foreach ($product_ids as $pid) {
            $product = wc_get_product($pid);
            if ($product) {
                $product_names[] = $product->get_name();
            }
        }

        $title_products = count($product_names) === 1
            ? $product_names[0]
            : sprintf(
                __('%s + %d more', 'shopvision'),
                $product_names[0],
                count($product_names) - 1
            );

        // Create post
        $post_data = [
            'post_type'   => self::POST_TYPE,
            'post_status' => 'publish',
            'post_title'  => sprintf(
                __('Generation - %s', 'shopvision'),
                $title_products
            ),
            'post_author' => get_current_user_id(),
        ];

        $post_id = wp_insert_post($post_data, true);

        if (is_wp_error($post_id)) {
            return $post_id;
        }

        // Add meta data - store all product IDs as serialized array
        update_post_meta($post_id, '_mrv_product_ids', $product_ids);
        // Keep backwards compatible single ID for primary product
        update_post_meta($post_id, '_mrv_product_id', $product_ids[0]);
        update_post_meta($post_id, '_mrv_image_path', sanitize_text_field($args['image_path']));
        update_post_meta($post_id, '_mrv_image_url', esc_url_raw($args['image_url']));
        update_post_meta($post_id, '_mrv_session_id', sanitize_text_field($args['session_id']));
        update_post_meta($post_id, '_mrv_user_agent', sanitize_text_field($args['user_agent']));
        update_post_meta($post_id, '_mrv_created_at', time());

        // Set consent status
        if ($args['consent_given']) {
            update_post_meta($post_id, '_mrv_consent_status', 'approved');
            update_post_meta($post_id, '_mrv_consent_date', time());
            wp_set_object_terms($post_id, 'approved', self::TAXONOMY);
        } else {
            update_post_meta($post_id, '_mrv_consent_status', 'pending');
            wp_set_object_terms($post_id, 'pending', self::TAXONOMY);

            // Set expiration for pending items (use cleanup_hours setting)
            $cleanup_hours = (int) get_option('mrv_cleanup_hours', 24);
            if ($cleanup_hours > 0) {
                $expires_at = time() + ($cleanup_hours * HOUR_IN_SECONDS);
                update_post_meta($post_id, '_mrv_expires_at', $expires_at);
            }
        }

        return $post_id;
    }

    /**
     * Update consent status for a generation
     *
     * @param int    $post_id Generation post ID
     * @param string $status  New status: approved, rejected, featured
     * @return bool Success
     */
    public static function update_consent(int $post_id, string $status): bool {
        $valid_statuses = ['approved', 'rejected', 'featured'];

        if (!in_array($status, $valid_statuses, true)) {
            return false;
        }

        $post = get_post($post_id);
        if (!$post || $post->post_type !== self::POST_TYPE) {
            return false;
        }

        update_post_meta($post_id, '_mrv_consent_status', $status);
        update_post_meta($post_id, '_mrv_consent_date', time());
        wp_set_object_terms($post_id, $status, self::TAXONOMY);

        // Remove expiration for approved/featured items
        if ($status === 'approved' || $status === 'featured') {
            delete_post_meta($post_id, '_mrv_expires_at');
        }

        return true;
    }

    /**
     * Get generations with optional filters
     *
     * @param array $args Query arguments
     * @return WP_Post[] Array of generation posts
     */
    public static function get_generations(array $args = []): array {
        $defaults = [
            'post_type'      => self::POST_TYPE,
            'posts_per_page' => 20,
            'post_status'    => 'publish',
            'orderby'        => 'date',
            'order'          => 'DESC',
        ];

        $args = wp_parse_args($args, $defaults);

        return get_posts($args);
    }

    /**
     * Delete a generation and its associated file
     *
     * @param int $post_id Generation post ID
     * @return bool Success
     */
    public static function delete_generation(int $post_id): bool {
        $post = get_post($post_id);
        if (!$post || $post->post_type !== self::POST_TYPE) {
            return false;
        }

        // Delete associated image file
        $image_path = get_post_meta($post_id, '_mrv_image_path', true);
        if ($image_path) {
            $upload_dir = wp_upload_dir();
            $full_path = $upload_dir['basedir'] . '/' . $image_path;
            if (file_exists($full_path)) {
                @unlink($full_path);
            }
        }

        // Delete the post
        $result = wp_delete_post($post_id, true);

        return $result !== false && $result !== null;
    }

    /**
     * Get count of generations by status
     *
     * @return array Counts by status
     */
    public static function get_status_counts(): array {
        global $wpdb;

        $counts = [
            'all'      => 0,
            'pending'  => 0,
            'approved' => 0,
            'featured' => 0,
            'rejected' => 0,
        ];

        // Get total count
        $counts['all'] = (int) wp_count_posts(self::POST_TYPE)->publish;

        // Get counts by taxonomy term
        $terms = get_terms([
            'taxonomy'   => self::TAXONOMY,
            'hide_empty' => false,
        ]);

        if (!is_wp_error($terms)) {
            foreach ($terms as $term) {
                if (isset($counts[$term->slug])) {
                    $counts[$term->slug] = (int) $term->count;
                }
            }
        }

        return $counts;
    }
}

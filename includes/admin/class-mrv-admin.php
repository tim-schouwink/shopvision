<?php
/**
 * Admin functionality class
 */

if (!defined('ABSPATH')) {
    exit;
}

class MRV_Admin {

    /**
     * Check if we're on a Shopvision admin page
     */
    private function is_mrv_page(string $hook): bool {
        return $hook === 'woocommerce_page_mrv-settings';
    }

    /**
     * Enqueue admin styles
     */
    public function enqueue_styles(string $hook): void {
        if (!$this->is_mrv_page($hook)) {
            return;
        }

        // WordPress color picker
        wp_enqueue_style('wp-color-picker');

        wp_enqueue_style(
            'mrv-admin',
            MRV_PLUGIN_URL . 'assets/css/mrv-admin.css',
            ['wp-color-picker'],
            MRV_VERSION
        );
    }

    /**
     * Enqueue admin scripts
     */
    public function enqueue_scripts(string $hook): void {
        if (!$this->is_mrv_page($hook)) {
            return;
        }

        // WordPress color picker
        wp_enqueue_script('wp-color-picker');

        // WordPress media uploader
        wp_enqueue_media();

        wp_enqueue_script(
            'mrv-admin',
            MRV_PLUGIN_URL . 'assets/js/mrv-admin.js',
            ['jquery', 'wp-color-picker'],
            MRV_VERSION,
            true
        );

        wp_localize_script('mrv-admin', 'mrvAdmin', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('search-products'),
            'icons'   => [
                'camera'  => '<rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/>',
                'eye'     => '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>',
                'home'    => '<path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>',
                'sparkle' => '<path d="M12 3l1.5 6.5L20 12l-6.5 2.5L12 21l-1.5-6.5L4 12l6.5-2.5z"/>',
                'wand'    => '<path d="M15 4V2"/><path d="M15 16v-2"/><path d="M8 9h2"/><path d="M20 9h2"/><path d="M17.8 11.8L19 13"/><path d="M15 9h.01"/><path d="M17.8 6.2L19 5"/><path d="m3 21 9-9"/><path d="M12.2 6.2L11 5"/>',
            ],
        ]);

        wp_localize_script('mrv-admin', 'mrvAdminI18n', [
            'featured' => __('Featured', 'shopvision'),
            'approved' => __('Approved', 'shopvision'),
            'pending'  => __('Pending', 'shopvision'),
            'rejected' => __('Rejected', 'shopvision'),
        ]);
    }

    /**
     * AJAX handler for product search
     */
    public function ajax_search_products(): void {
        check_ajax_referer('search-products', 'security');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json([]);
        }

        $term = isset($_POST['term']) ? sanitize_text_field($_POST['term']) : '';

        if (empty($term)) {
            wp_send_json([]);
        }

        $products = wc_get_products([
            'status'  => 'publish',
            'limit'   => 20,
            's'       => $term,
            'orderby' => 'title',
            'order'   => 'ASC',
        ]);

        $results = [];
        foreach ($products as $product) {
            $results[$product->get_id()] = $product->get_name();
        }

        wp_send_json($results);
    }

    /**
     * AJAX handler for getting all product IDs (lightweight)
     */
    public function ajax_get_all_products(): void {
        check_ajax_referer('search-products', 'security');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error();
        }

        // Just get IDs - much faster for large catalogs
        $product_ids = wc_get_products([
            'status'  => 'publish',
            'limit'   => -1,
            'return'  => 'ids',
        ]);

        wp_send_json_success([
            'ids'   => $product_ids,
            'total' => count($product_ids),
        ]);
    }

    /**
     * AJAX handler for toggling featured status
     */
    public function ajax_toggle_featured(): void {
        check_ajax_referer('search-products', 'security');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => __('Access denied.', 'shopvision')]);
        }

        $post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
        $status = isset($_POST['status']) ? sanitize_key($_POST['status']) : '';

        if (!$post_id) {
            wp_send_json_error(['message' => __('Invalid post ID.', 'shopvision')]);
        }

        if (!in_array($status, ['approved', 'featured'], true)) {
            wp_send_json_error(['message' => __('Invalid status.', 'shopvision')]);
        }

        // Update the consent status
        $result = MRV_Post_Types::update_consent($post_id, $status);

        if ($result) {
            wp_send_json_success(['status' => $status]);
        } else {
            wp_send_json_error(['message' => __('Failed to update status.', 'shopvision')]);
        }
    }

    /**
     * AJAX handler for testing API key
     */
    public function ajax_test_api_key(): void {
        check_ajax_referer('search-products', 'security');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => __('Access denied.', 'shopvision')]);
        }

        // Get API key from request (in case user changed it but didn't save yet)
        $api_key = isset($_POST['api_key']) ? sanitize_text_field($_POST['api_key']) : '';

        if (empty($api_key)) {
            wp_send_json_error([
                'message' => __('No API key provided.', 'shopvision'),
                'details' => __('Please enter an API key first.', 'shopvision'),
            ]);
        }

        // Test with the same model we use for visualization (Nano Banana Pro)
        $test_url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-3-pro-image-preview:generateContent?key=' . $api_key;

        $response = wp_remote_post($test_url, [
            'timeout' => 30,
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'body' => wp_json_encode([
                'contents' => [
                    [
                        'parts' => [
                            ['text' => 'Say "API key works!" in exactly those words.'],
                        ],
                    ],
                ],
            ]),
        ]);

        if (is_wp_error($response)) {
            $error = $response->get_error_message();

            if (strpos($error, 'cURL error 28') !== false || strpos($error, 'timed out') !== false) {
                wp_send_json_error([
                    'message' => __('Connection timeout', 'shopvision'),
                    'details' => __('The API did not respond in time. Please try again later.', 'shopvision'),
                ]);
            }

            wp_send_json_error([
                'message' => __('Connection error', 'shopvision'),
                'details' => $error,
            ]);
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        // Check for specific error codes
        if ($status_code === 400) {
            $error_msg = $data['error']['message'] ?? '';

            if (strpos($error_msg, 'API key not valid') !== false) {
                wp_send_json_error([
                    'message' => __('Invalid API key', 'shopvision'),
                    'details' => __('The API key is not valid. Check if you copied the correct key.', 'shopvision'),
                ]);
            }

            wp_send_json_error([
                'message' => __('API error', 'shopvision'),
                'details' => $error_msg ?: __('Unknown validation error.', 'shopvision'),
            ]);
        }

        if ($status_code === 403) {
            $error_msg = $data['error']['message'] ?? '';

            if (strpos($error_msg, 'API_KEY_INVALID') !== false || strpos($error_msg, 'invalid') !== false) {
                wp_send_json_error([
                    'message' => __('Invalid API key', 'shopvision'),
                    'details' => __('The API key is not valid or has been deactivated.', 'shopvision'),
                ]);
            }

            if (strpos($error_msg, 'PERMISSION_DENIED') !== false || strpos($error_msg, 'permission') !== false) {
                wp_send_json_error([
                    'message' => __('Access denied', 'shopvision'),
                    'details' => __('The API key does not have access to the Generative Language API. Check if the API is enabled in Google Cloud Console.', 'shopvision'),
                ]);
            }

            wp_send_json_error([
                'message' => __('Access denied', 'shopvision'),
                'details' => $error_msg ?: __('Check your Google Cloud project settings.', 'shopvision'),
            ]);
        }

        if ($status_code === 429) {
            wp_send_json_error([
                'message' => __('Rate limit reached', 'shopvision'),
                'details' => __('Too many requests. Please wait and try again.', 'shopvision'),
            ]);
        }

        if ($status_code !== 200) {
            $error_msg = $data['error']['message'] ?? '';
            wp_send_json_error([
                'message' => sprintf(__('API error (HTTP %d)', 'shopvision'), $status_code),
                'details' => $error_msg ?: __('Unknown error.', 'shopvision'),
            ]);
        }

        // Success!
        wp_send_json_success([
            'message' => __('API key works!', 'shopvision'),
            'details' => __('The API key is valid and has access to the Gemini API.', 'shopvision'),
        ]);
    }
}

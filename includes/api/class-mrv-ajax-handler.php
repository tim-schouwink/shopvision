<?php
/**
 * AJAX handler for visualization requests
 */

if (!defined('ABSPATH')) {
    exit;
}

class MRV_Ajax_Handler {

    /**
     * Allowed mime types for upload
     */
    private const ALLOWED_TYPES = ['image/jpeg', 'image/png', 'image/webp', 'image/heic', 'image/heif'];

    /**
     * Max file size in bytes (10MB)
     */
    private const MAX_FILE_SIZE = 10 * 1024 * 1024;

    /**
     * Process visualization request
     * Supports both single product (product_id) and multi-product (product_ids[]) modes
     */
    public function process_visualization(): void {
        // Verify nonce
        if (!check_ajax_referer('mrv_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => __('Security check failed.', 'shopvision')], 403);
        }

        // Check rate limit
        $rate_check = MRV_Rate_Limiter::check();
        if (!$rate_check['allowed']) {
            $reset_time = MRV_Rate_Limiter::format_reset_time($rate_check['reset_in']);
            wp_send_json_error([
                'message' => sprintf(
                    __('Too many requests. Please try again in %s.', 'shopvision'),
                    $reset_time
                ),
                'rate_limited' => true,
                'reset_in' => $rate_check['reset_in'],
            ], 429);
        }

        // Get product IDs - support both single and multiple
        $product_ids = [];

        if (!empty($_POST['product_ids']) && is_array($_POST['product_ids'])) {
            // Multi-product mode
            $product_ids = array_map('absint', $_POST['product_ids']);
            $product_ids = array_filter($product_ids);

            // Limit to max items
            $max_items = (int) get_option('mrv_multi_product_max_items', 5);
            $product_ids = array_slice($product_ids, 0, $max_items);
        } elseif (!empty($_POST['product_id'])) {
            // Single product mode (backwards compatible)
            $product_ids = [absint($_POST['product_id'])];
        }

        if (empty($product_ids)) {
            wp_send_json_error(['message' => __('Invalid product.', 'shopvision')], 400);
        }

        // Check if all products have visualizer enabled
        foreach ($product_ids as $product_id) {
            if (!$this->is_product_enabled($product_id)) {
                wp_send_json_error(['message' => __('Visualizer not enabled for one or more products.', 'shopvision')], 400);
            }
        }

        // Validate file upload
        if (empty($_FILES['room_image'])) {
            wp_send_json_error(['message' => __('No image uploaded.', 'shopvision')], 400);
        }

        $file = $_FILES['room_image'];

        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            wp_send_json_error(['message' => __('Upload error.', 'shopvision')], 400);
        }

        // Validate file size
        if ($file['size'] > self::MAX_FILE_SIZE) {
            wp_send_json_error(['message' => __('File too large. Maximum size is 10MB.', 'shopvision')], 400);
        }

        // Validate mime type
        $mime_type = mime_content_type($file['tmp_name']);
        if (!in_array($mime_type, self::ALLOWED_TYPES)) {
            wp_send_json_error(['message' => __('Invalid file type. Please upload JPG, PNG, or WebP.', 'shopvision')], 400);
        }

        // Convert HEIC/HEIF to JPEG before processing (prevents API timeouts)
        if (in_array($mime_type, ['image/heic', 'image/heif'])) {
            $converted_path = $this->convert_heic_to_jpeg($file['tmp_name']);
            if ($converted_path) {
                $file['tmp_name'] = $converted_path;
            } else {
                wp_send_json_error(['message' => __('HEIC/HEIF files are not supported on this server. Please upload a JPG or PNG.', 'shopvision')], 400);
            }
        }

        // Get consent status from request
        $consent_given = isset($_POST['consent']) && $_POST['consent'] === '1';

        // Process with Gemini API
        $gemini = new MRV_Gemini_Client();

        if (!$gemini->is_configured()) {
            wp_send_json_error(['message' => __('API not configured. Please contact the administrator.', 'shopvision')], 500);
        }

        // Generate visualization with all products
        $result = $gemini->generate_visualization($file['tmp_name'], $product_ids);

        if (!$result['success']) {
            wp_send_json_error(['message' => $result['error']], 500);
        }

        // Save generated image and create generation post (use first product as primary)
        $generation_data = $this->save_generation($result['image_data'], $product_ids, $consent_given);

        if (!$generation_data) {
            wp_send_json_error(['message' => __('Failed to save generated image.', 'shopvision')], 500);
        }

        // Increment rate limit counter after successful generation
        MRV_Rate_Limiter::increment();

        wp_send_json_success([
            'image_url'     => $generation_data['image_url'],
            'generation_id' => $generation_data['generation_id'],
        ]);
    }

    /**
     * Save generated image and create generation post
     *
     * @param string    $base64_data   Base64 encoded image data
     * @param array|int $product_ids   WooCommerce product ID(s) - single int or array
     * @param bool      $consent_given Whether user gave consent for marketing use
     * @return array|null Array with image_url and generation_id, or null on failure
     */
    private function save_generation(string $base64_data, array|int $product_ids, bool $consent_given): ?array {
        // Normalize to array
        if (!is_array($product_ids)) {
            $product_ids = [$product_ids];
        }
        $upload_dir = wp_upload_dir();

        // Create separate folder for visualizations
        $mrv_dir = $upload_dir['basedir'] . '/mrv-visualizations';
        if (!file_exists($mrv_dir)) {
            wp_mkdir_p($mrv_dir);
        }

        // Add .htaccess to prevent direct listing
        $htaccess_path = $mrv_dir . '/.htaccess';
        if (!file_exists($htaccess_path)) {
            file_put_contents($htaccess_path, "Options -Indexes\n");
        }

        $filename = 'visualization-' . time() . '-' . wp_generate_password(8, false) . '.png';
        $file_path = $mrv_dir . '/' . $filename;
        $relative_path = 'mrv-visualizations/' . $filename;
        $file_url = $upload_dir['baseurl'] . '/mrv-visualizations/' . $filename;

        // Decode and save file
        $image_data = base64_decode($base64_data);

        if ($image_data === false) {
            return null;
        }

        if (file_put_contents($file_path, $image_data) === false) {
            return null;
        }

        // Get session ID for guest tracking
        if (!session_id()) {
            @session_start();
        }
        $session_id = session_id() ?: wp_generate_password(32, false);

        // Create generation post
        $generation_id = MRV_Post_Types::create_generation([
            'product_ids'   => $product_ids,
            'image_path'    => $relative_path,
            'image_url'     => $file_url,
            'consent_given' => $consent_given,
            'session_id'    => $session_id,
            'user_agent'    => isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field($_SERVER['HTTP_USER_AGENT']) : '',
        ]);

        if (is_wp_error($generation_id)) {
            @unlink($file_path);
            return null;
        }

        // Set tracking cookie for conversion tracking
        if (class_exists('MRV_Conversion_Tracker')) {
            MRV_Conversion_Tracker::set_tracking_cookie($session_id);
        }

        return [
            'image_url'     => $file_url,
            'generation_id' => $generation_id,
        ];
    }

    /**
     * Update consent status for a generation
     */
    public function update_consent(): void {
        // Verify nonce
        if (!check_ajax_referer('mrv_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => __('Security check failed.', 'shopvision')], 403);
        }

        $generation_id = isset($_POST['generation_id']) ? absint($_POST['generation_id']) : 0;
        $consent = isset($_POST['consent']) && $_POST['consent'] === '1';

        if (!$generation_id) {
            wp_send_json_error(['message' => __('Invalid generation ID.', 'shopvision')], 400);
        }

        if ($consent) {
            $result = MRV_Post_Types::update_consent($generation_id, 'approved');
        } else {
            $result = MRV_Post_Types::update_consent($generation_id, 'pending');
        }

        if (!$result) {
            wp_send_json_error(['message' => __('Failed to update consent.', 'shopvision')], 500);
        }

        wp_send_json_success(['status' => $consent ? 'approved' : 'pending']);
    }

    /**
     * Add product to cart via AJAX (fallback method)
     * Supports both simple and variable products
     */
    public function add_to_cart(): void {
        // Verify nonce
        if (!check_ajax_referer('mrv_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => __('Security check failed.', 'shopvision')], 403);
        }

        // Validate product ID
        $product_id = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;
        $variation_id = isset($_POST['variation_id']) ? absint($_POST['variation_id']) : 0;

        if (!$product_id) {
            wp_send_json_error(['message' => __('Invalid product.', 'shopvision')], 400);
        }

        // Ensure WooCommerce is loaded
        if (!function_exists('WC') || !WC()->cart) {
            wp_send_json_error(['message' => __('WooCommerce not available.', 'shopvision')], 500);
        }

        // Get product
        $product = wc_get_product($product_id);
        if (!$product) {
            wp_send_json_error(['message' => __('Product not found.', 'shopvision')], 404);
        }

        // Handle variation attributes
        $variation_attributes = [];
        if ($variation_id) {
            // Parse variation attributes if provided
            if (!empty($_POST['variation_attributes'])) {
                $variation_attributes = json_decode(stripslashes($_POST['variation_attributes']), true);
                if (!is_array($variation_attributes)) {
                    $variation_attributes = [];
                }
            }

            // If no attributes provided, get them from the variation
            if (empty($variation_attributes)) {
                $variation = wc_get_product($variation_id);
                if ($variation && $variation->is_type('variation')) {
                    $variation_attributes = $variation->get_variation_attributes();
                }
            }
        }

        // Add to cart
        $cart_item_key = WC()->cart->add_to_cart(
            $product_id,
            1,
            $variation_id,
            $variation_attributes
        );

        if (!$cart_item_key) {
            wp_send_json_error(['message' => __('Could not add to cart.', 'shopvision')], 500);
        }

        // Get refreshed fragments
        ob_start();
        woocommerce_mini_cart();
        $mini_cart = ob_get_clean();

        $data = [
            'fragments' => apply_filters('woocommerce_add_to_cart_fragments', [
                'div.widget_shopping_cart_content' => '<div class="widget_shopping_cart_content">' . $mini_cart . '</div>',
            ]),
            'cart_hash' => WC()->cart->get_cart_hash(),
        ];

        wp_send_json_success($data);
    }

    /**
     * Track WhatsApp button click
     */
    public function track_whatsapp_click(): void {
        // Verify nonce
        if (!check_ajax_referer('mrv_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => __('Security check failed.', 'shopvision')], 403);
        }

        // Increment WhatsApp click counter
        $clicks = (int) get_option('mrv_alltime_whatsapp_clicks', 0);
        update_option('mrv_alltime_whatsapp_clicks', $clicks + 1);

        // Clear analytics cache
        delete_transient('mrv_analytics_7days');
        delete_transient('mrv_analytics_30days');
        delete_transient('mrv_analytics_90days');
        delete_transient('mrv_analytics_all');

        wp_send_json_success(['tracked' => true]);
    }

    /**
     * Check if a product has visualizer enabled (all products, directly, or via tag)
     */
    private function is_product_enabled(int $product_id): bool {
        // Check if all products are enabled
        if (get_option('mrv_all_products_enabled', false)) {
            return true;
        }

        // Check if product is directly enabled
        $enabled_products = get_option('mrv_enabled_products', []);
        if (in_array($product_id, $enabled_products)) {
            return true;
        }

        // Check if product has an enabled tag
        $enabled_tags = get_option('mrv_enabled_tags', []);
        if (!empty($enabled_tags)) {
            $product_tags = wp_get_post_terms($product_id, 'product_tag', ['fields' => 'ids']);
            if (!is_wp_error($product_tags) && !empty(array_intersect($product_tags, $enabled_tags))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Convert HEIC/HEIF to JPEG
     * Returns path to converted file or null on failure
     */
    private function convert_heic_to_jpeg(string $path): ?string {
        $temp_file = wp_tempnam('mrv_converted_') . '.jpg';

        // Try ImageMagick extension first
        if (extension_loaded('imagick')) {
            try {
                $imagick = new \Imagick($path);
                $imagick->setImageFormat('jpeg');
                $imagick->setImageCompressionQuality(85);
                $imagick->writeImage($temp_file);
                $imagick->destroy();
                if (file_exists($temp_file) && filesize($temp_file) > 0) {
                    return $temp_file;
                }
            } catch (\Exception $e) {
                // Fall through
            }
        }

        // Try WordPress image editor
        $editor = wp_get_image_editor($path);
        if (!is_wp_error($editor)) {
            $saved = $editor->save($temp_file, 'image/jpeg');
            if (!is_wp_error($saved) && file_exists($saved['path'])) {
                return $saved['path'];
            }
        }

        // Cleanup on failure
        @unlink($temp_file);
        return null;
    }
}

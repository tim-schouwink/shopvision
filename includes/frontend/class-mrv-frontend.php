<?php
/**
 * Frontend functionality class
 */

if (!defined('ABSPATH')) {
    exit;
}

class MRV_Frontend {

    /**
     * Icon SVG paths
     */
    private const ICONS = [
        'camera' => '<rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/>',
        'eye' => '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>',
        'home' => '<path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>',
        'sparkle' => '<path d="M12 3l1.5 6.5L20 12l-6.5 2.5L12 21l-1.5-6.5L4 12l6.5-2.5z"/>',
        'wand' => '<path d="M15 4V2"/><path d="M15 16v-2"/><path d="M8 9h2"/><path d="M20 9h2"/><path d="M17.8 11.8L19 13"/><path d="M15 9h.01"/><path d="M17.8 6.2L19 5"/><path d="m3 21 9-9"/><path d="M12.2 6.2L11 5"/>',
    ];

    /**
     * Check if current product has visualizer enabled
     */
    private function is_visualizer_enabled(): bool {
        global $product;

        if (!$product || !is_a($product, 'WC_Product')) {
            return false;
        }

        // Check if all products are enabled
        if (get_option('mrv_all_products_enabled', false)) {
            return true;
        }

        $product_id = $product->get_id();

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
     * Output dynamic CSS variables based on settings
     */
    public function output_dynamic_styles(): void {
        if (!function_exists('is_product') || !is_singular('product')) {
            return;
        }

        $accent_color = get_option('mrv_accent_color', '#2563eb');
        $button_text_color = get_option('mrv_button_text_color', '');
        $hover_bg_color = get_option('mrv_hover_bg_color', '');
        $hover_text_color = get_option('mrv_hover_text_color', '');
        $button_style = get_option('mrv_button_style', 'outline');
        $button_radius = get_option('mrv_button_radius', 'rounded');

        // Text color falls back to accent color if empty
        $text_color = !empty($button_text_color) ? $button_text_color : $accent_color;

        // Hover colors with defaults
        $hover_bg = !empty($hover_bg_color) ? $hover_bg_color : $accent_color;
        $hover_text = !empty($hover_text_color) ? $hover_text_color : '#ffffff';

        // Calculate derived colors
        $accent_light = $this->hex_to_rgba($accent_color, 0.08);
        $hover_bg_light = $this->hex_to_rgba($hover_bg, 0.1);

        // Calculate radius value
        $radius_map = [
            'sharp' => '0',
            'rounded' => '8px',
            'pill' => '100px',
        ];
        $radius_value = $radius_map[$button_radius] ?? '8px';

        ?>
        <style id="mrv-dynamic-styles">
            :root {
                --mrv-accent: <?php echo esc_attr($accent_color); ?>;
                --mrv-accent-light: <?php echo esc_attr($accent_light); ?>;
                --mrv-btn-text: <?php echo esc_attr($text_color); ?>;
                --mrv-hover-bg: <?php echo esc_attr($hover_bg); ?>;
                --mrv-hover-text: <?php echo esc_attr($hover_text); ?>;
                --mrv-hover-bg-light: <?php echo esc_attr($hover_bg_light); ?>;
                --mrv-btn-radius: <?php echo esc_attr($radius_value); ?>;
            }
        </style>
        <?php
    }

    /**
     * Adjust hex color brightness
     */
    private function adjust_brightness(string $hex, int $percent): string {
        $hex = ltrim($hex, '#');

        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));

        $r = max(0, min(255, $r + ($r * $percent / 100)));
        $g = max(0, min(255, $g + ($g * $percent / 100)));
        $b = max(0, min(255, $b + ($b * $percent / 100)));

        return sprintf('#%02x%02x%02x', (int)$r, (int)$g, (int)$b);
    }

    /**
     * Convert hex to rgba
     */
    private function hex_to_rgba(string $hex, float $alpha): string {
        $hex = ltrim($hex, '#');

        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));

        return sprintf('rgba(%d, %d, %d, %s)', $r, $g, $b, $alpha);
    }

    /**
     * Get icon SVG
     */
    private function get_icon_svg(string $type): string {
        return self::ICONS[$type] ?? self::ICONS['camera'];
    }

    /**
     * Render the visualizer button
     */
    public function render_visualizer_button(): void {
        if (!$this->is_visualizer_enabled()) {
            return;
        }

        global $product;

        $button_text = get_option('mrv_button_text', __('Visualize in your room', 'shopvision'));
        $product_id = $product->get_id();
        $button_style = get_option('mrv_button_style', 'outline');
        $icon_enabled = get_option('mrv_icon_enabled', true);
        $icon_type = get_option('mrv_icon_type', 'camera');

        // Build CSS classes
        $classes = ['mrv-visualizer-button', 'mrv-btn-' . $button_style];

        ?>
        <button type="button"
                class="<?php echo esc_attr(implode(' ', $classes)); ?>"
                data-product-id="<?php echo esc_attr($product_id); ?>">
            <?php if ($icon_enabled) : ?>
                <span class="mrv-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <?php echo $this->get_icon_svg($icon_type); ?>
                    </svg>
                </span>
            <?php endif; ?>
            <?php echo esc_html($button_text); ?>
        </button>
        <?php
    }

    /**
     * Render the modal in footer
     */
    public function render_modal(): void {
        if (!function_exists('is_product') || !is_singular('product')) {
            return;
        }

        include MRV_PLUGIN_DIR . 'templates/modal.php';
    }

    /**
     * Render visualizer button via shortcode
     * Usage: [shopvision]
     *
     * @param array $atts Shortcode attributes
     * @return string Button HTML
     */
    public function render_shortcode($atts = []): string {
        // Only render on single product pages
        if (!function_exists('is_product') || !is_singular('product')) {
            return '';
        }

        if (!$this->is_visualizer_enabled()) {
            return '';
        }

        global $product;

        $button_text = get_option('mrv_button_text', __('Visualize in your room', 'shopvision'));
        $product_id = $product->get_id();
        $button_style = get_option('mrv_button_style', 'outline');
        $icon_enabled = get_option('mrv_icon_enabled', true);
        $icon_type = get_option('mrv_icon_type', 'camera');

        // Build CSS classes
        $classes = ['mrv-visualizer-button', 'mrv-btn-' . $button_style];

        ob_start();
        ?>
        <button type="button"
                class="<?php echo esc_attr(implode(' ', $classes)); ?>"
                data-product-id="<?php echo esc_attr($product_id); ?>">
            <?php if ($icon_enabled) : ?>
                <span class="mrv-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <?php echo $this->get_icon_svg($icon_type); ?>
                    </svg>
                </span>
            <?php endif; ?>
            <?php echo esc_html($button_text); ?>
        </button>
        <?php
        return ob_get_clean();
    }

    /**
     * Enqueue frontend styles
     */
    public function enqueue_styles(): void {
        // Only load if WooCommerce is active and on a single product page
        if (!function_exists('is_product') || !is_singular('product')) {
            return;
        }

        wp_enqueue_style(
            'mrv-frontend',
            MRV_PLUGIN_URL . 'assets/css/mrv-frontend.css',
            [],
            MRV_VERSION
        );
    }

    /**
     * Enqueue frontend scripts
     */
    public function enqueue_scripts(): void {
        // Only load if WooCommerce is active and on a single product page
        if (!function_exists('is_product') || !is_singular('product')) {
            return;
        }

        // Ensure WooCommerce add-to-cart script is loaded (provides wc_add_to_cart_params)
        wp_enqueue_script('wc-add-to-cart');
        wp_enqueue_script('wc-cart-fragments');

        // Cart management for multi-product feature
        wp_enqueue_script(
            'mrv-cart',
            MRV_PLUGIN_URL . 'assets/js/mrv-cart.js',
            [],
            MRV_VERSION,
            true
        );

        wp_enqueue_script(
            'mrv-frontend',
            MRV_PLUGIN_URL . 'assets/js/mrv-frontend.js',
            ['wc-add-to-cart', 'mrv-cart'],
            MRV_VERSION,
            true
        );

        wp_localize_script('mrv-frontend', 'mrvConfig', $this->get_frontend_config());
    }

    /**
     * Get centralized frontend configuration
     * All settings in one place for easy access in JavaScript
     */
    private function get_frontend_config(): array {
        $accent_color = get_option('mrv_accent_color', '#2563eb');
        $button_text_color = get_option('mrv_button_text_color', '');
        $text_color = !empty($button_text_color) ? $button_text_color : $accent_color;

        return [
            // Core
            'version'  => MRV_VERSION,
            'ajaxUrl'  => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('mrv_nonce'),

            // Styling settings
            'styling'  => [
                'accentColor'  => $accent_color,
                'accentHover'  => $this->adjust_brightness($accent_color, -15),
                'accentLight'  => $this->hex_to_rgba($accent_color, 0.08),
                'textColor'    => $text_color,
                'buttonStyle'  => get_option('mrv_button_style', 'outline'),
                'buttonRadius' => get_option('mrv_button_radius', 'rounded'),
                'iconEnabled'  => (bool) get_option('mrv_icon_enabled', true),
                'iconType'     => get_option('mrv_icon_type', 'camera'),
            ],

            // File upload settings
            'upload'   => [
                'maxSize'    => 10 * 1024 * 1024, // 10MB
                'validTypes' => ['image/jpeg', 'image/png', 'image/webp', 'image/heic', 'image/heif'],
            ],

            // Internationalization
            'i18n'     => [
                'uploading'    => __('Uploading...', 'shopvision'),
                'processing'   => __('AI is creating your visualization...', 'shopvision'),
                'error'        => __('Something went wrong. Please try again.', 'shopvision'),
                'invalidType'  => __('Invalid file type. Use JPG, PNG, WebP, HEIC or HEIF.', 'shopvision'),
                'fileTooLarge' => __('File is too large. Maximum 10MB.', 'shopvision'),
                'download'     => get_option('mrv_download_button_text', __('Download', 'shopvision')),
                'tryAgain'     => __('Try again', 'shopvision'),
                'orderThis'    => get_option('mrv_order_button_text', __('Order This', 'shopvision')),
                'addingToCart' => __('Adding...', 'shopvision'),
                'addedToCart'  => __('Added!', 'shopvision'),
                'dropHere'     => __('Drop your room photo here', 'shopvision'),
                'orBrowse'     => __('or click to browse', 'shopvision'),
                'maxSize'      => __('Maximum 5MB - JPG, PNG, WebP, HEIC or HEIF', 'shopvision'),
            ],

            // Feature flags
            'features' => [
                'orderButton'    => (bool) get_option('mrv_order_button_enabled', false),
                'examplesGallery' => (bool) get_option('mrv_examples_enabled', false),
                'multiProduct'   => (bool) get_option('mrv_multi_product_enabled', false),
                'maxProducts'    => (int) get_option('mrv_multi_product_max_items', 5),
                'whatsapp'       => (bool) get_option('mrv_whatsapp_enabled', false),
                'multipleImages' => false, // Future: allow multiple room images
                'roomPresets'    => false, // Future: preset room templates
                'shareResult'    => false, // Future: share visualization
            ],

            // WhatsApp settings
            'whatsapp' => [
                'enabled'    => (bool) get_option('mrv_whatsapp_enabled', false),
                'phone'      => get_option('mrv_whatsapp_phone', ''),
                'buttonText' => get_option('mrv_whatsapp_button_text', __('Request quote via WhatsApp', 'shopvision')),
                'message'    => get_option('mrv_whatsapp_message', __('Hello! I would like a quote for the following items:', 'shopvision')),
                'bgColor'    => get_option('mrv_whatsapp_bg_color', '#25D366'),
                'textColor'  => get_option('mrv_whatsapp_text_color', '#ffffff'),
                'hoverBg'    => get_option('mrv_whatsapp_hover_bg_color', '#128C7E'),
                'hoverText'  => get_option('mrv_whatsapp_hover_text_color', '#ffffff'),
            ],

            // Preset (interior/fashion/custom)
            'preset' => get_option('mrv_preset', 'interior'),

            // Preset-specific labels
            'presetLabels' => $this->get_preset_labels(),

            // Current product data (for multi-product cart)
            'currentProduct' => $this->get_current_product_data(),

            // Example images for deck gallery
            'examples' => $this->get_example_images(),

            // WooCommerce Store API
            'wc' => [
                'cartUrl'       => wc_get_cart_url(),
                'storeApiUrl'   => rest_url('wc/store/v1/cart/add-item'),
                'storeApiNonce' => wp_create_nonce('wc_store_api'),
            ],
        ];
    }

    /**
     * Get current product data for multi-product cart
     */
    private function get_current_product_data(): ?array {
        // Use get_the_ID() instead of global $product since scripts are enqueued
        // before WooCommerce sets up the product global
        $product_id = get_the_ID();
        if (!$product_id) {
            return null;
        }

        $product = wc_get_product($product_id);
        if (!$product) {
            return null;
        }

        $image_id = $product->get_image_id();
        $image_thumb = $image_id ? wp_get_attachment_image_url($image_id, 'thumbnail') : wc_placeholder_img_src('thumbnail');
        $image_url = $image_id ? wp_get_attachment_image_url($image_id, 'medium') : wc_placeholder_img_src('medium');

        return [
            'product_id'  => $product->get_id(),
            'name'        => $product->get_name(),
            'price'       => $product->get_price_html(),
            'image_url'   => $image_url,
            'image_thumb' => $image_thumb,
            'permalink'   => $product->get_permalink(),
        ];
    }

    /**
     * Get preset-specific UI labels
     */
    private function get_preset_labels(): array {
        $preset = get_option('mrv_preset', 'interior');

        $labels = [
            'interior' => [
                'dropHere'     => __('Drag your room photo here', 'shopvision'),
                'orBrowse'     => __('or click to browse — your visualization starts immediately', 'shopvision'),
                'processing'   => __('AI is creating your visualization...', 'shopvision'),
                'exampleTitle' => __('See it for yourself', 'shopvision'),
                'exampleSubtitle' => __('Upload a photo of your room', 'shopvision'),
                'buttonText'   => __('Visualize in your room', 'shopvision'),
            ],
            'fashion' => [
                'dropHere'     => __('Upload a photo of yourself', 'shopvision'),
                'orBrowse'     => __('or click to browse — your visualization starts immediately', 'shopvision'),
                'processing'   => __('AI is fitting the clothing to your photo...', 'shopvision'),
                'exampleTitle' => __('Try it on virtually', 'shopvision'),
                'exampleSubtitle' => __('Upload a photo of yourself', 'shopvision'),
                'buttonText'   => __('Virtual try-on', 'shopvision'),
            ],
            'custom' => [
                'dropHere'     => __('Drag your photo here', 'shopvision'),
                'orBrowse'     => __('or click to browse — your visualization starts immediately', 'shopvision'),
                'processing'   => __('AI is creating your visualization...', 'shopvision'),
                'exampleTitle' => __('See it for yourself', 'shopvision'),
                'exampleSubtitle' => __('Upload a photo', 'shopvision'),
                'buttonText'   => __('Visualize', 'shopvision'),
            ],
        ];

        return $labels[$preset] ?? $labels['interior'];
    }

    /**
     * Get example images for deck gallery
     */
    private function get_example_images(): array {
        if (!get_option('mrv_examples_enabled', false)) {
            return [];
        }

        $images = [];
        for ($i = 1; $i <= 3; $i++) {
            $image_id = get_option('mrv_example_image_' . $i, 0);
            if ($image_id) {
                $image_url = wp_get_attachment_image_url($image_id, 'medium');
                if ($image_url) {
                    $images[] = $image_url;
                }
            }
        }

        return $images;
    }
}

<?php
/**
 * Widget Shortcodes - Displays approved visualizations in various layouts
 */

if (!defined('ABSPATH')) {
    exit;
}

class MRV_Widgets {

    /**
     * Minimum images required for marquee gallery
     */
    private const MIN_IMAGES = 6;

    /**
     * Initialize widgets
     */
    public function __construct() {
        add_action('init', [$this, 'register_shortcodes']);
    }

    /**
     * Register all widget shortcodes
     *
     * Hooked to 'init' per WordPress best practices.
     */
    public function register_shortcodes(): void {
        add_shortcode('shopvision_gallery', [$this, 'render_marquee_gallery']);
    }

    /**
     * Render the marquee gallery shortcode
     *
     * @param array $atts Shortcode attributes (unused, settings come from admin)
     * @return string HTML output
     */
    public function render_marquee_gallery($atts = []): string {
        // Check if widget is enabled
        if (!get_option('mrv_widget_marquee_enabled', false)) {
            return '';
        }

        // Get settings from database
        $settings = $this->get_marquee_settings();

        // Get visualizations
        $images = $this->get_gallery_images(
            $settings['status'],
            (int) $settings['limit']
        );

        // Need minimum images for seamless loop
        if (count($images) < self::MIN_IMAGES) {
            return '';
        }

        // Enqueue assets
        $this->enqueue_widget_assets();

        // Render HTML
        return $this->render_marquee_html($images, $settings);
    }

    /**
     * Get marquee settings from database
     *
     * @return array Settings array
     */
    private function get_marquee_settings(): array {
        return [
            'title'         => get_option('mrv_widget_marquee_title', __('Customer Visualizations', 'shopvision')),
            'subtitle'      => get_option('mrv_widget_marquee_subtitle', ''),
            'button_text'   => get_option('mrv_widget_marquee_button_text', __('Shop Now', 'shopvision')),
            'button_url'    => get_option('mrv_widget_marquee_button_url', '/shop'),
            'bg_color'      => get_option('mrv_widget_marquee_bg_color', '#f9fafb'),
            'overlay_color' => get_option('mrv_widget_marquee_overlay_color', 'rgba(0,0,0,0.6)'),
            'text_color'    => get_option('mrv_widget_marquee_text_color', '#ffffff'),
            'button_color'  => get_option('mrv_widget_marquee_button_color', get_option('mrv_accent_color', '#2563eb')),
            'height'        => get_option('mrv_widget_marquee_height', '60vh'),
            'speed'         => (int) get_option('mrv_widget_marquee_speed', 30),
            'fade_width'    => (int) get_option('mrv_widget_marquee_fade_width', 150),
            'status'        => get_option('mrv_widget_marquee_status', 'featured'),
            'limit'         => (int) get_option('mrv_widget_marquee_limit', 12),
            'link_product'  => (bool) get_option('mrv_widget_marquee_link_product', true),
        ];
    }

    /**
     * Get approved/featured visualizations with product links
     *
     * @param string $status Status filter: 'featured' or 'approved,featured'
     * @param int    $limit  Maximum number of images
     * @return array Array of image data
     */
    private function get_gallery_images(string $status, int $limit): array {
        $statuses = array_map('trim', explode(',', $status));

        $generations = MRV_Post_Types::get_generations([
            'posts_per_page' => $limit,
            'tax_query' => [
                [
                    'taxonomy' => MRV_Post_Types::TAXONOMY,
                    'terms'    => $statuses,
                    'field'    => 'slug',
                    'operator' => 'IN',
                ],
            ],
        ]);

        $images = [];
        foreach ($generations as $gen) {
            $url = get_post_meta($gen->ID, '_mrv_image_url', true);
            if (!$url) {
                continue;
            }

            // Get product IDs (multi-product support)
            $product_ids = get_post_meta($gen->ID, '_mrv_product_ids', true);
            if (!is_array($product_ids) || empty($product_ids)) {
                $single_id = get_post_meta($gen->ID, '_mrv_product_id', true);
                $product_ids = $single_id ? [$single_id] : [];
            }

            // Find most expensive product for link
            $product_url = null;
            $product_name = '';
            $max_price = 0;

            foreach ($product_ids as $pid) {
                $product = wc_get_product($pid);
                if ($product) {
                    $price = (float) $product->get_price();
                    if ($price > $max_price || $product_url === null) {
                        $max_price = $price;
                        $product_url = get_permalink($pid);
                        $product_name = $product->get_name();
                    }
                }
            }

            $images[] = [
                'url'          => $url,
                'product_url'  => $product_url,
                'product_name' => $product_name,
            ];
        }

        return $images;
    }

    /**
     * Enqueue widget assets
     */
    private function enqueue_widget_assets(): void {
        wp_enqueue_style(
            'mrv-widgets',
            MRV_PLUGIN_URL . 'assets/css/mrv-widgets.css',
            [],
            MRV_VERSION
        );
    }

    /**
     * Render marquee gallery HTML
     *
     * @param array $images   Image data array
     * @param array $settings Widget settings
     * @return string HTML output
     */
    private function render_marquee_html(array $images, array $settings): string {
        // Split images into two rows
        $half = (int) ceil(count($images) / 2);
        $row1_images = array_slice($images, 0, $half);
        $row2_images = array_slice($images, $half);

        // Ensure both rows have images
        if (empty($row2_images)) {
            $row2_images = $row1_images;
        }

        // Duplicate images 3x for seamless loop
        $row1_images = array_merge($row1_images, $row1_images, $row1_images);
        $row2_images = array_merge($row2_images, $row2_images, $row2_images);

        ob_start();
        ?>
        <section class="mrv-marquee-gallery" style="
            height: <?php echo esc_attr($settings['height']); ?>;
            --mrv-speed: <?php echo esc_attr($settings['speed']); ?>s;
            --mrv-gallery-bg: <?php echo esc_attr($settings['bg_color']); ?>;
            --mrv-overlay-color: <?php echo esc_attr($settings['overlay_color']); ?>;
            --mrv-text-color: <?php echo esc_attr($settings['text_color']); ?>;
            --mrv-button-color: <?php echo esc_attr($settings['button_color']); ?>;
            --mrv-fade-width: <?php echo esc_attr($settings['fade_width']); ?>px;
        ">
            <div class="mrv-marquee-track">
                <!-- Row 1: Scroll left -->
                <div class="mrv-marquee-row">
                    <?php foreach ($row1_images as $image): ?>
                        <?php echo $this->render_image($image, $settings['link_product']); ?>
                    <?php endforeach; ?>
                </div>

                <!-- Row 2: Scroll right -->
                <div class="mrv-marquee-row mrv-marquee-row--reverse">
                    <?php foreach ($row2_images as $image): ?>
                        <?php echo $this->render_image($image, $settings['link_product']); ?>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Overlay -->
            <div class="mrv-marquee-overlay">
                <div class="mrv-marquee-content">
                    <?php if (!empty($settings['title'])): ?>
                        <h2 class="mrv-marquee-title"><?php echo esc_html($settings['title']); ?></h2>
                    <?php endif; ?>

                    <?php if (!empty($settings['subtitle'])): ?>
                        <p class="mrv-marquee-subtitle"><?php echo esc_html($settings['subtitle']); ?></p>
                    <?php endif; ?>

                    <?php if (!empty($settings['button_text']) && !empty($settings['button_url'])): ?>
                        <a href="<?php echo esc_url($settings['button_url']); ?>" class="mrv-marquee-btn">
                            <?php echo esc_html($settings['button_text']); ?>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </section>
        <?php
        return ob_get_clean();
    }

    /**
     * Render a single image element
     *
     * @param array $image       Image data
     * @param bool  $link_product Whether to link to product
     * @return string HTML output
     */
    private function render_image(array $image, bool $link_product): string {
        $img_html = sprintf(
            '<img src="%s" alt="%s" class="mrv-marquee-img" loading="lazy" />',
            esc_url($image['url']),
            esc_attr($image['product_name'] ?: __('Customer visualization', 'shopvision'))
        );

        if ($link_product && !empty($image['product_url'])) {
            return sprintf(
                '<a href="%s" class="mrv-marquee-link" title="%s">%s</a>',
                esc_url($image['product_url']),
                esc_attr($image['product_name']),
                $img_html
            );
        }

        return $img_html;
    }

    /**
     * Get gallery images for admin preview (static method for AJAX)
     *
     * @param string $status Status filter
     * @param int    $limit  Maximum images
     * @return array Image URLs
     */
    public static function get_preview_images(string $status = 'featured', int $limit = 12): array {
        $widget = new self();
        $images = $widget->get_gallery_images($status, $limit);

        // Return just URLs for preview
        return array_map(function ($img) {
            return $img['url'];
        }, $images);
    }
}

<?php
/**
 * Settings page class
 */

if (!defined('ABSPATH')) {
    exit;
}

class MRV_Settings {

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
     * Add settings page under WooCommerce menu
     */
    public function add_settings_page(): void {
        // Main settings page (includes all tabs: Algemeen, Styling, Producten, Generaties)
        add_submenu_page(
            'woocommerce',
            __('Shopvision', 'shopvision'),
            __('Shopvision', 'shopvision'),
            'manage_woocommerce',
            'mrv-settings',
            [$this, 'render_settings_page']
        );
    }

    /**
     * Register settings
     *
     * Settings are split into separate groups per tab to prevent
     * settings from being cleared when saving a different tab.
     */
    public function register_settings(): void {
        // ========================================
        // Group: mrv_settings_general (Algemeen tab)
        // ========================================
        register_setting('mrv_settings_general', 'mrv_api_key', [
            'type'              => 'string',
            'sanitize_callback' => [$this, 'sanitize_api_key'],
        ]);

        register_setting('mrv_settings_general', 'mrv_preset', [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => 'interior',
        ]);

        register_setting('mrv_settings_general', 'mrv_custom_prompt', [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_textarea_field',
            'default'           => '',
        ]);

        register_setting('mrv_settings_general', 'mrv_button_text', [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => 'Visualize in your room',
        ]);

        register_setting('mrv_settings_general', 'mrv_button_position', [
            'type'              => 'string',
            'sanitize_callback' => [$this, 'sanitize_button_position'],
            'default'           => 'after_button',
        ]);

        register_setting('mrv_settings_general', 'mrv_cleanup_hours', [
            'type'              => 'integer',
            'sanitize_callback' => 'absint',
            'default'           => 24,
        ]);

        register_setting('mrv_settings_general', 'mrv_rate_limit_guest', [
            'type'              => 'integer',
            'sanitize_callback' => 'absint',
            'default'           => 10,
        ]);

        register_setting('mrv_settings_general', 'mrv_rate_limit_logged_in', [
            'type'              => 'integer',
            'sanitize_callback' => 'absint',
            'default'           => 20,
        ]);

        register_setting('mrv_settings_general', 'mrv_order_button_enabled', [
            'type'              => 'boolean',
            'sanitize_callback' => 'rest_sanitize_boolean',
            'default'           => false,
        ]);

        register_setting('mrv_settings_general', 'mrv_order_button_text', [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => 'Order This',
        ]);

        register_setting('mrv_settings_general', 'mrv_download_button_text', [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => 'Download',
        ]);

        register_setting('mrv_settings_general', 'mrv_examples_enabled', [
            'type'              => 'boolean',
            'sanitize_callback' => 'rest_sanitize_boolean',
            'default'           => false,
        ]);

        register_setting('mrv_settings_general', 'mrv_example_image_1', [
            'type'              => 'integer',
            'sanitize_callback' => 'absint',
            'default'           => 0,
        ]);

        register_setting('mrv_settings_general', 'mrv_example_image_2', [
            'type'              => 'integer',
            'sanitize_callback' => 'absint',
            'default'           => 0,
        ]);

        register_setting('mrv_settings_general', 'mrv_example_image_3', [
            'type'              => 'integer',
            'sanitize_callback' => 'absint',
            'default'           => 0,
        ]);

        register_setting('mrv_settings_general', 'mrv_examples_text_enabled', [
            'type'              => 'boolean',
            'sanitize_callback' => 'rest_sanitize_boolean',
            'default'           => true,
        ]);

        register_setting('mrv_settings_general', 'mrv_examples_title', [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => 'See it for yourself',
        ]);

        register_setting('mrv_settings_general', 'mrv_examples_subtitle', [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => 'Upload a photo of your room',
        ]);

        register_setting('mrv_settings_general', 'mrv_multi_product_enabled', [
            'type'              => 'boolean',
            'sanitize_callback' => 'rest_sanitize_boolean',
            'default'           => false,
        ]);

        register_setting('mrv_settings_general', 'mrv_multi_product_max_items', [
            'type'              => 'integer',
            'sanitize_callback' => [$this, 'sanitize_max_items'],
            'default'           => 5,
        ]);

        // WhatsApp Button settings
        register_setting('mrv_settings_general', 'mrv_whatsapp_enabled', [
            'type'              => 'boolean',
            'sanitize_callback' => 'rest_sanitize_boolean',
            'default'           => false,
        ]);

        register_setting('mrv_settings_general', 'mrv_whatsapp_phone', [
            'type'              => 'string',
            'sanitize_callback' => [$this, 'sanitize_phone_number'],
            'default'           => '',
        ]);

        register_setting('mrv_settings_general', 'mrv_whatsapp_button_text', [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => 'Request quote via WhatsApp',
        ]);

        register_setting('mrv_settings_general', 'mrv_whatsapp_message', [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_textarea_field',
            'default'           => 'Hello! I would like a quote for the following items:',
        ]);

        // ========================================
        // Group: mrv_settings_styling (Styling tab)
        // ========================================
        register_setting('mrv_settings_styling', 'mrv_accent_color', [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_hex_color',
            'default'           => '#2563eb',
        ]);

        register_setting('mrv_settings_styling', 'mrv_button_text_color', [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_hex_color',
            'default'           => '',  // Empty = use accent color
        ]);

        register_setting('mrv_settings_styling', 'mrv_hover_bg_color', [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_hex_color',
            'default'           => '',  // Empty = use accent color
        ]);

        register_setting('mrv_settings_styling', 'mrv_hover_text_color', [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_hex_color',
            'default'           => '',  // Empty = white
        ]);

        register_setting('mrv_settings_styling', 'mrv_button_style', [
            'type'              => 'string',
            'sanitize_callback' => [$this, 'sanitize_button_style'],
            'default'           => 'outline',
        ]);

        register_setting('mrv_settings_styling', 'mrv_button_radius', [
            'type'              => 'string',
            'sanitize_callback' => [$this, 'sanitize_button_radius'],
            'default'           => 'rounded',
        ]);

        register_setting('mrv_settings_styling', 'mrv_icon_enabled', [
            'type'              => 'boolean',
            'sanitize_callback' => 'rest_sanitize_boolean',
            'default'           => true,
        ]);

        register_setting('mrv_settings_styling', 'mrv_icon_type', [
            'type'              => 'string',
            'sanitize_callback' => [$this, 'sanitize_icon_type'],
            'default'           => 'camera',
        ]);

        // WhatsApp button styling
        register_setting('mrv_settings_styling', 'mrv_whatsapp_bg_color', [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_hex_color',
            'default'           => '#25D366',  // WhatsApp green
        ]);

        register_setting('mrv_settings_styling', 'mrv_whatsapp_text_color', [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_hex_color',
            'default'           => '#ffffff',
        ]);

        register_setting('mrv_settings_styling', 'mrv_whatsapp_hover_bg_color', [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_hex_color',
            'default'           => '#128C7E',  // Darker WhatsApp green
        ]);

        register_setting('mrv_settings_styling', 'mrv_whatsapp_hover_text_color', [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_hex_color',
            'default'           => '#ffffff',
        ]);

        // ========================================
        // Group: mrv_settings_products (Producten tab)
        // ========================================
        register_setting('mrv_settings_products', 'mrv_enabled_products', [
            'type'              => 'array',
            'sanitize_callback' => [$this, 'sanitize_products'],
        ]);

        register_setting('mrv_settings_products', 'mrv_all_products_enabled', [
            'type'              => 'boolean',
            'sanitize_callback' => 'rest_sanitize_boolean',
            'default'           => false,
        ]);

        register_setting('mrv_settings_products', 'mrv_enabled_tags', [
            'type'              => 'array',
            'sanitize_callback' => [$this, 'sanitize_tags'],
        ]);

        // ========================================
        // Group: mrv_settings_widgets (Widgets tab)
        // ========================================
        register_setting('mrv_settings_widgets', 'mrv_widget_marquee_enabled', [
            'type'              => 'boolean',
            'sanitize_callback' => 'rest_sanitize_boolean',
            'default'           => false,
        ]);

        register_setting('mrv_settings_widgets', 'mrv_widget_marquee_title', [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => 'Bekijk onze resultaten',
        ]);

        register_setting('mrv_settings_widgets', 'mrv_widget_marquee_subtitle', [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => '',
        ]);

        register_setting('mrv_settings_widgets', 'mrv_widget_marquee_button_text', [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => 'Shop nu',
        ]);

        register_setting('mrv_settings_widgets', 'mrv_widget_marquee_button_url', [
            'type'              => 'string',
            'sanitize_callback' => 'esc_url_raw',
            'default'           => '/shop',
        ]);

        register_setting('mrv_settings_widgets', 'mrv_widget_marquee_bg_color', [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_hex_color',
            'default'           => '#f9fafb',
        ]);

        register_setting('mrv_settings_widgets', 'mrv_widget_marquee_overlay_color', [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => 'rgba(0,0,0,0.6)',
        ]);

        register_setting('mrv_settings_widgets', 'mrv_widget_marquee_text_color', [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_hex_color',
            'default'           => '#ffffff',
        ]);

        register_setting('mrv_settings_widgets', 'mrv_widget_marquee_button_color', [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_hex_color',
            'default'           => '',  // Empty = use accent color
        ]);

        register_setting('mrv_settings_widgets', 'mrv_widget_marquee_height', [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => '60vh',
        ]);

        register_setting('mrv_settings_widgets', 'mrv_widget_marquee_speed', [
            'type'              => 'integer',
            'sanitize_callback' => 'absint',
            'default'           => 30,
        ]);

        register_setting('mrv_settings_widgets', 'mrv_widget_marquee_status', [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => 'featured',
        ]);

        register_setting('mrv_settings_widgets', 'mrv_widget_marquee_limit', [
            'type'              => 'integer',
            'sanitize_callback' => 'absint',
            'default'           => 12,
        ]);

        register_setting('mrv_settings_widgets', 'mrv_widget_marquee_link_product', [
            'type'              => 'boolean',
            'sanitize_callback' => 'rest_sanitize_boolean',
            'default'           => true,
        ]);
    }

    /**
     * Sanitize tag IDs array
     */
    public function sanitize_tags($input): array {
        if (!is_array($input)) {
            return [];
        }
        return array_map('absint', $input);
    }

    /**
     * Sanitize phone number (remove all non-numeric except +)
     */
    public function sanitize_phone_number($input): string {
        // Remove everything except digits and +
        $phone = preg_replace('/[^0-9+]/', '', $input);
        // Ensure it starts with country code (add 31 for NL if just starts with 0)
        if (strpos($phone, '0') === 0) {
            $phone = '31' . substr($phone, 1);
        }
        // Remove leading + if present (wa.me doesn't need it)
        $phone = ltrim($phone, '+');
        return $phone;
    }

    /**
     * Sanitize product IDs array
     */
    public function sanitize_products($input): array {
        if (!is_array($input)) {
            return [];
        }
        return array_map('absint', $input);
    }

    /**
     * Sanitize button style
     */
    public function sanitize_button_style($input): string {
        return in_array($input, ['outline', 'filled', 'ghost'], true) ? $input : 'outline';
    }

    /**
     * Sanitize button radius
     */
    public function sanitize_button_radius($input): string {
        return in_array($input, ['sharp', 'rounded', 'pill'], true) ? $input : 'rounded';
    }

    /**
     * Sanitize icon type
     */
    public function sanitize_icon_type($input): string {
        return in_array($input, array_keys(self::ICONS), true) ? $input : 'camera';
    }

    /**
     * Sanitize max items (2-5 range)
     */
    public function sanitize_max_items($input): int {
        $value = absint($input);
        return max(2, min(5, $value));
    }

    /**
     * Sanitize button position
     */
    public function sanitize_button_position($input): string {
        return in_array($input, ['after_button', 'after_form', 'shortcode'], true) ? $input : 'after_button';
    }

    /**
     * Sanitize and encrypt API key
     *
     * @param string $input The API key input
     * @return string Encrypted API key
     */
    public function sanitize_api_key($input): string {
        $input = sanitize_text_field($input);

        if (empty($input)) {
            return '';
        }

        // Encrypt the API key before storing
        return MRV_Encryption::encrypt($input);
    }

    /**
     * Get current active tab
     */
    private function get_active_tab(): string {
        return isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'algemeen';
    }

    /**
     * Get tab URL
     */
    private function get_tab_url(string $tab): string {
        return add_query_arg('tab', $tab, admin_url('admin.php?page=mrv-settings'));
    }

    /**
     * Render the settings page
     */
    public function render_settings_page(): void {
        $active_tab = $this->get_active_tab();

        $generation_counts = MRV_Post_Types::get_status_counts();

        $tabs = [
            'algemeen'  => [
                'label' => __('General', 'shopvision'),
                'icon'  => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/>',
            ],
            'styling'   => [
                'label' => __('Styling', 'shopvision'),
                'icon'  => '<circle cx="13.5" cy="6.5" r="2.5"/><path d="M17 3a2.83 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/>',
            ],
            'producten' => [
                'label' => __('Products', 'shopvision'),
                'icon'  => '<path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/>',
            ],
            'generaties' => [
                'label' => __('Generations', 'shopvision'),
                'icon'  => '<rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/>',
                'badge' => $generation_counts['all'] > 0 ? $generation_counts['all'] : null,
            ],
            'analytics' => [
                'label' => __('Analytics', 'shopvision'),
                'icon'  => '<line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/>',
            ],
            'widgets' => [
                'label' => __('Widgets', 'shopvision'),
                'icon'  => '<rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>',
            ],
        ];
        ?>
        <div class="mrv-wrap">
            <!-- Header -->
            <div class="mrv-header">
                <div class="mrv-header-left">
                    <a href="<?php echo esc_url(admin_url('admin.php?page=mrv-settings')); ?>" class="mrv-logo">
                        <div class="mrv-logo-icon">
                            <svg viewBox="0 0 24 24" fill="currentColor">
                                <path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V5h14v14zm-7-2h2v-4h4v-2h-4V7h-2v4H8v2h4z"/>
                            </svg>
                        </div>
                        <span class="mrv-logo-text">Shopvision</span>
                    </a>
                    <nav class="mrv-nav">
                        <?php foreach ($tabs as $tab_key => $tab_data): ?>
                            <a href="<?php echo esc_url($this->get_tab_url($tab_key)); ?>"
                               class="mrv-nav-item<?php echo $active_tab === $tab_key ? ' active' : ''; ?>">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <?php echo $tab_data['icon']; ?>
                                </svg>
                                <?php echo esc_html($tab_data['label']); ?>
                                <?php if (!empty($tab_data['badge'])): ?>
                                    <span class="mrv-nav-badge"><?php echo esc_html($tab_data['badge']); ?></span>
                                <?php endif; ?>
                            </a>
                        <?php endforeach; ?>
                    </nav>
                </div>
                <div class="mrv-header-right">
                    <span class="mrv-version">v<?php echo esc_html(MRV_VERSION); ?></span>
                </div>
            </div>

            <!-- Content -->
            <div class="mrv-content">
                <?php
                // Show settings saved notice
                if (isset($_GET['settings-updated']) && $_GET['settings-updated'] === 'true') {
                    echo '<div class="mrv-notice mrv-notice-success">';
                    echo '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>';
                    echo esc_html__('Settings saved', 'shopvision');
                    echo '</div>';
                }

                switch ($active_tab) {
                    case 'styling':
                        $this->render_styling_tab();
                        break;
                    case 'producten':
                        $this->render_producten_tab();
                        break;
                    case 'generaties':
                        $this->render_generaties_tab();
                        break;
                    case 'analytics':
                        $this->render_analytics_tab();
                        break;
                    case 'widgets':
                        $this->render_widgets_tab();
                        break;
                    default:
                        $this->render_algemeen_tab();
                        break;
                }
                ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render Algemeen tab
     */
    private function render_algemeen_tab(): void {
        $api_key_encrypted = get_option('mrv_api_key', '');
        $api_key = MRV_Encryption::decrypt($api_key_encrypted);
        $preset = get_option('mrv_preset', 'interior');
        $custom_prompt = get_option('mrv_custom_prompt', '');
        $button_text = get_option('mrv_button_text', 'Visualize in your room');
        $cleanup_hours = get_option('mrv_cleanup_hours', 24);
        $rate_limit_guest = get_option('mrv_rate_limit_guest', 10);
        $rate_limit_logged_in = get_option('mrv_rate_limit_logged_in', 20);
        $order_button_enabled = get_option('mrv_order_button_enabled', false);
        $order_button_text = get_option('mrv_order_button_text', 'Order This');
        $download_button_text = get_option('mrv_download_button_text', 'Download');
        $examples_enabled = get_option('mrv_examples_enabled', false);
        $example_image_1 = get_option('mrv_example_image_1', 0);
        $example_image_2 = get_option('mrv_example_image_2', 0);
        $example_image_3 = get_option('mrv_example_image_3', 0);
        $examples_text_enabled = get_option('mrv_examples_text_enabled', true);
        $examples_title = get_option('mrv_examples_title', 'See it for yourself');
        $examples_subtitle = get_option('mrv_examples_subtitle', 'Upload a photo of your room');
        $multi_product_enabled = get_option('mrv_multi_product_enabled', false);
        $multi_product_max_items = get_option('mrv_multi_product_max_items', 5);
        $button_position = get_option('mrv_button_position', 'after_button');
        $whatsapp_enabled = get_option('mrv_whatsapp_enabled', false);
        $whatsapp_phone = get_option('mrv_whatsapp_phone', '');
        $whatsapp_button_text = get_option('mrv_whatsapp_button_text', 'Request quote via WhatsApp');
        $whatsapp_message = get_option('mrv_whatsapp_message', 'Hello! I would like a quote for the following items:');
        $api_configured = !empty($api_key);
        ?>
        <h1 class="mrv-page-title"><?php esc_html_e('General Settings', 'shopvision'); ?></h1>
        <p class="mrv-page-description"><?php esc_html_e('Configure the basic settings', 'shopvision'); ?></p>

        <form method="post" action="options.php">
            <?php settings_fields('mrv_settings_general'); ?>
            <input type="hidden" name="_wp_http_referer" value="<?php echo esc_url(add_query_arg('tab', 'algemeen', admin_url('admin.php?page=mrv-settings'))); ?>">

            <!-- API Settings -->
            <div class="mrv-card">
                <div class="mrv-card-header">
                    <h2 class="mrv-card-title">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                        </svg>
                        <?php esc_html_e('API Configuration', 'shopvision'); ?>
                    </h2>
                    <?php if ($api_configured): ?>
                        <span class="mrv-status mrv-status-success">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>
                            <?php esc_html_e('Connected', 'shopvision'); ?>
                        </span>
                    <?php else: ?>
                        <span class="mrv-status mrv-status-warning">
                            <?php esc_html_e('Not configured', 'shopvision'); ?>
                        </span>
                    <?php endif; ?>
                </div>
                <div class="mrv-card-body">
                    <div class="mrv-field">
                        <label class="mrv-field-label" for="mrv_api_key">
                            <?php esc_html_e('Google Gemini API Key', 'shopvision'); ?>
                        </label>
                        <div style="display: flex; gap: 10px; align-items: flex-start;">
                            <div style="position: relative; flex: 1;">
                                <input type="password" id="mrv_api_key" name="mrv_api_key"
                                       value="<?php echo esc_attr($api_key); ?>" autocomplete="off" style="width: 100%; padding-right: 40px;">
                                <button type="button" id="mrv-toggle-api-key" style="position: absolute; right: 8px; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; padding: 4px; color: #666;" title="<?php esc_attr_e('Show/hide API key', 'shopvision'); ?>">
                                    <svg id="mrv-eye-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 20px; height: 20px;">
                                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
                                    </svg>
                                    <svg id="mrv-eye-off-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 20px; height: 20px; display: none;">
                                        <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/>
                                    </svg>
                                </button>
                            </div>
                            <button type="button" id="mrv-test-api-key" class="button button-secondary" style="white-space: nowrap;">
                                <?php esc_html_e('Test API Key', 'shopvision'); ?>
                            </button>
                        </div>
                        <div id="mrv-api-test-result" style="margin-top: 12px; display: none; padding: 12px 14px; border-radius: 8px; font-size: 13px;"></div>
                        <p class="mrv-field-description">
                            <?php esc_html_e('Enter your Google Gemini API key', 'shopvision'); ?>
                            <a href="https://aistudio.google.com/app/apikey" target="_blank"><?php esc_html_e('Create API key', 'shopvision'); ?></a>
                        </p>
                    </div>
                </div>
            </div>

            <!-- Preset Selection -->
            <div class="mrv-card">
                <div class="mrv-card-header">
                    <h2 class="mrv-card-title">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polygon points="12 2 2 7 12 12 22 7 12 2"/><polyline points="2 17 12 22 22 17"/><polyline points="2 12 12 17 22 12"/>
                        </svg>
                        <?php esc_html_e('Industry Preset', 'shopvision'); ?>
                    </h2>
                </div>
                <div class="mrv-card-body">
                    <div class="mrv-field">
                        <p class="mrv-field-description" style="margin-bottom: 16px;">
                            <?php esc_html_e('Choose a preset that matches your product type', 'shopvision'); ?>
                        </p>
                        <div class="mrv-preset-options">
                            <label class="mrv-preset-option <?php echo $preset === 'interior' ? 'mrv-preset-active' : ''; ?>">
                                <input type="radio" name="mrv_preset" value="interior" <?php checked($preset, 'interior'); ?>>
                                <div class="mrv-preset-icon">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                        <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>
                                    </svg>
                                </div>
                                <div class="mrv-preset-content">
                                    <span class="mrv-preset-title"><?php esc_html_e('Interior', 'shopvision'); ?></span>
                                    <span class="mrv-preset-desc"><?php esc_html_e('Furniture, decoration, lighting', 'shopvision'); ?></span>
                                </div>
                            </label>
                            <label class="mrv-preset-option <?php echo $preset === 'fashion' ? 'mrv-preset-active' : ''; ?>">
                                <input type="radio" name="mrv_preset" value="fashion" <?php checked($preset, 'fashion'); ?>>
                                <div class="mrv-preset-icon">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                        <path d="M20.38 3.46L16 2a4 4 0 0 1-8 0L3.62 3.46a2 2 0 0 0-1.34 2.23l.58 3.47a1 1 0 0 0 .99.84H6v10c0 1.1.9 2 2 2h8a2 2 0 0 0 2-2V10h2.15a1 1 0 0 0 .99-.84l.58-3.47a2 2 0 0 0-1.34-2.23z"/>
                                    </svg>
                                </div>
                                <div class="mrv-preset-content">
                                    <span class="mrv-preset-title"><?php esc_html_e('Fashion', 'shopvision'); ?></span>
                                    <span class="mrv-preset-desc"><?php esc_html_e('Clothing, shoes, accessories', 'shopvision'); ?></span>
                                </div>
                            </label>
                            <label class="mrv-preset-option <?php echo $preset === 'custom' ? 'mrv-preset-active' : ''; ?>">
                                <input type="radio" name="mrv_preset" value="custom" <?php checked($preset, 'custom'); ?>>
                                <div class="mrv-preset-icon">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                        <path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/>
                                    </svg>
                                </div>
                                <div class="mrv-preset-content">
                                    <span class="mrv-preset-title"><?php esc_html_e('Custom', 'shopvision'); ?></span>
                                    <span class="mrv-preset-desc"><?php esc_html_e('Write your own prompt', 'shopvision'); ?></span>
                                </div>
                            </label>
                        </div>
                    </div>
                    <!-- Custom Prompt Field (shown when custom is selected) -->
                    <div class="mrv-field mrv-custom-prompt-field" style="<?php echo $preset !== 'custom' ? 'display:none;' : ''; ?>margin-top:20px;">
                        <label class="mrv-field-label" for="mrv_custom_prompt">
                            <?php esc_html_e('Custom Prompt', 'shopvision'); ?>
                        </label>
                        <textarea id="mrv_custom_prompt" name="mrv_custom_prompt" rows="8" style="width:100%;font-family:monospace;font-size:13px;"><?php echo esc_textarea($custom_prompt); ?></textarea>
                        <p class="mrv-field-description">
                            <?php esc_html_e('Write your own prompt for the AI', 'shopvision'); ?>
                        </p>
                    </div>
                </div>
            </div>

            <!-- Display Settings -->
            <div class="mrv-card">
                <div class="mrv-card-header">
                    <h2 class="mrv-card-title">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="9" y1="21" x2="9" y2="9"/>
                        </svg>
                        <?php esc_html_e('Display', 'shopvision'); ?>
                    </h2>
                </div>
                <div class="mrv-card-body">
                    <div class="mrv-field">
                        <label class="mrv-field-label" for="mrv_button_text">
                            <?php esc_html_e('Button Text', 'shopvision'); ?>
                        </label>
                        <input type="text" id="mrv_button_text" name="mrv_button_text"
                               value="<?php echo esc_attr($button_text); ?>">
                        <p class="mrv-field-description">
                            <?php esc_html_e('The text shown on the visualizer button', 'shopvision'); ?>
                        </p>
                    </div>

                    <div class="mrv-field" style="margin-top: 20px;">
                        <label class="mrv-field-label" for="mrv_button_position">
                            <?php esc_html_e('Button Position', 'shopvision'); ?>
                        </label>
                        <select id="mrv_button_position" name="mrv_button_position" style="max-width: 400px;">
                            <option value="after_button" <?php selected($button_position, 'after_button'); ?>>
                                <?php esc_html_e('Automatic (after add to cart button)', 'shopvision'); ?>
                            </option>
                            <option value="after_form" <?php selected($button_position, 'after_form'); ?>>
                                <?php esc_html_e('Automatic (below product form)', 'shopvision'); ?>
                            </option>
                            <option value="shortcode" <?php selected($button_position, 'shortcode'); ?>>
                                <?php esc_html_e('Manual (shortcode)', 'shopvision'); ?>
                            </option>
                        </select>
                        <p class="mrv-field-description">
                            <?php esc_html_e('Choose where the button is placed', 'shopvision'); ?>
                        </p>
                        <div class="mrv-shortcode-info" style="margin-top: 12px; padding: 12px 14px; background: #f0f9ff; border: 1px solid #bae6fd; border-radius: 8px;<?php echo $button_position !== 'shortcode' ? ' display: none;' : ''; ?>">
                            <p style="margin: 0 0 8px; font-size: 13px; font-weight: 500; color: #0c4a6e;">
                                <?php esc_html_e('Shortcode', 'shopvision'); ?>
                            </p>
                            <code style="display: block; padding: 8px 12px; background: #fff; border-radius: 4px; font-size: 13px; color: #0369a1; user-select: all;">[shopvision]</code>
                            <p style="margin: 8px 0 0; font-size: 12px; color: #0c4a6e;">
                                <?php esc_html_e('Place this shortcode', 'shopvision'); ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Add to Cart CTA -->
            <div class="mrv-card">
                <div class="mrv-card-header">
                    <h2 class="mrv-card-title">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>
                        </svg>
                        <?php esc_html_e('Add to Cart CTA', 'shopvision'); ?>
                    </h2>
                </div>
                <div class="mrv-card-body">
                    <div class="mrv-field">
                        <div class="mrv-icon-toggle-row">
                            <label class="mrv-toggle">
                                <input type="hidden" name="mrv_order_button_enabled" value="0">
                                <input type="checkbox" id="mrv_order_button_enabled" name="mrv_order_button_enabled" value="1" <?php checked($order_button_enabled, true); ?>>
                                <span class="mrv-toggle-slider"></span>
                            </label>
                            <span class="mrv-toggle-text"><?php esc_html_e('Show order button after visualization', 'shopvision'); ?></span>
                        </div>
                        <p class="mrv-field-description">
                            <?php esc_html_e('Shows an order button after a successful visualization', 'shopvision'); ?>
                        </p>
                    </div>

                    <div class="mrv-cta-text-fields"<?php echo !$order_button_enabled ? ' style="display:none;"' : ''; ?>>
                        <div class="mrv-field">
                            <label class="mrv-field-label" for="mrv_order_button_text">
                                <?php esc_html_e('Order Button Text', 'shopvision'); ?>
                            </label>
                            <input type="text" id="mrv_order_button_text" name="mrv_order_button_text"
                                   value="<?php echo esc_attr($order_button_text); ?>"
                                   placeholder="<?php echo esc_attr__('Order This', 'shopvision'); ?>">
                        </div>

                        <div class="mrv-field">
                            <label class="mrv-field-label" for="mrv_download_button_text">
                                <?php esc_html_e('Download Button Text', 'shopvision'); ?>
                            </label>
                            <input type="text" id="mrv_download_button_text" name="mrv_download_button_text"
                                   value="<?php echo esc_attr($download_button_text); ?>"
                                   placeholder="<?php echo esc_attr__('Download', 'shopvision'); ?>">
                            <p class="mrv-field-description">
                                <?php esc_html_e('The download button becomes secondary', 'shopvision'); ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Example Gallery Settings -->
            <div class="mrv-card">
                <div class="mrv-card-header">
                    <h2 class="mrv-card-title">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/>
                        </svg>
                        <?php esc_html_e('Example Gallery', 'shopvision'); ?>
                    </h2>
                </div>
                <div class="mrv-card-body">
                    <div class="mrv-field">
                        <div class="mrv-icon-toggle-row">
                            <label class="mrv-toggle">
                                <input type="hidden" name="mrv_examples_enabled" value="0">
                                <input type="checkbox" id="mrv_examples_enabled" name="mrv_examples_enabled" value="1" <?php checked($examples_enabled, true); ?>>
                                <span class="mrv-toggle-slider"></span>
                            </label>
                            <span class="mrv-toggle-text"><?php esc_html_e('Show examples in modal', 'shopvision'); ?></span>
                        </div>
                        <p class="mrv-field-description">
                            <?php esc_html_e('Shows inspiring example images', 'shopvision'); ?>
                        </p>
                    </div>

                    <div class="mrv-examples-fields"<?php echo !$examples_enabled ? ' style="display:none;"' : ''; ?>>
                        <div class="mrv-examples-grid">
                            <?php for ($i = 1; $i <= 3; $i++):
                                $image_id = ${'example_image_' . $i};
                                $image_url = $image_id ? wp_get_attachment_image_url($image_id, 'medium') : '';
                            ?>
                            <div class="mrv-example-uploader" data-index="<?php echo $i; ?>">
                                <input type="hidden" name="mrv_example_image_<?php echo $i; ?>" id="mrv_example_image_<?php echo $i; ?>" value="<?php echo esc_attr($image_id); ?>">
                                <div class="mrv-example-preview<?php echo $image_url ? ' has-image' : ''; ?>" style="<?php echo $image_url ? 'background-image: url(' . esc_url($image_url) . ')' : ''; ?>">
                                    <div class="mrv-example-placeholder">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                            <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
                                        </svg>
                                    </div>
                                    <button type="button" class="mrv-example-remove" title="<?php esc_attr_e('Remove', 'shopvision'); ?>">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                                        </svg>
                                    </button>
                                </div>
                                <span class="mrv-example-label"><?php printf(esc_html__('Image %d', 'shopvision'), $i); ?></span>
                            </div>
                            <?php endfor; ?>
                        </div>
                        <p class="mrv-field-description" style="margin-top: 12px; margin-bottom: 24px;">
                            <?php esc_html_e('Upload 3 example images', 'shopvision'); ?>
                        </p>

                        <!-- Title & Subtitle -->
                        <div class="mrv-field" style="margin-bottom: 16px;">
                            <div class="mrv-icon-toggle-row">
                                <label class="mrv-toggle">
                                    <input type="hidden" name="mrv_examples_text_enabled" value="0">
                                    <input type="checkbox" id="mrv_examples_text_enabled" name="mrv_examples_text_enabled" value="1" <?php checked($examples_text_enabled, true); ?>>
                                    <span class="mrv-toggle-slider"></span>
                                </label>
                                <span class="mrv-toggle-text"><?php esc_html_e('Show title and subtitle', 'shopvision'); ?></span>
                            </div>
                        </div>

                        <div class="mrv-examples-text-fields"<?php echo !$examples_text_enabled ? ' style="display:none;"' : ''; ?>>
                            <div class="mrv-field" style="margin-bottom: 16px;">
                                <label class="mrv-field-label" for="mrv_examples_title">
                                    <?php esc_html_e('Title', 'shopvision'); ?>
                                </label>
                                <input type="text" id="mrv_examples_title" name="mrv_examples_title"
                                       value="<?php echo esc_attr($examples_title); ?>" style="max-width: 300px;">
                            </div>
                            <div class="mrv-field">
                                <label class="mrv-field-label" for="mrv_examples_subtitle">
                                    <?php esc_html_e('Subtitle', 'shopvision'); ?>
                                </label>
                                <input type="text" id="mrv_examples_subtitle" name="mrv_examples_subtitle"
                                       value="<?php echo esc_attr($examples_subtitle); ?>" style="max-width: 300px;">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Multi-Product Settings -->
            <div class="mrv-card">
                <div class="mrv-card-header">
                    <h2 class="mrv-card-title">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>
                        </svg>
                        <?php esc_html_e('Multi-product Visualization', 'shopvision'); ?>
                    </h2>
                </div>
                <div class="mrv-card-body">
                    <div class="mrv-field">
                        <div class="mrv-icon-toggle-row">
                            <label class="mrv-toggle">
                                <input type="hidden" name="mrv_multi_product_enabled" value="0">
                                <input type="checkbox" id="mrv_multi_product_enabled" name="mrv_multi_product_enabled" value="1" <?php checked($multi_product_enabled, true); ?>>
                                <span class="mrv-toggle-slider"></span>
                            </label>
                            <span class="mrv-toggle-text"><?php esc_html_e('Enable multi-product mode', 'shopvision'); ?></span>
                        </div>
                        <p class="mrv-field-description">
                            <?php esc_html_e('When enabled, customers can collect multiple products', 'shopvision'); ?>
                        </p>
                    </div>

                    <div class="mrv-multi-product-fields"<?php echo !$multi_product_enabled ? ' style="display:none;"' : ''; ?>>
                        <div class="mrv-field">
                            <label class="mrv-field-label" for="mrv_multi_product_max_items">
                                <?php esc_html_e('Maximum number of products', 'shopvision'); ?>
                            </label>
                            <div class="mrv-range-field">
                                <input type="range" id="mrv_multi_product_max_items" name="mrv_multi_product_max_items"
                                       value="<?php echo esc_attr($multi_product_max_items); ?>"
                                       min="2" max="5" step="1">
                                <span class="mrv-range-value" id="mrv-max-items-value"><?php echo esc_html($multi_product_max_items); ?></span>
                            </div>
                            <p class="mrv-field-description mrv-field-info">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:14px;height:14px;vertical-align:-2px;margin-right:4px;">
                                    <circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/>
                                </svg>
                                <?php esc_html_e('The AI automatically determines logical quantities', 'shopvision'); ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- WhatsApp Button Settings -->
            <div class="mrv-card">
                <div class="mrv-card-header">
                    <h2 class="mrv-card-title">
                        <svg viewBox="0 0 24 24" fill="currentColor" style="color: #25D366;">
                            <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
                        </svg>
                        <?php esc_html_e('WhatsApp Quote Request', 'shopvision'); ?>
                    </h2>
                </div>
                <div class="mrv-card-body">
                    <div class="mrv-field">
                        <div class="mrv-icon-toggle-row">
                            <label class="mrv-toggle">
                                <input type="hidden" name="mrv_whatsapp_enabled" value="0">
                                <input type="checkbox" id="mrv_whatsapp_enabled" name="mrv_whatsapp_enabled" value="1" <?php checked($whatsapp_enabled, true); ?>>
                                <span class="mrv-toggle-slider"></span>
                            </label>
                            <span class="mrv-toggle-text"><?php esc_html_e('Show WhatsApp button', 'shopvision'); ?></span>
                        </div>
                        <p class="mrv-field-description">
                            <?php esc_html_e('Show a WhatsApp button', 'shopvision'); ?>
                        </p>
                    </div>

                    <div class="mrv-whatsapp-fields"<?php echo !$whatsapp_enabled ? ' style="display:none;"' : ''; ?>>
                        <div class="mrv-field">
                            <label class="mrv-field-label" for="mrv_whatsapp_phone">
                                <?php esc_html_e('WhatsApp Phone Number', 'shopvision'); ?>
                            </label>
                            <input type="text" id="mrv_whatsapp_phone" name="mrv_whatsapp_phone"
                                   value="<?php echo esc_attr($whatsapp_phone); ?>"
                                   placeholder="+31612345678"
                                   class="regular-text">
                            <p class="mrv-field-description">
                                <?php esc_html_e('Including country code', 'shopvision'); ?>
                            </p>
                        </div>

                        <div class="mrv-field">
                            <label class="mrv-field-label" for="mrv_whatsapp_button_text">
                                <?php esc_html_e('Button Text', 'shopvision'); ?>
                            </label>
                            <input type="text" id="mrv_whatsapp_button_text" name="mrv_whatsapp_button_text"
                                   value="<?php echo esc_attr($whatsapp_button_text); ?>"
                                   class="regular-text">
                        </div>

                        <div class="mrv-field">
                            <label class="mrv-field-label" for="mrv_whatsapp_message">
                                <?php esc_html_e('Message Template', 'shopvision'); ?>
                            </label>
                            <textarea id="mrv_whatsapp_message" name="mrv_whatsapp_message"
                                      rows="3" class="large-text"><?php echo esc_textarea($whatsapp_message); ?></textarea>
                            <p class="mrv-field-description">
                                <?php esc_html_e('This message is automatically filled with product names', 'shopvision'); ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Cleanup Settings -->
            <div class="mrv-card">
                <div class="mrv-card-header">
                    <h2 class="mrv-card-title">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                        </svg>
                        <?php esc_html_e('Cleanup', 'shopvision'); ?>
                    </h2>
                </div>
                <div class="mrv-card-body">
                    <div class="mrv-field">
                        <label class="mrv-field-label" for="mrv_cleanup_hours">
                            <?php esc_html_e('Automatically delete after', 'shopvision'); ?>
                        </label>
                        <div class="mrv-field-inline">
                            <input type="number" id="mrv_cleanup_hours" name="mrv_cleanup_hours"
                                   value="<?php echo esc_attr($cleanup_hours); ?>"
                                   min="0" max="168" step="1">
                            <span class="mrv-field-suffix"><?php esc_html_e('hours', 'shopvision'); ?></span>
                        </div>
                        <p class="mrv-field-description">
                            <?php esc_html_e('Generated images are automatically deleted after this period. Set to 0 to never delete automatically.', 'shopvision'); ?>
                        </p>
                    </div>
                </div>
            </div>

            <!-- Rate Limiting -->
            <div class="mrv-card">
                <div class="mrv-card-header">
                    <h2 class="mrv-card-title">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                        </svg>
                        <?php esc_html_e('Rate Limiting', 'shopvision'); ?>
                    </h2>
                </div>
                <div class="mrv-card-body">
                    <p class="mrv-section-description">
                        <?php esc_html_e('Protect your API usage by limiting visualizations per user per hour.', 'shopvision'); ?>
                    </p>

                    <div class="mrv-field">
                        <label class="mrv-field-label" for="mrv_rate_limit_guest">
                            <?php esc_html_e('Guest limit', 'shopvision'); ?>
                        </label>
                        <div class="mrv-field-inline">
                            <input type="number" id="mrv_rate_limit_guest" name="mrv_rate_limit_guest"
                                   value="<?php echo esc_attr($rate_limit_guest); ?>"
                                   min="0" max="100" step="1">
                            <span class="mrv-field-suffix"><?php esc_html_e('per hour', 'shopvision'); ?></span>
                        </div>
                        <p class="mrv-field-description">
                            <?php esc_html_e('Maximum visualizations per hour for guests (not logged in). Set to 0 for unlimited.', 'shopvision'); ?>
                        </p>
                    </div>

                    <div class="mrv-field">
                        <label class="mrv-field-label" for="mrv_rate_limit_logged_in">
                            <?php esc_html_e('Logged-in user limit', 'shopvision'); ?>
                        </label>
                        <div class="mrv-field-inline">
                            <input type="number" id="mrv_rate_limit_logged_in" name="mrv_rate_limit_logged_in"
                                   value="<?php echo esc_attr($rate_limit_logged_in); ?>"
                                   min="0" max="100" step="1">
                            <span class="mrv-field-suffix"><?php esc_html_e('per hour', 'shopvision'); ?></span>
                        </div>
                        <p class="mrv-field-description">
                            <?php esc_html_e('Maximum visualizations per hour for logged-in users. Set to 0 for unlimited.', 'shopvision'); ?>
                        </p>
                    </div>
                </div>
            </div>

            <div class="mrv-submit-wrap">
                <button type="submit" class="mrv-submit-btn">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/>
                    </svg>
                    <?php esc_html_e('Save', 'shopvision'); ?>
                </button>
            </div>
        </form>
        <?php
    }

    /**
     * Render Styling tab
     */
    private function render_styling_tab(): void {
        $accent_color = get_option('mrv_accent_color', '#2563eb');
        $button_text_color = get_option('mrv_button_text_color', '');
        $hover_bg_color = get_option('mrv_hover_bg_color', '');
        $hover_text_color = get_option('mrv_hover_text_color', '');
        $button_style = get_option('mrv_button_style', 'outline');
        $button_radius = get_option('mrv_button_radius', 'rounded');
        $icon_enabled = get_option('mrv_icon_enabled', true);
        $icon_type = get_option('mrv_icon_type', 'camera');
        $button_text = get_option('mrv_button_text', 'Visualize in your room');

        $styles = [
            'outline' => [
                'label' => __('Outline', 'shopvision'),
                'desc'  => __('Transparent with border', 'shopvision'),
            ],
            'filled' => [
                'label' => __('Filled', 'shopvision'),
                'desc'  => __('Filled background', 'shopvision'),
            ],
            'ghost' => [
                'label' => __('Ghost', 'shopvision'),
                'desc'  => __('Text only', 'shopvision'),
            ],
        ];

        $radii = [
            'sharp' => [
                'label' => __('Sharp', 'shopvision'),
            ],
            'rounded' => [
                'label' => __('Rounded', 'shopvision'),
            ],
            'pill' => [
                'label' => __('Pill', 'shopvision'),
            ],
        ];
        ?>
        <h1 class="mrv-page-title"><?php esc_html_e('Styling', 'shopvision'); ?></h1>
        <p class="mrv-page-description"><?php esc_html_e('Customize the appearance of the visualizer button', 'shopvision'); ?></p>

        <form method="post" action="options.php">
            <?php settings_fields('mrv_settings_styling'); ?>
            <input type="hidden" name="_wp_http_referer" value="<?php echo esc_url(add_query_arg('tab', 'styling', admin_url('admin.php?page=mrv-settings'))); ?>">

            <div class="mrv-styling-layout">
                <!-- Left Column: Controls -->
                <div class="mrv-styling-controls">

                    <!-- Colors -->
                    <div class="mrv-card">
                        <div class="mrv-card-header">
                            <h2 class="mrv-card-title">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="3"/>
                                </svg>
                                <?php esc_html_e('Colors', 'shopvision'); ?>
                            </h2>
                        </div>
                        <div class="mrv-card-body">
                            <!-- Normal State -->
                            <div class="mrv-color-section">
                                <div class="mrv-color-section-label"><?php esc_html_e('Normal', 'shopvision'); ?></div>
                                <div class="mrv-color-grid">
                                    <div class="mrv-color-field">
                                        <label class="mrv-field-label"><?php esc_html_e('Primary Color', 'shopvision'); ?></label>
                                        <input type="text"
                                               id="mrv_accent_color"
                                               name="mrv_accent_color"
                                               value="<?php echo esc_attr($accent_color); ?>"
                                               class="mrv-color-picker"
                                               data-default-color="#2563eb">
                                        <p class="mrv-field-description">
                                            <?php esc_html_e('Border color', 'shopvision'); ?>
                                        </p>
                                    </div>
                                    <div class="mrv-color-field">
                                        <label class="mrv-field-label"><?php esc_html_e('Text color', 'shopvision'); ?></label>
                                        <input type="text"
                                               id="mrv_button_text_color"
                                               name="mrv_button_text_color"
                                               value="<?php echo esc_attr($button_text_color); ?>"
                                               class="mrv-color-picker"
                                               data-default-color="<?php echo esc_attr($accent_color); ?>">
                                        <p class="mrv-field-description">
                                            <?php esc_html_e('Empty = accent color', 'shopvision'); ?>
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <!-- Hover State -->
                            <div class="mrv-color-section" style="margin-top: 24px;">
                                <div class="mrv-color-section-label"><?php esc_html_e('Hover', 'shopvision'); ?></div>
                                <div class="mrv-color-grid">
                                    <div class="mrv-color-field">
                                        <label class="mrv-field-label"><?php esc_html_e('Background Color', 'shopvision'); ?></label>
                                        <input type="text"
                                               id="mrv_hover_bg_color"
                                               name="mrv_hover_bg_color"
                                               value="<?php echo esc_attr($hover_bg_color); ?>"
                                               class="mrv-color-picker"
                                               data-default-color="<?php echo esc_attr($accent_color); ?>">
                                        <p class="mrv-field-description">
                                            <?php esc_html_e('Empty = accent color', 'shopvision'); ?>
                                        </p>
                                    </div>
                                    <div class="mrv-color-field">
                                        <label class="mrv-field-label"><?php esc_html_e('Text color', 'shopvision'); ?></label>
                                        <input type="text"
                                               id="mrv_hover_text_color"
                                               name="mrv_hover_text_color"
                                               value="<?php echo esc_attr($hover_text_color); ?>"
                                               class="mrv-color-picker"
                                               data-default-color="#ffffff">
                                        <p class="mrv-field-description">
                                            <?php esc_html_e('Empty = white', 'shopvision'); ?>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Button Style -->
                    <div class="mrv-card">
                        <div class="mrv-card-header">
                            <h2 class="mrv-card-title">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
                                </svg>
                                <?php esc_html_e('Button style', 'shopvision'); ?>
                            </h2>
                        </div>
                        <div class="mrv-card-body">
                            <div class="mrv-button-style-grid">
                                <?php foreach ($styles as $value => $data): ?>
                                <label class="mrv-button-style-card<?php echo $button_style === $value ? ' is-selected' : ''; ?>">
                                    <input type="radio" name="mrv_button_style" value="<?php echo esc_attr($value); ?>"
                                           <?php checked($button_style, $value); ?>>
                                    <span class="mrv-style-demo mrv-style-demo--<?php echo esc_attr($value); ?>">
                                        <?php echo esc_html($data['label']); ?>
                                    </span>
                                    <span class="mrv-style-name"><?php echo esc_html($data['label']); ?></span>
                                    <span class="mrv-style-desc"><?php echo esc_html($data['desc']); ?></span>
                                </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Button Radius -->
                    <div class="mrv-card">
                        <div class="mrv-card-header">
                            <h2 class="mrv-card-title">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
                                </svg>
                                <?php esc_html_e('Button radius', 'shopvision'); ?>
                            </h2>
                        </div>
                        <div class="mrv-card-body">
                            <div class="mrv-radius-options">
                                <?php foreach ($radii as $value => $data): ?>
                                <label class="mrv-radius-card<?php echo $button_radius === $value ? ' is-selected' : ''; ?>">
                                    <input type="radio" name="mrv_button_radius" value="<?php echo esc_attr($value); ?>"
                                           <?php checked($button_radius, $value); ?>>
                                    <span class="mrv-radius-visual mrv-radius-visual--<?php echo esc_attr($value); ?>"></span>
                                    <span class="mrv-radius-name"><?php echo esc_html($data['label']); ?></span>
                                </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Icon Settings -->
                    <div class="mrv-card">
                        <div class="mrv-card-header">
                            <h2 class="mrv-card-title">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/>
                                </svg>
                                <?php esc_html_e('Icon', 'shopvision'); ?>
                            </h2>
                        </div>
                        <div class="mrv-card-body">
                            <div class="mrv-icon-toggle-row">
                                <label class="mrv-toggle">
                                    <input type="hidden" name="mrv_icon_enabled" value="0">
                                    <input type="checkbox" id="mrv_icon_enabled" name="mrv_icon_enabled" value="1" <?php checked($icon_enabled, true); ?>>
                                    <span class="mrv-toggle-slider"></span>
                                </label>
                                <span class="mrv-toggle-text"><?php esc_html_e('Show icon on button', 'shopvision'); ?></span>
                            </div>

                            <div class="mrv-icon-grid-wrap"<?php echo !$icon_enabled ? ' style="display:none;"' : ''; ?>>
                                <div class="mrv-icon-grid">
                                    <?php foreach (self::ICONS as $icon_key => $icon_path): ?>
                                    <label class="mrv-icon-box<?php echo $icon_type === $icon_key ? ' is-selected' : ''; ?>">
                                        <input type="radio" name="mrv_icon_type" value="<?php echo esc_attr($icon_key); ?>"
                                               <?php checked($icon_type, $icon_key); ?>>
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <?php echo $icon_path; ?>
                                        </svg>
                                    </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <?php if (get_option('mrv_whatsapp_enabled', false)): ?>
                    <?php
                    $whatsapp_bg_color = get_option('mrv_whatsapp_bg_color', '#25D366');
                    $whatsapp_text_color = get_option('mrv_whatsapp_text_color', '#ffffff');
                    $whatsapp_hover_bg_color = get_option('mrv_whatsapp_hover_bg_color', '#128C7E');
                    $whatsapp_hover_text_color = get_option('mrv_whatsapp_hover_text_color', '#ffffff');
                    ?>
                    <!-- WhatsApp Button Styling -->
                    <div class="mrv-card">
                        <div class="mrv-card-header">
                            <h2 class="mrv-card-title">
                                <svg viewBox="0 0 24 24" fill="currentColor" style="color: #25D366;">
                                    <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
                                </svg>
                                <?php esc_html_e('WhatsApp button', 'shopvision'); ?>
                            </h2>
                        </div>
                        <div class="mrv-card-body">
                            <!-- Normal State -->
                            <div class="mrv-color-section">
                                <div class="mrv-color-section-label"><?php esc_html_e('Normal', 'shopvision'); ?></div>
                                <div class="mrv-color-grid">
                                    <div class="mrv-color-field">
                                        <label class="mrv-field-label"><?php esc_html_e('Background Color', 'shopvision'); ?></label>
                                        <input type="text"
                                               id="mrv_whatsapp_bg_color"
                                               name="mrv_whatsapp_bg_color"
                                               value="<?php echo esc_attr($whatsapp_bg_color); ?>"
                                               class="mrv-color-picker"
                                               data-default-color="#25D366">
                                    </div>
                                    <div class="mrv-color-field">
                                        <label class="mrv-field-label"><?php esc_html_e('Text color', 'shopvision'); ?></label>
                                        <input type="text"
                                               id="mrv_whatsapp_text_color"
                                               name="mrv_whatsapp_text_color"
                                               value="<?php echo esc_attr($whatsapp_text_color); ?>"
                                               class="mrv-color-picker"
                                               data-default-color="#ffffff">
                                    </div>
                                </div>
                            </div>

                            <!-- Hover State -->
                            <div class="mrv-color-section" style="margin-top: 24px;">
                                <div class="mrv-color-section-label"><?php esc_html_e('Hover', 'shopvision'); ?></div>
                                <div class="mrv-color-grid">
                                    <div class="mrv-color-field">
                                        <label class="mrv-field-label"><?php esc_html_e('Background Color', 'shopvision'); ?></label>
                                        <input type="text"
                                               id="mrv_whatsapp_hover_bg_color"
                                               name="mrv_whatsapp_hover_bg_color"
                                               value="<?php echo esc_attr($whatsapp_hover_bg_color); ?>"
                                               class="mrv-color-picker"
                                               data-default-color="#128C7E">
                                    </div>
                                    <div class="mrv-color-field">
                                        <label class="mrv-field-label"><?php esc_html_e('Text color', 'shopvision'); ?></label>
                                        <input type="text"
                                               id="mrv_whatsapp_hover_text_color"
                                               name="mrv_whatsapp_hover_text_color"
                                               value="<?php echo esc_attr($whatsapp_hover_text_color); ?>"
                                               class="mrv-color-picker"
                                               data-default-color="#ffffff">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="mrv-submit-wrap">
                        <button type="submit" class="mrv-submit-btn">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/>
                            </svg>
                            <?php esc_html_e('Save', 'shopvision'); ?>
                        </button>
                    </div>
                </div>

                <!-- Right Column: Live Preview -->
                <div class="mrv-styling-preview">
                    <div class="mrv-preview-sticky">
                        <div class="mrv-preview-card">
                            <div class="mrv-preview-label"><?php esc_html_e('Live preview', 'shopvision'); ?></div>
                            <div class="mrv-preview-mockup">
                                <div class="mrv-preview-product-image">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                        <rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/>
                                    </svg>
                                </div>
                                <div class="mrv-preview-product-info">
                                    <div class="mrv-preview-product-name">Voorbeeld Product</div>
                                    <div class="mrv-preview-product-price">&euro; 299,00</div>
                                </div>
                                <button type="button" class="mrv-preview-add-to-cart"><?php esc_html_e('Add to cart', 'shopvision'); ?></button>
                                <button type="button" class="mrv-live-preview-btn" id="mrv-live-preview-btn">
                                    <span class="mrv-preview-icon" id="mrv-preview-icon"<?php echo !$icon_enabled ? ' style="display:none;"' : ''; ?>>
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <?php echo self::ICONS[$icon_type]; ?>
                                        </svg>
                                    </span>
                                    <span class="mrv-preview-text"><?php echo esc_html($button_text); ?></span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
        <?php
    }

    /**
     * Render Producten tab
     */
    private function render_producten_tab(): void {
        $all_products_enabled = get_option('mrv_all_products_enabled', false);
        $enabled_products = get_option('mrv_enabled_products', []);
        $enabled_tags = get_option('mrv_enabled_tags', []);

        // Only load product details for individual selection (and not too many)
        $selected_products = [];
        if (!$all_products_enabled && !empty($enabled_products) && count($enabled_products) <= 100) {
            $selected_products = wc_get_products([
                'include' => $enabled_products,
                'limit'   => -1,
            ]);
        }

        // Get all product tags
        $all_tags = get_terms([
            'taxonomy'   => 'product_tag',
            'hide_empty' => false,
            'orderby'    => 'name',
        ]);
        ?>
        <h1 class="mrv-page-title"><?php esc_html_e('Products', 'shopvision'); ?></h1>
        <p class="mrv-page-description"><?php esc_html_e('Select which products show the visualizer button', 'shopvision'); ?></p>

        <form method="post" action="options.php">
            <?php settings_fields('mrv_settings_products'); ?>
            <input type="hidden" name="_wp_http_referer" value="<?php echo esc_url(add_query_arg('tab', 'producten', admin_url('admin.php?page=mrv-settings'))); ?>">

            <!-- All products toggle -->
            <div class="mrv-card">
                <div class="mrv-card-header">
                    <h2 class="mrv-card-title">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>
                        </svg>
                        <?php esc_html_e('All Products', 'shopvision'); ?>
                    </h2>
                </div>
                <div class="mrv-card-body">
                    <div class="mrv-field">
                        <div class="mrv-icon-toggle-row">
                            <label class="mrv-toggle">
                                <input type="hidden" name="mrv_all_products_enabled" value="0">
                                <input type="checkbox" id="mrv_all_products_enabled" name="mrv_all_products_enabled" value="1" <?php checked($all_products_enabled); ?>>
                                <span class="mrv-toggle-slider"></span>
                            </label>
                            <span class="mrv-toggle-text"><?php esc_html_e('Enable for all products', 'shopvision'); ?></span>
                        </div>
                        <p class="mrv-field-description">
                            <?php esc_html_e('The visualizer button is shown on all', 'shopvision'); ?>
                        </p>
                    </div>
                </div>
            </div>

            <!-- Tag-based activation -->
            <div class="mrv-card">
                <div class="mrv-card-header">
                    <h2 class="mrv-card-title">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/>
                        </svg>
                        <?php esc_html_e('Product Tags', 'shopvision'); ?>
                    </h2>
                </div>
                <div class="mrv-card-body">
                    <p class="mrv-field-description" style="margin-bottom: 16px;">
                        <?php esc_html_e('Select tags', 'shopvision'); ?>
                    </p>
                    <?php if (!empty($all_tags) && !is_wp_error($all_tags)): ?>
                        <div class="mrv-tag-grid">
                            <?php foreach ($all_tags as $tag): ?>
                                <label class="mrv-tag-checkbox">
                                    <input type="checkbox"
                                           name="mrv_enabled_tags[]"
                                           value="<?php echo esc_attr($tag->term_id); ?>"
                                           <?php checked(in_array($tag->term_id, $enabled_tags)); ?>>
                                    <span class="mrv-tag-label">
                                        <?php echo esc_html($tag->name); ?>
                                        <span class="mrv-tag-count"><?php echo esc_html($tag->count); ?></span>
                                    </span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="mrv-empty-message">
                            <?php esc_html_e('No product tags found', 'shopvision'); ?>
                        </p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Individual product selection -->
            <div class="mrv-card">
                <div class="mrv-card-header">
                    <h2 class="mrv-card-title">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/>
                        </svg>
                        <?php esc_html_e('Individual Products', 'shopvision'); ?>
                    </h2>
                    <span class="mrv-product-badge" id="mrv-selected-count"><?php echo count($selected_products); ?></span>
                </div>
                <div class="mrv-card-body">
                    <div class="mrv-product-picker">
                        <div class="mrv-product-picker-search">
                            <input type="text"
                                   id="mrv-product-search"
                                   placeholder="<?php echo esc_attr__('Search products by name or SKU...', 'shopvision'); ?>"
                                   autocomplete="off">
                            <div class="mrv-search-results" id="mrv-search-results"></div>
                        </div>

                        <div class="mrv-selected-products" id="mrv-selected-products">
                            <div class="mrv-selected-list" id="mrv-selected-list">
                                <?php if (empty($selected_products)): ?>
                                    <div class="mrv-empty-state" id="mrv-empty-state">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                            <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/>
                                        </svg>
                                        <p><?php esc_html_e('No products selected', 'shopvision'); ?></p>
                                        <span><?php esc_html_e('Search products', 'shopvision'); ?></span>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($selected_products as $product): ?>
                                        <div class="mrv-selected-item" data-id="<?php echo esc_attr($product->get_id()); ?>">
                                            <?php
                                            $thumb = $product->get_image_id() ? wp_get_attachment_image_url($product->get_image_id(), 'thumbnail') : wc_placeholder_img_src('thumbnail');
                                            ?>
                                            <img src="<?php echo esc_url($thumb); ?>" alt="" class="mrv-product-thumb">
                                            <div class="mrv-product-info">
                                                <span class="mrv-product-name"><?php echo esc_html($product->get_name()); ?></span>
                                                <?php if ($product->get_sku()): ?>
                                                    <span class="mrv-product-sku">SKU: <?php echo esc_html($product->get_sku()); ?></span>
                                                <?php endif; ?>
                                            </div>
                                            <button type="button" class="mrv-remove-product" data-id="<?php echo esc_attr($product->get_id()); ?>">
                                                <span class="dashicons dashicons-no-alt"></span>
                                            </button>
                                            <input type="hidden" name="mrv_enabled_products[]" value="<?php echo esc_attr($product->get_id()); ?>">
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mrv-submit-wrap">
                <button type="submit" class="mrv-submit-btn">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/>
                    </svg>
                    <?php esc_html_e('Save', 'shopvision'); ?>
                </button>
            </div>
        </form>
        <?php
    }

    /**
     * Cleanup expired generations
     * Runs when viewing the generaties tab to ensure stale items are removed
     */
    private function cleanup_expired_generations(): void {
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
    }

    /**
     * Render Generaties tab
     */
    private function render_generaties_tab(): void {
        // Run cleanup when viewing this tab to remove expired items
        $this->cleanup_expired_generations();

        $list_table = new MRV_Generations_List();
        $list_table->prepare_items();

        $counts = MRV_Post_Types::get_status_counts();
        ?>
        <h1 class="mrv-page-title"><?php esc_html_e('Generations', 'shopvision'); ?></h1>
        <p class="mrv-page-description"><?php esc_html_e('Manage all visualizations created by customers.', 'shopvision'); ?></p>

        <!-- Stats Cards -->
        <div class="mrv-stats-grid">
            <div class="mrv-stat-card">
                <div class="mrv-stat-value"><?php echo esc_html($counts['all']); ?></div>
                <div class="mrv-stat-label"><?php esc_html_e('Total', 'shopvision'); ?></div>
            </div>
            <div class="mrv-stat-card mrv-stat-pending">
                <div class="mrv-stat-value"><?php echo esc_html($counts['pending']); ?></div>
                <div class="mrv-stat-label"><?php esc_html_e('Pending', 'shopvision'); ?></div>
            </div>
            <div class="mrv-stat-card mrv-stat-approved">
                <div class="mrv-stat-value"><?php echo esc_html($counts['approved']); ?></div>
                <div class="mrv-stat-label"><?php esc_html_e('Approved', 'shopvision'); ?></div>
            </div>
            <div class="mrv-stat-card mrv-stat-featured">
                <div class="mrv-stat-value"><?php echo esc_html($counts['featured']); ?></div>
                <div class="mrv-stat-label"><?php esc_html_e('Featured', 'shopvision'); ?></div>
            </div>
        </div>

        <!-- List Table -->
        <div class="mrv-card">
            <form method="get">
                <input type="hidden" name="page" value="mrv-settings">
                <input type="hidden" name="tab" value="generaties">
                <?php
                $list_table->display();
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * Render Analytics tab
     */
    private function render_analytics_tab(): void {
        // Get period from query string
        $period = isset($_GET['period']) ? sanitize_text_field($_GET['period']) : '30days';
        $valid_periods = ['7days', '30days', '90days', 'all'];
        if (!in_array($period, $valid_periods)) {
            $period = '30days';
        }

        // Get analytics data
        $analytics = MRV_Conversion_Tracker::get_analytics($period);
        $currency = $analytics['currency_symbol'];

        // Get all-time visualization stats
        $alltime_stats = MRV_Post_Types::get_alltime_stats();

        // Get all-time conversion stats
        $alltime_conversions = MRV_Conversion_Tracker::get_alltime_conversion_stats();

        // Get all-time WhatsApp clicks
        $alltime_whatsapp = (int) get_option('mrv_alltime_whatsapp_clicks', 0);

        // Period labels
        $period_labels = [
            '7days'  => __('Last 7 days', 'shopvision'),
            '30days' => __('Last 30 days', 'shopvision'),
            '90days' => __('Last 90 days', 'shopvision'),
            'all'    => __('All time', 'shopvision'),
        ];
        ?>
        <div class="mrv-analytics-header">
            <div>
                <h1 class="mrv-page-title"><?php esc_html_e('Analytics', 'shopvision'); ?></h1>
                <p class="mrv-page-description"><?php esc_html_e('Track visualizer performance and conversion metrics.', 'shopvision'); ?></p>
            </div>
            <div class="mrv-period-selector">
                <select id="mrv-analytics-period" onchange="window.location.href='<?php echo esc_url(admin_url('admin.php?page=mrv-settings&tab=analytics&period=')); ?>'+this.value">
                    <?php foreach ($period_labels as $key => $label): ?>
                        <option value="<?php echo esc_attr($key); ?>" <?php selected($period, $key); ?>>
                            <?php echo esc_html($label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <!-- Main Stats Cards -->
        <div class="mrv-analytics-stats">
            <div class="mrv-analytics-card">
                <div class="mrv-analytics-value"><?php echo esc_html(number_format_i18n($analytics['visualizations'])); ?></div>
                <div class="mrv-analytics-label"><?php esc_html_e('Visualizations', 'shopvision'); ?></div>
                <?php $this->render_change_badge($analytics['visualizations_change']); ?>
            </div>
            <div class="mrv-analytics-card">
                <div class="mrv-analytics-value"><?php echo esc_html(number_format_i18n($analytics['conversions'])); ?></div>
                <div class="mrv-analytics-label"><?php esc_html_e('Conversions', 'shopvision'); ?></div>
                <?php $this->render_change_badge($analytics['conversions_change']); ?>
            </div>
            <div class="mrv-analytics-card">
                <div class="mrv-analytics-value"><?php echo esc_html($analytics['conversion_rate']); ?>%</div>
                <div class="mrv-analytics-label"><?php esc_html_e('Conversion Rate', 'shopvision'); ?></div>
            </div>
        </div>

        <div class="mrv-analytics-stats">
            <div class="mrv-analytics-card">
                <div class="mrv-analytics-value"><?php echo esc_html($currency . number_format_i18n($analytics['revenue'], 2)); ?></div>
                <div class="mrv-analytics-label"><?php esc_html_e('Revenue from Visualizer', 'shopvision'); ?></div>
                <?php $this->render_change_badge($analytics['revenue_change']); ?>
            </div>
            <div class="mrv-analytics-card">
                <div class="mrv-analytics-value"><?php echo esc_html($currency . number_format_i18n($analytics['avg_order_value'], 2)); ?></div>
                <div class="mrv-analytics-label"><?php esc_html_e('Avg. Order Value', 'shopvision'); ?></div>
            </div>
            <div class="mrv-analytics-card">
                <div class="mrv-analytics-value"><?php echo esc_html($analytics['days_to_convert']); ?></div>
                <div class="mrv-analytics-label"><?php esc_html_e('Avg. Days to Convert', 'shopvision'); ?></div>
            </div>
        </div>

        <!-- All-time Stats -->
        <div class="mrv-alltime-wrapper">
            <div class="mrv-alltime-header">
                <h2 class="mrv-alltime-title"><?php esc_html_e('All-time Statistics', 'shopvision'); ?></h2>
                <p class="mrv-alltime-description">
                    <?php esc_html_e('Lifetime totals including visualizations that have been automatically deleted.', 'shopvision'); ?>
                </p>
            </div>

            <!-- Visualization Stats -->
            <div class="mrv-alltime-section">
                <h4 class="mrv-alltime-section-title"><?php esc_html_e('Visualizations', 'shopvision'); ?></h4>
                <div class="mrv-analytics-stats" style="margin: 0;">
                    <div class="mrv-analytics-card">
                        <div class="mrv-analytics-value"><?php echo esc_html(number_format_i18n($alltime_stats['total'])); ?></div>
                        <div class="mrv-analytics-label"><?php esc_html_e('Total', 'shopvision'); ?></div>
                    </div>
                    <div class="mrv-analytics-card">
                        <div class="mrv-analytics-value"><?php echo esc_html(number_format_i18n($alltime_stats['approved'])); ?></div>
                        <div class="mrv-analytics-label"><?php esc_html_e('Approved', 'shopvision'); ?></div>
                    </div>
                    <div class="mrv-analytics-card">
                        <div class="mrv-analytics-value"><?php echo esc_html(number_format_i18n($alltime_stats['not_approved'])); ?></div>
                        <div class="mrv-analytics-label"><?php esc_html_e('Not Approved', 'shopvision'); ?></div>
                    </div>
                </div>
            </div>

            <!-- Conversion Stats -->
            <div class="mrv-alltime-section">
                <h4 class="mrv-alltime-section-title"><?php esc_html_e('Conversions & Engagement', 'shopvision'); ?></h4>
                <div class="mrv-analytics-stats" style="margin: 0;">
                    <div class="mrv-analytics-card">
                        <div class="mrv-analytics-value"><?php echo esc_html(number_format_i18n($alltime_conversions['conversions'])); ?></div>
                        <div class="mrv-analytics-label"><?php esc_html_e('Conversions', 'shopvision'); ?></div>
                    </div>
                    <div class="mrv-analytics-card">
                        <div class="mrv-analytics-value"><?php echo esc_html($currency . number_format_i18n($alltime_conversions['revenue'], 2)); ?></div>
                        <div class="mrv-analytics-label"><?php esc_html_e('Revenue', 'shopvision'); ?></div>
                    </div>
                    <div class="mrv-analytics-card">
                        <div class="mrv-analytics-value"><?php echo esc_html(number_format_i18n($alltime_whatsapp)); ?></div>
                        <div class="mrv-analytics-label"><?php esc_html_e('WhatsApp Quotes', 'shopvision'); ?></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tables Section -->
        <div class="mrv-analytics-tables">
            <!-- Recent Conversions -->
            <div class="mrv-card">
                <h2 class="mrv-card-title"><?php esc_html_e('Recent Conversions', 'shopvision'); ?></h2>
                <?php if (empty($analytics['recent_conversions'])): ?>
                    <p class="mrv-empty-state"><?php esc_html_e('No conversions yet. Orders from customers who used the visualizer will appear here.', 'shopvision'); ?></p>
                <?php else: ?>
                    <table class="mrv-analytics-table">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Order', 'shopvision'); ?></th>
                                <th><?php esc_html_e('Total', 'shopvision'); ?></th>
                                <th><?php esc_html_e('Date', 'shopvision'); ?></th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($analytics['recent_conversions'] as $conversion): ?>
                                <tr>
                                    <td><strong>#<?php echo esc_html($conversion['order_number']); ?></strong></td>
                                    <td><?php echo esc_html($currency . number_format_i18n($conversion['total'], 2)); ?></td>
                                    <td><?php echo esc_html($conversion['date']); ?></td>
                                    <td>
                                        <a href="<?php echo esc_url($conversion['edit_url']); ?>" class="mrv-link">
                                            <?php esc_html_e('View', 'shopvision'); ?> →
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <!-- Top Products -->
            <div class="mrv-card">
                <h2 class="mrv-card-title"><?php esc_html_e('Top Visualized Products', 'shopvision'); ?></h2>
                <?php if (empty($analytics['top_products'])): ?>
                    <p class="mrv-empty-state"><?php esc_html_e('No visualizations yet. Products visualized by customers will appear here.', 'shopvision'); ?></p>
                <?php else: ?>
                    <table class="mrv-analytics-table">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Product', 'shopvision'); ?></th>
                                <th><?php esc_html_e('Visualizations', 'shopvision'); ?></th>
                                <th><?php esc_html_e('Conversions', 'shopvision'); ?></th>
                                <th><?php esc_html_e('Conv. Rate', 'shopvision'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($analytics['top_products'] as $product): ?>
                                <tr>
                                    <td>
                                        <a href="<?php echo esc_url($product['edit_url']); ?>">
                                            <?php echo esc_html($product['name']); ?>
                                        </a>
                                    </td>
                                    <td><?php echo esc_html(number_format_i18n($product['visualizations'])); ?></td>
                                    <td><?php echo esc_html(number_format_i18n($product['conversions'])); ?></td>
                                    <td><?php echo esc_html($product['conversion_rate']); ?>%</td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

        <!-- Info Box -->
        <div class="mrv-info-box">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/>
            </svg>
            <div>
                <strong><?php esc_html_e('How conversion tracking works', 'shopvision'); ?></strong>
                <p><?php esc_html_e('When a customer uses the visualizer, a cookie tracks their session. If they place an order within 30 days, it\'s counted as a conversion. This is a functional cookie that doesn\'t require GDPR consent.', 'shopvision'); ?></p>
            </div>
        </div>
        <?php
    }

    /**
     * Render change badge (up/down arrow with percentage)
     *
     * @param float $change Percentage change
     */
    private function render_change_badge(float $change): void {
        if ($change == 0) {
            return;
        }

        $class = $change > 0 ? 'mrv-change-up' : 'mrv-change-down';
        $arrow = $change > 0 ? '↑' : '↓';
        $value = abs($change);

        echo '<div class="mrv-analytics-change ' . esc_attr($class) . '">';
        echo esc_html($arrow . ' ' . $value . '%');
        echo '</div>';
    }

    /**
     * Render Widgets tab
     */
    private function render_widgets_tab(): void {
        // Get current settings
        $enabled = get_option('mrv_widget_marquee_enabled', false);
        $title = get_option('mrv_widget_marquee_title', 'Bekijk onze resultaten');
        $subtitle = get_option('mrv_widget_marquee_subtitle', '');
        $button_text = get_option('mrv_widget_marquee_button_text', 'Shop nu');
        $button_url = get_option('mrv_widget_marquee_button_url', '/shop');
        $bg_color = get_option('mrv_widget_marquee_bg_color', '#f9fafb');
        $overlay_color = get_option('mrv_widget_marquee_overlay_color', 'rgba(0,0,0,0.6)');
        $text_color = get_option('mrv_widget_marquee_text_color', '#ffffff');
        $button_color = get_option('mrv_widget_marquee_button_color', '') ?: get_option('mrv_accent_color', '#2563eb');
        $height = get_option('mrv_widget_marquee_height', '60vh');
        $speed = get_option('mrv_widget_marquee_speed', 30);
        $status = get_option('mrv_widget_marquee_status', 'featured');
        $limit = get_option('mrv_widget_marquee_limit', 12);
        $link_product = get_option('mrv_widget_marquee_link_product', true);

        // Get preview images
        $preview_images = MRV_Widgets::get_preview_images($status, $limit);
        $image_count = count($preview_images);
        ?>
        <form method="post" action="options.php">
            <?php settings_fields('mrv_settings_widgets'); ?>

            <!-- Page Header -->
            <div class="mrv-page-header">
                <h1 class="mrv-page-title"><?php esc_html_e('Widgets', 'shopvision'); ?></h1>
                <p class="mrv-page-description"><?php esc_html_e('Display approved visualizations on your website using shortcodes.', 'shopvision'); ?></p>
            </div>

            <!-- Marquee Gallery Widget -->
            <div class="mrv-card">
                <div class="mrv-card-header">
                    <h2 class="mrv-card-title"><?php esc_html_e('Marquee Gallery', 'shopvision'); ?></h2>
                    <p class="mrv-card-description"><?php esc_html_e('A dynamic gallery with two scrolling rows of visualizations and a text overlay.', 'shopvision'); ?></p>
                </div>

                <!-- Enable Toggle -->
                <div class="mrv-field">
                    <div class="mrv-icon-toggle-row">
                        <label class="mrv-toggle">
                            <input type="hidden" name="mrv_widget_marquee_enabled" value="0">
                            <input type="checkbox" id="mrv_widget_marquee_enabled" name="mrv_widget_marquee_enabled" value="1" <?php checked($enabled); ?>>
                            <span class="mrv-toggle-slider"></span>
                        </label>
                        <span class="mrv-toggle-text"><?php esc_html_e('Enable Widget', 'shopvision'); ?></span>
                    </div>
                </div>

                <div class="mrv-widget-settings" style="<?php echo !$enabled ? 'opacity: 0.5; pointer-events: none;' : ''; ?>">
                    <!-- Two Column Layout -->
                    <div class="mrv-grid mrv-grid-2">
                        <!-- Left Column: Text Settings -->
                        <div class="mrv-widget-section">
                            <h3 class="mrv-section-title"><?php esc_html_e('Text', 'shopvision'); ?></h3>

                            <div class="mrv-form-row">
                                <label for="mrv_widget_marquee_title"><?php esc_html_e('Title', 'shopvision'); ?></label>
                                <input type="text" id="mrv_widget_marquee_title" name="mrv_widget_marquee_title"
                                       value="<?php echo esc_attr($title); ?>" class="regular-text" />
                            </div>

                            <div class="mrv-form-row">
                                <label for="mrv_widget_marquee_subtitle"><?php esc_html_e('Subtitle', 'shopvision'); ?></label>
                                <input type="text" id="mrv_widget_marquee_subtitle" name="mrv_widget_marquee_subtitle"
                                       value="<?php echo esc_attr($subtitle); ?>" class="regular-text" />
                                <p class="mrv-field-hint"><?php esc_html_e('Optional text below the title.', 'shopvision'); ?></p>
                            </div>

                            <div class="mrv-form-row">
                                <label for="mrv_widget_marquee_button_text"><?php esc_html_e('Button Text', 'shopvision'); ?></label>
                                <input type="text" id="mrv_widget_marquee_button_text" name="mrv_widget_marquee_button_text"
                                       value="<?php echo esc_attr($button_text); ?>" class="regular-text" />
                            </div>

                            <div class="mrv-form-row">
                                <label for="mrv_widget_marquee_button_url"><?php esc_html_e('Button URL', 'shopvision'); ?></label>
                                <input type="text" id="mrv_widget_marquee_button_url" name="mrv_widget_marquee_button_url"
                                       value="<?php echo esc_attr($button_url); ?>" class="regular-text" />
                            </div>
                        </div>

                        <!-- Right Column: Styling -->
                        <div class="mrv-widget-section">
                            <h3 class="mrv-section-title"><?php esc_html_e('Styling', 'shopvision'); ?></h3>

                            <div class="mrv-color-field">
                                <label class="mrv-field-label"><?php esc_html_e('Background', 'shopvision'); ?></label>
                                <input type="text"
                                       id="mrv_widget_marquee_bg_color"
                                       name="mrv_widget_marquee_bg_color"
                                       value="<?php echo esc_attr($bg_color); ?>"
                                       class="mrv-color-picker"
                                       data-default-color="#f9fafb">
                                <p class="mrv-field-description"><?php esc_html_e('Also used for fade effect on edges.', 'shopvision'); ?></p>
                            </div>

                            <div class="mrv-color-field">
                                <label class="mrv-field-label"><?php esc_html_e('Text Color', 'shopvision'); ?></label>
                                <input type="text"
                                       id="mrv_widget_marquee_text_color"
                                       name="mrv_widget_marquee_text_color"
                                       value="<?php echo esc_attr($text_color); ?>"
                                       class="mrv-color-picker"
                                       data-default-color="#ffffff">
                            </div>

                            <div class="mrv-color-field">
                                <label class="mrv-field-label"><?php esc_html_e('Button Color', 'shopvision'); ?></label>
                                <input type="text"
                                       id="mrv_widget_marquee_button_color"
                                       name="mrv_widget_marquee_button_color"
                                       value="<?php echo esc_attr($button_color); ?>"
                                       class="mrv-color-picker"
                                       data-default-color="#2563eb">
                            </div>

                            <div class="mrv-form-row">
                                <label for="mrv_widget_marquee_height"><?php esc_html_e('Height', 'shopvision'); ?></label>
                                <select id="mrv_widget_marquee_height" name="mrv_widget_marquee_height">
                                    <option value="40vh" <?php selected($height, '40vh'); ?>>40vh (<?php esc_html_e('Small', 'shopvision'); ?>)</option>
                                    <option value="50vh" <?php selected($height, '50vh'); ?>>50vh (<?php esc_html_e('Medium', 'shopvision'); ?>)</option>
                                    <option value="60vh" <?php selected($height, '60vh'); ?>>60vh (<?php esc_html_e('Large', 'shopvision'); ?>)</option>
                                    <option value="80vh" <?php selected($height, '80vh'); ?>>80vh (<?php esc_html_e('Extra Large', 'shopvision'); ?>)</option>
                                    <option value="100vh" <?php selected($height, '100vh'); ?>>100vh (<?php esc_html_e('Full Screen', 'shopvision'); ?>)</option>
                                </select>
                            </div>

                            <div class="mrv-form-row">
                                <label for="mrv_widget_marquee_speed"><?php esc_html_e('Animation Speed', 'shopvision'); ?></label>
                                <div class="mrv-input-with-suffix">
                                    <input type="number" id="mrv_widget_marquee_speed" name="mrv_widget_marquee_speed"
                                           value="<?php echo esc_attr($speed); ?>" min="10" max="120" step="5" />
                                    <span class="mrv-input-suffix"><?php esc_html_e('seconds', 'shopvision'); ?></span>
                                </div>
                                <p class="mrv-field-hint"><?php esc_html_e('Higher = slower. Recommended: 20-40.', 'shopvision'); ?></p>
                            </div>
                        </div>
                    </div>

                    <!-- Images Section -->
                    <div class="mrv-widget-section" style="margin-top: 24px;">
                        <h3 class="mrv-section-title"><?php esc_html_e('Images', 'shopvision'); ?></h3>

                        <div class="mrv-grid mrv-grid-2">
                            <div class="mrv-form-row">
                                <label for="mrv_widget_marquee_status"><?php esc_html_e('Show Visualizations', 'shopvision'); ?></label>
                                <select id="mrv_widget_marquee_status" name="mrv_widget_marquee_status">
                                    <option value="featured" <?php selected($status, 'featured'); ?>><?php esc_html_e('Featured only', 'shopvision'); ?></option>
                                    <option value="approved,featured" <?php selected($status, 'approved,featured'); ?>><?php esc_html_e('Approved + Featured', 'shopvision'); ?></option>
                                </select>
                            </div>

                            <div class="mrv-form-row">
                                <label for="mrv_widget_marquee_limit"><?php esc_html_e('Maximum Images', 'shopvision'); ?></label>
                                <input type="number" id="mrv_widget_marquee_limit" name="mrv_widget_marquee_limit"
                                       value="<?php echo esc_attr($limit); ?>" min="6" max="30" step="1" />
                                <p class="mrv-field-hint"><?php esc_html_e('Minimum 6 required for widget to display.', 'shopvision'); ?></p>
                            </div>
                        </div>

                        <div class="mrv-field">
                            <div class="mrv-icon-toggle-row">
                                <label class="mrv-toggle">
                                    <input type="hidden" name="mrv_widget_marquee_link_product" value="0">
                                    <input type="checkbox" id="mrv_widget_marquee_link_product" name="mrv_widget_marquee_link_product" value="1" <?php checked($link_product); ?>>
                                    <span class="mrv-toggle-slider"></span>
                                </label>
                                <span class="mrv-toggle-text"><?php esc_html_e('Link images to product pages', 'shopvision'); ?></span>
                            </div>
                        </div>

                        <!-- Image count status -->
                        <?php if ($image_count < 6): ?>
                            <div class="mrv-notice mrv-notice-warning" style="margin-top: 16px;">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
                                </svg>
                                <?php
                                printf(
                                    esc_html__('Only %d visualizations available. Need at least 6 for the widget to display.', 'shopvision'),
                                    $image_count
                                );
                                ?>
                            </div>
                        <?php else: ?>
                            <div class="mrv-notice mrv-notice-success" style="margin-top: 16px;">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>
                                </svg>
                                <?php
                                printf(
                                    esc_html__('%d visualizations available for the gallery.', 'shopvision'),
                                    $image_count
                                );
                                ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Shortcode Section -->
                    <div class="mrv-widget-section" style="margin-top: 24px; padding-top: 24px; border-top: 1px solid #e5e7eb;">
                        <h3 class="mrv-section-title"><?php esc_html_e('Shortcode', 'shopvision'); ?></h3>
                        <p class="mrv-field-hint" style="margin-bottom: 12px;">
                            <?php esc_html_e('Copy this shortcode and paste it anywhere on your site (pages, posts, theme templates).', 'shopvision'); ?>
                        </p>
                        <div class="mrv-shortcode-box">
                            <code id="mrv-widget-shortcode">[shopvision_gallery]</code>
                            <button type="button" class="mrv-copy-btn" onclick="navigator.clipboard.writeText('[shopvision_gallery]').then(() => { this.textContent = '<?php esc_attr_e('Copied!', 'shopvision'); ?>'; setTimeout(() => { this.textContent = '<?php esc_attr_e('Copy', 'shopvision'); ?>'; }, 2000); });">
                                <?php esc_html_e('Copy', 'shopvision'); ?>
                            </button>
                        </div>
                    </div>

                    <!-- Preview Section -->
                    <?php if ($image_count >= 6): ?>
                    <div class="mrv-widget-section" style="margin-top: 24px; padding-top: 24px; border-top: 1px solid #e5e7eb;">
                        <h3 class="mrv-section-title"><?php esc_html_e('Preview', 'shopvision'); ?></h3>
                        <p class="mrv-field-hint" style="margin-bottom: 12px;">
                            <?php esc_html_e('This is a scaled preview. Save changes to see updates.', 'shopvision'); ?>
                        </p>
                        <div class="mrv-widget-preview-container" style="height: 300px; overflow: hidden;">
                            <div class="mrv-widget-preview">
                                <?php
                                // Render actual widget preview
                                $widget = new MRV_Widgets();
                                echo $widget->render_marquee_gallery();
                                ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Save Button -->
            <div class="mrv-form-actions">
                <?php submit_button(__('Save Changes', 'shopvision'), 'primary', 'submit', false); ?>
            </div>
        </form>
        <?php
    }

}

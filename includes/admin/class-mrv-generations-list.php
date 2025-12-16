<?php
/**
 * Generations List Table for admin
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class MRV_Generations_List extends WP_List_Table {

    /**
     * Constructor
     */
    public function __construct() {
        parent::__construct([
            'singular' => __('Generation', 'shopvision'),
            'plural'   => __('Generations', 'shopvision'),
            'ajax'     => false,
        ]);
    }

    /**
     * Get columns
     */
    public function get_columns(): array {
        return [
            'cb'         => '<input type="checkbox" />',
            'thumbnail'  => __('Preview', 'shopvision'),
            'product'    => __('Product', 'shopvision'),
            'user'       => __('User', 'shopvision'),
            'status'     => __('Status', 'shopvision'),
            'created'    => __('Created', 'shopvision'),
            'expires'    => __('Expires', 'shopvision'),
        ];
    }

    /**
     * Get sortable columns
     */
    public function get_sortable_columns(): array {
        return [
            'created' => ['post_date', true],
            'product' => ['_mrv_product_id', false],
        ];
    }

    /**
     * Get bulk actions
     */
    public function get_bulk_actions(): array {
        return [
            'approve' => __('Approve', 'shopvision'),
            'reject'  => __('Reject', 'shopvision'),
            'delete'  => __('Delete', 'shopvision'),
        ];
    }

    /**
     * Process bulk actions
     */
    public function process_bulk_action(): void {
        $action = $this->current_action();

        if (!$action) {
            return;
        }

        // Verify nonce
        $nonce = isset($_REQUEST['_wpnonce']) ? sanitize_text_field($_REQUEST['_wpnonce']) : '';
        if (!wp_verify_nonce($nonce, 'bulk-' . $this->_args['plural'])) {
            return;
        }

        $ids = isset($_REQUEST['generation']) ? array_map('absint', (array) $_REQUEST['generation']) : [];

        if (empty($ids)) {
            return;
        }

        switch ($action) {
            case 'approve':
                foreach ($ids as $id) {
                    MRV_Post_Types::update_consent($id, 'approved');
                }
                break;

            case 'reject':
                foreach ($ids as $id) {
                    MRV_Post_Types::update_consent($id, 'rejected');
                }
                break;

            case 'delete':
                foreach ($ids as $id) {
                    MRV_Post_Types::delete_generation($id);
                }
                break;
        }

        // Redirect to remove action from URL
        wp_redirect(remove_query_arg(['action', 'action2', 'generation', '_wpnonce']));
        exit;
    }

    /**
     * Prepare items for display
     */
    public function prepare_items(): void {
        $this->process_bulk_action();

        $per_page = 20;
        $current_page = $this->get_pagenum();

        // Build query args
        $args = [
            'post_type'      => MRV_Post_Types::POST_TYPE,
            'posts_per_page' => $per_page,
            'paged'          => $current_page,
            'post_status'    => 'publish',
        ];

        // Handle status filter
        $status_filter = isset($_GET['status']) ? sanitize_key($_GET['status']) : '';
        if ($status_filter && $status_filter !== 'all') {
            $args['tax_query'] = [
                [
                    'taxonomy' => MRV_Post_Types::TAXONOMY,
                    'field'    => 'slug',
                    'terms'    => $status_filter,
                ],
            ];
        }

        // Handle sorting
        $orderby = isset($_GET['orderby']) ? sanitize_key($_GET['orderby']) : 'post_date';
        $order = isset($_GET['order']) ? sanitize_key($_GET['order']) : 'DESC';

        if ($orderby === '_mrv_product_id') {
            $args['meta_key'] = '_mrv_product_id';
            $args['orderby'] = 'meta_value_num';
        } else {
            $args['orderby'] = $orderby;
        }
        $args['order'] = strtoupper($order);

        // Execute query
        $query = new WP_Query($args);

        $this->items = $query->posts;

        // Set pagination
        $this->set_pagination_args([
            'total_items' => $query->found_posts,
            'per_page'    => $per_page,
            'total_pages' => $query->max_num_pages,
        ]);

        // Set columns
        $this->_column_headers = [
            $this->get_columns(),
            [],
            $this->get_sortable_columns(),
        ];
    }

    /**
     * Checkbox column
     */
    public function column_cb($item): string {
        return sprintf(
            '<input type="checkbox" name="generation[]" value="%d" />',
            $item->ID
        );
    }

    /**
     * Thumbnail column
     */
    public function column_thumbnail($item): string {
        $image_url = get_post_meta($item->ID, '_mrv_image_url', true);

        if (!$image_url) {
            return '<span class="mrv-no-image">—</span>';
        }

        return sprintf(
            '<a href="%s" target="_blank" class="mrv-thumbnail-link"><img src="%s" alt="" class="mrv-admin-thumbnail" /></a>',
            esc_url($image_url),
            esc_url($image_url)
        );
    }

    /**
     * Product column - supports multi-product visualizations
     */
    public function column_product($item): string {
        // Try multi-product first, fallback to single
        $product_ids = get_post_meta($item->ID, '_mrv_product_ids', true);

        if (empty($product_ids) || !is_array($product_ids)) {
            // Fallback to legacy single product
            $product_id = get_post_meta($item->ID, '_mrv_product_id', true);
            $product_ids = $product_id ? [$product_id] : [];
        }

        if (empty($product_ids)) {
            return '<span class="mrv-deleted-product">' . esc_html__('No product', 'shopvision') . '</span>';
        }

        // Get first product
        $first_product = wc_get_product($product_ids[0]);

        if (!$first_product) {
            return sprintf(
                '<span class="mrv-deleted-product">%s #%d</span>',
                esc_html__('Deleted product', 'shopvision'),
                $product_ids[0]
            );
        }

        $output = sprintf(
            '<a href="%s" class="mrv-product-link">%s</a>',
            esc_url(get_edit_post_link($product_ids[0])),
            esc_html($first_product->get_name())
        );

        // Show additional products count
        $extra_count = count($product_ids) - 1;
        if ($extra_count > 0) {
            $output .= sprintf(
                '<span class="mrv-extra-products"> + %d %s</span>',
                $extra_count,
                $extra_count === 1 ? esc_html__('more', 'shopvision') : esc_html__('more', 'shopvision')
            );
        }

        return $output;
    }

    /**
     * User column
     */
    public function column_user($item): string {
        $user_id = $item->post_author;

        if (!$user_id) {
            $session_id = get_post_meta($item->ID, '_mrv_session_id', true);
            return sprintf(
                '<span class="mrv-guest-user">%s</span><br><small class="mrv-session-id">%s</small>',
                esc_html__('Guest', 'shopvision'),
                esc_html(substr($session_id, 0, 8) . '...')
            );
        }

        $user = get_userdata($user_id);
        if (!$user) {
            return '<span class="mrv-deleted-user">' . esc_html__('Deleted user', 'shopvision') . '</span>';
        }

        return sprintf(
            '<a href="%s">%s</a>',
            esc_url(get_edit_user_link($user_id)),
            esc_html($user->display_name)
        );
    }

    /**
     * Status column
     */
    public function column_status($item): string {
        $status = get_post_meta($item->ID, '_mrv_consent_status', true) ?: 'pending';

        $labels = [
            'pending'  => __('Pending', 'shopvision'),
            'approved' => __('Approved', 'shopvision'),
            'featured' => __('Featured', 'shopvision'),
            'rejected' => __('Rejected', 'shopvision'),
        ];

        $colors = [
            'pending'  => '#f59e0b',
            'approved' => '#10b981',
            'featured' => '#8b5cf6',
            'rejected' => '#ef4444',
        ];

        return sprintf(
            '<span class="mrv-status-badge" style="background-color: %s;">%s</span>',
            esc_attr($colors[$status] ?? '#6b7280'),
            esc_html($labels[$status] ?? $status)
        );
    }

    /**
     * Created column
     */
    public function column_created($item): string {
        $date = get_the_date('d M Y', $item);
        $time = get_the_time('H:i', $item);
        $human = human_time_diff(get_the_time('U', $item), time());

        return sprintf(
            '<span title="%s %s">%s geleden</span>',
            esc_attr($date),
            esc_attr($time),
            esc_html($human)
        );
    }

    /**
     * Expires column
     */
    public function column_expires($item): string {
        $expires_at = get_post_meta($item->ID, '_mrv_expires_at', true);

        if (!$expires_at) {
            return '<span class="mrv-no-expiry">—</span>';
        }

        $now = time();
        $diff = $expires_at - $now;

        if ($diff < 0) {
            return '<span class="mrv-expired">' . esc_html__('Expired', 'shopvision') . '</span>';
        }

        return sprintf(
            '<span class="mrv-expires-in">%s</span>',
            esc_html(human_time_diff($now, $expires_at))
        );
    }

    /**
     * Default column handler
     */
    public function column_default($item, $column_name): string {
        return '—';
    }

    /**
     * Extra table navigation (status filters)
     */
    protected function extra_tablenav($which): void {
        if ($which !== 'top') {
            return;
        }

        $counts = MRV_Post_Types::get_status_counts();
        $current = isset($_GET['status']) ? sanitize_key($_GET['status']) : 'all';

        echo '<div class="alignleft actions mrv-status-filters">';

        $statuses = [
            'all'      => __('All', 'shopvision'),
            'pending'  => __('Pending', 'shopvision'),
            'approved' => __('Approved', 'shopvision'),
            'featured' => __('Featured', 'shopvision'),
            'rejected' => __('Rejected', 'shopvision'),
        ];

        foreach ($statuses as $status => $label) {
            $count = $counts[$status] ?? 0;
            $class = $current === $status ? 'current' : '';
            $url = $status === 'all'
                ? remove_query_arg('status')
                : add_query_arg('status', $status);

            printf(
                '<a href="%s" class="%s">%s <span class="count">(%d)</span></a>',
                esc_url($url),
                esc_attr($class),
                esc_html($label),
                $count
            );

            if ($status !== 'rejected') {
                echo ' | ';
            }
        }

        echo '</div>';
    }

    /**
     * Message when no items found
     */
    public function no_items(): void {
        esc_html_e('No generations found.', 'shopvision');
    }
}

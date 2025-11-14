<?php

namespace WpComet\AISays;

if (!class_exists('WP_List_Table')) {
    require_once ABSPATH.'wp-admin/includes/class-wp-list-table.php';
}

class ProductsTable extends \WP_List_Table {
    private $per_page = 20;

    public function __construct() {
        parent::__construct([
            'singular' => 'product',
            'plural' => 'products',
            'ajax' => false,
        ]);
    }

    protected function get_sortable_columns() {
        return [
            'product' => ['post_title', false],
        ];
    }

    protected function column_cb($item) {
        return sprintf(
            '<label><input type="checkbox" name="product_ids[]" value="%s" /></label>',
            $item->ID
        );
    }

    protected function column_product($item) {
        $product = wc_get_product($item->ID);
        $edit_url = get_edit_post_link($item->ID);
        $title = $item->post_title;
        $sku = $product->get_sku();
        $thumb = $product->get_image('thumbnail');
        $product_url = get_permalink($item->ID);

        $output = '<div style="display: flex; align-items: center; gap: 10px;">';
        $output .= '<div class="img-wrap"><a href="'.esc_url($edit_url).'" style="position:relative"><span class="dashicons dashicons-edit"></span>'.$thumb.'</a></div>';
        $output .= '<div>';
        $output .= '<a href="'.esc_url($product_url).'" target="_blank"><strong>'.esc_html($title).'</strong>';
        $output .= '<span class="dashicons dashicons-external"></span></a>';

        if ($sku) {
            $output .= '<br><small>'.esc_html__('SKU:', 'comet-ai-says').' '.esc_html($sku).'</small>';
        }
        $output .= '</div>';
        $output .= '</div>';

        return $output;
    }

    protected function column_short_desc($item) {
        $short_desc = $item->post_excerpt;
        if (empty($short_desc)) {
            return '<span style="color: #881300f7; font-style: italic;">'.esc_html__('No short description', 'comet-ai-says').'</span>';
        }

        return '<div style="max-height: 60px; overflow: hidden;">'.wp_trim_words($short_desc, 10).'</div>';
    }

    protected function column_full_desc($item) {
        $full_desc = $item->post_content;
        if (empty($full_desc)) {
            return '<span style="color: #881300f7; font-style: italic;">'.esc_html__('No description', 'comet-ai-says').'</span>';
        }

        return '<div style="max-height: 60px; overflow: hidden;">'.wp_trim_words($full_desc, 15).'</div>';
    }

    protected function get_bulk_actions() {
        if (!current_user_can('manage_woocommerce')) {
            return [];
        }

        return [
            'bulk_generate' => esc_html__('Generate AI Descriptions for Selected', 'comet-ai-says'),
            'bulk_delete' => esc_html__('Delete AI Descriptions for Selected', 'comet-ai-says'),
        ];
    }

    public function get_columns() {
        return [
            'cb' => '<input type="checkbox" />',
            'product' => esc_html__('Product', 'comet-ai-says'),
            'short_desc' => esc_html__('Short Description', 'comet-ai-says'),
            'full_desc' => esc_html__('Full Description', 'comet-ai-says'),
            'actions' => esc_html__('Actions', 'comet-ai-says'),
            // translators: Status
            'status' => esc_html__('S', 'comet-ai-says'),
        ];
    }

    // In your ProductsTable class, enhance the columns
    public function column_ai_description($item) {
        $description = get_post_meta($item->ID, '_wpcmt_aisays_description', true);

        if (empty($description)) {
            return '<span class="ai-status-badge badge-empty">'.esc_html__('No AI Description', 'comet-ai-says').'</span>';
        }

        $preview = wp_trim_words($description, 15);

        return '<div class="ai-description-preview" title="'.esc_attr($description).'">'.esc_html($preview).'</div>';
    }

    public function column_status($item) {
        $has_ai = !empty(get_post_meta($item->ID, '_wpcmt_aisays_description', true));
        $status_class = $has_ai ? 'dashicons-yes text-success' : 'dashicons-no text-warning';

        return '<span class="status-indicator dashicons '.esc_attr($status_class).'"></span>';
    }

    public function column_actions($item) {
        $has_ai = !empty(get_post_meta($item->ID, '_wpcmt_aisays_description', true));
        // $status_class = $has_ai ? 'status-has-ai' : 'status-no-ai';
        $status_class = $has_ai ? 'dashicons-yes text-success' : 'dashicons-no text-warning';
        $actions = [];
        if (!$has_ai) {
            $actions = [
                'generate' => sprintf(
                    '<a href="javascript:void(0);" class="generate-single-ai button button-primary" data-product-id="%d" data-product-name="%s"><span class="dashicons dashicons-media-text"></span> %s</a>',
                    $item->ID,
                    esc_attr($item->post_title),
                    esc_html__('Generate AI desc.', 'comet-ai-says')
                ),
            ];
        } else {
            $actions['view'] = sprintf(
                '<a href="javascript:void(0);" class="view-ai-desc button" data-product-id="%d"><span class="dashicons dashicons-visibility"></span> %s</a>',
                $item->ID,
                esc_html__('View AI desc', 'comet-ai-says')
            );
            $actions['regenerate'] = sprintf(
                '<a href="javascript:void(0);" class="generate-single-ai button button-primary" data-product-id="%d" data-product-name="%s"><span class="dashicons dashicons-update"></span> %s</a>',
                $item->ID,
                esc_attr($item->post_title),
                esc_html__('Regenerate', 'comet-ai-says')
            );
            // Add the delete button
            $actions['delete'] = sprintf(
                '<a href="javascript:void(0);" class="delete-ai-desc button button-link-delete" data-product-id="%d" data-product-name="%s"><span class="dashicons dashicons-trash"></span> %s</a>',
                $item->ID,
                esc_attr($item->post_title),
                esc_html__('Delete AI desc', 'comet-ai-says')
            );
        }

        return '<div class="action-buttons">'.implode('', $actions).'</div>';
    }

    public function prepare_items() {
        $this->_column_headers = [$this->get_columns(), [], $this->get_sortable_columns()];

        // phpcs:disable WordPress.Security.NonceVerification.Recommended
        $args = [
            'post_type' => 'product',
            'post_status' => isset($_GET['status']) ? sanitize_text_field(wp_unslash($_GET['status'])) : 'publish',
            'posts_per_page' => $this->per_page,
            'paged' => $this->get_pagenum(),
        ];

        // Search
        if (!empty($_GET['s'])) {
            $search = sanitize_text_field(wp_unslash($_GET['s']));
            $args['s'] = $search;
        }

        // Category filter
        if (!empty($_GET['category'])) {
            $args['tax_query'] = [
                [
                    'taxonomy' => 'product_cat',
                    'field' => 'term_id',
                    'terms' => intval($_GET['category']),
                ],
            ];
        }

        // Description status filter
        if (!empty($_GET['desc_status'])) {
            $desc_status = sanitize_text_field(wp_unslash($_GET['desc_status']));

            switch ($desc_status) {
                case 'no_short':
                    $args['meta_query'] = [
                        [
                            'key' => '_wpcmt_aisays_description',
                            'compare' => 'NOT EXISTS',
                        ],
                    ];

                    break;
                case 'has_ai':
                    $args['meta_query'] = [
                        [
                            'key' => '_wpcmt_aisays_description',
                            'compare' => 'EXISTS',
                        ],
                    ];

                    break;
                case 'no_full':
                    $args['meta_query'] = [
                        [
                            'key' => '_wpcmt_aisays_description',
                            'compare' => 'EXISTS',
                        ],
                    ];

                    break;
                case 'both_missing':
                    $args['meta_query'] = [
                        [
                            'key' => '_wpcmt_aisays_description',
                            'compare' => 'NOT EXISTS',
                        ],
                    ];

                    break;
            }
        }

        // Sort
        if (!empty($_GET['orderby'])) {
            switch ($_GET['orderby']) {
                case 'product':
                    $args['orderby'] = 'title';

                    break;
                case 'date':
                    $args['orderby'] = 'date';

                    break;
                default:
                    $args['orderby'] = 'title';
            }
            $args['order'] = (!empty($_GET['order']) && 'desc' === $_GET['order']) ? 'DESC' : 'ASC';
        }
        // phpcs:enable WordPress.Security.NonceVerification.Recommended

        $query = new \WP_Query($args);
        $this->items = $query->posts;

        $this->set_pagination_args([
            'total_items' => $query->found_posts,
            'per_page' => $this->per_page,
            'total_pages' => ceil($query->found_posts / $this->per_page),
        ]);
    }

    public function search_box($text, $input_id) {
        AdminInterface::display_usage_stats();
    }

    // Helper method for no full description filter
    public function where_no_full_description($where) {
        global $wpdb;
        $where .= " AND ({$wpdb->posts}.post_content IS NULL OR {$wpdb->posts}.post_content = '')";

        return $where;
    }

    // Helper method for both descriptions missing filter
    public function where_both_descriptions_missing($where) {
        global $wpdb;
        $where .= " AND ({$wpdb->posts}.post_excerpt IS NULL OR {$wpdb->posts}.post_excerpt = '')";
        $where .= " AND ({$wpdb->posts}.post_content IS NULL OR {$wpdb->posts}.post_content = '')";

        return $where;
    }

    public function extra_tablenav($which) {
        if ('top' !== $which) {
            return;
        }

        $categories = get_terms(['taxonomy' => 'product_cat', 'hide_empty' => false]);

        // phpcs:disable WordPress.Security.NonceVerification.Recommended
        $current_category = isset($_GET['category']) ? intval($_GET['category']) : '';
        $current_status = isset($_GET['status']) ? sanitize_text_field(wp_unslash($_GET['status'])) : '';
        $current_desc_status = isset($_GET['desc_status']) ? sanitize_text_field(wp_unslash($_GET['desc_status'])) : '';
        $current_sort = isset($_GET['orderby']) ? sanitize_text_field(wp_unslash($_GET['orderby'])) : '';
        $current_search = isset($_GET['s']) ? esc_attr(sanitize_text_field(wp_unslash($_GET['s']))) : '';
        // phpcs:enable WordPress.Security.NonceVerification.Recommended
        ?>
<div class="alignleft actions">
	<!-- Search -->
	<input type="text" name="s"
		value="<?php echo esc_attr($current_search); ?>"
		placeholder="<?php esc_attr_e('Search name or SKU', 'comet-ai-says'); ?>" />

	<!-- Status -->
	<select name="status">
		<option value="">
			<?php esc_html_e('All Statuses', 'comet-ai-says'); ?>
		</option>
		<option value="publish" <?php selected($current_status, 'publish'); ?>><?php esc_html_e('Published', 'comet-ai-says'); ?>
		</option>
		<option value="draft" <?php selected($current_status, 'draft'); ?>><?php esc_html_e('Draft', 'comet-ai-says'); ?>
		</option>
		<option value="pending" <?php selected($current_status, 'pending'); ?>><?php esc_html_e('Pending', 'comet-ai-says'); ?>
		</option>
		<option value="any" <?php selected($current_status, 'any'); ?>><?php esc_html_e('Any Status', 'comet-ai-says'); ?>
		</option>
	</select>

	<!-- Category -->
	<select name="category">
		<option value="">
			<?php esc_html_e('All Categories', 'comet-ai-says'); ?>
		</option>
		<?php foreach ($categories as $category): ?>
		<option value="<?php echo esc_attr($category->term_id); ?>"
			<?php selected($current_category, $category->term_id); ?>>
			<?php echo esc_html($category->name); ?>
		</option>
		<?php endforeach; ?>
	</select>

	<!-- Description Status -->
	<select name="desc_status">
		<option value="">
			<?php esc_html_e('All Products', 'comet-ai-says'); ?>
		</option>
		<option value="no_short" <?php selected($current_desc_status, 'no_short'); ?>><?php esc_html_e('No AI Description', 'comet-ai-says'); ?>
		</option>
		<option value="has_ai" <?php selected($current_desc_status, 'has_ai'); ?>><?php esc_html_e('Has AI Description', 'comet-ai-says'); ?>
		</option>
		<option value="no_full" <?php selected($current_desc_status, 'no_full'); ?>><?php esc_html_e('No Full Description', 'comet-ai-says'); ?>
		</option>
		<option value="both_missing" <?php selected($current_desc_status, 'both_missing'); ?>><?php esc_html_e('Both Missing', 'comet-ai-says'); ?>
		</option>
	</select>

	<!-- Sort -->
	<select name="orderby">
		<option value="product" <?php selected($current_sort, 'product'); ?>><?php esc_html_e('Sort by Name', 'comet-ai-says'); ?>
		</option>
		<option value="date" <?php selected($current_sort, 'date'); ?>><?php esc_html_e('Sort by Date', 'comet-ai-says'); ?>
		</option>
	</select>

	<?php submit_button(esc_html__('Filter', 'comet-ai-says'), '', 'filter_action', false); ?>

	<!-- Reset button -->
	<a href="<?php echo esc_url(admin_url('edit.php?post_type=product&page=wpcmt-aisays-table')); ?>"
		class="button">
		<?php esc_html_e('Reset', 'comet-ai-says'); ?>
	</a>
</div>
<?php
    }

    /**
     * Display the table with proper form structure for bulk actions
     * This is the ONLY method we need to override.
     */
    public function display() {
        echo '<form method="post" id="wpcmt-aisays-bulk-form">';
        // This is crucial - it adds the bulk action nonce that WordPress expects
        wp_nonce_field('bulk-products', '_wpnonce', false);
        echo '<input type="hidden" name="page" value="wpcmt-aisays-table">';

        // Call the parent display method to maintain all the original table functionality
        parent::display();

        echo '</form>';
    }
}
?>
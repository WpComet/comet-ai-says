<?php

namespace WpComet\AISays;

defined('ABSPATH') || exit;

if (!class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * WooCommerce Products List Table for Comet AI Says.
 */
class ProductsTable extends \WP_List_Table
{
    private $per_page = 20;

    public function __construct()
    {
        parent::__construct([
            'singular' => 'product',
            'plural'   => 'products',
            'ajax'     => false,
        ]);
    }

    protected function get_table_classes(): array
    {
        return [
            'wp-list-table',
            'widefat',
            'fixed',
            'striped',
            'table',
            'is-striped',
            'is-hoverable',
            'is-fullwidth',
            $this->_args['plural'],
        ];
    }


    protected function get_sortable_columns(): array
    {
        return [
            'product' => ['post_title', false],
        ];
    }

    protected function column_cb($item): string
    {
        return sprintf(
            '<label class="comet-table-cb-label"><input type="checkbox" name="product_ids[]" value="%s" /></label>',
            esc_attr($item->ID)
        );
    }

    protected function column_product($item): string
    {
        $product = wc_get_product($item->ID);
        if (!$product) {
            return esc_html($item->post_title);
        }

        $edit_url    = get_edit_post_link($item->ID);
        $title       = $item->post_title;
        $sku         = $product->get_sku();
        $thumb       = $product->get_image('thumbnail');
        $product_url = get_permalink($item->ID);

        $output  = '<div class="comet-product-cell">';
        $output .= '<div class="comet-product-thumb"><a href="' . esc_url($edit_url) . '" title="' . esc_attr__('Edit product', 'comet-ai-says') . '">' . $thumb . '</a></div>';
        $output .= '<div class="comet-product-info">';
        $output .= '<a href="' . esc_url($edit_url) . '" class="comet-product-title"><strong>' . esc_html($title) . '</strong></a>';
        $output .= ' <a href="' . esc_url($product_url) . '" target="_blank" class="comet-external-link" title="' . esc_attr__('View product page', 'comet-ai-says') . '"><span class="dashicons dashicons-external"></span></a>';

        if ($sku) {
            $output .= '<div class="comet-product-sku"><small>' . esc_html__('SKU:', 'comet-ai-says') . ' ' . esc_html($sku) . '</small></div>';
        }
        $output .= '</div>';
        $output .= '</div>';

        return $output;
    }

    protected function column_short_desc($item): string
    {
        $short_desc = $item->post_excerpt;
        if (empty($short_desc)) {
            return '<span class="comet-table-empty">' . esc_html__('No short description', 'comet-ai-says') . '</span>';
        }

        return '<div class="comet-desc-preview">' . esc_html(wp_trim_words($short_desc, 10)) . '</div>';
    }

    protected function column_full_desc($item): string
    {
        $full_desc = $item->post_content;
        if (empty($full_desc)) {
            return '<span class="comet-table-empty">' . esc_html__('No description', 'comet-ai-says') . '</span>';
        }

        return '<div class="comet-desc-preview">' . esc_html(wp_trim_words(wp_strip_all_tags($full_desc), 15)) . '</div>';
    }

    protected function get_bulk_actions(): array
    {
        if (!current_user_can('edit_products')) {
            return [];
        }

        return [
            'bulk_generate' => esc_html__('Generate AI Descriptions for Selected', 'comet-ai-says'),
            'bulk_delete'   => esc_html__('Delete AI Descriptions for Selected', 'comet-ai-says'),
        ];
    }

    public function get_columns(): array
    {
        return [
            'cb'         => '<label class="comet-table-cb-label"><input type="checkbox" /></label>',
            'product'    => esc_html__('Product', 'comet-ai-says'),
            'short_desc' => esc_html__('Short Description', 'comet-ai-says'),
            'full_desc'  => esc_html__('Full Description', 'comet-ai-says'),
            'actions'    => esc_html__('Actions', 'comet-ai-says'),
            'status'     => esc_html__('Status', 'comet-ai-says'),
        ];
    }

    public function column_ai_description($item): string
    {
        $description = get_post_meta($item->ID, '_wpcmt_aisays_description', true);

        if (!AIGenerator::is_valid_description($description)) {
            return '<span class="ai-status-badge badge-empty">' . esc_html__('No AI Description', 'comet-ai-says') . '</span>';
        }

        $preview = wp_trim_words(wp_strip_all_tags($description), 15);

        return '<div class="ai-description-preview" title="' . esc_attr($description) . '">' . esc_html($preview) . '</div>';
    }

    public function column_status($item): string
    {
        $has_ai       = AIGenerator::is_valid_description(get_post_meta($item->ID, '_wpcmt_aisays_description', true));
        $status_class = $has_ai ? 'dashicons-yes text-success' : 'dashicons-no text-warning';
        $title        = $has_ai ? esc_attr__('Has AI Description', 'comet-ai-says') : esc_attr__('No AI Description', 'comet-ai-says');

        return '<span class="status-indicator dashicons ' . esc_attr($status_class) . '" title="' . $title . '"></span>';
    }

    public function column_actions($item): string
    {
        $has_ai  = AIGenerator::is_valid_description(get_post_meta($item->ID, '_wpcmt_aisays_description', true));
        $actions = [];

        if (!$has_ai) {
            $actions['generate'] = sprintf(
                '<a href="javascript:void(0);" class="generate-single-ai button button-primary" data-product-id="%d" data-product-name="%s"><span class="dashicons dashicons-media-text"></span> %s</a>',
                $item->ID,
                esc_attr($item->post_title),
                esc_html__('Generate AI desc.', 'comet-ai-says')
            );
        } else {
            $actions['view'] = sprintf(
                '<a href="javascript:void(0);" class="view-ai-desc button" data-product-id="%d" data-product-name="%s"><span class="dashicons dashicons-visibility"></span> %s</a>',
                $item->ID,
                esc_attr($item->post_title),
                esc_html__('View AI desc', 'comet-ai-says')
            );
            $actions['regenerate'] = sprintf(
                '<a href="javascript:void(0);" class="generate-single-ai button button-primary" data-product-id="%d" data-product-name="%s"><span class="dashicons dashicons-update"></span> %s</a>',
                $item->ID,
                esc_attr($item->post_title),
                esc_html__('Regenerate', 'comet-ai-says')
            );
            $actions['delete'] = sprintf(
                '<a href="javascript:void(0);" class="delete-ai-desc button button-link-delete" data-product-id="%d" data-product-name="%s"><span class="dashicons dashicons-trash"></span> %s</a>',
                $item->ID,
                esc_attr($item->post_title),
                esc_html__('Delete AI desc', 'comet-ai-says')
            );
        }

        return '<div class="action-buttons">' . implode(' ', $actions) . '</div>';
    }

    public function filter_where_no_full_desc(string $where): string
    {
        global $wpdb;
        $where .= " AND ({$wpdb->posts}.post_content IS NULL OR {$wpdb->posts}.post_content = '')";
        return $where;
    }

    public function filter_where_no_short_desc(string $where): string
    {
        global $wpdb;
        $where .= " AND ({$wpdb->posts}.post_excerpt IS NULL OR {$wpdb->posts}.post_excerpt = '')";
        return $where;
    }

    public function filter_where_both_missing(string $where): string
    {
        global $wpdb;
        $where .= " AND ({$wpdb->posts}.post_excerpt IS NULL OR {$wpdb->posts}.post_excerpt = '')";
        $where .= " AND ({$wpdb->posts}.post_content IS NULL OR {$wpdb->posts}.post_content = '')";
        return $where;
    }

    public function prepare_items(): void
    {
        $this->_column_headers = [$this->get_columns(), [], $this->get_sortable_columns()];

        // phpcs:disable WordPress.Security.NonceVerification.Recommended
        $args = [
            'post_type'      => 'product',
            'post_status'    => isset($_GET['status']) ? sanitize_text_field(wp_unslash($_GET['status'])) : 'publish',
            'posts_per_page' => $this->per_page,
            'paged'          => $this->get_pagenum(),
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
                    'field'    => 'term_id',
                    'terms'    => intval($_GET['category']),
                ],
            ];
        }

        // Description status filter
        $desc_status = !empty($_GET['desc_status']) ? sanitize_text_field(wp_unslash($_GET['desc_status'])) : '';

        if ('no_ai' === $desc_status || 'no_short' === $desc_status) {
            $args['meta_query'] = [
                'relation' => 'OR',
                [
                    'key'     => '_wpcmt_aisays_description',
                    'compare' => 'NOT EXISTS',
                ],
                [
                    'key'     => '_wpcmt_aisays_description',
                    'value'   => '',
                    'compare' => '=',
                ],
            ];
        } elseif ('has_ai' === $desc_status) {
            $args['meta_query'] = [
                [
                    'key'     => '_wpcmt_aisays_description',
                    'value'   => '',
                    'compare' => '!=',
                ],
            ];
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
            $args['order'] = (!empty($_GET['order']) && 'desc' === strtolower($_GET['order'])) ? 'DESC' : 'ASC';
        }
        // phpcs:enable WordPress.Security.NonceVerification.Recommended

        // Hook query filters for content/excerpt checks
        if ('no_full' === $desc_status) {
            add_filter('posts_where', [$this, 'filter_where_no_full_desc']);
        } elseif ('no_short_desc' === $desc_status) {
            add_filter('posts_where', [$this, 'filter_where_no_short_desc']);
        } elseif ('both_missing' === $desc_status) {
            add_filter('posts_where', [$this, 'filter_where_both_missing']);
        }

        $query = new \WP_Query($args);

        // Remove filters immediately after query
        remove_filter('posts_where', [$this, 'filter_where_no_full_desc']);
        remove_filter('posts_where', [$this, 'filter_where_no_short_desc']);
        remove_filter('posts_where', [$this, 'filter_where_both_missing']);

        $this->items = $query->posts;

        $this->set_pagination_args([
            'total_items' => $query->found_posts,
            'per_page'    => $this->per_page,
            'total_pages' => ceil($query->found_posts / $this->per_page),
        ]);
    }

    public function search_box($text, $input_id): void
    {
        parent::search_box($text, $input_id);
    }

    public function extra_tablenav($which): void
    {
        if ('top' !== $which) {
            return;
        }

        $categories = get_terms(['taxonomy' => 'product_cat', 'hide_empty' => false]);
        if (is_wp_error($categories)) {
            $categories = [];
        }

        // phpcs:disable WordPress.Security.NonceVerification.Recommended
        $current_category    = isset($_GET['category']) ? intval($_GET['category']) : '';
        $current_status      = isset($_GET['status']) ? sanitize_text_field(wp_unslash($_GET['status'])) : '';
        $current_desc_status = isset($_GET['desc_status']) ? sanitize_text_field(wp_unslash($_GET['desc_status'])) : '';
        $current_sort        = isset($_GET['orderby']) ? sanitize_text_field(wp_unslash($_GET['orderby'])) : '';
        $current_search      = isset($_GET['s']) ? esc_attr(sanitize_text_field(wp_unslash($_GET['s']))) : '';
        // phpcs:enable WordPress.Security.NonceVerification.Recommended
        ?>
        <div class="alignleft actions">
            <!-- Search -->
            <input type="text" name="s"
                value="<?php echo esc_attr($current_search); ?>"
                placeholder="<?php esc_attr_e('Search name or SKU', 'comet-ai-says'); ?>" />

            <!-- Status -->
            <select name="status">
                <option value=""><?php esc_html_e('All Statuses', 'comet-ai-says'); ?></option>
                <option value="publish" <?php selected($current_status, 'publish'); ?>><?php esc_html_e('Published', 'comet-ai-says'); ?></option>
                <option value="draft" <?php selected($current_status, 'draft'); ?>><?php esc_html_e('Draft', 'comet-ai-says'); ?></option>
                <option value="pending" <?php selected($current_status, 'pending'); ?>><?php esc_html_e('Pending', 'comet-ai-says'); ?></option>
                <option value="any" <?php selected($current_status, 'any'); ?>><?php esc_html_e('Any Status', 'comet-ai-says'); ?></option>
            </select>

            <!-- Category -->
            <select name="category">
                <option value=""><?php esc_html_e('All Categories', 'comet-ai-says'); ?></option>
                <?php foreach ($categories as $category): ?>
                    <option value="<?php echo esc_attr($category->term_id); ?>" <?php selected($current_category, $category->term_id); ?>>
                        <?php echo esc_html($category->name); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <!-- Description Status -->
            <select name="desc_status">
                <option value=""><?php esc_html_e('All Products', 'comet-ai-says'); ?></option>
                <option value="no_ai" <?php selected($current_desc_status === 'no_ai' || $current_desc_status === 'no_short', true); ?>>
                    <?php esc_html_e('No AI Description', 'comet-ai-says'); ?>
                </option>
                <option value="has_ai" <?php selected($current_desc_status, 'has_ai'); ?>>
                    <?php esc_html_e('Has AI Description', 'comet-ai-says'); ?>
                </option>
                <option value="no_short_desc" <?php selected($current_desc_status, 'no_short_desc'); ?>>
                    <?php esc_html_e('No Short Description', 'comet-ai-says'); ?>
                </option>
                <option value="no_full" <?php selected($current_desc_status, 'no_full'); ?>>
                    <?php esc_html_e('No Full Description', 'comet-ai-says'); ?>
                </option>
                <option value="both_missing" <?php selected($current_desc_status, 'both_missing'); ?>>
                    <?php esc_html_e('Both Missing (Short & Full)', 'comet-ai-says'); ?>
                </option>
            </select>

            <!-- Sort -->
            <select name="orderby">
                <option value="product" <?php selected($current_sort, 'product'); ?>><?php esc_html_e('Sort by Name', 'comet-ai-says'); ?></option>
                <option value="date" <?php selected($current_sort, 'date'); ?>><?php esc_html_e('Sort by Date', 'comet-ai-says'); ?></option>
            </select>

            <?php submit_button(esc_html__('Filter', 'comet-ai-says'), '', 'filter_action', false); ?>

            <!-- Reset button -->
            <a href="<?php echo esc_url(admin_url('edit.php?post_type=product&page=wpcmt-aisays-table')); ?>" class="button">
                <?php esc_html_e('Reset', 'comet-ai-says'); ?>
            </a>
        </div>
        <?php
    }

    public function display(): void
    {
        echo '<form method="post" id="wpcmt-aisays-bulk-form">';
        wp_nonce_field('bulk-products', '_wpnonce', false);
        echo '<input type="hidden" name="page" value="wpcmt-aisays-table">';

        parent::display();

        echo '</form>';
    }
}

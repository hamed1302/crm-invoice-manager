<?php
// Security Check
if ( ! defined( 'WPINC' ) ) die;

/**
 * Registers all custom post types and taxonomies for the plugin.
 */
function cim_register_cpts_and_taxonomies() {

    // --- CPT for Clients (مشتریان) ---
    $client_labels = [
        'name'          => 'مشتریان',
        'singular_name' => 'مشتری',
        'add_new'       => 'افزودن مشتری',
        'add_new_item'  => 'افزودن مشتری جدید',
        'edit_item'     => 'ویرایش مشتری',
        'all_items'     => 'همه مشتریان',
    ];
    $client_args = [
        'labels'        => $client_labels,
        'public'        => true,
        'has_archive'   => false,
        'show_in_menu'  => 'edit.php?post_type=invoice',
        'supports'      => ['title'],
        'rewrite'       => ['slug' => 'clients'],
    ];
    register_post_type('client', $client_args);

    // --- CPT for Invoices (فاکتورها) ---
    $invoice_labels = [
        'name'          => 'فاکتورها',
        'singular_name' => 'فاکتور',
        'add_new'       => 'افزودن فاکتور',
        'add_new_item'  => 'افزودن فاکتور جدید',
        'edit_item'     => 'ویرایش فاکتور',
        'all_items'     => 'همه فاکتورها',
    ];
    $invoice_args = [
        'labels'        => $invoice_labels,
        'public'        => true,
        'has_archive'   => false,
        'menu_icon'     => 'dashicons-text-page',
        'supports'      => ['title'],
        'rewrite'       => ['slug' => 'invoices'],
    ];
    register_post_type('invoice', $invoice_args);
    
    // --- Custom Taxonomy for Invoice Status (وضعیت فاکتور) ---
    $status_labels = [
        'name'          => 'وضعیت‌های فاکتور',
        'singular_name' => 'وضعیت',
        'all_items'     => 'همه وضعیت‌ها',
        'edit_item'     => 'ویرایش وضعیت',
        'update_item'   => 'بروزرسانی وضعیت',
        'add_new_item'  => 'افزودن وضعیت جدید',
        'new_item_name' => 'نام وضعیت جدید',
        'menu_name'     => 'وضعیت‌ها',
    ];
    $status_args = [
        'hierarchical'      => false,
        'labels'            => $status_labels,
        'show_ui'           => true,
        'show_admin_column' => true,
        'query_var'         => true,
        'rewrite'           => ['slug' => 'invoice-status'],
    ];
    register_taxonomy('invoice_status', 'invoice', $status_args);
}
add_action('init', 'cim_register_cpts_and_taxonomies');

/**
 * Creates default invoice statuses on plugin activation.
 */
function cim_create_default_invoice_statuses() {
    $statuses = [
        'صادر شده'      => 'issued',
        'پرداخت شده'    => 'paid',
        'در انتظار پرداخت' => 'pending',
        'لغو شده'       => 'cancelled',
    ];

    foreach ($statuses as $name => $slug) {
        if ( ! term_exists( $name, 'invoice_status' ) ) {
            wp_insert_term($name, 'invoice_status', ['slug' => $slug]);
        }
    }
}

/**
 * Adds meta boxes for Client details.
 */
function cim_add_client_meta_boxes() {
    add_meta_box('cim_client_details', 'اطلاعات خریدار', 'cim_render_client_details_meta_box', 'client', 'normal', 'high');
}
add_action('add_meta_boxes', 'cim_add_client_meta_boxes');

/**
 * Renders the meta box for Client details.
 */
function cim_render_client_details_meta_box($post) {
    wp_nonce_field('cim_save_client_meta_data', 'cim_client_nonce');
    $client_phone = get_post_meta($post->ID, '_client_phone', true);
    $client_address = get_post_meta($post->ID, '_client_address', true);
    ?>
    <p>
        <label for="client_phone"><strong>تلفن خریدار:</strong></label><br>
        <input type="text" id="client_phone" name="client_phone" value="<?php echo esc_attr($client_phone); ?>" style="width:100%;">
    </p>
    <p>
        <label for="client_address"><strong>آدرس خریدار:</strong></label><br>
        <textarea id="client_address" name="client_address" rows="3" style="width:100%;"><?php echo esc_textarea($client_address); ?></textarea>
    </p>
    <?php
}

/**
 * Saves the Client meta data.
 */
function cim_save_client_meta_data($post_id) {
    if (!isset($_POST['cim_client_nonce']) || !wp_verify_nonce($_POST['cim_client_nonce'], 'cim_save_client_meta_data')) return;
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_post', $post_id)) return;
    if ('client' !== get_post_type($post_id)) return;

    if (isset($_POST['client_phone'])) {
        update_post_meta($post_id, '_client_phone', sanitize_text_field($_POST['client_phone']));
    }
    if (isset($_POST['client_address'])) {
        update_post_meta($post_id, '_client_address', sanitize_textarea_field($_POST['client_address']));
    }
}
add_action('save_post', 'cim_save_client_meta_data');


// --- Add custom columns to the invoice list ---
function cim_add_invoice_columns($columns) {
    $new_columns = [];
    foreach ($columns as $key => $title) {
        if ($key == 'date') {
            $new_columns['client'] = 'مشتری';
            // Use the original taxonomy key for sorting to work easily
            $new_columns['taxonomy-invoice_status'] = 'وضعیت فاکتور';
        }
        $new_columns[$key] = $title;
    }
    // Fallback in case 'date' column is not standard
    if (!isset($new_columns['taxonomy-invoice_status'])) {
         $new_columns['taxonomy-invoice_status'] = 'وضعیت فاکتور';
    }
    if (!isset($new_columns['client'])) {
         $new_columns['client'] = 'مشتری';
    }
    
    return $new_columns;
}
add_filter('manage_invoice_posts_columns', 'cim_add_invoice_columns');

// --- Populate custom columns ---
function cim_populate_invoice_columns($column, $post_id) {
    if ($column === 'client') {
        $client_id = get_post_meta($post_id, '_cim_client_id', true);
        if ($client_id) {
            echo '<a href="' . get_edit_post_link($client_id) . '">' . esc_html(get_the_title($client_id)) . '</a>';
        } else {
            echo '—';
        }
    }
    // Note: The 'taxonomy-invoice_status' column is populated automatically by WordPress.
}
add_action('manage_invoice_posts_custom_column', 'cim_populate_invoice_columns', 10, 2);


// --- NEW: Make custom columns sortable ---
function cim_make_invoice_columns_sortable($columns) {
    $columns['client'] = 'client';
    // Use the original taxonomy key for sorting
    $columns['taxonomy-invoice_status'] = 'taxonomy-invoice_status';
    return $columns;
}
add_filter('manage_edit-invoice_sortable_columns', 'cim_make_invoice_columns_sortable');

// --- NEW: Handle the custom sorting logic ---
function cim_handle_invoice_custom_sorting($query) {
    if (!is_admin() || !$query->is_main_query()) {
        return;
    }

    $orderby = $query->get('orderby');

    if ($orderby === 'client') {
        $query->set('meta_key', '_cim_client_name');
        $query->set('orderby', 'meta_value');
    }
}
add_action('pre_get_posts', 'cim_handle_invoice_custom_sorting');


// --- Add a dropdown to filter invoices by client ---
function cim_add_client_filter_to_invoices() {
    global $typenow;
    if ($typenow == 'invoice') {
        $clients = get_posts(['post_type' => 'client', 'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC']);
        $current_client = isset($_GET['client_filter']) ? $_GET['client_filter'] : '';
        
        echo '<select name="client_filter" id="client_filter">';
        echo '<option value="">همه مشتریان</option>';
        foreach ($clients as $client) {
            printf(
                '<option value="%s"%s>%s</option>',
                esc_attr($client->ID),
                selected($current_client, $client->ID, false),
                esc_html($client->post_title)
            );
        }
        echo '</select>';
    }
}
add_action('restrict_manage_posts', 'cim_add_client_filter_to_invoices');

// --- Process the client filter ---
function cim_process_client_filter($query) {
    global $pagenow;
    if (is_admin() && $pagenow == 'edit.php' && isset($_GET['post_type']) && $_GET['post_type'] == 'invoice' && isset($_GET['client_filter']) && !empty($_GET['client_filter'])) {
        $query->set('meta_key', '_cim_client_id');
        $query->set('meta_value', sanitize_text_field($_GET['client_filter']));
    }
}
add_filter('parse_query', 'cim_process_client_filter');

/**
 * Helper function to get the next available invoice number.
 */
function cim_get_next_invoice_number() {
    $last_number = (int) get_option('cim_invoice_last_number', 0);
    $start_number = (int) get_option('cim_invoice_start_number', 1000);
    
    if ($last_number < $start_number) {
        return $start_number;
    }
    
    return $last_number + 1;
}

/**
 * Pre-fills the invoice number field when creating a new invoice.
 */
function cim_prefill_invoice_number() {
    $screen = get_current_screen();
    if ( $screen && $screen->base === 'post' && $screen->id === 'invoice' && $screen->action === 'add' ) {
        $next_invoice_number = cim_get_next_invoice_number();
        $GLOBALS['new_invoice_number'] = $next_invoice_number;
    }
}
add_action('current_screen', 'cim_prefill_invoice_number');
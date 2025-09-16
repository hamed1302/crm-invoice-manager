<?php
// Security Check
if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Add custom rewrite rules to WordPress.
 */
function cim_add_rewrite_rules() {
    add_rewrite_rule('^invoice-view/([^/]*)/?', 'index.php?is_invoice_view=1&invoice_hash=$matches[1]', 'top');
    add_rewrite_rule('^invoice-proxy/([^/]*)/?', 'index.php?is_invoice_proxy=1&invoice_hash=$matches[1]', 'top');
}
add_action('init', 'cim_add_rewrite_rules');

/**
 * Add custom query variables to WordPress.
 */
function cim_add_query_vars($vars) {
    $vars[] = 'is_invoice_view';
    $vars[] = 'is_invoice_proxy';
    $vars[] = 'invoice_hash';
    return $vars;
}
add_filter('query_vars', 'cim_add_query_vars');

/**
 * Main handler that directs traffic for our custom URLs.
 */
function cim_template_redirect_handler() {
    if (get_query_var('is_invoice_proxy')) {
        cim_handle_pdf_proxy_view();
    }
    
    if (get_query_var('is_invoice_view')) {
        cim_handle_landing_page_view();
    }
}
add_action('template_redirect', 'cim_template_redirect_handler');

/**
 * Handles the landing page view, logs the visit, and passes data to the template.
 */
function cim_handle_landing_page_view() {
    header('X-Robots-Tag: noindex, nofollow');

    $hash = get_query_var('invoice_hash');
    $invoice = cim_get_invoice_by_hash($hash);

    if ($invoice) {
        // Log the visit
        $visits = get_post_meta($invoice->ID, '_cim_visits_log', true) ?: [];
        $visits[] = ['ip' => $_SERVER['REMOTE_ADDR'], 'time' => current_time('mysql')];
        update_post_meta($invoice->ID, '_cim_visits_log', $visits);

        // Prepare data for the template
        global $invoice_data;
        $client_id = get_post_meta($invoice->ID, '_cim_client_id', true);
        $tax_rate = get_post_meta($invoice->ID, '_cim_tax_rate', true);
        
        // Fallback logic for contract terms: if invoice-specific terms are empty, use the default from settings.
        $contract_terms = get_post_meta($invoice->ID, '_cim_contract_terms', true);
        if (empty($contract_terms)) {
            $contract_terms = get_option('cim_contract_terms');
        }

        // Fallback logic for footer content
        $footer_content = get_post_meta($invoice->ID, '_cim_footer_content', true);
         if (empty($footer_content)) {
            $footer_content = get_option('cim_footer_content');
        }
        
        // Get PDF upload info
        $uploaded_pdf_id = get_post_meta($invoice->ID, '_cim_uploaded_pdf_id', true);

        $invoice_data = [
            'invoice_type'    => get_post_meta($invoice->ID, '_cim_invoice_type', true),
            'invoice_number'  => get_post_meta($invoice->ID, '_cim_invoice_number', true),
            'issue_date'      => get_post_meta($invoice->ID, '_cim_issue_date', true),
            
            'seller_name'     => get_option('cim_seller_name'),
            'seller_address'  => get_option('cim_seller_address'),
            'seller_phone'    => get_option('cim_seller_phone'),
            
            'client_name'     => get_the_title($client_id),
            'client_address'  => get_post_meta($client_id, '_client_address', true),
            'client_phone'    => get_post_meta($client_id, '_client_phone', true),
            
            'line_items'      => get_post_meta($invoice->ID, '_cim_line_items', true),
            'share_url'       => home_url('/invoice-view/' . $hash),
            'discount_amount' => get_post_meta($invoice->ID, '_cim_discount_amount', true),
            'tax_rate'        => ($tax_rate !== '') ? $tax_rate : get_option('cim_default_tax_rate', '9'),
            
            // Corrected & Restored Data
            'entry_method'    => get_post_meta($invoice->ID, '_cim_entry_method', true) ?: 'manual',
            'uploaded_pdf_url'  => $uploaded_pdf_id ? wp_get_attachment_url($uploaded_pdf_id) : null,
            
            // New Custom Fields
            'payment_method'  => get_post_meta($invoice->ID, '_cim_payment_method', true),
            'dimensions_text' => get_post_meta($invoice->ID, '_cim_dimensions_text', true),
            'promotions'      => get_post_meta($invoice->ID, '_cim_promotions', true),
            'contract_terms'  => $contract_terms,
            'footer_content'  => $footer_content,
            
            // Added for logo carousel
            'client_logos'    => get_option('cim_client_logos'),
        ];

        // Load the template file
        $template = CIM_PLUGIN_DIR . 'templates/invoice-landing-page.php';
        if (file_exists($template)) {
            include $template;
            exit;
        }
    } else {
        wp_redirect(home_url());
        exit;
    }
}

/**
 * Securely serves the uploaded PDF file.
 */
function cim_handle_pdf_proxy_view() {
    header('X-Robots-Tag: noindex, nofollow');
    $hash = get_query_var('invoice_hash');
    $invoice = cim_get_invoice_by_hash($hash);
    if ($invoice) {
        $pdf_id = get_post_meta($invoice->ID, '_cim_uploaded_pdf_id', true);
        if (!$pdf_id) { status_header(404); die('File ID not found.'); }
        $file_path = get_attached_file($pdf_id);
        if (file_exists($file_path)) {
            while (ob_get_level()) { ob_end_clean(); }
            header("Access-Control-Allow-Origin: *");
            header('Content-Type: application/pdf');
            header('Content-Disposition: inline; filename="' . basename($file_path) . '"');
            header('Content-Length: ' . filesize($file_path));
            readfile($file_path);
            die();
        } else { status_header(404); die('File not found on server.'); }
    } else { status_header(403); die('Invalid invoice link.'); }
}

/**
 * Helper function to get an invoice post by its secure hash.
 */
function cim_get_invoice_by_hash($hash) {
    if (empty($hash)) return null;
    $args = [
        'post_type'      => 'invoice',
        'meta_key'       => '_cim_secure_hash',
        'meta_value'     => $hash,
        'posts_per_page' => 1,
        'post_status'    => 'publish',
    ];
    $invoices = get_posts($args);
    return $invoices ? $invoices[0] : null;
}
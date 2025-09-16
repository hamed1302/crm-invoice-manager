<?php
// Security Check
if ( ! defined( 'WPINC' ) ) die;

/**
 * Adds all meta boxes for the plugin.
 */
function cim_add_meta_boxes() {
    add_meta_box('cim_invoice_builder_meta_box', '۱. اطلاعات و اقلام فاکتور', 'cim_render_invoice_builder_meta_box', 'invoice', 'normal', 'high');
    add_meta_box('cim_invoice_details_meta_box', '۲. جزئیات سفارشی (اختیاری)', 'cim_render_invoice_details_meta_box', 'invoice', 'normal', 'default');
    add_meta_box('cim_invoice_content_meta_box', '۳. شرایط پرداخت و قرارداد', 'cim_render_invoice_content_meta_box', 'invoice', 'normal', 'default');
    add_meta_box('cim_invoice_tracking_meta_box', 'لینک امن و آمار بازدید', 'cim_render_invoice_tracking_meta_box', 'invoice', 'side', 'default');
    add_meta_box('cim_client_invoices_meta_box', 'فاکتورهای ثبت شده برای این مشتری', 'cim_render_client_invoices_meta_box', 'client', 'normal', 'default');
}
add_action('add_meta_boxes', 'cim_add_meta_boxes');

/**
 * Renders Meta Box 1: Main info, entry method, and line items.
 */
function cim_render_invoice_builder_meta_box($post) {
    wp_nonce_field('cim_save_invoice_meta_data', 'cim_invoice_nonce');

    // Get all saved data
    $client_id = get_post_meta($post->ID, '_cim_client_id', true);
    $invoice_type = get_post_meta($post->ID, '_cim_invoice_type', true);
    $invoice_number = get_post_meta($post->ID, '_cim_invoice_number', true);
    // Pre-fill invoice number if it's a new post
    if (empty($invoice_number) && isset($GLOBALS['new_invoice_number'])) {
        $invoice_number = $GLOBALS['new_invoice_number'];
    }
    $issue_date = get_post_meta($post->ID, '_cim_issue_date', true);
    $line_items = get_post_meta($post->ID, '_cim_line_items', true);
    $discount_amount = get_post_meta($post->ID, '_cim_discount_amount', true);
    $tax_rate = get_post_meta($post->ID, '_cim_tax_rate', true);
    if ($tax_rate === '') { $tax_rate = get_option('cim_default_tax_rate', '9'); }

    $entry_method = get_post_meta($post->ID, '_cim_entry_method', true) ?: 'manual';
    $uploaded_pdf_id = get_post_meta($post->ID, '_cim_uploaded_pdf_id', true);
    $uploaded_pdf_url = $uploaded_pdf_id ? wp_get_attachment_url($uploaded_pdf_id) : '';
    ?>
    <style>
        .cim-form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; }
        .cim-table-wrapper, .cim-upload-wrapper { margin-top: 20px; border-top: 1px solid #ddd; padding-top: 20px; }
        .cim-line-items, .cim-dimensions-table { width: 100%; border-collapse: collapse; }
        .cim-line-items th, .cim-line-items td, .cim-dimensions-table td { border: 1px solid #ddd; padding: 8px; text-align: right; }
        .cim-line-items input, .cim-dimensions-table input { width: 100%; box-sizing: border-box; }
        .row-remover { cursor: pointer; color: red; font-weight: bold; text-align: center; }
        .cim-totals-row { display: grid; grid-template-columns: 1fr auto auto; gap: 20px; margin-top: 20px; align-items: center; }
        .cim-totals-field label { font-weight: bold; }
        #live-total-display { background-color: #e9ecef; padding: 10px 15px; border-radius: 4px; font-size: 1.2em; font-weight: bold; color: #198754; text-align: center; }
    </style>

    <div class="cim-form-grid">
        <p><label><strong>مشتری:</strong><select name="cim_client_id" style="width:100%;"><?php $clients = get_posts(['post_type' => 'client', 'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC']); foreach ($clients as $client) { echo '<option value="' . esc_attr($client->ID) . '"' . selected($client_id, $client->ID, false) . '>' . esc_html($client->post_title) . '</option>'; } ?></select></label></p>
        <p><label><strong>نوع سند:</strong><select name="cim_invoice_type" style="width:100%;"><option value="invoice" <?php selected($invoice_type, 'invoice'); ?>>فاکتور</option><option value="proforma" <?php selected($invoice_type, 'proforma'); ?>>پیش فاکتور</option></select></label></p>
        <p><label><strong>شماره:</strong><input type="text" name="cim_invoice_number" value="<?php echo esc_attr($invoice_number); ?>" style="width:100%;"></label></p>
        <p><label><strong>تاریخ:</strong><input type="text" name="cim_issue_date" value="<?php echo esc_attr($issue_date); ?>" style="width:100%;" placeholder="مثال: ۱۴۰۴/۰۵/۳۰"></label></p>
    </div>

    <div style="margin-top: 20px; padding: 10px; background-color: #f5f5f5; border: 1px solid #ddd;">
        <strong>روش ورود اطلاعات:</strong>
        <label style="margin: 0 15px;"><input type="radio" name="cim_entry_method" value="manual" <?php checked($entry_method, 'manual'); ?>> ورود دستی اقلام</label>
        <label><input type="radio" name="cim_entry_method" value="upload" <?php checked($entry_method, 'upload'); ?>> آپلود فایل PDF آماده</label>
    </div>

    <div id="cim-manual-entry-container">
        <div class="cim-table-wrapper">
            <h4>اقلام فاکتور</h4>
            <table id="cim-line-items-table" class="cim-line-items">
                <thead><tr><th style="width:5%;">ردیف</th><th>شرح کالا / خدمات</th><th style="width:10%;">تعداد</th><th style="width:15%;">مبلغ واحد</th><th style="width:15%;">مبلغ کل</th><th style="width:5%;">حذف</th></tr></thead>
                <tbody>
                <?php if (!empty($line_items) && is_array($line_items)) { foreach ($line_items as $i => $item) {
                    $qty = $item['qty'] ?? 1;
                    $price = $item['price'] ?? 0;
                    echo '<tr>
                        <td>'.($i+1).'</td>
                        <td><input type="text" name="line_items['.$i.'][desc]" value="'.esc_attr($item['desc'] ?? '').'"></td>
                        <td><input type="text" class="cim-format-number item-qty" value="'.esc_attr(number_format((float)$qty)).'"><input type="hidden" name="line_items['.$i.'][qty]" value="'.esc_attr($qty).'"></td>
                        <td><input type="text" class="cim-format-number item-price" value="'.esc_attr(number_format((float)$price)).'"><input type="hidden" name="line_items['.$i.'][price]" value="'.esc_attr($price).'"></td>
                        <td><input type="text" class="item-total" value="'.esc_attr(number_format((float)($item['total'] ?? 0))).'" readonly><input type="hidden" class="item-total-raw" name="line_items['.$i.'][total]" value="'.esc_attr($item['total'] ?? 0).'"></td>
                        <td class="row-remover">X</td>
                    </tr>';
                } } ?>
                </tbody>
            </table>
            <button type="button" class="button" id="add-line-item">افزودن ردیف جدید</button>
            <div class="cim-totals-row">
                 <div id="live-total-display">مبلغ نهایی: ۰ ریال</div>
                <div class="cim-totals-field">
                    <label>تخفیف (ریال):
                        <input type="text" class="cim-format-number" id="cim_discount_amount_formatted" value="<?php echo esc_attr(number_format((float)$discount_amount)); ?>">
                        <input type="hidden" name="cim_discount_amount" id="cim_discount_amount" value="<?php echo esc_attr($discount_amount); ?>">
                    </label>
                </div>
                <div class="cim-totals-field">
                    <label>مالیات (%): <input type="number" step="0.01" id="cim_tax_rate" name="cim_tax_rate" value="<?php echo esc_attr($tax_rate); ?>"></label>
                </div>
            </div>
        </div>
    </div>

    <div id="cim-pdf-upload-container" class="cim-upload-wrapper">
        <h4>آپلود فایل PDF</h4>
        <button type="button" class="button" id="upload-pdf-button">انتخاب یا آپلود فایل PDF</button>
        <input type="hidden" name="cim_uploaded_pdf_id" id="cim_uploaded_pdf_id" value="<?php echo esc_attr($uploaded_pdf_id); ?>">
        <div id="pdf-upload-preview">
            <?php if ($uploaded_pdf_url): ?><a href="<?php echo esc_url($uploaded_pdf_url); ?>" target="_blank">مشاهده فایل آپلود شده</a><?php endif; ?>
        </div>
    </div>

    <script>
    jQuery(document).ready(function($) {
        function formatNumber(n) { return n.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ","); }

        function calculateLiveTotal() {
            var subtotal = 0;
            $('#cim-line-items-table tbody .item-total-raw').each(function() { subtotal += parseFloat($(this).val()) || 0; });
            var discount = parseFloat($('#cim_discount_amount').val()) || 0;
            var taxRate = parseFloat($('#cim_tax_rate').val()) || 0;
            var subtotalAfterDiscount = subtotal - discount;
            var taxAmount = (taxRate > 0) ? (subtotalAfterDiscount * taxRate) / 100 : 0;
            var finalTotal = subtotalAfterDiscount + taxAmount;
            $('#live-total-display').text('مبلغ نهایی: ' + formatNumber(Math.round(finalTotal)) + ' ریال');
        }

        function attachFormatter(selector) {
            $(document).on('input', selector, function() {
                var $this = $(this);
                var raw_value = $this.val().replace(/[^0-9]/g, '');
                $this.val(formatNumber(raw_value));
                $this.siblings('input[type="hidden"]').val(raw_value);

                if ($this.hasClass('item-qty') || $this.hasClass('item-price')) {
                    var row = $this.closest('tr');
                    var qty = parseFloat(row.find('.item-qty').siblings('input[type="hidden"]').val()) || 0;
                    var price = parseFloat(row.find('.item-price').siblings('input[type="hidden"]').val()) || 0;
                    var total = qty * price;
                    row.find('.item-total').val(formatNumber(total));
                    row.find('.item-total-raw').val(total);
                }
                calculateLiveTotal();
            });
        }
        
        attachFormatter('.cim-format-number');
        attachFormatter('#cim-line-items-table .item-qty');
        attachFormatter('#cim-line-items-table .item-price');
        
        function toggleEntryMethodView() {
            var method = $('input[name="cim_entry_method"]:checked').val();
            if (method === 'upload') { $('#cim-manual-entry-container, #cim_invoice_details_meta_box, #cim_invoice_content_meta_box').hide(); $('#cim-pdf-upload-container').show(); } 
            else { $('#cim-manual-entry-container, #cim_invoice_details_meta_box, #cim_invoice_content_meta_box').show(); $('#cim-pdf-upload-container').hide(); }
        }

        toggleEntryMethodView();
        calculateLiveTotal();
        $('input[name="cim_entry_method"]').change(toggleEntryMethodView);
        $('#cim_tax_rate').on('input change', calculateLiveTotal);
        
        $('#upload-pdf-button').click(function(e) { e.preventDefault(); var uploader = wp.media({ title: 'انتخاب PDF', library: { type: 'application/pdf' }, multiple: false }).on('select', function() { var attachment = uploader.state().get('selection').first().toJSON(); $('#cim_uploaded_pdf_id').val(attachment.id); $('#pdf-upload-preview').html('<a href="' + attachment.url + '" target="_blank">مشاهده فایل آپلود شده</a>'); }).open(); });
        
        $('#add-line-item').click(function() { 
            var rc = $('#cim-line-items-table tbody tr').length; 
            $('#cim-line-items-table tbody').append(`<tr><td>${rc+1}</td><td><input type="text" name="line_items[${rc}][desc]"></td><td><input type="text" class="cim-format-number item-qty" value="1"><input type="hidden" name="line_items[${rc}][qty]" value="1"></td><td><input type="text" class="cim-format-number item-price" value="0"><input type="hidden" name="line_items[${rc}][price]" value="0"></td><td><input type="text" class="item-total" value="0" readonly><input type="hidden" class="item-total-raw" name="line_items[${rc}][total]" value="0"></td><td class="row-remover">X</td></tr>`); 
        });
        
        $('#cim-line-items-table').on('click', '.row-remover', function() { $(this).closest('tr').remove(); $('#cim-line-items-table tbody tr').each(function(i) { $(this).find('td:first').text(i + 1); }); calculateLiveTotal(); });
    });
    </script>
    <?php
}

/**
 * Renders Meta Box 2: Custom Details (Dimensions and Promotions) in two columns.
 */
function cim_render_invoice_details_meta_box($post) {
    $dimensions = get_post_meta($post->ID, '_cim_dimensions_text', true);
    $promotions = get_post_meta($post->ID, '_cim_promotions', true);
    ?>
    <style> .details-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 30px; } </style>
    <div class="details-grid">
        <div class="dimensions-column">
            <h4>جدول توضیحات ابعاد</h4>
            <table id="cim-dimensions-table" class="cim-dimensions-table">
                <tbody>
                <?php if (!empty($dimensions) && is_array($dimensions)) { foreach ($dimensions as $i => $dim_text) { echo '<tr><td><input type="text" name="dimensions_text[]" value="'.esc_attr($dim_text).'"></td><td style="width:5%;" class="row-remover">X</td></tr>'; } } ?>
                </tbody>
            </table>
            <button type="button" class="button" id="add-dimension-item">افزودن ردیف</button>
            <p class="description">مثال: ارتفاع :2.60+0.2 | عرض : 2.60</p>
        </div>
        <div class="promotions-column">
            <h4>متن پیشنهادات و پروموشن‌ها</h4>
            <textarea name="cim_promotions" rows="8" style="width:100%;"><?php echo esc_textarea($promotions); ?></textarea>
            <p class="description">هر پیشنهاد را در یک خط جدید وارد کنید.</p>
        </div>
    </div>
    <script>
    jQuery(document).ready(function($) {
        $('#add-dimension-item').click(function() { $('#cim-dimensions-table tbody').append(`<tr><td><input type="text" name="dimensions_text[]" value=""></td><td style="width:5%;" class="row-remover">X</td></tr>`); });
        $('#cim-dimensions-table').on('click', '.row-remover', function() { $(this).closest('tr').remove(); });
    });
    </script>
    <?php
}

/**
 * Renders Meta Box 3: Payment and Contract Terms.
 */
function cim_render_invoice_content_meta_box($post) {
    $payment_method = get_post_meta($post->ID, '_cim_payment_method', true) ?: 'cash';
    $terms = get_post_meta($post->ID, '_cim_contract_terms', true);
    if (empty($terms) && $post->post_status == 'auto-draft') { $terms = get_option('cim_contract_terms'); }
    ?>
    <p><strong>شرایط پرداخت:</strong>
        <label style="margin-left: 15px;"><input type="radio" name="cim_payment_method" value="cash" <?php checked($payment_method, 'cash'); ?>> نقدی</label>
        <label><input type="radio" name="cim_payment_method" value="installments" <?php checked($payment_method, 'installments'); ?>> اقساطی</label>
    </p>
    <hr>
    <p><label for="cim_contract_terms"><strong>متن شرایط قرارداد:</strong></label></p>
    <textarea id="cim_contract_terms" name="cim_contract_terms" rows="15" style="width:100%;"><?php echo esc_textarea($terms); ?></textarea>
    <?php
}

/**
 * Renders the meta box for the secure link and tracking info.
 */
function cim_render_invoice_tracking_meta_box($post) {
    $secure_hash = get_post_meta($post->ID, '_cim_secure_hash', true);
    if ($secure_hash) {
        $secure_link = home_url('/invoice-view/' . $secure_hash);
        echo '<p><strong>لینک امن:</strong></p>';
        echo '<input type="text" value="' . esc_url($secure_link) . '" style="width: 100%; text-align: left; direction: ltr;" readonly onfocus="this.select();">';
        $visits = get_post_meta($post->ID, '_cim_visits_log', true);
        echo '<h4>بازدیدها:</h4>';
        if (!empty($visits) && is_array($visits)) {
            echo '<ul style="max-height: 200px; overflow-y: auto; background: #f9f9f9; border: 1px solid #ddd; padding: 10px; margin:0;">';
            foreach (array_reverse($visits) as $visit) {
                echo '<li><strong>IP:</strong> ' . esc_html($visit['ip'] ?? 'N/A') . '<br><strong>زمان:</strong> ' . esc_html( wp_date('Y/m/d H:i:s', strtotime($visit['time'] ?? 'now'))) . '</li>';
            }
            echo '</ul>';
        } else { echo '<p><em>هنوز بازدیدی ثبت نشده است.</em></p>'; }
    } else { echo '<p><em>لینک امن پس از ذخیره فاکتور نمایش داده خواهد شد.</em></p>'; }
}

/**
 * Renders the meta box on the client edit screen.
 */
function cim_render_client_invoices_meta_box($post) {
    $args = ['post_type' => 'invoice', 'posts_per_page' => -1, 'meta_key' => '_cim_client_id', 'meta_value' => $post->ID, 'orderby' => 'date', 'order' => 'DESC'];
    $client_invoices = get_posts($args);
    if (empty($client_invoices)) { echo '<p>هیچ فاکتوری برای این مشتری ثبت نشده است.</p>'; return; }
    echo '<table class="widefat fixed striped"><thead><tr><th>شماره فاکتور</th><th>عنوان فاکتور</th><th>تاریخ صدور</th><th>وضعیت</th><th></th></tr></thead><tbody>';
    foreach ($client_invoices as $invoice) {
        $invoice_number = get_post_meta($invoice->ID, '_cim_invoice_number', true) ?: '---';
        $issue_date = get_post_meta($invoice->ID, '_cim_issue_date', true) ?: '---';
        $status_terms = get_the_terms($invoice->ID, 'invoice_status');
        $status = !empty($status_terms) ? esc_html($status_terms[0]->name) : '<i>تعیین نشده</i>';
        echo '<tr><td>' . esc_html($invoice_number) . '</td><td>' . esc_html($invoice->post_title) . '</td><td>' . esc_html($issue_date) . '</td><td>' . $status . '</td><td><a href="' . get_edit_post_link($invoice->ID) . '" class="button button-small">مشاهده/ویرایش</a></td></tr>';
    }
    echo '</tbody></table>';
}

/**
 * Saves all custom meta data for the 'invoice' post type.
 */
function cim_save_invoice_meta_data($post_id) {
    if (!isset($_POST['cim_invoice_nonce']) || !wp_verify_nonce($_POST['cim_invoice_nonce'], 'cim_save_invoice_meta_data')) return;
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_post', $post_id)) return;
    if (get_post_type($post_id) !== 'invoice') return;

	// === NEW: Save client name for sorting ===
    if (isset($_POST['cim_client_id'])) {
        $client_id = sanitize_text_field($_POST['cim_client_id']);
        update_post_meta($post_id, '_cim_client_id', $client_id);
        update_post_meta($post_id, '_cim_client_name', get_the_title($client_id));
    }
	// =======================================

    $fields = ['cim_invoice_type', 'cim_invoice_number', 'cim_issue_date', 'cim_discount_amount', 'cim_tax_rate', 'cim_payment_method', 'cim_entry_method', 'cim_uploaded_pdf_id'];
    foreach($fields as $field) {
        if(isset($_POST[$field])) {
            update_post_meta($post_id, '_'.$field, sanitize_text_field(str_replace(',', '', $_POST[$field])));
        }
    }

    $textareas = ['cim_promotions', 'cim_contract_terms'];
    foreach($textareas as $area) { if(isset($_POST[$area])) { update_post_meta($post_id, '_'.$area, sanitize_textarea_field($_POST[$area])); } }

    if (isset($_POST['line_items']) && is_array($_POST['line_items'])) {
        $sanitized_items = [];
        foreach ($_POST['line_items'] as $item) {
            $sanitized_item = [];
            foreach ($item as $key => $value) {
                $sanitized_item[$key] = sanitize_text_field(str_replace(',', '', $value));
            }
            $sanitized_items[] = $sanitized_item;
        }
        update_post_meta($post_id, '_cim_line_items', $sanitized_items);
    } else {
        delete_post_meta($post_id, '_cim_line_items');
    }
    
    if (isset($_POST['dimensions_text']) && is_array($_POST['dimensions_text'])) {
        $sanitized_dims = array_map('sanitize_text_field', $_POST['dimensions_text']);
        update_post_meta($post_id, '_cim_dimensions_text', $sanitized_dims);
    } else {
        delete_post_meta($post_id, '_cim_dimensions_text');
    }

    if (!get_post_meta($post_id, '_cim_secure_hash', true)) {
        update_post_meta($post_id, '_cim_secure_hash', wp_generate_uuid4());
    }

    // --- LOGIC TO UPDATE LAST INVOICE NUMBER ---
    if (isset($_POST['post_status']) && $_POST['post_status'] === 'publish') {
        $is_already_published = get_post_meta($post_id, '_cim_is_published_once', true);

        if ( !$is_already_published ) {
            $invoice_number = isset($_POST['cim_invoice_number']) ? sanitize_text_field($_POST['cim_invoice_number']) : '';
            if (is_numeric($invoice_number)) {
                $last_number = (int) get_option('cim_invoice_last_number', 0);
                if ((int)$invoice_number > $last_number) {
                    update_option('cim_invoice_last_number', (int)$invoice_number);
                    update_post_meta($post_id, '_cim_is_published_once', 'yes');
                }
            }
        }
    }
}
add_action('save_post', 'cim_save_invoice_meta_data');
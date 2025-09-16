<?php
// Security Check
if ( ! defined( 'WPINC' ) ) die;

// Add submenu page
function cim_add_settings_page() {
    add_submenu_page('edit.php?post_type=invoice', 'تنظیمات افزونه فاکتور', 'تنظیمات', 'manage_options', 'cim-settings', 'cim_render_settings_page');
}
add_action('admin_menu', 'cim_add_settings_page');

// Register settings
function cim_register_settings() {
    register_setting('cim_settings_group', 'cim_seller_name');
    register_setting('cim_settings_group', 'cim_seller_address');
    register_setting('cim_settings_group', 'cim_seller_phone');
    register_setting('cim_settings_group', 'cim_company_logo');
    register_setting('cim_settings_group', 'cim_client_logos');
    register_setting('cim_settings_group', 'cim_default_tax_rate');
    register_setting('cim_settings_group', 'cim_contract_terms'); 
    register_setting('cim_settings_group', 'cim_footer_content');
    // *** NEW: Register invoice numbering settings ***
    register_setting('cim_settings_group', 'cim_invoice_start_number');
    register_setting('cim_settings_group', 'cim_invoice_last_number');
}
add_action('admin_init', 'cim_register_settings');

// Render the HTML for the settings page
function cim_render_settings_page() {
    ?>
    <div class="wrap cim-settings-wrap">
        <h1>تنظیمات افزونه فاکتور</h1>
        <form method="post" action="options.php">
            <?php settings_fields('cim_settings_group'); ?>
            
            <h2>اطلاعات فروشنده</h2>
            <p>این اطلاعات به صورت پیش‌فرض در تمام فاکتورها نمایش داده می‌شود.</p>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row"><label for="cim_seller_name">نام فروشنده</label></th>
                    <td><input type="text" id="cim_seller_name" name="cim_seller_name" value="<?php echo esc_attr(get_option('cim_seller_name')); ?>" class="regular-text"></td>
                </tr>
                <tr valign="top">
                    <th scope="row"><label for="cim_seller_address">آدرس فروشنده</label></th>
                    <td><textarea id="cim_seller_address" name="cim_seller_address" rows="3" class="large-text"><?php echo esc_textarea(get_option('cim_seller_address')); ?></textarea></td>
                </tr>
                <tr valign="top">
                    <th scope="row"><label for="cim_seller_phone">تلفن فروشنده</label></th>
                    <td><input type="text" id="cim_seller_phone" name="cim_seller_phone" value="<?php echo esc_attr(get_option('cim_seller_phone')); ?>" class="regular-text"></td>
                </tr>
            </table>
            <hr>

            <h2>لوگوها</h2>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row"><label for="upload_logo_button">لوگوی شرکت</label></th>
                    <td>
                        <div class="logo-preview-wrapper single">
                            <?php $logo_id = get_option('cim_company_logo'); if ($logo_id) { echo '<div class="logo-item"><img src="' . wp_get_attachment_url($logo_id) . '" /></div>'; } ?>
                        </div>
                        <input type="hidden" name="cim_company_logo" id="cim_company_logo" value="<?php echo esc_attr($logo_id); ?>">
                        <button type="button" class="button" id="upload_logo_button">آپلود/انتخاب لوگو</button>
                        <button type="button" class="button remove-single-logo" style="<?php echo $logo_id ? '' : 'display:none;'; ?>">حذف لوگو</button>
                    </td>
                </tr>
                 <tr valign="top">
                    <th scope="row"><label for="upload_logos_button">لوگوهای مشتریان (برای کاروسل)</label></th>
                    <td>
                        <div class="logo-preview-wrapper multiple">
                            <?php $logo_ids = get_option('cim_client_logos', ''); if ($logo_ids) { $logo_id_array = explode(',', $logo_ids); foreach ($logo_id_array as $logo_id) { if(empty(trim($logo_id))) continue; echo '<div class="logo-item" data-id="'.esc_attr($logo_id).'"><img src="' . wp_get_attachment_url($logo_id) . '" /><span class="remove-logo">×</span></div>'; } } ?>
                        </div>
                        <input type="hidden" name="cim_client_logos" id="cim_client_logos" value="<?php echo esc_attr($logo_ids); ?>">
                        <button type="button" class="button" id="upload_logos_button">آپلود/انتخاب لوگوها</button>
                    </td>
                </tr>
            </table>
            <hr>

            <h2>شماره‌گذاری فاکتور</h2>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row"><label for="cim_invoice_start_number">شماره شروع فاکتورها</label></th>
                    <td>
                        <input type="number" id="cim_invoice_start_number" name="cim_invoice_start_number" value="<?php echo esc_attr(get_option('cim_invoice_start_number', '1000')); ?>" class="regular-text">
                        <p class="description">اولین فاکتور شما با این شماره شروع خواهد شد. پس از آن، شماره‌ها به صورت خودکار افزایش می‌یابند.</p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row"><label for="cim_invoice_last_number">آخرین شماره استفاده شده</label></th>
                    <td>
                        <input type="number" id="cim_invoice_last_number" name="cim_invoice_last_number" value="<?php echo esc_attr(get_option('cim_invoice_last_number', '0')); ?>" class="regular-text" readonly>
                        <p class="description">این عدد به صورت خودکار با صدور هر فاکتور جدید به‌روز می‌شود. (فقط جهت نمایش)</p>
                    </td>
                </tr>
            </table>
            <hr>
            
            <h2>تنظیمات مالی پیش‌فرض</h2>
             <table class="form-table">
                <tr valign="top">
                    <th scope="row"><label for="cim_default_tax_rate">نرخ مالیات پیش‌فرض (٪)</label></th>
                    <td>
                        <input type="number" step="0.01" id="cim_default_tax_rate" name="cim_default_tax_rate" value="<?php echo esc_attr(get_option('cim_default_tax_rate', '9')); ?>" class="small-text">
                        <p class="description">این مقدار در زمان ایجاد فاکتور جدید به صورت پیش‌فرض قرار می‌گیرد.</p>
                    </td>
                </tr>
            </table>
            <hr>

            <h2>محتوای پیش‌فرض قالب فاکتور</h2>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row"><label for="cim_contract_terms">متن پیش‌فرض شرایط قرارداد</label></th>
                    <td>
                        <textarea id="cim_contract_terms" name="cim_contract_terms" rows="15" class="large-text"><?php
                            $default_terms = "چک صیادی شماره ................................... تا 24 ساعت پس از انعقاد این قرارداد در سامانه بانکی مربوطه ثبت می گردد\nدهنه به عرض بالای 5 متر ریل فولادی و تیغه 10 سانتی متری توصیه میگردد\nاشتباه محاسباتی قابل برگشت می باشد.\nهزینه نصب داربست به عهده مشتری میباشد\nمدت زمان نصب و تحویل ........روز کاری بعد از عقد قرارداد میباشد.\nخروج کالا از کارگاه، بعد از تسویه فاکتور مقدور میباشد\nدرصورت درخواست چشمی/فلاشر/یو پی اس/جاقفلی/کاور و.... طی فاکتور جداگانه محاسبه خواهد شد\nتنها ملاک برای نصب و عمل به تعهدات تسویه حساب کامل از جانب مشتری می باشد.\nدر صورت فسخ قرار داد از سوی مشتری ،تا قبل از نصب 25% درصد و بعد از نصب 50% درصد از کل مبلغ قرار داد\nبه عنوان جبران خسارت به پیمانکار پرداخت میگردد\nکلیه اجناس نصب شده تا تسویه کامل و نقد شدن تمامی چکها در نزد مشتری بصورت امانی می باشد ودرصورت عدم وصول چکها یا\nعدم تسویه حساب مشتری هیچ گونه ادعایی درمورد مالکیت اجناس نصب شده نداشته وفروشنده مختارخواهد بود بدون مراجعه به \nمراکز قانونی حق تام جهت عودت کلیه اجناس و کم کردن کلیه ضرروزیان را دارا می باشد و مشتری حق هرگونه اعتراض و شکایت \nرا از خود سلب مینماید\nکلیه اجناس به رویت و تایید مشتری رسیده است\nترتیب چک ها از تاریخ قرارداد ماه به ماه و به صورت مرتب می باشد";
                            echo esc_textarea(get_option('cim_contract_terms', $default_terms)); 
                        ?></textarea>
                        <p class="description">این متن به صورت پیش‌فرض در فاکتورهای جدید درج می‌شود، اما برای هر فاکتور قابل ویرایش است.</p>
                    </td>
                </tr>
                 <tr valign="top">
                    <th scope="row"><label for="cim_footer_content">محتوای فوتر (پاورقی) پیش‌فرض</label></th>
                    <td>
                        <textarea id="cim_footer_content" name="cim_footer_content" rows="4" class="large-text"><?php echo esc_textarea(get_option('cim_footer_content')); ?></textarea>
                        <p class="description">این متن نیز به صورت پیش‌فرض در فاکتورهای جدید قرار می‌گیرد.</p>
                    </td>
                </tr>
            </table>
            
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}
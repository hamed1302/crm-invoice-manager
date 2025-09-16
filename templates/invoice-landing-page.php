<?php
if ( ! defined( 'WPINC' ) ) die;
global $invoice_data;

// --- Calculations ---
$subtotal = 0;
if (!empty($invoice_data['line_items']) && is_array($invoice_data['line_items'])) {
    foreach($invoice_data['line_items'] as $item) {
        $subtotal += isset($item['total']) ? floatval($item['total']) : 0;
    }
}
$discount_amount = isset($invoice_data['discount_amount']) ? floatval($invoice_data['discount_amount']) : 0;
$tax_rate = isset($invoice_data['tax_rate']) ? floatval($invoice_data['tax_rate']) : 0;
$subtotal_after_discount = $subtotal - $discount_amount;
$tax_amount = ($tax_rate > 0) ? (($subtotal_after_discount * $tax_rate) / 100) : 0;
$final_amount = $subtotal_after_discount + $tax_amount;

// --- Function to convert number to Persian words ---
function number_to_persian_words($number) {
    if ($number == 0) return 'صفر';
    $persian_digits = ['صفر', 'یک', 'دو', 'سه', 'چهار', 'پنج', 'شش', 'هفت', 'هشت', 'نه'];
    $persian_teens = ['ده', 'یازده', 'دوازده', 'سیزده', 'چهارده', 'پانزده', 'شانزده', 'هفده', 'هجده', 'نوزده'];
    $persian_tens = ['', '', 'بیست', 'سی', 'چهل', 'پنجاه', 'شصت', 'هفتاد', 'هشتاد', 'نود'];
    $persian_hundreds = ['', 'یکصد', 'دویست', 'سیصد', 'چهارصد', 'پانصد', 'ششصد', 'هفتصد', 'هشتصد', 'نهصد'];
    $persian_megas = ['', 'هزار', 'میلیون', 'میلیارد', 'تریلیون'];
    $parts = []; $mega_counter = 0;
    while ($number > 0) {
        $part = $number % 1000;
        if ($part > 0) {
            $part_words = []; $hundreds = floor($part / 100); $tens_and_ones = $part % 100;
            if ($hundreds > 0) $part_words[] = $persian_hundreds[$hundreds];
            if ($tens_and_ones > 0) {
                if ($tens_and_ones < 10) $part_words[] = $persian_digits[$tens_and_ones];
                elseif ($tens_and_ones < 20) $part_words[] = $persian_teens[$tens_and_ones - 10];
                else {
                    $tens = floor($tens_and_ones / 10); $ones = $tens_and_ones % 10;
                    $part_words[] = $persian_tens[$tens];
                    if ($ones > 0) $part_words[] = $persian_digits[$ones];
                }
            }
            $parts[] = implode(' و ', $part_words) . ($mega_counter > 0 ? ' ' . $persian_megas[$mega_counter] : '');
        }
        $number = floor($number / 1000); $mega_counter++;
    }
    return implode(' و ', array_reverse($parts));
}
$final_amount_words = number_to_persian_words($final_amount);
$company_logo_id = get_option('cim_company_logo');

// Prepare logo carousel data
$client_logo_ids = [];
if (!empty($invoice_data['client_logos'])) {
    $client_logo_ids = explode(',', $invoice_data['client_logos']);
}
?>
<!DOCTYPE html>
<html lang="fa-IR" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo ($invoice_data['invoice_type'] ?? 'invoice') === 'proforma' ? 'پیش فاکتور' : 'فاکتور'; ?> شماره <?php echo esc_html($invoice_data['invoice_number'] ?? ''); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css"/>
    <style>
        :root {
            --font-main: 'Vazirmatn', sans-serif; --text-dark: #212529; --text-light: #6c757d;
            --border-color: #dee2e6; --discount-color: #dc3545; --payable-color: #198754; --primary-color: #4F46E5;
            --whatsapp-color: #25D366; --telegram-color: #0088CC;
            --header-bg-darker: #e9ecef;
        }
        body { font-family: var(--font-main); margin: 0; padding: 1rem; background-color: #f4f5f7; color: var(--text-dark); font-size: 13px; line-height: 1.6; }
        .toolbar-wrapper { max-width: 21cm; margin: 0 auto 1rem auto; background: #fff; padding: .5rem; border-radius: .5rem; box-shadow: 0 2px 4px rgba(0,0,0,0.1); display: flex; justify-content: center; gap: .5rem; flex-wrap: wrap; }
        .btn { display: inline-flex; align-items: center; gap: .5rem; background: #f8f9fa; color: #343a40; border: 1px solid var(--border-color); padding: .4rem .8rem; border-radius: .25rem; font-size: 12px; cursor: pointer; text-decoration: none; }
        .btn svg { width: 1.1rem; height: 1.1rem; }
        .btn.btn-whatsapp { background-color: var(--whatsapp-color); color: white; border-color: var(--whatsapp-color); }
        .btn.btn-telegram { background-color: var(--telegram-color); color: white; border-color: var(--telegram-color); }
        .invoice-box { max-width: 21cm; min-height: 29.7cm; margin: auto; padding: 1.5cm; background: #fff; box-shadow: 0 0 15px rgba(0,0,0,0.1); box-sizing: border-box; }
        table { width: 100%; border-collapse: collapse; }
        .header-top { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid var(--text-dark); padding-bottom: .5rem; margin-bottom: 1rem; }
        .company-logo { max-width: 120px; max-height: 60px; }
        .invoice-title { font-size: 1.8rem; font-weight: 700; }
        .header-details { text-align: left; font-size: 12px; line-height: 1.5; }
        .header-details p, .parties-info p { direction: rtl; unicode-bidi: embed; } /* Fix for RTL colon issue */
        .parties-info table { border: 1px solid var(--border-color); margin-bottom: 1rem; }
        .parties-info th { background-color: var(--header-bg-darker); padding: 8px; font-size: 13px; text-align: right; border-left: 1px solid var(--border-color); }
        .parties-info th:last-child { border-left: none; }
        .parties-info td { padding: 8px; vertical-align: top; border-left: 1px solid var(--border-color); }
        .parties-info td:last-child { border-left: none; }
        .items-table { border: 1px solid var(--border-color); margin-bottom: 1rem; }
        .items-table thead th { background-color: var(--header-bg-darker); padding: 8px; border-bottom: 1px solid var(--border-color); border-left: 1px solid var(--border-color); }
        .items-table thead th:last-child { border-left: none; }
        .items-table td { padding: 8px; vertical-align: top; border-bottom: 1px solid var(--border-color); border-left: 1px solid var(--border-color); }
        .items-table td:last-child { border-left: none; }
        .items-table tbody tr:last-child td { border-bottom: none; }
        .text-center { text-align: center; } .text-left { text-align: left; }
        .new-layout-grid { display: flex; gap: 1rem; margin-bottom: 1rem; align-items: flex-start; }
        .left-column { flex: 1.2; } .right-column { flex: 1; }
        .calculations-box table { border: 1px solid var(--border-color); }
        .calculations-box td { padding: 8px; border-bottom: 1px solid var(--border-color); }
        .calculations-box tr:last-child td { border-bottom: none; }
        .calculations-box .value { text-align: left; font-weight: 700; }
        .calculations-box .discount { color: var(--discount-color); }
        .calculations-box .payable { background-color: var(--payable-color); color: white; font-size: 1.1em; }
        .payment-conditions { border: 1px solid var(--border-color); padding: 0.5rem 1rem; font-size: 12px; }
        .compact-table { border: 1px solid var(--border-color); margin-bottom: 1rem; }
        .compact-table td { padding: 8px; font-size: 11px; line-height: 1.5; border-bottom: 1px solid var(--border-color); }
        .compact-table tr:last-child td { border-bottom: none; }
        .compact-table.two-columns td { width: 50%; border-left: 1px solid var(--border-color); }
        .compact-table.two-columns tr td:last-child { border-left: none; }
        .contract-terms-box { border: 1px solid var(--border-color); padding: 10px; font-size: 10px; line-height: 1.6; text-align: justify; margin-bottom: 1rem; }
        .signatures-section { display: flex; justify-content: space-around; text-align: center; padding-top: 2rem; }
        .signature-box { flex: 1; padding-top: 3rem; border-top: 1px solid var(--text-dark); margin: 0 1rem; }
        .page-footer { text-align: center; color: var(--text-light); font-size: 0.9rem; padding-top: 1rem; border-top: 1px solid var(--border-color); margin-top: auto; }
        .popup-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(17, 24, 39, 0.6); backdrop-filter: blur(8px); display: flex; justify-content: center; align-items: center; z-index: 1000; opacity: 0; visibility: hidden; transition: opacity 0.3s ease; }
        .popup-overlay.show { opacity: 1; visibility: visible; }
        .popup-content { background: #fff; padding: 2.5rem; border-radius: 1rem; text-align: center; max-width: 400px; width: 90%; transform: scale(0.95); transition: transform 0.3s ease; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1), 0 8px 10px -6px rgba(0,0,0,0.1); }
        .popup-overlay.show .popup-content { transform: scale(1); }
        .popup-icon { width: 60px; height: 60px; margin: 0 auto 1.5rem auto; background-color: #ECFDF5; color: #10B981; border-radius: 50%; display: flex; align-items: center; justify-content: center; }
        .popup-icon svg { width: 32px; height: 32px; }
        .popup-content h2 { font-weight: 700; margin: 0 0 0.5rem 0; font-size: 1.25rem; color: var(--text-dark); }
        .popup-content p { color: var(--text-light); margin: 0 0 1.5rem 0; font-size: 0.9rem; }
        .popup-content .btn-primary { background-color: var(--primary-color); color: white; border: none; width: 100%; padding: 0.75rem; font-size: 1rem; border-radius: .5rem; }
        
        /* Carousel Styles */
        .client-logos-carousel { padding: 2rem 0; margin-top: 2rem; border-top: 1px solid var(--border-color); }
        .swiper { overflow: hidden; }
        .swiper-wrapper { align-items: center; }
        .swiper-slide { text-align: center; display: flex; justify-content: center; align-items: center; }
        .swiper-slide img { max-height: 60px; width: auto; max-width: 150px; filter: grayscale(100%); opacity: 0.6; transition: all 0.3s ease; }
        .swiper-slide:hover img { filter: grayscale(0%); opacity: 1; }
        
        @media print {
            body { background: none; padding: 0; font-size: 10pt; }
            .toolbar-wrapper, .popup-overlay, .client-logos-carousel { display: none; }
            .invoice-box { width: 100%; height: 100%; min-height: auto; margin: 0; padding: 0; box-shadow: none; border: none; display: flex; flex-direction: column; }
            .new-layout-grid, .signatures-section { page-break-inside: avoid; }
        }
    </style>
</head>
<body>
    <div class="popup-overlay" id="welcome-popup">
        <div class="popup-content">
            <div class="popup-icon"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg></div>
            <h2>سلام, <?php echo esc_html($invoice_data['client_name'] ?? 'مشتری گرامی'); ?></h2>
            <p>صورتحساب شما با موفقیت صادر شده و آماده مشاهده است.</p>
            <button class="btn btn-primary" onclick="document.getElementById('welcome-popup').classList.remove('show')">مشاهده صورتحساب</button>
        </div>
    </div>

    <div class="toolbar-wrapper">
        <?php if (($invoice_data['entry_method'] ?? 'manual') === 'manual') : ?>
            <button class="btn" id="download-btn"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" /></svg><span>دانلود PDF</span></button>
            <button class="btn" onclick="window.print()"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0 1 10.56 0m-10.56 0L6 18.25m0 0a2.25 2.25 0 0 0 2.25 2.25h1.5a2.25 2.25 0 0 0 2.25-2.25m-7.5 0h7.5m-7.5 0a2.25 2.25 0 0 1 2.25-2.25h1.5a2.25 2.25 0 0 1 2.25 2.25m0 0h3.113c.772 0 1.423-.423 1.812-1.034a3.001 3.001 0 0 0 .624-2.032V8.25a3.001 3.001 0 0 0-2.436-2.988l-4.154-1.107A3.001 3.001 0 0 0 9.75 5.25v2.538" /></svg><span>چاپ</span></button>
        <?php else: ?>
            <button class="btn" id="print-pdf-btn"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0 1 10.56 0m-10.56 0L6 18.25m0 0a2.25 2.25 0 0 0 2.25 2.25h1.5a2.25 2.25 0 0 0 2.25-2.25m-7.5 0h7.5m-7.5 0a2.25 2.25 0 0 1 2.25-2.25h1.5a2.25 2.25 0 0 1 2.25 2.25m0 0h3.113c.772 0 1.423-.423 1.812-1.034a3.001 3.001 0 0 0 .624-2.032V8.25a3.001 3.001 0 0 0-2.436-2.988l-4.154-1.107A3.001 3.001 0 0 0 9.75 5.25v2.538" /></svg><span>چاپ فایل PDF</span></button>
        <?php endif; ?>
        <button class="btn btn-whatsapp" id="share-whatsapp-btn"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M12.04 2.01A10.03 10.03 0 0 0 2 12.04a10.03 10.03 0 0 0 10.04 10.03 10.03 10.03 0 0 0 10.03-10.03c0-5.52-4.51-10.02-10.03-10.03m0 18.2a8.18 8.18 0 0 1-4.22-1.2l-4.47 1.34 1.37-4.34a8.18 8.18 0 1 1 7.32 4.2m3.5-6.17c-.18-.28-.65-1.3-1.02-1.51-.37-.21-.65-.21-.92.22-.27.43-1.03 1.28-1.26 1.54s-.46.3-.83.1a5.88 5.88 0 0 1-2.93-1.82 6.54 6.54 0 0 1-2-2.83c-.09-.28.18-.43.37-.62s.4-.48.6-.64.3-.27.43-.48.09-.45-.04-.83c-.14-.37-1.26-3.03-1.54-3.4s-.55-.32-.75-.32h-.4a1.2 1.2 0 0 0-.93.43c-.27.43-1.03 1.27-1.03 3.1s1.06 3.6 1.2 3.88 2.08 3.18 5.03 4.46c2.95 1.28 2.95.85 3.48.82s1.7-.7 1.94-1.37c.24-.67.24-1.23.18-1.37m0 0"/></svg><span>واتساپ</span></button>
        <button class="btn btn-telegram" id="share-telegram-btn"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="m9.417 15.181-.397 5.584c.568 0 .814-.244 1.109-.537l2.663-2.545 5.518 4.041c1.012.564 1.725.267 1.998-.931L22.092 3.408c.341-1.615-.556-2.207-1.541-1.712L2.32 8.551c-1.579.595-1.584 1.451-.27 1.816l4.243 1.325 9.461-5.919c.438-.272.833-.131.503.178z"/></svg><span>تلگرام</span></button>
    </div>
    <div class="invoice-box" id="invoice-content">
        <header class="header-top">
            <div class="header-logo"><?php if($company_logo_id): ?><img src="<?php echo esc_url(wp_get_attachment_url($company_logo_id)); ?>" class="company-logo"><?php endif; ?></div>
            <div class="header-title"><h1 class="invoice-title"><?php echo ($invoice_data['invoice_type'] ?? 'invoice') === 'proforma' ? 'پیش فاکتور' : 'فاکتور'; ?></h1></div>
            <div class="header-details"><p><strong>شماره: </strong><?php echo esc_html($invoice_data['invoice_number'] ?? ''); ?></p><p><strong>تاریخ: </strong><?php echo esc_html($invoice_data['issue_date'] ?? ''); ?></p></div>
        </header>

        <section class="parties-info">
            <table><thead><tr><th style="width: 50%;">فروشنده</th><th style="width: 50%;">خریدار</th></tr></thead>
            <tbody><tr><td><p><strong>نام:</strong> <?php echo esc_html($invoice_data['seller_name'] ?? ''); ?></p><p><strong>نشانی:</strong> <?php echo nl2br(esc_html($invoice_data['seller_address'] ?? '')); ?></p></td><td><p><strong>نام:</strong> <?php echo esc_html($invoice_data['client_name'] ?? ''); ?></p><p><strong>نشانی:</strong> <?php echo nl2br(esc_html($invoice_data['client_address'] ?? '')); ?></p></td></tr></tbody></table>
        </section>

        <?php if (($invoice_data['entry_method'] ?? 'manual') === 'manual') : ?>
            <section class="items-table-section">
                <table class="items-table">
                    <thead><tr><th style="width:5%;" class="text-center">ردیف</th><th>شرح</th><th style="width:10%;" class="text-center">تعداد</th><th style="width:20%;" class="text-left">قیمت واحد</th><th style="width:20%;" class="text-left">قیمت کل</th></tr></thead>
                    <tbody>
                    <?php if (!empty($invoice_data['line_items'])) : foreach ($invoice_data['line_items'] as $i => $item) : ?>
                        <tr><td class="text-center"><?php echo $i + 1; ?></td><td><?php echo esc_html($item['desc'] ?? ''); ?></td><td class="text-center"><?php echo esc_html($item['qty'] ?? ''); ?></td><td class="text-left"><?php echo number_format((float)($item['price'] ?? 0)); ?></td><td class="text-left"><?php echo number_format((float)($item['total'] ?? 0)); ?></td></tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </section>
            
            <div class="new-layout-grid">
                <div class="left-column">
                    <div class="calculations-box">
                        <table>
                            <tr><td>جمع کل</td><td class="value"><?php echo number_format($subtotal); ?> ریال</td></tr>
                            <tr><td>تخفیف</td><td class="value discount"><?php echo number_format($discount_amount); ?> ریال</td></tr>
                            <?php if ($tax_rate > 0): ?>
                            <tr><td>مالیات (<?php echo esc_html($tax_rate); ?>%)</td><td class="value"><?php echo number_format($tax_amount); ?> ریال</td></tr>
                            <?php endif; ?>
                            <tr class="payable"><td><strong>قابل پرداخت</strong></td><td class="value"><strong><?php echo number_format($final_amount); ?> ریال</strong></td></tr>
                        </table>
                    </div>
                    <p style="font-size: 12px; text-align: center; margin-top: 1rem;"><strong>به حروف:</strong> <?php echo esc_html($final_amount_words); ?> ریال</p>
                </div>
                <div class="right-column">
                    <div class="payment-conditions">
                        <strong>شرایط پرداخت:</strong> <?php echo ($invoice_data['payment_method'] ?? '') == 'installments' ? 'اقساطی' : 'نقدی'; ?>
                    </div>
                    <?php if (!empty($invoice_data['dimensions_text']) && is_array($invoice_data['dimensions_text'])) : ?>
                    <table class="compact-table two-columns" style="margin-top: 1rem;">
                        <tbody>
                        <?php for ($i = 0; $i < count($invoice_data['dimensions_text']); $i += 2): ?>
                            <tr>
                                <td><?php echo esc_html($invoice_data['dimensions_text'][$i] ?? ''); ?></td>
                                <td><?php echo esc_html($invoice_data['dimensions_text'][$i+1] ?? ''); ?></td>
                            </tr>
                        <?php endfor; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                </div>
            </div>

            <?php $promotions = trim($invoice_data['promotions'] ?? ''); if (!empty($promotions)) : $promo_lines = explode("\n", $promotions); ?>
            <table class="compact-table">
                <tbody>
                <?php foreach ($promo_lines as $line) : if(empty(trim($line))) continue; ?>
                    <tr><td><?php echo esc_html(trim($line)); ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
            
            <?php $contract_terms = trim($invoice_data['contract_terms'] ?? ''); if (!empty($contract_terms)) : ?>
            <div class="contract-terms-box"><?php echo nl2br(esc_html($contract_terms)); ?></div>
            <?php endif; ?>

            <section class="signatures-section">
                <div class="signature-box">امضای فروشنده</div>
                <div class="signature-box">امضای خریدار</div>
            </section>

        <?php elseif (($invoice_data['entry_method'] ?? 'manual') === 'upload' && !empty($invoice_data['uploaded_pdf_url'])) : ?>
            <div style="text-align:center; padding: 4rem 1rem; border: 2px dashed var(--border-color); border-radius: 8px;">
                <h3 style="margin-top:0;">فایل اصلی صورتحساب به صورت PDF ضمیمه شده است.</h3>
                <p>برای مشاهده جزئیات کامل، لطفاً فایل را از طریق دکمه زیر دریافت نمایید.</p>
                <a href="<?php echo esc_url($invoice_data['uploaded_pdf_url']); ?>" class="btn" style="font-size: 1rem; padding: 0.75rem 1.5rem; background-color: var(--primary-color); color: white;">دانلود فایل اصلی فاکتور</a>
            </div>
        <?php endif; ?>

        <?php if (!empty($client_logo_ids)) : ?>
        <section class="client-logos-carousel">
            <div class="swiper">
                <div class="swiper-wrapper">
                    <?php foreach ($client_logo_ids as $logo_id) : $logo_url = wp_get_attachment_url(trim($logo_id)); if ($logo_url) : ?>
                    <div class="swiper-slide"><img src="<?php echo esc_url($logo_url); ?>" alt="Client Logo"></div>
                    <?php endif; endforeach; ?>
                </div>
            </div>
        </section>
        <?php endif; ?>

        <?php $footer_content = trim($invoice_data['footer_content'] ?? ''); if (!empty($footer_content)): ?>
        <footer class="page-footer"><?php echo nl2br(esc_html($footer_content)); ?></footer>
        <?php endif; ?>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            document.getElementById('welcome-popup').classList.add('show');
            
            const downloadBtn = document.getElementById('download-btn');
            if (downloadBtn) {
                downloadBtn.addEventListener('click', function() {
                    const btn = this;
                    const invoiceElement = document.getElementById('invoice-content');
                    const invoiceNumber = "<?php echo esc_js($invoice_data['invoice_number'] ?? 'invoice'); ?>";
                    const filename = `factor-${invoiceNumber}.pdf`;
                    const pdfOptions = { margin: 0, filename: filename, image: { type: 'jpeg', quality: 0.98 }, html2canvas: { scale: 3, useCORS: true }, jsPDF: { unit: 'cm', format: 'a4', orientation: 'portrait' } };
                    const originalContent = btn.innerHTML;
                    btn.innerHTML = '<span>در حال آماده‌سازی...</span>'; btn.disabled = true;
                    html2pdf().set(pdfOptions).from(invoiceElement).save().then(() => { btn.innerHTML = originalContent; btn.disabled = false; });
                });
            }

            const printPdfBtn = document.getElementById('print-pdf-btn');
            if (printPdfBtn) {
                printPdfBtn.addEventListener('click', function() {
                    const pdfUrl = "<?php echo esc_js($invoice_data['uploaded_pdf_url'] ?? ''); ?>";
                    if (pdfUrl) {
                        const printFrame = document.createElement('iframe');
                        printFrame.style.display = 'none';
                        printFrame.src = pdfUrl;
                        document.body.appendChild(printFrame);
                        printFrame.onload = function() {
                            try {
                                printFrame.contentWindow.focus();
                                printFrame.contentWindow.print();
                            } catch (e) {
                                window.open(pdfUrl, '_blank');
                            }
                        };
                    }
                });
            }

            const shareUrl = "<?php echo esc_url_raw($invoice_data['share_url'] ?? home_url()); ?>";
            const shareText = `صورتحساب شماره ${"<?php echo esc_js($invoice_data['invoice_number'] ?? ''); ?>".replace(/<[^>]*>?/gm, '')}`;
            
            const whatsappBtn = document.getElementById('share-whatsapp-btn');
            if(whatsappBtn) whatsappBtn.addEventListener('click', function() { window.open(`https://api.whatsapp.com/send?text=${encodeURIComponent(shareText + '\n' + shareUrl)}`, '_blank'); });

            const telegramBtn = document.getElementById('share-telegram-btn');
            if(telegramBtn) telegramBtn.addEventListener('click', function() { window.open(`https://t.me/share/url?url=${encodeURIComponent(shareUrl)}&text=${encodeURIComponent(shareText)}`, '_blank'); });

            // Initialize Swiper
            if (document.querySelector('.client-logos-carousel .swiper')) {
                const swiper = new Swiper('.client-logos-carousel .swiper', {
                    // فعال کردن چرخش نامحدود
                    loop: true,
                    
                    // تنظیمات حرکت خودکار
                    autoplay: {
                        delay: 2500, // تاخیر بین هر حرکت (2.5 ثانیه)
                        disableOnInteraction: false,
                    },
                    
                    // سرعت انیمیشن حرکت
                    speed: 600,
                    
                    // تعداد لوگوهایی که در هر لحظه نمایش داده می‌شود
                    slidesPerView: 5, // نمایش 5 لوگو به صورت همزمان
                    spaceBetween: 40, // فاصله بین لوگوها

                    // تنظیمات برای صفحات کوچکتر (موبایل)
                    breakpoints: {
                        // زمانی که عرض صفحه 320 پیکسل یا بیشتر باشد
                        320: {
                          slidesPerView: 2, // 2 لوگو نمایش بده
                          spaceBetween: 20
                        },
                        // زمانی که عرض صفحه 640 پیکسل یا بیشتر باشد
                        640: {
                          slidesPerView: 3, // 3 لوگو نمایش بده
                          spaceBetween: 30
                        },
                        // زمانی که عرض صفحه 1024 پیکسل یا بیشتر باشد
                        1024: {
                          slidesPerView: 5, // 5 لوگو نمایش بده
                          spaceBetween: 40
                        }
                    }
                });
            }
        });
    </script>
</body>
</html>
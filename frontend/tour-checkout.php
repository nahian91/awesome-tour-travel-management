<?php
if (!defined('ABSPATH')) {
    exit;
}

add_shortcode('nttbs_tour_checkout', function($atts) {
    global $wpdb;

    $args = shortcode_atts([
        'trip_id'         => 0,
        'departure_id'    => 0,
        'thanks_page_url' => site_url('/tour-thanks/'),
    ], $atts, 'nttbs_tour_checkout');

    $trip_id      = !empty($args['trip_id']) ? absint($args['trip_id']) : (isset($_REQUEST['trip_id']) ? absint($_REQUEST['trip_id']) : 0);
    $departure_id = !empty($args['departure_id']) ? absint($args['departure_id']) : (isset($_REQUEST['departure_id']) ? absint($_REQUEST['departure_id']) : 0);
    $currency     = get_option('nttbs_currency_symbol', NTTBS_CURRENCY_SYMBOL_DEFAULT);
    $tax_percent  = (float) get_option('nttbs_tax_percentage', 0);

    $table_trips = $wpdb->prefix . 'nttbs_trips';
    $trip = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table_trips} WHERE id = %d", $trip_id), ARRAY_A);

    if (!$trip) {
        return '<div style="max-width:600px; margin:40px auto; text-align:center; padding:32px; background:#fff; border:1px solid #e2e8f0; border-radius:12px; font-family:sans-serif;">' . esc_html__('Please select an active tour to book.', 'nahian-tour-travel-booking-solution') . '</div>';
    }

    // Atomic Checkout Handler
    if (isset($_POST['nttbs_process_checkout']) && check_admin_referer('nttbs_checkout_nonce')) {
        $cust_name   = sanitize_text_field(wp_unslash($_POST['customer_name'] ?? ''));
        $cust_email  = sanitize_email(wp_unslash($_POST['customer_email'] ?? ''));
        $cust_phone  = sanitize_text_field(wp_unslash($_POST['customer_phone'] ?? ''));
        $pax_names   = isset($_POST['pax_names']) ? array_map('sanitize_text_field', (array) wp_unslash($_POST['pax_names'])) : [];
        $total_pax   = max(1, count(array_filter($pax_names)));
        $pay_method  = sanitize_text_field(wp_unslash($_POST['payment_method'] ?? 'Counter Cash'));

        $unit_price  = (float) $trip['price_adult'];
        $subtotal    = $unit_price * $total_pax;
        $tax_amt     = $subtotal * ($tax_percent / 100);
        $total_cost  = $subtotal + $tax_amt;
        $code        = 'NT-' . strtoupper(wp_generate_password(6, false, false));

        $wpdb->query('START TRANSACTION');
        try {
            $table_cust = $wpdb->prefix . 'nttbs_customers';
            $c = $wpdb->get_row($wpdb->prepare("SELECT id FROM {$table_cust} WHERE email = %s FOR UPDATE", $cust_email), ARRAY_A);
            if ($c) {
                $cust_id = (int)$c['id'];
            } else {
                $wpdb->insert($table_cust, [
                    'full_name' => $cust_name,
                    'email'     => $cust_email,
                    'phone'     => $cust_phone
                ]);
                $cust_id = (int)$wpdb->insert_id;
            }

            $table_b = $wpdb->prefix . 'nttbs_bookings';
            $wpdb->insert($table_b, [
                'booking_code'    => $code,
                'customer_id'     => $cust_id,
                'departure_id'    => $departure_id,
                'trip_id'         => $trip_id,
                'assigned_seats'  => 'GEN-' . $total_pax,
                'adults'          => $total_pax,
                'total_pax'       => $total_pax,
                'subtotal_amount' => $subtotal,
                'total_amount'    => $total_cost,
                'paid_amount'     => ($pay_method === 'Counter Cash') ? 0.00 : $total_cost,
                'balance_due'     => ($pay_method === 'Counter Cash') ? $total_cost : 0.00,
                'payment_method'  => $pay_method,
                'payment_status'  => ($pay_method === 'Counter Cash') ? 'pending' : 'paid',
                'status'          => 'confirmed'
            ]);

            $wpdb->query('COMMIT');

            $thanks_url = add_query_arg(['booking_code' => $code], esc_url($args['thanks_page_url']));
            wp_safe_redirect($thanks_url);
            exit;
        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
        }
    }

    ob_start();
    ?>
    <style>
    .nttbs-checkout-wrap {
        max-width: 960px;
        margin: 40px auto;
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Inter", sans-serif;
        display: grid;
        grid-template-columns: 1.8fr 1fr;
        gap: 32px;
        box-sizing: border-box;
    }
    .nttbs-chk-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 14px;
        padding: 24px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.03);
    }
    .nttbs-form-row { margin-bottom: 16px; }
    .nttbs-form-row label { display: block; font-size: 12px; font-weight: 800; margin-bottom: 6px; color:#334155; }
    @media (max-width: 768px) {
        .nttbs-checkout-wrap { grid-template-columns: 1fr; }
    }
    </style>

    <div class="nttbs-checkout-wrap">
        <form method="post" class="nttbs-chk-card">
            <?php wp_nonce_field('nttbs_checkout_nonce'); ?>
            <h2 style="font-size:20px; font-weight:800; margin:0 0 18px 0; color:#0f172a;"><?php esc_html_e('Primary Passenger & Billing', 'nahian-tour-travel-booking-solution'); ?></h2>

            <div class="nttbs-form-row">
                <label><?php esc_html_e('Lead Passenger Full Name *', 'nahian-tour-travel-booking-solution'); ?></label>
                <input type="text" name="customer_name" required class="nttbs-form-input">
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
                <div class="nttbs-form-row">
                    <label><?php esc_html_e('Email Address *', 'nahian-tour-travel-booking-solution'); ?></label>
                    <input type="email" name="customer_email" required class="nttbs-form-input">
                </div>
                <div class="nttbs-form-row">
                    <label><?php esc_html_e('Contact Phone *', 'nahian-tour-travel-booking-solution'); ?></label>
                    <input type="text" name="customer_phone" required class="nttbs-form-input">
                </div>
            </div>

            <h3 style="font-size:16px; font-weight:800; margin:22px 0 12px 0; color:#0f172a;"><?php esc_html_e('Manifest Passenger Roster', 'nahian-tour-travel-booking-solution'); ?></h3>
            <div id="nttbs-pax-wrapper">
                <div class="nttbs-form-row">
                    <input type="text" name="pax_names[]" required class="nttbs-form-input" placeholder="<?php esc_attr_e('Passenger 1 Full Name', 'nahian-tour-travel-booking-solution'); ?>">
                </div>
            </div>
            <button type="button" onclick="nttbsAddPaxField()" style="background:#f1f5f9; border:1px solid #cbd5e1; padding:7px 14px; border-radius:6px; font-size:12px; font-weight:700; cursor:pointer;">
                + <?php esc_html_e('Add Another Passenger', 'nahian-tour-travel-booking-solution'); ?>
            </button>

            <h3 style="font-size:16px; font-weight:800; margin:24px 0 12px 0; color:#0f172a;"><?php esc_html_e('Payment Gateway', 'nahian-tour-travel-booking-solution'); ?></h3>
            <select name="payment_method" class="nttbs-form-select">
                <option value="Counter Cash"><?php esc_html_e('Pay on Arrival (Counter Cash)', 'nahian-tour-travel-booking-solution'); ?></option>
                <option value="Online Portal"><?php esc_html_e('Instant Card Terminal (Stripe)', 'nahian-tour-travel-booking-solution'); ?></option>
            </select>

            <button type="submit" name="nttbs_process_checkout" class="nttbs-search-submit-btn" style="width:100%; margin-top:24px; padding:14px; justify-content:center;">
                <?php esc_html_e('Confirm & Issue Tour Voucher', 'nahian-tour-travel-booking-solution'); ?>
            </button>
        </form>

        <div class="nttbs-chk-card" style="height:fit-content; background:#f8fafc;">
            <h3 style="font-size:16px; font-weight:800; margin:0 0 12px 0; color:#0f172a;"><?php esc_html_e('Order Summary', 'nahian-tour-travel-booking-solution'); ?></h3>
            <p style="font-size:14px; font-weight:700; margin:0 0 6px 0; color:#0f172a;"><?php echo esc_html($trip['title']); ?></p>
            <div style="font-size:12px; color:#64748b; margin-bottom:14px;">⏱️ <?php echo esc_html($trip['duration_days']); ?>D / <?php echo esc_html($trip['duration_nights']); ?>N</div>

            <div style="border-top:1px solid #e2e8f0; padding-top:12px; display:flex; justify-content:space-between; font-size:13px; margin-bottom:6px;">
                <span><?php esc_html_e('Rate per Pax:', 'nahian-tour-travel-booking-solution'); ?></span>
                <strong style="font-family:'JetBrains Mono', monospace;"><?php echo esc_html($currency . ' ' . number_format((float)$trip['price_adult'], 2)); ?></strong>
            </div>
            <div style="display:flex; justify-content:space-between; font-size:16px; font-weight:800; border-top:1px solid #e2e8f0; padding-top:12px; color:#1d4ed8;">
                <span><?php esc_html_e('Payable Total:', 'nahian-tour-travel-booking-solution'); ?></span>
                <span id="nttbs-total-amount" style="font-family:'JetBrains Mono', monospace;"><?php echo esc_html($currency . ' ' . number_format((float)$trip['price_adult'], 2)); ?></span>
            </div>
        </div>
    </div>

    <script>
    let nttbsBasePrice = <?php echo (float)$trip['price_adult']; ?>;
    let nttbsCurrency  = <?php echo json_encode($currency); ?>;

    function nttbsAddPaxField() {
        const wrap = document.getElementById('nttbs-pax-wrapper');
        const count = wrap.children.length + 1;
        const div = document.createElement('div');
        div.className = 'nttbs-form-row';
        div.innerHTML = `<input type="text" name="pax_names[]" required class="nttbs-form-input" placeholder="Passenger ${count} Full Name">`;
        wrap.appendChild(div);
        document.getElementById('nttbs-total-amount').textContent = `${nttbsCurrency} ${(nttbsBasePrice * count).toFixed(2)}`;
    }
    </script>
    <?php
    return ob_get_clean();
});
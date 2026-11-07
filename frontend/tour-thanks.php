<?php
if (!defined('ABSPATH')) {
    exit;
}

add_shortcode('nttbs_tour_thanks', function($atts) {
    global $wpdb;

    $args = shortcode_atts([
        'booking_code' => '',
    ], $atts, 'nttbs_tour_thanks');

    $code     = !empty($args['booking_code']) ? sanitize_text_field($args['booking_code']) : (isset($_GET['booking_code']) ? sanitize_text_field(wp_unslash($_GET['booking_code'])) : '');
    $currency = get_option('nttbs_currency_symbol', NTTBS_CURRENCY_SYMBOL_DEFAULT);

    $table_b = $wpdb->prefix . 'nttbs_bookings';
    $table_t = $wpdb->prefix . 'nttbs_trips';
    $table_c = $wpdb->prefix . 'nttbs_customers';

    $booking = null;
    if (!empty($code)) {
        $booking = $wpdb->get_row($wpdb->prepare(
            "SELECT b.*, t.title AS trip_title, c.full_name, c.email 
             FROM {$table_b} b 
             LEFT JOIN {$table_t} t ON b.trip_id = t.id 
             LEFT JOIN {$table_c} c ON b.customer_id = c.id 
             WHERE b.booking_code = %s", 
            $code
        ), ARRAY_A);
    }

    if (!$booking) {
        return '<div style="max-width:600px; margin:40px auto; text-align:center; padding:32px; background:#fff; border:1px solid #e2e8f0; border-radius:14px; font-family:sans-serif;">' . esc_html__('Booking voucher could not be retrieved.', 'nahian-tour-travel-booking-solution') . '</div>';
    }

    ob_start();
    ?>
    <div style="max-width:640px; margin:50px auto; background:#ffffff; border:1px solid #e2e8f0; border-radius:16px; padding:36px; text-align:center; box-shadow:0 10px 25px rgba(0,0,0,0.05); font-family:-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
        <div style="width:64px; height:64px; background:#ecfdf5; border-radius:50%; display:flex; align-items:center; justify-content:center; margin:0 auto 16px auto; color:#059669; font-size:30px;">✓</div>
        <h1 style="font-size:24px; font-weight:900; color:#0f172a; margin:0 0 6px 0;"><?php esc_html_e('Reservation Confirmed!', 'nahian-tour-travel-booking-solution'); ?></h1>
        <p style="font-size:13.5px; color:#64748b; margin:0 0 24px 0;"><?php esc_html_e('Your passenger manifest slot is officially secured.', 'nahian-tour-travel-booking-solution'); ?></p>

        <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; padding:20px; text-align:left; margin-bottom:24px;">
            <div style="display:flex; justify-content:space-between; margin-bottom:10px; font-size:13px;">
                <span style="color:#64748b;"><?php esc_html_e('Booking Reference:', 'nahian-tour-travel-booking-solution'); ?></span>
                <strong style="color:#2563eb; font-family:'JetBrains Mono', monospace; font-size:14px;"><?php echo esc_html($booking['booking_code']); ?></strong>
            </div>
            <div style="display:flex; justify-content:space-between; margin-bottom:10px; font-size:13px;">
                <span style="color:#64748b;"><?php esc_html_e('Expedition:', 'nahian-tour-travel-booking-solution'); ?></span>
                <strong><?php echo esc_html($booking['trip_title']); ?></strong>
            </div>
            <div style="display:flex; justify-content:space-between; font-size:14px; font-weight:800; border-top:1px solid #e2e8f0; padding-top:10px;">
                <span style="color:#0f172a;"><?php esc_html_e('Total Amount:', 'nahian-tour-travel-booking-solution'); ?></span>
                <strong style="color:#059669; font-family:'JetBrains Mono', monospace;"><?php echo esc_html($currency . ' ' . number_format((float)$booking['total_amount'], 2)); ?></strong>
            </div>
        </div>

        <button type="button" onclick="window.print()" style="background:#2563eb; color:#fff; border:none; padding:12px 24px; border-radius:8px; font-weight:700; cursor:pointer;">
            🖨️ <?php esc_html_e('Print Passenger Voucher', 'nahian-tour-travel-booking-solution'); ?>
        </button>
    </div>
    <?php
    return ob_get_clean();
});
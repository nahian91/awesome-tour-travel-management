<?php
if (!defined('ABSPATH')) {
    exit;
}

add_shortcode('nttbs_tour_account', function($atts) {
    global $wpdb;

    $args = shortcode_atts([
        'login_page_url'   => site_url('/tour-login/'),
        'thanks_page_url'  => site_url('/tour-thanks/'),
    ], $atts, 'nttbs_tour_account');

    if (!is_user_logged_in()) {
        wp_safe_redirect(esc_url($args['login_page_url']));
        exit;
    }

    $current_user = wp_get_current_user();
    $currency     = get_option('nttbs_currency_symbol', NTTBS_CURRENCY_SYMBOL_DEFAULT);

    $table_b = $wpdb->prefix . 'nttbs_bookings';
    $table_c = $wpdb->prefix . 'nttbs_customers';
    $table_t = $wpdb->prefix . 'nttbs_trips';

    $customer = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table_c} WHERE email = %s", $current_user->user_email), ARRAY_A);

    $bookings = [];
    if ($customer) {
        $bookings = $wpdb->get_results($wpdb->prepare(
            "SELECT b.*, t.title AS trip_title 
             FROM {$table_b} b 
             LEFT JOIN {$table_t} t ON b.trip_id = t.id 
             WHERE b.customer_id = %d 
             ORDER BY b.id DESC", 
            $customer['id']
        ), ARRAY_A) ?: [];
    }

    ob_start();
    ?>
    <div style="max-width:1000px; margin:40px auto; font-family:-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; color:#0f172a;">
        <div style="display:flex; justify-content:space-between; align-items:center; background:#ffffff; border:1px solid #e2e8f0; border-radius:14px; padding:24px; margin-bottom:24px;">
            <div>
                <h1 style="font-size:22px; font-weight:800; margin:0;"><?php printf(esc_html__('Welcome, %s', 'nahian-tour-travel-booking-solution'), esc_html($current_user->display_name)); ?></h1>
                <p style="font-size:13px; color:#64748b; margin:4px 0 0 0;"><?php echo esc_html($current_user->user_email); ?></p>
            </div>
            <a href="<?php echo esc_url(wp_logout_url(site_url('/'))); ?>" style="background:#fee2e2; color:#ef4444; padding:8px 16px; border-radius:6px; font-weight:700; text-decoration:none; font-size:12px;">
                <?php esc_html_e('Sign Out', 'nahian-tour-travel-booking-solution'); ?>
            </a>
        </div>

        <div style="background:#ffffff; border:1px solid #e2e8f0; border-radius:14px; padding:24px;">
            <h3 style="font-size:16px; font-weight:800; margin:0 0 16px 0;"><?php esc_html_e('My Booking Reservations', 'nahian-tour-travel-booking-solution'); ?></h3>
            
            <?php if (empty($bookings)): ?>
                <p style="color:#64748b; font-size:13px; margin:0;"><?php esc_html_e('You have no registered bookings under this account email.', 'nahian-tour-travel-booking-solution'); ?></p>
            <?php else: ?>
                <table style="width:100%; border-collapse:collapse; font-size:13px; text-align:left;">
                    <thead>
                        <tr style="border-bottom:1px solid #e2e8f0; color:#64748b;">
                            <th style="padding:10px 0;"><?php esc_html_e('Booking Code', 'nahian-tour-travel-booking-solution'); ?></th>
                            <th style="padding:10px 0;"><?php esc_html_e('Expedition', 'nahian-tour-travel-booking-solution'); ?></th>
                            <th style="padding:10px 0;"><?php esc_html_e('Passengers', 'nahian-tour-travel-booking-solution'); ?></th>
                            <th style="padding:10px 0;"><?php esc_html_e('Total Paid', 'nahian-tour-travel-booking-solution'); ?></th>
                            <th style="padding:10px 0; text-align:right;"><?php esc_html_e('Voucher', 'nahian-tour-travel-booking-solution'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($bookings as $b): ?>
                            <tr style="border-bottom:1px solid #f1f5f9;">
                                <td style="padding:12px 0;"><code style="font-weight:700; color:#2563eb; font-family:'JetBrains Mono', monospace;"><?php echo esc_html($b['booking_code']); ?></code></td>
                                <td style="padding:12px 0; font-weight:700;"><?php echo esc_html($b['trip_title']); ?></td>
                                <td style="padding:12px 0;"><?php echo esc_html($b['total_pax']); ?> Pax</td>
                                <td style="padding:12px 0; font-weight:700; color:#059669; font-family:'JetBrains Mono', monospace;"><?php echo esc_html($currency . ' ' . number_format((float)$b['paid_amount'], 2)); ?></td>
                                <td style="padding:12px 0; text-align:right;">
                                    <a href="<?php echo esc_url(add_query_arg(['booking_code' => $b['booking_code']], esc_url($args['thanks_page_url']))); ?>" style="color:#2563eb; font-weight:700; text-decoration:none;">
                                        <?php esc_html_e('View Voucher', 'nahian-tour-travel-booking-solution'); ?> →
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
});
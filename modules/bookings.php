<?php
if (!defined('ABSPATH')) {
    exit;
}

/* ==========================================================================
   1. BOOKING CONTROLLERS & FORM DISPATCHERS
   ========================================================================== */

function nttbs_handle_booking_dispatchers() {
    global $wpdb;

    $table_bookings   = $wpdb->prefix . 'nttbs_bookings';
    $table_seats      = $wpdb->prefix . 'nttbs_seats';
    $table_departures = $wpdb->prefix . 'nttbs_departures';
    $table_customers  = $wpdb->prefix . 'nttbs_customers';

    // 1.1 Cancellation Handler
    if (isset($_POST['nttbs_dispatch_booking_cancel']) && check_admin_referer('nttbs_cancel_nonce')) {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Unauthorized administrative clearance.', 'nahian-tour-travel-booking-solution'), 403);
        }

        $booking_id = absint($_POST['booking_id'] ?? 0);

        $wpdb->query('START TRANSACTION');
        $b = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table_bookings} WHERE id = %d FOR UPDATE", $booking_id), ARRAY_A);

        if ($b && $b['status'] !== 'cancelled') {
            $seats     = array_filter(array_map('trim', explode(',', $b['assigned_seats'])));
            $pax_count = (int) $b['total_pax'];

            $wpdb->update($table_bookings, ['status' => 'cancelled'], ['id' => $booking_id], ['%s'], ['%d']);

            if (!empty($seats) && $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_seats)) === $table_seats) {
                foreach ($seats as $s_code) {
                    $wpdb->update($table_seats, [
                        'status'     => 'available',
                        'booking_id' => 0
                    ], [
                        'departure_id' => $b['departure_id'],
                        'seat_code'    => $s_code
                    ], ['%s', '%d'], ['%d', '%s']);
                }
            }

            $wpdb->query($wpdb->prepare(
                "UPDATE {$table_departures} SET booked_seats = GREATEST(0, booked_seats - %d) WHERE id = %d",
                $pax_count,
                $b['departure_id']
            ));

            $wpdb->query($wpdb->prepare(
                "UPDATE {$table_customers} SET total_spent = GREATEST(0, total_spent - %f) WHERE id = %d",
                $b['paid_amount'],
                $b['customer_id']
            ));

            $wpdb->query('COMMIT');

            if (function_exists('nttbs_log_activity')) {
                nttbs_log_activity('CANCEL_BOOKING', "Cancelled booking #{$b['booking_code']} and restored seat inventory");
            }

            wp_safe_redirect(admin_url('admin.php?page=nttbs-core&view=bookings&subtab=all&status=cancelled'));
            exit;
        }

        $wpdb->query('ROLLBACK');
        wp_safe_redirect(admin_url('admin.php?page=nttbs-core&view=bookings&subtab=all&status=error'));
        exit;
    }

    // 1.2 Comprehensive Operator Booking Creation Handler
    if (isset($_POST['nttbs_dispatch_comprehensive_booking']) && check_admin_referer('nttbs_comp_booking_nonce')) {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Unauthorized administrative clearance.', 'nahian-tour-travel-booking-solution'), 403);
        }

        $trip_id       = absint($_POST['trip_id'] ?? 0);
        $departure_id  = absint($_POST['departure_id'] ?? 0);
        $trip_code     = sanitize_text_field(wp_unslash($_POST['trip_code'] ?? ''));
        $status        = sanitize_key($_POST['booking_status'] ?? 'confirmed');
        $special_notes = sanitize_textarea_field(wp_unslash($_POST['special_notes'] ?? ''));

        // Customer Details
        $first_name = sanitize_text_field(wp_unslash($_POST['billing_first_name'] ?? ''));
        $last_name  = sanitize_text_field(wp_unslash($_POST['billing_last_name'] ?? ''));
        $cust_email = sanitize_email(wp_unslash($_POST['billing_email'] ?? ''));
        $cust_phone = sanitize_text_field(wp_unslash($_POST['billing_phone'] ?? ''));
        $full_name  = trim($first_name . ' ' . $last_name) ?: 'Guest Passenger';

        // Travelers
        $traveler_names = isset($_POST['traveler_name']) ? array_map('sanitize_text_field', (array) wp_unslash($_POST['traveler_name'])) : [];
        $total_pax      = max(1, count(array_filter($traveler_names)) ?: absint($_POST['num_travelers'] ?? 1));

        // Ledger Calculations
        $subtotal    = floatval($_POST['calculated_total'] ?? 0.00);
        $paid_amount = floatval($_POST['total_paid'] ?? 0.00);
        $tax_rate    = (float) get_option('nttbs_tax_percentage', 0);
        $tax_amount  = $subtotal * ($tax_rate / 100);
        $total_cost  = $subtotal + $tax_amount;
        $balance_due = max(0, $total_cost - $paid_amount);

        if (!$departure_id) {
            $departure_id = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$table_departures} WHERE trip_id = %d AND status = 'active' ORDER BY departure_date ASC LIMIT 1",
                $trip_id
            ));
        }

        $wpdb->query('START TRANSACTION');

        try {
            // Customer Deduplication
            $customer = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table_customers} WHERE email = %s FOR UPDATE", $cust_email), ARRAY_A);
            if ($customer) {
                $customer_id = (int) $customer['id'];
                $wpdb->update(
                    $table_customers,
                    ['full_name' => $full_name, 'phone' => $cust_phone],
                    ['id' => $customer_id],
                    ['%s', '%s'],
                    ['%d']
                );
            } else {
                $wpdb->insert($table_customers, [
                    'full_name'      => $full_name,
                    'email'          => $cust_email ?: ('guest_' . time() . '@domain.com'),
                    'phone'          => $cust_phone,
                    'total_bookings' => 0,
                    'total_spent'    => 0.00
                ], ['%s', '%s', '%s', '%d', '%f']);
                $customer_id = (int) $wpdb->insert_id;
            }

            // Seat Locking Sequence
            $assigned_seats = [];
            if ($departure_id && $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_seats)) === $table_seats) {
                $assigned_seats = $wpdb->get_col($wpdb->prepare(
                    "SELECT seat_code FROM {$table_seats} WHERE departure_id = %d AND status = 'available' ORDER BY id ASC LIMIT %d FOR UPDATE",
                    $departure_id,
                    $total_pax
                )) ?: [];
            }

            $assigned_str = !empty($assigned_seats) ? implode(', ', $assigned_seats) : ('GEN-' . str_pad((string)$total_pax, 2, '0', STR_PAD_LEFT));
            $booking_code = $trip_code ?: ('NT-' . strtoupper(wp_generate_password(6, false, false)));
            $pay_method   = sanitize_text_field(wp_unslash($_POST['payment_method_name'][0] ?? 'Counter Cash'));

            // Insert Booking
            $wpdb->insert($table_bookings, [
                'booking_code'    => $booking_code,
                'customer_id'     => $customer_id,
                'departure_id'    => $departure_id,
                'trip_id'         => $trip_id,
                'assigned_seats'  => $assigned_str,
                'adults'          => $total_pax,
                'children'        => 0,
                'total_pax'       => $total_pax,
                'subtotal_amount' => $subtotal,
                'discount_amount' => 0.00,
                'total_amount'    => $total_cost,
                'paid_amount'     => $paid_amount,
                'balance_due'     => $balance_due,
                'payment_method'  => $pay_method,
                'payment_status'  => ($balance_due <= 0.01) ? 'paid' : ($paid_amount > 0 ? 'partial' : 'pending'),
                'status'          => $status,
                'special_notes'   => $special_notes
            ]);

            $booking_id = (int) $wpdb->insert_id;

            if (!empty($assigned_seats) && $departure_id) {
                foreach ($assigned_seats as $s_code) {
                    $wpdb->update($table_seats, [
                        'status'     => 'booked',
                        'booking_id' => $booking_id
                    ], [
                        'departure_id' => $departure_id,
                        'seat_code'    => $s_code
                    ]);
                }
                $wpdb->query($wpdb->prepare(
                    "UPDATE {$table_departures} SET booked_seats = booked_seats + %d WHERE id = %d",
                    count($assigned_seats),
                    $departure_id
                ));
            }

            $wpdb->query($wpdb->prepare(
                "UPDATE {$table_customers} SET total_bookings = total_bookings + 1, total_spent = total_spent + %f WHERE id = %d",
                $paid_amount,
                $customer_id
            ));

            $wpdb->query('COMMIT');

            if (function_exists('nttbs_log_activity')) {
                nttbs_log_activity('COMPREHENSIVE_BOOKING', "Dispatched manifest reservation #{$booking_code} for {$full_name} ({$total_pax} Pax)");
            }

            wp_safe_redirect(admin_url('admin.php?page=nttbs-core&view=bookings&subtab=all&status=created&code=' . rawurlencode($booking_code)));
            exit;

        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            wp_safe_redirect(admin_url('admin.php?page=nttbs-core&view=bookings&subtab=all&status=error_sql'));
            exit;
        }
    }
}
nttbs_handle_booking_dispatchers();

/* ==========================================================================
   2. VIEW LOGIC & DATA PREPARATION
   ========================================================================== */

global $wpdb;
$current_subtab = isset($_GET['subtab']) && $_GET['subtab'] === 'add' ? 'add' : 'all';
$currency       = get_option('nttbs_currency_symbol', NTTBS_CURRENCY_SYMBOL_DEFAULT);

$table_b = $wpdb->prefix . 'nttbs_bookings';
$table_c = $wpdb->prefix . 'nttbs_customers';
$table_t = $wpdb->prefix . 'nttbs_trips';

$bookings = [];
if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_b)) === $table_b) {
    $bookings = $wpdb->get_results(
        "SELECT b.*, c.full_name AS customer_name, c.email AS customer_email, t.title AS trip_name 
         FROM {$table_b} b 
         LEFT JOIN {$table_c} c ON b.customer_id = c.id 
         LEFT JOIN {$table_t} t ON b.trip_id = t.id 
         ORDER BY b.id DESC",
        ARRAY_A
    ) ?: [];
}

$trips       = function_exists('nttbs_get_all_trips') ? nttbs_get_all_trips() : [];
$tax_percent = (float) get_option('nttbs_tax_percentage', 0);

$status_flag = isset($_GET['status']) ? sanitize_key($_GET['status']) : '';
$status_code = isset($_GET['code']) ? sanitize_text_field(wp_unslash($_GET['code'])) : '';
?>

<style>
    .nte-booking-shell {
        display: flex;
        flex-direction: column;
        gap: 20px;
        animation: nteFadeIn 0.25s ease-out;
    }
    @keyframes nteFadeIn {
        from { opacity: 0; transform: translateY(4px); }
        to { opacity: 1; transform: translateY(0); }
    }

    /* Subtab Pills */
    .nte-subnav-bar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        border-bottom: 1px solid var(--border-light);
        padding-bottom: 16px;
    }
    .nte-pill-toggle {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 8px 16px;
        border-radius: 8px;
        font-size: 13px;
        font-weight: 700;
        text-decoration: none;
        transition: all 0.15s ease;
    }
    .nte-pill-toggle.active {
        background-color: var(--c-blue);
        color: #ffffff;
        box-shadow: var(--shadow-sm);
    }
    .nte-pill-toggle.inactive {
        background-color: var(--bg-surface);
        color: var(--text-muted);
        border: 1px solid var(--border-light);
    }
    .nte-pill-toggle.inactive:hover {
        background-color: var(--bg-subtle);
        color: var(--text-main);
    }

    /* Alerts */
    .nte-alert {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 12px 18px;
        border-radius: 8px;
        font-size: 13px;
        font-weight: 600;
    }
    .nte-alert-success {
        background-color: var(--c-emerald-bg);
        border: 1px solid var(--c-emerald-border);
        color: var(--c-emerald);
    }
    .nte-alert-danger {
        background-color: var(--c-rose-bg);
        border: 1px solid var(--c-rose-border);
        color: var(--c-rose);
    }

    /* Panels & Tables */
    .nte-white-panel {
        background: var(--bg-surface);
        border: 1px solid var(--border-light);
        border-radius: 12px;
        box-shadow: var(--shadow-sm);
        overflow: hidden;
        margin-bottom: 20px;
    }
    .nte-white-panel:last-child {
        margin-bottom: 0;
    }
    .nte-panel-header {
        padding: 16px 20px;
        border-bottom: 1px solid var(--border-light);
        display: flex;
        align-items: center;
        justify-content: space-between;
        background: linear-gradient(180deg, rgba(255,255,255,0.02) 0%, rgba(0,0,0,0.02) 100%);
    }
    .nte-panel-header h3 {
        font-size: 14px;
        font-weight: 800;
        margin: 0;
        color: var(--text-main);
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .nte-table-responsive {
        width: 100%;
        overflow-x: auto;
    }
    .nte-data-table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
        font-size: 13px;
        text-align: left;
    }
    .nte-data-table th {
        background-color: var(--bg-subtle);
        color: var(--text-muted);
        font-size: 11px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        padding: 11px 18px;
        border-bottom: 1px solid var(--border-light);
        white-space: nowrap;
    }
    .nte-data-table td {
        padding: 14px 18px;
        border-bottom: 1px solid var(--border-light);
        color: var(--text-main);
        vertical-align: middle;
    }
    .nte-data-table tr:last-child td {
        border-bottom: none;
    }
    .nte-data-table tr:hover td {
        background-color: var(--bg-subtle);
    }

    /* Dynamic Action Buttons */
    .nte-btn-icon-danger {
        background-color: var(--c-rose-bg);
        border: 1px solid var(--c-rose-border);
        color: var(--c-rose);
        border-radius: 6px;
        padding: 5px 10px;
        font-size: 11px;
        font-weight: 700;
        cursor: pointer;
        transition: all 0.15s ease;
    }
    .nte-btn-icon-danger:hover {
        background-color: var(--c-rose);
        color: #ffffff;
    }

    .nte-status-pill {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 3px 8px;
        border-radius: 999px;
        font-size: 11px;
        font-weight: 700;
        letter-spacing: 0.2px;
        text-transform: uppercase;
    }
    .nte-status-pill::before {
        content: '';
        width: 6px;
        height: 6px;
        border-radius: 50%;
    }
    .nte-status-confirmed {
        background-color: var(--c-emerald-bg);
        border: 1px solid var(--c-emerald-border);
        color: var(--c-emerald);
    }
    .nte-status-confirmed::before {
        background-color: var(--c-emerald);
    }
    .nte-status-cancelled {
        background-color: var(--c-rose-bg);
        border: 1px solid var(--c-rose-border);
        color: var(--c-rose);
    }
    .nte-status-cancelled::before {
        background-color: var(--c-rose);
    }
</style>

<div class="nte-booking-shell">

    <!-- Subnav Tabs -->
    <div class="nte-subnav-bar">
        <div style="display:flex; gap:10px;">
            <a href="<?php echo esc_url(admin_url('admin.php?page=nttbs-core&view=bookings&subtab=all')); ?>" 
               class="nte-pill-toggle <?php echo $current_subtab === 'all' ? 'active' : 'inactive'; ?>">
                <span class="nte-nav-icon"><?php echo nttbs_svg_icon('bookings', 16, 16); ?></span>
                <?php esc_html_e('Reservation Ledger', 'nahian-tour-travel-booking-solution'); ?>
                <span style="background:rgba(255,255,255,0.2); font-size:11px; padding:2px 7px; border-radius:999px; margin-left:4px;">
                    <?php echo count($bookings); ?>
                </span>
            </a>
            <a href="<?php echo esc_url(admin_url('admin.php?page=nttbs-core&view=bookings&subtab=add')); ?>" 
               class="nte-pill-toggle <?php echo $current_subtab === 'add' ? 'active' : 'inactive'; ?>">
                <span>+</span> <?php esc_html_e('New Reservation', 'nahian-tour-travel-booking-solution'); ?>
            </a>
        </div>

        <?php if ($status_flag === 'created'): ?>
            <div class="nte-alert nte-alert-success">
                <span class="nte-nav-icon"><?php echo nttbs_svg_icon('check', 16, 16); ?></span>
                <?php printf(esc_html__('Booking confirmed successfully: Code %s', 'nahian-tour-travel-booking-solution'), '<strong>' . esc_html($status_code) . '</strong>'); ?>
            </div>
        <?php elseif ($status_flag === 'cancelled'): ?>
            <div class="nte-alert nte-alert-danger">
                <span class="nte-nav-icon"><?php echo nttbs_svg_icon('close', 16, 16); ?></span>
                <?php esc_html_e('Booking cancelled and seat inventory released.', 'nahian-tour-travel-booking-solution'); ?>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($current_subtab === 'all'): ?>
        <!-- TAB 1: ALL BOOKINGS LIST TABLE -->
        <div class="nte-white-panel">
            <div class="nte-panel-header">
                <h3>
                    <span class="nte-nav-icon icon-bookings"><?php echo nttbs_svg_icon('bookings', 18, 18); ?></span>
                    <span><?php esc_html_e('Master Passenger Reservation Ledger', 'nahian-tour-travel-booking-solution'); ?></span>
                </h3>
                <a href="<?php echo esc_url(admin_url('admin.php?page=nttbs-core&view=bookings&subtab=add')); ?>" class="nte-btn-primary" style="padding:6px 14px; font-size:12px; text-decoration:none;">
                    + <?php esc_html_e('Create Booking', 'nahian-tour-travel-booking-solution'); ?>
                </a>
            </div>
            <div class="nte-table-responsive">
                <table class="nte-data-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Code', 'nahian-tour-travel-booking-solution'); ?></th>
                            <th><?php esc_html_e('Lead Passenger', 'nahian-tour-travel-booking-solution'); ?></th>
                            <th><?php esc_html_e('Tour Expedition', 'nahian-tour-travel-booking-solution'); ?></th>
                            <th><?php esc_html_e('Allocated Manifest', 'nahian-tour-travel-booking-solution'); ?></th>
                            <th><?php esc_html_e('Total Amount', 'nahian-tour-travel-booking-solution'); ?></th>
                            <th><?php esc_html_e('Status', 'nahian-tour-travel-booking-solution'); ?></th>
                            <th style="text-align:right;"><?php esc_html_e('Management', 'nahian-tour-travel-booking-solution'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($bookings)): ?>
                            <tr>
                                <td colspan="7" style="text-align:center; padding:36px; color:var(--text-muted);">
                                    <?php esc_html_e('No active reservation records found in the ledger database.', 'nahian-tour-travel-booking-solution'); ?>
                                </td>
                            </tr>
                        <?php else: foreach ($bookings as $b): ?>
                            <tr>
                                <td>
                                    <code style="font-family:ui-monospace, monospace; font-size:12px; font-weight:800; color:var(--c-blue); background:var(--c-blue-bg); padding:3px 6px; border-radius:4px; border:1px solid var(--c-blue-border);">
                                        <?php echo esc_html($b['booking_code']); ?>
                                    </code>
                                </td>
                                <td>
                                    <strong style="color:var(--text-main); font-weight:800;"><?php echo esc_html($b['customer_name']); ?></strong><br>
                                    <span style="color:var(--text-muted); font-size:11px;"><?php echo esc_html($b['customer_email']); ?></span>
                                </td>
                                <td>
                                    <div style="font-weight:700; color:var(--text-main);"><?php echo esc_html($b['trip_name']); ?></div>
                                </td>
                                <td>
                                    <span style="background-color:var(--c-purple-bg); color:var(--c-purple); border:1px solid var(--c-purple-border); padding:2px 7px; border-radius:6px; font-weight:800; font-size:11px;">
                                        <?php echo esc_html($b['assigned_seats']); ?>
                                    </span>
                                </td>
                                <td>
                                    <strong style="font-weight:800; color:var(--text-main);">
                                        <?php echo esc_html($currency . ' ' . number_format_i18n((float)$b['total_amount'], 2)); ?>
                                    </strong>
                                </td>
                                <td>
                                    <?php $is_confirmed = ($b['status'] === 'confirmed'); ?>
                                    <span class="nte-status-pill <?php echo $is_confirmed ? 'nte-status-confirmed' : 'nte-status-cancelled'; ?>">
                                        <?php echo esc_html(strtoupper($b['status'])); ?>
                                    </span>
                                </td>
                                <td style="text-align:right;">
                                    <?php if ($b['status'] !== 'cancelled'): ?>
                                        <form method="post" style="display:inline;" onsubmit="return confirm('<?php echo esc_js(__('Are you sure you want to cancel this booking and restore allocated seats?', 'nahian-tour-travel-booking-solution')); ?>');">
                                            <?php wp_nonce_field('nttbs_cancel_nonce'); ?>
                                            <input type="hidden" name="booking_id" value="<?php echo esc_attr($b['id']); ?>">
                                            <button type="submit" name="nttbs_dispatch_booking_cancel" class="nte-btn-icon-danger">
                                                <?php esc_html_e('Cancel & Release', 'nahian-tour-travel-booking-solution'); ?>
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <span style="color:var(--text-muted); font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:0.5px;">
                                            <?php esc_html_e('Archived', 'nahian-tour-travel-booking-solution'); ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    <?php else: ?>
        <!-- TAB 2: OPERATOR BOOKING WORKSPACE -->
        <form method="post" action="<?php echo esc_url(admin_url('admin.php?page=nttbs-core&view=bookings&subtab=add')); ?>" id="nttbs-comprehensive-booking-form">
            <?php wp_nonce_field('nttbs_comp_booking_nonce'); ?>
            <input type="hidden" name="calculated_total" id="nttbs_input_calculated_total" value="0.00">

            <div style="display:grid; grid-template-columns: minmax(0, 2.3fr) minmax(0, 1fr); gap:24px; align-items:start;">
                
                <!-- Main Booking Steps -->
                <div>
                    <!-- 1. Trip Configuration Section -->
                    <div class="nte-white-panel">
                        <div class="nte-panel-header">
                            <h3>
                                <span class="nte-nav-icon icon-trips"><?php echo nttbs_svg_icon('trips', 18, 18); ?></span>
                                <span><?php esc_html_e('Tour Expedition Parameters', 'nahian-tour-travel-booking-solution'); ?></span>
                            </h3>
                        </div>
                        <div style="padding:20px;">
                            <div style="display:grid; grid-template-columns: repeat(2, 1fr); gap:16px;">
                                <div class="nte-field-row">
                                    <label class="nte-field-label"><?php esc_html_e('Manifest Reference Code', 'nahian-tour-travel-booking-solution'); ?></label>
                                    <input type="text" name="trip_code" id="nttbs_trip_code" class="nte-control-input" value="NT-<?php echo strtoupper(wp_generate_password(6, false, false)); ?>" readonly>
                                </div>
                                <div class="nte-field-row">
                                    <label class="nte-field-label"><?php esc_html_e('Booking Entry Date', 'nahian-tour-travel-booking-solution'); ?></label>
                                    <input type="date" name="booked_date" class="nte-control-input" value="<?php echo date('Y-m-d'); ?>">
                                </div>
                            </div>

                            <div style="display:grid; grid-template-columns: repeat(2, 1fr); gap:16px;">
                                <div class="nte-field-row">
                                    <label class="nte-field-label"><?php esc_html_e('Target Tour Package *', 'nahian-tour-travel-booking-solution'); ?></label>
                                    <select name="trip_id" id="nttbs_trip_select" class="nte-control-input" required onchange="nttbsUpdateTripData(this)">
                                        <option value="" data-price="0" data-days="1">-- <?php esc_html_e('Select Tour Package', 'nahian-tour-travel-booking-solution'); ?> --</option>
                                        <?php foreach ($trips as $t): ?>
                                            <option value="<?php echo esc_attr($t['id']); ?>" data-price="<?php echo esc_attr($t['price_adult']); ?>" data-days="<?php echo esc_attr($t['duration_days']); ?>" data-title="<?php echo esc_attr($t['title']); ?>">
                                                <?php echo esc_html($t['title'] . ' (' . $currency . ' ' . number_format_i18n((float)$t['price_adult'], 2) . ')'); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="nte-field-row">
                                    <label class="nte-field-label"><?php esc_html_e('Passenger Count *', 'nahian-tour-travel-booking-solution'); ?></label>
                                    <input type="number" name="num_travelers" id="nttbs_num_travelers" min="1" max="24" value="1" class="nte-control-input" required oninput="nttbsSyncTravelerRows(this.value)">
                                </div>
                            </div>

                            <div style="display:grid; grid-template-columns: repeat(3, 1fr); gap:16px;">
                                <div class="nte-field-row">
                                    <label class="nte-field-label"><?php esc_html_e('Designation', 'nahian-tour-travel-booking-solution'); ?></label>
                                    <input type="text" id="nttbs_package_name_disp" class="nte-control-input" placeholder="<?php esc_attr_e('Auto-resolved', 'nahian-tour-travel-booking-solution'); ?>" readonly>
                                </div>
                                <div class="nte-field-row">
                                    <label class="nte-field-label"><?php esc_html_e('Expedition Start', 'nahian-tour-travel-booking-solution'); ?></label>
                                    <input type="date" name="start_date" id="nttbs_start_date" class="nte-control-input" value="<?php echo date('Y-m-d'); ?>" onchange="nttbsCalculateEndDate()">
                                </div>
                                <div class="nte-field-row">
                                    <label class="nte-field-label"><?php esc_html_e('Expedition Conclusion', 'nahian-tour-travel-booking-solution'); ?></label>
                                    <input type="date" name="end_date" id="nttbs_end_date" class="nte-control-input" value="<?php echo date('Y-m-d', strtotime('+2 days')); ?>" readonly>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 2. Passenger Manifest Allocation -->
                    <div class="nte-white-panel">
                        <div class="nte-panel-header">
                            <h3>
                                <span class="nte-nav-icon icon-customers"><?php echo nttbs_svg_icon('customers', 18, 18); ?></span>
                                <span><?php esc_html_e('Manifest Passenger Roster', 'nahian-tour-travel-booking-solution'); ?></span>
                            </h3>
                            <button type="button" onclick="nttbsAddTravelerRow()" class="nte-btn-primary" style="padding:6px 12px; font-size:12px; background:var(--c-emerald);">
                                + <?php esc_html_e('Add Passenger', 'nahian-tour-travel-booking-solution'); ?>
                            </button>
                        </div>
                        <div class="nte-table-responsive">
                            <table class="nte-data-table" id="nttbs_travelers_table">
                                <thead>
                                    <tr>
                                        <th><?php esc_html_e('Full Passenger Name', 'nahian-tour-travel-booking-solution'); ?></th>
                                        <th><?php esc_html_e('Passenger Category', 'nahian-tour-travel-booking-solution'); ?></th>
                                        <th style="width:60px; text-align:center;"><?php esc_html_e('Drop', 'nahian-tour-travel-booking-solution'); ?></th>
                                    </tr>
                                </thead>
                                <tbody id="nttbs_travelers_tbody">
                                    <tr>
                                        <td><input type="text" name="traveler_name[]" class="nte-control-input" placeholder="<?php esc_attr_e('First and last name', 'nahian-tour-travel-booking-solution'); ?>" required></td>
                                        <td>
                                            <select name="traveler_type[]" class="nte-control-input" onchange="nttbsRecalculateTotals()">
                                                <option value="adult" data-mod="1.0"><?php esc_html_e('Adult (100% Tariff)', 'nahian-tour-travel-booking-solution'); ?></option>
                                                <option value="child" data-mod="0.6"><?php esc_html_e('Child (60% Tariff)', 'nahian-tour-travel-booking-solution'); ?></option>
                                                <option value="infant" data-mod="0.1"><?php esc_html_e('Infant (10% Tariff)', 'nahian-tour-travel-booking-solution'); ?></option>
                                            </select>
                                        </td>
                                        <td style="text-align:center;">
                                            <button type="button" onclick="nttbsRemoveRow(this)" class="nte-btn-icon-danger">&times;</button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- 3. Emergency Contacts -->
                    <div class="nte-white-panel">
                        <div class="nte-panel-header">
                            <h3>
                                <span class="nte-nav-icon"><?php echo nttbs_svg_icon('activity', 18, 18); ?></span>
                                <span><?php esc_html_e('Safety & Emergency Escorts', 'nahian-tour-travel-booking-solution'); ?></span>
                            </h3>
                            <button type="button" onclick="nttbsAddEmergencyRow()" class="nte-btn-primary" style="padding:6px 12px; font-size:12px; background:var(--c-teal);">
                                + <?php esc_html_e('Add Contact', 'nahian-tour-travel-booking-solution'); ?>
                            </button>
                        </div>
                        <div class="nte-table-responsive">
                            <table class="nte-data-table" id="nttbs_emergency_table">
                                <thead>
                                    <tr>
                                        <th><?php esc_html_e('Contact Name', 'nahian-tour-travel-booking-solution'); ?></th>
                                        <th><?php esc_html_e('Relation', 'nahian-tour-travel-booking-solution'); ?></th>
                                        <th><?php esc_html_e('Emergency Phone', 'nahian-tour-travel-booking-solution'); ?></th>
                                        <th style="width:60px; text-align:center;"><?php esc_html_e('Drop', 'nahian-tour-travel-booking-solution'); ?></th>
                                    </tr>
                                </thead>
                                <tbody id="nttbs_emergency_tbody">
                                    <tr>
                                        <td><input type="text" name="emergency_name[]" class="nte-control-input" placeholder="<?php esc_attr_e('Full contact name', 'nahian-tour-travel-booking-solution'); ?>"></td>
                                        <td><input type="text" name="emergency_rel[]" class="nte-control-input" placeholder="<?php esc_attr_e('e.g. Spouse / Agent', 'nahian-tour-travel-booking-solution'); ?>"></td>
                                        <td><input type="text" name="emergency_phone[]" class="nte-control-input" placeholder="+00 000 000 0000"></td>
                                        <td style="text-align:center;">
                                            <button type="button" onclick="nttbsRemoveRow(this)" class="nte-btn-icon-danger">&times;</button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- 4. Payment Tranches -->
                    <div class="nte-white-panel">
                        <div class="nte-panel-header">
                            <h3>
                                <span class="nte-nav-icon"><?php echo nttbs_svg_icon('wallet', 18, 18); ?></span>
                                <span><?php esc_html_e('Payment Tranches & POS Collection', 'nahian-tour-travel-booking-solution'); ?></span>
                            </h3>
                            <button type="button" onclick="nttbsAddPaymentRow()" class="nte-btn-primary" style="padding:6px 12px; font-size:12px; background:var(--c-amber);">
                                + <?php esc_html_e('Add Payment Record', 'nahian-tour-travel-booking-solution'); ?>
                            </button>
                        </div>
                        <div class="nte-table-responsive">
                            <table class="nte-data-table">
                                <thead>
                                    <tr>
                                        <th><?php esc_html_e('Collection Method', 'nahian-tour-travel-booking-solution'); ?></th>
                                        <th><?php esc_html_e('Reference / Receipt ID', 'nahian-tour-travel-booking-solution'); ?></th>
                                        <th><?php esc_html_e('Amount Collected', 'nahian-tour-travel-booking-solution'); ?> (<?php echo esc_html($currency); ?>)</th>
                                        <th style="width:60px; text-align:center;"><?php esc_html_e('Drop', 'nahian-tour-travel-booking-solution'); ?></th>
                                    </tr>
                                </thead>
                                <tbody id="nttbs_payment_tbody">
                                    <tr>
                                        <td>
                                            <select name="payment_method_name[]" class="nte-control-input">
                                                <option value="Counter Cash"><?php esc_html_e('Counter Cash', 'nahian-tour-travel-booking-solution'); ?></option>
                                                <option value="Credit Card"><?php esc_html_e('Terminal Card (POS)', 'nahian-tour-travel-booking-solution'); ?></option>
                                                <option value="Bank Transfer"><?php esc_html_e('Bank Wire', 'nahian-tour-travel-booking-solution'); ?></option>
                                                <option value="Online Gateway"><?php esc_html_e('Online Gateway', 'nahian-tour-travel-booking-solution'); ?></option>
                                            </select>
                                        </td>
                                        <td><input type="text" name="payment_ref[]" class="nte-control-input" placeholder="<?php esc_attr_e('Auth code / Reference', 'nahian-tour-travel-booking-solution'); ?>"></td>
                                        <td><input type="number" step="0.01" name="payment_amount[]" class="nte-control-input nte-pay-input" value="0.00" oninput="nttbsRecalculateTotals()"></td>
                                        <td style="text-align:center;">
                                            <button type="button" onclick="nttbsRemoveRow(this)" class="nte-btn-icon-danger">&times;</button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- 5. Lead Passenger Billing Details -->
                    <div class="nte-white-panel">
                        <div class="nte-panel-header">
                            <h3>
                                <span class="nte-nav-icon"><?php echo nttbs_svg_icon('clipboard', 18, 18); ?></span>
                                <span><?php esc_html_e('Billing Profile & Lead Purchaser', 'nahian-tour-travel-booking-solution'); ?></span>
                            </h3>
                        </div>
                        <div style="padding:20px;">
                            <div style="display:grid; grid-template-columns: repeat(2, 1fr); gap:16px;">
                                <div class="nte-field-row">
                                    <label class="nte-field-label"><?php esc_html_e('First Name *', 'nahian-tour-travel-booking-solution'); ?></label>
                                    <input type="text" name="billing_first_name" required class="nte-control-input" placeholder="<?php esc_attr_e('Passenger primary name', 'nahian-tour-travel-booking-solution'); ?>">
                                </div>
                                <div class="nte-field-row">
                                    <label class="nte-field-label"><?php esc_html_e('Last Name *', 'nahian-tour-travel-booking-solution'); ?></label>
                                    <input type="text" name="billing_last_name" required class="nte-control-input" placeholder="<?php esc_attr_e('Passenger surname', 'nahian-tour-travel-booking-solution'); ?>">
                                </div>
                            </div>
                            <div style="display:grid; grid-template-columns: repeat(2, 1fr); gap:16px;">
                                <div class="nte-field-row">
                                    <label class="nte-field-label"><?php esc_html_e('Email Address *', 'nahian-tour-travel-booking-solution'); ?></label>
                                    <input type="email" name="billing_email" required class="nte-control-input" placeholder="traveler@domain.com">
                                </div>
                                <div class="nte-field-row">
                                    <label class="nte-field-label"><?php esc_html_e('Contact Phone', 'nahian-tour-travel-booking-solution'); ?></label>
                                    <input type="text" name="billing_phone" class="nte-control-input" placeholder="+00 000 000 0000">
                                </div>
                            </div>
                            <div class="nte-field-row">
                                <label class="nte-field-label"><?php esc_html_e('Physical Address', 'nahian-tour-travel-booking-solution'); ?></label>
                                <input type="text" name="billing_address" class="nte-control-input" placeholder="<?php esc_attr_e('Suite, street address, building...', 'nahian-tour-travel-booking-solution'); ?>">
                            </div>
                            <div style="display:grid; grid-template-columns: repeat(2, 1fr); gap:16px;">
                                <div class="nte-field-row">
                                    <label class="nte-field-label"><?php esc_html_e('City', 'nahian-tour-travel-booking-solution'); ?></label>
                                    <input type="text" name="billing_city" class="nte-control-input" placeholder="e.g. Cairo">
                                </div>
                                <div class="nte-field-row">
                                    <label class="nte-field-label"><?php esc_html_e('Country', 'nahian-tour-travel-booking-solution'); ?></label>
                                    <input type="text" name="billing_country" class="nte-control-input" placeholder="e.g. Egypt">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 6. Special Operational Directives -->
                    <div class="nte-white-panel">
                        <div class="nte-panel-header">
                            <h3>
                                <span class="nte-nav-icon"><?php echo nttbs_svg_icon('settings', 18, 18); ?></span>
                                <span><?php esc_html_e('Special Operational Directives', 'nahian-tour-travel-booking-solution'); ?></span>
                            </h3>
                        </div>
                        <div style="padding:20px;">
                            <div class="nte-field-row" style="margin-bottom:0;">
                                <textarea name="special_notes" rows="3" class="nte-control-input" placeholder="<?php esc_attr_e('Dietary notes, medical alerts, charter guide coordination notes...', 'nahian-tour-travel-booking-solution'); ?>"></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Sticky Summary -->
                <div style="position:sticky; top:20px; display:flex; flex-direction:column; gap:20px;">
                    <div class="nte-white-panel" style="border-top:4px solid var(--c-blue);">
                        <div class="nte-panel-header">
                            <h3><?php esc_html_e('Tariff Breakdown', 'nahian-tour-travel-booking-solution'); ?></h3>
                        </div>
                        <div style="padding:20px; display:flex; flex-direction:column; gap:12px;">
                            <div style="display:flex; justify-content:space-between; font-size:13px;">
                                <span style="color:var(--text-muted);"><?php esc_html_e('Passenger Count:', 'nahian-tour-travel-booking-solution'); ?></span>
                                <strong id="summary_travelers_count" style="color:var(--text-main);">1</strong>
                            </div>
                            <div style="display:flex; justify-content:space-between; font-size:13px;">
                                <span style="color:var(--text-muted);"><?php esc_html_e('Gross Subtotal:', 'nahian-tour-travel-booking-solution'); ?></span>
                                <strong id="summary_subtotal_amount" style="color:var(--text-main);"><?php echo esc_html($currency); ?> 0.00</strong>
                            </div>
                            <div style="display:flex; justify-content:space-between; font-size:13px;">
                                <span style="color:var(--text-muted);"><?php printf(esc_html__('Tax (%s%%):', 'nahian-tour-travel-booking-solution'), esc_html($tax_percent)); ?></span>
                                <strong id="summary_tax_amount" style="color:var(--text-main);"><?php echo esc_html($currency); ?> 0.00</strong>
                            </div>
                            <div style="display:flex; justify-content:space-between; font-size:15px; font-weight:800; border-top:1px solid var(--border-light); padding-top:12px;">
                                <span><?php esc_html_e('Net Total:', 'nahian-tour-travel-booking-solution'); ?></span>
                                <strong style="color:var(--c-blue);" id="summary_total_amount"><?php echo esc_html($currency); ?> 0.00</strong>
                            </div>
                        </div>
                    </div>

                    <div class="nte-white-panel" style="border-top:4px solid var(--c-emerald);">
                        <div class="nte-panel-header">
                            <h3><?php esc_html_e('Payment Status & Audit', 'nahian-tour-travel-booking-solution'); ?></h3>
                        </div>
                        <div style="padding:20px; display:flex; flex-direction:column; gap:12px;">
                            <div style="display:flex; justify-content:space-between; font-size:13px;">
                                <span style="color:var(--text-muted);"><?php esc_html_e('Deposit (50% Standard):', 'nahian-tour-travel-booking-solution'); ?></span>
                                <strong id="summary_deposit_amount" style="color:var(--text-main);"><?php echo esc_html($currency); ?> 0.00</strong>
                            </div>
                            <div style="display:flex; justify-content:space-between; font-size:14px; font-weight:800;">
                                <span style="color:var(--c-emerald);"><?php esc_html_e('Total Collected:', 'nahian-tour-travel-booking-solution'); ?></span>
                                <strong style="color:var(--c-emerald);" id="summary_paid_amount"><?php echo esc_html($currency); ?> 0.00</strong>
                            </div>
                            <div style="display:flex; justify-content:space-between; font-size:14px; font-weight:800; border-top:1px solid var(--border-light); padding-top:12px;">
                                <span style="color:var(--c-rose);"><?php esc_html_e('Balance Due:', 'nahian-tour-travel-booking-solution'); ?></span>
                                <strong style="color:var(--c-rose);" id="summary_amount_due"><?php echo esc_html($currency); ?> 0.00</strong>
                            </div>
                            <input type="hidden" name="total_paid" id="nttbs_input_total_paid" value="0.00">
                        </div>
                    </div>

                    <div class="nte-white-panel">
                        <div class="nte-panel-header">
                            <h3><?php esc_html_e('Manifest Validation', 'nahian-tour-travel-booking-solution'); ?></h3>
                        </div>
                        <div style="padding:20px;">
                            <div class="nte-field-row" style="margin-bottom:16px;">
                                <label class="nte-field-label"><?php esc_html_e('Initial Ledger State', 'nahian-tour-travel-booking-solution'); ?></label>
                                <select name="booking_status" class="nte-control-input">
                                    <option value="confirmed"><?php esc_html_e('Confirmed & Locked', 'nahian-tour-travel-booking-solution'); ?></option>
                                    <option value="pending" selected><?php esc_html_e('Pending Verification', 'nahian-tour-travel-booking-solution'); ?></option>
                                    <option value="cancelled"><?php esc_html_e('Cancelled', 'nahian-tour-travel-booking-solution'); ?></option>
                                </select>
                            </div>

                            <button type="submit" name="nttbs_dispatch_comprehensive_booking" class="nte-btn-primary" style="width:100%; padding:12px; font-size:13px;">
                                <?php echo nttbs_svg_icon('check', 16, 16); ?>
                                <?php esc_html_e('Save & Issue Manifest', 'nahian-tour-travel-booking-solution'); ?>
                            </button>
                        </div>
                    </div>
                </div>

            </div>
        </form>

        <script>
        let nttbsActiveTripPrice = 0;
        let nttbsActiveTripDays  = 1;
        const nttbsCurrency      = <?php echo json_encode($currency); ?>;
        const nttbsTaxRate       = <?php echo floatval($tax_percent); ?>;

        function nttbsUpdateTripData(selectEl) {
            const opt = selectEl.options[selectEl.selectedIndex];
            nttbsActiveTripPrice = parseFloat(opt.getAttribute('data-price') || 0);
            nttbsActiveTripDays  = parseInt(opt.getAttribute('data-days') || 1, 10);
            const nameDisp       = document.getElementById('nttbs_package_name_disp');
            if (nameDisp) nameDisp.value = opt.getAttribute('data-title') || '';
            nttbsCalculateEndDate();
            nttbsRecalculateTotals();
        }

        function nttbsCalculateEndDate() {
            const startInput = document.getElementById('nttbs_start_date');
            const endInput   = document.getElementById('nttbs_end_date');
            if (!startInput || !endInput || !startInput.value) return;

            const date = new Date(startInput.value);
            date.setDate(date.getDate() + nttbsActiveTripDays);
            const y = date.getFullYear();
            const m = String(date.getMonth() + 1).padStart(2, '0');
            const d = String(date.getDate()).padStart(2, '0');
            endInput.value = `${y}-${m}-${d}`;
        }

        function nttbsSyncTravelerRows(targetCount) {
            const tbody = document.getElementById('nttbs_travelers_tbody');
            if (!tbody) return;
            const count = Math.max(1, parseInt(targetCount || 1, 10));
            while (tbody.rows.length < count) {
                nttbsAddTravelerRow();
            }
            while (tbody.rows.length > count) {
                tbody.deleteRow(tbody.rows.length - 1);
            }
            nttbsRecalculateTotals();
        }

        function nttbsAddTravelerRow() {
            const tbody = document.getElementById('nttbs_travelers_tbody');
            if (!tbody) return;
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td><input type="text" name="traveler_name[]" class="nte-control-input" placeholder="First and last name" required></td>
                <td>
                    <select name="traveler_type[]" class="nte-control-input" onchange="nttbsRecalculateTotals()">
                        <option value="adult" data-mod="1.0"><?php echo esc_js(__('Adult (100% Tariff)', 'nahian-tour-travel-booking-solution')); ?></option>
                        <option value="child" data-mod="0.6"><?php echo esc_js(__('Child (60% Tariff)', 'nahian-tour-travel-booking-solution')); ?></option>
                        <option value="infant" data-mod="0.1"><?php echo esc_js(__('Infant (10% Tariff)', 'nahian-tour-travel-booking-solution')); ?></option>
                    </select>
                </td>
                <td style="text-align:center;"><button type="button" onclick="nttbsRemoveRow(this)" class="nte-btn-icon-danger">&times;</button></td>
            `;
            tbody.appendChild(tr);
            const countInput = document.getElementById('nttbs_num_travelers');
            if (countInput) countInput.value = tbody.rows.length;
            nttbsRecalculateTotals();
        }

        function nttbsAddEmergencyRow() {
            const tbody = document.getElementById('nttbs_emergency_tbody');
            if (!tbody) return;
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td><input type="text" name="emergency_name[]" class="nte-control-input" placeholder="Full contact name"></td>
                <td><input type="text" name="emergency_rel[]" class="nte-control-input" placeholder="e.g. Spouse / Agent"></td>
                <td><input type="text" name="emergency_phone[]" class="nte-control-input" placeholder="+00 000 000 0000"></td>
                <td style="text-align:center;"><button type="button" onclick="nttbsRemoveRow(this)" class="nte-btn-icon-danger">&times;</button></td>
            `;
            tbody.appendChild(tr);
        }

        function nttbsAddPaymentRow() {
            const tbody = document.getElementById('nttbs_payment_tbody');
            if (!tbody) return;
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>
                    <select name="payment_method_name[]" class="nte-control-input">
                        <option value="Counter Cash"><?php echo esc_js(__('Counter Cash', 'nahian-tour-travel-booking-solution')); ?></option>
                        <option value="Credit Card"><?php echo esc_js(__('Terminal Card (POS)', 'nahian-tour-travel-booking-solution')); ?></option>
                        <option value="Bank Transfer"><?php echo esc_js(__('Bank Wire', 'nahian-tour-travel-booking-solution')); ?></option>
                        <option value="Online Gateway"><?php echo esc_js(__('Online Gateway', 'nahian-tour-travel-booking-solution')); ?></option>
                    </select>
                </td>
                <td><input type="text" name="payment_ref[]" class="nte-control-input" placeholder="Auth code / Reference"></td>
                <td><input type="number" step="0.01" name="payment_amount[]" class="nte-control-input nte-pay-input" value="0.00" oninput="nttbsRecalculateTotals()"></td>
                <td style="text-align:center;"><button type="button" onclick="nttbsRemoveRow(this)" class="nte-btn-icon-danger">&times;</button></td>
            `;
            tbody.appendChild(tr);
        }

        function nttbsRemoveRow(btn) {
            const row = btn.closest('tr');
            const tbody = row.closest('tbody');
            if (tbody && tbody.rows.length > 1) {
                row.remove();
                if (tbody.id === 'nttbs_travelers_tbody') {
                    const countInput = document.getElementById('nttbs_num_travelers');
                    if (countInput) countInput.value = tbody.rows.length;
                }
                nttbsRecalculateTotals();
            }
        }

        function nttbsRecalculateTotals() {
            const travelerRows = document.querySelectorAll('#nttbs_travelers_tbody tr');
            let subtotal = 0;

            travelerRows.forEach(row => {
                const select = row.querySelector('select[name="traveler_type[]"]');
                const mod = select ? parseFloat(select.options[select.selectedIndex].getAttribute('data-mod') || 1.0) : 1.0;
                subtotal += (nttbsActiveTripPrice * mod);
            });

            const tax   = subtotal * (nttbsTaxRate / 100);
            const total = subtotal + tax;

            let paid = 0;
            document.querySelectorAll('.nte-pay-input').forEach(input => {
                paid += parseFloat(input.value || 0);
            });

            const due = Math.max(0, total - paid);

            const elCount    = document.getElementById('summary_travelers_count');
            const elSubtotal = document.getElementById('summary_subtotal_amount');
            const elTax      = document.getElementById('summary_tax_amount');
            const elTotal    = document.getElementById('summary_total_amount');
            const elDeposit  = document.getElementById('summary_deposit_amount');
            const elPaid     = document.getElementById('summary_paid_amount');
            const elDue      = document.getElementById('summary_amount_due');

            if (elCount) elCount.textContent = travelerRows.length;
            if (elSubtotal) elSubtotal.textContent = `${nttbsCurrency} ${subtotal.toFixed(2)}`;
            if (elTax) elTax.textContent = `${nttbsCurrency} ${tax.toFixed(2)}`;
            if (elTotal) elTotal.textContent = `${nttbsCurrency} ${total.toFixed(2)}`;
            if (elDeposit) elDeposit.textContent = `${nttbsCurrency} ${(total * 0.5).toFixed(2)}`;
            if (elPaid) elPaid.textContent = `${nttbsCurrency} ${paid.toFixed(2)}`;
            if (elDue) elDue.textContent = `${nttbsCurrency} ${due.toFixed(2)}`;

            const elHiddenTotal = document.getElementById('nttbs_input_calculated_total');
            const elHiddenPaid  = document.getElementById('nttbs_input_total_paid');
            if (elHiddenTotal) elHiddenTotal.value = subtotal.toFixed(2);
            if (elHiddenPaid) elHiddenPaid.value = paid.toFixed(2);
        }

        document.addEventListener('DOMContentLoaded', function() {
            const tripSelect = document.getElementById('nttbs_trip_select');
            if (tripSelect && tripSelect.value) {
                nttbsUpdateTripData(tripSelect);
            } else {
                nttbsRecalculateTotals();
            }
        });
        </script>
    <?php endif; ?>

</div>
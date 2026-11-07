<?php
if (!defined('ABSPATH')) {
    exit;
}

/* ==========================================================================
   1. DASHBOARD QUICK POS DISPATCHER & ATOMIC TRANSACTION LOGIC
   ========================================================================== */

function nttbs_handle_dashboard_dispatchers() {
    global $wpdb;

    if (
        !isset($_POST['nttbs_dispatch_modal_booking']) ||
        !check_admin_referer('nttbs_modal_booking_nonce', 'nttbs_booking_token')
    ) {
        return;
    }

    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('Unauthorized administrative clearance.', 'nahian-tour-travel-booking-solution'), 403);
    }

    $dep_id     = isset($_POST['departure_id']) ? absint($_POST['departure_id']) : 0;
    $cust_name  = isset($_POST['customer_name']) ? sanitize_text_field(wp_unslash($_POST['customer_name'])) : '';
    $cust_email = isset($_POST['customer_email']) ? sanitize_email(wp_unslash($_POST['customer_email'])) : '';
    $cust_phone = isset($_POST['customer_phone']) ? sanitize_text_field(wp_unslash($_POST['customer_phone'])) : '';
    $pax_count  = isset($_POST['pax_count']) ? max(1, min(20, absint($_POST['pax_count']))) : 1;
    $pay_method = isset($_POST['payment_method']) ? sanitize_text_field(wp_unslash($_POST['payment_method'])) : 'Counter Cash';

    if (!$dep_id || empty($cust_name) || !is_email($cust_email)) {
        wp_safe_redirect(admin_url('admin.php?page=nttbs-core&view=dashboard&status=error_validation'));
        exit;
    }

    $table_departures = $wpdb->prefix . 'nttbs_departures';
    $table_trips      = $wpdb->prefix . 'nttbs_trips';
    $table_customers  = $wpdb->prefix . 'nttbs_customers';
    $table_seats      = $wpdb->prefix . 'nttbs_seats';
    $table_bookings   = $wpdb->prefix . 'nttbs_bookings';

    $departure = $wpdb->get_row($wpdb->prepare(
        "SELECT d.*, t.price_adult, t.id AS trip_id, t.title AS trip_title 
         FROM {$table_departures} d 
         INNER JOIN {$table_trips} t ON d.trip_id = t.id 
         WHERE d.id = %d", 
        $dep_id
    ), ARRAY_A);

    if (!$departure) {
        wp_safe_redirect(admin_url('admin.php?page=nttbs-core&view=dashboard&status=error_departure'));
        exit;
    }

    $booked_seats = (int) ($departure['booked_seats'] ?? 0);
    $total_seats  = (int) (!empty($departure['total_seats']) ? $departure['total_seats'] : 24);

    if (($booked_seats + $pax_count) > $total_seats) {
        wp_safe_redirect(admin_url('admin.php?page=nttbs-core&view=dashboard&status=error_overcapacity'));
        exit;
    }

    // Begin atomic transaction
    $wpdb->query('START TRANSACTION');

    try {
        $customer = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM {$table_customers} WHERE email = %s FOR UPDATE", 
            $cust_email
        ), ARRAY_A);

        if ($customer) {
            $cust_id = (int) $customer['id'];
            $wpdb->update(
                $table_customers,
                ['full_name' => $cust_name, 'phone' => $cust_phone],
                ['id' => $cust_id],
                ['%s', '%s'],
                ['%d']
            );
        } else {
            $wpdb->insert($table_customers, [
                'full_name'      => $cust_name,
                'email'          => $cust_email,
                'phone'          => $cust_phone,
                'total_bookings' => 0,
                'total_spent'    => 0.00
            ], ['%s', '%s', '%s', '%d', '%f']);
            $cust_id = (int) $wpdb->insert_id;
        }

        $unit_price = (float) $departure['price_adult'];
        $total      = (float) ($unit_price * $pax_count);
        $code       = 'NT-' . strtoupper(wp_generate_password(6, false, false));

        $avail_seats = [];
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_seats)) === $table_seats) {
            $avail_seats = $wpdb->get_col($wpdb->prepare(
                "SELECT seat_code FROM {$table_seats} 
                 WHERE departure_id = %d AND status = 'available' 
                 ORDER BY id ASC LIMIT %d FOR UPDATE",
                $dep_id, 
                $pax_count
            ));
        }

        $assigned_str = !empty($avail_seats) ? implode(', ', $avail_seats) : ('GEN-' . str_pad((string)$pax_count, 2, '0', STR_PAD_LEFT));

        $insert_ok = $wpdb->insert($table_bookings, [
            'booking_code'    => $code,
            'customer_id'     => $cust_id,
            'departure_id'    => $dep_id,
            'trip_id'         => $departure['trip_id'],
            'assigned_seats'  => $assigned_str,
            'adults'          => $pax_count,
            'children'        => 0,
            'total_pax'       => $pax_count,
            'subtotal_amount' => $total,
            'discount_amount' => 0.00,
            'total_amount'    => $total,
            'paid_amount'     => $total,
            'balance_due'     => 0.00,
            'payment_method'  => $pay_method,
            'payment_status'  => 'paid',
            'status'          => 'confirmed'
        ]);

        if (false === $insert_ok) {
            throw new Exception('Failed to record booking ledger.');
        }

        $b_id = (int) $wpdb->insert_id;

        if (!empty($avail_seats) && $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_seats)) === $table_seats) {
            foreach ($avail_seats as $s_code) {
                $wpdb->update($table_seats, [
                    'status'     => 'booked',
                    'booking_id' => $b_id
                ], [
                    'departure_id' => $dep_id,
                    'seat_code'    => $s_code
                ]);
            }
        }

        $wpdb->query($wpdb->prepare(
            "UPDATE {$table_departures} SET booked_seats = booked_seats + %d WHERE id = %d",
            $pax_count, 
            $dep_id
        ));

        $wpdb->query($wpdb->prepare(
            "UPDATE {$table_customers} SET total_bookings = total_bookings + 1, total_spent = total_spent + %f WHERE id = %d",
            $total, 
            $cust_id
        ));

        $wpdb->query('COMMIT');

        if (function_exists('nttbs_log_activity')) {
            nttbs_log_activity('DISPATCH_BOOKING', "Dispatched Manifest #{$code} for {$cust_name} ({$pax_count} Pax)");
        }

        wp_safe_redirect(admin_url('admin.php?page=nttbs-core&view=dashboard&status=booked&code=' . rawurlencode($code)));
        exit;

    } catch (Exception $e) {
        $wpdb->query('ROLLBACK');
        wp_safe_redirect(admin_url('admin.php?page=nttbs-core&view=dashboard&status=error_sql'));
        exit;
    }
}
nttbs_handle_dashboard_dispatchers();

/* ==========================================================================
   2. DATA REPOSITORIES & METRICS LAYER
   ========================================================================== */

$currency = get_option('nttbs_currency_symbol', NTTBS_CURRENCY_SYMBOL_DEFAULT);

$telemetry = function_exists('nttbs_get_financial_telemetry') 
    ? nttbs_get_financial_telemetry() 
    : [
        'revenue'    => 0.00,
        'receivable' => 0.00,
        'tickets'    => 0,
        'orders'     => 0
    ];

$departures = function_exists('nttbs_get_all_departures') ? nttbs_get_all_departures() : [];
$trips      = function_exists('nttbs_get_all_trips') ? nttbs_get_all_trips() : [];

$table_b = $wpdb->prefix . 'nttbs_bookings';
$table_c = $wpdb->prefix . 'nttbs_customers';
$table_t = $wpdb->prefix . 'nttbs_trips';
$table_d = $wpdb->prefix . 'nttbs_departures';

$recent_bookings = [];
if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_b)) === $table_b) {
    $recent_bookings = $wpdb->get_results(
        "SELECT b.*, c.full_name, c.email, t.title AS trip_title, d.departure_date, d.departure_time
         FROM {$table_b} b
         LEFT JOIN {$table_c} c ON b.customer_id = c.id
         LEFT JOIN {$table_t} t ON b.trip_id = t.id
         LEFT JOIN {$table_d} d ON b.departure_id = d.id
         ORDER BY b.id DESC LIMIT 6",
        ARRAY_A
    ) ?: [];
}

$active_trips_count = 0;
if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_t)) === $table_t) {
    $active_trips_count = (int) $wpdb->get_var("SELECT COUNT(id) FROM {$table_t}");
}

$status_flag = isset($_GET['status']) ? sanitize_key($_GET['status']) : '';
$status_code = isset($_GET['code']) ? sanitize_text_field(wp_unslash($_GET['code'])) : '';
?>

<!-- ========================================================================
     3. STYLESHEET EXTENSIONS (ENTERPRISE POLISH)
     ======================================================================== -->
<style>
    .nte-pro-shell {
        display: flex;
        flex-direction: column;
        gap: 24px;
        animation: nteFadeIn 0.3s cubic-bezier(0.16, 1, 0.3, 1);
    }
    @keyframes nteFadeIn {
        from { opacity: 0; transform: translateY(6px); }
        to { opacity: 1; transform: translateY(0); }
    }

    /* Alerts */
    .nte-notice-banner {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 14px 20px;
        border-radius: 10px;
        font-size: 13px;
        font-weight: 600;
        box-shadow: var(--shadow-sm);
    }
    .nte-notice-banner.notice-success {
        background-color: var(--c-emerald-bg);
        border: 1px solid var(--c-emerald-border);
        color: var(--c-emerald);
    }
    .nte-notice-banner.notice-danger {
        background-color: var(--c-rose-bg);
        border: 1px solid var(--c-rose-border);
        color: var(--c-rose);
    }

    /* Enterprise Headers & Panels */
    .nte-white-panel {
        background: var(--bg-surface);
        border: 1px solid var(--border-light);
        border-radius: 12px;
        box-shadow: var(--shadow-sm);
        overflow: hidden;
        display: flex;
        flex-direction: column;
        transition: border-color 0.2s ease, box-shadow 0.2s ease;
    }
    .nte-white-panel:hover {
        box-shadow: var(--shadow-md);
    }
    .nte-panel-header {
        padding: 16px 22px;
        border-bottom: 1px solid var(--border-light);
        display: flex;
        align-items: center;
        justify-content: space-between;
        background: linear-gradient(180deg, rgba(255,255,255,0.02) 0%, rgba(0,0,0,0.015) 100%);
    }
    .nte-panel-header h2, .nte-panel-header h3 {
        font-size: 14px;
        font-weight: 800;
        letter-spacing: -0.2px;
        color: var(--text-main);
        display: flex;
        align-items: center;
        gap: 8px;
        margin: 0;
    }
    .nte-panel-actions a {
        font-size: 12px;
        font-weight: 700;
        color: var(--c-blue);
        text-decoration: none;
        transition: color 0.15s ease;
    }
    .nte-panel-actions a:hover {
        color: #1d4ed8;
    }

    /* Enterprise Table Styling */
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
        letter-spacing: 0.6px;
        padding: 11px 18px;
        border-bottom: 1px solid var(--border-light);
        white-space: nowrap;
    }
    .nte-data-table td {
        padding: 14px 18px;
        border-bottom: 1px solid var(--border-light);
        color: var(--text-main);
        vertical-align: middle;
        background-color: transparent;
        transition: background-color 0.12s ease;
    }
    .nte-data-table tr:last-child td {
        border-bottom: none;
    }
    .nte-data-table tr:hover td {
        background-color: var(--bg-subtle);
    }

    /* Action Buttons */
    .nte-btn-emerald {
        background-color: var(--c-emerald);
        color: #ffffff;
        border: none;
        padding: 6px 13px;
        border-radius: 6px;
        font-size: 12px;
        font-weight: 700;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        transition: background-color 0.15s ease, transform 0.1s ease;
        box-shadow: 0 1px 2px rgba(5, 150, 105, 0.2);
    }
    .nte-btn-emerald:hover {
        background-color: #047857;
        transform: translateY(-1px);
    }
    .nte-btn-emerald:active {
        transform: translateY(0);
    }

    /* Visual Data Gauges */
    .nte-meter-bar {
        background-color: var(--bg-subtle);
        border: 1px solid var(--border-light);
        border-radius: 999px;
        height: 6px;
        width: 86px;
        overflow: hidden;
        margin-bottom: 5px;
    }
    .nte-meter-fill {
        height: 100%;
        border-radius: 999px;
        transition: width 0.3s ease;
    }
    .nte-meter-blue { background-color: var(--c-blue); }
    .nte-meter-amber { background-color: var(--c-amber); }
    .nte-meter-rose { background-color: var(--c-rose); }

    /* Clean Status Badges */
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

    /* Catalog Cards (Right Rail) */
    .nte-catalog-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 14px 18px;
        border-bottom: 1px solid var(--border-light);
        transition: background-color 0.15s ease;
    }
    .nte-catalog-item:last-child {
        border-bottom: none;
    }
    .nte-catalog-item:hover {
        background-color: var(--bg-subtle);
    }

    /* Layout Responsive Grid */
    .nte-dash-grid {
        display: grid;
        grid-template-columns: minmax(0, 2.3fr) minmax(0, 1fr);
        gap: 24px;
        align-items: start;
    }
    @media (max-width: 1200px) {
        .nte-dash-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<!-- ========================================================================
     4. MAIN DASHBOARD TEMPLATE VIEW
     ======================================================================== -->
<div class="nte-pro-shell">

    <?php if ($status_flag === 'booked'): ?>
        <div class="nte-notice-banner notice-success">
            <div style="display:flex; align-items:center; gap:8px;">
                <span class="nte-nav-icon"><?php echo nttbs_svg_icon('check', 18, 18); ?></span>
                <span><?php printf(esc_html__('Passenger manifest confirmed successfully. Issued Booking Code: %s', 'nahian-tour-travel-booking-solution'), '<strong>' . esc_html($status_code) . '</strong>'); ?></span>
            </div>
            <button type="button" onclick="this.parentElement.remove()" style="background:none; border:none; cursor:pointer; color:inherit; font-size:16px;">×</button>
        </div>
    <?php elseif (strpos($status_flag, 'error_') === 0): ?>
        <div class="nte-notice-banner notice-danger">
            <div style="display:flex; align-items:center; gap:8px;">
                <span class="nte-nav-icon"><?php echo nttbs_svg_icon('close', 18, 18); ?></span>
                <span>
                    <?php 
                    if ($status_flag === 'error_overcapacity') {
                        esc_html_e('Booking rejected: The requested passenger count exceeds available departure capacity.', 'nahian-tour-travel-booking-solution');
                    } else {
                        esc_html_e('Booking transaction failed. Please check your parameters and retry.', 'nahian-tour-travel-booking-solution');
                    }
                    ?>
                </span>
            </div>
            <button type="button" onclick="this.parentElement.remove()" style="background:none; border:none; cursor:pointer; color:inherit; font-size:16px;">×</button>
        </div>
    <?php endif; ?>

    <!-- Operational Telemetry Hero Banner -->
    <header class="nte-hero-telemetry">
        <div>
            <div style="font-size:11px; font-weight:800; color:var(--c-blue); letter-spacing:1px; margin-bottom:4px; text-transform:uppercase;">
                <?php esc_html_e('Global Fleet Telemetry & Manifest Ops', 'nahian-tour-travel-booking-solution'); ?>
            </div>
            <h1 style="font-size:24px; font-weight:900; color:var(--text-main); letter-spacing:-0.4px;">
                <?php esc_html_e('Tour Operations Center', 'nahian-tour-travel-booking-solution'); ?>
            </h1>
            <div style="font-size:13px; color:var(--text-muted); margin-top:5px; display:flex; align-items:center; flex-wrap:wrap; gap:12px;">
                <span><?php esc_html_e('Active Tour Lines:', 'nahian-tour-travel-booking-solution'); ?> <strong style="color:var(--text-main); font-weight:700;"><?php echo esc_html($active_trips_count); ?></strong></span>
                <span style="opacity:0.3;">|</span>
                <span><?php esc_html_e('Manifest Volume:', 'nahian-tour-travel-booking-solution'); ?> <strong style="color:var(--c-emerald); font-weight:800;"><?php echo esc_html($telemetry['tickets'] ?? 0); ?> <?php esc_html_e('Pax', 'nahian-tour-travel-booking-solution'); ?></strong></span>
                <span style="opacity:0.3;">|</span>
                <span style="display:inline-flex; align-items:center; gap:5px;">
                    <span style="display:inline-block; width:8px; height:8px; border-radius:50%; background-color:var(--c-emerald);"></span>
                    <strong style="color:var(--c-emerald); font-weight:700;"><?php esc_html_e('Core Engine Operational', 'nahian-tour-travel-booking-solution'); ?></strong>
                </span>
            </div>
        </div>
        <div class="nte-hero-clock-box">
            <div style="font-size:11px; color:var(--c-blue); font-weight:700; text-transform:uppercase; letter-spacing:0.4px;">
                <?php echo esc_html(current_time('l, jS F Y')); ?>
            </div>
            <div class="nte-hero-clock" id="nte-live-clock">--:--:--</div>
        </div>
    </header>

    <!-- Top KPI Metrical Matrix -->
    <section class="nte-kpi-deck">
        <div class="nte-kpi-card kpi-blue">
            <div>
                <div class="nte-kpi-title"><?php esc_html_e('Realized Revenue', 'nahian-tour-travel-booking-solution'); ?></div>
                <div class="nte-kpi-value" style="color:var(--c-blue);">
                    <?php echo esc_html($currency . ' ' . number_format_i18n((float)($telemetry['revenue'] ?? 0), 2)); ?>
                </div>
            </div>
            <div class="nte-kpi-icon-box"><?php echo nttbs_svg_icon('wallet', 22, 22); ?></div>
        </div>

        <div class="nte-kpi-card kpi-rose">
            <div>
                <div class="nte-kpi-title"><?php esc_html_e('Receivables Outstanding', 'nahian-tour-travel-booking-solution'); ?></div>
                <div class="nte-kpi-value" style="color:var(--c-rose);">
                    <?php echo esc_html($currency . ' ' . number_format_i18n((float)($telemetry['receivable'] ?? 0), 2)); ?>
                </div>
            </div>
            <div class="nte-kpi-icon-box"><?php echo nttbs_svg_icon('clock', 22, 22); ?></div>
        </div>

        <div class="nte-kpi-card kpi-emerald">
            <div>
                <div class="nte-kpi-title"><?php esc_html_e('Manifest Volume', 'nahian-tour-travel-booking-solution'); ?></div>
                <div class="nte-kpi-value" style="color:var(--c-emerald);">
                    <?php echo esc_html(number_format_i18n((int)($telemetry['tickets'] ?? 0))); ?> 
                    <small style="font-size:12px; color:var(--text-muted); font-weight:600;"><?php esc_html_e('Pax', 'nahian-tour-travel-booking-solution'); ?></small>
                </div>
            </div>
            <div class="nte-kpi-icon-box"><?php echo nttbs_svg_icon('bookings', 22, 22); ?></div>
        </div>

        <div class="nte-kpi-card kpi-amber">
            <div>
                <div class="nte-kpi-title"><?php esc_html_e('Executed Bookings', 'nahian-tour-travel-booking-solution'); ?></div>
                <div class="nte-kpi-value" style="color:var(--c-amber);">
                    <?php echo esc_html(number_format_i18n((int)($telemetry['orders'] ?? 0))); ?>
                </div>
            </div>
            <div class="nte-kpi-icon-box"><?php echo nttbs_svg_icon('clipboard', 22, 22); ?></div>
        </div>
    </section>

    <!-- Operational Work Area (Split Grid) -->
    <div class="nte-dash-grid">
        
        <!-- Left Column: Operations Schedules & Recent Records -->
        <div style="display:flex; flex-direction:column; gap:24px;">

            <!-- Upcoming Scheduled Departures -->
            <div class="nte-white-panel">
                <div class="nte-panel-header">
                    <h3>
                        <span class="nte-nav-icon icon-trips"><?php echo nttbs_svg_icon('compass', 18, 18); ?></span>
                        <span><?php esc_html_e('Active Fleet Manifests & Next Departures', 'nahian-tour-travel-booking-solution'); ?></span>
                    </h3>
                    <div class="nte-panel-actions">
                        <a href="<?php echo esc_url(admin_url('admin.php?page=nttbs-core&view=trips')); ?>">+ <?php esc_html_e('Configure New Tour', 'nahian-tour-travel-booking-solution'); ?></a>
                    </div>
                </div>

                <div class="nte-table-responsive">
                    <table class="nte-data-table">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Schedule', 'nahian-tour-travel-booking-solution'); ?></th>
                                <th><?php esc_html_e('Expedition & Fleet', 'nahian-tour-travel-booking-solution'); ?></th>
                                <th><?php esc_html_e('Capacity / Balance', 'nahian-tour-travel-booking-solution'); ?></th>
                                <th><?php esc_html_e('Manifest Load', 'nahian-tour-travel-booking-solution'); ?></th>
                                <th style="text-align:right;"><?php esc_html_e('Dispatch', 'nahian-tour-travel-booking-solution'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($departures)): ?>
                                <tr>
                                    <td colspan="5" style="text-align:center; padding:36px; color:var(--text-muted);">
                                        <?php esc_html_e('No active expedition schedules detected in the fleet dispatch engine.', 'nahian-tour-travel-booking-solution'); ?>
                                    </td>
                                </tr>
                            <?php else: foreach ($departures as $dep): 
                                $booked    = (int) ($dep['booked_seats'] ?? 0);
                                $total     = (int) (!empty($dep['total_seats']) ? $dep['total_seats'] : 24);
                                $remaining = max(0, $total - $booked);
                                $pct       = $total > 0 ? min(100, round(($booked / $total) * 100)) : 0;
                                
                                $gauge_class = 'nte-meter-blue';
                                if ($pct >= 90) {
                                    $gauge_class = 'nte-meter-rose';
                                } elseif ($pct >= 70) {
                                    $gauge_class = 'nte-meter-amber';
                                }
                            ?>
                                <tr>
                                    <td>
                                        <strong style="color:var(--text-main); font-weight:800;"><?php echo esc_html($dep['departure_date']); ?></strong><br>
                                        <span style="color:var(--text-muted); font-size:11px; font-weight:700;"><?php echo esc_html($dep['departure_time']); ?></span>
                                    </td>
                                    <td>
                                        <div style="font-weight:800; color:var(--text-main);"><?php echo esc_html($dep['trip_title']); ?></div>
                                        <div style="color:var(--c-blue); font-size:11px; font-weight:700; margin-top:2px;">
                                            <?php echo esc_html($dep['transport_type'] ?? 'Fleet Charter'); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div style="font-weight:800; font-size:13px;"><?php echo esc_html($booked . ' / ' . $total); ?> <span style="font-size:11px; color:var(--text-muted); font-weight:600;"><?php esc_html_e('Pax', 'nahian-tour-travel-booking-solution'); ?></span></div>
                                        <div style="color:<?php echo $remaining === 0 ? 'var(--c-rose)' : 'var(--c-emerald)'; ?>; font-size:11px; font-weight:800;">
                                            <?php echo $remaining === 0 ? esc_html__('Sold Out', 'nahian-tour-travel-booking-solution') : sprintf(esc_html__('%d Available', 'nahian-tour-travel-booking-solution'), $remaining); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="nte-meter-bar">
                                            <div class="nte-meter-fill <?php echo esc_attr($gauge_class); ?>" style="width:<?php echo esc_attr($pct); ?>%;"></div>
                                        </div>
                                        <span style="font-size:11px; font-weight:800; color:var(--text-muted); font-variant-numeric:tabular-nums;">
                                            <?php echo esc_html($pct); ?>% <?php esc_html_e('Filled', 'nahian-tour-travel-booking-solution'); ?>
                                        </span>
                                    </td>
                                    <td style="text-align:right;">
                                        <?php if ($remaining > 0): ?>
                                            <button 
                                                type="button" 
                                                onclick="nttbsOpenDrawer(<?php echo (int) $dep['id']; ?>, '<?php echo esc_js($dep['trip_title']); ?>', <?php echo (float) $dep['price_adult']; ?>)" 
                                                class="nte-btn-emerald"
                                            >
                                                <?php echo nttbs_svg_icon('check', 14, 14); ?>
                                                <?php esc_html_e('Dispatch Pax', 'nahian-tour-travel-booking-solution'); ?>
                                            </button>
                                        <?php else: ?>
                                            <button type="button" disabled class="nte-btn-emerald" style="opacity:0.4; cursor:not-allowed; background:var(--text-muted);">
                                                <?php esc_html_e('Full', 'nahian-tour-travel-booking-solution'); ?>
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Recent Passenger Bookings -->
            <div class="nte-white-panel">
                <div class="nte-panel-header">
                    <h3>
                        <span class="nte-nav-icon icon-bookings"><?php echo nttbs_svg_icon('clipboard', 18, 18); ?></span>
                        <span><?php esc_html_e('Recent Passenger Manifest Ledger', 'nahian-tour-travel-booking-solution'); ?></span>
                    </h3>
                    <div class="nte-panel-actions">
                        <a href="<?php echo esc_url(admin_url('admin.php?page=nttbs-core&view=bookings')); ?>"><?php esc_html_e('View Complete Ledger →', 'nahian-tour-travel-booking-solution'); ?></a>
                    </div>
                </div>

                <div class="nte-table-responsive">
                    <table class="nte-data-table">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Booking ID', 'nahian-tour-travel-booking-solution'); ?></th>
                                <th><?php esc_html_e('Passenger / Contact', 'nahian-tour-travel-booking-solution'); ?></th>
                                <th><?php esc_html_e('Expedition / Date', 'nahian-tour-travel-booking-solution'); ?></th>
                                <th><?php esc_html_e('Seat Manifest', 'nahian-tour-travel-booking-solution'); ?></th>
                                <th><?php esc_html_e('Total Paid', 'nahian-tour-travel-booking-solution'); ?></th>
                                <th><?php esc_html_e('Status', 'nahian-tour-travel-booking-solution'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recent_bookings)): ?>
                                <tr>
                                    <td colspan="6" style="text-align:center; padding:36px; color:var(--text-muted);">
                                        <?php esc_html_e('No booking transactions have been recorded in this sequence.', 'nahian-tour-travel-booking-solution'); ?>
                                    </td>
                                </tr>
                            <?php else: foreach ($recent_bookings as $rb): ?>
                                <tr>
                                    <td>
                                        <code style="font-family:ui-monospace, monospace; font-size:12px; font-weight:800; color:var(--c-blue); background:var(--c-blue-bg); padding:3px 6px; border-radius:4px; border:1px solid var(--c-blue-border);">
                                            <?php echo esc_html($rb['booking_code']); ?>
                                        </code>
                                    </td>
                                    <td>
                                        <strong style="color:var(--text-main); font-weight:800;"><?php echo esc_html($rb['full_name']); ?></strong><br>
                                        <span style="color:var(--text-muted); font-size:11px;"><?php echo esc_html($rb['email']); ?></span>
                                    </td>
                                    <td>
                                        <div style="font-weight:700; color:var(--text-main);"><?php echo esc_html($rb['trip_title']); ?></div>
                                        <span style="color:var(--text-muted); font-size:11px;"><?php echo esc_html($rb['departure_date'] ?? 'Immediate Departure'); ?></span>
                                    </td>
                                    <td>
                                        <span style="background-color:var(--c-purple-bg); color:var(--c-purple); border:1px solid var(--c-purple-border); padding:2px 7px; border-radius:6px; font-weight:800; font-size:11px; letter-spacing:0.3px;">
                                            <?php echo esc_html($rb['assigned_seats']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <strong style="font-weight:800; color:var(--text-main);">
                                            <?php echo esc_html($currency . ' ' . number_format_i18n((float)$rb['paid_amount'], 2)); ?>
                                        </strong>
                                    </td>
                                    <td>
                                        <?php $is_confirmed = ($rb['status'] === 'confirmed'); ?>
                                        <span class="nte-status-pill <?php echo $is_confirmed ? 'nte-status-confirmed' : 'nte-status-cancelled'; ?>">
                                            <?php echo esc_html(strtoupper($rb['status'])); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>

        <!-- Right Column: Catalog Summary & Live Operations Status -->
        <div style="display:flex; flex-direction:column; gap:24px;">

            <!-- Active Tour Catalog -->
            <div class="nte-white-panel">
                <div class="nte-panel-header">
                    <h3>
                        <span class="nte-nav-icon icon-trip_type"><?php echo nttbs_svg_icon('price_cat', 18, 18); ?></span>
                        <span><?php esc_html_e('Configured Tour Lines', 'nahian-tour-travel-booking-solution'); ?></span>
                    </h3>
                </div>
                
                <div>
                    <?php if (empty($trips)): ?>
                        <div style="padding:24px; text-align:center; color:var(--text-muted); font-size:13px;">
                            <?php esc_html_e('No travel packages configured.', 'nahian-tour-travel-booking-solution'); ?>
                        </div>
                    <?php else: foreach (array_slice($trips, 0, 5) as $t): ?>
                        <div class="nte-catalog-item">
                            <div>
                                <div style="font-size:13px; font-weight:800; color:var(--text-main); margin-bottom:2px;">
                                    <?php echo esc_html($t['title']); ?>
                                </div>
                                <div style="font-size:11px; color:var(--text-muted); font-weight:600;">
                                    <?php echo esc_html($t['duration_days']); ?>D / <?php echo esc_html($t['duration_nights']); ?>N
                                    <span style="opacity:0.4;">•</span>
                                    <span style="color:var(--c-teal); font-weight:700;"><?php echo esc_html($t['destination_name'] ?? 'Harbor Station'); ?></span>
                                </div>
                            </div>
                            <div style="font-size:13px; font-weight:900; color:var(--c-blue); text-align:right;">
                                <?php echo esc_html($currency . ' ' . number_format_i18n((float)$t['price_adult'], 2)); ?>
                            </div>
                        </div>
                    <?php endforeach; endif; ?>
                </div>
            </div>

            <!-- Operations Dispatch Directive Card -->
            <div style="background-color:var(--c-teal-bg); border:1px solid var(--c-teal-border); border-left:4px solid var(--c-teal); border-radius:12px; padding:20px; box-shadow:var(--shadow-sm);">
                <div style="font-size:13px; font-weight:800; color:var(--c-teal); margin-bottom:6px; display:flex; align-items:center; gap:8px;">
                    <span class="nte-nav-icon" style="color:var(--c-teal);"><?php echo nttbs_svg_icon('check', 16, 16); ?></span>
                    <?php esc_html_e('Fleet Concurrency Protocol', 'nahian-tour-travel-booking-solution'); ?>
                </div>
                <p style="font-size:12px; color:var(--text-muted); line-height:1.6; margin:0;">
                    <?php esc_html_e('Real-time database locks prevent passenger seat contention during counter checkout. Departure loads automatically reflect immediate checkouts across the global reservation engine.', 'nahian-tour-travel-booking-solution'); ?>
                </p>
            </div>

        </div>

    </div>

</div>
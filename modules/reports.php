<?php
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;

/* ==========================================================================
   1. FILTER INPUTS & QUERY BUILDERS
   ========================================================================== */

$current_subtab = isset($_GET['subtab']) && in_array($_GET['subtab'], ['overview', 'tours', 'audit'], true) ? sanitize_key($_GET['subtab']) : 'overview';
$start_date     = isset($_GET['start_date']) ? sanitize_text_field(wp_unslash($_GET['start_date'])) : date('Y-m-01');
$end_date       = isset($_GET['end_date']) ? sanitize_text_field(wp_unslash($_GET['end_date'])) : date('Y-m-d');
$currency       = get_option('nttbs_currency_symbol', NTTBS_CURRENCY_SYMBOL_DEFAULT);

$b_table = $wpdb->prefix . 'nttbs_bookings';
$t_table = $wpdb->prefix . 'nttbs_trips';
$l_table = $wpdb->prefix . 'nttbs_audit_logs';

$has_b = ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $b_table)) === $b_table);
$has_t = ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $t_table)) === $t_table);
$has_l = ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $l_table)) === $l_table);

$period_revenue    = 0.00;
$period_receivable = 0.00;
$period_tickets    = 0;
$period_orders     = 0;
$payment_channels  = [];
$tour_performance  = [];
$logs              = [];

if ($has_b) {
    $range_where = $wpdb->prepare(
        "WHERE status != 'cancelled' AND DATE(created_at) >= %s AND DATE(created_at) <= %s",
        $start_date,
        $end_date
    );

    $period_revenue    = (float) $wpdb->get_var("SELECT SUM(paid_amount) FROM {$b_table} {$range_where}");
    $period_receivable = (float) $wpdb->get_var("SELECT SUM(balance_due) FROM {$b_table} {$range_where}");
    $period_tickets    = (int) $wpdb->get_var("SELECT SUM(total_pax) FROM {$b_table} {$range_where}");
    $period_orders     = (int) $wpdb->get_var("SELECT COUNT(id) FROM {$b_table} {$range_where}");

    // Payment Method Distribution
    $payment_channels = $wpdb->get_results(
        "SELECT payment_method, COUNT(id) AS count, SUM(paid_amount) AS total_collected 
         FROM {$b_table} 
         WHERE status != 'cancelled' 
         GROUP BY payment_method 
         ORDER BY total_collected DESC",
        ARRAY_A
    ) ?: [];
}

// Calculate total across all payment channels for percentage distribution
$total_channel_revenue = 0.00;
foreach ($payment_channels as $pc) {
    $total_channel_revenue += (float) $pc['total_collected'];
}

// Performance by Tour Package
if ($has_t && $has_b) {
    $tour_performance = $wpdb->get_results(
        "SELECT t.id, t.title, 
                COUNT(b.id) AS total_bookings, 
                SUM(b.total_pax) AS total_pax, 
                SUM(b.total_amount) AS total_gross, 
                SUM(b.paid_amount) AS total_paid,
                SUM(b.balance_due) AS total_due
         FROM {$t_table} t
         LEFT JOIN {$b_table} b ON t.id = b.trip_id AND b.status != 'cancelled'
         GROUP BY t.id, t.title
         ORDER BY total_paid DESC",
        ARRAY_A
    ) ?: [];
}

// System Audit Logs
if ($has_l) {
    $logs = $wpdb->get_results("SELECT * FROM {$l_table} ORDER BY id DESC LIMIT 50", ARRAY_A) ?: [];
}
?>

<style>
.nte-rep-shell {
    display: flex;
    flex-direction: column;
    gap: 20px;
    animation: nteFadeIn 0.25s ease-out;
}
@keyframes nteFadeIn {
    from { opacity: 0; transform: translateY(4px); }
    to { opacity: 1; transform: translateY(0); }
}

.nte-subnav-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid var(--border-light);
    padding-bottom: 16px;
    flex-wrap: wrap;
    gap: 12px;
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

.nte-filter-box {
    display: flex;
    align-items: center;
    gap: 8px;
    background: var(--bg-surface);
    padding: 4px 6px;
    border-radius: 8px;
    border: 1px solid var(--border-light);
}

.nte-white-panel {
    background: var(--bg-surface);
    border: 1px solid var(--border-light);
    border-radius: 12px;
    box-shadow: var(--shadow-sm);
    overflow: hidden;
    padding: 20px;
    margin-bottom: 20px;
}
.nte-white-panel:last-child {
    margin-bottom: 0;
}
.nte-panel-header {
    margin: -20px -20px 20px -20px;
    padding: 16px 20px;
    border-bottom: 1px solid var(--border-light);
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: linear-gradient(180deg, rgba(255,255,255,0.02) 0%, rgba(0,0,0,0.02) 100%);
    font-size: 14px;
    font-weight: 800;
    color: var(--text-main);
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
    padding: 11px 16px;
    border-bottom: 1px solid var(--border-light);
    white-space: nowrap;
}
.nte-data-table td {
    padding: 14px 16px;
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

.nte-metric-card {
    border-radius: 10px;
    padding: 18px;
    border: 1px solid transparent;
}
.nte-metric-card.card-blue {
    background-color: var(--c-blue-bg);
    border-color: var(--c-blue-border);
}
.nte-metric-card.card-rose {
    background-color: var(--c-rose-bg);
    border-color: var(--c-rose-border);
}
.nte-metric-card.card-emerald {
    background-color: var(--c-emerald-bg);
    border-color: var(--c-emerald-border);
}
.nte-metric-card.card-amber {
    background-color: var(--c-amber-bg);
    border-color: var(--c-amber-border);
}

.nte-metric-label {
    font-size: 11px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.nte-metric-value {
    font-size: 24px;
    font-weight: 900;
    margin-top: 4px;
}
</style>

<div class="nte-rep-shell">

    <!-- Subnav Tabs & Range Filter Header -->
    <div class="nte-subnav-bar">
        <div style="display:flex; gap:10px;">
            <a href="<?php echo esc_url(admin_url('admin.php?page=nttbs-core&view=reports&subtab=overview&start_date=' . urlencode($start_date) . '&end_date=' . urlencode($end_date))); ?>" 
               class="nte-pill-toggle <?php echo $current_subtab === 'overview' ? 'active' : 'inactive'; ?>">
                <span class="nte-nav-icon"><?php echo nttbs_svg_icon('reports', 16, 16); ?></span>
                <?php esc_html_e('Financial Overview', 'nahian-tour-travel-booking-solution'); ?>
            </a>
            <a href="<?php echo esc_url(admin_url('admin.php?page=nttbs-core&view=reports&subtab=tours&start_date=' . urlencode($start_date) . '&end_date=' . urlencode($end_date))); ?>" 
               class="nte-pill-toggle <?php echo $current_subtab === 'tours' ? 'active' : 'inactive'; ?>">
                <span class="nte-nav-icon"><?php echo nttbs_svg_icon('trips', 16, 16); ?></span>
                <?php esc_html_e('Tour Performance', 'nahian-tour-travel-booking-solution'); ?>
            </a>
            <a href="<?php echo esc_url(admin_url('admin.php?page=nttbs-core&view=reports&subtab=audit&start_date=' . urlencode($start_date) . '&end_date=' . urlencode($end_date))); ?>" 
               class="nte-pill-toggle <?php echo $current_subtab === 'audit' ? 'active' : 'inactive'; ?>">
                <span class="nte-nav-icon"><?php echo nttbs_svg_icon('clipboard', 16, 16); ?></span>
                <?php esc_html_e('Audit Trail', 'nahian-tour-travel-booking-solution'); ?>
            </a>
        </div>

        <!-- Date Filter Form -->
        <form method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>" class="nte-filter-box">
            <input type="hidden" name="page" value="nttbs-core">
            <input type="hidden" name="view" value="reports">
            <input type="hidden" name="subtab" value="<?php echo esc_attr($current_subtab); ?>">

            <input type="date" name="start_date" value="<?php echo esc_attr($start_date); ?>" class="nte-control-input" style="padding:4px 8px; font-size:12px; width:auto;">
            <span style="font-size:12px; color:var(--text-muted); font-weight:700;"><?php esc_html_e('to', 'nahian-tour-travel-booking-solution'); ?></span>
            <input type="date" name="end_date" value="<?php echo esc_attr($end_date); ?>" class="nte-control-input" style="padding:4px 8px; font-size:12px; width:auto;">
            
            <button type="submit" class="nte-btn-primary" style="padding:6px 14px; font-size:12px;"><?php esc_html_e('Apply Range', 'nahian-tour-travel-booking-solution'); ?></button>
        </form>
    </div>

    <?php if ($current_subtab === 'overview'): ?>
        <!-- TAB 1: EXECUTIVE FINANCIAL OVERVIEW -->
        <div class="nte-white-panel">
            <div class="nte-panel-header">
                <span><?php esc_html_e('Executive Financial Ledger Metrics', 'nahian-tour-travel-booking-solution'); ?></span>
                <small style="color:var(--text-muted); font-size:12px; font-weight:600;">
                    <?php echo esc_html($start_date . ' ' . __('to', 'nahian-tour-travel-booking-solution') . ' ' . $end_date); ?>
                </small>
            </div>
            
            <div style="display:grid; grid-template-columns: repeat(4, 1fr); gap:16px;">
                <div class="nte-metric-card card-blue">
                    <div class="nte-metric-label" style="color:var(--c-blue);"><?php esc_html_e('Settled Inward Revenue', 'nahian-tour-travel-booking-solution'); ?></div>
                    <div class="nte-metric-value" style="color:var(--c-blue);">
                        <?php echo esc_html($currency . ' ' . number_format_i18n((float)$period_revenue, 2)); ?>
                    </div>
                    <small style="color:var(--text-muted); font-size:11px;"><?php esc_html_e('Cleared cashflow', 'nahian-tour-travel-booking-solution'); ?></small>
                </div>

                <div class="nte-metric-card card-rose">
                    <div class="nte-metric-label" style="color:var(--c-rose);"><?php esc_html_e('Outstanding Receivables', 'nahian-tour-travel-booking-solution'); ?></div>
                    <div class="nte-metric-value" style="color:var(--c-rose);">
                        <?php echo esc_html($currency . ' ' . number_format_i18n((float)$period_receivable, 2)); ?>
                    </div>
                    <small style="color:var(--text-muted); font-size:11px;"><?php esc_html_e('Due on arrival / departure', 'nahian-tour-travel-booking-solution'); ?></small>
                </div>

                <div class="nte-metric-card card-emerald">
                    <div class="nte-metric-label" style="color:var(--c-emerald);"><?php esc_html_e('Manifest Volume', 'nahian-tour-travel-booking-solution'); ?></div>
                    <div class="nte-metric-value" style="color:var(--c-emerald);">
                        <?php echo esc_html(number_format_i18n((int)$period_tickets)); ?> <small style="font-size:14px; font-weight:700;"><?php esc_html_e('Pax', 'nahian-tour-travel-booking-solution'); ?></small>
                    </div>
                    <small style="color:var(--text-muted); font-size:11px;"><?php esc_html_e('Total registered passengers', 'nahian-tour-travel-booking-solution'); ?></small>
                </div>

                <div class="nte-metric-card card-amber">
                    <div class="nte-metric-label" style="color:var(--c-amber);"><?php esc_html_e('Reservation Passes', 'nahian-tour-travel-booking-solution'); ?></div>
                    <div class="nte-metric-value" style="color:var(--c-amber);">
                        <?php echo esc_html(number_format_i18n((int)$period_orders)); ?> <small style="font-size:14px; font-weight:700;"><?php esc_html_e('Orders', 'nahian-tour-travel-booking-solution'); ?></small>
                    </div>
                    <small style="color:var(--text-muted); font-size:11px;"><?php esc_html_e('Confirmed booking files', 'nahian-tour-travel-booking-solution'); ?></small>
                </div>
            </div>
        </div>

        <!-- Payment Channels Distribution -->
        <div class="nte-white-panel">
            <div class="nte-panel-header">
                <span><?php esc_html_e('Payment Channel Liquidity Breakdown', 'nahian-tour-travel-booking-solution'); ?></span>
            </div>
            <div class="nte-table-responsive">
                <table class="nte-data-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Payment Channel / Gateway', 'nahian-tour-travel-booking-solution'); ?></th>
                            <th><?php esc_html_e('Transaction Frequency', 'nahian-tour-travel-booking-solution'); ?></th>
                            <th><?php esc_html_e('Net Settled Volume', 'nahian-tour-travel-booking-solution'); ?></th>
                            <th><?php esc_html_e('Portfolio Share', 'nahian-tour-travel-booking-solution'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($payment_channels)): ?>
                            <tr><td colspan="4" style="text-align:center; padding:36px; color:var(--text-muted);"><?php esc_html_e('No transaction metrics found for the selected timeframe.', 'nahian-tour-travel-booking-solution'); ?></td></tr>
                        <?php else: foreach ($payment_channels as $pc): 
                            $share = $total_channel_revenue > 0 ? round(($pc['total_collected'] / $total_channel_revenue) * 100, 1) : 0;
                        ?>
                            <tr>
                                <td>
                                    <strong style="font-size:13px; color:var(--text-main);"><?php echo esc_html($pc['payment_method']); ?></strong>
                                </td>
                                <td>
                                    <?php echo esc_html(number_format_i18n((int)$pc['count'])); ?> <?php esc_html_e('transactions', 'nahian-tour-travel-booking-solution'); ?>
                                </td>
                                <td>
                                    <strong style="color:var(--c-emerald); font-size:13px; font-weight:800;">
                                        <?php echo esc_html($currency . ' ' . number_format_i18n((float)$pc['total_collected'], 2)); ?>
                                    </strong>
                                </td>
                                <td>
                                    <div style="display:flex; align-items:center; gap:10px;">
                                        <div style="background:var(--bg-subtle); border:1px solid var(--border-light); border-radius:999px; height:6px; width:120px; overflow:hidden;">
                                            <div style="width:<?php echo esc_attr(min(100, $share)); ?>%; background:var(--c-blue); height:100%; border-radius:999px;"></div>
                                        </div>
                                        <span style="font-size:11px; font-weight:800; color:var(--text-muted); font-variant-numeric:tabular-nums;"><?php echo esc_html($share); ?>%</span>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    <?php elseif ($current_subtab === 'tours'): ?>
        <!-- TAB 2: TOUR-BY-TOUR FINANCIAL PERFORMANCE -->
        <div class="nte-white-panel">
            <div class="nte-panel-header">
                <span><?php esc_html_e('Tour Package Commercial Ledger & Occupancy Yield', 'nahian-tour-travel-booking-solution'); ?></span>
            </div>
            <div class="nte-table-responsive">
                <table class="nte-data-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Tour Line Expedition', 'nahian-tour-travel-booking-solution'); ?></th>
                            <th><?php esc_html_e('Bookings', 'nahian-tour-travel-booking-solution'); ?></th>
                            <th><?php esc_html_e('Passenger Load', 'nahian-tour-travel-booking-solution'); ?></th>
                            <th><?php esc_html_e('Gross Invoiced', 'nahian-tour-travel-booking-solution'); ?></th>
                            <th><?php esc_html_e('Net Realized', 'nahian-tour-travel-booking-solution'); ?></th>
                            <th><?php esc_html_e('Receivable', 'nahian-tour-travel-booking-solution'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($tour_performance)): ?>
                            <tr><td colspan="6" style="text-align:center; padding:36px; color:var(--text-muted);"><?php esc_html_e('No expedition performance data registered.', 'nahian-tour-travel-booking-solution'); ?></td></tr>
                        <?php else: foreach ($tour_performance as $tp): ?>
                            <tr>
                                <td>
                                    <strong style="font-size:13px; color:var(--text-main);"><?php echo esc_html($tp['title']); ?></strong>
                                </td>
                                <td>
                                    <?php echo esc_html(number_format_i18n((int)($tp['total_bookings'] ?: 0))); ?> <?php esc_html_e('orders', 'nahian-tour-travel-booking-solution'); ?>
                                </td>
                                <td>
                                    <strong style="color:var(--text-main); font-weight:800;">
                                        <?php echo esc_html(number_format_i18n((int)($tp['total_pax'] ?: 0))); ?>
                                    </strong>
                                    <span style="font-size:11px; color:var(--text-muted); font-weight:600;"><?php esc_html_e('Pax', 'nahian-tour-travel-booking-solution'); ?></span>
                                </td>
                                <td>
                                    <?php echo esc_html($currency . ' ' . number_format_i18n((float)$tp['total_gross'], 2)); ?>
                                </td>
                                <td>
                                    <strong style="color:var(--c-emerald); font-weight:800;">
                                        <?php echo esc_html($currency . ' ' . number_format_i18n((float)$tp['total_paid'], 2)); ?>
                                    </strong>
                                </td>
                                <td>
                                    <strong style="color:var(--c-rose); font-weight:800;">
                                        <?php echo esc_html($currency . ' ' . number_format_i18n((float)$tp['total_due'], 2)); ?>
                                    </strong>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    <?php elseif ($current_subtab === 'audit'): ?>
        <!-- TAB 3: SYSTEM AUDIT & SECURITY TRAIL -->
        <div class="nte-white-panel">
            <div class="nte-panel-header">
                <span><?php esc_html_e('System Audit Trail & Security Ledger', 'nahian-tour-travel-booking-solution'); ?></span>
                <small style="color:var(--text-muted); font-size:12px; font-weight:600;"><?php esc_html_e('Last 50 recorded operational transactions', 'nahian-tour-travel-booking-solution'); ?></small>
            </div>
            <div class="nte-table-responsive">
                <table class="nte-data-table">
                    <thead>
                        <tr>
                            <th style="width:140px;"><?php esc_html_e('Timestamp', 'nahian-tour-travel-booking-solution'); ?></th>
                            <th><?php esc_html_e('Operator User', 'nahian-tour-travel-booking-solution'); ?></th>
                            <th><?php esc_html_e('Event Action', 'nahian-tour-travel-booking-solution'); ?></th>
                            <th><?php esc_html_e('Action Description', 'nahian-tour-travel-booking-solution'); ?></th>
                            <th><?php esc_html_e('IP Address', 'nahian-tour-travel-booking-solution'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($logs)): ?>
                            <tr><td colspan="5" style="text-align:center; padding:36px; color:var(--text-muted);"><?php esc_html_e('No operational event logs recorded in system.', 'nahian-tour-travel-booking-solution'); ?></td></tr>
                        <?php else: foreach ($logs as $log): ?>
                            <tr>
                                <td>
                                    <small style="color:var(--text-muted); font-weight:700; font-family:ui-monospace, monospace;"><?php echo esc_html($log['created_at']); ?></small>
                                </td>
                                <td>
                                    <strong style="color:var(--text-main); font-weight:800;"><?php echo esc_html($log['user_login']); ?></strong>
                                </td>
                                <td>
                                    <code style="background:var(--c-blue-bg); color:var(--c-blue); border:1px solid var(--c-blue-border); font-size:10px; font-weight:800; padding:2px 6px; border-radius:4px; font-family:ui-monospace, monospace;">
                                        <?php echo esc_html($log['action_type']); ?>
                                    </code>
                                </td>
                                <td>
                                    <span style="font-size:12px; color:var(--text-main); line-height:1.4; display:inline-block;"><?php echo esc_html($log['description']); ?></span>
                                </td>
                                <td>
                                    <code style="font-size:11px; background:var(--bg-subtle); border:1px solid var(--border-light); padding:2px 6px; border-radius:4px; color:var(--text-muted); font-family:ui-monospace, monospace;"><?php echo esc_html($log['ip_address']); ?></code>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>

</div>
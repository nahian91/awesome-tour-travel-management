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
.nttbs-rep-shell {
    display: flex;
    flex-direction: column;
    gap: 24px;
    animation: nttbsFadeIn 0.3s cubic-bezier(0.16, 1, 0.3, 1);
}
@keyframes nttbsFadeIn {
    from { opacity: 0; transform: translateY(6px); }
    to { opacity: 1; transform: translateY(0); }
}

/* Subnav Tabs & Header */
.nttbs-subnav-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid var(--nttbs-border);
    padding-bottom: 18px;
    flex-wrap: wrap;
    gap: 12px;
}
.nttbs-subnav-group {
    display: flex;
    align-items: center;
    gap: 10px;
}
.nttbs-pill-toggle {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 9px 18px;
    border-radius: 10px;
    font-size: 13px;
    font-weight: 700;
    text-decoration: none;
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}
.nttbs-pill-toggle.active {
    background: linear-gradient(135deg, var(--nttbs-primary), var(--nttbs-primary-dark));
    color: #ffffff;
    box-shadow: 0 4px 12px rgba(37, 99, 235, 0.25);
}
.nttbs-pill-toggle.inactive {
    background-color: var(--nttbs-bg-card);
    color: var(--nttbs-text-muted);
    border: 1px solid var(--nttbs-border);
}
.nttbs-pill-toggle.inactive:hover {
    background-color: var(--nttbs-bg-hover);
    color: var(--nttbs-text-heading);
    transform: translateY(-1px);
}

/* Filter Controls */
.nttbs-filter-box {
    display: flex;
    align-items: center;
    gap: 10px;
    background: var(--nttbs-bg-card);
    padding: 6px 10px;
    border-radius: 10px;
    border: 1px solid var(--nttbs-border);
    box-shadow: var(--nttbs-shadow-xs);
}

/* Panels */
.nttbs-white-panel {
    background: var(--nttbs-bg-card);
    border: 1px solid var(--nttbs-border);
    border-radius: 14px;
    box-shadow: var(--nttbs-shadow-sm);
    overflow: hidden;
    padding: 24px;
    margin-bottom: 22px;
    transition: box-shadow 0.2s ease, border-color 0.2s ease;
}
.nttbs-white-panel:last-child {
    margin-bottom: 0;
}
.nttbs-white-panel:hover {
    box-shadow: var(--nttbs-shadow-md);
}
.nttbs-panel-header {
    margin: -24px -24px 24px -24px;
    padding: 18px 24px;
    border-bottom: 1px solid var(--nttbs-border);
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: linear-gradient(180deg, #ffffff 0%, var(--nttbs-bg-body) 100%);
    font-size: 14.5px;
    font-weight: 800;
    color: var(--nttbs-text-heading);
    letter-spacing: -0.2px;
}

/* Tables */
.nttbs-table-responsive {
    width: 100%;
    overflow-x: auto;
}
.nttbs-data-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    font-size: 13px;
    text-align: left;
}
.nttbs-data-table th {
    background-color: var(--nttbs-bg-body);
    color: var(--nttbs-text-muted);
    font-size: 11px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.6px;
    padding: 12px 20px;
    border-bottom: 1px solid var(--nttbs-border);
    white-space: nowrap;
}
.nttbs-data-table td {
    padding: 15px 20px;
    border-bottom: 1px solid var(--nttbs-border);
    color: var(--nttbs-text-body);
    vertical-align: middle;
    transition: background-color 0.12s ease;
}
.nttbs-data-table tr:last-child td {
    border-bottom: none;
}
.nttbs-data-table tr:hover td {
    background-color: var(--nttbs-bg-hover);
}

/* Metric Cards */
.nttbs-metric-deck {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 18px;
}
@media (max-width: 1100px) {
    .nttbs-metric-deck {
        grid-template-columns: repeat(2, 1fr);
    }
}
@media (max-width: 600px) {
    .nttbs-metric-deck {
        grid-template-columns: 1fr;
    }
}

.nttbs-metric-card {
    border-radius: 12px;
    padding: 20px;
    border: 1px solid transparent;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}
.nttbs-metric-card:hover {
    transform: translateY(-2px);
    box-shadow: var(--nttbs-shadow-md);
}
.nttbs-metric-card.card-blue {
    background-color: var(--nttbs-primary-subtle);
    border-color: var(--nttbs-primary-border);
}
.nttbs-metric-card.card-rose {
    background-color: var(--nttbs-rose-subtle);
    border-color: var(--nttbs-rose-border);
}
.nttbs-metric-card.card-emerald {
    background-color: var(--nttbs-emerald-subtle);
    border-color: var(--nttbs-emerald-border);
}
.nttbs-metric-card.card-amber {
    background-color: var(--nttbs-amber-subtle);
    border-color: var(--nttbs-amber-border);
}

.nttbs-metric-label {
    font-size: 11.5px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.nttbs-metric-value {
    font-size: 24px;
    font-weight: 800;
    margin-top: 6px;
    font-family: 'JetBrains Mono', monospace;
    letter-spacing: -0.5px;
}

/* Portfolio Progress Meter */
.nttbs-progress-bar {
    background: var(--nttbs-border-subtle);
    border: 1px solid var(--nttbs-border);
    border-radius: 999px;
    height: 6px;
    width: 120px;
    overflow: hidden;
}
.nttbs-progress-fill {
    background: var(--nttbs-primary);
    height: 100%;
    border-radius: 999px;
    transition: width 0.3s ease;
}
</style>

<div class="nttbs-rep-shell">

    <!-- Subnav Tabs & Range Filter Header -->
    <div class="nttbs-subnav-bar">
        <div class="nttbs-subnav-group">
            <a href="<?php echo esc_url(admin_url('admin.php?page=nttbs-core&view=reports&subtab=overview&start_date=' . urlencode($start_date) . '&end_date=' . urlencode($end_date))); ?>" 
               class="nttbs-pill-toggle <?php echo $current_subtab === 'overview' ? 'active' : 'inactive'; ?>">
                <span class="nttbs-nav-icon"><?php echo nttbs_svg_icon('reports', 16, 16); ?></span>
                <span><?php esc_html_e('Financial Overview', 'nahian-tour-travel-booking-solution'); ?></span>
            </a>
            <a href="<?php echo esc_url(admin_url('admin.php?page=nttbs-core&view=reports&subtab=tours&start_date=' . urlencode($start_date) . '&end_date=' . urlencode($end_date))); ?>" 
               class="nttbs-pill-toggle <?php echo $current_subtab === 'tours' ? 'active' : 'inactive'; ?>">
                <span class="nttbs-nav-icon"><?php echo nttbs_svg_icon('trips', 16, 16); ?></span>
                <span><?php esc_html_e('Tour Performance', 'nahian-tour-travel-booking-solution'); ?></span>
            </a>
            <a href="<?php echo esc_url(admin_url('admin.php?page=nttbs-core&view=reports&subtab=audit&start_date=' . urlencode($start_date) . '&end_date=' . urlencode($end_date))); ?>" 
               class="nttbs-pill-toggle <?php echo $current_subtab === 'audit' ? 'active' : 'inactive'; ?>">
                <span class="nttbs-nav-icon"><?php echo nttbs_svg_icon('clipboard', 16, 16); ?></span>
                <span><?php esc_html_e('Audit Trail', 'nahian-tour-travel-booking-solution'); ?></span>
            </a>
        </div>

        <!-- Date Filter Form -->
        <form method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>" class="nttbs-filter-box">
            <input type="hidden" name="page" value="nttbs-core">
            <input type="hidden" name="view" value="reports">
            <input type="hidden" name="subtab" value="<?php echo esc_attr($current_subtab); ?>">

            <input type="date" name="start_date" value="<?php echo esc_attr($start_date); ?>" class="nttbs-control-input" style="padding:5px 9px; font-size:12.5px; width:auto; font-family:'JetBrains Mono', monospace;">
            <span style="font-size:12px; color:var(--nttbs-text-muted); font-weight:700;"><?php esc_html_e('to', 'nahian-tour-travel-booking-solution'); ?></span>
            <input type="date" name="end_date" value="<?php echo esc_attr($end_date); ?>" class="nttbs-control-input" style="padding:5px 9px; font-size:12.5px; width:auto; font-family:'JetBrains Mono', monospace;">
            
            <button type="submit" class="nttbs-btn-primary" style="padding:6px 14px; font-size:12px; border-radius:8px;"><?php esc_html_e('Apply Range', 'nahian-tour-travel-booking-solution'); ?></button>
        </form>
    </div>

    <?php if ($current_subtab === 'overview'): ?>
        <!-- TAB 1: EXECUTIVE FINANCIAL OVERVIEW -->
        <div class="nttbs-white-panel">
            <div class="nttbs-panel-header">
                <span><?php esc_html_e('Executive Financial Ledger Metrics', 'nahian-tour-travel-booking-solution'); ?></span>
                <small style="color:var(--nttbs-text-muted); font-size:12px; font-weight:600; font-family:'JetBrains Mono', monospace;">
                    <?php echo esc_html($start_date . ' ' . __('to', 'nahian-tour-travel-booking-solution') . ' ' . $end_date); ?>
                </small>
            </div>
            
            <div class="nttbs-metric-deck">
                <div class="nttbs-metric-card card-blue">
                    <div class="nttbs-metric-label" style="color:var(--nttbs-primary-dark);"><?php esc_html_e('Settled Inward Revenue', 'nahian-tour-travel-booking-solution'); ?></div>
                    <div class="nttbs-metric-value" style="color:var(--nttbs-primary-dark);">
                        <?php echo esc_html($currency . ' ' . number_format_i18n((float)$period_revenue, 2)); ?>
                    </div>
                    <small style="color:var(--nttbs-text-muted); font-size:11.5px;"><?php esc_html_e('Cleared cashflow', 'nahian-tour-travel-booking-solution'); ?></small>
                </div>

                <div class="nttbs-metric-card card-rose">
                    <div class="nttbs-metric-label" style="color:var(--nttbs-rose);"><?php esc_html_e('Outstanding Receivables', 'nahian-tour-travel-booking-solution'); ?></div>
                    <div class="nttbs-metric-value" style="color:var(--nttbs-rose);">
                        <?php echo esc_html($currency . ' ' . number_format_i18n((float)$period_receivable, 2)); ?>
                    </div>
                    <small style="color:var(--nttbs-text-muted); font-size:11.5px;"><?php esc_html_e('Due on arrival / departure', 'nahian-tour-travel-booking-solution'); ?></small>
                </div>

                <div class="nttbs-metric-card card-emerald">
                    <div class="nttbs-metric-label" style="color:var(--nttbs-emerald);"><?php esc_html_e('Manifest Volume', 'nahian-tour-travel-booking-solution'); ?></div>
                    <div class="nttbs-metric-value" style="color:var(--nttbs-emerald);">
                        <?php echo esc_html(number_format_i18n((int)$period_tickets)); ?> <small style="font-size:14px; font-weight:700; font-family:inherit;"><?php esc_html_e('Pax', 'nahian-tour-travel-booking-solution'); ?></small>
                    </div>
                    <small style="color:var(--nttbs-text-muted); font-size:11.5px;"><?php esc_html_e('Total registered passengers', 'nahian-tour-travel-booking-solution'); ?></small>
                </div>

                <div class="nttbs-metric-card card-amber">
                    <div class="nttbs-metric-label" style="color:var(--nttbs-amber);"><?php esc_html_e('Reservation Passes', 'nahian-tour-travel-booking-solution'); ?></div>
                    <div class="nttbs-metric-value" style="color:var(--nttbs-amber);">
                        <?php echo esc_html(number_format_i18n((int)$period_orders)); ?> <small style="font-size:14px; font-weight:700; font-family:inherit;"><?php esc_html_e('Orders', 'nahian-tour-travel-booking-solution'); ?></small>
                    </div>
                    <small style="color:var(--nttbs-text-muted); font-size:11.5px;"><?php esc_html_e('Confirmed booking files', 'nahian-tour-travel-booking-solution'); ?></small>
                </div>
            </div>
        </div>

        <!-- Payment Channels Distribution -->
        <div class="nttbs-white-panel">
            <div class="nttbs-panel-header">
                <span><?php esc_html_e('Payment Channel Liquidity Breakdown', 'nahian-tour-travel-booking-solution'); ?></span>
            </div>
            <div class="nttbs-table-responsive">
                <table class="nttbs-data-table">
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
                            <tr><td colspan="4" style="text-align:center; padding:44px 20px; color:var(--nttbs-text-muted);"><?php esc_html_e('No transaction metrics found for the selected timeframe.', 'nahian-tour-travel-booking-solution'); ?></td></tr>
                        <?php else: foreach ($payment_channels as $pc): 
                            $share = $total_channel_revenue > 0 ? round(($pc['total_collected'] / $total_channel_revenue) * 100, 1) : 0;
                        ?>
                            <tr>
                                <td>
                                    <strong style="font-size:13.5px; color:var(--nttbs-text-heading);"><?php echo esc_html($pc['payment_method']); ?></strong>
                                </td>
                                <td>
                                    <?php echo esc_html(number_format_i18n((int)$pc['count'])); ?> <?php esc_html_e('transactions', 'nahian-tour-travel-booking-solution'); ?>
                                </td>
                                <td>
                                    <strong style="color:var(--nttbs-emerald); font-size:13.5px; font-weight:800; font-family:'JetBrains Mono', monospace;">
                                        <?php echo esc_html($currency . ' ' . number_format_i18n((float)$pc['total_collected'], 2)); ?>
                                    </strong>
                                </td>
                                <td>
                                    <div style="display:flex; align-items:center; gap:12px;">
                                        <div class="nttbs-progress-bar">
                                            <div class="nttbs-progress-fill" style="width:<?php echo esc_attr(min(100, $share)); ?>%;"></div>
                                        </div>
                                        <span style="font-size:11.5px; font-weight:800; color:var(--nttbs-text-muted); font-family:'JetBrains Mono', monospace;"><?php echo esc_html($share); ?>%</span>
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
        <div class="nttbs-white-panel">
            <div class="nttbs-panel-header">
                <span><?php esc_html_e('Tour Package Commercial Ledger & Occupancy Yield', 'nahian-tour-travel-booking-solution'); ?></span>
            </div>
            <div class="nttbs-table-responsive">
                <table class="nttbs-data-table">
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
                            <tr><td colspan="6" style="text-align:center; padding:44px 20px; color:var(--nttbs-text-muted);"><?php esc_html_e('No expedition performance data registered.', 'nahian-tour-travel-booking-solution'); ?></td></tr>
                        <?php else: foreach ($tour_performance as $tp): ?>
                            <tr>
                                <td>
                                    <strong style="font-size:13.5px; color:var(--nttbs-text-heading);"><?php echo esc_html($tp['title']); ?></strong>
                                </td>
                                <td>
                                    <?php echo esc_html(number_format_i18n((int)($tp['total_bookings'] ?: 0))); ?> <?php esc_html_e('orders', 'nahian-tour-travel-booking-solution'); ?>
                                </td>
                                <td>
                                    <strong style="color:var(--nttbs-text-heading); font-weight:800; font-family:'JetBrains Mono', monospace;">
                                        <?php echo esc_html(number_format_i18n((int)($tp['total_pax'] ?: 0))); ?>
                                    </strong>
                                    <span style="font-size:11px; color:var(--nttbs-text-muted); font-weight:600;"><?php esc_html_e('Pax', 'nahian-tour-travel-booking-solution'); ?></span>
                                </td>
                                <td>
                                    <span style="font-family:'JetBrains Mono', monospace;">
                                        <?php echo esc_html($currency . ' ' . number_format_i18n((float)$tp['total_gross'], 2)); ?>
                                    </span>
                                </td>
                                <td>
                                    <strong style="color:var(--nttbs-emerald); font-weight:800; font-family:'JetBrains Mono', monospace;">
                                        <?php echo esc_html($currency . ' ' . number_format_i18n((float)$tp['total_paid'], 2)); ?>
                                    </strong>
                                </td>
                                <td>
                                    <strong style="color:var(--nttbs-rose); font-weight:800; font-family:'JetBrains Mono', monospace;">
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
        <div class="nttbs-white-panel">
            <div class="nttbs-panel-header">
                <span><?php esc_html_e('System Audit Trail & Security Ledger', 'nahian-tour-travel-booking-solution'); ?></span>
                <small style="color:var(--nttbs-text-muted); font-size:12px; font-weight:600;"><?php esc_html_e('Last 50 recorded operational transactions', 'nahian-tour-travel-booking-solution'); ?></small>
            </div>
            <div class="nttbs-table-responsive">
                <table class="nttbs-data-table">
                    <thead>
                        <tr>
                            <th style="width:160px;"><?php esc_html_e('Timestamp', 'nahian-tour-travel-booking-solution'); ?></th>
                            <th><?php esc_html_e('Operator User', 'nahian-tour-travel-booking-solution'); ?></th>
                            <th><?php esc_html_e('Event Action', 'nahian-tour-travel-booking-solution'); ?></th>
                            <th><?php esc_html_e('Action Description', 'nahian-tour-travel-booking-solution'); ?></th>
                            <th><?php esc_html_e('IP Address', 'nahian-tour-travel-booking-solution'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($logs)): ?>
                            <tr><td colspan="5" style="text-align:center; padding:44px 20px; color:var(--nttbs-text-muted);"><?php esc_html_e('No operational event logs recorded in system.', 'nahian-tour-travel-booking-solution'); ?></td></tr>
                        <?php else: foreach ($logs as $log): ?>
                            <tr>
                                <td>
                                    <small style="color:var(--nttbs-text-muted); font-weight:700; font-family:'JetBrains Mono', monospace;"><?php echo esc_html($log['created_at']); ?></small>
                                </td>
                                <td>
                                    <strong style="color:var(--nttbs-text-heading); font-weight:800;"><?php echo esc_html($log['user_login']); ?></strong>
                                </td>
                                <td>
                                    <code style="background:var(--nttbs-primary-subtle); color:var(--nttbs-primary-dark); border:1px solid var(--nttbs-primary-border); font-size:11px; font-weight:800; padding:3px 7px; border-radius:5px; font-family:'JetBrains Mono', monospace;">
                                        <?php echo esc_html($log['action_type']); ?>
                                    </code>
                                </td>
                                <td>
                                    <span style="font-size:12.5px; color:var(--nttbs-text-body); line-height:1.4; display:inline-block;"><?php echo esc_html($log['description']); ?></span>
                                </td>
                                <td>
                                    <code style="font-size:11.5px; background:var(--nttbs-bg-body); border:1px solid var(--nttbs-border); padding:3px 7px; border-radius:5px; color:var(--nttbs-text-muted); font-family:'JetBrains Mono', monospace;"><?php echo esc_html($log['ip_address']); ?></code>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>

</div>
<?php
if (!defined('ABSPATH')) {
    exit;
}

/* ==========================================================================
   1. CONTROLLER DISPATCHERS & SAVE HANDLERS
   ========================================================================== */

function nttbs_handle_settings_dispatchers() {
    global $wpdb;

    $table_coupons = $wpdb->prefix . 'nttbs_coupons';

    // 1.1 General & Fiscal Configuration Dispatcher
    if (isset($_POST['nttbs_dispatch_general_settings']) && check_admin_referer('nttbs_settings_nonce')) {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Access denied: Insufficient operator clearance.', 'nahian-tour-travel-booking-solution'), 403);
        }

        update_option('nttbs_operator_title', sanitize_text_field(wp_unslash($_POST['operator_title'] ?? '')));
        update_option('nttbs_currency_symbol', sanitize_text_field(wp_unslash($_POST['currency_symbol'] ?? '€')));
        update_option('nttbs_currency_code', strtoupper(sanitize_text_field(wp_unslash($_POST['currency_code'] ?? 'EUR'))));
        update_option('nttbs_tax_percentage', max(0, floatval($_POST['tax_percentage'] ?? 0)));
        update_option('nttbs_default_deposit_rate', min(100, max(1, absint($_POST['default_deposit_rate'] ?? 50))));
        update_option('nttbs_company_email', sanitize_email(wp_unslash($_POST['company_email'] ?? '')));
        update_option('nttbs_company_phone', sanitize_text_field(wp_unslash($_POST['company_phone'] ?? '')));
        update_option('nttbs_ops_address', sanitize_textarea_field(wp_unslash($_POST['ops_address'] ?? '')));

        if (function_exists('nttbs_log_activity')) {
            nttbs_log_activity('SETTINGS_UPDATE', 'Updated global fiscal policies and corporate identification profile.');
        }
        wp_safe_redirect(admin_url('admin.php?page=nttbs-core&view=settings&subtab=general&status=saved'));
        exit;
    }

    // 1.2 Payment Channels & Gateway Engine Dispatcher
    if (isset($_POST['nttbs_dispatch_gateway_settings']) && check_admin_referer('nttbs_settings_nonce')) {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Access denied: Insufficient operator clearance.', 'nahian-tour-travel-booking-solution'), 403);
        }

        update_option('nttbs_enable_counter_cash', isset($_POST['enable_counter_cash']) ? 1 : 0);
        update_option('nttbs_enable_terminal_pos', isset($_POST['enable_terminal_pos']) ? 1 : 0);
        update_option('nttbs_enable_bank_wire', isset($_POST['enable_bank_wire']) ? 1 : 0);
        update_option('nttbs_bank_wire_details', sanitize_textarea_field(wp_unslash($_POST['bank_wire_details'] ?? '')));
        update_option('nttbs_stripe_mode', sanitize_key($_POST['stripe_mode'] ?? 'test'));
        update_option('nttbs_stripe_public_key', sanitize_text_field(wp_unslash($_POST['stripe_public_key'] ?? '')));
        update_option('nttbs_stripe_secret_key', sanitize_text_field(wp_unslash($_POST['stripe_secret_key'] ?? '')));

        if (function_exists('nttbs_log_activity')) {
            nttbs_log_activity('SETTINGS_UPDATE', 'Updated liquidity gateways, POS terminal channels, and API credentials.');
        }
        wp_safe_redirect(admin_url('admin.php?page=nttbs-core&view=settings&subtab=gateways&status=saved'));
        exit;
    }

    // 1.3 Promotional Voucher Issue Dispatcher
    if (isset($_POST['nttbs_dispatch_coupon_save']) && check_admin_referer('nttbs_settings_nonce')) {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Access denied: Insufficient operator clearance.', 'nahian-tour-travel-booking-solution'), 403);
        }

        $code          = strtoupper(preg_replace('/[^A-Za-z0-9_-]/', '', sanitize_text_field(wp_unslash($_POST['coupon_code'] ?? ''))));
        $discount_type = sanitize_key($_POST['discount_type'] ?? 'percentage');
        $amount        = max(0.01, floatval($_POST['coupon_amount'] ?? 0));
        $usage_limit   = max(1, absint($_POST['usage_limit'] ?? 100));

        if (!empty($code)) {
            $wpdb->insert($table_coupons, [
                'code'          => $code,
                'discount_type' => $discount_type,
                'amount'        => $amount,
                'usage_limit'   => $usage_limit,
                'used_count'    => 0,
                'status'        => 'active'
            ]);
            if (function_exists('nttbs_log_activity')) {
                nttbs_log_activity('CREATE_COUPON', "Emitted promotional asset authorization token: #{$code}");
            }
        }

        wp_safe_redirect(admin_url('admin.php?page=nttbs-core&view=settings&subtab=vouchers&status=saved'));
        exit;
    }

    // 1.4 Promotional Voucher Revocation Dispatcher
    if (isset($_GET['action']) && $_GET['action'] === 'delete_coupon' && isset($_GET['coupon_id'])) {
        $coupon_id = absint($_GET['coupon_id']);
        check_admin_referer('nttbs_delete_coupon_' . $coupon_id);

        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Access denied.', 'nahian-tour-travel-booking-solution'), 403);
        }

        $wpdb->delete($table_coupons, ['id' => $coupon_id], ['%d']);
        if (function_exists('nttbs_log_activity')) {
            nttbs_log_activity('DELETE_COUPON', "Decommissioned promotional authorization voucher #{$coupon_id}");
        }
        wp_safe_redirect(admin_url('admin.php?page=nttbs-core&view=settings&subtab=vouchers&status=deleted'));
        exit;
    }
}
nttbs_handle_settings_dispatchers();

/* ==========================================================================
   2. DATA & SUBTAB ROUTING
   ========================================================================== */

global $wpdb;

$current_subtab = isset($_GET['subtab']) && in_array($_GET['subtab'], ['general', 'gateways', 'vouchers', 'diagnostics'], true) ? sanitize_key($_GET['subtab']) : 'general';

$default_app_name = defined('NTTBS_APP_NAME') ? NTTBS_APP_NAME : 'Nahian Tour & Travel';
$default_symbol   = defined('NTTBS_CURRENCY_SYMBOL_DEFAULT') ? NTTBS_CURRENCY_SYMBOL_DEFAULT : '€';
$default_code     = defined('NTTBS_CURRENCY_DEFAULT') ? NTTBS_CURRENCY_DEFAULT : 'EUR';

$title         = get_option('nttbs_operator_title', $default_app_name);
$currency      = get_option('nttbs_currency_symbol', $default_symbol);
$currency_code = get_option('nttbs_currency_code', $default_code);
$tax           = get_option('nttbs_tax_percentage', 0);
$deposit_rate  = get_option('nttbs_default_deposit_rate', 50);
$company_email = get_option('nttbs_company_email', get_option('admin_email'));
$company_phone = get_option('nttbs_company_phone', '+20 100 000 0000');
$ops_address   = get_option('nttbs_ops_address', "New Marina Boulevard, Suite 404\nHurghada, Red Sea Governorate, Egypt");

$enable_cash   = get_option('nttbs_enable_counter_cash', 1);
$enable_pos    = get_option('nttbs_enable_terminal_pos', 1);
$enable_wire   = get_option('nttbs_enable_bank_wire', 1);
$bank_details  = get_option('nttbs_bank_wire_details', "Financial Institution: National Bank of Egypt\nBeneficiary: Nahian Tour & Travel Operations\nIBAN: EG380002000100000000123456789\nSWIFT / BIC: NBEGEGCX");
$stripe_mode   = get_option('nttbs_stripe_mode', 'test');
$stripe_pub    = get_option('nttbs_stripe_public_key', '');
$stripe_sec    = get_option('nttbs_stripe_secret_key', '');

$table_coupons = $wpdb->prefix . 'nttbs_coupons';
$coupons       = [];
if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_coupons)) === $table_coupons) {
    $coupons = $wpdb->get_results("SELECT * FROM {$table_coupons} ORDER BY id DESC", ARRAY_A) ?: [];
}
?>

<style>
.nttbs-settings-canvas {
    display: flex;
    flex-direction: column;
    gap: 24px;
    animation: nttbsFadeIn 0.3s cubic-bezier(0.16, 1, 0.3, 1);
}
@keyframes nttbsFadeIn {
    from { opacity: 0; transform: translateY(6px); }
    to { opacity: 1; transform: translateY(0); }
}

/* Executive Hero */
.nttbs-settings-hero {
    background: var(--nttbs-bg-card);
    border: 1px solid var(--nttbs-border);
    border-radius: 14px;
    padding: 24px 28px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    box-shadow: var(--nttbs-shadow-sm);
    gap: 20px;
}
.nttbs-hero-badge {
    display: inline-block;
    font-size: 11px;
    font-weight: 800;
    color: var(--nttbs-primary-dark);
    background: var(--nttbs-primary-subtle);
    border: 1px solid var(--nttbs-primary-border);
    padding: 3px 9px;
    border-radius: 6px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 8px;
}
.nttbs-hero-dossier h2 {
    font-size: 22px;
    font-weight: 800;
    color: var(--nttbs-text-heading);
    margin: 0 0 6px 0;
    letter-spacing: -0.3px;
}
.nttbs-hero-dossier p {
    font-size: 13px;
    color: var(--nttbs-text-muted);
    margin: 0;
    line-height: 1.5;
}
.nttbs-hero-telemetry-cluster {
    display: flex;
    gap: 12px;
    flex-shrink: 0;
}
.nttbs-telemetry-badge {
    background: var(--nttbs-bg-body);
    border: 1px solid var(--nttbs-border);
    border-radius: 12px;
    padding: 14px 20px;
    text-align: center;
    min-width: 120px;
}
.nttbs-telemetry-badge .nttbs-t-label {
    display: block;
    font-size: 10.5px;
    font-weight: 800;
    color: var(--nttbs-text-muted);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 4px;
}
.nttbs-telemetry-badge .nttbs-t-value {
    font-size: 16px;
    font-weight: 800;
    color: var(--nttbs-text-heading);
    font-family: 'JetBrains Mono', monospace;
}

/* Nav Strip */
.nttbs-settings-nav-strip {
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid var(--nttbs-border);
    padding-bottom: 18px;
    gap: 12px;
    flex-wrap: wrap;
}
.nttbs-nav-left {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}
.nttbs-subnav-tab {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 9px 18px;
    border-radius: 10px;
    font-size: 13px;
    font-weight: 700;
    text-decoration: none;
    background: var(--nttbs-bg-card);
    color: var(--nttbs-text-muted);
    border: 1px solid var(--nttbs-border);
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}
.nttbs-subnav-tab:hover {
    color: var(--nttbs-text-heading);
    background: var(--nttbs-bg-hover);
    transform: translateY(-1px);
}
.nttbs-subnav-tab.active {
    background: linear-gradient(135deg, var(--nttbs-primary), var(--nttbs-primary-dark));
    color: #ffffff;
    border-color: transparent;
    box-shadow: 0 4px 12px rgba(37, 99, 235, 0.25);
}
.nttbs-subnav-tab .nttbs-tab-chip {
    background: var(--nttbs-bg-hover);
    font-size: 11px;
    font-weight: 800;
    padding: 2px 8px;
    border-radius: 999px;
    color: var(--nttbs-text-heading);
    font-family: 'JetBrains Mono', monospace;
}
.nttbs-subnav-tab.active .nttbs-tab-chip {
    background: rgba(255, 255, 255, 0.25);
    color: #ffffff;
}
.nttbs-alert-pill {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    font-size: 12.5px;
    font-weight: 700;
    padding: 7px 16px;
    border-radius: 8px;
}
.nttbs-alert-pill.saved {
    background: var(--nttbs-emerald-subtle);
    color: var(--nttbs-emerald);
    border: 1px solid var(--nttbs-emerald-border);
}
.nttbs-alert-pill.deleted {
    background: var(--nttbs-rose-subtle);
    color: var(--nttbs-rose);
    border: 1px solid var(--nttbs-rose-border);
}

/* Layout Grids */
.nttbs-stage-grid {
    display: grid;
    grid-template-columns: minmax(0, 2.2fr) minmax(0, 1fr);
    gap: 24px;
    align-items: start;
}
.nttbs-stage-grid.reverse-split {
    grid-template-columns: minmax(0, 1fr) minmax(0, 2fr);
}

/* Cards */
.nttbs-pro-card {
    background: var(--nttbs-bg-card);
    border: 1px solid var(--nttbs-border);
    border-radius: 14px;
    overflow: hidden;
    box-shadow: var(--nttbs-shadow-sm);
    margin-bottom: 22px;
    transition: box-shadow 0.2s ease, border-color 0.2s ease;
}
.nttbs-pro-card:last-child {
    margin-bottom: 0;
}
.nttbs-pro-card:hover {
    box-shadow: var(--nttbs-shadow-md);
}
.nttbs-pro-card-header {
    padding: 18px 24px;
    border-bottom: 1px solid var(--nttbs-border);
    background: linear-gradient(180deg, #ffffff 0%, var(--nttbs-bg-body) 100%);
}
.nttbs-pro-card-header h3 {
    font-size: 14.5px;
    font-weight: 800;
    color: var(--nttbs-text-heading);
    margin: 0 0 4px 0;
    letter-spacing: -0.2px;
}
.nttbs-pro-card-header p {
    font-size: 12px;
    color: var(--nttbs-text-muted);
    margin: 0;
}
.nttbs-pro-card-body {
    padding: 24px;
}
.nttbs-pro-card-footer {
    padding: 16px 24px;
    background: var(--nttbs-bg-body);
    border-top: 1px solid var(--nttbs-border);
    text-align: right;
}

/* Forms */
.nttbs-row { margin-bottom: 18px; }
.nttbs-row:last-child { margin-bottom: 0; }
.nttbs-split-2 {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 18px;
    margin-bottom: 18px;
}
.nttbs-split-2:last-child { margin-bottom: 0; }
.nttbs-form-label {
    display: block;
    font-size: 12px;
    font-weight: 700;
    color: var(--nttbs-text-heading);
    margin-bottom: 6px;
}
.nttbs-pro-input {
    width: 100%;
    padding: 10px 14px;
    border: 1px solid var(--nttbs-border);
    border-radius: 10px;
    font-size: 13.5px;
    font-family: inherit;
    color: var(--nttbs-text-heading);
    background-color: var(--nttbs-bg-card);
    box-sizing: border-box;
    transition: all 0.15s ease;
}
.nttbs-pro-input:focus {
    outline: none;
    border-color: var(--nttbs-primary);
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15);
}
.nttbs-input-hint {
    display: block;
    font-size: 11.5px;
    color: var(--nttbs-text-muted);
    margin-top: 6px;
}
.font-mono { font-family: 'JetBrains Mono', monospace; }
.uppercase { text-transform: uppercase; }

/* Affixes */
.nttbs-input-affix {
    position: relative;
    display: flex;
    align-items: center;
}
.nttbs-input-affix .nttbs-pro-input { padding-right: 36px; }
.nttbs-affix-tag {
    position: absolute;
    right: 14px;
    font-size: 13px;
    font-weight: 800;
    color: var(--nttbs-text-muted);
    pointer-events: none;
}

/* Action Buttons */
.nttbs-btn-action {
    background: linear-gradient(135deg, var(--nttbs-primary), var(--nttbs-primary-dark));
    color: #ffffff;
    border: none;
    padding: 12px 20px;
    border-radius: 10px;
    font-size: 13.5px;
    font-weight: 700;
    cursor: pointer;
    box-shadow: 0 2px 6px rgba(37, 99, 235, 0.25);
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}
.nttbs-btn-action:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(37, 99, 235, 0.35);
}
.nttbs-btn-action:active {
    transform: translateY(0);
}
.nttbs-btn-action.full-width { width: 100%; }

/* Switches */
.nttbs-switch-group {
    display: flex;
    flex-direction: column;
    gap: 14px;
}
.nttbs-switch-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: var(--nttbs-bg-body);
    border: 1px solid var(--nttbs-border);
    border-radius: 12px;
    padding: 16px 20px;
}
.nttbs-switch-meta .nttbs-switch-title {
    display: block;
    font-size: 13.5px;
    font-weight: 800;
    color: var(--nttbs-text-heading);
    margin-bottom: 3px;
}
.nttbs-switch-meta .nttbs-switch-desc {
    font-size: 11.5px;
    color: var(--nttbs-text-muted);
}
.nttbs-toggle {
    position: relative;
    display: inline-block;
    width: 44px;
    height: 24px;
    flex-shrink: 0;
}
.nttbs-toggle input { opacity: 0; width: 0; height: 0; }
.nttbs-toggle-slider {
    position: absolute;
    cursor: pointer;
    top: 0; left: 0; right: 0; bottom: 0;
    background-color: #cbd5e1;
    transition: .2s cubic-bezier(0.16, 1, 0.3, 1);
    border-radius: 999px;
}
.nttbs-toggle-slider:before {
    position: absolute;
    content: "";
    height: 18px;
    width: 18px;
    left: 3px;
    bottom: 3px;
    background-color: white;
    transition: .2s cubic-bezier(0.16, 1, 0.3, 1);
    border-radius: 50%;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.15);
}
.nttbs-toggle input:checked + .nttbs-toggle-slider {
    background-color: var(--nttbs-emerald);
}
.nttbs-toggle input:checked + .nttbs-toggle-slider:before {
    transform: translateX(20px);
}

/* Guidelines Sidebar Card */
.nttbs-guideline-card {
    background: var(--nttbs-bg-card);
    border: 1px solid var(--nttbs-border);
    border-left: 4px solid var(--nttbs-primary);
    border-radius: 14px;
    padding: 22px;
    box-shadow: var(--nttbs-shadow-sm);
}
.nttbs-guideline-card.accent-emerald {
    border-left-color: var(--nttbs-emerald);
}
.nttbs-guide-header {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 14px;
}
.nttbs-guide-header h4 {
    margin: 0;
    font-size: 13.5px;
    font-weight: 800;
    color: var(--nttbs-text-heading);
}
.nttbs-guide-list {
    margin: 0;
    padding-left: 18px;
    font-size: 12.5px;
    color: var(--nttbs-text-muted);
    line-height: 1.6;
}
.nttbs-guide-para {
    font-size: 12.5px;
    color: var(--nttbs-text-muted);
    line-height: 1.6;
    margin: 0;
}

/* Corporate Tables */
.nttbs-table-viewport {
    width: 100%;
    overflow-x: auto;
}
.nttbs-corporate-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    text-align: left;
}
.nttbs-corporate-table th {
    padding: 12px 20px;
    font-size: 11px;
    font-weight: 800;
    color: var(--nttbs-text-muted);
    text-transform: uppercase;
    letter-spacing: 0.6px;
    background: var(--nttbs-bg-body);
    border-bottom: 1px solid var(--nttbs-border);
    white-space: nowrap;
}
.nttbs-corporate-table td {
    padding: 15px 20px;
    font-size: 13px;
    color: var(--nttbs-text-body);
    border-bottom: 1px solid var(--nttbs-border);
    vertical-align: middle;
    transition: background-color 0.12s ease;
}
.nttbs-corporate-table tr:last-child td { border-bottom: none; }
.nttbs-corporate-table tr:hover td {
    background-color: var(--nttbs-bg-hover);
}

.nttbs-voucher-token-box {
    background: var(--nttbs-primary-subtle);
    color: var(--nttbs-primary-dark);
    border: 1px solid var(--nttbs-primary-border);
    padding: 4px 8px;
    border-radius: 6px;
    font-weight: 800;
    font-size: 12px;
    font-family: 'JetBrains Mono', monospace;
}
.nttbs-weight-tag {
    font-weight: 800;
    color: var(--nttbs-text-heading);
    font-family: 'JetBrains Mono', monospace;
}
.nttbs-quota-progress-cluster {
    display: flex;
    align-items: center;
    gap: 10px;
}
.nttbs-quota-bar {
    width: 80px;
    height: 6px;
    background: var(--nttbs-border-subtle);
    border: 1px solid var(--nttbs-border);
    border-radius: 999px;
    overflow: hidden;
}
.nttbs-quota-fill {
    height: 100%;
    background: var(--nttbs-primary);
    border-radius: 999px;
}
.nttbs-quota-text {
    font-size: 11.5px;
    font-weight: 700;
    color: var(--nttbs-text-muted);
    font-family: 'JetBrains Mono', monospace;
}
.nttbs-btn-revoke-inline {
    color: var(--nttbs-rose);
    font-weight: 700;
    text-decoration: none;
    font-size: 11.5px;
    background: var(--nttbs-rose-subtle);
    border: 1px solid var(--nttbs-rose-border);
    padding: 5px 10px;
    border-radius: 6px;
    transition: all 0.15s ease;
    display: inline-block;
}
.nttbs-btn-revoke-inline:hover {
    background: var(--nttbs-rose);
    color: #ffffff;
}
.nttbs-empty-cell {
    text-align: center;
    padding: 44px 20px !important;
    color: var(--nttbs-text-muted);
}

/* Diagnostics */
.nttbs-corporate-table.diagnostics td { padding: 16px 20px; }
.nttbs-metric-label { width: 260px; font-weight: 800; color: var(--nttbs-text-heading); font-size: 13px; }
.nttbs-sys-code {
    background: var(--nttbs-bg-body);
    border: 1px solid var(--nttbs-border);
    padding: 4px 8px;
    border-radius: 6px;
    font-weight: 800;
    color: var(--nttbs-text-heading);
    font-size: 12px;
    font-family: 'JetBrains Mono', monospace;
}
.nttbs-metric-sub {
    font-size: 11.5px;
    color: var(--nttbs-text-muted);
    margin-left: 8px;
}
.nttbs-schema-chip-cluster {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}
.nttbs-schema-chip {
    background: var(--nttbs-purple-subtle);
    border: 1px solid var(--nttbs-purple-border);
    padding: 3px 8px;
    border-radius: 6px;
    font-size: 11px;
    font-family: 'JetBrains Mono', monospace;
    color: var(--nttbs-purple);
    font-weight: 600;
}

/* Badges */
.nttbs-pill.confirmed {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    background: var(--nttbs-emerald-subtle);
    color: var(--nttbs-emerald);
    border: 1px solid var(--nttbs-emerald-border);
    padding: 3px 8px;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.3px;
}

@media (max-width: 900px) {
    .nttbs-settings-hero { flex-direction: column; align-items: flex-start; gap: 16px; }
    .nttbs-stage-grid, .nttbs-stage-grid.reverse-split { grid-template-columns: 1fr; }
    .nttbs-settings-nav-strip { flex-direction: column; align-items: flex-start; gap: 12px; }
    .nttbs-split-2 { grid-template-columns: 1fr; }
}
</style>

<div class="nttbs-settings-canvas">

    <!-- EXECUTIVE SETTINGS HERO -->
    <header class="nttbs-settings-hero">
        <div class="nttbs-hero-dossier">
            <span class="nttbs-hero-badge"><?php esc_html_e('Core Operational Matrix', 'nahian-tour-travel-booking-solution'); ?></span>
            <h2><?php esc_html_e('Enterprise Configuration Engine', 'nahian-tour-travel-booking-solution'); ?></h2>
            <p><?php esc_html_e('Calibrate multi-currency fiscal regimes, dispatch channel liquidities, gateway keys, and promotional voucher registries.', 'nahian-tour-travel-booking-solution'); ?></p>
        </div>
        <div class="nttbs-hero-telemetry-cluster">
            <div class="nttbs-telemetry-badge">
                <span class="nttbs-t-label"><?php esc_html_e('Fiscal Engine', 'nahian-tour-travel-booking-solution'); ?></span>
                <span class="nttbs-t-value"><?php echo esc_html($currency_code . ' (' . $currency . ')'); ?></span>
            </div>
            <div class="nttbs-telemetry-badge">
                <span class="nttbs-t-label"><?php esc_html_e('Voucher Registry', 'nahian-tour-travel-booking-solution'); ?></span>
                <span class="nttbs-t-value" style="color:var(--nttbs-purple);"><?php echo count($coupons); ?> <?php esc_html_e('Active', 'nahian-tour-travel-booking-solution'); ?></span>
            </div>
            <div class="nttbs-telemetry-badge">
                <span class="nttbs-t-label"><?php esc_html_e('Tax Policy', 'nahian-tour-travel-booking-solution'); ?></span>
                <span class="nttbs-t-value" style="color:var(--nttbs-emerald);"><?php echo esc_html($tax); ?>%</span>
            </div>
        </div>
    </header>

    <!-- NAVIGATION TAB DECK -->
    <nav class="nttbs-settings-nav-strip">
        <div class="nttbs-nav-left">
            <a href="<?php echo esc_url(admin_url('admin.php?page=nttbs-core&view=settings&subtab=general')); ?>" 
               class="nttbs-subnav-tab <?php echo $current_subtab === 'general' ? 'active' : ''; ?>">
                <span class="nttbs-tab-icon"><?php echo nttbs_svg_icon('settings', 16, 16); ?></span>
                <span class="nttbs-tab-label"><?php esc_html_e('General & Fiscal', 'nahian-tour-travel-booking-solution'); ?></span>
            </a>
            <a href="<?php echo esc_url(admin_url('admin.php?page=nttbs-core&view=settings&subtab=gateways')); ?>" 
               class="nttbs-subnav-tab <?php echo $current_subtab === 'gateways' ? 'active' : ''; ?>">
                <span class="nttbs-tab-icon"><?php echo nttbs_svg_icon('wallet', 16, 16); ?></span>
                <span class="nttbs-tab-label"><?php esc_html_e('Payment Gateways & POS', 'nahian-tour-travel-booking-solution'); ?></span>
            </a>
            <a href="<?php echo esc_url(admin_url('admin.php?page=nttbs-core&view=settings&subtab=vouchers')); ?>" 
               class="nttbs-subnav-tab <?php echo $current_subtab === 'vouchers' ? 'active' : ''; ?>">
                <span class="nttbs-tab-icon"><?php echo nttbs_svg_icon('price_cat', 16, 16); ?></span>
                <span class="nttbs-tab-label"><?php esc_html_e('Promotion Vouchers', 'nahian-tour-travel-booking-solution'); ?></span>
                <span class="nttbs-tab-chip"><?php echo count($coupons); ?></span>
            </a>
            <a href="<?php echo esc_url(admin_url('admin.php?page=nttbs-core&view=settings&subtab=diagnostics')); ?>" 
               class="nttbs-subnav-tab <?php echo $current_subtab === 'diagnostics' ? 'active' : ''; ?>">
                <span class="nttbs-tab-icon"><?php echo nttbs_svg_icon('dashboard', 16, 16); ?></span>
                <span class="nttbs-tab-label"><?php esc_html_e('Diagnostics & Integrity', 'nahian-tour-travel-booking-solution'); ?></span>
            </a>
        </div>

        <?php if (isset($_GET['status'])): ?>
            <?php if ($_GET['status'] === 'saved'): ?>
                <div class="nttbs-alert-pill saved">
                    <?php echo nttbs_svg_icon('check', 14, 14); ?>
                    <?php esc_html_e('Parameters updated successfully', 'nahian-tour-travel-booking-solution'); ?>
                </div>
            <?php elseif ($_GET['status'] === 'deleted'): ?>
                <div class="nttbs-alert-pill deleted">
                    <?php echo nttbs_svg_icon('close', 14, 14); ?>
                    <?php esc_html_e('Record deleted from registry', 'nahian-tour-travel-booking-solution'); ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </nav>

    <!-- WORKSPACE STAGES -->
    <div class="nttbs-stage-matrix">

        <?php if ($current_subtab === 'general'): ?>
            <!-- TAB 1: GENERAL & FISCAL PARAMETERS -->
            <div class="nttbs-stage-grid">
                <div class="nttbs-stage-primary">
                    <form method="post" action="<?php echo esc_url(admin_url('admin.php?page=nttbs-core&view=settings&subtab=general')); ?>">
                        <?php wp_nonce_field('nttbs_settings_nonce'); ?>

                        <!-- Card 1: Brand & Profile -->
                        <div class="nttbs-pro-card">
                            <div class="nttbs-pro-card-header">
                                <div class="hdr-text">
                                    <h3><?php esc_html_e('Corporate Operations Profile', 'nahian-tour-travel-booking-solution'); ?></h3>
                                    <p><?php esc_html_e('Official operator details surfaced across manifests, receipts, and dispatch dossiers.', 'nahian-tour-travel-booking-solution'); ?></p>
                                </div>
                            </div>
                            <div class="nttbs-pro-card-body">
                                <div class="nttbs-row">
                                    <label class="nttbs-form-label"><?php esc_html_e('Enterprise Operator Title *', 'nahian-tour-travel-booking-solution'); ?></label>
                                    <input type="text" name="operator_title" value="<?php echo esc_attr($title); ?>" required class="nttbs-pro-input" placeholder="e.g. Nahian Tour & Travel Engine PRO">
                                </div>
                                <div class="nttbs-split-2">
                                    <div class="nttbs-row">
                                        <label class="nttbs-form-label"><?php esc_html_e('Dispatch Support Email *', 'nahian-tour-travel-booking-solution'); ?></label>
                                        <input type="email" name="company_email" value="<?php echo esc_attr($company_email); ?>" required class="nttbs-pro-input" placeholder="ops@domain.com">
                                    </div>
                                    <div class="nttbs-row">
                                        <label class="nttbs-form-label"><?php esc_html_e('Emergency Operations Wire *', 'nahian-tour-travel-booking-solution'); ?></label>
                                        <input type="text" name="company_phone" value="<?php echo esc_attr($company_phone); ?>" required class="nttbs-pro-input" placeholder="+20 100 000 0000">
                                    </div>
                                </div>
                                <div class="nttbs-row">
                                    <label class="nttbs-form-label"><?php esc_html_e('Headquarters / Marina Harbor Dispatch Location', 'nahian-tour-travel-booking-solution'); ?></label>
                                    <textarea name="ops_address" rows="3" class="nttbs-pro-input"><?php echo esc_textarea($ops_address); ?></textarea>
                                </div>
                            </div>
                        </div>

                        <!-- Card 2: Monetary & Tax Policies -->
                        <div class="nttbs-pro-card">
                            <div class="nttbs-pro-card-header">
                                <div class="hdr-text">
                                    <h3><?php esc_html_e('Monetary & Fiscal Policies', 'nahian-tour-travel-booking-solution'); ?></h3>
                                    <p><?php esc_html_e('Configure base trading currency tokens, deposit ratios, and regional levies.', 'nahian-tour-travel-booking-solution'); ?></p>
                                </div>
                            </div>
                            <div class="nttbs-pro-card-body">
                                <div class="nttbs-split-2">
                                    <div class="nttbs-row">
                                        <label class="nttbs-form-label"><?php esc_html_e('Currency Symbol *', 'nahian-tour-travel-booking-solution'); ?></label>
                                        <input type="text" name="currency_symbol" value="<?php echo esc_attr($currency); ?>" required class="nttbs-pro-input" placeholder="€, $, £, EGP">
                                        <span class="nttbs-input-hint"><?php esc_html_e('Symbol displayed on client tariffs and drawer cards.', 'nahian-tour-travel-booking-solution'); ?></span>
                                    </div>
                                    <div class="nttbs-row">
                                        <label class="nttbs-form-label"><?php esc_html_e('Currency ISO 4217 Code *', 'nahian-tour-travel-booking-solution'); ?></label>
                                        <input type="text" name="currency_code" value="<?php echo esc_attr($currency_code); ?>" required class="nttbs-pro-input font-mono uppercase" placeholder="EUR, USD, EGP">
                                        <span class="nttbs-input-hint"><?php esc_html_e('3-letter ISO representation for payment webhooks.', 'nahian-tour-travel-booking-solution'); ?></span>
                                    </div>
                                </div>
                                <div class="nttbs-split-2">
                                    <div class="nttbs-row">
                                        <label class="nttbs-form-label"><?php esc_html_e('Conservation / Regional Port Levy (%)', 'nahian-tour-travel-booking-solution'); ?></label>
                                        <div class="nttbs-input-affix">
                                            <input type="number" step="0.01" min="0" max="100" name="tax_percentage" value="<?php echo esc_attr($tax); ?>" class="nttbs-pro-input font-mono">
                                            <span class="nttbs-affix-tag">%</span>
                                        </div>
                                        <span class="nttbs-input-hint"><?php esc_html_e('Appended to booking ledger subtotals automatically.', 'nahian-tour-travel-booking-solution'); ?></span>
                                    </div>
                                    <div class="nttbs-row">
                                        <label class="nttbs-form-label"><?php esc_html_e('Mandatory Checkout Deposit (%)', 'nahian-tour-travel-booking-solution'); ?></label>
                                        <div class="nttbs-input-affix">
                                            <input type="number" min="1" max="100" name="default_deposit_rate" value="<?php echo esc_attr($deposit_rate); ?>" class="nttbs-pro-input font-mono">
                                            <span class="nttbs-affix-tag">%</span>
                                        </div>
                                        <span class="nttbs-input-hint"><?php esc_html_e('Minimum partial upfront balance requirement.', 'nahian-tour-travel-booking-solution'); ?></span>
                                    </div>
                                </div>
                            </div>
                            <div class="nttbs-pro-card-footer">
                                <button type="submit" name="nttbs_dispatch_general_settings" class="nttbs-btn-action">
                                    <?php esc_html_e('Save Configuration', 'nahian-tour-travel-booking-solution'); ?>
                                </button>
                            </div>
                        </div>
                    </form>
                </div>

                <div class="nttbs-stage-secondary">
                    <div class="nttbs-guideline-card">
                        <div class="nttbs-guide-header">
                            <span class="nttbs-g-icon"><?php echo nttbs_svg_icon('check', 18, 18); ?></span>
                            <h4><?php esc_html_e('Fiscal Protocol Guidance', 'nahian-tour-travel-booking-solution'); ?></h4>
                        </div>
                        <ul class="nttbs-guide-list">
                            <li><strong><?php esc_html_e('Real-Time Propagation:', 'nahian-tour-travel-booking-solution'); ?></strong> <?php esc_html_e('Modifications made to fiscal symbols update active POS registers without cache flushing.', 'nahian-tour-travel-booking-solution'); ?></li>
                            <li><strong><?php esc_html_e('Deposit Locking:', 'nahian-tour-travel-booking-solution'); ?></strong> <?php esc_html_e('Setting deposits below 100% enables partial payments across checkout drawers.', 'nahian-tour-travel-booking-solution'); ?></li>
                            <li><strong><?php esc_html_e('Audit Trail Logging:', 'nahian-tour-travel-booking-solution'); ?></strong> <?php esc_html_e('Every save sequence records the operational user account, timestamp, and client IP address.', 'nahian-tour-travel-booking-solution'); ?></li>
                        </ul>
                    </div>
                </div>
            </div>

        <?php elseif ($current_subtab === 'gateways'): ?>
            <!-- TAB 2: PAYMENT GATEWAYS & POS TERMINALS -->
            <div class="nttbs-stage-grid">
                <div class="nttbs-stage-primary">
                    <form method="post" action="<?php echo esc_url(admin_url('admin.php?page=nttbs-core&view=settings&subtab=gateways')); ?>">
                        <?php wp_nonce_field('nttbs_settings_nonce'); ?>

                        <!-- Active Checkout Rails -->
                        <div class="nttbs-pro-card">
                            <div class="nttbs-pro-card-header">
                                <div class="hdr-text">
                                    <h3><?php esc_html_e('Operational Liquidity Rails', 'nahian-tour-travel-booking-solution'); ?></h3>
                                    <p><?php esc_html_e('Activate local payment channels available to dispatch counter clerks and online travelers.', 'nahian-tour-travel-booking-solution'); ?></p>
                                </div>
                            </div>
                            <div class="nttbs-pro-card-body">
                                <div class="nttbs-switch-group">
                                    <div class="nttbs-switch-item">
                                        <div class="nttbs-switch-meta">
                                            <span class="nttbs-switch-title"><?php esc_html_e('Counter Cash Settlements', 'nahian-tour-travel-booking-solution'); ?></span>
                                            <span class="nttbs-switch-desc"><?php esc_html_e('Permits frontline operators to register paper tender at dockside booths.', 'nahian-tour-travel-booking-solution'); ?></span>
                                        </div>
                                        <label class="nttbs-toggle">
                                            <input type="checkbox" name="enable_counter_cash" value="1" <?php checked($enable_cash, 1); ?>>
                                            <span class="nttbs-toggle-slider"></span>
                                        </label>
                                    </div>
                                    <div class="nttbs-switch-item">
                                        <div class="nttbs-switch-meta">
                                            <span class="nttbs-switch-title"><?php esc_html_e('Terminal Hardware POS Reader', 'nahian-tour-travel-booking-solution'); ?></span>
                                            <span class="nttbs-switch-desc"><?php esc_html_e('Accepts on-premise chip, swipe, and NFC contactless card terminals.', 'nahian-tour-travel-booking-solution'); ?></span>
                                        </div>
                                        <label class="nttbs-toggle">
                                            <input type="checkbox" name="enable_terminal_pos" value="1" <?php checked($enable_pos, 1); ?>>
                                            <span class="nttbs-toggle-slider"></span>
                                        </label>
                                    </div>
                                    <div class="nttbs-switch-item">
                                        <div class="nttbs-switch-meta">
                                            <span class="nttbs-switch-title"><?php esc_html_e('Corporate Bank Wire Transfer', 'nahian-tour-travel-booking-solution'); ?></span>
                                            <span class="nttbs-switch-desc"><?php esc_html_e('Displays IBAN, BIC/SWIFT wire coordinates for group charter invoices.', 'nahian-tour-travel-booking-solution'); ?></span>
                                        </div>
                                        <label class="nttbs-toggle">
                                            <input type="checkbox" name="enable_bank_wire" value="1" <?php checked($enable_wire, 1); ?>>
                                            <span class="nttbs-toggle-slider"></span>
                                        </label>
                                    </div>
                                </div>

                                <div class="nttbs-row" style="margin-top:20px;">
                                    <label class="nttbs-form-label"><?php esc_html_e('Institutional Bank Wire Instructions', 'nahian-tour-travel-booking-solution'); ?></label>
                                    <textarea name="bank_wire_details" rows="4" class="nttbs-pro-input"><?php echo esc_textarea($bank_details); ?></textarea>
                                </div>
                            </div>
                        </div>

                        <!-- Card Gateway Integration -->
                        <div class="nttbs-pro-card">
                            <div class="nttbs-pro-card-header">
                                <div class="hdr-text">
                                    <h3><?php esc_html_e('Stripe Cloud Gateway & Portal API', 'nahian-tour-travel-booking-solution'); ?></h3>
                                    <p><?php esc_html_e('Connect cryptographic merchant API keys for remote digital settlements.', 'nahian-tour-travel-booking-solution'); ?></p>
                                </div>
                            </div>
                            <div class="nttbs-pro-card-body">
                                <div class="nttbs-row">
                                    <label class="nttbs-form-label"><?php esc_html_e('Gateway Environment Mode', 'nahian-tour-travel-booking-solution'); ?></label>
                                    <select name="stripe_mode" class="nttbs-pro-input">
                                        <option value="test" <?php selected($stripe_mode, 'test'); ?>><?php esc_html_e('Sandbox / Test Development Environment', 'nahian-tour-travel-booking-solution'); ?></option>
                                        <option value="live" <?php selected($stripe_mode, 'live'); ?>><?php esc_html_e('Production Live Merchant Environment', 'nahian-tour-travel-booking-solution'); ?></option>
                                    </select>
                                </div>
                                <div class="nttbs-row">
                                    <label class="nttbs-form-label"><?php esc_html_e('Publishable API Token Key', 'nahian-tour-travel-booking-solution'); ?></label>
                                    <input type="text" name="stripe_public_key" value="<?php echo esc_attr($stripe_pub); ?>" class="nttbs-pro-input font-mono" placeholder="pk_test_...">
                                </div>
                                <div class="nttbs-row">
                                    <label class="nttbs-form-label"><?php esc_html_e('Restricted Secret Signing Token Key', 'nahian-tour-travel-booking-solution'); ?></label>
                                    <input type="password" name="stripe_secret_key" value="<?php echo esc_attr($stripe_sec); ?>" class="nttbs-pro-input font-mono" placeholder="sk_test_...">
                                </div>
                            </div>
                            <div class="nttbs-pro-card-footer">
                                <button type="submit" name="nttbs_dispatch_gateway_settings" class="nttbs-btn-action">
                                    <?php esc_html_e('Save Gateway Configuration', 'nahian-tour-travel-booking-solution'); ?>
                                </button>
                            </div>
                        </div>
                    </form>
                </div>

                <div class="nttbs-stage-secondary">
                    <div class="nttbs-guideline-card accent-emerald">
                        <div class="nttbs-guide-header">
                            <span class="nttbs-g-icon"><?php echo nttbs_svg_icon('wallet', 18, 18); ?></span>
                            <h4><?php esc_html_e('POS Integration Note', 'nahian-tour-travel-booking-solution'); ?></h4>
                        </div>
                        <p class="nttbs-guide-para">
                            <?php esc_html_e('Enabling POS terminals activates the immediate terminal charge protocol within the Slide-over manifest booking drawer.', 'nahian-tour-travel-booking-solution'); ?>
                        </p>
                    </div>
                </div>
            </div>

        <?php elseif ($current_subtab === 'vouchers'): ?>
            <!-- TAB 3: PROMOTIONAL VOUCHERS -->
            <div class="nttbs-stage-grid reverse-split">
                <div class="nttbs-stage-sidebar-form">
                    <div class="nttbs-pro-card">
                        <div class="nttbs-pro-card-header">
                            <div class="hdr-text">
                                <h3><?php esc_html_e('Emit Promotional Voucher', 'nahian-tour-travel-booking-solution'); ?></h3>
                                <p><?php esc_html_e('Deploy discount tokens against charter catalogs.', 'nahian-tour-travel-booking-solution'); ?></p>
                            </div>
                        </div>
                        <div class="nttbs-pro-card-body">
                            <form method="post" action="<?php echo esc_url(admin_url('admin.php?page=nttbs-core&view=settings&subtab=vouchers')); ?>">
                                <?php wp_nonce_field('nttbs_settings_nonce'); ?>

                                <div class="nttbs-row">
                                    <label class="nttbs-form-label"><?php esc_html_e('Authorization Code Token *', 'nahian-tour-travel-booking-solution'); ?></label>
                                    <input type="text" name="coupon_code" required class="nttbs-pro-input font-mono uppercase" placeholder="e.g. VIP2026">
                                </div>
                                <div class="nttbs-split-2">
                                    <div class="nttbs-row">
                                        <label class="nttbs-form-label"><?php esc_html_e('Reduction Model', 'nahian-tour-travel-booking-solution'); ?></label>
                                        <select name="discount_type" class="nttbs-pro-input">
                                            <option value="percentage"><?php esc_html_e('Percentage (%)', 'nahian-tour-travel-booking-solution'); ?></option>
                                            <option value="fixed"><?php esc_html_e('Fixed Amount', 'nahian-tour-travel-booking-solution'); ?></option>
                                        </select>
                                    </div>
                                    <div class="nttbs-row">
                                        <label class="nttbs-form-label"><?php esc_html_e('Magnitude', 'nahian-tour-travel-booking-solution'); ?></label>
                                        <input type="number" step="0.01" min="0.01" name="coupon_amount" value="10.00" required class="nttbs-pro-input font-mono">
                                    </div>
                                </div>
                                <div class="nttbs-row">
                                    <label class="nttbs-form-label"><?php esc_html_e('Redemption Threshold Quota', 'nahian-tour-travel-booking-solution'); ?></label>
                                    <input type="number" min="1" name="usage_limit" value="100" class="nttbs-pro-input font-mono">
                                </div>
                                <button type="submit" name="nttbs_dispatch_coupon_save" class="nttbs-btn-action full-width" style="margin-top:12px;">
                                    <?php esc_html_e('Emit Authorization Token', 'nahian-tour-travel-booking-solution'); ?>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="nttbs-stage-main-table">
                    <div class="nttbs-pro-card">
                        <div class="nttbs-pro-card-header">
                            <div class="hdr-text">
                                <h3><?php esc_html_e('Voucher Registry Ledger', 'nahian-tour-travel-booking-solution'); ?></h3>
                                <p><?php esc_html_e('Inspection of active promotional allocations and burn rates.', 'nahian-tour-travel-booking-solution'); ?></p>
                            </div>
                        </div>
                        <div class="nttbs-table-viewport">
                            <table class="nttbs-corporate-table">
                                <thead>
                                    <tr>
                                        <th><?php esc_html_e('Voucher Token', 'nahian-tour-travel-booking-solution'); ?></th>
                                        <th><?php esc_html_e('Reduction Weight', 'nahian-tour-travel-booking-solution'); ?></th>
                                        <th><?php esc_html_e('Burn Quota', 'nahian-tour-travel-booking-solution'); ?></th>
                                        <th><?php esc_html_e('Lifecycle', 'nahian-tour-travel-booking-solution'); ?></th>
                                        <th style="text-align:right;"><?php esc_html_e('Action', 'nahian-tour-travel-booking-solution'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($coupons)): ?>
                                        <tr>
                                            <td colspan="5" class="nttbs-empty-cell">
                                                <?php esc_html_e('No promotional allocations currently deployed in database.', 'nahian-tour-travel-booking-solution'); ?>
                                            </td>
                                        </tr>
                                    <?php else: foreach ($coupons as $cp): 
                                        $burn_ratio = $cp['usage_limit'] > 0 ? min(100, round(($cp['used_count'] / $cp['usage_limit']) * 100)) : 0;
                                    ?>
                                        <tr>
                                            <td>
                                                <code class="nttbs-voucher-token-box"><?php echo esc_html($cp['code']); ?></code>
                                            </td>
                                            <td>
                                                <span class="nttbs-weight-tag">
                                                    <?php echo $cp['discount_type'] === 'percentage' 
                                                        ? esc_html($cp['amount']) . '%' 
                                                        : esc_html($currency . ' ' . number_format_i18n((float)$cp['amount'], 2)); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="nttbs-quota-progress-cluster">
                                                    <div class="nttbs-quota-bar"><div class="nttbs-quota-fill" style="width:<?php echo esc_attr($burn_ratio); ?>%;"></div></div>
                                                    <span class="nttbs-quota-text"><?php echo esc_html($cp['used_count']); ?> / <?php echo esc_html($cp['usage_limit']); ?></span>
                                                </div>
                                            </td>
                                            <td><span class="nttbs-pill confirmed"><?php echo esc_html(strtoupper($cp['status'])); ?></span></td>
                                            <td style="text-align:right;">
                                                <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=nttbs-core&view=settings&subtab=vouchers&action=delete_coupon&coupon_id=' . $cp['id']), 'nttbs_delete_coupon_' . $cp['id'])); ?>" 
                                                   onclick="return confirm('<?php echo esc_js(__('Decommission this promotional voucher code?', 'nahian-tour-travel-booking-solution')); ?>');" 
                                                   class="nttbs-btn-revoke-inline"><?php esc_html_e('Revoke', 'nahian-tour-travel-booking-solution'); ?></a>
                                            </td>
                                        </tr>
                                    <?php endforeach; endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

        <?php elseif ($current_subtab === 'diagnostics'): ?>
            <!-- TAB 4: DIAGNOSTICS & SYSTEM INTEGRITY -->
            <div class="nttbs-pro-card">
                <div class="nttbs-pro-card-header">
                    <div class="hdr-text">
                        <h3><?php esc_html_e('Telemetry & Operational Environment Matrix', 'nahian-tour-travel-booking-solution'); ?></h3>
                        <p><?php esc_html_e('Runtime verification checks covering underlying storage engines and memory allocations.', 'nahian-tour-travel-booking-solution'); ?></p>
                    </div>
                </div>
                <div class="nttbs-table-viewport">
                    <table class="nttbs-corporate-table diagnostics">
                        <tbody>
                            <tr>
                                <td class="nttbs-metric-label"><?php esc_html_e('Execution Engine Version', 'nahian-tour-travel-booking-solution'); ?></td>
                                <td class="nttbs-metric-value"><code class="nttbs-sys-code">v<?php echo esc_html(defined('NTTBS_VERSION') ? NTTBS_VERSION : '1.0.0'); ?></code></td>
                            </tr>
                            <tr>
                                <td class="nttbs-metric-label"><?php esc_html_e('Relational Storage Engine', 'nahian-tour-travel-booking-solution'); ?></td>
                                <td class="nttbs-metric-value">
                                    <span class="nttbs-pill confirmed">
                                        <?php printf(esc_html__('Operational (MySQL %s)', 'nahian-tour-travel-booking-solution'), esc_html($wpdb->db_version())); ?>
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <td class="nttbs-metric-label"><?php esc_html_e('PHP Environment Profile', 'nahian-tour-travel-booking-solution'); ?></td>
                                <td class="nttbs-metric-value">
                                    <code class="nttbs-sys-code">PHP <?php echo esc_html(PHP_VERSION); ?></code>
                                    <span class="nttbs-metric-sub"><?php printf(esc_html__('(Memory Ceiling: %s)', 'nahian-tour-travel-booking-solution'), esc_html(ini_get('memory_limit'))); ?></span>
                                </td>
                            </tr>
                            <tr>
                                <td class="nttbs-metric-label"><?php esc_html_e('Registered Database Schemas', 'nahian-tour-travel-booking-solution'); ?></td>
                                <td class="nttbs-metric-value">
                                    <div class="nttbs-schema-chip-cluster">
                                        <?php 
                                        $schemas = [
                                            $wpdb->prefix . 'nttbs_trips',
                                            $wpdb->prefix . 'nttbs_departures',
                                            $wpdb->prefix . 'nttbs_seats',
                                            $wpdb->prefix . 'nttbs_bookings',
                                            $wpdb->prefix . 'nttbs_customers',
                                            $wpdb->prefix . 'nttbs_taxonomies',
                                            $wpdb->prefix . 'nttbs_price_categories',
                                            $wpdb->prefix . 'nttbs_coupons',
                                            $wpdb->prefix . 'nttbs_audit_logs'
                                        ];
                                        foreach ($schemas as $s): ?>
                                            <span class="nttbs-schema-chip"><?php echo esc_html($s); ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

    </div>
</div>
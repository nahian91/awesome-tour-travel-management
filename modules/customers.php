<?php
if (!defined('ABSPATH')) {
    exit;
}

$title = __('Customers & Travelers', 'nahian-tour-travel-booking-solution');

/* ==========================================================================
   1. CUSTOMER CONTROLLERS & FORM DISPATCHERS
   ========================================================================== */

function nttbs_handle_customer_dispatchers() {
    global $wpdb;

    $table_customers = $wpdb->prefix . 'nttbs_customers';
    $table_bookings  = $wpdb->prefix . 'nttbs_bookings';

    // 1.1 Customer Save/Update Dispatcher
    if (isset($_POST['nttbs_dispatch_customer_save']) && check_admin_referer('nttbs_customer_nonce')) {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Access denied: Insufficient operator permissions.', 'nahian-tour-travel-booking-solution'), 403);
        }

        $customer_id       = !empty($_POST['customer_id']) ? absint($_POST['customer_id']) : null;
        $full_name         = sanitize_text_field(wp_unslash($_POST['full_name'] ?? ''));
        $email             = sanitize_email(wp_unslash($_POST['email'] ?? ''));
        $phone             = sanitize_text_field(wp_unslash($_POST['phone'] ?? ''));
        $passport_nid      = sanitize_text_field(wp_unslash($_POST['passport_nid'] ?? ''));
        $emergency_contact = sanitize_text_field(wp_unslash($_POST['emergency_contact'] ?? ''));

        if (empty($full_name) || empty($email) || !is_email($email)) {
            wp_die(esc_html__('Validation error: Customer full name and a valid email address are mandatory.', 'nahian-tour-travel-booking-solution'));
        }

        $data = [
            'full_name'         => $full_name,
            'email'             => $email,
            'phone'             => $phone,
            'passport_nid'      => $passport_nid,
            'emergency_contact' => $emergency_contact
        ];

        $wpdb->query('START TRANSACTION');

        if ($customer_id) {
            $wpdb->update($table_customers, $data, ['id' => $customer_id], ['%s', '%s', '%s', '%s', '%s'], ['%d']);
            $msg     = 'updated';
            $log_act = 'UPDATE_CUSTOMER';
            $log_msg = "Updated customer profile #{$customer_id} ({$full_name})";
        } else {
            $data['total_bookings'] = 0;
            $data['total_spent']    = 0.00;
            $wpdb->insert($table_customers, $data, ['%s', '%s', '%s', '%s', '%s', '%d', '%f']);
            $customer_id = (int) $wpdb->insert_id;
            $msg     = 'created';
            $log_act = 'CREATE_CUSTOMER';
            $log_msg = "Created customer profile #{$customer_id} ({$full_name})";
        }

        $wpdb->query('COMMIT');

        if (function_exists('nttbs_log_activity')) {
            nttbs_log_activity($log_act, $log_msg);
        }

        wp_safe_redirect(admin_url('admin.php?page=nttbs-core&view=customers&subtab=all&status=' . $msg));
        exit;
    }

    // 1.2 Customer Delete Dispatcher
    if (isset($_GET['action']) && $_GET['action'] === 'delete_customer' && isset($_GET['customer_id'])) {
        $customer_id = absint($_GET['customer_id']);
        check_admin_referer('nttbs_delete_cust_' . $customer_id);

        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Access denied: Unauthorized operation.', 'nahian-tour-travel-booking-solution'), 403);
        }

        $wpdb->query('START TRANSACTION');
        // Unlink customer association in historical bookings before deleting
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_bookings)) === $table_bookings) {
            $wpdb->update($table_bookings, ['customer_id' => 0], ['customer_id' => $customer_id], ['%d'], ['%d']);
        }
        $wpdb->delete($table_customers, ['id' => $customer_id], ['%d']);
        $wpdb->query('COMMIT');

        if (function_exists('nttbs_log_activity')) {
            nttbs_log_activity('DELETE_CUSTOMER', "Deleted customer profile #{$customer_id}");
        }

        wp_safe_redirect(admin_url('admin.php?page=nttbs-core&view=customers&subtab=all&status=deleted'));
        exit;
    }
}
nttbs_handle_customer_dispatchers();

/* ==========================================================================
   2. DATA & ROUTING
   ========================================================================== */

global $wpdb;
$current_subtab = isset($_GET['subtab']) && in_array($_GET['subtab'], ['add', 'all'], true) ? sanitize_key($_GET['subtab']) : 'all';
$edit_id        = isset($_GET['edit_id']) ? absint($_GET['edit_id']) : 0;
$currency       = get_option('nttbs_currency_symbol', NTTBS_CURRENCY_SYMBOL_DEFAULT);

$table_customers = $wpdb->prefix . 'nttbs_customers';
$table_bookings  = $wpdb->prefix . 'nttbs_bookings';
$table_trips     = $wpdb->prefix . 'nttbs_trips';

$customer_data = null;
if ($edit_id && $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_customers)) === $table_customers) {
    $customer_data = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table_customers} WHERE id = %d", $edit_id), ARRAY_A);
    if ($customer_data) {
        $current_subtab = 'add';
    }
}

$customers = [];
if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_customers)) === $table_customers) {
    $customers = $wpdb->get_results("SELECT * FROM {$table_customers} ORDER BY total_spent DESC, id DESC", ARRAY_A) ?: [];
}
?>

<style>
.nttbs-cust-shell {
    display: flex;
    flex-direction: column;
    gap: 24px;
    animation: nttbsFadeIn 0.3s cubic-bezier(0.16, 1, 0.3, 1);
}
@keyframes nttbsFadeIn {
    from { opacity: 0; transform: translateY(6px); }
    to { opacity: 1; transform: translateY(0); }
}

/* Subnav Tabs */
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
.nttbs-pill-badge {
    background: rgba(255, 255, 255, 0.25);
    color: inherit;
    font-size: 11px;
    font-weight: 800;
    padding: 2px 8px;
    border-radius: 999px;
    font-family: 'JetBrains Mono', monospace;
}
.nttbs-pill-toggle.inactive .nttbs-pill-badge {
    background: var(--nttbs-bg-hover);
    color: var(--nttbs-text-heading);
}

/* System Alerts */
.nttbs-alert {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 18px;
    border-radius: 10px;
    font-size: 13px;
    font-weight: 600;
}
.nttbs-alert-success {
    background-color: var(--nttbs-emerald-subtle);
    border: 1px solid var(--nttbs-emerald-border);
    color: var(--nttbs-emerald);
}
.nttbs-alert-danger {
    background-color: var(--nttbs-rose-subtle);
    border: 1px solid var(--nttbs-rose-border);
    color: var(--nttbs-rose);
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

.nttbs-avatar-badge {
    width: 38px;
    height: 38px;
    border-radius: 10px;
    background-color: var(--nttbs-primary-subtle);
    color: var(--nttbs-primary-dark);
    border: 1px solid var(--nttbs-primary-border);
    font-weight: 800;
    font-size: 13px;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: var(--nttbs-shadow-xs);
    font-family: 'JetBrains Mono', monospace;
}
.nttbs-form-grid {
    display: grid;
    grid-template-columns: minmax(0, 2fr) minmax(0, 1fr);
    gap: 24px;
    align-items: start;
}
@media (max-width: 1200px) {
    .nttbs-form-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="nttbs-cust-shell">

    <!-- Subnav Tabs -->
    <div class="nttbs-subnav-bar">
        <div class="nttbs-subnav-group">
            <a href="<?php echo esc_url(admin_url('admin.php?page=nttbs-core&view=customers&subtab=all')); ?>" 
               class="nttbs-pill-toggle <?php echo $current_subtab === 'all' ? 'active' : 'inactive'; ?>">
                <span class="nttbs-nav-icon"><?php echo nttbs_svg_icon('customers', 16, 16); ?></span>
                <span><?php esc_html_e('All Customers', 'nahian-tour-travel-booking-solution'); ?></span>
                <span class="nttbs-pill-badge"><?php echo count($customers); ?></span>
            </a>
            <a href="<?php echo esc_url(admin_url('admin.php?page=nttbs-core&view=customers&subtab=add')); ?>" 
               class="nttbs-pill-toggle <?php echo $current_subtab === 'add' ? 'active' : 'inactive'; ?>">
                <span>+</span>
                <span><?php echo $edit_id ? sprintf(esc_html__('Edit Traveler #%d', 'nahian-tour-travel-booking-solution'), (int)$edit_id) : esc_html__('Add Traveler', 'nahian-tour-travel-booking-solution'); ?></span>
            </a>
        </div>

        <?php if (isset($_GET['status'])): ?>
            <?php if ($_GET['status'] === 'created'): ?>
                <div class="nttbs-alert nttbs-alert-success"><?php esc_html_e('✓ Customer profile successfully created', 'nahian-tour-travel-booking-solution'); ?></div>
            <?php elseif ($_GET['status'] === 'updated'): ?>
                <div class="nttbs-alert nttbs-alert-success"><?php esc_html_e('✓ Customer profile specifications updated', 'nahian-tour-travel-booking-solution'); ?></div>
            <?php elseif ($_GET['status'] === 'deleted'): ?>
                <div class="nttbs-alert nttbs-alert-danger"><?php esc_html_e('✓ Customer record removed from system', 'nahian-tour-travel-booking-solution'); ?></div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <?php if ($current_subtab === 'all'): ?>
        <!-- TAB 1: CUSTOMER DIRECTORY -->
        <div class="nttbs-white-panel">
            <div class="nttbs-panel-header">
                <div style="display:flex; align-items:center; gap:8px;">
                    <span class="nttbs-nav-icon nttbs-icon-customers"><?php echo nttbs_svg_icon('customers', 18, 18); ?></span>
                    <span><?php printf(esc_html__('Traveler Profiles & Commercial Client Ledger (%d)', 'nahian-tour-travel-booking-solution'), count($customers)); ?></span>
                </div>
                <a href="<?php echo esc_url(admin_url('admin.php?page=nttbs-core&view=customers&subtab=add')); ?>" class="nttbs-btn-primary" style="padding:7px 14px; font-size:12px; text-decoration:none;">
                    + <?php esc_html_e('Add Customer', 'nahian-tour-travel-booking-solution'); ?>
                </a>
            </div>

            <div class="nttbs-table-responsive">
                <table class="nttbs-data-table">
                    <thead>
                        <tr>
                            <th style="width:50px;"><?php esc_html_e('Ref', 'nahian-tour-travel-booking-solution'); ?></th>
                            <th><?php esc_html_e('Customer Dossier', 'nahian-tour-travel-booking-solution'); ?></th>
                            <th><?php esc_html_e('Contact Email / Phone', 'nahian-tour-travel-booking-solution'); ?></th>
                            <th><?php esc_html_e('Passport / National ID', 'nahian-tour-travel-booking-solution'); ?></th>
                            <th><?php esc_html_e('Manifests', 'nahian-tour-travel-booking-solution'); ?></th>
                            <th><?php esc_html_e('Lifetime Revenue', 'nahian-tour-travel-booking-solution'); ?></th>
                            <th style="text-align:right;"><?php esc_html_e('Actions', 'nahian-tour-travel-booking-solution'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($customers)): ?>
                            <tr><td colspan="7" style="text-align:center; padding:44px 20px; color:var(--nttbs-text-muted);"><?php esc_html_e('No customer records found in directory.', 'nahian-tour-travel-booking-solution'); ?></td></tr>
                        <?php else: foreach ($customers as $c): 
                            $initials = strtoupper(substr($c['full_name'], 0, 2));
                        ?>
                            <tr>
                                <td>
                                    <div class="nttbs-avatar-badge"><?php echo esc_html($initials); ?></div>
                                </td>
                                <td>
                                    <strong style="font-size:13.5px; color:var(--nttbs-text-heading);"><?php echo esc_html($c['full_name']); ?></strong><br>
                                    <small style="color:var(--nttbs-text-muted); font-size:11.5px;"><?php printf(esc_html__('Member since %s', 'nahian-tour-travel-booking-solution'), esc_html(substr($c['created_at'] ?? current_time('mysql'), 0, 10))); ?></small>
                                </td>
                                <td>
                                    <strong style="color:var(--nttbs-text-heading);"><?php echo esc_html($c['email']); ?></strong><br>
                                    <small style="color:var(--nttbs-text-muted); font-size:11.5px;"><?php echo esc_html($c['phone'] ?: __('No Phone Recorded', 'nahian-tour-travel-booking-solution')); ?></small>
                                </td>
                                <td>
                                    <code style="font-size:11.5px; background:var(--nttbs-bg-body); border:1px solid var(--nttbs-border); padding:3px 7px; border-radius:5px; font-family:'JetBrains Mono', monospace; color:var(--nttbs-text-muted);">
                                        <?php echo esc_html($c['passport_nid'] ?: __('N/A', 'nahian-tour-travel-booking-solution')); ?>
                                    </code>
                                </td>
                                <td>
                                    <span style="background-color:var(--nttbs-primary-subtle); color:var(--nttbs-primary-dark); border:1px solid var(--nttbs-primary-border); padding:3px 8px; border-radius:6px; font-weight:800; font-size:11px; font-family:'JetBrains Mono', monospace;">
                                        <?php echo esc_html($c['total_bookings']); ?> <?php esc_html_e('Tours', 'nahian-tour-travel-booking-solution'); ?>
                                    </span>
                                </td>
                                <td>
                                    <strong style="color:var(--nttbs-emerald); font-size:13.5px; font-weight:800; font-family:'JetBrains Mono', monospace;">
                                        <?php echo esc_html($currency . ' ' . number_format_i18n((float)$c['total_spent'], 2)); ?>
                                    </strong>
                                </td>
                                <td style="text-align:right; white-space:nowrap;">
                                    <a href="<?php echo esc_url(admin_url('admin.php?page=nttbs-core&view=customers&subtab=add&edit_id=' . $c['id'])); ?>" 
                                       style="color:var(--nttbs-primary); font-weight:800; text-decoration:none; margin-right:12px;"><?php esc_html_e('Edit', 'nahian-tour-travel-booking-solution'); ?></a>
                                    <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=nttbs-core&view=customers&action=delete_customer&customer_id=' . $c['id']), 'nttbs_delete_cust_' . $c['id'])); ?>" 
                                       onclick="return confirm('<?php echo esc_js(__('Permanently remove this traveler profile? Bookings will be unlinked.', 'nahian-tour-travel-booking-solution')); ?>');" 
                                       style="color:var(--nttbs-rose); font-weight:800; text-decoration:none;"><?php esc_html_e('Delete', 'nahian-tour-travel-booking-solution'); ?></a>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    <?php else: ?>
        <!-- TAB 2: ADD / EDIT CUSTOMER PROFILE -->
        <div class="nttbs-form-grid">
            <div class="nttbs-white-panel">
                <div class="nttbs-panel-header">
                    <span><?php echo $edit_id ? esc_html__('Update Traveler Dossier', 'nahian-tour-travel-booking-solution') : esc_html__('Register New Traveler Profile', 'nahian-tour-travel-booking-solution'); ?></span>
                </div>

                <form method="post" action="<?php echo esc_url(admin_url('admin.php?page=nttbs-core&view=customers&subtab=add')); ?>">
                    <?php wp_nonce_field('nttbs_customer_nonce'); ?>
                    <input type="hidden" name="customer_id" value="<?php echo esc_attr($customer_data['id'] ?? ''); ?>">

                    <div class="nttbs-field-row">
                        <label class="nttbs-field-label"><?php esc_html_e('Full Legal Passenger Name *', 'nahian-tour-travel-booking-solution'); ?></label>
                        <input type="text" name="full_name" value="<?php echo esc_attr($customer_data['full_name'] ?? ''); ?>" required class="nttbs-control-input" placeholder="<?php esc_attr_e('e.g. Abdullah Nahian', 'nahian-tour-travel-booking-solution'); ?>">
                    </div>

                    <div style="display:grid; grid-template-columns: repeat(2, 1fr); gap:18px;">
                        <div class="nttbs-field-row">
                            <label class="nttbs-field-label"><?php esc_html_e('Primary Email Address *', 'nahian-tour-travel-booking-solution'); ?></label>
                            <input type="email" name="email" value="<?php echo esc_attr($customer_data['email'] ?? ''); ?>" required class="nttbs-control-input" placeholder="traveler@domain.com">
                        </div>
                        <div class="nttbs-field-row">
                            <label class="nttbs-field-label"><?php esc_html_e('Contact Telephone *', 'nahian-tour-travel-booking-solution'); ?></label>
                            <input type="text" name="phone" value="<?php echo esc_attr($customer_data['phone'] ?? ''); ?>" required class="nttbs-control-input" placeholder="+1 234 567 8900">
                        </div>
                    </div>

                    <div style="display:grid; grid-template-columns: repeat(2, 1fr); gap:18px;">
                        <div class="nttbs-field-row">
                            <label class="nttbs-field-label"><?php esc_html_e('Passport / Identification Document', 'nahian-tour-travel-booking-solution'); ?></label>
                            <input type="text" name="passport_nid" value="<?php echo esc_attr($customer_data['passport_nid'] ?? ''); ?>" class="nttbs-control-input" placeholder="e.g. A12345678" style="font-family:'JetBrains Mono', monospace;">
                            <small style="color:var(--nttbs-text-muted); font-size:11.5px;"><?php esc_html_e('Required for port clearances and coast guard manifests.', 'nahian-tour-travel-booking-solution'); ?></small>
                        </div>
                        <div class="nttbs-field-row">
                            <label class="nttbs-field-label"><?php esc_html_e('Emergency Escort Contact', 'nahian-tour-travel-booking-solution'); ?></label>
                            <input type="text" name="emergency_contact" value="<?php echo esc_attr($customer_data['emergency_contact'] ?? ''); ?>" class="nttbs-control-input" placeholder="<?php esc_attr_e('Name & Phone Number', 'nahian-tour-travel-booking-solution'); ?>">
                            <small style="color:var(--nttbs-text-muted); font-size:11.5px;"><?php esc_html_e('Contact for urgent operational or medical matters.', 'nahian-tour-travel-booking-solution'); ?></small>
                        </div>
                    </div>

                    <button type="submit" name="nttbs_dispatch_customer_save" class="nttbs-btn-primary" style="margin-top:8px; width:100%; padding:14px; font-size:13.5px;">
                        <?php echo $edit_id ? esc_html__('Update Traveler Dossier', 'nahian-tour-travel-booking-solution') : esc_html__('Save & Register Traveler Profile', 'nahian-tour-travel-booking-solution'); ?>
                    </button>
                </form>
            </div>

            <!-- Right Insights Sidebar -->
            <div>
                <?php if ($edit_id && $customer_data): 
                    $cust_bookings = [];
                    if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_bookings)) === $table_bookings) {
                        $cust_bookings = $wpdb->get_results($wpdb->prepare(
                            "SELECT b.*, t.title AS trip_title 
                             FROM {$table_bookings} b 
                             LEFT JOIN {$table_trips} t ON b.trip_id = t.id 
                             WHERE b.customer_id = %d 
                             ORDER BY b.id DESC LIMIT 5",
                            $edit_id
                        ), ARRAY_A) ?: [];
                    }
                ?>
                    <div class="nttbs-white-panel" style="border-top:4px solid var(--nttbs-primary);">
                        <div class="nttbs-panel-header"><span><?php esc_html_e('Client Lifetime Value', 'nahian-tour-travel-booking-solution'); ?></span></div>
                        <div style="display:flex; justify-content:space-between; margin-bottom:14px; font-size:13px;">
                            <span style="color:var(--nttbs-text-muted);"><?php esc_html_e('Manifest Registrations:', 'nahian-tour-travel-booking-solution'); ?></span>
                            <strong style="color:var(--nttbs-text-heading); font-weight:800; font-family:'JetBrains Mono', monospace;"><?php echo esc_html($customer_data['total_bookings']); ?></strong>
                        </div>
                        <div style="display:flex; justify-content:space-between; font-size:13px;">
                            <span style="color:var(--nttbs-text-muted);"><?php esc_html_e('Total Volume Settled:', 'nahian-tour-travel-booking-solution'); ?></span>
                            <strong style="color:var(--nttbs-emerald); font-weight:800; font-family:'JetBrains Mono', monospace;"><?php echo esc_html($currency . ' ' . number_format_i18n((float)$customer_data['total_spent'], 2)); ?></strong>
                        </div>
                    </div>

                    <div class="nttbs-white-panel">
                        <div class="nttbs-panel-header"><span><?php esc_html_e('Recent Reservation History', 'nahian-tour-travel-booking-solution'); ?></span></div>
                        <?php if (empty($cust_bookings)): ?>
                            <p style="font-size:12.5px; color:var(--nttbs-text-muted); margin:0;"><?php esc_html_e('No prior booking reservations linked to this traveler.', 'nahian-tour-travel-booking-solution'); ?></p>
                        <?php else: foreach ($cust_bookings as $cb): ?>
                            <div style="padding-bottom:12px; margin-bottom:12px; border-bottom:1px solid var(--nttbs-border); font-size:12.5px;">
                                <strong style="color:var(--nttbs-text-heading); font-weight:700;"><?php echo esc_html($cb['trip_title'] ?: __('Custom Expedition', 'nahian-tour-travel-booking-solution')); ?></strong><br>
                                <div style="color:var(--nttbs-text-muted); font-size:11.5px; margin-top:3px;">
                                    <?php printf(esc_html__('Code: %s • Amount: %s', 'nahian-tour-travel-booking-solution'), '<code style="font-family:\'JetBrains Mono\', monospace; color:var(--nttbs-primary-dark); background:var(--nttbs-primary-subtle); padding:2px 6px; border-radius:4px; border:1px solid var(--nttbs-primary-border);">' . esc_html($cb['booking_code']) . '</code>', esc_html($currency . ' ' . number_format_i18n((float)$cb['total_amount'], 2))); ?>
                                </div>
                            </div>
                        <?php endforeach; endif; ?>
                    </div>
                <?php else: ?>
                    <div class="nttbs-white-panel" style="border-left:4px solid var(--nttbs-primary);">
                        <div class="nttbs-panel-header"><span><?php esc_html_e('CRM Documentation', 'nahian-tour-travel-booking-solution'); ?></span></div>
                        <p style="font-size:12.5px; color:var(--nttbs-text-muted); line-height:1.6; margin:0;">
                            <?php esc_html_e('Traveler profiles store verified passenger documentation and contact channels. Completed booking invoices dynamically aggregate into lifetime customer metrics.', 'nahian-tour-travel-booking-solution'); ?>
                        </p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

</div>
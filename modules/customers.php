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
.nte-cust-shell {
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

.nte-alert {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 6px 14px;
    border-radius: 8px;
    font-size: 12px;
    font-weight: 700;
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

.nte-avatar-badge {
    width: 36px;
    height: 36px;
    border-radius: 8px;
    background-color: var(--c-blue-bg);
    color: var(--c-blue);
    border: 1px solid var(--c-blue-border);
    font-weight: 800;
    font-size: 13px;
    display: flex;
    align-items: center;
    justify-content: center;
}
</style>

<div class="nte-cust-shell">

    <!-- Subnav Tabs -->
    <div class="nte-subnav-bar">
        <div style="display:flex; gap:10px;">
            <a href="<?php echo esc_url(admin_url('admin.php?page=nttbs-core&view=customers&subtab=all')); ?>" 
               class="nte-pill-toggle <?php echo $current_subtab === 'all' ? 'active' : 'inactive'; ?>">
                <span class="nte-nav-icon"><?php echo nttbs_svg_icon('customers', 16, 16); ?></span>
                <?php esc_html_e('All Customers', 'nahian-tour-travel-booking-solution'); ?>
                <span style="background:rgba(255,255,255,0.25); font-size:11px; padding:2px 7px; border-radius:999px; margin-left:4px;">
                    <?php echo count($customers); ?>
                </span>
            </a>
            <a href="<?php echo esc_url(admin_url('admin.php?page=nttbs-core&view=customers&subtab=add')); ?>" 
               class="nte-pill-toggle <?php echo $current_subtab === 'add' ? 'active' : 'inactive'; ?>">
                <span>+</span> <?php echo $edit_id ? sprintf(esc_html__('Edit Traveler #%d', 'nahian-tour-travel-booking-solution'), (int)$edit_id) : esc_html__('Add Traveler', 'nahian-tour-travel-booking-solution'); ?>
            </a>
        </div>

        <?php if (isset($_GET['status'])): ?>
            <?php if ($_GET['status'] === 'created'): ?>
                <div class="nte-alert nte-alert-success"><?php esc_html_e('✓ Customer profile successfully created', 'nahian-tour-travel-booking-solution'); ?></div>
            <?php elseif ($_GET['status'] === 'updated'): ?>
                <div class="nte-alert nte-alert-success"><?php esc_html_e('✓ Customer profile specifications updated', 'nahian-tour-travel-booking-solution'); ?></div>
            <?php elseif ($_GET['status'] === 'deleted'): ?>
                <div class="nte-alert nte-alert-danger"><?php esc_html_e('✓ Customer record removed from system', 'nahian-tour-travel-booking-solution'); ?></div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <?php if ($current_subtab === 'all'): ?>
        <!-- TAB 1: CUSTOMER DIRECTORY -->
        <div class="nte-white-panel">
            <div class="nte-panel-header">
                <div style="display:flex; align-items:center; gap:8px;">
                    <span class="nte-nav-icon icon-cust"><?php echo nttbs_svg_icon('customers', 18, 18); ?></span>
                    <span><?php printf(esc_html__('Traveler Profiles & Commercial Client Ledger (%d)', 'nahian-tour-travel-booking-solution'), count($customers)); ?></span>
                </div>
                <a href="<?php echo esc_url(admin_url('admin.php?page=nttbs-core&view=customers&subtab=add')); ?>" class="nte-btn-primary" style="padding:6px 14px; font-size:12px; text-decoration:none;">
                    + <?php esc_html_e('Add Customer', 'nahian-tour-travel-booking-solution'); ?>
                </a>
            </div>

            <div class="nte-table-responsive">
                <table class="nte-data-table">
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
                            <tr><td colspan="7" style="text-align:center; padding:36px; color:var(--text-muted);"><?php esc_html_e('No customer records found in directory.', 'nahian-tour-travel-booking-solution'); ?></td></tr>
                        <?php else: foreach ($customers as $c): 
                            $initials = strtoupper(substr($c['full_name'], 0, 2));
                        ?>
                            <tr>
                                <td>
                                    <div class="nte-avatar-badge"><?php echo esc_html($initials); ?></div>
                                </td>
                                <td>
                                    <strong style="font-size:13px; color:var(--text-main);"><?php echo esc_html($c['full_name']); ?></strong><br>
                                    <small style="color:var(--text-muted); font-size:11px;"><?php printf(esc_html__('Member since %s', 'nahian-tour-travel-booking-solution'), esc_html(substr($c['created_at'] ?? current_time('mysql'), 0, 10))); ?></small>
                                </td>
                                <td>
                                    <strong style="color:var(--text-main);"><?php echo esc_html($c['email']); ?></strong><br>
                                    <small style="color:var(--text-muted);"><?php echo esc_html($c['phone'] ?: __('No Phone Recorded', 'nahian-tour-travel-booking-solution')); ?></small>
                                </td>
                                <td>
                                    <code style="font-size:11px; background:var(--bg-subtle); border:1px solid var(--border-light); padding:2px 6px; border-radius:4px; font-family:ui-monospace, monospace; color:var(--text-muted);">
                                        <?php echo esc_html($c['passport_nid'] ?: __('N/A', 'nahian-tour-travel-booking-solution')); ?>
                                    </code>
                                </td>
                                <td>
                                    <span style="background-color:var(--c-blue-bg); color:var(--c-blue); border:1px solid var(--c-blue-border); padding:2px 7px; border-radius:6px; font-weight:800; font-size:11px;">
                                        <?php echo esc_html($c['total_bookings']); ?> <?php esc_html_e('Tours', 'nahian-tour-travel-booking-solution'); ?>
                                    </span>
                                </td>
                                <td>
                                    <strong style="color:var(--c-emerald); font-size:13px; font-weight:800;">
                                        <?php echo esc_html($currency . ' ' . number_format_i18n((float)$c['total_spent'], 2)); ?>
                                    </strong>
                                </td>
                                <td style="text-align:right; white-space:nowrap;">
                                    <a href="<?php echo esc_url(admin_url('admin.php?page=nttbs-core&view=customers&subtab=add&edit_id=' . $c['id'])); ?>" 
                                       style="color:var(--c-blue); font-weight:800; text-decoration:none; margin-right:12px;"><?php esc_html_e('Edit', 'nahian-tour-travel-booking-solution'); ?></a>
                                    <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=nttbs-core&view=customers&action=delete_customer&customer_id=' . $c['id']), 'nttbs_delete_cust_' . $c['id'])); ?>" 
                                       onclick="return confirm('<?php echo esc_js(__('Permanently remove this traveler profile? Bookings will be unlinked.', 'nahian-tour-travel-booking-solution')); ?>');" 
                                       style="color:var(--c-rose); font-weight:800; text-decoration:none;"><?php esc_html_e('Delete', 'nahian-tour-travel-booking-solution'); ?></a>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    <?php else: ?>
        <!-- TAB 2: ADD / EDIT CUSTOMER PROFILE -->
        <div style="display:grid; grid-template-columns: minmax(0, 2fr) minmax(0, 1fr); gap:24px; align-items:start;">
            <div class="nte-white-panel">
                <div class="nte-panel-header">
                    <span><?php echo $edit_id ? esc_html__('Update Traveler Dossier', 'nahian-tour-travel-booking-solution') : esc_html__('Register New Traveler Profile', 'nahian-tour-travel-booking-solution'); ?></span>
                </div>

                <form method="post" action="<?php echo esc_url(admin_url('admin.php?page=nttbs-core&view=customers&subtab=add')); ?>">
                    <?php wp_nonce_field('nttbs_customer_nonce'); ?>
                    <input type="hidden" name="customer_id" value="<?php echo esc_attr($customer_data['id'] ?? ''); ?>">

                    <div class="nte-field-row">
                        <label class="nte-field-label"><?php esc_html_e('Full Legal Passenger Name *', 'nahian-tour-travel-booking-solution'); ?></label>
                        <input type="text" name="full_name" value="<?php echo esc_attr($customer_data['full_name'] ?? ''); ?>" required class="nte-control-input" placeholder="<?php esc_attr_e('e.g. Abdullah Nahian', 'nahian-tour-travel-booking-solution'); ?>">
                    </div>

                    <div style="display:grid; grid-template-columns: repeat(2, 1fr); gap:16px;">
                        <div class="nte-field-row">
                            <label class="nte-field-label"><?php esc_html_e('Primary Email Address *', 'nahian-tour-travel-booking-solution'); ?></label>
                            <input type="email" name="email" value="<?php echo esc_attr($customer_data['email'] ?? ''); ?>" required class="nte-control-input" placeholder="traveler@domain.com">
                        </div>
                        <div class="nte-field-row">
                            <label class="nte-field-label"><?php esc_html_e('Contact Telephone *', 'nahian-tour-travel-booking-solution'); ?></label>
                            <input type="text" name="phone" value="<?php echo esc_attr($customer_data['phone'] ?? ''); ?>" required class="nte-control-input" placeholder="+00 000 000 0000">
                        </div>
                    </div>

                    <div style="display:grid; grid-template-columns: repeat(2, 1fr); gap:16px;">
                        <div class="nte-field-row">
                            <label class="nte-field-label"><?php esc_html_e('Passport / Identification Document', 'nahian-tour-travel-booking-solution'); ?></label>
                            <input type="text" name="passport_nid" value="<?php echo esc_attr($customer_data['passport_nid'] ?? ''); ?>" class="nte-control-input" placeholder="e.g. A12345678">
                            <small style="color:var(--text-muted); font-size:11px;"><?php esc_html_e('Required for port clearances and coast guard manifests.', 'nahian-tour-travel-booking-solution'); ?></small>
                        </div>
                        <div class="nte-field-row">
                            <label class="nte-field-label"><?php esc_html_e('Emergency Escort Contact', 'nahian-tour-travel-booking-solution'); ?></label>
                            <input type="text" name="emergency_contact" value="<?php echo esc_attr($customer_data['emergency_contact'] ?? ''); ?>" class="nte-control-input" placeholder="<?php esc_attr_e('Name & Phone Number', 'nahian-tour-travel-booking-solution'); ?>">
                            <small style="color:var(--text-muted); font-size:11px;"><?php esc_html_e('Contact for urgent operational or medical matters.', 'nahian-tour-travel-booking-solution'); ?></small>
                        </div>
                    </div>

                    <button type="submit" name="nttbs_dispatch_customer_save" class="nte-btn-primary" style="margin-top:8px; width:100%; padding:12px; font-size:13px;">
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
                    <div class="nte-white-panel" style="border-top:4px solid var(--c-blue);">
                        <div class="nte-panel-header"><span><?php esc_html_e('Client Lifetime Value', 'nahian-tour-travel-booking-solution'); ?></span></div>
                        <div style="display:flex; justify-content:space-between; margin-bottom:12px; font-size:13px;">
                            <span style="color:var(--text-muted);"><?php esc_html_e('Manifest Registrations:', 'nahian-tour-travel-booking-solution'); ?></span>
                            <strong style="color:var(--text-main); font-weight:800;"><?php echo esc_html($customer_data['total_bookings']); ?></strong>
                        </div>
                        <div style="display:flex; justify-content:space-between; font-size:13px;">
                            <span style="color:var(--text-muted);"><?php esc_html_e('Total Volume Settled:', 'nahian-tour-travel-booking-solution'); ?></span>
                            <strong style="color:var(--c-emerald); font-weight:800;"><?php echo esc_html($currency . ' ' . number_format_i18n((float)$customer_data['total_spent'], 2)); ?></strong>
                        </div>
                    </div>

                    <div class="nte-white-panel">
                        <div class="nte-panel-header"><span><?php esc_html_e('Recent Reservation History', 'nahian-tour-travel-booking-solution'); ?></span></div>
                        <?php if (empty($cust_bookings)): ?>
                            <p style="font-size:12px; color:var(--text-muted); margin:0;"><?php esc_html_e('No prior booking reservations linked to this traveler.', 'nahian-tour-travel-booking-solution'); ?></p>
                        <?php else: foreach ($cust_bookings as $cb): ?>
                            <div style="padding-bottom:10px; margin-bottom:10px; border-bottom:1px solid var(--border-light); font-size:12px;">
                                <strong style="color:var(--text-main); font-weight:700;"><?php echo esc_html($cb['trip_title'] ?: __('Custom Expedition', 'nahian-tour-travel-booking-solution')); ?></strong><br>
                                <div style="color:var(--text-muted); font-size:11px; margin-top:2px;">
                                    <?php printf(esc_html__('Code: %s • Amount: %s', 'nahian-tour-travel-booking-solution'), '<code style="font-family:ui-monospace, monospace; color:var(--c-blue);">' . esc_html($cb['booking_code']) . '</code>', esc_html($currency . ' ' . number_format_i18n((float)$cb['total_amount'], 2))); ?>
                                </div>
                            </div>
                        <?php endforeach; endif; ?>
                    </div>
                <?php else: ?>
                    <div class="nte-white-panel" style="border-left:4px solid var(--c-blue);">
                        <div class="nte-panel-header"><span><?php esc_html_e('CRM Documentation', 'nahian-tour-travel-booking-solution'); ?></span></div>
                        <p style="font-size:12px; color:var(--text-muted); line-height:1.6; margin:0;">
                            <?php esc_html_e('Traveler profiles store verified passenger documentation and contact channels. Completed booking invoices dynamically aggregate into lifetime customer metrics.', 'nahian-tour-travel-booking-solution'); ?>
                        </p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

</div>
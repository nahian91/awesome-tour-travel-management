<?php
if (!defined('ABSPATH')) {
    exit;
}

$title = __('Price Categories', 'nahian-tour-travel-booking-solution');
$desc  = __('Manage passenger tariff groups, pricing tiers, age brackets, and dynamic fare multipliers.', 'nahian-tour-travel-booking-solution');

/* ==========================================================================
   1. DATABASE SCHEMA AUTO-UPGRADE (PARENT, SLUG, PRIMARY & AGE GROUP)
   ========================================================================== */

function nttbs_ensure_price_category_schema_columns() {
    global $wpdb;
    $table = $wpdb->prefix . 'nttbs_price_categories';

    $schema_version = get_option('nttbs_price_cat_schema_version', '1.0.0');
    if (version_compare($schema_version, '1.1.0', '>=')) {
        return;
    }

    if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table)) !== $table) {
        return;
    }

    $columns = [
        'slug'       => "varchar(100) NOT NULL DEFAULT '' AFTER `name`",
        'parent_id'  => "bigint(20) unsigned NOT NULL DEFAULT 0 AFTER `slug`",
        'is_primary' => "tinyint(1) NOT NULL DEFAULT 0 AFTER `multiplier`",
        'age_group'  => "varchar(50) NOT NULL DEFAULT 'Adult' AFTER `is_primary`"
    ];

    foreach ($columns as $col => $definition) {
        $check = $wpdb->get_results($wpdb->prepare("SHOW COLUMNS FROM `{$table}` LIKE %s", $col));
        if (empty($check)) {
            $wpdb->query("ALTER TABLE `{$table}` ADD `{$col}` {$definition}");
        }
    }

    update_option('nttbs_price_cat_schema_version', '1.1.0');
}
nttbs_ensure_price_category_schema_columns();

/* ==========================================================================
   2. CONTROLLER DISPATCHERS (CREATE / UPDATE / DELETE)
   ========================================================================== */

function nttbs_handle_price_cat_dispatchers() {
    global $wpdb;

    $table_cats = $wpdb->prefix . 'nttbs_price_categories';

    // 2.1 Save or Update Price Category
    if (isset($_POST['nttbs_dispatch_price_cat_save']) && check_admin_referer('nttbs_price_cat_nonce')) {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Access denied: Insufficient permissions.', 'nahian-tour-travel-booking-solution'), 403);
        }

        $cat_id     = !empty($_POST['cat_id']) ? absint($_POST['cat_id']) : null;
        $name       = sanitize_text_field(wp_unslash($_POST['name'] ?? ''));
        $raw_slug   = sanitize_text_field(wp_unslash($_POST['slug'] ?? ''));
        $slug       = sanitize_title(!empty($raw_slug) ? $raw_slug : $name);
        $parent_id  = absint($_POST['parent_id'] ?? 0);
        $raw_code   = sanitize_text_field(wp_unslash($_POST['code'] ?? ''));
        $code       = strtoupper(!empty($raw_code) ? $raw_code : $slug);
        $multiplier = max(0.00, floatval($_POST['multiplier'] ?? 1.00));
        $is_primary = isset($_POST['is_primary']) ? 1 : 0;
        $age_group  = sanitize_text_field(wp_unslash($_POST['age_group'] ?? 'Adult'));
        $desc       = sanitize_textarea_field(wp_unslash($_POST['description'] ?? ''));

        if (empty($name)) {
            wp_die(esc_html__('Validation error: Price Category name is required.', 'nahian-tour-travel-booking-solution'));
        }

        if ($cat_id && $cat_id === $parent_id) {
            $parent_id = 0;
        }

        $wpdb->query('START TRANSACTION');

        if ($is_primary) {
            $wpdb->update($table_cats, ['is_primary' => 0], ['is_primary' => 1]);
        }

        $data = [
            'name'        => $name,
            'slug'        => $slug,
            'parent_id'   => $parent_id,
            'code'        => $code,
            'multiplier'  => $multiplier,
            'is_primary'  => $is_primary,
            'age_group'   => $age_group,
            'description' => $desc,
            'status'      => 'active'
        ];

        if ($cat_id) {
            $wpdb->update($table_cats, $data, ['id' => $cat_id]);
            $msg = 'updated';
            $log_act = 'UPDATE_PRICE_CAT';
            $log_msg = "Updated tariff category #{$cat_id} ({$name})";
        } else {
            $wpdb->insert($table_cats, $data);
            $cat_id  = (int) $wpdb->insert_id;
            $msg = 'created';
            $log_act = 'CREATE_PRICE_CAT';
            $log_msg = "Created tariff category #{$cat_id} ({$name})";
        }

        $wpdb->query('COMMIT');

        if (function_exists('nttbs_log_activity')) {
            nttbs_log_activity($log_act, $log_msg);
        }

        wp_safe_redirect(admin_url('admin.php?page=nttbs-core&view=price_categories&subtab=all&status=' . $msg));
        exit;
    }

    // 2.2 Delete Price Category
    if (isset($_GET['action']) && $_GET['action'] === 'delete_price_cat' && isset($_GET['cat_id'])) {
        $cat_id = absint($_GET['cat_id']);
        check_admin_referer('nttbs_delete_cat_' . $cat_id);

        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Access denied.', 'nahian-tour-travel-booking-solution'), 403);
        }

        $wpdb->query('START TRANSACTION');
        $wpdb->update($table_cats, ['parent_id' => 0], ['parent_id' => $cat_id]);
        $wpdb->delete($table_cats, ['id' => $cat_id], ['%d']);
        $wpdb->query('COMMIT');

        if (function_exists('nttbs_log_activity')) {
            nttbs_log_activity('DELETE_PRICE_CAT', "Deleted tariff category #{$cat_id}");
        }

        wp_safe_redirect(admin_url('admin.php?page=nttbs-core&view=price_categories&subtab=all&status=deleted'));
        exit;
    }
}
nttbs_handle_price_cat_dispatchers();

/* ==========================================================================
   3. DATA FETCHING & SUBTAB ROUTING
   ========================================================================== */

global $wpdb;
$current_subtab = isset($_GET['subtab']) && in_array($_GET['subtab'], ['add', 'all'], true) ? sanitize_key($_GET['subtab']) : 'all';
$edit_id        = isset($_GET['edit_id']) ? absint($_GET['edit_id']) : 0;
$edit_cat       = null;

$table_cats = $wpdb->prefix . 'nttbs_price_categories';

if ($edit_id && $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_cats)) === $table_cats) {
    $edit_cat = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table_cats} WHERE id = %d", $edit_id), ARRAY_A);
    if ($edit_cat) {
        $current_subtab = 'add';
    }
}

$cats = [];
if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_cats)) === $table_cats) {
    $cats = $wpdb->get_results(
        "SELECT c.*, p.name AS parent_name 
         FROM {$table_cats} c 
         LEFT JOIN {$table_cats} p ON c.parent_id = p.id 
         ORDER BY c.is_primary DESC, c.multiplier ASC, c.name ASC",
        ARRAY_A
    ) ?: [];
}
?>

<style>
.nttbs-price-shell {
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

/* Badges */
.nttbs-badge-primary {
    display: inline-flex;
    align-items: center;
    background: var(--nttbs-emerald-subtle);
    color: var(--nttbs-emerald);
    border: 1px solid var(--nttbs-emerald-border);
    font-size: 10.5px;
    padding: 3px 9px;
    border-radius: 999px;
    font-weight: 800;
    letter-spacing: 0.3px;
}
.nttbs-badge-parent {
    display: inline-flex;
    align-items: center;
    background: var(--nttbs-amber-subtle);
    color: var(--nttbs-amber);
    border: 1px solid var(--nttbs-amber-border);
    font-size: 11px;
    padding: 2px 8px;
    border-radius: 6px;
    font-weight: 700;
}
.nttbs-badge-age {
    display: inline-flex;
    align-items: center;
    background-color: var(--nttbs-purple-subtle);
    color: var(--nttbs-purple);
    border: 1px solid var(--nttbs-purple-border);
    padding: 2px 8px;
    border-radius: 6px;
    font-weight: 700;
    font-size: 11px;
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

<div class="nttbs-price-shell">

    <!-- Subnav Tabs -->
    <div class="nttbs-subnav-bar">
        <div class="nttbs-subnav-group">
            <a href="<?php echo esc_url(admin_url('admin.php?page=nttbs-core&view=price_categories&subtab=all')); ?>" 
               class="nttbs-pill-toggle <?php echo $current_subtab === 'all' ? 'active' : 'inactive'; ?>">
                <span class="nttbs-nav-icon"><?php echo nttbs_svg_icon('price_cat', 16, 16); ?></span>
                <span><?php esc_html_e('All Price Categories', 'nahian-tour-travel-booking-solution'); ?></span>
                <span class="nttbs-pill-badge"><?php echo count($cats); ?></span>
            </a>
            <a href="<?php echo esc_url(admin_url('admin.php?page=nttbs-core&view=price_categories&subtab=add')); ?>" 
               class="nttbs-pill-toggle <?php echo $current_subtab === 'add' ? 'active' : 'inactive'; ?>">
                <span>+</span>
                <span><?php echo $edit_cat ? sprintf(esc_html__('Edit Category #%d', 'nahian-tour-travel-booking-solution'), (int)$edit_cat['id']) : esc_html__('Add Price Category', 'nahian-tour-travel-booking-solution'); ?></span>
            </a>
        </div>

        <?php if (isset($_GET['status'])): ?>
            <?php if ($_GET['status'] === 'created'): ?>
                <div class="nttbs-alert nttbs-alert-success"><?php esc_html_e('✓ Price Category successfully created', 'nahian-tour-travel-booking-solution'); ?></div>
            <?php elseif ($_GET['status'] === 'updated'): ?>
                <div class="nttbs-alert nttbs-alert-success"><?php esc_html_e('✓ Price Category successfully updated', 'nahian-tour-travel-booking-solution'); ?></div>
            <?php elseif ($_GET['status'] === 'deleted'): ?>
                <div class="nttbs-alert nttbs-alert-danger"><?php esc_html_e('✓ Price Category removed', 'nahian-tour-travel-booking-solution'); ?></div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <?php if ($current_subtab === 'all'): ?>
        <!-- TAB 1: ALL PRICE CATEGORIES TABLE -->
        <div class="nttbs-white-panel">
            <div class="nttbs-panel-header">
                <div style="display:flex; align-items:center; gap:8px;">
                    <span class="nttbs-nav-icon nttbs-icon-price_cat"><?php echo nttbs_svg_icon('price_cat', 18, 18); ?></span>
                    <span><?php printf(esc_html__('Dynamic Pricing Tariff Tiers (%d)', 'nahian-tour-travel-booking-solution'), count($cats)); ?></span>
                </div>
                <a href="<?php echo esc_url(admin_url('admin.php?page=nttbs-core&view=price_categories&subtab=add')); ?>" class="nttbs-btn-primary" style="padding:7px 14px; font-size:12px; text-decoration:none;">
                    + <?php esc_html_e('Add Price Category', 'nahian-tour-travel-booking-solution'); ?>
                </a>
            </div>
            <p style="font-size:12px; color:var(--nttbs-text-muted); margin-bottom:18px; margin-top:-8px;"><?php echo esc_html($desc); ?></p>

            <div class="nttbs-table-responsive">
                <table class="nttbs-data-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Code', 'nahian-tour-travel-booking-solution'); ?></th>
                            <th><?php esc_html_e('Name', 'nahian-tour-travel-booking-solution'); ?></th>
                            <th><?php esc_html_e('Age Bracket', 'nahian-tour-travel-booking-solution'); ?></th>
                            <th><?php esc_html_e('Hierarchy / Parent', 'nahian-tour-travel-booking-solution'); ?></th>
                            <th><?php esc_html_e('Slug', 'nahian-tour-travel-booking-solution'); ?></th>
                            <th><?php esc_html_e('Multiplier', 'nahian-tour-travel-booking-solution'); ?></th>
                            <th><?php esc_html_e('Primary Default', 'nahian-tour-travel-booking-solution'); ?></th>
                            <th style="text-align:right;"><?php esc_html_e('Actions', 'nahian-tour-travel-booking-solution'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($cats)): ?>
                            <tr><td colspan="8" style="text-align:center; padding:44px 20px; color:var(--nttbs-text-muted);"><?php esc_html_e('No tariff tiers recorded in database.', 'nahian-tour-travel-booking-solution'); ?></td></tr>
                        <?php else: foreach ($cats as $c): ?>
                            <tr>
                                <td>
                                    <code style="background:var(--nttbs-primary-subtle); color:var(--nttbs-primary-dark); border:1px solid var(--nttbs-primary-border); padding:4px 8px; border-radius:6px; font-weight:800; font-family:'JetBrains Mono', monospace; font-size:12px;">
                                        <?php echo esc_html($c['code']); ?>
                                    </code>
                                </td>
                                <td>
                                    <strong style="font-size:13.5px; color:var(--nttbs-text-heading);"><?php echo esc_html($c['name']); ?></strong>
                                    <?php if (!empty($c['description'])): ?>
                                        <br><small style="color:var(--nttbs-text-muted); font-size:11.5px;"><?php echo esc_html(wp_trim_words($c['description'], 10)); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="nttbs-badge-age">
                                        <?php echo esc_html($c['age_group'] ?: 'Adult'); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if (!empty($c['parent_name'])): ?>
                                        <span class="nttbs-badge-parent">
                                            ↳ <?php echo esc_html($c['parent_name']); ?>
                                        </span>
                                    <?php else: ?>
                                        <span style="color:var(--nttbs-text-muted); font-size:11.5px; font-weight:600;"><?php esc_html_e('— Root Baseline —', 'nahian-tour-travel-booking-solution'); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <code style="font-size:11.5px; background:var(--nttbs-bg-body); border:1px solid var(--nttbs-border); padding:3px 7px; border-radius:5px; color:var(--nttbs-text-muted); font-family:'JetBrains Mono', monospace;">
                                        <?php echo esc_html($c['slug'] ?: strtolower($c['code'])); ?>
                                    </code>
                                </td>
                                <td>
                                    <strong style="color:var(--nttbs-emerald); font-size:13.5px; font-weight:800; font-family:'JetBrains Mono', monospace;"><?php echo esc_html(number_format((float)$c['multiplier'], 2)); ?>x</strong>
                                </td>
                                <td>
                                    <?php if (!empty($c['is_primary'])): ?>
                                        <span class="nttbs-badge-primary">
                                            <?php esc_html_e('★ PRIMARY TARIFF', 'nahian-tour-travel-booking-solution'); ?>
                                        </span>
                                    <?php else: ?>
                                        <span style="color:var(--nttbs-text-muted); font-size:12px;">—</span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align:right; white-space:nowrap;">
                                    <a href="<?php echo esc_url(admin_url('admin.php?page=nttbs-core&view=price_categories&subtab=add&edit_id=' . $c['id'])); ?>" 
                                       style="color:var(--nttbs-primary); font-weight:800; text-decoration:none; margin-right:12px;"><?php esc_html_e('Edit', 'nahian-tour-travel-booking-solution'); ?></a>
                                    <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=nttbs-core&view=price_categories&action=delete_price_cat&cat_id=' . $c['id']), 'nttbs_delete_cat_' . $c['id'])); ?>" 
                                       onclick="return confirm('<?php echo esc_js(__('Remove this tariff category? Child tiers will automatically detach to root level.', 'nahian-tour-travel-booking-solution')); ?>');" 
                                       style="color:var(--nttbs-rose); font-weight:800; text-decoration:none;"><?php esc_html_e('Delete', 'nahian-tour-travel-booking-solution'); ?></a>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    <?php else: ?>
        <!-- TAB 2: ADD / EDIT PRICE CATEGORY FORM -->
        <div class="nttbs-form-grid">
            <div class="nttbs-white-panel">
                <div class="nttbs-panel-header">
                    <span><?php echo $edit_cat ? esc_html__('Edit Price Category Specifications', 'nahian-tour-travel-booking-solution') : esc_html__('Deploy New Tariff Category', 'nahian-tour-travel-booking-solution'); ?></span>
                </div>

                <form method="post" action="<?php echo esc_url(admin_url('admin.php?page=nttbs-core&view=price_categories&subtab=add')); ?>">
                    <?php wp_nonce_field('nttbs_price_cat_nonce'); ?>
                    <input type="hidden" name="cat_id" value="<?php echo esc_attr($edit_cat['id'] ?? ''); ?>">

                    <!-- 1. Name -->
                    <div class="nttbs-field-row">
                        <label class="nttbs-field-label"><?php esc_html_e('Category Name *', 'nahian-tour-travel-booking-solution'); ?></label>
                        <input type="text" name="name" id="nttbs_cat_name" required class="nttbs-control-input" 
                               value="<?php echo esc_attr($edit_cat['name'] ?? ''); ?>" 
                               placeholder="<?php esc_attr_e('e.g. Standard Adult Pass', 'nahian-tour-travel-booking-solution'); ?>" onkeyup="nttbsAutoGenerateSlugAndCode(this.value)">
                    </div>

                    <!-- 2. Slug & Code -->
                    <div style="display:grid; grid-template-columns: repeat(2, 1fr); gap:18px;">
                        <div class="nttbs-field-row">
                            <label class="nttbs-field-label"><?php esc_html_e('Formula Slug', 'nahian-tour-travel-booking-solution'); ?></label>
                            <input type="text" name="slug" id="nttbs_cat_slug" class="nttbs-control-input" 
                                   value="<?php echo esc_attr($edit_cat['slug'] ?? ''); ?>" 
                                   placeholder="standard-adult-pass">
                            <small style="color:var(--nttbs-text-muted); font-size:11.5px;"><?php esc_html_e('Unique identifier used in formulas and exports.', 'nahian-tour-travel-booking-solution'); ?></small>
                        </div>
                        <div class="nttbs-field-row">
                            <label class="nttbs-field-label"><?php esc_html_e('Tariff Code *', 'nahian-tour-travel-booking-solution'); ?></label>
                            <input type="text" name="code" id="nttbs_cat_code" required class="nttbs-control-input" 
                                   value="<?php echo esc_attr($edit_cat['code'] ?? ''); ?>" 
                                   placeholder="ADULT_STD" style="font-family:'JetBrains Mono', monospace; font-weight:700;">
                            <small style="color:var(--nttbs-text-muted); font-size:11.5px;"><?php esc_html_e('Short reference identifier (e.g. ADULT, CHILD, VIP).', 'nahian-tour-travel-booking-solution'); ?></small>
                        </div>
                    </div>

                    <!-- 3. Parent Category Selection -->
                    <div class="nttbs-field-row">
                        <label class="nttbs-field-label"><?php esc_html_e('Parent Tariff Tier', 'nahian-tour-travel-booking-solution'); ?></label>
                        <select name="parent_id" class="nttbs-control-input">
                            <option value="0"><?php esc_html_e('None (Root Baseline Level)', 'nahian-tour-travel-booking-solution'); ?></option>
                            <?php foreach ($cats as $c): ?>
                                <?php if ($edit_cat && (int)$c['id'] === (int)$edit_cat['id']) continue; ?>
                                <option value="<?php echo esc_attr($c['id']); ?>" <?php selected($edit_cat['parent_id'] ?? 0, $c['id']); ?>>
                                    <?php echo esc_html($c['name'] . ' (' . $c['code'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small style="color:var(--nttbs-text-muted); font-size:11.5px;"><?php esc_html_e('Nest sub-tier pricing (e.g. Student Weekend) under a parent bracket.', 'nahian-tour-travel-booking-solution'); ?></small>
                    </div>

                    <!-- 4. Age Group Selection & Multiplier -->
                    <div style="display:grid; grid-template-columns: repeat(2, 1fr); gap:18px;">
                        <div class="nttbs-field-row">
                            <label class="nttbs-field-label"><?php esc_html_e('Target Age Bracket', 'nahian-tour-travel-booking-solution'); ?></label>
                            <select name="age_group" class="nttbs-control-input">
                                <?php
                                $age_groups = [
                                    'Adult'         => __('Adult', 'nahian-tour-travel-booking-solution'),
                                    'Youth (12-17)' => __('Youth (12-17)', 'nahian-tour-travel-booking-solution'),
                                    'Child (3-11)'  => __('Child (3-11)', 'nahian-tour-travel-booking-solution'),
                                    'Infant (0-2)'  => __('Infant (0-2)', 'nahian-tour-travel-booking-solution'),
                                    'Senior (60+)'  => __('Senior (60+)', 'nahian-tour-travel-booking-solution'),
                                    'All Ages'      => __('All Ages', 'nahian-tour-travel-booking-solution'),
                                ];
                                foreach ($age_groups as $key => $label):
                                ?>
                                    <option value="<?php echo esc_attr($key); ?>" <?php selected($edit_cat['age_group'] ?? 'Adult', $key); ?>>
                                        <?php echo esc_html($label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small style="color:var(--nttbs-text-muted); font-size:11.5px;"><?php esc_html_e('Used to validate manifest passenger eligibility.', 'nahian-tour-travel-booking-solution'); ?></small>
                        </div>
                        <div class="nttbs-field-row">
                            <label class="nttbs-field-label"><?php esc_html_e('Fare Multiplier (1.00 = Base Rate)', 'nahian-tour-travel-booking-solution'); ?></label>
                            <input type="number" step="0.01" min="0" name="multiplier" class="nttbs-control-input" 
                                   value="<?php echo esc_attr($edit_cat['multiplier'] ?? '1.00'); ?>" required style="font-family:'JetBrains Mono', monospace; font-weight:700;">
                            <small style="color:var(--nttbs-text-muted); font-size:11.5px;"><?php esc_html_e('e.g. 0.60 = 40% discount, 1.25 = 25% surcharge.', 'nahian-tour-travel-booking-solution'); ?></small>
                        </div>
                    </div>

                    <!-- 5. Primary Category Toggle -->
                    <div class="nttbs-field-row" style="background:var(--nttbs-bg-body); padding:18px; border-radius:10px; border:1px solid var(--nttbs-border);">
                        <label style="cursor:pointer; display:flex; align-items:center; gap:10px; font-size:13px; font-weight:700; color:var(--nttbs-text-heading);">
                            <input type="checkbox" name="is_primary" value="1" <?php checked($edit_cat['is_primary'] ?? 0, 1); ?> style="width:16px; height:16px;">
                            <?php esc_html_e('Designate as Primary Default Tariff Tier', 'nahian-tour-travel-booking-solution'); ?>
                        </label>
                        <small style="color:var(--nttbs-text-muted); font-size:11.5px; display:block; margin-top:6px;">
                            <?php esc_html_e('The primary tier serves as the initial rate applied to new bookings and quick checkout operations.', 'nahian-tour-travel-booking-solution'); ?>
                        </small>
                    </div>

                    <!-- 6. Description -->
                    <div class="nttbs-field-row">
                        <label class="nttbs-field-label"><?php esc_html_e('Operational Documentation', 'nahian-tour-travel-booking-solution'); ?></label>
                        <textarea name="description" rows="3" class="nttbs-control-input" placeholder="<?php esc_attr_e('Tariff rules, identity document requirements, age verification terms...', 'nahian-tour-travel-booking-solution'); ?>"><?php echo esc_textarea($edit_cat['description'] ?? ''); ?></textarea>
                    </div>

                    <button type="submit" name="nttbs_dispatch_price_cat_save" class="nttbs-btn-primary" style="width:100%; padding:14px; font-size:13.5px; margin-top:8px;">
                        <?php echo $edit_cat ? esc_html__('Update Price Category', 'nahian-tour-travel-booking-solution') : esc_html__('Save & Deploy Price Category', 'nahian-tour-travel-booking-solution'); ?>
                    </button>
                </form>
            </div>

            <!-- Right Architectural Guidelines -->
            <div>
                <div class="nttbs-white-panel" style="border-left:4px solid var(--nttbs-amber);">
                    <div class="nttbs-panel-header">
                        <span><?php esc_html_e('Dynamic Pricing Engine', 'nahian-tour-travel-booking-solution'); ?></span>
                    </div>
                    <p style="font-size:12.5px; color:var(--nttbs-text-muted); line-height:1.6; margin-bottom:16px; margin-top:-8px;">
                        <?php esc_html_e('Tariff categories establish real-time calculations against base excursion prices across the reservation engine.', 'nahian-tour-travel-booking-solution'); ?>
                    </p>
                    <ul style="font-size:12.5px; color:var(--nttbs-text-muted); padding-left:16px; line-height:1.7; margin:0;">
                        <li><strong><?php esc_html_e('Rate Factor:', 'nahian-tour-travel-booking-solution'); ?></strong> <?php esc_html_e('Multiplier adjusts the adult base fare directly (e.g. € 100.00 × 0.60 = € 60.00 Child).', 'nahian-tour-travel-booking-solution'); ?></li>
                        <li><strong><?php esc_html_e('Primary Default:', 'nahian-tour-travel-booking-solution'); ?></strong> <?php esc_html_e('Selecting a tier as Primary automatically clears the previous primary record.', 'nahian-tour-travel-booking-solution'); ?></li>
                        <li><strong><?php esc_html_e('Parent Hierarchy:', 'nahian-tour-travel-booking-solution'); ?></strong> <?php esc_html_e('Allows nesting corporate, promotional, or local discounts underneath standard brackets.', 'nahian-tour-travel-booking-solution'); ?></li>
                    </ul>
                </div>
            </div>
        </div>
    <?php endif; ?>

</div>

<script>
function nttbsAutoGenerateSlugAndCode(val) {
    <?php if (!$edit_cat): ?>
    const slugInput = document.getElementById('nttbs_cat_slug');
    const codeInput = document.getElementById('nttbs_cat_code');

    if (slugInput && !slugInput.dataset.manual) {
        slugInput.value = val.toLowerCase()
            .replace(/[^\w ]+/g, '')
            .replace(/ +/g, '-');
    }

    if (codeInput && !codeInput.dataset.manual) {
        codeInput.value = val.toUpperCase()
            .replace(/[^\w ]+/g, '')
            .replace(/ +/g, '_')
            .substring(0, 16);
    }
    <?php endif; ?>
}
</script>
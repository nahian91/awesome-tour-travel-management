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
.nte-price-shell {
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

.nte-badge-primary {
    display: inline-flex;
    align-items: center;
    background: var(--c-emerald-bg);
    color: var(--c-emerald);
    border: 1px solid var(--c-emerald-border);
    font-size: 10px;
    padding: 2px 7px;
    border-radius: 999px;
    font-weight: 800;
    letter-spacing: 0.3px;
}
.nte-badge-parent {
    display: inline-flex;
    align-items: center;
    background: var(--c-amber-bg);
    color: var(--c-amber);
    border: 1px solid var(--c-amber-border);
    font-size: 11px;
    padding: 2px 8px;
    border-radius: 6px;
    font-weight: 700;
}
.nte-badge-age {
    display: inline-flex;
    align-items: center;
    background-color: var(--c-purple-bg);
    color: var(--c-purple);
    border: 1px solid var(--c-purple-border);
    padding: 2px 8px;
    border-radius: 6px;
    font-weight: 700;
    font-size: 11px;
}
</style>

<div class="nte-price-shell">

    <!-- Subnav Tabs -->
    <div class="nte-subnav-bar">
        <div style="display:flex; gap:10px;">
            <a href="<?php echo esc_url(admin_url('admin.php?page=nttbs-core&view=price_categories&subtab=all')); ?>" 
               class="nte-pill-toggle <?php echo $current_subtab === 'all' ? 'active' : 'inactive'; ?>">
                <span class="nte-nav-icon"><?php echo nttbs_svg_icon('price_cat', 16, 16); ?></span>
                <?php esc_html_e('All Price Categories', 'nahian-tour-travel-booking-solution'); ?>
                <span style="background:rgba(255,255,255,0.25); font-size:11px; padding:2px 7px; border-radius:999px; margin-left:4px;">
                    <?php echo count($cats); ?>
                </span>
            </a>
            <a href="<?php echo esc_url(admin_url('admin.php?page=nttbs-core&view=price_categories&subtab=add')); ?>" 
               class="nte-pill-toggle <?php echo $current_subtab === 'add' ? 'active' : 'inactive'; ?>">
                <span>+</span> <?php echo $edit_cat ? sprintf(esc_html__('Edit Category #%d', 'nahian-tour-travel-booking-solution'), (int)$edit_cat['id']) : esc_html__('Add Price Category', 'nahian-tour-travel-booking-solution'); ?>
            </a>
        </div>

        <?php if (isset($_GET['status'])): ?>
            <?php if ($_GET['status'] === 'created'): ?>
                <div class="nte-alert nte-alert-success"><?php esc_html_e('✓ Price Category successfully created', 'nahian-tour-travel-booking-solution'); ?></div>
            <?php elseif ($_GET['status'] === 'updated'): ?>
                <div class="nte-alert nte-alert-success"><?php esc_html_e('✓ Price Category successfully updated', 'nahian-tour-travel-booking-solution'); ?></div>
            <?php elseif ($_GET['status'] === 'deleted'): ?>
                <div class="nte-alert nte-alert-danger"><?php esc_html_e('✓ Price Category removed', 'nahian-tour-travel-booking-solution'); ?></div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <?php if ($current_subtab === 'all'): ?>
        <!-- TAB 1: ALL PRICE CATEGORIES TABLE -->
        <div class="nte-white-panel">
            <div class="nte-panel-header">
                <div style="display:flex; align-items:center; gap:8px;">
                    <span class="nte-nav-icon icon-price"><?php echo nttbs_svg_icon('price_cat', 18, 18); ?></span>
                    <span><?php printf(esc_html__('Dynamic Pricing Tariff Tiers (%d)', 'nahian-tour-travel-booking-solution'), count($cats)); ?></span>
                </div>
                <a href="<?php echo esc_url(admin_url('admin.php?page=nttbs-core&view=price_categories&subtab=add')); ?>" class="nte-btn-primary" style="padding:6px 14px; font-size:12px; text-decoration:none;">
                    + <?php esc_html_e('Add Price Category', 'nahian-tour-travel-booking-solution'); ?>
                </a>
            </div>
            <p style="font-size:12px; color:var(--text-muted); margin-bottom:16px; margin-top:-6px;"><?php echo esc_html($desc); ?></p>

            <div class="nte-table-responsive">
                <table class="nte-data-table">
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
                            <tr><td colspan="8" style="text-align:center; padding:36px; color:var(--text-muted);"><?php esc_html_e('No tariff tiers recorded in database.', 'nahian-tour-travel-booking-solution'); ?></td></tr>
                        <?php else: foreach ($cats as $c): ?>
                            <tr>
                                <td>
                                    <code style="background:var(--c-blue-bg); color:var(--c-blue); border:1px solid var(--c-blue-border); padding:3px 6px; border-radius:4px; font-weight:800; font-family:ui-monospace, monospace;">
                                        <?php echo esc_html($c['code']); ?>
                                    </code>
                                </td>
                                <td>
                                    <strong style="font-size:13px; color:var(--text-main);"><?php echo esc_html($c['name']); ?></strong>
                                    <?php if (!empty($c['description'])): ?>
                                        <br><small style="color:var(--text-muted);"><?php echo esc_html(wp_trim_words($c['description'], 10)); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="nte-badge-age">
                                        <?php echo esc_html($c['age_group'] ?: 'Adult'); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if (!empty($c['parent_name'])): ?>
                                        <span class="nte-badge-parent">
                                            ↳ <?php echo esc_html($c['parent_name']); ?>
                                        </span>
                                    <?php else: ?>
                                        <span style="color:var(--text-muted); font-size:11px; font-weight:600;"><?php esc_html_e('— Root Baseline —', 'nahian-tour-travel-booking-solution'); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <code style="font-size:11px; background:var(--bg-subtle); border:1px solid var(--border-light); padding:2px 6px; border-radius:4px; color:var(--text-muted);">
                                        <?php echo esc_html($c['slug'] ?: strtolower($c['code'])); ?>
                                    </code>
                                </td>
                                <td>
                                    <strong style="color:var(--c-emerald); font-size:13px; font-weight:800;"><?php echo esc_html(number_format((float)$c['multiplier'], 2)); ?>x</strong>
                                </td>
                                <td>
                                    <?php if (!empty($c['is_primary'])): ?>
                                        <span class="nte-badge-primary">
                                            <?php esc_html_e('★ PRIMARY TARIFF', 'nahian-tour-travel-booking-solution'); ?>
                                        </span>
                                    <?php else: ?>
                                        <span style="color:var(--text-muted); font-size:12px;">—</span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align:right; white-space:nowrap;">
                                    <a href="<?php echo esc_url(admin_url('admin.php?page=nttbs-core&view=price_categories&subtab=add&edit_id=' . $c['id'])); ?>" 
                                       style="color:var(--c-blue); font-weight:800; text-decoration:none; margin-right:12px;"><?php esc_html_e('Edit', 'nahian-tour-travel-booking-solution'); ?></a>
                                    <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=nttbs-core&view=price_categories&action=delete_price_cat&cat_id=' . $c['id']), 'nttbs_delete_cat_' .$c['id'])); ?>" 
                                       onclick="return confirm('<?php echo esc_js(__('Remove this tariff category? Child tiers will automatically detach to root level.', 'nahian-tour-travel-booking-solution')); ?>');" 
                                       style="color:var(--c-rose); font-weight:800; text-decoration:none;"><?php esc_html_e('Delete', 'nahian-tour-travel-booking-solution'); ?></a>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    <?php else: ?>
        <!-- TAB 2: ADD / EDIT PRICE CATEGORY FORM -->
        <div style="display:grid; grid-template-columns: minmax(0, 2fr) minmax(0, 1fr); gap:24px; align-items:start;">
            <div class="nte-white-panel">
                <div class="nte-panel-header">
                    <span><?php echo $edit_cat ? esc_html__('Edit Price Category Specifications', 'nahian-tour-travel-booking-solution') : esc_html__('Deploy New Tariff Category', 'nahian-tour-travel-booking-solution'); ?></span>
                </div>

                <form method="post" action="<?php echo esc_url(admin_url('admin.php?page=nttbs-core&view=price_categories&subtab=add')); ?>">
                    <?php wp_nonce_field('nttbs_price_cat_nonce'); ?>
                    <input type="hidden" name="cat_id" value="<?php echo esc_attr($edit_cat['id'] ?? ''); ?>">

                    <!-- 1. Name -->
                    <div class="nte-field-row">
                        <label class="nte-field-label"><?php esc_html_e('Category Name *', 'nahian-tour-travel-booking-solution'); ?></label>
                        <input type="text" name="name" id="nttbs_cat_name" required class="nte-control-input" 
                               value="<?php echo esc_attr($edit_cat['name'] ?? ''); ?>" 
                               placeholder="<?php esc_attr_e('e.g. Standard Adult Pass', 'nahian-tour-travel-booking-solution'); ?>" onkeyup="nttbsAutoGenerateSlugAndCode(this.value)">
                    </div>

                    <!-- 2. Slug & Code -->
                    <div style="display:grid; grid-template-columns: repeat(2, 1fr); gap:16px;">
                        <div class="nte-field-row">
                            <label class="nte-field-label"><?php esc_html_e('Formula Slug', 'nahian-tour-travel-booking-solution'); ?></label>
                            <input type="text" name="slug" id="nttbs_cat_slug" class="nte-control-input" 
                                   value="<?php echo esc_attr($edit_cat['slug'] ?? ''); ?>" 
                                   placeholder="standard-adult-pass">
                            <small style="color:var(--text-muted); font-size:11px;"><?php esc_html_e('Unique identifier used in formulas and exports.', 'nahian-tour-travel-booking-solution'); ?></small>
                        </div>
                        <div class="nte-field-row">
                            <label class="nte-field-label"><?php esc_html_e('Tariff Code *', 'nahian-tour-travel-booking-solution'); ?></label>
                            <input type="text" name="code" id="nttbs_cat_code" required class="nte-control-input" 
                                   value="<?php echo esc_attr($edit_cat['code'] ?? ''); ?>" 
                                   placeholder="ADULT_STD">
                            <small style="color:var(--text-muted); font-size:11px;"><?php esc_html_e('Short reference identifier (e.g. ADULT, CHILD, VIP).', 'nahian-tour-travel-booking-solution'); ?></small>
                        </div>
                    </div>

                    <!-- 3. Parent Category Selection -->
                    <div class="nte-field-row">
                        <label class="nte-field-label"><?php esc_html_e('Parent Tariff Tier', 'nahian-tour-travel-booking-solution'); ?></label>
                        <select name="parent_id" class="nte-control-input">
                            <option value="0"><?php esc_html_e('None (Root Baseline Level)', 'nahian-tour-travel-booking-solution'); ?></option>
                            <?php foreach ($cats as$c): ?>
                                <?php if ($edit_cat && (int)$c['id'] === (int)$edit_cat['id']) continue; ?>
                                <option value="<?php echo esc_attr($c['id']); ?>" <?php selected($edit_cat['parent_id'] ?? 0,$c['id']); ?>>
                                    <?php echo esc_html($c['name'] . ' (' .$c['code'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small style="color:var(--text-muted); font-size:11px;"><?php esc_html_e('Nest sub-tier pricing (e.g. Student Weekend) under a parent bracket.', 'nahian-tour-travel-booking-solution'); ?></small>
                    </div>

                    <!-- 4. Age Group Selection & Multiplier -->
                    <div style="display:grid; grid-template-columns: repeat(2, 1fr); gap:16px;">
                        <div class="nte-field-row">
                            <label class="nte-field-label"><?php esc_html_e('Target Age Bracket', 'nahian-tour-travel-booking-solution'); ?></label>
                            <select name="age_group" class="nte-control-input">
                                <?php
                                $age_groups = [
                                    'Adult'         => __('Adult', 'nahian-tour-travel-booking-solution'),
                                    'Youth (12-17)' => __('Youth (12-17)', 'nahian-tour-travel-booking-solution'),
                                    'Child (3-11)'  => __('Child (3-11)', 'nahian-tour-travel-booking-solution'),
                                    'Infant (0-2)'  => __('Infant (0-2)', 'nahian-tour-travel-booking-solution'),
                                    'Senior (60+)'  => __('Senior (60+)', 'nahian-tour-travel-booking-solution'),
                                    'All Ages'      => __('All Ages', 'nahian-tour-travel-booking-solution'),
                                ];
                                foreach ($age_groups as $key =>$label):
                                ?>
                                    <option value="<?php echo esc_attr($key); ?>" <?php selected($edit_cat['age_group'] ?? 'Adult',$key); ?>>
                                        <?php echo esc_html($label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small style="color:var(--text-muted); font-size:11px;"><?php esc_html_e('Used to validate manifest passenger eligibility.', 'nahian-tour-travel-booking-solution'); ?></small>
                        </div>
                        <div class="nte-field-row">
                            <label class="nte-field-label"><?php esc_html_e('Fare Multiplier (1.00 = Base Rate)', 'nahian-tour-travel-booking-solution'); ?></label>
                            <input type="number" step="0.01" min="0" name="multiplier" class="nte-control-input" 
                                   value="<?php echo esc_attr($edit_cat['multiplier'] ?? '1.00'); ?>" required>
                            <small style="color:var(--text-muted); font-size:11px;"><?php esc_html_e('e.g. 0.60 = 40% discount, 1.25 = 25% surcharge.', 'nahian-tour-travel-booking-solution'); ?></small>
                        </div>
                    </div>

                    <!-- 5. Primary Category Toggle -->
                    <div class="nte-field-row" style="background:var(--bg-subtle); padding:16px; border-radius:8px; border:1px solid var(--border-light);">
                        <label style="cursor:pointer; display:flex; align-items:center; gap:10px; font-size:13px; font-weight:800; color:var(--text-main);">
                            <input type="checkbox" name="is_primary" value="1" <?php checked($edit_cat['is_primary'] ?? 0, 1); ?> style="width:16px; height:16px;">
                            <?php esc_html_e('Designate as Primary Default Tariff Tier', 'nahian-tour-travel-booking-solution'); ?>
                        </label>
                        <small style="color:var(--text-muted); font-size:11px; display:block; margin-top:4px;">
                            <?php esc_html_e('The primary tier serves as the initial rate applied to new bookings and quick checkout operations.', 'nahian-tour-travel-booking-solution'); ?>
                        </small>
                    </div>

                    <!-- 6. Description -->
                    <div class="nte-field-row">
                        <label class="nte-field-label"><?php esc_html_e('Operational Documentation', 'nahian-tour-travel-booking-solution'); ?></label>
                        <textarea name="description" rows="3" class="nte-control-input" placeholder="<?php esc_attr_e('Tariff rules, identity document requirements, age verification terms...', 'nahian-tour-travel-booking-solution'); ?>"><?php echo esc_textarea($edit_cat['description'] ?? ''); ?></textarea>
                    </div>

                    <button type="submit" name="nttbs_dispatch_price_cat_save" class="nte-btn-primary" style="width:100%; padding:12px; font-size:13px; margin-top:8px;">
                        <?php echo $edit_cat ? esc_html__('Update Price Category', 'nahian-tour-travel-booking-solution') : esc_html__('Save & Deploy Price Category', 'nahian-tour-travel-booking-solution'); ?>
                    </button>
                </form>
            </div>

            <!-- Right Architectural Guidelines -->
            <div>
                <div class="nte-white-panel" style="border-left:4px solid var(--c-amber);">
                    <div class="nte-panel-header">
                        <span><?php esc_html_e('Dynamic Pricing Engine', 'nahian-tour-travel-booking-solution'); ?></span>
                    </div>
                    <p style="font-size:12px; color:var(--text-muted); line-height:1.6; margin-bottom:14px; margin-top:-6px;">
                        <?php esc_html_e('Tariff categories establish real-time calculations against base excursion prices across the reservation engine.', 'nahian-tour-travel-booking-solution'); ?>
                    </p>
                    <ul style="font-size:12px; color:var(--text-muted); padding-left:16px; line-height:1.7; margin:0;">
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
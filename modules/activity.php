<?php
if (!defined('ABSPATH')) {
    exit;
}

$type  = 'activity';
$title = __('Activities', 'nahian-tour-travel-booking-solution');
$desc  = __('Manage tour activities, parent categories, featured visuals, and expedition tags.', 'nahian-tour-travel-booking-solution');

/* ==========================================================================
   1. DATABASE SCHEMA AUTO-UPGRADE CHECK (CACHED VERSION MIGRATION)
   ========================================================================== */

function nttbs_ensure_activity_schema_columns() {
    global $wpdb;
    $table = $wpdb->prefix . 'nttbs_taxonomies';

    $schema_version = get_option('nttbs_activity_schema_version', '1.0.0');
    if (version_compare($schema_version, '1.1.0', '>=')) {
        return;
    }

    if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table)) !== $table) {
        return;
    }

    $columns = [
        'parent_id' => "bigint(20) unsigned NOT NULL DEFAULT 0 AFTER `type`",
        'image_url' => "varchar(255) DEFAULT '' AFTER `badge_color`"
    ];

    foreach ($columns as $col => $definition) {
        $check = $wpdb->get_results($wpdb->prepare("SHOW COLUMNS FROM `{$table}` LIKE %s", $col));
        if (empty($check)) {
            $wpdb->query("ALTER TABLE `{$table}` ADD `{$col}` {$definition}");
        }
    }

    update_option('nttbs_activity_schema_version', '1.1.0');
}
nttbs_ensure_activity_schema_columns();

/* ==========================================================================
   2. CONTROLLER DISPATCHERS (CREATE / UPDATE / DELETE)
   ========================================================================== */

function nttbs_handle_activity_dispatchers() {
    global $wpdb;

    $table_tax = $wpdb->prefix . 'nttbs_taxonomies';

    // 2.1 Save or Update Activity
    if (isset($_POST['nttbs_dispatch_activity_save']) && check_admin_referer('nttbs_activity_nonce')) {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Access denied: Insufficient operator clearance.', 'nahian-tour-travel-booking-solution'), 403);
        }

        $act_id    = !empty($_POST['activity_id']) ? absint($_POST['activity_id']) : null;
        $name      = sanitize_text_field(wp_unslash($_POST['name'] ?? ''));
        $raw_slug  = sanitize_text_field(wp_unslash($_POST['slug'] ?? ''));
        $slug      = sanitize_title(!empty($raw_slug) ? $raw_slug : $name);
        $parent_id = absint($_POST['parent_id'] ?? 0);
        $image_url = esc_url_raw(wp_unslash($_POST['image_url'] ?? ''));
        $desc      = sanitize_textarea_field(wp_unslash($_POST['description'] ?? ''));
        $color     = sanitize_hex_color($_POST['badge_color'] ?? '') ?: '#10b981';

        if (empty($name)) {
            wp_die(esc_html__('Validation error: Activity designation name is required.', 'nahian-tour-travel-booking-solution'));
        }

        // Avoid cyclic parenting
        if ($act_id && $act_id === $parent_id) {
            $parent_id = 0;
        }

        $data = [
            'type'        => 'activity',
            'parent_id'   => $parent_id,
            'name'        => $name,
            'slug'        => $slug,
            'description' => $desc,
            'badge_color' => $color,
            'image_url'   => $image_url
        ];

        $wpdb->query('START TRANSACTION');

        if ($act_id) {
            $wpdb->update($table_tax, $data, ['id' => $act_id]);
            $msg     = 'updated';
            $log_act = 'UPDATE_ACTIVITY';
            $log_msg = "Updated activity #{$act_id} ({$name})";
        } else {
            $wpdb->insert($table_tax, $data);
            $act_id  = (int) $wpdb->insert_id;
            $msg     = 'created';
            $log_act = 'CREATE_ACTIVITY';
            $log_msg = "Created activity #{$act_id} ({$name})";
        }

        $wpdb->query('COMMIT');

        if (function_exists('nttbs_log_activity')) {
            nttbs_log_activity($log_act, $log_msg);
        }

        wp_safe_redirect(admin_url('admin.php?page=nttbs-core&view=activity&subtab=all&status=' . $msg));
        exit;
    }

    // 2.2 Delete Activity
    if (isset($_GET['action']) && $_GET['action'] === 'delete_act' && isset($_GET['act_id'])) {
        $act_id = absint($_GET['act_id']);
        check_admin_referer('nttbs_delete_act_' . $act_id);

        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Access denied: Unauthorized operation.', 'nahian-tour-travel-booking-solution'), 403);
        }

        $wpdb->query('START TRANSACTION');
        // Unlink nested child activities to root level
        $wpdb->update($table_tax, ['parent_id' => 0], ['parent_id' => $act_id], ['%d'], ['%d']);
        $wpdb->delete($table_tax, ['id' => $act_id], ['%d']);
        $wpdb->query('COMMIT');

        if (function_exists('nttbs_log_activity')) {
            nttbs_log_activity('DELETE_ACTIVITY', "Deleted activity #{$act_id}");
        }

        wp_safe_redirect(admin_url('admin.php?page=nttbs-core&view=activity&subtab=all&status=deleted'));
        exit;
    }
}
nttbs_handle_activity_dispatchers();

/* ==========================================================================
   3. DATA FETCHING & SUBTAB ROUTING
   ========================================================================== */

global $wpdb;
$current_subtab = isset($_GET['subtab']) && in_array($_GET['subtab'], ['add', 'all'], true) ? sanitize_key($_GET['subtab']) : 'all';
$edit_id        = isset($_GET['edit_id']) ? absint($_GET['edit_id']) : 0;
$edit_act       = null;

$table_tax = $wpdb->prefix . 'nttbs_taxonomies';

if ($edit_id && $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_tax)) === $table_tax) {
    $edit_act = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table_tax} WHERE id = %d AND type = 'activity'", $edit_id), ARRAY_A);
    if ($edit_act) {
        $current_subtab = 'add';
    }
}

// Fetch activities with resolved parent names
$activities = [];
if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_tax)) === $table_tax) {
    $activities = $wpdb->get_results(
        "SELECT a.*, p.name AS parent_name 
         FROM {$table_tax} a 
         LEFT JOIN {$table_tax} p ON a.parent_id = p.id 
         WHERE a.type = 'activity' 
         ORDER BY a.parent_id ASC, a.name ASC",
        ARRAY_A
    ) ?: [];
}

wp_enqueue_media();
?>

<style>
.nttbs-act-shell {
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

.nttbs-thumb-avatar {
    width: 44px;
    height: 44px;
    border-radius: 10px;
    background: var(--nttbs-bg-body);
    border: 1px solid var(--nttbs-border);
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: var(--nttbs-shadow-xs);
}
.nttbs-thumb-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
}

.nttbs-badge-parent {
    display: inline-flex;
    align-items: center;
    background: var(--nttbs-emerald-subtle);
    color: var(--nttbs-emerald);
    border: 1px solid var(--nttbs-emerald-border);
    font-size: 11px;
    padding: 2px 8px;
    border-radius: 6px;
    font-weight: 700;
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

<div class="nttbs-act-shell">

    <!-- Subnav Tabs -->
    <div class="nttbs-subnav-bar">
        <div class="nttbs-subnav-group">
            <a href="<?php echo esc_url(admin_url('admin.php?page=nttbs-core&view=activity&subtab=all')); ?>" 
               class="nttbs-pill-toggle <?php echo $current_subtab === 'all' ? 'active' : 'inactive'; ?>">
                <span class="nttbs-nav-icon"><?php echo nttbs_svg_icon('activity', 16, 16); ?></span>
                <span><?php esc_html_e('All Activities', 'nahian-tour-travel-booking-solution'); ?></span>
                <span class="nttbs-pill-badge"><?php echo count($activities); ?></span>
            </a>
            <a href="<?php echo esc_url(admin_url('admin.php?page=nttbs-core&view=activity&subtab=add')); ?>" 
               class="nttbs-pill-toggle <?php echo $current_subtab === 'add' ? 'active' : 'inactive'; ?>">
                <span>+</span>
                <span><?php echo $edit_act ? sprintf(esc_html__('Edit Activity #%d', 'nahian-tour-travel-booking-solution'), (int)$edit_act['id']) : esc_html__('Add Activity', 'nahian-tour-travel-booking-solution'); ?></span>
            </a>
        </div>

        <?php if (isset($_GET['status'])): ?>
            <?php if ($_GET['status'] === 'created'): ?>
                <div class="nttbs-alert nttbs-alert-success"><?php esc_html_e('✓ Activity successfully registered', 'nahian-tour-travel-booking-solution'); ?></div>
            <?php elseif ($_GET['status'] === 'updated'): ?>
                <div class="nttbs-alert nttbs-alert-success"><?php esc_html_e('✓ Activity specifications updated', 'nahian-tour-travel-booking-solution'); ?></div>
            <?php elseif ($_GET['status'] === 'deleted'): ?>
                <div class="nttbs-alert nttbs-alert-danger"><?php esc_html_e('✓ Activity removed from catalogue', 'nahian-tour-travel-booking-solution'); ?></div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <?php if ($current_subtab === 'all'): ?>
        <!-- TAB 1: ALL ACTIVITIES TABLE -->
        <div class="nttbs-white-panel">
            <div class="nttbs-panel-header">
                <div style="display:flex; align-items:center; gap:8px;">
                    <span class="nttbs-nav-icon nttbs-icon-activity"><?php echo nttbs_svg_icon('activity', 18, 18); ?></span>
                    <span><?php printf(esc_html__('Expedition Activity Catalog (%d)', 'nahian-tour-travel-booking-solution'), count($activities)); ?></span>
                </div>
                <a href="<?php echo esc_url(admin_url('admin.php?page=nttbs-core&view=activity&subtab=add')); ?>" class="nttbs-btn-primary" style="padding:7px 14px; font-size:12px; text-decoration:none;">
                    + <?php esc_html_e('Add Activity', 'nahian-tour-travel-booking-solution'); ?>
                </a>
            </div>
            <p style="font-size:12px; color:var(--nttbs-text-muted); margin-bottom:18px; margin-top:-8px;"><?php echo esc_html($desc); ?></p>

            <div class="nttbs-table-responsive">
                <table class="nttbs-data-table">
                    <thead>
                        <tr>
                            <th style="width:60px;"><?php esc_html_e('Media', 'nahian-tour-travel-booking-solution'); ?></th>
                            <th><?php esc_html_e('Activity Name', 'nahian-tour-travel-booking-solution'); ?></th>
                            <th><?php esc_html_e('Parent Discipline / Category', 'nahian-tour-travel-booking-solution'); ?></th>
                            <th><?php esc_html_e('Slug', 'nahian-tour-travel-booking-solution'); ?></th>
                            <th><?php esc_html_e('Briefing Overview', 'nahian-tour-travel-booking-solution'); ?></th>
                            <th style="text-align:right;"><?php esc_html_e('Actions', 'nahian-tour-travel-booking-solution'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($activities)): ?>
                            <tr><td colspan="6" style="text-align:center; padding:44px 20px; color:var(--nttbs-text-muted);"><?php esc_html_e('No expedition activities recorded in system.', 'nahian-tour-travel-booking-solution'); ?></td></tr>
                        <?php else: foreach ($activities as $r): ?>
                            <tr>
                                <td>
                                    <div class="nttbs-thumb-avatar">
                                        <?php if (!empty($r['image_url'])): ?>
                                            <img src="<?php echo esc_url($r['image_url']); ?>" alt="<?php echo esc_attr($r['name']); ?>">
                                        <?php else: ?>
                                            <span style="font-size:10px; font-weight:800; color:var(--nttbs-text-muted);"><?php esc_html_e('N/A', 'nahian-tour-travel-booking-solution'); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <strong style="font-size:13.5px; color:var(--nttbs-text-heading);"><?php echo esc_html($r['name']); ?></strong><br>
                                    <span style="display:inline-flex; align-items:center; gap:4px; font-size:10px; font-weight:800; color:#ffffff; background-color:<?php echo esc_attr($r['badge_color'] ?: '#10b981'); ?>; padding:2px 7px; border-radius:4px; margin-top:3px; font-family:'JetBrains Mono', monospace;">
                                        #<?php echo esc_html($r['id']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if (!empty($r['parent_name'])): ?>
                                        <span class="nttbs-badge-parent">
                                            ↳ <?php echo esc_html($r['parent_name']); ?>
                                        </span>
                                    <?php else: ?>
                                        <span style="color:var(--nttbs-text-muted); font-size:11.5px; font-weight:600;"><?php esc_html_e('— Main Discipline —', 'nahian-tour-travel-booking-solution'); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <code style="font-size:11.5px; background:var(--nttbs-bg-body); border:1px solid var(--nttbs-border); padding:3px 7px; border-radius:5px; color:var(--nttbs-text-muted); font-family:'JetBrains Mono', monospace;">
                                        <?php echo esc_html($r['slug']); ?>
                                    </code>
                                </td>
                                <td>
                                    <small style="color:var(--nttbs-text-muted); line-height:1.4; display:inline-block; max-width:280px; font-size:11.5px;">
                                        <?php echo esc_html(wp_trim_words($r['description'], 12)); ?>
                                    </small>
                                </td>
                                <td style="text-align:right; white-space:nowrap;">
                                    <a href="<?php echo esc_url(admin_url('admin.php?page=nttbs-core&view=activity&subtab=add&edit_id=' . $r['id'])); ?>" 
                                       style="color:var(--nttbs-primary); font-weight:800; text-decoration:none; margin-right:12px;"><?php esc_html_e('Edit', 'nahian-tour-travel-booking-solution'); ?></a>
                                    <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=nttbs-core&view=activity&action=delete_act&act_id=' . $r['id']), 'nttbs_delete_act_' . $r['id'])); ?>" 
                                       onclick="return confirm('<?php echo esc_js(__('Remove this activity? Child sub-activities will detach to root level.', 'nahian-tour-travel-booking-solution')); ?>');" 
                                       style="color:var(--nttbs-rose); font-weight:800; text-decoration:none;"><?php esc_html_e('Delete', 'nahian-tour-travel-booking-solution'); ?></a>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    <?php else: ?>
        <!-- TAB 2: ADD / EDIT ACTIVITY FORM -->
        <div class="nttbs-form-grid">
            <div class="nttbs-white-panel">
                <div class="nttbs-panel-header">
                    <span><?php echo $edit_act ? esc_html__('Edit Activity Specifications', 'nahian-tour-travel-booking-solution') : esc_html__('Deploy New Expedition Activity', 'nahian-tour-travel-booking-solution'); ?></span>
                </div>

                <form method="post" action="<?php echo esc_url(admin_url('admin.php?page=nttbs-core&view=activity&subtab=add')); ?>">
                    <?php wp_nonce_field('nttbs_activity_nonce'); ?>
                    <input type="hidden" name="activity_id" value="<?php echo esc_attr($edit_act['id'] ?? ''); ?>">

                    <!-- 1. Activity Name -->
                    <div class="nttbs-field-row">
                        <label class="nttbs-field-label"><?php esc_html_e('Activity Name *', 'nahian-tour-travel-booking-solution'); ?></label>
                        <input type="text" name="name" id="nttbs_act_name" required class="nttbs-control-input" 
                               value="<?php echo esc_attr($edit_act['name'] ?? ''); ?>" 
                               placeholder="<?php esc_attr_e('e.g. Scuba Diving & Coral Exploration', 'nahian-tour-travel-booking-solution'); ?>" onkeyup="nttbsAutoGenerateSlug(this.value)">
                    </div>

                    <!-- 2. Slug -->
                    <div class="nttbs-field-row">
                        <label class="nttbs-field-label"><?php esc_html_e('URL / Taxonomy Slug', 'nahian-tour-travel-booking-solution'); ?></label>
                        <input type="text" name="slug" id="nttbs_act_slug" class="nttbs-control-input" 
                               value="<?php echo esc_attr($edit_act['slug'] ?? ''); ?>" 
                               placeholder="scuba-diving-coral-exploration">
                        <small style="color:var(--nttbs-text-muted); font-size:11.5px;"><?php esc_html_e('Unique identifier used across tour packages and filters.', 'nahian-tour-travel-booking-solution'); ?></small>
                    </div>

                    <!-- 3. Parent Activity Selection -->
                    <div class="nttbs-field-row">
                        <label class="nttbs-field-label"><?php esc_html_e('Parent Discipline', 'nahian-tour-travel-booking-solution'); ?></label>
                        <select name="parent_id" class="nttbs-control-input">
                            <option value="0"><?php esc_html_e('None (Top Level Activity)', 'nahian-tour-travel-booking-solution'); ?></option>
                            <?php foreach ($activities as $a): ?>
                                <?php if ($edit_act && (int)$a['id'] === (int)$edit_act['id']) continue; ?>
                                <option value="<?php echo esc_attr($a['id']); ?>" <?php selected($edit_act['parent_id'] ?? 0, $a['id']); ?>>
                                    <?php echo esc_html($a['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small style="color:var(--nttbs-text-muted); font-size:11.5px;"><?php esc_html_e('Group sub-disciplines (e.g. Deep Diving) under a primary category (e.g. Water Sports).', 'nahian-tour-travel-booking-solution'); ?></small>
                    </div>

                    <!-- 4. Activity Thumbnail / Image -->
                    <div class="nttbs-field-row">
                        <label class="nttbs-field-label"><?php esc_html_e('Featured Activity Visual', 'nahian-tour-travel-booking-solution'); ?></label>
                        <div style="display:flex; gap:10px; margin-bottom:12px;">
                            <input type="text" name="image_url" id="nttbs_act_image_url" class="nttbs-control-input" 
                                   value="<?php echo esc_attr($edit_act['image_url'] ?? ''); ?>" 
                                   placeholder="<?php esc_attr_e('https://... or select from WordPress Library', 'nahian-tour-travel-booking-solution'); ?>">
                            <button type="button" class="nttbs-btn-primary" onclick="nttbsOpenMediaUploader()" style="padding:0 18px; font-size:12px; white-space:nowrap;">
                                <?php esc_html_e('Media Library', 'nahian-tour-travel-booking-solution'); ?>
                            </button>
                        </div>
                        <div id="nttbs_image_preview_box" style="width:100%; height:150px; background:var(--nttbs-bg-body); border:1px dashed var(--nttbs-border); border-radius:10px; display:flex; align-items:center; justify-content:center; overflow:hidden;">
                            <?php if (!empty($edit_act['image_url'])): ?>
                                <img src="<?php echo esc_url($edit_act['image_url']); ?>" alt="<?php echo esc_attr($edit_act['name'] ?? ''); ?>" style="width:100%; height:100%; object-fit:cover;">
                            <?php else: ?>
                                <span style="font-size:12px; color:var(--nttbs-text-muted); font-weight:600;"><?php esc_html_e('No visual asset attached', 'nahian-tour-travel-booking-solution'); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- 5. Accent Color Code -->
                    <div class="nttbs-field-row">
                        <label class="nttbs-field-label"><?php esc_html_e('Indicator Accent Color', 'nahian-tour-travel-booking-solution'); ?></label>
                        <input type="color" name="badge_color" value="<?php echo esc_attr($edit_act['badge_color'] ?? '#10b981'); ?>" class="nttbs-control-input" style="height:40px; padding:2px; cursor:pointer;">
                    </div>

                    <!-- 6. Description -->
                    <div class="nttbs-field-row">
                        <label class="nttbs-field-label"><?php esc_html_e('Operational Briefing & Gear Specifications', 'nahian-tour-travel-booking-solution'); ?></label>
                        <textarea name="description" rows="4" class="nttbs-control-input" placeholder="<?php esc_attr_e('Activity gear specifications, guide requirements, depth / duration details...', 'nahian-tour-travel-booking-solution'); ?>"><?php echo esc_textarea($edit_act['description'] ?? ''); ?></textarea>
                    </div>

                    <button type="submit" name="nttbs_dispatch_activity_save" class="nttbs-btn-primary" style="width:100%; padding:14px; font-size:13.5px; margin-top:8px;">
                        <?php echo $edit_act ? esc_html__('Update Activity', 'nahian-tour-travel-booking-solution') : esc_html__('Save & Publish Activity', 'nahian-tour-travel-booking-solution'); ?>
                    </button>
                </form>
            </div>

            <!-- Right Informational Sidebar -->
            <div>
                <div class="nttbs-white-panel" style="border-left:4px solid var(--nttbs-emerald);">
                    <div class="nttbs-panel-header">
                        <span><?php esc_html_e('Activity Taxonomy Specs', 'nahian-tour-travel-booking-solution'); ?></span>
                    </div>
                    <p style="font-size:12.5px; color:var(--nttbs-text-muted); line-height:1.6; margin-bottom:16px; margin-top:-8px;">
                        <?php esc_html_e('Activities define the experiential and operational requirements tagged to tour itineraries and fleet allocations.', 'nahian-tour-travel-booking-solution'); ?>
                    </p>
                    <ul style="font-size:12.5px; color:var(--nttbs-text-muted); padding-left:16px; line-height:1.7; margin:0;">
                        <li><strong><?php esc_html_e('Discipline Nesting:', 'nahian-tour-travel-booking-solution'); ?></strong> <?php esc_html_e('Group sub-experiences (e.g. Scuba Snorkeling → Night Diving) under parent umbrellas.', 'nahian-tour-travel-booking-solution'); ?></li>
                        <li><strong><?php esc_html_e('Equipment Coordination:', 'nahian-tour-travel-booking-solution'); ?></strong> <?php esc_html_e('Link briefing notes to clarify required equipment and certifications on manifests.', 'nahian-tour-travel-booking-solution'); ?></li>
                        <li><strong><?php esc_html_e('Visual Badging:', 'nahian-tour-travel-booking-solution'); ?></strong> <?php esc_html_e('Colors and banners render consistently across operational timetables.', 'nahian-tour-travel-booking-solution'); ?></li>
                    </ul>
                </div>
            </div>
        </div>
    <?php endif; ?>

</div>

<script>
function nttbsAutoGenerateSlug(val) {
    <?php if (!$edit_act): ?>
    const slugInput = document.getElementById('nttbs_act_slug');
    if (slugInput && !slugInput.dataset.manual) {
        slugInput.value = val.toLowerCase()
            .replace(/[^\w ]+/g, '')
            .replace(/ +/g, '-');
    }
    <?php endif; ?>
}

function nttbsOpenMediaUploader() {
    const frame = wp.media({
        title: <?php echo json_encode(__('Select Activity Featured Image', 'nahian-tour-travel-booking-solution')); ?>,
        button: { text: <?php echo json_encode(__('Use Selected Image', 'nahian-tour-travel-booking-solution')); ?> },
        multiple: false
    });

    frame.on('select', function() {
        const attachment = frame.state().get('selection').first().toJSON();
        const urlInput   = document.getElementById('nttbs_act_image_url');
        const previewBox = document.getElementById('nttbs_image_preview_box');
        const imgUrl     = attachment.sizes && attachment.sizes.medium ? attachment.sizes.medium.url : attachment.url;
        
        if (urlInput) {
            urlInput.value = imgUrl;
        }
        if (previewBox) {
            previewBox.innerHTML = '<img src="' + encodeURI(imgUrl) + '" style="width:100%; height:100%; object-fit:cover;">';
        }
    });

    frame.open();
}
</script>
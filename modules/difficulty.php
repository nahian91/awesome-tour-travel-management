<?php
if (!defined('ABSPATH')) {
    exit;
}

$type  = 'difficulty';
$title = __('Difficulty Levels', 'nahian-tour-travel-booking-solution');
$desc  = __('Manage endurance ratings, trail levels, featured visuals, and terrain challenge classifications.', 'nahian-tour-travel-booking-solution');

/* ==========================================================================
   1. DATABASE SCHEMA AUTO-UPGRADE CHECK (CACHED VERSION MIGRATION)
   ========================================================================== */

function nttbs_ensure_difficulty_schema_columns() {
    global $wpdb;
    $table = $wpdb->prefix . 'nttbs_taxonomies';

    $schema_version = get_option('nttbs_difficulty_schema_version', '1.0.0');
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

    update_option('nttbs_difficulty_schema_version', '1.1.0');
}
nttbs_ensure_difficulty_schema_columns();

/* ==========================================================================
   2. CONTROLLER DISPATCHERS (CREATE / UPDATE / DELETE)
   ========================================================================== */

function nttbs_handle_difficulty_dispatchers() {
    global $wpdb;

    $table_tax =$wpdb->prefix . 'nttbs_taxonomies';

    // 2.1 Save or Update Difficulty
    if (isset($_POST['nttbs_dispatch_difficulty_save']) && check_admin_referer('nttbs_difficulty_nonce')) {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Access denied: Insufficient operator clearance.', 'nahian-tour-travel-booking-solution'), 403);
        }

        $diff_id   = !empty($_POST['difficulty_id']) ? absint($_POST['difficulty_id']) : null;
        $name      = sanitize_text_field(wp_unslash($_POST['name'] ?? ''));
        $raw_slug  = sanitize_text_field(wp_unslash($_POST['slug'] ?? ''));
        $slug      = sanitize_title(!empty($raw_slug) ? $raw_slug :$name);
        $parent_id = absint($_POST['parent_id'] ?? 0);
        $image_url = esc_url_raw(wp_unslash($_POST['image_url'] ?? ''));
        $desc      = sanitize_textarea_field(wp_unslash($_POST['description'] ?? ''));
        $color     = sanitize_hex_color($_POST['badge_color'] ?? '') ?: '#ea580c';

        if (empty($name)) {
            wp_die(esc_html__('Validation error: Difficulty level designation is required.', 'nahian-tour-travel-booking-solution'));
        }

        // Prevent circular parent dependency
        if ($diff_id &&$diff_id === $parent_id) {$parent_id = 0;
        }

        $data = [
            'type'        => 'difficulty',
            'parent_id'   => $parent_id,
            'name'        => $name,
            'slug'        => $slug,
            'description' => $desc,
            'badge_color' => $color,
            'image_url'   => $image_url
        ];

        $wpdb->query('START TRANSACTION');

        if ($diff_id) {$wpdb->update($table_tax,$data, ['id' => $diff_id]);$msg     = 'updated';
            $log_act = 'UPDATE_DIFFICULTY';$log_msg = "Updated difficulty level #{$diff_id} ({$name})";
        } else {
            $wpdb->insert($table_tax,$data);
            $diff_id = (int)$wpdb->insert_id;
            $msg     = 'created';
            $log_act = 'CREATE_DIFFICULTY';$log_msg = "Created difficulty level #{$diff_id} ({$name})";
        }

        $wpdb->query('COMMIT');

        if (function_exists('nttbs_log_activity')) {
            nttbs_log_activity($log_act,$log_msg);
        }

        wp_safe_redirect(admin_url('admin.php?page=nttbs-core&view=difficulty&subtab=all&status=' . $msg));
        exit;
    }

    // 2.2 Delete Difficulty
    if (isset($_GET['action']) && $_GET['action'] === 'delete_diff' && isset($_GET['diff_id'])) {
        $diff_id = absint($_GET['diff_id']);
        check_admin_referer('nttbs_delete_diff_' . $diff_id);

        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Access denied: Unauthorized operation.', 'nahian-tour-travel-booking-solution'), 403);
        }

        $wpdb->query('START TRANSACTION');
        // Unlink nested child categories to root level
        $wpdb->update($table_tax, ['parent_id' => 0], ['parent_id' =>$diff_id], ['%d'], ['%d']);
        $wpdb->delete($table_tax, ['id' => $diff_id], ['%d']);$wpdb->query('COMMIT');

        if (function_exists('nttbs_log_activity')) {
            nttbs_log_activity('DELETE_DIFFICULTY', "Deleted difficulty level #{$diff_id}");
        }

        wp_safe_redirect(admin_url('admin.php?page=nttbs-core&view=difficulty&subtab=all&status=deleted'));
        exit;
    }
}
nttbs_handle_difficulty_dispatchers();

/* ==========================================================================
   3. DATA FETCHING & SUBTAB ROUTING
   ========================================================================== */

global $wpdb;
$current_subtab = isset($_GET['subtab']) && in_array($_GET['subtab'], ['add', 'all'], true) ? sanitize_key($_GET['subtab']) : 'all';$edit_id        = isset($_GET['edit_id']) ? absint($_GET['edit_id']) : 0;
$edit_diff      = null;

$table_tax =$wpdb->prefix . 'nttbs_taxonomies';

if ($edit_id && $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_tax)) ===$table_tax) {
    $edit_diff =$wpdb->get_row($wpdb->prepare("SELECT * FROM {$table_tax} WHERE id = %d AND type = 'difficulty'", $edit_id), ARRAY_A);
    if ($edit_diff) {$current_subtab = 'add';
    }
}

// Query all difficulties with hierarchical parent joins
$difficulties = [];
if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_tax)) ===$table_tax) {
    $difficulties =$wpdb->get_results(
        "SELECT d.*, p.name AS parent_name 
         FROM {$table_tax} d 
         LEFT JOIN {$table_tax} p ON d.parent_id = p.id 
         WHERE d.type = 'difficulty' 
         ORDER BY d.parent_id ASC, d.name ASC",
        ARRAY_A
    ) ?: [];
}

wp_enqueue_media();
?>

<style>
.nte-diff-shell {
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

.nte-thumb-avatar {
    width: 44px;
    height: 44px;
    border-radius: 8px;
    background: var(--bg-subtle);
    border: 1px solid var(--border-light);
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: center;
}
.nte-thumb-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
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
</style>

<div class="nte-diff-shell">

    <!-- Subnav Tabs -->
    <div class="nte-subnav-bar">
        <div style="display:flex; gap:10px;">
            <a href="<?php echo esc_url(admin_url('admin.php?page=nttbs-core&view=difficulty&subtab=all')); ?>" 
               class="nte-pill-toggle <?php echo $current_subtab === 'all' ? 'active' : 'inactive'; ?>">
                <span class="nte-nav-icon"><?php echo nttbs_svg_icon('diff', 16, 16); ?></span>
                <?php esc_html_e('All Difficulties', 'nahian-tour-travel-booking-solution'); ?>
                <span style="background:rgba(255,255,255,0.25); font-size:11px; padding:2px 7px; border-radius:999px; margin-left:4px;">
                    <?php echo count($difficulties); ?>
                </span>
            </a>
            <a href="<?php echo esc_url(admin_url('admin.php?page=nttbs-core&view=difficulty&subtab=add')); ?>" 
               class="nte-pill-toggle <?php echo $current_subtab === 'add' ? 'active' : 'inactive'; ?>">
                <span>+</span> <?php echo $edit_diff ? sprintf(esc_html__('Edit Difficulty #%d', 'nahian-tour-travel-booking-solution'), (int)$edit_diff['id']) : esc_html__('Add Difficulty', 'nahian-tour-travel-booking-solution'); ?>
            </a>
        </div>

        <?php if (isset($_GET['status'])): ?>
            <?php if ($_GET['status'] === 'created'): ?>
                <div class="nte-alert nte-alert-success"><?php esc_html_e('✓ Difficulty successfully registered', 'nahian-tour-travel-booking-solution'); ?></div>
            <?php elseif ($_GET['status'] === 'updated'): ?>
                <div class="nte-alert nte-alert-success"><?php esc_html_e('✓ Difficulty specifications updated', 'nahian-tour-travel-booking-solution'); ?></div>
            <?php elseif ($_GET['status'] === 'deleted'): ?>
                <div class="nte-alert nte-alert-danger"><?php esc_html_e('✓ Difficulty removed from catalogue', 'nahian-tour-travel-booking-solution'); ?></div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <?php if ($current_subtab === 'all'): ?>
        <!-- TAB 1: ALL DIFFICULTIES CATALOGUE TABLE -->
        <div class="nte-white-panel">
            <div class="nte-panel-header">
                <div style="display:flex; align-items:center; gap:8px;">
                    <span class="nte-nav-icon icon-diff"><?php echo nttbs_svg_icon('diff', 18, 18); ?></span>
                    <span><?php printf(esc_html__('Endurance & Terrain Rating Catalog (%d)', 'nahian-tour-travel-booking-solution'), count($difficulties)); ?></span>
                </div>
                <a href="<?php echo esc_url(admin_url('admin.php?page=nttbs-core&view=difficulty&subtab=add')); ?>" class="nte-btn-primary" style="padding:6px 14px; font-size:12px; text-decoration:none;">
                    + <?php esc_html_e('Add Difficulty', 'nahian-tour-travel-booking-solution'); ?>
                </a>
            </div>
            <p style="font-size:12px; color:var(--text-muted); margin-bottom:16px; margin-top:-6px;"><?php echo esc_html($desc); ?></p>

            <div class="nte-table-responsive">
                <table class="nte-data-table">
                    <thead>
                        <tr>
                            <th style="width:60px;"><?php esc_html_e('Media', 'nahian-tour-travel-booking-solution'); ?></th>
                            <th><?php esc_html_e('Rating Designation', 'nahian-tour-travel-booking-solution'); ?></th>
                            <th><?php esc_html_e('Parent Tier / Baseline', 'nahian-tour-travel-booking-solution'); ?></th>
                            <th><?php esc_html_e('Slug', 'nahian-tour-travel-booking-solution'); ?></th>
                            <th><?php esc_html_e('Health & Fitness Briefing', 'nahian-tour-travel-booking-solution'); ?></th>
                            <th style="text-align:right;"><?php esc_html_e('Actions', 'nahian-tour-travel-booking-solution'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($difficulties)): ?>
                            <tr><td colspan="6" style="text-align:center; padding:36px; color:var(--text-muted);"><?php esc_html_e('No difficulty tiers recorded in system.', 'nahian-tour-travel-booking-solution'); ?></td></tr>
                        <?php else: foreach ($difficulties as$r): ?>
                            <tr>
                                <td>
                                    <div class="nte-thumb-avatar">
                                        <?php if (!empty($r['image_url'])): ?>
                                            <img src="<?php echo esc_url($r['image_url']); ?>" alt="<?php echo esc_attr($r['name']); ?>">
                                        <?php else: ?>
                                            <span style="font-size:10px; font-weight:800; color:var(--text-muted);"><?php esc_html_e('N/A', 'nahian-tour-travel-booking-solution'); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <strong style="font-size:13px; color:var(--text-main);"><?php echo esc_html($r['name']); ?></strong><br>
                                    <span style="display:inline-flex; align-items:center; gap:4px; font-size:10px; font-weight:800; color:#ffffff; background-color:<?php echo esc_attr($r['badge_color'] ?: '#ea580c'); ?>; padding:1px 6px; border-radius:4px; margin-top:2px;">
                                        #<?php echo esc_html($r['id']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if (!empty($r['parent_name'])): ?>
                                        <span class="nte-badge-parent">
                                            ↳ <?php echo esc_html($r['parent_name']); ?>
                                        </span>
                                    <?php else: ?>
                                        <span style="color:var(--text-muted); font-size:11px; font-weight:600;"><?php esc_html_e('— Root Fitness Tier —', 'nahian-tour-travel-booking-solution'); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <code style="font-size:11px; background:var(--bg-subtle); border:1px solid var(--border-light); padding:2px 6px; border-radius:4px; color:var(--text-muted); font-family:ui-monospace, monospace;">
                                        <?php echo esc_html($r['slug']); ?>
                                    </code>
                                </td>
                                <td>
                                    <small style="color:var(--text-muted); line-height:1.4; display:inline-block; max-width:280px;">
                                        <?php echo esc_html(wp_trim_words($r['description'], 12)); ?>
                                    </small>
                                </td>
                                <td style="text-align:right; white-space:nowrap;">
                                    <a href="<?php echo esc_url(admin_url('admin.php?page=nttbs-core&view=difficulty&subtab=add&edit_id=' . $r['id'])); ?>" 
                                       style="color:var(--c-blue); font-weight:800; text-decoration:none; margin-right:12px;"><?php esc_html_e('Edit', 'nahian-tour-travel-booking-solution'); ?></a>
                                    <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=nttbs-core&view=difficulty&action=delete_diff&diff_id=' . $r['id']), 'nttbs_delete_diff_' .$r['id'])); ?>" 
                                       onclick="return confirm('<?php echo esc_js(__('Remove this difficulty tier? Child sub-ratings will detach to root level.', 'nahian-tour-travel-booking-solution')); ?>');" 
                                       style="color:var(--c-rose); font-weight:800; text-decoration:none;"><?php esc_html_e('Delete', 'nahian-tour-travel-booking-solution'); ?></a>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    <?php else: ?>
        <!-- TAB 2: ADD / EDIT DIFFICULTY FORM -->
        <div style="display:grid; grid-template-columns: minmax(0, 2fr) minmax(0, 1fr); gap:24px; align-items:start;">
            <div class="nte-white-panel">
                <div class="nte-panel-header">
                    <span><?php echo $edit_diff ? esc_html__('Edit Difficulty Rating Specifications', 'nahian-tour-travel-booking-solution') : esc_html__('Deploy New Terrain Challenge Tier', 'nahian-tour-travel-booking-solution'); ?></span>
                </div>

                <form method="post" action="<?php echo esc_url(admin_url('admin.php?page=nttbs-core&view=difficulty&subtab=add')); ?>">
                    <?php wp_nonce_field('nttbs_difficulty_nonce'); ?>
                    <input type="hidden" name="difficulty_id" value="<?php echo esc_attr($edit_diff['id'] ?? ''); ?>">

                    <!-- 1. Difficulty Name -->
                    <div class="nte-field-row">
                        <label class="nte-field-label"><?php esc_html_e('Difficulty Name *', 'nahian-tour-travel-booking-solution'); ?></label>
                        <input type="text" name="name" id="nttbs_diff_name" required class="nte-control-input" 
                               value="<?php echo esc_attr($edit_diff['name'] ?? ''); ?>" 
                               placeholder="<?php esc_attr_e('e.g. Demanding High-Altitude Trek', 'nahian-tour-travel-booking-solution'); ?>" onkeyup="nttbsAutoGenerateSlug(this.value)">
                    </div>

                    <!-- 2. Slug -->
                    <div class="nte-field-row">
                        <label class="nte-field-label"><?php esc_html_e('URL / Taxonomy Slug', 'nahian-tour-travel-booking-solution'); ?></label>
                        <input type="text" name="slug" id="nttbs_diff_slug" class="nte-control-input" 
                               value="<?php echo esc_attr($edit_diff['slug'] ?? ''); ?>" 
                               placeholder="demanding-high-altitude-trek">
                        <small style="color:var(--text-muted); font-size:11px;"><?php esc_html_e('Unique identifier used across tour filters, itinerary difficulty chips, and database lookups.', 'nahian-tour-travel-booking-solution'); ?></small>
                    </div>

                    <!-- 3. Parent Difficulty Selection -->
                    <div class="nte-field-row">
                        <label class="nte-field-label"><?php esc_html_e('Parent Fitness Tier', 'nahian-tour-travel-booking-solution'); ?></label>
                        <select name="parent_id" class="nte-control-input">
                            <option value="0"><?php esc_html_e('None (Root Difficulty Tier)', 'nahian-tour-travel-booking-solution'); ?></option>
                            <?php foreach ($difficulties as$d): ?>
                                <?php if ($edit_diff && (int)$d['id'] === (int)$edit_diff['id']) continue; ?>
                                <option value="<?php echo esc_attr($d['id']); ?>" <?php selected($edit_diff['parent_id'] ?? 0,$d['id']); ?>>
                                    <?php echo esc_html($d['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small style="color:var(--text-muted); font-size:11px;"><?php esc_html_e('Cluster specialized conditions (e.g. Vertical Scramble) under a broader level (e.g. Extreme Expedition).', 'nahian-tour-travel-booking-solution'); ?></small>
                    </div>

                    <!-- 4. Difficulty Thumbnail / Badge Image -->
                    <div class="nte-field-row">
                        <label class="nte-field-label"><?php esc_html_e('Featured Badge / Icon Image', 'nahian-tour-travel-booking-solution'); ?></label>
                        <div style="display:flex; gap:8px; margin-bottom:10px;">
                            <input type="text" name="image_url" id="nttbs_diff_image_url" class="nte-control-input" 
                                   value="<?php echo esc_attr($edit_diff['image_url'] ?? ''); ?>" 
                                   placeholder="<?php esc_attr_e('https://... or select from WordPress Library', 'nahian-tour-travel-booking-solution'); ?>">
                            <button type="button" class="nte-btn-primary" onclick="nttbsOpenMediaUploader()" style="padding:0 16px; font-size:12px; white-space:nowrap;">
                                <?php esc_html_e('Media Library', 'nahian-tour-travel-booking-solution'); ?>
                            </button>
                        </div>
                        <div id="nttbs_image_preview_box" style="width:100%; height:150px; background:var(--bg-subtle); border:1px dashed var(--border-light); border-radius:8px; display:flex; align-items:center; justify-content:center; overflow:hidden;">
                            <?php if (!empty($edit_diff['image_url'])): ?>
                                <img src="<?php echo esc_url($edit_diff['image_url']); ?>" alt="<?php echo esc_attr($edit_diff['name'] ?? ''); ?>" style="width:100%; height:100%; object-fit:cover;">
                            <?php else: ?>
                                <span style="font-size:12px; color:var(--text-muted); font-weight:600;"><?php esc_html_e('No visual asset attached', 'nahian-tour-travel-booking-solution'); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- 5. Accent Color Code -->
                    <div class="nte-field-row">
                        <label class="nte-field-label"><?php esc_html_e('Tag Badge Accent Color', 'nahian-tour-travel-booking-solution'); ?></label>
                        <input type="color" name="badge_color" value="<?php echo esc_attr($edit_diff['badge_color'] ?? '#ea580c'); ?>" class="nte-control-input" style="height:38px; padding:2px;">
                    </div>

                    <!-- 6. Description -->
                    <div class="nte-field-row">
                        <label class="nte-field-label"><?php esc_html_e('Health, Cardio & Fitness Prerequisites', 'nahian-tour-travel-booking-solution'); ?></label>
                        <textarea name="description" rows="4" class="nte-control-input" placeholder="<?php esc_attr_e('Cardio prerequisites, elevation changes, minimum age restrictions, rough sea tolerance...', 'nahian-tour-travel-booking-solution'); ?>"><?php echo esc_textarea($edit_diff['description'] ?? ''); ?></textarea>
                    </div>

                    <button type="submit" name="nttbs_dispatch_difficulty_save" class="nte-btn-primary" style="width:100%; padding:12px; font-size:13px; margin-top:8px;">
                        <?php echo $edit_diff ? esc_html__('Update Difficulty Level', 'nahian-tour-travel-booking-solution') : esc_html__('Save & Publish Difficulty', 'nahian-tour-travel-booking-solution'); ?>
                    </button>
                </form>
            </div>

            <!-- Right Informational Sidebar -->
            <div>
                <div class="nte-white-panel" style="border-left:4px solid var(--c-amber);">
                    <div class="nte-panel-header">
                        <span><?php esc_html_e('Endurance Taxonomy Specs', 'nahian-tour-travel-booking-solution'); ?></span>
                    </div>
                    <p style="font-size:12px; color:var(--text-muted); line-height:1.6; margin-bottom:14px; margin-top:-6px;">
                        <?php esc_html_e('Difficulty levels guide travelers on physical conditioning, technical skill, and equipment necessities prior to expedition dispatch.', 'nahian-tour-travel-booking-solution'); ?>
                    </p>
                    <ul style="font-size:12px; color:var(--text-muted); padding-left:16px; line-height:1.7; margin:0;">
                        <li><strong><?php esc_html_e('Tier Hierarchies:', 'nahian-tour-travel-booking-solution'); ?></strong> <?php esc_html_e('Nest specialized grades under main brackets (e.g. Scramble Grade 2 under Extreme Alpine).', 'nahian-tour-travel-booking-solution'); ?></li>
                        <li><strong><?php esc_html_e('Safety Badging:', 'nahian-tour-travel-booking-solution'); ?></strong> <?php esc_html_e('Accent badges dynamically signal physical requirements on boarding passes and vouchers.', 'nahian-tour-travel-booking-solution'); ?></li>
                        <li><strong><?php esc_html_e('Media Integration:', 'nahian-tour-travel-booking-solution'); ?></strong> <?php esc_html_e('Bind custom elevation profiles or badge graphics via the media uploader.', 'nahian-tour-travel-booking-solution'); ?></li>
                    </ul>
                </div>
            </div>
        </div>
    <?php endif; ?>

</div>

<script>
function nttbsAutoGenerateSlug(val) {
    <?php if (!$edit_diff): ?>
    const slugInput = document.getElementById('nttbs_diff_slug');
    if (slugInput && !slugInput.dataset.manual) {
        slugInput.value = val.toLowerCase()
            .replace(/[^\w ]+/g, '')
            .replace(/ +/g, '-');
    }
    <?php endif; ?>
}

function nttbsOpenMediaUploader() {
    const frame = wp.media({
        title: <?php echo json_encode(__('Select Difficulty Icon / Feature Image', 'nahian-tour-travel-booking-solution')); ?>,
        button: { text: <?php echo json_encode(__('Use Selected Image', 'nahian-tour-travel-booking-solution')); ?> },
        multiple: false
    });

    frame.on('select', function() {
        const attachment = frame.state().get('selection').first().toJSON();
        const urlInput   = document.getElementById('nttbs_diff_image_url');
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
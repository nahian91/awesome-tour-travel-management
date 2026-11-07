<?php
if (!defined('ABSPATH')) {
    exit;
}

$title = __('Expeditions & Tours', 'nahian-tour-travel-booking-solution');
$desc  = __('Enterprise Expedition Suite: multi-tier packages, dynamic pricing, itinerary builders, revenue upsells, and digital assets.', 'nahian-tour-travel-booking-solution');

/* ==========================================================================
   1. ENTERPRISE DATABASE SCHEMA MIGRATION
   ========================================================================== */

function nttbs_ensure_trip_enterprise_columns() {
    global $wpdb;
    $table = $wpdb->prefix . 'nttbs_trips';

    // Optimization: Check schema version option before running introspective queries
    $schema_version = get_option('nttbs_trips_schema_version', '1.0.0');
    if (version_compare($schema_version, '1.1.0', '>=')) {
        return;
    }

    if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table)) !== $table) {
        return;
    }

    $columns = [
        'trip_code'       => "varchar(100) NOT NULL DEFAULT '' AFTER `slug`",
        'min_age'         => "int(3) NOT NULL DEFAULT 0 AFTER `duration_nights`",
        'max_age'         => "int(3) NOT NULL DEFAULT 99 AFTER `min_age`",
        'min_pax'         => "int(5) NOT NULL DEFAULT 1 AFTER `max_age`",
        'cutoff_time'     => "varchar(100) NOT NULL DEFAULT '24 Hours Before' AFTER `min_pax`",
        'highlights'      => "longtext AFTER `description`",
        'trip_facts'      => "longtext AFTER `highlights`",
        'itinerary_title' => "varchar(255) NOT NULL DEFAULT 'Trip Itinerary' AFTER `trip_facts`",
        'itinerary_desc'  => "longtext AFTER `itinerary_title`",
        'packages_json'   => "longtext AFTER `deposit_percent`",
        'inc_exc_title'   => "varchar(255) NOT NULL DEFAULT 'Inclusions & Exclusions' AFTER `packages_json`",
        'gallery_images'  => "longtext AFTER `exclusions`",
        'video_gallery'   => "longtext AFTER `gallery_images`",
        'map_image'       => "varchar(255) DEFAULT '' AFTER `video_gallery`",
        'map_iframe'      => "text AFTER `map_image`",
        'faqs'            => "longtext AFTER `map_iframe`",
        'group_discounts' => "longtext AFTER `faqs`",
        'extra_services'  => "longtext AFTER `group_discounts`",
        'advanced_meta'   => "longtext AFTER `extra_services`"
    ];

    foreach ($columns as $col => $definition) {
        $check = $wpdb->get_results($wpdb->prepare("SHOW COLUMNS FROM `{$table}` LIKE %s", $col));
        if (empty($check)) {
            $wpdb->query("ALTER TABLE `{$table}` ADD `{$col}` {$definition}");
        }
    }

    update_option('nttbs_trips_schema_version', '1.1.0');
}
nttbs_ensure_trip_enterprise_columns();

/* ==========================================================================
   2. CRUD DISPATCHERS & PAYLOAD PROCESSING
   ========================================================================== */

function nttbs_handle_trip_dispatchers() {
    global $wpdb;

    $table_trips       = $wpdb->prefix . 'nttbs_trips';
    $table_departures  = $wpdb->prefix . 'nttbs_departures';

    // 2.1 Save / Update Expedition Record
    if (isset($_POST['nttbs_dispatch_trip_save']) && check_admin_referer('nttbs_trip_nonce')) {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Access denied: Insufficient operator permissions.', 'nahian-tour-travel-booking-solution'), 403);
        }

        $trip_id         = !empty($_POST['trip_id']) ? absint($_POST['trip_id']) : null;
        $title           = sanitize_text_field(wp_unslash($_POST['title'] ?? ''));
        $raw_slug        = sanitize_text_field(wp_unslash($_POST['slug'] ?? ''));
        $slug            = sanitize_title(!empty($raw_slug) ? $raw_slug : $title);
        $trip_code       = sanitize_text_field(wp_unslash($_POST['trip_code'] ?? ''));
        $trip_code       = !empty($trip_code) ? $trip_code : ('NT-' . strtoupper(wp_generate_password(5, false, false)));
        
        $destination_id  = absint($_POST['destination_id'] ?? 0);
        $activity_id     = absint($_POST['activity_id'] ?? 0);
        $trip_type_id    = absint($_POST['trip_type_id'] ?? 0);
        $difficulty_id   = absint($_POST['difficulty_id'] ?? 0);
        $duration_days   = max(1, absint($_POST['duration_days'] ?? 1));
        $duration_nights = absint($_POST['duration_nights'] ?? 0);
        $min_age         = absint($_POST['min_age'] ?? 0);
        $max_age         = min(120, max(0, absint($_POST['max_age'] ?? 99)));
        $min_pax         = max(1, absint($_POST['min_pax'] ?? 1));
        $max_seats       = max(1, absint($_POST['max_seats'] ?? 24));
        $cutoff_time     = sanitize_text_field(wp_unslash($_POST['cutoff_time'] ?? '24 Hours Before Departure'));
        $price_adult     = floatval($_POST['price_adult'] ?? 0);
        $price_child     = floatval($_POST['price_child'] ?? 0);
        $deposit_percent = min(100, max(1, absint($_POST['deposit_percent'] ?? 100)));
        $description     = wp_kses_post(wp_unslash($_POST['description'] ?? ''));
        $itinerary_title = sanitize_text_field(wp_unslash($_POST['itinerary_title_head'] ?? 'Trip Itinerary'));
        $itinerary_desc  = sanitize_textarea_field(wp_unslash($_POST['itinerary_desc_head'] ?? ''));
        $inc_exc_title   = sanitize_text_field(wp_unslash($_POST['inc_exc_title'] ?? 'Inclusions & Exclusions'));
        $inclusions      = sanitize_textarea_field(wp_unslash($_POST['inclusions'] ?? ''));
        $exclusions      = sanitize_textarea_field(wp_unslash($_POST['exclusions'] ?? ''));
        $gallery_images  = sanitize_text_field(wp_unslash($_POST['gallery_images'] ?? ''));
        $video_gallery   = sanitize_textarea_field(wp_unslash($_POST['video_gallery'] ?? ''));
        $map_image       = esc_url_raw(wp_unslash($_POST['map_image'] ?? ''));
        $map_iframe      = wp_kses(wp_unslash($_POST['map_iframe'] ?? ''), [
            'iframe' => [
                'src'             => [],
                'width'           => [],
                'height'          => [],
                'style'           => [],
                'loading'         => [],
                'referrerpolicy'  => [],
                'frameborder'     => [],
                'allowfullscreen' => []
            ]
        ]);

        // Highlights
        $highlights = [];
        if (!empty($_POST['highlights']) && is_array($_POST['highlights'])) {
            $raw_highlights = (array) wp_unslash($_POST['highlights']);
            foreach ($raw_highlights as $hl) {
                if (trim($hl)) {
                    $highlights[] = sanitize_text_field($hl);
                }
            }
        }

        // Facts Matrix
        $trip_facts = [];
        if (!empty($_POST['fact_key']) && is_array($_POST['fact_key'])) {
            $fact_keys = (array) wp_unslash($_POST['fact_key']);
            $fact_vals = isset($_POST['fact_val']) ? (array) wp_unslash($_POST['fact_val']) : [];
            foreach ($fact_keys as $k => $key_label) {
                if (trim($key_label)) {
                    $trip_facts[] = [
                        'title' => sanitize_text_field($key_label),
                        'value' => sanitize_text_field($fact_vals[$k] ?? '')
                    ];
                }
            }
        }

        // Itinerary Days
        $itinerary = [];
        if (!empty($_POST['itinerary_day_title']) && is_array($_POST['itinerary_day_title'])) {
            $it_titles = (array) wp_unslash($_POST['itinerary_day_title']);
            $it_descs  = isset($_POST['itinerary_day_desc']) ? (array) wp_unslash($_POST['itinerary_day_desc']) : [];
            $it_meals  = isset($_POST['itinerary_day_meals']) ? (array) wp_unslash($_POST['itinerary_day_meals']) : [];
            $it_lodges = isset($_POST['itinerary_day_lodge']) ? (array) wp_unslash($_POST['itinerary_day_lodge']) : [];

            foreach ($it_titles as $i => $it_title) {
                if (trim($it_title)) {
                    $itinerary[] = [
                        'day'   => $i + 1,
                        'title' => sanitize_text_field($it_title),
                        'desc'  => sanitize_textarea_field($it_descs[$i] ?? ''),
                        'meals' => sanitize_text_field($it_meals[$i] ?? ''),
                        'lodge' => sanitize_text_field($it_lodges[$i] ?? '')
                    ];
                }
            }
        }

        // Multiple Packages Engine
        $packages_json = [];
        if (!empty($_POST['pkg_name']) && is_array($_POST['pkg_name'])) {
            $pkg_names  = (array) wp_unslash($_POST['pkg_name']);
            $pkg_adults = isset($_POST['pkg_adult']) ? (array) wp_unslash($_POST['pkg_adult']) : [];
            $pkg_childs = isset($_POST['pkg_child']) ? (array) wp_unslash($_POST['pkg_child']) : [];
            $pkg_caps   = isset($_POST['pkg_cap']) ? (array) wp_unslash($_POST['pkg_cap']) : [];
            $pkg_descs  = isset($_POST['pkg_desc']) ? (array) wp_unslash($_POST['pkg_desc']) : [];

            foreach ($pkg_names as $p => $p_name) {
                if (trim($p_name)) {
                    $packages_json[] = [
                        'name'        => sanitize_text_field($p_name),
                        'price_adult' => floatval($pkg_adults[$p] ?? $price_adult),
                        'price_child' => floatval($pkg_childs[$p] ?? $price_child),
                        'capacity'    => absint($pkg_caps[$p] ?? $max_seats),
                        'desc'        => sanitize_text_field($pkg_descs[$p] ?? '')
                    ];
                }
            }
        }

        // FAQs
        $faqs = [];
        if (!empty($_POST['faq_q']) && is_array($_POST['faq_q'])) {
            $faq_qs = (array) wp_unslash($_POST['faq_q']);
            $faq_as = isset($_POST['faq_a']) ? (array) wp_unslash($_POST['faq_a']) : [];
            foreach ($faq_qs as $f => $q) {
                if (trim($q)) {
                    $faqs[] = [
                        'q' => sanitize_text_field($q),
                        'a' => sanitize_textarea_field($faq_as[$f] ?? '')
                    ];
                }
            }
        }

        // Group Discounts
        $group_discounts = [];
        if (!empty($_POST['gd_pax']) && is_array($_POST['gd_pax'])) {
            $gd_paxs      = (array) wp_unslash($_POST['gd_pax']);
            $gd_discounts = isset($_POST['gd_discount']) ? (array) wp_unslash($_POST['gd_discount']) : [];
            $gd_types     = isset($_POST['gd_type']) ? (array) wp_unslash($_POST['gd_type']) : [];

            foreach ($gd_paxs as $g => $gd_pax_count) {
                if (intval($gd_pax_count) > 0) {
                    $group_discounts[] = [
                        'min_pax'  => absint($gd_pax_count),
                        'discount' => floatval($gd_discounts[$g] ?? 0),
                        'type'     => sanitize_key($gd_types[$g] ?? 'percentage')
                    ];
                }
            }
        }

        // Extra Services / Upsells
        $extra_services = [];
        if (!empty($_POST['extra_title']) && is_array($_POST['extra_title'])) {
            $ext_titles = (array) wp_unslash($_POST['extra_title']);
            $ext_prices = isset($_POST['extra_price']) ? (array) wp_unslash($_POST['extra_price']) : [];
            $ext_types  = isset($_POST['extra_type']) ? (array) wp_unslash($_POST['extra_type']) : [];

            foreach ($ext_titles as $e => $ext_title) {
                if (trim($ext_title)) {
                    $extra_services[] = [
                        'title' => sanitize_text_field($ext_title),
                        'price' => floatval($ext_prices[$e] ?? 0),
                        'type'  => sanitize_key($ext_types[$e] ?? 'per_pax')
                    ];
                }
            }
        }

        // Advanced Configuration
        $advanced_meta = [
            'allow_partial' => isset($_POST['adv_allow_partial']) ? 1 : 0,
            'require_id'    => isset($_POST['adv_require_id']) ? 1 : 0,
            'badge_custom'  => sanitize_text_field(wp_unslash($_POST['adv_badge_custom'] ?? '')),
            'external_link' => esc_url_raw(wp_unslash($_POST['adv_external_link'] ?? ''))
        ];

        $payload = [
            'title'           => $title,
            'slug'            => $slug,
            'trip_code'       => $trip_code,
            'destination_id'  => $destination_id,
            'activity_id'     => $activity_id,
            'trip_type_id'    => $trip_type_id,
            'difficulty_id'   => $difficulty_id,
            'duration_days'   => $duration_days,
            'duration_nights' => $duration_nights,
            'min_age'         => $min_age,
            'max_age'         => $max_age,
            'min_pax'         => $min_pax,
            'max_seats'       => $max_seats,
            'cutoff_time'     => $cutoff_time,
            'price_adult'     => $price_adult,
            'price_child'     => $price_child,
            'deposit_percent' => $deposit_percent,
            'packages_json'   => wp_json_encode($packages_json),
            'description'     => $description,
            'highlights'      => wp_json_encode($highlights),
            'trip_facts'      => wp_json_encode($trip_facts),
            'itinerary_title' => $itinerary_title,
            'itinerary_desc'  => $itinerary_desc,
            'itinerary'       => wp_json_encode($itinerary),
            'inc_exc_title'   => $inc_exc_title,
            'inclusions'      => $inclusions,
            'exclusions'      => $exclusions,
            'gallery_images'  => $gallery_images,
            'video_gallery'   => $video_gallery,
            'map_image'       => $map_image,
            'map_iframe'      => $map_iframe,
            'faqs'            => wp_json_encode($faqs),
            'group_discounts' => wp_json_encode($group_discounts),
            'extra_services'  => wp_json_encode($extra_services),
            'advanced_meta'   => wp_json_encode($advanced_meta),
            'status'          => 'published'
        ];

        if ($trip_id) {
            $wpdb->update($table_trips, $payload, ['id' => $trip_id]);
            if (function_exists('nttbs_log_activity')) {
                nttbs_log_activity('UPDATE_PACKAGE', "Updated full expedition specifications #{$trip_id} ({$title})");
            }
            $msg = 'updated';
        } else {
            $wpdb->query('START TRANSACTION');

            $inserted = $wpdb->insert($table_trips, $payload);
            if (!$inserted) {
                $wpdb->query('ROLLBACK');
                wp_die(esc_html__('Failed to register tour expedition into catalog.', 'nahian-tour-travel-booking-solution'));
            }

            $new_trip_id = (int) $wpdb->insert_id;

            // Generate First Initial Departure Slot
            $dep_ok = $wpdb->insert($table_departures, [
                'trip_id'        => $new_trip_id,
                'slot_label'     => $title . ' - Primary Fleet Slot',
                'departure_date' => date('Y-m-d', strtotime('+7 days')),
                'departure_time' => '07:30 AM',
                'return_date'    => date('Y-m-d', strtotime('+' . ($duration_days + 7) . ' days')),
                'guide_name'     => 'Lead Expedition Captain',
                'transport_type' => 'Catamaran / AC Volvo Coach',
                'total_seats'    => $max_seats,
                'booked_seats'   => 0,
                'status'         => 'active'
            ]);

            $dep_id = (int) $wpdb->insert_id;

            if ($dep_ok && function_exists('nttbs_generate_seats')) {
                nttbs_generate_seats($dep_id, $max_seats);
            }

            $wpdb->query('COMMIT');

            if (function_exists('nttbs_log_activity')) {
                nttbs_log_activity('CREATE_PACKAGE', "Created expedition #{$new_trip_id} ({$title}) with departure roster #{$dep_id}");
            }
            $msg = 'created';
        }

        wp_safe_redirect(admin_url('admin.php?page=nttbs-core&view=trips&subtab=all&status=' . $msg));
        exit;
    }

    // 2.2 Delete Trip
    if (isset($_GET['action']) && $_GET['action'] === 'delete_trip' && isset($_GET['trip_id'])) {
        $trip_id = absint($_GET['trip_id']);
        check_admin_referer('nttbs_delete_trip_' . $trip_id);

        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Access denied.', 'nahian-tour-travel-booking-solution'), 403);
        }

        $wpdb->query('START TRANSACTION');
        $wpdb->delete($table_trips, ['id' => $trip_id], ['%d']);
        $wpdb->delete($table_departures, ['trip_id' => $trip_id], ['%d']);
        $wpdb->query('COMMIT');

        if (function_exists('nttbs_log_activity')) {
            nttbs_log_activity('DELETE_PACKAGE', "Deleted expedition package #{$trip_id}");
        }

        wp_safe_redirect(admin_url('admin.php?page=nttbs-core&view=trips&subtab=all&status=deleted'));
        exit;
    }
}
nttbs_handle_trip_dispatchers();

/* ==========================================================================
   3. DATA FETCHING & FORM DESERIALIZATION
   ========================================================================== */

global $wpdb;
$current_subtab = isset($_GET['subtab']) && in_array($_GET['subtab'], ['add', 'all'], true) ? sanitize_key($_GET['subtab']) : 'all';
$edit_id        = isset($_GET['edit_id']) ? absint($_GET['edit_id']) : 0;
$edit_trip      = null;

$table_trips = $wpdb->prefix . 'nttbs_trips';
if ($edit_id && $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_trips)) === $table_trips) {
    $edit_trip = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table_trips} WHERE id = %d", $edit_id), ARRAY_A);
    if ($edit_trip) {
        $current_subtab = 'add';
    }
}

$trips        = function_exists('nttbs_get_all_trips') ? nttbs_get_all_trips() : [];
$destinations = function_exists('nttbs_get_taxonomies') ? nttbs_get_taxonomies('destination') : [];
$activities   = function_exists('nttbs_get_taxonomies') ? nttbs_get_taxonomies('activity') : [];
$trip_types   = function_exists('nttbs_get_taxonomies') ? nttbs_get_taxonomies('trip_type') : [];
$difficulties = function_exists('nttbs_get_taxonomies') ? nttbs_get_taxonomies('difficulty') : [];
$currency     = get_option('nttbs_currency_symbol', NTTBS_CURRENCY_SYMBOL_DEFAULT);

// Deserialized Complex Arrays
$trip_highlights = !empty($edit_trip['highlights']) ? json_decode($edit_trip['highlights'], true) : [];
$trip_facts      = !empty($edit_trip['trip_facts']) ? json_decode($edit_trip['trip_facts'], true) : [];
$trip_itinerary  = !empty($edit_trip['itinerary']) ? json_decode($edit_trip['itinerary'], true) : [];
$trip_packages   = !empty($edit_trip['packages_json']) ? json_decode($edit_trip['packages_json'], true) : [];
$trip_faqs       = !empty($edit_trip['faqs']) ? json_decode($edit_trip['faqs'], true) : [];
$group_discounts = !empty($edit_trip['group_discounts']) ? json_decode($edit_trip['group_discounts'], true) : [];
$extra_services  = !empty($edit_trip['extra_services']) ? json_decode($edit_trip['extra_services'], true) : [];
$adv_meta        = !empty($edit_trip['advanced_meta']) ? json_decode($edit_trip['advanced_meta'], true) : [];
$gallery_array   = !empty($edit_trip['gallery_images']) ? array_filter(explode(',', $edit_trip['gallery_images'])) : [];

wp_enqueue_media();
?>

<style>
.nte-trip-shell {
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

.nte-badge {
    display: inline-block;
    padding: 3px 8px;
    border-radius: 6px;
    color: #ffffff;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.3px;
}

.nte-subnav-btn {
    width: 100%;
    text-align: left;
    background: transparent;
    border: none;
    padding: 11px 14px;
    border-radius: 6px;
    font-size: 13px;
    font-weight: 700;
    color: var(--text-muted);
    cursor: pointer;
    transition: all 0.15s ease;
    display: flex;
    align-items: center;
    gap: 8px;
}
.nte-subnav-btn:hover {
    background: var(--bg-subtle);
    color: var(--text-main);
}
.nte-subnav-btn.active {
    background: var(--c-blue-bg);
    color: var(--c-blue);
    border-left: 3px solid var(--c-blue);
    border-radius: 0 6px 6px 0;
}
.nte-card-bordered {
    background: var(--bg-subtle);
    border: 1px solid var(--border-light);
    border-radius: 8px;
    padding: 14px;
}
.nte-del-btn {
    background: var(--c-rose-bg);
    border: 1px solid var(--c-rose-border);
    color: var(--c-rose);
    border-radius: 6px;
    cursor: pointer;
    padding: 3px 8px;
    font-weight: 800;
    font-size: 13px;
    line-height: 1;
    transition: all 0.15s ease;
}
.nte-del-btn:hover {
    background: var(--c-rose);
    color: #ffffff;
}
</style>

<div class="nte-trip-shell">

    <!-- Subnav Tabs -->
    <div class="nte-subnav-bar">
        <div style="display:flex; gap:10px;">
            <a href="<?php echo esc_url(admin_url('admin.php?page=nttbs-core&view=trips&subtab=all')); ?>" 
               class="nte-pill-toggle <?php echo $current_subtab === 'all' ? 'active' : 'inactive'; ?>">
                <span class="nte-nav-icon"><?php echo nttbs_svg_icon('trips', 16, 16); ?></span>
                <?php esc_html_e('All Expeditions', 'nahian-tour-travel-booking-solution'); ?>
                <span style="background:rgba(255,255,255,0.25); font-size:11px; padding:2px 7px; border-radius:999px; margin-left:4px;">
                    <?php echo count($trips); ?>
                </span>
            </a>
            <a href="<?php echo esc_url(admin_url('admin.php?page=nttbs-core&view=trips&subtab=add')); ?>" 
               class="nte-pill-toggle <?php echo $current_subtab === 'add' ? 'active' : 'inactive'; ?>">
                <span>+</span> <?php echo $edit_trip ? sprintf(esc_html__('Edit Expedition #%d', 'nahian-tour-travel-booking-solution'), (int)$edit_trip['id']) : esc_html__('Deploy New Expedition', 'nahian-tour-travel-booking-solution'); ?>
            </a>
        </div>

        <?php if (isset($_GET['status'])): ?>
            <?php if ($_GET['status'] === 'created'): ?>
                <div class="nte-alert nte-alert-success"><?php esc_html_e('✓ Expedition Successfully Published', 'nahian-tour-travel-booking-solution'); ?></div>
            <?php elseif ($_GET['status'] === 'updated'): ?>
                <div class="nte-alert nte-alert-success"><?php esc_html_e('✓ Expedition Specifications Updated', 'nahian-tour-travel-booking-solution'); ?></div>
            <?php elseif ($_GET['status'] === 'deleted'): ?>
                <div class="nte-alert nte-alert-danger"><?php esc_html_e('✓ Expedition Dossier Removed', 'nahian-tour-travel-booking-solution'); ?></div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <?php if ($current_subtab === 'all'): ?>
        <!-- TAB 1: ALL EXPEDITIONS ROSTER -->
        <div class="nte-white-panel">
            <div class="nte-panel-header">
                <div style="display:flex; align-items:center; gap:8px;">
                    <span class="nte-nav-icon icon-trips"><?php echo nttbs_svg_icon('trips', 18, 18); ?></span>
                    <span><?php printf(esc_html__('Active Commercial Expedition Catalog (%d)', 'nahian-tour-travel-booking-solution'), count($trips)); ?></span>
                </div>
                <a href="<?php echo esc_url(admin_url('admin.php?page=nttbs-core&view=trips&subtab=add')); ?>" class="nte-btn-primary" style="padding:6px 14px; font-size:12px; text-decoration:none;">
                    + <?php esc_html_e('Add Expedition', 'nahian-tour-travel-booking-solution'); ?>
                </a>
            </div>
            <p style="font-size:12px; color:var(--text-muted); margin-bottom:16px; margin-top:-6px;"><?php echo esc_html($desc); ?></p>

            <div class="nte-table-responsive">
                <table class="nte-data-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Code', 'nahian-tour-travel-booking-solution'); ?></th>
                            <th><?php esc_html_e('Trip Title', 'nahian-tour-travel-booking-solution'); ?></th>
                            <th><?php esc_html_e('Taxonomies', 'nahian-tour-travel-booking-solution'); ?></th>
                            <th><?php esc_html_e('Duration', 'nahian-tour-travel-booking-solution'); ?></th>
                            <th><?php esc_html_e('Age Policy', 'nahian-tour-travel-booking-solution'); ?></th>
                            <th><?php esc_html_e('Base Tariff', 'nahian-tour-travel-booking-solution'); ?></th>
                            <th><?php esc_html_e('Max Capacity', 'nahian-tour-travel-booking-solution'); ?></th>
                            <th style="text-align:right;"><?php esc_html_e('Actions', 'nahian-tour-travel-booking-solution'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($trips)): ?>
                            <tr><td colspan="8" style="text-align:center; padding:36px; color:var(--text-muted);"><?php esc_html_e('No tour packages registered.', 'nahian-tour-travel-booking-solution'); ?></td></tr>
                        <?php else: foreach ($trips as $t): ?>
                            <tr>
                                <td>
                                    <code style="background:var(--c-blue-bg); color:var(--c-blue); border:1px solid var(--c-blue-border); padding:3px 6px; border-radius:4px; font-weight:800; font-family:ui-monospace, monospace;">
                                        <?php echo esc_html(!empty($t['trip_code']) ? $t['trip_code'] : ('#TRP-' . $t['id'])); ?>
                                    </code>
                                </td>
                                <td>
                                    <strong style="font-size:13px; color:var(--text-main);"><?php echo esc_html($t['title']); ?></strong>
                                </td>
                                <td>
                                    <span class="nte-badge" style="background-color:var(--c-blue);">
                                        <?php echo esc_html($t['destination_name'] ?? 'Region'); ?>
                                    </span>
                                    <span class="nte-badge" style="background-color:<?php echo esc_attr($t['difficulty_color'] ?? '#059669'); ?>;">
                                        <?php echo esc_html($t['difficulty_name'] ?? 'Easy'); ?>
                                    </span>
                                </td>
                                <td>
                                    <strong><?php echo esc_html($t['duration_days']); ?>D / <?php echo esc_html($t['duration_nights']); ?>N</strong>
                                </td>
                                <td>
                                    <small style="color:var(--text-muted); font-weight:600;"><?php echo esc_html($t['min_age'] ?? 0); ?> - <?php echo esc_html($t['max_age'] ?? 99); ?> <?php esc_html_e('Yrs', 'nahian-tour-travel-booking-solution'); ?></small>
                                </td>
                                <td>
                                    <strong style="color:var(--c-blue); font-size:13px; font-weight:800;">
                                        <?php echo esc_html($currency . ' ' . number_format_i18n((float)$t['price_adult'], 2)); ?>
                                    </strong>
                                </td>
                                <td>
                                    <span><?php echo esc_html($t['max_seats']); ?> <?php esc_html_e('Pax', 'nahian-tour-travel-booking-solution'); ?></span>
                                </td>
                                <td style="text-align:right; white-space:nowrap;">
                                    <a href="<?php echo esc_url(admin_url('admin.php?page=nttbs-core&view=trips&subtab=add&edit_id=' . $t['id'])); ?>" 
                                       style="color:var(--c-blue); font-weight:800; text-decoration:none; margin-right:12px;"><?php esc_html_e('Edit', 'nahian-tour-travel-booking-solution'); ?></a>
                                    <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=nttbs-core&view=trips&action=delete_trip&trip_id=' . $t['id']), 'nttbs_delete_trip_' . $t['id'])); ?>" 
                                       onclick="return confirm('<?php echo esc_js(__('Permanently delete this expedition and all corresponding departure schedules?', 'nahian-tour-travel-booking-solution')); ?>');" 
                                       style="color:var(--c-rose); font-weight:800; text-decoration:none;"><?php esc_html_e('Delete', 'nahian-tour-travel-booking-solution'); ?></a>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    <?php else: ?>
        <!-- TAB 2: 10-TAB ADVANCED TRIP BUILDER WORKSPACE -->
        <form method="post" action="<?php echo esc_url(admin_url('admin.php?page=nttbs-core&view=trips&subtab=add')); ?>" id="nttbs-enterprise-trip-builder">
            <?php wp_nonce_field('nttbs_trip_nonce'); ?>
            <input type="hidden" name="trip_id" value="<?php echo esc_attr($edit_trip['id'] ?? ''); ?>">

            <!-- Header Title Bar -->
            <div class="nte-white-panel" style="border-left:5px solid var(--c-blue); margin-bottom:20px;">
                <div class="nte-field-row" style="margin-bottom:0;">
                    <label class="nte-field-label" style="font-size:13px; text-transform:uppercase; letter-spacing:0.5px;"><?php esc_html_e('Expedition / Tour Title *', 'nahian-tour-travel-booking-solution'); ?></label>
                    <input type="text" name="title" id="nttbs_trip_title" value="<?php echo esc_attr($edit_trip['title'] ?? ''); ?>" required class="nte-control-input" style="font-size:16px; font-weight:800; padding:12px;" placeholder="<?php esc_attr_e('e.g. Red Sea Royal Catamaran Island Odyssey & Deep Reef Exploration', 'nahian-tour-travel-booking-solution'); ?>">
                </div>
            </div>

            <div style="display:grid; grid-template-columns: 260px 1fr; gap:22px; align-items:flex-start;">
                
                <!-- Left Vertical Tab Menu (10 Standard Tabs) -->
                <div class="nte-white-panel" style="padding:10px;">
                    <ul style="list-style:none; display:flex; flex-direction:column; gap:4px; padding:0; margin:0;" id="nttbs-trip-subnav">
                        <li><button type="button" class="nte-subnav-btn active" onclick="nttbsSwitchTab('general', this)">⚙️ <?php esc_html_e('General', 'nahian-tour-travel-booking-solution'); ?></button></li>
                        <li><button type="button" class="nte-subnav-btn" onclick="nttbsSwitchTab('overview', this)">📝 <?php esc_html_e('Overview & Info', 'nahian-tour-travel-booking-solution'); ?></button></li>
                        <li><button type="button" class="nte-subnav-btn" onclick="nttbsSwitchTab('itinerary', this)">📅 <?php esc_html_e('Itinerary', 'nahian-tour-travel-booking-solution'); ?></button></li>
                        <li><button type="button" class="nte-subnav-btn" onclick="nttbsSwitchTab('pricing', this)">💳 <?php esc_html_e('Date & Price', 'nahian-tour-travel-booking-solution'); ?></button></li>
                        <li><button type="button" class="nte-subnav-btn" onclick="nttbsSwitchTab('includes', this)">📋 <?php esc_html_e('Includes / Excludes', 'nahian-tour-travel-booking-solution'); ?></button></li>
                        <li><button type="button" class="nte-subnav-btn" onclick="nttbsSwitchTab('gallery', this)">🗺️ <?php esc_html_e('Map & Gallery', 'nahian-tour-travel-booking-solution'); ?></button></li>
                        <li><button type="button" class="nte-subnav-btn" onclick="nttbsSwitchTab('faqs', this)">❓ <?php esc_html_e('FAQs', 'nahian-tour-travel-booking-solution'); ?></button></li>
                        <li><button type="button" class="nte-subnav-btn" onclick="nttbsSwitchTab('revenue', this)">📈 <?php esc_html_e('Increase Revenue', 'nahian-tour-travel-booking-solution'); ?></button></li>
                        <li><button type="button" class="nte-subnav-btn" onclick="nttbsSwitchTab('extras', this)">🧰 <?php esc_html_e('Extra Services', 'nahian-tour-travel-booking-solution'); ?></button></li>
                        <li><button type="button" class="nte-subnav-btn" onclick="nttbsSwitchTab('advanced', this)">⚡ <?php esc_html_e('Advanced Settings', 'nahian-tour-travel-booking-solution'); ?></button></li>
                    </ul>

                    <div style="border-top:1px solid var(--border-light); margin-top:16px; padding-top:16px;">
                        <button type="submit" name="nttbs_dispatch_trip_save" class="nte-btn-primary" style="width:100%; padding:12px; font-size:13px; font-weight:800;">
                            <?php echo $edit_trip ? esc_html__('Update Expedition', 'nahian-tour-travel-booking-solution') : esc_html__('Publish Expedition', 'nahian-tour-travel-booking-solution'); ?>
                        </button>
                    </div>
                </div>

                <!-- Right Content Stages -->
                <div>
                    
                    <!-- 1. GENERAL TAB -->
                    <div class="nte-stage-panel active" id="stage-general">
                        <div class="nte-white-panel">
                            <div class="nte-panel-header"><span><?php esc_html_e('General Expedition Setup', 'nahian-tour-travel-booking-solution'); ?></span></div>
                            <div style="display:grid; grid-template-columns: repeat(2, 1fr); gap:16px;">
                                <div class="nte-field-row">
                                    <label class="nte-field-label"><?php esc_html_e('Trip Code', 'nahian-tour-travel-booking-solution'); ?></label>
                                    <input type="text" name="trip_code" value="<?php echo esc_attr($edit_trip['trip_code'] ?? ('NT-' . strtoupper(wp_generate_password(5, false, false)))); ?>" class="nte-control-input">
                                    <small style="color:var(--text-muted); font-size:11px;"><?php esc_html_e('Unique identifier displayed across invoices & manifests.', 'nahian-tour-travel-booking-solution'); ?></small>
                                </div>
                                <div class="nte-field-row">
                                    <label class="nte-field-label"><?php esc_html_e('URL Slug', 'nahian-tour-travel-booking-solution'); ?></label>
                                    <input type="text" name="slug" value="<?php echo esc_attr($edit_trip['slug'] ?? ''); ?>" class="nte-control-input" placeholder="auto-generated-if-blank">
                                </div>
                            </div>

                            <div style="display:grid; grid-template-columns: repeat(2, 1fr); gap:16px;">
                                <div class="nte-field-row">
                                    <label class="nte-field-label"><?php esc_html_e('Duration (Days)', 'nahian-tour-travel-booking-solution'); ?></label>
                                    <input type="number" min="1" name="duration_days" value="<?php echo esc_attr($edit_trip['duration_days'] ?? 1); ?>" class="nte-control-input">
                                </div>
                                <div class="nte-field-row">
                                    <label class="nte-field-label"><?php esc_html_e('Duration (Nights)', 'nahian-tour-travel-booking-solution'); ?></label>
                                    <input type="number" min="0" name="duration_nights" value="<?php echo esc_attr($edit_trip['duration_nights'] ?? 0); ?>" class="nte-control-input">
                                </div>
                            </div>

                            <div style="display:grid; grid-template-columns: repeat(4, 1fr); gap:12px;">
                                <div class="nte-field-row">
                                    <label class="nte-field-label"><?php esc_html_e('Min. Age', 'nahian-tour-travel-booking-solution'); ?></label>
                                    <input type="number" min="0" name="min_age" value="<?php echo esc_attr($edit_trip['min_age'] ?? 0); ?>" class="nte-control-input">
                                </div>
                                <div class="nte-field-row">
                                    <label class="nte-field-label"><?php esc_html_e('Max. Age', 'nahian-tour-travel-booking-solution'); ?></label>
                                    <input type="number" max="120" name="max_age" value="<?php echo esc_attr($edit_trip['max_age'] ?? 99); ?>" class="nte-control-input">
                                </div>
                                <div class="nte-field-row">
                                    <label class="nte-field-label"><?php esc_html_e('Min. Travelers (Pax)', 'nahian-tour-travel-booking-solution'); ?></label>
                                    <input type="number" min="1" name="min_pax" value="<?php echo esc_attr($edit_trip['min_pax'] ?? 1); ?>" class="nte-control-input">
                                </div>
                                <div class="nte-field-row">
                                    <label class="nte-field-label"><?php esc_html_e('Total Capacity Limit', 'nahian-tour-travel-booking-solution'); ?></label>
                                    <input type="number" min="1" name="max_seats" value="<?php echo esc_attr($edit_trip['max_seats'] ?? 24); ?>" class="nte-control-input">
                                </div>
                            </div>

                            <div class="nte-field-row" style="margin-bottom:0;">
                                <label class="nte-field-label"><?php esc_html_e('Cut-off Time Window', 'nahian-tour-travel-booking-solution'); ?></label>
                                <input type="text" name="cutoff_time" value="<?php echo esc_attr($edit_trip['cutoff_time'] ?? '24 Hours Before Departure'); ?>" class="nte-control-input">
                                <small style="color:var(--text-muted); font-size:11px;"><?php esc_html_e('Bookings are locked when this threshold is reached before departure.', 'nahian-tour-travel-booking-solution'); ?></small>
                            </div>
                        </div>
                    </div>

                    <!-- 2. OVERVIEW & INFO TAB -->
                    <div class="nte-stage-panel" id="stage-overview" style="display:none;">
                        <div class="nte-white-panel">
                            <div class="nte-panel-header"><span><?php esc_html_e('Trip Overview & Description', 'nahian-tour-travel-booking-solution'); ?></span></div>
                            <div class="nte-field-row">
                                <label class="nte-field-label"><?php esc_html_e('Detailed Narrative', 'nahian-tour-travel-booking-solution'); ?></label>
                                <textarea name="description" rows="6" class="nte-control-input" placeholder="<?php esc_attr_e('Comprehensive tour description, geography, historical highlights, marine life...', 'nahian-tour-travel-booking-solution'); ?>"><?php echo esc_textarea($edit_trip['description'] ?? ''); ?></textarea>
                            </div>

                            <div class="nte-panel-header" style="border-top:1px solid var(--border-light); padding-top:16px; margin-top:20px;">
                                <span><?php esc_html_e('Trip Highlights', 'nahian-tour-travel-booking-solution'); ?></span>
                            </div>
                            <div id="nttbs-highlights-wrap" style="display:flex; flex-direction:column; gap:8px; margin-bottom:12px;">
                                <?php if (!empty($trip_highlights)): foreach ($trip_highlights as $hl): ?>
                                    <div style="display:flex; gap:8px;">
                                        <input type="text" name="highlights[]" value="<?php echo esc_attr($hl); ?>" class="nte-control-input">
                                        <button type="button" onclick="this.parentElement.remove()" class="nte-del-btn">&times;</button>
                                    </div>
                                <?php endforeach; else: ?>
                                    <div style="display:flex; gap:8px;">
                                        <input type="text" name="highlights[]" value="Private yacht catamaran cruise through island archipelagos" class="nte-control-input">
                                        <button type="button" onclick="this.parentElement.remove()" class="nte-del-btn">&times;</button>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <button type="button" onclick="nttbsAddHighlightRow()" class="nte-btn-primary" style="font-size:12px; padding:6px 12px; background:var(--c-emerald);">
                                + <?php esc_html_e('Add Trip Highlight', 'nahian-tour-travel-booking-solution'); ?>
                            </button>

                            <div class="nte-panel-header" style="border-top:1px solid var(--border-light); padding-top:16px; margin-top:20px;">
                                <span><?php esc_html_e('Trip Key Facts Matrix', 'nahian-tour-travel-booking-solution'); ?></span>
                            </div>
                            <div class="nte-table-responsive" style="margin-bottom:12px;">
                                <table class="nte-data-table">
                                    <thead>
                                        <tr><th><?php esc_html_e('Fact Label', 'nahian-tour-travel-booking-solution'); ?></th><th><?php esc_html_e('Fact Value', 'nahian-tour-travel-booking-solution'); ?></th><th style="width:60px; text-align:center;"><?php esc_html_e('Action', 'nahian-tour-travel-booking-solution'); ?></th></tr>
                                    </thead>
                                    <tbody id="nttbs-facts-tbody">
                                        <?php if (!empty($trip_facts)): foreach ($trip_facts as $tf): ?>
                                            <tr>
                                                <td><input type="text" name="fact_key[]" value="<?php echo esc_attr($tf['title']); ?>" class="nte-control-input"></td>
                                                <td><input type="text" name="fact_val[]" value="<?php echo esc_attr($tf['value']); ?>" class="nte-control-input"></td>
                                                <td style="text-align:center;"><button type="button" onclick="this.closest('tr').remove()" class="nte-del-btn">&times;</button></td>
                                            </tr>
                                        <?php endforeach; else: ?>
                                            <tr>
                                                <td><input type="text" name="fact_key[]" value="Transport" class="nte-control-input"></td>
                                                <td><input type="text" name="fact_val[]" value="Royal Catamaran Cruiser" class="nte-control-input"></td>
                                                <td style="text-align:center;"><button type="button" onclick="this.closest('tr').remove()" class="nte-del-btn">&times;</button></td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                            <button type="button" onclick="nttbsAddFactRow()" class="nte-btn-primary" style="font-size:12px; padding:6px 12px; background:var(--c-teal);">
                                + <?php esc_html_e('Add Fact', 'nahian-tour-travel-booking-solution'); ?>
                            </button>
                        </div>
                    </div>

                    <!-- 3. ITINERARY TAB -->
                    <div class="nte-stage-panel" id="stage-itinerary" style="display:none;">
                        <div class="nte-white-panel">
                            <div class="nte-panel-header"><span><?php esc_html_e('Itinerary Header Narrative', 'nahian-tour-travel-booking-solution'); ?></span></div>
                            <div class="nte-field-row">
                                <label class="nte-field-label"><?php esc_html_e('Section Title', 'nahian-tour-travel-booking-solution'); ?></label>
                                <input type="text" name="itinerary_title_head" value="<?php echo esc_attr($edit_trip['itinerary_title'] ?? 'Expedition Day-by-Day Itinerary'); ?>" class="nte-control-input">
                            </div>
                            <div class="nte-field-row">
                                <label class="nte-field-label"><?php esc_html_e('General Itinerary Description', 'nahian-tour-travel-booking-solution'); ?></label>
                                <textarea name="itinerary_desc_head" rows="2" class="nte-control-input"><?php echo esc_textarea($edit_trip['itinerary_desc'] ?? 'A comprehensive breakdown of daily schedules, anchorages, and excursions.'); ?></textarea>
                            </div>

                            <div class="nte-panel-header" style="border-top:1px solid var(--border-light); padding-top:16px; margin-top:20px;">
                                <span><?php esc_html_e('Daily Procedural Schedule', 'nahian-tour-travel-booking-solution'); ?></span>
                            </div>
                            <div id="nttbs-itinerary-container" style="display:flex; flex-direction:column; gap:14px; margin-bottom:14px;">
                                <?php if (!empty($trip_itinerary)): foreach ($trip_itinerary as $it): ?>
                                    <div class="nte-card-bordered">
                                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                                            <strong style="color:var(--c-blue); font-size:13px;"><?php esc_html_e('Day', 'nahian-tour-travel-booking-solution'); ?> <span class="day-num"><?php echo esc_html($it['day']); ?></span></strong>
                                            <button type="button" onclick="this.closest('.nte-card-bordered').remove(); nttbsReindexDays();" class="nte-del-btn">&times;</button>
                                        </div>
                                        <input type="text" name="itinerary_day_title[]" value="<?php echo esc_attr($it['title']); ?>" class="nte-control-input" placeholder="Title: Port Boarding & Dolphin Bay Cruising" style="margin-bottom:8px; font-weight:700;">
                                        <textarea name="itinerary_day_desc[]" rows="2" class="nte-control-input" placeholder="Daily narrative..." style="margin-bottom:8px;"><?php echo esc_textarea($it['desc']); ?></textarea>
                                        <div style="display:grid; grid-template-columns: repeat(2, 1fr); gap:10px;">
                                            <input type="text" name="itinerary_day_meals[]" value="<?php echo esc_attr($it['meals'] ?? ''); ?>" class="nte-control-input" placeholder="Meals: Buffet Lunch, Dinner">
                                            <input type="text" name="itinerary_day_lodge[]" value="<?php echo esc_attr($it['lodge'] ?? ''); ?>" class="nte-control-input" placeholder="Accommodation: Onboard Yacht Cabin">
                                        </div>
                                    </div>
                                <?php endforeach; else: ?>
                                    <div class="nte-card-bordered">
                                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                                            <strong style="color:var(--c-blue); font-size:13px;"><?php esc_html_e('Day', 'nahian-tour-travel-booking-solution'); ?> <span class="day-num">1</span></strong>
                                            <button type="button" onclick="this.closest('.nte-card-bordered').remove(); nttbsReindexDays();" class="nte-del-btn">&times;</button>
                                        </div>
                                        <input type="text" name="itinerary_day_title[]" value="Marina Boarding & Coral Reef Cruising" class="nte-control-input" style="margin-bottom:8px; font-weight:700;">
                                        <textarea name="itinerary_day_desc[]" rows="2" class="nte-control-input" style="margin-bottom:8px;">Morning embarkation at marina, dolphin bay cruise, guided reef snorkeling.</textarea>
                                        <div style="display:grid; grid-template-columns: repeat(2, 1fr); gap:10px;">
                                            <input type="text" name="itinerary_day_meals[]" value="Buffet Lunch, Mineral Water" class="nte-control-input">
                                            <input type="text" name="itinerary_day_lodge[]" value="Onboard Yacht Luxury Cabin" class="nte-control-input">
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <button type="button" onclick="nttbsAddItineraryDay()" class="nte-btn-primary" style="font-size:12px; padding:8px 16px;">
                                + <?php esc_html_e('Add Itinerary Day', 'nahian-tour-travel-booking-solution'); ?>
                            </button>
                        </div>
                    </div>

                    <!-- 4. DATE & PRICING TAB (MULTI-PACKAGE ENGINE) -->
                    <div class="nte-stage-panel" id="stage-pricing" style="display:none;">
                        <div class="nte-white-panel">
                            <div class="nte-panel-header"><span><?php esc_html_e('Baseline Rates & Taxonomies', 'nahian-tour-travel-booking-solution'); ?></span></div>
                            
                            <div style="display:grid; grid-template-columns: repeat(3, 1fr); gap:16px;">
                                <div class="nte-field-row">
                                    <label class="nte-field-label"><?php esc_html_e('Base Adult Rate', 'nahian-tour-travel-booking-solution'); ?> (<?php echo esc_html($currency); ?>) *</label>
                                    <input type="number" step="0.01" name="price_adult" value="<?php echo esc_attr($edit_trip['price_adult'] ?? '185.00'); ?>" required class="nte-control-input" style="font-weight:800; color:var(--c-blue);">
                                </div>
                                <div class="nte-field-row">
                                    <label class="nte-field-label"><?php esc_html_e('Base Child Rate', 'nahian-tour-travel-booking-solution'); ?> (<?php echo esc_html($currency); ?>)</label>
                                    <input type="number" step="0.01" name="price_child" value="<?php echo esc_attr($edit_trip['price_child'] ?? '110.00'); ?>" class="nte-control-input">
                                </div>
                                <div class="nte-field-row">
                                    <label class="nte-field-label"><?php esc_html_e('Deposit Requirement (%)', 'nahian-tour-travel-booking-solution'); ?></label>
                                    <input type="number" min="1" max="100" name="deposit_percent" value="<?php echo esc_attr($edit_trip['deposit_percent'] ?? 50); ?>" class="nte-control-input">
                                </div>
                            </div>

                            <div style="display:grid; grid-template-columns: repeat(2, 1fr); gap:16px;">
                                <div class="nte-field-row">
                                    <label class="nte-field-label"><?php esc_html_e('Destination', 'nahian-tour-travel-booking-solution'); ?></label>
                                    <select name="destination_id" class="nte-control-input">
                                        <option value="0">-- <?php esc_html_e('Select Destination', 'nahian-tour-travel-booking-solution'); ?> --</option>
                                        <?php foreach ($destinations as $d): ?>
                                            <option value="<?php echo esc_attr($d['id']); ?>" <?php selected($edit_trip['destination_id'] ?? 0, $d['id']); ?>><?php echo esc_html($d['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="nte-field-row">
                                    <label class="nte-field-label"><?php esc_html_e('Activity', 'nahian-tour-travel-booking-solution'); ?></label>
                                    <select name="activity_id" class="nte-control-input">
                                        <option value="0">-- <?php esc_html_e('Select Activity', 'nahian-tour-travel-booking-solution'); ?> --</option>
                                        <?php foreach ($activities as $a): ?>
                                            <option value="<?php echo esc_attr($a['id']); ?>" <?php selected($edit_trip['activity_id'] ?? 0, $a['id']); ?>><?php echo esc_html($a['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div style="display:grid; grid-template-columns: repeat(2, 1fr); gap:16px;">
                                <div class="nte-field-row">
                                    <label class="nte-field-label"><?php esc_html_e('Trip Classification', 'nahian-tour-travel-booking-solution'); ?></label>
                                    <select name="trip_type_id" class="nte-control-input">
                                        <option value="0">-- <?php esc_html_e('Select Type', 'nahian-tour-travel-booking-solution'); ?> --</option>
                                        <?php foreach ($trip_types as $tt): ?>
                                            <option value="<?php echo esc_attr($tt['id']); ?>" <?php selected($edit_trip['trip_type_id'] ?? 0, $tt['id']); ?>><?php echo esc_html($tt['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="nte-field-row">
                                    <label class="nte-field-label"><?php esc_html_e('Endurance Rating', 'nahian-tour-travel-booking-solution'); ?></label>
                                    <select name="difficulty_id" class="nte-control-input">
                                        <option value="0">-- <?php esc_html_e('Select Difficulty', 'nahian-tour-travel-booking-solution'); ?> --</option>
                                        <?php foreach ($difficulties as $df): ?>
                                            <option value="<?php echo esc_attr($df['id']); ?>" <?php selected($edit_trip['difficulty_id'] ?? 0, $df['id']); ?>><?php echo esc_html($df['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <!-- Multi-Package Tiers Repeater -->
                            <div class="nte-panel-header" style="border-top:1px solid var(--border-light); padding-top:16px; margin-top:20px;">
                                <span><?php esc_html_e('Multi-Tier Packages (e.g. Standard, Deluxe Saloon, VIP Suite)', 'nahian-tour-travel-booking-solution'); ?></span>
                            </div>
                            <div id="nttbs-packages-wrap" style="display:flex; flex-direction:column; gap:12px; margin-bottom:12px;">
                                <?php if (!empty($trip_packages)): foreach ($trip_packages as $pkg): ?>
                                    <div class="nte-card-bordered">
                                        <div style="display:flex; justify-content:space-between; margin-bottom:6px;">
                                            <strong><?php esc_html_e('Package Tier', 'nahian-tour-travel-booking-solution'); ?></strong>
                                            <button type="button" onclick="this.closest('.nte-card-bordered').remove()" class="nte-del-btn">&times;</button>
                                        </div>
                                        <div style="display:grid; grid-template-columns: 2fr 1fr 1fr 1fr; gap:10px;">
                                            <input type="text" name="pkg_name[]" value="<?php echo esc_attr($pkg['name']); ?>" class="nte-control-input" placeholder="Package Name (e.g. VIP Private Saloon)">
                                            <input type="number" step="0.01" name="pkg_adult[]" value="<?php echo esc_attr($pkg['price_adult']); ?>" class="nte-control-input" placeholder="Adult Fare">
                                            <input type="number" step="0.01" name="pkg_child[]" value="<?php echo esc_attr($pkg['price_child']); ?>" class="nte-control-input" placeholder="Child Fare">
                                            <input type="number" name="pkg_cap[]" value="<?php echo esc_attr($pkg['capacity']); ?>" class="nte-control-input" placeholder="Max Seats">
                                        </div>
                                        <input type="text" name="pkg_desc[]" value="<?php echo esc_attr($pkg['desc']); ?>" class="nte-control-input" placeholder="Package tier details or inclusions..." style="margin-top:6px;">
                                    </div>
                                <?php endforeach; else: ?>
                                    <div class="nte-card-bordered">
                                        <div style="display:flex; justify-content:space-between; margin-bottom:6px;">
                                            <strong><?php esc_html_e('Standard Package', 'nahian-tour-travel-booking-solution'); ?></strong>
                                            <button type="button" onclick="this.closest('.nte-card-bordered').remove()" class="nte-del-btn">&times;</button>
                                        </div>
                                        <div style="display:grid; grid-template-columns: 2fr 1fr 1fr 1fr; gap:10px;">
                                            <input type="text" name="pkg_name[]" value="Standard Saloon Passage" class="nte-control-input">
                                            <input type="number" step="0.01" name="pkg_adult[]" value="185.00" class="nte-control-input">
                                            <input type="number" step="0.01" name="pkg_child[]" value="110.00" class="nte-control-input">
                                            <input type="number" name="pkg_cap[]" value="24" class="nte-control-input">
                                        </div>
                                        <input type="text" name="pkg_desc[]" value="Includes full standard catamaran berth access and meals." class="nte-control-input" style="margin-top:6px;">
                                    </div>
                                <?php endif; ?>
                            </div>
                            <button type="button" onclick="nttbsAddPackageTier()" class="nte-btn-primary" style="font-size:12px; padding:6px 14px;">
                                + <?php esc_html_e('Add New Package Tier', 'nahian-tour-travel-booking-solution'); ?>
                            </button>
                        </div>
                    </div>

                    <!-- 5. INCLUDES / EXCLUDES TAB -->
                    <div class="nte-stage-panel" id="stage-includes" style="display:none;">
                        <div class="nte-white-panel">
                            <div class="nte-panel-header"><span><?php esc_html_e('Inclusions & Exclusions Header', 'nahian-tour-travel-booking-solution'); ?></span></div>
                            <div class="nte-field-row">
                                <label class="nte-field-label"><?php esc_html_e('Section Heading', 'nahian-tour-travel-booking-solution'); ?></label>
                                <input type="text" name="inc_exc_title" value="<?php echo esc_attr($edit_trip['inc_exc_title'] ?? 'Expedition Inclusions & Exclusions'); ?>" class="nte-control-input">
                            </div>

                            <div style="display:grid; grid-template-columns: repeat(2, 1fr); gap:16px;">
                                <div class="nte-field-row" style="margin-bottom:0;">
                                    <label class="nte-field-label" style="color:var(--c-emerald);"><?php esc_html_e('✓ Services Included in Fare', 'nahian-tour-travel-booking-solution'); ?></label>
                                    <textarea name="inclusions" rows="8" class="nte-control-input" placeholder="e.g. Catamaran stay, buffet dining, snorkeling gear, marine preservation permits, bottled water..."><?php echo esc_textarea($edit_trip['inclusions'] ?? ''); ?></textarea>
                                </div>
                                <div class="nte-field-row" style="margin-bottom:0;">
                                    <label class="nte-field-label" style="color:var(--c-rose);"><?php esc_html_e('✕ Services Excluded', 'nahian-tour-travel-booking-solution'); ?></label>
                                    <textarea name="exclusions" rows="8" class="nte-control-input" placeholder="e.g. Flight transport, personal dive gear insurance, gratuities, alcoholic beverages..."><?php echo esc_textarea($edit_trip['exclusions'] ?? ''); ?></textarea>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 6. MAP & GALLERY TAB -->
                    <div class="nte-stage-panel" id="stage-gallery" style="display:none;">
                        <div class="nte-white-panel">
                            <div class="nte-panel-header"><span><?php esc_html_e('High-Resolution Media Gallery', 'nahian-tour-travel-booking-solution'); ?></span></div>
                            <div class="nte-field-row">
                                <input type="hidden" name="gallery_images" id="nttbs_gallery_images_input" value="<?php echo esc_attr($edit_trip['gallery_images'] ?? ''); ?>">
                                <button type="button" class="nte-btn-primary" onclick="nttbsOpenGalleryUploader()" style="padding:8px 16px; font-size:12px; margin-bottom:12px;">
                                    + <?php esc_html_e('Add Images from Media Library', 'nahian-tour-travel-booking-solution'); ?>
                                </button>
                                <div id="nttbs-gallery-preview-wrap" style="display:grid; grid-template-columns: repeat(5, 1fr); gap:12px;">
                                    <?php if (!empty($gallery_array)): foreach ($gallery_array as $img_url): if (trim($img_url)): ?>
                                        <div class="gallery-thumb-box" style="position:relative; border-radius:8px; overflow:hidden; border:1px solid var(--border-light);">
                                            <img src="<?php echo esc_url($img_url); ?>" style="width:100%; height:90px; object-fit:cover; display:block;">
                                            <button type="button" onclick="nttbsRemoveGalleryImage(this, '<?php echo esc_js($img_url); ?>')" style="position:absolute; top:4px; right:4px; background:var(--c-rose); color:#fff; border:none; border-radius:999px; width:22px; height:22px; cursor:pointer; font-weight:800; line-height:1;">&times;</button>
                                        </div>
                                    <?php endif; endforeach; endif; ?>
                                </div>
                            </div>

                            <div class="nte-panel-header" style="border-top:1px solid var(--border-light); padding-top:16px; margin-top:20px;">
                                <span><?php esc_html_e('Video & Interactive Maps', 'nahian-tour-travel-booking-solution'); ?></span>
                            </div>
                            <div class="nte-field-row">
                                <label class="nte-field-label"><?php esc_html_e('Video Showcase Links (YouTube / Vimeo / MP4)', 'nahian-tour-travel-booking-solution'); ?></label>
                                <textarea name="video_gallery" rows="2" class="nte-control-input" placeholder="https://www.youtube.com/watch?v=..."><?php echo esc_textarea($edit_trip['video_gallery'] ?? ''); ?></textarea>
                            </div>
                            <div class="nte-field-row">
                                <label class="nte-field-label"><?php esc_html_e('Interactive Map Embed (Google Maps iframe)', 'nahian-tour-travel-booking-solution'); ?></label>
                                <textarea name="map_iframe" rows="3" class="nte-control-input" placeholder="<iframe src='https://www.google.com/maps/embed?...'></iframe>"><?php echo esc_textarea($edit_trip['map_iframe'] ?? ''); ?></textarea>
                            </div>
                            <div class="nte-field-row" style="margin-bottom:0;">
                                <label class="nte-field-label"><?php esc_html_e('Static Route Map Image', 'nahian-tour-travel-booking-solution'); ?></label>
                                <div style="display:flex; gap:8px;">
                                    <input type="text" name="map_image" id="nttbs_map_image_input" value="<?php echo esc_attr($edit_trip['map_image'] ?? ''); ?>" class="nte-control-input" placeholder="Image URL">
                                    <button type="button" class="nte-btn-primary" onclick="nttbsOpenMapUploader()" style="padding:0 16px; font-size:12px; white-space:nowrap;"><?php esc_html_e('Upload Graphic', 'nahian-tour-travel-booking-solution'); ?></button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 7. FAQS TAB -->
                    <div class="nte-stage-panel" id="stage-faqs" style="display:none;">
                        <div class="nte-white-panel">
                            <div class="nte-panel-header"><span><?php esc_html_e('Frequently Asked Questions', 'nahian-tour-travel-booking-solution'); ?></span></div>
                            <div id="nttbs-faqs-container" style="display:flex; flex-direction:column; gap:12px; margin-bottom:12px;">
                                <?php if (!empty($trip_faqs)): foreach ($trip_faqs as $fq): ?>
                                    <div class="nte-card-bordered">
                                        <div style="display:flex; justify-content:space-between; margin-bottom:6px;">
                                            <strong><?php esc_html_e('Question', 'nahian-tour-travel-booking-solution'); ?></strong>
                                            <button type="button" onclick="this.closest('.nte-card-bordered').remove()" class="nte-del-btn">&times;</button>
                                        </div>
                                        <input type="text" name="faq_q[]" value="<?php echo esc_attr($fq['q']); ?>" class="nte-control-input" style="margin-bottom:6px;">
                                        <textarea name="faq_a[]" rows="2" class="nte-control-input"><?php echo esc_textarea($fq['a']); ?></textarea>
                                    </div>
                                <?php endforeach; else: ?>
                                    <div class="nte-card-bordered">
                                        <div style="display:flex; justify-content:space-between; margin-bottom:6px;">
                                            <strong><?php esc_html_e('Question', 'nahian-tour-travel-booking-solution'); ?></strong>
                                            <button type="button" onclick="this.closest('.nte-card-bordered').remove()" class="nte-del-btn">&times;</button>
                                        </div>
                                        <input type="text" name="faq_q[]" value="What should I pack for the catamaran voyage?" class="nte-control-input" style="margin-bottom:6px;">
                                        <textarea name="faq_a[]" rows="2" class="nte-control-input">Swimwear, reef-safe sunscreen, sunglasses, and personal identification passport.</textarea>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <button type="button" onclick="nttbsAddFAQRow()" class="nte-btn-primary" style="font-size:12px; padding:6px 14px;">
                                + <?php esc_html_e('Add FAQ Item', 'nahian-tour-travel-booking-solution'); ?>
                            </button>
                        </div>
                    </div>

                    <!-- 8. INCREASE REVENUE TAB (GROUP DISCOUNTS) -->
                    <div class="nte-stage-panel" id="stage-revenue" style="display:none;">
                        <div class="nte-white-panel">
                            <div class="nte-panel-header"><span><?php esc_html_e('Automated Volume Group Discounts', 'nahian-tour-travel-booking-solution'); ?></span></div>
                            <p style="font-size:12px; color:var(--text-muted); margin-bottom:14px; margin-top:-6px;"><?php esc_html_e('Incentivize larger private bookings with volume-based tier reductions.', 'nahian-tour-travel-booking-solution'); ?></p>
                            
                            <div class="nte-table-responsive" style="margin-bottom:12px;">
                                <table class="nte-data-table">
                                    <thead>
                                        <tr>
                                            <th><?php esc_html_e('Min Travelers (Pax)', 'nahian-tour-travel-booking-solution'); ?></th>
                                            <th><?php esc_html_e('Discount Value', 'nahian-tour-travel-booking-solution'); ?></th>
                                            <th><?php esc_html_e('Type', 'nahian-tour-travel-booking-solution'); ?></th>
                                            <th style="width:60px; text-align:center;"><?php esc_html_e('Action', 'nahian-tour-travel-booking-solution'); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody id="nttbs-gd-tbody">
                                        <?php if (!empty($group_discounts)): foreach ($group_discounts as $gd): ?>
                                            <tr>
                                                <td><input type="number" min="2" name="gd_pax[]" value="<?php echo esc_attr($gd['min_pax']); ?>" class="nte-control-input"></td>
                                                <td><input type="number" step="0.01" name="gd_discount[]" value="<?php echo esc_attr($gd['discount']); ?>" class="nte-control-input"></td>
                                                <td>
                                                    <select name="gd_type[]" class="nte-control-input">
                                                        <option value="percentage" <?php selected($gd['type'], 'percentage'); ?>><?php esc_html_e('Percentage (%)', 'nahian-tour-travel-booking-solution'); ?></option>
                                                        <option value="fixed" <?php selected($gd['type'], 'fixed'); ?>><?php esc_html_e('Fixed Amount', 'nahian-tour-travel-booking-solution'); ?></option>
                                                    </select>
                                                </td>
                                                <td style="text-align:center;"><button type="button" onclick="this.closest('tr').remove()" class="nte-del-btn">&times;</button></td>
                                            </tr>
                                        <?php endforeach; else: ?>
                                            <tr>
                                                <td><input type="number" min="2" name="gd_pax[]" value="6" class="nte-control-input"></td>
                                                <td><input type="number" step="0.01" name="gd_discount[]" value="10.00" class="nte-control-input"></td>
                                                <td>
                                                    <select name="gd_type[]" class="nte-control-input">
                                                        <option value="percentage"><?php esc_html_e('Percentage (%)', 'nahian-tour-travel-booking-solution'); ?></option>
                                                        <option value="fixed"><?php esc_html_e('Fixed Amount', 'nahian-tour-travel-booking-solution'); ?></option>
                                                    </select>
                                                </td>
                                                <td style="text-align:center;"><button type="button" onclick="this.closest('tr').remove()" class="nte-del-btn">&times;</button></td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                            <button type="button" onclick="nttbsAddGroupDiscountRow()" class="nte-btn-primary" style="font-size:12px; padding:6px 14px; background:var(--c-emerald);">
                                + <?php esc_html_e('Add Volume Discount Tier', 'nahian-tour-travel-booking-solution'); ?>
                            </button>
                        </div>
                    </div>

                    <!-- 9. EXTRA SERVICES / ADD-ONS TAB -->
                    <div class="nte-stage-panel" id="stage-extras" style="display:none;">
                        <div class="nte-white-panel">
                            <div class="nte-panel-header"><span><?php esc_html_e('Optional Upsell Add-ons & Extra Services', 'nahian-tour-travel-booking-solution'); ?></span></div>
                            <p style="font-size:12px; color:var(--text-muted); margin-bottom:14px; margin-top:-6px;"><?php esc_html_e('Add services bookable alongside standard seats (e.g. VIP Transfer, Dive Gear Rental).', 'nahian-tour-travel-booking-solution'); ?></p>

                            <div class="nte-table-responsive" style="margin-bottom:12px;">
                                <table class="nte-data-table">
                                    <thead>
                                        <tr>
                                            <th><?php esc_html_e('Service Title', 'nahian-tour-travel-booking-solution'); ?></th>
                                            <th><?php esc_html_e('Fee', 'nahian-tour-travel-booking-solution'); ?> (<?php echo esc_html($currency); ?>)</th>
                                            <th><?php esc_html_e('Pricing Model', 'nahian-tour-travel-booking-solution'); ?></th>
                                            <th style="width:60px; text-align:center;"><?php esc_html_e('Action', 'nahian-tour-travel-booking-solution'); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody id="nttbs-extras-tbody">
                                        <?php if (!empty($extra_services)): foreach ($extra_services as $es): ?>
                                            <tr>
                                                <td><input type="text" name="extra_title[]" value="<?php echo esc_attr($es['title']); ?>" class="nte-control-input"></td>
                                                <td><input type="number" step="0.01" name="extra_price[]" value="<?php echo esc_attr($es['price']); ?>" class="nte-control-input"></td>
                                                <td>
                                                    <select name="extra_type[]" class="nte-control-input">
                                                        <option value="per_pax" <?php selected($es['type'], 'per_pax'); ?>><?php esc_html_e('Per Passenger', 'nahian-tour-travel-booking-solution'); ?></option>
                                                        <option value="per_booking" <?php selected($es['type'], 'per_booking'); ?>><?php esc_html_e('Per Booking Order', 'nahian-tour-travel-booking-solution'); ?></option>
                                                    </select>
                                                </td>
                                                <td style="text-align:center;"><button type="button" onclick="this.closest('tr').remove()" class="nte-del-btn">&times;</button></td>
                                            </tr>
                                        <?php endforeach; else: ?>
                                            <tr>
                                                <td><input type="text" name="extra_title[]" value="Private Hotel Harbor Transfer" class="nte-control-input"></td>
                                                <td><input type="number" step="0.01" name="extra_price[]" value="25.00" class="nte-control-input"></td>
                                                <td>
                                                    <select name="extra_type[]" class="nte-control-input">
                                                        <option value="per_booking"><?php esc_html_e('Per Booking Order', 'nahian-tour-travel-booking-solution'); ?></option>
                                                        <option value="per_pax"><?php esc_html_e('Per Passenger', 'nahian-tour-travel-booking-solution'); ?></option>
                                                    </select>
                                                </td>
                                                <td style="text-align:center;"><button type="button" onclick="this.closest('tr').remove()" class="nte-del-btn">&times;</button></td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                            <button type="button" onclick="nttbsAddExtraRow()" class="nte-btn-primary" style="font-size:12px; padding:6px 14px; background:var(--c-teal);">
                                + <?php esc_html_e('Add Extra Service Upsell', 'nahian-tour-travel-booking-solution'); ?>
                            </button>
                        </div>
                    </div>

                    <!-- 10. ADVANCED SETTINGS TAB -->
                    <div class="nte-stage-panel" id="stage-advanced" style="display:none;">
                        <div class="nte-white-panel">
                            <div class="nte-panel-header"><span><?php esc_html_e('Advanced Operational Constraints', 'nahian-tour-travel-booking-solution'); ?></span></div>
                            
                            <div class="nte-field-row" style="background:var(--bg-subtle); padding:16px; border-radius:8px; border:1px solid var(--border-light); margin-bottom:16px;">
                                <label style="cursor:pointer; display:flex; align-items:center; gap:10px; font-size:13px; font-weight:800; color:var(--text-main);">
                                    <input type="checkbox" name="adv_allow_partial" value="1" <?php checked($adv_meta['allow_partial'] ?? 1, 1); ?>>
                                    <?php esc_html_e('Allow Partial Deposit Bookings (Uses configured deposit percentage)', 'nahian-tour-travel-booking-solution'); ?>
                                </label>
                                <label style="cursor:pointer; display:flex; align-items:center; gap:10px; font-size:13px; font-weight:800; color:var(--text-main); margin-top:12px;">
                                    <input type="checkbox" name="adv_require_id" value="1" <?php checked($adv_meta['require_id'] ?? 0, 1); ?>>
                                    <?php esc_html_e('Mandatory Passport / National ID on Booking', 'nahian-tour-travel-booking-solution'); ?>
                                </label>
                            </div>

                            <div class="nte-field-row">
                                <label class="nte-field-label"><?php esc_html_e('Custom Ribbons & Promotional Badges', 'nahian-tour-travel-booking-solution'); ?></label>
                                <input type="text" name="adv_badge_custom" value="<?php echo esc_attr($adv_meta['badge_custom'] ?? 'FEATURED EXPEDITION'); ?>" class="nte-control-input" placeholder="e.g. BEST SELLER, EXCLUSIVE">
                            </div>

                            <div class="nte-field-row" style="margin-bottom:0;">
                                <label class="nte-field-label"><?php esc_html_e('Affiliate / External Booking Redirection URL (Optional)', 'nahian-tour-travel-booking-solution'); ?></label>
                                <input type="url" name="adv_external_link" value="<?php echo esc_attr($adv_meta['external_link'] ?? ''); ?>" class="nte-control-input" placeholder="https://...">
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </form>

        <script>
        function nttbsSwitchTab(stageKey, btn) {
            document.querySelectorAll('.nte-stage-panel').forEach(el => el.style.display = 'none');
            document.querySelectorAll('.nte-subnav-btn').forEach(el => el.classList.remove('active'));
            
            const target = document.getElementById('stage-' + stageKey);
            if (target) target.style.display = 'block';
            btn.classList.add('active');
        }

        function nttbsAddHighlightRow() {
            const wrap = document.getElementById('nttbs-highlights-wrap');
            if (!wrap) return;
            const div = document.createElement('div');
            div.style.cssText = 'display:flex; gap:8px;';
            div.innerHTML = `
                <input type="text" name="highlights[]" class="nte-control-input" placeholder="<?php echo esc_js(__('New highlight point', 'nahian-tour-travel-booking-solution')); ?>">
                <button type="button" onclick="this.parentElement.remove()" class="nte-del-btn">&times;</button>
            `;
            wrap.appendChild(div);
        }

        function nttbsAddFactRow() {
            const tbody = document.getElementById('nttbs-facts-tbody');
            if (!tbody) return;
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td><input type="text" name="fact_key[]" class="nte-control-input" placeholder="<?php echo esc_js(__('Fact Label', 'nahian-tour-travel-booking-solution')); ?>"></td>
                <td><input type="text" name="fact_val[]" class="nte-control-input" placeholder="<?php echo esc_js(__('Fact Value', 'nahian-tour-travel-booking-solution')); ?>"></td>
                <td style="text-align:center;"><button type="button" onclick="this.closest('tr').remove()" class="nte-del-btn">&times;</button></td>
            `;
            tbody.appendChild(tr);
        }

        function nttbsAddItineraryDay() {
            const container = document.getElementById('nttbs-itinerary-container');
            if (!container) return;
            const count = container.querySelectorAll('.nte-card-bordered').length + 1;
            const div = document.createElement('div');
            div.className = 'nte-card-bordered';
            div.innerHTML = `
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                    <strong style="color:var(--c-blue); font-size:13px;"><?php echo esc_js(__('Day', 'nahian-tour-travel-booking-solution')); ?> <span class="day-num">${count}</span></strong>
                    <button type="button" onclick="this.closest('.nte-card-bordered').remove(); nttbsReindexDays();" class="nte-del-btn">&times;</button>
                </div>
                <input type="text" name="itinerary_day_title[]" class="nte-control-input" placeholder="<?php echo esc_js(__('Daily Milestone Title', 'nahian-tour-travel-booking-solution')); ?>" style="margin-bottom:8px; font-weight:700;">
                <textarea name="itinerary_day_desc[]" rows="2" class="nte-control-input" placeholder="<?php echo esc_js(__('Daily narrative...', 'nahian-tour-travel-booking-solution')); ?>" style="margin-bottom:8px;"></textarea>
                <div style="display:grid; grid-template-columns: repeat(2, 1fr); gap:10px;">
                    <input type="text" name="itinerary_day_meals[]" class="nte-control-input" placeholder="<?php echo esc_js(__('Meals: Breakfast, Lunch, Dinner', 'nahian-tour-travel-booking-solution')); ?>">
                    <input type="text" name="itinerary_day_lodge[]" class="nte-control-input" placeholder="<?php echo esc_js(__('Accommodation / Lodging', 'nahian-tour-travel-booking-solution')); ?>">
                </div>
            `;
            container.appendChild(div);
        }

        function nttbsReindexDays() {
            document.querySelectorAll('#nttbs-itinerary-container .day-num').forEach((el, index) => {
                el.textContent = index + 1;
            });
        }

        function nttbsAddPackageTier() {
            const wrap = document.getElementById('nttbs-packages-wrap');
            if (!wrap) return;
            const div = document.createElement('div');
            div.className = 'nte-card-bordered';
            div.innerHTML = `
                <div style="display:flex; justify-content:space-between; margin-bottom:6px;">
                    <strong><?php echo esc_js(__('Package Tier', 'nahian-tour-travel-booking-solution')); ?></strong>
                    <button type="button" onclick="this.closest('.nte-card-bordered').remove()" class="nte-del-btn">&times;</button>
                </div>
                <div style="display:grid; grid-template-columns: 2fr 1fr 1fr 1fr; gap:10px;">
                    <input type="text" name="pkg_name[]" class="nte-control-input" placeholder="<?php echo esc_js(__('Package Title', 'nahian-tour-travel-booking-solution')); ?>">
                    <input type="number" step="0.01" name="pkg_adult[]" class="nte-control-input" placeholder="<?php echo esc_js(__('Adult Fare', 'nahian-tour-travel-booking-solution')); ?>">
                    <input type="number" step="0.01" name="pkg_child[]" class="nte-control-input" placeholder="<?php echo esc_js(__('Child Fare', 'nahian-tour-travel-booking-solution')); ?>">
                    <input type="number" name="pkg_cap[]" class="nte-control-input" placeholder="<?php echo esc_js(__('Capacity', 'nahian-tour-travel-booking-solution')); ?>">
                </div>
                <input type="text" name="pkg_desc[]" class="nte-control-input" placeholder="<?php echo esc_js(__('Package specifications...', 'nahian-tour-travel-booking-solution')); ?>" style="margin-top:6px;">
            `;
            wrap.appendChild(div);
        }

        function nttbsAddFAQRow() {
            const container = document.getElementById('nttbs-faqs-container');
            if (!container) return;
            const div = document.createElement('div');
            div.className = 'nte-card-bordered';
            div.innerHTML = `
                <div style="display:flex; justify-content:space-between; margin-bottom:6px;">
                    <strong><?php echo esc_js(__('Question', 'nahian-tour-travel-booking-solution')); ?></strong>
                    <button type="button" onclick="this.closest('.nte-card-bordered').remove()" class="nte-del-btn">&times;</button>
                </div>
                <input type="text" name="faq_q[]" class="nte-control-input" placeholder="<?php echo esc_js(__('Question', 'nahian-tour-travel-booking-solution')); ?>" style="margin-bottom:6px;">
                <textarea name="faq_a[]" rows="2" class="nte-control-input" placeholder="<?php echo esc_js(__('Answer response...', 'nahian-tour-travel-booking-solution')); ?>"></textarea>
            `;
            container.appendChild(div);
        }

        function nttbsAddGroupDiscountRow() {
            const tbody = document.getElementById('nttbs-gd-tbody');
            if (!tbody) return;
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td><input type="number" min="2" name="gd_pax[]" class="nte-control-input" placeholder="<?php echo esc_js(__('Min Pax', 'nahian-tour-travel-booking-solution')); ?>"></td>
                <td><input type="number" step="0.01" name="gd_discount[]" class="nte-control-input" placeholder="<?php echo esc_js(__('Discount', 'nahian-tour-travel-booking-solution')); ?>"></td>
                <td>
                    <select name="gd_type[]" class="nte-control-input">
                        <option value="percentage"><?php echo esc_js(__('Percentage (%)', 'nahian-tour-travel-booking-solution')); ?></option>
                        <option value="fixed"><?php echo esc_js(__('Fixed Amount', 'nahian-tour-travel-booking-solution')); ?></option>
                    </select>
                </td>
                <td style="text-align:center;"><button type="button" onclick="this.closest('tr').remove()" class="nte-del-btn">&times;</button></td>
            `;
            tbody.appendChild(tr);
        }

        function nttbsAddExtraRow() {
            const tbody = document.getElementById('nttbs-extras-tbody');
            if (!tbody) return;
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td><input type="text" name="extra_title[]" class="nte-control-input" placeholder="<?php echo esc_js(__('Service Name', 'nahian-tour-travel-booking-solution')); ?>"></td>
                <td><input type="number" step="0.01" name="extra_price[]" class="nte-control-input" placeholder="<?php echo esc_js(__('Rate', 'nahian-tour-travel-booking-solution')); ?>"></td>
                <td>
                    <select name="extra_type[]" class="nte-control-input">
                        <option value="per_pax"><?php echo esc_js(__('Per Passenger', 'nahian-tour-travel-booking-solution')); ?></option>
                        <option value="per_booking"><?php echo esc_js(__('Per Booking Order', 'nahian-tour-travel-booking-solution')); ?></option>
                    </select>
                </td>
                <td style="text-align:center;"><button type="button" onclick="this.closest('tr').remove()" class="nte-del-btn">&times;</button></td>
            `;
            tbody.appendChild(tr);
        }

        function nttbsOpenGalleryUploader() {
            const frame = wp.media({
                title: '<?php echo esc_js(__('Select Expedition Gallery Photos', 'nahian-tour-travel-booking-solution')); ?>',
                button: { text: '<?php echo esc_js(__('Add to Gallery', 'nahian-tour-travel-booking-solution')); ?>' },
                multiple: true
            });

            frame.on('select', function() {
                const attachments = frame.state().get('selection').toJSON();
                const previewWrap = document.getElementById('nttbs-gallery-preview-wrap');
                const hiddenInput = document.getElementById('nttbs_gallery_images_input');
                
                let current = hiddenInput.value ? hiddenInput.value.split(',') : [];

                attachments.forEach(att => {
                    const imgUrl = att.sizes && att.sizes.medium ? att.sizes.medium.url : att.url;
                    if (!current.includes(imgUrl)) {
                        current.push(imgUrl);
                        const thumb = document.createElement('div');
                        thumb.className = 'gallery-thumb-box';
                        thumb.style.cssText = 'position:relative; border-radius:8px; overflow:hidden; border:1px solid var(--border-light);';
                        thumb.innerHTML = `
                            <img src="${imgUrl}" style="width:100%; height:90px; object-fit:cover; display:block;">
                            <button type="button" onclick="nttbsRemoveGalleryImage(this, '${imgUrl}')" style="position:absolute; top:4px; right:4px; background:var(--c-rose); color:#fff; border:none; border-radius:999px; width:22px; height:22px; cursor:pointer; font-weight:800; line-height:1;">&times;</button>
                        `;
                        previewWrap.appendChild(thumb);
                    }
                });

                hiddenInput.value = current.join(',');
            });

            frame.open();
        }

        function nttbsRemoveGalleryImage(btn, url) {
            const hiddenInput = document.getElementById('nttbs_gallery_images_input');
            let current = hiddenInput.value ? hiddenInput.value.split(',') : [];
            current = current.filter(item => item !== url);
            hiddenInput.value = current.join(',');
            btn.closest('.gallery-thumb-box').remove();
        }

        function nttbsOpenMapUploader() {
            const frame = wp.media({
                title: '<?php echo esc_js(__('Select Route Map Graphic', 'nahian-tour-travel-booking-solution')); ?>',
                button: { text: '<?php echo esc_js(__('Assign Image', 'nahian-tour-travel-booking-solution')); ?>' },
                multiple: false
            });

            frame.on('select', function() {
                const attachment = frame.state().get('selection').first().toJSON();
                const mapInput = document.getElementById('nttbs_map_image_input');
                if (mapInput) {
                    mapInput.value = attachment.url;
                }
            });

            frame.open();
        }
        </script>
    <?php endif; ?>

</div>
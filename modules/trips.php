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
.nttbs-trip-shell {
    display: flex;
    flex-direction: column;
    gap: 24px;
    animation: nttbsFadeIn 0.3s cubic-bezier(0.16, 1, 0.3, 1);
}
@keyframes nttbsFadeIn {
    from { opacity: 0; transform: translateY(6px); }
    to { opacity: 1; transform: translateY(0); }
}

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

.nttbs-badge {
    display: inline-block;
    padding: 3px 9px;
    border-radius: 999px;
    color: #ffffff;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.3px;
}

.nttbs-subnav-btn {
    width: 100%;
    text-align: left;
    background: transparent;
    border: none;
    padding: 11px 16px;
    border-radius: 8px;
    font-size: 13.5px;
    font-weight: 700;
    color: var(--nttbs-text-muted);
    cursor: pointer;
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
    display: flex;
    align-items: center;
    gap: 10px;
}
.nttbs-subnav-btn:hover {
    background: var(--nttbs-bg-hover);
    color: var(--nttbs-text-heading);
    transform: translateX(2px);
}
.nttbs-subnav-btn.active {
    background: var(--nttbs-primary-subtle);
    color: var(--nttbs-primary-dark);
    border-left: 3px solid var(--nttbs-primary);
    border-radius: 0 8px 8px 0;
}
.nttbs-card-bordered {
    background: var(--nttbs-bg-body);
    border: 1px solid var(--nttbs-border);
    border-radius: 10px;
    padding: 16px;
}
.nttbs-del-btn {
    background: var(--nttbs-rose-subtle);
    border: 1px solid var(--nttbs-rose-border);
    color: var(--nttbs-rose);
    border-radius: 6px;
    cursor: pointer;
    padding: 4px 8px;
    font-weight: 800;
    font-size: 14px;
    line-height: 1;
    transition: all 0.15s ease;
}
.nttbs-del-btn:hover {
    background: var(--nttbs-rose);
    color: #ffffff;
}
</style>

<div class="nttbs-trip-shell">

    <!-- Subnav Tabs -->
    <div class="nttbs-subnav-bar">
        <div class="nttbs-subnav-group">
            <a href="<?php echo esc_url(admin_url('admin.php?page=nttbs-core&view=trips&subtab=all')); ?>" 
               class="nttbs-pill-toggle <?php echo $current_subtab === 'all' ? 'active' : 'inactive'; ?>">
                <span class="nttbs-nav-icon"><?php echo nttbs_svg_icon('trips', 16, 16); ?></span>
                <span><?php esc_html_e('All Expeditions', 'nahian-tour-travel-booking-solution'); ?></span>
                <span class="nttbs-pill-badge"><?php echo count($trips); ?></span>
            </a>
            <a href="<?php echo esc_url(admin_url('admin.php?page=nttbs-core&view=trips&subtab=add')); ?>" 
               class="nttbs-pill-toggle <?php echo $current_subtab === 'add' ? 'active' : 'inactive'; ?>">
                <span>+</span>
                <span><?php echo $edit_trip ? sprintf(esc_html__('Edit Expedition #%d', 'nahian-tour-travel-booking-solution'), (int)$edit_trip['id']) : esc_html__('Deploy New Expedition', 'nahian-tour-travel-booking-solution'); ?></span>
            </a>
        </div>

        <?php if (isset($_GET['status'])): ?>
            <?php if ($_GET['status'] === 'created'): ?>
                <div class="nttbs-alert nttbs-alert-success"><?php esc_html_e('✓ Expedition Successfully Published', 'nahian-tour-travel-booking-solution'); ?></div>
            <?php elseif ($_GET['status'] === 'updated'): ?>
                <div class="nttbs-alert nttbs-alert-success"><?php esc_html_e('✓ Expedition Specifications Updated', 'nahian-tour-travel-booking-solution'); ?></div>
            <?php elseif ($_GET['status'] === 'deleted'): ?>
                <div class="nttbs-alert nttbs-alert-danger"><?php esc_html_e('✓ Expedition Dossier Removed', 'nahian-tour-travel-booking-solution'); ?></div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <?php if ($current_subtab === 'all'): ?>
        <!-- TAB 1: ALL EXPEDITIONS ROSTER -->
        <div class="nttbs-white-panel">
            <div class="nttbs-panel-header">
                <div style="display:flex; align-items:center; gap:8px;">
                    <span class="nttbs-nav-icon nttbs-icon-trips"><?php echo nttbs_svg_icon('trips', 18, 18); ?></span>
                    <span><?php printf(esc_html__('Active Commercial Expedition Catalog (%d)', 'nahian-tour-travel-booking-solution'), count($trips)); ?></span>
                </div>
                <a href="<?php echo esc_url(admin_url('admin.php?page=nttbs-core&view=trips&subtab=add')); ?>" class="nttbs-btn-primary" style="padding:7px 14px; font-size:12px; text-decoration:none;">
                    + <?php esc_html_e('Add Expedition', 'nahian-tour-travel-booking-solution'); ?>
                </a>
            </div>
            <p style="font-size:12px; color:var(--nttbs-text-muted); margin-bottom:18px; margin-top:-8px;"><?php echo esc_html($desc); ?></p>

            <div class="nttbs-table-responsive">
                <table class="nttbs-data-table">
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
                            <tr><td colspan="8" style="text-align:center; padding:44px 20px; color:var(--nttbs-text-muted);"><?php esc_html_e('No tour packages registered.', 'nahian-tour-travel-booking-solution'); ?></td></tr>
                        <?php else: foreach ($trips as $t): ?>
                            <tr>
                                <td>
                                    <code style="background:var(--nttbs-primary-subtle); color:var(--nttbs-primary-dark); border:1px solid var(--nttbs-primary-border); padding:4px 8px; border-radius:6px; font-weight:800; font-family:'JetBrains Mono', monospace; font-size:12px;">
                                        <?php echo esc_html(!empty($t['trip_code']) ? $t['trip_code'] : ('#TRP-' . $t['id'])); ?>
                                    </code>
                                </td>
                                <td>
                                    <strong style="font-size:13.5px; color:var(--nttbs-text-heading);"><?php echo esc_html($t['title']); ?></strong>
                                </td>
                                <td>
                                    <span class="nttbs-badge" style="background-color:var(--nttbs-primary);">
                                        <?php echo esc_html($t['destination_name'] ?? 'Region'); ?>
                                    </span>
                                    <span class="nttbs-badge" style="background-color:<?php echo esc_attr($t['difficulty_color'] ?? '#10b981'); ?>;">
                                        <?php echo esc_html($t['difficulty_name'] ?? 'Easy'); ?>
                                    </span>
                                </td>
                                <td>
                                    <strong><?php echo esc_html($t['duration_days']); ?>D / <?php echo esc_html($t['duration_nights']); ?>N</strong>
                                </td>
                                <td>
                                    <small style="color:var(--nttbs-text-muted); font-weight:600; font-family:'JetBrains Mono', monospace;"><?php echo esc_html($t['min_age'] ?? 0); ?> - <?php echo esc_html($t['max_age'] ?? 99); ?> <?php esc_html_e('Yrs', 'nahian-tour-travel-booking-solution'); ?></small>
                                </td>
                                <td>
                                    <strong style="color:var(--nttbs-primary-dark); font-size:13.5px; font-weight:800; font-family:'JetBrains Mono', monospace;">
                                        <?php echo esc_html($currency . ' ' . number_format_i18n((float)$t['price_adult'], 2)); ?>
                                    </strong>
                                </td>
                                <td>
                                    <span style="font-family:'JetBrains Mono', monospace;"><?php echo esc_html($t['max_seats']); ?> <?php esc_html_e('Pax', 'nahian-tour-travel-booking-solution'); ?></span>
                                </td>
                                <td style="text-align:right; white-space:nowrap;">
                                    <a href="<?php echo esc_url(admin_url('admin.php?page=nttbs-core&view=trips&subtab=add&edit_id=' . $t['id'])); ?>" 
                                       style="color:var(--nttbs-primary); font-weight:800; text-decoration:none; margin-right:12px;"><?php esc_html_e('Edit', 'nahian-tour-travel-booking-solution'); ?></a>
                                    <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=nttbs-core&view=trips&action=delete_trip&trip_id=' . $t['id']), 'nttbs_delete_trip_' . $t['id'])); ?>" 
                                       onclick="return confirm('<?php echo esc_js(__('Permanently delete this expedition and all corresponding departure schedules?', 'nahian-tour-travel-booking-solution')); ?>');" 
                                       style="color:var(--nttbs-rose); font-weight:800; text-decoration:none;"><?php esc_html_e('Delete', 'nahian-tour-travel-booking-solution'); ?></a>
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
            <div class="nttbs-white-panel" style="border-left:5px solid var(--nttbs-primary); margin-bottom:24px;">
                <div class="nttbs-field-row" style="margin-bottom:0;">
                    <label class="nttbs-field-label" style="font-size:12px; text-transform:uppercase; letter-spacing:0.5px;"><?php esc_html_e('Expedition / Tour Title *', 'nahian-tour-travel-booking-solution'); ?></label>
                    <input type="text" name="title" id="nttbs_trip_title" value="<?php echo esc_attr($edit_trip['title'] ?? ''); ?>" required class="nttbs-control-input" style="font-size:16px; font-weight:800; padding:12px 16px;" placeholder="<?php esc_attr_e('e.g. Red Sea Royal Catamaran Island Odyssey & Deep Reef Exploration', 'nahian-tour-travel-booking-solution'); ?>">
                </div>
            </div>

            <div style="display:grid; grid-template-columns: 260px 1fr; gap:24px; align-items:flex-start;">
                
                <!-- Left Vertical Tab Menu (10 Standard Tabs) -->
                <div class="nttbs-white-panel" style="padding:12px;">
                    <ul style="list-style:none; display:flex; flex-direction:column; gap:4px; padding:0; margin:0;" id="nttbs-trip-subnav">
                        <li><button type="button" class="nttbs-subnav-btn active" onclick="nttbsSwitchTab('general', this)">⚙️ <?php esc_html_e('General', 'nahian-tour-travel-booking-solution'); ?></button></li>
                        <li><button type="button" class="nttbs-subnav-btn" onclick="nttbsSwitchTab('overview', this)">📝 <?php esc_html_e('Overview & Info', 'nahian-tour-travel-booking-solution'); ?></button></li>
                        <li><button type="button" class="nttbs-subnav-btn" onclick="nttbsSwitchTab('itinerary', this)">📅 <?php esc_html_e('Itinerary', 'nahian-tour-travel-booking-solution'); ?></button></li>
                        <li><button type="button" class="nttbs-subnav-btn" onclick="nttbsSwitchTab('pricing', this)">💳 <?php esc_html_e('Date & Price', 'nahian-tour-travel-booking-solution'); ?></button></li>
                        <li><button type="button" class="nttbs-subnav-btn" onclick="nttbsSwitchTab('includes', this)">📋 <?php esc_html_e('Includes / Excludes', 'nahian-tour-travel-booking-solution'); ?></button></li>
                        <li><button type="button" class="nttbs-subnav-btn" onclick="nttbsSwitchTab('gallery', this)">🗺️ <?php esc_html_e('Map & Gallery', 'nahian-tour-travel-booking-solution'); ?></button></li>
                        <li><button type="button" class="nttbs-subnav-btn" onclick="nttbsSwitchTab('faqs', this)">❓ <?php esc_html_e('FAQs', 'nahian-tour-travel-booking-solution'); ?></button></li>
                        <li><button type="button" class="nttbs-subnav-btn" onclick="nttbsSwitchTab('revenue', this)">📈 <?php esc_html_e('Increase Revenue', 'nahian-tour-travel-booking-solution'); ?></button></li>
                        <li><button type="button" class="nttbs-subnav-btn" onclick="nttbsSwitchTab('extras', this)">🧰 <?php esc_html_e('Extra Services', 'nahian-tour-travel-booking-solution'); ?></button></li>
                        <li><button type="button" class="nttbs-subnav-btn" onclick="nttbsSwitchTab('advanced', this)">⚡ <?php esc_html_e('Advanced Settings', 'nahian-tour-travel-booking-solution'); ?></button></li>
                    </ul>

                    <div style="border-top:1px solid var(--nttbs-border); margin-top:16px; padding-top:16px;">
                        <button type="submit" name="nttbs_dispatch_trip_save" class="nttbs-btn-primary" style="width:100%; padding:12px; font-size:13px; font-weight:800;">
                            <?php echo $edit_trip ? esc_html__('Update Expedition', 'nahian-tour-travel-booking-solution') : esc_html__('Publish Expedition', 'nahian-tour-travel-booking-solution'); ?>
                        </button>
                    </div>
                </div>

                <!-- Right Content Stages -->
                <div>
                    
                    <!-- 1. GENERAL TAB -->
                    <div class="nttbs-stage-panel active" id="nttbs-stage-general">
                        <div class="nttbs-white-panel">
                            <div class="nttbs-panel-header"><span><?php esc_html_e('General Expedition Setup', 'nahian-tour-travel-booking-solution'); ?></span></div>
                            <div style="display:grid; grid-template-columns: repeat(2, 1fr); gap:18px;">
                                <div class="nttbs-field-row">
                                    <label class="nttbs-field-label"><?php esc_html_e('Trip Code', 'nahian-tour-travel-booking-solution'); ?></label>
                                    <input type="text" name="trip_code" value="<?php echo esc_attr($edit_trip['trip_code'] ?? ('NT-' . strtoupper(wp_generate_password(5, false, false)))); ?>" class="nttbs-control-input" style="font-family:'JetBrains Mono', monospace; font-weight:700;">
                                    <small style="color:var(--nttbs-text-muted); font-size:11.5px;"><?php esc_html_e('Unique identifier displayed across invoices & manifests.', 'nahian-tour-travel-booking-solution'); ?></small>
                                </div>
                                <div class="nttbs-field-row">
                                    <label class="nttbs-field-label"><?php esc_html_e('URL Slug', 'nahian-tour-travel-booking-solution'); ?></label>
                                    <input type="text" name="slug" value="<?php echo esc_attr($edit_trip['slug'] ?? ''); ?>" class="nttbs-control-input" placeholder="auto-generated-if-blank">
                                </div>
                            </div>

                            <div style="display:grid; grid-template-columns: repeat(2, 1fr); gap:18px;">
                                <div class="nttbs-field-row">
                                    <label class="nttbs-field-label"><?php esc_html_e('Duration (Days)', 'nahian-tour-travel-booking-solution'); ?></label>
                                    <input type="number" min="1" name="duration_days" value="<?php echo esc_attr($edit_trip['duration_days'] ?? 1); ?>" class="nttbs-control-input">
                                </div>
                                <div class="nttbs-field-row">
                                    <label class="nttbs-field-label"><?php esc_html_e('Duration (Nights)', 'nahian-tour-travel-booking-solution'); ?></label>
                                    <input type="number" min="0" name="duration_nights" value="<?php echo esc_attr($edit_trip['duration_nights'] ?? 0); ?>" class="nttbs-control-input">
                                </div>
                            </div>

                            <div style="display:grid; grid-template-columns: repeat(4, 1fr); gap:14px;">
                                <div class="nttbs-field-row">
                                    <label class="nttbs-field-label"><?php esc_html_e('Min. Age', 'nahian-tour-travel-booking-solution'); ?></label>
                                    <input type="number" min="0" name="min_age" value="<?php echo esc_attr($edit_trip['min_age'] ?? 0); ?>" class="nttbs-control-input">
                                </div>
                                <div class="nttbs-field-row">
                                    <label class="nttbs-field-label"><?php esc_html_e('Max. Age', 'nahian-tour-travel-booking-solution'); ?></label>
                                    <input type="number" max="120" name="max_age" value="<?php echo esc_attr($edit_trip['max_age'] ?? 99); ?>" class="nttbs-control-input">
                                </div>
                                <div class="nttbs-field-row">
                                    <label class="nttbs-field-label"><?php esc_html_e('Min. Travelers (Pax)', 'nahian-tour-travel-booking-solution'); ?></label>
                                    <input type="number" min="1" name="min_pax" value="<?php echo esc_attr($edit_trip['min_pax'] ?? 1); ?>" class="nttbs-control-input">
                                </div>
                                <div class="nttbs-field-row">
                                    <label class="nttbs-field-label"><?php esc_html_e('Total Capacity Limit', 'nahian-tour-travel-booking-solution'); ?></label>
                                    <input type="number" min="1" name="max_seats" value="<?php echo esc_attr($edit_trip['max_seats'] ?? 24); ?>" class="nttbs-control-input">
                                </div>
                            </div>

                            <div class="nttbs-field-row" style="margin-bottom:0;">
                                <label class="nttbs-field-label"><?php esc_html_e('Cut-off Time Window', 'nahian-tour-travel-booking-solution'); ?></label>
                                <input type="text" name="cutoff_time" value="<?php echo esc_attr($edit_trip['cutoff_time'] ?? '24 Hours Before Departure'); ?>" class="nttbs-control-input">
                                <small style="color:var(--nttbs-text-muted); font-size:11.5px;"><?php esc_html_e('Bookings are locked when this threshold is reached before departure.', 'nahian-tour-travel-booking-solution'); ?></small>
                            </div>
                        </div>
                    </div>

                    <!-- 2. OVERVIEW & INFO TAB -->
                    <div class="nttbs-stage-panel" id="nttbs-stage-overview" style="display:none;">
                        <div class="nttbs-white-panel">
                            <div class="nttbs-panel-header"><span><?php esc_html_e('Trip Overview & Description', 'nahian-tour-travel-booking-solution'); ?></span></div>
                            <div class="nttbs-field-row">
                                <label class="nttbs-field-label"><?php esc_html_e('Detailed Narrative', 'nahian-tour-travel-booking-solution'); ?></label>
                                <textarea name="description" rows="6" class="nttbs-control-input" placeholder="<?php esc_attr_e('Comprehensive tour description, geography, historical highlights, marine life...', 'nahian-tour-travel-booking-solution'); ?>"><?php echo esc_textarea($edit_trip['description'] ?? ''); ?></textarea>
                            </div>

                            <div class="nttbs-panel-header" style="border-top:1px solid var(--nttbs-border); padding-top:18px; margin-top:24px;">
                                <span><?php esc_html_e('Trip Highlights', 'nahian-tour-travel-booking-solution'); ?></span>
                            </div>
                            <div id="nttbs-highlights-wrap" style="display:flex; flex-direction:column; gap:10px; margin-bottom:14px;">
                                <?php if (!empty($trip_highlights)): foreach ($trip_highlights as $hl): ?>
                                    <div style="display:flex; gap:8px;">
                                        <input type="text" name="highlights[]" value="<?php echo esc_attr($hl); ?>" class="nttbs-control-input">
                                        <button type="button" onclick="this.parentElement.remove()" class="nttbs-del-btn" aria-label="<?php esc_attr_e('Remove highlight', 'nahian-tour-travel-booking-solution'); ?>">&times;</button>
                                    </div>
                                <?php endforeach; else: ?>
                                    <div style="display:flex; gap:8px;">
                                        <input type="text" name="highlights[]" value="Private yacht catamaran cruise through island archipelagos" class="nttbs-control-input">
                                        <button type="button" onclick="this.parentElement.remove()" class="nttbs-del-btn" aria-label="<?php esc_attr_e('Remove highlight', 'nahian-tour-travel-booking-solution'); ?>">&times;</button>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <button type="button" onclick="nttbsAddHighlightRow()" class="nttbs-btn-emerald">
                                + <?php esc_html_e('Add Trip Highlight', 'nahian-tour-travel-booking-solution'); ?>
                            </button>

                            <div class="nttbs-panel-header" style="border-top:1px solid var(--nttbs-border); padding-top:18px; margin-top:24px;">
                                <span><?php esc_html_e('Trip Key Facts Matrix', 'nahian-tour-travel-booking-solution'); ?></span>
                            </div>
                            <div class="nttbs-table-responsive" style="margin-bottom:14px;">
                                <table class="nttbs-data-table">
                                    <thead>
                                        <tr><th><?php esc_html_e('Fact Label', 'nahian-tour-travel-booking-solution'); ?></th><th><?php esc_html_e('Fact Value', 'nahian-tour-travel-booking-solution'); ?></th><th style="width:60px; text-align:center;"><?php esc_html_e('Action', 'nahian-tour-travel-booking-solution'); ?></th></tr>
                                    </thead>
                                    <tbody id="nttbs-facts-tbody">
                                        <?php if (!empty($trip_facts)): foreach ($trip_facts as $tf): ?>
                                            <tr>
                                                <td><input type="text" name="fact_key[]" value="<?php echo esc_attr($tf['title']); ?>" class="nttbs-control-input"></td>
                                                <td><input type="text" name="fact_val[]" value="<?php echo esc_attr($tf['value']); ?>" class="nttbs-control-input"></td>
                                                <td style="text-align:center;"><button type="button" onclick="this.closest('tr').remove()" class="nttbs-del-btn" aria-label="<?php esc_attr_e('Remove fact', 'nahian-tour-travel-booking-solution'); ?>">&times;</button></td>
                                            </tr>
                                        <?php endforeach; else: ?>
                                            <tr>
                                                <td><input type="text" name="fact_key[]" value="Transport" class="nttbs-control-input"></td>
                                                <td><input type="text" name="fact_val[]" value="Royal Catamaran Cruiser" class="nttbs-control-input"></td>
                                                <td style="text-align:center;"><button type="button" onclick="this.closest('tr').remove()" class="nttbs-del-btn" aria-label="<?php esc_attr_e('Remove fact', 'nahian-tour-travel-booking-solution'); ?>">&times;</button></td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                            <button type="button" onclick="nttbsAddFactRow()" class="nttbs-btn-primary" style="font-size:12px; padding:7px 14px; background:linear-gradient(135deg, var(--nttbs-teal), #0d9488);">
                                + <?php esc_html_e('Add Fact', 'nahian-tour-travel-booking-solution'); ?>
                            </button>
                        </div>
                    </div>

                    <!-- 3. ITINERARY TAB -->
                    <div class="nttbs-stage-panel" id="nttbs-stage-itinerary" style="display:none;">
                        <div class="nttbs-white-panel">
                            <div class="nttbs-panel-header"><span><?php esc_html_e('Itinerary Header Narrative', 'nahian-tour-travel-booking-solution'); ?></span></div>
                            <div class="nttbs-field-row">
                                <label class="nttbs-field-label"><?php esc_html_e('Section Title', 'nahian-tour-travel-booking-solution'); ?></label>
                                <input type="text" name="itinerary_title_head" value="<?php echo esc_attr($edit_trip['itinerary_title'] ?? 'Expedition Day-by-Day Itinerary'); ?>" class="nttbs-control-input">
                            </div>
                            <div class="nttbs-field-row">
                                <label class="nttbs-field-label"><?php esc_html_e('General Itinerary Description', 'nahian-tour-travel-booking-solution'); ?></label>
                                <textarea name="itinerary_desc_head" rows="2" class="nttbs-control-input"><?php echo esc_textarea($edit_trip['itinerary_desc'] ?? 'A comprehensive breakdown of daily schedules, anchorages, and excursions.'); ?></textarea>
                            </div>

                            <div class="nttbs-panel-header" style="border-top:1px solid var(--nttbs-border); padding-top:18px; margin-top:24px;">
                                <span><?php esc_html_e('Daily Procedural Schedule', 'nahian-tour-travel-booking-solution'); ?></span>
                            </div>
                            <div id="nttbs-itinerary-container" style="display:flex; flex-direction:column; gap:16px; margin-bottom:16px;">
                                <?php if (!empty($trip_itinerary)): foreach ($trip_itinerary as $it): ?>
                                    <div class="nttbs-card-bordered">
                                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                                            <strong style="color:var(--nttbs-primary-dark); font-size:13.5px;"><?php esc_html_e('Day', 'nahian-tour-travel-booking-solution'); ?> <span class="nttbs-day-num" style="font-family:'JetBrains Mono', monospace;"><?php echo esc_html($it['day']); ?></span></strong>
                                            <button type="button" onclick="this.closest('.nttbs-card-bordered').remove(); nttbsReindexDays();" class="nttbs-del-btn" aria-label="<?php esc_attr_e('Remove day', 'nahian-tour-travel-booking-solution'); ?>">&times;</button>
                                        </div>
                                        <input type="text" name="itinerary_day_title[]" value="<?php echo esc_attr($it['title']); ?>" class="nttbs-control-input" placeholder="Title: Port Boarding & Dolphin Bay Cruising" style="margin-bottom:8px; font-weight:700;">
                                        <textarea name="itinerary_day_desc[]" rows="2" class="nttbs-control-input" placeholder="Daily narrative..." style="margin-bottom:8px;"><?php echo esc_textarea($it['desc']); ?></textarea>
                                        <div style="display:grid; grid-template-columns: repeat(2, 1fr); gap:12px;">
                                            <input type="text" name="itinerary_day_meals[]" value="<?php echo esc_attr($it['meals'] ?? ''); ?>" class="nttbs-control-input" placeholder="Meals: Buffet Lunch, Dinner">
                                            <input type="text" name="itinerary_day_lodge[]" value="<?php echo esc_attr($it['lodge'] ?? ''); ?>" class="nttbs-control-input" placeholder="Accommodation: Onboard Yacht Cabin">
                                        </div>
                                    </div>
                                <?php endforeach; else: ?>
                                    <div class="nttbs-card-bordered">
                                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                                            <strong style="color:var(--nttbs-primary-dark); font-size:13.5px;"><?php esc_html_e('Day', 'nahian-tour-travel-booking-solution'); ?> <span class="nttbs-day-num" style="font-family:'JetBrains Mono', monospace;">1</span></strong>
                                            <button type="button" onclick="this.closest('.nttbs-card-bordered').remove(); nttbsReindexDays();" class="nttbs-del-btn" aria-label="<?php esc_attr_e('Remove day', 'nahian-tour-travel-booking-solution'); ?>">&times;</button>
                                        </div>
                                        <input type="text" name="itinerary_day_title[]" value="Marina Boarding & Coral Reef Cruising" class="nttbs-control-input" style="margin-bottom:8px; font-weight:700;">
                                        <textarea name="itinerary_day_desc[]" rows="2" class="nttbs-control-input" style="margin-bottom:8px;">Morning embarkation at marina, dolphin bay cruise, guided reef snorkeling.</textarea>
                                        <div style="display:grid; grid-template-columns: repeat(2, 1fr); gap:12px;">
                                            <input type="text" name="itinerary_day_meals[]" value="Buffet Lunch, Mineral Water" class="nttbs-control-input">
                                            <input type="text" name="itinerary_day_lodge[]" value="Onboard Yacht Luxury Cabin" class="nttbs-control-input">
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <button type="button" onclick="nttbsAddItineraryDay()" class="nttbs-btn-primary" style="font-size:12px; padding:9px 18px;">
                                + <?php esc_html_e('Add Itinerary Day', 'nahian-tour-travel-booking-solution'); ?>
                            </button>
                        </div>
                    </div>

                    <!-- 4. DATE & PRICING TAB -->
                    <div class="nttbs-stage-panel" id="nttbs-stage-pricing" style="display:none;">
                        <div class="nttbs-white-panel">
                            <div class="nttbs-panel-header"><span><?php esc_html_e('Baseline Rates & Taxonomies', 'nahian-tour-travel-booking-solution'); ?></span></div>
                            
                            <div style="display:grid; grid-template-columns: repeat(3, 1fr); gap:18px;">
                                <div class="nttbs-field-row">
                                    <label class="nttbs-field-label"><?php esc_html_e('Base Adult Rate', 'nahian-tour-travel-booking-solution'); ?> (<?php echo esc_html($currency); ?>) *</label>
                                    <input type="number" step="0.01" name="price_adult" value="<?php echo esc_attr($edit_trip['price_adult'] ?? '185.00'); ?>" required class="nttbs-control-input" style="font-weight:800; color:var(--nttbs-primary-dark); font-family:'JetBrains Mono', monospace;">
                                </div>
                                <div class="nttbs-field-row">
                                    <label class="nttbs-field-label"><?php esc_html_e('Base Child Rate', 'nahian-tour-travel-booking-solution'); ?> (<?php echo esc_html($currency); ?>)</label>
                                    <input type="number" step="0.01" name="price_child" value="<?php echo esc_attr($edit_trip['price_child'] ?? '110.00'); ?>" class="nttbs-control-input" style="font-family:'JetBrains Mono', monospace;">
                                </div>
                                <div class="nttbs-field-row">
                                    <label class="nttbs-field-label"><?php esc_html_e('Deposit Requirement (%)', 'nahian-tour-travel-booking-solution'); ?></label>
                                    <input type="number" min="1" max="100" name="deposit_percent" value="<?php echo esc_attr($edit_trip['deposit_percent'] ?? 50); ?>" class="nttbs-control-input" style="font-family:'JetBrains Mono', monospace;">
                                </div>
                            </div>

                            <div style="display:grid; grid-template-columns: repeat(2, 1fr); gap:18px;">
                                <div class="nttbs-field-row">
                                    <label class="nttbs-field-label"><?php esc_html_e('Destination', 'nahian-tour-travel-booking-solution'); ?></label>
                                    <select name="destination_id" class="nttbs-control-input">
                                        <option value="0">-- <?php esc_html_e('Select Destination', 'nahian-tour-travel-booking-solution'); ?> --</option>
                                        <?php foreach ($destinations as $d): ?>
                                            <option value="<?php echo esc_attr($d['id']); ?>" <?php selected($edit_trip['destination_id'] ?? 0, $d['id']); ?>><?php echo esc_html($d['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="nttbs-field-row">
                                    <label class="nttbs-field-label"><?php esc_html_e('Activity', 'nahian-tour-travel-booking-solution'); ?></label>
                                    <select name="activity_id" class="nttbs-control-input">
                                        <option value="0">-- <?php esc_html_e('Select Activity', 'nahian-tour-travel-booking-solution'); ?> --</option>
                                        <?php foreach ($activities as $a): ?>
                                            <option value="<?php echo esc_attr($a['id']); ?>" <?php selected($edit_trip['activity_id'] ?? 0, $a['id']); ?>><?php echo esc_html($a['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div style="display:grid; grid-template-columns: repeat(2, 1fr); gap:18px;">
                                <div class="nttbs-field-row">
                                    <label class="nttbs-field-label"><?php esc_html_e('Trip Classification', 'nahian-tour-travel-booking-solution'); ?></label>
                                    <select name="trip_type_id" class="nttbs-control-input">
                                        <option value="0">-- <?php esc_html_e('Select Type', 'nahian-tour-travel-booking-solution'); ?> --</option>
                                        <?php foreach ($trip_types as $tt): ?>
                                            <option value="<?php echo esc_attr($tt['id']); ?>" <?php selected($edit_trip['trip_type_id'] ?? 0, $tt['id']); ?>><?php echo esc_html($tt['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="nttbs-field-row">
                                    <label class="nttbs-field-label"><?php esc_html_e('Endurance Rating', 'nahian-tour-travel-booking-solution'); ?></label>
                                    <select name="difficulty_id" class="nttbs-control-input">
                                        <option value="0">-- <?php esc_html_e('Select Difficulty', 'nahian-tour-travel-booking-solution'); ?> --</option>
                                        <?php foreach ($difficulties as $df): ?>
                                            <option value="<?php echo esc_attr($df['id']); ?>" <?php selected($edit_trip['difficulty_id'] ?? 0, $df['id']); ?>><?php echo esc_html($df['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <!-- Multi-Package Tiers Repeater -->
                            <div class="nttbs-panel-header" style="border-top:1px solid var(--nttbs-border); padding-top:18px; margin-top:24px;">
                                <span><?php esc_html_e('Multi-Tier Packages (e.g. Standard, Deluxe Saloon, VIP Suite)', 'nahian-tour-travel-booking-solution'); ?></span>
                            </div>
                            <div id="nttbs-packages-wrap" style="display:flex; flex-direction:column; gap:14px; margin-bottom:14px;">
                                <?php if (!empty($trip_packages)): foreach ($trip_packages as $pkg): ?>
                                    <div class="nttbs-card-bordered">
                                        <div style="display:flex; justify-content:space-between; margin-bottom:8px;">
                                            <strong style="color:var(--nttbs-text-heading);"><?php esc_html_e('Package Tier', 'nahian-tour-travel-booking-solution'); ?></strong>
                                            <button type="button" onclick="this.closest('.nttbs-card-bordered').remove()" class="nttbs-del-btn" aria-label="<?php esc_attr_e('Remove package', 'nahian-tour-travel-booking-solution'); ?>">&times;</button>
                                        </div>
                                        <div style="display:grid; grid-template-columns: 2fr 1fr 1fr 1fr; gap:12px;">
                                            <input type="text" name="pkg_name[]" value="<?php echo esc_attr($pkg['name']); ?>" class="nttbs-control-input" placeholder="Package Name (e.g. VIP Private Saloon)">
                                            <input type="number" step="0.01" name="pkg_adult[]" value="<?php echo esc_attr($pkg['price_adult']); ?>" class="nttbs-control-input" placeholder="Adult Fare">
                                            <input type="number" step="0.01" name="pkg_child[]" value="<?php echo esc_attr($pkg['price_child']); ?>" class="nttbs-control-input" placeholder="Child Fare">
                                            <input type="number" name="pkg_cap[]" value="<?php echo esc_attr($pkg['capacity']); ?>" class="nttbs-control-input" placeholder="Max Seats">
                                        </div>
                                        <input type="text" name="pkg_desc[]" value="<?php echo esc_attr($pkg['desc']); ?>" class="nttbs-control-input" placeholder="Package tier details or inclusions..." style="margin-top:8px;">
                                    </div>
                                <?php endforeach; else: ?>
                                    <div class="nttbs-card-bordered">
                                        <div style="display:flex; justify-content:space-between; margin-bottom:8px;">
                                            <strong style="color:var(--nttbs-text-heading);"><?php esc_html_e('Standard Package', 'nahian-tour-travel-booking-solution'); ?></strong>
                                            <button type="button" onclick="this.closest('.nttbs-card-bordered').remove()" class="nttbs-del-btn" aria-label="<?php esc_attr_e('Remove package', 'nahian-tour-travel-booking-solution'); ?>">&times;</button>
                                        </div>
                                        <div style="display:grid; grid-template-columns: 2fr 1fr 1fr 1fr; gap:12px;">
                                            <input type="text" name="pkg_name[]" value="Standard Saloon Passage" class="nttbs-control-input">
                                            <input type="number" step="0.01" name="pkg_adult[]" value="185.00" class="nttbs-control-input">
                                            <input type="number" step="0.01" name="pkg_child[]" value="110.00" class="nttbs-control-input">
                                            <input type="number" name="pkg_cap[]" value="24" class="nttbs-control-input">
                                        </div>
                                        <input type="text" name="pkg_desc[]" value="Includes full standard catamaran berth access and meals." class="nttbs-control-input" style="margin-top:8px;">
                                    </div>
                                <?php endif; ?>
                            </div>
                            <button type="button" onclick="nttbsAddPackageTier()" class="nttbs-btn-primary" style="font-size:12px; padding:7px 16px;">
                                + <?php esc_html_e('Add New Package Tier', 'nahian-tour-travel-booking-solution'); ?>
                            </button>
                        </div>
                    </div>

                    <!-- 5. INCLUDES / EXCLUDES TAB -->
                    <div class="nttbs-stage-panel" id="nttbs-stage-includes" style="display:none;">
                        <div class="nttbs-white-panel">
                            <div class="nttbs-panel-header"><span><?php esc_html_e('Inclusions & Exclusions Header', 'nahian-tour-travel-booking-solution'); ?></span></div>
                            <div class="nttbs-field-row">
                                <label class="nttbs-field-label"><?php esc_html_e('Section Heading', 'nahian-tour-travel-booking-solution'); ?></label>
                                <input type="text" name="inc_exc_title" value="<?php echo esc_attr($edit_trip['inc_exc_title'] ?? 'Expedition Inclusions & Exclusions'); ?>" class="nttbs-control-input">
                            </div>

                            <div style="display:grid; grid-template-columns: repeat(2, 1fr); gap:18px;">
                                <div class="nttbs-field-row" style="margin-bottom:0;">
                                    <label class="nttbs-field-label" style="color:var(--nttbs-emerald);"><?php esc_html_e('✓ Services Included in Fare', 'nahian-tour-travel-booking-solution'); ?></label>
                                    <textarea name="inclusions" rows="8" class="nttbs-control-input" placeholder="e.g. Catamaran stay, buffet dining, snorkeling gear, marine preservation permits, bottled water..."><?php echo esc_textarea($edit_trip['inclusions'] ?? ''); ?></textarea>
                                </div>
                                <div class="nttbs-field-row" style="margin-bottom:0;">
                                    <label class="nttbs-field-label" style="color:var(--nttbs-rose);"><?php esc_html_e('✕ Services Excluded', 'nahian-tour-travel-booking-solution'); ?></label>
                                    <textarea name="exclusions" rows="8" class="nttbs-control-input" placeholder="e.g. Flight transport, personal dive gear insurance, gratuities, alcoholic beverages..."><?php echo esc_textarea($edit_trip['exclusions'] ?? ''); ?></textarea>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 6. MAP & GALLERY TAB -->
                    <div class="nttbs-stage-panel" id="nttbs-stage-gallery" style="display:none;">
                        <div class="nttbs-white-panel">
                            <div class="nttbs-panel-header"><span><?php esc_html_e('High-Resolution Media Gallery', 'nahian-tour-travel-booking-solution'); ?></span></div>
                            <div class="nttbs-field-row">
                                <input type="hidden" name="gallery_images" id="nttbs_gallery_images_input" value="<?php echo esc_attr($edit_trip['gallery_images'] ?? ''); ?>">
                                <button type="button" class="nttbs-btn-primary" onclick="nttbsOpenGalleryUploader()" style="padding:9px 18px; font-size:12px; margin-bottom:14px;">
                                    + <?php esc_html_e('Add Images from Media Library', 'nahian-tour-travel-booking-solution'); ?>
                                </button>
                                <div id="nttbs-gallery-preview-wrap" style="display:grid; grid-template-columns: repeat(5, 1fr); gap:14px;">
                                    <?php if (!empty($gallery_array)): foreach ($gallery_array as $img_url): if (trim($img_url)): ?>
                                        <div class="nttbs-gallery-thumb-box" style="position:relative; border-radius:10px; overflow:hidden; border:1px solid var(--nttbs-border); box-shadow:var(--nttbs-shadow-xs);">
                                            <img src="<?php echo esc_url($img_url); ?>" style="width:100%; height:95px; object-fit:cover; display:block;">
                                            <button type="button" onclick="nttbsRemoveGalleryImage(this, '<?php echo esc_js($img_url); ?>')" style="position:absolute; top:6px; right:6px; background:var(--nttbs-rose); color:#fff; border:none; border-radius:999px; width:22px; height:22px; cursor:pointer; font-weight:800; line-height:1;" aria-label="<?php esc_attr_e('Remove image', 'nahian-tour-travel-booking-solution'); ?>">&times;</button>
                                        </div>
                                    <?php endif; endforeach; endif; ?>
                                </div>
                            </div>

                            <div class="nttbs-panel-header" style="border-top:1px solid var(--nttbs-border); padding-top:18px; margin-top:24px;">
                                <span><?php esc_html_e('Video & Interactive Maps', 'nahian-tour-travel-booking-solution'); ?></span>
                            </div>
                            <div class="nttbs-field-row">
                                <label class="nttbs-field-label"><?php esc_html_e('Video Showcase Links (YouTube / Vimeo / MP4)', 'nahian-tour-travel-booking-solution'); ?></label>
                                <textarea name="video_gallery" rows="2" class="nttbs-control-input" placeholder="https://www.youtube.com/watch?v=..."><?php echo esc_textarea($edit_trip['video_gallery'] ?? ''); ?></textarea>
                            </div>
                            <div class="nttbs-field-row">
                                <label class="nttbs-field-label"><?php esc_html_e('Interactive Map Embed (Google Maps iframe)', 'nahian-tour-travel-booking-solution'); ?></label>
                                <textarea name="map_iframe" rows="3" class="nttbs-control-input" placeholder="<iframe src='https://www.google.com/maps/embed?...'></iframe>"><?php echo esc_textarea($edit_trip['map_iframe'] ?? ''); ?></textarea>
                            </div>
                            <div class="nttbs-field-row" style="margin-bottom:0;">
                                <label class="nttbs-field-label"><?php esc_html_e('Static Route Map Image', 'nahian-tour-travel-booking-solution'); ?></label>
                                <div style="display:flex; gap:10px;">
                                    <input type="text" name="map_image" id="nttbs_map_image_input" value="<?php echo esc_attr($edit_trip['map_image'] ?? ''); ?>" class="nttbs-control-input" placeholder="Image URL">
                                    <button type="button" class="nttbs-btn-primary" onclick="nttbsOpenMapUploader()" style="padding:0 18px; font-size:12px; white-space:nowrap;"><?php esc_html_e('Upload Graphic', 'nahian-tour-travel-booking-solution'); ?></button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 7. FAQS TAB -->
                    <div class="nttbs-stage-panel" id="nttbs-stage-faqs" style="display:none;">
                        <div class="nttbs-white-panel">
                            <div class="nttbs-panel-header"><span><?php esc_html_e('Frequently Asked Questions', 'nahian-tour-travel-booking-solution'); ?></span></div>
                            <div id="nttbs-faqs-container" style="display:flex; flex-direction:column; gap:14px; margin-bottom:14px;">
                                <?php if (!empty($trip_faqs)): foreach ($trip_faqs as $fq): ?>
                                    <div class="nttbs-card-bordered">
                                        <div style="display:flex; justify-content:space-between; margin-bottom:8px;">
                                            <strong style="color:var(--nttbs-text-heading);"><?php esc_html_e('Question', 'nahian-tour-travel-booking-solution'); ?></strong>
                                            <button type="button" onclick="this.closest('.nttbs-card-bordered').remove()" class="nttbs-del-btn" aria-label="<?php esc_attr_e('Remove FAQ', 'nahian-tour-travel-booking-solution'); ?>">&times;</button>
                                        </div>
                                        <input type="text" name="faq_q[]" value="<?php echo esc_attr($fq['q']); ?>" class="nttbs-control-input" style="margin-bottom:8px;">
                                        <textarea name="faq_a[]" rows="2" class="nttbs-control-input"><?php echo esc_textarea($fq['a']); ?></textarea>
                                    </div>
                                <?php endforeach; else: ?>
                                    <div class="nttbs-card-bordered">
                                        <div style="display:flex; justify-content:space-between; margin-bottom:8px;">
                                            <strong style="color:var(--nttbs-text-heading);"><?php esc_html_e('Question', 'nahian-tour-travel-booking-solution'); ?></strong>
                                            <button type="button" onclick="this.closest('.nttbs-card-bordered').remove()" class="nttbs-del-btn" aria-label="<?php esc_attr_e('Remove FAQ', 'nahian-tour-travel-booking-solution'); ?>">&times;</button>
                                        </div>
                                        <input type="text" name="faq_q[]" value="What should I pack for the catamaran voyage?" class="nttbs-control-input" style="margin-bottom:8px;">
                                        <textarea name="faq_a[]" rows="2" class="nttbs-control-input">Swimwear, reef-safe sunscreen, sunglasses, and personal identification passport.</textarea>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <button type="button" onclick="nttbsAddFAQRow()" class="nttbs-btn-primary" style="font-size:12px; padding:7px 16px;">
                                + <?php esc_html_e('Add FAQ Item', 'nahian-tour-travel-booking-solution'); ?>
                            </button>
                        </div>
                    </div>

                    <!-- 8. INCREASE REVENUE TAB (GROUP DISCOUNTS) -->
                    <div class="nttbs-stage-panel" id="nttbs-stage-revenue" style="display:none;">
                        <div class="nttbs-white-panel">
                            <div class="nttbs-panel-header"><span><?php esc_html_e('Automated Volume Group Discounts', 'nahian-tour-travel-booking-solution'); ?></span></div>
                            <p style="font-size:12px; color:var(--nttbs-text-muted); margin-bottom:16px; margin-top:-8px;"><?php esc_html_e('Incentivize larger private bookings with volume-based tier reductions.', 'nahian-tour-travel-booking-solution'); ?></p>
                            
                            <div class="nttbs-table-responsive" style="margin-bottom:14px;">
                                <table class="nttbs-data-table">
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
                                                <td><input type="number" min="2" name="gd_pax[]" value="<?php echo esc_attr($gd['min_pax']); ?>" class="nttbs-control-input"></td>
                                                <td><input type="number" step="0.01" name="gd_discount[]" value="<?php echo esc_attr($gd['discount']); ?>" class="nttbs-control-input"></td>
                                                <td>
                                                    <select name="gd_type[]" class="nttbs-control-input">
                                                        <option value="percentage" <?php selected($gd['type'], 'percentage'); ?>><?php esc_html_e('Percentage (%)', 'nahian-tour-travel-booking-solution'); ?></option>
                                                        <option value="fixed" <?php selected($gd['type'], 'fixed'); ?>><?php esc_html_e('Fixed Amount', 'nahian-tour-travel-booking-solution'); ?></option>
                                                    </select>
                                                </td>
                                                <td style="text-align:center;"><button type="button" onclick="this.closest('tr').remove()" class="nttbs-del-btn" aria-label="<?php esc_attr_e('Remove tier', 'nahian-tour-travel-booking-solution'); ?>">&times;</button></td>
                                            </tr>
                                        <?php endforeach; else: ?>
                                            <tr>
                                                <td><input type="number" min="2" name="gd_pax[]" value="6" class="nttbs-control-input"></td>
                                                <td><input type="number" step="0.01" name="gd_discount[]" value="10.00" class="nttbs-control-input"></td>
                                                <td>
                                                    <select name="gd_type[]" class="nttbs-control-input">
                                                        <option value="percentage"><?php esc_html_e('Percentage (%)', 'nahian-tour-travel-booking-solution'); ?></option>
                                                        <option value="fixed"><?php esc_html_e('Fixed Amount', 'nahian-tour-travel-booking-solution'); ?></option>
                                                    </select>
                                                </td>
                                                <td style="text-align:center;"><button type="button" onclick="this.closest('tr').remove()" class="nttbs-del-btn" aria-label="<?php esc_attr_e('Remove tier', 'nahian-tour-travel-booking-solution'); ?>">&times;</button></td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                            <button type="button" onclick="nttbsAddGroupDiscountRow()" class="nttbs-btn-emerald">
                                + <?php esc_html_e('Add Volume Discount Tier', 'nahian-tour-travel-booking-solution'); ?>
                            </button>
                        </div>
                    </div>

                    <!-- 9. EXTRA SERVICES / ADD-ONS TAB -->
                    <div class="nttbs-stage-panel" id="nttbs-stage-extras" style="display:none;">
                        <div class="nttbs-white-panel">
                            <div class="nttbs-panel-header"><span><?php esc_html_e('Optional Upsell Add-ons & Extra Services', 'nahian-tour-travel-booking-solution'); ?></span></div>
                            <p style="font-size:12px; color:var(--nttbs-text-muted); margin-bottom:16px; margin-top:-8px;"><?php esc_html_e('Add services bookable alongside standard seats (e.g. VIP Transfer, Dive Gear Rental).', 'nahian-tour-travel-booking-solution'); ?></p>

                            <div class="nttbs-table-responsive" style="margin-bottom:14px;">
                                <table class="nttbs-data-table">
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
                                                <td><input type="text" name="extra_title[]" value="<?php echo esc_attr($es['title']); ?>" class="nttbs-control-input"></td>
                                                <td><input type="number" step="0.01" name="extra_price[]" value="<?php echo esc_attr($es['price']); ?>" class="nttbs-control-input" style="font-family:'JetBrains Mono', monospace;"></td>
                                                <td>
                                                    <select name="extra_type[]" class="nttbs-control-input">
                                                        <option value="per_pax" <?php selected($es['type'], 'per_pax'); ?>><?php esc_html_e('Per Passenger', 'nahian-tour-travel-booking-solution'); ?></option>
                                                        <option value="per_booking" <?php selected($es['type'], 'per_booking'); ?>><?php esc_html_e('Per Booking Order', 'nahian-tour-travel-booking-solution'); ?></option>
                                                    </select>
                                                </td>
                                                <td style="text-align:center;"><button type="button" onclick="this.closest('tr').remove()" class="nttbs-del-btn" aria-label="<?php esc_attr_e('Remove service', 'nahian-tour-travel-booking-solution'); ?>">&times;</button></td>
                                            </tr>
                                        <?php endforeach; else: ?>
                                            <tr>
                                                <td><input type="text" name="extra_title[]" value="Private Hotel Harbor Transfer" class="nttbs-control-input"></td>
                                                <td><input type="number" step="0.01" name="extra_price[]" value="25.00" class="nttbs-control-input" style="font-family:'JetBrains Mono', monospace;"></td>
                                                <td>
                                                    <select name="extra_type[]" class="nttbs-control-input">
                                                        <option value="per_booking"><?php esc_html_e('Per Booking Order', 'nahian-tour-travel-booking-solution'); ?></option>
                                                        <option value="per_pax"><?php esc_html_e('Per Passenger', 'nahian-tour-travel-booking-solution'); ?></option>
                                                    </select>
                                                </td>
                                                <td style="text-align:center;"><button type="button" onclick="this.closest('tr').remove()" class="nttbs-del-btn" aria-label="<?php esc_attr_e('Remove service', 'nahian-tour-travel-booking-solution'); ?>">&times;</button></td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                            <button type="button" onclick="nttbsAddExtraRow()" class="nttbs-btn-primary" style="font-size:12px; padding:7px 16px; background:linear-gradient(135deg, var(--nttbs-teal), #0d9488);">
                                + <?php esc_html_e('Add Extra Service Upsell', 'nahian-tour-travel-booking-solution'); ?>
                            </button>
                        </div>
                    </div>

                    <!-- 10. ADVANCED SETTINGS TAB -->
                    <div class="nttbs-stage-panel" id="nttbs-stage-advanced" style="display:none;">
                        <div class="nttbs-white-panel">
                            <div class="nttbs-panel-header"><span><?php esc_html_e('Advanced Operational Constraints', 'nahian-tour-travel-booking-solution'); ?></span></div>
                            
                            <div class="nttbs-field-row" style="background:var(--nttbs-bg-body); padding:18px; border-radius:10px; border:1px solid var(--nttbs-border); margin-bottom:18px;">
                                <label style="cursor:pointer; display:flex; align-items:center; gap:10px; font-size:13px; font-weight:700; color:var(--nttbs-text-heading);">
                                    <input type="checkbox" name="adv_allow_partial" value="1" <?php checked($adv_meta['allow_partial'] ?? 1, 1); ?>>
                                    <?php esc_html_e('Allow Partial Deposit Bookings (Uses configured deposit percentage)', 'nahian-tour-travel-booking-solution'); ?>
                                </label>
                                <label style="cursor:pointer; display:flex; align-items:center; gap:10px; font-size:13px; font-weight:700; color:var(--nttbs-text-heading); margin-top:14px;">
                                    <input type="checkbox" name="adv_require_id" value="1" <?php checked($adv_meta['require_id'] ?? 0, 1); ?>>
                                    <?php esc_html_e('Mandatory Passport / National ID on Booking', 'nahian-tour-travel-booking-solution'); ?>
                                </label>
                            </div>

                            <div class="nttbs-field-row">
                                <label class="nttbs-field-label"><?php esc_html_e('Custom Ribbons & Promotional Badges', 'nahian-tour-travel-booking-solution'); ?></label>
                                <input type="text" name="adv_badge_custom" value="<?php echo esc_attr($adv_meta['badge_custom'] ?? 'FEATURED EXPEDITION'); ?>" class="nttbs-control-input" placeholder="e.g. BEST SELLER, EXCLUSIVE">
                            </div>

                            <div class="nttbs-field-row" style="margin-bottom:0;">
                                <label class="nttbs-field-label"><?php esc_html_e('Affiliate / External Booking Redirection URL (Optional)', 'nahian-tour-travel-booking-solution'); ?></label>
                                <input type="url" name="adv_external_link" value="<?php echo esc_attr($adv_meta['external_link'] ?? ''); ?>" class="nttbs-control-input" placeholder="https://...">
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </form>

        <script>
        function nttbsSwitchTab(stageKey, btn) {
            document.querySelectorAll('.nttbs-stage-panel').forEach(el => el.style.display = 'none');
            document.querySelectorAll('.nttbs-subnav-btn').forEach(el => el.classList.remove('active'));
            
            const target = document.getElementById('nttbs-stage-' + stageKey);
            if (target) target.style.display = 'block';
            btn.classList.add('active');
        }

        function nttbsAddHighlightRow() {
            const wrap = document.getElementById('nttbs-highlights-wrap');
            if (!wrap) return;
            const div = document.createElement('div');
            div.style.cssText = 'display:flex; gap:8px;';
            div.innerHTML = `
                <input type="text" name="highlights[]" class="nttbs-control-input" placeholder="<?php echo esc_js(__('New highlight point', 'nahian-tour-travel-booking-solution')); ?>">
                <button type="button" onclick="this.parentElement.remove()" class="nttbs-del-btn" aria-label="<?php echo esc_js(__('Remove highlight', 'nahian-tour-travel-booking-solution')); ?>">&times;</button>
            `;
            wrap.appendChild(div);
        }

        function nttbsAddFactRow() {
            const tbody = document.getElementById('nttbs-facts-tbody');
            if (!tbody) return;
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td><input type="text" name="fact_key[]" class="nttbs-control-input" placeholder="<?php echo esc_js(__('Fact Label', 'nahian-tour-travel-booking-solution')); ?>"></td>
                <td><input type="text" name="fact_val[]" class="nttbs-control-input" placeholder="<?php echo esc_js(__('Fact Value', 'nahian-tour-travel-booking-solution')); ?>"></td>
                <td style="text-align:center;"><button type="button" onclick="this.closest('tr').remove()" class="nttbs-del-btn" aria-label="<?php echo esc_js(__('Remove fact', 'nahian-tour-travel-booking-solution')); ?>">&times;</button></td>
            `;
            tbody.appendChild(tr);
        }

        function nttbsAddItineraryDay() {
            const container = document.getElementById('nttbs-itinerary-container');
            if (!container) return;
            const count = container.querySelectorAll('.nttbs-card-bordered').length + 1;
            const div = document.createElement('div');
            div.className = 'nttbs-card-bordered';
            div.innerHTML = `
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                    <strong style="color:var(--nttbs-primary-dark); font-size:13.5px;"><?php echo esc_js(__('Day', 'nahian-tour-travel-booking-solution')); ?> <span class="nttbs-day-num" style="font-family:'JetBrains Mono', monospace;">${count}</span></strong>
                    <button type="button" onclick="this.closest('.nttbs-card-bordered').remove(); nttbsReindexDays();" class="nttbs-del-btn" aria-label="<?php echo esc_js(__('Remove day', 'nahian-tour-travel-booking-solution')); ?>">&times;</button>
                </div>
                <input type="text" name="itinerary_day_title[]" class="nttbs-control-input" placeholder="<?php echo esc_js(__('Daily Milestone Title', 'nahian-tour-travel-booking-solution')); ?>" style="margin-bottom:8px; font-weight:700;">
                <textarea name="itinerary_day_desc[]" rows="2" class="nttbs-control-input" placeholder="<?php echo esc_js(__('Daily narrative...', 'nahian-tour-travel-booking-solution')); ?>" style="margin-bottom:8px;"></textarea>
                <div style="display:grid; grid-template-columns: repeat(2, 1fr); gap:12px;">
                    <input type="text" name="itinerary_day_meals[]" class="nttbs-control-input" placeholder="<?php echo esc_js(__('Meals: Breakfast, Lunch, Dinner', 'nahian-tour-travel-booking-solution')); ?>">
                    <input type="text" name="itinerary_day_lodge[]" class="nttbs-control-input" placeholder="<?php echo esc_js(__('Accommodation / Lodging', 'nahian-tour-travel-booking-solution')); ?>">
                </div>
            `;
            container.appendChild(div);
        }

        function nttbsReindexDays() {
            document.querySelectorAll('#nttbs-itinerary-container .nttbs-day-num').forEach((el, index) => {
                el.textContent = index + 1;
            });
        }

        function nttbsAddPackageTier() {
            const wrap = document.getElementById('nttbs-packages-wrap');
            if (!wrap) return;
            const div = document.createElement('div');
            div.className = 'nttbs-card-bordered';
            div.innerHTML = `
                <div style="display:flex; justify-content:space-between; margin-bottom:8px;">
                    <strong style="color:var(--nttbs-text-heading);"><?php echo esc_js(__('Package Tier', 'nahian-tour-travel-booking-solution')); ?></strong>
                    <button type="button" onclick="this.closest('.nttbs-card-bordered').remove()" class="nttbs-del-btn" aria-label="<?php echo esc_js(__('Remove package', 'nahian-tour-travel-booking-solution')); ?>">&times;</button>
                </div>
                <div style="display:grid; grid-template-columns: 2fr 1fr 1fr 1fr; gap:12px;">
                    <input type="text" name="pkg_name[]" class="nttbs-control-input" placeholder="<?php echo esc_js(__('Package Title', 'nahian-tour-travel-booking-solution')); ?>">
                    <input type="number" step="0.01" name="pkg_adult[]" class="nttbs-control-input" placeholder="<?php echo esc_js(__('Adult Fare', 'nahian-tour-travel-booking-solution')); ?>">
                    <input type="number" step="0.01" name="pkg_child[]" class="nttbs-control-input" placeholder="<?php echo esc_js(__('Child Fare', 'nahian-tour-travel-booking-solution')); ?>">
                    <input type="number" name="pkg_cap[]" class="nttbs-control-input" placeholder="<?php echo esc_js(__('Capacity', 'nahian-tour-travel-booking-solution')); ?>">
                </div>
                <input type="text" name="pkg_desc[]" class="nttbs-control-input" placeholder="<?php echo esc_js(__('Package specifications...', 'nahian-tour-travel-booking-solution')); ?>" style="margin-top:8px;">
            `;
            wrap.appendChild(div);
        }

        function nttbsAddFAQRow() {
            const container = document.getElementById('nttbs-faqs-container');
            if (!container) return;
            const div = document.createElement('div');
            div.className = 'nttbs-card-bordered';
            div.innerHTML = `
                <div style="display:flex; justify-content:space-between; margin-bottom:8px;">
                    <strong style="color:var(--nttbs-text-heading);"><?php echo esc_js(__('Question', 'nahian-tour-travel-booking-solution')); ?></strong>
                    <button type="button" onclick="this.closest('.nttbs-card-bordered').remove()" class="nttbs-del-btn" aria-label="<?php echo esc_js(__('Remove FAQ', 'nahian-tour-travel-booking-solution')); ?>">&times;</button>
                </div>
                <input type="text" name="faq_q[]" class="nttbs-control-input" placeholder="<?php echo esc_js(__('Question', 'nahian-tour-travel-booking-solution')); ?>" style="margin-bottom:8px;">
                <textarea name="faq_a[]" rows="2" class="nttbs-control-input" placeholder="<?php echo esc_js(__('Answer response...', 'nahian-tour-travel-booking-solution')); ?>"></textarea>
            `;
            container.appendChild(div);
        }

        function nttbsAddGroupDiscountRow() {
            const tbody = document.getElementById('nttbs-gd-tbody');
            if (!tbody) return;
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td><input type="number" min="2" name="gd_pax[]" class="nttbs-control-input" placeholder="<?php echo esc_js(__('Min Pax', 'nahian-tour-travel-booking-solution')); ?>"></td>
                <td><input type="number" step="0.01" name="gd_discount[]" class="nttbs-control-input" placeholder="<?php echo esc_js(__('Discount', 'nahian-tour-travel-booking-solution')); ?>"></td>
                <td>
                    <select name="gd_type[]" class="nttbs-control-input">
                        <option value="percentage"><?php echo esc_js(__('Percentage (%)', 'nahian-tour-travel-booking-solution')); ?></option>
                        <option value="fixed"><?php echo esc_js(__('Fixed Amount', 'nahian-tour-travel-booking-solution')); ?></option>
                    </select>
                </td>
                <td style="text-align:center;"><button type="button" onclick="this.closest('tr').remove()" class="nttbs-del-btn" aria-label="<?php echo esc_js(__('Remove tier', 'nahian-tour-travel-booking-solution')); ?>">&times;</button></td>
            `;
            tbody.appendChild(tr);
        }

        function nttbsAddExtraRow() {
            const tbody = document.getElementById('nttbs-extras-tbody');
            if (!tbody) return;
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td><input type="text" name="extra_title[]" class="nttbs-control-input" placeholder="<?php echo esc_js(__('Service Name', 'nahian-tour-travel-booking-solution')); ?>"></td>
                <td><input type="number" step="0.01" name="extra_price[]" class="nttbs-control-input" placeholder="<?php echo esc_js(__('Rate', 'nahian-tour-travel-booking-solution')); ?>"></td>
                <td>
                    <select name="extra_type[]" class="nttbs-control-input">
                        <option value="per_pax"><?php echo esc_js(__('Per Passenger', 'nahian-tour-travel-booking-solution')); ?></option>
                        <option value="per_booking"><?php echo esc_js(__('Per Booking Order', 'nahian-tour-travel-booking-solution')); ?></option>
                    </select>
                </td>
                <td style="text-align:center;"><button type="button" onclick="this.closest('tr').remove()" class="nttbs-del-btn" aria-label="<?php echo esc_js(__('Remove service', 'nahian-tour-travel-booking-solution')); ?>">&times;</button></td>
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
                        thumb.className = 'nttbs-gallery-thumb-box';
                        thumb.style.cssText = 'position:relative; border-radius:10px; overflow:hidden; border:1px solid var(--nttbs-border); box-shadow:var(--nttbs-shadow-xs);';
                        thumb.innerHTML = `
                            <img src="${imgUrl}" style="width:100%; height:95px; object-fit:cover; display:block;">
                            <button type="button" onclick="nttbsRemoveGalleryImage(this, '${imgUrl}')" style="position:absolute; top:6px; right:6px; background:var(--nttbs-rose); color:#fff; border:none; border-radius:999px; width:22px; height:22px; cursor:pointer; font-weight:800; line-height:1;" aria-label="<?php echo esc_js(__('Remove image', 'nahian-tour-travel-booking-solution')); ?>">&times;</button>
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
            btn.closest('.nttbs-gallery-thumb-box').remove();
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
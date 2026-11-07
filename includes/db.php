<?php
if (!defined('ABSPATH')) {
    exit;
}

/* ==========================================================================
   1. COMPREHENSIVE DATABASE SCHEMA BUILDER
   ========================================================================== */

function nttbs_install_complete_database() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();

    $table_packages     = $wpdb->prefix . 'nttbs_trips';
    $table_departures   = $wpdb->prefix . 'nttbs_departures';
    $table_seat_roster  = $wpdb->prefix . 'nttbs_seats';
    $table_bookings     = $wpdb->prefix . 'nttbs_bookings';
    $table_customers    = $wpdb->prefix . 'nttbs_customers';
    $table_transactions = $wpdb->prefix . 'nttbs_transactions';
    $table_taxonomies   = $wpdb->prefix . 'nttbs_taxonomies';
    $table_price_cats   = $wpdb->prefix . 'nttbs_price_categories';
    $table_coupons      = $wpdb->prefix . 'nttbs_coupons';
    $table_logs         = $wpdb->prefix . 'nttbs_audit_logs';

    $sql_packages = "CREATE TABLE $table_packages (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        title varchar(255) NOT NULL,
        slug varchar(255) NOT NULL,
        trip_code varchar(100) NOT NULL DEFAULT '',
        destination_id bigint(20) unsigned NOT NULL DEFAULT 0,
        activity_id bigint(20) unsigned NOT NULL DEFAULT 0,
        trip_type_id bigint(20) unsigned NOT NULL DEFAULT 0,
        difficulty_id bigint(20) unsigned NOT NULL DEFAULT 0,
        duration_days int(5) NOT NULL DEFAULT 1,
        duration_nights int(5) NOT NULL DEFAULT 0,
        min_age int(3) NOT NULL DEFAULT 0,
        max_age int(3) NOT NULL DEFAULT 99,
        min_pax int(5) NOT NULL DEFAULT 1,
        max_seats int(11) NOT NULL DEFAULT 24,
        cutoff_time varchar(100) NOT NULL DEFAULT '24 Hours Before',
        price_adult decimal(12,2) NOT NULL DEFAULT 0.00,
        price_child decimal(12,2) NOT NULL DEFAULT 0.00,
        deposit_percent int(3) NOT NULL DEFAULT 100,
        packages_json longtext,
        description longtext,
        highlights longtext,
        trip_facts longtext,
        itinerary_title varchar(255) NOT NULL DEFAULT 'Trip Itinerary',
        itinerary_desc longtext,
        itinerary longtext,
        inc_exc_title varchar(255) NOT NULL DEFAULT 'Inclusions & Exclusions',
        inclusions text,
        exclusions text,
        gallery_images longtext,
        video_gallery longtext,
        map_type varchar(50) NOT NULL DEFAULT 'iframe',
        map_image varchar(255) DEFAULT '',
        map_iframe text,
        faqs longtext,
        group_discounts longtext,
        extra_services longtext,
        advanced_meta longtext,
        status varchar(50) NOT NULL DEFAULT 'published',
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY slug (slug),
        KEY trip_code_idx (trip_code)
    ) $charset_collate;";

    $sql_departures = "CREATE TABLE $table_departures (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        trip_id bigint(20) unsigned NOT NULL,
        slot_label varchar(255) NOT NULL,
        departure_date date NOT NULL,
        departure_time varchar(50) NOT NULL DEFAULT '07:30 AM',
        return_date date NOT NULL,
        guide_name varchar(255) NOT NULL DEFAULT '',
        transport_type varchar(100) NOT NULL DEFAULT '',
        total_seats int(11) NOT NULL DEFAULT 24,
        booked_seats int(11) NOT NULL DEFAULT 0,
        status varchar(50) NOT NULL DEFAULT 'active',
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY trip_id (trip_id),
        KEY departure_date (departure_date)
    ) $charset_collate;";

    $sql_seats = "CREATE TABLE $table_seat_roster (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        departure_id bigint(20) unsigned NOT NULL,
        booking_id bigint(20) unsigned DEFAULT 0,
        seat_code varchar(20) NOT NULL,
        seat_tier varchar(50) NOT NULL DEFAULT 'STANDARD',
        is_vip tinyint(1) NOT NULL DEFAULT 0,
        status varchar(50) NOT NULL DEFAULT 'available',
        price_modifier decimal(10,2) NOT NULL DEFAULT 0.00,
        PRIMARY KEY (id),
        KEY departure_id (departure_id),
        KEY seat_lookup (departure_id, seat_code)
    ) $charset_collate;";

    $sql_bookings = "CREATE TABLE $table_bookings (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        booking_code varchar(50) NOT NULL,
        customer_id bigint(20) unsigned NOT NULL,
        departure_id bigint(20) unsigned NOT NULL,
        trip_id bigint(20) unsigned NOT NULL,
        assigned_seats text NOT NULL,
        adults int(5) NOT NULL DEFAULT 1,
        children int(5) NOT NULL DEFAULT 0,
        total_pax int(5) NOT NULL DEFAULT 1,
        subtotal_amount decimal(12,2) NOT NULL DEFAULT 0.00,
        discount_amount decimal(12,2) NOT NULL DEFAULT 0.00,
        total_amount decimal(12,2) NOT NULL DEFAULT 0.00,
        paid_amount decimal(12,2) NOT NULL DEFAULT 0.00,
        balance_due decimal(12,2) NOT NULL DEFAULT 0.00,
        payment_method varchar(50) NOT NULL DEFAULT 'Counter Cash',
        payment_status varchar(50) NOT NULL DEFAULT 'partial',
        status varchar(50) NOT NULL DEFAULT 'confirmed',
        special_notes text,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY booking_code (booking_code),
        KEY departure_id (departure_id),
        KEY customer_id (customer_id)
    ) $charset_collate;";

    $sql_customers = "CREATE TABLE $table_customers (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        full_name varchar(255) NOT NULL,
        email varchar(255) NOT NULL,
        phone varchar(50) NOT NULL,
        passport_nid varchar(100) DEFAULT '',
        emergency_contact varchar(255) DEFAULT '',
        total_bookings int(11) NOT NULL DEFAULT 0,
        total_spent decimal(14,2) NOT NULL DEFAULT 0.00,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY email (email),
        KEY phone (phone)
    ) $charset_collate;";

    $sql_transactions = "CREATE TABLE $table_transactions (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        booking_id bigint(20) unsigned NOT NULL,
        transaction_id varchar(100) NOT NULL,
        payment_gateway varchar(50) NOT NULL,
        amount decimal(12,2) NOT NULL DEFAULT 0.00,
        currency varchar(10) NOT NULL DEFAULT 'EUR',
        payment_type varchar(50) NOT NULL DEFAULT 'deposit',
        status varchar(50) NOT NULL DEFAULT 'completed',
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY booking_id (booking_id)
    ) $charset_collate;";

    $sql_taxonomies = "CREATE TABLE $table_taxonomies (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        type varchar(50) NOT NULL,
        parent_id bigint(20) unsigned NOT NULL DEFAULT 0,
        name varchar(255) NOT NULL,
        slug varchar(255) NOT NULL,
        description text,
        badge_color varchar(50) NOT NULL DEFAULT '#3b82f6',
        image_url varchar(255) DEFAULT '',
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY type_idx (type),
        KEY parent_idx (parent_id)
    ) $charset_collate;";

    $sql_price_cats = "CREATE TABLE $table_price_cats (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        name varchar(100) NOT NULL,
        slug varchar(100) NOT NULL DEFAULT '',
        parent_id bigint(20) unsigned NOT NULL DEFAULT 0,
        code varchar(50) NOT NULL,
        multiplier decimal(5,2) NOT NULL DEFAULT 1.00,
        is_primary tinyint(1) NOT NULL DEFAULT 0,
        age_group varchar(50) NOT NULL DEFAULT 'Adult',
        description varchar(255) DEFAULT '',
        status varchar(20) NOT NULL DEFAULT 'active',
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY code (code),
        KEY parent_idx (parent_id)
    ) $charset_collate;";

    $sql_coupons = "CREATE TABLE $table_coupons (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        code varchar(50) NOT NULL,
        discount_type varchar(20) NOT NULL DEFAULT 'percentage',
        amount decimal(10,2) NOT NULL DEFAULT 0.00,
        usage_limit int(11) NOT NULL DEFAULT 100,
        used_count int(11) NOT NULL DEFAULT 0,
        status varchar(20) NOT NULL DEFAULT 'active',
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY code (code)
    ) $charset_collate;";

    $sql_logs = "CREATE TABLE $table_logs (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        user_login varchar(100) NOT NULL,
        action_type varchar(100) NOT NULL,
        description text NOT NULL,
        ip_address varchar(50) NOT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) $charset_collate;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql_packages);
    dbDelta($sql_departures);
    dbDelta($sql_seats);
    dbDelta($sql_bookings);
    dbDelta($sql_customers);
    dbDelta($sql_transactions);
    dbDelta($sql_taxonomies);
    dbDelta($sql_price_cats);
    dbDelta($sql_coupons);
    dbDelta($sql_logs);
}

/* ==========================================================================
   2. SEAT ROSTER GENERATION HELPER
   ========================================================================== */

function nttbs_generate_seats($departure_id, $capacity = 24) {
    global $wpdb;
    $table_seats = $wpdb->prefix . 'nttbs_seats';
    $rows = ['A', 'B', 'C', 'D', 'E', 'F'];
    $created = 0;
    
    foreach ($rows as $row) {
        for ($col = 1; $col <= 4; $col++) {
            if ($created >= $capacity) {
                break 2;
            }
            $code = $row . $col;
            $is_vip = ($row === 'A');
            $wpdb->insert($table_seats, [
                'departure_id'   => $departure_id,
                'booking_id'     => 0,
                'seat_code'      => $code,
                'seat_tier'      => $is_vip ? 'VIP PREMIUM' : 'STANDARD',
                'is_vip'         => $is_vip ? 1 : 0,
                'status'         => 'available',
                'price_modifier' => 0.00
            ], ['%d', '%d', '%s', '%s', '%d', '%s', '%f']);
            $created++;
        }
    }
}

/* ==========================================================================
   3. GLOBAL AUDIT & EVENT LOGGER
   ========================================================================== */

function nttbs_log_activity($action_type, $description) {
    global $wpdb;
    $table_logs = $wpdb->prefix . 'nttbs_audit_logs';

    if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_logs)) !== $table_logs) {
        return;
    }

    $user = wp_get_current_user();
    $login = $user->exists() ? $user->user_login : 'System Core';
    $ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '127.0.0.1';

    $wpdb->insert($table_logs, [
        'user_login'  => $login,
        'action_type' => sanitize_text_field($action_type),
        'description' => sanitize_textarea_field($description),
        'ip_address'  => $ip
    ], ['%s', '%s', '%s', '%s']);
}

/* ==========================================================================
   4. COMMON REPOSITORIES & FINANCIAL TELEMETRY
   ========================================================================== */

function nttbs_get_financial_telemetry() {
    global $wpdb;
    $table_bookings = $wpdb->prefix . 'nttbs_bookings';

    if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_bookings)) !== $table_bookings) {
        return [
            'revenue'    => 0.00,
            'receivable' => 0.00,
            'orders'     => 0,
            'tickets'    => 0,
            'counter'    => 0.00,
            'online'     => 0.00
        ];
    }

    return [
        'revenue'    => (float) $wpdb->get_var("SELECT SUM(paid_amount) FROM {$table_bookings} WHERE status != 'cancelled'"),
        'receivable' => (float) $wpdb->get_var("SELECT SUM(balance_due) FROM {$table_bookings} WHERE status != 'cancelled'"),
        'orders'     => (int) $wpdb->get_var("SELECT COUNT(id) FROM {$table_bookings} WHERE status != 'cancelled'"),
        'tickets'    => (int) $wpdb->get_var("SELECT SUM(total_pax) FROM {$table_bookings} WHERE status != 'cancelled'"),
        'counter'    => (float) $wpdb->get_var("SELECT SUM(paid_amount) FROM {$table_bookings} WHERE payment_method = 'Counter Cash' AND status != 'cancelled'"),
        'online'     => (float) $wpdb->get_var("SELECT SUM(paid_amount) FROM {$table_bookings} WHERE payment_method != 'Counter Cash' AND status != 'cancelled'")
    ];
}

function nttbs_get_taxonomies($type) {
    global $wpdb;
    $table_tax = $wpdb->prefix . 'nttbs_taxonomies';

    if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_tax)) !== $table_tax) {
        return [];
    }

    return $wpdb->get_results(
        $wpdb->prepare("SELECT * FROM {$table_tax} WHERE type = %s ORDER BY name ASC", $type),
        ARRAY_A
    ) ?: [];
}

function nttbs_get_price_categories() {
    global $wpdb;
    $table_price = $wpdb->prefix . 'nttbs_price_categories';

    if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_price)) !== $table_price) {
        return [];
    }

    return $wpdb->get_results("SELECT * FROM {$table_price} ORDER BY multiplier ASC", ARRAY_A) ?: [];
}

function nttbs_get_all_trips() {
    global $wpdb;
    $table_trips = $wpdb->prefix . 'nttbs_trips';
    $table_tax   = $wpdb->prefix . 'nttbs_taxonomies';

    if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_trips)) !== $table_trips) {
        return [];
    }

    return $wpdb->get_results(
        "SELECT t.*, 
                d.name AS destination_name, 
                a.name AS activity_name, 
                tt.name AS trip_type_name, 
                df.name AS difficulty_name, df.badge_color AS difficulty_color
         FROM {$table_trips} t
         LEFT JOIN {$table_tax} d ON t.destination_id = d.id
         LEFT JOIN {$table_tax} a ON t.activity_id = a.id
         LEFT JOIN {$table_tax} tt ON t.trip_type_id = tt.id
         LEFT JOIN {$table_tax} df ON t.difficulty_id = df.id
         ORDER BY t.id DESC",
        ARRAY_A
    ) ?: [];
}

function nttbs_get_all_departures() {
    global $wpdb;
    $table_departures = $wpdb->prefix . 'nttbs_departures';
    $table_trips      = $wpdb->prefix . 'nttbs_trips';
    $table_tax        = $wpdb->prefix . 'nttbs_taxonomies';

    if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_departures)) !== $table_departures) {
        return [];
    }

    return $wpdb->get_results(
        "SELECT d.*, t.title AS trip_title, t.price_adult, t.price_child, dest.name AS destination_name
         FROM {$table_departures} d 
         LEFT JOIN {$table_trips} t ON d.trip_id = t.id 
         LEFT JOIN {$table_tax} dest ON t.destination_id = dest.id
         ORDER BY d.departure_date ASC",
        ARRAY_A
    ) ?: [];
}
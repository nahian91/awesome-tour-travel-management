<?php
if (!defined('ABSPATH')) {
    exit;
}

/* ==========================================================================
   1. COMPREHENSIVE DATABASE SCHEMA BUILDER
   ========================================================================== */

function nte_install_complete_database() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();

    $table_packages     = $wpdb->prefix . 'nte_trips';
    $table_departures   = $wpdb->prefix . 'nte_departures';
    $table_seat_roster  = $wpdb->prefix . 'nte_seats';
    $table_bookings     = $wpdb->prefix . 'nte_bookings';
    $table_customers    = $wpdb->prefix . 'nte_customers';
    $table_transactions = $wpdb->prefix . 'nte_transactions';
    $table_taxonomies   = $wpdb->prefix . 'nte_taxonomies';
    $table_price_cats   = $wpdb->prefix . 'nte_price_categories';
    $table_coupons      = $wpdb->prefix . 'nte_coupons';
    $table_logs         = $wpdb->prefix . 'nte_audit_logs';

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
        guide_name varchar(255) NOT NULL DEFAULT 'Captain Abdullah Nahian',
        transport_type varchar(100) NOT NULL DEFAULT 'AC Scania / Catamaran Cruiser',
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
        badge_color varchar(50) NOT NULL DEFAULT '#2563eb',
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

    nte_seed_production_taxonomy_data();
}

/* ==========================================================================
   2. INITIAL SEED DATA BUILDER
   ========================================================================== */

function nte_seed_production_taxonomy_data() {
    global $wpdb;
    $table_tax   = $wpdb->prefix . 'nte_taxonomies';
    $table_price = $wpdb->prefix . 'nte_price_categories';
    $table_trips = $wpdb->prefix . 'nte_trips';
    $table_deps  = $wpdb->prefix . 'nte_departures';

    // Seed Taxonomies
    if ((int)$wpdb->get_var("SELECT COUNT(id) FROM $table_tax") === 0) {
        $destinations = [
            ['Hurghada Red Sea Coral', 'hurghada-red-sea', 'Egyptian Red Sea Coral Coast & Giftun Reefs', '#0284c7'],
            ['Sundarbans Mangrove Reserve', 'sundarbans-mangrove', 'UNESCO World Heritage Mangrove Forest', '#059669'],
            ['Sajek Kingdom of Clouds', 'sajek-valley', 'Valley of Clouds & Mountain Range', '#7c3aed'],
            ["Cox's Bazar Horizon Coast", 'coxs-bazar', 'World longest unbroken natural beach & marine drive', '#ea580c']
        ];
        foreach ($destinations as $d) {
            $wpdb->insert($table_tax, ['type' => 'destination', 'parent_id' => 0, 'name' => $d[0], 'slug' => $d[1], 'description' => $d[2], 'badge_color' => $d[3], 'image_url' => '']);
        }

        $activities = [
            ['Giftun Coral Snorkeling', 'giftun-snorkeling', 'Deep red sea dolphin coral exploration', '#06b6d4'],
            ['Mangrove Safari Cruising', 'mangrove-cruising', 'Deep tidal rivers on equipped watercraft', '#10b981'],
            ['Helipad Sunset Watch', 'sunset-watch', 'Panoramic skyline golden hour excursions', '#f59e0b'],
            ['Bedouin Camp Stargazing', 'bedouin-stargazing', 'Desert astronomical night experiences', '#8b5cf6']
        ];
        foreach ($activities as $a) {
            $wpdb->insert($table_tax, ['type' => 'activity', 'parent_id' => 0, 'name' => $a[0], 'slug' => $a[1], 'description' => $a[2], 'badge_color' => $a[3], 'image_url' => '']);
        }

        $types = [
            ['Luxury Sea Cruise', 'sea-cruise', 'Private yachts and expedition vessels', '#2563eb'],
            ['Mountain 4x4 Convoy', 'mountain-convoy', 'Rugged vehicle hill climbs & valley cross', '#dc2626'],
            ['Desert Safari Expedition', 'desert-safari', 'Dune quad-bikes, camels, and oasis camps', '#d97706'],
            ['Corporate Retreat', 'corporate-retreat', 'Executive VIP team events with priority reservations', '#475569']
        ];
        foreach ($types as $t) {
            $wpdb->insert($table_tax, ['type' => 'trip_type', 'parent_id' => 0, 'name' => $t[0], 'slug' => $t[1], 'description' => $t[2], 'badge_color' => $t[3], 'image_url' => '']);
        }

        $difficulties = [
            ['Serene & Leisurely', 'serene-leisure', 'Zero physical stress, family & luxury travelers', '#16a34a'],
            ['Moderate Adventure', 'moderate-adventure', 'Mild trails, marine transfers, light climbing', '#0284c7'],
            ['Demanding Trail', 'demanding-trail', 'Remote wilderness, extended open sea journeys', '#f97316'],
            ['Extreme Expedition', 'extreme-expedition', 'High stamina rugged backcountry navigation', '#e11d48']
        ];
        foreach ($difficulties as $df) {
            $wpdb->insert($table_tax, ['type' => 'difficulty', 'parent_id' => 0, 'name' => $df[0], 'slug' => $df[1], 'description' => $df[2], 'badge_color' => $df[3], 'image_url' => '']);
        }
    }

    // Seed Price Categories
    if ((int)$wpdb->get_var("SELECT COUNT(id) FROM $table_price") === 0) {
        $price_tiers = [
            ['Standard Adult Passage', 'standard-adult-pass', 0, 'ADULT_STD', 1.00, 1, 'Adult', 'Baseline full tariff for independent passengers'],
            ['Youth Explorer (12-17 Yrs)', 'youth-explorer', 0, 'YOUTH_EXP', 0.80, 0, 'Youth (12-17)', 'Discounted youth pass for teen explorers'],
            ['Child Seat Pass (3-11 Yrs)', 'child-pass', 0, 'CHILD_YOUTH', 0.60, 0, 'Child (3-11)', 'Child seat reservation with safety lifevest'],
            ['Senior VIP Privilege', 'senior-privilege', 0, 'SENIOR_VIP', 0.85, 0, 'Senior (60+)', 'Privilege fare for senior travelers aged 60+']
        ];
        foreach ($price_tiers as $pt) {
            $wpdb->insert($table_price, [
                'name'        => $pt[0],
                'slug'        => $pt[1],
                'parent_id'   => $pt[2],
                'code'        => $pt[3],
                'multiplier'  => $pt[4],
                'is_primary'  => $pt[5],
                'age_group'   => $pt[6],
                'description' => $pt[7],
                'status'      => 'active'
            ]);
        }
    }

    // Seed Comprehensive Production Trip
    if ((int)$wpdb->get_var("SELECT COUNT(id) FROM $table_trips") === 0) {
        $sample_itinerary = [
            [
                'day'   => 1,
                'title' => 'Marina Boarding & Orange Bay Coral Reef',
                'desc'  => 'Morning yacht embarkation at Hurghada New Marina, dolphin bay cruise, and guided coral snorkeling.',
                'meals' => 'Buffet Lunch, Mineral Water & Soft Drinks',
                'lodge' => 'Onboard Royal Catamaran Cabin'
            ],
            [
                'day'   => 2,
                'title' => 'Giftun Island Lagoon & Sunset Seafood Dinner',
                'desc'  => 'Island relaxation on private sands, fresh seafood dining aboard ship, sunset return transit.',
                'meals' => 'Continental Breakfast, Fresh Catch Lunch',
                'lodge' => 'Return Harbor Transit'
            ]
        ];

        $sample_packages = [
            [
                'name'        => 'Standard Saloon Passage',
                'price_adult' => 185.00,
                'price_child' => 110.00,
                'capacity'    => 18,
                'desc'        => 'Includes full standard catamaran berth access and meals.'
            ],
            [
                'name'        => 'VIP Private Ocean Suite',
                'price_adult' => 260.00,
                'price_child' => 150.00,
                'capacity'    => 6,
                'desc'        => 'Forward panoramic deck cabin, priority snorkeling tender, champagne lunch.'
            ]
        ];

        $sample_facts = [
            ['title' => 'Transport', 'value' => 'Mon Hurgada Royal Catamaran Cruiser'],
            ['title' => 'Departure Pier', 'value' => 'Hurghada New Marina - Gate 3'],
            ['title' => 'Snorkeling Depth', 'value' => '3 to 12 Meters (Guided)']
        ];

        $sample_highlights = [
            'Guided drift snorkeling with wild dolphins at Shaab El Erg',
            'Exclusive 2-hour private beach anchor at Orange Bay Giftun Island',
            'Freshly prepared Red Sea catch seafood buffet prepared by onboard chef',
            'Modern catamaran cruiser with air-conditioned lounge and open sun deck'
        ];

        $sample_faqs = [
            [
                'q' => 'Is snorkeling and diving equipment provided?',
                'a' => 'Yes, professional-grade masks, snorkels, fins, and certified lifevests are sanitized and provided free of charge.'
            ],
            [
                'q' => 'What happens in case of rough sea weather?',
                'a' => 'Expeditions are monitored in real time with the coast guard. If sea conditions are not optimal, departures are safely rescheduled or fully refunded.'
            ]
        ];

        $sample_discounts = [
            ['min_pax' => 6, 'discount' => 10.00, 'type' => 'percentage'],
            ['min_pax' => 12, 'discount' => 18.00, 'type' => 'percentage']
        ];

        $sample_extras = [
            ['title' => 'Private Hotel Harbor Transfer (Round-trip)', 'price' => 25.00, 'type' => 'per_booking'],
            ['title' => 'Underwater GoPro 4K Video Package', 'price' => 35.00, 'type' => 'per_pax']
        ];

        $sample_adv_meta = [
            'allow_partial' => 1,
            'require_id'    => 1,
            'badge_custom'  => 'POPULAR CHOICE',
            'external_link' => ''
        ];

        $wpdb->insert($table_trips, [
            'title'           => 'Hurghada Red Sea Yacht Odyssey (2D/1N)',
            'slug'            => 'hurghada-red-sea-yacht-odyssey',
            'trip_code'       => 'TRP-REDSEA',
            'destination_id'  => 1,
            'activity_id'     => 1,
            'trip_type_id'    => 1,
            'difficulty_id'   => 1,
            'duration_days'   => 2,
            'duration_nights' => 1,
            'min_age'         => 3,
            'max_age'         => 80,
            'min_pax'         => 1,
            'max_seats'       => 24,
            'cutoff_time'     => '18 Hours Before Departure',
            'price_adult'     => 185.00,
            'price_child'     => 110.00,
            'deposit_percent' => 50,
            'packages_json'   => wp_json_encode($sample_packages),
            'description'     => 'Elite coastal marine voyage navigating through pristine coral archipelagos, dolphin bays, and island lagoons aboard a luxury catamaran.',
            'highlights'      => wp_json_encode($sample_highlights),
            'trip_facts'      => wp_json_encode($sample_facts),
            'itinerary_title' => 'Expedition Itinerary & Anchor Points',
            'itinerary_desc'  => 'Day-by-day procedural cruise schedule across protected Red Sea marine zones.',
            'itinerary'       => wp_json_encode($sample_itinerary),
            'inc_exc_title'   => 'Services Included & Excluded',
            'inclusions'      => "Private yacht catamaran berth & stay\nBuffet seafood & international dining\nAll snorkeling equipment and marine permits\nBottled mineral water, tea, and espresso",
            'exclusions'      => "International airfare to Egypt\nAlcoholic beverages\nGratuities for crew and marine guides",
            'gallery_images'  => '',
            'video_gallery'   => '',
            'map_type'        => 'iframe',
            'map_image'       => '',
            'map_iframe'      => '<iframe src="https://maps.google.com/maps?q=Hurghada%20Marina&t=&z=13&ie=UTF8&iwloc=&output=embed" width="100%" height="250" style="border:0;" loading="lazy"></iframe>',
            'faqs'            => wp_json_encode($sample_faqs),
            'group_discounts' => wp_json_encode($sample_discounts),
            'extra_services'  => wp_json_encode($sample_extras),
            'advanced_meta'   => wp_json_encode($sample_adv_meta),
            'status'          => 'published'
        ]);
        $sample_trip_id = $wpdb->insert_id;

        $wpdb->insert($table_deps, [
            'trip_id'        => $sample_trip_id,
            'slot_label'     => 'Hurghada Marina Pier Alpha - Royal Catamaran',
            'departure_date' => date('Y-m-d', strtotime('+3 days')),
            'departure_time' => '07:30 AM',
            'return_date'    => date('Y-m-d', strtotime('+5 days')),
            'guide_name'     => 'Captain Abdullah Nahian',
            'transport_type' => 'Mon Hurgada Royal Catamaran Cruiser',
            'total_seats'    => 24,
            'booked_seats'   => 6,
            'status'         => 'active'
        ]);
        $sample_dep_id = $wpdb->insert_id;
        nte_generate_seats($sample_dep_id, 24);

        $wpdb->query($wpdb->prepare(
            "UPDATE {$wpdb->prefix}nte_seats SET status = 'booked' WHERE departure_id = %d AND seat_code IN ('A1','A2','B1','B2','C1','C2')",
            $sample_dep_id
        ));
    }
}

/* ==========================================================================
   3. SEAT ROSTER GENERATION
   ========================================================================== */

function nte_generate_seats($departure_id, $capacity = 24) {
    global $wpdb;
    $rows = ['A', 'B', 'C', 'D', 'E', 'F'];
    $created = 0;
    foreach ($rows as $row) {
        for ($col = 1; $col <= 4; $col++) {
            if ($created >= $capacity) break 2;
            $code = $row . $col;
            $is_vip = ($row === 'A');
            $wpdb->insert($wpdb->prefix . 'nte_seats', [
                'departure_id'   => $departure_id,
                'booking_id'     => 0,
                'seat_code'      => $code,
                'seat_tier'      => $is_vip ? 'VIP PREMIUM' : 'DELUXE SALOON',
                'is_vip'         => $is_vip ? 1 : 0,
                'status'         => 'available',
                'price_modifier' => $is_vip ? 25.00 : 0.00
            ]);
            $created++;
        }
    }
}

/* ==========================================================================
   4. GLOBAL LOGGING & AUDIT TRAIL
   ========================================================================== */

function nte_log_activity($action_type, $description) {
    global $wpdb;
    $user = wp_get_current_user();
    $login = $user->exists() ? $user->user_login : 'System Core';
    $ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '127.0.0.1';

    $wpdb->insert($wpdb->prefix . 'nte_audit_logs', [
        'user_login'  => $login,
        'action_type' => sanitize_text_field($action_type),
        'description' => sanitize_textarea_field($description),
        'ip_address'  => $ip
    ]);
}

/* ==========================================================================
   5. COMMON REPOSITORIES & FINANCIAL TELEMETRY
   ========================================================================== */

function nte_get_financial_telemetry() {
    global $wpdb;
    $b = $wpdb->prefix . 'nte_bookings';
    return [
        'revenue'    => (float) $wpdb->get_var("SELECT SUM(paid_amount) FROM $b WHERE status != 'cancelled'"),
        'receivable' => (float) $wpdb->get_var("SELECT SUM(balance_due) FROM $b WHERE status != 'cancelled'"),
        'orders'     => (int) $wpdb->get_var("SELECT COUNT(id) FROM $b WHERE status != 'cancelled'"),
        'tickets'    => (int) $wpdb->get_var("SELECT SUM(total_pax) FROM $b WHERE status != 'cancelled'"),
        'counter'    => (float) $wpdb->get_var("SELECT SUM(paid_amount) FROM $b WHERE payment_method = 'Counter Cash' AND status != 'cancelled'"),
        'online'     => (float) $wpdb->get_var("SELECT SUM(paid_amount) FROM $b WHERE payment_method != 'Counter Cash' AND status != 'cancelled'")
    ];
}

function nte_get_taxonomies($type) {
    global $wpdb;
    return $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}nte_taxonomies WHERE type = %s ORDER BY name ASC", $type), ARRAY_A);
}

function nte_get_price_categories() {
    global $wpdb;
    return $wpdb->get_results("SELECT * FROM {$wpdb->prefix}nte_price_categories ORDER BY multiplier ASC", ARRAY_A);
}

function nte_get_all_trips() {
    global $wpdb;
    return $wpdb->get_results(
        "SELECT t.*, 
                d.name AS destination_name, 
                a.name AS activity_name, 
                tt.name AS trip_type_name, 
                df.name AS difficulty_name, df.badge_color AS difficulty_color
         FROM {$wpdb->prefix}nte_trips t
         LEFT JOIN {$wpdb->prefix}nte_taxonomies d ON t.destination_id = d.id
         LEFT JOIN {$wpdb->prefix}nte_taxonomies a ON t.activity_id = a.id
         LEFT JOIN {$wpdb->prefix}nte_taxonomies tt ON t.trip_type_id = tt.id
         LEFT JOIN {$wpdb->prefix}nte_taxonomies df ON t.difficulty_id = df.id
         ORDER BY t.id DESC",
        ARRAY_A
    );
}

function nte_get_all_departures() {
    global $wpdb;
    return $wpdb->get_results(
        "SELECT d.*, t.title AS trip_title, t.price_adult, t.price_child, dest.name AS destination_name
         FROM {$wpdb->prefix}nte_departures d 
         LEFT JOIN {$wpdb->prefix}nte_trips t ON d.trip_id = t.id 
         LEFT JOIN {$wpdb->prefix}nte_taxonomies dest ON t.destination_id = dest.id
         ORDER BY d.departure_date ASC",
        ARRAY_A
    );
}
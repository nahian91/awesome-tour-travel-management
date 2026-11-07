<?php
if (!defined('ABSPATH')) {
    exit;
}

add_shortcode('nttbs_tour_single', function($atts) {
    global $wpdb;

    $args = shortcode_atts([
        'trip_id'           => 0,
        'checkout_page_url' => site_url('/tour-checkout/'),
    ], $atts, 'nttbs_tour_single');

    $trip_id  = !empty($args['trip_id']) ? absint($args['trip_id']) : (isset($_GET['trip_id']) ? absint($_GET['trip_id']) : 0);
    $currency = get_option('nttbs_currency_symbol', NTTBS_CURRENCY_SYMBOL_DEFAULT);

    $table_trips = $wpdb->prefix . 'nttbs_trips';
    $table_deps  = $wpdb->prefix . 'nttbs_departures';
    $table_tax   = $wpdb->prefix . 'nttbs_taxonomies';

    $trip = $wpdb->get_row($wpdb->prepare(
        "SELECT t.*, d.name AS destination_name, a.name AS activity_name, df.name AS difficulty_name 
         FROM {$table_trips} t 
         LEFT JOIN {$table_tax} d ON t.destination_id = d.id 
         LEFT JOIN {$table_tax} a ON t.activity_id = a.id 
         LEFT JOIN {$table_tax} df ON t.difficulty_id = df.id 
         WHERE t.id = %d AND t.status = 'published'", 
        $trip_id
    ), ARRAY_A);

    if (!$trip) {
        return '<div style="max-width:800px; margin:40px auto; padding:24px; background:#fff; border:1px solid #e2e8f0; border-radius:12px; text-align:center; font-family:sans-serif;">' . esc_html__('Expedition dossier not found or published.', 'nahian-tour-travel-booking-solution') . '</div>';
    }

    $departure = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$table_deps} WHERE trip_id = %d AND status = 'active' AND departure_date >= CURDATE() ORDER BY departure_date ASC LIMIT 1", 
        $trip_id
    ), ARRAY_A);

    $itinerary  = json_decode($trip['itinerary'] ?? '[]', true) ?: [];
    $gallery    = !empty($trip['gallery_images']) ? array_filter(explode(',', $trip['gallery_images'])) : [];

    ob_start();
    ?>
    <style>
    .nttbs-single-wrap {
        max-width: 1200px;
        margin: 30px auto;
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Inter", sans-serif;
        color: #0f172a;
        display: grid;
        grid-template-columns: minmax(0, 2fr) 380px;
        gap: 32px;
        box-sizing: border-box;
    }
    .nttbs-hero-img-box {
        width: 100%;
        height: 400px;
        border-radius: 14px;
        overflow: hidden;
        background: #f1f5f9;
        margin-bottom: 24px;
    }
    .nttbs-hero-img-box img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: block;
    }
    .nttbs-content-panel {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 14px;
        padding: 24px;
        margin-bottom: 24px;
    }
    .nttbs-panel-title {
        font-size: 17px;
        font-weight: 800;
        margin: 0 0 16px 0;
        border-bottom: 1px solid #f1f5f9;
        padding-bottom: 12px;
    }
    .nttbs-timeline-item {
        position: relative;
        padding-left: 28px;
        margin-bottom: 20px;
        border-left: 2px solid #e2e8f0;
    }
    .nttbs-timeline-item::before {
        content: '';
        position: absolute;
        left: -6px;
        top: 4px;
        width: 10px;
        height: 10px;
        background: #3b82f6;
        border-radius: 50%;
    }
    .nttbs-timeline-day {
        font-size: 11.5px;
        font-weight: 800;
        color: #3b82f6;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .nttbs-timeline-title {
        font-size: 15px;
        font-weight: 700;
        margin: 2px 0 6px 0;
    }
    .nttbs-booking-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-top: 4px solid #3b82f6;
        border-radius: 14px;
        padding: 24px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        position: sticky;
        top: 24px;
    }
    .nttbs-btn-book-now {
        width: 100%;
        background: linear-gradient(135deg, #3b82f6, #1d4ed8);
        color: #ffffff;
        padding: 14px;
        font-size: 14.5px;
        font-weight: 800;
        border-radius: 8px;
        border: none;
        cursor: pointer;
        text-align: center;
        text-decoration: none;
        display: block;
        margin-top: 18px;
        box-sizing: border-box;
        box-shadow: 0 4px 12px rgba(37, 99, 235, 0.25);
        transition: transform 0.15s ease;
    }
    .nttbs-btn-book-now:hover {
        transform: translateY(-1px);
    }
    @media (max-width: 900px) {
        .nttbs-single-wrap { grid-template-columns: 1fr; }
    }
    </style>

    <div class="nttbs-single-wrap">
        <div>
            <div class="nttbs-hero-img-box">
                <?php if (!empty($gallery[0])): ?>
                    <img src="<?php echo esc_url($gallery[0]); ?>" alt="<?php echo esc_attr($trip['title']); ?>">
                <?php endif; ?>
            </div>

            <h1 style="font-size:28px; font-weight:900; margin:0 0 10px 0; letter-spacing:-0.4px;"><?php echo esc_html($trip['title']); ?></h1>
            <div style="display:flex; gap:16px; font-size:13px; color:#64748b; margin-bottom:24px; flex-wrap:wrap;">
                <span>📍 <?php echo esc_html($trip['destination_name']); ?></span>
                <span>⏱️ <?php echo esc_html($trip['duration_days']); ?> Days / <?php echo esc_html($trip['duration_nights']); ?> Nights</span>
                <span>⚡ <?php echo esc_html($trip['difficulty_name']); ?></span>
            </div>

            <div class="nttbs-content-panel">
                <h3 class="nttbs-panel-title"><?php esc_html_e('Expedition Overview', 'nahian-tour-travel-booking-solution'); ?></h3>
                <div style="font-size:14px; line-height:1.7; color:#334155;"><?php echo wp_kses_post(wpautop($trip['description'])); ?></div>
            </div>

            <?php if (!empty($itinerary)): ?>
                <div class="nttbs-content-panel">
                    <h3 class="nttbs-panel-title"><?php esc_html_e('Day-by-Day Procedural Itinerary', 'nahian-tour-travel-booking-solution'); ?></h3>
                    <?php foreach ($itinerary as $it): ?>
                        <div class="nttbs-timeline-item">
                            <div class="nttbs-timeline-day"><?php printf(esc_html__('Day %d', 'nahian-tour-travel-booking-solution'), $it['day']); ?></div>
                            <div class="nttbs-timeline-title"><?php echo esc_html($it['title']); ?></div>
                            <p style="font-size:13px; color:#64748b; margin:0 0 6px 0; line-height:1.5;"><?php echo esc_html($it['desc']); ?></p>
                            <?php if (!empty($it['meals'])): ?>
                                <small style="color:#059669; font-weight:700;">🍴 <?php echo esc_html($it['meals']); ?></small>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div class="nttbs-content-panel" style="display:grid; grid-template-columns:1fr 1fr; gap:24px;">
                <div>
                    <h4 style="color:#059669; margin:0 0 10px 0; font-size:14px; font-weight:800;">✓ <?php esc_html_e('Inclusions', 'nahian-tour-travel-booking-solution'); ?></h4>
                    <div style="font-size:13px; line-height:1.6; color:#475569;"><?php echo nl2br(esc_html($trip['inclusions'])); ?></div>
                </div>
                <div>
                    <h4 style="color:#e11d48; margin:0 0 10px 0; font-size:14px; font-weight:800;">✕ <?php esc_html_e('Exclusions', 'nahian-tour-travel-booking-solution'); ?></h4>
                    <div style="font-size:13px; line-height:1.6; color:#475569;"><?php echo nl2br(esc_html($trip['exclusions'])); ?></div>
                </div>
            </div>
        </div>

        <div>
            <div class="nttbs-booking-card">
                <div style="font-size:11.5px; font-weight:800; color:#64748b; text-transform:uppercase;"><?php esc_html_e('Tariff from', 'nahian-tour-travel-booking-solution'); ?></div>
                <div style="font-size:30px; font-weight:900; color:#1d4ed8; margin:4px 0 16px 0; font-family:'JetBrains Mono', monospace;">
                    <?php echo esc_html($currency . ' ' . number_format((float)$trip['price_adult'], 2)); ?>
                    <span style="font-size:12px; color:#64748b; font-weight:600; font-family:sans-serif;">/ <?php esc_html_e('adult', 'nahian-tour-travel-booking-solution'); ?></span>
                </div>

                <?php if ($departure): ?>
                    <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:14px; margin-bottom:14px; font-size:12.5px;">
                        <div>📅 <strong><?php esc_html_e('Next Departure:', 'nahian-tour-travel-booking-solution'); ?></strong> <?php echo esc_html($departure['departure_date']); ?></div>
                        <div style="margin-top:4px;">💺 <strong><?php esc_html_e('Capacity:', 'nahian-tour-travel-booking-solution'); ?></strong> <?php echo esc_html($departure['total_seats'] - $departure['booked_seats']); ?> <?php esc_html_e('Seats Left', 'nahian-tour-travel-booking-solution'); ?></div>
                    </div>
                <?php endif; ?>

                <?php
                $book_url = add_query_arg([
                    'trip_id'      => $trip['id'],
                    'departure_id' => $departure ? $departure['id'] : 0
                ], esc_url($args['checkout_page_url']));
                ?>

                <a href="<?php echo esc_url($book_url); ?>" class="nttbs-btn-book-now">
                    <?php esc_html_e('Instant Checkout', 'nahian-tour-travel-booking-solution'); ?> →
                </a>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
});
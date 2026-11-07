<?php
if (!defined('ABSPATH')) {
    exit;
}

add_shortcode('nttbs_tours_grid', function($atts) {
    global $wpdb;

    $args = shortcode_atts([
        'columns'     => 3,
        'per_page'    => 12,
        'destination' => 0,
        'activity'    => 0,
        'single_page_url' => '', // Optional direct target single page URL
    ], $atts, 'nttbs_tours_grid');

    $table_trips = $wpdb->prefix . 'nttbs_trips';
    $table_tax   = $wpdb->prefix . 'nttbs_taxonomies';
    $currency    = get_option('nttbs_currency_symbol', NTTBS_CURRENCY_SYMBOL_DEFAULT);

    $columns  = max(1, min(4, absint($args['columns'])));
    $per_page = absint($args['per_page']);

    $where_clauses = ["t.status = 'published'"];
    $where_params  = [];

    if (!empty($_GET['nttbs_query'])) {
        $where_clauses[] = "t.title LIKE %s";
        $where_params[]  = '%' . $wpdb->esc_like(sanitize_text_field(wp_unslash($_GET['nttbs_query']))) . '%';
    }

    $dest_filter = !empty($_GET['nttbs_dest']) ? absint($_GET['nttbs_dest']) : absint($args['destination']);
    if (!empty($dest_filter)) {
        $where_clauses[] = "t.destination_id = %d";
        $where_params[]  = $dest_filter;
    }

    $act_filter = !empty($_GET['nttbs_act']) ? absint($_GET['nttbs_act']) : absint($args['activity']);
    if (!empty($act_filter)) {
        $where_clauses[] = "t.activity_id = %d";
        $where_params[]  = $act_filter;
    }

    $where_sql = implode(' AND ', $where_clauses);
    $query = "SELECT t.*, d.name AS destination_name, a.name AS activity_name, df.name AS difficulty_name, df.badge_color AS difficulty_color 
              FROM {$table_trips} t 
              LEFT JOIN {$table_tax} d ON t.destination_id = d.id 
              LEFT JOIN {$table_tax} a ON t.activity_id = a.id 
              LEFT JOIN {$table_tax} df ON t.difficulty_id = df.id 
              WHERE {$where_sql} 
              ORDER BY t.id DESC 
              LIMIT %d";
    $where_params[] = $per_page;

    $trips = $wpdb->get_results($wpdb->prepare($query, $where_params), ARRAY_A);

    ob_start();
    ?>
    <style>
    .nttbs-catalog-wrapper {
        max-width: 1240px;
        margin: 0 auto;
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Inter", sans-serif;
        color: #0f172a;
        box-sizing: border-box;
    }
    .nttbs-catalog-grid {
        display: grid;
        grid-template-columns: repeat(<?php echo esc_attr($columns); ?>, minmax(0, 1fr));
        gap: 24px;
    }
    .nttbs-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 14px;
        overflow: hidden;
        box-shadow: 0 2px 6px rgba(15, 23, 42, 0.04);
        display: flex;
        flex-direction: column;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    .nttbs-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 10px 20px -3px rgba(15, 23, 42, 0.08);
    }
    .nttbs-card-media {
        position: relative;
        height: 210px;
        background: #f1f5f9;
        overflow: hidden;
    }
    .nttbs-card-media img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: block;
        transition: transform 0.3s ease;
    }
    .nttbs-card:hover .nttbs-card-media img {
        transform: scale(1.04);
    }
    .nttbs-badge-overlay {
        position: absolute;
        top: 12px;
        left: 12px;
        background: rgba(15, 23, 42, 0.78);
        color: #ffffff;
        font-size: 11px;
        font-weight: 700;
        padding: 3px 9px;
        border-radius: 6px;
        backdrop-filter: blur(4px);
    }
    .nttbs-card-body {
        padding: 20px;
        flex-grow: 1;
        display: flex;
        flex-direction: column;
    }
    .nttbs-card-meta {
        font-size: 12px;
        color: #64748b;
        font-weight: 600;
        margin-bottom: 8px;
        display: flex;
        gap: 12px;
        flex-wrap: wrap;
    }
    .nttbs-card-title {
        font-size: 16px;
        font-weight: 800;
        line-height: 1.35;
        margin: 0 0 10px 0;
    }
    .nttbs-card-title a {
        color: #0f172a;
        text-decoration: none;
        transition: color 0.15s ease;
    }
    .nttbs-card-title a:hover {
        color: #3b82f6;
    }
    .nttbs-card-excerpt {
        font-size: 12.5px;
        color: #64748b;
        line-height: 1.5;
        margin-bottom: 18px;
        flex-grow: 1;
    }
    .nttbs-card-footer {
        display: flex;
        justify-content: space-between;
        align-items: center;
        border-top: 1px solid #f1f5f9;
        padding-top: 14px;
    }
    .nttbs-price-box {
        display: flex;
        flex-direction: column;
    }
    .nttbs-price-label {
        font-size: 10.5px;
        color: #64748b;
        font-weight: 700;
        text-transform: uppercase;
    }
    .nttbs-price-amount {
        font-size: 18px;
        font-weight: 800;
        color: #1d4ed8;
        font-family: 'JetBrains Mono', monospace, sans-serif;
    }
    .nttbs-book-btn {
        background: #eff6ff;
        color: #2563eb;
        border: 1px solid #bfdbfe;
        font-size: 12.5px;
        font-weight: 700;
        padding: 8px 16px;
        border-radius: 8px;
        text-decoration: none;
        transition: all 0.15s ease;
    }
    .nttbs-book-btn:hover {
        background: #2563eb;
        color: #ffffff;
    }
    @media (max-width: 1024px) {
        .nttbs-catalog-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }
    @media (max-width: 640px) {
        .nttbs-catalog-grid { grid-template-columns: 1fr; }
    }
    </style>

    <div class="nttbs-catalog-wrapper">
        <div class="nttbs-catalog-grid">
            <?php if (empty($trips)): ?>
                <div style="grid-column: 1 / -1; background:#ffffff; border:1px solid #e2e8f0; padding:48px 24px; text-align:center; border-radius:14px; color:#64748b;">
                    <h3 style="font-size:18px; font-weight:800; margin:0 0 6px 0; color:#0f172a;"><?php esc_html_e('No Expeditions Matched Your Parameters', 'nahian-tour-travel-booking-solution'); ?></h3>
                    <p style="font-size:13px; margin:0;"><?php esc_html_e('Try clearing search terms or selecting a different region or style.', 'nahian-tour-travel-booking-solution'); ?></p>
                </div>
            <?php else: foreach ($trips as $t): 
                $gallery  = !empty($t['gallery_images']) ? array_filter(explode(',', $t['gallery_images'])) : [];
                $img_url  = !empty($gallery[0]) ? $gallery[0] : '';
                $base_url = !empty($args['single_page_url']) ? esc_url($args['single_page_url']) : get_permalink();
                $link     = add_query_arg(['trip_id' => $t['id']], $base_url);
            ?>
                <div class="nttbs-card">
                    <div class="nttbs-card-media">
                        <?php if ($img_url): ?>
                            <img src="<?php echo esc_url($img_url); ?>" alt="<?php echo esc_attr($t['title']); ?>">
                        <?php else: ?>
                            <div style="width:100%; height:100%; display:flex; align-items:center; justify-content:center; color:#94a3b8; font-size:12px; font-weight:700;">
                                <?php esc_html_e('No Image Asset', 'nahian-tour-travel-booking-solution'); ?>
                            </div>
                        <?php endif; ?>
                        <span class="nttbs-badge-overlay"><?php echo esc_html($t['duration_days']); ?>D / <?php echo esc_html($t['duration_nights']); ?>N</span>
                    </div>
                    <div class="nttbs-card-body">
                        <div class="nttbs-card-meta">
                            <span>📍 <?php echo esc_html($t['destination_name'] ?: __('General Zone', 'nahian-tour-travel-booking-solution')); ?></span>
                            <span>⚡ <?php echo esc_html($t['difficulty_name'] ?: __('Moderate', 'nahian-tour-travel-booking-solution')); ?></span>
                        </div>
                        <h3 class="nttbs-card-title">
                            <a href="<?php echo esc_url($link); ?>"><?php echo esc_html($t['title']); ?></a>
                        </h3>
                        <p class="nttbs-card-excerpt">
                            <?php echo esc_html(wp_trim_words(strip_tags($t['description']), 14)); ?>
                        </p>
                        <div class="nttbs-card-footer">
                            <div class="nttbs-price-box">
                                <span class="nttbs-price-label"><?php esc_html_e('Starting from', 'nahian-tour-travel-booking-solution'); ?></span>
                                <span class="nttbs-price-amount"><?php echo esc_html($currency . ' ' . number_format((float)$t['price_adult'], 2)); ?></span>
                            </div>
                            <a href="<?php echo esc_url($link); ?>" class="nttbs-book-btn"><?php esc_html_e('View Expedition', 'nahian-tour-travel-booking-solution'); ?></a>
                        </div>
                    </div>
                </div>
            <?php endforeach; endif; ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
});
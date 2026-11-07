<?php
if (!defined('ABSPATH')) {
    exit;
}

add_shortcode('nttbs_tour_search_form', function($atts) {
    global $wpdb;

    $args = shortcode_atts([
        'target_url' => '',
        'style'      => 'horizontal', // 'horizontal' or 'stacked'
    ], $atts, 'nttbs_tour_search_form');

    $destinations = function_exists('nttbs_get_taxonomies') ? nttbs_get_taxonomies('destination') : [];
    $activities   = function_exists('nttbs_get_taxonomies') ? nttbs_get_taxonomies('activity') : [];

    $selected_dest = isset($_GET['nttbs_dest']) ? absint($_GET['nttbs_dest']) : 0;
    $selected_act  = isset($_GET['nttbs_act']) ? absint($_GET['nttbs_act']) : 0;
    $selected_date = isset($_GET['nttbs_date']) ? sanitize_text_field(wp_unslash($_GET['nttbs_date'])) : '';
    $search_query  = isset($_GET['nttbs_query']) ? sanitize_text_field(wp_unslash($_GET['nttbs_query'])) : '';

    $target_action = !empty($args['target_url']) ? esc_url($args['target_url']) : esc_url(get_permalink());
    $layout_style  = $args['style'] === 'stacked' ? 'nttbs-stacked' : 'nttbs-horizontal';

    ob_start();
    ?>
    <style>
    .nttbs-search-banner-wrap {
        width: 100%;
        margin: 0 auto 30px auto;
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Inter", sans-serif;
        box-sizing: border-box;
    }
    .nttbs-search-form-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 14px;
        padding: 20px 24px;
        box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.06);
        display: grid;
        gap: 16px;
        align-items: flex-end;
        box-sizing: border-box;
    }
    .nttbs-search-form-card.nttbs-horizontal {
        grid-template-columns: 1.5fr 1fr 1fr 1fr auto;
    }
    .nttbs-search-form-card.nttbs-stacked {
        grid-template-columns: 1fr;
    }
    .nttbs-field-group {
        display: flex;
        flex-direction: column;
    }
    .nttbs-field-group label {
        font-size: 11.5px;
        font-weight: 800;
        text-transform: uppercase;
        color: #64748b;
        margin-bottom: 6px;
        letter-spacing: 0.5px;
    }
    .nttbs-form-input, 
    .nttbs-form-select {
        width: 100%;
        border: 1px solid #cbd5e1;
        border-radius: 8px;
        padding: 10px 14px;
        font-size: 13.5px;
        color: #0f172a;
        background: #f8fafc;
        outline: none;
        transition: all 0.15s ease;
        box-sizing: border-box;
        font-family: inherit;
    }
    .nttbs-form-input:focus, 
    .nttbs-form-select:focus {
        border-color: #3b82f6;
        background: #ffffff;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15);
    }
    .nttbs-search-submit-btn {
        background: linear-gradient(135deg, #3b82f6, #1d4ed8);
        color: #ffffff;
        border: none;
        border-radius: 8px;
        padding: 11px 24px;
        font-size: 13.5px;
        font-weight: 800;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        box-shadow: 0 4px 12px rgba(37, 99, 235, 0.25);
        transition: transform 0.15s ease, box-shadow 0.15s ease;
        height: 42px;
    }
    .nttbs-search-submit-btn:hover {
        transform: translateY(-1px);
        box-shadow: 0 6px 16px rgba(37, 99, 235, 0.35);
    }
    @media (max-width: 992px) {
        .nttbs-search-form-card.nttbs-horizontal { grid-template-columns: 1fr; }
        .nttbs-search-submit-btn { width: 100%; }
    }
    </style>

    <div class="nttbs-search-banner-wrap">
        <form method="get" action="<?php echo $target_action; ?>" class="nttbs-search-form-card <?php echo esc_attr($layout_style); ?>">
            <div class="nttbs-field-group">
                <label><?php esc_html_e('Expedition Keywords', 'nahian-tour-travel-booking-solution'); ?></label>
                <input type="text" name="nttbs_query" value="<?php echo esc_attr($search_query); ?>" class="nttbs-form-input" placeholder="<?php esc_attr_e('e.g. Coral Reef Cruise, Desert Safari...', 'nahian-tour-travel-booking-solution'); ?>">
            </div>

            <div class="nttbs-field-group">
                <label><?php esc_html_e('Destination Harbor', 'nahian-tour-travel-booking-solution'); ?></label>
                <select name="nttbs_dest" class="nttbs-form-select">
                    <option value="0"><?php esc_html_e('All Regions', 'nahian-tour-travel-booking-solution'); ?></option>
                    <?php foreach ($destinations as $d): ?>
                        <option value="<?php echo esc_attr($d['id']); ?>" <?php selected($selected_dest, $d['id']); ?>><?php echo esc_html($d['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="nttbs-field-group">
                <label><?php esc_html_e('Activity Style', 'nahian-tour-travel-booking-solution'); ?></label>
                <select name="nttbs_act" class="nttbs-form-select">
                    <option value="0"><?php esc_html_e('All Experiences', 'nahian-tour-travel-booking-solution'); ?></option>
                    <?php foreach ($activities as $a): ?>
                        <option value="<?php echo esc_attr($a['id']); ?>" <?php selected($selected_act, $a['id']); ?>><?php echo esc_html($a['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="nttbs-field-group">
                <label><?php esc_html_e('Departure Date', 'nahian-tour-travel-booking-solution'); ?></label>
                <input type="date" name="nttbs_date" value="<?php echo esc_attr($selected_date); ?>" class="nttbs-form-input">
            </div>

            <button type="submit" class="nttbs-search-submit-btn">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                <span><?php esc_html_e('Search', 'nahian-tour-travel-booking-solution'); ?></span>
            </button>
        </form>
    </div>
    <?php
    return ob_get_clean();
});
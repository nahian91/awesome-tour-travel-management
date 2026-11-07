<?php
/**
 * Plugin Name: Nahian Tour & Travel Booking Solution
 * Plugin URI:  https://github.com/abdullahnahian/nahian-tour-travel-booking-solution
 * Description: High-Performance Light ERP Architecture with Dynamic Spectrum Accents, Live Telemetry & Slide-over POS Drawer.
 * Version:     1.1.0
 * Author:      Abdullah Nahian
 * Text Domain: nahian-tour-travel-booking-solution
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) {
    exit;
}

/* ==========================================================================
   1. GLOBAL SYSTEM CONSTANTS & MODULE AUTOLOADER
   ========================================================================== */

define('NTTBS_VERSION', '1.1.0');
define('NTTBS_AUTHOR', 'Abdullah Nahian');
define('NTTBS_APP_NAME', 'Nahian Tour & Travel');
define('NTTBS_SLUG', 'nahian-tour-travel-booking-solution');
define('NTTBS_CURRENCY_DEFAULT', 'EUR');
define('NTTBS_CURRENCY_SYMBOL_DEFAULT', '€');
define('NTTBS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('NTTBS_PLUGIN_URL', plugin_dir_url(__FILE__));

// Load Database & Shared Repositories Layer
$nttbs_db_loader = NTTBS_PLUGIN_DIR . 'includes/db.php';
if (file_exists($nttbs_db_loader)) {
    require_once $nttbs_db_loader;
}

if (function_exists('nttbs_install_complete_database')) {
    register_activation_hook(__FILE__, 'nttbs_install_complete_database');
}

/* ==========================================================================
   2. INLINE SVG ICON SYSTEM
   ========================================================================== */

function nttbs_svg_icon($name, $width = 18, $height = 18, $class = '') {
    $width  = (int) $width;
    $height = (int) $height;
    $class  = esc_attr($class);

    $icons = [
        'dashboard' => '<svg width="' . $width . '" height="' . $height . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="' . $class . '"><rect x="3" y="3" width="7" height="9" rx="1.5"></rect><rect x="14" y="3" width="7" height="5" rx="1.5"></rect><rect x="14" y="12" width="7" height="9" rx="1.5"></rect><rect x="3" y="16" width="7" height="5" rx="1.5"></rect></svg>',
        'bookings'  => '<svg width="' . $width . '" height="' . $height . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="' . $class . '"><path d="M2 9a3 3 0 0 1 0 6v2a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-2a3 3 0 0 1 0-6V7a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2Z"></path><path d="M13 5v2"></path><path d="M13 17v2"></path><path d="M13 11v2"></path></svg>',
        'trips'     => '<svg width="' . $width . '" height="' . $height . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="' . $class . '"><polygon points="3 6 9 3 15 6 21 3 21 18 15 21 9 18 3 21"></polygon><line x1="9" y1="3" x2="9" y2="18"></line><line x1="15" y1="6" x2="15" y2="21"></line></svg>',
        'price_cat' => '<svg width="' . $width . '" height="' . $height . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="' . $class . '"><path d="M12 2H2v10l9.29 9.29c.94.94 2.48.94 3.42 0l6.58-6.58c.94-.94.94-2.48 0-3.42L12 2Z"></path><circle cx="7.5" cy="7.5" r="1.5" fill="currentColor"></circle></svg>',
        'dest'      => '<svg width="' . $width . '" height="' . $height . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="' . $class . '"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"></path><circle cx="12" cy="10" r="3"></circle></svg>',
        'activity'  => '<svg width="' . $width . '" height="' . $height . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="' . $class . '"><circle cx="12" cy="5" r="2"></circle><path d="m9 20 3-6 4 2 2-5"></path><path d="m6 8 6 2 5-3"></path><path d="m11 14-3 6"></path></svg>',
        'trip_type' => '<svg width="' . $width . '" height="' . $height . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="' . $class . '"><circle cx="12" cy="12" r="10"></circle><polygon points="16.24 7.76 14.12 14.12 7.76 16.24 9.88 9.88 16.24 7.76"></polygon></svg>',
        'diff'      => '<svg width="' . $width . '" height="' . $height . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="' . $class . '"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon></svg>',
        'customers' => '<svg width="' . $width . '" height="' . $height . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="' . $class . '"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M22 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>',
        'reports'   => '<svg width="' . $width . '" height="' . $height . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="' . $class . '"><line x1="12" y1="20" x2="12" y2="10"></line><line x1="18" y1="20" x2="18" y2="4"></line><line x1="6" y1="20" x2="6" y2="16"></line></svg>',
        'shortcode' => '<svg width="' . $width . '" height="' . $height . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="' . $class . '"><polyline points="16 18 22 12 16 6"></polyline><polyline points="8 6 2 12 8 18"></polyline></svg>',
        'settings'  => '<svg width="' . $width . '" height="' . $height . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="' . $class . '"><path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"></path><circle cx="12" cy="12" r="3"></circle></svg>',
        'signout'   => '<svg width="' . $width . '" height="' . $height . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="' . $class . '"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>',
        'close'     => '<svg width="' . $width . '" height="' . $height . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="' . $class . '"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>'
    ];

    return $icons[$name] ?? '';
}

/* ==========================================================================
   3. WORKSPACE MENU HOOK & STANDALONE INTERCEPTOR
   ========================================================================== */

function nttbs_register_admin_workspace() {
    add_menu_page(
        __('Tour & Travel ERP', 'nahian-tour-travel-booking-solution'),
        __('Tour & Travel ERP', 'nahian-tour-travel-booking-solution'),
        'manage_options',
        'nttbs-core',
        '__return_null',
        'dashicons-palmtree',
        2
    );
}
add_action('admin_menu', 'nttbs_register_admin_workspace');

function nttbs_execute_standalone_interceptor() {
    if (!is_admin() || !isset($_GET['page']) || sanitize_key($_GET['page']) !== 'nttbs-core') {
        return;
    }

    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('Unauthorized: Operational clearance required.', 'nahian-tour-travel-booking-solution'), 403);
    }

    nttbs_render_enterprise_workspace();
    exit;
}
add_action('admin_init', 'nttbs_execute_standalone_interceptor');

// Load all individual frontend shortcodes
$nttbs_shortcode_dir = NTTBS_PLUGIN_DIR . 'frontend/';
$nttbs_shortcode_files = [
    'tour-search-form.php',
    'tours-grid.php',
    'tour-single.php',
    'tour-checkout.php',
    'tour-thanks.php',
    'tour-login-registration.php',
    'tour-account.php',
];

foreach ($nttbs_shortcode_files as $file) {
    if (file_exists($nttbs_shortcode_dir . $file)) {
        require_once $nttbs_shortcode_dir . $file;
    }
}

/* ==========================================================================
   4. WORKSPACE RENDERER
   ========================================================================== */

function nttbs_render_enterprise_workspace() {
    global $wpdb;

    $current_view = isset($_GET['view']) ? sanitize_key($_GET['view']) : 'dashboard';
    
    $telemetry = function_exists('nttbs_get_financial_telemetry') 
        ? nttbs_get_financial_telemetry() 
        : ['revenue' => 0.00, 'orders' => 0];

    $currency = get_option('nttbs_currency_symbol', NTTBS_CURRENCY_SYMBOL_DEFAULT);

    $table_bookings = $wpdb->prefix . 'nttbs_bookings';
    $table_trips    = $wpdb->prefix . 'nttbs_trips';

    $badge_bookings = 0;
    $badge_trips    = 0;

    if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_bookings)) === $table_bookings) {
        $badge_bookings = (int) $wpdb->get_var("SELECT COUNT(id) FROM {$table_bookings} WHERE status = 'confirmed'");
    }
    if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_trips)) === $table_trips) {
        $badge_trips = (int) $wpdb->get_var("SELECT COUNT(id) FROM {$table_trips}");
    }
    ?>
    <!DOCTYPE html>
    <html lang="<?php echo esc_attr(get_locale()); ?>">
    <head>
        <meta charset="<?php bloginfo('charset'); ?>">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?php echo esc_html(NTTBS_APP_NAME); ?> — Operational Suite</title>
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
        
        <style>
            :root {
                --nttbs-bg-body: #f8fafc;
                --nttbs-bg-card: #ffffff;
                --nttbs-bg-hover: #f1f5f9;
                --nttbs-border: #e2e8f0;
                --nttbs-border-subtle: #f1f5f9;
                
                --nttbs-text-heading: #0f172a;
                --nttbs-text-body: #334155;
                --nttbs-text-muted: #64748b;
                
                --nttbs-primary: #3b82f6;
                --nttbs-primary-dark: #2563eb;
                --nttbs-primary-subtle: #eff6ff;
                --nttbs-primary-border: #bfdbfe;
                
                --nttbs-emerald: #10b981;
                --nttbs-emerald-subtle: #ecfdf5;
                --nttbs-emerald-border: #a7f3d0;
                
                --nttbs-amber: #f59e0b;
                --nttbs-amber-subtle: #fffbeb;
                --nttbs-amber-border: #fde68a;
                
                --nttbs-rose: #f43f5e;
                --nttbs-rose-subtle: #fff1f2;
                --nttbs-rose-border: #fecdd3;
                
                --nttbs-purple: #8b5cf6;
                --nttbs-purple-subtle: #f5f3ff;
                --nttbs-purple-border: #ddd6fe;

                --nttbs-teal: #14b8a6;
                --nttbs-teal-subtle: #f0fdfa;
                --nttbs-teal-border: #ccfbf1;

                --nttbs-shadow-xs: 0 1px 2px rgba(15, 23, 42, 0.04);
                --nttbs-shadow-sm: 0 2px 4px rgba(15, 23, 42, 0.04), 0 1px 2px rgba(15, 23, 42, 0.02);
                --nttbs-shadow-md: 0 8px 16px -4px rgba(15, 23, 42, 0.06), 0 2px 6px -1px rgba(15, 23, 42, 0.03);
                --nttbs-shadow-xl: 0 20px 25px -5px rgba(15, 23, 42, 0.1), 0 10px 10px -5px rgba(15, 23, 42, 0.04);
            }

            *, *::before, *::after { 
                box-sizing: border-box; 
                margin: 0; 
                padding: 0; 
            }

            * {
                scrollbar-width: thin;
                scrollbar-color: #cbd5e1 transparent;
            }
            ::-webkit-scrollbar { width: 6px; height: 6px; }
            ::-webkit-scrollbar-track { background: transparent; }
            ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 999px; }
            ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }

            html, body {
                width: 100vw;
                height: 100vh;
                overflow: hidden;
                background-color: var(--nttbs-bg-body);
                font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
                color: var(--nttbs-text-body);
                -webkit-font-smoothing: antialiased;
            }

            .nttbs-viewport {
                display: flex;
                width: 100vw;
                height: 100vh;
                overflow: hidden;
            }

            /* --- SIDEBAR --- */
            .nttbs-sidebar {
                width: 270px;
                background-color: var(--nttbs-bg-card);
                border-right: 1px solid var(--nttbs-border);
                display: flex;
                flex-direction: column;
                padding: 24px 16px;
                flex-shrink: 0;
                overflow-y: auto;
                box-shadow: 2px 0 10px rgba(0, 0, 0, 0.02);
            }

            .nttbs-brand-box {
                display: flex;
                align-items: center;
                gap: 12px;
                padding: 0 8px 20px 8px;
                border-bottom: 1px solid var(--nttbs-border);
                margin-bottom: 16px;
            }

            .nttbs-brand-logo {
                width: 40px;
                height: 40px;
                background: linear-gradient(135deg, var(--nttbs-primary), #1d4ed8);
                border-radius: 10px;
                display: flex;
                align-items: center;
                justify-content: center;
                color: #ffffff;
                box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
            }

            .nttbs-brand-title {
                font-size: 15px;
                font-weight: 800;
                color: var(--nttbs-text-heading);
                letter-spacing: -0.3px;
                line-height: 1.2;
            }

            .nttbs-brand-subtitle {
                font-size: 11px;
                font-weight: 600;
                color: var(--nttbs-text-muted);
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }

            .nttbs-nav-menu {
                list-style: none;
                display: flex;
                flex-direction: column;
                gap: 4px;
                flex-grow: 1;
            }

            .nttbs-nav-item a {
                display: flex;
                align-items: center;
                justify-content: space-between;
                padding: 10px 14px;
                border-radius: 8px;
                color: var(--nttbs-text-muted);
                text-decoration: none;
                font-size: 13.5px;
                font-weight: 600;
                transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
            }

            .nttbs-nav-item a:hover {
                background-color: var(--nttbs-bg-hover);
                color: var(--nttbs-text-heading);
                transform: translateX(3px);
            }

            .nttbs-nav-item.active a {
                background-color: var(--nttbs-primary-subtle);
                color: var(--nttbs-primary-dark);
                font-weight: 700;
                box-shadow: inset 3px 0 0 var(--nttbs-primary);
            }

            .nttbs-nav-label-group {
                display: flex;
                align-items: center;
                gap: 12px;
            }

            .nttbs-nav-chip {
                background-color: var(--nttbs-bg-hover);
                color: var(--nttbs-text-heading);
                font-size: 11px;
                font-weight: 700;
                padding: 2px 8px;
                border-radius: 999px;
            }

            .nttbs-nav-item.active .nttbs-nav-chip {
                background-color: var(--nttbs-primary);
                color: #ffffff;
            }

            .nttbs-nav-icon {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                width: 20px;
                height: 20px;
                transition: transform 0.2s ease;
            }

            .nttbs-nav-item a:hover .nttbs-nav-icon {
                transform: scale(1.08);
            }

            .nttbs-icon-dashboard { color: var(--nttbs-primary); }
            .nttbs-icon-bookings  { color: var(--nttbs-emerald); }
            .nttbs-icon-trips     { color: var(--nttbs-purple); }
            .nttbs-icon-price_cat { color: var(--nttbs-amber); }
            .nttbs-icon-dest      { color: var(--nttbs-rose); }
            .nttbs-icon-activity  { color: var(--nttbs-teal); }
            .nttbs-icon-trip_type { color: #d946ef; }
            .nttbs-icon-diff      { color: #ea580c; }
            .nttbs-icon-customers { color: #0284c7; }
            .nttbs-icon-reports   { color: #059669; }
            .nttbs-icon-shortcode { color: #6366f1; }
            .nttbs-icon-settings  { color: #64748b; }

            .nttbs-nav-item-signout {
                margin-top: auto;
                border-top: 1px solid var(--nttbs-border);
                padding-top: 12px;
            }

            .nttbs-nav-item-signout a {
                color: var(--nttbs-rose);
            }

            .nttbs-nav-item-signout a:hover {
                background-color: var(--nttbs-rose-subtle);
                color: var(--nttbs-rose);
            }

            /* --- MAIN VIEWPORT --- */
            .nttbs-main-stage {
                flex-grow: 1;
                height: 100vh;
                overflow-y: auto;
                background-color: var(--nttbs-bg-body);
                padding: 32px 40px;
            }

            /* --- HERO TELEMETRY --- */
            .nttbs-hero-telemetry {
                background-color: var(--nttbs-bg-card);
                border: 1px solid var(--nttbs-border);
                border-left: 5px solid var(--nttbs-primary);
                border-radius: 14px;
                padding: 24px 28px;
                display: flex;
                align-items: center;
                justify-content: space-between;
                margin-bottom: 24px;
                box-shadow: var(--nttbs-shadow-sm);
            }

            .nttbs-hero-badge {
                display: inline-flex;
                align-items: center;
                gap: 6px;
                background-color: var(--nttbs-emerald-subtle);
                color: var(--nttbs-emerald);
                border: 1px solid var(--nttbs-emerald-border);
                padding: 3px 9px;
                border-radius: 999px;
                font-size: 11px;
                font-weight: 700;
                margin-bottom: 8px;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }

            .nttbs-hero-badge::before {
                content: '';
                width: 6px;
                height: 6px;
                background: var(--nttbs-emerald);
                border-radius: 50%;
                animation: nttbsPulse 1.8s infinite;
            }

            @keyframes nttbsPulse {
                0%, 100% { opacity: 1; transform: scale(1); }
                50% { opacity: 0.4; transform: scale(0.85); }
            }

            .nttbs-hero-title {
                font-size: 20px;
                font-weight: 800;
                color: var(--nttbs-text-heading);
                letter-spacing: -0.3px;
                margin-bottom: 4px;
            }

            .nttbs-hero-description {
                font-size: 13px;
                color: var(--nttbs-text-muted);
            }

            .nttbs-hero-clock-box {
                background: linear-gradient(135deg, var(--nttbs-primary-subtle), #ffffff);
                border: 1px solid var(--nttbs-primary-border);
                padding: 12px 20px;
                border-radius: 12px;
                text-align: right;
                box-shadow: var(--nttbs-shadow-xs);
            }

            .nttbs-hero-clock {
                font-family: 'JetBrains Mono', monospace;
                font-size: 24px;
                font-weight: 700;
                color: var(--nttbs-primary-dark);
                line-height: 1.1;
            }

            .nttbs-hero-clock-label {
                font-size: 11px;
                font-weight: 700;
                color: var(--nttbs-text-muted);
                text-transform: uppercase;
                letter-spacing: 0.6px;
            }

            /* --- KPI METRIC CARDS --- */
            .nttbs-kpi-deck {
                display: grid;
                grid-template-columns: repeat(4, 1fr);
                gap: 20px;
                margin-bottom: 28px;
            }

            .nttbs-kpi-card {
                background-color: var(--nttbs-bg-card);
                border: 1px solid var(--nttbs-border);
                border-radius: 14px;
                padding: 20px 22px;
                display: flex;
                justify-content: space-between;
                align-items: center;
                box-shadow: var(--nttbs-shadow-sm);
                transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
                position: relative;
                overflow: hidden;
            }

            .nttbs-kpi-card::after {
                content: '';
                position: absolute;
                top: 0; left: 0; right: 0;
                height: 3px;
            }

            .nttbs-kpi-blue::after    { background: var(--nttbs-primary); }
            .nttbs-kpi-emerald::after { background: var(--nttbs-emerald); }
            .nttbs-kpi-amber::after   { background: var(--nttbs-amber); }
            .nttbs-kpi-rose::after    { background: var(--nttbs-rose); }

            .nttbs-kpi-card:hover {
                transform: translateY(-3px);
                box-shadow: var(--nttbs-shadow-md);
            }

            .nttbs-kpi-title {
                font-size: 11.5px;
                font-weight: 700;
                color: var(--nttbs-text-muted);
                text-transform: uppercase;
                letter-spacing: 0.5px;
                margin-bottom: 8px;
            }

            .nttbs-kpi-value {
                font-size: 24px;
                font-weight: 800;
                color: var(--nttbs-text-heading);
                letter-spacing: -0.5px;
                font-family: 'JetBrains Mono', monospace;
            }

            .nttbs-kpi-icon-box {
                width: 48px;
                height: 48px;
                border-radius: 12px;
                display: flex;
                align-items: center;
                justify-content: center;
            }

            .nttbs-kpi-blue .nttbs-kpi-icon-box    { background-color: var(--nttbs-primary-subtle); color: var(--nttbs-primary); }
            .nttbs-kpi-emerald .nttbs-kpi-icon-box { background-color: var(--nttbs-emerald-subtle); color: var(--nttbs-emerald); }
            .nttbs-kpi-amber .nttbs-kpi-icon-box   { background-color: var(--nttbs-amber-subtle); color: var(--nttbs-amber); }
            .nttbs-kpi-rose .nttbs-kpi-icon-box    { background-color: var(--nttbs-rose-subtle); color: var(--nttbs-rose); }

            /* --- FORM CONTROLS & UI ELEMENTS --- */
            .nttbs-control-input {
                width: 100%;
                padding: 10px 14px;
                border: 1px solid var(--nttbs-border);
                border-radius: 10px;
                font-size: 13.5px;
                font-family: inherit;
                color: var(--nttbs-text-heading);
                background-color: var(--nttbs-bg-card);
                transition: all 0.15s ease;
            }

            .nttbs-control-input:focus {
                outline: none;
                border-color: var(--nttbs-primary);
                box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15);
            }

            .nttbs-field-row {
                margin-bottom: 16px;
            }

            .nttbs-field-label {
                display: block;
                font-size: 12px;
                font-weight: 700;
                color: var(--nttbs-text-heading);
                margin-bottom: 6px;
            }

            .nttbs-btn-primary {
                background: linear-gradient(135deg, var(--nttbs-primary), var(--nttbs-primary-dark));
                color: #ffffff;
                border: none;
                padding: 12px 20px;
                border-radius: 10px;
                font-size: 13.5px;
                font-weight: 700;
                cursor: pointer;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                gap: 8px;
                box-shadow: 0 2px 6px rgba(37, 99, 235, 0.25);
                transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
            }

            .nttbs-btn-primary:hover {
                transform: translateY(-1px);
                box-shadow: 0 4px 12px rgba(37, 99, 235, 0.35);
            }

            .nttbs-btn-primary:active {
                transform: translateY(0);
            }

            /* --- SLIDE-OVER POS DRAWER --- */
            .nttbs-modal-backdrop {
                position: fixed;
                top: 0; left: 0; width: 100vw; height: 100vh;
                background-color: rgba(15, 23, 42, 0.45);
                backdrop-filter: blur(4px);
                z-index: 99999;
                display: none;
                justify-content: flex-end;
                transition: opacity 0.25s ease;
            }

            .nttbs-modal-backdrop.active {
                display: flex;
            }

            .nttbs-drawer-panel {
                width: 460px;
                height: 100vh;
                background: var(--nttbs-bg-card);
                border-left: 1px solid var(--nttbs-border);
                box-shadow: var(--nttbs-shadow-xl);
                display: flex;
                flex-direction: column;
                animation: nttbsSlideIn 0.3s cubic-bezier(0.16, 1, 0.3, 1) forwards;
            }

            @keyframes nttbsSlideIn {
                from { transform: translateX(100%); }
                to { transform: translateX(0); }
            }

            .nttbs-drawer-header {
                padding: 22px 28px;
                border-bottom: 1px solid var(--nttbs-border);
                display: flex;
                justify-content: space-between;
                align-items: center;
                background: linear-gradient(180deg, var(--nttbs-primary-subtle), var(--nttbs-bg-card));
            }

            .nttbs-drawer-body {
                padding: 28px;
                overflow-y: auto;
                flex-grow: 1;
            }

            .nttbs-drawer-close {
                background: transparent;
                border: none;
                cursor: pointer;
                color: var(--nttbs-text-muted);
                width: 34px;
                height: 34px;
                border-radius: 8px;
                display: flex;
                align-items: center;
                justify-content: center;
                transition: all 0.15s ease;
            }

            .nttbs-drawer-close:hover {
                background-color: var(--nttbs-bg-hover);
                color: var(--nttbs-text-heading);
            }

            .nttbs-pricing-preview {
                background: linear-gradient(135deg, var(--nttbs-primary-subtle), #ffffff);
                border: 1px solid var(--nttbs-primary-border);
                border-radius: 12px;
                padding: 16px 18px;
                margin: 20px 0;
            }
        </style>
    </head>
    <body>
        <div class="nttbs-viewport">
            <!-- Sidebar -->
            <aside class="nttbs-sidebar">
                <div class="nttbs-brand-box">
                    <div class="nttbs-brand-logo">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="10"></circle>
                            <polygon points="12 2 15 9 12 7 9 9 12 2" fill="currentColor"></polygon>
                            <path d="M12 22v-4"></path>
                            <path d="M2 12h4"></path>
                            <path d="M18 12h4"></path>
                            <circle cx="12" cy="12" r="2" fill="currentColor"></circle>
                        </svg>
                    </div>
                    <div>
                        <div class="nttbs-brand-title"><?php echo esc_html(NTTBS_APP_NAME); ?></div>
                        <div class="nttbs-brand-subtitle"><?php esc_html_e('ERP Architecture', 'nahian-tour-travel-booking-solution'); ?></div>
                    </div>
                </div>

                <ul class="nttbs-nav-menu">
                    <?php
                    $nav_items = [
                        'dashboard'        => [__('Dashboard', 'nahian-tour-travel-booking-solution'), 'dashboard', null, 'nttbs-icon-dashboard'],
                        'bookings'         => [__('Bookings', 'nahian-tour-travel-booking-solution'), 'bookings', $badge_bookings, 'nttbs-icon-bookings'],
                        'trips'            => [__('Trips', 'nahian-tour-travel-booking-solution'), 'trips', $badge_trips, 'nttbs-icon-trips'],
                        'price_categories' => [__('Price Categories', 'nahian-tour-travel-booking-solution'), 'price_cat', null, 'nttbs-icon-price_cat'],
                        'destination'      => [__('Destinations', 'nahian-tour-travel-booking-solution'), 'dest', null, 'nttbs-icon-dest'],
                        'activity'         => [__('Activities', 'nahian-tour-travel-booking-solution'), 'activity', null, 'nttbs-icon-activity'],
                        'trip_type'        => [__('Trip Type', 'nahian-tour-travel-booking-solution'), 'trip_type', null, 'nttbs-icon-trip_type'],
                        'difficulty'       => [__('Difficulty', 'nahian-tour-travel-booking-solution'), 'diff', null, 'nttbs-icon-diff'],
                        'customers'        => [__('Customers', 'nahian-tour-travel-booking-solution'), 'customers', null, 'nttbs-icon-customers'],
                        'reports'          => [__('Reports', 'nahian-tour-travel-booking-solution'), 'reports', null, 'nttbs-icon-reports'],
                        'shortcodes'       => [__('Shortcodes', 'nahian-tour-travel-booking-solution'), 'shortcode', null, 'nttbs-icon-shortcode'],
                        'settings'         => [__('Settings', 'nahian-tour-travel-booking-solution'), 'settings', null, 'nttbs-icon-settings'],
                    ];

                    foreach ($nav_items as $slug => $meta) :
                        $is_active = ($current_view === $slug);
                    ?>
                        <li class="nttbs-nav-item <?php echo $is_active ? 'active' : ''; ?>">
                            <a href="<?php echo esc_url(admin_url('admin.php?page=nttbs-core&view=' . $slug)); ?>">
                                <span class="nttbs-nav-label-group">
                                    <span class="nttbs-nav-icon <?php echo esc_attr($meta[3]); ?>">
                                        <?php echo nttbs_svg_icon($meta[1]); ?>
                                    </span>
                                    <span><?php echo esc_html($meta[0]); ?></span>
                                </span>
                                <?php if ($meta[2] !== null) : ?>
                                    <span class="nttbs-nav-chip"><?php echo esc_html($meta[2]); ?></span>
                                <?php endif; ?>
                            </a>
                        </li>
                    <?php endforeach; ?>

                    <li class="nttbs-nav-item nttbs-nav-item-signout">
                        <a href="<?php echo esc_url(wp_logout_url(admin_url())); ?>">
                            <span class="nttbs-nav-label-group">
                                <span class="nttbs-nav-icon"><?php echo nttbs_svg_icon('signout'); ?></span>
                                <span><?php esc_html_e('Sign Out', 'nahian-tour-travel-booking-solution'); ?></span>
                            </span>
                        </a>
                    </li>
                </ul>
            </aside>

            <!-- Main Stage -->
            <main class="nttbs-main-stage">
                <?php if ($current_view === 'dashboard') : ?>
                    <!-- Header Live Telemetry (Dashboard Only) -->
                    <header class="nttbs-hero-telemetry">
                        <div>
                            <div class="nttbs-hero-badge"><?php esc_html_e('Live Manifest Telemetry', 'nahian-tour-travel-booking-solution'); ?></div>
                            <h1 class="nttbs-hero-title"><?php esc_html_e('Operations & Dispatch Console', 'nahian-tour-travel-booking-solution'); ?></h1>
                            <p class="nttbs-hero-description"><?php esc_html_e('Live tracking, ticket issuance, and inventory reservations.', 'nahian-tour-travel-booking-solution'); ?></p>
                        </div>
                        <div class="nttbs-hero-clock-box">
                            <div class="nttbs-hero-clock" id="nttbs-live-clock">--:--:--</div>
                            <div class="nttbs-hero-clock-label"><?php esc_html_e('Operational UTC', 'nahian-tour-travel-booking-solution'); ?></div>
                        </div>
                    </header>

                    <!-- KPI Deck (Dashboard Only) -->
                    <section class="nttbs-kpi-deck">
                        <div class="nttbs-kpi-card nttbs-kpi-blue">
                            <div>
                                <div class="nttbs-kpi-title"><?php esc_html_e('Active Bookings', 'nahian-tour-travel-booking-solution'); ?></div>
                                <div class="nttbs-kpi-value"><?php echo esc_html($badge_bookings); ?></div>
                            </div>
                            <div class="nttbs-kpi-icon-box">
                                <?php echo nttbs_svg_icon('bookings', 22, 22); ?>
                            </div>
                        </div>

                        <div class="nttbs-kpi-card nttbs-kpi-emerald">
                            <div>
                                <div class="nttbs-kpi-title"><?php esc_html_e('Catalog Inventory', 'nahian-tour-travel-booking-solution'); ?></div>
                                <div class="nttbs-kpi-value"><?php echo esc_html($badge_trips); ?></div>
                            </div>
                            <div class="nttbs-kpi-icon-box">
                                <?php echo nttbs_svg_icon('trips', 22, 22); ?>
                            </div>
                        </div>

                        <div class="nttbs-kpi-card nttbs-kpi-amber">
                            <div>
                                <div class="nttbs-kpi-title"><?php esc_html_e('Total Orders', 'nahian-tour-travel-booking-solution'); ?></div>
                                <div class="nttbs-kpi-value"><?php echo esc_html($telemetry['orders'] ?? 0); ?></div>
                            </div>
                            <div class="nttbs-kpi-icon-box">
                                <?php echo nttbs_svg_icon('price_cat', 22, 22); ?>
                            </div>
                        </div>

                        <div class="nttbs-kpi-card nttbs-kpi-rose">
                            <div>
                                <div class="nttbs-kpi-title"><?php esc_html_e('Gross Telemetry', 'nahian-tour-travel-booking-solution'); ?></div>
                                <div class="nttbs-kpi-value"><?php echo esc_html($currency . ' ' . number_format((float)($telemetry['revenue'] ?? 0), 2)); ?></div>
                            </div>
                            <div class="nttbs-kpi-icon-box">
                                <?php echo nttbs_svg_icon('reports', 22, 22); ?>
                            </div>
                        </div>
                    </section>
                <?php endif; ?>

                <!-- Sub-Module Injection -->
                <?php
                $allowed_modules = [
                    'dashboard'        => 'dashboard.php',
                    'bookings'         => 'bookings.php',
                    'trips'            => 'trips.php',
                    'price_categories' => 'price-categories.php',
                    'destination'      => 'destination.php',
                    'activity'         => 'activity.php',
                    'trip_type'        => 'trip-type.php',
                    'difficulty'       => 'difficulty.php',
                    'customers'        => 'customers.php',
                    'reports'          => 'reports.php',
                    'shortcodes'       => 'shortcodes.php',
                    'settings'         => 'settings.php'
                ];

                $target_file = $allowed_modules[$current_view] ?? 'dashboard.php';
                $target_module = NTTBS_PLUGIN_DIR . 'modules/' . $target_file;

                if (file_exists($target_module)) {
                    include $target_module;
                } else {
                    echo '<div style="background:#fff1f2; border:1px solid #fecdd3; color:#e11d48; padding:18px; border-radius:12px; font-weight:700;">' . 
                         sprintf(esc_html__('Module file missing: %s', 'nahian-tour-travel-booking-solution'), esc_html($target_file)) . 
                         '</div>';
                }
                ?>
            </main>
        </div>

        <!-- Slide-over POS Modal Drawer -->
        <div class="nttbs-modal-backdrop" id="nttbs-pos-drawer" role="dialog" aria-modal="true">
            <div class="nttbs-drawer-panel">
                <div class="nttbs-drawer-header">
                    <div>
                        <h3 style="font-size:16px; font-weight:800; color:var(--nttbs-primary-dark);"><?php esc_html_e('Dispatch Tour Manifest', 'nahian-tour-travel-booking-solution'); ?></h3>
                        <small style="color:var(--nttbs-text-muted);" id="nttbs-drawer-subtitle"><?php esc_html_e('Direct Checkout Module', 'nahian-tour-travel-booking-solution'); ?></small>
                    </div>
                    <button type="button" class="nttbs-drawer-close" onclick="nttbsCloseDrawer()" aria-label="<?php esc_attr_e('Close Drawer', 'nahian-tour-travel-booking-solution'); ?>">
                        <?php echo nttbs_svg_icon('close', 18, 18); ?>
                    </button>
                </div>

                <div class="nttbs-drawer-body">
                    <form method="post" action="<?php echo esc_url(admin_url('admin.php?page=nttbs-core&view=dashboard')); ?>">
                        <?php wp_nonce_field('nttbs_modal_booking_nonce', 'nttbs_booking_token'); ?>
                        <input type="hidden" name="departure_id" id="nttbs-drawer-departure-id">

                        <div class="nttbs-field-row">
                            <label class="nttbs-field-label"><?php esc_html_e('Lead Passenger Name *', 'nahian-tour-travel-booking-solution'); ?></label>
                            <input type="text" name="customer_name" required class="nttbs-control-input" placeholder="e.g. John Doe">
                        </div>

                        <div class="nttbs-field-row">
                            <label class="nttbs-field-label"><?php esc_html_e('Email Address *', 'nahian-tour-travel-booking-solution'); ?></label>
                            <input type="email" name="customer_email" required class="nttbs-control-input" placeholder="passenger@domain.com">
                        </div>

                        <div class="nttbs-field-row">
                            <label class="nttbs-field-label"><?php esc_html_e('Contact Phone', 'nahian-tour-travel-booking-solution'); ?></label>
                            <input type="text" name="customer_phone" class="nttbs-control-input" placeholder="+1 234 567 8900">
                        </div>

                        <div class="nttbs-field-row" style="display:flex; gap:14px;">
                            <div style="flex:1;">
                                <label class="nttbs-field-label"><?php esc_html_e('Pax Count', 'nahian-tour-travel-booking-solution'); ?></label>
                                <input type="number" name="pax_count" id="nttbs-drawer-pax-count" min="1" max="50" value="1" class="nttbs-control-input" oninput="nttbsUpdateDrawerCalculation()">
                            </div>
                            <div style="flex:1.2;">
                                <label class="nttbs-field-label"><?php esc_html_e('Payment Gateway', 'nahian-tour-travel-booking-solution'); ?></label>
                                <select name="payment_method" class="nttbs-control-input">
                                    <option value="Counter Cash"><?php esc_html_e('Counter Cash', 'nahian-tour-travel-booking-solution'); ?></option>
                                    <option value="Bank Card"><?php esc_html_e('Terminal Card', 'nahian-tour-travel-booking-solution'); ?></option>
                                    <option value="Online Portal"><?php esc_html_e('Online Portal', 'nahian-tour-travel-booking-solution'); ?></option>
                                </select>
                            </div>
                        </div>

                        <div class="nttbs-pricing-preview">
                            <div style="display:flex; justify-content:space-between; margin-bottom:8px; font-size:12.5px; color:var(--nttbs-text-muted);">
                                <span><?php esc_html_e('Unit Tariff:', 'nahian-tour-travel-booking-solution'); ?></span>
                                <strong id="nttbs-drawer-unit-price" style="color:var(--nttbs-primary-dark); font-family:'JetBrains Mono', monospace;">€ 0.00</strong>
                            </div>
                            <div style="display:flex; justify-content:space-between; font-size:15px; font-weight:800; color:var(--nttbs-text-heading);">
                                <span><?php esc_html_e('Total Payable:', 'nahian-tour-travel-booking-solution'); ?></span>
                                <strong id="nttbs-drawer-total-price" style="color:var(--nttbs-primary-dark); font-family:'JetBrains Mono', monospace; font-size:17px;">€ 0.00</strong>
                            </div>
                        </div>

                        <button type="submit" name="nttbs_dispatch_modal_booking" class="nttbs-btn-primary" style="width:100%;">
                            <?php esc_html_e('Confirm & Issue Tour Voucher', 'nahian-tour-travel-booking-solution'); ?>
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <script>
        function nttbsTickClock() {
            const date = new Date();
            const hrs = String(date.getHours()).padStart(2, '0');
            const mins = String(date.getMinutes()).padStart(2, '0');
            const secs = String(date.getSeconds()).padStart(2, '0');
            const target = document.getElementById('nttbs-live-clock');
            if (target) target.textContent = `${hrs}:${mins}:${secs}`;
        }
        setInterval(nttbsTickClock, 1000);
        nttbsTickClock();

        let nttbsDrawerBasePrice = 0;
        const nttbsCurrencySymbol = <?php echo json_encode($currency); ?>;

        function nttbsOpenDrawer(depId, tripTitle, price) {
            nttbsDrawerBasePrice = parseFloat(price) || 0;
            const depInput = document.getElementById('nttbs-drawer-departure-id');
            const subTitle = document.getElementById('nttbs-drawer-subtitle');
            const unitPrice = document.getElementById('nttbs-drawer-unit-price');
            const paxCount = document.getElementById('nttbs-drawer-pax-count');
            const drawer = document.getElementById('nttbs-pos-drawer');

            if (depInput) depInput.value = depId;
            if (subTitle) subTitle.textContent = tripTitle;
            if (unitPrice) unitPrice.textContent = `${nttbsCurrencySymbol} ${nttbsDrawerBasePrice.toFixed(2)}`;
            if (paxCount) paxCount.value = 1;

            nttbsUpdateDrawerCalculation();
            if (drawer) drawer.classList.add('active');
        }

        function nttbsCloseDrawer() {
            const drawer = document.getElementById('nttbs-pos-drawer');
            if (drawer) drawer.classList.remove('active');
        }

        function nttbsUpdateDrawerCalculation() {
            const countInput = document.getElementById('nttbs-drawer-pax-count');
            const totalDisplay = document.getElementById('nttbs-drawer-total-price');
            const count = parseInt(countInput ? countInput.value : 1, 10) || 1;
            const total = (nttbsDrawerBasePrice * count).toFixed(2);
            if (totalDisplay) {
                totalDisplay.textContent = `${nttbsCurrencySymbol} ${total}`;
            }
        }

        // Close drawer on backdrop click
        document.addEventListener('DOMContentLoaded', function() {
            const backdrop = document.getElementById('nttbs-pos-drawer');
            if (backdrop) {
                backdrop.addEventListener('click', function(e) {
                    if (e.target === backdrop) {
                        nttbsCloseDrawer();
                    }
                });
            }
        });
        </script>
    </body>
    </html>
    <?php
}
<?php
/**
 * Plugin Name: Nahian Tour & Travel Booking Solution
 * Plugin URI:  https://github.com/abdullahnahian/nahian-tour-travel-booking-solution
 * Description: Vibrant Multi-Color Light ERP Architecture with Dynamic Spectrum Accents, Live Manifest Telemetry & Slide-over POS Drawer.
 * Version:     1.0.0
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

define('NTTBS_VERSION', '1.0.0');
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
        'dashboard' => '<svg width="' . $width . '" height="' . $height . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="' . $class . '"><rect x="3" y="3" width="7" height="9" rx="1"></rect><rect x="14" y="3" width="7" height="5" rx="1"></rect><rect x="14" y="12" width="7" height="9" rx="1"></rect><rect x="3" y="16" width="7" height="5" rx="1"></rect></svg>',
        'bookings'  => '<svg width="' . $width . '" height="' . $height . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="' . $class . '"><path d="M2 9a3 3 0 0 1 0 6v2a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-2a3 3 0 0 1 0-6V7a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2Z"></path><path d="M13 5v2"></path><path d="M13 17v2"></path><path d="M13 11v2"></path></svg>',
        'trips'     => '<svg width="' . $width . '" height="' . $height . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="' . $class . '"><polygon points="3 6 9 3 15 6 21 3 21 18 15 21 9 18 3 21"></polygon><line x1="9" y1="3" x2="9" y2="18"></line><line x1="15" y1="6" x2="15" y2="21"></line></svg>',
        'price_cat' => '<svg width="' . $width . '" height="' . $height . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="' . $class . '"><path d="M12 2H2v10l9.29 9.29c.94.94 2.48.94 3.42 0l6.58-6.58c.94-.94.94-2.48 0-3.42L12 2Z"></path><path d="M7 7h.01"></path></svg>',
        'dest'      => '<svg width="' . $width . '" height="' . $height . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="' . $class . '"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"></path><circle cx="12" cy="10" r="3"></circle></svg>',
        'activity'  => '<svg width="' . $width . '" height="' . $height . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="' . $class . '"><circle cx="12" cy="5" r="2"></circle><path d="m9 20 3-6 4 2 2-5"></path><path d="m6 8 6 2 5-3"></path><path d="m11 14-3 6"></path></svg>',
        'trip_type' => '<svg width="' . $width . '" height="' . $height . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="' . $class . '"><circle cx="12" cy="12" r="10"></circle><polygon points="16.24 7.76 14.12 14.12 7.76 16.24 9.88 9.88 16.24 7.76"></polygon></svg>',
        'diff'      => '<svg width="' . $width . '" height="' . $height . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="' . $class . '"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon></svg>',
        'customers' => '<svg width="' . $width . '" height="' . $height . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="' . $class . '"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M22 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>',
        'reports'   => '<svg width="' . $width . '" height="' . $height . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="' . $class . '"><line x1="12" y1="20" x2="12" y2="10"></line><line x1="18" y1="20" x2="18" y2="4"></line><line x1="6" y1="20" x2="6" y2="16"></line></svg>',
        'shortcode' => '<svg width="' . $width . '" height="' . $height . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="' . $class . '"><polyline points="16 18 22 12 16 6"></polyline><polyline points="8 6 2 12 8 18"></polyline></svg>',
        'settings'  => '<svg width="' . $width . '" height="' . $height . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="' . $class . '"><path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"></path><circle cx="12" cy="12" r="3"></circle></svg>',
        'signout'   => '<svg width="' . $width . '" height="' . $height . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="' . $class . '"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>',
        'wallet'    => '<svg width="' . $width . '" height="' . $height . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="' . $class . '"><rect x="2" y="4" width="20" height="16" rx="2"></rect><line x1="2" y1="10" x2="22" y2="10"></line></svg>',
        'clock'     => '<svg width="' . $width . '" height="' . $height . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="' . $class . '"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>',
        'clipboard' => '<svg width="' . $width . '" height="' . $height . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="' . $class . '"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"></path><rect x="8" y="2" width="8" height="4" rx="1" ry="1"></rect></svg>',
        'compass'   => '<svg width="' . $width . '" height="' . $height . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="' . $class . '"><circle cx="12" cy="12" r="10"></circle><polygon points="16.24 7.76 14.12 14.12 7.76 16.24 9.88 9.88 16.24 7.76"></polygon></svg>',
        'check'     => '<svg width="' . $width . '" height="' . $height . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="' . $class . '"><polyline points="20 6 9 17 4 12"></polyline></svg>',
        'close'     => '<svg width="' . $width . '" height="' . $height . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="' . $class . '"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>',
        'sun'       => '<svg width="' . $width . '" height="' . $height . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="' . $class . '"><circle cx="12" cy="12" r="5"></circle><line x1="12" y1="1" x2="12" y2="3"></line><line x1="12" y1="21" x2="12" y2="23"></line><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line><line x1="1" y1="12" x2="3" y2="12"></line><line x1="21" y1="12" x2="23" y2="12"></line><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line></svg>',
        'moon'      => '<svg width="' . $width . '" height="' . $height . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="' . $class . '"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path></svg>'
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
        <style>
            :root {
                --bg-base: #f8fafc;
                --bg-surface: #ffffff;
                --bg-subtle: #f1f5f9;
                --border-light: #e2e8f0;
                --text-main: #0f172a;
                --text-muted: #64748b;
                
                --c-blue: #2563eb;
                --c-blue-bg: #eff6ff;
                --c-blue-border: #bfdbfe;
                
                --c-emerald: #059669;
                --c-emerald-bg: #ecfdf5;
                --c-emerald-border: #a7f3d0;
                
                --c-amber: #d97706;
                --c-amber-bg: #fffbeb;
                --c-amber-border: #fde68a;
                
                --c-purple: #7c3aed;
                --c-purple-bg: #f5f3ff;
                --c-purple-border: #ddd6fe;
                
                --c-rose: #e11d48;
                --c-rose-bg: #fff1f2;
                --c-rose-border: #fecdd3;

                --c-teal: #0d9488;
                --c-teal-bg: #f0fdfa;
                --c-teal-border: #99f6e4;

                --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
                --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.07), 0 2px 4px -2px rgba(0, 0, 0, 0.07);
            }

            /* Dark Theme Variables & Adjustments */
            :root[data-theme="dark"] {
                --bg-base: #0b1120;
                --bg-surface: #1e293b;
                --bg-subtle: #334155;
                --border-light: #334155;
                --text-main: #f8fafc;
                --text-muted: #94a3b8;

                --c-blue-bg: rgba(37, 99, 235, 0.15);
                --c-blue-border: #1d4ed8;

                --c-emerald-bg: rgba(5, 150, 105, 0.15);
                --c-emerald-border: #047857;

                --c-amber-bg: rgba(217, 119, 6, 0.15);
                --c-amber-border: #b45309;

                --c-purple-bg: rgba(124, 58, 237, 0.15);
                --c-purple-border: #6d28d9;

                --c-rose-bg: rgba(225, 29, 72, 0.15);
                --c-rose-border: #be123c;

                --c-teal-bg: rgba(13, 148, 136, 0.15);
                --c-teal-border: #0f766e;

                scrollbar-color: #475569 transparent;
            }

            *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

            * {
                scrollbar-width: thin;
                scrollbar-color: #cbd5e1 transparent;
            }
            ::-webkit-scrollbar {
                width: 5px;
                height: 5px;
            }
            ::-webkit-scrollbar-track {
                background: transparent;
            }
            ::-webkit-scrollbar-thumb {
                background: #cbd5e1;
                border-radius: 4px;
            }
            ::-webkit-scrollbar-thumb:hover {
                background: #94a3b8;
            }

            :root[data-theme="dark"] ::-webkit-scrollbar-thumb {
                background: #475569;
            }
            :root[data-theme="dark"] ::-webkit-scrollbar-thumb:hover {
                background: #64748b;
            }

            html, body {
                width: 100vw;
                height: 100vh;
                overflow: hidden;
                background-color: var(--bg-base);
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Inter", sans-serif;
                color: var(--text-main);
                -webkit-font-smoothing: antialiased;
                transition: background-color 0.2s ease, color 0.2s ease;
            }

            .nte-viewport { display: flex; width: 100vw; height: 100vh; overflow: hidden; }

            .nte-sidebar {
                width: 265px;
                background-color: var(--bg-surface);
                border-right: 1px solid var(--border-light);
                display: flex;
                flex-direction: column;
                padding: 22px 16px;
                flex-shrink: 0;
                overflow-y: auto;
                transition: background-color 0.2s ease, border-color 0.2s ease;
            }

            .nte-brand-box {
                display: flex;
                align-items: center;
                gap: 12px;
                padding: 0 4px 18px 4px;
                border-bottom: 1px solid var(--border-light);
                margin-bottom: 16px;
            }
            .nte-brand-logo {
                width: 38px;
                height: 38px;
                background-color: var(--c-blue);
                border-radius: 10px;
                display: flex;
                align-items: center;
                justify-content: center;
                color: #ffffff;
                box-shadow: 0 4px 10px rgba(37, 99, 235, 0.25);
            }
            .nte-brand-title {
                font-size: 15px;
                font-weight: 800;
                color: var(--text-main);
                letter-spacing: -0.2px;
                line-height: 1.2;
            }

            .nte-nav-menu { list-style: none; display: flex; flex-direction: column; gap: 4px; flex-grow: 1; }
            .nte-nav-item a,
            .nte-nav-item button {
                display: flex;
                align-items: center;
                justify-content: space-between;
                padding: 10px 14px;
                border-radius: 8px;
                color: var(--text-muted);
                text-decoration: none;
                font-size: 13px;
                font-weight: 600;
                transition: all 0.15s ease;
                width: 100%;
                background: transparent;
                border: none;
                cursor: pointer;
                font-family: inherit;
                text-align: left;
            }
            .nte-nav-item a:hover,
            .nte-nav-item button:hover { 
                background-color: var(--bg-subtle); 
                color: var(--text-main); 
                transform: translateX(2px);
            }
            .nte-nav-item.active a {
                background-color: var(--c-blue-bg);
                color: var(--c-blue);
                font-weight: 700;
                box-shadow: inset 3px 0 0 var(--c-blue);
            }
            .nte-nav-label-group {
                display: flex;
                align-items: center;
                gap: 10px;
            }
            .nte-nav-chip {
                background-color: var(--bg-subtle);
                color: var(--text-main);
                font-size: 11px;
                font-weight: 700;
                padding: 2px 7px;
                border-radius: 999px;
            }
            .nte-nav-item.active .nte-nav-chip {
                background-color: var(--c-blue);
                color: #ffffff;
            }
            
            .nte-nav-item-theme {
                margin-top: auto;
                border-top: 1px solid var(--border-light);
                padding-top: 8px;
            }
            .nte-nav-item-theme button {
                color: var(--text-muted);
            }

            .nte-nav-item-signout a {
                color: var(--c-rose);
            }
            .nte-nav-item-signout a:hover {
                background-color: var(--c-rose-bg);
                color: var(--c-rose);
            }
            .nte-nav-icon { display: inline-flex; align-items: center; justify-content: center; width: 18px; height: 18px; }

            .icon-dashboard { color: var(--c-blue); }
            .icon-bookings { color: var(--c-emerald); }
            .icon-trips { color: var(--c-purple); }
            .icon-price_categories { color: var(--c-amber); }
            .icon-destination { color: var(--c-rose); }
            .icon-activity { color: var(--c-teal); }
            .icon-trip_type { color: #d946ef; }
            .icon-difficulty { color: #f97316; }
            .icon-customers { color: #0284c7; }
            .icon-reports { color: #059669; }
            .icon-shortcodes { color: #8b5cf6; }
            .icon-settings { color: #64748b; }
            .icon-theme { color: var(--c-amber); }

            .nte-main-stage {
                flex-grow: 1;
                height: 100vh;
                overflow-y: auto;
                background-color: var(--bg-base);
                padding: 28px 36px;
                transition: background-color 0.2s ease;
            }

            .nte-hero-telemetry {
                background-color: var(--bg-surface);
                border: 1px solid var(--border-light);
                border-left: 5px solid var(--c-blue);
                border-radius: 12px;
                padding: 20px 26px;
                display: flex;
                align-items: center;
                justify-content: space-between;
                margin-bottom: 22px;
                box-shadow: var(--shadow-sm);
            }
            .nte-hero-clock-box {
                background-color: var(--c-blue-bg);
                border: 1px solid var(--c-blue-border);
                padding: 10px 18px;
                border-radius: 10px;
                text-align: right;
            }
            .nte-hero-clock { font-size: 22px; font-weight: 800; color: var(--c-blue); font-variant-numeric: tabular-nums; }

            .nte-kpi-deck { display: grid; grid-template-columns: repeat(4, 1fr); gap: 18px; margin-bottom: 24px; }
            .nte-kpi-card {
                background-color: var(--bg-surface);
                border: 1px solid var(--border-light);
                border-radius: 12px;
                padding: 18px 20px;
                display: flex;
                justify-content: space-between;
                align-items: center;
                box-shadow: var(--shadow-sm);
                transition: transform 0.2s ease, box-shadow 0.2s ease;
            }
            .nte-kpi-card:hover {
                transform: translateY(-2px);
                box-shadow: var(--shadow-md);
            }
            .nte-kpi-card.kpi-blue { border-top: 4px solid var(--c-blue); }
            .nte-kpi-card.kpi-rose { border-top: 4px solid var(--c-rose); }
            .nte-kpi-card.kpi-emerald { border-top: 4px solid var(--c-emerald); }
            .nte-kpi-card.kpi-amber { border-top: 4px solid var(--c-amber); }

            .nte-kpi-title { font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 6px; }
            .nte-kpi-value { font-size: 22px; font-weight: 900; color: var(--text-main); }

            .nte-kpi-icon-box {
                width: 44px;
                height: 44px;
                border-radius: 10px;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            .kpi-blue .nte-kpi-icon-box { background-color: var(--c-blue-bg); color: var(--c-blue); }
            .kpi-rose .nte-kpi-icon-box { background-color: var(--c-rose-bg); color: var(--c-rose); }
            .kpi-emerald .nte-kpi-icon-box { background-color: var(--c-emerald-bg); color: var(--c-emerald); }
            .kpi-amber .nte-kpi-icon-box { background-color: var(--c-amber-bg); color: var(--c-amber); }

            .nte-control-input {
                width: 100%;
                padding: 9px 12px;
                border: 1px solid #cbd5e1;
                border-radius: 8px;
                font-size: 13px;
                color: var(--text-main);
                background-color: #ffffff;
                transition: all 0.15s ease;
            }
            :root[data-theme="dark"] .nte-control-input {
                background-color: #0f172a;
                border-color: #475569;
                color: #f8fafc;
            }
            .nte-control-input:focus { outline: none; border-color: var(--c-blue); box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15); }
            .nte-field-row { margin-bottom: 15px; }
            .nte-field-label { display: block; font-size: 12px; font-weight: 800; color: var(--text-main); margin-bottom: 5px; }
            
            .nte-btn-primary {
                background-color: var(--c-blue);
                color: #ffffff;
                border: none;
                padding: 10px 18px;
                border-radius: 8px;
                font-size: 13px;
                font-weight: 800;
                cursor: pointer;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                gap: 8px;
                transition: background-color 0.15s ease;
            }
            .nte-btn-primary:hover { background-color: #1d4ed8; }

            /* SLIDE-OVER POS MODAL DRAWER */
            .nte-modal-backdrop {
                position: fixed;
                top: 0; left: 0; width: 100vw; height: 100vh;
                background-color: rgba(15, 23, 42, 0.4);
                backdrop-filter: blur(2px);
                z-index: 99999;
                display: none;
                justify-content: flex-end;
            }
            .nte-modal-backdrop.active { display: flex; }
            .nte-drawer-panel {
                width: 440px;
                height: 100vh;
                background: var(--bg-surface);
                border-left: 1px solid var(--border-light);
                box-shadow: -10px 0 25px -5px rgba(0, 0, 0, 0.1);
                display: flex;
                flex-direction: column;
                animation: nteSlideIn 0.25s cubic-bezier(0.16, 1, 0.3, 1) forwards;
            }
            @keyframes nteSlideIn {
                from { transform: translateX(100%); }
                to { transform: translateX(0); }
            }
            .nte-drawer-header {
                padding: 20px 24px;
                border-bottom: 1px solid var(--border-light);
                display: flex;
                justify-content: space-between;
                align-items: center;
                background-color: var(--c-blue-bg);
            }
            .nte-drawer-body {
                padding: 24px;
                overflow-y: auto;
                flex-grow: 1;
            }
            .nte-drawer-close {
                background: transparent; border: none; cursor: pointer; color: var(--text-muted);
            }
            .nte-drawer-close:hover { color: var(--text-main); }
        </style>
    </head>
    <body>
        <script>
            (function() {
                var storedTheme = localStorage.getItem('nttbs_theme');
                if (storedTheme === 'dark' || (!storedTheme && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                    document.documentElement.setAttribute('data-theme', 'dark');
                }
            })();
        </script>
        <div class="nte-viewport">
            <aside class="nte-sidebar">
                <div class="nte-brand-box">
                    <div class="nte-brand-logo">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="10"></circle>
                            <polygon points="12 2 15 9 12 7 9 9 12 2" fill="currentColor"></polygon>
                            <path d="M12 22v-4"></path>
                            <path d="M2 12h4"></path>
                            <path d="M18 12h4"></path>
                            <circle cx="12" cy="12" r="2" fill="currentColor"></circle>
                        </svg>
                    </div>
                    <div>
                        <div class="nte-brand-title"><?php echo esc_html(NTTBS_APP_NAME); ?></div>
                    </div>
                </div>

                <ul class="nte-nav-menu">
                    <?php
                    $nav_items = [
                        'dashboard'        => [__('Dashboard', 'nahian-tour-travel-booking-solution'), 'dashboard', null],
                        'bookings'         => [__('Bookings', 'nahian-tour-travel-booking-solution'), 'bookings', $badge_bookings],
                        'trips'            => [__('Trips', 'nahian-tour-travel-booking-solution'), 'trips', $badge_trips],
                        'price_categories' => [__('Price Categories', 'nahian-tour-travel-booking-solution'), 'price_cat', null],
                        'destination'      => [__('Destinations', 'nahian-tour-travel-booking-solution'), 'dest', null],
                        'activity'         => [__('Activities', 'nahian-tour-travel-booking-solution'), 'activity', null],
                        'trip_type'        => [__('Trip Type', 'nahian-tour-travel-booking-solution'), 'trip_type', null],
                        'difficulty'       => [__('Difficulty', 'nahian-tour-travel-booking-solution'), 'diff', null],
                        'customers'        => [__('Customers', 'nahian-tour-travel-booking-solution'), 'customers', null],
                        'reports'          => [__('Reports', 'nahian-tour-travel-booking-solution'), 'reports', null],
                        'shortcodes'       => [__('Shortcodes', 'nahian-tour-travel-booking-solution'), 'shortcode', null],
                        'settings'         => [__('Settings', 'nahian-tour-travel-booking-solution'), 'settings', null],
                    ];

                    foreach ($nav_items as $slug => $meta) :
                        $is_active = ($current_view === $slug);
                    ?>
                        <li class="nte-nav-item <?php echo $is_active ? 'active' : ''; ?>">
                            <a href="<?php echo esc_url(admin_url('admin.php?page=nttbs-core&view=' . $slug)); ?>">
                                <span class="nte-nav-label-group">
                                    <span class="nte-nav-icon icon-<?php echo esc_attr($slug); ?>">
                                        <?php echo nttbs_svg_icon($meta[1]); ?>
                                    </span>
                                    <?php echo esc_html($meta[0]); ?>
                                </span>
                                <?php if ($meta[2] !== null) : ?>
                                    <span class="nte-nav-chip"><?php echo esc_html($meta[2]); ?></span>
                                <?php endif; ?>
                            </a>
                        </li>
                    <?php endforeach; ?>

                    <li class="nte-nav-item nte-nav-item-theme">
                        <button type="button" id="nte-theme-toggle" onclick="nttbsToggleTheme()" aria-label="Toggle theme">
                            <span class="nte-nav-label-group">
                                <span class="nte-nav-icon icon-theme" id="nte-theme-icon">
                                    <?php echo nttbs_svg_icon('moon'); ?>
                                </span>
                                <span id="nte-theme-label"><?php esc_html_e('Dark Mode', 'nahian-tour-travel-booking-solution'); ?></span>
                            </span>
                        </button>
                    </li>

                    <li class="nte-nav-item nte-nav-item-signout">
                        <a href="<?php echo esc_url(wp_logout_url(admin_url())); ?>">
                            <span class="nte-nav-label-group">
                                <span class="nte-nav-icon"><?php echo nttbs_svg_icon('signout'); ?></span>
                                <?php esc_html_e('Signout', 'nahian-tour-travel-booking-solution'); ?>
                            </span>
                        </a>
                    </li>
                </ul>
            </aside>

            <main class="nte-main-stage">
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
                    echo '<p style="color:var(--c-rose); font-weight:800;">' . 
                         sprintf(esc_html__('Module file missing: %s', 'nahian-tour-travel-booking-solution'), esc_html($target_file)) . 
                         '</p>';
                }
                ?>
            </main>
        </div>

        <!-- Slide-over POS Modal Drawer -->
        <div class="nte-modal-backdrop" id="nte-pos-drawer" role="dialog" aria-modal="true">
            <div class="nte-drawer-panel">
                <div class="nte-drawer-header">
                    <div>
                        <h3 style="font-size:16px; font-weight:900; color:var(--c-blue);"><?php esc_html_e('Dispatch Tour Manifest', 'nahian-tour-travel-booking-solution'); ?></h3>
                        <small style="color:var(--text-muted);" id="nte-drawer-subtitle"><?php esc_html_e('Direct Checkout Module', 'nahian-tour-travel-booking-solution'); ?></small>
                    </div>
                    <button type="button" class="nte-drawer-close" onclick="nttbsCloseDrawer()" aria-label="Close Drawer">
                        <?php echo nttbs_svg_icon('close', 18, 18); ?>
                    </button>
                </div>
                <div class="nte-drawer-body">
                    <form method="post" action="<?php echo esc_url(admin_url('admin.php?page=nttbs-core&view=dashboard')); ?>">
                        <?php wp_nonce_field('nttbs_modal_booking_nonce', 'nttbs_booking_token'); ?>
                        <input type="hidden" name="departure_id" id="drawer_departure_id">

                        <div class="nte-field-row">
                            <label class="nte-field-label"><?php esc_html_e('Lead Passenger Name *', 'nahian-tour-travel-booking-solution'); ?></label>
                            <input type="text" name="customer_name" required class="nte-control-input" placeholder="Full passenger name">
                        </div>

                        <div class="nte-field-row">
                            <label class="nte-field-label"><?php esc_html_e('Email Address *', 'nahian-tour-travel-booking-solution'); ?></label>
                            <input type="email" name="customer_email" required class="nte-control-input" placeholder="receipt@domain.com">
                        </div>

                        <div class="nte-field-row">
                            <label class="nte-field-label"><?php esc_html_e('Contact Phone', 'nahian-tour-travel-booking-solution'); ?></label>
                            <input type="text" name="customer_phone" class="nte-control-input" placeholder="+20 100 000 0000">
                        </div>

                        <div class="nte-field-row" style="display:flex; gap:12px;">
                            <div style="flex:1;">
                                <label class="nte-field-label"><?php esc_html_e('Passenger Count', 'nahian-tour-travel-booking-solution'); ?></label>
                                <input type="number" name="pax_count" id="drawer_pax_count" min="1" max="12" value="1" class="nte-control-input" oninput="nttbsUpdateDrawerCalculation()">
                            </div>
                            <div style="flex:1;">
                                <label class="nte-field-label"><?php esc_html_e('Payment Method', 'nahian-tour-travel-booking-solution'); ?></label>
                                <select name="payment_method" class="nte-control-input">
                                    <option value="Counter Cash"><?php esc_html_e('Counter Cash', 'nahian-tour-travel-booking-solution'); ?></option>
                                    <option value="Bank Card"><?php esc_html_e('Terminal Card', 'nahian-tour-travel-booking-solution'); ?></option>
                                    <option value="Online Portal"><?php esc_html_e('Online Portal', 'nahian-tour-travel-booking-solution'); ?></option>
                                </select>
                            </div>
                        </div>

                        <div style="background:var(--c-blue-bg); border:1px solid var(--c-blue-border); border-radius:8px; padding:14px; margin:18px 0;">
                            <div style="display:flex; justify-content:space-between; margin-bottom:6px; font-size:12px; color:var(--text-muted);">
                                <span><?php esc_html_e('Unit Tariff:', 'nahian-tour-travel-booking-solution'); ?></span>
                                <strong id="drawer_unit_price" style="color:var(--c-blue);">€ 0.00</strong>
                            </div>
                            <div style="display:flex; justify-content:space-between; font-size:15px; font-weight:900; color:var(--text-main);">
                                <span><?php esc_html_e('Total Payable:', 'nahian-tour-travel-booking-solution'); ?></span>
                                <strong id="drawer_total_price" style="color:var(--c-blue);">€ 0.00</strong>
                            </div>
                        </div>

                        <button type="submit" name="nttbs_dispatch_modal_booking" class="nte-btn-primary" style="width:100%;">
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
            const target = document.getElementById('nte-live-clock');
            if (target) target.textContent = `${hrs}:${mins}:${secs}`;
        }
        setInterval(nttbsTickClock, 1000);
        nttbsTickClock();

        let drawerBasePrice = 0;
        const currencySymbol = <?php echo json_encode($currency); ?>;

        function nttbsOpenDrawer(depId, tripTitle, price) {
            drawerBasePrice = parseFloat(price) || 0;
            const depInput = document.getElementById('drawer_departure_id');
            const subTitle = document.getElementById('nte-drawer-subtitle');
            const unitPrice = document.getElementById('drawer_unit_price');
            const paxCount = document.getElementById('drawer_pax_count');
            const drawer = document.getElementById('nte-pos-drawer');

            if (depInput) depInput.value = depId;
            if (subTitle) subTitle.textContent = tripTitle;
            if (unitPrice) unitPrice.textContent = `${currencySymbol} ${drawerBasePrice.toFixed(2)}`;
            if (paxCount) paxCount.value = 1;

            nttbsUpdateDrawerCalculation();
            if (drawer) drawer.classList.add('active');
        }

        function nttbsCloseDrawer() {
            const drawer = document.getElementById('nte-pos-drawer');
            if (drawer) drawer.classList.remove('active');
        }

        function nttbsUpdateDrawerCalculation() {
            const countInput = document.getElementById('drawer_pax_count');
            const totalDisplay = document.getElementById('drawer_total_price');
            const count = parseInt(countInput ? countInput.value : 1, 10) || 1;
            const total = (drawerBasePrice * count).toFixed(2);
            if (totalDisplay) {
                totalDisplay.textContent = `${currencySymbol} ${total}`;
            }
        }

        /* Dark / Light Mode Switcher */
        const nttbsIconMoon = <?php echo json_encode(nttbs_svg_icon('moon')); ?>;
        const nttbsIconSun = <?php echo json_encode(nttbs_svg_icon('sun')); ?>;
        const nttbsLabelDark = <?php echo json_encode(__('Dark Mode', 'nahian-tour-travel-booking-solution')); ?>;
        const nttbsLabelLight = <?php echo json_encode(__('Light Mode', 'nahian-tour-travel-booking-solution')); ?>;

        function nttbsSyncThemeUI(theme) {
            const iconElem = document.getElementById('nte-theme-icon');
            const labelElem = document.getElementById('nte-theme-label');
            if (!iconElem || !labelElem) return;

            if (theme === 'dark') {
                iconElem.innerHTML = nttbsIconSun;
                labelElem.textContent = nttbsLabelLight;
            } else {
                iconElem.innerHTML = nttbsIconMoon;
                labelElem.textContent = nttbsLabelDark;
            }
        }

        function nttbsToggleTheme() {
            const currentTheme = document.documentElement.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
            const nextTheme = currentTheme === 'dark' ? 'light' : 'dark';

            if (nextTheme === 'dark') {
                document.documentElement.setAttribute('data-theme', 'dark');
            } else {
                document.documentElement.removeAttribute('data-theme');
            }

            localStorage.setItem('nttbs_theme', nextTheme);
            nttbsSyncThemeUI(nextTheme);
        }

        document.addEventListener('DOMContentLoaded', function() {
            const activeTheme = document.documentElement.getAttribute('data-theme') || 'light';
            nttbsSyncThemeUI(activeTheme);
        });
        </script>
    </body>
    </html>
    <?php
}
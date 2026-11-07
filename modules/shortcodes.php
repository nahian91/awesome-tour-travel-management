<?php
if (!defined('ABSPATH')) {
    exit;
}

$shortcode_definitions = [
    [
        'tag'         => '[nttbs_trips]',
        'category'    => __('Catalog & Booking', 'nahian-tour-travel-booking-solution'),
        'badge'       => __('Interactive Feed', 'nahian-tour-travel-booking-solution'),
        'badge_color' => 'var(--c-blue)',
        'title'       => __('All Expeditions (Left Sidebar Filter & Grid/List View)', 'nahian-tour-travel-booking-solution'),
        'description' => __('Renders a full multi-filter tour catalog with instant Grid/List view switching, keyword search, price slider, and taxonomy filters.', 'nahian-tour-travel-booking-solution'),
        'example'     => '[nttbs_trips default_view="grid" per_page="12"]',
        'attributes'  => [
            'default_view' => ['type' => 'string', 'default' => 'grid', 'desc' => __('Default layout mode ("grid" or "list").', 'nahian-tour-travel-booking-solution')],
            'per_page'     => ['type' => 'integer', 'default' => '12', 'desc' => __('Number of expeditions displayed per page.', 'nahian-tour-travel-booking-solution')],
            'destination'  => ['type' => 'string', 'default' => 'all', 'desc' => __('Filter specifically by destination slug.', 'nahian-tour-travel-booking-solution')],
            'order_by'     => ['type' => 'string', 'default' => 'date', 'desc' => __('Sort column ("price", "date", "title").', 'nahian-tour-travel-booking-solution')]
        ]
    ],
    [
        'tag'         => '[nttbs_destinations]',
        'category'    => __('Showcase Grids', 'nahian-tour-travel-booking-solution'),
        'badge'       => __('Visual Grid', 'nahian-tour-travel-booking-solution'),
        'badge_color' => 'var(--c-emerald)',
        'title'       => __('Destinations Showcase Grid', 'nahian-tour-travel-booking-solution'),
        'description' => __('Displays responsive photo cards for registered harbors, islands, and regions with live expedition counts.', 'nahian-tour-travel-booking-solution'),
        'example'     => '[nttbs_destinations columns="3" limit="6"]',
        'attributes'  => [
            'columns' => ['type' => 'integer', 'default' => '3', 'desc' => __('Grid columns layout (2, 3, or 4).', 'nahian-tour-travel-booking-solution')],
            'limit'   => ['type' => 'integer', 'default' => '12', 'desc' => __('Maximum destination cards to render.', 'nahian-tour-travel-booking-solution')],
            'show_count' => ['type' => 'boolean', 'default' => 'true', 'desc' => __('Show count badge of active tours in region.', 'nahian-tour-travel-booking-solution')]
        ]
    ],
    [
        'tag'         => '[nttbs_activities]',
        'category'    => __('Showcase Grids', 'nahian-tour-travel-booking-solution'),
        'badge'       => __('Visual Grid', 'nahian-tour-travel-booking-solution'),
        'badge_color' => 'var(--c-purple)',
        'title'       => __('Activities Showcase Grid', 'nahian-tour-travel-booking-solution'),
        'description' => __('Shows excursion categories (Snorkeling, Quad Safari, Yacht Cruises) with badge indicators and quick discovery links.', 'nahian-tour-travel-booking-solution'),
        'example'     => '[nttbs_activities columns="4" limit="8"]',
        'attributes'  => [
            'columns' => ['type' => 'integer', 'default' => '4', 'desc' => __('Grid columns layout (2, 3, or 4).', 'nahian-tour-travel-booking-solution')],
            'limit'   => ['type' => 'integer', 'default' => '12', 'desc' => __('Maximum activity categories to render.', 'nahian-tour-travel-booking-solution')]
        ]
    ],
    [
        'tag'         => '[nttbs_booking_lookup]',
        'category'    => __('Search & Utility', 'nahian-tour-travel-booking-solution'),
        'badge'       => __('Customer Self-Service', 'nahian-tour-travel-booking-solution'),
        'badge_color' => 'var(--c-teal)',
        'title'       => __('Passenger Reservation & Manifest Lookup', 'nahian-tour-travel-booking-solution'),
        'description' => __('Self-service portal where customers input their Booking Code and Email to check boarding status and download printable PDF vouchers.', 'nahian-tour-travel-booking-solution'),
        'example'     => '[nttbs_booking_lookup allow_print="true"]',
        'attributes'  => [
            'allow_print'  => ['type' => 'boolean', 'default' => 'true', 'desc' => __('Permit one-click browser print of vouchers.', 'nahian-tour-travel-booking-solution')],
            'accent_color' => ['type' => 'string', 'default' => '#2563eb', 'desc' => __('Custom theme color for lookup button & header.', 'nahian-tour-travel-booking-solution')]
        ]
    ],
    [
        'tag'         => '[nttbs_search_bar]',
        'category'    => __('Search & Utility', 'nahian-tour-travel-booking-solution'),
        'badge'       => __('Omni Search', 'nahian-tour-travel-booking-solution'),
        'badge_color' => '#f59e0b',
        'title'       => __('Global Floating Search Bar Widget', 'nahian-tour-travel-booking-solution'),
        'description' => __('Horizontal multi-field search bar for Homepage hero banners. Includes Destination dropdown, Datepicker, and Passenger counter.', 'nahian-tour-travel-booking-solution'),
        'example'     => '[nttbs_search_bar style="horizontal" target_url="/tours/"]',
        'attributes'  => [
            'style'      => ['type' => 'string', 'default' => 'horizontal', 'desc' => __('Layout orientation ("horizontal" or "stacked").', 'nahian-tour-travel-booking-solution')],
            'target_url' => ['type' => 'string', 'default' => '', 'desc' => __('URL of the catalog page results will submit to.', 'nahian-tour-travel-booking-solution')]
        ]
    ],
    [
        'tag'         => '[nttbs_featured_deals]',
        'category'    => __('Catalog & Booking', 'nahian-tour-travel-booking-solution'),
        'badge'       => __('Promotion Carousel', 'nahian-tour-travel-booking-solution'),
        'badge_color' => 'var(--c-rose)',
        'title'       => __('Promotional & Limited-Seat Expeditions', 'nahian-tour-travel-booking-solution'),
        'description' => __('Highlights discounted packages or upcoming departures with low remaining availability to drive booking urgency.', 'nahian-tour-travel-booking-solution'),
        'example'     => '[nttbs_featured_deals badge="LIMITED SEATS" count="4"]',
        'attributes'  => [
            'count' => ['type' => 'integer', 'default' => '4', 'desc' => __('Total deal cards to show.', 'nahian-tour-travel-booking-solution')],
            'badge' => ['type' => 'string', 'default' => 'HOT DEAL', 'desc' => __('Custom ribbon label overlaid on tour thumbnails.', 'nahian-tour-travel-booking-solution')]
        ]
    ],
    [
        'tag'         => '[nttbs_booking_form]',
        'category'    => __('Booking & Checkout', 'nahian-tour-travel-booking-solution'),
        'badge'       => __('Direct Conversion', 'nahian-tour-travel-booking-solution'),
        'badge_color' => '#0284c7',
        'title'       => __('Direct Single Tour Booking Checkout', 'nahian-tour-travel-booking-solution'),
        'description' => __('Embeds a complete standalone reservation drawer or inline form pre-selected to an expedition ID for targeted landing pages.', 'nahian-tour-travel-booking-solution'),
        'example'     => '[nttbs_booking_form trip_id="12" show_deposit="true"]',
        'attributes'  => [
            'trip_id'      => ['type' => 'integer', 'default' => '0', 'desc' => __('Target expedition database ID to lock.', 'nahian-tour-travel-booking-solution')],
            'show_deposit' => ['type' => 'boolean', 'default' => 'true', 'desc' => __('Display deposit requirement calculate box.', 'nahian-tour-travel-booking-solution')]
        ]
    ]
];
?>

<style>
.nttbs-shortcode-workspace {
    display: flex;
    flex-direction: column;
    gap: 20px;
    animation: nteFadeIn 0.25s ease-out;
}
@keyframes nteFadeIn {
    from { opacity: 0; transform: translateY(4px); }
    to { opacity: 1; transform: translateY(0); }
}

/* Hero Header */
.nttbs-shortcode-hero {
    background: var(--bg-surface);
    border: 1px solid var(--border-light);
    border-radius: 12px;
    padding: 24px 28px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    box-shadow: var(--shadow-sm);
    gap: 20px;
}
.nttbs-hero-badge {
    display: inline-block;
    font-size: 11px;
    font-weight: 800;
    color: var(--c-blue);
    background: var(--c-blue-bg);
    border: 1px solid var(--c-blue-border);
    padding: 3px 9px;
    border-radius: 6px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 8px;
}
.nttbs-hero-heading {
    font-size: 22px;
    font-weight: 900;
    color: var(--text-main);
    margin: 0 0 6px 0;
    letter-spacing: -0.3px;
}
.nttbs-hero-sub {
    font-size: 13px;
    color: var(--text-muted);
    margin: 0;
    line-height: 1.5;
}
.nttbs-hero-stat {
    background: var(--bg-subtle);
    border: 1px solid var(--border-light);
    border-radius: 10px;
    padding: 14px 20px;
    text-align: center;
    min-width: 120px;
    flex-shrink: 0;
}
.nttbs-stat-number {
    display: block;
    font-size: 26px;
    font-weight: 900;
    color: var(--c-blue);
    line-height: 1;
}
.nttbs-stat-label {
    font-size: 11px;
    font-weight: 700;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: 0.4px;
    margin-top: 5px;
    display: block;
}

/* Control Bar */
.nttbs-control-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 16px;
    flex-wrap: wrap;
    background: var(--bg-surface);
    border: 1px solid var(--border-light);
    padding: 12px 18px;
    border-radius: 10px;
}
.nttbs-filter-tabs {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}
.nttbs-tab-btn {
    background: transparent;
    border: 1px solid transparent;
    color: var(--text-muted);
    padding: 6px 12px;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.15s ease;
}
.nttbs-tab-btn:hover {
    background: var(--bg-subtle);
    color: var(--text-main);
}
.nttbs-tab-btn.active {
    background: var(--c-blue-bg);
    border-color: var(--c-blue-border);
    color: var(--c-blue);
}
.nttbs-search-field {
    width: 250px;
    padding: 7px 12px;
    font-size: 12px;
    border-radius: 6px;
    border: 1px solid var(--border-light);
    background: var(--bg-subtle);
    color: var(--text-main);
    outline: none;
}
.nttbs-search-field:focus {
    border-color: var(--c-blue);
}

/* Cards Stack */
.nttbs-cards-stack {
    display: flex;
    flex-direction: column;
    gap: 20px;
}
.nttbs-sc-card {
    background: var(--bg-surface);
    border: 1px solid var(--border-light);
    border-radius: 12px;
    overflow: hidden;
    box-shadow: var(--shadow-sm);
    transition: border-color 0.2s ease, box-shadow 0.2s ease;
}
.nttbs-sc-card:hover {
    box-shadow: var(--shadow-md);
}

/* Card Header */
.nttbs-sc-topbar {
    padding: 14px 20px;
    background: linear-gradient(180deg, rgba(255,255,255,0.02) 0%, rgba(0,0,0,0.02) 100%);
    border-bottom: 1px solid var(--border-light);
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
}
.nttbs-sc-tags {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}
.nttbs-tag-pill {
    background: var(--bg-surface);
    border: 1px solid var(--border-light);
    font-size: 11px;
    font-weight: 700;
    color: var(--text-muted);
    padding: 3px 9px;
    border-radius: 4px;
}
.nttbs-badge-pill {
    font-size: 11px;
    font-weight: 800;
    padding: 3px 8px;
    border-radius: 6px;
    text-transform: uppercase;
    letter-spacing: 0.3px;
    border: 1px solid transparent;
}
.nttbs-sc-tagname code {
    background: var(--bg-subtle);
    border: 1px solid var(--border-light);
    color: var(--text-main);
    font-weight: 800;
    font-size: 12px;
    padding: 3px 8px;
    border-radius: 6px;
    font-family: ui-monospace, monospace;
}

/* Card Body */
.nttbs-sc-body {
    padding: 20px;
}
.nttbs-sc-title {
    font-size: 15px;
    font-weight: 800;
    color: var(--text-main);
    margin: 0 0 6px 0;
}
.nttbs-sc-desc {
    font-size: 13px;
    color: var(--text-muted);
    line-height: 1.5;
    margin: 0 0 16px 0;
}

/* Snippet Console Bar */
.nttbs-snippet-bar {
    background: #0f172a;
    border: 1px solid var(--border-light);
    border-radius: 10px;
    padding: 10px 14px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 18px;
    gap: 12px;
}
.nttbs-snippet-code {
    display: flex;
    align-items: center;
    gap: 10px;
    overflow-x: auto;
}
.nttbs-code-prefix {
    color: #38bdf8;
    font-weight: 900;
    font-family: ui-monospace, monospace;
    font-size: 14px;
    user-select: none;
}
.nttbs-code-val {
    color: #f8fafc;
    font-family: ui-monospace, monospace;
    font-size: 13px;
    font-weight: 600;
    white-space: nowrap;
}
.nttbs-copy-btn {
    background: rgba(255, 255, 255, 0.12);
    border: 1px solid rgba(255, 255, 255, 0.2);
    color: #ffffff;
    border-radius: 6px;
    padding: 6px 12px;
    font-size: 11px;
    font-weight: 700;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: all 0.15s ease;
    flex-shrink: 0;
}
.nttbs-copy-btn:hover {
    background: rgba(255, 255, 255, 0.22);
}

/* Parameter Matrix */
.nttbs-params-wrap {
    margin-top: 14px;
}
.nttbs-params-label {
    display: block;
    font-size: 11px;
    font-weight: 800;
    text-transform: uppercase;
    color: var(--text-muted);
    letter-spacing: 0.5px;
    margin-bottom: 8px;
}
.nttbs-param-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    background: var(--bg-subtle);
    border: 1px solid var(--border-light);
    border-radius: 8px;
    overflow: hidden;
}
.nttbs-param-table th {
    background: var(--bg-surface);
    padding: 9px 14px;
    font-size: 11px;
    font-weight: 800;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    text-align: left;
    border-bottom: 1px solid var(--border-light);
}
.nttbs-param-table td {
    padding: 11px 14px;
    font-size: 12px;
    border-bottom: 1px solid var(--border-light);
    color: var(--text-main);
    vertical-align: middle;
}
.nttbs-param-table tr:last-child td {
    border-bottom: none;
}
.nttbs-attr-code {
    background: var(--bg-surface);
    border: 1px solid var(--border-light);
    color: var(--c-purple);
    font-weight: 800;
    padding: 2px 6px;
    border-radius: 4px;
    font-family: ui-monospace, monospace;
}
.nttbs-type-chip {
    font-size: 11px;
    font-weight: 700;
    color: var(--text-muted);
    background: var(--bg-surface);
    border: 1px solid var(--border-light);
    padding: 2px 6px;
    border-radius: 4px;
}
.nttbs-default-chip {
    font-family: ui-monospace, monospace;
    font-weight: 800;
    color: var(--text-main);
}
.nttbs-desc-cell {
    color: var(--text-muted);
}

@media (max-width: 768px) {
    .nttbs-shortcode-hero {
        flex-direction: column;
        align-items: flex-start;
    }
    .nttbs-hero-stat {
        width: 100%;
        text-align: left;
    }
    .nttbs-sc-topbar,
    .nttbs-snippet-bar {
        flex-direction: column;
        align-items: flex-start;
    }
    .nttbs-copy-btn {
        width: 100%;
        justify-content: center;
    }
}
</style>

<div class="nttbs-shortcode-workspace">
    <!-- HERO HEADER -->
    <header class="nttbs-shortcode-hero">
        <div>
            <span class="nttbs-hero-badge"><?php esc_html_e('Embedding & Integration Engine', 'nahian-tour-travel-booking-solution'); ?></span>
            <h1 class="nttbs-hero-heading"><?php esc_html_e('Shortcode Central Repository', 'nahian-tour-travel-booking-solution'); ?></h1>
            <p class="nttbs-hero-sub"><?php esc_html_e('Deploy customer booking interfaces, interactive discovery catalogs, and destination showcases into pages, posts, or page builders.', 'nahian-tour-travel-booking-solution'); ?></p>
        </div>
        <div class="nttbs-hero-stat">
            <span class="nttbs-stat-number"><?php echo count($shortcode_definitions); ?></span>
            <span class="nttbs-stat-label"><?php esc_html_e('Active Engines', 'nahian-tour-travel-booking-solution'); ?></span>
        </div>
    </header>

    <!-- INTERACTIVE CONTROL / FILTER BAR -->
    <div class="nttbs-control-bar">
        <div class="nttbs-filter-tabs">
            <button type="button" class="nttbs-tab-btn active" onclick="nttbsFilterCategory('all', this)"><?php esc_html_e('All Shortcodes', 'nahian-tour-travel-booking-solution'); ?></button>
            <button type="button" class="nttbs-tab-btn" onclick="nttbsFilterCategory('Catalog & Booking', this)"><?php esc_html_e('Catalog & Booking', 'nahian-tour-travel-booking-solution'); ?></button>
            <button type="button" class="nttbs-tab-btn" onclick="nttbsFilterCategory('Showcase Grids', this)"><?php esc_html_e('Showcase Grids', 'nahian-tour-travel-booking-solution'); ?></button>
            <button type="button" class="nttbs-tab-btn" onclick="nttbsFilterCategory('Search & Utility', this)"><?php esc_html_e('Search & Utility', 'nahian-tour-travel-booking-solution'); ?></button>
        </div>
        <div>
            <input type="text" id="nttbs-sc-search" class="nttbs-search-field" placeholder="<?php esc_attr_e('Filter shortcode or tag...', 'nahian-tour-travel-booking-solution'); ?>" oninput="nttbsFilterBySearch(this.value)">
        </div>
    </div>

    <!-- SHORTCODES FEED -->
    <div class="nttbs-cards-stack" id="nttbs-cards-container">
        <?php foreach ($shortcode_definitions as $index =>$sc): ?>
            <div class="nttbs-sc-card" data-category="<?php echo esc_attr($sc['category']); ?>" data-search="<?php echo esc_attr(strtolower($sc['tag'] . ' ' . $sc['title'] . ' ' .$sc['category'])); ?>">
                <!-- Top Meta Bar -->
                <div class="nttbs-sc-topbar">
                    <div class="nttbs-sc-tags">
                        <span class="nttbs-tag-pill" style="border-left: 3px solid <?php echo esc_attr($sc['badge_color']); ?>;">
                            <?php echo esc_html($sc['category']); ?>
                        </span>
                        <span class="nttbs-badge-pill" style="background-color: var(--c-blue-bg); color: <?php echo esc_attr($sc['badge_color']); ?>; border-color: var(--border-light);">
                            <?php echo esc_html($sc['badge']); ?>
                        </span>
                    </div>
                    <span class="nttbs-sc-tagname"><code><?php echo esc_html($sc['tag']); ?></code></span>
                </div>

                <!-- Main Info -->
                <div class="nttbs-sc-body">
                    <h3 class="nttbs-sc-title"><?php echo esc_html($sc['title']); ?></h3>
                    <p class="nttbs-sc-desc"><?php echo esc_html($sc['description']); ?></p>

                    <!-- Copy Snippet Bar -->
                    <div class="nttbs-snippet-bar">
                        <div class="nttbs-snippet-code">
                            <span class="nttbs-code-prefix">&gt;</span>
                            <span class="nttbs-code-val" id="sc_text_<?php echo $index; ?>"><?php echo esc_html($sc['example']); ?></span>
                        </div>
                        <button type="button" class="nttbs-copy-btn" onclick="nttbsCopySnippet('sc_text_<?php echo $index; ?>', this)">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                                <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
                            </svg>
                            <span><?php esc_html_e('Copy Shortcode', 'nahian-tour-travel-booking-solution'); ?></span>
                        </button>
                    </div>

                    <!-- Parameter Matrix -->
                    <?php if (!empty($sc['attributes'])): ?>
                        <div class="nttbs-params-wrap">
                            <span class="nttbs-params-label"><?php esc_html_e('Configurable Parameters', 'nahian-tour-travel-booking-solution'); ?></span>
                            <table class="nttbs-param-table">
                                <thead>
                                    <tr>
                                        <th><?php esc_html_e('Attribute', 'nahian-tour-travel-booking-solution'); ?></th>
                                        <th><?php esc_html_e('Data Type', 'nahian-tour-travel-booking-solution'); ?></th>
                                        <th><?php esc_html_e('Default', 'nahian-tour-travel-booking-solution'); ?></th>
                                        <th><?php esc_html_e('Description', 'nahian-tour-travel-booking-solution'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($sc['attributes'] as $attr =>$meta): ?>
                                        <tr>
                                            <td><code class="nttbs-attr-code"><?php echo esc_html($attr); ?></code></td>
                                            <td><span class="nttbs-type-chip"><?php echo esc_html($meta['type']); ?></span></td>
                                            <td><span class="nttbs-default-chip"><?php echo esc_html($meta['default']); ?></span></td>
                                            <td class="nttbs-desc-cell"><?php echo esc_html($meta['desc']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<script>
function nttbsCopySnippet(elId, btn) {
    const el = document.getElementById(elId);
    if (!el) return;

    const textToCopy = el.textContent.trim();
    const originalHTML = btn.innerHTML;

    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(textToCopy).then(function() {
            nttbsApplyCopySuccess(btn, originalHTML);
        });
    } else {
        const textArea = document.createElement('textarea');
        textArea.value = textToCopy;
        document.body.appendChild(textArea);
        textArea.select();
        document.execCommand('copy');
        document.body.removeChild(textArea);
        nttbsApplyCopySuccess(btn, originalHTML);
    }
}

function nttbsApplyCopySuccess(btn, originalHTML) {
    btn.style.background = 'var(--c-emerald, #059669)';
    btn.style.borderColor = 'var(--c-emerald, #059669)';
    btn.innerHTML = `
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="20 6 9 17 4 12"></polyline>
        </svg>
        <span><?php echo esc_js(__('Copied!', 'nahian-tour-travel-booking-solution')); ?></span>
    `;
    setTimeout(function() {
        btn.style.background = '';
        btn.style.borderColor = '';
        btn.innerHTML = originalHTML;
    }, 1800);
}

function nttbsFilterCategory(cat, btn) {
    document.querySelectorAll('.nttbs-tab-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');

    const cards = document.querySelectorAll('.nttbs-sc-card');
    cards.forEach(card => {
        if (cat === 'all' || card.getAttribute('data-category').toLowerCase().includes(cat.toLowerCase())) {
            card.style.display = 'block';
        } else {
            card.style.display = 'none';
        }
    });
}

function nttbsFilterBySearch(term) {
    const val = term.toLowerCase().trim();
    const cards = document.querySelectorAll('.nttbs-sc-card');
    cards.forEach(card => {
        const searchPool = card.getAttribute('data-search') || '';
        if (!val || searchPool.includes(val)) {
            card.style.display = 'block';
        } else {
            card.style.display = 'none';
        }
    });
}
</script>
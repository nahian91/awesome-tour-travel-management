<?php
if (!defined('ABSPATH')) {
    exit;
}

add_shortcode('nttbs_tour_login_register', function($atts) {
    $args = shortcode_atts([
        'redirect_url' => site_url('/tour-account/'),
    ], $atts, 'nttbs_tour_login_register');

    $redirect = esc_url($args['redirect_url']);

    if (is_user_logged_in()) {
        return '<div style="max-width:500px; margin:40px auto; text-align:center; font-family:sans-serif;">' . 
               sprintf(esc_html__('You are signed in. %sGo to My Portal%s', 'nahian-tour-travel-booking-solution'), '<a href="' . $redirect . '">', '</a>') . 
               '</div>';
    }

    $login_error = '';
    if (isset($_POST['nttbs_auth_login']) && check_admin_referer('nttbs_auth_nonce')) {
        $creds = [
            'user_login'    => sanitize_text_field(wp_unslash($_POST['log'] ?? '')),
            'user_password' => $_POST['pwd'] ?? '',
            'remember'      => true
        ];
        $user = wp_signon($creds, is_ssl());
        if (is_wp_error($user)) {
            $login_error = $user->get_error_message();
        } else {
            wp_safe_redirect($redirect);
            exit;
        }
    }

    ob_start();
    ?>
    <div style="max-width:440px; margin:40px auto; background:#ffffff; border:1px solid #e2e8f0; border-radius:14px; padding:32px; box-shadow:0 8px 20px rgba(0,0,0,0.04); font-family:-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
        <h2 style="font-size:20px; font-weight:800; margin:0 0 18px 0; text-align:center; color:#0f172a;"><?php esc_html_e('Traveler Portal Sign In', 'nahian-tour-travel-booking-solution'); ?></h2>

        <?php if ($login_error): ?>
            <div style="background:#fff1f2; border:1px solid #fecdd3; color:#e11d48; padding:10px 14px; border-radius:8px; font-size:12.5px; margin-bottom:16px;">
                <?php echo wp_kses_post($login_error); ?>
            </div>
        <?php endif; ?>

        <form method="post">
            <?php wp_nonce_field('nttbs_auth_nonce'); ?>
            <div style="margin-bottom:14px;">
                <label style="display:block; font-size:12px; font-weight:700; margin-bottom:6px; color:#334155;"><?php esc_html_e('Username or Email', 'nahian-tour-travel-booking-solution'); ?></label>
                <input type="text" name="log" required style="width:100%; border:1px solid #cbd5e1; border-radius:8px; padding:10px 14px; box-sizing:border-box; outline:none;">
            </div>
            <div style="margin-bottom:18px;">
                <label style="display:block; font-size:12px; font-weight:700; margin-bottom:6px; color:#334155;"><?php esc_html_e('Password', 'nahian-tour-travel-booking-solution'); ?></label>
                <input type="password" name="pwd" required style="width:100%; border:1px solid #cbd5e1; border-radius:8px; padding:10px 14px; box-sizing:border-box; outline:none;">
            </div>
            <button type="submit" name="nttbs_auth_login" style="width:100%; background:#2563eb; color:#fff; border:none; padding:12px; border-radius:8px; font-weight:800; font-size:14px; cursor:pointer;">
                <?php esc_html_e('Log In', 'nahian-tour-travel-booking-solution'); ?>
            </button>
        </form>
    </div>
    <?php
    return ob_get_clean();
});
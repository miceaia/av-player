<?php
declare(strict_types=1);
/**
 * Clase para la administración del plugin
 */
class AVP_Admin {

    public function __construct() {
        add_action('admin_init', array($this, 'register_settings'));
        add_action('wp_ajax_avp_save_settings', array($this, 'save_settings'));
    }

    public function register_settings(): void {
        register_setting('avp_settings_group', 'avp_settings');
    }

    public function save_settings(): void {
        // Verificar nonce
        $nonce = isset($_POST['avp_settings_nonce']) ? sanitize_text_field(wp_unslash($_POST['avp_settings_nonce'])) : '';

        if ($nonce === '' || !wp_verify_nonce($nonce, 'avp-settings-save')) {
            wp_send_json_error(array('message' => 'Security check failed'));
            return;
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
            return;
        }
        
        $existing_settings = get_option('avp_settings', array());
        $default_watch_limit = isset($_POST['default_watch_limit'])
            ? intval(wp_unslash($_POST['default_watch_limit']))
            : intval($existing_settings['default_watch_limit'] ?? 180);

        $settings = array(
            // Bunny.net settings
            'bunny_api_key' => sanitize_text_field(wp_unslash($_POST['bunny_api_key'] ?? '')),
            'bunny_library_id' => sanitize_text_field(wp_unslash($_POST['bunny_library_id'] ?? '')),
            'bunny_cdn_hostname' => sanitize_text_field(wp_unslash($_POST['bunny_cdn_hostname'] ?? '')),

            // Player settings
            'default_player_width' => sanitize_text_field(wp_unslash($_POST['default_player_width'] ?? '100%')),
            'default_player_height' => sanitize_text_field(wp_unslash($_POST['default_player_height'] ?? '500px')),
            'autoplay' => isset($_POST['autoplay']),
            'controls' => isset($_POST['controls']),
            'loop' => isset($_POST['loop']),
            'muted' => isset($_POST['muted']),
            'preload' => sanitize_text_field(wp_unslash($_POST['preload'] ?? 'metadata')),

            // Features
            'enable_ads' => isset($_POST['enable_ads']),
            'enable_analytics' => isset($_POST['enable_analytics']),
            'default_watch_limit' => max(0, $default_watch_limit)
        );

        update_option('avp_settings', $settings);

        wp_send_json_success(array('message' => 'Settings saved successfully'));
    }
}

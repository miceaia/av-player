<?php
/**
 * Meta boxes for per-video settings.
 */
class AVP_Video_Meta {
    const META_KEY = '_avp_video_watch_limit';

    public function __construct() {
        add_action('add_meta_boxes', array($this, 'register_meta_box'));
        add_action('save_post', array($this, 'save_meta_box'));
    }

    public static function get_limit_minutes($post_id) {
        $post_id = absint($post_id);
        if ($post_id <= 0) {
            return 0;
        }

        $value = get_post_meta($post_id, self::META_KEY, true);
        if ($value === '' || $value === null) {
            return 0;
        }

        $minutes = intval($value);
        return $minutes > 0 ? $minutes : 0;
    }

    public function register_meta_box($post_type) {
        $supported = apply_filters('avp_watch_limit_meta_post_types', $this->get_supported_post_types());
        if (!in_array($post_type, $supported, true)) {
            return;
        }

        add_meta_box(
            'avp-watch-limit-meta',
            __('Playback time limit', 'advanced-video-player'),
            array($this, 'render_meta_box'),
            $post_type,
            'side',
            'default'
        );
    }

    public function render_meta_box($post) {
        if (!current_user_can('manage_options')) {
            echo '<p>' . esc_html__('You do not have permission to edit playback limits.', 'advanced-video-player') . '</p>';
            return;
        }

        wp_nonce_field('avp_watch_limit_meta', 'avp_watch_limit_meta_nonce');

        $minutes = self::get_limit_minutes($post->ID);
        $settings = get_option('avp_settings', array());
        $default = isset($settings['default_watch_limit']) ? intval($settings['default_watch_limit']) : AVP_Watch_Limits::DEFAULT_MINUTES;
        if ($default < 0) {
            $default = 0;
        }

        echo '<p>' . esc_html__('Override the default playback allowance for this lesson or video. Leave empty to inherit the global default.', 'advanced-video-player') . '</p>';
        echo '<label for="avp-watch-limit-override" class="screen-reader-text">' . esc_html__('Minutes allowed', 'advanced-video-player') . '</label>';
        echo '<input type="number" min="0" step="1" id="avp-watch-limit-override" name="avp_watch_limit_override" value="' . esc_attr($minutes > 0 ? $minutes : '') . '" style="width:100%;" />';
        echo '<p class="description">' . sprintf(esc_html__('Default allowance: %d minutes.', 'advanced-video-player'), $default) . '</p>';
    }

    public function save_meta_box($post_id) {
        if (!isset($_POST['avp_watch_limit_meta_nonce']) || !wp_verify_nonce($_POST['avp_watch_limit_meta_nonce'], 'avp_watch_limit_meta')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('manage_options')) {
            return;
        }

        if (isset($_POST['avp_watch_limit_override']) && $_POST['avp_watch_limit_override'] !== '') {
            $minutes = intval($_POST['avp_watch_limit_override']);
            if ($minutes < 0) {
                $minutes = 0;
            }

            if ($minutes > 0) {
                update_post_meta($post_id, self::META_KEY, $minutes);
            } else {
                delete_post_meta($post_id, self::META_KEY);
            }
        } else {
            delete_post_meta($post_id, self::META_KEY);
        }
    }

    private function get_supported_post_types() {
        $types = array('post', 'page');

        $learndash_types = array('sfwd-courses', 'sfwd-lessons', 'sfwd-topic', 'sfwd-quiz');
        foreach ($learndash_types as $type) {
            if (post_type_exists($type)) {
                $types[] = $type;
            }
        }

        return array_unique($types);
    }
}

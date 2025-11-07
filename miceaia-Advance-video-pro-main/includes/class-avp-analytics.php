<?php
declare(strict_types=1);
/**
 * Clase para analíticas
 */
class AVP_Analytics {

    public function __construct() {
        add_action('wp_ajax_avp_track_event', array($this, 'track_event'));
        add_action('wp_ajax_nopriv_avp_track_event', array($this, 'track_event'));
        add_action('wp_ajax_avp_track_error', array($this, 'track_error'));
        add_action('wp_ajax_nopriv_avp_track_error', array($this, 'track_error'));
    }

    public function track_event(): void {
        check_ajax_referer('avp-nonce', 'nonce');
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'avp_analytics';
        
        $event = $_POST['event'] ?? array();
        
        $wpdb->insert(
            $table_name,
            array(
                'video_id' => sanitize_text_field($event['video_id'] ?? ''),
                'video_url' => '',
                'event_type' => sanitize_text_field($event['event_type'] ?? ''),
                'user_id' => get_current_user_id(),
                'ip_address' => $this->get_user_ip(),
                'user_agent' => sanitize_text_field($_SERVER['HTTP_USER_AGENT'] ?? ''),
                'duration' => intval($event['duration'] ?? 0),
                'timestamp' => current_time('mysql')
            ),
            array('%s', '%s', '%s', '%d', '%s', '%s', '%d', '%s')
        );
        
        wp_send_json_success();
    }
    
    public function track_error(): void {
        check_ajax_referer('avp-nonce', 'nonce');
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'avp_analytics';
        
        $wpdb->insert(
            $table_name,
            array(
                'video_id' => sanitize_text_field($_POST['video_id'] ?? ''),
                'video_url' => '',
                'event_type' => 'error',
                'user_id' => get_current_user_id(),
                'ip_address' => $this->get_user_ip(),
                'user_agent' => sanitize_text_field($_SERVER['HTTP_USER_AGENT'] ?? ''),
                'duration' => 0,
                'timestamp' => current_time('mysql')
            ),
            array('%s', '%s', '%s', '%d', '%s', '%s', '%d', '%s')
        );
        
        wp_send_json_success();
    }
    
    public function get_analytics_data(?string $video_id = null, int $days = 7): array {
        global $wpdb;
        $table_name = $wpdb->prefix . 'avp_analytics';
        
        $date_limit = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        if ($video_id) {
            $query = $wpdb->prepare(
                "SELECT * FROM $table_name WHERE video_id = %s AND timestamp >= %s ORDER BY timestamp DESC",
                $video_id,
                $date_limit
            );
        } else {
            $query = $wpdb->prepare(
                "SELECT * FROM $table_name WHERE timestamp >= %s ORDER BY timestamp DESC",
                $date_limit
            );
        }
        
        return $wpdb->get_results($query);
    }
    
    public function get_stats_summary(int $days = 7): array {
        global $wpdb;
        $table_name = $wpdb->prefix . 'avp_analytics';
        $date_limit = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        $stats = array(
            'total_views' => 0,
            'total_plays' => 0,
            'total_completes' => 0,
            'avg_watch_time' => 0
        );
        
        // Total de reproducciones
        $plays = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE event_type = 'play' AND timestamp >= %s",
            $date_limit
        ));
        $stats['total_plays'] = intval($plays);
        
        // Videos completados
        $completes = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE event_type = 'ended' AND timestamp >= %s",
            $date_limit
        ));
        $stats['total_completes'] = intval($completes);
        
        return $stats;
    }
    
    private function get_user_ip(): string {
        $ip = '';
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        return sanitize_text_field($ip);
    }
}

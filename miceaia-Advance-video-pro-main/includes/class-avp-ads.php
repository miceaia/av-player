<?php
declare(strict_types=1);
/**
 * Clase para gestión de anuncios
 */
class AVP_Ads {

    private array $ad_types = array('preroll', 'midroll', 'postroll', 'overlay');
    
    public function __construct() {
        add_action('wp_ajax_avp_get_ad', array($this, 'get_ad'));
        add_action('wp_ajax_nopriv_avp_get_ad', array($this, 'get_ad'));
    }
    
    public function create_ad_campaign(array $data): array {
        $campaign = array(
            'name'        => sanitize_text_field($data['name'] ?? ''),
            'ad_type'     => in_array($data['ad_type'] ?? '', $this->ad_types, true) ? $data['ad_type'] : 'preroll',
            'ad_url'      => esc_url_raw($data['ad_url'] ?? ''),
            'click_url'   => esc_url_raw($data['click_url'] ?? ''),
            'duration'    => isset($data['duration']) ? intval($data['duration']) : 0,
            'skip_after'  => isset($data['skip_after']) ? intval($data['skip_after']) : 0,
            'start_time'  => isset($data['start_time']) ? intval($data['start_time']) : 0,
            'frequency'   => isset($data['frequency']) ? max(1, intval($data['frequency'])) : 1,
            'impressions' => 0,
            'clicks'      => 0,
            'status'      => 'active',
            'created_at'  => current_time('mysql'),
        );

        $campaigns           = get_option('avp_ad_campaigns', array());
        $campaign['id']      = 'ad_' . wp_generate_uuid4();
        $campaigns[$campaign['id']] = $campaign;
        update_option('avp_ad_campaigns', $campaigns);

        return $campaign;
    }

    public function get_ad(): void {
        check_ajax_referer('avp-nonce', 'nonce');

        $video_id = sanitize_text_field(wp_unslash($_POST['video_id'] ?? ''));
        $ad_type  = sanitize_text_field(wp_unslash($_POST['ad_type'] ?? 'preroll'));
        
        $campaigns = get_option('avp_ad_campaigns', array());
        $active_ads = array();
        
        foreach ($campaigns as $campaign) {
            if ($campaign['status'] === 'active' && $campaign['ad_type'] === $ad_type) {
                $active_ads[] = $campaign;
            }
        }
        
        if (empty($active_ads)) {
            wp_send_json_error(array('message' => 'No ads available'));
        }
        
        // Seleccionar un anuncio aleatorio
        $selected_ad = $active_ads[array_rand($active_ads)];
        
        // Incrementar impresiones
        $campaigns[$selected_ad['id']]['impressions']++;
        update_option('avp_ad_campaigns', $campaigns);
        
        wp_send_json_success($selected_ad);
    }
    
    public function track_ad_impression(string $ad_id): void {
        $campaigns = get_option('avp_ad_campaigns', array());
        
        if (isset($campaigns[$ad_id])) {
            $campaigns[$ad_id]['impressions']++;
            update_option('avp_ad_campaigns', $campaigns);
        }
    }
    
    public function track_ad_click(string $ad_id): void {
        $campaigns = get_option('avp_ad_campaigns', array());
        
        if (isset($campaigns[$ad_id])) {
            $campaigns[$ad_id]['clicks']++;
            update_option('avp_ad_campaigns', $campaigns);
        }
    }
    
    public function get_campaign(string $ad_id): ?array {
        $campaigns = get_option('avp_ad_campaigns', array());
        return isset($campaigns[$ad_id]) ? $campaigns[$ad_id] : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function update_campaign(string $ad_id, array $data): ?array
    {
        $campaigns = get_option('avp_ad_campaigns', array());

        if (!isset($campaigns[$ad_id])) {
            return null;
        }

        $allowed_fields = array('name', 'ad_url', 'click_url', 'duration', 'skip_after', 'start_time', 'frequency', 'status');

        foreach ($allowed_fields as $field) {
            if (isset($data[$field])) {
                switch ($field) {
                    case 'ad_url':
                    case 'click_url':
                        $campaigns[$ad_id][$field] = esc_url_raw((string) $data[$field]);
                        break;
                    case 'duration':
                    case 'skip_after':
                    case 'start_time':
                    case 'frequency':
                        $campaigns[$ad_id][$field] = intval($data[$field]);
                        break;
                    case 'status':
                        $campaigns[$ad_id][$field] = in_array($data[$field], array('active', 'paused'), true)
                            ? $data[$field]
                            : $campaigns[$ad_id][$field];
                        break;
                    default:
                        $campaigns[$ad_id][$field] = sanitize_text_field((string) $data[$field]);
                }
            }
        }

        $campaigns[$ad_id]['updated_at'] = current_time('mysql');
        update_option('avp_ad_campaigns', $campaigns);

        return $campaigns[$ad_id];
    }

    public function delete_campaign(string $ad_id): bool {
        $campaigns = get_option('avp_ad_campaigns', array());

        if (isset($campaigns[$ad_id])) {
            unset($campaigns[$ad_id]);
            update_option('avp_ad_campaigns', $campaigns);
            return true;
        }

        return false;
    }

    public function get_all_campaigns(): array {
        $campaigns = get_option('avp_ad_campaigns', array());
        return is_array($campaigns) ? $campaigns : array();
    }

    public function get_campaign_stats(?string $ad_id = null): ?array {
        $campaigns = get_option('avp_ad_campaigns', array());

        if ($ad_id) {
            if (!isset($campaigns[$ad_id])) {
                return null;
            }
            
            $campaign = $campaigns[$ad_id];
            $ctr = $campaign['impressions'] > 0 ? ($campaign['clicks'] / $campaign['impressions']) * 100 : 0;
            
            return array(
                'impressions' => $campaign['impressions'],
                'clicks' => $campaign['clicks'],
                'ctr' => round($ctr, 2)
            );
        }
        
        // Stats para todas las campañas
        $total_impressions = 0;
        $total_clicks = 0;
        
        foreach ($campaigns as $campaign) {
            $total_impressions += $campaign['impressions'];
            $total_clicks += $campaign['clicks'];
        }
        
        $ctr = $total_impressions > 0 ? ($total_clicks / $total_impressions) * 100 : 0;
        
        return array(
            'total_campaigns' => count($campaigns),
            'total_impressions' => $total_impressions,
            'total_clicks' => $total_clicks,
            'avg_ctr' => round($ctr, 2)
        );
    }
    
    /**
     * @return array<string, mixed>|null
     */
    public function parse_vast_xml(string $vast_url): ?array {
        $response = wp_safe_remote_get(esc_url_raw($vast_url));

        if (is_wp_error($response)) {
            return null;
        }

        $xml = simplexml_load_string(wp_remote_retrieve_body($response));

        if (!$xml) {
            return null;
        }
        
        $ad_data = array(
            'title' => (string) $xml->Ad->InLine->AdTitle,
            'duration' => (string) $xml->Ad->InLine->Creatives->Creative->Linear->Duration,
            'media_files' => array(),
            'click_through' => (string) $xml->Ad->InLine->Creatives->Creative->Linear->VideoClicks->ClickThrough
        );
        
        // Extraer archivos de medios
        foreach ($xml->Ad->InLine->Creatives->Creative->Linear->MediaFiles->MediaFile as $media) {
            $ad_data['media_files'][] = array(
                'url' => (string) $media,
                'type' => (string) $media['type'],
                'width' => (int) $media['width'],
                'height' => (int) $media['height']
            );
        }
        
        return $ad_data;
    }
}
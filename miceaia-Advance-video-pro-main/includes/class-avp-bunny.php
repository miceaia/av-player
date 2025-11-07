<?php
declare(strict_types=1);
/**
 * Clase para integración con Bunny.net
 */
class AVP_Bunny {

    private string $api_key = '';
    private string $library_id = '';
    private string $cdn_hostname = '';
    private string $api_url = 'https://video.bunnycdn.com';
    
    public function __construct() {
        $settings = get_option('avp_settings', array());
        $this->api_key = $settings['bunny_api_key'] ?? '';
        $this->library_id = $settings['bunny_library_id'] ?? '';
        $this->cdn_hostname = $settings['bunny_cdn_hostname'] ?? '';
        
        // AJAX endpoints
        add_action('wp_ajax_avp_get_bunny_videos', array($this, 'get_videos'));
        add_action('wp_ajax_avp_get_bunny_collections', array($this, 'get_collections'));
        add_action('wp_ajax_avp_search_bunny_videos', array($this, 'search_videos'));
        add_action('wp_ajax_avp_get_bunny_video_details', array($this, 'get_video_details'));
        add_action('wp_ajax_avp_test_bunny_connection', array($this, 'test_connection'));
    }
    
    /**
     * Obtener lista de videos desde Bunny.net
     */
    public function get_videos(): void {
        check_ajax_referer('avp-admin-nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }
        
        $page           = isset($_POST['page']) ? intval(wp_unslash($_POST['page'])) : 1;
        $items_per_page = isset($_POST['items_per_page']) ? intval(wp_unslash($_POST['items_per_page'])) : 100;
        $collection_id  = isset($_POST['collection_id']) ? sanitize_text_field(wp_unslash($_POST['collection_id'])) : '';
        
        $endpoint = "/library/{$this->library_id}/videos";
        $params = array(
            'page' => $page,
            'itemsPerPage' => $items_per_page
        );
        
        if (!empty($collection_id)) {
            $params['collection'] = $collection_id;
        }
        
        $response = $this->make_request('GET', $endpoint, $params);
        
        if (is_wp_error($response)) {
            wp_send_json_error(array('message' => $response->get_error_message()));
        }
        
        wp_send_json_success($response);
    }
    
    /**
     * Obtener colecciones de videos
     */
    public function get_collections(): void {
        check_ajax_referer('avp-admin-nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }
        
        $endpoint = "/library/{$this->library_id}/collections";
        $params = array(
            'page' => 1,
            'itemsPerPage' => 100
        );
        
        $response = $this->make_request('GET', $endpoint, $params);
        
        if (is_wp_error($response)) {
            wp_send_json_error(array('message' => $response->get_error_message()));
        }
        
        wp_send_json_success($response);
    }
    
    /**
     * Buscar videos en Bunny.net
     */
    public function search_videos(): void {
        check_ajax_referer('avp-admin-nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }
        
        $search_term = isset($_POST['search']) ? sanitize_text_field(wp_unslash($_POST['search'])) : '';
        
        if (empty($search_term)) {
            wp_send_json_error(array('message' => 'Search term is required'));
        }
        
        $endpoint = "/library/{$this->library_id}/videos";
        $params = array(
            'search' => $search_term,
            'page' => 1,
            'itemsPerPage' => 50
        );
        
        $response = $this->make_request('GET', $endpoint, $params);
        
        if (is_wp_error($response)) {
            wp_send_json_error(array('message' => $response->get_error_message()));
        }
        
        wp_send_json_success($response);
    }
    
    /**
     * Obtener detalles de un video específico
     */
    public function get_video_details(): void {
        check_ajax_referer('avp-admin-nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }
        
        $video_id = isset($_POST['video_id']) ? sanitize_text_field(wp_unslash($_POST['video_id'])) : '';
        
        if (empty($video_id)) {
            wp_send_json_error(array('message' => 'Video ID is required'));
        }
        
        $endpoint = "/library/{$this->library_id}/videos/{$video_id}";
        $response = $this->make_request('GET', $endpoint);
        
        if (is_wp_error($response)) {
            wp_send_json_error(array('message' => $response->get_error_message()));
        }
        
        wp_send_json_success($response);
    }
    
    /**
     * Probar conexión con Bunny.net
     */
    public function test_connection(): void {
        check_ajax_referer('avp-admin-nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }
        
        // Actualizar credenciales temporalmente para la prueba
        $test_api_key    = isset($_POST['api_key']) ? sanitize_text_field(wp_unslash($_POST['api_key'])) : $this->api_key;
        $test_library_id = isset($_POST['library_id']) ? sanitize_text_field(wp_unslash($_POST['library_id'])) : $this->library_id;
        
        if (empty($test_api_key) || empty($test_library_id)) {
            wp_send_json_error(array('message' => 'API Key and Library ID are required'));
        }
        
        $endpoint = "/library/{$test_library_id}/videos";
        $params = array('page' => 1, 'itemsPerPage' => 1);
        
        $response = $this->make_request('GET', $endpoint, $params, $test_api_key);
        
        if (is_wp_error($response)) {
            wp_send_json_error(array(
                'message' => 'Connection failed: ' . $response->get_error_message()
            ));
        }
        
        wp_send_json_success(array(
            'message' => 'Connection successful!',
            'library_name' => $response['libraryName'] ?? 'Unknown'
        ));
    }
    
    /**
     * Realizar petición a la API de Bunny.net
     */
    /**
     * @return array<string, mixed>|\WP_Error
     */
    private function make_request(string $method, string $endpoint, array $params = array(), ?string $custom_api_key = null) {
        $api_key = $custom_api_key ?? $this->api_key;

        if (empty($api_key)) {
            return new \WP_Error('no_api_key', 'Bunny.net API key not configured');
        }

        $url = esc_url_raw($this->api_url . $endpoint);

        $args = array(
            'method' => $method,
            'headers' => array(
                'AccessKey' => $api_key,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            ),
            'timeout' => 30
        );
        
        if ($method === 'GET' && !empty($params)) {
            $url = add_query_arg($params, $url);
        } elseif ($method === 'POST' && !empty($params)) {
            $args['body'] = json_encode($params);
        }

        try {
            $response = wp_remote_request($url, $args);
        } catch (\Exception $exception) {
            return new \WP_Error('request_exception', $exception->getMessage());
        }

        if (is_wp_error($response)) {
            return $response;
        }
        
        $body = wp_remote_retrieve_body($response);
        $code = wp_remote_retrieve_response_code($response);
        
        if ($code !== 200) {
            return new \WP_Error('api_error', "API returned status code {$code}: {$body}");
        }
        
        $data = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return new \WP_Error('json_error', 'Failed to decode API response');
        }

        if (!is_array($data)) {
            return new \WP_Error('invalid_response', 'Unexpected API response payload');
        }

        return $data;
    }

    /**
     * Generar URL del video para reproducción
     */
    /**
     * @return string|\WP_Error
     */
    public function get_video_url(string $video_id, bool $use_iframe = false) {
        if (empty($this->cdn_hostname)) {
            return new \WP_Error('no_cdn', 'Bunny.net CDN hostname not configured');
        }

        if ($use_iframe) {
            // URL del iframe embed
            return "https://iframe.mediadelivery.net/embed/{$this->library_id}/{$video_id}";
        } else {
            // URL del video HLS para reproducción directa
            return "https://{$this->cdn_hostname}/{$video_id}/playlist.m3u8";
        }
    }
    
    /**
     * Generar URL de thumbnail
     */
    public function get_thumbnail_url(string $video_id, int $width = 1920, int $height = 1080): string {
        if (empty($this->cdn_hostname)) {
            return '';
        }

        $video_id = sanitize_file_name($video_id);

        return "https://{$this->cdn_hostname}/{$video_id}/thumbnail.jpg";
    }
    
    /**
     * Validar configuración de Bunny.net
     */
    public function is_configured(): bool {
        return !empty($this->api_key) && !empty($this->library_id);
    }
}

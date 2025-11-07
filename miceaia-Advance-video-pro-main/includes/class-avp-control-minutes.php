<?php
/**
 * Integración con el plugin externo Control Minutos.
 */
class AVP_Control_Minutes {
    const CLIENT_SLUG = 'advanced-video-player';

    /**
     * @var AVP_Control_Minutes|null
     */
    private static $instance = null;

    /**
     * Obtiene la instancia singleton.
     *
     * @return AVP_Control_Minutes
     */
    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct() {
        add_action('plugins_loaded', array($this, 'register_client'), 20);
        add_filter('avp_localized_script_data', array($this, 'inject_localized_data'));

        add_action('wp_ajax_avp_control_minutes_event', array($this, 'handle_event'));
        add_action('wp_ajax_nopriv_avp_control_minutes_event', array($this, 'handle_event'));
    }

    /**
     * Determina si el plugin externo está disponible.
     *
     * @return bool
     */
    public function is_available() {
        if (defined('CONTROL_MINUTOS_VERSION')) {
            return true;
        }

        if (class_exists('Control_Minutos') || class_exists('Control_Minutos_Plugin')) {
            return true;
        }

        if (function_exists('control_minutos')) {
            return true;
        }

        if (has_action('control_minutos/register_client') || has_filter('control_minutos/client_config')) {
            return true;
        }

        return false;
    }

    /**
     * Permite que el plugin externo detecte este reproductor y se registre.
     */
    public function register_client() {
        if (!$this->is_available()) {
            return;
        }

        $args = apply_filters(
            'avp_control_minutes_client_args',
            array(
                'slug'   => self::CLIENT_SLUG,
                'name'   => __('Advanced Video Player', 'advanced-video-player'),
                'events' => array('ready', 'play', 'pause', 'progress', 'complete'),
            )
        );

        /**
         * Notifica al plugin Control Minutos que este reproductor está listo para enviar eventos.
         */
        do_action('control_minutos/register_client', $args);
    }

    /**
     * Devuelve información de estado para la interfaz de administración.
     *
     * @return array{
     *     available: bool,
     *     connected: bool,
     *     details: array<string,mixed>
     * }
     */
    public function get_status() {
        $available = $this->is_available();
        $external  = apply_filters('control_minutos/client_status', array(), self::CLIENT_SLUG);

        $connected = false;
        if (is_array($external) && !empty($external['connected'])) {
            $connected = (bool) $external['connected'];
        } elseif ($available) {
            // Si el plugin está activo pero no devuelve estado, asumimos que la conexión es posible.
            $connected = true;
        }

        return array(
            'available' => $available,
            'connected' => $connected,
            'details'   => is_array($external) ? $external : array(),
        );
    }

    /**
     * Inyecta la configuración para el script frontal.
     *
     * @param array $data Datos localizados actuales.
     *
     * @return array
     */
    public function inject_localized_data($data) {
        $config = array(
            'enabled' => false,
            'client'  => self::CLIENT_SLUG,
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'action'  => 'avp_control_minutes_event',
            'nonce'   => wp_create_nonce('avp-control-minutes'),
            'rest'    => array(
                'track'  => '',
                'status' => '',
                'nonce'  => '',
            ),
        );

        $external = apply_filters('control_minutos/client_config', array(), self::CLIENT_SLUG);

        if (is_array($external) && !empty($external)) {
            $config = array_merge($config, $external);
        }

        if ($this->is_available() || !empty($config['rest']['track'])) {
            $config['enabled'] = true;
        }

        $data['controlMinutes'] = $config;

        return $data;
    }

    /**
     * Gestiona los eventos enviados desde el frontal.
     */
    public function handle_event() {
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'avp-control-minutes')) {
            wp_send_json_error(array('message' => __('Invalid security token', 'advanced-video-player')), 403);
        }

        $events = array();

        if (isset($_POST['events'])) {
            $events = $this->normalize_events($_POST['events']);
        } else {
            $events[] = $this->sanitize_event($_POST);
        }

        $events = array_values(array_filter($events));

        foreach ($events as $payload) {
            /**
             * Permite que el plugin externo procese cada evento.
             */
            do_action('control_minutos/receive_event', $payload);
        }

        $response = apply_filters('control_minutos/receive_event_response', array('received' => true), $events);

        wp_send_json_success(array(
            'events'   => $events,
            'response' => $response,
        ));
    }

    /**
     * Sanitiza el contexto enviado desde JavaScript.
     *
     * @param array $context
     *
     * @return array
     */
    private function sanitize_context($context) {
        $sanitized = array();

        foreach ($context as $key => $value) {
            $sanitized_key = sanitize_key($key);

            if (is_numeric($value)) {
                $sanitized[$sanitized_key] = intval($value);
            } else {
                $sanitized[$sanitized_key] = sanitize_text_field(wp_unslash($value));
            }
        }

        return $sanitized;
    }

    /**
     * Sanitiza la metadata adicional.
     *
     * @param array $meta
     *
     * @return array
     */
    private function sanitize_meta(array $meta): array {
        $sanitized = array();

        foreach ($meta as $key => $value) {
            $sanitized_key = sanitize_key($key);

            if (is_scalar($value)) {
                if (is_numeric($value)) {
                    $sanitized[$sanitized_key] = floatval($value);
                } else {
                    $sanitized[$sanitized_key] = sanitize_text_field(wp_unslash($value));
                }
            }
        }

        return $sanitized;
    }

    /**
     * Normaliza los eventos recibidos desde la llamada AJAX.
     *
     * @param array|string $events
     *
     * @return array
     */
    private function normalize_events($events) {
        if (is_string($events)) {
            $decoded = json_decode(wp_unslash($events), true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $events = $decoded;
            }
        }

        if (!is_array($events)) {
            return array();
        }

        $sanitized = array();

        foreach ($events as $event) {
            if (!is_array($event)) {
                continue;
            }

            $sanitized_event = $this->sanitize_event($event);

            if (!empty($sanitized_event['event'])) {
                $sanitized[] = $sanitized_event;
            }
        }

        return $sanitized;
    }

    /**
     * Sanitiza un único evento recibido desde JavaScript.
     *
     * @param array $source
     *
     * @return array
     */
    private function sanitize_event($source) {
        $event = array(
            'event'     => sanitize_text_field(wp_unslash($source['event'] ?? '')),
            'videoId'   => sanitize_text_field(wp_unslash($source['videoId'] ?? '')),
            'playerId'  => sanitize_text_field(wp_unslash($source['playerId'] ?? '')),
            'seconds'   => isset($source['seconds']) ? floatval(wp_unslash($source['seconds'])) : 0,
            'position'  => isset($source['position']) ? floatval(wp_unslash($source['position'])) : 0,
            'duration'  => isset($source['duration']) ? floatval(wp_unslash($source['duration'])) : 0,
            'timestamp' => isset($source['timestamp']) ? sanitize_text_field(wp_unslash($source['timestamp'])) : gmdate('c'),
            'client'    => self::CLIENT_SLUG,
            'userId'    => get_current_user_id(),
            'context'   => array(),
            'meta'      => array(),
        );

        if (isset($source['context']) && is_array($source['context'])) {
            $event['context'] = $this->sanitize_context($source['context']);
        }

        if (isset($source['meta']) && is_array($source['meta'])) {
            $event['meta'] = $this->sanitize_meta(is_array($source['meta']) ? $source['meta'] : array());
        }

        return $event;
    }
}

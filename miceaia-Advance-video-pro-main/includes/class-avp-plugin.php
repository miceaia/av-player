<?php
declare(strict_types=1);
namespace AVP;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Plugin {

    /**
     * Singleton instance.
     *
     * @var Plugin|null
     */
    private static ?Plugin $instance = null;

    /**
     * Accumulated dependency errors.
     *
     * @var array<int, string>
     */
    private array $dependency_errors = array();

    /**
     * Shortcode handler instance.
     */
    private ?\AVP_Shortcode $shortcode_handler = null;

    /**
     * Analytics instance.
     */
    private ?\AVP_Analytics $analytics = null;

    /**
     * Bunny integration instance.
     */
    private ?\AVP_Bunny $bunny = null;

    /**
     * Control minutos bridge instance.
     */
    private ?\AVP_Control_Minutes_Bridge $control_minutes_bridge = null;

    /**
     * Returns singleton instance.
     */
    public static function instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct() {
        $this->load_dependencies();

        if ( ! empty( $this->dependency_errors ) ) {
            \add_action( 'admin_notices', array( $this, 'render_dependency_notice' ) );
            \add_action( 'network_admin_notices', array( $this, 'render_dependency_notice' ) );
            return;
        }

        \add_action( 'init', array( $this, 'init' ) );
        \add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        \add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
        \add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
        \add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_block_editor_assets' ) );
    }

    /**
     * Loads PHP dependencies with safeguards.
     */
    private function load_dependencies(): void {
        $this->require_dependency( 'includes/class-avp-admin.php', 'AVP_Admin' );
        $this->require_dependency( 'includes/class-avp-shortcode.php', 'AVP_Shortcode' );
        $this->require_dependency( 'includes/class-avp-player.php', 'AVP_Player' );
        $this->require_dependency( 'includes/class-avp-ads.php', 'AVP_Ads' );
        $this->require_dependency( 'includes/class-avp-analytics.php', 'AVP_Analytics' );
        $this->require_dependency( 'includes/class-avp-bunny.php', 'AVP_Bunny' );
        $this->require_dependency( 'includes/class-avp-control-minutes-bridge.php', 'AVP_Control_Minutes_Bridge' );

        if ( defined( 'WP_CLI' ) && WP_CLI ) {
            $this->require_dependency( 'includes/class-avp-cli.php', 'AVP\\CLI\\Command' );
        }
    }

    /**
     * Registers hooks once WordPress has initialised.
     */
    public function init(): void {
        $this->maybe_run_activation_tasks();
        $this->init_components();
    }

    /**
     * Initialise plugin components that require WordPress context.
     */
    private function init_components(): void {
        if ( class_exists( '\\AVP_Shortcode' ) && ! ( $this->shortcode_handler instanceof \AVP_Shortcode ) ) {
            $this->shortcode_handler = new \AVP_Shortcode();
        }

        if ( class_exists( '\\AVP_Bunny' ) && ! ( $this->bunny instanceof \AVP_Bunny ) ) {
            $this->bunny = new \AVP_Bunny();
        }

        if ( class_exists( '\\AVP_Analytics' ) && ! ( $this->analytics instanceof \AVP_Analytics ) ) {
            $this->analytics = new \AVP_Analytics();
        }

        if ( class_exists( '\\AVP_Control_Minutes_Bridge' ) ) {
            $this->control_minutes_bridge = \AVP_Control_Minutes_Bridge::instance();
        }

        if ( \is_admin() && class_exists( '\\AVP_Admin' ) ) {
            new \AVP_Admin();
        }

        $this->register_cli_commands();
    }

    /**
     * Optionally run activation routines outside of activation hook.
     */
    private function maybe_run_activation_tasks(): void {
        if ( ! \get_option( 'avp_just_activated' ) ) {
            return;
        }

        $this->run_installation_tasks();
        \delete_option( 'avp_just_activated' );
    }

    /**
     * Executes installation routines safely.
     */
    private function run_installation_tasks(): void {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();
        $table_name      = $wpdb->prefix . 'avp_analytics';

        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            video_id varchar(255) NOT NULL,
            video_url text NOT NULL,
            event_type varchar(50) NOT NULL,
            user_id bigint(20),
            ip_address varchar(100),
            user_agent text,
            duration int(11),
            timestamp datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY video_id (video_id),
            KEY event_type (event_type)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        \dbDelta( $sql );

        if ( ! \get_option( 'avp_settings' ) ) {
            \add_option( 'avp_settings', array(
                'default_player_width'  => '100%',
                'default_player_height' => '500px',
                'autoplay'              => false,
                'controls'              => true,
                'loop'                  => false,
                'muted'                 => false,
                'preload'               => 'metadata',
                'enable_ads'            => false,
                'enable_analytics'      => true,
                'bunny_api_key'         => '',
                'bunny_library_id'      => '',
                'bunny_cdn_hostname'    => '',
            ) );
        }
    }

    /**
     * Enqueues frontend assets.
     */
    public function enqueue_scripts(): void {
        \wp_enqueue_style( 'videojs-css', 'https://vjs.zencdn.net/8.10.0/video-js.css', array(), '8.10.0' );
        \wp_enqueue_script( 'videojs', 'https://vjs.zencdn.net/8.10.0/video.min.js', array(), '8.10.0', true );
        \wp_enqueue_script( 'hlsjs', 'https://cdn.jsdelivr.net/npm/hls.js@latest', array(), null, true );
        \wp_enqueue_script( 'dashjs', 'https://cdn.dashjs.org/latest/dash.all.min.js', array(), null, true );

        \wp_enqueue_style( 'avp-styles', \AVP_URL . 'assets/css/avp-player.css', array(), \AVP_VERSION );
        \wp_enqueue_script( 'avp-player', \AVP_URL . 'assets/js/avp-player.js', array( 'jquery', 'videojs' ), \AVP_VERSION, true );

        $settings = \get_option( 'avp_settings', array() );
        if ( ! \is_array( $settings ) ) {
            $settings = array();
        }

        $watermark_lines = array();
        $user            = null;

        if ( \is_user_logged_in() ) {
            $maybe_user = \wp_get_current_user();
            if ( $maybe_user instanceof \WP_User && $maybe_user->exists() ) {
                $user = $maybe_user;
            }
        }

        $raw_ip = $this->get_request_ip_address();
        if ( $user instanceof \WP_User ) {
            $raw_ip = \apply_filters( 'avp_watermark_ip_address', $raw_ip, $user );
        }

        $ip_display = $raw_ip !== '' ? \sanitize_text_field( $raw_ip ) : \__( 'No disponible', 'advanced-video-player' );
        $watermark_lines[] = sprintf( \__( 'IP: %s', 'advanced-video-player' ), $ip_display );

        if ( $user instanceof \WP_User ) {
            $email = \sanitize_email( $user->user_email );

            $dni_meta_keys = \apply_filters( 'avp_watermark_dni_meta_keys', array( 'dni', 'DNI', 'documento', 'document_number', 'documento_identidad' ) );
            $dni_value     = '';

            if ( \is_array( $dni_meta_keys ) ) {
                foreach ( $dni_meta_keys as $meta_key ) {
                    $meta_key_original = \is_string( $meta_key ) ? trim( $meta_key ) : '';
                    if ( '' === $meta_key_original ) {
                        continue;
                    }

                    $candidate = \get_user_meta( $user->ID, $meta_key_original, true );
                    if ( '' === $candidate ) {
                        $candidate = \get_user_meta( $user->ID, \sanitize_key( $meta_key_original ), true );
                    }

                    if ( '' !== $candidate ) {
                        $dni_value = $candidate;
                        break;
                    }
                }
            }

            $dni_value = \apply_filters( 'avp_watermark_dni_value', $dni_value, $user );
            $dni_value = '' !== $dni_value ? \sanitize_text_field( $dni_value ) : '';

            $email_display = ! empty( $email ) ? $email : \__( 'No disponible', 'advanced-video-player' );
            $watermark_lines[] = sprintf( \__( 'Email: %s', 'advanced-video-player' ), $email_display );

            $dni_display = '' !== $dni_value ? $dni_value : \__( 'No registrado', 'advanced-video-player' );
            $watermark_lines[] = sprintf( \__( 'DNI: %s', 'advanced-video-player' ), $dni_display );
        } else {
            $watermark_lines[] = sprintf( \__( 'Email: %s', 'advanced-video-player' ), \__( 'Invitado', 'advanced-video-player' ) );
            $watermark_lines[] = sprintf( \__( 'DNI: %s', 'advanced-video-player' ), \__( 'No registrado', 'advanced-video-player' ) );
        }

        $watermark = array(
            'enabled'      => ! empty( $watermark_lines ),
            'lines'        => array_map( '\\sanitize_text_field', $watermark_lines ),
            'moveInterval' => (int) \apply_filters( 'avp_watermark_move_interval', 15000 ),
        );

        $localized = array(
            'ajaxUrl'          => \admin_url( 'admin-ajax.php' ),
            'nonce'            => \wp_create_nonce( 'avp-nonce' ),
            'settings'         => $settings,
            'isUserLoggedIn'   => \is_user_logged_in(),
            'watermark'        => $watermark,
            'labels'           => array(
                'quality'       => \__( 'Calidad', 'advanced-video-player' ),
                'qualityOption' => \__( 'Cambiar a %s', 'advanced-video-player' ),
            ),
        );

        $localized = \apply_filters( 'avp_localized_script_data', $localized );
        \wp_localize_script( 'avp-player', 'avpData', $localized );
    }

    /**
     * Enqueues admin assets.
     */
    public function admin_enqueue_scripts( string $hook ): void {
        if ( false === strpos( (string) $hook, 'advanced-video-player' ) && 'post.php' !== $hook && 'post-new.php' !== $hook ) {
            return;
        }

        \wp_enqueue_style( 'avp-admin-styles', \AVP_URL . 'assets/css/avp-admin.css', array(), \AVP_VERSION );
        \wp_enqueue_script( 'hlsjs', 'https://cdn.jsdelivr.net/npm/hls.js@latest', array(), null, true );
        \wp_enqueue_script( 'avp-admin', \AVP_URL . 'assets/js/avp-admin.js', array( 'jquery', 'hlsjs' ), \AVP_VERSION, true );

        $settings = \get_option( 'avp_settings', array() );

        \wp_localize_script( 'avp-admin', 'avpAdmin', array(
            'ajaxUrl'  => \admin_url( 'admin-ajax.php' ),
            'nonce'    => \wp_create_nonce( 'avp-admin-nonce' ),
            'settings' => $settings,
            'strings'  => array(
                'bunnyMissingCredentials' => \__( 'Configura tus credenciales de Bunny.net en Ajustes para ver tu biblioteca.', 'advanced-video-player' ),
                'bunnyLoadError'          => \__( 'No se pudieron cargar los videos de Bunny.net.', 'advanced-video-player' ),
                'bunnySearchError'        => \__( 'No se pudieron obtener resultados para la búsqueda en Bunny.net.', 'advanced-video-player' ),
                'bunnyNetworkError'       => \__( 'Ocurrió un error de red al contactar con Bunny.net.', 'advanced-video-player' ),
                'bunnyNoVideos'           => \__( 'No se encontraron videos de Bunny.net que coincidan con el filtro actual.', 'advanced-video-player' ),
                'bunnyPageInfo'           => \__( 'Página %1$s de %2$s', 'advanced-video-player' ),
            ),
        ) );

        \wp_enqueue_media();
    }

    /**
     * Enqueues Gutenberg assets.
     */
    public function enqueue_block_editor_assets(): void {
        \wp_enqueue_script(
            'avp-gutenberg-block',
            \AVP_URL . 'assets/js/avp-gutenberg-block.js',
            array( 'wp-blocks', 'wp-element', 'wp-editor', 'wp-components', 'wp-i18n' ),
            \AVP_VERSION,
            true
        );

        \wp_localize_script( 'avp-gutenberg-block', 'avpGutenberg', array(
            'ajaxUrl' => \admin_url( 'admin-ajax.php' ),
            'nonce'   => \wp_create_nonce( 'avp-admin-nonce' ),
        ) );
    }

    /**
     * Registers plugin admin menus.
     */
    public function add_admin_menu(): void {
        \add_menu_page(
            \__( 'Advanced Video Player', 'advanced-video-player' ),
            \__( 'Video Player', 'advanced-video-player' ),
            'manage_options',
            'advanced-video-player',
            array( $this, 'admin_page' ),
            'dashicons-video-alt3',
            30
        );

        \add_submenu_page(
            'advanced-video-player',
            \__( 'Add Video', 'advanced-video-player' ),
            \__( 'Add Video', 'advanced-video-player' ),
            'manage_options',
            'advanced-video-player',
            array( $this, 'admin_page' )
        );

        \add_submenu_page(
            'advanced-video-player',
            \__( 'Bunny.net Library', 'advanced-video-player' ),
            \__( 'Bunny.net Library', 'advanced-video-player' ),
            'manage_options',
            'advanced-video-player-bunny',
            array( $this, 'bunny_library_page' )
        );

        \add_submenu_page(
            'advanced-video-player',
            \__( 'Settings', 'advanced-video-player' ),
            \__( 'Settings', 'advanced-video-player' ),
            'manage_options',
            'advanced-video-player-settings',
            array( $this, 'settings_page' )
        );

        \add_submenu_page(
            'advanced-video-player',
            \__( 'Control de minutos', 'advanced-video-player' ),
            \__( 'Control de minutos', 'advanced-video-player' ),
            'manage_options',
            'advanced-video-player-control-minutes',
            array( $this, 'control_minutes_page' )
        );

        \add_submenu_page(
            'advanced-video-player',
            \__( 'Analytics', 'advanced-video-player' ),
            \__( 'Analytics', 'advanced-video-player' ),
            'manage_options',
            'advanced-video-player-analytics',
            array( $this, 'analytics_page' )
        );
    }

    /**
     * Primary admin page renderer.
     */
    public function admin_page(): void {
        $this->include_template( 'templates/admin/main-page.php' );
    }

    /**
     * Bunny library page renderer.
     */
    public function bunny_library_page(): void {
        $this->include_template( 'templates/admin/bunny-library.php' );
    }

    /**
     * Settings page renderer.
     */
    public function settings_page(): void {
        $this->include_template( 'templates/admin/settings-page.php' );
    }

    /**
     * Control minutos page renderer.
     */
    public function control_minutes_page(): void {
        $this->include_template( 'templates/admin/control-minutes.php' );
    }

    /**
     * Analytics page renderer.
     */
    public function analytics_page(): void {
        $this->include_template( 'templates/admin/analytics-page.php' );
    }

    /**
     * Returns shortcode handler instance.
     */
    public function get_shortcode_handler(): ?\AVP_Shortcode {
        return $this->shortcode_handler;
    }

    /**
     * Returns analytics instance lazily.
     */
    public function get_analytics_instance(): ?\AVP_Analytics {
        if ( $this->analytics instanceof \AVP_Analytics ) {
            return $this->analytics;
        }

        if ( class_exists( '\\AVP_Analytics' ) ) {
            $this->analytics = new \AVP_Analytics();
        }

        return $this->analytics;
    }

    /**
     * Includes a template when present.
     */
    private function include_template( string $relative_path ): void {
        $full_path = \AVP_DIR . ltrim( $relative_path, '/' );

        if ( ! file_exists( $full_path ) ) {
            echo '<div class="notice notice-error"><p>' . \esc_html( sprintf( \__( 'No se encontró la plantilla: %s', 'advanced-video-player' ), $relative_path ) ) . '</p></div>';
            return;
        }

        include $full_path;
    }

    /**
     * Attempts to determine the client IP address.
     */
    private function get_request_ip_address(): string {
        $server = $_SERVER;
        $candidates = array(
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR',
        );

        foreach ( $candidates as $header ) {
            if ( empty( $server[ $header ] ) ) {
                continue;
            }

            $raw_value = \wp_unslash( $server[ $header ] );
            $parts     = array_map( 'trim', explode( ',', (string) $raw_value ) );

            foreach ( $parts as $part ) {
                if ( \filter_var( $part, FILTER_VALIDATE_IP ) ) {
                    return $part;
                }
            }
        }

        return '';
    }

    /**
     * Records dependency errors when files or classes are missing.
     */
    private function require_dependency( string $relative_path, ?string $expected_class = null ): void {
        $full_path = \AVP_DIR . ltrim( $relative_path, '/' );

        if ( ! file_exists( $full_path ) ) {
            $this->dependency_errors[] = sprintf(
                /* translators: %s: relative file path */
                \__( 'No se pudo cargar el archivo requerido: %s.', 'advanced-video-player' ),
                $relative_path
            );
            return;
        }

        require_once $full_path;

        if ( $expected_class && ! class_exists( $expected_class, false ) ) {
            $this->dependency_errors[] = sprintf(
                /* translators: 1: class name, 2: relative file path */
                \__( 'La clase %1$s no se encontró en %2$s.', 'advanced-video-player' ),
                $expected_class,
                $relative_path
            );
        }
    }

    /**
     * Registers WP-CLI commands when available.
     */
    private function register_cli_commands(): void {
        if ( ! ( defined( 'WP_CLI' ) && WP_CLI ) ) {
            return;
        }

        if ( ! class_exists( 'WP_CLI' ) || ! class_exists( 'AVP\\CLI\\Command', false ) ) {
            return;
        }

        \WP_CLI::add_command( 'avp', 'AVP\\CLI\\Command' );
    }

    /**
     * Renders dependency notices in the admin when something is missing.
     */
    public function render_dependency_notice(): void {
        if ( empty( $this->dependency_errors ) ) {
            return;
        }

        echo '<div class="notice notice-error"><p>' . \esc_html__( 'Advanced Video Player no se pudo inicializar porque faltan archivos requeridos.', 'advanced-video-player' ) . '</p><ul>';

        foreach ( $this->dependency_errors as $message ) {
            echo '<li>' . \esc_html( $message ) . '</li>';
        }

        echo '</ul><p>' . \esc_html__( 'Asegúrate de subir todos los archivos del plugin o revisa el snippet antes de activarlo.', 'advanced-video-player' ) . '</p></div>';
    }
}

<?php
declare(strict_types=1);
/**
 * Plugin Name: Advanced Video Player Pro – Bunny.net Edition
 * Plugin URI: https://tudominio.com/advanced-video-player
 * Description: Reproductor de video avanzado con integración de Bunny.net, YouTube, Vimeo, MP4, HLS y más.
 * Version: 2.0.0
 * Requires PHP: 7.4.33
 * Requires at least: 5.0
 * Author: Miceanou
 * Author URI: https://miceanou.com
 * License: GPL v2 or later
 * Text Domain: advanced-video-player
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'AVP_MIN_PHP', '7.4.33' );
define( 'AVP_MIN_WP', '5.0' );
define( 'AVP_FILE', __FILE__ );
define( 'AVP_DIR', plugin_dir_path( __FILE__ ) );
define( 'AVP_URL', plugin_dir_url( __FILE__ ) );
define( 'AVP_VERSION', '2.0.0' );

do_action( 'avp/bootstrapping' );

if ( function_exists( 'register_activation_hook' ) ) {
    register_activation_hook( __FILE__, function () {
        update_option( 'avp_just_activated', 1, false );
    } );
}

if ( function_exists( 'register_deactivation_hook' ) ) {
    register_deactivation_hook( __FILE__, function () {
        delete_option( 'avp_just_activated' );
    } );
}

add_action( 'admin_init', function () {
    if ( ! current_user_can( 'activate_plugins' ) ) {
        return;
    }

    $errors = array();
    global $wp_version;

    if ( version_compare( PHP_VERSION, AVP_MIN_PHP, '<' ) ) {
        $errors[] = sprintf( 'NECESITA PHP %s o superior. Detectado: %s', AVP_MIN_PHP, PHP_VERSION );
    }

    if ( isset( $wp_version ) && version_compare( $wp_version, AVP_MIN_WP, '<' ) ) {
        $errors[] = sprintf( 'WordPress %s o superior requerido. Detectado: %s', AVP_MIN_WP, $wp_version );
    }

    $autoload = AVP_DIR . 'vendor/autoload.php';
    if ( file_exists( $autoload ) ) {
        require_once $autoload;
    }

    $main_class_file = AVP_DIR . 'includes/class-avp-plugin.php';
    if ( ! file_exists( $main_class_file ) ) {
        $errors[] = 'Falta includes/class-avp-plugin.php';
    }

    if ( empty( $errors ) ) {
        return;
    }

    deactivate_plugins( plugin_basename( AVP_FILE ) );

    add_action( 'admin_notices', function () use ( $errors ) {
        echo '<div class="notice notice-error"><p><strong>Advanced Video Player Pro</strong> no pudo activarse:</p><ul>';
        foreach ( $errors as $message ) {
            echo '<li>' . esc_html( $message ) . '</li>';
        }
        echo '</ul><p>Consulta <code>wp-content/debug.log</code> para más detalles.</p></div>';
    } );
} );

add_action( 'plugins_loaded', function () {
    $file = AVP_DIR . 'includes/class-avp-plugin.php';
    if ( ! file_exists( $file ) ) {
        return;
    }

    require_once $file;

    if ( class_exists( '\AVP\Plugin' ) ) {
        \AVP\Plugin::instance();
        return;
    }

    add_action( 'admin_notices', function () {
        echo '<div class="notice notice-error"><p>AVP: Clase principal no encontrada.</p></div>';
    } );
} );

/**
 * BLOQUE GUTENBERG - AUTO-DISCOVERY DESDE block.json
 */
add_action( 'init', function() {
    $block_json_file = AVP_DIR . 'blocks/block.json';
    
    if ( ! file_exists( $block_json_file ) ) {
        error_log( 'AVP: block.json not found at ' . $block_json_file );
        return;
    }
    
    error_log( 'AVP: Registering block from block.json' );
    
    register_block_type( $block_json_file );
}, 10 );

/**
 * Callback para renderizar el bloque
 */
function avp_render_block_callback( $attributes, $content ) {
    $src = isset( $attributes['src'] ) ? esc_url( $attributes['src'] ) : '';
    
    if ( ! $src ) {
        return '<div style="background: #fee; padding: 20px; border-radius: 8px; color: #c00; border: 1px solid #f00;">
            ⚠️ Advanced Video Player: Por favor, añade una URL de video
        </div>';
    }
    
    return do_shortcode( '[avp_player src="' . esc_attr( $src ) . '"]' );
}
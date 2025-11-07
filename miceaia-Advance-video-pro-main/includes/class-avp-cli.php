<?php
declare(strict_types=1);
namespace AVP\CLI;

use WP_CLI;
use WP_CLI_Command;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * WP-CLI helpers for Advanced Video Player.
 */
class Command extends WP_CLI_Command {

    /**
     * Performs a quick health check of the hosting environment.
     *
     * ## EXAMPLES
     *
     *     wp avp doctor
     *
     * @when after_wp_load
     */
    public function doctor(): void {
        WP_CLI::log( '=== Advanced Video Player :: Doctor ===' );

        $php_version = PHP_VERSION;
        $wp_version  = get_bloginfo( 'version' );

        WP_CLI::log( sprintf( 'PHP version: %s %s', $php_version, $this->format_requirement_status( $php_version, \AVP_MIN_PHP ) ) );
        WP_CLI::log( sprintf( 'WordPress version: %s %s', $wp_version, $this->format_requirement_status( $wp_version, \AVP_MIN_WP ) ) );

        $extensions = array( 'curl', 'mbstring', 'intl', 'zip', 'gd', 'imagick', 'mysqli', 'opcache' );
        WP_CLI::log( 'Required extensions:' );
        foreach ( $extensions as $extension ) {
            $status = extension_loaded( $extension ) ? '✅ loaded' : '❌ missing';
            WP_CLI::log( sprintf( '  - %s: %s', $extension, $status ) );
        }

        if ( ! function_exists( 'get_plugins' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $plugins        = get_plugins();
        $active_plugins = get_option( 'active_plugins', array() );

        WP_CLI::log( 'Plugins summary:' );
        foreach ( $plugins as $file => $data ) {
            $is_active = in_array( $file, $active_plugins, true );
            $status    = $is_active ? 'active' : 'inactive';
            WP_CLI::log( sprintf( '  - %s (%s) – %s', $data['Name'] ?? $file, $data['Version'] ?? 'n/a', $status ) );
        }

        $themes = wp_get_themes();
        WP_CLI::log( 'Installed themes:' );
        foreach ( $themes as $stylesheet => $theme ) {
            $status = ( get_option( 'stylesheet' ) === $stylesheet ) ? 'active' : 'inactive';
            WP_CLI::log( sprintf( '  - %s (%s) – %s', $theme->get( 'Name' ), $theme->get( 'Version' ), $status ) );
        }

        $debug_log = WP_CONTENT_DIR . '/debug.log';
        if ( file_exists( $debug_log ) && is_readable( $debug_log ) ) {
            WP_CLI::log( 'Recent debug.log entries:' );
            foreach ( $this->tail_file( $debug_log, 20 ) as $line ) {
                WP_CLI::log( '  ' . $line );
            }
        } else {
            WP_CLI::warning( 'debug.log not found or not readable.' );
        }

        WP_CLI::success( 'Doctor check complete.' );
    }

    /**
     * Formats requirement status label.
     *
     * @param string $current Version detected.
     * @param string $required Minimum required version.
     */
    private function format_requirement_status( string $current, string $required ): string {
        return version_compare( $current, $required, '>=' ) ? '(meets requirement)' : '(needs upgrade)';
    }

    /**
     * Reads the last N lines of a file.
     *
     * @param string $path File path.
     * @param int    $lines Number of lines.
     * @return array<int, string>
     */
    private function tail_file( string $path, int $lines = 20 ): array {
        $lines = max( 1, $lines );

        try {
            $file = new \SplFileObject( $path, 'r' );
        } catch ( \RuntimeException $exception ) {
            WP_CLI::warning( sprintf( 'Unable to inspect debug.log: %s', $exception->getMessage() ) );
            return array();
        }

        $file->seek( PHP_INT_MAX );
        $last_line = $file->key();

        $start = max( 0, $last_line - ( $lines - 1 ) );
        $output = array();

        for ( $position = $start; $position <= $last_line; $position++ ) {
            $file->seek( $position );
            $current = rtrim( (string) $file->current() );
            if ( '' !== $current ) {
                $output[] = $current;
            }
        }

        return $output;
    }
}

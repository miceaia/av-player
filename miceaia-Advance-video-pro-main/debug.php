<?php
declare(strict_types=1);
/**
 * Advanced Video Player - Debug Tool
 * 
 * Este archivo ayuda a diagnosticar problemas con el plugin.
 * Colócalo en wp-content/plugins/advanced-video-player-pro/
 * y accede a: tu-sitio.com/wp-content/plugins/advanced-video-player-pro/debug.php
 */

// Cargar WordPress
require_once('../../../../wp-load.php');

// Verificar que sea admin
if (!current_user_can('manage_options')) {
    die('Unauthorized');
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>AVP Debug Tool</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            max-width: 1200px;
            margin: 40px auto;
            padding: 20px;
            background: #f0f0f1;
        }
        .debug-section {
            background: white;
            padding: 20px;
            margin-bottom: 20px;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        h1 {
            color: #1d2327;
        }
        h2 {
            color: #2271b1;
            border-bottom: 2px solid #2271b1;
            padding-bottom: 10px;
        }
        .status {
            padding: 10px;
            border-radius: 4px;
            margin: 10px 0;
        }
        .status.success {
            background: #d7f0db;
            color: #00701a;
            border-left: 4px solid #00a32a;
        }
        .status.error {
            background: #fcf0f1;
            color: #8b0000;
            border-left: 4px solid #d63638;
        }
        .status.warning {
            background: #fcf9e8;
            color: #614200;
            border-left: 4px solid #dba617;
        }
        code {
            background: #f6f7f7;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: 'Courier New', monospace;
        }
        pre {
            background: #1e1e1e;
            color: #d4d4d4;
            padding: 15px;
            border-radius: 4px;
            overflow-x: auto;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background: #f6f7f7;
            font-weight: 600;
        }
        .icon {
            margin-right: 8px;
        }
    </style>
</head>
<body>
    <h1>🔍 Advanced Video Player - Debug Tool</h1>
    
    <!-- Plugin Status -->
    <div class="debug-section">
        <h2>📦 Plugin Status</h2>
        <?php
        $plugin_file = WP_PLUGIN_DIR . '/advanced-video-player-pro/advanced-video-player.php';
        $plugin_active = is_plugin_active('advanced-video-player-pro/advanced-video-player.php');
        ?>
        
        <table>
            <tr>
                <th>Plugin File</th>
                <td>
                    <?php if (file_exists($plugin_file)): ?>
                        <span class="status success">✓ Found</span> 
                        <code><?php echo $plugin_file; ?></code>
                    <?php else: ?>
                        <span class="status error">✗ Not Found</span>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th>Plugin Active</th>
                <td>
                    <?php if ($plugin_active): ?>
                        <span class="status success">✓ Active</span>
                    <?php else: ?>
                        <span class="status error">✗ Inactive</span>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th>Plugin Version</th>
                <td><code><?php echo defined('AVP_VERSION') ? AVP_VERSION : 'Unknown'; ?></code></td>
            </tr>
        </table>
    </div>
    
    <!-- Required Classes -->
    <div class="debug-section">
        <h2>🔧 Required Classes</h2>
        <table>
            <?php
            $required_classes = array(
                'AVP\\Plugin',
                'AVP_Admin',
                'AVP_Shortcode',
                'AVP_Player',
                'AVP_Ads',
                'AVP_Analytics',
                'AVP_Bunny'
            );
            
            foreach ($required_classes as $class) {
                $exists = class_exists($class);
                echo '<tr>';
                echo '<th>' . $class . '</th>';
                echo '<td>';
                if ($exists) {
                    echo '<span class="status success">✓ Loaded</span>';
                } else {
                    echo '<span class="status error">✗ Not Found</span>';
                }
                echo '</td>';
                echo '</tr>';
            }
            ?>
        </table>
    </div>
    
    <!-- Settings -->
    <div class="debug-section">
        <h2>⚙️ Current Settings</h2>
        <?php
        $settings = get_option('avp_settings', array());
        
        if (empty($settings)) {
            echo '<div class="status warning">⚠ No settings found. Plugin may not be configured yet.</div>';
        } else {
            echo '<table>';
            echo '<tr><th>Setting</th><th>Value</th></tr>';
            
            foreach ($settings as $key => $value) {
                $display_value = $value;
                
                // Ocultar API key por seguridad
                if ($key === 'bunny_api_key' && !empty($value)) {
                    $display_value = substr($value, 0, 8) . '...' . substr($value, -4);
                }
                
                // Convertir booleanos
                if (is_bool($value)) {
                    $display_value = $value ? '<span class="status success">✓ Yes</span>' : '<span class="status error">✗ No</span>';
                }
                
                echo '<tr>';
                echo '<th>' . esc_html($key) . '</th>';
                echo '<td>' . $display_value . '</td>';
                echo '</tr>';
            }
            
            echo '</table>';
        }
        ?>
    </div>
    
    <!-- Bunny.net Configuration -->
    <div class="debug-section">
        <h2>🐰 Bunny.net Configuration</h2>
        <?php
        $bunny_configured = !empty($settings['bunny_api_key']) && 
                           !empty($settings['bunny_library_id']) && 
                           !empty($settings['bunny_cdn_hostname']);
        ?>
        
        <div class="status <?php echo $bunny_configured ? 'success' : 'warning'; ?>">
            <?php if ($bunny_configured): ?>
                ✓ Bunny.net is configured
            <?php else: ?>
                ⚠ Bunny.net is not fully configured
            <?php endif; ?>
        </div>
        
        <table>
            <tr>
                <th>API Key</th>
                <td>
                    <?php if (!empty($settings['bunny_api_key'])): ?>
                        <span class="status success">✓ Set</span>
                        (<?php echo substr($settings['bunny_api_key'], 0, 8); ?>...)
                    <?php else: ?>
                        <span class="status error">✗ Not Set</span>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th>Library ID</th>
                <td>
                    <?php if (!empty($settings['bunny_library_id'])): ?>
                        <span class="status success">✓ Set</span>
                        (<code><?php echo esc_html($settings['bunny_library_id']); ?></code>)
                    <?php else: ?>
                        <span class="status error">✗ Not Set</span>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th>CDN Hostname</th>
                <td>
                    <?php if (!empty($settings['bunny_cdn_hostname'])): ?>
                        <span class="status success">✓ Set</span>
                        (<code><?php echo esc_html($settings['bunny_cdn_hostname']); ?></code>)
                    <?php else: ?>
                        <span class="status error">✗ Not Set</span>
                    <?php endif; ?>
                </td>
            </tr>
        </table>
    </div>
    
    <!-- AJAX Actions -->
    <div class="debug-section">
        <h2>🔌 Registered AJAX Actions</h2>
        <?php
        global $wp_filter;
        
        $ajax_actions = array(
            'wp_ajax_avp_save_settings',
            'wp_ajax_avp_get_bunny_videos',
            'wp_ajax_avp_get_bunny_collections',
            'wp_ajax_avp_search_bunny_videos',
            'wp_ajax_avp_get_bunny_video_details',
            'wp_ajax_avp_test_bunny_connection',
            'wp_ajax_avp_track_event',
            'wp_ajax_nopriv_avp_track_event',
            'wp_ajax_avp_get_ad',
            'wp_ajax_nopriv_avp_get_ad'
        );
        
        echo '<table>';
        echo '<tr><th>Action</th><th>Status</th></tr>';
        
        foreach ($ajax_actions as $action) {
            $registered = isset($wp_filter[$action]);
            echo '<tr>';
            echo '<th><code>' . $action . '</code></th>';
            echo '<td>';
            if ($registered) {
                echo '<span class="status success">✓ Registered</span>';
            } else {
                echo '<span class="status error">✗ Not Registered</span>';
            }
            echo '</td>';
            echo '</tr>';
        }
        
        echo '</table>';
        ?>
    </div>

    <!-- File Structure -->
    <div class="debug-section">
        <h2>📁 File Structure</h2>
        <?php
        $required_files = array(
            'advanced-video-player.php',
            'includes/class-avp-admin.php',
            'includes/class-avp-shortcode.php',
            'includes/class-avp-player.php',
            'includes/class-avp-ads.php',
            'includes/class-avp-analytics.php',
            'includes/class-avp-bunny.php',
            'assets/js/avp-admin.js',
            'assets/js/avp-player.js',
            'assets/css/avp-admin.css',
            'assets/css/avp-player.css',
            'templates/admin/main-page.php',
            'templates/admin/settings-page.php',
            'templates/admin/analytics-page.php'
        );
        
        $plugin_dir = WP_PLUGIN_DIR . '/advanced-video-player-pro/';
        
        echo '<table>';
        echo '<tr><th>File</th><th>Status</th></tr>';
        
        foreach ($required_files as $file) {
            $full_path = $plugin_dir . $file;
            $exists = file_exists($full_path);
            
            echo '<tr>';
            echo '<th><code>' . $file . '</code></th>';
            echo '<td>';
            if ($exists) {
                echo '<span class="status success">✓ Found</span>';
            } else {
                echo '<span class="status error">✗ Missing</span>';
            }
            echo '</td>';
            echo '</tr>';
        }
        
        echo '</table>';
        ?>
    </div>

    <!-- WordPress Environment -->
    <div class="debug-section">
        <h2>🌍 WordPress Environment</h2>
        <table>
            <tr>
                <th>WordPress Version</th>
                <td><code><?php echo get_bloginfo('version'); ?></code></td>
            </tr>
            <tr>
                <th>PHP Version</th>
                <td><code><?php echo phpversion(); ?></code></td>
            </tr>
            <tr>
                <th>Admin AJAX URL</th>
                <td><code><?php echo admin_url('admin-ajax.php'); ?></code></td>
            </tr>
            <tr>
                <th>Plugins Dir</th>
                <td><code><?php echo WP_PLUGIN_DIR; ?></code></td>
            </tr>
            <tr>
                <th>Debug Mode</th>
                <td>
                    <?php if (defined('WP_DEBUG') && WP_DEBUG): ?>
                        <span class="status warning">⚠ Enabled</span>
                    <?php else: ?>
                        <span class="status success">✓ Disabled</span>
                    <?php endif; ?>
                </td>
            </tr>
        </table>
    </div>
    
    <div class="debug-section" style="background: #f0f6fc; border-left: 4px solid #0969da;">
        <h2 style="color: #0969da;">💡 Next Steps</h2>
        
        <?php if (!$plugin_active): ?>
            <p>❗ <strong>Plugin is not active.</strong> Go to <a href="<?php echo admin_url('plugins.php'); ?>">Plugins</a> and activate it.</p>
        <?php elseif (!$bunny_configured): ?>
            <p>❗ <strong>Bunny.net is not configured.</strong> Go to <a href="<?php echo admin_url('admin.php?page=advanced-video-player-settings'); ?>">Settings</a> and configure your Bunny.net credentials.</p>
        <?php else: ?>
            <p>✓ Everything looks good! You can now:</p>
            <ul>
                <li>Go to <a href="<?php echo admin_url('admin.php?page=advanced-video-player'); ?>">Add Video</a> to insert videos</li>
                <li>Check <a href="<?php echo admin_url('admin.php?page=advanced-video-player-analytics'); ?>">Analytics</a> to see video statistics</li>
            </ul>
        <?php endif; ?>
    </div>
    
    <p style="text-align: center; color: #666; margin-top: 40px;">
        <small>Generated: <?php echo date('Y-m-d H:i:s'); ?> | 
        <a href="<?php echo admin_url(); ?>">Back to Admin</a></small>
    </p>
</body>
</html>

<?php
/**
 * Página de configuración
 */

if (!defined('ABSPATH')) {
    exit;
}

$settings = get_option('avp_settings', array());
$learndash_active = post_type_exists('sfwd-courses');
$control_minutes_status = class_exists('AVP_Control_Minutes_Bridge') ? AVP_Control_Minutes_Bridge::instance()->get_status() : array(
    'available' => false,
    'connected' => false,
    'details'   => array(),
);
?>

<div class="wrap avp-admin-wrap">
    <h1><?php _e('Video Player Settings', 'advanced-video-player'); ?></h1>
    
    <form id="avp-settings-form" method="post" action="">
        <?php wp_nonce_field('avp-settings-save', 'avp_settings_nonce'); ?>
        
        <div class="avp-settings-container">
            <!-- Bunny.net Configuration -->
            <div class="avp-settings-section">
                <h2>
                    <span class="dashicons dashicons-admin-network"></span>
                    <?php _e('Bunny.net Configuration', 'advanced-video-player'); ?>
                </h2>
                
                <p class="description">
                    <?php _e('Configure your Bunny.net Stream credentials. You can find these in your Bunny.net dashboard.', 'advanced-video-player'); ?>
                    <a href="https://dash.bunny.net/" target="_blank"><?php _e('Open Bunny.net Dashboard', 'advanced-video-player'); ?></a>
                </p>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="bunny_api_key">
                                <?php _e('API Key', 'advanced-video-player'); ?>
                                <span class="required">*</span>
                            </label>
                        </th>
                        <td>
                            <input type="password" 
                                   name="bunny_api_key" 
                                   id="bunny_api_key" 
                                   value="<?php echo esc_attr($settings['bunny_api_key'] ?? ''); ?>" 
                                   class="regular-text" />
                            <button type="button" class="button avp-toggle-password">
                                <span class="dashicons dashicons-visibility"></span>
                            </button>
                            <p class="description">
                                <?php _e('Your Bunny.net Stream API key (found in Stream → API)', 'advanced-video-player'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="bunny_library_id">
                                <?php _e('Library ID', 'advanced-video-player'); ?>
                                <span class="required">*</span>
                            </label>
                        </th>
                        <td>
                            <input type="text" 
                                   name="bunny_library_id" 
                                   id="bunny_library_id" 
                                   value="<?php echo esc_attr($settings['bunny_library_id'] ?? ''); ?>" 
                                   class="regular-text" />
                            <p class="description">
                                <?php _e('Your Video Library ID (found in Stream → Video Library)', 'advanced-video-player'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="bunny_cdn_hostname">
                                <?php _e('CDN Hostname', 'advanced-video-player'); ?>
                                <span class="required">*</span>
                            </label>
                        </th>
                        <td>
                            <input type="text" 
                                   name="bunny_cdn_hostname" 
                                   id="bunny_cdn_hostname" 
                                   value="<?php echo esc_attr($settings['bunny_cdn_hostname'] ?? ''); ?>" 
                                   class="regular-text" 
                                   placeholder="vz-xxxxx-xxx.b-cdn.net" />
                            <p class="description">
                                <?php _e('Your Video Library CDN hostname (e.g., vz-xxxxx-xxx.b-cdn.net)', 'advanced-video-player'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"></th>
                        <td>
                            <button type="button" id="avp-test-bunny-connection" class="button button-secondary">
                                <span class="dashicons dashicons-update"></span>
                                <?php _e('Test Connection', 'advanced-video-player'); ?>
                            </button>
                            <span id="avp-connection-status"></span>
                        </td>
                    </tr>
                </table>
            </div>
            
            <!-- Default Player Settings -->
            <div class="avp-settings-section">
                <h2>
                    <span class="dashicons dashicons-video-alt3"></span>
                    <?php _e('Default Player Settings', 'advanced-video-player'); ?>
                </h2>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="default_player_width"><?php _e('Default Width', 'advanced-video-player'); ?></label>
                        </th>
                        <td>
                            <input type="text" 
                                   name="default_player_width" 
                                   id="default_player_width" 
                                   value="<?php echo esc_attr($settings['default_player_width'] ?? '100%'); ?>" 
                                   class="regular-text" />
                            <p class="description">
                                <?php _e('Default player width (e.g., 100%, 800px)', 'advanced-video-player'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="default_player_height"><?php _e('Default Height', 'advanced-video-player'); ?></label>
                        </th>
                        <td>
                            <input type="text" 
                                   name="default_player_height" 
                                   id="default_player_height" 
                                   value="<?php echo esc_attr($settings['default_player_height'] ?? '500px'); ?>" 
                                   class="regular-text" />
                            <p class="description">
                                <?php _e('Default player height (e.g., 500px, 56.25%)', 'advanced-video-player'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Autoplay', 'advanced-video-player'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" 
                                       name="autoplay" 
                                       id="autoplay" 
                                       value="1" 
                                       <?php checked(!empty($settings['autoplay'])); ?> />
                                <?php _e('Start playing videos automatically', 'advanced-video-player'); ?>
                            </label>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Loop', 'advanced-video-player'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" 
                                       name="loop" 
                                       id="loop" 
                                       value="1" 
                                       <?php checked(!empty($settings['loop'])); ?> />
                                <?php _e('Loop video playback', 'advanced-video-player'); ?>
                            </label>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Muted', 'advanced-video-player'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" 
                                       name="muted" 
                                       id="muted" 
                                       value="1" 
                                       <?php checked(!empty($settings['muted'])); ?> />
                                <?php _e('Start videos muted', 'advanced-video-player'); ?>
                            </label>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Controls', 'advanced-video-player'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" 
                                       name="controls" 
                                       id="controls" 
                                       value="1" 
                                       <?php checked(!isset($settings['controls']) || $settings['controls']); ?> />
                                <?php _e('Show player controls', 'advanced-video-player'); ?>
                            </label>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="preload"><?php _e('Preload', 'advanced-video-player'); ?></label>
                        </th>
                        <td>
                            <select name="preload" id="preload">
                                <option value="none" <?php selected($settings['preload'] ?? 'metadata', 'none'); ?>>
                                    <?php _e('None', 'advanced-video-player'); ?>
                                </option>
                                <option value="metadata" <?php selected($settings['preload'] ?? 'metadata', 'metadata'); ?>>
                                    <?php _e('Metadata', 'advanced-video-player'); ?>
                                </option>
                                <option value="auto" <?php selected($settings['preload'] ?? 'metadata', 'auto'); ?>>
                                    <?php _e('Auto', 'advanced-video-player'); ?>
                                </option>
                            </select>
                            <p class="description">
                                <?php _e('How much video data to preload', 'advanced-video-player'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
            </div>
            
            <!-- Features -->
            <div class="avp-settings-section">
                <h2>
                    <span class="dashicons dashicons-admin-plugins"></span>
                    <?php _e('Features', 'advanced-video-player'); ?>
                </h2>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('Analytics', 'advanced-video-player'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" 
                                       name="enable_analytics" 
                                       id="enable_analytics" 
                                       value="1" 
                                       <?php checked(!isset($settings['enable_analytics']) || $settings['enable_analytics']); ?> />
                                <?php _e('Enable video analytics and tracking', 'advanced-video-player'); ?>
                            </label>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Advertisements', 'advanced-video-player'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" 
                                       name="enable_ads" 
                                       id="enable_ads" 
                                       value="1" 
                                       <?php checked(!empty($settings['enable_ads'])); ?> />
                                <?php _e('Enable advertisement support', 'advanced-video-player'); ?>
                            </label>
                        </td>
                    </tr>
                </table>
            </div>

            <div class="avp-settings-section">
                <h2>
                    <span class="dashicons dashicons-admin-links"></span>
                    <?php _e('Integración con control de minutos', 'advanced-video-player'); ?>
                </h2>

                <?php
                $status_badge_class = $control_minutes_status['connected'] ? 'avp-status-badge--connected' : 'avp-status-badge--disconnected';
                $status_label = $control_minutes_status['connected']
                    ? __('Conectado con Control Minutos', 'advanced-video-player')
                    : ($control_minutes_status['available']
                        ? __('Plugin detectado, pendiente de vinculación', 'advanced-video-player')
                        : __('Plugin no detectado', 'advanced-video-player'));
                ?>

                <p class="description">
                    <?php
                    printf(
                        /* translators: %s: external plugin name */
                        esc_html__(
                            'El conteo de minutos se delega en el plugin externo %s, que se instala por separado. Conecta ambos sistemas para habilitar límites, reportes y reinicios.',
                            'advanced-video-player'
                        ),
                        'Control Minutos'
                    );
                    ?>
                </p>

                <p class="avp-status-badge <?php echo esc_attr($status_badge_class); ?>">
                    <span class="dashicons <?php echo $control_minutes_status['connected'] ? 'dashicons-yes-alt' : 'dashicons-warning'; ?>"></span>
                    <?php echo esc_html($status_label); ?>
                </p>

                <?php if (!empty($control_minutes_status['details']['message'])) : ?>
                    <p class="description">
                        <?php echo esc_html($control_minutes_status['details']['message']); ?>
                    </p>
                <?php endif; ?>

                <p>
                    <a class="button button-primary" href="<?php echo esc_url(admin_url('admin.php?page=advanced-video-player-control-minutes')); ?>">
                        <?php _e('Abrir asistente de conexión', 'advanced-video-player'); ?>
                    </a>
                </p>
            </div>
        </div>
        
        <p class="submit">
            <button type="submit" name="submit" id="submit" class="button button-primary">
                <span class="dashicons dashicons-saved"></span>
                <?php _e('Save Settings', 'advanced-video-player'); ?>
            </button>
        </p>
    </form>
</div>

<style>
.avp-settings-container {
    max-width: 900px;
}

.avp-settings-section {
    background: #fff;
    border: 1px solid #ccd0d4;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
    margin: 20px 0;
    padding: 20px;
}

.avp-settings-section h2 {
    margin-top: 0;
    border-bottom: 1px solid #ccd0d4;
    padding-bottom: 10px;
    font-size: 18px;
}

.avp-settings-section h2 .dashicons {
    color: #0073aa;
}

.required {
    color: #d63638;
}

.avp-toggle-password {
    margin-left: 5px;
}

#avp-connection-status {
    margin-left: 10px;
    font-weight: 600;
}

#avp-connection-status.success {
    color: #00a32a;
}

#avp-connection-status.error {
    color: #d63638;
}

.avp-status-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 12px;
    border-radius: 999px;
    font-weight: 600;
    margin: 12px 0;
}

.avp-status-badge--connected {
    background: rgba(0, 163, 42, 0.12);
    color: #0a6d1f;
}

.avp-status-badge--disconnected {
    background: rgba(214, 54, 56, 0.12);
    color: #8a1d1f;
}
</style>

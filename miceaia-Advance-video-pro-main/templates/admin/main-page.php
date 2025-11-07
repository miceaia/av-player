<?php
/**
 * Página principal del admin - Add Video
 */

if (!defined('ABSPATH')) {
    exit;
}

$settings = get_option('avp_settings', array());
$bunny_configured = !empty($settings['bunny_api_key']) && !empty($settings['bunny_library_id']);
$default_watch_limit = isset($settings['default_watch_limit']) ? intval($settings['default_watch_limit']) : 180;
$learndash_active = post_type_exists('sfwd-courses');
?>

<div class="wrap avp-admin-wrap">
    <h1><?php _e('Add Video to Your Site', 'advanced-video-player'); ?></h1>
    
    <?php if (!$bunny_configured): ?>
        <div class="notice notice-warning">
            <p>
                <strong><?php _e('Bunny.net not configured!', 'advanced-video-player'); ?></strong><br>
                <?php _e('Please configure your Bunny.net API credentials in', 'advanced-video-player'); ?>
                <a href="<?php echo admin_url('admin.php?page=advanced-video-player-settings'); ?>">
                    <?php _e('Settings', 'advanced-video-player'); ?>
                </a>
            </p>
        </div>
    <?php endif; ?>
    
    <div class="avp-admin-container">
        <!-- Tabs -->
        <div class="avp-tabs">
            <button class="avp-tab-button active" data-tab="bunny">
                <span class="dashicons dashicons-video-alt3"></span>
                <?php _e('Bunny.net Library', 'advanced-video-player'); ?>
            </button>
            <button class="avp-tab-button" data-tab="youtube">
                <span class="dashicons dashicons-youtube"></span>
                <?php _e('YouTube', 'advanced-video-player'); ?>
            </button>
            <button class="avp-tab-button" data-tab="vimeo">
                <span class="dashicons dashicons-video-alt"></span>
                <?php _e('Vimeo', 'advanced-video-player'); ?>
            </button>
            <button class="avp-tab-button" data-tab="custom">
                <span class="dashicons dashicons-media-video"></span>
                <?php _e('Custom URL', 'advanced-video-player'); ?>
            </button>
            <button class="avp-tab-button" data-tab="watch-limits">
                <span class="dashicons dashicons-clock"></span>
                <?php _e('Playback Time Limit', 'advanced-video-player'); ?>
            </button>
        </div>
        
        <!-- Bunny.net Tab -->
        <div class="avp-tab-content active" id="bunny-tab">
            <div class="avp-bunny-header">
                <div class="avp-search-box">
                    <input type="text" id="avp-bunny-search" placeholder="<?php _e('Search videos...', 'advanced-video-player'); ?>" />
                    <button id="avp-bunny-search-btn" class="button">
                        <span class="dashicons dashicons-search"></span>
                        <?php _e('Search', 'advanced-video-player'); ?>
                    </button>
                </div>
                
                <div class="avp-filter-box">
                    <label><?php _e('Collection:', 'advanced-video-player'); ?></label>
                    <select id="avp-bunny-collection">
                        <option value=""><?php _e('All Collections', 'advanced-video-player'); ?></option>
                    </select>
                    <button id="avp-bunny-refresh" class="button">
                        <span class="dashicons dashicons-update"></span>
                        <?php _e('Refresh', 'advanced-video-player'); ?>
                    </button>
                </div>
            </div>
            
            <div id="avp-bunny-loading" class="avp-loading" style="display: none;">
                <div class="spinner is-active"></div>
                <p><?php _e('Loading videos from Bunny.net...', 'advanced-video-player'); ?></p>
            </div>
            
            <div id="avp-bunny-videos" class="avp-video-grid">
                <!-- Los videos se cargarán aquí dinámicamente -->
            </div>
            
            <div id="avp-bunny-pagination" class="avp-pagination" style="display: none;">
                <button id="avp-prev-page" class="button">
                    <span class="dashicons dashicons-arrow-left-alt2"></span>
                    <?php _e('Previous', 'advanced-video-player'); ?>
                </button>
                <span id="avp-page-info"></span>
                <button id="avp-next-page" class="button">
                    <?php _e('Next', 'advanced-video-player'); ?>
                    <span class="dashicons dashicons-arrow-right-alt2"></span>
                </button>
            </div>
        </div>
        
        <!-- YouTube Tab -->
        <div class="avp-tab-content" id="youtube-tab">
            <div class="avp-url-input-container">
                <h3><?php _e('Add YouTube Video', 'advanced-video-player'); ?></h3>
                <p class="description">
                    <?php _e('Enter a YouTube video URL (e.g., https://www.youtube.com/watch?v=VIDEO_ID)', 'advanced-video-player'); ?>
                </p>
                <div class="avp-url-input-box">
                    <input type="url" id="avp-youtube-url" class="large-text" 
                           placeholder="https://www.youtube.com/watch?v=..." />
                    <button id="avp-youtube-preview" class="button button-primary">
                        <span class="dashicons dashicons-visibility"></span>
                        <?php _e('Preview', 'advanced-video-player'); ?>
                    </button>
                </div>
                <div id="avp-youtube-preview-container" class="avp-preview-container"></div>
            </div>
        </div>
        
        <!-- Vimeo Tab -->
        <div class="avp-tab-content" id="vimeo-tab">
            <div class="avp-url-input-container">
                <h3><?php _e('Add Vimeo Video', 'advanced-video-player'); ?></h3>
                <p class="description">
                    <?php _e('Enter a Vimeo video URL (e.g., https://vimeo.com/VIDEO_ID)', 'advanced-video-player'); ?>
                </p>
                <div class="avp-url-input-box">
                    <input type="url" id="avp-vimeo-url" class="large-text" 
                           placeholder="https://vimeo.com/..." />
                    <button id="avp-vimeo-preview" class="button button-primary">
                        <span class="dashicons dashicons-visibility"></span>
                        <?php _e('Preview', 'advanced-video-player'); ?>
                    </button>
                </div>
                <div id="avp-vimeo-preview-container" class="avp-preview-container"></div>
            </div>
        </div>
        
        <!-- Custom URL Tab -->
        <div class="avp-tab-content" id="custom-tab">
            <div class="avp-url-input-container">
                <h3><?php _e('Add Custom Video URL', 'advanced-video-player'); ?></h3>
                <p class="description">
                    <?php _e('Enter a direct video URL (MP4, WebM, HLS .m3u8, DASH .mpd)', 'advanced-video-player'); ?>
                </p>
                <div class="avp-url-input-box">
                    <input type="url" id="avp-custom-url" class="large-text"
                           placeholder="https://example.com/video.mp4" />
                    <select id="avp-custom-type">
                        <option value="auto"><?php _e('Auto-detect', 'advanced-video-player'); ?></option>
                        <option value="mp4">MP4</option>
                        <option value="webm">WebM</option>
                        <option value="hls">HLS (.m3u8)</option>
                        <option value="dash">DASH (.mpd)</option>
                    </select>
                    <button id="avp-custom-preview" class="button button-primary">
                        <span class="dashicons dashicons-visibility"></span>
                        <?php _e('Preview', 'advanced-video-player'); ?>
                    </button>
                </div>
                <div id="avp-custom-preview-container" class="avp-preview-container"></div>
            </div>
        </div>

        <!-- Placeholder Tab removed -->
    </div>
    
    <!-- Modal para insertar video -->
    <div id="avp-insert-modal" class="avp-modal" style="display: none;">
        <div class="avp-modal-content">
            <div class="avp-modal-header">
                <h2><?php _e('Insert Video', 'advanced-video-player'); ?></h2>
                <button class="avp-modal-close">
                    <span class="dashicons dashicons-no-alt"></span>
                </button>
            </div>
            
            <div class="avp-modal-body">
                <div class="avp-video-preview-large">
                    <video id="avp-modal-preview-video" class="avp-modal-preview-video" controls playsinline style="display:none;"></video>
                    <img id="avp-modal-thumbnail" src="" alt="" />
                    <div class="avp-video-info">
                        <h3 id="avp-modal-title"></h3>
                        <p id="avp-modal-description"></p>
                        <p class="avp-video-meta">
                            <span id="avp-modal-duration"></span> •
                            <span id="avp-modal-views"></span>
                        </p>
                    </div>
                </div>
                
                <div class="avp-insert-options">
                    <h3><?php _e('Player Options', 'advanced-video-player'); ?></h3>
                    
                    <table class="form-table">
                        <tr>
                            <th><?php _e('Width', 'advanced-video-player'); ?></th>
                            <td>
                                <input type="text" id="avp-insert-width" value="100%" class="regular-text" />
                            </td>
                        </tr>
                        <tr>
                            <th><?php _e('Height', 'advanced-video-player'); ?></th>
                            <td>
                                <input type="text" id="avp-insert-height" value="500px" class="regular-text" />
                            </td>
                        </tr>
                        <tr>
                            <th><?php _e('Autoplay', 'advanced-video-player'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" id="avp-insert-autoplay" />
                                    <?php _e('Start playing automatically', 'advanced-video-player'); ?>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th><?php _e('Loop', 'advanced-video-player'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" id="avp-insert-loop" />
                                    <?php _e('Loop video playback', 'advanced-video-player'); ?>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th><?php _e('Muted', 'advanced-video-player'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" id="avp-insert-muted" />
                                    <?php _e('Start muted', 'advanced-video-player'); ?>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th><?php _e('Controls', 'advanced-video-player'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" id="avp-insert-controls" checked />
                                    <?php _e('Show player controls', 'advanced-video-player'); ?>
                                </label>
                            </td>
                        </tr>
                    </table>
                    
                    <div class="avp-advanced-options">
                        <button type="button" class="button avp-toggle-advanced">
                            <span class="dashicons dashicons-arrow-down-alt2"></span>
                            <?php _e('Advanced Options', 'advanced-video-player'); ?>
                        </button>
                        
                        <div class="avp-advanced-options-content" style="display: none;">
                            <table class="form-table">
                                <tr>
                                    <th><?php _e('AB Loop', 'advanced-video-player'); ?></th>
                                    <td>
                                        <label>
                                            <input type="checkbox" id="avp-insert-abloop" />
                                            <?php _e('Enable AB loop controls', 'advanced-video-player'); ?>
                                        </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php _e('Poster Image', 'advanced-video-player'); ?></th>
                                    <td>
                                        <input type="text" id="avp-insert-poster" class="regular-text" 
                                               placeholder="<?php _e('Optional thumbnail URL', 'advanced-video-player'); ?>" />
                                        <button type="button" class="button avp-select-poster">
                                            <?php _e('Select Image', 'advanced-video-player'); ?>
                                        </button>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
                
                <div class="avp-shortcode-preview">
                    <h3><?php _e('Shortcode', 'advanced-video-player'); ?></h3>
                    <div class="avp-shortcode-box">
                        <code id="avp-generated-shortcode">[avp_player src=""]</code>
                        <button type="button" class="button avp-copy-shortcode" title="<?php _e('Copy shortcode', 'advanced-video-player'); ?>">
                            <span class="dashicons dashicons-clipboard"></span>
                        </button>
                    </div>
                    <p class="description">
                        <?php _e('Copy this shortcode and paste it into any post or page.', 'advanced-video-player'); ?>
                    </p>
                </div>
            </div>
            
            <div class="avp-modal-footer">
                <button type="button" class="button button-secondary avp-modal-cancel">
                    <?php _e('Cancel', 'advanced-video-player'); ?>
                </button>
                <button type="button" class="button button-primary avp-insert-shortcode">
                    <span class="dashicons dashicons-plus"></span>
                    <?php _e('Insert into Post', 'advanced-video-player'); ?>
                </button>
            </div>
        </div>
    </div>
</div>

<style>
.avp-admin-wrap {
    margin: 20px 20px 20px 0;
}

.avp-admin-container {
    background: #fff;
    border: 1px solid #ccd0d4;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
    margin-top: 20px;
}

.avp-tabs {
    display: flex;
    border-bottom: 1px solid #ccd0d4;
    background: #f6f7f7;
}

.avp-tab-button {
    padding: 15px 25px;
    border: none;
    background: none;
    cursor: pointer;
    font-size: 14px;
    font-weight: 500;
    color: #50575e;
    border-bottom: 3px solid transparent;
    transition: all 0.2s;
}

.avp-tab-button:hover {
    background: #fff;
    color: #0073aa;
}

.avp-tab-button.active {
    background: #fff;
    color: #0073aa;
    border-bottom-color: #0073aa;
}

.avp-tab-button .dashicons {
    margin-right: 5px;
}

.avp-tab-content {
    display: none;
    padding: 20px;
}

.avp-tab-content.active {
    display: block;
}

.avp-bunny-header {
    display: flex;
    gap: 20px;
    margin-bottom: 20px;
    flex-wrap: wrap;
}

.avp-search-box {
    flex: 1;
    min-width: 300px;
    display: flex;
    gap: 10px;
}

.avp-search-box input {
    flex: 1;
    padding: 8px 12px;
    border: 1px solid #8c8f94;
    border-radius: 4px;
}

.avp-filter-box {
    display: flex;
    gap: 10px;
    align-items: center;
}

.avp-filter-box select {
    min-width: 200px;
}

.avp-loading {
    text-align: center;
    padding: 60px 20px;
    color: #666;
}

.avp-video-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 20px;
    margin-bottom: 20px;
}

.avp-bunny-placeholder {
    grid-column: 1 / -1;
    padding: 48px 20px;
    text-align: center;
    color: #475569;
    border: 1px dashed #cbd5e1;
    border-radius: 6px;
    background: #f8fafc;
    font-size: 14px;
    line-height: 1.6;
}

.avp-bunny-placeholder--error {
    color: #b91c1c;
    border-color: #f8b4b4;
    background: #fef2f2;
}

.avp-bunny-placeholder--info {
    color: #1d4ed8;
    border-color: #bfdbfe;
    background: #eff6ff;
}

.avp-video-card {
    border: 1px solid #dcdcde;
    border-radius: 4px;
    overflow: hidden;
    transition: all 0.2s;
    cursor: pointer;
    background: #fff;
}

.avp-video-card:hover {
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    border-color: #0073aa;
}

.avp-video-thumbnail {
    width: 100%;
    height: 160px;
    object-fit: cover;
    background: #f0f0f1;
}

.avp-video-info-card {
    padding: 12px;
}

.avp-video-title {
    font-weight: 600;
    margin: 0 0 8px 0;
    font-size: 14px;
    line-height: 1.4;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.avp-video-meta {
    font-size: 12px;
    color: #646970;
    margin: 0;
}

.avp-video-duration {
    position: absolute;
    bottom: 8px;
    right: 8px;
    background: rgba(0,0,0,0.8);
    color: #fff;
    padding: 2px 6px;
    border-radius: 3px;
    font-size: 11px;
    font-weight: 600;
}

.avp-pagination {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px 0;
    border-top: 1px solid #dcdcde;
}

.avp-url-input-container {
    max-width: 800px;
}

.avp-url-input-box {
    display: flex;
    gap: 10px;
    margin: 20px 0;
}

.avp-url-input-box input {
    flex: 1;
}

.avp-preview-container {
    margin-top: 30px;
    padding: 20px;
    border: 1px solid #dcdcde;
    border-radius: 4px;
    background: #f6f7f7;
    display: none;
}

.avp-preview-container.active {
    display: block;
}

/* Modal Styles */
.avp-modal {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0,0,0,0.7);
    z-index: 100000;
    display: flex;
    align-items: center;
    justify-content: center;
}

.avp-modal-content {
    background: #fff;
    width: 90%;
    max-width: 800px;
    max-height: 90vh;
    overflow-y: auto;
    border-radius: 4px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.3);
}

.avp-modal-header {
    padding: 20px;
    border-bottom: 1px solid #dcdcde;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.avp-modal-header h2 {
    margin: 0;
    font-size: 20px;
}

.avp-modal-close {
    background: none;
    border: none;
    cursor: pointer;
    padding: 5px;
    color: #646970;
}

.avp-modal-close:hover {
    color: #d63638;
}

.avp-modal-body {
    padding: 20px;
}

.avp-video-preview-large {
    display: flex;
    gap: 20px;
    margin-bottom: 30px;
    padding-bottom: 20px;
    border-bottom: 1px solid #dcdcde;
}

.avp-video-preview-large img {
    width: 200px;
    height: 120px;
    object-fit: cover;
    border-radius: 4px;
}

.avp-video-info {
    flex: 1;
}

.avp-video-info h3 {
    margin: 0 0 10px 0;
    font-size: 16px;
}

.avp-shortcode-preview {
    margin-top: 30px;
    padding-top: 20px;
    border-top: 1px solid #dcdcde;
}

.avp-shortcode-box {
    display: flex;
    gap: 10px;
    align-items: center;
    padding: 15px;
    background: #f6f7f7;
    border: 1px solid #dcdcde;
    border-radius: 4px;
    margin: 10px 0;
}

.avp-shortcode-box code {
    flex: 1;
    font-size: 13px;
    word-break: break-all;
}

.avp-modal-footer {
    padding: 20px;
    border-top: 1px solid #dcdcde;
    display: flex;
    justify-content: flex-end;
    gap: 10px;
}

.avp-advanced-options-content {
    margin-top: 15px;
    padding-top: 15px;
    border-top: 1px solid #dcdcde;
}
</style>

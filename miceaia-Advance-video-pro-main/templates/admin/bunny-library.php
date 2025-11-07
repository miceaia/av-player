<?php
if (!defined('ABSPATH')) {
    exit;
}

$settings = get_option('avp_settings', array());
$bunny_configured = !empty($settings['bunny_api_key']) && !empty($settings['bunny_library_id']);
?>

<div class="wrap avp-admin-wrap avp-bunny-library-page">
    <h1><?php esc_html_e('Bunny.net Library', 'advanced-video-player'); ?></h1>

    <?php if (!$bunny_configured) : ?>
        <div class="notice notice-warning">
            <p>
                <strong><?php esc_html_e('Bunny.net not configured!', 'advanced-video-player'); ?></strong><br />
                <?php esc_html_e('Please configure your Bunny.net API credentials in', 'advanced-video-player'); ?>
                <a href="<?php echo esc_url(admin_url('admin.php?page=advanced-video-player-settings')); ?>">
                    <?php esc_html_e('Settings', 'advanced-video-player'); ?>
                </a>
            </p>
        </div>
    <?php endif; ?>

    <div class="avp-bunny-library">
        <div class="avp-bunny-header">
            <div class="avp-search-box">
                <input type="text" id="avp-bunny-search" placeholder="<?php esc_attr_e('Search videos...', 'advanced-video-player'); ?>" />
                <button id="avp-bunny-search-btn" class="button">
                    <span class="dashicons dashicons-search"></span>
                    <?php esc_html_e('Search', 'advanced-video-player'); ?>
                </button>
            </div>

            <div class="avp-filter-box">
                <label for="avp-bunny-collection"><?php esc_html_e('Collection:', 'advanced-video-player'); ?></label>
                <select id="avp-bunny-collection">
                    <option value=""><?php esc_html_e('All Collections', 'advanced-video-player'); ?></option>
                </select>
                <button id="avp-bunny-refresh" class="button">
                    <span class="dashicons dashicons-update"></span>
                    <?php esc_html_e('Refresh', 'advanced-video-player'); ?>
                </button>
            </div>
        </div>

        <div id="avp-bunny-loading" class="avp-loading" style="display: none;">
            <div class="spinner is-active"></div>
            <p><?php esc_html_e('Loading videos from Bunny.net...', 'advanced-video-player'); ?></p>
        </div>

        <div id="avp-bunny-videos" class="avp-video-grid"></div>

        <div id="avp-bunny-pagination" class="avp-pagination" style="display: none;">
            <button id="avp-prev-page" class="button">
                <span class="dashicons dashicons-arrow-left-alt2"></span>
                <?php esc_html_e('Previous', 'advanced-video-player'); ?>
            </button>
            <span id="avp-page-info"></span>
            <button id="avp-next-page" class="button">
                <?php esc_html_e('Next', 'advanced-video-player'); ?>
                <span class="dashicons dashicons-arrow-right-alt2"></span>
            </button>
        </div>
    </div>
</div>

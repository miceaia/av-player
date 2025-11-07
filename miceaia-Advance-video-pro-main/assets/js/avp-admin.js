/**
 * Advanced Video Player - Admin JavaScript
 */

(function($) {
    'use strict';
    
    const AVPAdmin = {
        currentPage: 1,
        totalPages: 1,
        currentCollection: '',
        selectedVideo: null,
        previewHls: null,
        init: function() {
            this.bindEvents();
            if ($('#avp-bunny-videos').length) {
                if (this.ensureBunnySetup()) {
                    this.loadBunnyVideos();
                    this.loadBunnyCollections();
                }
            }
        },

        bindEvents: function() {
            // Tab switching
            $('.avp-tab-button').on('click', this.switchTab.bind(this));
            
            // Bunny.net actions
            $('#avp-bunny-search-btn').on('click', this.searchBunnyVideos.bind(this));
            $('#avp-bunny-search').on('keypress', function(e) {
                if (e.which === 13) {
                    AVPAdmin.searchBunnyVideos();
                }
            });
            $('#avp-bunny-collection').on('change', this.filterByCollection.bind(this));
            $('#avp-bunny-refresh').on('click', this.loadBunnyVideos.bind(this));
            
            // Pagination
            $('#avp-prev-page').on('click', this.prevPage.bind(this));
            $('#avp-next-page').on('click', this.nextPage.bind(this));
            
            // Video preview buttons
            $('#avp-youtube-preview').on('click', this.previewYouTube.bind(this));
            $('#avp-vimeo-preview').on('click', this.previewVimeo.bind(this));
            $('#avp-custom-preview').on('click', this.previewCustom.bind(this));

            // Modal actions
            $('.avp-modal-close, .avp-modal-cancel').on('click', this.closeModal.bind(this));
            $('.avp-insert-shortcode').on('click', this.insertShortcode.bind(this));
            $('.avp-copy-shortcode').on('click', this.copyShortcode.bind(this));
            $('.avp-toggle-advanced').on('click', this.toggleAdvanced.bind(this));
            $('.avp-select-poster').on('click', this.selectPoster.bind(this));

            // Settings helpers
            $('.avp-toggle-password').on('click', this.togglePasswordVisibility.bind(this));
            $('#avp-test-bunny-connection').on('click', this.testBunnyConnection.bind(this));

            // Update shortcode on option change
            $('#avp-insert-modal input, #avp-insert-modal select').on('change', this.updateShortcode.bind(this));

            // Close modal on outside click
            $(document).on('click', '.avp-modal', function(e) {
                if ($(e.target).hasClass('avp-modal')) {
                    AVPAdmin.closeModal();
                }
            });
        },
        
        switchTab: function(e) {
            const tab = $(e.currentTarget).data('tab');

            $('.avp-tab-button').removeClass('active');
            $(e.currentTarget).addClass('active');

            $('.avp-tab-content').removeClass('active');
            $(`#${tab}-tab`).addClass('active');
        },

        getString: function(key, fallback) {
            if (typeof avpAdmin !== 'undefined' && avpAdmin.strings && avpAdmin.strings[key]) {
                return avpAdmin.strings[key];
            }
            return fallback || '';
        },

        getBunnySettings: function() {
            if (typeof avpAdmin === 'undefined' || !avpAdmin.settings) {
                return {};
            }
            return avpAdmin.settings;
        },

        hasBunnyCredentials: function(requireCdn) {
            const settings = this.getBunnySettings();
            const apiKey = settings.bunny_api_key ? String(settings.bunny_api_key).trim() : '';
            const libraryId = settings.bunny_library_id ? String(settings.bunny_library_id).trim() : '';
            const cdnHostname = settings.bunny_cdn_hostname ? String(settings.bunny_cdn_hostname).trim() : '';
            if (requireCdn) {
                return apiKey !== '' && libraryId !== '' && cdnHostname !== '';
            }

            return apiKey !== '' && libraryId !== '';
        },

        ensureBunnySetup: function() {
            if (this.hasBunnyCredentials(false)) {
                return true;
            }

            const message = this.getString('bunnyMissingCredentials', 'Configure Bunny.net credentials in Settings to view your library.');
            this.showBunnyPlaceholder(message, 'info');
            return false;
        },

        showBunnyPlaceholder: function(message, state) {
            const $container = $('#avp-bunny-videos');
            if (!$container.length) {
                return;
            }

            let classes = 'avp-bunny-placeholder';
            if (state === 'error') {
                classes += ' avp-bunny-placeholder--error';
            } else if (state === 'info') {
                classes += ' avp-bunny-placeholder--info';
            }

            $container.html(`<div class="${classes}">${message || ''}</div>`);
            $('#avp-bunny-loading').hide();
            $('#avp-bunny-pagination').hide();
        },

        handleBunnyError: function(stringKey, fallback) {
            const message = this.getString(stringKey, fallback);
            this.totalPages = 1;
            this.showBunnyPlaceholder(message || fallback || '', 'error');
        },

        loadBunnyVideos: function() {
            if (!this.hasBunnyCredentials(false)) {
                return;
            }

            $('#avp-bunny-loading').show();
            $('#avp-bunny-videos').empty();
            $('#avp-bunny-pagination').hide();

            $.ajax({
                url: avpAdmin.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'avp_get_bunny_videos',
                    nonce: avpAdmin.nonce,
                    page: this.currentPage,
                    items_per_page: 50,
                    collection_id: this.currentCollection
                },
                success: (response) => {
                    $('#avp-bunny-loading').hide();
                    
                    if (response.success && response.data) {
                        this.renderBunnyVideos(response.data);
                    } else {
                        this.handleBunnyError('bunnyLoadError', 'Failed to load videos');
                    }
                },
                error: () => {
                    $('#avp-bunny-loading').hide();
                    this.handleBunnyError('bunnyNetworkError', 'Network error');
                }
            });
        },

        loadBunnyCollections: function() {
            if (!this.hasBunnyCredentials(false)) {
                return;
            }

            $.ajax({
                url: avpAdmin.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'avp_get_bunny_collections',
                    nonce: avpAdmin.nonce
                },
                success: (response) => {
                    if (response.success && response.data && response.data.items) {
                        const $select = $('#avp-bunny-collection');
                        $select.find('option:not(:first)').remove();
                        
                        response.data.items.forEach(collection => {
                            $select.append(
                                $('<option>', {
                                    value: collection.guid,
                                    text: collection.name
                                })
                            );
                        });
                    }
                }
            });
        },
        
        renderBunnyVideos: function(data) {
            const $container = $('#avp-bunny-videos');
            if (!$container.length) {
                return;
            }

            $container.empty();

            if (!data || !Array.isArray(data.items) || data.items.length === 0) {
                this.totalPages = 1;
                this.showBunnyPlaceholder(this.getString('bunnyNoVideos', 'No videos found.'), 'info');
                return;
            }

            const settings = this.getBunnySettings();
            const cdnHostname = settings.bunny_cdn_hostname ? String(settings.bunny_cdn_hostname).trim().replace(/\/+$/, '') : '';
            const pageSize = 50;
            const fallbackThumbnail = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='280' height='160'%3E%3Crect fill='%23f0f0f1' width='280' height='160'/%3E%3Ctext fill='%23646970' font-family='Arial' font-size='14' x='50%25' y='50%25' text-anchor='middle' dy='.3em'%3ENo Preview%3C/text%3E%3C/svg%3E";
            const fallbackThumbnailEscaped = fallbackThumbnail.replace(/'/g, "\\'");

            data.items.forEach(video => {
                const duration = this.formatDuration(video.length);
                let thumbnailUrl = '';

                if (video.thumbnailFileName && /^https?:\/\//i.test(video.thumbnailFileName)) {
                    thumbnailUrl = video.thumbnailFileName;
                } else if (cdnHostname) {
                    thumbnailUrl = `https://${cdnHostname}/${video.guid}/thumbnail.jpg`;
                }

                const safeThumbnail = thumbnailUrl || fallbackThumbnail;

                const $card = $(`
                    <div class="avp-video-card" data-video-id="${video.guid}">
                        <div style="position: relative;">
                            <img src="${safeThumbnail}"
                                 alt="${video.title || 'Untitled'}"
                                 class="avp-video-thumbnail"
                                 onerror="this.src='${fallbackThumbnailEscaped}'" />
                            ${duration ? `<span class="avp-video-duration">${duration}</span>` : ''}
                        </div>
                        <div class="avp-video-info-card">
                            <h4 class="avp-video-title">${video.title || 'Untitled'}</h4>
                            <p class="avp-video-meta">
                                ${video.views || 0} views •
                                ${this.timeAgo(video.dateUploaded)}
                            </p>
                        </div>
                    </div>
                `);

                $card.on('click', () => this.selectBunnyVideo(video));
                $container.append($card);
            });

            this.totalPages = Math.max(1, Math.ceil((data.totalItems || data.items.length) / pageSize));
            this.updatePagination();
        },
        
        selectBunnyVideo: function(video) {
            const settings = this.getBunnySettings();
            const cdnHostname = settings.bunny_cdn_hostname ? String(settings.bunny_cdn_hostname).trim().replace(/\/+$/, '') : '';

            if (!this.hasBunnyCredentials(true)) {
                this.handleBunnyError('bunnyMissingCredentials', 'Configure Bunny.net CDN hostname in Settings to insert videos.');
                return;
            }

            let thumbnail = '';
            if (video.thumbnailFileName && /^https?:\/\//i.test(video.thumbnailFileName)) {
                thumbnail = video.thumbnailFileName;
            } else {
                thumbnail = `https://${cdnHostname}/${video.guid}/thumbnail.jpg`;
            }

            this.selectedVideo = {
                id: video.guid,
                title: video.title || 'Untitled',
                thumbnail: thumbnail,
                duration: this.formatDuration(video.length),
                views: video.views || 0,
                url: `https://${cdnHostname}/${video.guid}/playlist.m3u8`,
                type: 'hls',
                source: 'bunny'
            };

            this.openInsertModal();
        },
        
        searchBunnyVideos: function() {
            if (!this.hasBunnyCredentials(false)) {
                this.ensureBunnySetup();
                return;
            }

            const searchTerm = $('#avp-bunny-search').val().trim();

            if (!searchTerm) {
                this.loadBunnyVideos();
                return;
            }

            $('#avp-bunny-loading').show();
            $('#avp-bunny-videos').empty();
            $('#avp-bunny-pagination').hide();

            $.ajax({
                url: avpAdmin.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'avp_search_bunny_videos',
                    nonce: avpAdmin.nonce,
                    search: searchTerm
                },
                success: (response) => {
                    $('#avp-bunny-loading').hide();
                    
                    if (response.success && response.data) {
                        this.renderBunnyVideos(response.data);
                    } else {
                        this.handleBunnyError('bunnySearchError', 'Search failed');
                    }
                },
                error: () => {
                    $('#avp-bunny-loading').hide();
                    this.handleBunnyError('bunnyNetworkError', 'Network error');
                }
            });
        },
        
        filterByCollection: function() {
            this.currentCollection = $('#avp-bunny-collection').val();
            this.currentPage = 1;
            this.loadBunnyVideos();
        },
        
        prevPage: function() {
            if (this.currentPage > 1) {
                this.currentPage--;
                this.loadBunnyVideos();
            }
        },
        
        nextPage: function() {
            if (this.currentPage < this.totalPages) {
                this.currentPage++;
                this.loadBunnyVideos();
            }
        },
        
        updatePagination: function() {
            const $pagination = $('#avp-bunny-pagination');

            if (this.totalPages <= 1) {
                $pagination.hide();
                return;
            }

            $pagination.show();
            const template = this.getString('bunnyPageInfo', 'Page %1$s of %2$s');
            const label = template.replace('%1$s', this.currentPage).replace('%2$s', this.totalPages);
            $('#avp-page-info').text(label);

            $('#avp-prev-page').prop('disabled', this.currentPage === 1);
            $('#avp-next-page').prop('disabled', this.currentPage === this.totalPages);
        },
        
        previewYouTube: function() {
            const url = $('#avp-youtube-url').val().trim();
            
            if (!url) {
                alert('Please enter a YouTube URL');
                return;
            }
            
            const videoId = this.extractYouTubeId(url);
            
            if (!videoId) {
                alert('Invalid YouTube URL');
                return;
            }
            
            this.selectedVideo = {
                id: videoId,
                title: 'YouTube Video',
                thumbnail: `https://img.youtube.com/vi/${videoId}/maxresdefault.jpg`,
                url: url,
                type: 'youtube',
                source: 'youtube'
            };
            
            const $preview = $('#avp-youtube-preview-container');
            $preview.html(`
                <iframe width="100%" height="400" 
                        src="https://www.youtube.com/embed/${videoId}" 
                        frameborder="0" allowfullscreen></iframe>
            `).addClass('active');
            
            this.openInsertModal();
        },
        
        previewVimeo: function() {
            const url = $('#avp-vimeo-url').val().trim();
            
            if (!url) {
                alert('Please enter a Vimeo URL');
                return;
            }
            
            const videoId = this.extractVimeoId(url);
            
            if (!videoId) {
                alert('Invalid Vimeo URL');
                return;
            }
            
            this.selectedVideo = {
                id: videoId,
                title: 'Vimeo Video',
                thumbnail: '',
                url: url,
                type: 'vimeo',
                source: 'vimeo'
            };
            
            const $preview = $('#avp-vimeo-preview-container');
            $preview.html(`
                <iframe src="https://player.vimeo.com/video/${videoId}" 
                        width="100%" height="400" frameborder="0" allowfullscreen></iframe>
            `).addClass('active');
            
            this.openInsertModal();
        },
        
        previewCustom: function() {
            const url = $('#avp-custom-url').val().trim();
            const type = $('#avp-custom-type').val();
            
            if (!url) {
                alert('Please enter a video URL');
                return;
            }
            
            this.selectedVideo = {
                id: 'custom',
                title: 'Custom Video',
                thumbnail: '',
                url: url,
                type: type === 'auto' ? this.detectVideoType(url) : type,
                source: 'custom'
            };
            
            this.openInsertModal();
        },
        
        openInsertModal: function() {
            if (!this.selectedVideo) return;

            this.preparePreviewMedia();

            // Populate modal
            $('#avp-modal-thumbnail').attr('alt', this.selectedVideo.title || '');
            $('#avp-modal-title').text(this.selectedVideo.title);
            $('#avp-modal-duration').text(this.selectedVideo.duration || '');
            $('#avp-modal-views').text(this.selectedVideo.views ? `${this.selectedVideo.views} views` : '');

            // Set default options
            $('#avp-insert-width').val(avpAdmin.settings.default_player_width || '100%');
            $('#avp-insert-height').val(avpAdmin.settings.default_player_height || '500px');
            $('#avp-insert-autoplay').prop('checked', avpAdmin.settings.autoplay || false);
            $('#avp-insert-loop').prop('checked', avpAdmin.settings.loop || false);
            $('#avp-insert-muted').prop('checked', avpAdmin.settings.muted || false);
            $('#avp-insert-controls').prop('checked', avpAdmin.settings.controls !== false);
            
            this.updateShortcode();
            $('#avp-insert-modal').fadeIn(200);
        },

        closeModal: function() {
            this.destroyPreviewPlayer();
            $('#avp-modal-preview-video').hide();
            $('#avp-modal-thumbnail').show();
            $('#avp-insert-modal').fadeOut(200);
        },

        preparePreviewMedia: function() {
            const $thumbnail = $('#avp-modal-thumbnail');
            const $video = $('#avp-modal-preview-video');
            const videoEl = $video.get(0);

            if (!this.selectedVideo) {
                $thumbnail.hide();
                if (videoEl) {
                    $video.hide();
                }
                return;
            }

            this.destroyPreviewPlayer();

            if (this.selectedVideo.source === 'bunny' && videoEl) {
                if (this.selectedVideo.thumbnail) {
                    videoEl.poster = this.selectedVideo.thumbnail;
                } else {
                    videoEl.removeAttribute('poster');
                }

                $thumbnail.hide();
                $video.show();
                videoEl.controls = true;
                videoEl.muted = false;

                this.setupBunnyPreview(videoEl, this.selectedVideo.url);
            } else {
                if (this.selectedVideo.thumbnail) {
                    $thumbnail.attr('src', this.selectedVideo.thumbnail).show();
                } else {
                    $thumbnail.removeAttr('src').hide();
                }

                if (videoEl) {
                    $video.hide();
                    videoEl.removeAttribute('poster');
                }
            }
        },

        setupBunnyPreview: function(videoEl, url) {
            if (!videoEl || !url) {
                return;
            }

            if (videoEl.canPlayType('application/vnd.apple.mpegurl')) {
                videoEl.src = url;
                videoEl.load();
                return;
            }

            if (window.Hls) {
                if (this.previewHls) {
                    this.previewHls.destroy();
                }

                this.previewHls = new Hls();
                this.previewHls.loadSource(url);
                this.previewHls.attachMedia(videoEl);
                return;
            }

            if (this.selectedVideo && this.selectedVideo.thumbnail) {
                $('#avp-modal-thumbnail').attr('src', this.selectedVideo.thumbnail).show();
            }

            $(videoEl).hide();
            this.showError('HLS preview is not supported in this browser.');
        },

        destroyPreviewPlayer: function() {
            if (this.previewHls) {
                try {
                    this.previewHls.destroy();
                } catch (error) {
                    console.warn('Error destroying HLS preview instance', error);
                }
                this.previewHls = null;
            }

            const videoEl = document.getElementById('avp-modal-preview-video');
            if (videoEl) {
                try {
                    videoEl.pause();
                } catch (error) {
                    console.warn('Error pausing preview video', error);
                }
                videoEl.removeAttribute('src');
                videoEl.load();
                videoEl.removeAttribute('poster');
            }
        },
        
        updateShortcode: function() {
            if (!this.selectedVideo) return;
            
            const attrs = {
                src: this.selectedVideo.url,
                type: this.selectedVideo.type,
                width: $('#avp-insert-width').val(),
                height: $('#avp-insert-height').val(),
                autoplay: $('#avp-insert-autoplay').is(':checked') ? 'true' : 'false',
                loop: $('#avp-insert-loop').is(':checked') ? 'true' : 'false',
                muted: $('#avp-insert-muted').is(':checked') ? 'true' : 'false',
                controls: $('#avp-insert-controls').is(':checked') ? 'true' : 'false'
            };
            
            if ($('#avp-insert-abloop').is(':checked')) {
                attrs.ab_loop = 'true';
            }
            
            const poster = $('#avp-insert-poster').val();
            if (poster) {
                attrs.poster = poster;
            } else if (this.selectedVideo.thumbnail) {
                attrs.poster = this.selectedVideo.thumbnail;
            }
            
            let shortcode = '[avp_player';
            
            for (const [key, value] of Object.entries(attrs)) {
                shortcode += ` ${key}="${value}"`;
            }
            
            shortcode += ']';
            
            $('#avp-generated-shortcode').text(shortcode);
        },
        
        insertShortcode: function() {
            const shortcode = $('#avp-generated-shortcode').text();
            
            // Try to insert into the editor
            if (typeof wp !== 'undefined' && wp.media && wp.media.editor) {
                wp.media.editor.insert(shortcode);
            } else {
                // Copy to clipboard
                this.copyToClipboard(shortcode);
                alert('Shortcode copied to clipboard! Paste it into your post or page.');
            }
            
            this.closeModal();
        },
        
        copyShortcode: function() {
            const shortcode = $('#avp-generated-shortcode').text();
            this.copyToClipboard(shortcode);
            
            const $btn = $('.avp-copy-shortcode');
            const originalHtml = $btn.html();
            $btn.html('<span class="dashicons dashicons-yes"></span>').prop('disabled', true);
            
            setTimeout(() => {
                $btn.html(originalHtml).prop('disabled', false);
            }, 2000);
        },
        
        copyToClipboard: function(text) {
            const $temp = $('<textarea>');
            $('body').append($temp);
            $temp.val(text).select();
            document.execCommand('copy');
            $temp.remove();
        },
        
        toggleAdvanced: function(e) {
            const $btn = $(e.currentTarget);
            const $content = $('.avp-advanced-options-content');
            
            $content.slideToggle(200);
            $btn.find('.dashicons')
                .toggleClass('dashicons-arrow-down-alt2 dashicons-arrow-up-alt2');
        },
        
        selectPoster: function() {
            const frame = wp.media({
                title: 'Select Poster Image',
                button: {
                    text: 'Use this image'
                },
                multiple: false
            });

            frame.on('select', function() {
                const attachment = frame.state().get('selection').first().toJSON();
                $('#avp-insert-poster').val(attachment.url);
                AVPAdmin.updateShortcode();
            });

            frame.open();
        },

        togglePasswordVisibility: function(e) {
            const $btn = $(e.currentTarget);
            const $input = $btn.prev('input');

            if (!$input.length) {
                return;
            }

            const newType = $input.attr('type') === 'password' ? 'text' : 'password';
            $input.attr('type', newType);
            $btn.find('.dashicons').toggleClass('dashicons-visibility dashicons-hidden');
        },

        testBunnyConnection: function(e) {
            e.preventDefault();

            const $btn = $('#avp-test-bunny-connection');
            const $status = $('#avp-connection-status');

            if (!$btn.length || !$status.length) {
                return;
            }

            const apiKey = $('#bunny_api_key').val();
            const libraryId = $('#bunny_library_id').val();

            if (!apiKey || !libraryId) {
                $status.removeClass('success').addClass('error').text('Please fill in API Key and Library ID first');
                return;
            }

            $btn.prop('disabled', true).find('.dashicons').addClass('spin');
            $status.removeClass('success error').text('Testing...');

            $.ajax({
                url: avpAdmin.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'avp_test_bunny_connection',
                    nonce: avpAdmin.nonce,
                    api_key: apiKey,
                    library_id: libraryId
                }
            }).done((response) => {
                if (response.success) {
                    $status.removeClass('error').addClass('success').html('✓ ' + (response.data ? response.data.message : 'Connected successfully'));
                } else {
                    const message = response.data && response.data.message ? response.data.message : 'Connection failed';
                    $status.removeClass('success').addClass('error').html('✗ ' + message);
                }
            }).fail(() => {
                $status.removeClass('success').addClass('error').html('✗ Connection failed');
            }).always(() => {
                $btn.prop('disabled', false).find('.dashicons').removeClass('spin');
            });
        },

        // Utility functions
        formatDuration: function(seconds) {
            if (!seconds) return '';
            
            const hours = Math.floor(seconds / 3600);
            const minutes = Math.floor((seconds % 3600) / 60);
            const secs = Math.floor(seconds % 60);
            
            if (hours > 0) {
                return `${hours}:${minutes.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
            }
            return `${minutes}:${secs.toString().padStart(2, '0')}`;
        },
        
        timeAgo: function(date) {
            if (!date) return '';
            
            const now = new Date();
            const past = new Date(date);
            const diffMs = now - past;
            const diffDays = Math.floor(diffMs / (1000 * 60 * 60 * 24));
            
            if (diffDays === 0) return 'Today';
            if (diffDays === 1) return 'Yesterday';
            if (diffDays < 7) return `${diffDays} days ago`;
            if (diffDays < 30) return `${Math.floor(diffDays / 7)} weeks ago`;
            if (diffDays < 365) return `${Math.floor(diffDays / 30)} months ago`;
            return `${Math.floor(diffDays / 365)} years ago`;
        },
        
        extractYouTubeId: function(url) {
            const match = url.match(/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/i);
            return match ? match[1] : null;
        },
        
        extractVimeoId: function(url) {
            const match = url.match(/(?:vimeo\.com\/)(\d+)/i);
            return match ? match[1] : null;
        },
        
        detectVideoType: function(url) {
            if (url.includes('youtube.com') || url.includes('youtu.be')) return 'youtube';
            if (url.includes('vimeo.com')) return 'vimeo';
            if (url.includes('.m3u8')) return 'hls';
            if (url.includes('.mpd')) return 'dash';
            if (url.includes('.webm')) return 'webm';
            return 'mp4';
        },
        
        showError: function(message) {
            alert(message);
        }
    };
    
    // Initialize on document ready
    $(document).ready(function() {
        if ($('.avp-admin-wrap').length) {
            AVPAdmin.init();
        }
    });
    
})(jQuery);

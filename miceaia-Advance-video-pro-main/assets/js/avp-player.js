/**
 * Advanced Video Player - JavaScript Principal
 */
var AVPPlayer = (function($) {
    'use strict';
    
    var players = {};
    var playerConfigs = {};
    var playbackRateQueue = {};
    var analytics = {
        enabled: true,
        events: []
    };
    var watermarkSessions = {};
    var sourceMenus = {};
    var watermarkResizeBound = false;
    var labels = (typeof window !== 'undefined' && window.avpData && window.avpData.labels) ? window.avpData.labels : {};
    labels = labels || {};
    var DEFAULT_WATERMARK_INTERVAL = 15000;
    var controlMinutesConfig = (typeof window !== 'undefined' && window.avpData && window.avpData.controlMinutes) ? window.avpData.controlMinutes : null;
    var controlMinutesQueue = [];
    var controlMinutesTimer = null;
    var controlMinutesState = {};
    var controlMinutesTimers = {};
    var controlMinutesBeforeUnloadBound = false;
    var CONTROL_MINUTES_FLUSH_INTERVAL = 15000;
    var CONTROL_MINUTES_MIN_DELTA = 15;
    var CONTROL_MINUTES_POLL_INTERVAL = 5000;

    return {
        getContextKey: function() {
            return '';
        },

        createStatus: function() {
            return {
                key: '',
                enforced: false,
                limitSeconds: 0,
                consumedSeconds: 0,
                remainingSeconds: 0,
                loaded: true,
                context: null
            };
        },

        getContextStatus: function() {
            return this.createStatus();
        },

        setContextStatus: function() {},

        hasControlMinutes: function() {
            return !!(controlMinutesConfig && controlMinutesConfig.enabled);
        },

        prepareControlMinutes: function(config) {
            if (!this.hasControlMinutes() || !config || !config.playerId) {
                return;
            }

            controlMinutesState[config.playerId] = {
                accumulated: 0,
                lastPosition: 0,
                lastFlush: Date.now(),
                duration: 0
            };

            if (!controlMinutesBeforeUnloadBound) {
                controlMinutesBeforeUnloadBound = true;
                $(window).on('beforeunload.avpControlMinutes', this.flushControlMinutesQueue.bind(this, true));
            }

            this.sendControlMinutesEvent('ready', config, {
                position: 0,
                duration: 0,
                seconds: 0
            });
        },

        teardownControlMinutes: function(playerId) {
            if (controlMinutesState[playerId]) {
                delete controlMinutesState[playerId];
            }

            this.stopControlMinutesTimer(playerId);
        },

        scheduleControlMinutesFlush: function() {
            if (!this.hasControlMinutes()) {
                return;
            }

            if (controlMinutesTimer) {
                return;
            }

            var self = this;
            controlMinutesTimer = setTimeout(function() {
                controlMinutesTimer = null;
                self.flushControlMinutesQueue();
            }, CONTROL_MINUTES_FLUSH_INTERVAL);
        },

        startControlMinutesTimer: function(playerId, callback) {
            if (!this.hasControlMinutes()) {
                return;
            }

            if (controlMinutesTimers[playerId]) {
                clearInterval(controlMinutesTimers[playerId]);
            }

            controlMinutesTimers[playerId] = setInterval(function() {
                try {
                    callback();
                } catch (error) {
                    console.warn('Control Minutos timer error', error);
                }
            }, CONTROL_MINUTES_POLL_INTERVAL);
        },

        stopControlMinutesTimer: function(playerId) {
            if (controlMinutesTimers[playerId]) {
                clearInterval(controlMinutesTimers[playerId]);
                delete controlMinutesTimers[playerId];
            }
        },

        flushControlMinutesQueue: function(force) {
            if (!this.hasControlMinutes()) {
                return;
            }

            if (!force && !controlMinutesQueue.length) {
                return;
            }

            if (!controlMinutesQueue.length) {
                return;
            }

            var payload = controlMinutesQueue.slice(0);
            controlMinutesQueue = [];

            var endpoint = this.getControlMinutesEndpoint();
            if (!endpoint) {
                return;
            }

            var requestBody = {
                client: (controlMinutesConfig && controlMinutesConfig.client) || 'advanced-video-player',
                events: payload
            };

            if (controlMinutesConfig && controlMinutesConfig.rest && controlMinutesConfig.rest.track && typeof window.fetch === 'function') {
                var headers = this.getControlMinutesHeaders();
                headers['Content-Type'] = 'application/json';

                fetch(controlMinutesConfig.rest.track, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: headers,
                    body: JSON.stringify(requestBody)
                }).catch(function(error) {
                    console.warn('Control Minutos request failed', error);
                });
            } else {
                var ajaxData = {
                    action: (controlMinutesConfig && controlMinutesConfig.action) || 'avp_control_minutes_event',
                    nonce: controlMinutesConfig ? controlMinutesConfig.nonce : '',
                    events: JSON.stringify(requestBody.events),
                    client: requestBody.client
                };

                var ajaxTarget = (controlMinutesConfig && controlMinutesConfig.ajaxUrl) || (window.avpData ? avpData.ajaxUrl : endpoint);

                $.post(ajaxTarget, ajaxData)
                    .fail(function(error) {
                        console.warn('Control Minutos AJAX error', error);
                    });
            }
        },

        getControlMinutesEndpoint: function() {
            if (controlMinutesConfig && controlMinutesConfig.rest && controlMinutesConfig.rest.track) {
                return controlMinutesConfig.rest.track;
            }

            if (controlMinutesConfig && controlMinutesConfig.ajaxUrl) {
                return controlMinutesConfig.ajaxUrl;
            }

            return '';
        },

        getControlMinutesHeaders: function() {
            var headers = {};

            if (controlMinutesConfig && controlMinutesConfig.rest && controlMinutesConfig.rest.nonce) {
                headers['X-WP-Nonce'] = controlMinutesConfig.rest.nonce;
            }

            return headers;
        },

        enqueueControlMinutesEvent: function(eventPayload) {
            if (!this.hasControlMinutes() || !eventPayload) {
                return;
            }

            controlMinutesQueue.push(eventPayload);
            this.scheduleControlMinutesFlush();
        },

        sendControlMinutesEvent: function(type, config, extra) {
            if (!this.hasControlMinutes()) {
                return;
            }

            var context = config && config.context ? $.extend({}, config.context) : {};
            var payload = $.extend({
                event: type,
                videoId: config ? config.videoId : '',
                playerId: config ? config.playerId : '',
                source: config ? config.src : '',
                context: context,
                timestamp: new Date().toISOString(),
                seconds: 0,
                position: 0,
                duration: 0,
                meta: {}
            }, extra || {});

            if (typeof navigator !== 'undefined' && navigator.userAgent) {
                payload.meta = payload.meta || {};
                if (!payload.meta.userAgent) {
                    payload.meta.userAgent = navigator.userAgent;
                }
            }

            this.enqueueControlMinutesEvent(payload);
        },

        onProgressUpdate: function(config, currentTime, duration) {
            if (!this.hasControlMinutes()) {
                return;
            }

            var state = controlMinutesState[config.playerId];
            if (!state) {
                return;
            }

            duration = duration || 0;

            if (!isNaN(duration) && duration > 0) {
                state.duration = duration;
            }

            var delta = 0;
            if (!isNaN(currentTime) && currentTime >= 0) {
                delta = Math.max(0, currentTime - state.lastPosition);
                state.lastPosition = currentTime;
            }

            state.accumulated += delta;

            var now = Date.now();
            var shouldSend = state.accumulated >= CONTROL_MINUTES_MIN_DELTA || (now - state.lastFlush) >= CONTROL_MINUTES_FLUSH_INTERVAL;

            if (shouldSend) {
                this.sendControlMinutesEvent('progress', config, {
                    seconds: state.accumulated,
                    position: currentTime,
                    duration: state.duration || duration
                });

                state.accumulated = 0;
                state.lastFlush = now;
            }
        },

        init: function(config) {
            var self = this;
            var playerId = config.playerId;

            config.sources = self.normalizeSources(config.sources);

            if (!config.originalVideoId) {
                config.originalVideoId = config.videoId;
            }

            if (config.type === 'youtube' || config.type === 'vimeo') {
                config.sources = [];
            } else if (config.sources.length > 0) {
                var initialSource = config.sources[0];
                if (initialSource && initialSource.src) {
                    config.src = initialSource.src;
                    config.type = self.resolveSourceType(initialSource.type, initialSource.src);
                }
            }

            config.activeSourceIndex = 0;
            config.playbackRates = self.normalizePlaybackRates(config.playbackRates, config.defaultPlaybackRate);
            config.defaultPlaybackRate = self.resolveDefaultPlaybackRate(config);
            config.context = config.context || null;
            playerConfigs[playerId] = config;

            if (typeof playbackRateQueue[playerId] === 'undefined') {
                playbackRateQueue[playerId] = config.defaultPlaybackRate;
            }

            self.prepareControlMinutes(config);

            self.prepareSpeedSelect(config);

            if (config.type === 'youtube') {
                self.initYouTube(config);
            } else if (config.type === 'vimeo') {
                self.initVimeo(config);
            } else {
                self.initHTML5(config);
            }

            if (self.shouldShowWatermark()) {
                self.renderWatermark(config);
            }
            
            // Inicializar analytics
            if (analytics.enabled) {
                self.initAnalytics(config);
            }
        },
        
        initHTML5: function(config) {
            var self = this;
            var player = videojs(config.playerId, {
                fluid: true,
                responsive: true,
                controls: true,
                preload: 'auto',
                controlBar: {
                    fullscreenToggle: true,
                    volumePanel: {
                        inline: false
                    }
                }
            });

            players[config.playerId] = player;

            player.ready(function() {
                self.positionSpeedSelect(config.playerId);
                self.applyStoredPlaybackRate(config.playerId);
                self.setupSourceMenu(player, config);
            });

            // Configurar HLS
            if (config.type === 'hls') {
                if (Hls.isSupported()) {
                    var hls = new Hls({
                        enableWorker: true,
                        lowLatencyMode: true
                    });

                    hls.loadSource(config.src);
                    hls.attachMedia(player.tech().el());
                    config._hlsInstance = hls;

                    // Manejar HLS encriptado
                    if (config.encrypted && config.drmUrl) {
                        hls.on(Hls.Events.KEY_LOADING, function(event, data) {
                            self.handleDRM(data, config.drmUrl);
                        });
                    }
                    
                    hls.on(Hls.Events.ERROR, function(event, data) {
                        if (data.fatal) {
                            console.error('Error fatal en HLS:', data);
                            self.trackError(config.videoId, 'HLS Error: ' + data.type);
                        }
                    });
                } else if (player.tech().el().canPlayType('application/vnd.apple.mpegurl')) {
                    // Safari nativo
                    player.src({
                        src: config.src,
                        type: 'application/x-mpegURL'
                    });
                }
            }

            // Configurar DASH
            if (config.type === 'dash') {
                var dashPlayer = dashjs.MediaPlayer().create();
                dashPlayer.initialize(player.tech().el(), config.src, false);
                config._dashInstance = dashPlayer;
            }
            
            // Configurar AB Loop
            if (config.abLoop) {
                self.setupABLoop(player, config);
            }
            
            // Configurar anuncios
            if (config.ads) {
                self.setupAds(player, config);
            }
            
            // Event listeners
            player.on('play', function() {
                self.trackEvent(config.videoId, 'play', player.currentTime());
                self.sendControlMinutesEvent('play', config, {
                    position: player.currentTime(),
                    duration: player.duration()
                });
            });

            player.on('pause', function() {
                var current = player.currentTime();
                var total = player.duration();
                self.trackEvent(config.videoId, 'pause', current);
                self.onProgressUpdate(config, current, total);
                self.sendControlMinutesEvent('pause', config, {
                    position: current,
                    duration: total
                });
                self.flushControlMinutesQueue(true);
            });

            player.on('ended', function() {
                var totalDuration = player.duration();
                self.trackEvent(config.videoId, 'ended', totalDuration);
                self.onProgressUpdate(config, totalDuration, totalDuration);
                self.sendControlMinutesEvent('complete', config, {
                    position: totalDuration,
                    duration: totalDuration
                });
                self.flushControlMinutesQueue(true);
            });

            player.on('timeupdate', function() {
                var currentTime = player.currentTime();
                var duration = player.duration();

                // Track milestone (25%, 50%, 75%, 100%)
                var progress = (currentTime / duration) * 100;
                if (progress >= 25 && !player.milestone25) {
                    player.milestone25 = true;
                    self.trackEvent(config.videoId, 'milestone_25', currentTime);
                }
                if (progress >= 50 && !player.milestone50) {
                    player.milestone50 = true;
                    self.trackEvent(config.videoId, 'milestone_50', currentTime);
                }
                if (progress >= 75 && !player.milestone75) {
                    player.milestone75 = true;
                    self.trackEvent(config.videoId, 'milestone_75', currentTime);
                }

                self.onProgressUpdate(config, currentTime, duration);
            });

            player.on('dispose', function() {
                self.teardownControlMinutes(config.playerId);
            });
        },
        
        initYouTube: function(config) {
            var self = this;
            var tag = document.createElement('script');
            tag.src = 'https://www.youtube.com/iframe_api';
            var firstScriptTag = document.getElementsByTagName('script')[0];
            firstScriptTag.parentNode.insertBefore(tag, firstScriptTag);
            
            window.onYouTubeIframeAPIReady = function() {
                var player = new YT.Player(config.playerId, {
                    events: {
                        'onReady': function(event) {
                            self.trackEvent(config.videoId, 'ready', 0);
                            self.positionSpeedSelect(config.playerId);
                            self.applyStoredPlaybackRate(config.playerId);
                        },
                        'onStateChange': function(event) {
                            if (event.data === YT.PlayerState.PLAYING) {
                                var currentTime = event.target.getCurrentTime();
                                var duration = event.target.getDuration();
                                self.trackEvent(config.videoId, 'play', currentTime);
                                self.sendControlMinutesEvent('play', config, {
                                    position: currentTime,
                                    duration: duration
                                });
                                self.startControlMinutesTimer(config.playerId, function() {
                                    var time = event.target.getCurrentTime();
                                    var total = event.target.getDuration();
                                    self.onProgressUpdate(config, time, total);
                                });
                            } else if (event.data === YT.PlayerState.PAUSED) {
                                var pauseTime = event.target.getCurrentTime();
                                var pauseDuration = event.target.getDuration();
                                self.trackEvent(config.videoId, 'pause', pauseTime);
                                self.onProgressUpdate(config, pauseTime, pauseDuration);
                                self.sendControlMinutesEvent('pause', config, {
                                    position: pauseTime,
                                    duration: pauseDuration
                                });
                                self.stopControlMinutesTimer(config.playerId);
                                self.flushControlMinutesQueue(true);
                            } else if (event.data === YT.PlayerState.ENDED) {
                                var totalDuration = event.target.getDuration();
                                self.trackEvent(config.videoId, 'ended', totalDuration);
                                self.stopControlMinutesTimer(config.playerId);
                                self.sendControlMinutesEvent('complete', config, {
                                    position: totalDuration,
                                    duration: totalDuration
                                });
                                self.flushControlMinutesQueue(true);
                            }
                        }
                    }
                });

                players[config.playerId] = player;
                self.positionSpeedSelect(config.playerId);
            };
        },
        
        initVimeo: function(config) {
            var self = this;
            var iframe = document.getElementById(config.playerId);
            var player = new Vimeo.Player(iframe);

            players[config.playerId] = player;
            self.positionSpeedSelect(config.playerId);

            if (player && typeof player.ready === 'function') {
                player.ready().then(function() {
                    self.positionSpeedSelect(config.playerId);
                    self.applyStoredPlaybackRate(config.playerId);
                }).catch(function(error) {
                    console.warn('Error preparing Vimeo playback rate', error);
                });
            }

            player.on('play', function() {
                player.getCurrentTime().then(function(time) {
                    self.trackEvent(config.videoId, 'play', time);
                    player.getDuration().then(function(duration) {
                        self.sendControlMinutesEvent('play', config, {
                            position: time,
                            duration: duration
                        });
                    });
                });
            });

            player.on('pause', function() {
                player.getCurrentTime().then(function(time) {
                    self.trackEvent(config.videoId, 'pause', time);
                    player.getDuration().then(function(duration) {
                        self.onProgressUpdate(config, time, duration);
                        self.sendControlMinutesEvent('pause', config, {
                            position: time,
                            duration: duration
                        });
                        self.flushControlMinutesQueue(true);
                    });
                });
                self.stopControlMinutesTimer(config.playerId);
            });

            player.on('ended', function() {
                player.getDuration().then(function(duration) {
                    self.trackEvent(config.videoId, 'ended', duration);
                    self.stopControlMinutesTimer(config.playerId);
                    self.sendControlMinutesEvent('complete', config, {
                        position: duration,
                        duration: duration
                    });
                    self.flushControlMinutesQueue(true);
                });
            });

            player.on('timeupdate', function(eventData) {
                if (!eventData || typeof eventData.seconds === 'undefined') {
                    return;
                }

                var current = eventData.seconds;
                var total = eventData.duration || 0;

                self.onProgressUpdate(config, current, total);
            });
        },

        setSpeedSelectMode: function($control, mode) {
            if (!$control || !$control.length) {
                return;
            }

            $control.removeClass('avp-speed-select--overlay avp-speed-select--inline vjs-control vjs-button');

            if (mode === 'overlay') {
                $control.addClass('avp-speed-select--overlay');
            } else if (mode === 'inline') {
                $control.addClass('avp-speed-select--inline vjs-control vjs-button');
            }

            $control.css('display', 'inline-flex');
            $control.removeAttr('hidden');
        },

        prepareSpeedSelect: function(config) {
            var self = this;
            var playerId = config.playerId;
            var $control = $('.avp-speed-select[data-player="' + playerId + '"]');

            if (!$control.length) {
                return;
            }

            var $field = $control.find('.avp-speed-select__field');
            if (!$field.length) {
                return;
            }

            var labelText = $.trim($control.find('.avp-speed-select__label').text());
            if (labelText) {
                $field.attr('aria-label', labelText);
            }

            var defaultRate = parseFloat($control.data('default-rate'));
            if (!isNaN(defaultRate) && defaultRate > 0) {
                playbackRateQueue[playerId] = defaultRate;
            }

            $field.off('change.avpSpeed').on('change.avpSpeed', function() {
                var rate = parseFloat($(this).val());
                if (isNaN(rate) || rate <= 0) {
                    return;
                }

                self.setPlaybackRate(playerId, rate, true);
            });

            self.updateSpeedControlUI(playerId, playbackRateQueue[playerId]);
            self.positionSpeedSelect(playerId);
        },

        setPlaybackRate: function(playerId, rate, allowQueue) {
            var self = this;
            var numericRate = parseFloat(rate);

            if (isNaN(numericRate) || numericRate <= 0) {
                return false;
            }

            var player = players[playerId];
            var config = playerConfigs[playerId] || {};
            var type = config.type || '';
            var applied = false;

            if (player) {
                if (type === 'youtube' && typeof player.setPlaybackRate === 'function') {
                    try {
                        player.setPlaybackRate(numericRate);
                        applied = true;
                    } catch (error) {
                        console.warn('Error setting YouTube playback rate', error);
                    }
                } else if (type === 'vimeo' && typeof player.setPlaybackRate === 'function') {
                    player.setPlaybackRate(numericRate).then(function() {
                        self.updateSpeedControlUI(playerId, numericRate);
                    }).catch(function(error) {
                        console.warn('Error setting Vimeo playback rate', error);
                    });
                    applied = true;
                } else if (player && typeof player.playbackRate === 'function') {
                    player.playbackRate(numericRate);
                    applied = true;
                } else if (player && typeof player.tech === 'function' && player.tech() && typeof player.tech().setPlaybackRate === 'function') {
                    player.tech().setPlaybackRate(numericRate);
                    applied = true;
                }
            }

            if (allowQueue !== false) {
                playbackRateQueue[playerId] = numericRate;
            }

            self.updateSpeedControlUI(playerId, numericRate);

            return applied;
        },

        applyStoredPlaybackRate: function(playerId) {
            var desiredRate = playbackRateQueue[playerId];

            if (typeof desiredRate === 'undefined') {
                var config = playerConfigs[playerId] || {};
                desiredRate = config.defaultPlaybackRate || 1;
                playbackRateQueue[playerId] = desiredRate;
            }

            this.setPlaybackRate(playerId, desiredRate, false);
        },

        positionSpeedSelect: function(playerId, attempt) {
            var self = this;
            var config = playerConfigs[playerId] || {};
            var $control = $('.avp-speed-select[data-player="' + playerId + '"]');

            if (!$control.length) {
                return;
            }

            if (config.type === 'youtube' || config.type === 'vimeo') {
                var $wrapper = $('[data-player-id="' + playerId + '"]');
                if ($wrapper.length) {
                    var $overlay = $control.detach();
                    $overlay.appendTo($wrapper);
                    self.setSpeedSelectMode($overlay, 'overlay');
                }
                return;
            }

            var player = players[playerId];
            if (!player || !player.controlBar || typeof player.controlBar.el !== 'function') {
                if ((attempt || 0) < 5) {
                    setTimeout(function() {
                        self.positionSpeedSelect(playerId, (attempt || 0) + 1);
                    }, 150);
                }
                return;
            }

            var controlBarEl = player.controlBar.el();
            if (!controlBarEl) {
                if ((attempt || 0) < 5) {
                    setTimeout(function() {
                        self.positionSpeedSelect(playerId, (attempt || 0) + 1);
                    }, 150);
                }
                return;
            }

            var $detached = $control.detach();
            self.setSpeedSelectMode($detached, 'inline');

            var fullscreenToggle = player.controlBar.getChild('FullscreenToggle');
            if (fullscreenToggle && typeof fullscreenToggle.el === 'function' && fullscreenToggle.el()) {
                $(fullscreenToggle.el()).before($detached);
                return;
            }

            var fullscreenFallback = controlBarEl.querySelector('.vjs-fullscreen-control');
            if (fullscreenFallback) {
                $(fullscreenFallback).before($detached);
                return;
            }

            var volumePanel = player.controlBar.getChild('VolumePanel');
            if (volumePanel && typeof volumePanel.el === 'function' && volumePanel.el()) {
                $(volumePanel.el()).after($detached);
                return;
            }

            $(controlBarEl).append($detached);
        },

        updateSpeedControlUI: function(playerId, rate) {
            var $control = $('.avp-speed-select[data-player="' + playerId + '"]');
            if (!$control.length) {
                return;
            }

            var $field = $control.find('.avp-speed-select__field');
            if (!$field.length) {
                return;
            }

            var target = this.stringifyRate(rate);
            if (target && $field.find('option[value="' + target + '"]').length) {
                $field.val(target);
            } else if (!$field.val()) {
                var firstOption = $field.find('option:first').val();
                if (typeof firstOption !== 'undefined') {
                    $field.val(firstOption);
                }
            }
        },

        stringifyRate: function(rate) {
            var numeric = parseFloat(rate);

            if (isNaN(numeric) || numeric <= 0) {
                return '';
            }

            if (Math.abs(numeric - Math.round(numeric)) < 0.001) {
                return String(Math.round(numeric));
            }

            return parseFloat(numeric.toFixed(2)).toString();
        },

        normalizePlaybackRates: function(rates, defaultRate) {
            var parsed = [];

            if (Array.isArray(rates)) {
                parsed = rates;
            } else if (typeof rates === 'string') {
                parsed = rates.split(',');
            }

            var cleaned = [];

            parsed.forEach(function(rate) {
                var numericRate = parseFloat(rate);
                if (!isNaN(numericRate) && numericRate > 0) {
                    cleaned.push(numericRate);
                }
            });

            if (!cleaned.length) {
                cleaned = [1, 1.5, 2];
            }

            if (typeof defaultRate === 'number' && !isNaN(defaultRate) && defaultRate > 0) {
                cleaned.push(defaultRate);
            }

            cleaned.push(1);

            var unique = [];

            cleaned.forEach(function(rate) {
                if (unique.every(function(existing) { return Math.abs(existing - rate) >= 0.001; })) {
                    unique.push(rate);
                }
            });

            unique.sort(function(a, b) {
                return a - b;
            });

            return unique;
        },

        resolveDefaultPlaybackRate: function(config) {
            var desired = parseFloat(config && config.defaultPlaybackRate);
            var rates = Array.isArray(config && config.playbackRates) ? config.playbackRates : [];

            if (isNaN(desired) || desired <= 0) {
                desired = 1;
            }

            var hasDesired = rates.some(function(rate) {
                return Math.abs(rate - desired) < 0.001;
            });

            if (hasDesired) {
                return desired;
            }

            var hasOne = rates.some(function(rate) {
                return Math.abs(rate - 1) < 0.001;
            });

            if (hasOne) {
                return 1;
            }

            return rates.length ? rates[0] : 1;
        },

        normalizeSources: function(sources) {
            if (!Array.isArray(sources)) {
                return [];
            }

            var self = this;
            var seen = {};
            var normalized = [];

            sources.forEach(function(item) {
                if (!item || typeof item !== 'object') {
                    return;
                }

                var src = item.src ? String(item.src).trim() : '';

                if (!src || seen[src]) {
                    return;
                }

                seen[src] = true;

                var entry = {
                    src: src,
                    label: item.label ? String(item.label) : self.guessSourceLabel(src, normalized.length),
                    type: self.resolveSourceType(item.type, src)
                };

                if (item.id) {
                    entry.id = String(item.id);
                }

                normalized.push(entry);
            });

            return normalized;
        },

        guessSourceLabel: function(src, index) {
            var match = src ? src.match(/([0-9]{3,4})p/i) : null;

            if (match && match[1]) {
                return match[1] + 'p';
            }

            if (labels && labels.quality) {
                return labels.quality + ' ' + (index + 1);
            }

            return index === 0 ? 'HD' : 'Quality ' + (index + 1);
        },

        resolveSourceType: function(type, src) {
            var value = (type || '').toString().toLowerCase();

            if (!value) {
                if (src && src.indexOf('.m3u8') !== -1) {
                    return 'hls';
                }
                if (src && src.indexOf('.mpd') !== -1) {
                    return 'dash';
                }
                if (src && src.indexOf('.webm') !== -1) {
                    return 'webm';
                }

                return 'mp4';
            }

            if (value === 'hls' || value.indexOf('mpegurl') !== -1 || value.indexOf('m3u8') !== -1) {
                return 'hls';
            }

            if (value === 'dash' || value.indexOf('dash') !== -1 || value.indexOf('mpd') !== -1) {
                return 'dash';
            }

            if (value.indexOf('webm') !== -1) {
                return 'webm';
            }

            if (value.indexOf('ogg') !== -1) {
                return 'ogv';
            }

            return 'mp4';
        },

        mapSourceToVideoJs: function(source) {
            var type = this.resolveSourceType(source.type, source.src);
            var mime = 'video/mp4';

            if (type === 'hls') {
                mime = 'application/x-mpegURL';
            } else if (type === 'dash') {
                mime = 'application/dash+xml';
            } else if (type === 'webm') {
                mime = 'video/webm';
            } else if (type === 'ogv') {
                mime = 'video/ogg';
            }

            return {
                src: source.src,
                type: mime
            };
        },

        getQualityLabel: function() {
            return (labels && labels.quality) ? labels.quality : 'Calidad';
        },

        formatQualityOptionLabel: function(optionLabel) {
            var template = (labels && labels.qualityOption) ? labels.qualityOption : 'Cambiar a %s';
            return template.replace('%s', optionLabel);
        },

        setupSourceMenu: function(player, config) {
            if (!config.sources || config.sources.length < 2) {
                if (sourceMenus[config.playerId]) {
                    sourceMenus[config.playerId].remove();
                    delete sourceMenus[config.playerId];
                }
                return;
            }

            var self = this;
            var $wrapper = $('[data-player-id="' + config.playerId + '"]');

            if (!$wrapper.length) {
                return;
            }

            if (sourceMenus[config.playerId]) {
                sourceMenus[config.playerId].remove();
            }

            var $container = $('<div>', {
                'class': 'avp-source-menu',
                'role': 'group',
                'aria-label': self.getQualityLabel()
            });

            var $label = $('<span>', {
                'class': 'avp-source-menu__label',
                text: self.getQualityLabel()
            });

            var $options = $('<div>', {
                'class': 'avp-source-menu__options'
            });

            $container.append($label).append($options);

            config.sources.forEach(function(source, index) {
                var labelText = source.label || self.guessSourceLabel(source.src, index);
                var $button = $('<button>', {
                    type: 'button',
                    'class': 'avp-source-menu__option' + (index === config.activeSourceIndex ? ' is-active' : ''),
                    text: labelText
                });

                $button.attr('data-source-index', index);
                $button.attr('aria-pressed', index === config.activeSourceIndex ? 'true' : 'false');
                $button.attr('title', self.formatQualityOptionLabel(labelText));

                $button.on('click', function(event) {
                    event.preventDefault();
                    self.switchSource(config.playerId, index);
                });

                $options.append($button);
            });

            $wrapper.append($container);

            sourceMenus[config.playerId] = $container;
            this.updateSourceMenu(config.playerId);
        },

        updateSourceMenu: function(playerId) {
            var container = sourceMenus[playerId];
            var config = playerConfigs[playerId];

            if (!container || !config) {
                return;
            }

            container.find('.avp-source-menu__option').each(function() {
                var $btn = $(this);
                var index = parseInt($btn.attr('data-source-index'), 10);
                var isActive = index === config.activeSourceIndex;
                $btn.toggleClass('is-active', isActive);
                $btn.attr('aria-pressed', isActive ? 'true' : 'false');
            });
        },

        switchSource: function(playerId, index) {
            var config = playerConfigs[playerId];

            if (!config || !config.sources || index < 0 || index >= config.sources.length) {
                return;
            }

            if (config.type === 'youtube' || config.type === 'vimeo') {
                return;
            }

            if (config.activeSourceIndex === index) {
                return;
            }

            var player = players[playerId];

            if (!player) {
                return;
            }

            var self = this;
            var source = config.sources[index];
            var wasPlaying = false;

            try {
                wasPlaying = !player.paused();
            } catch (error) {
                wasPlaying = false;
            }

            this.stopWatchSession(playerId, true);
            this.detachAdaptiveEngines(config);

            config.activeSourceIndex = index;
            config.src = source.src;
            config.type = this.resolveSourceType(source.type, source.src);
            config.videoId = source.id ? source.id : (config.originalVideoId ? config.originalVideoId + '-q' + (index + 1) : config.videoId + '-q' + (index + 1));

            var mappedSource = this.mapSourceToVideoJs(source);

            if (config.type === 'hls') {
                if (typeof Hls !== 'undefined' && Hls.isSupported()) {
                    var hls = new Hls({ enableWorker: true, lowLatencyMode: true });
                    hls.loadSource(mappedSource.src);
                    hls.attachMedia(player.tech().el());
                    config._hlsInstance = hls;

                    if (config.encrypted && config.drmUrl) {
                        hls.on(Hls.Events.KEY_LOADING, function(event, data) {
                            self.handleDRM(data, config.drmUrl);
                        });
                    }

                    hls.on(Hls.Events.ERROR, function(event, data) {
                        if (data.fatal) {
                            console.error('Error fatal en HLS:', data);
                            self.trackError(config.videoId, 'HLS Error: ' + data.type);
                        }
                    });
                } else {
                    player.src(mappedSource);
                }
            } else if (config.type === 'dash') {
                if (typeof dashjs !== 'undefined') {
                    var dashPlayer = dashjs.MediaPlayer().create();
                    dashPlayer.initialize(player.tech().el(), mappedSource.src, false);
                    config._dashInstance = dashPlayer;
                }
                player.src(mappedSource);
            } else {
                player.src(mappedSource);
            }

            this.updateSourceMenu(playerId);
            this.refreshRemainingDisplays();

            if (wasPlaying) {
                player.play().catch(function(error) {
                    console.warn('No se pudo reanudar la reproducción tras cambiar la calidad.', error);
                });
            }
        },

        detachAdaptiveEngines: function(config) {
            if (config._hlsInstance && typeof config._hlsInstance.destroy === 'function') {
                try {
                    config._hlsInstance.destroy();
                } catch (error) {
                    console.warn('No se pudo destruir la instancia de HLS previa.', error);
                }
            }

            config._hlsInstance = null;

            if (config._dashInstance && typeof config._dashInstance.reset === 'function') {
                try {
                    config._dashInstance.reset();
                } catch (error) {
                    console.warn('No se pudo reiniciar la instancia de DASH previa.', error);
                }
            }

            config._dashInstance = null;
        },

        setupABLoop: function(player, config) {
            var pointA = config.abStart || 0;
            var pointB = config.abEnd || 0;
            var loopEnabled = pointA > 0 && pointB > pointA;
            
            var $wrapper = $('[data-player-id="' + config.playerId + '"]');
            var $controls = $wrapper.find('.avp-ab-loop-controls');
            
            // Set Point A
            $controls.find('.avp-set-point-a').on('click', function() {
                pointA = player.currentTime();
                $(this).addClass('active');
                $controls.find('.avp-loop-indicator').text('A: ' + pointA.toFixed(2) + 's');
                
                if (pointB > pointA) {
                    loopEnabled = true;
                }
            });
            
            // Set Point B
            $controls.find('.avp-set-point-b').on('click', function() {
                pointB = player.currentTime();
                $(this).addClass('active');
                var indicator = $controls.find('.avp-loop-indicator');
                var currentText = indicator.text();
                indicator.text(currentText + ' | B: ' + pointB.toFixed(2) + 's');
                
                if (pointB > pointA) {
                    loopEnabled = true;
                }
            });
            
            // Clear loop
            $controls.find('.avp-clear-loop').on('click', function() {
                pointA = 0;
                pointB = 0;
                loopEnabled = false;
                $controls.find('button').removeClass('active');
                $controls.find('.avp-loop-indicator').text('');
            });
            
            // Implement loop
            player.on('timeupdate', function() {
                if (loopEnabled && player.currentTime() >= pointB) {
                    player.currentTime(pointA);
                }
            });
        },
        
        setupAds: function(player, config) {
            var self = this;
            var adDisplayed = false;
            
            player.on('play', function() {
                if (!adDisplayed && config.ads) {
                    adDisplayed = true;
                    self.displayAd(player, config);
                }
            });
        },
        
        displayAd: function(player, config) {
            player.pause();
            
            var $overlay = $('<div class="avp-ad-overlay"></div>');
            var $adContainer = $('<div class="avp-ad-container"></div>');
            var $adVideo = $('<video class="avp-ad-video" autoplay></video>');
            var $skipButton = $('<button class="avp-skip-ad" style="display:none;">Saltar anuncio en <span class="countdown">' + config.adsSkip + '</span>s</button>');
            
            $adVideo.attr('src', config.ads);
            $adContainer.append($adVideo);
            $adContainer.append($skipButton);
            $overlay.append($adContainer);
            
            $(player.el()).parent().append($overlay);
            
            // Countdown for skip button
            var countdown = config.adsSkip;
            var interval = setInterval(function() {
                countdown--;
                $skipButton.find('.countdown').text(countdown);
                
                if (countdown <= 0) {
                    $skipButton.html('Saltar anuncio').show();
                    clearInterval(interval);
                }
            }, 1000);
            
            // Skip ad
            $skipButton.on('click', function() {
                $overlay.remove();
                player.play();
                clearInterval(interval);
            });
            
            // Ad ended
            $adVideo[0].addEventListener('ended', function() {
                $overlay.remove();
                player.play();
                clearInterval(interval);
            });
        },
        
        handleDRM: function(data, drmUrl) {
            // Implementar lógica de DRM para HLS encriptado
            $.ajax({
                url: drmUrl,
                method: 'POST',
                data: JSON.stringify({
                    keyUri: data.frag.decryptdata.uri
                }),
                contentType: 'application/json',
                success: function(response) {
                    if (response.key) {
                        data.frag.decryptdata.key = response.key;
                    }
                },
                error: function(error) {
                    console.error('Error al obtener la clave DRM:', error);
                }
            });
        },
        
        trackEvent: function(videoId, eventType, currentTime) {
            if (!analytics.enabled) return;
            
            var eventData = {
                video_id: videoId,
                event_type: eventType,
                duration: Math.round(currentTime),
                timestamp: new Date().toISOString()
            };
            
            analytics.events.push(eventData);
            
            // Enviar al servidor
            $.ajax({
                url: avpData.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'avp_track_event',
                    nonce: avpData.nonce,
                    event: eventData
                },
                error: function(error) {
                    console.error('Error al trackear evento:', error);
                }
            });
        },
        
        trackError: function(videoId, errorMessage) {
            this.trackEvent(videoId, 'error', 0);
            
            $.ajax({
                url: avpData.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'avp_track_error',
                    nonce: avpData.nonce,
                    video_id: videoId,
                    error_message: errorMessage
                }
            });
        },
        
        initAnalytics: function(config) {
            // Inicializar tracking de analytics
            this.trackEvent(config.videoId, 'load', 0);
        },
        
        getPlayer: function(playerId) {
            return players[playerId];
        },
        
        getAnalytics: function() {
            return analytics.events;
        },

        setAnalyticsEnabled: function(enabled) {
            analytics.enabled = enabled;
        },

        getWatermarkConfig: function() {
            if (typeof avpData === 'undefined' || !avpData) {
                return null;
            }

            return avpData.watermark || null;
        },

        shouldShowWatermark: function() {
            var config = this.getWatermarkConfig();

            if (!config || !config.enabled) {
                return false;
            }

            var hasLines = Array.isArray(config.lines) && config.lines.length > 0;
            var hasText = !!config.text;

            return hasLines || hasText;
        },

        registerWatermarkSession: function(playerId, $element) {
            if (!$element || !$element.length) {
                return;
            }

            if (!watermarkSessions[playerId]) {
                watermarkSessions[playerId] = {
                    element: $element,
                    intervalId: null
                };
            } else {
                watermarkSessions[playerId].element = $element;
            }
        },

        renderWatermark: function(config) {
            var watermarkConfig = this.getWatermarkConfig();

            if (!watermarkConfig || !watermarkConfig.enabled) {
                return;
            }

            var $container = this.getWatermarkContainer(config);
            if (!$container.length) {
                return;
            }

            var $existing = $container.find('.avp-watermark');
            if ($existing.length) {
                this.registerWatermarkSession(config.playerId, $existing);
                this.applyWatermarkPosition(config.playerId, true);
                this.startWatermarkTicker(config.playerId);
                return;
            }

            var lines = Array.isArray(watermarkConfig.lines) ? watermarkConfig.lines.slice(0) : [];
            if (!lines.length && watermarkConfig.text) {
                lines.push(watermarkConfig.text);
            }

            if (!lines.length) {
                return;
            }

            var $watermark = $('<div class="avp-watermark"></div>');
            lines.forEach(function(line) {
                $('<div class="avp-watermark-line"></div>').text(line).appendTo($watermark);
            });

            $container.append($watermark);

            this.registerWatermarkSession(config.playerId, $watermark);
            this.applyWatermarkPosition(config.playerId, true);
            this.startWatermarkTicker(config.playerId);

            if (!watermarkResizeBound) {
                var self = this;
                $(window).on('resize.avpWatermark', function() {
                    self.refreshWatermarkPositions(true);
                });
                watermarkResizeBound = true;
            }
        },

        getWatermarkContainer: function(config) {
            var $wrapper = $('[data-player-id="' + config.playerId + '"]');

            if (!$wrapper.length) {
                return $();
            }

            var $videoContainer = $wrapper.find('.video-js');

            if ($videoContainer.length) {
                return $videoContainer.eq(0);
            }

            return $wrapper.eq(0);
        },

        startWatermarkTicker: function(playerId) {
            var session = watermarkSessions[playerId];

            if (!session || session.intervalId) {
                if (session && session.intervalId && (!session.element || !session.element.length)) {
                    clearInterval(session.intervalId);
                    session.intervalId = null;
                }
                return;
            }

            if (!session.element || !session.element.length) {
                return;
            }

            var watermarkConfig = this.getWatermarkConfig();
            var interval = DEFAULT_WATERMARK_INTERVAL;

            if (watermarkConfig && watermarkConfig.moveInterval) {
                var parsedInterval = parseInt(watermarkConfig.moveInterval, 10);
                if (!isNaN(parsedInterval) && parsedInterval > 0) {
                    interval = parsedInterval;
                }
            }

            var self = this;
            session.intervalId = setInterval(function() {
                self.applyWatermarkPosition(playerId, false);
            }, interval);
        },

        applyWatermarkPosition: function(playerId, immediate) {
            var session = watermarkSessions[playerId];

            if (!session) {
                return;
            }

            if (!session.element || !session.element.length) {
                if (session.intervalId) {
                    clearInterval(session.intervalId);
                }
                delete watermarkSessions[playerId];
                return;
            }

            var $element = session.element;

            if (immediate) {
                $element.addClass('avp-watermark--immediate');
            }

            var vertical = 10 + Math.random() * 70;
            var horizontal = 10 + Math.random() * 70;
            var opacity = 0.5 + Math.random() * 0.3;
            var rotation = (Math.random() * 4) - 2;

            $element.css({
                top: vertical.toFixed(2) + '%',
                left: horizontal.toFixed(2) + '%',
                right: 'auto',
                bottom: 'auto',
                transform: 'translate(-50%, -50%) rotate(' + rotation.toFixed(2) + 'deg)',
                opacity: opacity.toFixed(2)
            });

            if (immediate) {
                setTimeout(function() {
                    $element.removeClass('avp-watermark--immediate');
                }, 60);
            }
        },

        refreshWatermarkPositions: function(forceImmediate) {
            var self = this;

            Object.keys(watermarkSessions).forEach(function(playerId) {
                self.applyWatermarkPosition(playerId, !!forceImmediate);
            });
        },

        isWatchLimitEnabled: function() {
            return false;
        },

        ensureContextStatus: function() {
            return $.Deferred().resolve(this.createStatus()).promise();
        },

        normaliseContextResponse: function() {
            return this.createStatus();
        },

        onPlaybackStart: function() {},

        onPlaybackStop: function() {},

        stopWatchSession: function() {},

        showWatchLimitMessage: function() {},

        removeWatchLimitMessage: function() {},

        refreshRemainingDisplays: function() {},

        flushWatchUsage: function() {},
    };
    
})(jQuery);

// Inicialización global
jQuery(document).ready(function($) {
    // Cargar Vimeo Player API si es necesario
    if ($('iframe[src*="vimeo.com"]').length > 0) {
        if (typeof Vimeo === 'undefined') {
            var script = document.createElement('script');
            script.src = 'https://player.vimeo.com/api/player.js';
            document.head.appendChild(script);
        }
    }
});
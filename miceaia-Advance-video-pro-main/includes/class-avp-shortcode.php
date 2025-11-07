<?php
declare(strict_types=1);

/**
 * Clase para manejar el shortcode del reproductor.
 */
class AVP_Shortcode {

    /**
     * @var int Contador de reproductores en la página
     */
    private static $player_count = 0;

    public function __construct() {
        add_shortcode('avp_player', array($this, 'render_player'));
        add_shortcode('video_player', array($this, 'render_player'));
    }

    /**
     * Renderiza el reproductor de video
     *
     * @param array $atts Atributos del shortcode
     * @return string HTML del reproductor
     */
    public function render_player(array $atts = array()) {
        self::$player_count++;

        $atts = shortcode_atts(
            array(
                'src'            => '',
                'type'           => 'auto',
                'poster'         => '',
                'width'          => '100%',
                'height'         => '500px',
                'autoplay'       => 'false',
                'loop'           => 'false',
                'muted'          => 'false',
                'controls'       => 'true',
                'preload'        => 'metadata',
                'ab_loop'        => 'false',
                'ab_start'       => '0',
                'ab_end'         => '0',
                'ads'            => '',
                'ads_skip'       => '5',
                'subtitle'       => '',
                'subtitle_label' => 'Español',
                'encrypted'      => 'false',
                'drm_url'        => '',
                'id'             => '',
                'playback_rates' => '0.5,0.75,1,1.5,1.75,2',
                'sources'        => ''
            ),
            $atts
        );

        $player_id = 'avp-player-' . self::$player_count;
        $video_id  = ! empty($atts['id']) ? sanitize_text_field($atts['id']) : $player_id;

        $playback_rates = $this->parse_playback_rates($atts['playback_rates']);
        $default_rate   = $this->get_default_playback_rate($playback_rates);
        $sources        = $this->parse_sources($atts['sources']);

        if (empty($atts['src']) && ! empty($sources)) {
            $primary_source = reset($sources);
            if (is_array($primary_source) && isset($primary_source['src'])) {
                $atts['src'] = $primary_source['src'];
                if (! empty($primary_source['type'])) {
                    $atts['type'] = $primary_source['type'];
                }
            }
        }

        global $post;
        $post_id      = isset($post) ? (int) $post->ID : 0;
        $course_id    = 0;
        $course_title = '';
        $lesson_id    = $post_id;
        $lesson_title = $post_id ? get_the_title($post_id) : '';

        if ($post_id && function_exists('learndash_get_course_id')) {
            $course_id = (int) learndash_get_course_id($post_id);
            if ($course_id) {
                $course_title = get_the_title($course_id);
            }
        }

        if ($post_id && function_exists('learndash_get_lesson_id')) {
            $derived_lesson_id = (int) learndash_get_lesson_id($post_id);
            if ($derived_lesson_id > 0) {
                $lesson_id    = $derived_lesson_id;
                $lesson_title = get_the_title($derived_lesson_id);
            }
        }

        $player_context = array(
            'postId'      => $post_id,
            'lessonId'    => $lesson_id,
            'courseId'    => $course_id,
            'lessonTitle' => $lesson_title ? wp_strip_all_tags($lesson_title) : '',
            'courseTitle' => $course_title ? wp_strip_all_tags($course_title) : ''
        );

        if ($atts['type'] === 'auto') {
            $atts['type'] = $this->detect_video_type($atts['src']);
        }

        ob_start();

        echo '<div class="avp-player-wrapper" data-player-id="' . esc_attr($player_id) . '">';

        if ($atts['type'] === 'youtube' || $atts['type'] === 'vimeo') {
            echo $this->render_embed_player($player_id, $atts);
        } else {
            echo $this->render_html5_player($player_id, $atts, $sources);
        }

        $select_id = $player_id . '-speed';

        echo '<div class="avp-speed-select" hidden data-player="' . esc_attr($player_id) . '" data-default-rate="' . esc_attr($this->format_playback_rate_value($default_rate)) . '">';
        echo '<label class="avp-speed-select__label" for="' . esc_attr($select_id) . '">' . esc_html__('Velocidad', 'advanced-video-player') . '</label>';
        echo '<select class="avp-speed-select__field" id="' . esc_attr($select_id) . '" autocomplete="off">';

        foreach ($playback_rates as $rate) {
            $value     = $this->format_playback_rate_value($rate);
            $is_active = abs($rate - $default_rate) < 0.001;
            echo '<option value="' . esc_attr($value) . '"' . selected($is_active, true, false) . '>' . esc_html($value . 'x') . '</option>';
        }

        echo '</select>';
        echo '</div>';
        echo '</div>';

        $this->render_player_script($player_id, $video_id, $atts, $playback_rates, $default_rate, $player_context, $sources);

        return ob_get_clean();
    }

    /**
     * Detecta el tipo de video basado en la URL
     *
     * @param string $src URL del video
     * @return string Tipo de video detectado
     */
    private function detect_video_type($src) {
        if (strpos($src, 'youtube.com') !== false || strpos($src, 'youtu.be') !== false) {
            return 'youtube';
        }

        if (strpos($src, 'vimeo.com') !== false) {
            return 'vimeo';
        }

        if (strpos($src, '.m3u8') !== false) {
            return 'hls';
        }

        if (strpos($src, '.mpd') !== false) {
            return 'dash';
        }

        if (strpos($src, '.webm') !== false) {
            return 'webm';
        }

        return 'mp4';
    }

    /**
     * Renderiza el reproductor HTML5
     *
     * @param string $player_id ID del reproductor
     * @param array $atts Atributos del reproductor
     * @param array $sources Fuentes de video adicionales
     * @return string HTML del reproductor
     */
    private function render_html5_player($player_id, array $atts, array $sources = array()) {
        $width  = esc_attr($atts['width']);
        $height = esc_attr($atts['height']);
        $poster = ! empty($atts['poster']) ? ' poster="' . esc_url($atts['poster']) . '"' : '';

        $html  = '<video id="' . esc_attr($player_id) . '" class="video-js vjs-default-skin vjs-big-play-centered"';
        $html .= ' style="width:' . $width . ';height:' . $height . ';"';
        $html .= $atts['controls'] === 'true' ? ' controls' : '';
        $html .= $atts['autoplay'] === 'true' ? ' autoplay' : '';
        $html .= $atts['loop'] === 'true' ? ' loop' : '';
        $html .= $atts['muted'] === 'true' ? ' muted' : '';
        $html .= ' preload="' . esc_attr($atts['preload']) . '"';
        $html .= $poster;
        $html .= '>';

        if ($atts['type'] === 'hls' || $atts['type'] === 'dash') {
            $html .= '<source src="' . esc_url($atts['src']) . '" type="application/x-mpegURL">';
        } else {
            $mime_type = $this->get_mime_type($atts['type']);
            $html      .= '<source src="' . esc_url($atts['src']) . '" type="' . $mime_type . '">';
        }

        if (! empty($sources)) {
            $index = 0;
            foreach ($sources as $source) {
                if (!is_array($source)) {
                    continue;
                }

                $index++;
                if (! isset($source['src']) || $source['src'] === '') {
                    continue;
                }

                if ($index === 1 && $source['src'] === $atts['src']) {
                    continue;
                }

                $source_type = isset($source['type']) ? $source['type'] : '';
                $html       .= '<source src="' . esc_url($source['src']) . '" type="' . esc_attr($this->get_mime_type($source_type)) . '">';
            }
        }

        if (! empty($atts['subtitle'])) {
            $html .= '<track kind="subtitles" src="' . esc_url($atts['subtitle']) . '" srclang="es" label="' . esc_attr($atts['subtitle_label']) . '">';
        }

        $html .= '<p class="vjs-no-js">Para ver este video, habilita JavaScript y considera actualizar tu navegador.</p>';
        $html .= '</video>';

        if ($atts['ab_loop'] === 'true') {
            $html .= $this->render_ab_loop_controls($player_id);
        }

        return $html;
    }

    /**
     * Renderiza el reproductor embed (YouTube/Vimeo)
     *
     * @param string $player_id ID del reproductor
     * @param array $atts Atributos del reproductor
     * @return string HTML del reproductor embed
     */
    private function render_embed_player($player_id, array $atts) {
        $width  = esc_attr($atts['width']);
        $height = esc_attr($atts['height']);

        if ($atts['type'] === 'youtube') {
            $video_id = $this->extract_youtube_id($atts['src']);
            $autoplay = $atts['autoplay'] === 'true' ? '&autoplay=1' : '';
            $loop     = $atts['loop'] === 'true' ? '&loop=1&playlist=' . $video_id : '';
            $muted    = $atts['muted'] === 'true' ? '&mute=1' : '';

            return '<iframe id="' . esc_attr($player_id) . '" width="' . $width . '" height="' . $height . '"'
                . ' src="https://www.youtube.com/embed/' . $video_id . '?enablejsapi=1' . $autoplay . $loop . $muted . '"'
                . ' frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"'
                . ' allowfullscreen></iframe>';
        }

        if ($atts['type'] === 'vimeo') {
            $video_id = $this->extract_vimeo_id($atts['src']);
            $autoplay = $atts['autoplay'] === 'true' ? '&autoplay=1' : '';
            $loop     = $atts['loop'] === 'true' ? '&loop=1' : '';
            $muted    = $atts['muted'] === 'true' ? '&muted=1' : '';

            return '<iframe id="' . esc_attr($player_id) . '" src="https://player.vimeo.com/video/' . $video_id . '?api=1' . $autoplay . $loop . $muted . '"'
                . ' width="' . $width . '" height="' . $height . '" frameborder="0"'
                . ' allow="autoplay; fullscreen; picture-in-picture" allowfullscreen></iframe>';
        }

        return '';
    }

    /**
     * Renderiza los controles A/B Loop
     *
     * @param string $player_id ID del reproductor
     * @return string HTML de los controles
     */
    private function render_ab_loop_controls($player_id) {
        return '
        <div class="avp-ab-loop-controls" data-player="' . esc_attr($player_id) . '">
            <button class="avp-set-point-a" title="Set Point A">A</button>
            <button class="avp-set-point-b" title="Set Point B">B</button>
            <button class="avp-clear-loop" title="Clear Loop">Clear</button>
            <span class="avp-loop-indicator"></span>
        </div>';
    }

    /**
     * Renderiza el script del reproductor
     *
     * @param string $player_id ID del reproductor
     * @param string $video_id ID del video
     * @param array $atts Atributos del reproductor
     * @param array $playback_rates Velocidades de reproducción disponibles
     * @param float $default_rate Velocidad por defecto
     * @param array $player_context Contexto del reproductor
     * @param array $sources Fuentes de video adicionales
     * @return void
     */
    private function render_player_script($player_id, $video_id, array $atts, array $playback_rates, $default_rate, array $player_context, array $sources) {
        ?>
        <script>
        jQuery(document).ready(function($) {
            var playerConfig = {
                playerId: '<?php echo esc_js($player_id); ?>',
                videoId: '<?php echo esc_js($video_id); ?>',
                type: '<?php echo esc_js($atts['type']); ?>',
                src: '<?php echo esc_js($atts['src']); ?>',
                abLoop: <?php echo $atts['ab_loop'] === 'true' ? 'true' : 'false'; ?>,
                abStart: <?php echo intval($atts['ab_start']); ?>,
                abEnd: <?php echo intval($atts['ab_end']); ?>,
                ads: '<?php echo esc_js($atts['ads']); ?>',
                adsSkip: <?php echo intval($atts['ads_skip']); ?>,
                encrypted: <?php echo $atts['encrypted'] === 'true' ? 'true' : 'false'; ?>,
                drmUrl: '<?php echo esc_js($atts['drm_url']); ?>',
                playbackRates: <?php echo wp_json_encode(array_values($playback_rates)); ?>,
                defaultPlaybackRate: <?php echo wp_json_encode($default_rate); ?>,
                context: <?php echo wp_json_encode($player_context); ?>,
                sources: <?php echo wp_json_encode(array_values($sources)); ?>
            };

            if (typeof AVPPlayer !== 'undefined') {
                AVPPlayer.init(playerConfig);
            }
        });
        </script>
        <?php
    }

    /**
     * Parsea las fuentes de video desde string o array
     *
     * @param string|array $raw_sources Fuentes en formato string o array
     * @return array Array de fuentes parseadas
     */
    private function parse_sources($raw_sources) {
        if (empty($raw_sources)) {
            return array();
        }

        if (is_array($raw_sources)) {
            $candidates = $raw_sources;
        } else {
            $decoded = json_decode(wp_unslash($raw_sources), true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $candidates = $decoded;
            } else {
                $candidates = $this->parse_sources_from_string($raw_sources);
            }
        }

        if (!is_array($candidates)) {
            return array();
        }

        $normalized = array();

        foreach ($candidates as $candidate) {
            $entry = $this->normalize_source_entry($candidate);
            if (is_array($entry)) {
                $normalized[] = $entry;
            }
        }

        return $normalized;
    }

    /**
     * Parsea fuentes de video desde formato string
     *
     * @param string $raw_sources String con fuentes de video
     * @return array Array de fuentes parseadas
     */
    private function parse_sources_from_string($raw_sources) {
        $segments = array_map('trim', explode(',', $raw_sources));
        $parsed   = array();

        foreach ($segments as $segment) {
            if ($segment === '') {
                continue;
            }

            $parts = array_map('trim', explode('|', $segment));
            $entry = array();

            if (isset($parts[0])) {
                $entry['label'] = $parts[0];
            }

            if (isset($parts[1])) {
                $entry['src'] = $parts[1];
            }

            if (isset($parts[2])) {
                $entry['type'] = $parts[2];
            }

            $parsed[] = $entry;
        }

        return $parsed;
    }

    /**
     * Normaliza una entrada de fuente de video
     *
     * @param mixed $candidate Candidato a normalizar
     * @return array|null Array normalizado o null
     */
    private function normalize_source_entry($candidate) {
        if (!is_array($candidate)) {
            return null;
        }

        $src = isset($candidate['src']) ? esc_url_raw($candidate['src']) : '';
        if ($src === '') {
            return null;
        }

        if (isset($candidate['label'])) {
            $label = sanitize_text_field((string) $candidate['label']);
        } elseif (isset($candidate['quality'])) {
            $label = sanitize_text_field((string) $candidate['quality']);
        } else {
            $label = $this->guess_source_label($src);
        }

        $type = isset($candidate['type']) ? sanitize_text_field((string) $candidate['type']) : '';
        if ($type === '') {
            $type = $this->detect_video_type($src);
        }

        $entry = array(
            'src'   => $src,
            'label' => $label,
            'type'  => $type,
        );

        if (isset($candidate['id']) && $candidate['id'] !== '') {
            $entry['id'] = sanitize_key((string) $candidate['id']);
        }

        return $entry;
    }

    /**
     * Adivina la calidad de la fuente desde la URL
     *
     * @param string $src URL de la fuente
     * @return string Etiqueta de calidad
     */
    private function guess_source_label($src) {
        if (preg_match('/([0-9]{3,4})p/i', $src, $matches)) {
            return $matches[1] . 'p';
        }

        return __('Calidad alternativa', 'advanced-video-player');
    }

    /**
     * Extrae el ID de video de YouTube
     *
     * @param string $url URL del video
     * @return string ID del video
     */
    private function extract_youtube_id($url) {
        preg_match('/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/ ]{11})/i', $url, $matches);
        return isset($matches[1]) ? $matches[1] : '';
    }

    /**
     * Extrae el ID de video de Vimeo
     *
     * @param string $url URL del video
     * @return string ID del video
     */
    private function extract_vimeo_id($url) {
        preg_match('/(?:vimeo\.com\/)(\d+)/i', $url, $matches);
        return isset($matches[1]) ? $matches[1] : '';
    }

    /**
     * Parsea las velocidades de reproducción
     *
     * @param string|array $rates Velocidades en formato string o array
     * @return array Array de velocidades parseadas
     */
    private function parse_playback_rates($rates) {
        if (is_string($rates)) {
            $rates = explode(',', $rates);
        }

        if (!is_array($rates)) {
            $rates = array();
        }

        $parsed = array();

        foreach ($rates as $rate) {
            if (is_numeric($rate)) {
                $value = (float) $rate;
            } else {
                $value = (float) str_replace(',', '.', (string) $rate);
            }

            if ($value > 0) {
                $parsed[] = $value;
            }
        }

        if (empty($parsed)) {
            $parsed = array(1.0, 1.5, 2.0);
        }

        if (!in_array(1.0, $parsed, true)) {
            $parsed[] = 1.0;
        }

        $parsed = array_unique(array_map('floatval', $parsed));
        sort($parsed, SORT_NUMERIC);

        return $parsed;
    }

    /**
     * Obtiene la velocidad de reproducción por defecto
     *
     * @param array $rates Velocidades disponibles
     * @return float Velocidad por defecto
     */
    private function get_default_playback_rate(array $rates) {
    return 1.0;
    }
    
    /**
     * Formatea el valor de velocidad de reproducción
     *
     * @param float $rate Velocidad
     * @return string Velocidad formateada
     */
    private function format_playback_rate_value($rate) {
        if (abs($rate - round($rate)) < 0.001) {
            return (string) (int) round($rate);
        }

        return rtrim(rtrim(number_format($rate, 2, '.', ''), '0'), '.');
    }

    /**
     * Obtiene el tipo MIME para un tipo de video
     *
     * @param string $type Tipo de video
     * @return string Tipo MIME
     */
    private function get_mime_type($type) {
        $mime_types = array(
            'mp4' => 'video/mp4',
            'webm' => 'video/webm',
            'ogv' => 'video/ogg',
            'hls' => 'application/x-mpegURL',
            'dash' => 'application/dash+xml',
        );

        return isset($mime_types[$type]) ? $mime_types[$type] : 'video/mp4';
    }
}
?>
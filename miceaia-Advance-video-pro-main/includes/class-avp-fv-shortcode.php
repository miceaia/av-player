<?php
/**
 * Compatibilidad básica con la sintaxis del shortcode de FV Player.
 */
class AVP_FV_Shortcodes {

    /** @var AVP_Shortcode */
    private $shortcode_handler;

    /**
     * @param AVP_Shortcode $shortcode_handler
     */
    public function __construct($shortcode_handler) {
        if (!($shortcode_handler instanceof AVP_Shortcode)) {
            return;
        }

        $this->shortcode_handler = $shortcode_handler;

        add_shortcode('fvplayer', array($this, 'render_fvplayer'));
    }

    /**
     * Renderiza el shortcode [fvplayer] mapeándolo al render del reproductor principal.
     *
     * @param array $atts
     * @return string
     */
    public function render_fvplayer($atts) {
        if (!$this->shortcode_handler) {
            return '';
        }

        $atts = shortcode_atts(array(
            'src' => '',
            'src1' => '',
            'src2' => '',
            'src3' => '',
            'src4' => '',
            'src5' => '',
            'quality' => '',
            'quality1' => '',
            'quality2' => '',
            'quality3' => '',
            'quality4' => '',
            'quality5' => '',
            'speed' => '',
            'autoplay' => '',
            'loop' => '',
            'muted' => '',
            'splash' => '',
            'subtitles' => '',
            'captions' => '',
            'captions_label' => '',
            'id' => '',
            'drm' => '',
            'drm_url' => '',
            'drmurl' => '',
            'drmkey' => '',
            'ad' => '',
            'ad_skip' => '',
            'width' => '',
            'height' => '',
            'controls' => '',
            'mute' => ''
        ), $atts, 'fvplayer');

        $sources = $this->collect_sources($atts);
        $primary_source = !empty($sources) ? $sources[0]['src'] : $this->sanitize_url($atts['src']);
        $playback_rates = $this->parse_speed_attribute($atts['speed']);
        $subtitle_src = $this->sanitize_url($atts['subtitles']);

        if (!$subtitle_src) {
            $subtitle_src = $this->sanitize_url($atts['captions']);
        }

        $mapped = array(
            'src' => $primary_source,
            'poster' => $this->sanitize_url($atts['splash']),
            'autoplay' => $this->normalize_flag($atts['autoplay']),
            'loop' => $this->normalize_flag($atts['loop']),
            'muted' => $this->normalize_flag($atts['muted'] ?: $atts['mute']),
            'controls' => $this->normalize_controls_flag($atts['controls']),
            'playback_rates' => $playback_rates,
            'subtitle' => $subtitle_src,
            'subtitle_label' => $this->sanitize_text($atts['captions_label']),
            'drm_url' => $this->sanitize_url($atts['drm_url'] ?: $atts['drmurl'] ?: $atts['drm'] ?: $atts['drmkey']),
            'ads' => $this->sanitize_url($atts['ad']),
            'ads_skip' => $this->sanitize_integer($atts['ad_skip']),
            'width' => $this->sanitize_dimension($atts['width']),
            'height' => $this->sanitize_dimension($atts['height'])
        );

        if (!empty($sources)) {
            $mapped['sources'] = $sources;
        }

        if (!empty($atts['id'])) {
            $mapped['id'] = sanitize_key($atts['id']);
        }

        $filtered = array();
        foreach ($mapped as $key => $value) {
            if ($value === '' || $value === null || (is_array($value) && empty($value))) {
                continue;
            }

            $filtered[$key] = $value;
        }

        return $this->shortcode_handler->render_player($filtered);
    }

    /**
     * Recoge y normaliza múltiples calidades definidas en el shortcode de FV Player.
     *
     * @param array $atts
     * @return array
     */
    private function collect_sources($atts) {
        $sources = array();

        $candidates = array(
            array('src' => $atts['src'], 'label' => $atts['quality']),
            array('src' => $atts['src1'], 'label' => $atts['quality1']),
            array('src' => $atts['src2'], 'label' => $atts['quality2']),
            array('src' => $atts['src3'], 'label' => $atts['quality3']),
            array('src' => $atts['src4'], 'label' => $atts['quality4']),
            array('src' => $atts['src5'], 'label' => $atts['quality5'])
        );

        $index = 0;
        foreach ($candidates as $candidate) {
            $src = $this->sanitize_url($candidate['src']);
            if (!$src) {
                continue;
            }

            $label = $this->sanitize_text($candidate['label']);
            if ($label === '') {
                $label = $this->guess_quality_label($src, $index);
            }

            $sources[] = array(
                'src' => $src,
                'label' => $label,
                'type' => $this->guess_source_type($src),
                'id' => $this->build_source_id($atts, $index)
            );

            $index++;
        }

        return $sources;
    }

    private function guess_quality_label($src, $index) {
        $pattern = '/([0-9]{3,4})p/';
        if (preg_match($pattern, $src, $matches)) {
            return $matches[1] . 'p';
        }

        return $index === 0 ? __('Original', 'advanced-video-player') : sprintf(__('Opción %d', 'advanced-video-player'), $index + 1);
    }

    private function guess_source_type($src) {
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

    private function build_source_id($atts, $index) {
        $base_id = !empty($atts['id']) ? sanitize_key($atts['id']) : '';
        if ($base_id === '') {
            return '';
        }

        return $index === 0 ? $base_id : $base_id . '-q' . ($index + 1);
    }

    private function parse_speed_attribute($raw) {
        if (empty($raw)) {
            return '';
        }

        $raw = wp_strip_all_tags((string) $raw);
        $parts = array_map('trim', explode(',', $raw));
        $rates = array();

        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }

            $chunks = array_map('trim', explode('|', $part));
            $candidate = $chunks[0];

            $candidate = str_replace(',', '.', $candidate);
            if (is_numeric($candidate)) {
                $value = (float) $candidate;
                if ($value > 0) {
                    $rates[] = $value;
                }
            }
        }

        if (empty($rates)) {
            return '';
        }

        $rates = array_unique(array_map('floatval', $rates));
        sort($rates, SORT_NUMERIC);

        return implode(',', array_map(array($this, 'format_rate'), $rates));
    }

    private function format_rate($rate) {
        $rate = (float) $rate;
        if (abs($rate - round($rate)) < 0.001) {
            return (string) round($rate);
        }

        return rtrim(rtrim(number_format($rate, 2, '.', ''), '0'), '.');
    }

    private function normalize_flag($value) {
        if ($value === true || $value === 'true' || $value === '1' || $value === 1 || $value === 'yes' || $value === 'on') {
            return 'true';
        }

        return 'false';
    }

    private function normalize_controls_flag($value) {
        if ($value === '') {
            return 'true';
        }

        return $this->normalize_flag($value);
    }

    private function sanitize_text($value) {
        return $value !== '' ? sanitize_text_field($value) : '';
    }

    private function sanitize_url($value) {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        $sanitized = esc_url_raw($value);
        return $sanitized ?: '';
    }

    private function sanitize_integer($value) {
        if ($value === '' || $value === null) {
            return '';
        }

        return (string) max(0, (int) $value);
    }

    private function sanitize_dimension($value) {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        if (is_numeric($value)) {
            return $value . 'px';
        }

        return preg_match('/^[0-9]+(px|%|vh|vw)$/', $value) ? $value : '';
    }
}

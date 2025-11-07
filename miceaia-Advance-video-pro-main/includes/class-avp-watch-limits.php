<?php
/**
 * Gestión de límites de tiempo de reproducción por usuario
 */
class AVP_Watch_Limits {

    const META_CONSUMED = 'avp_watch_consumed';
    const META_LIMIT = 'avp_watch_limit';
    const META_POST_PREFIX = 'avp_watch_post_';
    const DEFAULT_MINUTES = 180;

    public function __construct() {
        add_action('wp_ajax_avp_get_watch_status', array($this, 'get_watch_status'));
        add_action('wp_ajax_nopriv_avp_get_watch_status', array($this, 'get_watch_status'));
        add_action('wp_ajax_avp_update_watch_time', array($this, 'update_watch_time'));
        add_action('wp_ajax_nopriv_avp_update_watch_time', array($this, 'update_watch_time'));
        add_action('wp_ajax_avp_get_user_watch_time', array($this, 'get_user_watch_time'));
        add_action('wp_ajax_avp_reset_watch_time', array($this, 'reset_watch_time'));
        add_action('wp_ajax_avp_get_learndash_courses', array($this, 'get_learndash_courses'));
        add_action('wp_ajax_avp_get_course_users', array($this, 'get_course_users'));
        add_action('wp_ajax_avp_reset_course_watch_time', array($this, 'reset_course_watch_time'));
        add_action('wp_ajax_avp_get_watch_users', array($this, 'get_watch_users'));
        add_action('wp_ajax_avp_get_watch_records', array($this, 'get_watch_records'));
        add_action('wp_ajax_avp_update_default_watch_limit', array($this, 'update_default_watch_limit'));
    }

    public function get_watch_status() {
        check_ajax_referer('avp-nonce', 'nonce');

        $raw_context = isset($_POST['context']) ? $_POST['context'] : array();
        $context = $this->parse_single_context($raw_context);
        $context_key = $this->get_context_key($context);
        $post_id = $this->resolve_context_post_id($context);
        $limit_post_id = $this->resolve_context_limit_post_id($context);

        $user_id = get_current_user_id();
        if (!$user_id) {
            $payload = array(
                'global' => $this->build_watch_response(0, 0),
                'context' => null,
                'contextKey' => $context_key,
            );

            if ($post_id > 0) {
                $context_status = $this->build_watch_response(0, 0, $post_id);
                $context_status['key'] = $context_key;
                $context_status['postId'] = $post_id;
                if (isset($context['lessonId'])) {
                    $context_status['lessonId'] = $context['lessonId'];
                }
                if (isset($context['courseId'])) {
                    $context_status['courseId'] = $context['courseId'];
                }
                $payload['context'] = $context_status;
            }

            wp_send_json_success($payload);
            return;
        }

        $global_limit = $this->get_user_limit_seconds($user_id);
        $data = array(
            'global' => $this->build_watch_response($user_id, $global_limit),
            'context' => null,
            'contextKey' => $context_key,
        );

        if ($post_id > 0) {
            $context_limit = $this->get_user_limit_seconds($user_id, $limit_post_id);
            $context_status = $this->build_watch_response($user_id, $context_limit, $post_id);
            $context_status['key'] = $context_key;
            $context_status['postId'] = $post_id;
            if (isset($context['lessonId'])) {
                $context_status['lessonId'] = $context['lessonId'];
            }
            if (isset($context['courseId'])) {
                $context_status['courseId'] = $context['courseId'];
            }
            $data['context'] = $context_status;
        }

        wp_send_json_success($data);
    }

    public function update_watch_time() {
        check_ajax_referer('avp-nonce', 'nonce');

        $user_id = get_current_user_id();
        if (!$user_id) {
            wp_send_json_error(array('message' => __('Usuario no autenticado', 'advanced-video-player')));
            return;
        }

        $seconds = isset($_POST['seconds']) ? intval($_POST['seconds']) : 0;
        $limit = $this->get_user_limit_seconds($user_id);

        $entries = $this->parse_watch_entries($_POST['entries'] ?? array());

        if ($seconds <= 0 && empty($entries)) {
            wp_send_json_success(array(
                'global' => $this->build_watch_response($user_id, $limit),
                'contexts' => array(),
            ));
            return;
        }

        $consumed = intval(get_user_meta($user_id, self::META_CONSUMED, true));
        $consumed = max(0, $consumed);

        $remaining_budget = $limit > 0 ? max($limit - $consumed, 0) : $seconds;
        if ($limit > 0 && $remaining_budget <= 0) {
            wp_send_json_success(array(
                'global' => $this->build_watch_response($user_id, $limit),
                'contexts' => array(),
            ));
            return;
        }

        $applied_total = 0;
        $context_updates = array();

        if (!empty($entries)) {
            foreach ($entries as $entry) {
                if ($limit > 0 && $remaining_budget <= 0) {
                    break;
                }

                $context = $entry['context'];
                $post_id = $this->resolve_context_post_id($context);
                $limit_post_id = $this->resolve_context_limit_post_id($context);
                $context_limit = $this->get_user_limit_seconds($user_id, $limit_post_id);
                $context_consumed = $this->get_context_consumed_seconds($user_id, $post_id);
                $context_remaining = $context_limit > 0 ? max($context_limit - $context_consumed, 0) : $entry['seconds'];

                $entry_cap = $entry['seconds'];
                if ($context_limit > 0) {
                    $entry_cap = min($entry_cap, $context_remaining);
                }
                if ($limit > 0) {
                    $entry_cap = min($entry_cap, $remaining_budget);
                }

                if ($entry_cap <= 0) {
                    continue;
                }

                if ($limit > 0) {
                    $remaining_budget -= $entry_cap;
                }

                $applied_total += $entry_cap;
                $this->update_context_usage($user_id, $entry_cap, $context);

                $context_key = $this->get_context_key($context);
                if ($context_key !== '') {
                    $updated_limit = $this->get_user_limit_seconds($user_id, $limit_post_id);
                    $context_updates[$context_key] = $this->build_context_status($user_id, $updated_limit, $post_id, $context_key, $context);
                }
            }
        }

        if ($limit > 0) {
            $payload_cap = min($seconds, max($limit - $consumed, 0));
            if ($payload_cap > $applied_total) {
                $applied_total = $payload_cap;
            }
        } else {
            if ($seconds > $applied_total) {
                $applied_total = $seconds;
            }
        }

        if ($applied_total > 0) {
            if ($limit > 0) {
                $new_consumed = min($limit, $consumed + $applied_total);
                update_user_meta($user_id, self::META_CONSUMED, $new_consumed);
            } else {
                update_user_meta($user_id, self::META_CONSUMED, $consumed + $applied_total);
            }
        }

        wp_send_json_success(array(
            'global' => $this->build_watch_response($user_id, $limit),
            'contexts' => array_values($context_updates),
        ));
    }

    public function get_user_watch_time() {
        check_ajax_referer('avp-admin-nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('No tienes permisos suficientes', 'advanced-video-player')));
            return;
        }

        $identifier = isset($_POST['user']) ? sanitize_text_field(wp_unslash($_POST['user'])) : '';
        $user = $this->find_user($identifier);

        if (!$user) {
            wp_send_json_error(array('message' => __('Usuario no encontrado', 'advanced-video-player')));
            return;
        }

        $limit = $this->get_user_limit_seconds($user->ID);
        $response = $this->build_watch_response($user->ID, $limit);
        $response['user'] = $this->format_user_data($user);

        wp_send_json_success($response);
    }

    public function reset_watch_time() {
        check_ajax_referer('avp-admin-nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('No tienes permisos suficientes', 'advanced-video-player')));
            return;
        }

        $identifier = isset($_POST['user']) ? sanitize_text_field(wp_unslash($_POST['user'])) : '';
        $user = $this->find_user($identifier);

        if (!$user) {
            wp_send_json_error(array('message' => __('Usuario no encontrado', 'advanced-video-player')));
            return;
        }

        update_user_meta($user->ID, self::META_CONSUMED, 0);
        $this->clear_user_watch_usage($user->ID);

        $limit = $this->get_user_limit_seconds($user->ID);
        $response = $this->build_watch_response($user->ID, $limit);
        $response['user'] = $this->format_user_data($user);

        wp_send_json_success($response);
    }

    public function get_learndash_courses() {
        check_ajax_referer('avp-admin-nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('No tienes permisos suficientes', 'advanced-video-player')));
            return;
        }

        if (!post_type_exists('sfwd-courses')) {
            wp_send_json_error(array('message' => __('LearnDash no está activo o no se encontraron cursos.', 'advanced-video-player')));
            return;
        }

        $courses = get_posts(array(
            'post_type'      => 'sfwd-courses',
            'numberposts'    => -1,
            'post_status'    => 'publish',
            'orderby'        => 'title',
            'order'          => 'ASC',
            'suppress_filters' => false,
        ));

        $data = array();

        if ($courses) {
            foreach ($courses as $course) {
                $data[] = array(
                    'id'    => (int) $course->ID,
                    'title' => get_the_title($course),
                );
            }
        }

        wp_send_json_success(array('courses' => $data));
    }

    public function get_course_users() {
        check_ajax_referer('avp-admin-nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('No tienes permisos suficientes', 'advanced-video-player')));
            return;
        }

        $course_id = isset($_POST['course_id']) ? absint($_POST['course_id']) : 0;

        if ($course_id <= 0) {
            wp_send_json_error(array('message' => __('Curso no válido', 'advanced-video-player')));
            return;
        }

        if (!post_type_exists('sfwd-courses')) {
            wp_send_json_error(array('message' => __('LearnDash no está activo o no se encontraron cursos.', 'advanced-video-player')));
            return;
        }

        $user_ids = $this->get_course_user_ids($course_id);
        $users = array();

        if ($user_ids) {
            $user_query = new WP_User_Query(array(
                'include' => $user_ids,
                'number'  => -1,
                'orderby' => 'display_name',
                'order'   => 'ASC',
            ));

            foreach ($user_query->get_results() as $user) {
                $users[] = $this->format_user_data($user);
            }
        }

        wp_send_json_success(array('users' => $users));
    }

    public function reset_course_watch_time() {
        check_ajax_referer('avp-admin-nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('No tienes permisos suficientes', 'advanced-video-player')));
            return;
        }

        $course_id = isset($_POST['course_id']) ? absint($_POST['course_id']) : 0;
        $user_ids  = isset($_POST['user_ids']) ? (array) $_POST['user_ids'] : array();

        if ($course_id <= 0) {
            wp_send_json_error(array('message' => __('Curso no válido', 'advanced-video-player')));
            return;
        }

        if (!post_type_exists('sfwd-courses')) {
            wp_send_json_error(array('message' => __('LearnDash no está activo o no se encontraron cursos.', 'advanced-video-player')));
            return;
        }

        $user_ids = array_filter(array_map('absint', $user_ids));

        if (empty($user_ids)) {
            $user_ids = $this->get_course_user_ids($course_id);
        }

        if (empty($user_ids)) {
            wp_send_json_success(array(
                'reset'   => 0,
                'message' => __('No se encontraron usuarios inscritos en el curso.', 'advanced-video-player'),
            ));
            return;
        }

        $user_ids = array_unique($user_ids);
        $reset_count = 0;

        foreach ($user_ids as $user_id) {
            if ($user_id > 0) {
                update_user_meta($user_id, self::META_CONSUMED, 0);
                $this->clear_user_watch_usage($user_id, $course_id);
                $reset_count++;
            }
        }

        wp_send_json_success(array(
            'reset'   => $reset_count,
            'message' => sprintf(_n('%d usuario ha sido reiniciado.', '%d usuarios han sido reiniciados.', $reset_count, 'advanced-video-player'), $reset_count),
        ));
    }

    private function get_user_limit_seconds($user_id, $post_id = 0) {
        $post_id = absint($post_id);

        if ($post_id > 0) {
            $post_minutes = 0;

            if (class_exists('AVP_Video_Meta')) {
                $post_minutes = AVP_Video_Meta::get_limit_minutes($post_id);
            } else {
                $meta_key = '_avp_video_watch_limit';
                $post_minutes = intval(get_post_meta($post_id, $meta_key, true));
            }

            if ($post_minutes > 0) {
                return $post_minutes * 60;
            }
        }

        $custom_limit = intval(get_user_meta($user_id, self::META_LIMIT, true));
        if ($custom_limit > 0) {
            return $custom_limit;
        }

        $settings = get_option('avp_settings', array());
        $minutes = isset($settings['default_watch_limit']) ? intval($settings['default_watch_limit']) : self::DEFAULT_MINUTES;
        if ($minutes < 0) {
            $minutes = 0;
        }

        return $minutes > 0 ? $minutes * 60 : 0;
    }

    private function build_watch_response($user_id, $limit_seconds, $post_id = 0) {
        $limit_seconds = max(0, intval($limit_seconds));

        if ($post_id > 0) {
            $consumed = $this->get_context_consumed_seconds($user_id, $post_id);
        } else {
            $consumed = intval(get_user_meta($user_id, self::META_CONSUMED, true));
        }

        $consumed = max(0, $consumed);

        if ($limit_seconds > 0) {
            $consumed = min($consumed, $limit_seconds);
            $remaining = max($limit_seconds - $consumed, 0);
        } else {
            $remaining = 0;
        }

        return array(
            'enforced' => $limit_seconds > 0,
            'limitSeconds' => $limit_seconds,
            'consumedSeconds' => $consumed,
            'remainingSeconds' => $remaining
        );
    }

    private function find_user($identifier) {
        if (empty($identifier)) {
            return null;
        }

        if (is_numeric($identifier)) {
            $user = get_user_by('id', intval($identifier));
            if ($user) {
                return $user;
            }
        }

        $user = get_user_by('login', $identifier);
        if ($user) {
            return $user;
        }

        if (is_email($identifier)) {
            $user = get_user_by('email', $identifier);
            if ($user) {
                return $user;
            }
        }

        return null;
    }

    private function format_user_data(WP_User $user) {
        return array(
            'id' => $user->ID,
            'displayName' => $user->display_name,
            'userLogin' => $user->user_login,
            'userEmail' => $user->user_email
        );
    }

    private function get_course_user_ids($course_id) {
        $course_id = absint($course_id);

        if ($course_id <= 0) {
            return array();
        }

        $user_ids = array();

        $meta_key = 'course_' . $course_id . '_access_from';

        $user_query = new WP_User_Query(array(
            'number'     => -1,
            'fields'     => 'ID',
            'meta_query' => array(
                array(
                    'key'     => $meta_key,
                    'compare' => 'EXISTS',
                ),
            ),
        ));

        if (!is_wp_error($user_query)) {
            $results = $user_query->get_results();
            if (!empty($results)) {
                $user_ids = array_map('intval', $results);
            }
        }

        if (empty($user_ids)) {
            $access_list = get_post_meta($course_id, 'course_access_list', true);
            if (!empty($access_list)) {
                $list = array_map('absint', array_filter(array_map('trim', explode(',', (string) $access_list))));
                if (!empty($list)) {
                    $user_ids = array_merge($user_ids, $list);
                }
            }
        }

        return array_unique(array_filter($user_ids));
    }

    public function get_watch_users() {
        check_ajax_referer('avp-admin-nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('No tienes permisos suficientes', 'advanced-video-player')));
            return;
        }

        $course_id = isset($_POST['course_id']) ? absint($_POST['course_id']) : 0;
        $search = isset($_POST['search']) ? sanitize_text_field(wp_unslash($_POST['search'])) : '';

        $args = array(
            'number' => 50,
            'orderby' => 'display_name',
            'order' => 'ASC',
            'fields' => array('ID', 'display_name', 'user_email')
        );

        if ($course_id > 0) {
            $user_ids = $this->get_course_user_ids($course_id);
            if (empty($user_ids)) {
                wp_send_json_success(array('users' => array()));
                return;
            }

            $args['include'] = $user_ids;
            $args['number'] = -1;
        } else {
            $args['meta_query'] = array(
                'relation' => 'OR',
                array(
                    'key' => self::META_CONSUMED,
                    'compare' => 'EXISTS',
                ),
                array(
                    'key' => self::META_LIMIT,
                    'compare' => 'EXISTS',
                ),
            );
        }

        if (!empty($search)) {
            $args['search'] = '*' . $search . '*';
            $args['search_columns'] = array('user_login', 'user_email', 'display_name');
        }

        $query = new WP_User_Query($args);
        $users = array();

        foreach ($query->get_results() as $user) {
            $users[] = array(
                'id' => $user->ID,
                'name' => $this->get_user_full_name($user),
                'email' => $user->user_email,
            );
        }

        wp_send_json_success(array('users' => $users));
    }

    public function get_watch_records() {
        check_ajax_referer('avp-admin-nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('No tienes permisos suficientes', 'advanced-video-player')));
            return;
        }

        $course_id = isset($_POST['course_id']) ? absint($_POST['course_id']) : 0;
        $user_id = isset($_POST['user_id']) ? absint($_POST['user_id']) : 0;

        if ($course_id <= 0 && $user_id <= 0) {
            wp_send_json_error(array('message' => __('Selecciona un curso o un usuario para continuar.', 'advanced-video-player')));
            return;
        }

        $users = array();

        if ($user_id > 0) {
            $user = get_user_by('id', $user_id);
            if ($user) {
                $users[] = $user;
            }
        } elseif ($course_id > 0) {
            $user_ids = $this->get_course_user_ids($course_id);
            if (!empty($user_ids)) {
                $user_query = new WP_User_Query(array(
                    'include' => $user_ids,
                    'number'  => -1,
                    'orderby' => 'display_name',
                    'order'   => 'ASC',
                ));

                $users = $user_query->get_results();
            }
        }

        if (empty($users)) {
            wp_send_json_success(array('records' => array()));
            return;
        }

        $records = array();

        foreach ($users as $user) {
            $user_records = $this->collect_user_records($user, $course_id);

            if (empty($user_records)) {
                continue;
            }

            foreach ($user_records as $record) {
                $records[] = $record;
            }
        }

        wp_send_json_success(array('records' => $records));
    }

    public function update_default_watch_limit() {
        check_ajax_referer('avp-admin-nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('No tienes permisos suficientes', 'advanced-video-player')));
            return;
        }

        $minutes = isset($_POST['minutes']) ? max(0, intval($_POST['minutes'])) : 0;

        $settings = get_option('avp_settings', array());
        $settings['default_watch_limit'] = $minutes;
        update_option('avp_settings', $settings);

        wp_send_json_success(array(
            'minutes' => $minutes,
            'message' => __('El límite predeterminado se ha actualizado.', 'advanced-video-player'),
        ));
    }

    private function parse_watch_entries($raw_entries) {
        if (empty($raw_entries)) {
            return array();
        }

        if (is_string($raw_entries)) {
            $decoded = json_decode(wp_unslash($raw_entries), true);
        } else {
            $decoded = $raw_entries;
        }

        if (!is_array($decoded)) {
            return array();
        }

        $entries = array();

        foreach ($decoded as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $seconds = isset($entry['seconds']) ? intval($entry['seconds']) : 0;
            if ($seconds <= 0) {
                continue;
            }

            $context = array();
            if (isset($entry['context']) && is_array($entry['context'])) {
                foreach ($entry['context'] as $key => $value) {
                    if (in_array($key, array('postId', 'lessonId', 'courseId'), true)) {
                        $context[$key] = absint($value);
                    } elseif (in_array($key, array('lessonTitle', 'courseTitle'), true)) {
                        $context[$key] = sanitize_text_field(wp_unslash($value));
                    }
                }
            }

            $entries[] = array(
                'seconds' => $seconds,
                'context' => $context,
            );
        }

        return $entries;
    }

    private function parse_single_context($raw_context) {
        if (empty($raw_context)) {
            return array();
        }

        if (is_string($raw_context)) {
            $decoded = json_decode(wp_unslash($raw_context), true);
        } else {
            $decoded = $raw_context;
        }

        if (!is_array($decoded)) {
            return array();
        }

        $context = array();

        if (isset($decoded['postId'])) {
            $context['postId'] = absint($decoded['postId']);
        }

        if (isset($decoded['lessonId'])) {
            $context['lessonId'] = absint($decoded['lessonId']);
        }

        if (isset($decoded['courseId'])) {
            $context['courseId'] = absint($decoded['courseId']);
        }

        if (isset($decoded['lessonTitle'])) {
            $context['lessonTitle'] = sanitize_text_field(wp_unslash($decoded['lessonTitle']));
        }

        if (isset($decoded['courseTitle'])) {
            $context['courseTitle'] = sanitize_text_field(wp_unslash($decoded['courseTitle']));
        }

        return $context;
    }

    private function resolve_context_post_id($context) {
        if (isset($context['postId']) && $context['postId'] > 0) {
            return absint($context['postId']);
        }

        if (isset($context['lessonId']) && $context['lessonId'] > 0) {
            return absint($context['lessonId']);
        }

        return 0;
    }

    private function get_context_key($context) {
        if (isset($context['postId']) && $context['postId'] > 0) {
            return 'post-' . absint($context['postId']);
        }

        if (isset($context['lessonId']) && $context['lessonId'] > 0) {
            return 'lesson-' . absint($context['lessonId']);
        }

        if (isset($context['courseId']) && $context['courseId'] > 0) {
            return 'course-' . absint($context['courseId']);
        }

        return '';
    }

    private function resolve_context_limit_post_id($context) {
        $post_id = isset($context['postId']) ? absint($context['postId']) : 0;
        if ($post_id > 0) {
            $post_minutes = 0;

            if (class_exists('AVP_Video_Meta')) {
                $post_minutes = AVP_Video_Meta::get_limit_minutes($post_id);
            } else {
                $post_minutes = intval(get_post_meta($post_id, '_avp_video_watch_limit', true));
            }

            if ($post_minutes > 0) {
                return $post_id;
            }
        }

        $lesson_id = isset($context['lessonId']) ? absint($context['lessonId']) : 0;
        if ($lesson_id > 0) {
            $lesson_minutes = 0;

            if (class_exists('AVP_Video_Meta')) {
                $lesson_minutes = AVP_Video_Meta::get_limit_minutes($lesson_id);
            } else {
                $lesson_minutes = intval(get_post_meta($lesson_id, '_avp_video_watch_limit', true));
            }

            if ($lesson_minutes > 0) {
                return $lesson_id;
            }
        }

        if ($post_id > 0) {
            return $post_id;
        }

        if ($lesson_id > 0) {
            return $lesson_id;
        }

        return 0;
    }

    private function update_context_usage($user_id, $seconds, $context) {
        if ($seconds <= 0) {
            return;
        }

        $post_id = $this->resolve_context_post_id($context);

        if ($post_id <= 0) {
            return;
        }

        $key = self::META_POST_PREFIX . $post_id;
        $current = intval(get_user_meta($user_id, $key, true));
        $current = max(0, $current);

        update_user_meta($user_id, $key, $current + $seconds);
    }

    private function get_context_consumed_seconds($user_id, $post_id) {
        $user_id = absint($user_id);
        $post_id = absint($post_id);

        if ($user_id <= 0 || $post_id <= 0) {
            return 0;
        }

        $key = self::META_POST_PREFIX . $post_id;
        $value = get_user_meta($user_id, $key, true);

        return max(0, intval($value));
    }

    private function build_context_status($user_id, $limit_seconds, $post_id, $context_key, $context) {
        $status = $this->build_watch_response($user_id, $limit_seconds, $post_id);
        $status['key'] = $context_key;
        $status['postId'] = $post_id;

        if (isset($context['lessonId'])) {
            $status['lessonId'] = $context['lessonId'];
        }

        if (isset($context['courseId'])) {
            $status['courseId'] = $context['courseId'];
        }

        if (isset($context['lessonTitle'])) {
            $status['lessonTitle'] = $context['lessonTitle'];
        }

        if (isset($context['courseTitle'])) {
            $status['courseTitle'] = $context['courseTitle'];
        }

        return $status;
    }

    private function clear_user_watch_usage($user_id, $course_id = 0) {
        $all_meta = get_user_meta($user_id);
        if (empty($all_meta)) {
            return;
        }

        foreach ($all_meta as $key => $values) {
            if (strpos($key, self::META_POST_PREFIX) !== 0) {
                continue;
            }

            $post_id = intval(substr($key, strlen(self::META_POST_PREFIX)));
            if ($post_id <= 0) {
                continue;
            }

            if ($course_id > 0) {
                if (function_exists('learndash_get_course_id')) {
                    $post_course = (int) learndash_get_course_id($post_id);
                    if ($post_course !== $course_id) {
                        continue;
                    }
                } else {
                    continue;
                }
            }

            delete_user_meta($user_id, $key);
        }
    }

    private function collect_user_records(WP_User $user, $course_filter = 0) {
        $user_id = $user->ID;
        $meta = get_user_meta($user_id);
        $records = array();
        if (!empty($meta)) {
            foreach ($meta as $key => $values) {
                if (strpos($key, self::META_POST_PREFIX) !== 0) {
                    continue;
                }

                $post_id = intval(substr($key, strlen(self::META_POST_PREFIX)));
                if ($post_id <= 0) {
                    continue;
                }

                $seconds = isset($values[0]) ? intval($values[0]) : 0;
                if ($seconds <= 0) {
                    continue;
                }

                $course_id = 0;
                if (function_exists('learndash_get_course_id')) {
                    $course_id = (int) learndash_get_course_id($post_id);
                }

                if ($course_filter > 0 && $course_id !== $course_filter) {
                    continue;
                }

                $course_title = $course_id ? get_the_title($course_id) : '';
                $lesson_title = get_the_title($post_id);
                if (!$lesson_title) {
                    $post = get_post($post_id);
                    if (!$post) {
                        continue;
                    }
                    $lesson_title = $post->post_title;
                }

                $limit_seconds = $this->get_user_limit_seconds($user_id, $post_id);
                $limit_minutes = $limit_seconds > 0 ? (int) ceil($limit_seconds / 60) : 0;
                $consumed_minutes = (int) ceil($seconds / 60);

                $records[] = array(
                    'userId' => $user_id,
                    'userName' => $this->get_user_full_name($user),
                    'userEmail' => $user->user_email,
                    'courseId' => $course_id,
                    'courseTitle' => $course_title ? wp_strip_all_tags($course_title) : __('Sin curso', 'advanced-video-player'),
                    'lessonId' => $post_id,
                    'lessonTitle' => $lesson_title ? wp_strip_all_tags($lesson_title) : __('Sin datos', 'advanced-video-player'),
                    'consumedMinutes' => $consumed_minutes,
                    'limitMinutes' => $limit_minutes,
                    'visualized' => $this->format_visualized_value($consumed_minutes, $limit_minutes),
                );
            }
        }

        return $records;
    }

    private function get_user_full_name(WP_User $user) {
        $first = trim((string) get_user_meta($user->ID, 'first_name', true));
        $last = trim((string) get_user_meta($user->ID, 'last_name', true));
        $full = trim($first . ' ' . $last);

        if ($full === '') {
            $full = $user->display_name;
        }

        if ($full === '') {
            $full = $user->user_login;
        }

        return $full;
    }

    private function format_visualized_value($consumed_minutes, $limit_minutes) {
        if ($limit_minutes > 0) {
            return sprintf('%d/%d', $consumed_minutes, $limit_minutes);
        }

        return sprintf('%d/%s', $consumed_minutes, __('Sin límite', 'advanced-video-player'));
    }
}

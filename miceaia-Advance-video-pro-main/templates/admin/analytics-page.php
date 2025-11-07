?>
<div class="wrap avp-admin-wrapper">
    <div class="avp-admin-header">
        <h1><?php echo esc_html__('Analíticas del Reproductor', 'advanced-video-player'); ?></h1>
    </div>

    <?php
    if (!class_exists('AVP_Analytics')) {
        echo '<div class="notice notice-error"><p>' . esc_html__(
            'No se pudieron cargar las analíticas porque falta la clase AVP_Analytics. Revisa la instalación del plugin.',
            'advanced-video-player'
        ) . '</p></div>';
        return;
    }

    $plugin_instance = null;
    if ( class_exists('\\AVP\\Plugin') ) {
        $plugin_instance = \AVP\Plugin::instance();
    }
    $analytics = $plugin_instance ? $plugin_instance->get_analytics_instance() : null;

    if (!$analytics instanceof AVP_Analytics) {
        echo '<div class="notice notice-error"><p>' . esc_html__(
            'No se pudo inicializar el módulo de analíticas. Comprueba que todos los archivos del plugin estén presentes.',
            'advanced-video-player'
        ) . '</p></div>';
        return;
    }
    $stats = $analytics->get_stats_summary(7);
    ?>

    <div class="avp-stats-grid">
        <div class="avp-stat-card">
            <h3><?php echo esc_html__('Total de Reproducciones', 'advanced-video-player'); ?></h3>
            <div class="stat-value"><?php echo number_format($stats['total_plays']); ?></div>
            <p class="stat-subtitle"><?php echo esc_html__('Últimos 7 días', 'advanced-video-player'); ?></p>
        </div>

        <div class="avp-stat-card">
            <h3><?php echo esc_html__('Videos Completados', 'advanced-video-player'); ?></h3>
            <div class="stat-value"><?php echo number_format($stats['total_completes']); ?></div>
            <p class="stat-subtitle"><?php echo esc_html__('Últimos 7 días', 'advanced-video-player'); ?></p>
        </div>

        <div class="avp-stat-card">
            <h3><?php echo esc_html__('Tasa de Finalización', 'advanced-video-player'); ?></h3>
            <div class="stat-value">
                <?php 
                $completion_rate = $stats['total_plays'] > 0 
                    ? round(($stats['total_completes'] / $stats['total_plays']) * 100, 1) 
                    : 0;
                echo $completion_rate . '%';
                ?>
            </div>
            <p class="stat-subtitle"><?php echo esc_html__('Últimos 7 días', 'advanced-video-player'); ?></p>
        </div>
    </div>

    <div style="background: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); margin-top: 20px;">
        <h2><?php echo esc_html__('Eventos Recientes', 'advanced-video-player'); ?></h2>
        
        <?php
        $recent_events = $analytics->get_analytics_data(null, 7);
        
        if (!empty($recent_events)) {
            ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php echo esc_html__('Video ID', 'advanced-video-player'); ?></th>
                        <th><?php echo esc_html__('Evento', 'advanced-video-player'); ?></th>
                        <th><?php echo esc_html__('Duración', 'advanced-video-player'); ?></th>
                        <th><?php echo esc_html__('IP', 'advanced-video-player'); ?></th>
                        <th><?php echo esc_html__('Fecha', 'advanced-video-player'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (array_slice($recent_events, 0, 50) as $event): ?>
                        <tr>
                            <td><?php echo esc_html($event->video_id); ?></td>
                            <td>
                                <span class="event-badge event-<?php echo esc_attr($event->event_type); ?>">
                                    <?php echo esc_html(ucfirst($event->event_type)); ?>
                                </span>
                            </td>
                            <td><?php echo esc_html($event->duration); ?>s</td>
                            <td><?php echo esc_html($event->ip_address); ?></td>
                            <td><?php echo esc_html(mysql2date('d/m/Y H:i', $event->timestamp)); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php
        } else {
            echo '<p>' . esc_html__('No hay eventos registrados aún.', 'advanced-video-player') . '</p>';
        }
        ?>
    </div>

    <style>
        .stat-subtitle {
            margin-top: 5px;
            color: #666;
            font-size: 13px;
        }
        .event-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }
        .event-play {
            background: #d4edda;
            color: #155724;
        }
        .event-pause {
            background: #fff3cd;
            color: #856404;
        }
        .event-ended {
            background: #d1ecf1;
            color: #0c5460;
        }
        .event-error {
            background: #f8d7da;
            color: #721c24;
        }
        .event-load {
            background: #e2e3e5;
            color: #383d41;
        }
    </style>
</div>
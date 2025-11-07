<?php
if (!defined('ABSPATH')) {
    exit;
}

$status   = class_exists('AVP_Control_Minutes_Bridge') ? AVP_Control_Minutes_Bridge::instance()->get_status() : array(
    'available' => false,
    'connected' => false,
    'details'   => array(),
);
$details  = is_array($status['details']) ? $status['details'] : array();
$plugin_url = 'https://github.com/miceaia/Control-Minutos';
$settings_url = isset($details['settings_url']) ? esc_url($details['settings_url']) : '';
$docs_url = isset($details['docs_url']) ? esc_url($details['docs_url']) : $plugin_url;
$badge_class = $status['connected'] ? 'avp-status-badge--connected' : 'avp-status-badge--disconnected';
$badge_label = $status['connected']
    ? __('Conexión activa con Control Minutos', 'advanced-video-player')
    : ($status['available'] ? __('Plugin detectado, finaliza la vinculación', 'advanced-video-player') : __('Plugin no detectado', 'advanced-video-player'));
?>

<div class="wrap avp-admin-wrap">
    <h1><?php _e('Integración con Control Minutos', 'advanced-video-player'); ?></h1>

    <div class="notice notice-info inline">
        <p>
            <?php
            printf(
                /* translators: %s: external plugin name */
                esc_html__(
                    'Este reproductor no incluye %s. Instala y activa el plugin por separado para habilitar el conteo de minutos.',
                    'advanced-video-player'
                ),
                '<strong>Control Minutos</strong>'
            );
            ?>
        </p>
    </div>

    <div class="avp-control-minutes-grid">
        <section class="avp-control-minutes-card">
            <h2><?php _e('Estado de conexión', 'advanced-video-player'); ?></h2>
            <p class="avp-status-badge <?php echo esc_attr($badge_class); ?>">
                <span class="dashicons <?php echo $status['connected'] ? 'dashicons-yes-alt' : 'dashicons-warning'; ?>"></span>
                <?php echo esc_html($badge_label); ?>
            </p>

            <?php if (!empty($details['message'])) : ?>
                <p class="description"><?php echo esc_html($details['message']); ?></p>
            <?php elseif (!$status['available']) : ?>
                <p class="description"><?php _e('Instala y activa el plugin Control Minutos para comenzar a sincronizar los consumos.', 'advanced-video-player'); ?></p>
            <?php else : ?>
                <p class="description"><?php _e('El reproductor ya puede enviar eventos. Completa la configuración dentro del plugin Control Minutos si necesitas límites o reportes personalizados.', 'advanced-video-player'); ?></p>
            <?php endif; ?>
        </section>

        <section class="avp-control-minutes-card">
            <h2><?php _e('Pasos para vincular', 'advanced-video-player'); ?></h2>
            <ol>
                <li><?php _e('Descarga el plugin Control Minutos desde el repositorio indicado y súbelo a tu instalación de WordPress.', 'advanced-video-player'); ?></li>
                <li><?php _e('Activa el plugin y accede a su panel para definir los límites, cursos y reglas de consumo.', 'advanced-video-player'); ?></li>
                <li><?php _e('Verifica en esta pantalla que el estado cambie a “Conectado”.', 'advanced-video-player'); ?></li>
            </ol>
            <p>
                <a class="button button-secondary" href="<?php echo esc_url($plugin_url); ?>" target="_blank" rel="noopener noreferrer">
                    <span class="dashicons dashicons-download"></span>
                    <?php _e('Abrir repositorio Control Minutos', 'advanced-video-player'); ?>
                </a>
            </p>
            <?php if ($settings_url) : ?>
                <p>
                    <a class="button button-primary" href="<?php echo $settings_url; ?>">
                        <span class="dashicons dashicons-admin-generic"></span>
                        <?php _e('Ir a la configuración del plugin', 'advanced-video-player'); ?>
                    </a>
                </p>
            <?php endif; ?>
        </section>

        <section class="avp-control-minutes-card">
            <h2><?php _e('Comprobaciones rápidas', 'advanced-video-player'); ?></h2>
            <ul class="avp-control-minutes-checklist">
                <li>
                    <?php if ($status['available']) : ?>
                        <span class="dashicons dashicons-yes"></span>
                        <?php _e('Plugin detectado por WordPress', 'advanced-video-player'); ?>
                    <?php else : ?>
                        <span class="dashicons dashicons-no"></span>
                        <?php _e('Plugin aún no instalado o inactivo', 'advanced-video-player'); ?>
                    <?php endif; ?>
                </li>
                <li>
                    <?php if ($status['connected']) : ?>
                        <span class="dashicons dashicons-yes"></span>
                        <?php _e('Eventos de reproducción habilitados', 'advanced-video-player'); ?>
                    <?php else : ?>
                        <span class="dashicons dashicons-no"></span>
                        <?php _e('A la espera de conexión para registrar minutos', 'advanced-video-player'); ?>
                    <?php endif; ?>
                </li>
                <?php if (!empty($details['last_sync'])) : ?>
                    <li>
                        <span class="dashicons dashicons-clock"></span>
                        <?php printf(__('Última sincronización: %s', 'advanced-video-player'), esc_html($details['last_sync'])); ?>
                    </li>
                <?php endif; ?>
            </ul>
            <p class="description">
                <?php _e('Si el estado no cambia después de activar el plugin, revisa que no haya bloqueos de seguridad (firewall, cachés) y vuelve a cargar esta página.', 'advanced-video-player'); ?>
            </p>
            <p>
                <a class="button" href="<?php echo esc_url(add_query_arg('avp-refresh', '1')); ?>">
                    <span class="dashicons dashicons-update"></span>
                    <?php _e('Volver a comprobar', 'advanced-video-player'); ?>
                </a>
                <?php if ($docs_url) : ?>
                    <a class="button" href="<?php echo $docs_url; ?>" target="_blank" rel="noopener noreferrer">
                        <span class="dashicons dashicons-media-document"></span>
                        <?php _e('Ver documentación', 'advanced-video-player'); ?>
                    </a>
                <?php endif; ?>
            </p>
        </section>
    </div>
</div>

<style>
.avp-control-minutes-grid {
    display: grid;
    gap: 24px;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    margin-top: 20px;
}

.avp-control-minutes-card {
    background: #fff;
    border: 1px solid #d5d8dc;
    border-radius: 8px;
    padding: 24px;
    box-shadow: 0 2px 6px rgba(15, 23, 42, 0.05);
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

.avp-control-minutes-card h2 {
    margin-top: 0;
}

.avp-control-minutes-card .dashicons {
    vertical-align: middle;
}

.avp-control-minutes-card ol,
.avp-control-minutes-card ul {
    margin-left: 18px;
}

.avp-control-minutes-checklist {
    list-style: none;
    margin: 0;
    padding: 0;
}

.avp-control-minutes-checklist li {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 8px;
}

.avp-control-minutes-checklist .dashicons {
    font-size: 18px;
}
</style>

# Advanced Video Player Pro - Bunny.net Edition

Plugin de WordPress para reproducir videos con integración completa de Bunny.net CDN.

## 🎥 Características

- ✅ **Integración completa con Bunny.net** - Accede a tu biblioteca de videos directamente desde WordPress
- ✅ **Múltiples formatos** - YouTube, Vimeo, MP4, WebM, HLS, DASH
- ✅ **Interfaz visual** - Selecciona videos desde una galería visual
- ✅ **Shortcodes fáciles** - Inserta videos con un solo click
- ✅ **Analytics integrado** - Seguimiento de reproducciones y eventos
- ✅ **Responsive** - Se adapta a cualquier pantalla
- ✅ **AB Loop** - Control de bucle entre dos puntos
- ✅ **Anuncios personalizados** - Sistema de anuncios pre/mid/post-roll

## 📦 Estructura de Archivos

```
advanced-video-player-pro/
│
├── advanced-video-player.php          # Archivo principal del plugin
│
├── includes/
│   ├── class-avp-admin.php           # Gestión del admin
│   ├── class-avp-shortcode.php       # Manejo de shortcodes
│   ├── class-avp-player.php          # Lógica del reproductor
│   ├── class-avp-ads.php             # Sistema de anuncios
│   ├── class-avp-analytics.php       # Analytics
│   └── class-avp-bunny.php           # Integración Bunny.net (NUEVO)
│
├── assets/
│   ├── css/
│   │   ├── avp-player.css            # Estilos del reproductor
│   │   └── avp-admin.css             # Estilos del admin
│   │
│   └── js/
│       ├── avp-player.js             # JavaScript del reproductor
│       ├── avp-admin.js              # JavaScript del admin (ACTUALIZADO)
│       └── avp-gutenberg-block.js    # Bloque de Gutenberg (opcional)
│
└── templates/
    └── admin/
        ├── main-page.php             # Página principal (ACTUALIZADA)
        ├── settings-page.php         # Configuración (ACTUALIZADA)
        ├── analytics-page.php        # Analytics
        └── bunny-library.php         # Biblioteca Bunny.net (NUEVA)
```

## 🚀 Instalación

### Método 1: Instalación Manual

1. **Descarga los archivos** y colócalos en tu servidor en:
   ```
   wp-content/plugins/advanced-video-player-pro/
   ```

2. **Estructura necesaria**:
   ```
   advanced-video-player-pro/
   ├── advanced-video-player.php
   ├── includes/
   │   ├── class-avp-admin.php
   │   ├── class-avp-shortcode.php
   │   ├── class-avp-player.php
   │   ├── class-avp-ads.php
   │   ├── class-avp-analytics.php
   │   └── class-avp-bunny.php
   ├── assets/
   │   ├── css/
   │   │   ├── avp-player.css
   │   │   └── avp-admin.css
   │   └── js/
   │       ├── avp-player.js
   │       └── avp-admin.js
   └── templates/
       └── admin/
           ├── main-page.php
           ├── settings-page.php
           └── analytics-page.php
   ```

3. **Activa el plugin** desde WordPress:
   - Ve a `Plugins > Plugins Instalados`
   - Busca "Advanced Video Player Pro"
   - Click en "Activar"

### Método 2: Instalación vía ZIP

1. **Comprime todos los archivos** en un ZIP
2. Ve a `Plugins > Añadir nuevo > Subir plugin`
3. Selecciona el archivo ZIP y haz click en "Instalar ahora"
4. Activa el plugin

## ⚙️ Configuración de Bunny.net

### Paso 1: Obtener credenciales de Bunny.net

1. **Inicia sesión** en [Bunny.net Dashboard](https://dash.bunny.net/)

2. **Obtén tu API Key**:
   - Ve a `Stream > API`
   - Copia tu API Key

3. **Obtén tu Library ID**:
   - Ve a `Stream > Video Libraries`
   - Selecciona tu librería
   - El Library ID aparece en la URL: `https://dash.bunny.net/stream/library/XXXXX`

4. **Obtén tu CDN Hostname**:
   - En tu Video Library, ve a la sección "Settings"
   - Busca "Video CDN Hostname"
   - Copia el hostname (ej: `vz-12345-678.b-cdn.net`)

### Paso 2: Configurar en WordPress

1. Ve a `Video Player > Settings` en tu panel de WordPress

2. Rellena los campos de **Bunny.net Configuration**:
   - **API Key**: Pega tu API Key
   - **Library ID**: Pega tu Library ID
   - **CDN Hostname**: Pega tu CDN hostname

3. Haz click en **"Test Connection"** para verificar que todo funcione

4. Si la conexión es exitosa, guarda la configuración

## 📝 Uso del Plugin

### Opción 1: Desde la interfaz visual

1. Ve a `Video Player > Add Video` en WordPress

2. Verás 4 pestañas:
   - **Bunny.net Library**: Tu biblioteca de videos
   - **YouTube**: Añadir videos de YouTube
   - **Vimeo**: Añadir videos de Vimeo
   - **Custom URL**: URLs personalizadas (MP4, HLS, DASH, etc.)

3. **Para Bunny.net**:
   - Tus videos aparecerán automáticamente
   - Usa el buscador para encontrar videos específicos
   - Filtra por colección si es necesario
   - Haz click en cualquier video

4. **Configura las opciones**:
   - Ajusta ancho y alto
   - Activa autoplay, loop, muted según necesites
   - Expande "Advanced Options" para más configuración

5. **Inserta el video**:
   - Click en "Insert into Post"
   - O copia el shortcode generado

### Opción 2: Shortcode manual

Puedes crear shortcodes manualmente:

```php
[avp_player src="https://tu-cdn.b-cdn.net/video-guid/playlist.m3u8" type="hls" width="100%" height="500px"]
```

### Parámetros del Shortcode

| Parámetro | Descripción | Ejemplo |
|-----------|-------------|---------|
| `src` | URL del video (requerido) | `"https://..."` |
| `type` | Tipo de video | `"hls"`, `"mp4"`, `"youtube"`, `"vimeo"`, `"dash"`, `"webm"` |
| `width` | Ancho del reproductor | `"100%"`, `"800px"` |
| `height` | Alto del reproductor | `"500px"`, `"56.25%"` |
| `autoplay` | Reproducción automática | `"true"`, `"false"` |
| `loop` | Bucle | `"true"`, `"false"` |
| `muted` | Silenciado | `"true"`, `"false"` |
| `controls` | Mostrar controles | `"true"`, `"false"` |
| `poster` | Imagen de portada | `"https://...jpg"` |
| `ab_loop` | Activar loop AB | `"true"`, `"false"` |

### Ejemplos de Uso

**Video básico de Bunny.net:**
```php
[avp_player src="https://vz-xxxxx.b-cdn.net/xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx/playlist.m3u8" type="hls"]
```

**Video con autoplay y sin sonido:**
```php
[avp_player src="..." autoplay="true" muted="true"]
```

**Video de YouTube:**
```php
[avp_player src="https://www.youtube.com/watch?v=dQw4w9WgXcQ" type="youtube"]
```

**Video personalizado con todas las opciones:**
```php
[avp_player
    src="https://example.com/video.mp4"
    type="mp4"
    width="100%"
    height="600px"
    poster="https://example.com/thumbnail.jpg"
    autoplay="false"
    loop="true"
    controls="true"
    ab_loop="true"]
```

## 🩺 Diagnóstico del entorno y requisitos

El plugin necesita **PHP 8.2 o superior** y **WordPress 6.2+**. Si el servidor todavía ejecuta versiones anteriores aparecerá un aviso y el plugin se desactivará automáticamente.

Para revisar rápidamente la configuración desde la terminal puedes utilizar el nuevo comando WP-CLI:

```bash
wp avp doctor
```

El informe mostrará:

- Versión actual de PHP y WordPress junto con el estado del requisito mínimo.
- Estado de las extensiones recomendadas (`curl`, `mbstring`, `intl`, `zip`, `gd`, `imagick`, `mysqli`, `opcache`).
- Listado de plugins y temas instalados con su estado activo/inactivo.
- Las últimas líneas de `wp-content/debug.log` (si está disponible) para detectar errores recientes.

> **Consejo:** Después de actualizar PHP recuerda reiniciar PHP-FPM/Apache y vaciar las cachés (WordPress, CDN o plugins de optimización) para que los cambios sean efectivos.

## 🎨 Personalización de Estilos

Puedes personalizar los estilos añadiendo CSS personalizado:

```css
/* Cambiar color de los controles */
.avp-player-wrapper .vjs-control-bar {
    background-color: rgba(0, 0, 0, 0.8);
}

/* Personalizar el botón de play */
.avp-player-wrapper .vjs-big-play-button {
    background-color: #ff0000;
    border-color: #ff0000;
}
```

## 📊 Analytics

El plugin registra automáticamente:
- Reproducciones (play)
- Videos completados (ended)
- Pausas
- Errores

Accede a las estadísticas en `Video Player > Analytics`

## 🔧 Solución de Problemas

### Los videos de Bunny.net no se cargan

1. **Verifica las credenciales**:
   - Ve a `Video Player > Settings`
   - Haz click en "Test Connection"
   - Asegúrate de que aparezca "Connection successful"

2. **Verifica los permisos de la API**:
   - Tu API Key debe tener permisos de lectura
   - Revisa en el dashboard de Bunny.net

3. **Verifica el CDN Hostname**:
   - Debe ser exactamente como aparece en Bunny.net
   - Sin `https://` al principio
   - Ejemplo correcto: `vz-12345-678.b-cdn.net`

### El reproductor no aparece

1. **Verifica que el shortcode esté correcto**
2. **Limpia la caché** de WordPress
3. **Desactiva otros plugins** de video temporalmente
4. **Revisa la consola del navegador** (F12) para errores

### Los videos no se reproducen

1. **Verifica la URL del video**:
   - Debe ser accesible públicamente
   - Para Bunny.net, debe terminar en `/playlist.m3u8`

2. **Verifica el tipo de video**:
   - HLS requiere el parámetro `type="hls"`
   - MP4 requiere `type="mp4"`

3. **Prueba en otro navegador**

## 📄 Requisitos

- WordPress 6.2 o superior
- PHP 8.2 o superior (recomendado 8.2+)
- Extensiones: curl, mbstring, intl, zip, gd, imagick, mysqli, opcache
- Cuenta activa de Bunny.net (para usar la integración)

## 🆘 Soporte

Para soporte, contacta a: [tu-email@ejemplo.com]

## 📜 Licencia

GPL v2 or later

## 🔄 Changelog

### Version 2.0.0
- ✅ Integración completa con Bunny.net
- ✅ Interfaz visual para seleccionar videos
- ✅ Búsqueda y filtrado de videos
- ✅ Test de conexión con Bunny.net
- ✅ Mejoras en la UI/UX del admin
- ✅ Generación automática de shortcodes

### Version 1.0.0
- Lanzamiento inicial

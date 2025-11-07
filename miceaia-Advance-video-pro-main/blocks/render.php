<?php
$src = isset( $attributes['src'] ) ? esc_url( $attributes['src'] ) : '';

if ( ! $src ) {
    echo '<div style="background: #fee; padding: 20px; border-radius: 8px; color: #c00; border: 1px solid #f00;">
        ⚠️ Advanced Video Player: Por favor, añade una URL de video
    </div>';
    return;
}

echo do_shortcode( '[avp_player src="' . esc_attr( $src ) . '"]' );
?>
<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Get all available image sizes for an attachment.
 *
 * @param int $attachment_id The attachment ID.
 * @return array An associative array of image sizes with URLs.
 */
function headlesswc_get_image_sizes( $attachment_id ) {
    $sizes = array();
    $image_sizes = get_intermediate_image_sizes();
    foreach ( $image_sizes as $size ) {
        $image_src = wp_get_attachment_image_src( $attachment_id, $size );
        if ( $image_src ) {
            $sizes[ $size ] = $image_src[0];
        }
    }
    $sizes['full'] = wp_get_attachment_url( $attachment_id );
    return $sizes;
}

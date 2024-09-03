<?php
/**
 * Get all products gallery images as special image object contained all sizes
 * @return object[]
 */
function headlesswc_get_gallery_images( $wc_product ) {
        $meta_data = headlesswc_get_meta_data( $wc_product );
        $gallery_images = array();
	foreach ( $wc_product->get_gallery_image_ids() as $image_id ) {
		$gallery_images
		[] = headlesswc_get_image_sizes( $image_id );
	}
	if ( ! empty( $meta_data['wpcvi_images'] ) ) {
		foreach ( explode( ',', $meta_data['wpcvi_images'] ) as $image_id ) {
			$gallery_images
			[] = headlesswc_get_image_sizes( $image_id );
		}
	}
    return $gallery_images;
}

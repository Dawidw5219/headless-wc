<?php
/**
 * Get all products meta data
 * @return string[]
 */
function headlesswc_get_meta_data( $wc_product ) {
    $meta_data = array();
	foreach ( $wc_product->get_meta_data() as $meta ) {
		$meta_data[ $meta->key ] = $meta->value;
	}
    return $meta_data;
}

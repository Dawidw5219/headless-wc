<?php
/**
 * Get all products attributes
 * @return string[]
 */
function headlesswc_get_attributes( $wc_product ): array {
    $attributes = [];
	foreach ( $wc_product->get_attributes() as $attribute ) {
		$attributes[] = $attribute->get_name();
	}
    return $attributes;
}

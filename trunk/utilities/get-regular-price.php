<?php
/**
 * Get the regular price of a product
 * @return string
 */
function headlesswc_get_regular_price( $wc_product ): string {
	if ( $wc_product->get_type() !== 'variable' ) {
		return $wc_product->get_regular_price();
	} else {
		return $wc_product->get_variation_sale_price( 'min', true );
	}
}

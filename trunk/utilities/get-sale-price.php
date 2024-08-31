<?php
/**
 * Get the sale price of a product
 * @return string
 */
function headlesswc_get_sale_price( $wc_product ): string {
	if ( $wc_product->get_type() !== 'variable' ) {
		return $wc_product->get_sale_price();
	} else {
		$sale_price = $wc_product->get_variation_sale_price( 'min', true );
		if ( $sale_price === headlesswc_get_regular_price( $wc_product ) ) {
			$sale_price = null;
		}
		return $sale_price;
	}
}

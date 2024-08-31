<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function headlesswc_redirect_after_order() {
	if ( is_wc_endpoint_url( 'order-received' ) ) {
		$order_id = isset( $GLOBALS['wp']->query_vars['order-received'] ) ? intval( $GLOBALS['wp']->query_vars['order-received'] ) : false;
		if ( ! $order_id ) {
			return;
		}
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}
		$redirect_url = $order->get_meta( 'redirect_url' );
		if ( ! empty( $redirect_url ) ) {
			// phpcs:disable
			wp_redirect( $redirect_url );
			exit;
		}
	}
}

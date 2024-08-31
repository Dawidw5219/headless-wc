<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}


if ( ! function_exists( 'nvl' ) ) {
	function nvl( $value, $default_value = null ) {
		return ! empty( $value ) ? $value : $default_value;
	}
}

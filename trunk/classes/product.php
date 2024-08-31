<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class HWC_Product {

    protected $wc_product;

    public function __construct( $wc_product ) {
        $this->wc_product = $wc_product;
    }

    public function get_base_data() {
        switch ( $this->wc_product->get_type() ) {
            case 'variable':
                $product = new HWC_Variable_Product( $this->wc_product );
                break;
            default:
                $product = new HWC_Simple_Product( $this->wc_product );
                break;
        }
        return $product->get_data();
    }

    public function get_detailed_data() {
        switch ( $this->wc_product->get_type() ) {
            case 'variable':
                $product = new HWC_Variable_Product_Detailed( $this->wc_product );
                break;
            default:
                $product = new HWC_Simple_Product_Detailed( $this->wc_product );
                break;
        }
        return $product->get_data();
    }
}

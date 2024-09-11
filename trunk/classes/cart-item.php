<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
class HWC_Cart_Item extends HWC_Product_Base {
    public int $quantity;
    public string $total;
    public string $tax;

    public function __construct( $cart_item ) {
        parent::__construct( $cart_item['data'] );
        $this->quantity = $cart_item['quantity'];
        $this->total = $cart_item['line_total'];
        $this->tax = $cart_item['line_tax'];
    }

    public function get_data(): array {
        $data = get_object_vars( $this );
        ksort( $data );
        return $data;
    }
}

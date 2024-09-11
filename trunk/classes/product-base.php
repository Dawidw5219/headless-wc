<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
class HWC_Product_Base {
    public int $id;
    public string $name;
    public string $slug;
    public int $quantity;
    public string $permalink;
    public string $currency;
    public string $price;
    public string $regular_price;
    public ?string $sale_price;
    public ?array $image;


    public function __construct( $wc_product ) {
        $this->id = $wc_product->get_id();
        $this->name = $wc_product->get_name();
        $this->slug = $wc_product->get_slug();
        $this->permalink = $wc_product->get_permalink();
        $this->currency = get_woocommerce_currency();
        $this->price = $wc_product->get_price();
        $this->regular_price = $wc_product->get_regular_price();
        $this->sale_price = $wc_product->get_sale_price();
        $this->image = headlesswc_get_image_sizes( $wc_product->get_image_id() );
    }

    public function get_data(): array {
        return get_object_vars( $this );
    }
}

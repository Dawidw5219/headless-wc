<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class HWC_Product {
    public bool $is_on_sale;
    public bool $is_virtual;
    public bool $is_featured;
    public bool $is_sold_individually;
    public int $id;
    public string $name;
    /** @var string Possible values: "simple", "variable", "grouped", "external" */
    public string $type;
    public string $slug;
    public string $permalink;
    public string $sku;
    public string $price;
    public string $regular_price;
    /** @var string Possible values: "onbackorder", "instock", "outofstock" */
    public string $stock_status;
    public ?string $sale_price;
    public ?string $sale_start_datetime;
    public ?string $sale_end_datetime;
    public ?array $short_description;
    /** @var string[] */
    public ?array $categories;
    /** @var string[] */
    public ?array $tags;
    /** @var string[] */
    public ?array $image;
    /** @var string[] */
    public ?array $stock;
    /**
     * Only if $type is "variable" $_variables prefixed params will be present
     */
    public ?string $variable_min_price = null;
    public ?string $variable_max_price = null;
    public ?array $variable_attributes = null;

    public function __construct( $wc_product ) {
        $this->name = $wc_product->get_name();
        $this->id = $wc_product->get_id();
        $this->type = $wc_product->get_type();
        $this->slug = get_post_field( 'post_name', $wc_product->get_id() );
        $this->permalink = get_permalink( $wc_product->get_id() );
        $this->sku = $wc_product->get_sku();
        $this->is_on_sale = $wc_product->is_on_sale();
        $this->is_virtual = $wc_product->is_virtual();
        $this->is_featured = $wc_product->is_featured();
        $this->is_sold_individually = $wc_product->is_sold_individually();
        $this->short_description = $wc_product->get_short_description() ? [
            'rendered' => nvl( wp_kses_post( $wc_product->get_short_description() ) ),
            'plain' => nvl( wp_strip_all_tags( $wc_product->get_short_description() ) ),
        ] : null;
        $this->categories = wp_get_post_terms( $wc_product->get_id(), 'product_cat', [ 'fields' => 'names' ] );
        $this->tags = wp_get_post_terms( $wc_product->get_id(), 'product_tag', [ 'fields' => 'names' ] );
        $this->image = headlesswc_get_image_sizes( $wc_product->get_image_id() );
        $this->price = sprintf( '%.2f', $wc_product->get_price( $wc_product ) );
        $this->regular_price = sprintf( '%.2f', headlesswc_get_regular_price( $wc_product ) );
        $this->sale_price = headlesswc_get_sale_price( $wc_product ) ? sprintf( '%.2f', headlesswc_get_sale_price( $wc_product ) ) : null;
        $this->sale_start_datetime = $wc_product->get_date_on_sale_from() ? $wc_product->get_date_on_sale_from()->format( 'c' ) : null;
        $this->sale_end_datetime = $wc_product->get_date_on_sale_to() ? $wc_product->get_date_on_sale_to()->format( 'c' ) : null;
        $this->stock_status = $wc_product->get_stock_status();
        $this->stock = $wc_product->managing_stock() ? [
            'quantity' => $wc_product->get_stock_quantity(),
            'low_stock_amount' => nvl( get_post_meta( $wc_product->get_id(), '_low_stock_amount', true ) ),
            'backorders_status' => $wc_product->get_backorders(),
        ] : null;
        ////////////////////////////////////////////////////////////////////////////////////
        if ( $wc_product->get_type() === 'variable' ) {
			$this->variable_min_price = $wc_product->get_variation_price( 'min', true );
			$this->variable_max_price = $wc_product->get_variation_price( 'max', true );
			$this->variable_attributes = headlesswc_get_attributes( $wc_product );
		}
        ////////////////////////////////////////////////////////////////////////////////////
    }

    public function get_data(): array {
        $data = get_object_vars( $this );
        if ( $this->type !== 'variable' ) {
            unset( $data['variable_min_price'], $data['variable_max_price'], $data['variable_attributes'] );
        }
        return $data;
    }
}

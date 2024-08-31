<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class HWC_Content {
    public string $rendered;
    public string $plain;
}

class HWC_Simple_Product_Detailed extends HWC_Simple_Product {
    public string $weight_unit;
    public string $dimension_unit;
    public ?float $width = null;
    public ?float $length = null;
    public ?float $height = null;
    public ?float $weight = null;
    public array $gallery_images = [];
    public array $upsell_ids = [];
    public array $cross_sell_ids = [];
    public array $meta_data = [];
    public ?HWC_Content $content;

    public function __construct( $wc_product ) {
        parent::__construct( $wc_product );
        if ( is_string( $wc_product->get_description ) ) {
            $this->content = new HWC_Content(
                wp_kses_post( $wc_product->get_description ),
                wp_strip_all_tags( $wc_product->get_description )
            );
        } else {
            $this->content = null;
        }
        $this->weight_unit = get_option( 'woocommerce_weight_unit' );
        $this->dimension_unit = get_option( 'woocommerce_dimension_unit' );
        $this->width = nvl( $wc_product->get_width() );
        $this->length = nvl( $wc_product->get_length() );
        $this->height = nvl( $wc_product->get_height() );
        $this->weight = nvl( $wc_product->get_weight() );
        $this->gallery_images = $this->get_gallery_images();
        $this->upsell_ids = $wc_product->get_upsell_ids();
        $this->cross_sell_ids = $wc_product->get_cross_sell_ids();
        $this->meta_data = $this->get_meta_data();
        // 'product_meta' => get_post_meta($wc_product->get_id()),
        //'allData' => $wc_product->get_data(),
    }

    public function get_data(): array {
        return get_object_vars( $this );
    }

    protected function get_meta_data() {
        $meta_data = array();
        foreach ( $this->wc_product->get_meta_data() as $meta ) {
            $meta_data[ $meta->key ] = $meta->value;
        }
        return $meta_data;
    }

    protected function get_gallery_images() {
        $meta_data = $this->get_meta_data();
        $gallery_images = array();
        foreach ( $this->wc_product->get_gallery_image_ids() as $image_id ) {
            $gallery_images
			[] = headlesswc_get_image_sizes( $image_id );
        }
        if ( ! empty( $meta_data['wpcvi_images'] ) ) {
            foreach ( explode( ',', $meta_data['wpcvi_images'] ) as $image_id ) {
                $gallery_images
				[] = headlesswc_get_image_sizes( $image_id );
            }
        }
        return $gallery_images;
    }
}

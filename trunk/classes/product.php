<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class HWC_Product_Base {
    public int $id;
    public string $name;
    public string $slug;
    public string $permalink;
    public string $currency;
    public string $price;
    public string $regularPrice;
    public ?string $salePrice;
    public ?array $image;

    public function __construct( $wc_product ) {
        $this->id = $wc_product->get_id();
        $this->name = $wc_product->get_name();
        $this->slug = $wc_product->get_slug();
        $this->permalink = $wc_product->get_permalink();
        $this->currency = get_woocommerce_currency();
        $this->price = sprintf( '%.2f', $wc_product->get_price( $wc_product ) );
        $this->regularPrice = sprintf( '%.2f', headlesswc_get_regular_price( $wc_product ) );
        $this->salePrice = headlesswc_get_sale_price( $wc_product ) ? sprintf( '%.2f', headlesswc_get_sale_price( $wc_product ) ) : null;
        $this->image = headlesswc_get_image_sizes( $wc_product->get_image_id() );
    }

    public function get_data(): array {
        return get_object_vars( $this );
    }
}


class HWC_Product extends HWC_Product_Base {
    public bool $isOnSale;
    public bool $isVirtual;
    public bool $isFeatured;
    public bool $isSoldIndividually;
    public ?int $stockQuantity;
    public ?int $variationId = null;
    /** @var string Possible values: "simple", "variable", "grouped", "external" */
    public string $type;
    /** @var string Possible values: "onbackorder", "instock", "outofstock" */
    public string $stockStatus;
    public ?string $sku;
    public ?string $globalUniqueId;
    public ?string $saleStartDatetime;
    public ?string $saleEndDatetime;
    /** @var string[] */
    public ?array $categories;
    /** @var string[] */
    public ?array $tags;
    /** @var string[] */
    public ?array $shortDescription = array(
        'rendered' => '',
        'plain' => '',
    );
    public ?array $content = null;
    public ?array $attributes = null;
    /**
     * Only if $type is "variable" $variations_ prefixed params will be present
     */
    public ?string $variationsMinPrice = null;
    public ?string $variationsMaxPrice = null;

    public function __construct( $wc_product ) {
        parent::__construct( $wc_product );
        $this->type = $wc_product->get_type();
        // $this->slug = get_post_field( 'post_name', $wc_product->get_id() );
        $this->sku = nvl( $wc_product->get_sku() );
        $this->globalUniqueId = nvl( $wc_product->get_global_unique_id() );
        $this->isOnSale = $wc_product->isOnSale();
        $this->isVirtual = $wc_product->isVirtual();
        $this->isFeatured = $wc_product->isFeatured();
        $this->isSoldIndividually = $wc_product->isSoldIndividually();
        $this->shortDescription = $wc_product->get_short_description() ? [
            'rendered' => wp_kses_post( $wc_product->get_short_description() ),
            'plain' => wp_strip_all_tags( $wc_product->get_short_description() ),
        ] : null;
        $this->categories = wp_get_post_terms( $wc_product->get_id(), 'product_cat', [ 'fields' => 'names' ] );
        $this->tags = wp_get_post_terms( $wc_product->get_id(), 'product_tag', [ 'fields' => 'names' ] );
        $this->saleStartDatetime = $wc_product->get_date_on_sale_from() ? $wc_product->get_date_on_sale_from()->format( 'c' ) : null;
        $this->saleEndDatetime = $wc_product->get_date_on_sale_to() ? $wc_product->get_date_on_sale_to()->format( 'c' ) : null;
        $this->stockStatus = $wc_product->get_stock_status();
        $this->stockQuantity = $wc_product->get_stock_quantity();
        $this->attributes = headlesswc_get_attributes_data( $wc_product );
        //// FOR VARIABLE PRODUCTS: ////
        if ( $wc_product->get_type() === 'variable' ) {
			$this->variationsMinPrice = $wc_product->get_variation_price( 'min', true );
			$this->variationsMaxPrice = $wc_product->get_variation_price( 'max', true );
		}
        if ( $this->type === 'variation' ) {
            $this->variationId = $wc_product->get_id();
            $this->content = [
                'rendered' => wp_kses_post( $wc_product->get_description() ),
                'plain' => wp_strip_all_tags( $wc_product->get_description() ),
            ];
        }
    }

    public function get_data(): array {
        $data = get_object_vars( $this );
        if ( $data['type'] !== 'variable' ) {
			unset( $data['variationsMinPrice'] );
            unset( $data['variationsMaxPrice'] );
		}
        if ( $data['type'] !== 'variation' ) {
            unset( $data['content'] );
        }
        if ( $data['type'] === 'variation' ) {
            unset( $data['attributes'] );
        }
         return [
            ...parent::get_data(),
            ...$data,
        ];
    }
}

<?php
if (!defined('ABSPATH')) {
    exit;
}

class HWC_Simple_Product
{
    protected $wc_product;

    public function __construct($wc_product)
    {
        $this->wc_product = $wc_product;
    }
    //abstract public function get_data();

    protected function get_regular_price()
    {
        if ($this->wc_product->get_type() != "variable") {
            return $this->wc_product->get_regular_price();
        } else {
            return $this->wc_product->get_variation_sale_price('min', true);
        }
    }

    protected function get_sale_price()
    {
        if ($this->wc_product->get_type() != "variable") {
            return $this->wc_product->get_sale_price();
        } else {
            $salePrice = $this->wc_product->get_variation_sale_price('min', true);
            if ($salePrice == $this->get_regular_price()) {
                $salePrice = null;
            }
            return $salePrice;
        }
    }

    public function get_base_data()
    {
        $wc_product = $this->wc_product;
        return [
            'name' => $wc_product->get_name(),
            'id' => $wc_product->get_id(),
            'type' => $wc_product->get_type(), // "simple" || "variable" || "grouped" || "external"
            'slug' => get_post_field('post_name', $wc_product->get_id()),
            'permalink' => get_permalink($wc_product->get_id()),
            'sku' => $wc_product->get_sku(),
            'is_on_sale' => $wc_product->is_on_sale(),
            'is_virtual' => $wc_product->is_virtual(),
            'is_featured' => $wc_product->is_featured(),
            'is_sold_individually' => $wc_product->is_sold_individually(),
            'short_description' => $wc_product->get_short_description() ? array(
                'rendered' => nvl(wp_kses_post($wc_product->get_short_description())),
                'plain' => nvl(wp_strip_all_tags($wc_product->get_short_description())),
            ) : null,
            'categories' => wp_get_post_terms($wc_product->get_id(), 'product_cat', array('fields' => 'names')),
            'tags' => wp_get_post_terms($wc_product->get_id(), 'product_tag', array('fields' => 'names')),
            'image' => headlesswc_get_image_sizes($wc_product->get_image_id()),
            'price' => sprintf('%.2f', $wc_product->get_price()),
            'regular_price' => sprintf('%.2f', $this->get_regular_price()),
            'sale_price' => $this->get_sale_price() ? sprintf('%.2f', $this->get_sale_price()) : null,
            'sale_start_datetime' => $wc_product->get_date_on_sale_from() ? $wc_product->get_date_on_sale_from()->format('c') : null,
            'sale_end_datetime' => $wc_product->get_date_on_sale_to() ? $wc_product->get_date_on_sale_to()->format('c') : null,
            //'tax_satus' => $wc_product->get_tax_status(),
            //'tax_class' => $wc_product->get_tax_class(),
            'stock_status' => $wc_product->get_stock_status(), //onbackorder || instock || outofstock,
            'stock' => $wc_product->managing_stock() ? array(
                'quantity' => $wc_product->get_stock_quantity(),
                'low_stock_amount' => nvl(get_post_meta($wc_product->get_id(), '_low_stock_amount', true)),
                'backorders_status' => $wc_product->get_backorders(),
            ) : null,
        ];
    }

    public function get_detailed_data()
    {
        $wc_product = $this->wc_product;

        return [
            ...$this->get_base_data(),
            'dimensions' => array(
                'width' => nvl($wc_product->get_width()),
                'length' => nvl($wc_product->get_length()),
                'height' => nvl($wc_product->get_height()),
                'weight' => nvl($wc_product->get_weight()),
                'weight_unit' => get_option('woocommerce_weight_unit'),
                'dimension_unit' => get_option('woocommerce_dimension_unit'),
            ),
            'content' => $wc_product->get_description ? array(
                'rendered' => wp_kses_post($wc_product->get_description),
                'plain' => wp_strip_all_tags($wc_product->get_description),
            ) : null,
            'gallery_images' => $this->get_gallery_images(),
            'upsell_ids' => $wc_product->get_upsell_ids(),
            'cross_sell_ids' => $wc_product->get_cross_sell_ids(),
            'meta_data' => $this->get_meta_data(),
            // 'product_meta' => get_post_meta($wc_product->get_id()),
            //'allData' => $wc_product->get_data(),
        ];
    }

    protected function get_meta_data()
    {
        $metaData = array();
        foreach ($this->wc_product->get_meta_data() as $meta) {
            $metaData[$meta->key] = $meta->value;
        }
        return $metaData;
    }

    protected function get_gallery_images()
    {
        $meta_data = $this->get_meta_data();
        $galleryImages = array();
        foreach ($this->wc_product->get_gallery_image_ids() as $image_id) {
            $galleryImages[] = headlesswc_get_image_sizes($image_id);
        }
        if (!empty($meta_data["wpcvi_images"])) {
            foreach (explode(",", $meta_data["wpcvi_images"]) as $image_id) {
                $galleryImages[] = headlesswc_get_image_sizes($image_id);
            }
        }
        return $galleryImages;
    }
}

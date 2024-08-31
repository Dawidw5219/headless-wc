<?php
class HWC_Variable_Product_Detailed extends HWC_Variable_Product {
    public array $attributes_data;
    public array $attributes_default_values;
    public array $variations;

    public function __construct( $wc_product ) {
		parent::__construct( $wc_product );
		$this->attributes_data = $this->get_attributes_data();
        $this->attributes_default_values = $wc_product->get_default_attributes();
        $this->variations = $this->get_variations();
    }

    protected function get_variations() {
        $variation_ids = $this->wc_product->get_children();
        foreach ( $variation_ids as $variation_id ) {
            $variation = wc_get_product( $variation_id );
            $product = new HWC_Simple_Product( $variation );
            $a = $variation->get_attributes();
            $variations[] = [
                'attributes' => $variation->get_attributes(),
                'product' => $product->get_data(),
                //'allData' => $variation->get_data(),
            ];
        }
        return $variations;
    }

    protected function get_attributes_data() {
        $attributes_data = [];
        $product = $this->wc_product;
        foreach ( $product->get_attributes() as $attribute ) {
            $attribute_name = $attribute->get_name();
            $attribute_slug = $attribute->get_taxonomy(); // Assuming taxonomy slug
            $attribute_description = ''; // Placeholder for description (custom meta or predefined text)
            // Check if the attribute is a taxonomy (e.g., color, size)
            if ( $attribute->is_taxonomy() ) {
                $taxonomy = $attribute->get_taxonomy(); // Get taxonomy name (e.g., pa_color)
                $term_ids = $attribute->get_options(); // Get term IDs

                $terms_with_meta = [];
                foreach ( $term_ids as $term_id ) {
                    $term = get_term( $term_id, $taxonomy );
                    if ( ! is_wp_error( $term ) ) {
                        $term_name = $term->name;
                        $term_slug = $term->slug;
                        $term_description = $term->description; // Assuming term description

                        $terms_with_meta[] = [
                            'name' => $term_name,
                            'slug' => $term_slug,
                            'description' => $term_description,
                            'meta' => get_term_meta( $term_id ), // Get all meta fields for the term
                        ];
                    }
                }
                usort(
                    $terms_with_meta,
                    function ( $a, $b ) {
                        return strcmp( $a['name'], $b['name'] );
                    }
                );
                $attributes_data[] = [
                    'name' => $attribute_name,
                    'slug' => $attribute_slug,
                    'description' => $attribute_description,
                    'values' => $terms_with_meta,
                ];
            } else {
                // For non-taxonomy (custom) attributes, directly use the options
                $attribute_values = $attribute->get_options();
                // Sort values alphabetically
                sort( $attribute_values );
                $attributes_data[] = [
                    'name' => $attribute_name,
                    'slug' => '', // Custom attributes typically don’t have slugs
                    'description' => '', // Custom attributes typically don’t have descriptions
                    'values' => array_map(
                        function ( $value ) {
                            return [ 'value' => $value ];
                        },
                        $attribute_values
                    ),
                ];
            }
        }
        return $attributes_data;
    }
}

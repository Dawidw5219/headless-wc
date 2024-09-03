<?php
/**
 * Get all products attributes data
 * @return object[]
 */
function headlesswc_get_attributes_data( $wc_product ) {
    $attributes_data = [];
    foreach ( $wc_product->get_attributes() as $attribute ) {
        if ( $attribute->is_taxonomy() ) {
            $taxonomy = $attribute->get_taxonomy();
            $term_ids = $attribute->get_options();

            // Get the taxonomy label to use as the attribute name
            $taxonomy_object = get_taxonomy( $taxonomy );
            $attribute_name = $taxonomy_object ? $taxonomy_object->labels->singular_name : $attribute->get_name();

            $terms_with_meta = [];
            foreach ( $term_ids as $term_id ) {
                $term = get_term( $term_id, $taxonomy );
                if ( ! is_wp_error( $term ) ) {
                    $term_name = $term->name;
                    $term_slug = $term->slug;

                    $terms_with_meta[] = [
                        'id' => $term_slug,
                        'name' => $term_name,
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
                'id' => $attribute->get_taxonomy(),
                'name' => $attribute_name,
                'values' => $terms_with_meta,
            ];
        } else {
            // For non-taxonomy (custom) attributes, directly use the options
            $attribute_values = $attribute->get_options();
            // Sort values alphabetically
            sort( $attribute_values );
            $attributes_data[] = [
                'id' => strtolower( rawurlencode( $attribute->get_name() ) ) . '-' . random_int( 10000, 99999 ),
                'name' => $attribute->get_name(),
                'values' => array_map(
                    function ( $value ) {
                        return [
                            'id' => strtolower( rawurlencode( $value ) ),
                            'name' => $value,
                        ];
                    },
                    $attribute_values
                ),
            ];
        }
    }
    return $attributes_data;
}

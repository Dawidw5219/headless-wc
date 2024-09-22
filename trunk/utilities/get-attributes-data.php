<?php
/**
 * Get all products attributes data
 * @return object[]
 */
function headlesswc_get_attributes_data( $wc_product ) {
    $attributes_data = [];
    $all_attributes = wc_get_attribute_taxonomies();
    foreach ( $wc_product->get_attributes() as $attribute ) {
        if ( is_string( $attribute ) ) {
            continue;
        }
        if ( $attribute->is_taxonomy() ) {
            $taxonomy = $attribute->get_taxonomy();
            $taxonomy_object = get_taxonomy( $taxonomy );
            $term_ids = $attribute->get_options();
            // Znajdź typ atrybutu
            $attribute_type = 'select';
            foreach ( $all_attributes as $attr ) {
                if ( 'pa_' . $attr->attribute_name === $taxonomy ) {
                    $attribute_type = $attr->attribute_type;
                    break;
                }
            }
            $attribute_values = [];
            foreach ( $term_ids as $term_id ) {
                $term = get_term( $term_id, $taxonomy );
                if ( ! is_wp_error( $term ) ) {
                    $attribute_value = [
                        'id' => $term->slug,
                        'name' => $term->name,
                        //'meta' => get_term_meta( $term_id ),
                    ];
                    if ( $attribute_type === 'image' ) {
                        $attribute_value['imageUrl'] = get_term_meta( $term_id, 'cfvsw_image', true );
                    }
                    if ( $attribute_type === 'color' ) {
                        $attribute_value['color'] = get_term_meta( $term_id, 'cfvsw_color', true );
                    }
                    $attribute_values[] = $attribute_value;
				}
			}
            usort(
                $attribute_values,
                function ( $a, $b ) {
                    return strcmp( $a['name'], $b['name'] );
                }
            );
            $attributes_data[] = [
                'id' => $attribute->get_taxonomy(),
                'name' => $taxonomy_object->labels->singular_name,
                'type' => $attribute_type, // select || image || color ||
                'isForVariations' => $attribute->get_variation(),
                'values' => $attribute_values,
            ];
		} else {
			$attribute_values = $attribute->get_options();
			sort( $attribute_values );
			$attributes_data[] = [
				'id' => $attribute->get_name(),
				'name' => $attribute->get_name(),
				'type' => 'select',
				'isForVariations' => $attribute->get_variation() ? 'true' : 'false',
				'values' => array_map(
                    function ( $value ) {
                        return [
                            'id' => $value,
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

<?php
/**
 * Get whitelisted products meta data
 * @param WC_Product $wc_product WooCommerce product object
 * @return array Filtered meta data array
 */
function headlesswc_get_meta_data( $wc_product ) {
    $meta_data = array();
    
    // Pobierz whitelistę meta tagów z ustawień
    $meta_whitelist = get_option( 'headlesswc_meta_whitelist', '' );
    $meta_whitelist = trim( $meta_whitelist );
    
    // Jeśli whitelist jest pusty, zwróć pustą tablicę (domyślne bezpieczne zachowanie)
    if ( empty( $meta_whitelist ) ) {
        return $meta_data;
    }
    
    // Sprawdź czy użytkownik wybrał opcję "*" (wszystkie meta tagi)
    if ( $meta_whitelist === '*' ) {
        // Zwróć wszystkie meta tagi - UWAGA: może zawierać wrażliwe dane!
        foreach ( $wc_product->get_meta_data() as $meta ) {
            $meta_data[ $meta->key ] = $meta->value;
        }
        return $meta_data;
    }
    
    // Przetwórz whitelistę - podziel po przecinkach i wyczyść białe znaki
    $allowed_keys = array_map( 'trim', explode( ',', $meta_whitelist ) );
    $allowed_keys = array_filter( $allowed_keys ); // Usuń puste wartości
    
    // Jeśli po przetworzeniu lista jest pusta, zwróć pustą tablicę
    if ( empty( $allowed_keys ) ) {
        return $meta_data;
    }
    
    // Filtruj meta tagi według whitelisty
    foreach ( $wc_product->get_meta_data() as $meta ) {
        if ( in_array( $meta->key, $allowed_keys, true ) ) {
            $meta_data[ $meta->key ] = $meta->value;
        }
    }
    
    return $meta_data;
}

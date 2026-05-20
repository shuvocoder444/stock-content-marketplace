<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// View count tracking
add_action( 'wp', 'scm_track_view' );
function scm_track_view() {
    if ( ! is_singular( 'stock_asset' ) ) return;
    $post_id = get_the_ID();
    $ip      = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '' ) );
    $key     = 'scm_viewed_' . $post_id . '_' . md5( $ip );
    if ( ! get_transient( $key ) ) {
        global $wpdb;
        $wpdb->insert( $wpdb->prefix . 'scm_view_logs', [
            'asset_id'  => $post_id,
            'user_id'   => get_current_user_id() ?: null,
            'ip_address'=> $ip,
            'viewed_at' => current_time( 'mysql' ),
        ] );
        $count = intval( get_post_meta( $post_id, 'scm_view_count', true ) );
        update_post_meta( $post_id, 'scm_view_count', $count + 1 );
        set_transient( $key, 1, HOUR_IN_SECONDS );
    }
}

// AJAX: check access status
add_action( 'wp_ajax_scm_check_access',        'scm_ajax_check_access' );
add_action( 'wp_ajax_nopriv_scm_check_access', 'scm_ajax_check_access' );
function scm_ajax_check_access() {
    check_ajax_referer( 'scm_nonce', 'nonce' );
    $asset_id = intval( $_POST['asset_id'] ?? 0 );
    $access   = scm_check_download_access( $asset_id );
    wp_send_json_success( [
        'allowed'     => $access['allowed'],
        'is_free'     => get_post_meta( $asset_id, 'scm_is_free', true ) === '1',
        'is_premium'  => get_post_meta( $asset_id, 'scm_is_premium', true ) === '1',
        'price'       => floatval( get_post_meta( $asset_id, 'scm_sale_price', true ) ?: get_post_meta( $asset_id, 'scm_regular_price', true ) ),
        'currency'    => get_post_meta( $asset_id, 'scm_currency', true ) ?: 'USD',
    ] );
}

// Helper: render price badge HTML
function scm_get_price_html( $asset_id ) {
    $meta      = scm_get_all_meta( $asset_id );
    $is_free   = $meta['is_free'] === '1';
    $reg_price = floatval( $meta['regular_price'] );
    $sale_price= floatval( $meta['sale_price'] );
    $symbol    = scm_currency_symbol( $meta['currency'] ?: 'USD' );

    if ( $is_free ) {
        return '<span class="scm-price-tag free">' . esc_html__( 'Free', 'scm' ) . '</span>';
    }

    if ( $sale_price && $sale_price < $reg_price ) {
        return '<span class="scm-price-tag sale"><del>' . esc_html( $symbol . number_format( $reg_price, 2 ) ) . '</del> ' . esc_html( $symbol . number_format( $sale_price, 2 ) ) . '</span>';
    }

    return '<span class="scm-price-tag premium">' . esc_html( $symbol . number_format( $reg_price, 2 ) ) . '</span>';
}

// Helper: get download button HTML
function scm_get_download_button_html( $asset_id ) {
    $access      = scm_check_download_access( $asset_id );
    $is_premium  = get_post_meta( $asset_id, 'scm_is_premium', true ) === '1';

    if ( $access['allowed'] ) {
        return '<button class="scm-btn scm-btn-primary scm-btn-download scm-btn-lg" data-id="' . esc_attr( $asset_id ) . '" data-premium="0">
            <span class="dashicons dashicons-download"></span> ' . esc_html__( 'Download Now', 'scm' ) . '</button>';
    }

    return '<button class="scm-btn scm-btn-premium scm-btn-lg scm-btn-unlock" data-id="' . esc_attr( $asset_id ) . '" data-premium="1">
        <span class="scm-crown-icon">&#9812;</span> ' . esc_html__( 'Unlock Download', 'scm' ) . '</button>';
}

<?php
if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'wp_ajax_scm_toggle_favorite', 'scm_ajax_toggle_favorite' );

function scm_ajax_toggle_favorite() {
    check_ajax_referer( 'scm_nonce', 'nonce' );

    if ( ! is_user_logged_in() ) {
        wp_send_json_error( [ 'message' => __( 'Please log in to save favorites.', 'scm' ) ] );
    }

    $asset_id = intval( $_POST['asset_id'] ?? 0 );
    $user_id  = get_current_user_id();

    if ( ! $asset_id ) wp_send_json_error();

    global $wpdb;

    $existing = $wpdb->get_var( $wpdb->prepare(
        "SELECT id FROM {$wpdb->prefix}scm_favorites WHERE user_id = %d AND asset_id = %d",
        $user_id, $asset_id
    ) );

    if ( $existing ) {
        $wpdb->delete( $wpdb->prefix . 'scm_favorites', [ 'user_id' => $user_id, 'asset_id' => $asset_id ] );
        wp_send_json_success( [ 'action' => 'removed', 'message' => __( 'Removed from favorites.', 'scm' ) ] );
    } else {
        $wpdb->insert( $wpdb->prefix . 'scm_favorites', [
            'user_id'    => $user_id,
            'asset_id'   => $asset_id,
            'created_at' => current_time( 'mysql' ),
        ] );
        wp_send_json_success( [ 'action' => 'added', 'message' => __( 'Added to favorites!', 'scm' ) ] );
    }
}

function scm_get_user_favorites( $user_id, $page = 1, $per_page = 24 ) {
    global $wpdb;
    $offset = ( $page - 1 ) * $per_page;

    return $wpdb->get_results( $wpdb->prepare(
        "SELECT asset_id FROM {$wpdb->prefix}scm_favorites WHERE user_id = %d ORDER BY created_at DESC LIMIT %d OFFSET %d",
        $user_id, $per_page, $offset
    ) );
}

function scm_is_favorited( $asset_id, $user_id = null ) {
    if ( ! $user_id ) $user_id = get_current_user_id();
    if ( ! $user_id ) return false;

    global $wpdb;
    return (bool) $wpdb->get_var( $wpdb->prepare(
        "SELECT id FROM {$wpdb->prefix}scm_favorites WHERE user_id = %d AND asset_id = %d",
        $user_id, $asset_id
    ) );
}

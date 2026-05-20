<?php
if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'wp_ajax_scm_create_collection',       'scm_ajax_create_collection' );
add_action( 'wp_ajax_scm_add_to_collection',        'scm_ajax_add_to_collection' );
add_action( 'wp_ajax_scm_remove_from_collection',   'scm_ajax_remove_from_collection' );
add_action( 'wp_ajax_scm_delete_collection',        'scm_ajax_delete_collection' );

function scm_ajax_create_collection() {
    check_ajax_referer( 'scm_nonce', 'nonce' );
    if ( ! is_user_logged_in() ) wp_send_json_error( [ 'message' => __( 'Login required.', 'scm' ) ] );

    global $wpdb;
    $name        = sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) );
    $description = sanitize_textarea_field( wp_unslash( $_POST['description'] ?? '' ) );
    $is_public   = intval( $_POST['is_public'] ?? 1 );

    if ( ! $name ) wp_send_json_error( [ 'message' => __( 'Collection name required.', 'scm' ) ] );

    $wpdb->insert( $wpdb->prefix . 'scm_collections', [
        'user_id'     => get_current_user_id(),
        'name'        => $name,
        'description' => $description,
        'is_public'   => $is_public,
        'created_at'  => current_time( 'mysql' ),
    ] );

    wp_send_json_success( [ 'id' => $wpdb->insert_id, 'name' => $name, 'message' => __( 'Collection created!', 'scm' ) ] );
}

function scm_ajax_add_to_collection() {
    check_ajax_referer( 'scm_nonce', 'nonce' );
    if ( ! is_user_logged_in() ) wp_send_json_error( [ 'message' => __( 'Login required.', 'scm' ) ] );

    global $wpdb;
    $collection_id = intval( $_POST['collection_id'] ?? 0 );
    $asset_id      = intval( $_POST['asset_id'] ?? 0 );
    $user_id       = get_current_user_id();

    $collection = $wpdb->get_row( $wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}scm_collections WHERE id = %d AND user_id = %d",
        $collection_id, $user_id
    ) );

    if ( ! $collection ) wp_send_json_error( [ 'message' => __( 'Collection not found.', 'scm' ) ] );

    $exists = $wpdb->get_var( $wpdb->prepare(
        "SELECT id FROM {$wpdb->prefix}scm_collection_items WHERE collection_id = %d AND asset_id = %d",
        $collection_id, $asset_id
    ) );

    if ( ! $exists ) {
        $wpdb->insert( $wpdb->prefix . 'scm_collection_items', [
            'collection_id' => $collection_id,
            'asset_id'      => $asset_id,
            'added_at'      => current_time( 'mysql' ),
        ] );
    }

    wp_send_json_success( [ 'message' => __( 'Added to collection!', 'scm' ) ] );
}

function scm_ajax_remove_from_collection() {
    check_ajax_referer( 'scm_nonce', 'nonce' );
    if ( ! is_user_logged_in() ) wp_send_json_error();

    global $wpdb;
    $collection_id = intval( $_POST['collection_id'] ?? 0 );
    $asset_id      = intval( $_POST['asset_id'] ?? 0 );
    $user_id       = get_current_user_id();

    $collection = $wpdb->get_row( $wpdb->prepare(
        "SELECT id FROM {$wpdb->prefix}scm_collections WHERE id = %d AND user_id = %d",
        $collection_id, $user_id
    ) );

    if ( ! $collection ) wp_send_json_error();

    $wpdb->delete( $wpdb->prefix . 'scm_collection_items', [
        'collection_id' => $collection_id,
        'asset_id'      => $asset_id,
    ] );

    wp_send_json_success( [ 'message' => __( 'Removed from collection.', 'scm' ) ] );
}

function scm_ajax_delete_collection() {
    check_ajax_referer( 'scm_nonce', 'nonce' );
    if ( ! is_user_logged_in() ) wp_send_json_error();

    global $wpdb;
    $collection_id = intval( $_POST['collection_id'] ?? 0 );
    $user_id       = get_current_user_id();

    $wpdb->delete( $wpdb->prefix . 'scm_collections',     [ 'id' => $collection_id, 'user_id' => $user_id ] );
    $wpdb->delete( $wpdb->prefix . 'scm_collection_items',[ 'collection_id' => $collection_id ] );

    wp_send_json_success( [ 'message' => __( 'Collection deleted.', 'scm' ) ] );
}

function scm_get_user_collections( $user_id ) {
    global $wpdb;
    return $wpdb->get_results( $wpdb->prepare(
        "SELECT c.*, COUNT(ci.id) AS item_count FROM {$wpdb->prefix}scm_collections c
         LEFT JOIN {$wpdb->prefix}scm_collection_items ci ON c.id = ci.collection_id
         WHERE c.user_id = %d GROUP BY c.id ORDER BY c.created_at DESC",
        $user_id
    ) );
}

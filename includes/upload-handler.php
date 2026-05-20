<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// Enqueue media uploader on asset edit screens
add_action( 'admin_enqueue_scripts', 'scm_enqueue_media_uploader' );
function scm_enqueue_media_uploader( $hook ) {
    if ( ! in_array( $hook, [ 'post.php', 'post-new.php' ] ) ) return;
    if ( get_post_type() !== 'stock_asset' ) return;
    wp_enqueue_media();
}

// AJAX: handle drag-and-drop upload from frontend
add_action( 'wp_ajax_scm_upload_file', 'scm_ajax_upload_file' );
function scm_ajax_upload_file() {
    check_ajax_referer( 'scm_nonce', 'nonce' );
    if ( ! current_user_can( 'upload_files' ) ) {
        wp_send_json_error( [ 'message' => __( 'Permission denied.', 'scm' ) ] );
    }

    if ( empty( $_FILES['file'] ) ) {
        wp_send_json_error( [ 'message' => __( 'No file received.', 'scm' ) ] );
    }

    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';

    $attachment_id = media_handle_upload( 'file', intval( $_POST['post_id'] ?? 0 ) );

    if ( is_wp_error( $attachment_id ) ) {
        wp_send_json_error( [ 'message' => $attachment_id->get_error_message() ] );
    }

    wp_send_json_success( [
        'id'       => $attachment_id,
        'url'      => wp_get_attachment_url( $attachment_id ),
        'thumb'    => wp_get_attachment_image_url( $attachment_id, 'thumbnail' ),
        'filename' => basename( get_attached_file( $attachment_id ) ),
        'size'     => size_format( filesize( get_attached_file( $attachment_id ) ) ),
    ] );
}

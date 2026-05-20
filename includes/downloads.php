<?php
if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'wp_ajax_scm_download_asset',        'scm_handle_download' );
add_action( 'wp_ajax_nopriv_scm_download_asset', 'scm_handle_download_nopriv' );
add_action( 'init',                              'scm_process_secure_download' );

function scm_handle_download_nopriv() {
    $options = get_option( 'scm_settings', [] );
    if ( ! empty( $options['require_login'] ) ) {
        wp_send_json_error( [ 'message' => __( 'Please log in to download.', 'scm' ), 'redirect' => wp_login_url() ] );
    }
    scm_handle_download();
}

function scm_handle_download() {
    check_ajax_referer( 'scm_nonce', 'nonce' );
    $asset_id = intval( $_POST['asset_id'] ?? 0 );
    if ( ! $asset_id ) wp_send_json_error( [ 'message' => 'Invalid asset.' ] );

    $access = scm_check_download_access( $asset_id );
    if ( ! $access['allowed'] ) {
        wp_send_json_error( [ 'message' => $access['message'], 'require_purchase' => true, 'asset_id' => $asset_id ] );
    }

    $url = scm_generate_download_url( $asset_id );
    scm_log_download( $asset_id );

    wp_send_json_success( [ 'download_url' => $url ] );
}

function scm_check_download_access( $asset_id ) {
    $access_type = get_post_meta( $asset_id, 'scm_access_type', true );
    $is_free     = get_post_meta( $asset_id, 'scm_is_free', true );

    if ( $is_free === '1' || $access_type === 'free' || empty( $access_type ) ) {
        return [ 'allowed' => true, 'message' => '' ];
    }

    if ( ! is_user_logged_in() ) {
        return [ 'allowed' => false, 'message' => __( 'Please log in to download this asset.', 'scm' ) ];
    }

    $user_id = get_current_user_id();

    // Check purchase
    if ( scm_user_has_purchased( $user_id, $asset_id ) ) {
        return [ 'allowed' => true, 'message' => '' ];
    }

    // Check subscription
    if ( scm_user_has_subscription( $user_id ) ) {
        return [ 'allowed' => true, 'message' => '' ];
    }

    return [ 'allowed' => false, 'message' => __( 'Purchase or subscribe to download this premium asset.', 'scm' ) ];
}

function scm_user_has_purchased( $user_id, $asset_id ) {
    global $wpdb;
    return (bool) $wpdb->get_var( $wpdb->prepare(
        "SELECT id FROM {$wpdb->prefix}scm_purchases WHERE user_id = %d AND asset_id = %d",
        $user_id, $asset_id
    ) );
}

function scm_user_has_subscription( $user_id ) {
    // WooCommerce Subscriptions check
    if ( function_exists( 'wcs_user_has_subscription' ) ) {
        return wcs_user_has_subscription( $user_id, '', 'active' );
    }
    // Paid Memberships Pro check
    if ( function_exists( 'pmpro_hasMembershipLevel' ) ) {
        return pmpro_hasMembershipLevel( null, $user_id );
    }
    // MemberPress check
    if ( function_exists( 'mepr_get_authorized_data' ) ) {
        $memberships = MeprUtils::get_current_user_memberships();
        return ! empty( $memberships );
    }
    // Custom meta fallback
    return (bool) get_user_meta( $user_id, 'scm_has_subscription', true );
}

function scm_generate_download_url( $asset_id ) {
    // Priority: 1) local attached file, 2) download_file_url, 3) video_file_url, 4) thumbnail/preview url
    $file_id          = get_post_meta( $asset_id, 'scm_download_file_id', true );
    $file_url_direct  = get_post_meta( $asset_id, 'scm_download_file_url', true );
    $video_file_url   = get_post_meta( $asset_id, 'scm_video_file_url', true );
    $thumbnail_url    = get_post_meta( $asset_id, 'scm_thumbnail_url', true );

    // Determine best available URL
    $fallback_url = $file_url_direct ?: $video_file_url ?: $thumbnail_url ?: '';

    if ( ! $file_id && ! $fallback_url ) return '';

    $token = wp_hash( $asset_id . '_' . time() . '_' . get_current_user_id() );
    set_transient( 'scm_dl_' . $token, [
        'asset_id' => $asset_id,
        'file_id'  => $file_id,
        'file_url' => $fallback_url,
    ], 300 );

    return add_query_arg( [ 'scm_download' => '1', 'token' => $token ], home_url( '/' ) );
}

function scm_process_secure_download() {
    if ( ! isset( $_GET['scm_download'] ) || ! isset( $_GET['token'] ) ) return;

    $token = sanitize_text_field( wp_unslash( $_GET['token'] ) );
    $data  = get_transient( 'scm_dl_' . $token );

    if ( ! $data ) {
        wp_die( esc_html__( 'Download link expired or invalid.', 'scm' ) );
    }

    delete_transient( 'scm_dl_' . $token );

    $file_id  = intval( $data['file_id'] ?? 0 );
    $file_url = $file_id ? wp_get_attachment_url( $file_id ) : ( $data['file_url'] ?? '' );

    if ( ! $file_url ) {
        wp_die( esc_html__( 'File not found.', 'scm' ) );
    }

    // ── 1) Local WordPress attachment — serve directly ──
    if ( $file_id ) {
        $file_path = get_attached_file( $file_id );
        if ( $file_path && file_exists( $file_path ) ) {
            $filename = basename( $file_path );
            $mime     = mime_content_type( $file_path ) ?: 'application/octet-stream';
            nocache_headers();
            header( 'Content-Type: ' . $mime );
            header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
            header( 'Content-Length: ' . filesize( $file_path ) );
            header( 'Cache-Control: no-cache, no-store, must-revalidate' );
            readfile( $file_path );
            exit;
        }
    }

    // ── 2) External URL — stream through PHP so browser downloads, not previews ──
    $filename = basename( parse_url( $file_url, PHP_URL_PATH ) );
    if ( ! $filename ) $filename = 'download';

    // Use WP HTTP API to get the file
    $response = wp_remote_get( $file_url, [
        'timeout'   => 60,
        'sslverify' => false,
        'stream'    => false,  // load into memory for streaming
    ] );

    if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
        // Fallback: force download via JS-compatible redirect with content-disposition hint
        $ext  = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );
        $mime = 'application/octet-stream';
        nocache_headers();
        header( 'Content-Type: ' . $mime );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
        header( 'X-Accel-Redirect: ' . $file_url );
        // Last resort redirect
        wp_redirect( $file_url );
        exit;
    }

    $body         = wp_remote_retrieve_body( $response );
    $content_type = wp_remote_retrieve_header( $response, 'content-type' ) ?: 'application/octet-stream';
    // Strip charset from content-type for binary types
    $content_type = explode( ';', $content_type )[0];
    // Force binary download regardless of type
    nocache_headers();
    header( 'Content-Type: application/octet-stream' );
    header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
    header( 'Content-Length: ' . strlen( $body ) );
    header( 'Cache-Control: no-cache, no-store, must-revalidate' );
    echo $body;
    exit;
}

function scm_log_download( $asset_id ) {
    global $wpdb;
    $user_id = get_current_user_id();
    $ip      = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '' ) );

    $wpdb->insert( $wpdb->prefix . 'scm_download_logs', [
        'asset_id'      => $asset_id,
        'user_id'       => $user_id ?: null,
        'ip_address'    => $ip,
        'downloaded_at' => current_time( 'mysql' ),
    ] );

    $count = intval( get_post_meta( $asset_id, 'scm_download_count', true ) );
    update_post_meta( $asset_id, 'scm_download_count', $count + 1 );
}
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

    $asset_id = intval( $_POST['post_id'] ?? 0 );
    $attachment_id = media_handle_upload( 'file', $asset_id );

    if ( is_wp_error( $attachment_id ) ) {
        wp_send_json_error( [ 'message' => $attachment_id->get_error_message() ] );
    }

    $mime = get_post_mime_type( $attachment_id );
    if ( $asset_id && $mime && strpos( $mime, 'video/' ) === 0 ) {
        scm_generate_video_thumbnail_for_asset( $asset_id, $attachment_id );
    }

    wp_send_json_success( [
        'id'       => $attachment_id,
        'url'      => wp_get_attachment_url( $attachment_id ),
        'thumb'    => wp_get_attachment_image_url( $attachment_id, 'thumbnail' ),
        'filename' => basename( get_attached_file( $attachment_id ) ),
        'size'     => size_format( filesize( get_attached_file( $attachment_id ) ) ),
    ] );
}

function scm_get_ffmpeg_path() {
    static $ffmpeg = null;
    if ( $ffmpeg !== null ) {
        return $ffmpeg;
    }

    $command = DIRECTORY_SEPARATOR === '\\' ? 'where ffmpeg' : 'which ffmpeg';
    $output  = [];
    $return  = 1;
    exec( $command . ' 2>&1', $output, $return );
    if ( $return === 0 && ! empty( $output[0] ) ) {
        $ffmpeg = trim( $output[0] );
    } else {
        $ffmpeg = false;
    }
    return $ffmpeg;
}

function scm_generate_video_thumbnail_for_asset( $asset_id, $video_attachment_id ) {
    if ( ! $asset_id || ! $video_attachment_id ) {
        return false;
    }

    $mime = get_post_mime_type( $video_attachment_id );
    if ( ! $mime || strpos( $mime, 'video/' ) !== 0 ) {
        return false;
    }

    $video_path = get_attached_file( $video_attachment_id );
    if ( ! $video_path || ! file_exists( $video_path ) ) {
        return false;
    }

    $ffmpeg = scm_get_ffmpeg_path();
    if ( $ffmpeg ) {
        $upload_dir = wp_upload_dir();
        $thumb_path = $upload_dir['path'] . '/scm-video-thumb-' . $video_attachment_id . '-' . time() . '.jpg';
        $cmd = escapeshellarg( $ffmpeg ) . ' -y -i ' . escapeshellarg( $video_path ) . ' -ss 00:00:01 -vframes 1 -q:v 2 ' . escapeshellarg( $thumb_path ) . ' 2>&1';
        exec( $cmd, $output, $return );

        if ( $return === 0 && file_exists( $thumb_path ) ) {
            $file_array = [
                'name'     => 'scm-video-thumb-' . $video_attachment_id . '.jpg',
                'tmp_name' => $thumb_path,
            ];

            $thumb_id = media_handle_sideload( $file_array, $asset_id );
            if ( ! is_wp_error( $thumb_id ) ) {
                set_post_thumbnail( $asset_id, $thumb_id );
                update_post_meta( $asset_id, 'scm_thumbnail_url', wp_get_attachment_url( $thumb_id ) );
                return $thumb_id;
            }

            @unlink( $thumb_path );
        }
    }

    return scm_generate_video_placeholder_thumbnail( $asset_id, $video_attachment_id );
}

function scm_generate_video_placeholder_thumbnail( $asset_id, $video_attachment_id ) {
    if ( ! function_exists( 'imagecreatetruecolor' ) ) {
        return false;
    }

    $upload_dir = wp_upload_dir();
    $thumb_path = $upload_dir['path'] . '/scm-video-placeholder-' . $video_attachment_id . '-' . time() . '.jpg';
    $width  = 1280;
    $height = 720;

    $image = imagecreatetruecolor( $width, $height );
    if ( ! $image ) {
        return false;
    }

    $bg_color = imagecolorallocate( $image, 18, 18, 18 );
    $text_color = imagecolorallocate( $image, 255, 255, 255 );
    $accent = imagecolorallocate( $image, 112, 112, 255 );
    imagefilledrectangle( $image, 0, 0, $width, $height, $bg_color );
    imagefilledellipse( $image, $width / 2, $height / 2, 180, 180, $accent );

    $triangle = [
        $width * 0.45, $height * 0.4,
        $width * 0.65, $height * 0.5,
        $width * 0.45, $height * 0.6,
    ];
    imagefilledpolygon( $image, $triangle, 3, $text_color );

    $font_path = null;
    if ( function_exists( 'imagettftext' ) ) {
        $font_path = dirname( __FILE__ ) . '/../assets/fonts/arial.ttf';
        if ( ! file_exists( $font_path ) ) {
            $font_path = null;
        }
    }

    $label = 'Video Preview';
    if ( $font_path ) {
        imagettftext( $image, 24, 0, 40, $height - 40, $text_color, $font_path, $label );
    } else {
        imagestring( $image, 5, 40, $height - 60, $label, $text_color );
    }

    imagejpeg( $image, $thumb_path, 90 );
    imagedestroy( $image );

    if ( ! file_exists( $thumb_path ) ) {
        return false;
    }

    $file_array = [
        'name'     => 'scm-video-placeholder-' . $video_attachment_id . '.jpg',
        'tmp_name' => $thumb_path,
    ];

    $thumb_id = media_handle_sideload( $file_array, $asset_id );
    if ( is_wp_error( $thumb_id ) ) {
        @unlink( $thumb_path );
        return false;
    }

    set_post_thumbnail( $asset_id, $thumb_id );
    update_post_meta( $asset_id, 'scm_thumbnail_url', wp_get_attachment_url( $thumb_id ) );
    return $thumb_id;
}

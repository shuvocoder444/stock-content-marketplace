<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// Auto-link purchase when WC order completes
add_action( 'woocommerce_order_status_completed', 'scm_woo_order_completed' );
add_action( 'woocommerce_payment_complete',        'scm_woo_order_completed' );

function scm_woo_order_completed( $order_id ) {
    if ( ! function_exists( 'wc_get_order' ) ) return;

    $order = wc_get_order( $order_id );
    if ( ! $order ) return;

    $user_id = $order->get_user_id();
    if ( ! $user_id ) return;

    foreach ( $order->get_items() as $item ) {
        $product_id = $item->get_product_id();
        // Find assets linked to this WC product
        $assets = get_posts( [
            'post_type'   => 'stock_asset',
            'numberposts' => -1,
            'meta_key'    => 'scm_woo_product_id',
            'meta_value'  => $product_id,
        ] );

        foreach ( $assets as $asset ) {
            scm_grant_download_access( $user_id, $asset->ID, $order_id );
        }
    }
}

function scm_grant_download_access( $user_id, $asset_id, $order_id = 0 ) {
    global $wpdb;
    $exists = $wpdb->get_var( $wpdb->prepare(
        "SELECT id FROM {$wpdb->prefix}scm_purchases WHERE user_id=%d AND asset_id=%d",
        $user_id, $asset_id
    ) );
    if ( ! $exists ) {
        $wpdb->insert( $wpdb->prefix . 'scm_purchases', [
            'user_id'      => $user_id,
            'asset_id'     => $asset_id,
            'order_id'     => $order_id ?: null,
            'purchased_at' => current_time( 'mysql' ),
        ] );
    }
}

// AJAX: auto-create WooCommerce product for asset
add_action( 'wp_ajax_scm_create_woo_product', 'scm_ajax_create_woo_product' );
function scm_ajax_create_woo_product() {
    check_ajax_referer( 'scm_admin_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error();

    if ( ! function_exists( 'wc_create_product' ) && ! class_exists( 'WC_Product_Simple' ) ) {
        wp_send_json_error( [ 'message' => __( 'WooCommerce is not active.', 'scm' ) ] );
    }

    $asset_id = intval( $_POST['asset_id'] ?? 0 );
    $post     = get_post( $asset_id );
    if ( ! $post ) wp_send_json_error();

    $meta         = scm_get_all_meta( $asset_id );
    $regular_price= floatval( $meta['regular_price'] ) ?: 9.99;
    $sale_price   = floatval( $meta['sale_price'] );

    $product = new WC_Product_Simple();
    $product->set_name( $post->post_title );
    $product->set_status( 'publish' );
    $product->set_regular_price( $regular_price );
    if ( $sale_price ) $product->set_sale_price( $sale_price );
    $product->set_virtual( true );
    $product->set_downloadable( true );
    $product->set_description( $post->post_content );
    $thumb_id = get_post_thumbnail_id( $asset_id );
    if ( $thumb_id ) $product->set_image_id( $thumb_id );

    $product_id = $product->save();
    update_post_meta( $asset_id, 'scm_woo_product_id', $product_id );

    wp_send_json_success( [
        'product_id' => $product_id,
        'edit_url'   => get_edit_post_link( $product_id ),
        'message'    => sprintf( __( 'WooCommerce product #%d created!', 'scm' ), $product_id ),
    ] );
}

// AJAX: Add to cart
add_action( 'wp_ajax_scm_add_to_cart',        'scm_ajax_add_to_cart' );
add_action( 'wp_ajax_nopriv_scm_add_to_cart', 'scm_ajax_add_to_cart' );
function scm_ajax_add_to_cart() {
    check_ajax_referer( 'scm_nonce', 'nonce' );
    $asset_id   = intval( $_POST['asset_id'] ?? 0 );
    $product_id = intval( get_post_meta( $asset_id, 'scm_woo_product_id', true ) );

    if ( ! $product_id ) {
        wp_send_json_error( [ 'message' => __( 'No product linked. Please contact admin.', 'scm' ) ] );
    }

    if ( function_exists( 'WC' ) ) {
        WC()->cart->add_to_cart( $product_id );
        wp_send_json_success( [
            'message'      => __( 'Added to cart!', 'scm' ),
            'checkout_url' => wc_get_checkout_url(),
            'cart_url'     => wc_get_cart_url(),
        ] );
    } else {
        wp_send_json_error( [ 'message' => __( 'WooCommerce not available.', 'scm' ) ] );
    }
}

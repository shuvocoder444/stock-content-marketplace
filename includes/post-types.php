<?php
if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'init', 'scm_register_post_types' );

function scm_register_post_types() {
    $labels = [
        'name'               => __( 'Stock Assets', 'scm' ),
        'singular_name'      => __( 'Stock Asset', 'scm' ),
        'menu_name'          => __( 'Stock Assets', 'scm' ),
        'add_new'            => __( 'Add New', 'scm' ),
        'add_new_item'       => __( 'Add New Asset', 'scm' ),
        'edit_item'          => __( 'Edit Asset', 'scm' ),
        'new_item'           => __( 'New Asset', 'scm' ),
        'view_item'          => __( 'View Asset', 'scm' ),
        'search_items'       => __( 'Search Assets', 'scm' ),
        'not_found'          => __( 'No assets found', 'scm' ),
        'not_found_in_trash' => __( 'No assets found in Trash', 'scm' ),
    ];

    $args = [
        'labels'             => $labels,
        'public'             => true,
        'publicly_queryable' => true,
        'show_ui'            => true,
        'show_in_menu'       => true,
        'query_var'          => true,
        'rewrite'            => [ 'slug' => 'stock', 'with_front' => false ],
        'capability_type'    => 'post',
        'has_archive'        => 'stock',
        'hierarchical'       => false,
        'menu_position'      => 5,
        'menu_icon'          => 'dashicons-format-gallery',
        'supports'           => [ 'title', 'editor', 'thumbnail', 'excerpt', 'author', 'custom-fields' ],
        'show_in_rest'       => true,
    ];

    register_post_type( 'stock_asset', $args );
}

add_filter( 'manage_stock_asset_posts_columns', 'scm_stock_asset_columns' );
function scm_stock_asset_columns( $columns ) {
    $new_columns = [];
    foreach ( $columns as $key => $title ) {
        if ( $key === 'title' ) {
            $new_columns['image'] = __( 'Image', 'scm' );
        }
        $new_columns[ $key ] = $title;
    }
    return $new_columns;
}

add_action( 'manage_stock_asset_posts_custom_column', 'scm_stock_asset_custom_column', 10, 2 );
function scm_stock_asset_custom_column( $column, $post_id ) {
    if ( $column === 'image' ) {
        $thumb = get_the_post_thumbnail_url( $post_id, 'thumbnail' ) ?: get_post_meta( $post_id, 'scm_thumbnail_url', true );
        if ( $thumb ) {
            echo '<img src="' . esc_url( $thumb ) . '" style="max-width: 50px; max-height: 50px; border-radius: 4px; object-fit: cover;" alt="" />';
        } else {
            echo '<div style="width: 50px; height: 50px; background: #eee; border-radius: 4px; display: flex; align-items: center; justify-content: center; color: #aaa;"><span class="dashicons dashicons-format-image"></span></div>';
        }
    }
}

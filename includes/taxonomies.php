<?php
if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'init', 'scm_register_taxonomies' );

function scm_register_taxonomies() {

    // Asset Type (Hierarchical)
    register_taxonomy( 'asset_type', 'stock_asset', [
        'labels' => [
            'name'              => __( 'Asset Types', 'scm' ),
            'singular_name'     => __( 'Asset Type', 'scm' ),
            'search_items'      => __( 'Search Asset Types', 'scm' ),
            'all_items'         => __( 'All Asset Types', 'scm' ),
            'parent_item'       => __( 'Parent Asset Type', 'scm' ),
            'parent_item_colon' => __( 'Parent Asset Type:', 'scm' ),
            'edit_item'         => __( 'Edit Asset Type', 'scm' ),
            'update_item'       => __( 'Update Asset Type', 'scm' ),
            'add_new_item'      => __( 'Add New Asset Type', 'scm' ),
            'new_item_name'     => __( 'New Asset Type Name', 'scm' ),
            'menu_name'         => __( 'Asset Types', 'scm' ),
        ],
        'hierarchical'      => true,
        'show_ui'           => true,
        'show_admin_column' => true,
        'query_var'         => true,
        'rewrite'           => [ 'slug' => 'asset-type' ],
        'show_in_rest'      => true,
    ] );

    // Asset Category (Hierarchical)
    register_taxonomy( 'asset_category', 'stock_asset', [
        'labels' => [
            'name'              => __( 'Asset Categories', 'scm' ),
            'singular_name'     => __( 'Asset Category', 'scm' ),
            'search_items'      => __( 'Search Categories', 'scm' ),
            'all_items'         => __( 'All Categories', 'scm' ),
            'parent_item'       => __( 'Parent Category', 'scm' ),
            'parent_item_colon' => __( 'Parent Category:', 'scm' ),
            'edit_item'         => __( 'Edit Category', 'scm' ),
            'update_item'       => __( 'Update Category', 'scm' ),
            'add_new_item'      => __( 'Add New Category', 'scm' ),
            'new_item_name'     => __( 'New Category Name', 'scm' ),
            'menu_name'         => __( 'Categories', 'scm' ),
        ],
        'hierarchical'      => true,
        'show_ui'           => true,
        'show_admin_column' => true,
        'query_var'         => true,
        'rewrite'           => [ 'slug' => 'asset-category' ],
        'show_in_rest'      => true,
    ] );

    // Asset Tags (Non-hierarchical)
    register_taxonomy( 'asset_tag', 'stock_asset', [
        'labels' => [
            'name'                       => __( 'Asset Tags', 'scm' ),
            'singular_name'              => __( 'Asset Tag', 'scm' ),
            'search_items'               => __( 'Search Tags', 'scm' ),
            'popular_items'              => __( 'Popular Tags', 'scm' ),
            'all_items'                  => __( 'All Tags', 'scm' ),
            'edit_item'                  => __( 'Edit Tag', 'scm' ),
            'update_item'                => __( 'Update Tag', 'scm' ),
            'add_new_item'               => __( 'Add New Tag', 'scm' ),
            'new_item_name'              => __( 'New Tag Name', 'scm' ),
            'separate_items_with_commas' => __( 'Separate tags with commas', 'scm' ),
            'add_or_remove_items'        => __( 'Add or remove tags', 'scm' ),
            'choose_from_most_used'      => __( 'Choose from the most used tags', 'scm' ),
            'menu_name'                  => __( 'Tags', 'scm' ),
        ],
        'hierarchical'      => false,
        'show_ui'           => true,
        'show_admin_column' => true,
        'query_var'         => true,
        'rewrite'           => [ 'slug' => 'asset-tag' ],
        'show_in_rest'      => true,
    ] );

    // Seed default asset types on first run
    scm_seed_asset_types();
}

function scm_seed_asset_types() {
    if ( get_option( 'scm_types_seeded' ) ) return;
    $types = [
        'Photos', 'Videos', 'Mockups', 'PSDs', 'Icons',
        'Fonts', 'Vectors', 'Illustrations', 'Templates',
        'Sound Effects', 'Music', '3D Models', 'Other',
    ];
    foreach ( $types as $type ) {
        if ( ! term_exists( $type, 'asset_type' ) ) {
            wp_insert_term( $type, 'asset_type' );
        }
    }
    update_option( 'scm_types_seeded', 1 );
}

<?php
if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'rest_api_init', 'scm_register_rest_routes' );

function scm_register_rest_routes() {
    $namespace = 'scm/v1';

    register_rest_route( $namespace, '/assets', [
        'methods'             => WP_REST_Server::READABLE,
        'callback'            => 'scm_api_get_assets',
        'permission_callback' => '__return_true',
        'args'                => [
            'page'       => [ 'default' => 1, 'sanitize_callback' => 'absint' ],
            'per_page'   => [ 'default' => 24, 'sanitize_callback' => 'absint' ],
            'search'     => [ 'sanitize_callback' => 'sanitize_text_field' ],
            'asset_type' => [ 'sanitize_callback' => 'sanitize_text_field' ],
            'category'   => [ 'sanitize_callback' => 'sanitize_text_field' ],
            'orderby'    => [ 'default' => 'date', 'sanitize_callback' => 'sanitize_key' ],
        ],
    ] );

    register_rest_route( $namespace, '/assets/(?P<id>\d+)', [
        'methods'             => WP_REST_Server::READABLE,
        'callback'            => 'scm_api_get_asset',
        'permission_callback' => '__return_true',
    ] );

    register_rest_route( $namespace, '/categories', [
        'methods'             => WP_REST_Server::READABLE,
        'callback'            => 'scm_api_get_categories',
        'permission_callback' => '__return_true',
    ] );

    register_rest_route( $namespace, '/favorites', [
        'methods'             => WP_REST_Server::READABLE,
        'callback'            => 'scm_api_get_favorites',
        'permission_callback' => 'is_user_logged_in',
    ] );

    register_rest_route( $namespace, '/favorites/(?P<asset_id>\d+)', [
        'methods'             => WP_REST_Server::EDITABLE,
        'callback'            => 'scm_api_toggle_favorite',
        'permission_callback' => 'is_user_logged_in',
    ] );

    register_rest_route( $namespace, '/collections', [
        [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => 'scm_api_get_collections',
            'permission_callback' => 'is_user_logged_in',
        ],
        [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => 'scm_api_create_collection',
            'permission_callback' => 'is_user_logged_in',
        ],
    ] );

    register_rest_route( $namespace, '/downloads/(?P<asset_id>\d+)', [
        'methods'             => WP_REST_Server::READABLE,
        'callback'            => 'scm_api_get_download',
        'permission_callback' => 'is_user_logged_in',
    ] );
}

function scm_api_get_assets( WP_REST_Request $request ) {
    $args = scm_build_query_args( [
        'keyword'    => $request->get_param( 'search' ),
        'asset_type' => $request->get_param( 'asset_type' ),
        'category'   => $request->get_param( 'category' ),
        'orderby'    => $request->get_param( 'orderby' ),
        'paged'      => $request->get_param( 'page' ),
    ] );
    $args['posts_per_page'] = $request->get_param( 'per_page' );

    $query   = new WP_Query( $args );
    $assets  = [];

    while ( $query->have_posts() ) {
        $query->the_post();
        $assets[] = scm_format_asset_for_api( get_post() );
    }
    wp_reset_postdata();

    $response = new WP_REST_Response( $assets, 200 );
    $response->header( 'X-WP-Total', $query->found_posts );
    $response->header( 'X-WP-TotalPages', $query->max_num_pages );
    return $response;
}

function scm_api_get_asset( WP_REST_Request $request ) {
    $id   = $request->get_param( 'id' );
    $post = get_post( $id );
    if ( ! $post || $post->post_type !== 'stock_asset' ) {
        return new WP_Error( 'not_found', __( 'Asset not found.', 'scm' ), [ 'status' => 404 ] );
    }
    return new WP_REST_Response( scm_format_asset_for_api( $post ), 200 );
}

function scm_format_asset_for_api( $post ) {
    $meta = scm_get_all_meta( $post->ID );
    return [
        'id'             => $post->ID,
        'title'          => $post->post_title,
        'excerpt'        => wp_trim_words( $post->post_excerpt ?: $post->post_content, 20 ),
        'permalink'      => get_permalink( $post->ID ),
        'thumbnail'      => get_the_post_thumbnail_url( $post->ID, 'large' ) ?: get_post_meta( $post->ID, 'scm_thumbnail_url', true ),
        'asset_types'    => wp_get_object_terms( $post->ID, 'asset_type', [ 'fields' => 'names' ] ),
        'categories'     => wp_get_object_terms( $post->ID, 'asset_category', [ 'fields' => 'names' ] ),
        'tags'           => wp_get_object_terms( $post->ID, 'asset_tag', [ 'fields' => 'names' ] ),
        'is_free'        => $meta['is_free'] === '1',
        'is_premium'     => $meta['is_premium'] === '1',
        'price'          => floatval( $meta['sale_price'] ?: $meta['regular_price'] ),
        'regular_price'  => floatval( $meta['regular_price'] ),
        'currency'       => $meta['currency'],
        'file_format'    => $meta['file_format'],
        'file_size'      => $meta['file_size'],
        'resolution'     => $meta['resolution'],
        'license_type'   => $meta['license_type'],
        'download_count' => intval( get_post_meta( $post->ID, 'scm_download_count', true ) ),
        'view_count'     => intval( get_post_meta( $post->ID, 'scm_view_count', true ) ),
        'date'           => $post->post_date,
    ];
}

function scm_api_get_categories( WP_REST_Request $request ) {
    $terms = get_terms( [ 'taxonomy' => 'asset_category', 'hide_empty' => false ] );
    $data  = [];
    if ( ! is_wp_error( $terms ) ) {
        foreach ( $terms as $term ) {
            $data[] = [ 'id' => $term->term_id, 'name' => $term->name, 'slug' => $term->slug, 'parent' => $term->parent, 'count' => $term->count ];
        }
    }
    return new WP_REST_Response( $data, 200 );
}

function scm_api_get_favorites( WP_REST_Request $request ) {
    $favorites = scm_get_user_favorites( get_current_user_id() );
    $ids       = wp_list_pluck( $favorites, 'asset_id' );
    return new WP_REST_Response( $ids, 200 );
}

function scm_api_toggle_favorite( WP_REST_Request $request ) {
    $asset_id = $request->get_param( 'asset_id' );
    $user_id  = get_current_user_id();
    global $wpdb;

    $existing = $wpdb->get_var( $wpdb->prepare(
        "SELECT id FROM {$wpdb->prefix}scm_favorites WHERE user_id=%d AND asset_id=%d",
        $user_id, $asset_id
    ) );

    if ( $existing ) {
        $wpdb->delete( $wpdb->prefix . 'scm_favorites', [ 'user_id' => $user_id, 'asset_id' => $asset_id ] );
        return new WP_REST_Response( [ 'action' => 'removed' ], 200 );
    } else {
        $wpdb->insert( $wpdb->prefix . 'scm_favorites', [ 'user_id' => $user_id, 'asset_id' => $asset_id, 'created_at' => current_time( 'mysql' ) ] );
        return new WP_REST_Response( [ 'action' => 'added' ], 201 );
    }
}

function scm_api_get_collections( WP_REST_Request $request ) {
    $collections = scm_get_user_collections( get_current_user_id() );
    return new WP_REST_Response( $collections, 200 );
}

function scm_api_create_collection( WP_REST_Request $request ) {
    global $wpdb;
    $name = sanitize_text_field( $request->get_param( 'name' ) );
    if ( ! $name ) return new WP_Error( 'invalid', __( 'Name required.', 'scm' ), [ 'status' => 400 ] );

    $wpdb->insert( $wpdb->prefix . 'scm_collections', [
        'user_id'    => get_current_user_id(),
        'name'       => $name,
        'created_at' => current_time( 'mysql' ),
    ] );

    return new WP_REST_Response( [ 'id' => $wpdb->insert_id, 'name' => $name ], 201 );
}

function scm_api_get_download( WP_REST_Request $request ) {
    $asset_id = $request->get_param( 'asset_id' );
    $access   = scm_check_download_access( $asset_id );

    if ( ! $access['allowed'] ) {
        return new WP_Error( 'forbidden', $access['message'], [ 'status' => 403 ] );
    }

    $url = scm_generate_download_url( $asset_id );
    scm_log_download( $asset_id );

    return new WP_REST_Response( [ 'download_url' => $url ], 200 );
}

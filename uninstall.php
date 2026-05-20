<?php
/**
 * Uninstall script — runs when plugin is deleted from WP admin.
 */
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) exit;

global $wpdb;

// Drop custom tables
$tables = [
    $wpdb->prefix . 'scm_collections',
    $wpdb->prefix . 'scm_collection_items',
    $wpdb->prefix . 'scm_favorites',
    $wpdb->prefix . 'scm_download_logs',
    $wpdb->prefix . 'scm_view_logs',
    $wpdb->prefix . 'scm_purchases',
];
foreach ( $tables as $table ) {
    $wpdb->query( "DROP TABLE IF EXISTS `{$table}`" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
}

// Delete options
delete_option( 'scm_settings' );
delete_option( 'scm_types_seeded' );

// Optionally delete all stock_asset posts and meta
$delete_data = get_option( 'scm_delete_data_on_uninstall', false );
if ( $delete_data ) {
    $posts = get_posts( [ 'post_type' => 'stock_asset', 'numberposts' => -1, 'post_status' => 'any' ] );
    foreach ( $posts as $post ) {
        wp_delete_post( $post->ID, true );
    }
}

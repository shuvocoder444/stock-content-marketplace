<?php
/**
 * Plugin Name: Stock Content Marketplace
 * Plugin URI:  https://example.com/stock-content-marketplace
 * Description: A premium stock content marketplace inspired by Freepik/Magnific with photos, videos, mockups, fonts, and more.
 * Version:     1.0.0
 * Author:      Your Name
 * Author URI:  https://example.com
 * License:     GPL-2.0+
 * Text Domain: scm
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'SCM_VERSION',     '1.0.0' );
define( 'SCM_PLUGIN_DIR',  plugin_dir_path( __FILE__ ) );
define( 'SCM_PLUGIN_URL',  plugin_dir_url( __FILE__ ) );
define( 'SCM_PLUGIN_FILE', __FILE__ );

// Autoload includes
$includes = [
    'post-types',
    'taxonomies',
    'meta-boxes',
    'upload-handler',
    'downloads',
    'favorites',
    'collections',
    'search',
    'shortcodes',
    'api',
    'settings',
    'pricing',
    'woocommerce',
    'elementor',
    'dashboard',
    'seo',
    'import-export',
];
foreach ( $includes as $file ) {
    $path = SCM_PLUGIN_DIR . 'includes/' . $file . '.php';
    if ( file_exists( $path ) ) {
        require_once $path;
    }
}

register_activation_hook( __FILE__,   'scm_activate' );
register_deactivation_hook( __FILE__, 'scm_deactivate' );

function scm_activate() {
    scm_create_tables();
    scm_register_post_types();
    scm_register_taxonomies();
    flush_rewrite_rules();
}

function scm_deactivate() {
    flush_rewrite_rules();
}

function scm_create_tables() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();

    $sql = [];

    $sql[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}scm_collections (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id BIGINT(20) UNSIGNED NOT NULL,
        name VARCHAR(255) NOT NULL,
        description TEXT,
        is_public TINYINT(1) DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY user_id (user_id)
    ) $charset_collate;";

    $sql[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}scm_collection_items (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        collection_id BIGINT(20) UNSIGNED NOT NULL,
        asset_id BIGINT(20) UNSIGNED NOT NULL,
        added_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY collection_id (collection_id),
        KEY asset_id (asset_id)
    ) $charset_collate;";

    $sql[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}scm_favorites (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id BIGINT(20) UNSIGNED NOT NULL,
        asset_id BIGINT(20) UNSIGNED NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY user_asset (user_id, asset_id)
    ) $charset_collate;";

    $sql[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}scm_download_logs (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        asset_id BIGINT(20) UNSIGNED NOT NULL,
        user_id BIGINT(20) UNSIGNED,
        ip_address VARCHAR(45),
        downloaded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY asset_id (asset_id),
        KEY user_id (user_id)
    ) $charset_collate;";

    $sql[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}scm_view_logs (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        asset_id BIGINT(20) UNSIGNED NOT NULL,
        user_id BIGINT(20) UNSIGNED,
        ip_address VARCHAR(45),
        viewed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY asset_id (asset_id)
    ) $charset_collate;";

    $sql[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}scm_purchases (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id BIGINT(20) UNSIGNED NOT NULL,
        asset_id BIGINT(20) UNSIGNED NOT NULL,
        order_id BIGINT(20) UNSIGNED,
        purchased_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY user_id (user_id),
        KEY asset_id (asset_id)
    ) $charset_collate;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    foreach ( $sql as $query ) {
        dbDelta( $query );
    }
}

// Enqueue frontend assets
add_action( 'wp_enqueue_scripts', 'scm_enqueue_frontend' );
function scm_enqueue_frontend() {
    wp_enqueue_style( 'scm-frontend', SCM_PLUGIN_URL . 'assets/css/frontend.css', [], SCM_VERSION );
    wp_enqueue_script( 'scm-frontend', SCM_PLUGIN_URL . 'assets/js/frontend.js', [ 'jquery' ], SCM_VERSION, true );
    wp_localize_script( 'scm-frontend', 'scm_ajax', [
        'ajax_url' => admin_url( 'admin-ajax.php' ),
        'nonce'    => wp_create_nonce( 'scm_nonce' ),
        'is_user_logged_in' => is_user_logged_in() ? 1 : 0,
        'login_url' => wp_login_url( get_permalink() ),
    ] );
}

// Enqueue admin assets
add_action( 'admin_enqueue_scripts', 'scm_enqueue_admin' );
function scm_enqueue_admin( $hook ) {
    if ( strpos( $hook, 'scm' ) === false && get_post_type() !== 'stock_asset' && ! in_array( $hook, [ 'post.php', 'post-new.php' ] ) ) {
        // Still load on stock_asset screens
    }
    wp_enqueue_style( 'scm-admin', SCM_PLUGIN_URL . 'assets/css/admin.css', [], SCM_VERSION );
    wp_enqueue_script( 'scm-admin', SCM_PLUGIN_URL . 'assets/js/admin.js', [ 'jquery', 'jquery-ui-sortable' ], SCM_VERSION, true );
    wp_localize_script( 'scm-admin', 'scm_admin_ajax', [
        'ajax_url' => admin_url( 'admin-ajax.php' ),
        'nonce'    => wp_create_nonce( 'scm_admin_nonce' ),
    ] );
}

// Template loader
add_filter( 'template_include', 'scm_template_loader' );
function scm_template_loader( $template ) {
    if ( is_singular( 'stock_asset' ) ) {
        $custom = SCM_PLUGIN_DIR . 'templates/single-stock_asset.php';
        if ( file_exists( $custom ) ) return $custom;
    }
    if ( is_post_type_archive( 'stock_asset' ) || is_tax( [ 'asset_type', 'asset_category', 'asset_tag' ] ) ) {
        $custom = SCM_PLUGIN_DIR . 'templates/archive-stock_asset.php';
        if ( file_exists( $custom ) ) return $custom;
    }
    return $template;
}

// Load textdomain
add_action( 'plugins_loaded', function() {
    load_plugin_textdomain( 'scm', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
} );

<?php
if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'admin_menu', 'scm_admin_menu' );

function scm_admin_menu() {
    add_menu_page(
        __( 'SCM Settings', 'scm' ),
        __( 'SCM Settings', 'scm' ),
        'manage_options',
        'scm-settings',
        'scm_render_settings_page',
        'dashicons-store',
        25
    );
    add_submenu_page( 'scm-settings', __( 'Dashboard', 'scm' ), __( 'Dashboard', 'scm' ), 'manage_options', 'scm-dashboard', 'scm_render_admin_dashboard' );
    add_submenu_page( 'scm-settings', __( 'Settings', 'scm' ), __( 'Settings', 'scm' ), 'manage_options', 'scm-settings', 'scm_render_settings_page' );
}

function scm_render_settings_page() {
    if ( ! current_user_can( 'manage_options' ) ) return;

    if ( isset( $_POST['scm_save_settings'] ) && check_admin_referer( 'scm_settings_save' ) ) {
        $settings = [
            'per_page'             => intval( $_POST['per_page'] ?? 24 ),
            'require_login'        => isset( $_POST['require_login'] ) ? 1 : 0,
            'primary_color'        => sanitize_hex_color( $_POST['primary_color'] ?? '#6c63ff' ),
            'site_name'            => sanitize_text_field( wp_unslash( $_POST['site_name'] ?? '' ) ),
            'enable_s3'            => isset( $_POST['enable_s3'] ) ? 1 : 0,
            's3_bucket'            => sanitize_text_field( wp_unslash( $_POST['s3_bucket'] ?? '' ) ),
            's3_key'               => sanitize_text_field( wp_unslash( $_POST['s3_key'] ?? '' ) ),
            's3_secret'            => sanitize_text_field( wp_unslash( $_POST['s3_secret'] ?? '' ) ),
            's3_region'            => sanitize_text_field( wp_unslash( $_POST['s3_region'] ?? '' ) ),
            'enable_seo'           => isset( $_POST['enable_seo'] ) ? 1 : 0,
            'enable_schema'        => isset( $_POST['enable_schema'] ) ? 1 : 0,
            'subscription_page_id' => intval( $_POST['subscription_page_id'] ?? 0 ),
            'woo_enabled'          => isset( $_POST['woo_enabled'] ) ? 1 : 0,
        ];
        update_option( 'scm_settings', $settings );
        echo '<div class="notice notice-success"><p>' . esc_html__( 'Settings saved.', 'scm' ) . '</p></div>';
    }

    $settings = wp_parse_args( get_option( 'scm_settings', [] ), [
        'per_page'             => 24,
        'require_login'        => 0,
        'primary_color'        => '#6c63ff',
        'site_name'            => get_bloginfo( 'name' ),
        'enable_s3'            => 0,
        's3_bucket'            => '',
        's3_key'               => '',
        's3_secret'            => '',
        's3_region'            => 'us-east-1',
        'enable_seo'           => 1,
        'enable_schema'        => 1,
        'subscription_page_id' => 0,
        'woo_enabled'          => 1,
    ] );
    ?>
    <div class="wrap scm-admin-settings">
        <h1><?php esc_html_e( 'Stock Content Marketplace — Settings', 'scm' ); ?></h1>
        <form method="post">
            <?php wp_nonce_field( 'scm_settings_save' ); ?>

            <div class="scm-settings-tabs">
                <nav class="scm-tabs-nav">
                    <a href="#general" class="scm-tab-link active"><?php esc_html_e( 'General', 'scm' ); ?></a>
                    <a href="#branding" class="scm-tab-link"><?php esc_html_e( 'Branding', 'scm' ); ?></a>
                    <a href="#downloads" class="scm-tab-link"><?php esc_html_e( 'Downloads', 'scm' ); ?></a>
                    <a href="#storage" class="scm-tab-link"><?php esc_html_e( 'Storage', 'scm' ); ?></a>
                    <a href="#membership" class="scm-tab-link"><?php esc_html_e( 'Membership', 'scm' ); ?></a>
                    <a href="#seo" class="scm-tab-link"><?php esc_html_e( 'SEO', 'scm' ); ?></a>
                </nav>

                <!-- General -->
                <div class="scm-tab-pane active" id="general">
                    <table class="form-table">
                        <tr>
                            <th><?php esc_html_e( 'Assets Per Page', 'scm' ); ?></th>
                            <td><input type="number" name="per_page" value="<?php echo esc_attr( $settings['per_page'] ); ?>" min="4" max="100" /></td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e( 'WooCommerce Integration', 'scm' ); ?></th>
                            <td><label><input type="checkbox" name="woo_enabled" value="1" <?php checked( $settings['woo_enabled'], 1 ); ?> /> <?php esc_html_e( 'Enable WooCommerce payments', 'scm' ); ?></label></td>
                        </tr>
                    </table>
                </div>

                <!-- Branding -->
                <div class="scm-tab-pane" id="branding">
                    <table class="form-table">
                        <tr>
                            <th><?php esc_html_e( 'Site Name', 'scm' ); ?></th>
                            <td><input type="text" name="site_name" value="<?php echo esc_attr( $settings['site_name'] ); ?>" class="regular-text" /></td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e( 'Primary Color', 'scm' ); ?></th>
                            <td><input type="color" name="primary_color" value="<?php echo esc_attr( $settings['primary_color'] ); ?>" /></td>
                        </tr>
                    </table>
                </div>

                <!-- Downloads -->
                <div class="scm-tab-pane" id="downloads">
                    <table class="form-table">
                        <tr>
                            <th><?php esc_html_e( 'Require Login', 'scm' ); ?></th>
                            <td><label><input type="checkbox" name="require_login" value="1" <?php checked( $settings['require_login'], 1 ); ?> /> <?php esc_html_e( 'Users must be logged in to download', 'scm' ); ?></label></td>
                        </tr>
                    </table>
                </div>

                <!-- Storage -->
                <div class="scm-tab-pane" id="storage">
                    <table class="form-table">
                        <tr>
                            <th><?php esc_html_e( 'Enable AWS S3', 'scm' ); ?></th>
                            <td><label><input type="checkbox" name="enable_s3" value="1" <?php checked( $settings['enable_s3'], 1 ); ?> /> <?php esc_html_e( 'Store files on Amazon S3', 'scm' ); ?></label></td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e( 'S3 Bucket', 'scm' ); ?></th>
                            <td><input type="text" name="s3_bucket" value="<?php echo esc_attr( $settings['s3_bucket'] ); ?>" class="regular-text" /></td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e( 'AWS Access Key', 'scm' ); ?></th>
                            <td><input type="text" name="s3_key" value="<?php echo esc_attr( $settings['s3_key'] ); ?>" class="regular-text" /></td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e( 'AWS Secret Key', 'scm' ); ?></th>
                            <td><input type="password" name="s3_secret" value="<?php echo esc_attr( $settings['s3_secret'] ); ?>" class="regular-text" /></td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e( 'AWS Region', 'scm' ); ?></th>
                            <td><input type="text" name="s3_region" value="<?php echo esc_attr( $settings['s3_region'] ); ?>" class="regular-text" /></td>
                        </tr>
                    </table>
                </div>

                <!-- Membership -->
                <div class="scm-tab-pane" id="membership">
                    <table class="form-table">
                        <tr>
                            <th><?php esc_html_e( 'Subscription/Pricing Page', 'scm' ); ?></th>
                            <td>
                                <?php wp_dropdown_pages( [ 'name' => 'subscription_page_id', 'selected' => $settings['subscription_page_id'], 'show_option_none' => __( '— Select Page —', 'scm' ) ] ); ?>
                            </td>
                        </tr>
                    </table>
                </div>

                <!-- SEO -->
                <div class="scm-tab-pane" id="seo">
                    <table class="form-table">
                        <tr>
                            <th><?php esc_html_e( 'Enable SEO Features', 'scm' ); ?></th>
                            <td><label><input type="checkbox" name="enable_seo" value="1" <?php checked( $settings['enable_seo'], 1 ); ?> /> <?php esc_html_e( 'Open Graph, Canonical URLs', 'scm' ); ?></label></td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e( 'Schema Markup', 'scm' ); ?></th>
                            <td><label><input type="checkbox" name="enable_schema" value="1" <?php checked( $settings['enable_schema'], 1 ); ?> /> <?php esc_html_e( 'Add structured data markup', 'scm' ); ?></label></td>
                        </tr>
                    </table>
                </div>
            </div>

            <?php submit_button( __( 'Save Settings', 'scm' ), 'primary', 'scm_save_settings' ); ?>
        </form>
    </div>
    <?php
}

function scm_render_admin_dashboard() {
    global $wpdb;
    $total_assets    = wp_count_posts( 'stock_asset' )->publish;
    $total_downloads = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}scm_download_logs" );
    $total_views     = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}scm_view_logs" );
    $top_assets      = $wpdb->get_results(
        "SELECT asset_id, COUNT(*) as cnt FROM {$wpdb->prefix}scm_download_logs GROUP BY asset_id ORDER BY cnt DESC LIMIT 5"
    );
    ?>
    <div class="wrap scm-admin-dashboard">
        <h1><?php esc_html_e( 'SCM Dashboard', 'scm' ); ?></h1>
        <div class="scm-stat-cards">
            <div class="scm-stat-card">
                <div class="scm-stat-number"><?php echo esc_html( number_format( intval( $total_assets ) ) ); ?></div>
                <div class="scm-stat-label"><?php esc_html_e( 'Total Assets', 'scm' ); ?></div>
            </div>
            <div class="scm-stat-card">
                <div class="scm-stat-number"><?php echo esc_html( number_format( intval( $total_downloads ) ) ); ?></div>
                <div class="scm-stat-label"><?php esc_html_e( 'Total Downloads', 'scm' ); ?></div>
            </div>
            <div class="scm-stat-card">
                <div class="scm-stat-number"><?php echo esc_html( number_format( intval( $total_views ) ) ); ?></div>
                <div class="scm-stat-label"><?php esc_html_e( 'Total Views', 'scm' ); ?></div>
            </div>
        </div>
        <h2><?php esc_html_e( 'Top Downloaded Assets', 'scm' ); ?></h2>
        <table class="widefat striped">
            <thead><tr><th><?php esc_html_e( 'Asset', 'scm' ); ?></th><th><?php esc_html_e( 'Downloads', 'scm' ); ?></th></tr></thead>
            <tbody>
                <?php if ( $top_assets ) : foreach ( $top_assets as $row ) :
                    $title = get_the_title( $row->asset_id );
                    $link  = get_edit_post_link( $row->asset_id );
                ?>
                    <tr>
                        <td><a href="<?php echo esc_url( $link ); ?>"><?php echo esc_html( $title ); ?></a></td>
                        <td><?php echo esc_html( number_format( intval( $row->cnt ) ) ); ?></td>
                    </tr>
                <?php endforeach; else : ?>
                    <tr><td colspan="2"><?php esc_html_e( 'No data yet.', 'scm' ); ?></td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php
}

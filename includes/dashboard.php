<?php
if ( ! defined( 'ABSPATH' ) ) exit;

add_shortcode( 'stock_dashboard', 'scm_render_user_dashboard' );

function scm_render_user_dashboard( $atts = [] ) {
    if ( ! is_user_logged_in() ) {
        return '<div class="scm-notice">' . esc_html__( 'Please log in to view your dashboard.', 'scm' ) . '</div>';
    }

    $user_id = get_current_user_id();
    $user    = wp_get_current_user();
    $tab     = sanitize_key( $_GET['tab'] ?? 'downloads' );

    ob_start();
    ?>
    <div class="scm-dashboard">
        <div class="scm-dashboard-sidebar">
            <div class="scm-user-info">
                <?php echo get_avatar( $user_id, 64 ); ?>
                <h3><?php echo esc_html( $user->display_name ); ?></h3>
                <span><?php echo esc_html( $user->user_email ); ?></span>
            </div>
            <nav class="scm-dashboard-nav">
                <a href="?tab=downloads" class="<?php echo $tab === 'downloads' ? 'active' : ''; ?>">
                    <span class="dashicons dashicons-download"></span> <?php esc_html_e( 'My Downloads', 'scm' ); ?>
                </a>
                <a href="?tab=purchases" class="<?php echo $tab === 'purchases' ? 'active' : ''; ?>">
                    <span class="dashicons dashicons-cart"></span> <?php esc_html_e( 'Purchases', 'scm' ); ?>
                </a>
                <a href="?tab=favorites" class="<?php echo $tab === 'favorites' ? 'active' : ''; ?>">
                    <span class="dashicons dashicons-heart"></span> <?php esc_html_e( 'My Favorites', 'scm' ); ?>
                </a>
                <a href="?tab=collections" class="<?php echo $tab === 'collections' ? 'active' : ''; ?>">
                    <span class="dashicons dashicons-portfolio"></span> <?php esc_html_e( 'My Collections', 'scm' ); ?>
                </a>
                <a href="?tab=profile" class="<?php echo $tab === 'profile' ? 'active' : ''; ?>">
                    <span class="dashicons dashicons-admin-users"></span> <?php esc_html_e( 'Profile Settings', 'scm' ); ?>
                </a>
            </nav>
        </div>

        <div class="scm-dashboard-content">
            <?php
            switch ( $tab ) {
                case 'downloads':
                    scm_dashboard_downloads( $user_id );
                    break;
                case 'purchases':
                    scm_dashboard_purchases( $user_id );
                    break;
                case 'favorites':
                    echo do_shortcode( '[stock_favorites]' );
                    break;
                case 'collections':
                    echo do_shortcode( '[stock_collections]' );
                    break;
                case 'profile':
                    scm_dashboard_profile( $user_id );
                    break;
                default:
                    scm_dashboard_downloads( $user_id );
            }
            ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

function scm_dashboard_downloads( $user_id ) {
    global $wpdb;
    $downloads = $wpdb->get_results( $wpdb->prepare(
        "SELECT DISTINCT asset_id, MAX(downloaded_at) as last_download FROM {$wpdb->prefix}scm_download_logs WHERE user_id = %d GROUP BY asset_id ORDER BY last_download DESC LIMIT 30",
        $user_id
    ) );
    ?>
    <h2><?php esc_html_e( 'My Downloads', 'scm' ); ?></h2>
    <?php if ( $downloads ) : ?>
        <div class="scm-grid">
            <?php foreach ( $downloads as $row ) :
                $post = get_post( $row->asset_id );
                if ( $post ) { setup_postdata( $post ); scm_render_asset_card( $row->asset_id ); }
            endforeach; wp_reset_postdata(); ?>
        </div>
    <?php else : ?>
        <p><?php esc_html_e( 'You have not downloaded any assets yet.', 'scm' ); ?></p>
    <?php endif;
}

function scm_dashboard_purchases( $user_id ) {
    global $wpdb;
    $purchases = $wpdb->get_results( $wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}scm_purchases WHERE user_id = %d ORDER BY purchased_at DESC",
        $user_id
    ) );
    ?>
    <h2><?php esc_html_e( 'Purchase History', 'scm' ); ?></h2>
    <table class="scm-table widefat">
        <thead>
            <tr>
                <th><?php esc_html_e( 'Asset', 'scm' ); ?></th>
                <th><?php esc_html_e( 'Date', 'scm' ); ?></th>
                <th><?php esc_html_e( 'Order', 'scm' ); ?></th>
                <th><?php esc_html_e( 'Download', 'scm' ); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if ( $purchases ) : foreach ( $purchases as $p ) : ?>
                <tr>
                    <td><a href="<?php echo esc_url( get_permalink( $p->asset_id ) ); ?>"><?php echo esc_html( get_the_title( $p->asset_id ) ); ?></a></td>
                    <td><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $p->purchased_at ) ) ); ?></td>
                    <td><?php echo $p->order_id ? '<a href="' . esc_url( get_edit_post_link( $p->order_id ) ) . '">#' . esc_html( $p->order_id ) . '</a>' : '—'; ?></td>
                    <td><button class="scm-btn scm-btn-sm scm-btn-download" data-id="<?php echo esc_attr( $p->asset_id ); ?>"><?php esc_html_e( 'Download', 'scm' ); ?></button></td>
                </tr>
            <?php endforeach; else : ?>
                <tr><td colspan="4"><?php esc_html_e( 'No purchases yet.', 'scm' ); ?></td></tr>
            <?php endif; ?>
        </tbody>
    </table>
    <?php
}

function scm_dashboard_profile( $user_id ) {
    $user = get_userdata( $user_id );
    if ( isset( $_POST['scm_save_profile'] ) && check_admin_referer( 'scm_save_profile_' . $user_id ) ) {
        wp_update_user( [
            'ID'           => $user_id,
            'first_name'   => sanitize_text_field( wp_unslash( $_POST['first_name'] ?? '' ) ),
            'last_name'    => sanitize_text_field( wp_unslash( $_POST['last_name'] ?? '' ) ),
            'description'  => sanitize_textarea_field( wp_unslash( $_POST['description'] ?? '' ) ),
        ] );
        echo '<div class="scm-notice scm-notice-success">' . esc_html__( 'Profile saved!', 'scm' ) . '</div>';
        $user = get_userdata( $user_id );
    }
    ?>
    <h2><?php esc_html_e( 'Profile Settings', 'scm' ); ?></h2>
    <form method="post" class="scm-profile-form">
        <?php wp_nonce_field( 'scm_save_profile_' . $user_id ); ?>
        <div class="scm-form-row">
            <label><?php esc_html_e( 'First Name', 'scm' ); ?></label>
            <input type="text" name="first_name" value="<?php echo esc_attr( $user->first_name ); ?>" />
        </div>
        <div class="scm-form-row">
            <label><?php esc_html_e( 'Last Name', 'scm' ); ?></label>
            <input type="text" name="last_name" value="<?php echo esc_attr( $user->last_name ); ?>" />
        </div>
        <div class="scm-form-row">
            <label><?php esc_html_e( 'Bio', 'scm' ); ?></label>
            <textarea name="description" rows="5"><?php echo esc_textarea( $user->description ); ?></textarea>
        </div>
        <button type="submit" name="scm_save_profile" class="scm-btn scm-btn-primary"><?php esc_html_e( 'Save Profile', 'scm' ); ?></button>
    </form>
    <?php
}

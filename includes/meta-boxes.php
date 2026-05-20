<?php
if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'add_meta_boxes', 'scm_add_meta_boxes' );
add_action( 'save_post_stock_asset', 'scm_save_meta_boxes', 10, 2 );

function scm_add_meta_boxes() {
    add_meta_box( 'scm_asset_details', __( 'Asset Details', 'scm' ), 'scm_render_asset_details_box', 'stock_asset', 'normal', 'high' );
    add_meta_box( 'scm_asset_files',   __( 'Asset Files & Downloads', 'scm' ), 'scm_render_asset_files_box', 'stock_asset', 'normal', 'high' );
    add_meta_box( 'scm_asset_pricing', __( 'Pricing & Access', 'scm' ), 'scm_render_pricing_box', 'stock_asset', 'side', 'high' );
    add_meta_box( 'scm_asset_stats',   __( 'Statistics', 'scm' ), 'scm_render_stats_box', 'stock_asset', 'side', 'low' );
}

function scm_render_asset_details_box( $post ) {
    wp_nonce_field( 'scm_save_meta', 'scm_meta_nonce' );
    $meta = scm_get_all_meta( $post->ID );
    ?>
    <div class="scm-meta-grid">
        <div class="scm-meta-row">
            <label><?php esc_html_e( 'File Format', 'scm' ); ?></label>
            <input type="text" name="scm_file_format" value="<?php echo esc_attr( $meta['file_format'] ); ?>" placeholder="JPG, PNG, MP4, ZIP..." />
        </div>
        <div class="scm-meta-row">
            <label><?php esc_html_e( 'File Size', 'scm' ); ?></label>
            <input type="text" name="scm_file_size" value="<?php echo esc_attr( $meta['file_size'] ); ?>" placeholder="e.g. 24.5 MB" />
        </div>
        <div class="scm-meta-row">
            <label><?php esc_html_e( 'Resolution', 'scm' ); ?></label>
            <input type="text" name="scm_resolution" value="<?php echo esc_attr( $meta['resolution'] ); ?>" placeholder="e.g. 4000x3000" />
        </div>
        <div class="scm-meta-row">
            <label><?php esc_html_e( 'Dimensions', 'scm' ); ?></label>
            <input type="text" name="scm_dimensions" value="<?php echo esc_attr( $meta['dimensions'] ); ?>" placeholder="e.g. 1920x1080 px" />
        </div>
        <div class="scm-meta-row">
            <label><?php esc_html_e( 'Duration', 'scm' ); ?></label>
            <input type="text" name="scm_duration" value="<?php echo esc_attr( $meta['duration'] ); ?>" placeholder="e.g. 0:30" />
        </div>
        <div class="scm-meta-row">
            <label><?php esc_html_e( 'License Type', 'scm' ); ?></label>
            <select name="scm_license_type">
                <option value="free" <?php selected( $meta['license_type'], 'free' ); ?>><?php esc_html_e( 'Free', 'scm' ); ?></option>
                <option value="commercial" <?php selected( $meta['license_type'], 'commercial' ); ?>><?php esc_html_e( 'Commercial', 'scm' ); ?></option>
                <option value="editorial" <?php selected( $meta['license_type'], 'editorial' ); ?>><?php esc_html_e( 'Editorial', 'scm' ); ?></option>
                <option value="extended" <?php selected( $meta['license_type'], 'extended' ); ?>><?php esc_html_e( 'Extended', 'scm' ); ?></option>
            </select>
        </div>
        <div class="scm-meta-row">
            <label><?php esc_html_e( 'Video Preview URL', 'scm' ); ?></label>
            <input type="url" name="scm_video_preview_url" value="<?php echo esc_url( $meta['video_preview_url'] ); ?>" placeholder="https://..." />
        </div>
        <div class="scm-meta-row">
            <label><?php esc_html_e( 'Author Name', 'scm' ); ?></label>
            <input type="text" name="scm_author_name" value="<?php echo esc_attr( $meta['author_name'] ); ?>" />
        </div>
        <div class="scm-meta-row">
            <label><?php esc_html_e( 'Author URL', 'scm' ); ?></label>
            <input type="url" name="scm_author_url" value="<?php echo esc_url( $meta['author_url'] ); ?>" />
        </div>
        <div class="scm-meta-row">
            <label><?php esc_html_e( 'Related Collection', 'scm' ); ?></label>
            <input type="text" name="scm_related_collection" value="<?php echo esc_attr( $meta['related_collection'] ); ?>" />
        </div>
        <div class="scm-meta-row scm-checkboxes">
            <label><input type="checkbox" name="scm_is_featured" value="1" <?php checked( $meta['is_featured'], '1' ); ?> /> <?php esc_html_e( 'Featured', 'scm' ); ?></label>
        </div>
        <div class="scm-meta-row">
            <label><?php esc_html_e( 'Preview Image Gallery (comma-separated attachment IDs)', 'scm' ); ?></label>
            <div class="scm-gallery-uploader">
                <input type="hidden" name="scm_gallery_ids" id="scm_gallery_ids" value="<?php echo esc_attr( $meta['gallery_ids'] ); ?>" />
                <button type="button" class="button scm-upload-gallery"><?php esc_html_e( 'Add Images', 'scm' ); ?></button>
                <div class="scm-gallery-preview">
                    <?php
                    if ( $meta['gallery_ids'] ) {
                        $ids = array_filter( explode( ',', $meta['gallery_ids'] ) );
                        foreach ( $ids as $id ) {
                            echo wp_get_attachment_image( intval( $id ), 'thumbnail' );
                        }
                    }
                    ?>
                </div>
            </div>
        </div>
    </div>
    <?php
}

function scm_render_asset_files_box( $post ) {
    $meta = scm_get_all_meta( $post->ID );
    ?>
    <div class="scm-meta-grid">
        <div class="scm-meta-row">
            <label><?php esc_html_e( 'Main Download File', 'scm' ); ?></label>
            <div class="scm-file-uploader">
                <input type="hidden" name="scm_download_file_id" id="scm_download_file_id" value="<?php echo esc_attr( $meta['download_file_id'] ); ?>" />
                <input type="text" id="scm_download_file_url" value="<?php echo esc_attr( $meta['download_file_id'] ? wp_get_attachment_url( intval( $meta['download_file_id'] ) ) : '' ); ?>" readonly placeholder="<?php esc_attr_e( 'No file selected', 'scm' ); ?>" />
                <button type="button" class="button scm-upload-file" data-target="scm_download_file_id" data-display="scm_download_file_url"><?php esc_html_e( 'Upload / Select', 'scm' ); ?></button>
                <?php if ( $meta['download_file_id'] ) : ?>
                    <a href="<?php echo esc_url( wp_get_attachment_url( intval( $meta['download_file_id'] ) ) ); ?>" target="_blank" class="button"><?php esc_html_e( 'View File', 'scm' ); ?></a>
                <?php endif; ?>
            </div>
        </div>
        <div class="scm-meta-row">
            <label><?php esc_html_e( 'Additional Download Files (comma-separated IDs)', 'scm' ); ?></label>
            <input type="text" name="scm_additional_files" value="<?php echo esc_attr( $meta['additional_files'] ); ?>" placeholder="attachment IDs..." />
        </div>
    </div>
    <?php
}

function scm_render_pricing_box( $post ) {
    $meta = scm_get_all_meta( $post->ID );
    ?>
    <div class="scm-meta-grid">
        <div class="scm-meta-row scm-checkboxes">
            <label><input type="checkbox" name="scm_is_free" value="1" <?php checked( $meta['is_free'], '1' ); ?> id="scm_is_free" /> <?php esc_html_e( 'Free Asset', 'scm' ); ?></label>
        </div>
        <div class="scm-meta-row scm-checkboxes">
            <label><input type="checkbox" name="scm_is_premium" value="1" <?php checked( $meta['is_premium'], '1' ); ?> id="scm_is_premium" /> <?php esc_html_e( 'Premium Asset', 'scm' ); ?></label>
        </div>
        <div class="scm-meta-row scm-premium-fields">
            <label><?php esc_html_e( 'Regular Price ($)', 'scm' ); ?></label>
            <input type="number" name="scm_regular_price" value="<?php echo esc_attr( $meta['regular_price'] ); ?>" step="0.01" min="0" placeholder="9.99" />
        </div>
        <div class="scm-meta-row scm-premium-fields">
            <label><?php esc_html_e( 'Sale Price ($)', 'scm' ); ?></label>
            <input type="number" name="scm_sale_price" value="<?php echo esc_attr( $meta['sale_price'] ); ?>" step="0.01" min="0" placeholder="4.99" />
        </div>
        <div class="scm-meta-row scm-premium-fields">
            <label><?php esc_html_e( 'Currency', 'scm' ); ?></label>
            <select name="scm_currency">
                <option value="USD" <?php selected( $meta['currency'], 'USD' ); ?>>USD ($)</option>
                <option value="EUR" <?php selected( $meta['currency'], 'EUR' ); ?>>EUR (€)</option>
                <option value="GBP" <?php selected( $meta['currency'], 'GBP' ); ?>>GBP (£)</option>
            </select>
        </div>
        <div class="scm-meta-row scm-premium-fields">
            <label><?php esc_html_e( 'WooCommerce Product ID', 'scm' ); ?></label>
            <input type="number" name="scm_woo_product_id" value="<?php echo esc_attr( $meta['woo_product_id'] ); ?>" placeholder="WC Product ID" />
            <button type="button" class="button scm-create-woo-product"><?php esc_html_e( 'Auto-Create WC Product', 'scm' ); ?></button>
        </div>
        <div class="scm-meta-row">
            <label><?php esc_html_e( 'Access Type', 'scm' ); ?></label>
            <select name="scm_access_type">
                <option value="free" <?php selected( $meta['access_type'], 'free' ); ?>><?php esc_html_e( 'Free', 'scm' ); ?></option>
                <option value="purchase" <?php selected( $meta['access_type'], 'purchase' ); ?>><?php esc_html_e( 'Single Purchase', 'scm' ); ?></option>
                <option value="subscription" <?php selected( $meta['access_type'], 'subscription' ); ?>><?php esc_html_e( 'Subscription Only', 'scm' ); ?></option>
                <option value="both" <?php selected( $meta['access_type'], 'both' ); ?>><?php esc_html_e( 'Purchase or Subscription', 'scm' ); ?></option>
            </select>
        </div>
    </div>
    <?php
}

function scm_render_stats_box( $post ) {
    $download_count = intval( get_post_meta( $post->ID, 'scm_download_count', true ) );
    $view_count     = intval( get_post_meta( $post->ID, 'scm_view_count', true ) );
    ?>
    <div class="scm-stats-box">
        <p>📥 <?php printf( esc_html__( 'Downloads: %d', 'scm' ), $download_count ); ?></p>
        <p>👁 <?php printf( esc_html__( 'Views: %d', 'scm' ), $view_count ); ?></p>
    </div>
    <?php
}

function scm_get_all_meta( $post_id ) {
    $keys = [
        'file_format', 'file_size', 'resolution', 'dimensions', 'duration',
        'license_type', 'video_preview_url', 'author_name', 'author_url',
        'related_collection', 'is_featured', 'gallery_ids',
        'download_file_id', 'additional_files',
        'is_free', 'is_premium', 'regular_price', 'sale_price', 'currency',
        'woo_product_id', 'access_type',
    ];
    $meta = [];
    foreach ( $keys as $key ) {
        $meta[ $key ] = get_post_meta( $post_id, 'scm_' . $key, true );
    }
    if ( empty( $meta['currency'] ) ) $meta['currency'] = 'USD';
    if ( empty( $meta['license_type'] ) ) $meta['license_type'] = 'free';
    if ( empty( $meta['access_type'] ) ) $meta['access_type'] = 'free';
    return $meta;
}

function scm_save_meta_boxes( $post_id, $post ) {
    if ( ! isset( $_POST['scm_meta_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['scm_meta_nonce'] ) ), 'scm_save_meta' ) ) {
        return;
    }
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
    if ( ! current_user_can( 'edit_post', $post_id ) ) return;

    $text_fields = [
        'scm_file_format', 'scm_file_size', 'scm_resolution', 'scm_dimensions',
        'scm_duration', 'scm_license_type', 'scm_author_name', 'scm_related_collection',
        'scm_gallery_ids', 'scm_additional_files', 'scm_currency', 'scm_access_type',
    ];
    $url_fields  = [ 'scm_video_preview_url', 'scm_author_url' ];
    $int_fields  = [ 'scm_download_file_id', 'scm_woo_product_id' ];
    $float_fields= [ 'scm_regular_price', 'scm_sale_price' ];
    $bool_fields = [ 'scm_is_featured', 'scm_is_free', 'scm_is_premium' ];

    foreach ( $text_fields as $field ) {
        $key = ltrim( $field, 'scm_' );
        update_post_meta( $post_id, $field, sanitize_text_field( wp_unslash( $_POST[ $field ] ?? '' ) ) );
    }
    foreach ( $url_fields as $field ) {
        update_post_meta( $post_id, $field, esc_url_raw( wp_unslash( $_POST[ $field ] ?? '' ) ) );
    }
    foreach ( $int_fields as $field ) {
        update_post_meta( $post_id, $field, intval( $_POST[ $field ] ?? 0 ) );
    }
    foreach ( $float_fields as $field ) {
        update_post_meta( $post_id, $field, floatval( $_POST[ $field ] ?? 0 ) );
    }
    foreach ( $bool_fields as $field ) {
        update_post_meta( $post_id, $field, isset( $_POST[ $field ] ) ? '1' : '0' );
    }
}

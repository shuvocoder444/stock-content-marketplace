<?php
if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'admin_menu', 'scm_import_export_menu', 20 );

function scm_import_export_menu() {
    add_submenu_page(
        'scm-settings',
        __( 'Import / Export', 'scm' ),
        __( 'Import / Export', 'scm' ),
        'manage_options',
        'scm-import-export',
        'scm_render_import_export_page'
    );
}

// Handle Export and Import Logic
add_action( 'admin_init', 'scm_process_import_export' );

function scm_process_import_export() {
    if ( ! current_user_can( 'manage_options' ) ) return;

    // Handle Export
    if ( isset( $_POST['scm_export_assets'] ) && check_admin_referer( 'scm_export_action', 'scm_export_nonce' ) ) {
        scm_do_export();
    }

    // Handle Import
    if ( isset( $_POST['scm_import_assets'] ) && check_admin_referer( 'scm_import_action', 'scm_import_nonce' ) ) {
        if ( ! empty( $_FILES['scm_import_file']['tmp_name'] ) ) {
            $file = $_FILES['scm_import_file']['tmp_name'];
            $result = scm_do_import( $file );
            if ( is_wp_error( $result ) ) {
                add_settings_error( 'scm_import', 'scm_import_error', $result->get_error_message(), 'error' );
            } else {
                add_settings_error( 'scm_import', 'scm_import_success', sprintf( __( 'Successfully imported %d assets.', 'scm' ), $result ), 'success' );
            }
        } else {
            add_settings_error( 'scm_import', 'scm_import_error', __( 'Please select a file to import.', 'scm' ), 'error' );
        }
    }
}

function scm_do_export() {
    $meta_keys = [
        'file_format', 'file_size', 'resolution', 'dimensions', 'duration',
        'license_type', 'video_preview_url', 'author_name', 'author_url',
        'related_collection', 'is_featured', 'gallery_ids',
        'download_file_id', 'additional_files',
        'is_free', 'is_premium', 'regular_price', 'sale_price', 'currency',
        'woo_product_id', 'access_type',
        // Extended fields for custom imports
        'software', 'width', 'height', 'orientation', 'fps', 'color_mode',
        'software_type', 'software_version', 'template_type', 'is_editable', 
        'compatibility', 'color_palette', 's3_url', 'preview_video_url', 
        'video_file_url', 'download_count', 'view_count', 'seo_title', 'seo_description'
    ];

    $taxonomies = [ 'asset_type', 'asset_category', 'asset_tag' ];

    $headers = array_merge( 
        [ 'ID', 'post_title', 'post_content', 'post_excerpt', 'post_status', 'post_date' ], 
        $taxonomies, 
        $meta_keys 
    );

    header( 'Content-Type: text/csv; charset=utf-8' );
    header( 'Content-Disposition: attachment; filename=scm-assets-export-' . date('Y-m-d') . '.csv' );
    
    $output = fopen( 'php://output', 'w' );
    fputcsv( $output, $headers );

    $args = [
        'post_type'      => 'stock_asset',
        'post_status'    => 'any',
        'posts_per_page' => -1,
    ];
    $assets = get_posts( $args );

    foreach ( $assets as $asset ) {
        $row = [
            $asset->ID,
            $asset->post_title,
            $asset->post_content,
            $asset->post_excerpt,
            $asset->post_status,
            $asset->post_date,
        ];

        // Add Taxonomies
        foreach ( $taxonomies as $tax ) {
            $terms = wp_get_post_terms( $asset->ID, $tax, [ 'fields' => 'names' ] );
            $row[] = ! is_wp_error( $terms ) && ! empty( $terms ) ? implode( '|', $terms ) : '';
        }

        // Add Meta
        foreach ( $meta_keys as $key ) {
            $meta_value = get_post_meta( $asset->ID, 'scm_' . $key, true );
            $row[] = $meta_value;
        }

        fputcsv( $output, $row );
    }

    fclose( $output );
    exit;
}

function scm_do_import( $file_path ) {
    $handle = fopen( $file_path, 'r' );
    if ( $handle === false ) {
        return new WP_Error( 'file_error', __( 'Could not open the file.', 'scm' ) );
    }

    $headers = fgetcsv( $handle );
    if ( ! $headers ) {
        fclose( $handle );
        return new WP_Error( 'file_empty', __( 'File is empty or not formatted correctly.', 'scm' ) );
    }

    // Identify indices
    $indices = array_flip( $headers );
    // Removed strict required_cols check to allow flexible title generation

    $meta_keys = [
        'file_format', 'file_size', 'resolution', 'dimensions', 'duration',
        'license_type', 'video_preview_url', 'author_name', 'author_url',
        'related_collection', 'is_featured', 'gallery_ids',
        'download_file_id', 'additional_files',
        'is_free', 'is_premium', 'regular_price', 'sale_price', 'currency',
        'woo_product_id', 'access_type',
        // Extended fields for custom imports
        'software', 'width', 'height', 'orientation', 'fps', 'color_mode',
        'software_type', 'software_version', 'template_type', 'is_editable', 
        'compatibility', 'color_palette', 's3_url', 'preview_video_url', 
        'video_file_url', 'download_count', 'view_count', 'seo_title', 'seo_description'
    ];
    $taxonomies = [ 'asset_type', 'asset_category', 'asset_tag' ];
    
    $count = 0;

    while ( ( $data = fgetcsv( $handle ) ) !== false ) {
        // Skip empty rows
        if ( empty( array_filter( $data ) ) ) continue;

        $post_id = isset( $indices['ID'] ) && ! empty( $data[ $indices['ID'] ] ) ? intval( $data[ $indices['ID'] ] ) : 0;
        if ( ! $post_id && isset( $indices['id'] ) && ! empty( $data[ $indices['id'] ] ) ) {
            $post_id = intval( $data[ $indices['id'] ] );
        }
        
        $post_title = '';
        if ( isset( $indices['post_title'] ) && ! empty( $data[ $indices['post_title'] ] ) ) {
            $post_title = $data[ $indices['post_title'] ];
        } else {
            $alt_title_keys = [ 'image title', 'image_title', 'title', 'name' ];
            foreach ( $alt_title_keys as $alt_key ) {
                foreach ( $indices as $header_name => $index ) {
                    if ( strtolower( trim( $header_name ) ) === $alt_key && ! empty( $data[ $index ] ) ) {
                        $post_title = $data[ $index ];
                        break 2;
                    }
                }
            }
            if ( empty( $post_title ) ) {
                $post_title = 'Asset - ' . uniqid();
            }
        }

        $get_val = function($keys) use ($indices, $data) {
            foreach ((array)$keys as $k) {
                foreach ($indices as $h => $idx) {
                    if (strtolower(trim($h)) === $k && !empty($data[$idx])) return $data[$idx];
                }
            }
            return '';
        };

        $post_data = [
            'post_type'    => 'stock_asset',
            'post_title'   => $post_title,
            'post_content' => $get_val(['post_content', 'content']),
            'post_excerpt' => $get_val(['post_excerpt', 'excerpt']),
            'post_status'  => $get_val(['post_status', 'status']) ?: 'publish',
        ];
        
        $post_date = $get_val(['post_date', 'date_created']);
        if ( $post_date ) {
            $post_data['post_date'] = $post_date;
        }

        if ( $post_id > 0 && get_post( $post_id ) && get_post_type( $post_id ) === 'stock_asset' ) {
            $post_data['ID'] = $post_id;
            $new_post_id = wp_update_post( $post_data );
        } else {
            $new_post_id = wp_insert_post( $post_data );
        }

        if ( is_wp_error( $new_post_id ) || $new_post_id == 0 ) {
            continue;
        }

        // Handle Taxonomies with Fallbacks
        $tax_mappings = [
            'asset_type' => ['asset_type', 'media_type'],
            'asset_category' => ['asset_category', 'categories', 'subcategories'],
            'asset_tag' => ['asset_tag', 'tags']
        ];

        foreach ( $tax_mappings as $tax => $csv_cols ) {
            $terms_str = [];
            foreach ($csv_cols as $col) {
                $val = $get_val([$col]);
                if ($val) $terms_str[] = $val;
            }
            $terms_str = implode('|', $terms_str);
            $terms_str = str_replace(',', '|', $terms_str); // Allow comma separated too
            
            if ( ! empty( $terms_str ) ) {
                $terms = array_filter( array_map( 'trim', explode( '|', $terms_str ) ) );
                $term_ids = [];
                foreach ( $terms as $term_name ) {
                    $term = term_exists( $term_name, $tax );
                    if ( ! $term ) {
                        $term = wp_insert_term( $term_name, $tax );
                    }
                    if ( ! is_wp_error( $term ) ) {
                        $term_ids[] = intval( $term['term_id'] );
                    }
                }
                wp_set_object_terms( $new_post_id, $term_ids, $tax, false );
            } else {
                wp_set_object_terms( $new_post_id, [], $tax, false );
            }
        }

        // Handle Meta
        $int_fields = [ 'download_file_id', 'woo_product_id' ];
        $float_fields = [ 'regular_price', 'sale_price' ];
        $bool_fields = [ 'is_featured', 'is_free', 'is_premium' ];

        foreach ( $meta_keys as $key ) {
            $val = $get_val([$key]);
            
            // Map preview_video_url to video_preview_url if not set explicitly
            if ( $key === 'video_preview_url' && empty($val) ) {
                $val = $get_val(['preview_video_url']);
            }
            
            if ( $val !== '' ) {
                if ( in_array( $key, $int_fields ) ) {
                    $val = intval( $val );
                } elseif ( in_array( $key, $float_fields ) ) {
                    $val = floatval( $val );
                } elseif ( in_array( $key, $bool_fields ) ) {
                    $val = empty( $val ) || $val == '0' || strtolower($val) == 'false' ? '0' : '1';
                }
                update_post_meta( $new_post_id, 'scm_' . $key, $val );
            }
        }

        // Combine width and height into dimensions if dimensions is empty
        $dimensions = get_post_meta($new_post_id, 'scm_dimensions', true);
        if ( empty($dimensions) ) {
            $w = $get_val(['width']);
            $h = $get_val(['height']);
            if ( $w && $h ) {
                update_post_meta( $new_post_id, 'scm_dimensions', $w . 'x' . $h );
            }
        }

        // Handle File Sideloading and save URLs as meta for fallback
        $thumbnail_url = $get_val(['thumbnail_url', 'images', 'image', 'image_url', 'featured_image']);
        $gallery_urls_str = $get_val(['gallery_urls', 'gallery', 'additional_images']);
        
        // If the thumbnail field contains commas (like WooCommerce 'Images' column), split it
        if ( $thumbnail_url && strpos($thumbnail_url, ',') !== false ) {
            $parts = array_filter( array_map( 'trim', explode(',', $thumbnail_url) ) );
            if ( ! empty($parts) ) {
                $thumbnail_url = array_shift($parts); // first image is thumbnail
                if ( empty($gallery_urls_str) && ! empty($parts) ) {
                    $gallery_urls_str = implode(',', $parts); // rest goes to gallery
                }
            }
        }

        if ( $thumbnail_url ) {
            update_post_meta( $new_post_id, 'scm_thumbnail_url', $thumbnail_url );
            $attach_id = scm_sideload_file_from_url( $thumbnail_url, $new_post_id );
            if ( $attach_id ) set_post_thumbnail( $new_post_id, $attach_id );
        }

        $download_file_url = $get_val(['download_file_url', 'file', 'download_url', 'downloadable_url']);
        if ( $download_file_url ) {
            update_post_meta( $new_post_id, 'scm_download_file_url', $download_file_url );
            $attach_id = scm_sideload_file_from_url( $download_file_url, $new_post_id );
            if ( $attach_id ) update_post_meta( $new_post_id, 'scm_download_file_id', $attach_id );
        }

        if ( $gallery_urls_str ) {
            update_post_meta( $new_post_id, 'scm_gallery_urls', $gallery_urls_str );
            $urls = array_filter( array_map( 'trim', explode( '|', str_replace(',', '|', $gallery_urls_str) ) ) );
            $gallery_ids = [];
            foreach ( $urls as $url ) {
                $attach_id = scm_sideload_file_from_url( $url, $new_post_id );
                if ( $attach_id ) $gallery_ids[] = $attach_id;
            }
            if ( ! empty( $gallery_ids ) ) {
                update_post_meta( $new_post_id, 'scm_gallery_ids', implode( ',', $gallery_ids ) );
            }
        }

        $count++;
    }

    fclose( $handle );
    return $count;
}

function scm_sideload_file_from_url( $url, $post_id ) {
    require_once( ABSPATH . 'wp-admin/includes/file.php' );
    require_once( ABSPATH . 'wp-admin/includes/media.php' );
    require_once( ABSPATH . 'wp-admin/includes/image.php' );

    $ext = strtolower(pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
    if ( in_array( $ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'] ) ) {
        $id = media_sideload_image( $url, $post_id, null, 'id' );
        if ( ! is_wp_error( $id ) ) return $id;
    }

    $tmp = download_url( $url );
    if ( is_wp_error( $tmp ) ) {
        return false;
    }

    $filename = basename( parse_url( $url, PHP_URL_PATH ) );

    if ( ! preg_match('/\.[a-zA-Z0-9]+$/', $filename) ) {
        $mime = mime_content_type($tmp);
        $ext_map = ['image/jpeg' => '.jpg', 'image/png' => '.png', 'image/webp' => '.webp', 'image/gif' => '.gif', 'video/mp4' => '.mp4', 'application/zip' => '.zip'];
        if ( $mime && isset($ext_map[$mime]) ) {
            $filename .= $ext_map[$mime];
        } else {
            $filename .= '.jpg';
        }
    }

    $file_array = [
        'name'     => current_time('Ymd_His') . '_' . wp_unique_filename( get_temp_dir(), $filename ),
        'tmp_name' => $tmp,
    ];
    $id = media_handle_sideload( $file_array, $post_id );
    if ( is_wp_error( $id ) ) {
        @unlink( $file_array['tmp_name'] );
        return false;
    }
    return $id;
}

function scm_render_import_export_page() {
    ?>
    <div class="wrap scm-admin-settings">
        <h1><?php esc_html_e( 'Import / Export Assets', 'scm' ); ?></h1>
        
        <?php settings_errors( 'scm_import' ); ?>

        <div class="scm-settings-tabs" style="margin-top: 20px;">
            <div class="scm-tab-pane active" style="display: flex; gap: 40px; flex-wrap: wrap;">
                
                <!-- Export Section -->
                <div class="scm-export-box" style="flex: 1; min-width: 300px; background: #fff; padding: 20px; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
                    <h2><?php esc_html_e( 'Export Assets', 'scm' ); ?></h2>
                    <p><?php esc_html_e( 'Export all Stock Assets into a CSV file. This includes all metadata and taxonomy terms.', 'scm' ); ?></p>
                    <form method="post">
                        <?php wp_nonce_field( 'scm_export_action', 'scm_export_nonce' ); ?>
                        <p>
                            <button type="submit" name="scm_export_assets" class="button button-primary">
                                <?php esc_html_e( 'Export to CSV', 'scm' ); ?>
                            </button>
                        </p>
                    </form>
                </div>

                <!-- Import Section -->
                <div class="scm-import-box" style="flex: 1; min-width: 300px; background: #fff; padding: 20px; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
                    <h2><?php esc_html_e( 'Import Assets', 'scm' ); ?></h2>
                    <p><?php esc_html_e( 'Import Stock Assets from a CSV file. If an asset has an ID matching an existing asset, it will be updated. Otherwise, a new asset will be created.', 'scm' ); ?></p>
                    <form method="post" enctype="multipart/form-data">
                        <?php wp_nonce_field( 'scm_import_action', 'scm_import_nonce' ); ?>
                        <p>
                            <input type="file" name="scm_import_file" accept=".csv" required />
                        </p>
                        <p>
                            <button type="submit" name="scm_import_assets" class="button button-primary">
                                <?php esc_html_e( 'Import CSV', 'scm' ); ?>
                            </button>
                        </p>
                    </form>
                    <p class="description">
                        <?php esc_html_e( 'Note: Multiple taxonomy terms (like tags) should be separated by a pipe character (|).', 'scm' ); ?>
                    </p>
                </div>

            </div>
        </div>
    </div>
    <?php
}

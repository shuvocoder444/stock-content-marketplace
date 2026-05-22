<?php
if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'wp_ajax_scm_search',        'scm_ajax_search' );
add_action( 'wp_ajax_nopriv_scm_search', 'scm_ajax_search' );
add_action( 'wp_ajax_scm_get_similar',        'scm_ajax_get_similar' );
add_action( 'wp_ajax_nopriv_scm_get_similar', 'scm_ajax_get_similar' );
add_action( 'wp_ajax_scm_get_quick_view',        'scm_ajax_get_quick_view' );
add_action( 'wp_ajax_nopriv_scm_get_quick_view', 'scm_ajax_get_quick_view' );

function scm_ajax_search() {
    check_ajax_referer( 'scm_nonce', 'nonce' );

    $args = scm_build_query_args( $_POST );
    $query = new WP_Query( $args );

    ob_start();
    if ( $query->have_posts() ) {
        while ( $query->have_posts() ) {
            $query->the_post();
            scm_render_asset_card( get_the_ID() );
        }
    } else {
        echo '<div class="scm-no-results"><p>' . esc_html__( 'No assets found. Try different search terms.', 'scm' ) . '</p></div>';
    }
    wp_reset_postdata();
    $html = ob_get_clean();

    wp_send_json_success( [
        'html'        => $html,
        'total'       => $query->found_posts,
        'max_pages'   => $query->max_num_pages,
        'current_page'=> intval( $_POST['paged'] ?? 1 ),
    ] );
}

function scm_build_query_args( $params ) {
    $paged     = intval( $params['paged'] ?? 1 );
    $per_page  = intval( get_option( 'scm_settings', [] )['per_page'] ?? 24 );
    $keyword   = sanitize_text_field( wp_unslash( $params['keyword'] ?? '' ) );
    $orderby   = sanitize_key( $params['orderby'] ?? 'date' );

    $args = [
        'post_type'      => 'stock_asset',
        'posts_per_page' => $per_page,
        'paged'          => $paged,
        'post_status'    => 'publish',
    ];

    if ( $keyword ) {
        $args['s'] = $keyword;
    }

    // Taxonomy filters
    $tax_query = [];

    $asset_type = sanitize_text_field( $params['asset_type'] ?? '' );
    if ( $asset_type ) {
        $tax_query[] = [
            'taxonomy' => 'asset_type',
            'field'    => 'slug',
            'terms'    => explode( ',', $asset_type ),
        ];
    }

    $category = sanitize_text_field( $params['category'] ?? '' );
    if ( $category ) {
        $tax_query[] = [
            'taxonomy' => 'asset_category',
            'field'    => 'slug',
            'terms'    => explode( ',', $category ),
        ];
    }

    $tags = sanitize_text_field( $params['tags'] ?? '' );
    if ( $tags ) {
        $tax_query[] = [
            'taxonomy' => 'asset_tag',
            'field'    => 'slug',
            'terms'    => explode( ',', $tags ),
        ];
    }

    if ( ! empty( $tax_query ) ) {
        $args['tax_query'] = array_merge( [ 'relation' => 'AND' ], $tax_query );
    }

    // Meta filters
    $meta_query = [];

    $is_free = sanitize_text_field( $params['is_free'] ?? '' );
    if ( $is_free === '1' ) {
        $meta_query[] = [ 'key' => 'scm_is_free', 'value' => '1', 'compare' => '=' ];
    }

    $is_premium = sanitize_text_field( $params['is_premium'] ?? '' );
    if ( $is_premium === '1' ) {
        $meta_query[] = [ 'key' => 'scm_is_premium', 'value' => '1', 'compare' => '=' ];
    }

    $file_format = sanitize_text_field( $params['file_format'] ?? '' );
    if ( $file_format ) {
        $meta_query[] = [ 'key' => 'scm_file_format', 'value' => $file_format, 'compare' => 'LIKE' ];
    }

    $orientation = sanitize_text_field( $params['orientation'] ?? '' );
    if ( $orientation ) {
        $meta_query[] = [ 'key' => 'scm_orientation', 'value' => $orientation, 'compare' => '=' ];
    }

    $color = sanitize_text_field( $params['color'] ?? '' );
    if ( $color ) {
        $meta_query[] = [ 'key' => 'scm_color_palette', 'value' => $color, 'compare' => 'LIKE' ];
    }

    $is_editable = sanitize_text_field( $params['is_editable'] ?? '' );
    if ( $is_editable === '1' ) {
        $meta_query[] = [ 'key' => 'scm_is_editable', 'value' => '1', 'compare' => '=' ];
    }

    $is_featured = sanitize_text_field( $params['is_featured'] ?? '' );
    if ( $is_featured === '1' ) {
        $meta_query[] = [ 'key' => 'scm_is_featured', 'value' => '1', 'compare' => '=' ];
    }

    $price_min = floatval( $params['price_min'] ?? 0 );
    $price_max = floatval( $params['price_max'] ?? 0 );
    if ( $price_max > 0 ) {
        $meta_query[] = [ 'key' => 'scm_regular_price', 'value' => [ $price_min, $price_max ], 'type' => 'DECIMAL', 'compare' => 'BETWEEN' ];
    }

    if ( ! empty( $meta_query ) ) {
        $args['meta_query'] = array_merge( [ 'relation' => 'AND' ], $meta_query );
    }

    // Sorting
    switch ( $orderby ) {
        case 'popular':
            $args['meta_key'] = 'scm_view_count';
            $args['orderby']  = 'meta_value_num';
            $args['order']    = 'DESC';
            break;
        case 'downloads':
            $args['meta_key'] = 'scm_download_count';
            $args['orderby']  = 'meta_value_num';
            $args['order']    = 'DESC';
            break;
        case 'featured':
            $args['meta_key'] = 'scm_is_featured';
            $args['orderby']  = 'meta_value';
            $args['order']    = 'DESC';
            break;
        case 'title':
            $args['orderby'] = 'title';
            $args['order']   = 'ASC';
            break;
        default: // date
            $args['orderby'] = 'date';
            $args['order']   = 'DESC';
    }

    return $args;
}

function scm_ajax_get_similar() {
    check_ajax_referer( 'scm_nonce', 'nonce' );
    $post_id = intval( $_POST['asset_id'] ?? 0 );
    if ( ! $post_id ) {
        wp_send_json_error( [ 'message' => esc_html__( 'Invalid asset ID', 'scm' ) ] );
    }

    $terms = wp_get_object_terms( $post_id, [ 'asset_type', 'asset_category' ], [ 'fields' => 'ids' ] );
    
    ob_start();
    if ( ! empty( $terms ) ) {
        $query = new WP_Query( [
            'post_type'      => 'stock_asset',
            'posts_per_page' => 4,
            'post__not_in'   => [ $post_id ],
            'tax_query'      => [
                'relation' => 'OR',
                [ 'taxonomy' => 'asset_type', 'field' => 'id', 'terms' => $terms, 'operator' => 'IN' ],
                [ 'taxonomy' => 'asset_category', 'field' => 'id', 'terms' => $terms, 'operator' => 'IN' ],
            ],
        ] );
        if ( $query->have_posts() ) {
            while ( $query->have_posts() ) {
                $query->the_post();
                scm_render_asset_card( get_the_ID() );
            }
            wp_reset_postdata();
        } else {
            echo '<p class="scm-no-similar-found">' . esc_html__( 'No similar assets found.', 'scm' ) . '</p>';
        }
    } else {
        echo '<p class="scm-no-similar-found">' . esc_html__( 'No similar assets found.', 'scm' ) . '</p>';
    }
    $html = ob_get_clean();
    wp_send_json_success( [ 'html' => $html ] );
}

function scm_ajax_get_quick_view() {
    check_ajax_referer( 'scm_nonce', 'nonce' );
    $post_id = intval( $_POST['asset_id'] ?? 0 );
    if ( ! $post_id ) {
        wp_send_json_error( [ 'message' => esc_html__( 'Invalid asset ID', 'scm' ) ] );
    }

    $meta        = scm_get_all_meta( $post_id );
    $is_premium  = $meta['is_premium'] === '1';
    $is_free     = $meta['is_free'] === '1' || ! $is_premium;
    $price_html  = scm_get_price_html( $post_id );
    $dl_button   = scm_get_download_button_html( $post_id );
    $thumb       = get_the_post_thumbnail_url( $post_id, 'full' ) ?: get_post_meta( $post_id, 'scm_thumbnail_url', true );
    $featured_media = $meta['featured_media'] ?? 'image';
    $featured_video_id = ! empty( $meta['featured_video_id'] ) ? intval( $meta['featured_video_id'] ) : 0;
    
    ob_start();
    ?>
    <div class="scm-qv-wrapper">
        <!-- Main Media Preview Section -->
        <div class="scm-qv-preview">
            <?php if ( $featured_media === 'video' && $featured_video_id ) : 
                $video_src = wp_get_attachment_url( $featured_video_id );
            ?>
                <div class="scm-qv-media-wrap video">
                    <video controls autoplay loop playsinline poster="<?php echo esc_url( $thumb ); ?>">
                        <source src="<?php echo esc_url( $video_src ); ?>" type="<?php echo esc_attr( get_post_mime_type( $featured_video_id ) ); ?>" />
                    </video>
                </div>
            <?php elseif ( ! empty( $meta['video_preview_url'] ) ) : ?>
                <div class="scm-qv-media-wrap video">
                    <video controls autoplay loop playsinline poster="<?php echo esc_url( $thumb ); ?>">
                        <source src="<?php echo esc_url( $meta['video_preview_url'] ); ?>" type="<?php echo esc_attr( wp_check_filetype( $meta['video_preview_url'] )['type'] ?? '' ); ?>" />
                    </video>
                </div>
            <?php elseif ( $thumb ) : ?>
                <div class="scm-qv-media-wrap image">
                    <img src="<?php echo esc_url( $thumb ); ?>" alt="<?php echo esc_attr( get_the_title( $post_id ) ); ?>" />
                </div>
            <?php endif; ?>
        </div>

        <!-- Info & Action Section -->
        <div class="scm-qv-info">
            <div class="scm-qv-header">
                <h2 class="scm-qv-title"><?php echo esc_html( get_the_title( $post_id ) ); ?></h2>
                <div class="scm-qv-badges">
                    <?php if ( $is_free ) : ?>
                        <span class="scm-badge scm-badge-free"><?php esc_html_e( 'Free Asset', 'scm' ); ?></span>
                    <?php elseif ( $is_premium ) : ?>
                        <span class="scm-badge scm-badge-premium"><span class="scm-crown-icon">&#9812;</span> <?php esc_html_e( 'Premium Asset', 'scm' ); ?></span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Price & Button (Download or Premium Buy) -->
            <div class="scm-qv-actions">
                <div class="scm-qv-price-block">
                    <?php echo $price_html; ?>
                </div>
                <div class="scm-qv-cta-block">
                    <?php echo $dl_button; ?>
                </div>
            </div>
        </div>

        <!-- Related Products Section at the bottom of popup -->
        <div class="scm-qv-related">
            <h3><?php esc_html_e( 'Related Assets', 'scm' ); ?></h3>
            <div class="scm-qv-related-grid">
                <?php
                $terms = wp_get_object_terms( $post_id, [ 'asset_type', 'asset_category' ], [ 'fields' => 'ids' ] );
                if ( ! empty( $terms ) ) {
                    $related_query = new WP_Query( [
                        'post_type'      => 'stock_asset',
                        'posts_per_page' => 4,
                        'post__not_in'   => [ $post_id ],
                        'tax_query'      => [
                            'relation' => 'OR',
                            [ 'taxonomy' => 'asset_type', 'field' => 'id', 'terms' => $terms, 'operator' => 'IN' ],
                            [ 'taxonomy' => 'asset_category', 'field' => 'id', 'terms' => $terms, 'operator' => 'IN' ],
                        ],
                    ] );
                    if ( $related_query->have_posts() ) {
                        while ( $related_query->have_posts() ) {
                            $related_query->the_post();
                            scm_render_asset_card( get_the_ID() );
                        }
                        wp_reset_postdata();
                    } else {
                        echo '<p class="scm-no-related-found">' . esc_html__( 'No related assets found.', 'scm' ) . '</p>';
                    }
                } else {
                    echo '<p class="scm-no-related-found">' . esc_html__( 'No related assets found.', 'scm' ) . '</p>';
                }
                ?>
            </div>
        </div>
    </div>
    <?php
    $html = ob_get_clean();
    wp_send_json_success( [ 'html' => $html ] );
}

function scm_render_asset_card( $post_id = null ) {
    if ( ! $post_id ) $post_id = get_the_ID();
    $meta       = scm_get_all_meta( $post_id );
    $is_premium = $meta['is_premium'] === '1';
    $is_free    = $meta['is_free'] === '1' || ! $is_premium;
    $price      = floatval( $meta['sale_price'] ?: $meta['regular_price'] );
    $reg_price  = floatval( $meta['regular_price'] );
    $currency   = $meta['currency'] ?: 'USD';
    $symbol     = scm_currency_symbol( $currency );
    $dl_count   = intval( get_post_meta( $post_id, 'scm_download_count', true ) );
    $favorited  = scm_is_favorited( $post_id );
    $types      = get_the_terms( $post_id, 'asset_type' );
    $categories = get_the_terms( $post_id, 'asset_category' );
    $type_name  = $types && ! is_wp_error( $types ) ? esc_html( $types[0]->name ) : '';
    $thumb      = get_the_post_thumbnail_url( $post_id, 'large' ) ?: get_post_meta( $post_id, 'scm_thumbnail_url', true );
    $permalink  = get_permalink( $post_id );

    // Build Similar Discover URL — points to taxonomy archive (grid view of all similar assets)
    $similar_url = '';
    if ( $types && ! is_wp_error( $types ) ) {
        $term = $types[0];
        $tax_link = get_term_link( $term, 'asset_type' );
        $similar_url = ! is_wp_error( $tax_link ) ? $tax_link : get_post_type_archive_link( 'stock_asset' );
    } elseif ( $categories && ! is_wp_error( $categories ) ) {
        $term = $categories[0];
        $tax_link = get_term_link( $term, 'asset_category' );
        $similar_url = ! is_wp_error( $tax_link ) ? $tax_link : get_post_type_archive_link( 'stock_asset' );
    } else {
        $similar_url = get_post_type_archive_link( 'stock_asset' );
    }
    ?>
    <div class="scm-card" data-id="<?php echo esc_attr( $post_id ); ?>">
        <div class="scm-card-thumb">
            <?php if ( $thumb ) : ?>
                <img src="<?php echo esc_url( $thumb ); ?>" alt="<?php echo esc_attr( get_the_title( $post_id ) ); ?>" loading="lazy" />
            <?php else : ?>
                <div class="scm-card-thumb-placeholder"><span class="dashicons dashicons-format-image"></span></div>
            <?php endif; ?>

            <?php if ( ! empty( $meta['video_preview_url'] ) ) : ?>
                <video class="scm-card-video-preview" src="<?php echo esc_url( $meta['video_preview_url'] ); ?>" muted loop preload="metadata" <?php echo $thumb ? 'poster="' . esc_url( $thumb ) . '"' : ''; ?>></video>
                <span class="scm-video-duration"></span>
            <?php endif; ?>

            <?php if ( $is_premium ) : ?>
                <div class="scm-crown-badge" title="<?php esc_attr_e( 'Premium', 'scm' ); ?>">
                    <span class="scm-crown-icon">&#9812;</span>
                </div>
            <?php endif; ?>

            <?php if ( $meta['is_featured'] === '1' ) : ?>
                <div class="scm-featured-badge"><?php esc_html_e( 'Featured', 'scm' ); ?></div>
            <?php endif; ?>

            <div class="scm-card-actions">
                <button class="scm-btn-favorite <?php echo $favorited ? 'is-favorited' : ''; ?>" data-id="<?php echo esc_attr( $post_id ); ?>" title="<?php esc_attr_e( 'Favorite', 'scm' ); ?>">
                    <span class="dashicons dashicons-heart"></span>
                </button>
                <button class="scm-btn-collection" data-id="<?php echo esc_attr( $post_id ); ?>" title="<?php esc_attr_e( 'Add to Collection', 'scm' ); ?>">
                    <span class="dashicons dashicons-plus-alt"></span>
                </button>
                <a class="scm-btn-similar" href="<?php echo esc_url( $similar_url ); ?>" title="<?php esc_attr_e( 'Similar Discover', 'scm' ); ?>">
                    <span class="dashicons dashicons-search"></span>
                </a>
                <button class="scm-btn-download" data-id="<?php echo esc_attr( $post_id ); ?>" data-premium="<?php echo $is_premium ? '1' : '0'; ?>" title="<?php esc_attr_e( 'Download', 'scm' ); ?>">
                    <span class="dashicons dashicons-download"></span>
                </button>
            </div>
        </div>

        <div class="scm-card-info">
            <h3 class="scm-card-title"><a href="<?php echo esc_url( $permalink ); ?>"><?php echo esc_html( get_the_title( $post_id ) ); ?></a></h3>
            <div class="scm-card-meta">
                <?php if ( $type_name ) : ?>
                    <span class="scm-type-badge"><?php echo esc_html( $type_name ); ?></span>
                <?php endif; ?>
                <span class="scm-dl-count"><span class="dashicons dashicons-download"></span> <?php echo esc_html( number_format( $dl_count ) ); ?></span>
                <span class="scm-price-badge <?php echo $is_free ? 'is-free' : 'is-premium'; ?>">
                    <?php if ( $is_free ) : ?>
                        <?php esc_html_e( 'Free', 'scm' ); ?>
                    <?php elseif ( $meta['sale_price'] && $meta['sale_price'] < $reg_price ) : ?>
                        <del><?php echo esc_html( $symbol . number_format( $reg_price, 2 ) ); ?></del>
                        <?php echo esc_html( $symbol . number_format( $price, 2 ) ); ?>
                    <?php else : ?>
                        <?php echo esc_html( $symbol . number_format( $price, 2 ) ); ?>
                    <?php endif; ?>
                </span>
            </div>
        </div>
    </div>
    <?php
}

function scm_currency_symbol( $currency ) {
    $symbols = [ 'USD' => '$', 'EUR' => '€', 'GBP' => '£', 'INR' => '₹', 'BDT' => '৳' ];
    return $symbols[ $currency ] ?? '$';
}

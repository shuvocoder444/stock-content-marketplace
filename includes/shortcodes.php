<?php
if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'init', 'scm_register_shortcodes' );

function scm_register_shortcodes() {
    $shortcodes = [
        'stock_home'          => 'scm_sc_home',
        'stock_search'        => 'scm_sc_search',
        'stock_photos'        => [ 'scm_sc_type', 'photos' ],
        'stock_videos'        => [ 'scm_sc_type', 'videos' ],
        'stock_mockups'       => [ 'scm_sc_type', 'mockups' ],
        'stock_icons'         => [ 'scm_sc_type', 'icons' ],
        'stock_fonts'         => [ 'scm_sc_type', 'fonts' ],
        'stock_vectors'       => [ 'scm_sc_type', 'vectors' ],
        'stock_templates'     => [ 'scm_sc_type', 'templates' ],
        'stock_psds'          => [ 'scm_sc_type', 'psds' ],
        'stock_music'         => [ 'scm_sc_type', 'music' ],
        'stock_sound_effects' => [ 'scm_sc_type', 'sound-effects' ],
        'stock_3d_models'     => [ 'scm_sc_type', '3d-models' ],
        'stock_category'      => 'scm_sc_category',
        'stock_single'        => 'scm_sc_single',
        'stock_related'       => 'scm_sc_related',
        'stock_favorites'     => 'scm_sc_favorites',
        'stock_collections'   => 'scm_sc_collections',
        'stock_pricing'       => 'scm_sc_pricing',
        'stock_buy_button'    => 'scm_sc_buy_button',
        'stock_price'         => 'scm_sc_price',
        'stock_premium_popup' => 'scm_sc_premium_popup',
    ];

    foreach ( $shortcodes as $tag => $callback ) {
        if ( is_array( $callback ) ) {
            $type = $callback[1];
            add_shortcode( $tag, function( $atts ) use ( $type ) {
                return scm_sc_type( $atts, $type );
            } );
        } else {
            add_shortcode( $tag, $callback );
        }
    }
}

function scm_sc_home( $atts ) {
    $atts = shortcode_atts( [ 'per_page' => 24, 'columns' => 4 ], $atts );
    ob_start();
    ?>
    <div class="scm-wrapper">
        <?php echo scm_render_search_form(); ?>
        <div class="scm-layout">
            <div class="scm-main">
                <?php echo scm_render_filter_bar(); ?>
                <div class="scm-grid scm-grid-cols-<?php echo intval( $atts['columns'] ); ?>" id="scm-asset-grid">
                    <?php
                    $query = new WP_Query( [
                        'post_type'      => 'stock_asset',
                        'posts_per_page' => intval( $atts['per_page'] ),
                        'post_status'    => 'publish',
                        'orderby'        => 'date',
                        'order'          => 'DESC',
                    ] );
                    while ( $query->have_posts() ) {
                        $query->the_post();
                        scm_render_asset_card();
                    }
                    wp_reset_postdata();
                    ?>
                </div>
                <?php echo scm_render_load_more_button(); ?>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

function scm_sc_type( $atts, $type_slug ) {
    ob_start();
    $term = get_term_by( 'slug', $type_slug, 'asset_type' );
    ?>
    <div class="scm-wrapper">
        <?php echo scm_render_search_form(); ?>
        <div class="scm-layout">
            <div class="scm-main">
                <?php if ( $term ) : ?>
                    <h2 class="scm-section-title"><?php echo esc_html( $term->name ); ?></h2>
                <?php endif; ?>
                <?php echo scm_render_filter_bar( [ 'asset_type' => $type_slug ] ); ?>
                <div class="scm-grid" id="scm-asset-grid" data-asset-type="<?php echo esc_attr( $type_slug ); ?>">
                    <?php
                    $query = new WP_Query( [
                        'post_type'      => 'stock_asset',
                        'posts_per_page' => 24,
                        'post_status'    => 'publish',
                        'tax_query'      => [[ 'taxonomy' => 'asset_type', 'field' => 'slug', 'terms' => $type_slug ]],
                    ] );
                    while ( $query->have_posts() ) { $query->the_post(); scm_render_asset_card(); }
                    wp_reset_postdata();
                    ?>
                </div>
                <?php echo scm_render_load_more_button(); ?>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

function scm_sc_category( $atts ) {
    $atts = shortcode_atts( [ 'id' => 0, 'slug' => '', 'per_page' => 24 ], $atts );
    ob_start();
    $tax_query = [];
    if ( $atts['id'] ) {
        $tax_query = [[ 'taxonomy' => 'asset_category', 'field' => 'id', 'terms' => intval( $atts['id'] ) ]];
    } elseif ( $atts['slug'] ) {
        $tax_query = [[ 'taxonomy' => 'asset_category', 'field' => 'slug', 'terms' => sanitize_key( $atts['slug'] ) ]];
    }
    ?>
    <div class="scm-wrapper">
        <div class="scm-grid" id="scm-asset-grid">
            <?php
            $query = new WP_Query( [ 'post_type' => 'stock_asset', 'posts_per_page' => intval( $atts['per_page'] ), 'tax_query' => $tax_query ] );
            while ( $query->have_posts() ) { $query->the_post(); scm_render_asset_card(); }
            wp_reset_postdata();
            ?>
        </div>
        <?php echo scm_render_load_more_button(); ?>
    </div>
    <?php
    return ob_get_clean();
}

function scm_sc_favorites( $atts ) {
    if ( ! is_user_logged_in() ) {
        return '<div class="scm-notice">' . esc_html__( 'Please log in to view your favorites.', 'scm' ) . '</div>';
    }
    $favorites = scm_get_user_favorites( get_current_user_id() );
    ob_start();
    echo '<div class="scm-wrapper"><h2>' . esc_html__( 'My Favorites', 'scm' ) . '</h2><div class="scm-grid">';
    if ( $favorites ) {
        foreach ( $favorites as $fav ) {
            global $post;
            $post = get_post( $fav->asset_id );
            setup_postdata( $post );
            scm_render_asset_card( $fav->asset_id );
        }
        wp_reset_postdata();
    } else {
        echo '<p>' . esc_html__( 'No favorites yet.', 'scm' ) . '</p>';
    }
    echo '</div></div>';
    return ob_get_clean();
}

function scm_sc_collections( $atts ) {
    if ( ! is_user_logged_in() ) {
        return '<div class="scm-notice">' . esc_html__( 'Please log in to view your collections.', 'scm' ) . '</div>';
    }
    $collections = scm_get_user_collections( get_current_user_id() );
    ob_start();
    ?>
    <div class="scm-wrapper">
        <div class="scm-collections-header">
            <h2><?php esc_html_e( 'My Collections', 'scm' ); ?></h2>
            <button class="scm-btn scm-btn-primary" id="scm-create-collection"><?php esc_html_e( '+ New Collection', 'scm' ); ?></button>
        </div>
        <div class="scm-collections-grid">
            <?php if ( $collections ) : ?>
                <?php foreach ( $collections as $col ) : ?>
                    <div class="scm-collection-card">
                        <h3><?php echo esc_html( $col->name ); ?></h3>
                        <p><?php echo esc_html( $col->description ); ?></p>
                        <span><?php printf( esc_html__( '%d items', 'scm' ), $col->item_count ); ?></span>
                        <div class="scm-collection-actions">
                            <button class="scm-btn scm-btn-sm scm-delete-collection" data-id="<?php echo esc_attr( $col->id ); ?>"><?php esc_html_e( 'Delete', 'scm' ); ?></button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else : ?>
                <p><?php esc_html_e( 'No collections yet. Create one!', 'scm' ); ?></p>
            <?php endif; ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

function scm_sc_pricing( $atts ) {
    ob_start();
    $plans = [
        [ 'name' => __( 'Free', 'scm' ), 'price' => '0', 'period' => '', 'features' => [ __( 'Access to free assets', 'scm' ), __( 'Standard resolution', 'scm' ), __( 'Personal license', 'scm' ) ], 'btn_text' => __( 'Get Started', 'scm' ), 'highlight' => false ],
        [ 'name' => __( 'Monthly Premium', 'scm' ), 'price' => '9.99', 'period' => '/mo', 'features' => [ __( 'Unlimited downloads', 'scm' ), __( 'High-resolution files', 'scm' ), __( 'Commercial license', 'scm' ), __( 'Priority support', 'scm' ) ], 'btn_text' => __( 'Subscribe Now', 'scm' ), 'highlight' => true ],
        [ 'name' => __( 'Yearly Premium', 'scm' ), 'price' => '79.99', 'period' => '/yr', 'features' => [ __( 'Everything in Monthly', 'scm' ), __( '33% savings', 'scm' ), __( 'Team access (up to 5)', 'scm' ), __( 'Extended license', 'scm' ) ], 'btn_text' => __( 'Subscribe Yearly', 'scm' ), 'highlight' => false ],
    ];
    ?>
    <div class="scm-pricing-table">
        <?php foreach ( $plans as $plan ) : ?>
            <div class="scm-pricing-card <?php echo $plan['highlight'] ? 'highlighted' : ''; ?>">
                <?php if ( $plan['highlight'] ) : ?><div class="scm-pricing-badge"><?php esc_html_e( 'Most Popular', 'scm' ); ?></div><?php endif; ?>
                <h3><?php echo esc_html( $plan['name'] ); ?></h3>
                <div class="scm-pricing-price">$<?php echo esc_html( $plan['price'] ); ?><span><?php echo esc_html( $plan['period'] ); ?></span></div>
                <ul>
                    <?php foreach ( $plan['features'] as $f ) : ?><li>✓ <?php echo esc_html( $f ); ?></li><?php endforeach; ?>
                </ul>
                <button class="scm-btn <?php echo $plan['highlight'] ? 'scm-btn-primary' : 'scm-btn-outline'; ?>"><?php echo esc_html( $plan['btn_text'] ); ?></button>
            </div>
        <?php endforeach; ?>
    </div>
    <?php
    return ob_get_clean();
}

function scm_sc_buy_button( $atts ) {
    $atts = shortcode_atts( [ 'id' => 0 ], $atts );
    $id   = intval( $atts['id'] );
    if ( ! $id ) return '';
    $meta = scm_get_all_meta( $id );
    $label = $meta['is_free'] === '1' ? __( 'Download Free', 'scm' ) : __( 'Buy Now', 'scm' );
    return '<button class="scm-btn scm-btn-primary scm-btn-download" data-id="' . esc_attr( $id ) . '" data-premium="' . ( $meta['is_premium'] === '1' ? '1' : '0' ) . '">' . esc_html( $label ) . '</button>';
}

function scm_sc_price( $atts ) {
    $atts  = shortcode_atts( [ 'id' => 0 ], $atts );
    $id    = intval( $atts['id'] );
    if ( ! $id ) return '';
    $meta  = scm_get_all_meta( $id );
    if ( $meta['is_free'] === '1' ) return '<span class="scm-price is-free">' . esc_html__( 'Free', 'scm' ) . '</span>';
    $sym   = scm_currency_symbol( $meta['currency'] );
    $price = $meta['sale_price'] ?: $meta['regular_price'];
    return '<span class="scm-price">' . esc_html( $sym . number_format( floatval( $price ), 2 ) ) . '</span>';
}

function scm_sc_search( $atts ) {
    return scm_render_search_form();
}

function scm_sc_related( $atts ) {
    $atts    = shortcode_atts( [ 'id' => get_the_ID(), 'limit' => 6 ], $atts );
    $post_id = intval( $atts['id'] );
    $terms   = wp_get_object_terms( $post_id, [ 'asset_type', 'asset_category' ], [ 'fields' => 'ids' ] );
    ob_start();
    if ( ! empty( $terms ) ) {
        $query = new WP_Query( [
            'post_type'      => 'stock_asset',
            'posts_per_page' => intval( $atts['limit'] ),
            'post__not_in'   => [ $post_id ],
            'tax_query'      => [[ 'taxonomy' => 'asset_type', 'field' => 'id', 'terms' => $terms, 'operator' => 'IN' ]],
        ] );
        if ( $query->have_posts() ) {
            echo '<div class="scm-grid scm-related-grid">';
            while ( $query->have_posts() ) { $query->the_post(); scm_render_asset_card(); }
            echo '</div>';
            wp_reset_postdata();
        }
    }
    return ob_get_clean();
}

function scm_sc_premium_popup( $atts ) {
    $atts = shortcode_atts( [ 'id' => 0 ], $atts );
    $id   = intval( $atts['id'] );
    ob_start();
    scm_render_premium_popup( $id );
    return ob_get_clean();
}

// Helpers
function scm_render_search_form() {
    ob_start();
    ?>
    <div class="scm-search-bar">
        <form class="scm-search-form" id="scm-search-form" role="search">
            <div class="scm-search-inner">
                <span class="scm-search-icon dashicons dashicons-search"></span>
                <input type="search" id="scm-search-input" placeholder="<?php esc_attr_e( 'Search photos, videos, mockups...', 'scm' ); ?>" autocomplete="off" />
                <button type="submit" class="scm-btn scm-btn-primary"><?php esc_html_e( 'Search', 'scm' ); ?></button>
            </div>
        </form>
    </div>
    <?php
    return ob_get_clean();
}

function scm_render_sidebar() {
    $types = get_terms( [ 'taxonomy' => 'asset_type', 'hide_empty' => false ] );
    $cats  = get_terms( [ 'taxonomy' => 'asset_category', 'parent' => 0, 'hide_empty' => false ] );
    ob_start();
    ?>
    <aside class="scm-sidebar">
        <div class="scm-sidebar-section">
            <h4><?php esc_html_e( 'Asset Type', 'scm' ); ?></h4>
            <ul class="scm-filter-list">
                <li><a href="#" class="scm-filter-link active" data-filter="asset_type" data-value=""><?php esc_html_e( 'All Types', 'scm' ); ?></a></li>
                <?php if ( $types && ! is_wp_error( $types ) ) : ?>
                    <?php foreach ( $types as $type ) : ?>
                        <li><a href="#" class="scm-filter-link" data-filter="asset_type" data-value="<?php echo esc_attr( $type->slug ); ?>"><?php echo esc_html( $type->name ); ?> <span class="scm-count">(<?php echo esc_html( $type->count ); ?>)</span></a></li>
                    <?php endforeach; ?>
                <?php endif; ?>
            </ul>
        </div>

        <div class="scm-sidebar-section">
            <h4><?php esc_html_e( 'Categories', 'scm' ); ?></h4>
            <ul class="scm-filter-list">
                <?php if ( $cats && ! is_wp_error( $cats ) ) : ?>
                    <?php foreach ( $cats as $cat ) : ?>
                        <li>
                            <a href="#" class="scm-filter-link" data-filter="category" data-value="<?php echo esc_attr( $cat->slug ); ?>"><?php echo esc_html( $cat->name ); ?></a>
                            <?php
                            $children = get_terms( [ 'taxonomy' => 'asset_category', 'parent' => $cat->term_id, 'hide_empty' => false ] );
                            if ( $children && ! is_wp_error( $children ) ) :
                            ?>
                                <ul class="scm-filter-sub">
                                    <?php foreach ( $children as $child ) : ?>
                                        <li><a href="#" class="scm-filter-link" data-filter="category" data-value="<?php echo esc_attr( $child->slug ); ?>"><?php echo esc_html( $child->name ); ?></a></li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                <?php endif; ?>
            </ul>
        </div>

        <div class="scm-sidebar-section">
            <h4><?php esc_html_e( 'Access', 'scm' ); ?></h4>
            <ul class="scm-filter-list">
                <li><a href="#" class="scm-filter-link" data-filter="is_free" data-value="1"><?php esc_html_e( 'Free Only', 'scm' ); ?></a></li>
                <li><a href="#" class="scm-filter-link" data-filter="is_premium" data-value="1"><?php esc_html_e( 'Premium Only', 'scm' ); ?></a></li>
            </ul>
        </div>
    </aside>
    <?php
    return ob_get_clean();
}

function scm_render_filter_bar( $defaults = [] ) {
    $types = get_terms( [ 'taxonomy' => 'asset_type', 'hide_empty' => false ] );
    ob_start();
    ?>
    <div class="scm-horizontal-filters">
        <div class="scm-filters-main">
            <!-- All Images / Types -->
            <div class="scm-filter-dropdown">
                <button class="scm-dropdown-btn"><?php esc_html_e('All Images', 'scm'); ?> <span class="dashicons dashicons-arrow-down-alt2"></span></button>
                <div class="scm-dropdown-content">
                    <a href="#" class="scm-filter-link active" data-filter="asset_type" data-value=""><?php esc_html_e('All Images', 'scm'); ?></a>
                    <?php if ( $types && ! is_wp_error( $types ) ) : foreach ( $types as $type ) : ?>
                        <a href="#" class="scm-filter-link" data-filter="asset_type" data-value="<?php echo esc_attr( $type->slug ); ?>"><?php echo esc_html( $type->name ); ?></a>
                    <?php endforeach; endif; ?>
                </div>
            </div>

            <!-- License -->
            <div class="scm-filter-dropdown">
                <button class="scm-dropdown-btn"><?php esc_html_e('License', 'scm'); ?> <span class="dashicons dashicons-arrow-down-alt2"></span></button>
                <div class="scm-dropdown-content">
                    <a href="#" class="scm-filter-link" data-filter="is_free" data-value="1"><?php esc_html_e('Free', 'scm'); ?></a>
                    <a href="#" class="scm-filter-link" data-filter="is_premium" data-value="1"><?php esc_html_e('Premium', 'scm'); ?></a>
                </div>
            </div>

            <!-- Orientation -->
            <div class="scm-filter-dropdown">
                <button class="scm-dropdown-btn"><?php esc_html_e('Orientation', 'scm'); ?> <span class="dashicons dashicons-arrow-down-alt2"></span></button>
                <div class="scm-dropdown-content">
                    <a href="#" class="scm-filter-link" data-filter="orientation" data-value="landscape"><?php esc_html_e('Horizontal', 'scm'); ?></a>
                    <a href="#" class="scm-filter-link" data-filter="orientation" data-value="portrait"><?php esc_html_e('Vertical', 'scm'); ?></a>
                    <a href="#" class="scm-filter-link" data-filter="orientation" data-value="square"><?php esc_html_e('Square', 'scm'); ?></a>
                </div>
            </div>

            <!-- Color -->
            <div class="scm-filter-dropdown">
                <button class="scm-dropdown-btn"><?php esc_html_e('Color', 'scm'); ?> <span class="dashicons dashicons-arrow-down-alt2"></span></button>
                <div class="scm-dropdown-content scm-color-dropdown">
                    <a href="#" class="scm-filter-link" data-filter="color" data-value="red"><span class="scm-color-circle" style="background:#ef4444"></span> <?php esc_html_e('Red', 'scm'); ?></a>
                    <a href="#" class="scm-filter-link" data-filter="color" data-value="blue"><span class="scm-color-circle" style="background:#3b82f6"></span> <?php esc_html_e('Blue', 'scm'); ?></a>
                    <a href="#" class="scm-filter-link" data-filter="color" data-value="green"><span class="scm-color-circle" style="background:#10b981"></span> <?php esc_html_e('Green', 'scm'); ?></a>
                    <a href="#" class="scm-filter-link" data-filter="color" data-value="yellow"><span class="scm-color-circle" style="background:#f59e0b"></span> <?php esc_html_e('Yellow', 'scm'); ?></a>
                    <a href="#" class="scm-filter-link" data-filter="color" data-value="black"><span class="scm-color-circle" style="background:#000000"></span> <?php esc_html_e('Black', 'scm'); ?></a>
                    <a href="#" class="scm-filter-link" data-filter="color" data-value="white"><span class="scm-color-circle" style="border:1px solid #e5e7eb;background:#ffffff"></span> <?php esc_html_e('White', 'scm'); ?></a>
                </div>
            </div>

            <!-- People -->
            <div class="scm-filter-dropdown">
                <button class="scm-dropdown-btn"><?php esc_html_e('People', 'scm'); ?> <span class="dashicons dashicons-arrow-down-alt2"></span></button>
                <div class="scm-dropdown-content">
                    <a href="#" class="scm-filter-link" data-filter="people" data-value="with_people"><?php esc_html_e('With People', 'scm'); ?></a>
                    <a href="#" class="scm-filter-link" data-filter="people" data-value="without_people"><?php esc_html_e('Without People', 'scm'); ?></a>
                </div>
            </div>

            <!-- File type -->
            <div class="scm-filter-dropdown">
                <button class="scm-dropdown-btn"><?php esc_html_e('File type', 'scm'); ?> <span class="dashicons dashicons-arrow-down-alt2"></span></button>
                <div class="scm-dropdown-content">
                    <a href="#" class="scm-filter-link" data-filter="file_format" data-value="JPG">JPG</a>
                    <a href="#" class="scm-filter-link" data-filter="file_format" data-value="PNG">PNG</a>
                    <a href="#" class="scm-filter-link" data-filter="file_format" data-value="PSD">PSD</a>
                    <a href="#" class="scm-filter-link" data-filter="file_format" data-value="EPS">EPS / Vector</a>
                    <a href="#" class="scm-filter-link" data-filter="file_format" data-value="MP4">MP4</a>
                </div>
            </div>

            <!-- Editable online -->
            <div class="scm-filter-dropdown">
                <button class="scm-dropdown-btn"><?php esc_html_e('Editable online', 'scm'); ?> <span class="dashicons dashicons-arrow-down-alt2"></span></button>
                <div class="scm-dropdown-content">
                    <a href="#" class="scm-filter-link" data-filter="is_editable" data-value="1"><?php esc_html_e('Yes', 'scm'); ?></a>
                </div>
            </div>

            <!-- Advanced -->
            <div class="scm-filter-dropdown">
                <button class="scm-dropdown-btn"><?php esc_html_e('Advanced', 'scm'); ?> <span class="dashicons dashicons-arrow-down-alt2"></span></button>
                <div class="scm-dropdown-content">
                    <a href="#" class="scm-filter-link" data-filter="is_featured" data-value="1"><?php esc_html_e('Featured Only', 'scm'); ?></a>
                </div>
            </div>
        </div>
        
        <div class="scm-filter-bar-bottom">
            <div class="scm-filter-bar-left">
                <span class="scm-results-count" id="scm-results-count"></span>
            </div>
            <div class="scm-filter-bar-right">
                <label for="scm-sort"><?php esc_html_e( 'Sort:', 'scm' ); ?></label>
                <select id="scm-sort" class="scm-select">
                    <option value="date"><?php esc_html_e( 'Latest', 'scm' ); ?></option>
                    <option value="popular"><?php esc_html_e( 'Most Viewed', 'scm' ); ?></option>
                    <option value="downloads"><?php esc_html_e( 'Most Downloaded', 'scm' ); ?></option>
                    <option value="featured"><?php esc_html_e( 'Featured', 'scm' ); ?></option>
                </select>
                <div class="scm-view-toggle">
                    <button class="scm-btn-icon active" data-view="grid" title="Grid View"><span class="dashicons dashicons-grid-view"></span></button>
                    <button class="scm-btn-icon" data-view="list" title="List View"><span class="dashicons dashicons-list-view"></span></button>
                </div>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

function scm_render_load_more_button() {
    return '<div class="scm-load-more-wrap"><button class="scm-btn scm-btn-outline" id="scm-load-more" data-page="1">' . esc_html__( 'Load More', 'scm' ) . '</button></div>';
}

function scm_render_premium_popup( $asset_id = 0 ) {
    $meta     = $asset_id ? scm_get_all_meta( $asset_id ) : [];
    $price    = $meta ? floatval( $meta['sale_price'] ?: $meta['regular_price'] ) : 0;
    $currency = $meta['currency'] ?? 'USD';
    $symbol   = scm_currency_symbol( $currency );
    ?>
    <div class="scm-modal" id="scm-premium-popup" style="display:none;" aria-modal="true" role="dialog">
        <div class="scm-modal-overlay"></div>
        <div class="scm-modal-box">
            <button class="scm-modal-close" aria-label="<?php esc_attr_e( 'Close', 'scm' ); ?>">&times;</button>
            <div class="scm-modal-crown">&#9812;</div>
            <h2><?php esc_html_e( 'Premium Asset', 'scm' ); ?></h2>
            <p><?php esc_html_e( 'Get unlimited downloads with a Premium plan or purchase this asset individually.', 'scm' ); ?></p>
            <div class="scm-modal-price" id="scm-modal-price"><?php echo $price ? esc_html( $symbol . number_format( $price, 2 ) ) : ''; ?></div>
            <ul class="scm-modal-benefits">
                <li>✓ <?php esc_html_e( 'Unlimited downloads', 'scm' ); ?></li>
                <li>✓ <?php esc_html_e( 'Commercial license', 'scm' ); ?></li>
                <li>✓ <?php esc_html_e( 'High-resolution files', 'scm' ); ?></li>
                <li>✓ <?php esc_html_e( 'Priority support', 'scm' ); ?></li>
            </ul>
            <div class="scm-modal-actions">
                <button class="scm-btn scm-btn-primary scm-btn-buy" id="scm-btn-buy-now"><?php esc_html_e( 'Buy Now', 'scm' ); ?></button>
                <button class="scm-btn scm-btn-outline scm-btn-subscribe"><?php esc_html_e( 'Subscribe Now', 'scm' ); ?></button>
            </div>
        </div>
    </div>
    <?php
}
add_action( 'wp_footer', 'scm_render_global_modals' );
function scm_render_global_modals() {
    scm_render_premium_popup();
    // Collection picker modal
    ?>
    <div class="scm-modal" id="scm-collection-modal" style="display:none;">
        <div class="scm-modal-overlay"></div>
        <div class="scm-modal-box">
            <button class="scm-modal-close">&times;</button>
            <h2><?php esc_html_e( 'Add to Collection', 'scm' ); ?></h2>
            <div id="scm-collection-list"></div>
            <div class="scm-collection-new">
                <input type="text" id="scm-new-collection-name" placeholder="<?php esc_attr_e( 'New collection name...', 'scm' ); ?>" />
                <button class="scm-btn scm-btn-primary" id="scm-create-new-collection"><?php esc_html_e( 'Create & Add', 'scm' ); ?></button>
            </div>
        </div>
    </div>
    <!-- Preview modal -->
    <div class="scm-modal" id="scm-preview-modal" style="display:none;">
        <div class="scm-modal-overlay"></div>
        <div class="scm-modal-box scm-preview-box">
            <button class="scm-modal-close">&times;</button>
            <div id="scm-preview-content"></div>
        </div>
    </div>
    <?php
}

<?php
/**
 * Single stock_asset template.
 */
if ( ! defined( 'ABSPATH' ) ) exit;
get_header();

while ( have_posts() ) : the_post();

$post_id     = get_the_ID();
$meta        = scm_get_all_meta( $post_id );
$is_premium  = $meta['is_premium'] === '1';
$is_free     = $meta['is_free'] === '1' || ! $is_premium;
$dl_count    = intval( get_post_meta( $post_id, 'scm_download_count', true ) );
$view_count  = intval( get_post_meta( $post_id, 'scm_view_count', true ) );
$types       = get_the_terms( $post_id, 'asset_type' );
$categories  = get_the_terms( $post_id, 'asset_category' );
$tags        = get_the_terms( $post_id, 'asset_tag' );
$thumb       = get_the_post_thumbnail_url( $post_id, 'full' ) ?: get_post_meta( $post_id, 'scm_thumbnail_url', true );
$featured_media = $meta['featured_media'] ?? 'image';
$featured_video_id = ! empty( $meta['featured_video_id'] ) ? intval( $meta['featured_video_id'] ) : 0;
$gallery_ids = array_filter( explode( ',', $meta['gallery_ids'] ?? '' ) );
$author_name = $meta['author_name'] ?: get_the_author_meta( 'display_name' );
$author_url  = $meta['author_url'] ?: get_author_posts_url( get_the_author_meta( 'ID' ) );
$price_html  = scm_get_price_html( $post_id );
$dl_button   = scm_get_download_button_html( $post_id );
$favorited   = scm_is_favorited( $post_id );
?>

<div class="scm-single-wrapper" data-id="<?php echo esc_attr( $post_id ); ?>">
    <div class="scm-single-breadcrumbs"><?php echo scm_get_breadcrumbs(); ?></div>

    <div class="scm-single-layout">

        <!-- LEFT: Preview -->
        <div class="scm-single-preview">
            <?php if ( $featured_media === 'video' && $featured_video_id ) :
                $video_src = wp_get_attachment_url( $featured_video_id );
                $container_style = $thumb ? 'style="background-image: url(' . esc_url( $thumb ) . ');"' : '';
            ?>
                <div class="scm-preview-video" <?php echo $container_style; ?>>
                    <div class="scm-preview-video-poster" <?php echo $thumb ? 'style="background-image: url(' . esc_url( $thumb ) . ');"' : ''; ?>></div>
                    <div class="scm-preview-video-overlay">
                        <span class="scm-preview-play-icon"></span>
                    </div>
                    <video controls muted loop playsinline preload="none" poster="<?php echo esc_url( $thumb ); ?>">
                        <source src="<?php echo esc_url( $video_src ); ?>" type="<?php echo esc_attr( get_post_mime_type( $featured_video_id ) ); ?>" />
                    </video>
                </div>
            <?php elseif ( ! empty( $meta['video_preview_url'] ) ) :
                $container_style = $thumb ? 'style="background-image: url(' . esc_url( $thumb ) . ');"' : '';
            ?>
                <div class="scm-preview-video" <?php echo $container_style; ?>>
                    <div class="scm-preview-video-poster" <?php echo $thumb ? 'style="background-image: url(' . esc_url( $thumb ) . ');"' : ''; ?>></div>
                    <div class="scm-preview-video-overlay">
                        <span class="scm-preview-play-icon"></span>
                    </div>
                    <video controls muted loop playsinline preload="none" poster="<?php echo esc_url( $thumb ); ?>">
                        <source src="<?php echo esc_url( $meta['video_preview_url'] ); ?>" type="<?php echo esc_attr( wp_check_filetype( $meta['video_preview_url'] )['type'] ?? '' ); ?>" />
                    </video>
                </div>
            <?php elseif ( $thumb ) : ?>
                <div class="scm-preview-image">
                    <img src="<?php echo esc_url( $thumb ); ?>" alt="<?php echo esc_attr( get_the_title() ); ?>" />
                    <?php if ( $is_premium ) : ?>
                        <div class="scm-preview-crown"><span class="scm-crown-icon">&#9812;</span> <?php esc_html_e( 'Premium', 'scm' ); ?></div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- Gallery -->
            <?php if ( $gallery_ids ) : ?>
                <div class="scm-preview-gallery">
                    <?php foreach ( $gallery_ids as $gid ) : ?>
                        <div class="scm-gallery-thumb">
                            <?php echo wp_get_attachment_image( intval( $gid ), 'thumbnail' ); ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- Similar / Related -->
            <div class="scm-single-related">
                <h3><?php esc_html_e( 'Similar Assets', 'scm' ); ?></h3>
                <?php echo do_shortcode( '[stock_related id="' . $post_id . '" limit="6"]' ); ?>
            </div>
        </div>

        <!-- RIGHT: Info Panel -->
        <div class="scm-single-info">

            <!-- Badges -->
            <div class="scm-single-badges">
                <?php if ( $types && ! is_wp_error( $types ) ) : foreach ( $types as $t ) : ?>
                    <a href="<?php echo esc_url( get_term_link( $t ) ); ?>" class="scm-badge scm-badge-type"><?php echo esc_html( $t->name ); ?></a>
                <?php endforeach; endif; ?>
                <?php if ( $is_free ) : ?>
                    <span class="scm-badge scm-badge-free"><?php esc_html_e( 'Free', 'scm' ); ?></span>
                <?php elseif ( $is_premium ) : ?>
                    <span class="scm-badge scm-badge-premium"><span class="scm-crown-icon">&#9812;</span> <?php esc_html_e( 'Premium', 'scm' ); ?></span>
                <?php endif; ?>
                <?php if ( $meta['is_featured'] === '1' ) : ?>
                    <span class="scm-badge scm-badge-featured">⭐ <?php esc_html_e( 'Featured', 'scm' ); ?></span>
                <?php endif; ?>
            </div>

            <h1 class="scm-single-title"><?php the_title(); ?></h1>

            <!-- Stats -->
            <div class="scm-single-stats">
                <span><span class="dashicons dashicons-download"></span> <?php echo esc_html( number_format( $dl_count ) ); ?> <?php esc_html_e( 'Downloads', 'scm' ); ?></span>
                <span><span class="dashicons dashicons-visibility"></span> <?php echo esc_html( number_format( $view_count ) ); ?> <?php esc_html_e( 'Views', 'scm' ); ?></span>
            </div>

            <!-- Price -->
            <div class="scm-single-price"><?php echo $price_html; ?></div>

            <!-- CTA Buttons -->
            <div class="scm-single-actions">
                <?php echo $dl_button; ?>

                <button class="scm-btn scm-btn-outline scm-btn-favorite <?php echo $favorited ? 'is-favorited' : ''; ?>" data-id="<?php echo esc_attr( $post_id ); ?>">
                    <span class="dashicons dashicons-heart"></span> <?php echo $favorited ? esc_html__( 'Favorited', 'scm' ) : esc_html__( 'Add to Favorites', 'scm' ); ?>
                </button>

                <button class="scm-btn scm-btn-outline scm-btn-collection" data-id="<?php echo esc_attr( $post_id ); ?>">
                    <span class="dashicons dashicons-plus-alt"></span> <?php esc_html_e( 'Add to Collection', 'scm' ); ?>
                </button>
            </div>

            <!-- Share -->
            <div class="scm-single-share">
                <span><?php esc_html_e( 'Share:', 'scm' ); ?></span>
                <a href="https://twitter.com/intent/tweet?url=<?php echo rawurlencode( get_permalink() ); ?>&text=<?php echo rawurlencode( get_the_title() ); ?>" target="_blank" rel="noopener" class="scm-share-btn scm-share-twitter">Twitter/X</a>
                <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo rawurlencode( get_permalink() ); ?>" target="_blank" rel="noopener" class="scm-share-btn scm-share-facebook">Facebook</a>
                <a href="https://pinterest.com/pin/create/button/?url=<?php echo rawurlencode( get_permalink() ); ?>&media=<?php echo rawurlencode( $thumb ); ?>&description=<?php echo rawurlencode( get_the_title() ); ?>" target="_blank" rel="noopener" class="scm-share-btn scm-share-pinterest">Pinterest</a>
                <button class="scm-share-btn scm-copy-link" data-url="<?php echo esc_url( get_permalink() ); ?>"><?php esc_html_e( 'Copy Link', 'scm' ); ?></button>
            </div>

            <!-- File Details -->
            <div class="scm-single-details">
                <h3><?php esc_html_e( 'File Details', 'scm' ); ?></h3>
                <table class="scm-details-table">
                    <?php if ( $meta['file_format'] ) : ?>
                        <tr><th><?php esc_html_e( 'Format', 'scm' ); ?></th><td><?php echo esc_html( $meta['file_format'] ); ?></td></tr>
                    <?php endif; ?>
                    <?php if ( $meta['file_size'] ) : ?>
                        <tr><th><?php esc_html_e( 'File Size', 'scm' ); ?></th><td><?php echo esc_html( $meta['file_size'] ); ?></td></tr>
                    <?php endif; ?>
                    <?php if ( $meta['resolution'] ) : ?>
                        <tr><th><?php esc_html_e( 'Resolution', 'scm' ); ?></th><td><?php echo esc_html( $meta['resolution'] ); ?></td></tr>
                    <?php endif; ?>
                    <?php if ( $meta['dimensions'] ) : ?>
                        <tr><th><?php esc_html_e( 'Dimensions', 'scm' ); ?></th><td><?php echo esc_html( $meta['dimensions'] ); ?></td></tr>
                    <?php endif; ?>
                    <?php if ( $meta['duration'] ) : ?>
                        <tr><th><?php esc_html_e( 'Duration', 'scm' ); ?></th><td><?php echo esc_html( $meta['duration'] ); ?></td></tr>
                    <?php endif; ?>
                    <tr><th><?php esc_html_e( 'License', 'scm' ); ?></th><td><?php echo esc_html( ucfirst( $meta['license_type'] ?: 'Free' ) ); ?></td></tr>
                </table>
            </div>

            <!-- Author Info -->
            <?php if ( $author_name ) : ?>
                <div class="scm-single-author">
                    <?php echo get_avatar( get_the_author_meta( 'ID' ), 40 ); ?>
                    <div class="scm-author-text">
                        <span class="scm-author-label"><?php esc_html_e( 'By', 'scm' ); ?></span>
                        <a href="<?php echo esc_url( $author_url ); ?>" class="scm-author-name"><?php echo esc_html( $author_name ); ?></a>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Categories -->
            <?php if ( $categories && ! is_wp_error( $categories ) ) : ?>
                <div class="scm-single-terms">
                    <strong><?php esc_html_e( 'Categories:', 'scm' ); ?></strong>
                    <?php foreach ( $categories as $cat ) : ?>
                        <a href="<?php echo esc_url( get_term_link( $cat ) ); ?>" class="scm-term-link"><?php echo esc_html( $cat->name ); ?></a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- Tags -->
            <?php if ( $tags && ! is_wp_error( $tags ) ) : ?>
                <div class="scm-single-terms scm-single-tags">
                    <strong><?php esc_html_e( 'Tags:', 'scm' ); ?></strong>
                    <?php foreach ( $tags as $tag ) : ?>
                        <a href="<?php echo esc_url( get_term_link( $tag ) ); ?>" class="scm-tag-link"><?php echo esc_html( $tag->name ); ?></a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- Description -->
            <?php if ( get_the_content() ) : ?>
                <div class="scm-single-description">
                    <h3><?php esc_html_e( 'Description', 'scm' ); ?></h3>
                    <?php the_content(); ?>
                </div>
            <?php endif; ?>

        </div><!-- .scm-single-info -->
    </div><!-- .scm-single-layout -->

    <!-- More from Author -->
    <div class="scm-more-from-author">
        <h3><?php printf( esc_html__( 'More from %s', 'scm' ), esc_html( $author_name ) ); ?></h3>
        <div class="scm-grid">
            <?php
            $more_query = new WP_Query( [
                'post_type'      => 'stock_asset',
                'posts_per_page' => 6,
                'author'         => get_the_author_meta( 'ID' ),
                'post__not_in'   => [ $post_id ],
            ] );
            while ( $more_query->have_posts() ) { $more_query->the_post(); scm_render_asset_card(); }
            wp_reset_postdata();
            ?>
        </div>
    </div>

</div><!-- .scm-single-wrapper -->

<?php endwhile; ?>
<?php scm_render_global_modals(); ?>
<?php get_footer(); ?>

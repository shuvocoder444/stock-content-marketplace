<?php
/**
 * Archive template for stock_asset post type.
 */
if ( ! defined( 'ABSPATH' ) ) exit;
get_header();
?>
<div class="scm-wrapper">
    <?php echo scm_render_search_form(); ?>

    <div class="scm-layout">


        <div class="scm-main">
            <?php if ( is_tax() ) : ?>
                <div class="scm-archive-header">
                    <?php echo scm_get_breadcrumbs(); ?>
                    <h1 class="scm-archive-title"><?php single_term_title(); ?></h1>
                    <?php
                    $term_description = term_description();
                    if ( $term_description ) echo '<div class="scm-archive-desc">' . wp_kses_post( $term_description ) . '</div>';
                    ?>
                </div>
            <?php else : ?>
                <div class="scm-archive-header">
                    <?php echo scm_get_breadcrumbs(); ?>
                    <h1 class="scm-archive-title"><?php esc_html_e( 'Stock Assets', 'scm' ); ?></h1>
                </div>
            <?php endif; ?>

            <?php echo scm_render_filter_bar(); ?>

            <?php if ( have_posts() ) : ?>
                <div class="scm-grid" id="scm-asset-grid">
                    <?php while ( have_posts() ) : the_post(); scm_render_asset_card(); endwhile; ?>
                </div>

                <div class="scm-pagination">
                    <?php
                    echo paginate_links( [
                        'prev_text' => '&laquo; ' . esc_html__( 'Previous', 'scm' ),
                        'next_text' => esc_html__( 'Next', 'scm' ) . ' &raquo;',
                    ] );
                    ?>
                </div>
                <?php echo scm_render_load_more_button(); ?>
            <?php else : ?>
                <div class="scm-no-results">
                    <div class="scm-no-results-icon">🔍</div>
                    <h3><?php esc_html_e( 'No assets found.', 'scm' ); ?></h3>
                    <p><?php esc_html_e( 'Try different keywords or browse categories.', 'scm' ); ?></p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php scm_render_global_modals(); ?>
<?php get_footer(); ?>

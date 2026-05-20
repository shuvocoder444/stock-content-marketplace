<?php
if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'wp_head', 'scm_output_seo_tags' );
function scm_output_seo_tags() {
    $settings = get_option( 'scm_settings', [] );
    if ( empty( $settings['enable_seo'] ) ) return;
    if ( ! is_singular( 'stock_asset' ) ) return;

    $post     = get_post();
    $meta     = scm_get_all_meta( $post->ID );
    $thumb    = get_the_post_thumbnail_url( $post->ID, 'large' ) ?: get_post_meta( $post->ID, 'scm_thumbnail_url', true );
    $desc     = wp_trim_words( $post->post_excerpt ?: $post->post_content, 30 );
    $url      = get_permalink( $post->ID );
    $types    = get_the_terms( $post->ID, 'asset_type' );
    $type_str = $types && ! is_wp_error( $types ) ? $types[0]->name : 'Stock Asset';

    // Open Graph
    echo '<meta property="og:title" content="' . esc_attr( get_the_title() ) . '" />' . "\n";
    echo '<meta property="og:description" content="' . esc_attr( $desc ) . '" />' . "\n";
    echo '<meta property="og:url" content="' . esc_url( $url ) . '" />' . "\n";
    echo '<meta property="og:type" content="website" />' . "\n";
    if ( $thumb ) {
        echo '<meta property="og:image" content="' . esc_url( $thumb ) . '" />' . "\n";
    }

    // Twitter Card
    echo '<meta name="twitter:card" content="summary_large_image" />' . "\n";
    echo '<meta name="twitter:title" content="' . esc_attr( get_the_title() ) . '" />' . "\n";
    echo '<meta name="twitter:description" content="' . esc_attr( $desc ) . '" />' . "\n";
    if ( $thumb ) {
        echo '<meta name="twitter:image" content="' . esc_url( $thumb ) . '" />' . "\n";
    }

    // Canonical
    echo '<link rel="canonical" href="' . esc_url( $url ) . '" />' . "\n";

    // Schema
    if ( ! empty( $settings['enable_schema'] ) ) {
        $schema = [
            '@context' => 'https://schema.org',
            '@type'    => 'DigitalDocument',
            'name'     => get_the_title(),
            'description' => $desc,
            'url'      => $url,
            'fileFormat' => $meta['file_format'] ?: '',
            'contentSize' => $meta['file_size'] ?: '',
            'license'  => $meta['license_type'] ?: '',
            'author'   => [
                '@type' => 'Person',
                'name'  => $meta['author_name'] ?: get_the_author_meta( 'display_name', $post->post_author ),
                'url'   => $meta['author_url'] ?: get_author_posts_url( $post->post_author ),
            ],
        ];
        if ( $thumb ) $schema['image'] = $thumb;
        echo '<script type="application/ld+json">' . wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>' . "\n";
    }
}

// Breadcrumbs
function scm_get_breadcrumbs() {
    $crumbs = [];
    $crumbs[] = [ 'url' => home_url( '/' ), 'label' => __( 'Home', 'scm' ) ];
    $crumbs[] = [ 'url' => get_post_type_archive_link( 'stock_asset' ), 'label' => __( 'Stock', 'scm' ) ];

    if ( is_singular( 'stock_asset' ) ) {
        $types = get_the_terms( get_the_ID(), 'asset_type' );
        if ( $types && ! is_wp_error( $types ) ) {
            $type = $types[0];
            $crumbs[] = [ 'url' => get_term_link( $type ), 'label' => $type->name ];
        }
        $crumbs[] = [ 'url' => '', 'label' => get_the_title() ];
    } elseif ( is_tax() ) {
        $term = get_queried_object();
        if ( $term->parent ) {
            $parent = get_term( $term->parent, $term->taxonomy );
            $crumbs[] = [ 'url' => get_term_link( $parent ), 'label' => $parent->name ];
        }
        $crumbs[] = [ 'url' => '', 'label' => $term->name ];
    }

    ob_start();
    echo '<nav class="scm-breadcrumbs" aria-label="' . esc_attr__( 'Breadcrumb', 'scm' ) . '">';
    $last = count( $crumbs ) - 1;
    foreach ( $crumbs as $i => $crumb ) {
        if ( $i < $last ) {
            echo '<a href="' . esc_url( $crumb['url'] ) . '">' . esc_html( $crumb['label'] ) . '</a> <span class="scm-bc-sep">›</span> ';
        } else {
            echo '<span>' . esc_html( $crumb['label'] ) . '</span>';
        }
    }
    echo '</nav>';
    return ob_get_clean();
}

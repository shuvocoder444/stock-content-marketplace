<?php
if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'elementor/widgets/register', 'scm_register_elementor_widgets' );
add_action( 'elementor/elements/categories_registered', 'scm_add_elementor_category' );

function scm_add_elementor_category( $manager ) {
    $manager->add_category( 'scm-elements', [
        'title' => __( 'Stock Marketplace', 'scm' ),
        'icon'  => 'fa fa-image',
    ] );
}

function scm_register_elementor_widgets( $manager ) {
    require_once SCM_PLUGIN_DIR . 'includes/elementor/widget-asset-grid.php';
    require_once SCM_PLUGIN_DIR . 'includes/elementor/widget-search-box.php';
    require_once SCM_PLUGIN_DIR . 'includes/elementor/widget-category-list.php';

    $manager->register( new SCM_Widget_Asset_Grid() );
    $manager->register( new SCM_Widget_Search_Box() );
    $manager->register( new SCM_Widget_Category_List() );
}

// Create Elementor widget directory
if ( ! file_exists( SCM_PLUGIN_DIR . 'includes/elementor' ) ) {
    mkdir( SCM_PLUGIN_DIR . 'includes/elementor', 0755, true );
}

<?php
if ( ! defined( 'ABSPATH' ) ) exit;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;

class SCM_Widget_Asset_Grid extends Widget_Base {

    public function get_name() { return 'scm_asset_grid'; }
    public function get_title() { return __( 'SCM Asset Grid', 'scm' ); }
    public function get_icon() { return 'eicon-gallery-grid'; }
    public function get_categories() { return [ 'scm-elements' ]; }

    protected function register_controls() {
        $this->start_controls_section( 'content_section', [
            'label' => __( 'Settings', 'scm' ),
            'tab'   => Controls_Manager::TAB_CONTENT,
        ] );

        $this->add_control( 'per_page', [
            'label'   => __( 'Assets Per Page', 'scm' ),
            'type'    => Controls_Manager::NUMBER,
            'default' => 12,
            'min'     => 4,
            'max'     => 100,
        ] );

        $this->add_control( 'columns', [
            'label'   => __( 'Columns', 'scm' ),
            'type'    => Controls_Manager::SELECT,
            'default' => '4',
            'options' => [ '2' => '2', '3' => '3', '4' => '4', '5' => '5', '6' => '6' ],
        ] );

        $this->add_control( 'asset_type', [
            'label'       => __( 'Filter by Asset Type (slug)', 'scm' ),
            'type'        => Controls_Manager::TEXT,
            'placeholder' => 'photos',
        ] );

        $this->add_control( 'orderby', [
            'label'   => __( 'Order By', 'scm' ),
            'type'    => Controls_Manager::SELECT,
            'default' => 'date',
            'options' => [
                'date'      => __( 'Latest', 'scm' ),
                'popular'   => __( 'Most Viewed', 'scm' ),
                'downloads' => __( 'Most Downloaded', 'scm' ),
            ],
        ] );

        $this->add_control( 'show_filter', [
            'label'   => __( 'Show Filter Bar', 'scm' ),
            'type'    => Controls_Manager::SWITCHER,
            'default' => 'yes',
        ] );

        $this->end_controls_section();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();
        $per_page = intval( $settings['per_page'] ) ?: 12;
        $columns  = intval( $settings['columns'] ) ?: 4;
        $orderby  = sanitize_key( $settings['orderby'] );
        $type     = sanitize_text_field( $settings['asset_type'] );

        $args = scm_build_query_args( [
            'asset_type' => $type,
            'orderby'    => $orderby,
            'paged'      => 1,
        ] );
        $args['posts_per_page'] = $per_page;

        $query = new WP_Query( $args );

        echo '<div class="scm-elementor-grid">';
        if ( $settings['show_filter'] === 'yes' ) {
            echo scm_render_filter_bar();
        }
        echo '<div class="scm-grid scm-grid-cols-' . esc_attr( $columns ) . '">';
        while ( $query->have_posts() ) {
            $query->the_post();
            scm_render_asset_card();
        }
        wp_reset_postdata();
        echo '</div>';
        echo scm_render_load_more_button();
        echo '</div>';
    }
}

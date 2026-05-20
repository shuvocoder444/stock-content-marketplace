<?php
if ( ! defined( 'ABSPATH' ) ) exit;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;

class SCM_Widget_Category_List extends Widget_Base {

    public function get_name() { return 'scm_category_list'; }
    public function get_title() { return __( 'SCM Category List', 'scm' ); }
    public function get_icon() { return 'eicon-bullet-list'; }
    public function get_categories() { return [ 'scm-elements' ]; }

    protected function register_controls() {
        $this->start_controls_section( 'content_section', [
            'label' => __( 'Category List', 'scm' ),
            'tab'   => Controls_Manager::TAB_CONTENT,
        ] );
        $this->add_control( 'taxonomy', [
            'label'   => __( 'Taxonomy', 'scm' ),
            'type'    => Controls_Manager::SELECT,
            'default' => 'asset_type',
            'options' => [
                'asset_type'     => __( 'Asset Type', 'scm' ),
                'asset_category' => __( 'Asset Category', 'scm' ),
            ],
        ] );
        $this->add_control( 'show_count', [
            'label'   => __( 'Show Count', 'scm' ),
            'type'    => Controls_Manager::SWITCHER,
            'default' => 'yes',
        ] );
        $this->end_controls_section();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();
        $taxonomy = sanitize_key( $settings['taxonomy'] );
        $terms    = get_terms( [ 'taxonomy' => $taxonomy, 'hide_empty' => false ] );
        if ( is_wp_error( $terms ) || empty( $terms ) ) return;

        echo '<ul class="scm-category-list">';
        foreach ( $terms as $term ) {
            $count = $settings['show_count'] === 'yes' ? ' <span class="scm-count">(' . intval( $term->count ) . ')</span>' : '';
            echo '<li><a href="' . esc_url( get_term_link( $term ) ) . '">' . esc_html( $term->name ) . $count . '</a></li>';
        }
        echo '</ul>';
    }
}

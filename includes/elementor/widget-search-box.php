<?php
if ( ! defined( 'ABSPATH' ) ) exit;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;

class SCM_Widget_Search_Box extends Widget_Base {

    public function get_name() { return 'scm_search_box'; }
    public function get_title() { return __( 'SCM Search Box', 'scm' ); }
    public function get_icon() { return 'eicon-search'; }
    public function get_categories() { return [ 'scm-elements' ]; }

    protected function register_controls() {
        $this->start_controls_section( 'content_section', [
            'label' => __( 'Search Box', 'scm' ),
            'tab'   => Controls_Manager::TAB_CONTENT,
        ] );
        $this->add_control( 'placeholder', [
            'label'   => __( 'Placeholder Text', 'scm' ),
            'type'    => Controls_Manager::TEXT,
            'default' => __( 'Search photos, videos, mockups...', 'scm' ),
        ] );
        $this->end_controls_section();
    }

    protected function render() {
        $settings    = $this->get_settings_for_display();
        $placeholder = esc_attr( $settings['placeholder'] );
        echo '<div class="scm-search-bar">
            <form class="scm-search-form" id="scm-search-form" role="search">
                <div class="scm-search-inner">
                    <span class="scm-search-icon dashicons dashicons-search"></span>
                    <input type="search" id="scm-search-input" placeholder="' . $placeholder . '" autocomplete="off" />
                    <button type="submit" class="scm-btn scm-btn-primary">' . esc_html__( 'Search', 'scm' ) . '</button>
                </div>
            </form>
        </div>';
    }
}

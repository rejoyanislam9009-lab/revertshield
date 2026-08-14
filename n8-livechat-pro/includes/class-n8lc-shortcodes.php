<?php

defined( 'ABSPATH' ) || exit;

final class N8LC_Shortcodes {
    private static $instance;
    public static function instance() { if ( ! self::$instance ) self::$instance = new self(); return self::$instance; }
    private function __construct() {}

    public function hooks() {
        add_shortcode( 'n8_livechat_button', array( $this, 'button' ) );
        add_shortcode( 'n8_livechat_status', array( $this, 'status' ) );
        add_shortcode( 'n8_livechat_kb', array( $this, 'knowledge' ) );
    }

    public function button( $atts ) {
        $atts = shortcode_atts( array( 'label' => __( 'Chat with us', 'n8-livechat-pro' ), 'class' => '' ), $atts, 'n8_livechat_button' );
        $label = sanitize_text_field( $atts['label'] );
        $class = sanitize_html_class( $atts['class'] );
        return '<button type="button" class="n8lc-shortcode-button ' . esc_attr( $class ) . '" data-n8lc-open-chat="1">' . esc_html( $label ) . '</button>';
    }

    public function status() {
        $online = N8LC_Availability::is_open();
        return '<span class="n8lc-shortcode-status n8lc-shortcode-status-' . esc_attr( $online ? 'online' : 'away' ) . '">' . esc_html( $online ? __( 'Support is online', 'n8-livechat-pro' ) : __( 'Support is away', 'n8-livechat-pro' ) ) . '</span>';
    }

    public function knowledge( $atts ) {
        $atts  = shortcode_atts( array( 'limit' => 6 ), $atts, 'n8_livechat_kb' );
        $limit = max( 1, min( 20, absint( $atts['limit'] ) ) );
        $posts = get_posts( array( 'post_type' => 'n8lc_article', 'post_status' => 'publish', 'numberposts' => $limit ) );
        if ( ! $posts ) return '';
        $html = '<div class="n8lc-kb-list">';
        foreach ( $posts as $post ) {
            $html .= '<article class="n8lc-kb-card"><h3>' . esc_html( get_the_title( $post ) ) . '</h3><p>' . esc_html( wp_trim_words( wp_strip_all_tags( $post->post_excerpt ?: $post->post_content ), 24 ) ) . '</p></article>';
        }
        return $html . '</div>';
    }
}

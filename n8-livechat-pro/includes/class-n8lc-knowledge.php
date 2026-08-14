<?php

defined( 'ABSPATH' ) || exit;

final class N8LC_Knowledge {
    private static $instance;

    public static function instance() {
        if ( ! self::$instance ) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {}

    public function hooks() {
        add_action( 'init', array( $this, 'register_content' ) );
        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
    }

    public function register_content() {
        register_post_type( 'n8lc_article', array(
            'labels' => array(
                'name'          => __( 'Knowledge Base', 'n8-livechat-pro' ),
                'singular_name' => __( 'Knowledge Article', 'n8-livechat-pro' ),
                'add_new_item'  => __( 'Add Knowledge Article', 'n8-livechat-pro' ),
                'edit_item'     => __( 'Edit Knowledge Article', 'n8-livechat-pro' ),
                'search_items'  => __( 'Search Knowledge Base', 'n8-livechat-pro' ),
            ),
            'public'              => false,
            'show_ui'             => true,
            'show_in_menu'        => 'n8-livechat',
            'show_in_rest'        => true,
            'supports'            => array( 'title', 'editor', 'excerpt', 'revisions', 'author' ),
            'capabilities'        => array(
                'edit_post'              => 'n8lc_manage_knowledge',
                'read_post'              => 'n8lc_manage_knowledge',
                'delete_post'            => 'n8lc_manage_knowledge',
                'edit_posts'             => 'n8lc_manage_knowledge',
                'edit_others_posts'      => 'n8lc_manage_knowledge',
                'publish_posts'          => 'n8lc_manage_knowledge',
                'read_private_posts'     => 'n8lc_manage_knowledge',
                'delete_posts'           => 'n8lc_manage_knowledge',
                'delete_private_posts'   => 'n8lc_manage_knowledge',
                'delete_published_posts' => 'n8lc_manage_knowledge',
                'delete_others_posts'    => 'n8lc_manage_knowledge',
                'edit_private_posts'     => 'n8lc_manage_knowledge',
                'edit_published_posts'   => 'n8lc_manage_knowledge',
                'create_posts'           => 'n8lc_manage_knowledge',
            ),
            'map_meta_cap'        => false,
            'menu_icon'           => 'dashicons-welcome-learn-more',
            'exclude_from_search' => true,
        ) );
        register_taxonomy( 'n8lc_kb_topic', 'n8lc_article', array(
            'labels'       => array( 'name' => __( 'Knowledge Topics', 'n8-livechat-pro' ), 'singular_name' => __( 'Knowledge Topic', 'n8-livechat-pro' ) ),
            'public'       => false,
            'show_ui'      => true,
            'show_in_rest' => true,
            'hierarchical' => true,
            'capabilities' => array(
                'manage_terms' => 'n8lc_manage_knowledge',
                'edit_terms'   => 'n8lc_manage_knowledge',
                'delete_terms' => 'n8lc_manage_knowledge',
                'assign_terms' => 'n8lc_manage_knowledge',
            ),
        ) );
    }

    public function register_routes() {
        register_rest_route( N8LC_REST::NS, '/knowledge', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( $this, 'search' ),
            'permission_callback' => '__return_true',
        ) );
    }

    public function search( WP_REST_Request $request ) {
        $platform = N8LC_Platform::settings();
        if ( empty( $platform['enable_knowledge_base'] ) ) {
            return rest_ensure_response( array( 'items' => array() ) );
        }
        $limit = max( 1, min( 12, absint( $request->get_param( 'limit' ) ?: 6 ) ) );
        $args  = array(
            'post_type'           => 'n8lc_article',
            'post_status'         => 'publish',
            'posts_per_page'      => $limit,
            's'                   => sanitize_text_field( (string) $request->get_param( 'q' ) ),
            'ignore_sticky_posts' => true,
            'no_found_rows'       => true,
        );
        $query = new WP_Query( $args );
        $items = array();
        foreach ( $query->posts as $post ) {
            $items[] = array(
                'id'      => (int) $post->ID,
                'title'   => get_the_title( $post ),
                'excerpt' => wp_trim_words( wp_strip_all_tags( $post->post_excerpt ?: $post->post_content ), 26 ),
                'content' => wp_kses_post( apply_filters( 'the_content', $post->post_content ) ),
            );
        }
        return rest_ensure_response( array( 'items' => $items ) );
    }
}

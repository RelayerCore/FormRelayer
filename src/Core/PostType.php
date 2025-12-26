<?php

declare(strict_types=1);

namespace FormRelayer\Core;

/**
 * Post Type Registration
 *
 * @package FormRelayer
 * @since 2.0.0
 */
final class PostType
{
    private static ?PostType $instance = null;

    public const POST_TYPE = 'fr_form';
    public const SUBMISSION_POST_TYPE = 'fr_submission';

    private function __construct()
    {
        add_action('init', [$this, 'register']);
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Register post types
     */
    public function register(): void
    {
        $this->registerFormPostType();
        $this->registerSubmissionPostType();
    }

    /**
     * Register Form post type
     */
    private function registerFormPostType(): void
    {
        $labels = [
            'name' => _x('Forms', 'post type general name', 'form-relayer'),
            'singular_name' => _x('Form', 'post type singular name', 'form-relayer'),
            'menu_name' => _x('FormRelayer', 'admin menu', 'form-relayer'),
            'add_new' => _x('Add New', 'form', 'form-relayer'),
            'add_new_item' => __('Add New Form', 'form-relayer'),
            'edit_item' => __('Edit Form', 'form-relayer'),
            'new_item' => __('New Form', 'form-relayer'),
            'view_item' => __('View Form', 'form-relayer'),
            'search_items' => __('Search Forms', 'form-relayer'),
            'not_found' => __('No forms found', 'form-relayer'),
            'not_found_in_trash' => __('No forms found in Trash', 'form-relayer'),
            'all_items' => __('All Forms', 'form-relayer'),
        ];

        $args = [
            'labels' => $labels,
            'public' => false,
            'publicly_queryable' => false,
            'show_ui' => true,
            'show_in_menu' => true,
            'query_var' => false,
            'rewrite' => false,
            'capability_type' => 'post',
            'has_archive' => false,
            'hierarchical' => false,
            'menu_position' => 25,
            'menu_icon' => 'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAyMCAyMCIgZmlsbD0iI2ZmZmZmZiI+PHBhdGggZD0iTTE3IDNIM2MtMS4xIDAtMiAuOS0yIDJ2MTBjMCAxLjEuOSAyIDIgMmgxNGMxLjEgMCAyLS45IDItMlY1YzAtMS4xLS45LTItMi0yem0tNSAxMkg0di0yaDh2MnptNC00SDRWOWgxMnYyem0wLTRINFY1aDEydjJ6Ii8+PC9zdmc+',
            'supports' => ['title'],
            'show_in_rest' => true,
            'rest_base' => 'forms',
            'rest_namespace' => 'formrelayer/v1',
        ];

        register_post_type(self::POST_TYPE, $args);
    }

    /**
     * Register Submission post type
     */
    private function registerSubmissionPostType(): void
    {
        $labels = [
            'name' => _x('Submissions', 'post type general name', 'form-relayer'),
            'singular_name' => _x('Submission', 'post type singular name', 'form-relayer'),
            'menu_name' => _x('Submissions', 'admin menu', 'form-relayer'),
            'all_items' => __('Submissions', 'form-relayer'),
            'view_item' => __('View Submission', 'form-relayer'),
            'search_items' => __('Search Submissions', 'form-relayer'),
            'not_found' => __('No submissions found', 'form-relayer'),
        ];

        $args = [
            'labels' => $labels,
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => 'edit.php?post_type=' . self::POST_TYPE,
            'query_var' => false,
            'rewrite' => false,
            'capability_type' => 'post',
            'capabilities' => [
                'create_posts' => 'do_not_allow',
            ],
            'map_meta_cap' => true,
            'has_archive' => false,
            'hierarchical' => false,
            'supports' => ['title'],
            'show_in_rest' => true,
            'rest_base' => 'submissions',
            'rest_namespace' => 'formrelayer/v1',
        ];

        register_post_type(self::SUBMISSION_POST_TYPE, $args);
    }

    /**
     * Get form post type name
     */
    public static function getFormPostType(): string
    {
        return self::POST_TYPE;
    }

    /**
     * Get submission post type name
     */
    public static function getSubmissionPostType(): string
    {
        return self::SUBMISSION_POST_TYPE;
    }
}

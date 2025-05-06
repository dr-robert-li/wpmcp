<?php
/**
 * WPMCP Resources Implementation
 * 
 * Implements MCP resources for WordPress
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPMCP_Resources {
    /**
     * Discover available WordPress resources
     */
    public static function discover_resources() {
        $resources = array();
        $allowed_endpoints = get_option('wpmcp_allowed_endpoints', array());
        
        foreach ($allowed_endpoints as $endpoint) {
            $resources[] = array(
                'uri' => 'wordpress:/' . $endpoint,
                'name' => $endpoint,
                'description' => 'WordPress ' . ucfirst($endpoint) . ' resource',
                'capabilities' => array(
                    'read' => true,
                    'write' => true,
                    'delete' => true
                )
            );
        }
        
        return $resources;
    }
    
    /**
     * Get a specific resource by URI
     */
    public static function get_resource($uri) {
        // Parse the URI
        if (!preg_match('/^wordpress:\/([a-zA-Z_]+)(?:\/(\d+))?$/', $uri, $matches)) {
            return null;
        }
        
        $resource_type = $matches[1];
        $resource_id = isset($matches[2]) ? intval($matches[2]) : null;
        
        // Check if this resource type is allowed
        $allowed_endpoints = get_option('wpmcp_allowed_endpoints', array());
        if (!in_array($resource_type, $allowed_endpoints)) {
            return null;
        }
        
        // Get resource data from WordPress
        switch ($resource_type) {
            case 'posts':
                return self::get_post_resource($resource_id);
            case 'pages':
                return self::get_page_resource($resource_id);
            case 'users':
                return self::get_user_resource($resource_id);
            case 'categories':
                return self::get_category_resource($resource_id);
            case 'tags':
                return self::get_tag_resource($resource_id);
            case 'comments':
                return self::get_comment_resource($resource_id);
            case 'media':
                return self::get_media_resource($resource_id);
            default:
                return null;
        }
    }
    
    /**
     * Get post resource
     */
    private static function get_post_resource($post_id = null) {
        if ($post_id) {
            $post = get_post($post_id);
            if (!$post) {
                return null;
            }
            
            return array(
                'uri' => 'wordpress:/posts/' . $post->ID,
                'name' => $post->post_title,
                'type' => 'post',
                'data' => array(
                    'id' => $post->ID,
                    'title' => $post->post_title,
                    'content' => $post->post_content,
                    'excerpt' => $post->post_excerpt,
                    'status' => $post->post_status,
                    'date' => $post->post_date,
                    'author' => $post->post_author
                )
            );
        } else {
            // Return list of posts
            $posts = get_posts(array(
                'numberposts' => 10,
                'post_status' => 'publish'
            ));
            
            $resources = array();
            foreach ($posts as $post) {
                $resources[] = array(
                    'uri' => 'wordpress:/posts/' . $post->ID,
                    'name' => $post->post_title,
                    'type' => 'post'
                );
            }
            
            return $resources;
        }
    }
    
    /**
     * Get page resource
     */
    private static function get_page_resource($page_id = null) {
        if ($page_id) {
            $page = get_post($page_id);
            if (!$page || $page->post_type !== 'page') {
                return null;
            }
            
            return array(
                'uri' => 'wordpress:/pages/' . $page->ID,
                'name' => $page->post_title,
                'type' => 'page',
                'data' => array(
                    'id' => $page->ID,
                    'title' => $page->post_title,
                    'content' => $page->post_content,
                    'status' => $page->post_status,
                    'date' => $page->post_date,
                    'author' => $page->post_author
                )
            );
        } else {
            // Return list of pages
            $pages = get_pages();
            
            $resources = array();
            foreach ($pages as $page) {
                $resources[] = array(
                    'uri' => 'wordpress:/pages/' . $page->ID,
                    'name' => $page->post_title,
                    'type' => 'page'
                );
            }
            
            return $resources;
        }
    }
    
    /**
     * Get user resource
     */
    private static function get_user_resource($user_id = null) {
        if ($user_id) {
            $user = get_userdata($user_id);
            if (!$user) {
                return null;
            }
            
            return array(
                'uri' => 'wordpress:/users/' . $user->ID,
                'name' => $user->display_name,
                'type' => 'user',
                'data' => array(
                    'id' => $user->ID,
                    'username' => $user->user_login,
                    'email' => $user->user_email,
                    'display_name' => $user->display_name,
                    'roles' => $user->roles
                )
            );
        } else {
            // Return list of users
            $users = get_users();
            
            $resources = array();
            foreach ($users as $user) {
                $resources[] = array(
                    'uri' => 'wordpress:/users/' . $user->ID,
                    'name' => $user->display_name,
                    'type' => 'user'
                );
            }
            
            return $resources;
        }
    }
    
    /**
     * Get category resource
     */
    private static function get_category_resource($category_id = null) {
        if ($category_id) {
            $category = get_category($category_id);
            if (!$category) {
                return null;
            }
            
            return array(
                'uri' => 'wordpress:/categories/' . $category->term_id,
                'name' => $category->name,
                'type' => 'category',
                'data' => array(
                    'id' => $category->term_id,
                    'name' => $category->name,
                    'slug' => $category->slug,
                    'description' => $category->description,
                    'count' => $category->count
                )
            );
        } else {
            // Return list of categories
            $categories = get_categories();
            
            $resources = array();
            foreach ($categories as $category) {
                $resources[] = array(
                    'uri' => 'wordpress:/categories/' . $category->term_id,
                    'name' => $category->name,
                    'type' => 'category'
                );
            }
            
            return $resources;
        }
    }
    
    /**
     * Get tag resource
     */
    private static function get_tag_resource($tag_id = null) {
        if ($tag_id) {
            $tag = get_tag($tag_id);
            if (!$tag) {
                return null;
            }
            
            return array(
                'uri' => 'wordpress:/tags/' . $tag->term_id,
                'name' => $tag->name,
                'type' => 'tag',
                'data' => array(
                    'id' => $tag->term_id,
                    'name' => $tag->name,
                    'slug' => $tag->slug,
                    'description' => $tag->description,
                    'count' => $tag->count
                )
            );
        } else {
            // Return list of tags
            $tags = get_tags();
            
            $resources = array();
            foreach ($tags as $tag) {
                $resources[] = array(
                    'uri' => 'wordpress:/tags/' . $tag->term_id,
                    'name' => $tag->name,
                    'type' => 'tag'
                );
            }
            
            return $resources;
        }
    }
    
    /**
     * Get comment resource
     */
    private static function get_comment_resource($comment_id = null) {
        if ($comment_id) {
            $comment = get_comment($comment_id);
            if (!$comment) {
                return null;
            }
            
            return array(
                'uri' => 'wordpress:/comments/' . $comment->comment_ID,
                'name' => 'Comment by ' . $comment->comment_author,
                'type' => 'comment',
                'data' => array(
                    'id' => $comment->comment_ID,
                    'author' => $comment->comment_author,
                    'email' => $comment->comment_author_email,
                    'content' => $comment->comment_content,
                    'date' => $comment->comment_date,
                    'post_id' => $comment->comment_post_ID,
                    'status' => $comment->comment_approved
                )
            );
        } else {
            // Return list of comments
            $comments = get_comments(array(
                'status' => 'approve',
                'number' => 10
            ));
            
            $resources = array();
            foreach ($comments as $comment) {
                $resources[] = array(
                    'uri' => 'wordpress:/comments/' . $comment->comment_ID,
                    'name' => 'Comment by ' . $comment->comment_author,
                    'type' => 'comment'
                );
            }
            
            return $resources;
        }
    }
    
    /**
     * Get media resource
     */
    private static function get_media_resource($media_id = null) {
        if ($media_id) {
            $attachment = get_post($media_id);
            if (!$attachment || $attachment->post_type !== 'attachment') {
                return null;
            }
            
            $metadata = wp_get_attachment_metadata($media_id);
            $url = wp_get_attachment_url($media_id);
            
            return array(
                'uri' => 'wordpress:/media/' . $attachment->ID,
                'name' => $attachment->post_title,
                'type' => 'media',
                'data' => array(
                    'id' => $attachment->ID,
                    'title' => $attachment->post_title,
                    'caption' => $attachment->post_excerpt,
                    'description' => $attachment->post_content,
                    'alt_text' => get_post_meta($attachment->ID, '_wp_attachment_image_alt', true),
                    'mime_type' => $attachment->post_mime_type,
                    'url' => $url,
                    'metadata' => $metadata
                )
            );
        } else {
            // Return list of media
            $attachments = get_posts(array(
                'post_type' => 'attachment',
                'posts_per_page' => 10
            ));
            
            $resources = array();
            foreach ($attachments as $attachment) {
                $resources[] = array(
                    'uri' => 'wordpress:/media/' . $attachment->ID,
                    'name' => $attachment->post_title,
                    'type' => 'media'
                );
            }
            
            return $resources;
        }
    }
}

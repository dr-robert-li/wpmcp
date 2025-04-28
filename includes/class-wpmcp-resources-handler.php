<?php
/**
 * Handles MCP Resources functionality.
 *
 * @since      1.0.0
 * @package    WPMCP
 * @subpackage WPMCP/includes
 */

class WPMCP_Resources_Handler {
    
    /**
     * Initialize the class.
     */
    public function __construct() {
        // Initialize any dependencies
    }
    
    /**
     * List available resources.
     *
     * @param array $params Request parameters including optional cursor.
     * @return array Response with resources and optional nextCursor.
     */
    public function list_resources($params = array()) {
        $cursor = isset($params['cursor']) ? $params['cursor'] : null;
        $page_size = 20; // Number of resources per page
        
        // Get WordPress resources based on allowed endpoints
        $allowed_endpoints = get_option('wpmcp_allowed_endpoints', array());
        $resources = array();
        
        // Implement pagination
        $page = 0;
        if ($cursor) {
            // Decode cursor (base64 encoded JSON)
            $cursor_data = json_decode(base64_decode($cursor), true);
            if (is_array($cursor_data) && isset($cursor_data['page'])) {
                $page = intval($cursor_data['page']);
            }
        }
        
        // Add posts resources
        if (in_array('posts', $allowed_endpoints)) {
            $posts_query = new WP_Query(array(
                'post_type' => 'post',
                'posts_per_page' => $page_size,
                'offset' => $page * $page_size,
                'post_status' => 'publish'
            ));
            
            foreach ($posts_query->posts as $post) {
                $resources[] = array(
                    'uri' => 'wp://posts/' . $post->ID,
                    'name' => $post->post_title,
                    'description' => wp_trim_words($post->post_content, 20),
                    'mimeType' => 'text/html'
                );
            }
        }
        
        // Add pages resources
        if (in_array('pages', $allowed_endpoints)) {
            $pages_query = new WP_Query(array(
                'post_type' => 'page',
                'posts_per_page' => $page_size,
                'offset' => $page * $page_size,
                'post_status' => 'publish'
            ));
            
            foreach ($pages_query->posts as $page_obj) {
                $resources[] = array(
                    'uri' => 'wp://pages/' . $page_obj->ID,
                    'name' => $page_obj->post_title,
                    'description' => wp_trim_words($page_obj->post_content, 20),
                    'mimeType' => 'text/html'
                );
            }
        }
        
        // Add categories resources
        if (in_array('categories', $allowed_endpoints)) {
            $categories = get_categories(array(
                'hide_empty' => false,
                'number' => $page_size,
                'offset' => $page * $page_size
            ));
            
            foreach ($categories as $category) {
                $resources[] = array(
                    'uri' => 'wp://categories/' . $category->term_id,
                    'name' => $category->name,
                    'description' => $category->description,
                    'mimeType' => 'application/json'
                );
            }
        }
        
        // Add tags resources
        if (in_array('tags', $allowed_endpoints)) {
            $tags = get_tags(array(
                'hide_empty' => false,
                'number' => $page_size,
                'offset' => $page * $page_size
            ));
            
            foreach ($tags as $tag) {
                $resources[] = array(
                    'uri' => 'wp://tags/' . $tag->term_id,
                    'name' => $tag->name,
                    'description' => $tag->description,
                    'mimeType' => 'application/json'
                );
            }
        }
        
        // Add users resources if allowed
        if (in_array('users', $allowed_endpoints)) {
            $users = get_users(array(
                'number' => $page_size,
                'offset' => $page * $page_size
            ));
            
            foreach ($users as $user) {
                $resources[] = array(
                    'uri' => 'wp://users/' . $user->ID,
                    'name' => $user->display_name,
                    'description' => 'User profile',
                    'mimeType' => 'application/json'
                );
            }
        }
        
        // Add media resources if allowed
        if (in_array('media', $allowed_endpoints)) {
            $media_query = new WP_Query(array(
                'post_type' => 'attachment',
                'posts_per_page' => $page_size,
                'offset' => $page * $page_size,
                'post_status' => 'inherit'
            ));
            
            foreach ($media_query->posts as $media) {
                $mime_type = get_post_mime_type($media->ID);
                $resources[] = array(
                    'uri' => 'wp://media/' . $media->ID,
                    'name' => $media->post_title,
                    'description' => $media->post_content,
                    'mimeType' => $mime_type,
                    'size' => filesize(get_attached_file($media->ID))
                );
            }
        }
        
        // Check if there are more resources
        $has_more = count($resources) >= $page_size;
        
        // Create next cursor if there are more resources
        $next_cursor = null;
        if ($has_more) {
            $next_cursor = base64_encode(json_encode(array('page' => $page + 1)));
        }
        
        return array(
            'resources' => $resources,
            'nextCursor' => $next_cursor
        );
    }
    
    /**
     * Read resource contents.
     *
     * @param string $uri Resource URI.
     * @return array Resource contents.
     */
    public function read_resource($uri) {
        // Parse the URI to determine resource type and ID
        $parsed_uri = $this->parse_resource_uri($uri);
        
        if (!$parsed_uri) {
            return new WP_Error('invalid_uri', 'Invalid resource URI format');
        }
        
        $resource_type = $parsed_uri['type'];
        $resource_id = $parsed_uri['id'];
        
        // Check if this resource type is allowed
        $allowed_endpoints = get_option('wpmcp_allowed_endpoints', array());
        if (!in_array($resource_type, $allowed_endpoints)) {
            return new WP_Error('forbidden_resource', 'Access to this resource type is not allowed');
        }
        
        // Get resource content based on type
        switch ($resource_type) {
            case 'posts':
                return $this->get_post_content($resource_id);
                
            case 'pages':
                return $this->get_page_content($resource_id);
                
            case 'categories':
                return $this->get_category_content($resource_id);
                
            case 'tags':
                return $this->get_tag_content($resource_id);
                
            case 'users':
                return $this->get_user_content($resource_id);
                
            case 'media':
                return $this->get_media_content($resource_id);
                
            default:
                return new WP_Error('unknown_resource_type', 'Unknown resource type');
        }
    }
    
    /**
     * Parse a resource URI into its components.
     *
     * @param string $uri Resource URI.
     * @return array|false Parsed URI components or false if invalid.
     */
    private function parse_resource_uri($uri) {
        // Expected format: wp://{type}/{id}
        if (strpos($uri, 'wp://') !== 0) {
            return false;
        }
        
        $path = substr($uri, 5); // Remove 'wp://'
        $parts = explode('/', $path);
        
        if (count($parts) < 2) {
            return false;
        }
        
        return array(
            'type' => $parts[0],
            'id' => $parts[1]
        );
    }
    
    /**
     * Get post content.
     *
     * @param int $post_id Post ID.
     * @return array|WP_Error Post content or error.
     */
    private function get_post_content($post_id) {
        $post = get_post($post_id);
        
        if (!$post) {
            return new WP_Error('resource_not_found', 'Post not found');
        }
        
        $content = array(
            'uri' => 'wp://posts/' . $post->ID,
            'mimeType' => 'text/html',
            'text' => $this->format_post_content($post)
        );
        
        return array('contents' => array($content));
    }
    
    /**
     * Format post content for display.
     *
     * @param WP_Post $post Post object.
     * @return string Formatted post content.
     */
    private function format_post_content($post) {
        $content = "# {$post->post_title}\n\n";
        $content .= "Date: " . get_the_date('', $post->ID) . "\n";
        $content .= "Author: " . get_the_author_meta('display_name', $post->post_author) . "\n\n";
        $content .= $post->post_content;
        
        return $content;
    }
    
    /**
     * Get page content.
     *
     * @param int $page_id Page ID.
     * @return array|WP_Error Page content or error.
     */
    private function get_page_content($page_id) {
        $page = get_post($page_id);
        
        if (!$page || $page->post_type !== 'page') {
            return new WP_Error('resource_not_found', 'Page not found');
        }
        
        $content = array(
            'uri' => 'wp://pages/' . $page->ID,
            'mimeType' => 'text/html',
            'text' => $this->format_post_content($page)
        );
        
        return array('contents' => array($content));
    }
    
    /**
     * Get category content.
     *
     * @param int $category_id Category ID.
     * @return array|WP_Error Category content or error.
     */
    private function get_category_content($category_id) {
        $category = get_term($category_id, 'category');
        
        if (!$category || is_wp_error($category)) {
            return new WP_Error('resource_not_found', 'Category not found');
        }
        
        // Get posts in this category
        $posts = get_posts(array(
            'category' => $category_id,
            'posts_per_page' => 10
        ));
        
        $posts_data = array();
        foreach ($posts as $post) {
            $posts_data[] = array(
                'id' => $post->ID,
                'title' => $post->post_title,
                'date' => get_the_date('c', $post->ID),
                'url' => get_permalink($post->ID)
            );
        }
        
        $category_data = array(
            'id' => $category->term_id,
            'name' => $category->name,
            'description' => $category->description,
            'count' => $category->count,
            'posts' => $posts_data
        );
        
        $content = array(
            'uri' => 'wp://categories/' . $category->term_id,
            'mimeType' => 'application/json',
            'text' => json_encode($category_data, JSON_PRETTY_PRINT)
        );
        
        return array('contents' => array($content));
    }
    
    /**
     * Get tag content.
     *
     * @param int $tag_id Tag ID.
     * @return array|WP_Error Tag content or error.
     */
    private function get_tag_content($tag_id) {
        $tag = get_term($tag_id, 'post_tag');
        
        if (!$tag || is_wp_error($tag)) {
            return new WP_Error('resource_not_found', 'Tag not found');
        }
        
        // Get posts with this tag
        $posts = get_posts(array(
            'tag_id' => $tag_id,
            'posts_per_page' => 10
        ));
        
        $posts_data = array();
        foreach ($posts as $post) {
            $posts_data[] = array(
                'id' => $post->ID,
                'title' => $post->post_title,
                'date' => get_the_date('c', $post->ID),
                'url' => get_permalink($post->ID)
            );
        }
        
        $tag_data = array(
            'id' => $tag->term_id,
            'name' => $tag->name,
            'description' => $tag->description,
            'count' => $tag->count,
            'posts' => $posts_data
        );
        
        $content = array(
            'uri' => 'wp://tags/' . $tag->term_id,
            'mimeType' => 'application/json',
            'text' => json_encode($tag_data, JSON_PRETTY_PRINT)
        );
        
        return array('contents' => array($content));
    }
    
    /**
     * Get user content.
     *
     * @param int $user_id User ID.
     * @return array|WP_Error User content or error.
     */
    private function get_user_content($user_id) {
        $user = get_userdata($user_id);
        
        if (!$user) {
            return new WP_Error('resource_not_found', 'User not found');
        }
        
        // Get user's recent posts
        $posts = get_posts(array(
            'author' => $user_id,
            'posts_per_page' => 10
        ));
        
        $posts_data = array();
        foreach ($posts as $post) {
            $posts_data[] = array(
                'id' => $post->ID,
                'title' => $post->post_title,
                'date' => get_the_date('c', $post->ID),
                'url' => get_permalink($post->ID)
            );
        }
        
        // Sanitize user data for security
        $user_data = array(
            'id' => $user->ID,
            'username' => $user->user_login,
            'name' => $user->display_name,
            'registered' => $user->user_registered,
            'website' => $user->user_url,
            'bio' => $user->description,
            'roles' => $user->roles,
            'posts' => $posts_data
        );
        
        $content = array(
            'uri' => 'wp://users/' . $user->ID,
            'mimeType' => 'application/json',
            'text' => json_encode($user_data, JSON_PRETTY_PRINT)
        );
        
        return array('contents' => array($content));
    }
    
    /**
     * Get media content.
     *
     * @param int $media_id Media ID.
     * @return array|WP_Error Media content or error.
     */
    private function get_media_content($media_id) {
        $media = get_post($media_id);
        
        if (!$media || $media->post_type !== 'attachment') {
            return new WP_Error('resource_not_found', 'Media not found');
        }
        
        $mime_type = get_post_mime_type($media->ID);
        $file_path = get_attached_file($media->ID);
        
        // For text-based files, return the content
        if (strpos($mime_type, 'text/') === 0) {
            $content = array(
                'uri' => 'wp://media/' . $media->ID,
                'mimeType' => $mime_type,
                'text' => file_get_contents($file_path)
            );
        } 
        // For images and other binary files, return base64 encoded data
        else {
            // For large files, just return metadata instead of the full content
            if (filesize($file_path) > 1024 * 1024) { // 1MB limit
                $media_data = array(
                    'id' => $media->ID,
                    'title' => $media->post_title,
                    'description' => $media->post_content,
                    'caption' => $media->post_excerpt,
                    'alt' => get_post_meta($media->ID, '_wp_attachment_image_alt', true),
                    'mime_type' => $mime_type,
                    'size' => filesize($file_path),
                    'url' => wp_get_attachment_url($media->ID),
                    'dimensions' => wp_get_attachment_metadata($media->ID)
                );
                
                $content = array(
                    'uri' => 'wp://media/' . $media->ID,
                    'mimeType' => 'application/json',
                    'text' => json_encode($media_data, JSON_PRETTY_PRINT)
                );
            } else {
                $content = array(
                    'uri' => 'wp://media/' . $media->ID,
                    'mimeType' => $mime_type,
                    'blob' => base64_encode(file_get_contents($file_path))
                );
            }
        }
        
        return array('contents' => array($content));
    }
    
    /**
     * List resource templates.
     *
     * @return array Resource templates.
     */
    public function list_resource_templates() {
        $templates = array();
        
        // Add post template
        $templates[] = array(
            'uriTemplate' => 'wp://posts/{id}',
            'name' => 'WordPress Post',
            'description' => 'Access a specific post by ID',
            'mimeType' => 'text/html'
        );
        
        // Add page template
        $templates[] = array(
            'uriTemplate' => 'wp://pages/{id}',
            'name' => 'WordPress Page',
            'description' => 'Access a specific page by ID',
            'mimeType' => 'text/html'
        );
        
        // Add category template
        $templates[] = array(
            'uriTemplate' => 'wp://categories/{id}',
            'name' => 'WordPress Category',
            'description' => 'Access a specific category by ID',
            'mimeType' => 'application/json'
        );
        
        // Add tag template
        $templates[] = array(
            'uriTemplate' => 'wp://tags/{id}',
            'name' => 'WordPress Tag',
            'description' => 'Access a specific tag by ID',
            'mimeType' => 'application/json'
        );
        
        // Add user template
        $templates[] = array(
            'uriTemplate' => 'wp://users/{id}',
            'name' => 'WordPress User',
            'description' => 'Access a specific user by ID',
            'mimeType' => 'application/json'
        );
        
        // Add media template
        $templates[] = array(
            'uriTemplate' => 'wp://media/{id}',
            'name' => 'WordPress Media',
            'description' => 'Access a specific media item by ID',
            'mimeType' => 'application/octet-stream'
        );
        
        return array('resourceTemplates' => $templates);
    }
    
    /**
     * Subscribe to resource changes.
     *
     * @param string $uri Resource URI to subscribe to.
     * @return array Subscription confirmation.
     */
    public function subscribe_to_resource($uri) {
        // Store subscription in options table
        $subscriptions = get_option('wpmcp_resource_subscriptions', array());
        
        if (!in_array($uri, $subscriptions)) {
            $subscriptions[] = $uri;
            update_option('wpmcp_resource_subscriptions', $subscriptions);
        }
        
        return array('subscribed' => true, 'uri' => $uri);
    }
    
    /**
     * Check if a resource has subscribers.
     *
     * @param string $uri Resource URI.
     * @return bool Whether the resource has subscribers.
     */
    public function has_subscribers($uri) {
        $subscriptions = get_option('wpmcp_resource_subscriptions', array());
        return in_array($uri, $subscriptions);
    }
    
    /**
     * Notify subscribers of resource changes.
     * This would be called from WordPress hooks when content changes.
     *
     * @param string $uri Resource URI that changed.
     */
    public function notify_resource_updated($uri) {
        // This method would be called by WordPress hooks
        // The actual notification sending would be handled by the main plugin class
    }
}


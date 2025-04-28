<?php
/**
 * Handles MCP Completion functionality.
 *
 * @since      2.0.0
 * @package    WPMCP
 * @subpackage WPMCP/includes
 */

class WPMCP_Completion_Handler {
    
    /**
     * Initialize the class.
     */
    public function __construct() {
        // Initialize any dependencies
    }
    
    /**
     * Complete argument values based on partial input.
     *
     * @param string $tool_name Tool name.
     * @param string $argument_name Argument name.
     * @param string $partial_value Partial value to complete.
     * @param array $context Additional context.
     * @return array Completion suggestions.
     */
    public function complete_argument($tool_name, $argument_name, $partial_value, $context = array()) {
        switch ($tool_name) {
            case 'wp_call_endpoint':
                return $this->complete_endpoint_argument($argument_name, $partial_value, $context);
                
            case 'resources/read':
                return $this->complete_resource_uri_argument($argument_name, $partial_value, $context);
                
            case 'prompts/get':
                return $this->complete_prompt_argument($argument_name, $partial_value, $context);
                
            default:
                return array('suggestions' => array());
        }
    }
    
    /**
     * Complete endpoint argument values.
     *
     * @param string $argument_name Argument name.
     * @param string $partial_value Partial value to complete.
     * @param array $context Additional context.
     * @return array Completion suggestions.
     */
    private function complete_endpoint_argument($argument_name, $partial_value, $context) {
        if ($argument_name !== 'endpoint') {
            return array('suggestions' => array());
        }
        
        // Get all registered REST routes
        $rest_server = rest_get_server();
        $routes = $rest_server->get_routes();
        
        $suggestions = array();
        
        foreach ($routes as $route => $route_handlers) {
            // Only include routes that match the partial value
            if (strpos($route, $partial_value) === 0) {
                // Extract namespace from the route path
                $namespace = '';
                $path_parts = explode('/', trim($route, '/'));
                
                if (count($path_parts) >= 2) {
                    // For routes like /wp/v2/posts, the namespace would be wp/v2
                    $namespace = $path_parts[0] . '/' . $path_parts[1];
                } elseif (count($path_parts) == 1) {
                    // For root namespace routes
                    $namespace = $path_parts[0];
                }
                
                // Only include wp/v2 and wpmcp/v1 namespaces
                if ($namespace == 'wp/v2' || $namespace == 'wpmcp/v1') {
                    $suggestions[] = array(
                        'value' => $route,
                        'label' => $route,
                        'detail' => $namespace
                    );
                }
            }
        }
        
        return array('suggestions' => $suggestions);
    }
    
    /**
     * Complete resource URI argument values.
     *
     * @param string $argument_name Argument name.
     * @param string $partial_value Partial value to complete.
     * @param array $context Additional context.
     * @return array Completion suggestions.
     */
    private function complete_resource_uri_argument($argument_name, $partial_value, $context) {
        if ($argument_name !== 'uri') {
            return array('suggestions' => array());
        }
        
        $suggestions = array();
        
        // Only complete wp:// URIs
        if (empty($partial_value) || strpos($partial_value, 'wp://') === 0) {
            $resource_types = array('posts', 'pages', 'categories', 'tags', 'users', 'media');
            
            foreach ($resource_types as $type) {
                if (empty($partial_value) || strpos('wp://' . $type, $partial_value) === 0) {
                    $suggestions[] = array(
                        'value' => 'wp://' . $type . '/',
                        'label' => 'wp://' . $type . '/',
                        'detail' => 'WordPress ' . ucfirst($type)
                    );
                }
            }
            
            // If partial value includes a resource type, suggest specific resources
            if (preg_match('#^wp://([^/]+)/#', $partial_value, $matches)) {
                $resource_type = $matches[1];
                
                switch ($resource_type) {
                    case 'posts':
                        $posts = get_posts(array('posts_per_page' => 10));
                        foreach ($posts as $post) {
                            $suggestions[] = array(
                                'value' => 'wp://posts/' . $post->ID,
                                'label' => 'wp://posts/' . $post->ID,
                                'detail' => $post->post_title
                            );
                        }
                        break;
                        
                    case 'pages':
                        $pages = get_pages(array('number' => 10));
                        foreach ($pages as $page) {
                            $suggestions[] = array(
                                'value' => 'wp://pages/' . $page->ID,
                                'label' => 'wp://pages/' . $page->ID,
                                'detail' => $page->post_title
                            );
                        }
                        break;
                        
                    case 'categories':
                        $categories = get_categories(array('hide_empty' => false, 'number' => 10));
                        foreach ($categories as $category) {
                            $suggestions[] = array(
                                'value' => 'wp://categories/' . $category->term_id,
                                'label' => 'wp://categories/' . $category->term_id,
                                'detail' => $category->name
                            );
                        }
                        break;
                        
                    case 'tags':
                        $tags = get_tags(array('hide_empty' => false, 'number' => 10));
                        foreach ($tags as $tag) {
                            $suggestions[] = array(
                                'value' => 'wp://tags/' . $tag->term_id,
                                'label' => 'wp://tags/' . $tag->term_id,
                                'detail' => $tag->name
                            );
                        }
                        break;
                        
                    case 'users':
                        $users = get_users(array('number' => 10));
                        foreach ($users as $user) {
                            $suggestions[] = array(
                                'value' => 'wp://users/' . $user->ID,
                                'label' => 'wp://users/' . $user->ID,
                                'detail' => $user->display_name
                            );
                        }
                        break;
                        
                    case 'media':
                        $media_items = get_posts(array('post_type' => 'attachment', 'posts_per_page' => 10));
                        foreach ($media_items as $media) {
                            $suggestions[] = array(
                                'value' => 'wp://media/' . $media->ID,
                                'label' => 'wp://media/' . $media->ID,
                                'detail' => $media->post_title
                            );
                        }
                        break;
                }
            }
        }
        
        return array('suggestions' => $suggestions);
    }
    
    /**
     * Complete prompt argument values.
     *
     * @param string $argument_name Argument name.
     * @param string $partial_value Partial value to complete.
     * @param array $context Additional context.
     * @return array Completion suggestions.
     */
    private function complete_prompt_argument($argument_name, $partial_value, $context) {
        if ($argument_name !== 'name') {
            return array('suggestions' => array());
        }
        
        // Define available prompts
        $prompts = array(
            'create_post' => 'Create a new WordPress post',
            'analyze_content' => 'Analyze WordPress content',
            'seo_optimize' => 'Generate SEO recommendations for content',
            'generate_excerpt' => 'Generate an excerpt from post content'
        );
        
        $suggestions = array();
        
        foreach ($prompts as $name => $description) {
            if (empty($partial_value) || strpos($name, $partial_value) === 0) {
                $suggestions[] = array(
                    'value' => $name,
                    'label' => $name,
                    'detail' => $description
                );
            }
        }
        
        return array('suggestions' => $suggestions);
    }
}

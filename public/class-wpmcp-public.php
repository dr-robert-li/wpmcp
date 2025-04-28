<?php
/**
 * The public-facing functionality of the plugin.
 *
 * @since      2.0.0
 * @package    WPMCP
 * @subpackage WPMCP/public
 */

class WPMCP_Public {

    /**
     * The ID of this plugin.
     *
     * @since    2.0.0
     * @access   private
     * @var      string    $plugin_name    The ID of this plugin.
     */
    private $plugin_name;

    /**
     * The version of this plugin.
     *
     * @since    2.0.0
     * @access   private
     * @var      string    $version    The current version of this plugin.
     */
    private $version;

    /**
     * The resources handler instance.
     *
     * @since    2.0.0
     * @access   private
     * @var      WPMCP_Resources_Handler    $resources_handler    Handles MCP resources.
     */
    private $resources_handler;

    /**
     * The notification manager instance.
     *
     * @since    2.0.0
     * @access   private
     * @var      WPMCP_Notification_Manager    $notification_manager    Handles MCP notifications.
     */
    private $notification_manager;

    /**
     * The prompts handler instance.
     *
     * @since    2.0.0
     * @access   private
     * @var      WPMCP_Prompts_Handler    $prompts_handler    Handles MCP prompts.
     */
    private $prompts_handler;

    /**
     * The completion handler instance.
     *
     * @since    2.0.0
     * @access   private
     * @var      WPMCP_Completion_Handler    $completion_handler    Handles MCP completions.
     */
    private $completion_handler;

    /**
     * The consent manager instance.
     *
     * @since    2.0.0
     * @access   private
     * @var      WPMCP_Consent_Manager    $consent_manager    Handles user consent.
     */
    private $consent_manager;

    /**
     * The error handler instance.
     *
     * @since    2.0.0
     * @access   private
     * @var      WPMCP_Error_Handler    $error_handler    Handles MCP errors.
     */
    private $error_handler;

    /**
     * Initialize the class and set its properties.
     *
     * @since    2.0.0
     * @param    string    $plugin_name       The name of the plugin.
     * @param    string    $version           The version of this plugin.
     */
    public function __construct($plugin_name = 'wpmcp', $version = WPMCP_VERSION) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
        
        // Initialize handlers
        $this->resources_handler = new WPMCP_Resources_Handler();
        $this->notification_manager = new WPMCP_Notification_Manager();
        $this->prompts_handler = new WPMCP_Prompts_Handler();
        $this->completion_handler = new WPMCP_Completion_Handler();
        $this->consent_manager = new WPMCP_Consent_Manager();
        $this->error_handler = new WPMCP_Error_Handler();
    }

    /**
     * Register the stylesheets for the public-facing side of the site.
     *
     * @since    2.0.0
     */
    public function enqueue_styles() {
        wp_enqueue_style(
            $this->plugin_name, 
            plugin_dir_url(__FILE__) . 'css/wpmcp-public.css', 
            array(), 
            $this->version, 
            'all'
        );
    }

    /**
     * Register the JavaScript for the public-facing side of the site.
     *
     * @since    2.0.0
     */
    public function enqueue_scripts() {
        wp_enqueue_script(
            $this->plugin_name, 
            plugin_dir_url(__FILE__) . 'js/wpmcp-public.js', 
            array('jquery'), 
            $this->version, 
            false
        );
        
        // Pass data to the script
        wp_localize_script($this->plugin_name, 'wpmcp_data', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'rest_url' => rest_url('wpmcp/v1'),
            'nonce' => wp_create_nonce('wpmcp-public-nonce')
        ));
    }

    /**
     * Register the REST API routes for this plugin.
     *
     * @since    2.0.0
     */
    public function register_rest_routes() {
        register_rest_route('wpmcp/v1', '/data', array(
            'methods' => 'POST',
            'callback' => array($this, 'handle_mcp_request'),
            'permission_callback' => array($this, 'verify_api_key')
        ));
        
        // Register additional routes for specific MCP functionality
        register_rest_route('wpmcp/v1', '/consent', array(
            'methods' => 'POST',
            'callback' => array($this->consent_manager, 'handle_consent_request'),
            'permission_callback' => array($this, 'verify_api_key')
        ));
        
        register_rest_route('wpmcp/v1', '/test', array(
            'methods' => 'GET',
            'callback' => array($this, 'handle_test_request'),
            'permission_callback' => array($this, 'verify_api_key')
        ));
    }
    
    /**
     * Verify the API key for REST API requests.
     *
     * @since    2.0.0
     * @param    WP_REST_Request    $request    The request object.
     * @return   bool                           Whether the request has valid authentication.
     */
    public function verify_api_key($request) {
        $api_key = get_option('wpmcp_api_key', '');
        
        if (empty($api_key)) {
            return false;
        }
        
        $headers = $request->get_headers();
        
        // Check for API key in headers (case-insensitive)
        if (isset($headers['x-api-key']) && !empty($headers['x-api-key'][0])) {
            return $headers['x-api-key'][0] === $api_key;
        }
        
        // Also check for X-API-Key (capital letters)
        if (isset($headers['X-API-Key']) && !empty($headers['X-API-Key'][0])) {
            return $headers['X-API-Key'][0] === $api_key;
        }
        
        // Check for API key in request body as fallback
        $json_str = file_get_contents('php://input');
        $data = json_decode($json_str, true);
        
        if (isset($data['api_key']) && $data['api_key'] === $api_key) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Handle test requests to verify API connectivity.
     *
     * @since    2.0.0
     * @param    WP_REST_Request    $request    The request object.
     * @return   WP_REST_Response                The response object.
     */
    public function handle_test_request($request) {
        return rest_ensure_response(array(
            'status' => 'success',
            'message' => 'WPMCP API is working correctly',
            'version' => $this->version,
            'timestamp' => current_time('c')
        ));
    }

    /**
     * Handle MCP protocol requests.
     *
     * @since    2.0.0
     * @param    WP_REST_Request    $request    The request object.
     * @return   WP_REST_Response|WP_Error      The response object or error.
     */
    public function handle_mcp_request($request) {
        try {
            // Get the raw POST data
            $json_str = file_get_contents('php://input');
            $data = json_decode($json_str, true);
            
            // Basic validation
            if (!$data || !isset($data['type'])) {
                return $this->error_handler->create_error('invalid_request', 'Invalid request format', 400);
            }
            
            // Handle different MCP request types
            switch ($data['type']) {
                case 'describe':
                    return $this->handle_describe_request();
                    
                case 'invoke':
                    if (!isset($data['name']) || !isset($data['arguments'])) {
                        return $this->error_handler->create_error('invalid_invoke', 'Invalid invoke request', 400);
                    }
                    return $this->handle_invoke_request($data['name'], $data['arguments']);
                    
                default:
                    return $this->error_handler->create_error('invalid_type', 'Invalid request type', 400);
            }
        } catch (Exception $e) {
            return $this->error_handler->create_error('server_error', $e->getMessage(), 500);
        }
    }
    
    /**
     * Handle describe requests.
     *
     * @since    2.0.0
     * @return   WP_REST_Response    The response object.
     */
    private function handle_describe_request() {
        // Return a comprehensive tool description
        $response = array(
            'type' => 'description',
            'data' => array(
                'name' => 'wpmcp',
                'version' => $this->version,
                'description' => 'WordPress Model Context Protocol Server enabling AI assistants to interact with this WordPress site.',
                'functions' => array(
                    array(
                        'name' => 'wp_discover_endpoints',
                        'description' => 'Maps all available REST API endpoints on this WordPress site and returns their methods and namespaces. This allows you to understand what operations are possible without having to manually specify endpoints.',
                        'parameters' => array(
                            'type' => 'object',
                            'properties' => array(),
                            'required' => array()
                        )
                    ),
                    array(
                        'name' => 'wp_call_endpoint',
                        'description' => 'Executes specific REST API requests to the WordPress site using provided parameters. It handles both read and write operations to manage content, users, and site settings.',
                        'parameters' => array(
                            'type' => 'object',
                            'properties' => array(
                                'endpoint' => array(
                                    'type' => 'string',
                                    'description' => 'API endpoint path (e.g., /wp/v2/posts)'
                                ),
                                'method' => array(
                                    'type' => 'string',
                                    'enum' => array('GET', 'POST', 'PUT', 'DELETE', 'PATCH'),
                                    'description' => 'HTTP method',
                                    'default' => 'GET'
                                ),
                                'params' => array(
                                    'type' => 'object',
                                    'description' => 'Request parameters or body data'
                                )
                            ),
                            'required' => array('endpoint')
                        )
                    ),
                    array(
                        'name' => 'resources/list',
                        'description' => 'Lists available WordPress resources that can be accessed.',
                        'parameters' => array(
                            'type' => 'object',
                            'properties' => array(
                                'cursor' => array(
                                    'type' => 'string',
                                    'description' => 'Pagination cursor for retrieving additional resources'
                                )
                            ),
                            'required' => array()
                        )
                    ),
                    array(
                        'name' => 'resources/read',
                        'description' => 'Reads the content of a specific WordPress resource.',
                        'parameters' => array(
                            'type' => 'object',
                            'properties' => array(
                                'uri' => array(
                                    'type' => 'string',
                                    'description' => 'Resource URI (e.g., wp://posts/123)'
                                )
                            ),
                            'required' => array('uri')
                        )
                    ),
                    array(
                        'name' => 'resources/subscribe',
                        'description' => 'Subscribes to changes for a specific WordPress resource.',
                        'parameters' => array(
                            'type' => 'object',
                            'properties' => array(
                                'uri' => array(
                                    'type' => 'string',
                                    'description' => 'Resource URI to subscribe to'
                                )
                            ),
                            'required' => array('uri')
                        )
                    ),
                    array(
                        'name' => 'prompts/list',
                        'description' => 'Lists available prompt templates.',
                        'parameters' => array(
                            'type' => 'object',
                            'properties' => array(),
                            'required' => array()
                        )
                    ),
                    array(
                        'name' => 'prompts/get',
                        'description' => 'Gets a specific prompt template with filled arguments.',
                        'parameters' => array(
                            'type' => 'object',
                            'properties' => array(
                                'name' => array(
                                    'type' => 'string',
                                    'description' => 'Name of the prompt template'
                                ),
                                'arguments' => array(
                                    'type' => 'object',
                                    'description' => 'Arguments to fill in the prompt template'
                                )
                            ),
                            'required' => array('name')
                        )
                    )
                ),
                'examples' => array(
                    array(
                        'name' => 'List recent posts',
                        'tool' => 'wp_call_endpoint',
                        'args' => array(
                            'endpoint' => '/wp/v2/posts',
                            'method' => 'GET',
                            'params' => array(
                                'per_page' => 5,
                                'orderby' => 'date',
                                'order' => 'desc'
                            )
                        )
                    ),
                    array(
                        'name' => 'Create a new post',
                        'tool' => 'wp_call_endpoint',
                        'args' => array(
                            'endpoint' => '/wp/v2/posts',
                            'method' => 'POST',
                            'params' => array(
                                'title' => 'Example Post Title',
                                'content' => 'This is the content of the post.',
                                'status' => 'draft'
                            )
                        )
                    ),
                    array(
                        'name' => 'List available resources',
                        'tool' => 'resources/list',
                        'args' => array()
                    ),
                    array(
                        'name' => 'Read a post resource',
                        'tool' => 'resources/read',
                        'args' => array(
                            'uri' => 'wp://posts/1'
                        )
                    ),
                    array(
                        'name' => 'Subscribe to resource changes',
                        'tool' => 'resources/subscribe',
                        'args' => array(
                            'uri' => 'wp://posts/1'
                        )
                    ),
                    array(
                        'name' => 'List prompt templates',
                        'tool' => 'prompts/list',
                        'args' => array()
                    ),
                    array(
                        'name' => 'Get a prompt template',
                        'tool' => 'prompts/get',
                        'args' => array(
                            'name' => 'create_post',
                            'arguments' => array(
                                'title' => 'My New Post',
                                'content' => 'This is the content of my new post.'
                            )
                        )
                    )
                )
            )
        );
        
        return rest_ensure_response($response);
    }
    
    /**
     * Handle invoke requests.
     *
     * @since    2.0.0
     * @param    string    $tool_name    The name of the tool to invoke.
     * @param    array     $arguments    The arguments for the tool.
     * @return   WP_REST_Response|WP_Error    The response object or error.
     */
    private function handle_invoke_request($tool_name, $arguments) {
        // Check if user consent is required
        $require_consent = get_option('wpmcp_require_consent', true);
        
        if ($require_consent) {
            // For tools that modify data, require consent
            $modifying_tools = array('wp_call_endpoint');
            $modifying_methods = array('POST', 'PUT', 'DELETE', 'PATCH');
            
            $requires_consent = false;
            
            if (in_array($tool_name, $modifying_tools)) {
                if ($tool_name === 'wp_call_endpoint' && 
                    isset($arguments['method']) && 
                    in_array(strtoupper($arguments['method']), $modifying_methods)) {
                    $requires_consent = true;
                }
            }
            
            if ($requires_consent) {
                // Check if consent has been granted
                $consent_granted = $this->consent_manager->check_consent($tool_name, $arguments);
                
                if (!$consent_granted) {
                    // Return error indicating consent is required
                    return $this->error_handler->create_error(
                        'consent_required',
                        'User consent is required for this operation',
                        403,
                        array(
                            'consent_url' => rest_url('wpmcp/v1/consent'),
                            'tool' => $tool_name,
                            'arguments' => $arguments
                        )
                    );
                }
            }
        }
        
        // Handle different tools
        switch ($tool_name) {
            case 'wp_discover_endpoints':
                return $this->handle_discover_endpoints();
                
            case 'wp_call_endpoint':
                if (!isset($arguments['endpoint'])) {
                    return $this->error_handler->create_error('missing_endpoint', 'Endpoint parameter is required', 400);
                }
                
                $endpoint = $arguments['endpoint'];
                $method = isset($arguments['method']) ? strtoupper($arguments['method']) : 'GET';
                $params = isset($arguments['params']) ? $arguments['params'] : array();
                
                return $this->handle_call_endpoint($endpoint, $method, $params);
                
            case 'resources/list':
                $cursor = isset($arguments['cursor']) ? $arguments['cursor'] : null;
                return $this->handle_list_resources($cursor);
                
            case 'resources/read':
                if (!isset($arguments['uri'])) {
                    return $this->error_handler->create_error('missing_uri', 'URI parameter is required', 400);
                }
                
                return $this->handle_read_resource($arguments['uri']);
                
            case 'resources/subscribe':
                if (!isset($arguments['uri'])) {
                    return $this->error_handler->create_error('missing_uri', 'URI parameter is required', 400);
                }
                
                return $this->handle_subscribe_resource($arguments['uri']);
                
            case 'prompts/list':
                return $this->handle_list_prompts();
                
            case 'prompts/get':
                if (!isset($arguments['name'])) {
                    return $this->error_handler->create_error('missing_name', 'Name parameter is required', 400);
                }
                
                $prompt_args = isset($arguments['arguments']) ? $arguments['arguments'] : array();
                return $this->handle_get_prompt($arguments['name'], $prompt_args);
                
            default:
                return $this->error_handler->create_error('unknown_tool', 'Unknown tool name: ' . $tool_name, 400);
        }
    }
    
    /**
     * Handle discover endpoints requests.
     *
     * @since    2.0.0
     * @return   WP_REST_Response    The response object.
     */
    private function handle_discover_endpoints() {
        // Get all registered REST routes
        $rest_server = rest_get_server();
        $routes = $rest_server->get_routes();
        
        $endpoints = array();
        
        foreach ($routes as $route => $route_handlers) {
            if (empty($route_handlers)) {
                continue;
            }
            
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
            if ($namespace != 'wp/v2' && $namespace != 'wpmcp/v1') {
                continue;
            }
            
            // Get available methods
            $methods = array();
            foreach ($route_handlers as $handler) {
                if (isset($handler['methods'])) {
                    $handler_methods = $handler['methods'];
                    foreach ($handler_methods as $method => $allowed) {
                        if ($allowed && !in_array($method, $methods)) {
                            $methods[] = $method;
                        }
                    }
                }
            }
            
            $endpoints[] = array(
                'path' => $route,
                'namespace' => $namespace,
                'methods' => $methods
            );
        }
        
        return rest_ensure_response(array(
            'type' => 'success',
            'data' => $endpoints
        ));
    }
    
    /**
     * Handle call endpoint requests.
     *
     * @since    2.0.0
     * @param    string    $endpoint    The endpoint to call.
     * @param    string    $method      The HTTP method to use.
     * @param    array     $params      The parameters for the request.
     * @return   WP_REST_Response|WP_Error    The response object or error.
     */
    private function handle_call_endpoint($endpoint, $method, $params) {
        // Ensure the endpoint starts with a slash
        if (substr($endpoint, 0, 1) !== '/') {
            $endpoint = '/' . $endpoint;
        }
        
        // Determine if this is a WP REST API endpoint
        $is_wp_api = (strpos($endpoint, '/wp/v2/') === 0);
        
        // For security, check if this endpoint is allowed
        if ($is_wp_api) {
            $allowed_endpoints = get_option('wpmcp_allowed_endpoints', array());
            $endpoint_type = $this->get_endpoint_type($endpoint);
            
            if (!in_array($endpoint_type, $allowed_endpoints)) {
                return $this->error_handler->create_error(
                    'forbidden_endpoint', 
                    'Access to this endpoint type is not allowed', 
                    403
                );
            }
        }
        
        // Get an admin user and set as current user
        $admin_users = get_users(array('role' => 'administrator', 'number' => 1));
        if (!empty($admin_users)) {
            $admin_user = $admin_users[0];
            wp_set_current_user($admin_user->ID);
        }
        
        // Create a REST request
        $server = rest_get_server();
        $request = new WP_REST_Request($method, $endpoint);
        
        // Add parameters based on method
        if ($method === 'GET') {
            $request->set_query_params($params);
        } else {
            $request->set_body_params($params);
        }
        
        // Dispatch the request
        $response = $server->dispatch($request);
        
        // Handle the response
        if ($response->is_error()) {
            $error = $response->as_error();
            return $this->error_handler->create_error(
                'api_error', 
                'API returned error: ' . $error->get_error_message(), 
                $error->get_error_code()
            );
        }
        
        return rest_ensure_response(array(
            'type' => 'success',
            'data' => $response->get_data()
        ));
    }
    
    /**
     * Handle list resources requests.
     *
     * @since    2.0.0
     * @param    string|null    $cursor    Pagination cursor.
     * @return   WP_REST_Response    The response object.
     */
    private function handle_list_resources($cursor = null) {
        $result = $this->resources_handler->list_resources(array('cursor' => $cursor));
        
        return rest_ensure_response(array(
            'type' => 'success',
            'data' => $result
        ));
    }
    
    /**
     * Handle read resource requests.
     *
     * @since    2.0.0
     * @param    string    $uri    Resource URI.
     * @return   WP_REST_Response|WP_Error    The response object or error.
     */
    private function handle_read_resource($uri) {
        $result = $this->resources_handler->read_resource($uri);
        
        if (is_wp_error($result)) {
            return $this->error_handler->create_error(
                $result->get_error_code(),
                $result->get_error_message(),
                400
            );
        }
        
        return rest_ensure_response(array(
            'type' => 'success',
            'data' => $result
        ));
    }
    
    /**
     * Handle subscribe resource requests.
     *
     * @since    2.0.0
     * @param    string    $uri    Resource URI.
     * @return   WP_REST_Response    The response object.
     */
    private function handle_subscribe_resource($uri) {
        $result = $this->resources_handler->subscribe_to_resource($uri);
        
        return rest_ensure_response(array(
            'type' => 'success',
            'data' => $result
        ));
    }
    
    /**
     * Handle list prompts requests.
     *
     * @since    2.0.0
     * @return   WP_REST_Response    The response object.
     */
    private function handle_list_prompts() {
        $result = $this->prompts_handler->list_prompts();
        
        return rest_ensure_response(array(
            'type' => 'success',
            'data' => $result
        ));
    }
    
    /**
     * Handle get prompt requests.
     *
     * @since    2.0.0
     * @param    string    $name        Prompt template name.
     * @param    array     $arguments   Arguments for the prompt.
     * @return   WP_REST_Response|WP_Error    The response object or error.
     */
    private function handle_get_prompt($name, $arguments) {
        $result = $this->prompts_handler->get_prompt($name, $arguments);
        
        if (is_wp_error($result)) {
            return $this->error_handler->create_error(
                $result->get_error_code(),
                $result->get_error_message(),
                400
            );
        }
        
        return rest_ensure_response(array(
            'type' => 'success',
            'data' => $result
        ));
    }
    
    /**
     * Get the endpoint type from an endpoint path.
     *
     * @since    2.0.0
     * @param    string    $endpoint    Endpoint path.
     * @return   string                 Endpoint type.
     */
    private function get_endpoint_type($endpoint) {
        // Extract the resource type from the endpoint path
        // Example: /wp/v2/posts -> posts
        $parts = explode('/', trim($endpoint, '/'));
        if (count($parts) >= 3 && $parts[0] === 'wp' && $parts[1] === 'v2') {
            return $parts[2];
        }
        return '';
    }
    
    /**
     * Register hooks for resource change notifications.
     *
     * @since    2.0.0
     */
    public function register_notification_hooks() {
        // Only register hooks if notifications are enabled
        if (!get_option('wpmcp_enable_notifications', true)) {
            return;
        }
        
        // Post changes
        add_action('save_post', array($this, 'notify_post_updated'), 10, 3);
        add_action('delete_post', array($this, 'notify_post_deleted'));
        
        // Page changes
        add_action('save_post_page', array($this, 'notify_page_updated'), 10, 3);
        add_action('delete_post', array($this, 'notify_page_deleted'));
        
        // Category changes
        add_action('edited_category', array($this, 'notify_category_updated'));
        add_action('delete_category', array($this, 'notify_category_deleted'));
        
        // Tag changes
        add_action('edited_post_tag', array($this, 'notify_tag_updated'));
        add_action('delete_post_tag', array($this, 'notify_tag_deleted'));
        
        // Comment changes
        add_action('wp_insert_comment', array($this, 'notify_comment_added'));
        add_action('edit_comment', array($this, 'notify_comment_updated'));
        add_action('delete_comment', array($this, 'notify_comment_deleted'));
        
        // User changes
        add_action('profile_update', array($this, 'notify_user_updated'));
        add_action('delete_user', array($this, 'notify_user_deleted'));
        
        // Media changes
        add_action('add_attachment', array($this, 'notify_media_added'));
        add_action('edit_attachment', array($this, 'notify_media_updated'));
        add_action('delete_attachment', array($this, 'notify_media_deleted'));
    }
    
    /**
    * Notify when a post is updated.
    *
    * @since    2.0.0
    * @param    int       $post_id    Post ID.
    * @param    WP_Post   $post       Post object.
    * @param    bool      $update     Whether this is an update.
    */
    public function notify_post_updated($post_id, $post, $update) {
        // Skip revisions and auto-saves
        if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
            return;
        }
        
        // Skip if not a post
        if ($post->post_type !== 'post') {
            return;
        }
        
        // Check if this resource has subscribers
        $uri = 'wp://posts/' . $post_id;
        if ($this->resources_handler->has_subscribers($uri)) {
            $action = $update ? 'updated' : 'created';
            $this->notification_manager->store_notification($uri, $action, array(
                'post_id' => $post_id,
                'title' => $post->post_title
            ));
        }
    }
    
    /**
    * Notify when a post is deleted.
    *
    * @since    2.0.0
    * @param    int    $post_id    Post ID.
    */
    public function notify_post_deleted($post_id) {
        // Skip if not a post
        $post_type = get_post_type($post_id);
        if ($post_type !== 'post') {
            return;
        }
        
        // Check if this resource has subscribers
        $uri = 'wp://posts/' . $post_id;
        if ($this->resources_handler->has_subscribers($uri)) {
            $this->notification_manager->store_notification($uri, 'deleted', array(
                'post_id' => $post_id
            ));
        }
    }
    
    /**
    * Notify when a page is updated.
    *
    * @since    2.0.0
    * @param    int       $post_id    Page ID.
    * @param    WP_Post   $post       Page object.
    * @param    bool      $update     Whether this is an update.
    */
    public function notify_page_updated($post_id, $post, $update) {
        // Skip revisions and auto-saves
        if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
            return;
        }
        
        // Check if this resource has subscribers
        $uri = 'wp://pages/' . $post_id;
        if ($this->resources_handler->has_subscribers($uri)) {
            $action = $update ? 'updated' : 'created';
            $this->notification_manager->store_notification($uri, $action, array(
                'page_id' => $post_id,
                'title' => $post->post_title
            ));
        }
    }
    
    /**
    * Notify when a page is deleted.
    *
    * @since    2.0.0
    * @param    int    $post_id    Page ID.
    */
    public function notify_page_deleted($post_id) {
        // Skip if not a page
        $post_type = get_post_type($post_id);
        if ($post_type !== 'page') {
            return;
        }
        
        // Check if this resource has subscribers
        $uri = 'wp://pages/' . $post_id;
        if ($this->resources_handler->has_subscribers($uri)) {
            $this->notification_manager->store_notification($uri, 'deleted', array(
                'page_id' => $post_id
            ));
        }
    }
    
    /**
    * Notify when a category is updated.
    *
    * @since    2.0.0
    * @param    int    $term_id    Category ID.
    */
    public function notify_category_updated($term_id) {
        // Check if this resource has subscribers
        $uri = 'wp://categories/' . $term_id;
        if ($this->resources_handler->has_subscribers($uri)) {
            $category = get_term($term_id, 'category');
            $this->notification_manager->store_notification($uri, 'updated', array(
                'category_id' => $term_id,
                'name' => $category->name
            ));
        }
    }
    
    /**
    * Notify when a category is deleted.
    *
    * @since    2.0.0
    * @param    int    $term_id    Category ID.
    */
    public function notify_category_deleted($term_id) {
        // Check if this resource has subscribers
        $uri = 'wp://categories/' . $term_id;
        if ($this->resources_handler->has_subscribers($uri)) {
            $this->notification_manager->store_notification($uri, 'deleted', array(
                'category_id' => $term_id
            ));
        }
    }
    
    /**
    * Notify when a tag is updated.
    *
    * @since    2.0.0
    * @param    int    $term_id    Tag ID.
    */
    public function notify_tag_updated($term_id) {
        // Check if this resource has subscribers
        $uri = 'wp://tags/' . $term_id;
        if ($this->resources_handler->has_subscribers($uri)) {
            $tag = get_term($term_id, 'post_tag');
            $this->notification_manager->store_notification($uri, 'updated', array(
                'tag_id' => $term_id,
                'name' => $tag->name
            ));
        }
    }
    
    /**
    * Notify when a tag is deleted.
    *
    * @since    2.0.0
    * @param    int    $term_id    Tag ID.
    */
    public function notify_tag_deleted($term_id) {
        // Check if this resource has subscribers
        $uri = 'wp://tags/' . $term_id;
        if ($this->resources_handler->has_subscribers($uri)) {
            $this->notification_manager->store_notification($uri, 'deleted', array(
                'tag_id' => $term_id
            ));
        }
    }
    
    /**
    * Notify when a comment is added.
    *
    * @since    2.0.0
    * @param    int    $comment_id    Comment ID.
    */
    public function notify_comment_added($comment_id) {
        $comment = get_comment($comment_id);
        
        // Check if this resource has subscribers
        $uri = 'wp://comments/' . $comment_id;
        if ($this->resources_handler->has_subscribers($uri)) {
            $this->notification_manager->store_notification($uri, 'created', array(
                'comment_id' => $comment_id,
                'post_id' => $comment->comment_post_ID
            ));
        }
        
        // Also notify post subscribers
        $post_uri = 'wp://posts/' . $comment->comment_post_ID;
        if ($this->resources_handler->has_subscribers($post_uri)) {
            $this->notification_manager->store_notification($post_uri, 'comment_added', array(
                'post_id' => $comment->comment_post_ID,
                'comment_id' => $comment_id
            ));
        }
    }
    
    /**
    * Notify when a comment is updated.
    *
    * @since    2.0.0
    * @param    int    $comment_id    Comment ID.
    */
    public function notify_comment_updated($comment_id) {
        // Check if this resource has subscribers
        $uri = 'wp://comments/' . $comment_id;
        if ($this->resources_handler->has_subscribers($uri)) {
            $comment = get_comment($comment_id);
            $this->notification_manager->store_notification($uri, 'updated', array(
                'comment_id' => $comment_id,
                'post_id' => $comment->comment_post_ID
            ));
        }
    }
    
    /**
    * Notify when a comment is deleted.
    *
    * @since    2.0.0
    * @param    int    $comment_id    Comment ID.
    */
    public function notify_comment_deleted($comment_id) {
        // Check if this resource has subscribers
        $uri = 'wp://comments/' . $comment_id;
        if ($this->resources_handler->has_subscribers($uri)) {
            $this->notification_manager->store_notification($uri, 'deleted', array(
                'comment_id' => $comment_id
            ));
        }
    }
    
    /**
    * Notify when a user is updated.
    *
    * @since    2.0.0
    * @param    int    $user_id    User ID.
    */
    public function notify_user_updated($user_id) {
        // Check if this resource has subscribers
        $uri = 'wp://users/' . $user_id;
        if ($this->resources_handler->has_subscribers($uri)) {
            $user = get_userdata($user_id);
            $this->notification_manager->store_notification($uri, 'updated', array(
                'user_id' => $user_id,
                'username' => $user->user_login
            ));
        }
    }
    
    /**
    * Notify when a user is deleted.
    *
    * @since    2.0.0
    * @param    int    $user_id    User ID.
    */
    public function notify_user_deleted($user_id) {
        // Check if this resource has subscribers
        $uri = 'wp://users/' . $user_id;
        if ($this->resources_handler->has_subscribers($uri)) {
            $this->notification_manager->store_notification($uri, 'deleted', array(
                'user_id' => $user_id
            ));
        }
    }
    
    /**
    * Notify when media is added.
    *
    * @since    2.0.0
    * @param    int    $attachment_id    Attachment ID.
    */
    public function notify_media_added($attachment_id) {
        // Check if this resource has subscribers
        $uri = 'wp://media/' . $attachment_id;
        if ($this->resources_handler->has_subscribers($uri)) {
            $attachment = get_post($attachment_id);
            $this->notification_manager->store_notification($uri, 'created', array(
                'media_id' => $attachment_id,
                'title' => $attachment->post_title
            ));
        }
    }
    
    /**
    * Notify when media is updated.
    *
    * @since    2.0.0
    * @param    int    $attachment_id    Attachment ID.
    */
    public function notify_media_updated($attachment_id) {
        // Check if this resource has subscribers
        $uri = 'wp://media/' . $attachment_id;
        if ($this->resources_handler->has_subscribers($uri)) {
            $attachment = get_post($attachment_id);
            $this->notification_manager->store_notification($uri, 'updated', array(
                'media_id' => $attachment_id,
                'title' => $attachment->post_title
            ));
        }
    }
    
    /**
    * Notify when media is deleted.
    *
    * @since    2.0.0
    * @param    int    $attachment_id    Attachment ID.
    */
    public function notify_media_deleted($attachment_id) {
        // Check if this resource has subscribers
        $uri = 'wp://media/' . $attachment_id;
        if ($this->resources_handler->has_subscribers($uri)) {
            $this->notification_manager->store_notification($uri, 'deleted', array(
                'media_id' => $attachment_id
            ));
        }
    }
    
    /**
    * Register AJAX handlers for the public-facing side of the site.
    *
    * @since    2.0.0
    */
    public function register_ajax_handlers() {
        add_action('wp_ajax_nopriv_wpmcp_consent', array($this->consent_manager, 'handle_ajax_consent'));
        add_action('wp_ajax_wpmcp_consent', array($this->consent_manager, 'handle_ajax_consent'));
    }
    
    /**
    * Add consent UI to the footer of the site.
    *
    * @since    2.0.0
    */
    public function add_consent_ui() {
        // Only add if consent is required
        if (!get_option('wpmcp_require_consent', true)) {
            return;
        }
        
        include_once plugin_dir_path(__FILE__) . 'partials/wpmcp-consent-display.php';
    }
}

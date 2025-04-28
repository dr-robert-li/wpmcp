<?php
/**
 * The core plugin class.
 *
 * @since      2.0.0
 * @package    WPMCP
 * @subpackage WPMCP/includes
 */

class WPMCP {
    
    /**
     * The loader that's responsible for maintaining and registering all hooks.
     *
     * @since    2.0.0
     * @access   protected
     * @var      WPMCP_Loader    $loader    Maintains and registers all hooks for the plugin.
     */
    protected $loader;
    
    /**
     * The API key for authentication.
     *
     * @since    2.0.0
     * @access   private
     * @var      string    $api_key    API key for authentication.
     */
    private $api_key;
    
    /**
     * The resources handler instance.
     *
     * @since    2.0.0
     * @access   private
     * @var      WPMCP_Resources_Handler    $resources_handler    Handles resource operations.
     */
    private $resources_handler;
    
    /**
     * The notification manager instance.
     *
     * @since    2.0.0
     * @access   private
     * @var      WPMCP_Notification_Manager    $notification_manager    Handles notifications.
     */
    private $notification_manager;
    
    /**
     * The prompts handler instance.
     *
     * @since    2.0.0
     * @access   private
     * @var      WPMCP_Prompts_Handler    $prompts_handler    Handles prompts.
     */
    private $prompts_handler;
    
    /**
     * The completion handler instance.
     *
     * @since    2.0.0
     * @access   private
     * @var      WPMCP_Completion_Handler    $completion_handler    Handles completions.
     */
    private $completion_handler;
    
    /**
     * The error handler instance.
     *
     * @since    2.0.0
     * @access   private
     * @var      WPMCP_Error_Handler    $error_handler    Handles errors.
     */
    private $error_handler;
    
    /**
     * The consent manager instance.
     *
     * @since    2.0.0
     * @access   private
     * @var      WPMCP_Consent_Manager    $consent_manager    Handles user consent.
     */
    private $consent_manager;
    
    /**
     * Define the core functionality of the plugin.
     *
     * @since    2.0.0
     */
    public function __construct() {
        $this->load_dependencies();
        $this->define_admin_hooks();
        $this->define_public_hooks();
        $this->define_api_hooks();
        
        // Load API key from options
        $this->api_key = get_option('wpmcp_api_key', '');
        
        // Initialize handlers
        $this->resources_handler = new WPMCP_Resources_Handler();
        $this->notification_manager = new WPMCP_Notification_Manager();
        $this->prompts_handler = new WPMCP_Prompts_Handler();
        $this->completion_handler = new WPMCP_Completion_Handler();
        $this->error_handler = new WPMCP_Error_Handler();
        $this->consent_manager = new WPMCP_Consent_Manager();
    }
    
    /**
     * Load the required dependencies for this plugin.
     *
     * @since    2.0.0
     * @access   private
     */
    private function load_dependencies() {
        // Core classes
        require_once WPMCP_PLUGIN_DIR . 'includes/class-wpmcp-loader.php';
        require_once WPMCP_PLUGIN_DIR . 'admin/class-wpmcp-admin.php';
        require_once WPMCP_PLUGIN_DIR . 'public/class-wpmcp-public.php';
        
        // Handler classes
        require_once WPMCP_PLUGIN_DIR . 'includes/class-wpmcp-resources-handler.php';
        require_once WPMCP_PLUGIN_DIR . 'includes/class-wpmcp-notification-manager.php';
        require_once WPMCP_PLUGIN_DIR . 'includes/class-wpmcp-prompts-handler.php';
        require_once WPMCP_PLUGIN_DIR . 'includes/class-wpmcp-completion-handler.php';
        require_once WPMCP_PLUGIN_DIR . 'includes/class-wpmcp-error-handler.php';
        require_once WPMCP_PLUGIN_DIR . 'includes/class-wpmcp-consent-manager.php';
        
        $this->loader = new WPMCP_Loader();
    }    
    
    /**
     * Register all of the hooks related to the admin area functionality
     * of the plugin.
     *
     * @since    2.0.0
     * @access   private
     */
    private function define_admin_hooks() {
        $plugin_admin = new WPMCP_Admin($this->get_plugin_name(), $this->get_version());
        
        $this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_styles');
        $this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts');
        $this->loader->add_action('admin_menu', $plugin_admin, 'add_admin_menu');
        $this->loader->add_action('admin_init', $plugin_admin, 'register_settings');
    }
    
    /**
     * Register all of the hooks related to the public-facing functionality
     * of the plugin.
     *
     * @since    2.0.0
     * @access   private
     */
    private function define_public_hooks() {
        $plugin_public = new WPMCP_Public($this->get_plugin_name(), $this->get_version());
        
        $this->loader->add_action('wp_enqueue_scripts', $plugin_public, 'enqueue_styles');
        $this->loader->add_action('wp_enqueue_scripts', $plugin_public, 'enqueue_scripts');
    }
    
    /**
     * Register all of the hooks related to the API functionality
     * of the plugin.
     *
     * @since    2.0.0
     * @access   private
     */
    private function define_api_hooks() {
        // Register REST API routes
        $this->loader->add_action('rest_api_init', $this, 'register_rest_routes');
        
        // Add hooks for resource change notifications
        $this->loader->add_action('save_post', $this, 'handle_post_update', 10, 3);
        $this->loader->add_action('deleted_post', $this, 'handle_post_delete');
        $this->loader->add_action('edited_terms', $this, 'handle_term_update', 10, 2);
        $this->loader->add_action('delete_term', $this, 'handle_term_delete', 10, 4);
        $this->loader->add_action('profile_update', $this, 'handle_user_update');
        $this->loader->add_action('delete_user', $this, 'handle_user_delete');
        $this->loader->add_action('add_attachment', $this, 'handle_media_update');
        $this->loader->add_action('edit_attachment', $this, 'handle_media_update');
        $this->loader->add_action('delete_attachment', $this, 'handle_media_delete');
    }
    
    /**
     * Register REST API routes.
     *
     * @since    2.0.0
     */
    public function register_rest_routes() {
        register_rest_route('wpmcp/v1', '/data', array(
            'methods' => 'POST',
            'callback' => array($this, 'handle_mcp_request'),
            'permission_callback' => array($this, 'verify_api_key')
        ));
        
        // Add a route for resource notifications (webhook style)
        register_rest_route('wpmcp/v1', '/notifications', array(
            'methods' => 'GET',
            'callback' => array($this, 'handle_notifications_request'),
            'permission_callback' => array($this, 'verify_api_key')
        ));
        
        // Add a route for user consent
        register_rest_route('wpmcp/v1', '/consent', array(
            'methods' => 'POST',
            'callback' => array($this, 'handle_consent_request'),
            'permission_callback' => array($this, 'verify_api_key')
        ));
    }
    
    /**
     * Verify API key for authentication.
     *
     * @since    2.0.0
     * @param    WP_REST_Request    $request    The request.
     * @return   bool                           Whether the request is authenticated.
     */
    public function verify_api_key($request) {
        $headers = $request->get_headers();
        
        // Debug output to see what headers are being received
        error_log('WPMCP Debug - Headers: ' . print_r($headers, true));
        
        // Check for API key in headers (WordPress normalizes header names to lowercase)
        if (isset($headers['x-api-key']) && !empty($headers['x-api-key'][0])) {
            return $headers['x-api-key'][0] === $this->api_key;
        }
        
        // Also check for other common header variations
        $header_variations = ['x-apikey', 'x_api_key', 'apikey', 'api_key'];
        foreach ($header_variations as $header) {
            if (isset($headers[$header]) && !empty($headers[$header][0])) {
                return $headers[$header][0] === $this->api_key;
            }
        }
        
        // Check for API key in request body as fallback
        $json_str = file_get_contents('php://input');
        $data = json_decode($json_str, true);
        
        if (isset($data['api_key']) && $data['api_key'] === $this->api_key) {
            return true;
        }
        
        return false;
    }    
    
    /**
     * Handle MCP request.
     *
     * @since    2.0.0
     * @param    WP_REST_Request    $request    The request.
     * @return   WP_REST_Response|WP_Error      The response or error.
     */
    public function handle_mcp_request($request) {
        // Get the raw POST data
        $json_str = file_get_contents('php://input');
        $data = json_decode($json_str, true);
        
        // Basic validation
        if (!$data || !isset($data['type'])) {
            $error = new WP_Error('invalid_request', 'Invalid request format', array('status' => 400));
            return rest_ensure_response($this->error_handler->convert_wp_error($error));
        }
        
        // Handle different MCP request types
        try {
            switch ($data['type']) {
                case 'invoke':
                    $result = $this->handle_invoke($data);
                    break;
                    
                case 'describe':
                    $result = $this->handle_describe();
                    break;
                    
                case 'resources/changed':
                    $result = $this->handle_resources_changed($data);
                    break;
                    
                default:
                    $error = new WP_Error('invalid_type', 'Invalid request type', array('status' => 400));
                    return rest_ensure_response($this->error_handler->convert_wp_error($error));
            }
            
            // If result is a WP_Error, convert it to MCP error format
            if (is_wp_error($result)) {
                return rest_ensure_response($this->error_handler->convert_wp_error($result));
            }
            
            return rest_ensure_response($result);
            
        } catch (Exception $e) {
            $error = $this->error_handler->create_error(
                WPMCP_Error_Handler::INTERNAL_ERROR,
                $e->getMessage(),
                array('trace' => $e->getTraceAsString())
            );
            return rest_ensure_response($error);
        }
    }
    
    /**
     * Handle notifications request.
     *
     * @since    2.0.0
     * @param    WP_REST_Request    $request    The request.
     * @return   WP_REST_Response|WP_Error      The response or error.
     */
    public function handle_notifications_request($request) {
        $cursor = $request->get_param('cursor');
        $result = $this->notification_manager->get_notifications($cursor);
        
        return rest_ensure_response($result);
    }
    
    /**
     * Handle consent request.
     *
     * @since    2.0.0
     * @param    WP_REST_Request    $request    The request.
     * @return   WP_REST_Response|WP_Error      The response or error.
     */
    public function handle_consent_request($request) {
        $params = $request->get_json_params();
        
        if (!isset($params['tool']) || !isset($params['session_id'])) {
            $error = new WP_Error('invalid_request', 'Missing required parameters', array('status' => 400));
            return rest_ensure_response($this->error_handler->convert_wp_error($error));
        }
        
        $tool_name = $params['tool'];
        $arguments = isset($params['arguments']) ? $params['arguments'] : array();
        $session_id = $params['session_id'];
        $user_id = isset($params['user_id']) ? $params['user_id'] : 'anonymous';
        
        // Record consent
        $consent_recorded = $this->consent_manager->record_consent($tool_name, $arguments, $user_id, $session_id);
        
        if (!$consent_recorded) {
            $error = new WP_Error('consent_error', 'Failed to record consent', array('status' => 500));
            return rest_ensure_response($this->error_handler->convert_wp_error($error));
        }
        
        // Generate consent token
        $token = $this->consent_manager->generate_consent_token($tool_name);
        
        return rest_ensure_response(array(
            'success' => true,
            'token' => $token,
            'expires_in' => 300 // 5 minutes
        ));
    }
    
    /**
     * Handle invoke request.
     *
     * @since    2.0.0
     * @param    array    $data    Request data.
     * @return   array|WP_Error    Response data or error.
     */
    private function handle_invoke($data) {
        if (!isset($data['name']) || !isset($data['arguments'])) {
            return new WP_Error('invalid_invoke', 'Invalid invoke request', array('status' => 400));
        }
        
        $tool_name = $data['name'];
        $arguments = $data['arguments'];
        $consent_token = isset($data['consentToken']) ? $data['consentToken'] : null;
        
        // Check if consent is required for this tool
        if ($this->consent_manager->is_consent_required($tool_name)) {
            // Verify consent
            if (!$this->consent_manager->verify_consent($tool_name, $arguments, $consent_token)) {
                // Return consent request details
                $consent_details = $this->consent_manager->get_consent_request_details($tool_name, $arguments);
                
                return array(
                    'type' => 'consent_required',
                    'data' => $consent_details
                );
            }
        }
        
        // Handle different tools
        switch ($tool_name) {
            case 'wp_discover_endpoints':
                return $this->discover_endpoints();
                
            case 'wp_call_endpoint':
                if (!isset($arguments['endpoint'])) {
                    return new WP_Error('missing_endpoint', 'Endpoint parameter is required', array('status' => 400));
                }
                
                $endpoint = $arguments['endpoint'];
                $method = isset($arguments['method']) ? strtoupper($arguments['method']) : 'GET';
                $params = isset($arguments['params']) ? $arguments['params'] : array();
                
                return $this->call_endpoint($endpoint, $method, $params);
                
            // Resource-related tools
            case 'resources/list':
                $cursor = isset($arguments['cursor']) ? $arguments['cursor'] : null;
                return $this->resources_handler->list_resources(array('cursor' => $cursor));
                
            case 'resources/read':
                if (!isset($arguments['uri'])) {
                    return new WP_Error('missing_uri', 'URI parameter is required', array('status' => 400));
                }
                return $this->resources_handler->read_resource($arguments['uri']);
                
            case 'resources/templates/list':
                return $this->resources_handler->list_resource_templates();
                
            case 'resources/subscribe':
                if (!isset($arguments['uri'])) {
                    return new WP_Error('missing_uri', 'URI parameter is required', array('status' => 400));
                }
                return $this->resources_handler->subscribe_to_resource($arguments['uri']);
                
            case 'resources/notifications/list':
                $cursor = isset($arguments['cursor']) ? $arguments['cursor'] : null;
                return $this->notification_manager->get_notifications($cursor);
                
            case 'resources/notifications/clear':
                $notification_ids = isset($arguments['ids']) ? $arguments['ids'] : array();
                return array(
                    'success' => $this->notification_manager->clear_notifications($notification_ids)
                );
                
            // Prompt-related tools
            case 'prompts/list':
                return $this->prompts_handler->list_prompts();
                
            case 'prompts/get':
                if (!isset($arguments['name'])) {
                    return new WP_Error('missing_name', 'Prompt name parameter is required', array('status' => 400));
                }
                return $this->prompts_handler->get_prompt($arguments['name'], $arguments);
                
            // Completion-related tools
            case 'completion/complete':
                if (!isset($arguments['tool']) || !isset($arguments['argument']) || !isset($arguments['partial'])) {
                    return new WP_Error('missing_parameters', 'Tool, argument, and partial parameters are required', array('status' => 400));
                }
                
                return $this->completion_handler->complete_argument(
                    $arguments['tool'],
                    $arguments['argument'],
                    $arguments['partial'],
                    isset($arguments['context']) ? $arguments['context'] : array()
                );
                
            default:
                return new WP_Error('unknown_tool', 'Unknown tool name', array('status' => 400));
        }
    }
    
    /**
     * Handle resources/changed request.
     *
     * @since    2.0.0
     * @param    array    $data    Request data.
     * @return   array             Response data.
     */
    private function handle_resources_changed($data) {
        // This method handles the resources/changed request type
        // It should return any resources that have changed since the last request
        
        $cursor = isset($data['cursor']) ? $data['cursor'] : null;
        $result = $this->notification_manager->get_notifications($cursor);
        
        return array(
            'type' => 'resources/changed',
            'data' => $result
        );
    }
    
    /**
     * Discover available endpoints.
     *
     * @since    2.0.0
     * @return   array    Endpoints data.
     */
    private function discover_endpoints() {
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
        
        return array(
            'type' => 'success',
            'data' => $endpoints
        );
    }
    
    /**
     * Call a WordPress REST API endpoint.
     *
     * @since    2.0.0
     * @param    string          $endpoint    The endpoint path.
     * @param    string          $method      The HTTP method.
     * @param    array           $params      The request parameters.
     * @return   array|WP_Error               The response or error.
     */
    private function call_endpoint($endpoint, $method, $params) {
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
                return new WP_Error(
                    'forbidden_endpoint', 
                    'Access to this endpoint type is not allowed', 
                    array('status' => 403)
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
            return new WP_Error(
                'api_error', 
                'API returned error: ' . $error->get_error_message(), 
                array('status' => $error->get_error_code())
            );
        }
        
        return array(
            'type' => 'success',
            'data' => $response->get_data()
        );
    }
    
    /**
     * Extract the resource type from an endpoint path.
     *
     * @since    2.0.0
     * @param    string    $endpoint    The endpoint path.
     * @return   string                 The resource type.
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
     * Handle describe request.
     *
     * @since    2.0.0
     * @return   array    Description data.
     */
    private function handle_describe() {
        // Return a comprehensive tool description
        return array(
            'type' => 'description',
            'data' => array(
                'name' => 'wpmcp',
                'version' => $this->get_version(),
                'description' => 'WordPress Model Context Protocol Server enabling AI assistants to interact with this WordPress site.',
                'functions' => array(
                    // WordPress API tools
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
                    
                    // Resource tools
                    array(
                        'name' => 'resources/list',
                        'description' => 'Lists available WordPress resources that can be accessed.',
                        'parameters' => array(
                            'type' => 'object',
                            'properties' => array(
                                'cursor' => array(
                                    'type' => 'string',
                                    'description' => 'Pagination cursor for retrieving next page of results'
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
                                    'description' => 'URI of the resource to read (e.g., wp://posts/123)'
                                )
                            ),
                            'required' => array('uri')
                        )
                    ),
                    array(
                        'name' => 'resources/templates/list',
                        'description' => 'Lists available resource templates that can be used to construct resource URIs.',
                        'parameters' => array(
                            'type' => 'object',
                            'properties' => array(),
                            'required' => array()
                        )
                    ),
                    array(
                        'name' => 'resources/subscribe',
                        'description' => 'Subscribes to changes for a specific resource.',
                        'parameters' => array(
                            'type' => 'object',
                            'properties' => array(
                                'uri' => array(
                                    'type' => 'string',
                                    'description' => 'URI of the resource to subscribe to'
                                )
                            ),
                            'required' => array('uri')
                        )
                    ),
                    array(
                        'name' => 'resources/notifications/list',
                        'description' => 'Lists notifications for resource changes.',
                        'parameters' => array(
                            'type' => 'object',
                            'properties' => array(
                                'cursor' => array(
                                    'type' => 'string',
                                    'description' => 'Pagination cursor for retrieving next page of notifications'
                                )
                            ),
                            'required' => array()
                        )
                    ),
                    array(
                        'name' => 'resources/notifications/clear',
                        'description' => 'Clears resource change notifications.',
                        'parameters' => array(
                            'type' => 'object',
                            'properties' => array(
                                'ids' => array(
                                    'type' => 'array',
                                    'items' => array(
                                        'type' => 'string'
                                    ),
                                    'description' => 'IDs of notifications to clear'
                                )
                            ),
                            'required' => array()
                        )
                    ),
                    
                    // Prompt tools
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
                        'description' => 'Gets a specific prompt template with messages.',
                        'parameters' => array(
                            'type' => 'object',
                            'properties' => array(
                                'name' => array(
                                    'type' => 'string',
                                    'description' => 'Name of the prompt template'
                                ),
                                'arguments' => array(
                                    'type' => 'object',
                                    'description' => 'Arguments for the prompt template'
                                )
                            ),
                            'required' => array('name')
                        )
                    ),
                    
                    // Completion tools
                    array(
                        'name' => 'completion/complete',
                        'description' => 'Completes argument values based on partial input.',
                        'parameters' => array(
                            'type' => 'object',
                            'properties' => array(
                                'tool' => array(
                                    'type' => 'string',
                                    'description' => 'Name of the tool'
                                ),
                                'argument' => array(
                                    'type' => 'string',
                                    'description' => 'Name of the argument to complete'
                                ),
                                'partial' => array(
                                    'type' => 'string',
                                    'description' => 'Partial value to complete'
                                ),
                                'context' => array(
                                    'type' => 'object',
                                    'description' => 'Additional context for completion'
                                )
                            ),
                            'required' => array('tool', 'argument', 'partial')
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
                        'name' => 'Get categories',
                        'tool' => 'wp_call_endpoint',
                        'args' => array(
                            'endpoint' => '/wp/v2/categories',
                            'method' => 'GET'
                        )
                    ),
                    array(
                        'name' => 'Read a post resource',
                        'tool' => 'resources/read',
                        'args' => array(
                            'uri' => 'wp://posts/1'
                        )
                    ),
                    array(
                        'name' => 'List available resources',
                        'tool' => 'resources/list',
                        'args' => array()
                    ),
                    array(
                        'name' => 'Get SEO optimization prompt',
                        'tool' => 'prompts/get',
                        'args' => array(
                            'name' => 'seo_optimize',
                            'arguments' => array(
                                'content' => 'This is the content to optimize for SEO.',
                                'keywords' => 'wordpress, mcp, ai'
                            )
                        )
                    )
                )
            )
        );
    }

    /**
     * Handle post update event.
     *
     * @since    2.0.0
     * @param    int       $post_id    Post ID.
     * @param    WP_Post   $post       Post object.
     * @param    bool      $update     Whether this is an existing post being updated.
     */
    public function handle_post_update($post_id, $post, $update) {
        // Skip revisions and auto-saves
        if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
            return;
        }
        
        // Skip if post is not published
        if ($post->post_status !== 'publish') {
            return;
        }
        
        $post_type = $post->post_type;
        
        // Determine resource type based on post type
        $resource_type = '';
        switch ($post_type) {
            case 'post':
                $resource_type = 'posts';
                break;
                
            case 'page':
                $resource_type = 'pages';
                break;
                
            case 'attachment':
                $resource_type = 'media';
                break;
                
            default:
                return; // Skip unknown post types
        }
        
        // Create resource URI
        $uri = 'wp://' . $resource_type . '/' . $post_id;
        
        // Check if this resource has subscribers
        if ($this->resources_handler->has_subscribers($uri)) {
            // Store notification
            $this->notification_manager->store_notification($uri, 'updated', array(
                'title' => $post->post_title,
                'type' => $post_type
            ));
        }
    }
    
    /**
     * Handle post delete event.
     *
     * @since    2.0.0
     * @param    int    $post_id    Post ID.
     */
    public function handle_post_delete($post_id) {
        // Get post type before it's deleted
        $post_type = get_post_type($post_id);
        
        if (!$post_type) {
            return;
        }
        
        // Determine resource type based on post type
        $resource_type = '';
        switch ($post_type) {
            case 'post':
                $resource_type = 'posts';
                break;
                
            case 'page':
                $resource_type = 'pages';
                break;
                
            case 'attachment':
                $resource_type = 'media';
                break;
                
            default:
                return; // Skip unknown post types
        }
        
        // Create resource URI
        $uri = 'wp://' . $resource_type . '/' . $post_id;
        
        // Check if this resource has subscribers
        if ($this->resources_handler->has_subscribers($uri)) {
            // Store notification
            $this->notification_manager->store_notification($uri, 'deleted', array(
                'id' => $post_id,
                'type' => $post_type
            ));
        }
    }
    
    /**
     * Handle term update event.
     *
     * @since    2.0.0
     * @param    int       $term_id    Term ID.
     * @param    string    $taxonomy   Taxonomy name.
     */
    public function handle_term_update($term_id, $taxonomy) {
        // Determine resource type based on taxonomy
        $resource_type = '';
        switch ($taxonomy) {
            case 'category':
                $resource_type = 'categories';
                break;
                
            case 'post_tag':
                $resource_type = 'tags';
                break;
                
            default:
                return; // Skip unknown taxonomies
        }
        
        // Create resource URI
        $uri = 'wp://' . $resource_type . '/' . $term_id;
        
        // Check if this resource has subscribers
        if ($this->resources_handler->has_subscribers($uri)) {
            // Get term data
            $term = get_term($term_id, $taxonomy);
            
            if (!is_wp_error($term)) {
                // Store notification
                $this->notification_manager->store_notification($uri, 'updated', array(
                    'name' => $term->name,
                    'taxonomy' => $taxonomy
                ));
            }
        }
    }
    
    /**
     * Handle term delete event.
     *
     * @since    2.0.0
     * @param    int       $term_id    Term ID.
     * @param    int       $tt_id      Term taxonomy ID.
     * @param    string    $taxonomy   Taxonomy name.
     * @param    mixed     $deleted_term Deleted term object.
     */
    public function handle_term_delete($term_id, $tt_id, $taxonomy, $deleted_term) {
        // Determine resource type based on taxonomy
        $resource_type = '';
        switch ($taxonomy) {
            case 'category':
                $resource_type = 'categories';
                break;
                
            case 'post_tag':
                $resource_type = 'tags';
                break;
                
            default:
                return; // Skip unknown taxonomies
        }
        
        // Create resource URI
        $uri = 'wp://' . $resource_type . '/' . $term_id;
        
        // Check if this resource has subscribers
        if ($this->resources_handler->has_subscribers($uri)) {
            // Store notification
            $this->notification_manager->store_notification($uri, 'deleted', array(
                'id' => $term_id,
                'taxonomy' => $taxonomy,
                'name' => $deleted_term->name
            ));
        }
    }
    
    /**
     * Handle user update event.
     *
     * @since    2.0.0
     * @param    int    $user_id    User ID.
     */
    public function handle_user_update($user_id) {
        // Create resource URI
        $uri = 'wp://users/' . $user_id;
        
        // Check if this resource has subscribers
        if ($this->resources_handler->has_subscribers($uri)) {
            // Get user data
            $user = get_userdata($user_id);
            
            if ($user) {
                // Store notification
                $this->notification_manager->store_notification($uri, 'updated', array(
                    'name' => $user->display_name,
                    'login' => $user->user_login
                ));
            }
        }
    }
    
    /**
     * Handle user delete event.
     *
     * @since    2.0.0
     * @param    int    $user_id    User ID.
     */
    public function handle_user_delete($user_id) {
        // Create resource URI
        $uri = 'wp://users/' . $user_id;
        
        // Check if this resource has subscribers
        if ($this->resources_handler->has_subscribers($uri)) {
            // Store notification
            $this->notification_manager->store_notification($uri, 'deleted', array(
                'id' => $user_id
            ));
        }
    }
    
    /**
     * Handle media update event.
     *
     * @since    2.0.0
     * @param    int    $attachment_id    Attachment ID.
     */
    public function handle_media_update($attachment_id) {
        // Create resource URI
        $uri = 'wp://media/' . $attachment_id;
        
        // Check if this resource has subscribers
        if ($this->resources_handler->has_subscribers($uri)) {
            // Get attachment data
            $attachment = get_post($attachment_id);
            
            if ($attachment) {
                // Store notification
                $this->notification_manager->store_notification($uri, 'updated', array(
                    'title' => $attachment->post_title,
                    'mime_type' => get_post_mime_type($attachment_id)
                ));
            }
        }
    }
    
    /**
     * Handle media delete event.
     *
     * @since    2.0.0
     * @param    int    $attachment_id    Attachment ID.
     */
    public function handle_media_delete($attachment_id) {
        // Create resource URI
        $uri = 'wp://media/' . $attachment_id;
        
        // Check if this resource has subscribers
        if ($this->resources_handler->has_subscribers($uri)) {
            // Store notification
            $this->notification_manager->store_notification($uri, 'deleted', array(
                'id' => $attachment_id
            ));
        }
    }
    
    /**
     * Run the plugin.
     *
     * @since    2.0.0
     */
    public function run() {
        $this->loader->run();
    }
    
    /**
     * The name of the plugin.
     *
     * @since     2.0.0
     * @return    string    The name of the plugin.
     */
    public function get_plugin_name() {
        return 'wpmcp';
    }
    
    /**
     * The version of the plugin.
     *
     * @since     2.0.0
     * @return    string    The version of the plugin.
     */
    public function get_version() {
        return WPMCP_VERSION;
    }
}

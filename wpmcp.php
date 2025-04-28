<?php
/**
 * Plugin Name: WPMCP
 * Plugin URI: https://github.com/dr-robert-li/wpmcp
 * Description: WordPress Model Context Protocol (MCP) - Enables AI assistants to interact with WordPress through REST API
 * Version: 1.0.0
 * Author: Dr. Robert Li
 * License: GPL v3
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPMCP_Plugin {
    private $api_key = '';
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('rest_api_init', array($this, 'register_rest_routes'));
        
        // Load API key from options
        $this->api_key = get_option('wpmcp_api_key', '');
    }

    public function add_admin_menu() {
        add_options_page(
            'WPMCP Settings',
            'WPMCP',
            'manage_options',
            'wpmcp-settings',
            array($this, 'settings_page')
        );
    }

    public function register_settings() {
        register_setting('wpmcp_settings', 'wpmcp_api_key', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => ''
        ));
        
        // Add additional settings for allowed endpoints/capabilities
        register_setting('wpmcp_settings', 'wpmcp_allowed_endpoints', array(
            'type' => 'array',
            'default' => array('posts', 'pages', 'categories', 'tags', 'comments', 'users')
        ));
    }

    public function settings_page() {
        ?>
        <div class="wrap">
            <h2>WordPress Model Context Protocol (MCP) Settings</h2>
            <p>This plugin enables AI assistants to interact with your WordPress site through the REST API.</p>
            
            <form method="post" action="options.php">
                <?php
                settings_fields('wpmcp_settings');
                do_settings_sections('wpmcp_settings');
                ?>
                <table class="form-table">
                    <tr>
                        <th scope="row">API Key</th>
                        <td>
                            <input type="text" name="wpmcp_api_key" value="<?php echo esc_attr(get_option('wpmcp_api_key')); ?>" class="regular-text">
                            <p class="description">API key for authentication (required for security)</p>
                            <?php if (empty(get_option('wpmcp_api_key'))): ?>
                                <button type="button" id="generate-api-key" class="button button-secondary">Generate API Key</button>
                                <script>
                                    document.getElementById('generate-api-key').addEventListener('click', function() {
                                        const apiKey = Math.random().toString(36).substring(2, 15) + 
                                                      Math.random().toString(36).substring(2, 15);
                                        document.querySelector('input[name="wpmcp_api_key"]').value = apiKey;
                                    });
                                </script>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Allowed Endpoints</th>
                        <td>
                            <?php 
                            $allowed_endpoints = get_option('wpmcp_allowed_endpoints', array('posts', 'pages', 'categories', 'tags', 'comments', 'users'));
                            $available_endpoints = array(
                                'posts' => 'Posts',
                                'pages' => 'Pages',
                                'categories' => 'Categories',
                                'tags' => 'Tags',
                                'comments' => 'Comments',
                                'users' => 'Users',
                                'media' => 'Media',
                                'plugins' => 'Plugins',
                                'themes' => 'Themes',
                                'settings' => 'Settings'
                            );
                            
                            foreach ($available_endpoints as $endpoint => $label): 
                            ?>
                                <label>
                                    <input type="checkbox" name="wpmcp_allowed_endpoints[]" value="<?php echo esc_attr($endpoint); ?>" 
                                        <?php checked(in_array($endpoint, $allowed_endpoints)); ?>>
                                    <?php echo esc_html($label); ?>
                                </label><br>
                            <?php endforeach; ?>
                            <p class="description">Select which WordPress resources can be accessed via the MCP API</p>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
            
            <h3>Usage Information</h3>
            <p>Endpoint URL: <code><?php echo esc_url(rest_url('wpmcp/v1/data')); ?></code></p>
            <p>This plugin implements the Model Context Protocol (MCP) standard, allowing AI assistants to:</p>
            <ul style="list-style-type: disc; margin-left: 20px;">
                <li>Discover available WordPress REST API endpoints</li>
                <li>Execute REST API requests with proper authentication</li>
                <li>Manage content, users, and site settings through natural language</li>
            </ul>
        </div>
        <?php
    }

    public function register_rest_routes() {
        register_rest_route('wpmcp/v1', '/data', array(
            'methods' => 'POST',
            'callback' => array($this, 'handle_mcp_request'),
            'permission_callback' => array($this, 'verify_api_key')
        ));
    }
    
    public function verify_api_key($request) {
        $headers = $request->get_headers();
        
        // Check for API key in headers (case-insensitive)
        if (isset($headers['x-api-key']) && !empty($headers['x-api-key'][0])) {
            return $headers['x-api-key'][0] === $this->api_key;
        }
        
        // Also check for X-API-Key (capital letters)
        if (isset($headers['x-api-key']) && !empty($headers['x-api-key'][0])) {
            return $headers['x-api-key'][0] === $this->api_key;
        }
        
        // Check for API key in request body as fallback
        $json_str = file_get_contents('php://input');
        $data = json_decode($json_str, true);
        
        if (isset($data['api_key']) && $data['api_key'] === $this->api_key) {
            return true;
        }
        
        return false;
    }    

    public function handle_mcp_request($request) {
        // Get the raw POST data
        $json_str = file_get_contents('php://input');
        $data = json_decode($json_str, true);

        // Basic validation
        if (!$data || !isset($data['type'])) {
            return new WP_Error('invalid_request', 'Invalid request format', array('status' => 400));
        }

        // Handle different MCP request types
        switch ($data['type']) {
            case 'invoke':
                return $this->handle_invoke($data);
            case 'describe':
                return $this->handle_describe();
            default:
                return new WP_Error('invalid_type', 'Invalid request type', array('status' => 400));
        }
    }

    private function handle_invoke($data) {
        if (!isset($data['name']) || !isset($data['arguments'])) {
            return new WP_Error('invalid_invoke', 'Invalid invoke request', array('status' => 400));
        }
        
        $tool_name = $data['name'];
        $arguments = $data['arguments'];
        
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
                
            default:
                return new WP_Error('unknown_tool', 'Unknown tool name', array('status' => 400));
        }
    }

    private function discover_endpoints() {
        // Get all registered REST routes
        $rest_server = rest_get_server();
        $routes = $rest_server->get_routes();
        
        error_log('WPMCP Debug - Number of routes found: ' . count($routes));
        
        $endpoints = array();
        
        foreach ($routes as $route => $route_handlers) {
            if (empty($route_handlers)) {
                continue;
            }
            
            // Extract namespace from the route path instead
            $namespace = '';
            $path_parts = explode('/', trim($route, '/'));
            
            if (count($path_parts) >= 2) {
                // For routes like /wp/v2/posts, the namespace would be wp/v2
                $namespace = $path_parts[0] . '/' . $path_parts[1];
            } elseif (count($path_parts) == 1) {
                // For root namespace routes
                $namespace = $path_parts[0];
            }
            
            error_log('WPMCP Debug - Route: ' . $route . ' extracted namespace: ' . $namespace);
            
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
        
        error_log('WPMCP Debug - Total endpoints found: ' . count($endpoints));
        
        return array(
            'type' => 'success',
            'data' => $endpoints
        );
    }       

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
    
    private function get_endpoint_type($endpoint) {
        // Extract the resource type from the endpoint path
        // Example: /wp/v2/posts -> posts
        $parts = explode('/', trim($endpoint, '/'));
        if (count($parts) >= 3 && $parts[0] === 'wp' && $parts[1] === 'v2') {
            return $parts[2];
        }
        return '';
    }

    private function handle_describe() {
        // Return a comprehensive tool description
        return array(
            'type' => 'description',
            'data' => array(
                'name' => 'wpmcp',
                'version' => '1.0.0',
                'description' => 'WordPress Model Context Protocol Server enabling AI assistants to interact this WordPress site.',
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
                        'name' => 'Update a post',
                        'tool' => 'wp_call_endpoint',
                        'args' => array(
                            'endpoint' => '/wp/v2/posts/123',
                            'method' => 'PUT',
                            'params' => array(
                                'title' => 'Updated Title',
                                'content' => 'Updated content for this post.'
                            )
                        )
                    ),
                    array(
                        'name' => 'Get site info',
                        'tool' => 'wp_call_endpoint',
                        'args' => array(
                            'endpoint' => '/',
                            'method' => 'GET'
                        )
                    )
                )
            )
        );
    }
}

// Initialize the plugin
new WPMCP_Plugin();
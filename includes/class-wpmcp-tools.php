<?php
/**
 * WPMCP Tools Implementation
 * 
 * Implements MCP tools for WordPress interaction
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPMCP_Tools {
    /**
     * Get all available tools with their descriptions and schemas
     */
    public static function get_tools_description() {
        return array(
            array(
                'name' => 'wp_discover_endpoints',
                'description' => 'Maps all available REST API endpoints on this WordPress site and returns their methods and namespaces.',
                'inputSchema' => array(
                    'type' => 'object',
                    'properties' => array(),
                    'required' => array()
                ),
                'annotations' => array(
                    'category' => 'discovery'
                )
            ),
            array(
                'name' => 'wp_call_endpoint',
                'description' => 'Executes specific REST API requests to the WordPress site using provided parameters.',
                'inputSchema' => array(
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
                ),
                'annotations' => array(
                    'category' => 'action'
                )
            ),
            array(
                'name' => 'wp_get_resource',
                'description' => 'Retrieves a WordPress resource by its URI.',
                'inputSchema' => array(
                    'type' => 'object',
                    'properties' => array(
                        'uri' => array(
                            'type' => 'string',
                            'description' => 'Resource URI (e.g., wordpress:/posts/1)'
                        )
                    ),
                    'required' => array('uri')
                ),
                'annotations' => array(
                    'category' => 'resource'
                )
            )
        );
    }
    
    /**
     * Execute a tool by name with given arguments
     */
    public static function execute_tool($name, $arguments) {
        switch ($name) {
            case 'wp_discover_endpoints':
                return self::discover_endpoints();
                
            case 'wp_call_endpoint':
                if (!isset($arguments['endpoint'])) {
                    return array(
                        'error' => array(
                            'code' => -32602,
                            'message' => 'Missing endpoint parameter'
                        )
                    );
                }
                
                $endpoint = $arguments['endpoint'];
                $method = isset($arguments['method']) ? strtoupper($arguments['method']) : 'GET';
                $params = isset($arguments['params']) ? $arguments['params'] : array();
                
                return self::call_endpoint($endpoint, $method, $params);
                
            case 'wp_get_resource':
                if (!isset($arguments['uri'])) {
                    return array(
                        'error' => array(
                            'code' => -32602,
                            'message' => 'Missing uri parameter'
                        )
                    );
                }
                
                return self::get_resource($arguments['uri']);
                
            default:
                return array(
                    'error' => array(
                        'code' => -32601,
                        'message' => 'Tool not found: ' . $name
                    )
                );
        }
    }
    
    /**
     * Discover available WordPress REST API endpoints
     */
    private static function discover_endpoints() {
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
                'methods' => $methods,
                'uri' => 'wordpress:' . $route
            );
        }
        
        return array(
            'endpoints' => $endpoints
        );
    }
    
    /**
     * Call a WordPress REST API endpoint
     */
    public static function call_endpoint($endpoint, $method, $params) {
        error_log('WPMCP Debug - Starting call_endpoint');
        error_log('WPMCP Debug - Endpoint: ' . $endpoint);
        error_log('WPMCP Debug - Method: ' . $method);
        error_log('WPMCP Debug - Params: ' . print_r($params, true));
        
        // Ensure the endpoint starts with a slash
        if (substr($endpoint, 0, 1) !== '/') {
            $endpoint = '/' . $endpoint;
        }
        
        // Determine if this is a WP REST API endpoint
        $is_wp_api = (strpos($endpoint, '/wp/v2/') === 0);
        
        // For security, check if this endpoint is allowed
        if ($is_wp_api) {
            // Use a default array of allowed endpoints if the option doesn't exist
            $default_allowed = array('posts', 'pages', 'categories', 'tags', 'comments', 'users', 'media', 'plugins', 'themes', 'settings');
            $allowed_endpoints = get_option('wpmcp_allowed_endpoints', $default_allowed);
            
            // If the option is empty, use the default
            if (empty($allowed_endpoints)) {
                $allowed_endpoints = $default_allowed;
            }
            
            $endpoint_type = self::get_endpoint_type($endpoint);
            error_log('WPMCP Debug - Endpoint type: ' . $endpoint_type);
            error_log('WPMCP Debug - Allowed endpoints: ' . print_r($allowed_endpoints, true));
            
            if (!in_array($endpoint_type, $allowed_endpoints)) {
                return array(
                    'error' => array(
                        'code' => 403,
                        'message' => 'Access to this endpoint type is not allowed'
                    )
                );
            }
        }
        
        // Create a REST request
        $server = rest_get_server();
        $request = new WP_REST_Request($method, $endpoint);
        
        // Add REST nonce to the request if available
        if (defined('WPMCP_REST_NONCE')) {
            $request->add_header('X-WP-Nonce', WPMCP_REST_NONCE);
        }
        
        // Add parameters based on method
        if ($method === 'GET') {
            $request->set_query_params($params);
        } else {
            $request->set_body_params($params);
        }
        
        // Mark this as an internal request to bypass normal REST API auth
        if (!defined('WPMCP_INTERNAL_REQUEST')) {
            define('WPMCP_INTERNAL_REQUEST', true);
        }
        
        // Dispatch the request
        $response = $server->dispatch($request);
        
        // Handle the response
        if ($response->is_error()) {
            $error = $response->as_error();
            return array(
                'error' => array(
                    'code' => $error->get_error_code(),
                    'message' => 'API returned error: ' . $error->get_error_message()
                )
            );
        }
        
        return array(
            'data' => $response->get_data()
        );
    }
    
    /**
     * Get a WordPress resource by URI
     */
    private static function get_resource($uri) {
        // Parse the URI to extract resource type and ID
        // Format: wordpress:/resource_type/id
        if (!preg_match('/^wordpress:\/([a-zA-Z_]+)(?:\/(\d+))?$/', $uri, $matches)) {
            return array(
                'error' => array(
                    'code' => 400,
                    'message' => 'Invalid resource URI format. Expected: wordpress:/resource_type/id'
                )
            );
        }
        
        $resource_type = $matches[1];
        $resource_id = isset($matches[2]) ? intval($matches[2]) : null;
        
        // Check if this resource type is allowed
        $allowed_endpoints = get_option('wpmcp_allowed_endpoints', array());
        if (!in_array($resource_type, $allowed_endpoints)) {
            return array(
                'error' => array(
                    'code' => 403,
                    'message' => 'Access to this resource type is not allowed'
                )
            );
        }
        
        // Map resource type to WP REST API endpoint
        $endpoint = '/wp/v2/' . $resource_type;
        if ($resource_id) {
            $endpoint .= '/' . $resource_id;
        }
        
        // Call the endpoint
        return self::call_endpoint($endpoint, 'GET', array());
    }
    
    /**
     * Extract the endpoint type from a path
     */
    private static function get_endpoint_type($endpoint) {
        // Extract the resource type from the endpoint path
        // Example: /wp/v2/posts -> posts
        $parts = explode('/', trim($endpoint, '/'));
        if (count($parts) >= 3 && $parts[0] === 'wp' && $parts[1] === 'v2') {
            return $parts[2];
        }
        return '';
    }
}

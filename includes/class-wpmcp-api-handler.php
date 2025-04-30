<?php
/**
 * Handles API functionality for WPMCP.
 *
 * @since      2.0.0
 * @package    WPMCP
 * @subpackage WPMCP/includes
 */

class WPMCP_API_Handler {
    
    /**
     * The consent manager instance.
     *
     * @since    2.0.0
     * @access   private
     * @var      WPMCP_Consent_Manager    $consent_manager    The consent manager instance.
     */
    private $consent_manager;
    
    /**
     * The error handler instance.
     *
     * @since    2.0.0
     * @access   private
     * @var      WPMCP_Error_Handler    $error_handler    The error handler instance.
     */
    private $error_handler;
    
    /**
     * Initialize the class and set its properties.
     *
     * @since    2.0.0
     * @param    WPMCP_Consent_Manager    $consent_manager    The consent manager instance.
     * @param    WPMCP_Error_Handler      $error_handler      The error handler instance.
     */
    public function __construct($consent_manager, $error_handler) {
        $this->consent_manager = $consent_manager;
        $this->error_handler = $error_handler;
    }
    
    /**
     * Discover available REST API endpoints.
     *
     * @since    2.0.0
     * @return   array|WP_Error    The endpoints or error.
     */
    public function discover_endpoints() {
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
     * @param    string    $endpoint    The endpoint path.
     * @param    string    $method      The HTTP method.
     * @param    array     $params      The request parameters.
     * @return   array|WP_Error         The response or error.
     */
    public function call_endpoint($endpoint, $method, $params) {
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
        
        // Check if this tool requires specific permissions
        $tool_permissions = get_option('wpmcp_tool_permissions', array(
            'wp_call_endpoint' => 'api_key'
        ));
        
        $required_permission = isset($tool_permissions['wp_call_endpoint']) ? 
                              $tool_permissions['wp_call_endpoint'] : 'api_key';
        
        // If admin permission is required, verify user is admin
        if ($required_permission === 'admin') {
            $current_user_id = get_current_user_id();
            if (!$current_user_id || !current_user_can('manage_options')) {
                return $this->error_handler->create_error(
                    'permission_denied',
                    'This operation requires administrator privileges',
                    403
                );
            }
        }
        
        // Check if consent is required for this operation
        if ($this->consent_manager->is_consent_required('wp_call_endpoint')) {
            $consent_granted = $this->consent_manager->check_consent('wp_call_endpoint', array(
                'endpoint' => $endpoint,
                'method' => $method,
                'params' => $params
            ));
            
            if (!$consent_granted) {
                return $this->error_handler->create_error(
                    'consent_required',
                    'User consent is required for this operation',
                    403,
                    array(
                        'consent_request' => $this->consent_manager->get_consent_request_details('wp_call_endpoint', array(
                            'endpoint' => $endpoint,
                            'method' => $method,
                            'params' => $params
                        ))
                    )
                );
            }
        }
        
        // Get an admin user and set as current user for API requests
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
        
        // Log the API request if enabled
        if (get_option('wpmcp_log_api_requests', false)) {
            $this->log_api_request($endpoint, $method, $params);
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
     * Log an API request.
     *
     * @since    2.0.0
     * @param    string    $endpoint    The endpoint path.
     * @param    string    $method      The HTTP method.
     * @param    array     $params      The request parameters.
     */
    private function log_api_request($endpoint, $method, $params) {
        // Get existing logs
        $logs = get_option('wpmcp_api_request_logs', array());
        
        // Add new log entry
        $logs[] = array(
            'endpoint' => $endpoint,
            'method' => $method,
            'params' => $params,
            'user_id' => get_current_user_id(),
            'timestamp' => current_time('mysql'),
            'ip' => $_SERVER['REMOTE_ADDR']
        );
        
        // Limit the number of logs (keep last 100)
        if (count($logs) > 100) {
            $logs = array_slice($logs, -100);
        }
        
        // Save logs
        update_option('wpmcp_api_request_logs', $logs);
    }
    
    /**
     * Get API request logs with pagination.
     *
     * @since    2.0.0
     * @param    int       $page        The page number.
     * @param    int       $per_page    Items per page.
     * @return   array                  Logs and pagination info.
     */
    public function get_api_request_logs($page = 1, $per_page = 20) {
        $logs = get_option('wpmcp_api_request_logs', array());
        
        // Reverse logs to show newest first
        $logs = array_reverse($logs);
        
        // Calculate pagination
        $total = count($logs);
        $total_pages = ceil($total / $per_page);
        $page = max(1, min($page, $total_pages));
        $offset = ($page - 1) * $per_page;
        
        // Get logs for current page
        $logs_page = array_slice($logs, $offset, $per_page);
        
        return array(
            'logs' => $logs_page,
            'total' => $total,
            'total_pages' => $total_pages,
            'current_page' => $page,
            'per_page' => $per_page
        );
    }
    
    /**
     * Clear API request logs.
     *
     * @since    2.0.0
     * @return   bool      Success status.
     */
    public function clear_api_request_logs() {
        return update_option('wpmcp_api_request_logs', array());
    }
}
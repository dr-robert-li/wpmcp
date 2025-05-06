<?php
/**
 * Plugin Name: WPMCP
 * Plugin URI: https://github.com/dr-robert-li/wpmcp
 * Description: WordPress Model Context Protocol (MCP) - Enables AI assistants to interact with WordPress through MCP protocol
 * Version: 1.1.0
 * Author: Dr. Robert Li
 * License: GPL v3
 */

if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('WPMCP_VERSION', '1.1.0');
define('WPMCP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WPMCP_PLUGIN_URL', plugin_dir_url(__FILE__));

// Include required files
require_once WPMCP_PLUGIN_DIR . 'includes/class-wpmcp-transport.php';
require_once WPMCP_PLUGIN_DIR . 'includes/class-wpmcp-tools.php';
require_once WPMCP_PLUGIN_DIR . 'includes/class-wpmcp-resources.php';

// Register activation hook
register_activation_hook(__FILE__, 'wpmcp_activate');

/**
 * Plugin activation function
 * Initializes all plugin options with default values
 */
function wpmcp_activate() {
    // Initialize API key if not set (generate a random one)
    if (get_option('wpmcp_api_key') === false) {
        $api_key = substr(md5(uniqid(rand(), true)), 0, 16);
        update_option('wpmcp_api_key', $api_key);
    }
    
    // Initialize allowed endpoints
    if (get_option('wpmcp_allowed_endpoints') === false) {
        update_option('wpmcp_allowed_endpoints', array(
            'posts', 'pages', 'categories', 'tags', 'comments', 'users', 'media', 'plugins', 'themes', 'settings'
        ));
    }
    
    // Initialize transport setting
    if (get_option('wpmcp_transport') === false) {
        update_option('wpmcp_transport', 'http');
    }
    
    // Initialize other plugin options
    if (get_option('wpmcp_enable_notifications') === false) {
        update_option('wpmcp_enable_notifications', 1);
    }
    
    if (get_option('wpmcp_require_consent') === false) {
        update_option('wpmcp_require_consent', '');
    }
    
    if (get_option('wpmcp_resource_subscriptions') === false) {
        update_option('wpmcp_resource_subscriptions', array());
    }
    
    if (get_option('wpmcp_resource_notifications') === false) {
        update_option('wpmcp_resource_notifications', array());
    }
    
    if (get_option('wpmcp_prompt_templates') === false) {
        update_option('wpmcp_prompt_templates', array());
    }
    
    if (get_option('wpmcp_consent_logs') === false) {
        update_option('wpmcp_consent_logs', array());
    }
    
    // Create includes directory if it doesn't exist
    if (!file_exists(WPMCP_PLUGIN_DIR . 'includes')) {
        mkdir(WPMCP_PLUGIN_DIR . 'includes', 0755);
    }
    
    // Flush rewrite rules to ensure our REST endpoints are registered
    flush_rewrite_rules();
}

class WPMCP_Plugin {
    private $api_key = '';
    private $implementation = array(
        'name' => 'wpmcp',
        'version' => '1.1.0'
    );

    /**
     * Generate a valid WordPress REST API nonce for the current user
     */
    private function generate_rest_nonce() {
        return wp_create_nonce('wp_rest');
    }
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('rest_api_init', array($this, 'register_rest_routes'));
        
        // Load API key from options
        $this->api_key = get_option('wpmcp_api_key', '');
        
        // Create includes directory if it doesn't exist
        if (!file_exists(WPMCP_PLUGIN_DIR . 'includes')) {
            mkdir(WPMCP_PLUGIN_DIR . 'includes', 0755);
        }
        
        // Add REST API authentication bypass for internal requests
        add_filter('rest_authentication_errors', array($this, 'handle_rest_authentication'), 999);
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
        
        // Add transport settings
        register_setting('wpmcp_settings', 'wpmcp_transport', array(
            'type' => 'string',
            'default' => 'http'
        ));
        
        // Register other settings
        register_setting('wpmcp_settings', 'wpmcp_enable_notifications', array(
            'type' => 'integer',
            'default' => 1
        ));
        
        register_setting('wpmcp_settings', 'wpmcp_require_consent', array(
            'type' => 'string',
            'default' => ''
        ));
        
        register_setting('wpmcp_settings', 'wpmcp_resource_subscriptions', array(
            'type' => 'array',
            'default' => array()
        ));
        
        register_setting('wpmcp_settings', 'wpmcp_resource_notifications', array(
            'type' => 'array',
            'default' => array()
        ));
        
        register_setting('wpmcp_settings', 'wpmcp_prompt_templates', array(
            'type' => 'array',
            'default' => array()
        ));
        
        register_setting('wpmcp_settings', 'wpmcp_consent_logs', array(
            'type' => 'array',
            'default' => array()
        ));
    }    

    public function settings_page() {
        ?>
        <div class="wrap">
            <h2>WordPress Model Context Protocol (MCP) Settings</h2>
            <p>This plugin enables AI assistants to interact with your WordPress site through the MCP protocol.</p>
            
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
                        <th scope="row">Transport</th>
                        <td>
                            <select name="wpmcp_transport">
                                <option value="http" <?php selected(get_option('wpmcp_transport', 'http'), 'http'); ?>>HTTP</option>
                                <option value="sse" <?php selected(get_option('wpmcp_transport', 'http'), 'sse'); ?>>Server-Sent Events (SSE)</option>
                            </select>
                            <p class="description">Transport protocol for MCP communication</p>
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
            <p>MCP Endpoint URL: <code><?php echo esc_url(rest_url('wpmcp/v1/mcp')); ?></code></p>
            <p>This plugin implements the Model Context Protocol (MCP) standard, allowing AI assistants to:</p>
            <ul style="list-style-type: disc; margin-left: 20px;">
                <li>Discover available WordPress resources</li>
                <li>Execute REST API requests with proper authentication</li>
                <li>Manage content, users, and site settings through natural language</li>
            </ul>
            
            <h3>MCP Protocol Information</h3>
            <p>This plugin implements the MCP protocol version 2025-04-30 with the following features:</p>
            <ul style="list-style-type: disc; margin-left: 20px;">
                <li>JSON-RPC 2.0 message format</li>
                <li>HTTP and SSE transport layers</li>
                <li>Tool-based interaction model</li>
                <li>Resource discovery and manipulation</li>
            </ul>
        </div>
        <?php
    }

    public function register_rest_routes() {
        register_rest_route('wpmcp/v1', '/mcp', array(
            'methods' => 'POST',
            'callback' => array($this, 'handle_mcp_request'),
            'permission_callback' => array($this, 'verify_api_key')
        ));
        
        // Register SSE endpoint if enabled
        if (get_option('wpmcp_transport', 'http') === 'sse') {
            register_rest_route('wpmcp/v1', '/mcp/sse', array(
                'methods' => 'GET',
                'callback' => array($this, 'handle_sse_connection'),
                'permission_callback' => array($this, 'verify_api_key')
            ));
        }
    }
    
    public function verify_api_key($request) {
        // Get the request method and endpoint
        $method = $request->get_method();
        $route = $request->get_route();
        
        // Check if this is a GET request to public endpoints (Posts, Pages, Categories, Tags)
        $public_endpoints = array('posts', 'pages', 'categories', 'tags');
        $is_public_endpoint = false;
        
        foreach ($public_endpoints as $endpoint) {
            if (strpos($route, '/wp/v2/' . $endpoint) !== false && $method === 'GET') {
                $is_public_endpoint = true;
                break;
            }
        }
        
        // If this is a GET request to a public endpoint, no API key required
        if ($is_public_endpoint) {
            // Set up a read-only user for public endpoints
            $this->setup_read_only_user();
            return true;
        }
        
        // For all other requests, API key is required
        $headers = $request->get_headers();
        $api_key_valid = false;
        
        // Check for API key in headers (with underscore format that WordPress uses)
        if (isset($headers['x_api_key']) && !empty($headers['x_api_key'][0])) {
            $api_key_valid = $headers['x_api_key'][0] === $this->api_key;
        }
        
        // Also check for hyphenated format as fallback
        if (!$api_key_valid && isset($headers['x-api-key']) && !empty($headers['x-api-key'][0])) {
            $api_key_valid = $headers['x-api-key'][0] === $this->api_key;
        }
        
        // Check for API key in request body as fallback
        if (!$api_key_valid) {
            $json_str = file_get_contents('php://input');
            $data = json_decode($json_str, true);
            
            if (isset($data['api_key']) && $data['api_key'] === $this->api_key) {
                $api_key_valid = true;
            }
        }
        
        // If API key is valid, set up an admin user with proper authentication
        if ($api_key_valid) {
            $this->setup_authenticated_admin_user();
        }
        
        return $api_key_valid;
    }
    
    /**
     * Set up a read-only user for public endpoints
     */
    private function setup_read_only_user() {
        // Create a subscriber-level user context for read-only operations
        $subscriber_users = get_users(array('role' => 'subscriber', 'number' => 1));
        
        if (!empty($subscriber_users)) {
            $user = $subscriber_users[0];
        } else {
            // If no subscriber exists, use a generic user context
            $user = new WP_User(0);
            $user->add_cap('read');
        }
        
        wp_set_current_user($user->ID);
    }
    
    /**
     * Set up an authenticated admin user with proper cookies/nonces
     */
    private function setup_authenticated_admin_user() {
        // Get an admin user
        $admin_users = get_users(array('role' => 'administrator', 'number' => 1));
        
        if (!empty($admin_users)) {
            $admin_user = $admin_users[0];
            
            // Set the current user to the admin
            wp_set_current_user($admin_user->ID);
            
            // Generate a REST nonce for this user
            $rest_nonce = $this->generate_rest_nonce();
            
            // Store the nonce for later use
            if (!defined('WPMCP_REST_NONCE')) {
                define('WPMCP_REST_NONCE', $rest_nonce);
            }
            
            // Mark this as an internal request
            if (!defined('WPMCP_INTERNAL_REQUEST')) {
                define('WPMCP_INTERNAL_REQUEST', true);
            }
        }
    }    

    /**
     * Handle REST API authentication for internal requests
     */
    public function handle_rest_authentication($errors) {
        // Skip authentication for our internal WPMCP requests
        if (defined('WPMCP_INTERNAL_REQUEST') && WPMCP_INTERNAL_REQUEST) {
            return true; // Authentication successful
        }
        
        return $errors; // Use default authentication for other requests
    }

    public function handle_mcp_request($request) {
        // Get the raw POST data
        $json_str = file_get_contents('php://input');
        $data = json_decode($json_str, true);

        // Basic validation for JSON-RPC 2.0
        if (!$data || !isset($data['jsonrpc']) || $data['jsonrpc'] !== '2.0') {
            return $this->create_jsonrpc_error(-32600, 'Invalid Request', null);
        }

        // Handle notification (no id)
        if (!isset($data['id'])) {
            // Process notification (no response needed)
            $this->process_notification($data);
            return new WP_REST_Response(null, 204);
        }

        $id = $data['id'];
        
        // Validate method
        if (!isset($data['method'])) {
            return $this->create_jsonrpc_error(-32600, 'Invalid Request: method is required', $id);
        }
        
        $method = $data['method'];
        $params = isset($data['params']) ? $data['params'] : null;

        // Handle different MCP methods
        switch ($method) {
            case 'initialize':
                return $this->handle_initialize($id, $params);
            case 'toolCall':
                return $this->handle_tool_call($id, $params);
            case 'describeTools':
                return $this->handle_describe_tools($id);
            case 'discoverResources':
                return $this->handle_discover_resources($id);
            case 'getResource':
                return $this->handle_get_resource($id, $params);
            default:
                return $this->create_jsonrpc_error(-32601, 'Method not found', $id);
        }
    }

    private function process_notification($data) {
        // Handle notifications (no response required)
        if (!isset($data['method'])) {
            return;
        }
        
        $method = $data['method'];
        $params = isset($data['params']) ? $data['params'] : null;
        
        // Process based on method
        switch ($method) {
            case 'ping':
                // Log ping if needed
                error_log('MCP ping received');
                break;
            case 'cancel':
                // Handle cancellation
                error_log('MCP cancel request received');
                break;
            default:
                // Unknown notification
                error_log('Unknown MCP notification: ' . $method);
                break;
        }
    }

    private function handle_initialize($id, $params) {
        // Check if client capabilities are compatible
        if (!isset($params['clientCapabilities'])) {
            return $this->create_jsonrpc_error(-32602, 'Invalid params: clientCapabilities required', $id);
        }

        // Return server capabilities
        return $this->create_jsonrpc_response($id, array(
            'serverInfo' => array(
                'name' => 'WordPress MCP Server',
                'version' => WPMCP_VERSION,
                'implementation' => $this->implementation
            ),
            'serverCapabilities' => array(
                'protocolVersion' => '2025-04-30',
                'transports' => array(
                    'http' => true,
                    'sse' => get_option('wpmcp_transport', 'http') === 'sse'
                ),
                'tools' => array(
                    'wp_discover_endpoints' => true,
                    'wp_call_endpoint' => true,
                    'wp_get_resource' => true
                ),
                'resources' => array(
                    'wordpress' => true
                )
            )
        ));
    }

    private function handle_tool_call($id, $params) {
        if (!isset($params['name']) || !isset($params['arguments'])) {
            return $this->create_jsonrpc_error(-32602, 'Invalid params: tool call requires name and arguments', $id);
        }
        
        $tool_name = $params['name'];
        $arguments = $params['arguments'];
        
        // For wp_call_endpoint, check if we need to enforce additional authentication
        if ($tool_name === 'wp_call_endpoint') {
            $endpoint = isset($arguments['endpoint']) ? $arguments['endpoint'] : '';
            $method = isset($arguments['method']) ? strtoupper($arguments['method']) : 'GET';
            
            // Check if this is a restricted endpoint (Comments, Users, Media, Plugins, Themes, Settings)
            $restricted_endpoints = array('comments', 'users', 'media', 'plugins', 'themes', 'settings');
            $is_restricted = false;
            
            foreach ($restricted_endpoints as $restricted) {
                if (strpos($endpoint, '/wp/v2/' . $restricted) !== false) {
                    $is_restricted = true;
                    break;
                }
            }
            
            // If this is a write operation or restricted endpoint, ensure we're authenticated
            if ($method !== 'GET' || $is_restricted) {
                // Ensure we're using an admin user with proper authentication
                $this->setup_authenticated_admin_user();
            }
        }
        
        // Execute the tool using the tools class
        $result = WPMCP_Tools::execute_tool($tool_name, $arguments);
        
        // Check for errors
        if (isset($result['error'])) {
            $error = $result['error'];
            return $this->create_jsonrpc_error(
                $error['code'],
                $error['message'],
                $id
            );
        }
        
        return $this->create_jsonrpc_response($id, $result);
    }    

    private function handle_describe_tools($id) {
        return $this->create_jsonrpc_response($id, array(
            'tools' => WPMCP_Tools::get_tools_description()
        ));
    }

    private function handle_discover_resources($id) {
        // Return available WordPress resources
        $resources = WPMCP_Resources::discover_resources();
        
        return $this->create_jsonrpc_response($id, array(
            'resources' => $resources
        ));
    }
    
    private function handle_get_resource($id, $params) {
        if (!isset($params['uri'])) {
            return $this->create_jsonrpc_error(-32602, 'Invalid params: uri is required', $id);
        }
        
        $uri = $params['uri'];
        $resource = WPMCP_Resources::get_resource($uri);
        
        if ($resource === null) {
            return $this->create_jsonrpc_error(404, 'Resource not found', $id);
        }
        
        return $this->create_jsonrpc_response($id, array(
            'resource' => $resource
        ));
    }

    /**
     * Handle SSE connection for streaming responses
     */
    public function handle_sse_connection($request) {
        // Initialize SSE connection
        WPMCP_Transport::init_sse_connection();
        
        // Send initial connection established message
        WPMCP_Transport::send_sse_message('connection', array(
            'status' => 'connected',
            'serverInfo' => array(
                'name' => 'WordPress MCP Server',
                'version' => WPMCP_VERSION,
                'implementation' => $this->implementation
            )
        ));
        
        // Keep connection open for a while to receive messages
        $start_time = time();
        $timeout = 3600; // 1 hour timeout (increase from 5 minutes)
        
        while (time() - $start_time < $timeout) {
            // Check for new messages in the queue
            // This is a placeholder - in a real implementation, you would check a message queue
            
            // Sleep to prevent CPU hogging
            sleep(1);
            
            // Send a keep-alive ping every 30 seconds
            if ((time() - $start_time) % 30 === 0) {
                WPMCP_Transport::send_sse_message('ping', array('time' => time()));
                error_log("SSE ping sent at " . time());
            }
            
            // Check if client disconnected
            if (connection_aborted()) {
                error_log("SSE connection aborted by client");
                break;
            }
        }
        
        error_log("SSE connection closed after timeout");
        exit;
    }    

    /**
     * Create a JSON-RPC 2.0 response
     */
    private function create_jsonrpc_response($id, $result) {
        return array(
            'jsonrpc' => '2.0',
            'id' => $id,
            'result' => $result
        );
    }
    
    /**
     * Create a JSON-RPC 2.0 error response
     */
    private function create_jsonrpc_error($code, $message, $id) {
        return array(
            'jsonrpc' => '2.0',
            'id' => $id,
            'error' => array(
                'code' => $code,
                'message' => $message
            )
        );
    }
}

// Initialize the plugin
new WPMCP_Plugin();
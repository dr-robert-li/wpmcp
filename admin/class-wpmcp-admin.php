<?php
/**
 * The admin-specific functionality of the plugin.
 *
 * @since      2.0.0
 * @package    WPMCP
 * @subpackage WPMCP/admin
 */

class WPMCP_Admin {

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
     * Initialize the class and set its properties.
     *
     * @since    2.0.0
     * @param    string    $plugin_name       The name of this plugin.
     * @param    string    $version           The version of this plugin.
     */
    public function __construct($plugin_name, $version) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
        
        // Add admin menu
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // Register settings
        add_action('admin_init', array($this, 'register_settings'));
        
        // Add admin scripts and styles
        add_action('admin_enqueue_scripts', array($this, 'enqueue_styles'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
    }

    /**
     * Register the stylesheets for the admin area.
     *
     * @since    2.0.0
     */
    public function enqueue_styles($hook) {
        // Only load on plugin settings page
        if ($hook != 'settings_page_wpmcp-settings') {
            return;
        }
        
        wp_enqueue_style($this->plugin_name, plugin_dir_url(__FILE__) . 'css/wpmcp-admin.css', array(), $this->version, 'all');
    }

    /**
     * Register the JavaScript for the admin area.
     *
     * @since    2.0.0
     */
    public function enqueue_scripts($hook) {
        // Only load on plugin settings page
        if ($hook != 'settings_page_wpmcp-settings') {
            return;
        }
        
        wp_enqueue_script($this->plugin_name, plugin_dir_url(__FILE__) . 'js/wpmcp-admin.js', array('jquery'), $this->version, false);
        
        // Pass data to JavaScript
        wp_localize_script($this->plugin_name, 'wpmcp_admin_data', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wpmcp_admin_nonce'),
            'rest_url' => rest_url('wpmcp/v1/data'),
            'plugin_version' => $this->version
        ));
    }

    /**
     * Add admin menu items.
     *
     * @since    2.0.0
     */
    public function add_admin_menu() {
        add_options_page(
            'WordPress MCP Settings',
            'WPMCP',
            'manage_options',
            'wpmcp-settings',
            array($this, 'display_settings_page')
        );
    }

    /**
     * Register plugin settings.
     *
     * @since    2.0.0
     */
    public function register_settings() {
        // API Key
        register_setting('wpmcp_settings', 'wpmcp_api_key', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => ''
        ));
        
        // Allowed endpoints
        register_setting('wpmcp_settings', 'wpmcp_allowed_endpoints', array(
            'type' => 'array',
            'sanitize_callback' => array($this, 'sanitize_allowed_endpoints'),
            'default' => array('posts', 'pages', 'categories', 'tags', 'comments', 'users')
        ));
        
        // Enable notifications
        register_setting('wpmcp_settings', 'wpmcp_enable_notifications', array(
            'type' => 'boolean',
            'sanitize_callback' => array($this, 'sanitize_boolean'),
            'default' => true
        ));
        
        // Require consent
        register_setting('wpmcp_settings', 'wpmcp_require_consent', array(
            'type' => 'boolean',
            'sanitize_callback' => array($this, 'sanitize_boolean'),
            'default' => true
        ));
        
        // Prompt templates
        register_setting('wpmcp_settings', 'wpmcp_prompt_templates', array(
            'type' => 'array',
            'sanitize_callback' => array($this, 'sanitize_prompt_templates'),
            'default' => array()
        ));
    }
    
    /**
     * Sanitize allowed endpoints.
     *
     * @since    2.0.0
     * @param    array    $input    The input array to sanitize.
     * @return   array              The sanitized array.
     */
    public function sanitize_allowed_endpoints($input) {
        if (!is_array($input)) {
            return array();
        }
        
        $valid_endpoints = array(
            'posts', 'pages', 'categories', 'tags', 'comments', 
            'users', 'media', 'plugins', 'themes', 'settings'
        );
        
        return array_intersect($input, $valid_endpoints);
    }
    
    /**
     * Sanitize boolean values.
     *
     * @since    2.0.0
     * @param    mixed    $input    The input value to sanitize.
     * @return   boolean            The sanitized boolean value.
     */
    public function sanitize_boolean($input) {
        return (bool) $input;
    }
    
    /**
     * Sanitize prompt templates.
     *
     * @since    2.0.0
     * @param    string    $input    JSON string of prompt templates.
     * @return   array               Sanitized prompt templates array.
     */
    public function sanitize_prompt_templates($input) {
        if (is_string($input)) {
            $templates = json_decode($input, true);
        } else {
            $templates = $input;
        }
        
        if (!is_array($templates)) {
            return array();
        }
        
        $sanitized = array();
        
        foreach ($templates as $template) {
            if (!isset($template['name']) || !isset($template['content'])) {
                continue;
            }
            
            $sanitized_template = array(
                'name' => sanitize_text_field($template['name']),
                'description' => isset($template['description']) ? sanitize_text_field($template['description']) : '',
                'content' => wp_kses_post($template['content']),
                'arguments' => array()
            );
            
            if (isset($template['arguments']) && is_array($template['arguments'])) {
                foreach ($template['arguments'] as $arg) {
                    if (!isset($arg['name'])) {
                        continue;
                    }
                    
                    $sanitized_template['arguments'][] = array(
                        'name' => sanitize_text_field($arg['name']),
                        'description' => isset($arg['description']) ? sanitize_text_field($arg['description']) : '',
                        'required' => isset($arg['required']) ? (bool) $arg['required'] : false
                    );
                }
            }
            
            $sanitized[] = $sanitized_template;
        }
        
        return $sanitized;
    }

    /**
     * Display the settings page.
     *
     * @since    2.0.0
     */
    public function display_settings_page() {
        // Check if API key is set, if not generate a random one
        if (empty(get_option('wpmcp_api_key'))) {
            $api_key = wp_generate_password(32, false);
            update_option('wpmcp_api_key', $api_key);
        }
        
        // Include the admin display template
        require_once plugin_dir_path(dirname(__FILE__)) . 'admin/partials/wpmcp-admin-display.php';
    }
    
    /**
     * Register AJAX handlers for admin functionality.
     *
     * @since    2.0.0
     */
    public function register_ajax_handlers() {
        // Example: Add AJAX handler for testing API connection
        add_action('wp_ajax_wpmcp_test_connection', array($this, 'ajax_test_connection'));
        
        // Example: Add AJAX handler for fetching consent logs
        add_action('wp_ajax_wpmcp_get_consent_logs', array($this, 'ajax_get_consent_logs'));
        
        // Example: Add AJAX handler for clearing consent logs
        add_action('wp_ajax_wpmcp_clear_consent_logs', array($this, 'ajax_clear_consent_logs'));
        
        // Example: Add AJAX handler for unsubscribing from resources
        add_action('wp_ajax_wpmcp_unsubscribe_resource', array($this, 'ajax_unsubscribe_resource'));
    }
    
    /**
     * AJAX handler for testing API connection.
     *
     * @since    2.0.0
     */
    public function ajax_test_connection() {
        // Check nonce for security
        check_ajax_referer('wpmcp_admin_nonce', 'nonce');
        
        // Only allow administrators
        if (!current_user_can('manage_options')) {
            error_log('WPMCP: API connection test failed - Permission denied');
            wp_send_json_error('Permission denied');
        }
        
        $api_key = get_option('wpmcp_api_key', '');
        
        if (empty($api_key)) {
            error_log('WPMCP: API connection test failed - API key is not set');
            wp_send_json_error('API key is not set');
        }
        
        // Log the test attempt
        error_log('WPMCP: Testing API connection with key: ' . substr($api_key, 0, 5) . '...');
        
        // Make a test request to the API using wp_discover_endpoints tool
        $response = wp_remote_post(
            rest_url('wpmcp/v1/data'),
            array(
                'headers' => array(
                    'Content-Type' => 'application/json',
                    'X-API-Key' => $api_key
                ),
                'body' => json_encode(array(
                    'type' => 'invoke',
                    'name' => 'wp_discover_endpoints',
                    'arguments' => array()
                ))
            )
        );
        
        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            error_log('WPMCP: API connection test failed - ' . $error_message);
            wp_send_json_error($error_message);
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        // Log the response details
        error_log('WPMCP: API response status: ' . $status_code);
        error_log('WPMCP: API response body: ' . substr($body, 0, 100) . (strlen($body) > 100 ? '...' : ''));
        
        if ($status_code !== 200) {
            error_log('WPMCP: API connection test failed - API returned error: ' . $status_code);
            wp_send_json_error('API returned error: ' . $status_code);
        }
        
        // Parse the response to check if it's a valid wp_discover_endpoints response
        $response_data = json_decode($body, true);
        if (!isset($response_data['type']) || $response_data['type'] !== 'success' || !isset($response_data['data'])) {
            error_log('WPMCP: API connection test failed - Invalid response format');
            wp_send_json_error('Invalid response format');
        }
        
        // Count the number of endpoints discovered
        $endpoints_count = count($response_data['data']);
        
        // Log success
        error_log('WPMCP: API connection test successful! Discovered ' . $endpoints_count . ' endpoints');
        wp_send_json_success('Connection successful! Discovered ' . $endpoints_count . ' endpoints');
    }
    
    /**
     * AJAX handler for getting consent logs.
     *
     * @since    2.0.0
     */
    public function ajax_get_consent_logs() {
        // Check nonce for security
        check_ajax_referer('wpmcp_admin_nonce', 'nonce');
        
        // Only allow administrators
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }
        
        // In a real implementation, this would fetch logs from the database
        // For now, we'll just return sample data
        $logs = array(
            array(
                'time' => current_time('mysql'),
                'user' => 'admin',
                'tool' => 'wp_call_endpoint',
                'arguments' => json_encode(array(
                    'endpoint' => '/wp/v2/posts',
                    'method' => 'GET'
                )),
                'status' => 'approved'
            )
        );
        
        wp_send_json_success($logs);
    }
    
    /**
     * AJAX handler for clearing consent logs.
     *
     * @since    2.0.0
     */
    public function ajax_clear_consent_logs() {
        // Check nonce for security
        check_ajax_referer('wpmcp_admin_nonce', 'nonce');
        
        // Only allow administrators
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }
        
        // In a real implementation, this would clear logs from the database
        // For now, we'll just return success
        wp_send_json_success('Logs cleared successfully');
    }
    
    /**
     * AJAX handler for unsubscribing from resources.
     *
     * @since    2.0.0
     */
    public function ajax_unsubscribe_resource() {
        // Check nonce for security
        check_ajax_referer('wpmcp_admin_nonce', 'nonce');
        
        // Only allow administrators
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }
        
        $uri = isset($_POST['uri']) ? sanitize_text_field($_POST['uri']) : '';
        
        if (empty($uri)) {
            wp_send_json_error('Resource URI is required');
        }
        
        // Get current subscriptions
        $subscriptions = get_option('wpmcp_resource_subscriptions', array());
        
        // Remove the specified URI
        $key = array_search($uri, $subscriptions);
        if ($key !== false) {
            unset($subscriptions[$key]);
            update_option('wpmcp_resource_subscriptions', array_values($subscriptions));
            wp_send_json_success('Unsubscribed successfully');
        } else {
            wp_send_json_error('Resource not found in subscriptions');
        }
    }
}

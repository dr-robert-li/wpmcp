<?php
/**
 * Handles MCP User Consent functionality.
 *
 * @since      2.0.0
 * @package    WPMCP
 * @subpackage WPMCP/includes
 */

class WPMCP_Consent_Manager {
    
    /**
     * Initialize the class.
     */
    public function __construct() {
        // Initialize any dependencies
    }
    
    /**
     * Check if consent is required for a tool.
     *
     * @param string $tool_name Tool name.
     * @return bool Whether consent is required.
     */
    public function is_consent_required($tool_name) {
        // Get global consent setting
        $require_consent = get_option('wpmcp_require_consent', true);
        
        if (!$require_consent) {
            return false;
        }
        
        // Define tools that require explicit consent
        $consent_required_tools = array(
            'wp_call_endpoint' => array(
                'methods' => array('POST', 'PUT', 'DELETE', 'PATCH')
            ),
            'resources/subscribe' => true
        );
        
        // Check if tool requires consent
        if (isset($consent_required_tools[$tool_name])) {
            if (is_array($consent_required_tools[$tool_name])) {
                // For tools that require consent only for certain methods
                $method = isset($_REQUEST['method']) ? strtoupper($_REQUEST['method']) : 'GET';
                return in_array($method, $consent_required_tools[$tool_name]['methods']);
            } else {
                // For tools that always require consent
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Record user consent for a tool invocation.
     *
     * @param string $tool_name Tool name.
     * @param array $arguments Tool arguments.
     * @param string $user_id User ID or identifier.
     * @param string $session_id Session ID.
     * @return bool Whether consent was recorded successfully.
     */
    public function record_consent($tool_name, $arguments, $user_id, $session_id) {
        $consent_logs = get_option('wpmcp_consent_logs', array());
        
        $consent_entry = array(
            'tool' => $tool_name,
            'arguments' => $arguments,
            'user_id' => $user_id,
            'session_id' => $session_id,
            'timestamp' => current_time('c'),
            'ip' => $_SERVER['REMOTE_ADDR']
        );
        
        // Add to consent logs
        $consent_logs[] = $consent_entry;
        
        // Limit the number of stored logs (keep last 1000)
        if (count($consent_logs) > 1000) {
            $consent_logs = array_slice($consent_logs, -1000);
        }
        
        update_option('wpmcp_consent_logs', $consent_logs);
        
        return true;
    }
    
    /**
     * Verify if consent has been given for a tool invocation.
     *
     * @param string $tool_name Tool name.
     * @param array $arguments Tool arguments.
     * @param string $consent_token Consent token.
     * @return bool Whether consent has been verified.
     */
    public function verify_consent($tool_name, $arguments, $consent_token) {
        if (empty($consent_token)) {
            return false;
        }
        
        // Decode consent token (base64 encoded JSON)
        $token_data = json_decode(base64_decode($consent_token), true);
        
        if (!is_array($token_data) || 
            !isset($token_data['tool']) || 
            !isset($token_data['timestamp']) || 
            !isset($token_data['signature'])) {
            return false;
        }
        
        // Verify token matches the tool
        if ($token_data['tool'] !== $tool_name) {
            return false;
        }
        
        // Verify token is not expired (valid for 5 minutes)
        $timestamp = strtotime($token_data['timestamp']);
        if (time() - $timestamp > 300) {
            return false;
        }
        
        // Verify signature
        $api_key = get_option('wpmcp_api_key', '');
        $expected_signature = hash_hmac('sha256', $token_data['tool'] . $token_data['timestamp'], $api_key);
        
        if ($token_data['signature'] !== $expected_signature) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Generate a consent token for a tool invocation.
     *
     * @param string $tool_name Tool name.
     * @return string Consent token.
     */
    public function generate_consent_token($tool_name) {
        $api_key = get_option('wpmcp_api_key', '');
        $timestamp = current_time('c');
        $signature = hash_hmac('sha256', $tool_name . $timestamp, $api_key);
        
        $token_data = array(
            'tool' => $tool_name,
            'timestamp' => $timestamp,
            'signature' => $signature
        );
        
        return base64_encode(json_encode($token_data));
    }
    
    /**
     * Get consent request details for admin UI.
     *
     * @param string $tool_name Tool name.
     * @param array $arguments Tool arguments.
     * @return array Consent request details.
     */
    public function get_consent_request_details($tool_name, $arguments) {
        $details = array(
            'tool' => $tool_name,
            'description' => $this->get_tool_description($tool_name),
            'arguments' => $arguments,
            'timestamp' => current_time('c'),
            'token' => $this->generate_consent_token($tool_name)
        );
        
        return $details;
    }
    
    /**
     * Get human-readable description for a tool.
     *
     * @param string $tool_name Tool name.
     * @return string Tool description.
     */
    private function get_tool_description($tool_name) {
        $tool_descriptions = array(
            'wp_call_endpoint' => 'Execute a WordPress REST API request',
            'wp_discover_endpoints' => 'Discover available WordPress REST API endpoints',
            'resources/list' => 'List available WordPress resources',
            'resources/read' => 'Read a WordPress resource',
            'resources/templates/list' => 'List resource templates',
            'resources/subscribe' => 'Subscribe to resource changes',
            'resources/notifications/list' => 'List resource change notifications',
            'resources/notifications/clear' => 'Clear resource change notifications',
            'prompts/list' => 'List available prompts',
            'prompts/get' => 'Get a prompt template',
            'completion/complete' => 'Complete argument values'
        );
        
        return isset($tool_descriptions[$tool_name]) ? 
            $tool_descriptions[$tool_name] : 'Execute a WordPress MCP operation';
    }
}

<?php
/**
 * Handles MCP Error functionality.
 *
 * @since      2.0.0
 * @package    WPMCP
 * @subpackage WPMCP/includes
 */

class WPMCP_Error_Handler {
    
    // JSON-RPC error codes
    const PARSE_ERROR = -32700;
    const INVALID_REQUEST = -32600;
    const METHOD_NOT_FOUND = -32601;
    const INVALID_PARAMS = -32602;
    const INTERNAL_ERROR = -32603;
    
    // Custom error codes
    const AUTHENTICATION_ERROR = -32000;
    const AUTHORIZATION_ERROR = -32001;
    const RESOURCE_NOT_FOUND = -32002;
    const RATE_LIMIT_EXCEEDED = -32003;
    const VALIDATION_ERROR = -32004;
    
    /**
     * Initialize the class.
     */
    public function __construct() {
        // Initialize any dependencies
    }
    
    /**
     * Convert WordPress error to MCP error.
     *
     * @param WP_Error $wp_error WordPress error object.
     * @return array MCP error response.
     */
    public function convert_wp_error($wp_error) {
        $code = $this->get_error_code($wp_error);
        $message = $wp_error->get_error_message();
        $data = $wp_error->get_error_data();
        
        return array(
            'type' => 'error',
            'error' => array(
                'code' => $code,
                'message' => $message,
                'data' => $data
            )
        );
    }
    
    /**
     * Create a new MCP error.
     *
     * @param int $code Error code.
     * @param string $message Error message.
     * @param mixed $data Additional error data.
     * @return array MCP error response.
     */
    public function create_error($code, $message, $data = null) {
        return array(
            'type' => 'error',
            'error' => array(
                'code' => $code,
                'message' => $message,
                'data' => $data
            )
        );
    }
    
    /**
     * Get appropriate error code for WordPress error.
     *
     * @param WP_Error $wp_error WordPress error object.
     * @return int Error code.
     */
    private function get_error_code($wp_error) {
        $data = $wp_error->get_error_data();
        
        // If error data contains a code, use it
        if (is_array($data) && isset($data['code'])) {
            return $data['code'];
        }
        
        // Map WordPress error codes to JSON-RPC error codes
        $code = $wp_error->get_error_code();
        
        switch ($code) {
            case 'invalid_request':
                return self::INVALID_REQUEST;
                
            case 'invalid_type':
            case 'unknown_tool':
            case 'prompt_not_found':
                return self::METHOD_NOT_FOUND;
                
            case 'missing_endpoint':
            case 'missing_uri':
            case 'missing_argument':
            case 'invalid_invoke':
                return self::INVALID_PARAMS;
                
            case 'resource_not_found':
                return self::RESOURCE_NOT_FOUND;
                
            case 'forbidden_endpoint':
            case 'forbidden_resource':
                return self::AUTHORIZATION_ERROR;
                
            default:
                return self::INTERNAL_ERROR;
        }
    }
}

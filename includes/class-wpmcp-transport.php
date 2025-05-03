<?php
/**
 * WPMCP Transport Handler
 * 
 * Handles different transport methods for MCP communication
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPMCP_Transport {
    /**
     * Send a JSON-RPC response via HTTP
     */
    public static function send_http_response($response) {
        return $response;
    }
    
    /**
     * Send a JSON-RPC response via SSE
     */
    public static function send_sse_message($event_type, $data) {
        // Ensure output buffering is off
        if (ob_get_level() > 0) {
            ob_end_flush();
        }
        
        echo "event: {$event_type}\n";
        echo "data: " . json_encode($data) . "\n\n";
        
        // Force flush
        if (ob_get_level() > 0) {
            ob_flush();
        }
        flush();
        
        // Log for debugging
        error_log("SSE message sent: {$event_type}");
    }
    
    /**
     * Initialize SSE connection
     */
    public static function init_sse_connection() {
        // Set headers for SSE
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no'); // Disable nginx buffering
        
        // Ensure output buffering is off
        if (ob_get_level() > 0) {
            ob_end_flush();
        }
        
        // Turn off output buffering
        ob_implicit_flush(true);
        
        // Set time limit to 0 (no timeout)
        set_time_limit(0);
        
        // Log for debugging
        error_log("SSE connection initialized");
    }
}

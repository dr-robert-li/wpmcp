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
        echo "event: {$event_type}\n";
        echo "data: " . json_encode($data) . "\n\n";
        flush();
    }
    
    /**
     * Initialize SSE connection
     */
    public static function init_sse_connection() {
        // Set headers for SSE
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no'); // Disable nginx buffering
    }
}

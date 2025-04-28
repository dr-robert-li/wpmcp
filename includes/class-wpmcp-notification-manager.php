<?php
/**
 * Handles MCP Resource Notifications.
 *
 * @since      1.0.0
 * @package    WPMCP
 * @subpackage WPMCP/includes
 */

class WPMCP_Notification_Manager {
    
    /**
     * Initialize the class.
     */
    public function __construct() {
        // Initialize any dependencies
    }
    
    /**
     * Store a notification for a resource change.
     *
     * @param string $uri Resource URI that changed.
     * @param string $action Action that occurred (updated, deleted).
     * @param array $data Additional data about the change.
     */
    public function store_notification($uri, $action, $data = array()) {
        $notifications = get_option('wpmcp_resource_notifications', array());
        
        // Add the new notification
        $notifications[] = array(
            'uri' => $uri,
            'action' => $action,
            'timestamp' => current_time('c'),
            'data' => $data
        );
        
        // Limit the number of stored notifications (keep last 100)
        if (count($notifications) > 100) {
            $notifications = array_slice($notifications, -100);
        }
        
        update_option('wpmcp_resource_notifications', $notifications);
    }
    
    /**
     * Get pending notifications for resources.
     *
     * @param string $cursor Cursor for pagination.
     * @return array Notifications and next cursor.
     */
    public function get_notifications($cursor = null) {
        $notifications = get_option('wpmcp_resource_notifications', array());
        
        // If no notifications, return empty array
        if (empty($notifications)) {
            return array(
                'notifications' => array(),
                'nextCursor' => null
            );
        }
        
        // Handle pagination with cursor
        $start_index = 0;
        if ($cursor) {
            // Decode cursor (base64 encoded JSON)
            $cursor_data = json_decode(base64_decode($cursor), true);
            if (is_array($cursor_data) && isset($cursor_data['index'])) {
                $start_index = intval($cursor_data['index']);
            }
        }
        
        // Get a batch of notifications (20 at a time)
        $batch_size = 20;
        $end_index = min($start_index + $batch_size, count($notifications));
        $batch = array_slice($notifications, $start_index, $batch_size);
        
        // Create next cursor if there are more notifications
        $next_cursor = null;
        if ($end_index < count($notifications)) {
            $next_cursor = base64_encode(json_encode(array('index' => $end_index)));
        }
        
        return array(
            'notifications' => $batch,
            'nextCursor' => $next_cursor
        );
    }
    
    /**
     * Clear notifications after they've been delivered.
     *
     * @param array $notification_ids IDs of notifications to clear.
     * @return bool Success status.
     */
    public function clear_notifications($notification_ids) {
        if (empty($notification_ids)) {
            return true;
        }
        
        $notifications = get_option('wpmcp_resource_notifications', array());
        
        // Remove the specified notifications
        foreach ($notification_ids as $id) {
            if (isset($notifications[$id])) {
                unset($notifications[$id]);
            }
        }
        
        // Reindex the array
        $notifications = array_values($notifications);
        
        update_option('wpmcp_resource_notifications', $notifications);
        return true;
    }
}

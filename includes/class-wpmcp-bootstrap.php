<?php
/**
 * WPMCP Bootstrap
 * 
 * Initializes all components of the WPMCP plugin
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPMCP_Bootstrap {
    /**
     * Initialize the plugin
     */
    public static function init() {
        // Check if required files exist
        self::check_files();
        
        // Load dependencies
        self::load_dependencies();
        
        // Initialize plugin
        new WPMCP_Plugin();
    }
    
    /**
     * Check if required files exist and create them if not
     */
    private static function check_files() {
        $includes_dir = WPMCP_PLUGIN_DIR . 'includes';
        
        // Create includes directory if it doesn't exist
        if (!file_exists($includes_dir)) {
            mkdir($includes_dir, 0755);
        }
    }
    
    /**
     * Load all dependencies
     */
    private static function load_dependencies() {
        require_once WPMCP_PLUGIN_DIR . 'includes/class-wpmcp-transport.php';
        require_once WPMCP_PLUGIN_DIR . 'includes/class-wpmcp-tools.php';
        require_once WPMCP_PLUGIN_DIR . 'includes/class-wpmcp-resources.php';
    }
}

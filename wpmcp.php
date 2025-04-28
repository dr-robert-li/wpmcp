<?php
/**
 * Plugin Name: WPMCP
 * Plugin URI: https://github.com/dr-robert-li/wpmcp
 * Description: WordPress Model Context Protocol (MCP) - Enables AI assistants to interact with WordPress through REST API
 * Version: 2.0.0
 * Author: Dr. Robert Li
 * License: GPL v3
 * Text Domain: wpmcp
 * Domain Path: /languages
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Define plugin constants
 */
define('WPMCP_VERSION', '2.0.0');
define('WPMCP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WPMCP_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WPMCP_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Include required files
 */
require_once WPMCP_PLUGIN_DIR . 'includes/class-wpmcp-loader.php';
require_once WPMCP_PLUGIN_DIR . 'includes/class-wpmcp-resources-handler.php';
require_once WPMCP_PLUGIN_DIR . 'includes/class-wpmcp-notification-manager.php';
require_once WPMCP_PLUGIN_DIR . 'includes/class-wpmcp-prompts-handler.php';
require_once WPMCP_PLUGIN_DIR . 'includes/class-wpmcp-completion-handler.php';
require_once WPMCP_PLUGIN_DIR . 'includes/class-wpmcp-error-handler.php';
require_once WPMCP_PLUGIN_DIR . 'includes/class-wpmcp-consent-manager.php';
require_once WPMCP_PLUGIN_DIR . 'admin/class-wpmcp-admin.php';
require_once WPMCP_PLUGIN_DIR . 'public/class-wpmcp-public.php';

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require_once WPMCP_PLUGIN_DIR . 'includes/class-wpmcp.php';

/**
 * Begins execution of the plugin.
 */
function run_wpmcp() {
    $plugin = new WPMCP();
    $plugin->run();
}

/**
 * Register activation and deactivation hooks
 */
register_activation_hook(__FILE__, 'wpmcp_activate');
register_deactivation_hook(__FILE__, 'wpmcp_deactivate');

/**
 * Plugin activation function
 */
function wpmcp_activate() {
    // Create necessary database tables and options
    if (!get_option('wpmcp_api_key')) {
        add_option('wpmcp_api_key', wp_generate_password(32, false));
    }
    
    // Default allowed endpoints
    if (!get_option('wpmcp_allowed_endpoints')) {
        add_option('wpmcp_allowed_endpoints', array('posts', 'pages', 'categories', 'tags', 'comments', 'users'));
    }
    
    // Feature settings
    add_option('wpmcp_enable_notifications', true);
    add_option('wpmcp_require_consent', true);
    
    // Resource tracking
    add_option('wpmcp_resource_subscriptions', array());
    add_option('wpmcp_resource_notifications', array());
    
    // Prompt templates
    add_option('wpmcp_prompt_templates', array(
        'seo_optimize' => array(
            'name' => 'seo_optimize',
            'description' => 'Optimize content for SEO',
            'arguments' => array(
                array(
                    'name' => 'content',
                    'description' => 'Content to optimize',
                    'required' => true
                ),
                array(
                    'name' => 'keywords',
                    'description' => 'Target keywords',
                    'required' => true
                )
            )
        ),
        'content_summary' => array(
            'name' => 'content_summary',
            'description' => 'Generate a summary of content',
            'arguments' => array(
                array(
                    'name' => 'content',
                    'description' => 'Content to summarize',
                    'required' => true
                ),
                array(
                    'name' => 'length',
                    'description' => 'Summary length (short, medium, long)',
                    'required' => false
                )
            )
        )
    ));
    
    // Consent logs
    add_option('wpmcp_consent_logs', array());
    
    // Flush rewrite rules to ensure our REST endpoints work
    flush_rewrite_rules();
}

/**
 * Plugin deactivation function
 */
function wpmcp_deactivate() {
    // Clean up if needed
    flush_rewrite_rules();
}

run_wpmcp();
<?php
/**     
 * Fired when the plugin is uninstalled.
 *
 * @link       https://github.com/dr-robert-li/wpmcp
 * @since      1.0.0
 * @package    WPMCP
 */

// If uninstall not called from WordPress, then exit.
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Delete plugin options
delete_option('wpmcp_api_key');
delete_option('wpmcp_allowed_endpoints');

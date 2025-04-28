<?php
/**
 * Provide a admin area view for the plugin
 *
 * @since      2.0.0
 * @package    WPMCP
 * @subpackage WPMCP/admin/partials
 */
?>

<div class="wrap">
    <h1>WordPress Model Context Protocol (MCP) Settings</h1>
    <p>This plugin enables AI assistants to interact with your WordPress site through the REST API using the Model Context Protocol.</p>
    
    <nav class="nav-tab-wrapper">
        <a href="#general-settings" class="nav-tab nav-tab-active">General</a>
        <a href="#resource-settings" class="nav-tab">Resources</a>
        <a href="#prompt-settings" class="nav-tab">Prompts</a>
        <a href="#security-settings" class="nav-tab">Security</a>
        <a href="#usage-info" class="nav-tab">Usage</a>
        <a href="#logs" class="nav-tab">Logs</a>
    </nav>
    
    <form method="post" action="options.php">
        <?php
        settings_fields('wpmcp_settings');
        do_settings_sections('wpmcp_settings');
        ?>
        
        <div id="general-settings" class="tab-content active">
            <h2>General Settings</h2>
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
                        <p>
                            <button type="button" id="test-api-connection" class="button button-secondary">Test API Connection</button>
                            <span id="api-connection-result"></span>
                        </p>
                    </td>
                </tr>
            </table>
        </div>
        
        <div id="resource-settings" class="tab-content">
            <h2>Resource Settings</h2>
            <p>Configure which WordPress resources can be accessed via the MCP API and how resource changes are handled.</p>
            
            <table class="form-table">
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
                            <label style="display: inline-block; margin-right: 15px; min-width: 120px;">
                                <input type="checkbox" name="wpmcp_allowed_endpoints[]" value="<?php echo esc_attr($endpoint); ?>" 
                                    <?php checked(in_array($endpoint, $allowed_endpoints)); ?>>
                                <?php echo esc_html($label); ?>
                            </label>
                        <?php endforeach; ?>
                        <p class="description">Select which WordPress resources can be accessed via the MCP API</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Resource Notifications</th>
                    <td>
                        <label>
                            <input type="checkbox" name="wpmcp_enable_notifications" value="1" 
                                <?php checked(get_option('wpmcp_enable_notifications', true)); ?>>
                            Enable resource change notifications
                        </label>
                        <p class="description">When enabled, the plugin will track changes to resources and notify subscribers</p>
                    </td>
                </tr>
            </table>
        </div>
        
        <div id="prompt-settings" class="tab-content">
            <h2>Prompt Templates</h2>
            <p>Configure prompt templates that can be used by AI assistants.</p>
            
            <?php 
            $prompt_templates = get_option('wpmcp_prompt_templates', array());
            ?>
            
            <div id="prompt-templates-container">
                <?php if (empty($prompt_templates)): ?>
                    <p>No prompt templates defined. Add your first template below.</p>
                <?php else: ?>
                    <table class="widefat" style="margin-bottom: 15px;">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Description</th>
                                <th>Arguments</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($prompt_templates as $template): ?>
                                <tr>
                                    <td><?php echo esc_html($template['name']); ?></td>
                                    <td><?php echo esc_html($template['description']); ?></td>
                                    <td>
                                        <?php 
                                        if (!empty($template['arguments'])) {
                                            $args = array();
                                            foreach ($template['arguments'] as $arg) {
                                                $args[] = $arg['name'] . (isset($arg['required']) && $arg['required'] ? ' (required)' : '');
                                            }
                                            echo esc_html(implode(', ', $args));
                                        } else {
                                            echo 'None';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <button type="button" class="button button-small edit-prompt" 
                                                data-name="<?php echo esc_attr($template['name']); ?>">Edit</button>
                                        <button type="button" class="button button-small delete-prompt" 
                                                data-name="<?php echo esc_attr($template['name']); ?>">Delete</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
                
                <button type="button" id="add-prompt-template" class="button button-secondary">Add Prompt Template</button>
                
                <div id="prompt-template-modal" style="display: none; background: #fff; padding: 20px; border: 1px solid #ccc; max-width: 600px; position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); z-index: 9999; box-shadow: 0 0 10px rgba(0,0,0,0.2);">
                    <h3 id="prompt-modal-title">Add Prompt Template</h3>
                    
                    <p>
                        <label>Name:</label><br>
                        <input type="text" id="prompt-name" class="regular-text">
                    </p>
                    
                    <p>
                        <label>Description:</label><br>
                        <input type="text" id="prompt-description" class="regular-text">
                    </p>
                    
                    <div id="prompt-arguments">
                        <h4>Arguments</h4>
                        <div id="prompt-arguments-list"></div>
                        <button type="button" id="add-argument" class="button button-secondary">Add Argument</button>
                    </div>
                    
                    <p>
                        <label>Template Content:</label><br>
                        <textarea id="prompt-content" rows="5" class="large-text"></textarea>
                        <span class="description">Use {{argument_name}} to include arguments in the template</span>
                    </p>
                    
                    <div style="margin-top: 20px; text-align: right;">
                        <button type="button" id="cancel-prompt" class="button button-secondary">Cancel</button>
                        <button type="button" id="save-prompt" class="button button-primary">Save Template</button>
                    </div>
                </div>
            </div>
            
            <input type="hidden" name="wpmcp_prompt_templates" id="wpmcp-prompt-templates-json" value="<?php echo esc_attr(json_encode($prompt_templates)); ?>">
        </div>
        
        <div id="security-settings" class="tab-content">
            <h2>Security Settings</h2>
            <p>Configure security settings for the MCP API.</p>
            
            <table class="form-table">
                <tr>
                    <th scope="row">User Consent</th>
                    <td>
                        <label>
                            <input type="checkbox" name="wpmcp_require_consent" value="1" 
                                <?php checked(get_option('wpmcp_require_consent', true)); ?>>
                            Require explicit user consent for tool invocations
                        </label>
                        <p class="description">When enabled, users must explicitly consent to tool invocations</p>
                    </td>
                </tr>
            </table>
        </div>
        
        <div id="usage-info" class="tab-content">
            <h2>Usage Information</h2>
            <p>Endpoint URL: <code><?php echo esc_url(rest_url('wpmcp/v1/data')); ?></code></p>
            <p>This plugin implements the Model Context Protocol (MCP) standard, allowing AI assistants to:</p>
            <ul style="list-style-type: disc; margin-left: 20px;">
                <li>Discover available WordPress REST API endpoints</li>
                <li>Execute REST API requests with proper authentication</li>
                <li>Access WordPress resources with proper permissions</li>
                <li>Use prompt templates for common tasks</li>
                <li>Manage content, users, and site settings through natural language</li>
            </ul>
            
            <h3>Integration Examples</h3>
            <p>To integrate with Claude Desktop or other MCP-compatible AI assistants, use the following configuration:</p>
            
            <div class="wpmcp-code-example">
                <h4>Claude Desktop Configuration Example:</h4>
                <pre>{
    "name": "wpmcp",
    "displayName": "WordPress MCP Integration",
    "version": "2.0.0",
    "description": "Model Context Protocol integration for WordPress websites",
    "tools": [
        {
            "name": "wp_discover_endpoints",
            "displayName": "WordPress Discover Endpoints",
            "description": "Maps all available REST API endpoints on a WordPress site",
            "schema": {
                "type": "object",
                "properties": {},
                "required": []
            },
            "server": {
                "url": "<?php echo esc_url(rest_url('wpmcp/v1/data')); ?>",
                "headers": {
                    "x-api-key": "YOUR_API_KEY_HERE"
                }
            }
        },
        {
            "name": "wp_call_endpoint",
            "displayName": "WordPress Call Endpoint",
            "description": "Executes specific REST API requests to the WordPress site",
            "schema": {
                "type": "object",
                "properties": {
                    "endpoint": {
                        "type": "string",
                        "description": "API endpoint path (e.g., /wp/v2/posts)"
                    },
                    "method": {
                        "type": "string",
                        "enum": ["GET", "POST", "PUT", "DELETE", "PATCH"],
                        "description": "HTTP method",
                        "default": "GET"
                    },
                    "params": {
                        "type": "object",
                        "description": "Request parameters or body data"
                    }
                },
                "required": ["endpoint"]
            },
            "server": {
                "url": "<?php echo esc_url(rest_url('wpmcp/v1/data')); ?>",
                "headers": {
                    "x-api-key": "YOUR_API_KEY_HERE"
                }
            }
        },
        {
            "name": "resources/list",
            "displayName": "WordPress List Resources",
            "description": "Lists available WordPress resources",
            "schema": {
                "type": "object",
                "properties": {
                    "cursor": {
                        "type": "string",
                        "description": "Pagination cursor"
                    }
                },
                "required": []
            },
            "server": {
                "url": "<?php echo esc_url(rest_url('wpmcp/v1/data')); ?>",
                "headers": {
                    "x-api-key": "YOUR_API_KEY_HERE"
                }
            }
        },
        {
            "name": "resources/read",
            "displayName": "WordPress Read Resource",
            "description": "Reads a specific WordPress resource",
            "schema": {
                "type": "object",
                "properties": {
                    "uri": {
                        "type": "string",
                        "description": "Resource URI (e.g., wp://posts/1)"
                    }
                },
                "required": ["uri"]
            },
            "server": {
                "url": "<?php echo esc_url(rest_url('wpmcp/v1/data')); ?>",
                "headers": {
                    "x-api-key": "YOUR_API_KEY_HERE"
                }
            }
        }
    ]
}</pre>
            </div>
            
            <p><strong>Note:</strong> Replace <code>YOUR_API_KEY_HERE</code> with the API key generated above.</p>
        </div>
        
        <div id="logs" class="tab-content">
            <h2>Logs and Monitoring</h2>
            
            <h3>Consent Logs</h3>
            <p>View logs of user consent for tool invocations.</p>
            
            <div id="consent-logs-container">
                <button type="button" id="view-consent-logs" class="button button-secondary">View Consent Logs</button>
                <button type="button" id="clear-consent-logs" class="button button-secondary">Clear Consent Logs</button>
                
                <div id="consent-logs-table" style="margin-top: 15px; display: none;">
                    <table class="widefat">
                        <thead>
                            <tr>
                                <th>Date/Time</th>
                                <th>User</th>
                                <th>Tool</th>
                                <th>Arguments</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody id="consent-logs-body">
                            <tr>
                                <td colspan="5">Loading logs...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <h3>Resource Subscriptions</h3>
            <p>View active resource subscriptions.</p>
            
            <?php
            $subscriptions = get_option('wpmcp_resource_subscriptions', array());
            ?>
            
            <div id="resource-subscriptions-container">
                <?php if (empty($subscriptions)): ?>
                    <p>No active resource subscriptions.</p>
                <?php else: ?>
                    <table class="widefat">
                        <thead>
                            <tr>
                                <th>Resource URI</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($subscriptions as $uri): ?>
                                <tr>
                                    <td><?php echo esc_html($uri); ?></td>
                                    <td>
                                        <button type="button" class="button button-small unsubscribe-resource" 
                                                data-uri="<?php echo esc_attr($uri); ?>">Unsubscribe</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
            
            <h3>Pending Notifications</h3>
            <p>View pending resource change notifications.</p>
            
            <?php
            $notifications = get_option('wpmcp_resource_notifications', array());
            ?>
            
            <div id="resource-notifications-container">
                <?php if (empty($notifications)): ?>
                    <p>No pending resource notifications.</p>
                <?php else: ?>
                    <table class="widefat">
                        <thead>
                            <tr>
                                <th>Resource URI</th>
                                <th>Action</th>
                                <th>Timestamp</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($notifications as $index => $notification): ?>
                                <tr>
                                    <td><?php echo esc_html($notification['uri']); ?></td>
                                    <td><?php echo esc_html($notification['action']); ?></td>
                                    <td><?php echo esc_html($notification['timestamp']); ?></td>
                                    <td>
                                        <button type="button" class="button button-small clear-notification" 
                                                data-index="<?php echo esc_attr($index); ?>">Clear</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <p>
                        <button type="button" id="clear-all-notifications" class="button button-secondary">Clear All Notifications</button>
                    </p>
                <?php endif; ?>
            </div>
        </div>
        
        <p class="submit">
            <input type="submit" name="submit" id="submit" class="button button-primary" value="Save Changes">
        </p>
    </form>
</div>

<style>
    .tab-content {
        display: none;
        padding: 20px 0;
    }
    
    .tab-content.active {
        display: block;
    }
    
    .nav-tab-wrapper {
        margin-bottom: 20px;
    }
    
    .wpmcp-code-example {
        background: #f9f9f9;
        padding: 15px;
        border: 1px solid #ddd;
        border-radius: 4px;
        margin: 15px 0;
    }
    
    .wpmcp-code-example pre {
        margin: 0;
        overflow-x: auto;
        white-space: pre-wrap;
        word-wrap: break-word;
    }
</style>

<script>
    jQuery(document).ready(function($) {
        // Tab navigation
        $('.nav-tab').on('click', function(e) {
            e.preventDefault();
            
            // Hide all tab content
            $('.tab-content').removeClass('active');
            
            // Remove active class from all tabs
            $('.nav-tab').removeClass('nav-tab-active');
            
            // Add active class to clicked tab
            $(this).addClass('nav-tab-active');
            
            // Show corresponding tab content
            const tabId = $(this).attr('href');
            $(tabId).addClass('active');
        });
        
        // Test API connection
        $('#test-api-connection').on('click', function() {
            const apiKey = $('input[name="wpmcp_api_key"]').val();
            
            if (!apiKey) {
                $('#api-connection-result').html('<span style="color: red;">API key is required</span>');
                return;
            }
            
            $('#api-connection-result').html('<span style="color: blue;">Testing connection...</span>');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'wpmcp_test_api_connection',
                    nonce: wpmcp_admin_data.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $('#api-connection-result').html('<span style="color: green;">✓ ' + response.data + '</span>');
                    } else {
                        $('#api-connection-result').html('<span style="color: red;">✗ ' + response.data + '</span>');
                    }
                },
                error: function() {
                    $('#api-connection-result').html('<span style="color: red;">✗ Connection failed</span>');
                }
            });
        });
        
        // View consent logs
        $('#view-consent-logs').on('click', function() {
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'wpmcp_view_consent_logs',
                    nonce: wpmcp_admin_data.nonce
                },
                success: function(response) {
                    if (response.success) {
                        const logs = response.data;
                        let html = '';
                        
                        if (logs.length === 0) {
                            html = '<tr><td colspan="5">No consent logs found.</td></tr>';
                        } else {
                            logs.forEach(function(log) {
                                html += '<tr>';
                                html += '<td>' + log.timestamp + '</td>';
                                html += '<td>' + log.user + '</td>';
                                html += '<td>' + log.tool + '</td>';
                                html += '<td><pre>' + JSON.stringify(log.arguments, null, 2) + '</pre></td>';
                                html += '<td>' + (log.granted ? '<span style="color: green;">Granted</span>' : '<span style="color: red;">Denied</span>') + '</td>';
                                html += '</tr>';
                            });
                        }
                        
                        $('#consent-logs-body').html(html);
                        $('#consent-logs-table').show();
                    } else {
                        alert('Error: ' + response.data);
                    }
                },
                error: function() {
                    alert('Failed to load consent logs');
                }
            });
        });
        
        // Clear consent logs
        $('#clear-consent-logs').on('click', function() {
            if (confirm('Are you sure you want to clear all consent logs?')) {
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'wpmcp_clear_consent_logs',
                        nonce: wpmcp_admin_data.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            $('#consent-logs-body').html('<tr><td colspan="5">No consent logs found.</td></tr>');
                            alert('Consent logs cleared successfully');
                        } else {
                            alert('Error: ' + response.data);
                        }
                    },
                    error: function() {
                        alert('Failed to clear consent logs');
                    }
                });
            }
        });
        
        // Unsubscribe resource
        $(document).on('click', '.unsubscribe-resource', function() {
            if (confirm('Are you sure you want to unsubscribe from this resource?')) {
                const uri = $(this).data('uri');
                const $row = $(this).closest('tr');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'wpmcp_unsubscribe_resource',
                        nonce: wpmcp_admin_data.nonce,
                        uri: uri
                    },
                    success: function(response) {
                        if (response.success) {
                            $row.remove();
                            if ($('#resource-subscriptions-container tbody tr').length === 0) {
                                $('#resource-subscriptions-container').html('<p>No active resource subscriptions.</p>');
                            }
                        } else {
                            alert('Error: ' + response.data);
                        }
                    },
                    error: function() {
                        alert('Failed to unsubscribe from resource');
                    }
                });
            }
        });
        
        // Clear notification
        $(document).on('click', '.clear-notification', function() {
            const index = $(this).data('index');
            const $row = $(this).closest('tr');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'wpmcp_clear_notification',
                    nonce: wpmcp_admin_data.nonce,
                    index: index
                },
                success: function(response) {
                    if (response.success) {
                        $row.remove();
                        if ($('#resource-notifications-container tbody tr').length === 0) {
                            $('#resource-notifications-container').html('<p>No pending resource notifications.</p>');
                        }
                    } else {
                        alert('Error: ' + response.data);
                    }
                },
                error: function() {
                    alert('Failed to clear notification');
                }
            });
        });
        
        // Clear all notifications
        $('#clear-all-notifications').on('click', function() {
            if (confirm('Are you sure you want to clear all notifications?')) {
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'wpmcp_clear_all_notifications',
                        nonce: wpmcp_admin_data.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            $('#resource-notifications-container').html('<p>No pending resource notifications.</p>');
                        } else {
                            alert('Error: ' + response.data);
                        }
                    },
                    error: function() {
                        alert('Failed to clear notifications');
                    }
                });
            }
        });
    });
</script>

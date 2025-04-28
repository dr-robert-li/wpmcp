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
    
    <form method="post" action="options.php">
        <?php
        settings_fields('wpmcp_settings');
        do_settings_sections('wpmcp_settings');
        ?>
        
        <div class="wpmcp-settings-section">
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
        
        <div class="wpmcp-settings-section">
            <h2>Resource Settings</h2>
            <p>Configure which WordPress resources can be accessed via the MCP API and how resource changes are handled.</p>
            
            <table class="form-table">
                <tr>
                    <th scope="row">Allowed Endpoints</th>
                    <td>
                        <?php 
                        $allowed_endpoints = get_option('wpmcp_allowed_endpoints', array('posts', 'pages', 'categories', 'tags', 'comments', 'users'));
                        $allowed_endpoints = is_array($allowed_endpoints) ? $allowed_endpoints : array();
                        
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
        
        <div class="wpmcp-settings-section">
            <h2><?php _e('Tool Permissions', 'wpmcp'); ?></h2>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php _e('Permission Levels', 'wpmcp'); ?></th>
                    <td>
                        <?php 
                        $tool_permissions = get_option('wpmcp_tool_permissions', array(
                            'wp_discover_endpoints' => 'api_key',
                            'wp_call_endpoint' => 'admin'
                        ));
                        
                        $available_tools = array(
                            'wp_discover_endpoints' => __('Discover Endpoints', 'wpmcp'),
                            'wp_call_endpoint' => __('Call Endpoint', 'wpmcp')
                        );
                        
                        $permission_levels = array(
                            'api_key' => __('API Key Only', 'wpmcp'),
                            'admin' => __('Admin Required', 'wpmcp')
                        );
                        
                        foreach ($available_tools as $tool => $label): 
                            $current_permission = isset($tool_permissions[$tool]) ? $tool_permissions[$tool] : 'api_key';
                        ?>
                            <p>
                                <label><strong><?php echo esc_html($label); ?></strong></label><br>
                                <select name="wpmcp_tool_permissions[<?php echo esc_attr($tool); ?>]">
                                    <?php foreach ($permission_levels as $level => $level_label): ?>
                                        <option value="<?php echo esc_attr($level); ?>" <?php selected($current_permission, $level); ?>>
                                            <?php echo esc_html($level_label); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </p>
                        <?php endforeach; ?>
                        <p class="description"><?php _e('Set permission levels for each tool. "API Key Only" allows any request with a valid API key. "Admin Required" requires the request to be authenticated as an admin user.', 'wpmcp'); ?></p>
                    </td>
                </tr>
            </table>

        </div>

        <div class="wpmcp-settings-section">
            <h2>Prompt Templates</h2>
            <p>Configure prompt templates that can be used by AI assistants.</p>
            
            <?php 
            $prompt_templates = get_option('wpmcp_prompt_templates', array());
            $prompt_templates = is_array($prompt_templates) ? $prompt_templates : array();
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
        
        <div class="wpmcp-settings-section">
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
        
        <div class="wpmcp-settings-section">
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
        }
    ]
}</pre>
            </div>
            
            <p><strong>Note:</strong> Replace <code>YOUR_API_KEY_HERE</code> with the API key generated above.</p>
        </div>
        
        <div class="wpmcp-settings-section">
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
            $subscriptions = is_array($subscriptions) ? $subscriptions : array();
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
            </div>
            
            <?php submit_button('Save Settings'); ?>
        </form>
        
        <div class="wpmcp-footer">
            <p>
                WordPress Model Context Protocol (WPMCP) v<?php echo WPMCP_VERSION; ?> | 
                <a href="https://github.com/dr-robert-li/wpmcp" target="_blank">GitHub</a> | 
                <a href="https://github.com/dr-robert-li/wpmcp/issues" target="_blank">Report Issues</a>
            </p>
        </div>
    </div>

    <style>
        .wpmcp-settings-section {
            background: #fff;
            border: 1px solid #ccd0d4;
            border-radius: 4px;
            padding: 15px 20px;
            margin: 20px 0;
        }
        
        .wpmcp-settings-section h2 {
            margin-top: 0;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        
        .wpmcp-code-example {
            background: #f9f9f9;
            border: 1px solid #e5e5e5;
            border-radius: 4px;
            padding: 15px;
            margin: 15px 0;
        }
        
        .wpmcp-code-example pre {
            margin: 0;
            padding: 10px;
            overflow-x: auto;
            background: #f1f1f1;
            border-radius: 3px;
        }
        
        .wpmcp-status {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 12px;
            font-weight: bold;
        }
        
        .wpmcp-status-success {
            background: #edfaef;
            color: #00a32a;
        }
        
        .wpmcp-status-error {
            background: #ffecec;
            color: #d63638;
        }
        
        .wpmcp-status-pending {
            background: #fef8ee;
            color: #bd8600;
        }
        
        .wpmcp-footer {
            margin-top: 30px;
            padding-top: 10px;
            border-top: 1px solid #eee;
            color: #666;
            font-size: 12px;
        }
    </style>

    <script>
        jQuery(document).ready(function($) {
            // Test API connection
            $('#test-api-connection').on('click', function() {
                const apiKey = $('input[name="wpmcp_api_key"]').val();
                if (!apiKey) {
                    $('#api-connection-result').html('<span style="color: red;">Please enter an API key first</span>');
                    return;
                }
                
                $('#api-connection-result').html('<span style="color: blue;">Testing connection...</span>');
                
                $.ajax({
                    url: '<?php echo esc_url(rest_url('wpmcp/v1/data')); ?>',
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    data: JSON.stringify({
                        type: 'invoke',
                        name: 'wp_discover_endpoints',
                        arguments: {},
                        api_key: apiKey  // Send API key in the request body
                    }),
                    success: function(response) {
                        if (response.type === 'success') {
                            const endpointCount = Array.isArray(response.data) ? response.data.length : 0;
                            $('#api-connection-result').html('<span style="color: green;">Connection successful! Discovered ' + endpointCount + ' endpoints.</span>');
                        } else {
                            $('#api-connection-result').html('<span style="color: red;">Connection failed: Invalid response format</span>');
                        }
                    },
                    error: function(xhr) {
                        $('#api-connection-result').html('<span style="color: red;">Connection failed: ' + (xhr.responseJSON?.message || 'Unknown error') + '</span>');
                    }
                });
            });
            
            // Generate API key button
            $('#generate-api-key').on('click', function() {
                // Generate a random API key
                const apiKey = Math.random().toString(36).substring(2, 15) + 
                            Math.random().toString(36).substring(2, 15);
                $('input[name="wpmcp_api_key"]').val(apiKey);
            });
            
            // Prompt templates management
            let promptTemplates = <?php echo json_encode($prompt_templates); ?>;
            let editingPromptIndex = -1;
            
            // Show add prompt modal
            $('#add-prompt-template').on('click', function() {
                $('#prompt-modal-title').text('Add Prompt Template');
                $('#prompt-name').val('');
                $('#prompt-description').val('');
                $('#prompt-content').val('');
                $('#prompt-arguments-list').empty();
                editingPromptIndex = -1;
                $('#prompt-template-modal').show();
            });
            
            // Close modal
            $('#cancel-prompt').on('click', function() {
                $('#prompt-template-modal').hide();
            });
            
            // Add argument
            $('#add-argument').on('click', function() {
                const argIndex = $('#prompt-arguments-list').children().length;
                const argHtml = `
                    <div class="prompt-argument" data-index="${argIndex}">
                        <p>
                            <input type="text" class="arg-name" placeholder="Argument name" style="width: 200px;">
                            <input type="text" class="arg-description" placeholder="Description" style="width: 200px;">
                            <label>
                                <input type="checkbox" class="arg-required"> Required
                            </label>
                            <button type="button" class="button button-small remove-argument">Remove</button>
                        </p>
                    </div>
                `;
                $('#prompt-arguments-list').append(argHtml);
            });
            
            // Remove argument
            $(document).on('click', '.remove-argument', function() {
                $(this).closest('.prompt-argument').remove();
            });
            
            // Save prompt template
            $('#save-prompt').on('click', function() {
                const name = $('#prompt-name').val().trim();
                const description = $('#prompt-description').val().trim();
                const content = $('#prompt-content').val().trim();
                
                if (!name || !content) {
                    alert('Name and content are required');
                    return;
                }
                
                // Collect arguments
                const arguments = [];
                $('.prompt-argument').each(function() {
                    const argName = $(this).find('.arg-name').val().trim();
                    const argDescription = $(this).find('.arg-description').val().trim();
                    const argRequired = $(this).find('.arg-required').is(':checked');
                    
                    if (argName) {
                        arguments.push({
                            name: argName,
                            description: argDescription,
                            required: argRequired
                        });
                    }
                });
                
                const template = {
                    name: name,
                    description: description,
                    content: content,
                    arguments: arguments
                };
                
                if (editingPromptIndex >= 0) {
                    // Update existing template
                    promptTemplates[editingPromptIndex] = template;
                } else {
                    // Add new template
                    promptTemplates.push(template);
                }
                
                // Update hidden field
                $('#wpmcp-prompt-templates-json').val(JSON.stringify(promptTemplates));
                
                // Close modal and reload page to show updated templates
                $('#prompt-template-modal').hide();
                location.reload();
            });
            
            // Edit prompt
            $(document).on('click', '.edit-prompt', function() {
                const promptName = $(this).data('name');
                const promptIndex = promptTemplates.findIndex(t => t.name === promptName);
                
                if (promptIndex >= 0) {
                    const template = promptTemplates[promptIndex];
                    editingPromptIndex = promptIndex;
                    
                    $('#prompt-modal-title').text('Edit Prompt Template');
                    $('#prompt-name').val(template.name);
                    $('#prompt-description').val(template.description);
                    $('#prompt-content').val(template.content);
                    
                    // Add arguments
                    $('#prompt-arguments-list').empty();
                    if (template.arguments && template.arguments.length > 0) {
                        template.arguments.forEach((arg, index) => {
                            const argHtml = `
                                <div class="prompt-argument" data-index="${index}">
                                    <p>
                                        <input type="text" class="arg-name" placeholder="Argument name" value="${arg.name}" style="width: 200px;">
                                        <input type="text" class="arg-description" placeholder="Description" value="${arg.description || ''}" style="width: 200px;">
                                        <label>
                                            <input type="checkbox" class="arg-required" ${arg.required ? 'checked' : ''}> Required
                                        </label>
                                        <button type="button" class="button button-small remove-argument">Remove</button>
                                    </p>
                                </div>
                            `;
                            $('#prompt-arguments-list').append(argHtml);
                        });
                    }
                    
                    $('#prompt-template-modal').show();
                }
            });
            
            // Delete prompt
            $(document).on('click', '.delete-prompt', function() {
                if (confirm('Are you sure you want to delete this prompt template?')) {
                    const promptName = $(this).data('name');
                    const promptIndex = promptTemplates.findIndex(t => t.name === promptName);
                    
                    if (promptIndex >= 0) {
                        promptTemplates.splice(promptIndex, 1);
                        $('#wpmcp-prompt-templates-json').val(JSON.stringify(promptTemplates));
                        location.reload();
                    }
                }
            });
            
            // View consent logs
            $('#view-consent-logs').on('click', function() {
                $('#consent-logs-table').toggle();
                
                if ($('#consent-logs-table').is(':visible')) {
                    // Show loading indicator
                    $('#consent-logs-body').html('<tr><td colspan="6">Loading...</td></tr>');
                    
                    // Fetch logs from the server
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'wpmcp_get_consent_logs',
                            nonce: wpmcp_admin.nonce,
                            page: 1,
                            per_page: 20
                        },
                        success: function(response) {
                            if (response.success && response.data.logs) {
                                const logs = response.data.logs;
                                
                                if (logs.length === 0) {
                                    $('#consent-logs-body').html('<tr><td colspan="6">No logs available</td></tr>');
                                    return;
                                }
                                
                                let logsHtml = '';
                                logs.forEach(log => {
                                    // Format timestamp
                                    const date = new Date(log.timestamp);
                                    const formattedDate = date.toLocaleString();
                                    
                                    // Format arguments for display
                                    let argsDisplay = '';
                                    try {
                                        if (typeof log.arguments === 'object') {
                                            // Arguments are already parsed
                                            argsDisplay = JSON.stringify(log.arguments, null, 2);
                                        } else {
                                            // Parse arguments if they're a string
                                            argsDisplay = JSON.stringify(JSON.parse(log.arguments), null, 2);
                                        }
                                        // Truncate if too long
                                        if (argsDisplay.length > 100) {
                                            argsDisplay = argsDisplay.substring(0, 100) + '...';
                                        }
                                    } catch (e) {
                                        argsDisplay = 'Error parsing arguments';
                                    }
                                    
                                    // Create table row
                                    logsHtml += `
                                        <tr>
                                            <td>${formattedDate}</td>
                                            <td>${log.user_name} (ID: ${log.user_id})</td>
                                            <td>${log.tool_name}</td>
                                            <td><pre>${argsDisplay}</pre></td>
                                            <td>${log.status}</td>
                                            <td>${log.ip_address}</td>
                                        </tr>
                                    `;
                                });
                                
                                $('#consent-logs-body').html(logsHtml);
                                
                                // Add pagination if needed
                                if (response.data.pages > 1) {
                                    let paginationHtml = '<div class="tablenav"><div class="tablenav-pages">';
                                    paginationHtml += `<span class="displaying-num">${response.data.total} items</span>`;
                                    
                                    if (response.data.current_page > 1) {
                                        paginationHtml += `<a class="prev-page button consent-log-page" data-page="${response.data.current_page - 1}"><span class="screen-reader-text">Previous page</span><span aria-hidden="true">‹</span></a>`;
                                    } else {
                                        paginationHtml += `<span class="prev-page button disabled"><span class="screen-reader-text">Previous page</span><span aria-hidden="true">‹</span></span>`;
                                    }
                                    
                                    paginationHtml += `<span class="paging-input">${response.data.current_page} of <span class="total-pages">${response.data.pages}</span></span>`;
                                    
                                    if (response.data.current_page < response.data.pages) {
                                        paginationHtml += `<a class="next-page button consent-log-page" data-page="${response.data.current_page + 1}"><span class="screen-reader-text">Next page</span><span aria-hidden="true">›</span></a>`;
                                    } else {
                                        paginationHtml += `<span class="next-page button disabled"><span class="screen-reader-text">Next page</span><span aria-hidden="true">›</span></span>`;
                                    }
                                    
                                    paginationHtml += '</div></div>';
                                    
                                    $('#consent-logs-pagination').html(paginationHtml);
                                } else {
                                    $('#consent-logs-pagination').html('');
                                }
                            } else {
                                $('#consent-logs-body').html('<tr><td colspan="6">Error loading logs</td></tr>');
                            }
                        },
                        error: function() {
                            $('#consent-logs-body').html('<tr><td colspan="6">Error loading logs</td></tr>');
                        }
                    });
                }
            });

            // Handle pagination clicks
            $(document).on('click', '.consent-log-page', function(e) {
                e.preventDefault();
                const page = $(this).data('page');
                
                // Show loading indicator
                $('#consent-logs-body').html('<tr><td colspan="6">Loading...</td></tr>');
                
                // Fetch logs for the selected page
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'wpmcp_get_consent_logs',
                        nonce: wpmcp_admin.nonce,
                        page: page,
                        per_page: 20
                    },
                    success: function(response) {
                        // Same handling as above, could be refactored into a function
                        if (response.success && response.data.logs) {
                            const logs = response.data.logs;
                            
                            if (logs.length === 0) {
                                $('#consent-logs-body').html('<tr><td colspan="6">No logs available</td></tr>');
                                return;
                            }
                            
                            let logsHtml = '';
                            logs.forEach(log => {
                                // Format timestamp
                                const date = new Date(log.timestamp);
                                const formattedDate = date.toLocaleString();
                                
                                // Format arguments for display
                                let argsDisplay = '';
                                try {
                                    if (typeof log.arguments === 'object') {
                                        argsDisplay = JSON.stringify(log.arguments, null, 2);
                                    } else {
                                        argsDisplay = JSON.stringify(JSON.parse(log.arguments), null, 2);
                                    }
                                    if (argsDisplay.length > 100) {
                                        argsDisplay = argsDisplay.substring(0, 100) + '...';
                                    }
                                } catch (e) {
                                    argsDisplay = 'Error parsing arguments';
                                }
                                
                                // Create table row
                                logsHtml += `
                                    <tr>
                                        <td>${formattedDate}</td>
                                        <td>${log.user_name} (ID: ${log.user_id})</td>
                                        <td>${log.tool_name}</td>
                                        <td><pre>${argsDisplay}</pre></td>
                                        <td>${log.status}</td>
                                        <td>${log.ip_address}</td>
                                    </tr>
                                `;
                            });
                            
                            $('#consent-logs-body').html(logsHtml);
                            
                            // Update pagination
                            if (response.data.pages > 1) {
                                let paginationHtml = '<div class="tablenav"><div class="tablenav-pages">';
                                paginationHtml += `<span class="displaying-num">${response.data.total} items</span>`;
                                
                                if (response.data.current_page > 1) {
                                    paginationHtml += `<a class="prev-page button consent-log-page" data-page="${response.data.current_page - 1}"><span class="screen-reader-text">Previous page</span><span aria-hidden="true">‹</span></a>`;
                                } else {
                                    paginationHtml += `<span class="prev-page button disabled"><span class="screen-reader-text">Previous page</span><span aria-hidden="true">‹</span></span>`;
                                }
                                
                                paginationHtml += `<span class="paging-input">${response.data.current_page} of <span class="total-pages">${response.data.pages}</span></span>`;
                                
                                if (response.data.current_page < response.data.pages) {
                                    paginationHtml += `<a class="next-page button consent-log-page" data-page="${response.data.current_page + 1}"><span class="screen-reader-text">Next page</span><span aria-hidden="true">›</span></a>`;
                                } else {
                                    paginationHtml += `<span class="next-page button disabled"><span class="screen-reader-text">Next page</span><span aria-hidden="true">›</span></span>`;
                                }
                                
                                paginationHtml += '</div></div>';
                                
                                $('#consent-logs-pagination').html(paginationHtml);
                            } else {
                                $('#consent-logs-pagination').html('');
                            }
                        } else {
                            $('#consent-logs-body').html('<tr><td colspan="6">Error loading logs</td></tr>');
                        }
                    },
                    error: function() {
                        $('#consent-logs-body').html('<tr><td colspan="6">Error loading logs</td></tr>');
                    }
                });
            });

            // Clear consent logs
            $('#clear-consent-logs').on('click', function() {
                if (confirm('Are you sure you want to clear all consent logs? This action cannot be undone.')) {
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'wpmcp_clear_consent_logs',
                            nonce: wpmcp_admin.nonce
                        },
                        success: function(response) {
                            if (response.success) {
                                $('#consent-logs-body').html('<tr><td colspan="6">No logs available</td></tr>');
                                $('#consent-logs-pagination').html('');
                                alert('Logs cleared successfully');
                            } else {
                                alert('Failed to clear logs: ' + (response.data || 'Unknown error'));
                            }
                        },
                        error: function() {
                            alert('Failed to clear logs due to a server error');
                        }
                    });
                }
            });
    </script>

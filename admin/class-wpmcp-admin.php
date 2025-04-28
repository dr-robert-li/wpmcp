<?php
/**
 * The admin-specific functionality of the plugin.
 *
 * @since      2.0.0
 * @package    WPMCP
 * @subpackage WPMCP/admin
 */

class WPMCP_Admin {

    /**
     * The ID of this plugin.
     *
     * @since    2.0.0
     * @access   private
     * @var      string    $plugin_name    The ID of this plugin.
     */
    private $plugin_name;

    /**
     * The version of this plugin.
     *
     * @since    2.0.0
     * @access   private
     * @var      string    $version    The current version of this plugin.
     */
    private $version;

    /**
     * Initialize the class and set its properties.
     *
     * @since    2.0.0
     * @param    string    $plugin_name       The name of this plugin.
     * @param    string    $version           The version of this plugin.
     */
    public function __construct($plugin_name = 'wpmcp', $version = WPMCP_VERSION) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
    }

    /**
     * Register the stylesheets for the admin area.
     *
     * @since    2.0.0
     */
    public function enqueue_styles() {
        wp_enqueue_style(
            $this->plugin_name,
            WPMCP_PLUGIN_URL . 'admin/css/wpmcp-admin.css',
            array(),
            $this->version,
            'all'
        );
    }

    /**
     * Register the JavaScript for the admin area.
     *
     * @since    2.0.0
     */
    public function enqueue_scripts() {
        wp_enqueue_script(
            $this->plugin_name,
            WPMCP_PLUGIN_URL . 'admin/js/wpmcp-admin.js',
            array('jquery'),
            $this->version,
            false
        );

        // Localize script with admin data
        wp_localize_script($this->plugin_name, 'wpmcp_admin_data', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wpmcp_admin_nonce'),
            'rest_url' => rest_url('wpmcp/v1'),
            'api_key' => get_option('wpmcp_api_key', '')
        ));
    }

    /**
     * Register the administration menu for this plugin.
     *
     * @since    2.0.0
     */
    public function add_options_page() {
        add_options_page(
            'WPMCP Settings',
            'WPMCP',
            'manage_options',
            'wpmcp-settings',
            array($this, 'display_settings_page')
        );
    }

    /**
     * Register the settings for this plugin.
     *
     * @since    2.0.0
     */
    public function register_settings() {
        // API Key
        register_setting('wpmcp_settings', 'wpmcp_api_key', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => ''
        ));
        
        // Allowed endpoints
        register_setting('wpmcp_settings', 'wpmcp_allowed_endpoints', array(
            'type' => 'array',
            'default' => array('posts', 'pages', 'categories', 'tags', 'comments', 'users')
        ));
        
        // Feature settings
        register_setting('wpmcp_settings', 'wpmcp_enable_notifications', array(
            'type' => 'boolean',
            'default' => true
        ));
        
        register_setting('wpmcp_settings', 'wpmcp_require_consent', array(
            'type' => 'boolean',
            'default' => true
        ));
        
        // Prompt templates
        register_setting('wpmcp_settings', 'wpmcp_prompt_templates', array(
            'type' => 'array',
            'default' => array()
        ));
        
        // Add settings sections
        add_settings_section(
            'wpmcp_section_general',
            'General Settings',
            array($this, 'render_section_general'),
            'wpmcp_settings'
        );
        
        add_settings_section(
            'wpmcp_section_resources',
            'Resource Settings',
            array($this, 'render_section_resources'),
            'wpmcp_settings'
        );
        
        add_settings_section(
            'wpmcp_section_prompts',
            'Prompt Templates',
            array($this, 'render_section_prompts'),
            'wpmcp_settings'
        );
        
        add_settings_section(
            'wpmcp_section_security',
            'Security Settings',
            array($this, 'render_section_security'),
            'wpmcp_settings'
        );
        
        // Add settings fields
        add_settings_field(
            'wpmcp_api_key',
            'API Key',
            array($this, 'render_api_key_field'),
            'wpmcp_settings',
            'wpmcp_section_general'
        );
        
        add_settings_field(
            'wpmcp_allowed_endpoints',
            'Allowed Endpoints',
            array($this, 'render_allowed_endpoints_field'),
            'wpmcp_settings',
            'wpmcp_section_resources'
        );
        
        add_settings_field(
            'wpmcp_enable_notifications',
            'Resource Notifications',
            array($this, 'render_enable_notifications_field'),
            'wpmcp_settings',
            'wpmcp_section_resources'
        );
        
        add_settings_field(
            'wpmcp_require_consent',
            'Require User Consent',
            array($this, 'render_require_consent_field'),
            'wpmcp_settings',
            'wpmcp_section_security'
        );
        
        add_settings_field(
            'wpmcp_prompt_templates',
            'Prompt Templates',
            array($this, 'render_prompt_templates_field'),
            'wpmcp_settings',
            'wpmcp_section_prompts'
        );
    }

    /**
     * Render the general section description.
     *
     * @since    2.0.0
     */
    public function render_section_general() {
        echo '<p>Configure general settings for the WordPress Model Context Protocol.</p>';
    }
    
    /**
     * Render the resources section description.
     *
     * @since    2.0.0
     */
    public function render_section_resources() {
        echo '<p>Configure which WordPress resources can be accessed via the MCP API.</p>';
    }
    
    /**
     * Render the prompts section description.
     *
     * @since    2.0.0
     */
    public function render_section_prompts() {
        echo '<p>Configure prompt templates that can be used by AI assistants.</p>';
    }
    
    /**
     * Render the security section description.
     *
     * @since    2.0.0
     */
    public function render_section_security() {
        echo '<p>Configure security settings for the MCP API.</p>';
    }
    
    /**
     * Render the API key field.
     *
     * @since    2.0.0
     */
    public function render_api_key_field() {
        $api_key = get_option('wpmcp_api_key', '');
        ?>
        <input type="text" name="wpmcp_api_key" value="<?php echo esc_attr($api_key); ?>" class="regular-text">
        <p class="description">API key for authentication (required for security)</p>
        <?php if (empty($api_key)): ?>
            <button type="button" id="generate-api-key" class="button button-secondary">Generate API Key</button>
            <script>
                document.getElementById('generate-api-key').addEventListener('click', function() {
                    const apiKey = Math.random().toString(36).substring(2, 15) + 
                                  Math.random().toString(36).substring(2, 15);
                    document.querySelector('input[name="wpmcp_api_key"]').value = apiKey;
                });
            </script>
        <?php endif; ?>
        <?php
    }
    
    /**
     * Render the allowed endpoints field.
     *
     * @since    2.0.0
     */
    public function render_allowed_endpoints_field() {
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
        <?php
    }
    
    /**
     * Render the enable notifications field.
     *
     * @since    2.0.0
     */
    public function render_enable_notifications_field() {
        $enable_notifications = get_option('wpmcp_enable_notifications', true);
        ?>
        <label>
            <input type="checkbox" name="wpmcp_enable_notifications" value="1" <?php checked($enable_notifications); ?>>
            Enable resource change notifications
        </label>
        <p class="description">When enabled, the plugin will track changes to resources and notify subscribers</p>
        <?php
    }
    
    /**
     * Render the require consent field.
     *
     * @since    2.0.0
     */
    public function render_require_consent_field() {
        $require_consent = get_option('wpmcp_require_consent', true);
        ?>
        <label>
            <input type="checkbox" name="wpmcp_require_consent" value="1" <?php checked($require_consent); ?>>
            Require explicit user consent for tool invocations
        </label>
        <p class="description">When enabled, users must explicitly consent to tool invocations</p>
        <?php
    }
    
    /**
     * Render the prompt templates field.
     *
     * @since    2.0.0
     */
    public function render_prompt_templates_field() {
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
        
        <script>
            jQuery(document).ready(function($) {
                // Prompt template management
                let promptTemplates = <?php echo json_encode($prompt_templates); ?>;
                let editingPromptName = null;
                
                // Show add prompt modal
                $('#add-prompt-template').on('click', function() {
                    editingPromptName = null;
                    $('#prompt-modal-title').text('Add Prompt Template');
                    $('#prompt-name').val('').prop('disabled', false);
                    $('#prompt-description').val('');
                    $('#prompt-content').val('');
                    $('#prompt-arguments-list').empty();
                    $('#prompt-template-modal').show();
                });
                
                // Edit prompt
                $(document).on('click', '.edit-prompt', function() {
                    const name = $(this).data('name');
                    editingPromptName = name;
                    const template = promptTemplates[name];
                    
                    $('#prompt-modal-title').text('Edit Prompt Template');
                    $('#prompt-name').val(name).prop('disabled', true);
                    $('#prompt-description').val(template.description);
                    $('#prompt-content').val(template.content || '');
                    
                    // Load arguments
                    $('#prompt-arguments-list').empty();
                    if (template.arguments && template.arguments.length) {
                        template.arguments.forEach(arg => {
                            addArgumentToList(arg.name, arg.description, arg.required);
                        });
                    }
                    
                    $('#prompt-template-modal').show();
                });
                
                // Delete prompt
                $(document).on('click', '.delete-prompt', function() {
                    if (confirm('Are you sure you want to delete this prompt template?')) {
                        const name = $(this).data('name');
                        delete promptTemplates[name];
                        updatePromptTemplatesField();
                        // Refresh the page to show changes
                        location.reload();
                    }
                });
                
                // Close modal
                $('#cancel-prompt').on('click', function() {
                    $('#prompt-template-modal').hide();
                });
                
                // Add argument
                $('#add-argument').on('click', function() {
                    addArgumentToList('', '', false);
                });
                
                // Remove argument
                $(document).on('click', '.remove-argument', function() {
                    $(this).closest('.argument-row').remove();
                });
                
                // Save prompt template
                $('#save-prompt').on('click', function() {
                    const name = $('#prompt-name').val().trim();
                    const description = $('#prompt-description').val().trim();
                    const content = $('#prompt-content').val().trim();
                    
                    if (!name) {
                        alert('Prompt name is required');
                        return;
                    }
                    
                    if (!description) {
                        alert('Prompt description is required');
                        return;
                    }
                    
                    // Collect arguments
                    const arguments = [];
                    $('.argument-row').each(function() {
                        const argName = $(this).find('.arg-name').val().trim();
                        const argDesc = $(this).find('.arg-desc').val().trim();
                        const argRequired = $(this).find('.arg-required').is(':checked');
                        
                        if (argName) {
                            arguments.push({
                                name: argName,
                                description: argDesc,
                                required: argRequired
                            });
                        }
                    });
                    
                    // Save template
                    promptTemplates[name] = {
                        name: name,
                        description: description,
                        arguments: arguments,
                        content: content
                    };
                    
                    updatePromptTemplatesField();
                    $('#prompt-template-modal').hide();
                    
                    // Refresh the page to show changes
                    location.reload();
                });
                
                // Helper to add argument to the list
                function addArgumentToList(name, description, required) {
                    const row = $(`
                        <div class="argument-row" style="margin-bottom: 10px; padding-bottom: 10px; border-bottom: 1px solid #eee;">
                            <div style="display: flex; margin-bottom: 5px;">
                                <input type="text" class="arg-name" placeholder="Argument name" value="${name}" style="flex: 1; margin-right: 10px;">
                                <label style="margin-right: 5px;">
                                    <input type="checkbox" class="arg-required" ${required ? 'checked' : ''}>
                                    Required
                                </label>
                                <button type="button" class="button button-small remove-argument">Remove</button>
                            </div>
                            <input type="text" class="arg-desc" placeholder="Argument description" value="${description}" style="width: 100%;">
                        </div>
                    `);
                    
                    $('#prompt-arguments-list').append(row);
                }
                
                // Update hidden field with JSON data
                function updatePromptTemplatesField() {
                    $('#wpmcp-prompt-templates-json').val(JSON.stringify(promptTemplates));
                }
            });
        </script>
        <?php
    }
    
    /**
     * Display the settings page content.
     *
     * @since    2.0.0
     */
    public function display_settings_page() {
        include_once 'partials/wpmcp-admin-display.php';
    }
    
    /**
     * Add settings link to plugin listing
     *
     * @since    2.0.0
     * @param    array    $links    Plugin action links
     * @return   array              Modified action links
     */
    public function add_settings_link($links) {
        $settings_link = '<a href="options-general.php?page=wpmcp-settings">' . __('Settings', 'wpmcp') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }
    
    /**
     * Add a dashboard widget for MCP stats
     *
     * @since    2.0.0
     */
    public function add_dashboard_widget() {
        wp_add_dashboard_widget(
            'wpmcp_dashboard_widget',
            'WPMCP Statistics',
            array($this, 'render_dashboard_widget')
        );
    }
    
    /**
     * Render the dashboard widget content
     *
     * @since    2.0.0
     */
    public function render_dashboard_widget() {
        // Get stats
        $subscriptions = get_option('wpmcp_resource_subscriptions', array());
        $notifications = get_option('wpmcp_resource_notifications', array());
        $consent_logs = get_option('wpmcp_consent_logs', array());
        
        // Display stats
        ?>
        <div class="wpmcp-stats">
            <div class="stat-item">
                <h4>Resource Subscriptions</h4>
                <p class="stat-value"><?php echo count($subscriptions); ?></p>
            </div>
            
            <div class="stat-item">
                <h4>Pending Notifications</h4>
                <p class="stat-value"><?php echo count($notifications); ?></p>
            </div>
            
            <div class="stat-item">
                <h4>Consent Logs</h4>
                <p class="stat-value"><?php echo count($consent_logs); ?></p>
            </div>
        </div>
        
        <p>
            <a href="options-general.php?page=wpmcp-settings" class="button button-primary">Manage Settings</a>
        </p>
        
        <style>
            .wpmcp-stats {
                display: flex;
                justify-content: space-between;
                margin-bottom: 15px;
            }
            .wpmcp-stats .stat-item {
                text-align: center;
                flex: 1;
                padding: 10px;
                background: #f9f9f9;
                border-radius: 4px;
                margin: 0 5px;
            }
            .wpmcp-stats .stat-item h4 {
                margin: 0 0 5px;
            }
            .wpmcp-stats .stat-value {
                font-size: 24px;
                font-weight: bold;
                margin: 0;
            }
        </style>
        <?php
    }
    
    /**
     * Handle AJAX request to view consent logs
     *
     * @since    2.0.0
     */
    public function ajax_view_consent_logs() {
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }
        
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'wpmcp_admin_nonce')) {
            wp_send_json_error('Invalid nonce');
        }
        
        // Get consent logs
        $consent_logs = get_option('wpmcp_consent_logs', array());
        
        // Return logs
        wp_send_json_success($consent_logs);
    }
    
    /**
     * Handle AJAX request to clear consent logs
     *
     * @since    2.0.0
     */
    public function ajax_clear_consent_logs() {
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }
        
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'wpmcp_admin_nonce')) {
            wp_send_json_error('Invalid nonce');
        }
        
        // Clear consent logs
        update_option('wpmcp_consent_logs', array());
        
        // Return success
        wp_send_json_success('Consent logs cleared');
    }
    
    /**
     * Handle AJAX request to test API connection
     *
     * @since    2.0.0
     */
    public function ajax_test_api_connection() {
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }
        
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'wpmcp_admin_nonce')) {
            wp_send_json_error('Invalid nonce');
        }
        
        // Get API key
        $api_key = get_option('wpmcp_api_key', '');
        
        if (empty($api_key)) {
            wp_send_json_error('API key is not set');
        }
        
        // Test API connection
        $response = wp_remote_post(rest_url('wpmcp/v1/data'), array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'X-API-Key' => $api_key
            ),
            'body' => json_encode(array(
                'type' => 'describe'
            ))
        ));
        
        if (is_wp_error($response)) {
            wp_send_json_error('API connection failed: ' . $response->get_error_message());
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (empty($data) || !isset($data['type']) || $data['type'] !== 'description') {
            wp_send_json_error('API response is invalid');
        }
        
        // Return success
        wp_send_json_success('API connection successful');
    }
}

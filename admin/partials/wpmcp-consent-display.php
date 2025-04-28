<?php
/**
 * Provide a public-facing view for the consent UI
 *
 * This file is used to markup the public-facing aspects of the plugin.
 *
 * @since      2.0.0
 * @package    WPMCP
 * @subpackage WPMCP/public/partials
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}
?>

<div id="wpmcp-consent-modal" style="display: none;" class="wpmcp-modal">
    <div class="wpmcp-modal-content">
        <div class="wpmcp-modal-header">
            <span class="wpmcp-close">&times;</span>
            <h2><?php _e('AI Assistant Action Approval', 'wpmcp'); ?></h2>
        </div>
        <div class="wpmcp-modal-body">
            <p><?php _e('An AI assistant is requesting permission to perform the following action:', 'wpmcp'); ?></p>
            
            <div class="wpmcp-consent-details">
                <h3><?php _e('Tool', 'wpmcp'); ?>: <span id="wpmcp-consent-tool"></span></h3>
                
                <div class="wpmcp-consent-arguments">
                    <h4><?php _e('Arguments', 'wpmcp'); ?>:</h4>
                    <pre id="wpmcp-consent-arguments"></pre>
                </div>
                
                <div class="wpmcp-consent-description">
                    <p id="wpmcp-consent-description"></p>
                </div>
            </div>
            
            <div class="wpmcp-consent-warning">
                <p><?php _e('This action will modify data on your WordPress site. Please review carefully before approving.', 'wpmcp'); ?></p>
            </div>
        </div>
        <div class="wpmcp-modal-footer">
            <button id="wpmcp-deny-consent" class="button button-secondary"><?php _e('Deny', 'wpmcp'); ?></button>
            <button id="wpmcp-approve-consent" class="button button-primary"><?php _e('Approve', 'wpmcp'); ?></button>
        </div>
    </div>
</div>

<div id="wpmcp-consent-notification" style="display: none;" class="wpmcp-notification">
    <span id="wpmcp-notification-message"></span>
    <span class="wpmcp-notification-close">&times;</span>
</div>

<style>
    .wpmcp-modal {
        position: fixed;
        z-index: 999999;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        overflow: auto;
        background-color: rgba(0, 0, 0, 0.5);
    }
    
    .wpmcp-modal-content {
        background-color: #fefefe;
        margin: 10% auto;
        padding: 20px;
        border: 1px solid #888;
        width: 80%;
        max-width: 600px;
        border-radius: 5px;
        box-shadow: 0 4px 8px 0 rgba(0, 0, 0, 0.2);
    }
    
    .wpmcp-modal-header {
        padding: 10px 0;
        border-bottom: 1px solid #eee;
        position: relative;
    }
    
    .wpmcp-modal-header h2 {
        margin: 0;
        padding: 0;
    }
    
    .wpmcp-close {
        position: absolute;
        right: 0;
        top: 0;
        color: #aaa;
        font-size: 28px;
        font-weight: bold;
        cursor: pointer;
    }
    
    .wpmcp-close:hover,
    .wpmcp-close:focus {
        color: black;
        text-decoration: none;
        cursor: pointer;
    }
    
    .wpmcp-modal-body {
        padding: 20px 0;
    }
    
    .wpmcp-modal-footer {
        padding: 10px 0;
        border-top: 1px solid #eee;
        text-align: right;
    }
    
    .wpmcp-consent-details {
        background-color: #f9f9f9;
        padding: 15px;
        border-radius: 4px;
        margin: 15px 0;
    }
    
    .wpmcp-consent-arguments pre {
        background-color: #f0f0f0;
        padding: 10px;
        border-radius: 4px;
        overflow-x: auto;
        white-space: pre-wrap;
        word-wrap: break-word;
    }
    
    .wpmcp-consent-warning {
        background-color: #fff8e5;
        border-left: 4px solid #ffb900;
        padding: 10px 15px;
        margin: 15px 0;
    }
    
    .wpmcp-notification {
        position: fixed;
        bottom: 20px;
        right: 20px;
        background-color: #333;
        color: white;
        padding: 15px 20px;
        border-radius: 4px;
        z-index: 999999;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
        display: flex;
        align-items: center;
        justify-content: space-between;
        min-width: 300px;
    }
    
    .wpmcp-notification-close {
        margin-left: 15px;
        color: #ccc;
        font-weight: bold;
        cursor: pointer;
    }
    
    .wpmcp-notification-close:hover {
        color: white;
    }
    
    .wpmcp-notification.success {
        background-color: #46b450;
    }
    
    .wpmcp-notification.error {
        background-color: #dc3232;
    }
</style>

<script>
jQuery(document).ready(function($) {
    // Global consent request data
    let currentConsentRequest = null;
    
    // Function to show the consent modal
    window.showWpmcpConsentModal = function(data) {
        // Store the current request
        currentConsentRequest = data;
        
        // Fill in the details
        $('#wpmcp-consent-tool').text(data.tool);
        $('#wpmcp-consent-arguments').text(JSON.stringify(data.arguments, null, 2));
        
        // Set description based on tool
        let description = '';
        if (data.tool === 'wp_call_endpoint') {
            const method = data.arguments.method || 'GET';
            const endpoint = data.arguments.endpoint || '';
            
            if (method === 'POST') {
                description = 'Create a new resource at ' + endpoint;
            } else if (method === 'PUT' || method === 'PATCH') {
                description = 'Update an existing resource at ' + endpoint;
            } else if (method === 'DELETE') {
                description = 'Delete a resource at ' + endpoint;
            }
        }
        $('#wpmcp-consent-description').text(description);
        
        // Show the modal
        $('#wpmcp-consent-modal').show();
    };
    
    // Close modal when clicking the X
    $('.wpmcp-close').on('click', function() {
        $('#wpmcp-consent-modal').hide();
        // Automatically deny if closed without decision
        if (currentConsentRequest) {
            sendConsentResponse(false);
        }
    });
    
    // Close modal when clicking outside
    $(window).on('click', function(event) {
        if ($(event.target).is('#wpmcp-consent-modal')) {
            $('#wpmcp-consent-modal').hide();
            // Automatically deny if closed without decision
            if (currentConsentRequest) {
                sendConsentResponse(false);
            }
        }
    });
    
    // Approve button
    $('#wpmcp-approve-consent').on('click', function() {
        sendConsentResponse(true);
    });
    
    // Deny button
    $('#wpmcp-deny-consent').on('click', function() {
        sendConsentResponse(false);
    });
    
    // Send consent response
    function sendConsentResponse(approved) {
        if (!currentConsentRequest) {
            return;
        }
        
        $.ajax({
            url: wpmcp_data.ajax_url,
            type: 'POST',
            data: {
                action: 'wpmcp_consent',
                nonce: wpmcp_data.nonce,
                request_id: currentConsentRequest.request_id,
                tool: currentConsentRequest.tool,
                arguments: JSON.stringify(currentConsentRequest.arguments),
                approved: approved
            },
            success: function(response) {
                // Hide the modal
                $('#wpmcp-consent-modal').hide();
                
                // Show notification
                if (response.success) {
                    showNotification(
                        approved ? 'Action approved' : 'Action denied',
                        approved ? 'success' : 'error'
                    );
                } else {
                    showNotification('Error: ' + response.data, 'error');
                }
                
                // Clear current request
                currentConsentRequest = null;
            },
            error: function() {
                // Hide the modal
                $('#wpmcp-consent-modal').hide();
                
                // Show error notification
                showNotification('Failed to process consent response', 'error');
                
                // Clear current request
                currentConsentRequest = null;
            }
        });
    }
    
    // Show notification
    function showNotification(message, type) {
        $('#wpmcp-notification-message').text(message);
        $('#wpmcp-consent-notification')
            .removeClass('success error')
            .addClass(type)
            .show();
        
        // Auto-hide after 5 seconds
        setTimeout(function() {
            $('#wpmcp-consent-notification').fadeOut();
        }, 5000);
    }
    
    // Close notification
    $('.wpmcp-notification-close').on('click', function() {
        $('#wpmcp-consent-notification').hide();
    });
    
    // Listen for consent requests from postMessage (for iframe integration)
    $(window).on('message', function(event) {
        const data = event.originalEvent.data;
        
        if (data && data.type === 'wpmcp_consent_request') {
            showWpmcpConsentModal(data);
        }
    });
});
</script>

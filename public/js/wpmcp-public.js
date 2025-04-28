/**
 * All of the code for your public-facing JavaScript source
 * should reside in this file.
 *
 * @since      2.0.0
 * @package    WPMCP
 * @subpackage WPMCP/public/js
 */

(function($) {
    'use strict';

    /**
     * WPMCP Public JS
     */
    var WPMCP_Public = {
        
        // Current consent request data
        currentConsentRequest: null,
        
        /**
         * Initialize the public JS
         */
        init: function() {
            // Set up event listeners
            this.setupEventListeners();
            
            // Set up window message listener for iframe integration
            this.setupMessageListener();
        },
        
        /**
         * Set up event listeners
         */
        setupEventListeners: function() {
            // Close modal when clicking the X
            $(document).on('click', '.wpmcp-close', function() {
                WPMCP_Public.hideConsentModal();
                
                // Automatically deny if closed without decision
                if (WPMCP_Public.currentConsentRequest) {
                    WPMCP_Public.sendConsentResponse(false);
                }
            });
            
            // Close modal when clicking outside
            $(window).on('click', function(event) {
                if ($(event.target).is('.wpmcp-modal')) {
                    WPMCP_Public.hideConsentModal();
                    
                    // Automatically deny if closed without decision
                    if (WPMCP_Public.currentConsentRequest) {
                        WPMCP_Public.sendConsentResponse(false);
                    }
                }
            });
            
            // Approve button
            $(document).on('click', '#wpmcp-approve-consent', function() {
                WPMCP_Public.sendConsentResponse(true);
            });
            
            // Deny button
            $(document).on('click', '#wpmcp-deny-consent', function() {
                WPMCP_Public.sendConsentResponse(false);
            });
            
            // Close notification
            $(document).on('click', '.wpmcp-notification-close', function() {
                WPMCP_Public.hideNotification();
            });
        },
        
        /**
         * Set up window message listener for iframe integration
         */
        setupMessageListener: function() {
            $(window).on('message', function(event) {
                const data = event.originalEvent.data;
                
                if (data && data.type === 'wpmcp_consent_request') {
                    WPMCP_Public.showConsentModal(data);
                }
            });
        },
        
        /**
         * Show the consent modal
         * 
         * @param {Object} data Consent request data
         */
        showConsentModal: function(data) {
            // Store the current request
            this.currentConsentRequest = data;
            
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
        },
        
        /**
         * Hide the consent modal
         */
        hideConsentModal: function() {
            $('#wpmcp-consent-modal').hide();
        },
        
        /**
         * Send consent response
         * 
         * @param {boolean} approved Whether the request was approved
         */
        sendConsentResponse: function(approved) {
            if (!this.currentConsentRequest) {
                return;
            }
            
            $.ajax({
                url: wpmcp_data.ajax_url,
                type: 'POST',
                data: {
                    action: 'wpmcp_consent',
                    nonce: wpmcp_data.nonce,
                    request_id: this.currentConsentRequest.request_id,
                    tool: this.currentConsentRequest.tool,
                    arguments: JSON.stringify(this.currentConsentRequest.arguments),
                    approved: approved
                },
                success: function(response) {
                    // Hide the modal
                    WPMCP_Public.hideConsentModal();
                    
                    // Show notification
                    if (response.success) {
                        WPMCP_Public.showNotification(
                            approved ? 'Action approved' : 'Action denied',
                            approved ? 'success' : 'error'
                        );
                    } else {
                        WPMCP_Public.showNotification('Error: ' + response.data, 'error');
                    }
                    
                    // Clear current request
                    WPMCP_Public.currentConsentRequest = null;
                },
                error: function() {
                    // Hide the modal
                    WPMCP_Public.hideConsentModal();
                    
                    // Show error notification
                    WPMCP_Public.showNotification('Failed to process consent response', 'error');
                    
                    // Clear current request
                    WPMCP_Public.currentConsentRequest = null;
                }
            });
        },
        
        /**
         * Show notification
         * 
         * @param {string} message Notification message
         * @param {string} type Notification type (success, error)
         */
        showNotification: function(message, type) {
            $('#wpmcp-notification-message').text(message);
            $('#wpmcp-consent-notification')
                .removeClass('success error')
                .addClass(type)
                .show();
            
            // Auto-hide after 5 seconds
            setTimeout(function() {
                WPMCP_Public.hideNotification();
            }, 5000);
        },
        
        /**
         * Hide notification
         */
        hideNotification: function() {
            $('#wpmcp-consent-notification').fadeOut();
        }
    };
    
    // Initialize on document ready
    $(document).ready(function() {
        WPMCP_Public.init();
    });
    
    // Make showConsentModal available globally
    window.showWpmcpConsentModal = function(data) {
        WPMCP_Public.showConsentModal(data);
    };

})(jQuery);

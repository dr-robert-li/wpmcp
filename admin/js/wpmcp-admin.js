/**
 * Admin JavaScript for WPMCP plugin.
 *
 * @since      2.0.0
 * @package    WPMCP
 * @subpackage WPMCP/admin/js
 */

(function($) {
    'use strict';

    /**
     * Initialize all admin functionality when DOM is ready
     */
    $(document).ready(function() {
        // Initialize tabs
        initTabs();
        
        // Initialize API key generation
        initApiKeyGeneration();
        
        // Initialize test connection button
        initTestConnection();
        
        // Initialize consent logs functionality
        initConsentLogs();
        
        // Initialize resource subscription management
        initResourceSubscriptions();
    });

    /**
     * Initialize tabbed interface
     */
    function initTabs() {
        $('.wpmcp-tabs-nav a').on('click', function(e) {
            e.preventDefault();
            
            // Remove active class from all tabs
            $('.wpmcp-tabs-nav a').removeClass('nav-tab-active');
            $('.wpmcp-tab-content').removeClass('active');
            
            // Add active class to current tab
            $(this).addClass('nav-tab-active');
            
            // Show the corresponding tab content
            var target = $(this).attr('href');
            $(target).addClass('active');
            
            // Store the active tab in localStorage
            localStorage.setItem('wpmcp_active_tab', target);
        });
        
        // Restore active tab from localStorage if available
        var activeTab = localStorage.getItem('wpmcp_active_tab');
        if (activeTab && $(activeTab).length) {
            $('.wpmcp-tabs-nav a[href="' + activeTab + '"]').trigger('click');
        } else {
            // Default to first tab
            $('.wpmcp-tabs-nav a:first').trigger('click');
        }
    }

    /**
     * Initialize API key generation
     */
    function initApiKeyGeneration() {
        $('#generate-api-key').on('click', function(e) {
            e.preventDefault();
            
            // Generate a random API key
            var apiKey = generateRandomString(32);
            
            // Set the value in the input field
            $('input[name="wpmcp_api_key"]').val(apiKey);
        });
    }

    /**
     * Generate a random string of specified length
     */
    function generateRandomString(length) {
        var chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        var result = '';
        
        for (var i = 0; i < length; i++) {
            result += chars.charAt(Math.floor(Math.random() * chars.length));
        }
        
        return result;
    }

    /**
     * Initialize test connection button
     */
    function initTestConnection() {
        $('#test-connection').on('click', function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var $resultContainer = $('#connection-test-result');
            
            // Disable button and show loading state
            $button.prop('disabled', true).text('Testing...');
            $resultContainer.html('<span class="spinner is-active"></span> Testing connection...').removeClass('error success').addClass('testing');
            
            // Make AJAX request to test connection
            $.ajax({
                url: wpmcp_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'wpmcp_test_connection',
                    nonce: wpmcp_admin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $resultContainer.html('<span class="dashicons dashicons-yes"></span> ' + response.data).removeClass('testing error').addClass('success');
                    } else {
                        $resultContainer.html('<span class="dashicons dashicons-no"></span> Error: ' + response.data).removeClass('testing success').addClass('error');
                    }
                },
                error: function(xhr, status, error) {
                    $resultContainer.html('<span class="dashicons dashicons-no"></span> Error: ' + error).removeClass('testing success').addClass('error');
                },
                complete: function() {
                    // Re-enable button
                    $button.prop('disabled', false).text('Test Connection');
                }
            });
        });
    }

    /**
     * Initialize consent logs functionality
     */
    function initConsentLogs() {
        // Load consent logs on tab activation
        $('.wpmcp-tabs-nav a[href="#tab-consent"]').on('click', function() {
            loadConsentLogs();
        });
        
        // Handle clear logs button
        $('#clear-consent-logs').on('click', function(e) {
            e.preventDefault();
            
            if (confirm('Are you sure you want to clear all consent logs? This action cannot be undone.')) {
                clearConsentLogs();
            }
        });
        
        // Handle refresh logs button
        $('#refresh-consent-logs').on('click', function(e) {
            e.preventDefault();
            loadConsentLogs();
        });
    }

    /**
     * Load consent logs via AJAX
     */
    function loadConsentLogs() {
        var $logsContainer = $('#consent-logs-container');
        
        // Show loading state
        $logsContainer.html('<div class="spinner is-active"></div> Loading logs...');
        
        // Make AJAX request to get logs
        $.ajax({
            url: wpmcp_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'wpmcp_get_consent_logs',
                nonce: wpmcp_admin.nonce,
                page: 1,
                per_page: 50
            },
            success: function(response) {
                if (response.success) {
                    if (response.data.logs && response.data.logs.length > 0) {
                        // Render logs table
                        var html = '<table class="widefat striped">';
                        html += '<thead><tr>';
                        html += '<th>Date</th>';
                        html += '<th>User</th>';
                        html += '<th>Tool</th>';
                        html += '<th>Status</th>';
                        html += '<th>Arguments</th>';
                        html += '</tr></thead>';
                        html += '<tbody>';
                        
                        $.each(response.data.logs, function(index, log) {
                            html += '<tr>';
                            html += '<td>' + formatDate(log.timestamp) + '</td>';
                            html += '<td>' + (log.user_name || 'Unknown') + '</td>';
                            html += '<td>' + log.tool + '</td>';
                            html += '<td>' + getStatusBadge(log.status) + '</td>';
                            html += '<td><pre>' + JSON.stringify(log.arguments, null, 2) + '</pre></td>';
                            html += '</tr>';
                        });
                        
                        html += '</tbody></table>';
                        
                        // Add pagination if needed
                        if (response.data.total_pages > 1) {
                            html += '<div class="tablenav"><div class="tablenav-pages">';
                            html += '<span class="displaying-num">' + response.data.total_items + ' items</span>';
                            html += '<span class="pagination-links">';
                            
                            // Add pagination controls
                            for (var i = 1; i <= response.data.total_pages; i++) {
                                if (i === 1) {
                                    html += '<span class="tablenav-pages-navspan button current">' + i + '</span>';
                                } else {
                                    html += '<a class="page-numbers" href="#" data-page="' + i + '">' + i + '</a>';
                                }
                            }
                            
                            html += '</span></div></div>';
                        }
                        
                        $logsContainer.html(html);
                        
                        // Add pagination event handlers
                        $logsContainer.find('.page-numbers').on('click', function(e) {
                            e.preventDefault();
                            var page = $(this).data('page');
                            loadConsentLogsPage(page);
                        });
                    } else {
                        $logsContainer.html('<p>No consent logs found.</p>');
                    }
                } else {
                    $logsContainer.html('<p class="error">Error loading logs: ' + response.data + '</p>');
                }
            },
            error: function(xhr, status, error) {
                $logsContainer.html('<p class="error">Error loading logs: ' + error + '</p>');
            }
        });
    }

    /**
     * Load a specific page of consent logs
     */
    function loadConsentLogsPage(page) {
        var $logsContainer = $('#consent-logs-container');
        
        // Show loading state
        $logsContainer.html('<div class="spinner is-active"></div> Loading logs...');
        
        // Make AJAX request to get logs
        $.ajax({
            url: wpmcp_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'wpmcp_get_consent_logs',
                nonce: wpmcp_admin.nonce,
                page: page,
                per_page: 50
            },
            success: function(response) {
                // Same rendering logic as loadConsentLogs
                if (response.success) {
                    // Render logs (same code as in loadConsentLogs)
                    // ...
                }
            },
            error: function(xhr, status, error) {
                $logsContainer.html('<p class="error">Error loading logs: ' + error + '</p>');
            }
        });
    }

    /**
     * Clear all consent logs
     */
    function clearConsentLogs() {
        var $logsContainer = $('#consent-logs-container');
        
        // Show loading state
        $logsContainer.html('<div class="spinner is-active"></div> Clearing logs...');
        
        // Make AJAX request to clear logs
        $.ajax({
            url: wpmcp_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'wpmcp_clear_consent_logs',
                nonce: wpmcp_admin.nonce
            },
            success: function(response) {
                if (response.success) {
                    $logsContainer.html('<p class="success">All consent logs have been cleared.</p>');
                } else {
                    $logsContainer.html('<p class="error">Error clearing logs: ' + response.data + '</p>');
                }
            },
            error: function(xhr, status, error) {
                $logsContainer.html('<p class="error">Error clearing logs: ' + error + '</p>');
            }
        });
    }

    /**
     * Initialize resource subscription management
     */
    function initResourceSubscriptions() {
        // Load subscriptions on tab activation
        $('.wpmcp-tabs-nav a[href="#tab-subscriptions"]').on('click', function() {
            loadResourceSubscriptions();
        });
    }

    /**
     * Load resource subscriptions
     */
    function loadResourceSubscriptions() {
        var $subscriptionsContainer = $('#resource-subscriptions-container');
        
        // Show loading state
        $subscriptionsContainer.html('<div class="spinner is-active"></div> Loading subscriptions...');
        
        // Get subscriptions from WordPress options via AJAX
        // This would typically be a custom endpoint, but for simplicity we'll use the wp_call_endpoint
        $.ajax({
            url: wpmcp_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'wp_ajax_wpmcp_get_subscriptions',
                nonce: wpmcp_admin.nonce
            },
            success: function(response) {
                if (response.success) {
                    var subscriptions = response.data;
                    
                    if (subscriptions && subscriptions.length > 0) {
                        // Render subscriptions table
                        var html = '<table class="widefat striped">';
                        html += '<thead><tr>';
                        html += '<th>Resource URI</th>';
                        html += '<th>Actions</th>';
                        html += '</tr></thead>';
                        html += '<tbody>';
                        
                        $.each(subscriptions, function(index, uri) {
                            html += '<tr>';
                            html += '<td>' + uri + '</td>';
                            html += '<td><button class="button button-small unsubscribe-resource" data-uri="' + uri + '">Unsubscribe</button></td>';
                            html += '</tr>';
                        });
                        
                        html += '</tbody></table>';
                        $subscriptionsContainer.html(html);
                        
                        // Add unsubscribe event handlers
                        $('.unsubscribe-resource').on('click', function(e) {
                            e.preventDefault();
                            var uri = $(this).data('uri');
                            unsubscribeResource(uri);
                        });
                    } else {
                        $subscriptionsContainer.html('<p>No resource subscriptions found.</p>');
                    }
                } else {
                    $subscriptionsContainer.html('<p class="error">Error loading subscriptions: ' + response.data + '</p>');
                }
            },
            error: function(xhr, status, error) {
                $subscriptionsContainer.html('<p class="error">Error loading subscriptions: ' + error + '</p>');
            }
        });
    }

    /**
     * Unsubscribe from a resource
     */
    function unsubscribeResource(uri) {
        if (confirm('Are you sure you want to unsubscribe from this resource?')) {
            $.ajax({
                url: wpmcp_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'wpmcp_unsubscribe_resource',
                    nonce: wpmcp_admin.nonce,
                    uri: uri
                },
                success: function(response) {
                    if (response.success) {
                        // Reload subscriptions
                        loadResourceSubscriptions();
                    } else {
                        alert('Error: ' + response.data);
                    }
                },
                error: function(xhr, status, error) {
                    alert('Error: ' + error);
                }
            });
        }
    }

    /**
     * Format a date string
     */
    function formatDate(dateString) {
        var date = new Date(dateString);
        return date.toLocaleString();
    }

    /**
     * Get a status badge HTML
     */
    function getStatusBadge(status) {
        var classes = '';
        
        switch (status) {
            case 'approved':
                classes = 'status-approved';
                break;
            case 'denied':
                classes = 'status-denied';
                break;
            case 'pending':
                classes = 'status-pending';
                break;
            default:
                classes = '';
        }
        
        return '<span class="status-badge ' + classes + '">' + status + '</span>';
    }

})(jQuery);

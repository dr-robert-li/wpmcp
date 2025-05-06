/**
 * MCP Client for WordPress
 * Implements the Model Context Protocol for interacting with WordPress sites
 */

import fs from 'fs';
import { HttpTransport } from './transports/http.js';
import { SseTransport } from './transports/sse.js';

export class MCPClient {
  /**
   * Create a new MCP client
   * @param {Object} options - Client options
   * @param {string} options.configFile - Path to the configuration file
   * @param {Object} options.config - Direct configuration object (alternative to configFile)
   */
  constructor(options = {}) {
    this.config = null;
    this.httpTransport = null;
    this.sseTransport = null;
    this.serverCapabilities = null;
    this.toolsDescription = null;
    this.initialized = false;
    
    // Load configuration
    if (options.configFile) {
      this._loadConfigFromFile(options.configFile);
    } else if (options.config) {
      this.config = options.config;
    } else {
      throw new Error('Either configFile or config must be provided');
    }
    
    // Initialize HTTP transport
    if (this.config.transports && this.config.transports.http) {
      this.httpTransport = new HttpTransport(this.config.transports.http);
    } else {
      throw new Error('HTTP transport configuration is required');
    }
    
    // Initialize SSE transport if configured
    if (this.config.transports && this.config.transports.sse) {
      this.sseTransport = new SseTransport(this.config.transports.sse);
    }
  }
  
  /**
   * Load configuration from a file
   * @param {string} filePath - Path to the configuration file
   * @private
   */
  _loadConfigFromFile(filePath) {
    try {
      const configData = fs.readFileSync(filePath, 'utf8');
      this.config = JSON.parse(configData);
    } catch (error) {
      throw new Error(`Failed to load configuration file: ${error.message}`);
    }
  }
  
  /**
   * Initialize the client
   * @returns {Promise<Object>} - Server capabilities
   */
  async initialize() {
    if (this.initialized) {
      return this.serverCapabilities;
    }
    
    // Define client capabilities
    const clientCapabilities = {
      protocolVersion: '2025-04-30',
      clientInfo: {
        name: 'A2A-MCP WordPress Client',
        version: '1.0.0'
      },
      transports: {
        http: true,
        sse: !!this.sseTransport
      }
    };
    
    // Initialize HTTP transport
    this.serverCapabilities = await this.httpTransport.initialize(clientCapabilities);
    
    // Get tool descriptions
    this.toolsDescription = await this.httpTransport.describeTools();
    
    this.initialized = true;
    return this.serverCapabilities;
  }
  
  /**
   * Connect to the SSE transport if available
   * @returns {Promise<Object>} - Connection result or null if SSE not available
   */
  async connectSSE() {
    if (!this.sseTransport) {
      console.warn('SSE transport not configured');
      return null;
    }
    
    return await this.sseTransport.connect();
  }
  
  /**
   * Add an event listener for SSE events
   * @param {string} event - Event type to listen for
   * @param {Function} callback - Callback function
   */
  onSSEEvent(event, callback) {
    if (!this.sseTransport) {
      console.warn('SSE transport not configured');
      return;
    }
    
    this.sseTransport.addEventListener(event, callback);
  }
  
  /**
   * Close the SSE connection
   */
  closeSSE() {
    if (this.sseTransport) {
      this.sseTransport.close();
    }
  }
  
  /**
   * Execute a WordPress MCP tool
   * @param {string} toolName - The tool name to execute
   * @param {Object} toolArgs - Arguments for the tool
   * @returns {Promise<Object>} - The tool execution result
   */
  async executeTool(toolName, toolArgs = {}) {
    if (!this.initialized) {
      await this.initialize();
    }
    
    return await this.httpTransport.executeTool(toolName, toolArgs);
  }
  
  /**
   * Discover available WordPress REST API endpoints
   * @returns {Promise<Object>} - The available endpoints
   */
  async discoverEndpoints() {
    return await this.executeTool('wp_discover_endpoints');
  }
  
  /**
   * Call a specific WordPress REST API endpoint
   * @param {string} endpoint - The endpoint path
   * @param {string} method - The HTTP method to use
   * @param {Object} params - Request parameters
   * @returns {Promise<Object>} - The endpoint response
   */
  async callEndpoint(endpoint, method = 'GET', params = {}) {
    return await this.executeTool('wp_call_endpoint', {
      endpoint,
      method,
      params
    });
  }
  
  /**
   * Get a WordPress resource by URI
   * @param {string} uri - The resource URI
   * @returns {Promise<Object>} - The resource
   */
  async getResource(uri) {
    return await this.executeTool('wp_get_resource', { uri });
  }
  
  /**
   * Get recent WordPress posts
   * @param {Object} options - Post query options
   * @param {number} options.per_page - Number of posts to retrieve
   * @param {string} options.orderby - Order by field
   * @param {string} options.order - Order direction ('asc' or 'desc')
   * @returns {Promise<Object>} - The posts
   */
  async getPosts(options = { per_page: 5, orderby: 'date', order: 'desc' }) {
    return await this.callEndpoint('/wp/v2/posts', 'GET', options);
  }
  
  /**
   * Create a new WordPress post
   * @param {string} title - Post title
   * @param {string} content - Post content
   * @param {string} status - Post status ('publish', 'draft', etc.)
   * @returns {Promise<Object>} - The created post
   */
  async createPost(title, content, status = 'draft') {
    return await this.callEndpoint('/wp/v2/posts', 'POST', {
      title,
      content,
      status
    });
  }
  
  /**
   * Update an existing WordPress post
   * @param {number} postId - The post ID
   * @param {Object} updates - Updates to apply
   * @returns {Promise<Object>} - The updated post
   */
  async updatePost(postId, updates) {
    return await this.callEndpoint(`/wp/v2/posts/${postId}`, 'PUT', updates);
  }
  
  /**
   * Delete a WordPress post
   * @param {number} postId - The post ID
   * @returns {Promise<Object>} - The deletion result
   */
  async deletePost(postId) {
    return await this.callEndpoint(`/wp/v2/posts/${postId}`, 'DELETE');
  }
  
  /**
   * Get WordPress categories
   * @param {Object} options - Category query options
   * @returns {Promise<Object>} - The categories
   */
  async getCategories(options = {}) {
    return await this.callEndpoint('/wp/v2/categories', 'GET', options);
  }
  
  /**
   * Get WordPress tags
   * @param {Object} options - Tag query options
   * @returns {Promise<Object>} - The tags
   */
  async getTags(options = {}) {
    return await this.callEndpoint('/wp/v2/tags', 'GET', options);
  }
  
  /**
   * Get site information
   * @returns {Promise<Object>} - The site information
   */
  async getSiteInfo() {
    return await this.callEndpoint('/', 'GET');
  }
}

export default MCPClient;
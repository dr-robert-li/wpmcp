/**
 * HTTP Transport for MCP Client
 * Handles HTTP communication with MCP servers
 */

import fetch from 'node-fetch';

export class HttpTransport {
  /**
   * Create a new HTTP transport
   * @param {Object} config - Configuration object
   * @param {string} config.url - The MCP server URL
   * @param {Object} config.headers - Headers to include in requests
   */
  constructor(config) {
    this.url = config.url;
    this.headers = config.headers || {};
    this.requestCounter = 1;
  }

  /**
   * Send a request to the MCP server
   * @param {string} method - The MCP method to call
   * @param {string} toolName - The tool name to execute
   * @param {Object} toolArgs - Arguments for the tool
   * @returns {Promise<Object>} - The JSON-RPC response
   */
  async sendRequest(method, toolName, toolArgs = {}) {
    const requestId = this.requestCounter++;
    
    try {
      const response = await fetch(this.url, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          ...this.headers
        },
        body: JSON.stringify({
          jsonrpc: '2.0',
          id: requestId,
          method: method,
          params: toolName ? (() => {
            const params = { name: toolName };
            params['arguments'] = toolArgs;
            return params;
          })() : undefined
        })
      });
      
      if (!response.ok) {
        throw new Error(`HTTP error! Status: ${response.status}`);
      }
      
      return await response.json();
    } catch (error) {
      console.error('Error sending request:', error);
      throw error;
    }
  }
  
  /**
   * Execute a tool on the MCP server
   * @param {string} toolName - The tool name to execute
   * @param {Object} toolArgs - Arguments for the tool
   * @returns {Promise<Object>} - The tool execution result
   */
  async executeTool(toolName, toolArgs = {}) {
    const response = await this.sendRequest('toolCall', toolName, toolArgs);
    
    if (response.error) {
      throw new Error(`Tool execution error: ${JSON.stringify(response.error)}`);
    }
    
    return response.result;
  }
  
  /**
   * Initialize the client with the server capabilities
   * @param {Object} clientCapabilities - The client capabilities
   * @returns {Promise<Object>} - The server capabilities
   */
  async initialize(clientCapabilities = {}) {
    // Make a direct request without using toolName
    const requestId = this.requestCounter++;
    
    try {
      const response = await fetch(this.url, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          ...this.headers
        },
        body: JSON.stringify({
          jsonrpc: '2.0',
          id: requestId,
          method: 'initialize',
          params: { clientCapabilities }
        })
      });
      
      if (!response.ok) {
        throw new Error(`HTTP error! Status: ${response.status}`);
      }
      
      const data = await response.json();
      
      if (data.error) {
        throw new Error(`Initialization error: ${JSON.stringify(data.error)}`);
      }
      
      return data.result;
    } catch (error) {
      console.error('Error initializing:', error);
      throw error;
    }
  }
  
  /**
   * Get the description of available tools
   * @returns {Promise<Object>} - The tools description
   */
  async describeTools() {
    const requestId = this.requestCounter++;
    
    try {
      const response = await fetch(this.url, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          ...this.headers
        },
        body: JSON.stringify({
          jsonrpc: '2.0',
          id: requestId,
          method: 'describeTools',
          params: {}
        })
      });
      
      if (!response.ok) {
        throw new Error(`HTTP error! Status: ${response.status}`);
      }
      
      const data = await response.json();
      
      if (data.error) {
        throw new Error(`Error describing tools: ${JSON.stringify(data.error)}`);
      }
      
      return data.result;
    } catch (error) {
      console.error('Error describing tools:', error);
      throw error;
    }
  }
  
  /**
   * Discover available resources
   * @returns {Promise<Object>} - The available resources
   */
  async discoverResources() {
    const response = await this.sendRequest('discoverResources');
    
    if (response.error) {
      throw new Error(`Error discovering resources: ${JSON.stringify(response.error)}`);
    }
    
    return response.result;
  }
  
  /**
   * Get a resource by URI
   * @param {string} uri - The resource URI
   * @returns {Promise<Object>} - The resource
   */
  async getResource(uri) {
    const response = await this.sendRequest('getResource', null, { uri });
    
    if (response.error) {
      throw new Error(`Error getting resource: ${JSON.stringify(response.error)}`);
    }
    
    return response.result;
  }
}

export default HttpTransport;
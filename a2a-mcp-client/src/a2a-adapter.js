/**
 * A2A Adapter for MCP Client
 * 
 * This adapter allows the MCP client to be used with the A2A protocol,
 * enabling seamless integration with other A2A-enabled agents and systems.
 */

import { MCPClient } from './client.js';
import fs from 'fs';
import path from 'path';

export class A2AMCPAdapter {
  /**
   * Create a new A2A adapter for the MCP client
   * @param {Object} options - Adapter options
   * @param {MCPClient} options.client - The MCP client instance
   * @param {string} options.agentCardPath - Path to store the agent card
   */
  constructor(options = {}) {
    if (!options.client || !(options.client instanceof MCPClient)) {
      throw new Error('Valid MCP client instance is required');
    }
    
    this.client = options.client;
    this.agentCardPath = options.agentCardPath || './.well-known/agent.json';
    this.taskStore = new Map(); // Store for ongoing tasks
    this.requestCounter = 1;
  }
  
  /**
   * Initialize the adapter
   * @returns {Promise<void>}
   */
  async initialize() {
    // Initialize the MCP client if not already done
    if (!this.client.initialized) {
      await this.client.initialize();
    }
    
    // Generate and save the agent card
    await this.generateAgentCard();
  }
  
  /**
   * Generate and save the agent card for A2A discovery
   * @returns {Promise<Object>} - The agent card object
   */
  async generateAgentCard() {
    // Create agent card based on client capabilities
    const agentCard = {
      agentId: 'wordpress-mcp-agent',
      displayName: 'WordPress MCP Agent',
      description: 'An agent for interacting with WordPress sites using the Model Context Protocol',
      iconUrl: null,
      isBot: true,
      apiEndpoint: '/a2a',
      apiFormat: 'json-rpc',
      apiAuth: {
        type: 'apiKey',
        headerName: 'x-api-key'
      },
      skills: [
        {
          name: 'wordpress-content-management',
          displayName: 'WordPress Content Management',
          description: 'Create, read, update, and delete WordPress content',
          examples: [
            'Create a new blog post',
            'List recent posts',
            'Update page content',
            'Manage categories and tags'
          ]
        },
        {
          name: 'wordpress-site-management',
          displayName: 'WordPress Site Management',
          description: 'Manage WordPress site settings and configuration',
          examples: [
            'Get site information',
            'Discover available endpoints',
            'Access WordPress resources'
          ]
        }
      ],
      supportedUXModes: ['text'],
      supportedTasks: ['query', 'execution'],
      serverCapabilities: {
        streaming: true,
        pushNotifications: false
      },
      status: 'online',
      version: '1.0.0',
      contact: null
    };
    
    // Ensure directory exists
    const dir = path.dirname(this.agentCardPath);
    if (!fs.existsSync(dir)) {
      fs.mkdirSync(dir, { recursive: true });
    }
    
    // Save the agent card
    fs.writeFileSync(this.agentCardPath, JSON.stringify(agentCard, null, 2));
    
    return agentCard;
  }
  
  /**
   * Handle an A2A JSON-RPC request
   * @param {Object} request - The JSON-RPC request
   * @returns {Promise<Object>} - The JSON-RPC response
   */
  async handleRequest(request) {
    try {
      // Basic validation for JSON-RPC 2.0
      if (!request || !request.jsonrpc || request.jsonrpc !== '2.0') {
        return this._createJsonRpcError(-32600, 'Invalid Request', request.id);
      }
      
      // Check for method
      if (!request.method) {
        return this._createJsonRpcError(-32600, 'Method is required', request.id);
      }
      
      // Process request based on method
      switch (request.method) {
        case 'tasks/send':
          return await this._handleTaskSend(request);
          
        case 'tasks/sendSubscribe':
          return await this._handleTaskSendSubscribe(request);
          
        case 'tasks/get':
          return await this._handleTaskGet(request);
          
        case 'tasks/cancel':
          return await this._handleTaskCancel(request);
          
        case 'agent/getInfo':
          return await this._handleAgentGetInfo(request);
          
        default:
          return this._createJsonRpcError(-32601, `Method not found: ${request.method}`, request.id);
      }
    } catch (error) {
      console.error('Error handling A2A request:', error);
      return this._createJsonRpcError(-32603, `Internal error: ${error.message}`, request.id);
    }
  }
  
  /**
   * Handle tasks/send request
   * @param {Object} request - The JSON-RPC request
   * @returns {Promise<Object>} - The JSON-RPC response
   * @private
   */
  async _handleTaskSend(request) {
    const params = request.params || {};
    
    // Validate required parameters
    if (!params.id || !params.message || !params.message.role || !params.message.parts) {
      return this._createJsonRpcError(-32602, 'Invalid params: missing required fields', request.id);
    }
    
    // Create or update task
    const taskId = params.id;
    let task = this.taskStore.get(taskId);
    
    if (!task) {
      // Create new task
      task = {
        id: taskId,
        status: 'submitted',
        messages: [],
        createdAt: new Date().toISOString(),
        updatedAt: new Date().toISOString(),
        artifacts: []
      };
      this.taskStore.set(taskId, task);
    }
    
    // Add message to task
    task.messages.push(params.message);
    task.status = 'working';
    task.updatedAt = new Date().toISOString();
    
    // Process the message
    const result = await this._processMessage(task, params.message);
    
    // Update task status
    task.status = 'completed';
    task.updatedAt = new Date().toISOString();
    
    // Add agent response to messages
    task.messages.push({
      role: 'agent',
      parts: [
        {
          type: 'text',
          text: result.text
        }
      ]
    });
    
    // Add any artifacts
    if (result.artifacts && result.artifacts.length > 0) {
      task.artifacts = [...task.artifacts, ...result.artifacts];
    }
    
    // Return response
    return this._createJsonRpcResponse(request.id, { task });
  }
  
  /**
   * Handle tasks/sendSubscribe request
   * @param {Object} request - The JSON-RPC request
   * @returns {Promise<Object>} - The JSON-RPC response with SSE configuration
   * @private
   */
  async _handleTaskSendSubscribe(request) {
    const params = request.params || {};
    
    // Validate required parameters
    if (!params.id || !params.message || !params.message.role || !params.message.parts) {
      return this._createJsonRpcError(-32602, 'Invalid params: missing required fields', request.id);
    }
    
    // Create or update task
    const taskId = params.id;
    let task = this.taskStore.get(taskId);
    
    if (!task) {
      // Create new task
      task = {
        id: taskId,
        status: 'submitted',
        messages: [],
        createdAt: new Date().toISOString(),
        updatedAt: new Date().toISOString(),
        artifacts: []
      };
      this.taskStore.set(taskId, task);
    }
    
    // Add message to task
    task.messages.push(params.message);
    task.status = 'working';
    task.updatedAt = new Date().toISOString();
    
    // Set up SSE connection
    // This would typically be handled by the server framework (Express, etc.)
    // For now, we'll just return a dummy SSE configuration
    
    // Process the message asynchronously
    setTimeout(async () => {
      try {
        const result = await this._processMessage(task, params.message);
        
        // Update task status
        task.status = 'completed';
        task.updatedAt = new Date().toISOString();
        
        // Add agent response to messages
        task.messages.push({
          role: 'agent',
          parts: [
            {
              type: 'text',
              text: result.text
            }
          ]
        });
        
        // Add any artifacts
        if (result.artifacts && result.artifacts.length > 0) {
          task.artifacts = [...task.artifacts, ...result.artifacts];
        }
        
        // SSE would emit these events
        console.log(`Task ${taskId} completed`);
      } catch (error) {
        task.status = 'failed';
        task.error = {
          code: 'processing_error',
          message: error.message
        };
        console.error(`Task ${taskId} failed:`, error);
      }
    }, 0);
    
    // Return immediate response to start SSE connection
    return this._createJsonRpcResponse(request.id, {
      sseConfiguration: {
        url: `/a2a/tasks/${taskId}/events`,
        headers: {}
      }
    });
  }
  
  /**
   * Handle tasks/get request
   * @param {Object} request - The JSON-RPC request
   * @returns {Promise<Object>} - The JSON-RPC response
   * @private
   */
  async _handleTaskGet(request) {
    const params = request.params || {};
    
    // Validate required parameters
    if (!params.id) {
      return this._createJsonRpcError(-32602, 'Invalid params: task id is required', request.id);
    }
    
    // Get task
    const task = this.taskStore.get(params.id);
    
    if (!task) {
      return this._createJsonRpcError(404, 'Task not found', request.id);
    }
    
    // Return response
    return this._createJsonRpcResponse(request.id, { task });
  }
  
  /**
   * Handle tasks/cancel request
   * @param {Object} request - The JSON-RPC request
   * @returns {Promise<Object>} - The JSON-RPC response
   * @private
   */
  async _handleTaskCancel(request) {
    const params = request.params || {};
    
    // Validate required parameters
    if (!params.id) {
      return this._createJsonRpcError(-32602, 'Invalid params: task id is required', request.id);
    }
    
    // Get task
    const task = this.taskStore.get(params.id);
    
    if (!task) {
      return this._createJsonRpcError(404, 'Task not found', request.id);
    }
    
    // Cancel task
    task.status = 'canceled';
    task.updatedAt = new Date().toISOString();
    
    // Return response
    return this._createJsonRpcResponse(request.id, { task });
  }
  
  /**
   * Handle agent/getInfo request
   * @param {Object} request - The JSON-RPC request
   * @returns {Promise<Object>} - The JSON-RPC response
   * @private
   */
  async _handleAgentGetInfo(request) {
    // Get agent card
    const agentCard = JSON.parse(fs.readFileSync(this.agentCardPath, 'utf8'));
    
    // Return response
    return this._createJsonRpcResponse(request.id, {
      agentInfo: agentCard
    });
  }
  
  /**
   * Process a message using the MCP client
   * @param {Object} task - The task object
   * @param {Object} message - The message to process
   * @returns {Promise<Object>} - The processing result
   * @private
   */
  async _processMessage(task, message) {
    // Extract message text
    const textPart = message.parts.find(part => part.type === 'text');
    if (!textPart || !textPart.text) {
      throw new Error('No text content in message');
    }
    
    const text = textPart.text;
    
    // Add debugging
    console.log('Processing message text:', text);
    
    // Parse the message to determine what WordPress operation to perform
    const lowerText = text.toLowerCase();
    console.log('Lowercase text:', lowerText);
    
    // Helper function to extract post creation params from any reasonable format
    const extractPostParams = (text) => {
      console.log('Attempting to extract post parameters from:', text);
      
      // Initialize with default values
      let title = 'Untitled Post';
      let content = 'No content provided.';
      let status = 'draft';
      
      // Try multiple approaches to extract the title
      const titlePatterns = [
        // Quoted title patterns
        /title\s*["']([^"']*)["']/i,
        /title[:]?\s*["']([^"']*)["']/i,
        // Title with colon
        /title[:]?\s*([^,"'\n]*?)(?:,|\s+content|\s+status|$)/i,
        // Title after "post" or "create post" 
        /(?:post|create post|new post)\s+(?:titled|called|named)?\s*["']?([^"',\n]*)["']?/i,
        // Post about X
        /post\s+about\s+["']?([^"',\n]*)["']?/i,
        // Simple extraction - everything between post and content
        /(?:create|new)\s+post\s+(.*?)(?:\s+content|\s+with content|$)/i
      ];
      
      // Try to extract title
      for (const pattern of titlePatterns) {
        const match = text.match(pattern);
        if (match && match[1] && match[1].trim()) {
          title = match[1].trim();
          console.log('Found title using pattern:', pattern.toString(), title);
          break;
        }
      }
      
      // Try to extract content
      const contentPatterns = [
        // Quoted content patterns
        /content\s*["']([^"']*)["']/i,
        /content[:]?\s*["']([^"']*)["']/i,
        // Content with colon
        /content[:]?\s*([^,"'\n]*?)(?:,|\s+status|$)/i,
        // Content after "saying"
        /saying\s+["']?([^"',\n]*)["']?/i,
        // Body as synonym for content
        /body\s*["']?([^"',\n]*)["']?/i,
        /body[:]?\s*["']?([^"',\n]*)["']?/i
      ];
      
      // Try to extract content
      for (const pattern of contentPatterns) {
        const match = text.match(pattern);
        if (match && match[1] && match[1].trim()) {
          content = match[1].trim();
          console.log('Found content using pattern:', pattern.toString(), content);
          break;
        }
      }
      
      // Try to extract status
      const statusPatterns = [
        // Quoted status patterns
        /status\s*["']([^"']*)["']/i,
        /status[:]?\s*["']([^"']*)["']/i,
        // Status with colon
        /status[:]?\s*([^,"'\n]*?)(?:,|$)/i,
        // "as" for status
        /as\s+["']?([a-z]+)["']?/i,
        // published/draft direct mentions
        /\s(publish|draft|pending|private)\b/i
      ];
      
      // Try to extract status
      for (const pattern of statusPatterns) {
        const match = text.match(pattern);
        if (match && match[1] && match[1].trim()) {
          const possibleStatus = match[1].toLowerCase().trim();
          if (['publish', 'draft', 'pending', 'private'].includes(possibleStatus)) {
            status = possibleStatus;
            console.log('Found valid status:', status);
            break;
          }
        }
      }
      
      // Final validation/cleanup
      if (!title || title.trim() === '') title = 'Untitled Post';
      if (!content || content.trim() === '') content = 'No content provided.';
      if (!status || !['publish', 'draft', 'pending', 'private'].includes(status.toLowerCase())) status = 'draft';
      
      return { title, content, status };
    };
    
    let result = '';
    const artifacts = [];
    
    try {
      // Check for various WordPress operations
      if (lowerText.includes('list posts') || lowerText.includes('show posts')) {
        console.log('Matched list/show posts pattern');
        const count = lowerText.match(/(\d+)\s+posts/) ? parseInt(lowerText.match(/(\d+)\s+posts/)[1]) : 5;
        const postsResponse = await this.client.getPosts({ per_page: count });
        
        result = `Here are the ${postsResponse.data.length} most recent posts:\n\n`;
        
        postsResponse.data.forEach(post => {
          result += `- ${post.title.rendered} (ID: ${post.id})\n`;
          if (post.link) {
            result += `  URL: ${post.link}\n`;
          }
          result += `  Status: ${post.status}\n`;
          result += `  Date: ${new Date(post.date).toLocaleString()}\n\n`;
        });
        
        artifacts.push({
          id: `posts-${Date.now()}`,
          type: 'application/json',
          title: 'WordPress Posts',
          description: 'Recent posts from WordPress site',
          parts: [
            {
              type: 'data',
              data: {
                contentType: 'application/json',
                content: JSON.stringify(postsResponse.data)
              }
            }
          ]
        });
      } 
      else if (lowerText.match(/get\s+post\s+(\d+)/i) || lowerText.match(/show\s+post\s+(\d+)/i)) {
        console.log('Matched get/show post pattern');
        const postIdMatch = lowerText.match(/post\s+(\d+)/i);
        const postId = postIdMatch ? postIdMatch[1] : null;
        
        if (!postId) {
          result = "I couldn't determine which post you're looking for. Please specify a post ID.";
        } else {
          const postResponse = await this.client.callEndpoint(`/wp/v2/posts/${postId}`, 'GET');
          
          result = `Title: ${postResponse.data.title.rendered}\n\n`;
          if (postResponse.data.link) {
            result += `URL: ${postResponse.data.link}\n`;
          }
          result += `Status: ${postResponse.data.status}\n`;
          result += `Date: ${new Date(postResponse.data.date).toLocaleString()}\n\n`;
          result += `Content:\n${postResponse.data.content.rendered.replace(/<[^>]*>/g, '')}\n`;
          
          artifacts.push({
            id: `post-${postId}-${Date.now()}`,
            type: 'application/json',
            title: `WordPress Post ${postId}`,
            description: 'Post content from WordPress site',
            parts: [
              {
                type: 'data',
                data: {
                  contentType: 'application/json',
                  content: JSON.stringify(postResponse.data)
                }
              }
            ]
          });
        }
      }
      // SPECIAL CASE FOR A2A - Directly match "Create post with title ... content ... status ..." pattern
      else if (lowerText.match(/create\s+post\s+with\s+title/i) && lowerText.includes('content') && lowerText.includes('status')) {
        console.log('Matched A2A specific post creation pattern!');
        
        // Extract title, content and status using regex
        const titleMatch = text.match(/title\s+"([^"]*)"/i);
        const contentMatch = text.match(/content\s+"([^"]*)"/i);
        const statusMatch = text.match(/status\s+"([^"]*)"/i);
        
        console.log('Direct matches:', { titleMatch, contentMatch, statusMatch });
        
        const title = titleMatch ? titleMatch[1] : 'Untitled Post';
        const content = contentMatch ? contentMatch[1] : 'No content provided.';
        const status = statusMatch && statusMatch[1].toLowerCase() === 'draft' ? 'draft' : 'draft';
        
        console.log('Direct extracted parameters:', { title, content, status });
        
        try {
          console.log('Directly calling client.createPost()');
          const postResponse = await this.client.createPost(title, content, status);
          console.log('Post creation response:', postResponse);
          
          result = `Post created successfully!\n\n`;
          result += `Title: ${postResponse.data.title.raw}\n`;
          result += `Status: ${postResponse.data.status}\n`;
          if (postResponse.data.link) {
            result += `URL: ${postResponse.data.link}\n`;
          }
          
          artifacts.push({
            id: `created-post-${Date.now()}`,
            type: 'application/json',
            title: 'Created WordPress Post',
            description: 'Details of the newly created post',
            parts: [
              {
                type: 'data',
                data: {
                  contentType: 'application/json',
                  content: JSON.stringify(postResponse.data)
                }
              }
            ]
          });
        } catch (error) {
          console.error('Error creating post directly:', error);
          result = `Error creating post: ${error.message}`;
        }
      }
      else if (lowerText.includes('create post') || lowerText.includes('new post')) {
        console.log('Matched simple create/new post pattern - returning instructions');
        // For creation, we'd typically ask for more information
        // In a real implementation, this would use input-required status
        result = "To create a post, please provide:\n\n1. Title\n2. Content\n3. Status (publish/draft)\n\nYou can create a post using the format: 'Create post with title \"My Title\", content \"My content.\", status \"draft\"'";
      }
      else if (lowerText.includes('title') && (lowerText.includes('create post') || lowerText.includes('new post'))) {
        console.log('Matched flexible create post pattern - attempting to create post');
        
        // Use the helper function to extract post parameters
        const { title, content, status } = this._extractPostParams(text);
        console.log('Extracted parameters using helper function: ', { title, content, status });
        
        try {
          console.log('Calling client.createPost() with:', { title, content, status });
          const postResponse = await this.client.createPost(title, content, status);
          console.log('Post creation response:', postResponse);
          
          result = `Post created successfully!\n\n`;
          result += `Title: ${postResponse.data.title.raw}\n`;
          result += `Status: ${postResponse.data.status}\n`;
          if (postResponse.data.link) {
            result += `URL: ${postResponse.data.link}\n`;
          }
          
          artifacts.push({
            id: `created-post-${Date.now()}`,
            type: 'application/json',
            title: 'Created WordPress Post',
            description: 'Details of the newly created post',
            parts: [
              {
                type: 'data',
                data: {
                  contentType: 'application/json',
                  content: JSON.stringify(postResponse.data)
                }
              }
            ]
          });
        } catch (error) {
          console.error('Error creating post:', error);
          result = `Error creating post: ${error.message}`;
        }
      }
      else if (lowerText.includes('list categories') || lowerText.includes('show categories')) {
        const categoriesResponse = await this.client.getCategories();
        
        result = `Here are the ${categoriesResponse.data.length} categories:\n\n`;
        
        categoriesResponse.data.forEach(category => {
          result += `- ${category.name} (ID: ${category.id})\n`;
          result += `  Slug: ${category.slug}\n`;
          result += `  Posts: ${category.count}\n\n`;
        });
        
        artifacts.push({
          id: `categories-${Date.now()}`,
          type: 'application/json',
          title: 'WordPress Categories',
          description: 'Categories from WordPress site',
          parts: [
            {
              type: 'data',
              data: {
                contentType: 'application/json',
                content: JSON.stringify(categoriesResponse.data)
              }
            }
          ]
        });
      }
      else if (lowerText.includes('list tags') || lowerText.includes('show tags')) {
        const tagsResponse = await this.client.getTags();
        
        result = `Here are the ${tagsResponse.data.length} tags:\n\n`;
        
        tagsResponse.data.forEach(tag => {
          result += `- ${tag.name} (ID: ${tag.id})\n`;
          result += `  Slug: ${tag.slug}\n`;
          result += `  Posts: ${tag.count}\n\n`;
        });
        
        artifacts.push({
          id: `tags-${Date.now()}`,
          type: 'application/json',
          title: 'WordPress Tags',
          description: 'Tags from WordPress site',
          parts: [
            {
              type: 'data',
              data: {
                contentType: 'application/json',
                content: JSON.stringify(tagsResponse.data)
              }
            }
          ]
        });
      }
      else if (lowerText.includes('site info') || lowerText.includes('about')) {
        const infoResponse = await this.client.getSiteInfo();
        
        result = `WordPress Site Information:\n\n`;
        result += `Name: ${infoResponse.data.name}\n`;
        result += `Description: ${infoResponse.data.description}\n`;
        result += `URL: ${infoResponse.data.url}\n`;
        
        artifacts.push({
          id: `site-info-${Date.now()}`,
          type: 'application/json',
          title: 'WordPress Site Information',
          description: 'Information about the WordPress site',
          parts: [
            {
              type: 'data',
              data: {
                contentType: 'application/json',
                content: JSON.stringify(infoResponse.data)
              }
            }
          ]
        });
      }
      else if (lowerText.includes('discover endpoints') || lowerText.includes('available endpoints')) {
        const endpointsResponse = await this.client.discoverEndpoints();
        
        result = `Available WordPress endpoints:\n\n`;
        
        endpointsResponse.endpoints.slice(0, 10).forEach(endpoint => {
          result += `- ${endpoint.path}\n`;
          result += `  Methods: ${endpoint.methods.join(', ')}\n`;
          result += `  Namespace: ${endpoint.namespace}\n\n`;
        });
        
        if (endpointsResponse.endpoints.length > 10) {
          result += `... and ${endpointsResponse.endpoints.length - 10} more endpoints\n`;
        }
        
        artifacts.push({
          id: `endpoints-${Date.now()}`,
          type: 'application/json',
          title: 'WordPress Endpoints',
          description: 'Available REST API endpoints',
          parts: [
            {
              type: 'data',
              data: {
                contentType: 'application/json',
                content: JSON.stringify(endpointsResponse.endpoints)
              }
            }
          ]
        });
      }
      // Catch-all handler for any request that might be attempting to create a post
      else if (lowerText.includes('post') && 
              (lowerText.includes('create') || lowerText.includes('make') || lowerText.includes('add') || lowerText.includes('new'))) {
        console.log('Detected possible post creation intent with non-standard format');
        
        // Use the helper function to extract post parameters
        const { title, content, status } = extractPostParams(text);
        console.log('Extracted parameters from catch-all handler:', { title, content, status });
        
        // Check if we've extracted enough meaningful information to create a post
        // Only proceed if we've got a title or content that's not the default
        if (title !== 'Untitled Post' || content !== 'No content provided.') {
          console.log('Attempting to create post with extracted information:', { title, content, status });
          
          try {
            console.log('Calling client.createPost() with:', { title, content, status });
            const postResponse = await this.client.createPost(title, content, status);
            console.log('Post creation response:', postResponse);
            
            result = `Post created successfully!\n\n`;
            result += `Title: ${postResponse.data.title.raw}\n`;
            result += `Status: ${postResponse.data.status}\n`;
            if (postResponse.data.link) {
              result += `URL: ${postResponse.data.link}\n`;
            }
            
            artifacts.push({
              id: `created-post-${Date.now()}`,
              type: 'application/json',
              title: 'Created WordPress Post',
              description: 'Details of the newly created post',
              parts: [
                {
                  type: 'data',
                  data: {
                    contentType: 'application/json',
                    content: JSON.stringify(postResponse.data)
                  }
                }
              ]
            });
          } catch (error) {
            console.error('Error creating post:', error);
            result = `Error creating post: ${error.message}`;
          }
        } else {
          // Not enough information was extracted
          result = "It seems like you want to create a post, but I couldn't extract all the necessary information. Please provide:\n\n1. Title\n2. Content\n3. Status (publish/draft)\n\nYou can create a post using the format: 'Create post with title \"My Title\", content \"My content.\", status \"draft\"'";
        }
      }
      else {
        // Generic help response
        result = "I'm a WordPress agent that can help you interact with your WordPress site. Here are some things you can ask me to do:\n\n";
        result += "- list posts\n";
        result += "- show post 123\n";
        result += "- create post\n";
        result += "- list categories\n";
        result += "- list tags\n";
        result += "- site info\n";
        result += "- discover endpoints\n";
      }
    } catch (error) {
      result = `There was an error processing your request: ${error.message}`;
    }
    
    return {
      text: result,
      artifacts
    };
  }
  
  /**
   * Create a JSON-RPC 2.0 response
   * @param {string|number} id - The request ID
   * @param {Object} result - The result object
   * @returns {Object} - The JSON-RPC response
   * @private
   */
  _createJsonRpcResponse(id, result) {
    return {
      jsonrpc: '2.0',
      id: id,
      result: result
    };
  }
  
  /**
   * Create a JSON-RPC 2.0 error response
   * @param {number} code - The error code
   * @param {string} message - The error message
   * @param {string|number} id - The request ID
   * @returns {Object} - The JSON-RPC error response
   * @private
   */
  _createJsonRpcError(code, message, id) {
    return {
      jsonrpc: '2.0',
      id: id,
      error: {
        code: code,
        message: message
      }
    };
  }
}

export default A2AMCPAdapter;
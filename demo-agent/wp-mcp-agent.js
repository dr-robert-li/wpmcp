#!/usr/bin/env node

/**
 * WordPress MCP Natural Language Agent
 * 
 * A demo agent that interacts with a WordPress site via MCP using natural language commands.
 * This agent can create, update, and retrieve posts through conversational interactions.
 * 
 * Usage:
 *   node wp-mcp-agent.js
 * 
 * @author Dr. Robert Li
 * @version 1.0.0
 */

const readline = require('readline');
const fetch = require('node-fetch');
const chalk = require('chalk');

// Configuration
const config = {
  serverUrl: 'http://localhost:10005/wp-json/wpmcp/v1/mcp',
  apiKey: '123456789',
  requestCounter: 1
};

// Create readline interface for user input
const rl = readline.createInterface({
  input: process.stdin,
  output: process.stdout
});

/**
 * Send a request to the MCP API
 */
async function sendMcpRequest(method, toolName, toolArguments) {
  const requestId = config.requestCounter++;
  
  try {
    const response = await fetch(config.serverUrl, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-API-Key': config.apiKey
      },
      body: JSON.stringify({
        jsonrpc: '2.0',
        id: requestId,
        method: method,
        params: {
          name: toolName,
          arguments: toolArguments
        }
      })
    });
    
    if (!response.ok) {
      throw new Error(`HTTP error! Status: ${response.status}`);
    }
    
    return await response.json();
  } catch (error) {
    console.error(chalk.red('Error sending request:'), error);
    return { error: error.message };
  }
}

/**
 * Get WordPress post by ID
 */
async function getPost(postId) {
  return sendMcpRequest('toolCall', 'wp_call_endpoint', {
    endpoint: `/wp/v2/posts/${postId}`,
    method: 'GET'
  });
}

/**
 * Get recent WordPress posts
 */
async function getRecentPosts(count = 5) {
  return sendMcpRequest('toolCall', 'wp_call_endpoint', {
    endpoint: '/wp/v2/posts',
    method: 'GET',
    params: {
      per_page: count,
      orderby: 'date',
      order: 'desc'
    }
  });
}

/**
 * Create a new WordPress post
 */
async function createPost(title, content, status = 'publish') {
  return sendMcpRequest('toolCall', 'wp_call_endpoint', {
    endpoint: '/wp/v2/posts',
    method: 'POST',
    params: {
      title: title,
      content: content,
      status: status
    }
  });
}

/**
 * Update an existing WordPress post
 */
async function updatePost(postId, updates) {
  return sendMcpRequest('toolCall', 'wp_call_endpoint', {
    endpoint: `/wp/v2/posts/${postId}`,
    method: 'PUT',
    params: updates
  });
}

/**
 * Delete a WordPress post
 */
async function deletePost(postId) {
  return sendMcpRequest('toolCall', 'wp_call_endpoint', {
    endpoint: `/wp/v2/posts/${postId}`,
    method: 'DELETE'
  });
}

/**
 * Get available WordPress endpoints
 */
async function discoverEndpoints() {
  return sendMcpRequest('toolCall', 'wp_discover_endpoints', {});
}

/**
 * Process natural language commands
 */
async function processCommand(input) {
  // Convert input to lowercase for easier matching
  const command = input.toLowerCase();
  
  // Help command
  if (command === 'help' || command === '?') {
    displayHelp();
    return;
  }
  
  // Exit command
  if (command === 'exit' || command === 'quit') {
    console.log(chalk.blue('Goodbye!'));
    rl.close();
    process.exit(0);
    return;
  }
  
  // Get recent posts
  if (command.match(/show|list|get|display/) && command.match(/recent|latest|posts/)) {
    console.log(chalk.yellow('Fetching recent posts...'));
    
    // Extract number of posts if specified
    const countMatch = command.match(/(\d+)\s+posts/);
    const count = countMatch ? parseInt(countMatch[1]) : 5;
    
    const result = await getRecentPosts(count);
    
    if (result.result && result.result.data) {
      console.log(chalk.green(`Found ${result.result.data.length} posts:`));
      result.result.data.forEach(post => {
        console.log(chalk.cyan(`ID: ${post.id} - ${post.title.rendered}`));
        console.log(chalk.gray(`URL: ${post.link}`));
        console.log(chalk.gray(`Status: ${post.status}`));
        console.log(chalk.gray(`Date: ${new Date(post.date).toLocaleString()}`));
        console.log(); // Empty line for better readability
      });
    } else {
      console.log(chalk.red('Error fetching posts:'), result.error || 'Unknown error');
    }
    return;
  }
  
  // Get post by ID
  if (command.match(/get|show|display|view/) && command.match(/post/) && command.match(/id\s*:\s*\d+|id\s+\d+|post\s+\d+/)) {
    const idMatch = command.match(/id\s*:\s*(\d+)|id\s+(\d+)|post\s+(\d+)/);
    const postId = idMatch[1] || idMatch[2] || idMatch[3];
    
    console.log(chalk.yellow(`Fetching post with ID: ${postId}...`));
    
    const result = await getPost(postId);
    
    if (result.result && result.result.data) {
      const post = result.result.data;
      console.log(chalk.green(`Post: ${post.title.rendered}`));
      console.log(chalk.gray(`URL: ${post.link}`));
      console.log(chalk.gray(`Status: ${post.status}`));
      console.log(chalk.gray(`Date: ${new Date(post.date).toLocaleString()}`));
      console.log(chalk.white('\nContent:'));
      console.log(chalk.white(post.content.raw || 'No content'));
    } else {
      console.log(chalk.red('Error fetching post:'), result.error || 'Unknown error');
    }
    return;
  }
  
  // Create a new post
  if (command.match(/create|write|add|new/) && command.match(/post|article/)) {
    console.log(chalk.yellow('Creating a new post...'));
    
    // Ask for title
    rl.question(chalk.cyan('Title: '), async (title) => {
      if (!title.trim()) {
        console.log(chalk.red('Title cannot be empty. Post creation cancelled.'));
        promptForCommand();
        return;
      }
      
      // Ask for content
      rl.question(chalk.cyan('Content: '), async (content) => {
        if (!content.trim()) {
          console.log(chalk.red('Content cannot be empty. Post creation cancelled.'));
          promptForCommand();
          return;
        }
        
        // Ask for status (publish or draft)
        rl.question(chalk.cyan('Status (publish/draft): '), async (status) => {
          status = status.trim().toLowerCase();
          if (status !== 'publish' && status !== 'draft') {
            status = 'draft';
          }
          
          const result = await createPost(title, content, status);
          
          if (result.result && result.result.data) {
            const post = result.result.data;
            console.log(chalk.green(`Post created successfully!`));
            console.log(chalk.cyan(`ID: ${post.id} - ${post.title.raw}`));
            console.log(chalk.gray(`URL: ${post.link}`));
            console.log(chalk.gray(`Status: ${post.status}`));
          } else {
            console.log(chalk.red('Error creating post:'), result.error || 'Unknown error');
          }
          
          promptForCommand();
        });
      });
    });
    return true; // Indicate that we're handling input asynchronously
  }
  
  // Update an existing post
  if (command.match(/update|edit|modify|change/) && command.match(/post/) && command.match(/id\s*:\s*\d+|id\s+\d+|post\s+\d+/)) {
    const idMatch = command.match(/id\s*:\s*(\d+)|id\s+(\d+)|post\s+(\d+)/);
    const postId = idMatch[1] || idMatch[2] || idMatch[3];
    
    console.log(chalk.yellow(`Updating post with ID: ${postId}...`));
    
    // First, get the current post
    const getResult = await getPost(postId);
    
    if (!getResult.result || !getResult.result.data) {
      console.log(chalk.red('Error fetching post:'), getResult.error || 'Unknown error');
      return;
    }
    
    const post = getResult.result.data;
    console.log(chalk.green(`Editing post: ${post.title.rendered}`));
    
    // Ask for new title or keep current
    rl.question(chalk.cyan(`New title (current: "${post.title.raw}") [press Enter to keep current]: `), async (title) => {
      title = title.trim() || post.title.raw;
      
      // Ask for new content or keep current
      rl.question(chalk.cyan('New content [press Enter to keep current]:\n'), async (content) => {
        content = content.trim() || post.content.raw;
        
        // Ask for new status or keep current
        rl.question(chalk.cyan(`New status (current: "${post.status}") [publish/draft or press Enter to keep current]: `), async (status) => {
          status = status.trim().toLowerCase();
          if (status !== 'publish' && status !== 'draft') {
            status = post.status;
          }
          
          const updates = {
            title: title,
            content: content,
            status: status
          };
          
          const result = await updatePost(postId, updates);
          
          if (result.result && result.result.data) {
            const updatedPost = result.result.data;
            console.log(chalk.green(`Post updated successfully!`));
            console.log(chalk.cyan(`ID: ${updatedPost.id} - ${updatedPost.title.raw}`));
            console.log(chalk.gray(`URL: ${updatedPost.link}`));
            console.log(chalk.gray(`Status: ${updatedPost.status}`));
          } else {
            console.log(chalk.red('Error updating post:'), result.error || 'Unknown error');
          }
          
          promptForCommand();
        });
      });
    });
    return true; // Indicate that we're handling input asynchronously
  }
  
  // Delete a post
  if (command.match(/delete|remove|trash/) && command.match(/post/) && command.match(/id\s*:\s*\d+|id\s+\d+|post\s+\d+/)) {
    const idMatch = command.match(/id\s*:\s*(\d+)|id\s+(\d+)|post\s+(\d+)/);
    const postId = idMatch[1] || idMatch[2] || idMatch[3];
    
    console.log(chalk.yellow(`Deleting post with ID: ${postId}...`));
    
    // Confirm deletion
    rl.question(chalk.red(`Are you sure you want to delete post #${postId}? (yes/no): `), async (answer) => {
      if (answer.toLowerCase() !== 'yes' && answer.toLowerCase() !== 'y') {
        console.log(chalk.blue('Deletion cancelled.'));
        promptForCommand();
        return;
      }
      
      const result = await deletePost(postId);
      
      if (result.result && result.result.data) {
        console.log(chalk.green(`Post #${postId} deleted successfully!`));
      } else {
        console.log(chalk.red('Error deleting post:'), result.error || 'Unknown error');
      }
      
      promptForCommand();
    });
    return true; // Indicate that we're handling input asynchronously
  }
  
  // Discover endpoints
  if (command.match(/discover|list|get|show/) && command.match(/endpoints|tools|capabilities/)) {
    console.log(chalk.yellow('Discovering available endpoints...'));
    
    const result = await discoverEndpoints();
    
    if (result.result && result.result.data) {
      console.log(chalk.green('Available endpoints:'));
      result.result.data.endpoints.forEach(endpoint => {
        console.log(chalk.cyan(`Path: ${endpoint.path}`));
        console.log(chalk.gray(`Methods: ${endpoint.methods.join(', ')}`));
        console.log(chalk.gray(`Namespace: ${endpoint.namespace}`));
        console.log(chalk.gray(`URI: ${endpoint.uri}`));
        console.log(); // Empty line for better readability
      });
    } else {
      console.log(chalk.red('Error discovering endpoints:'), result.error || 'Unknown error');
    }
    return;
  }
  
  // Command not recognized
  console.log(chalk.yellow('Command not recognized. Type "help" for available commands.'));
}

/**
 * Display help information
 */
function displayHelp() {
  console.log(chalk.green('WordPress MCP Natural Language Agent - Help'));
  console.log(chalk.cyan('\nAvailable commands:'));
  console.log(chalk.white('- "show recent posts" or "list latest posts" - Display recent posts'));
  console.log(chalk.white('- "show 10 posts" - Display a specific number of recent posts'));
  console.log(chalk.white('- "get post id: 123" or "show post 123" - Display a specific post'));
  console.log(chalk.white('- "create post" or "add new article" - Create a new post'));
  console.log(chalk.white('- "update post id: 123" or "edit post 123" - Update an existing post'));
  console.log(chalk.white('- "delete post id: 123" or "remove post 123" - Delete a post'));
  console.log(chalk.white('- "discover endpoints" or "show capabilities" - List available endpoints'));
  console.log(chalk.white('- "help" or "?" - Display this help information'));
  console.log(chalk.white('- "exit" or "quit" - Exit the agent'));
}

/**
 * Prompt for user command
 */
function promptForCommand() {
  rl.question(chalk.green('\nWhat would you like to do? '), async (input) => {
    const isAsync = await processCommand(input);
    if (!isAsync) {
      promptForCommand();
    }
  });
}

/**
 * Initialize the agent
 */
async function initAgent() {
  console.log(chalk.green('WordPress MCP Natural Language Agent'));
  console.log(chalk.cyan('Connected to: ') + config.serverUrl);
  console.log(chalk.white('Type "help" for available commands or "exit" to quit.'));
  
  promptForCommand();
}

// Start the agent
initAgent();
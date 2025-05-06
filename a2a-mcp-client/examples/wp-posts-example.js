#!/usr/bin/env node

/**
 * WordPress MCP Client Example
 * 
 * This example demonstrates how to use the MCP client to interact with a WordPress site.
 * 
 * Usage:
 *   node wp-posts-example.js
 */

import { MCPClient } from '../src/index.js';
import chalk from 'chalk';
import readline from 'readline';

// Create readline interface
const rl = readline.createInterface({
  input: process.stdin,
  output: process.stdout
});

// Create client instance
const client = new MCPClient({
  configFile: './wpmcp-config.json'
});

/**
 * Display help information
 */
function displayHelp() {
  console.log(chalk.green('WordPress MCP Client Example'));
  console.log(chalk.cyan('\nAvailable commands:'));
  console.log(chalk.white('- "discover" - Discover available endpoints'));
  console.log(chalk.white('- "posts" - List recent posts'));
  console.log(chalk.white('- "info" - Get site information'));
  console.log(chalk.white('- "post <id>" - Get a specific post'));
  console.log(chalk.white('- "create" - Create a new post'));
  console.log(chalk.white('- "categories" - List categories'));
  console.log(chalk.white('- "tags" - List tags'));
  console.log(chalk.white('- "help" - Display this help information'));
  console.log(chalk.white('- "exit" - Exit the example'));
}

/**
 * Create a new post
 */
async function createPost() {
  return new Promise((resolve) => {
    // Ask for title
    rl.question(chalk.cyan('Title: '), (title) => {
      if (!title.trim()) {
        console.log(chalk.red('Title cannot be empty. Post creation cancelled.'));
        resolve(null);
        return;
      }
      
      // Ask for content
      rl.question(chalk.cyan('Content: '), (content) => {
        if (!content.trim()) {
          console.log(chalk.red('Content cannot be empty. Post creation cancelled.'));
          resolve(null);
          return;
        }
        
        // Ask for status
        rl.question(chalk.cyan('Status (publish/draft): '), async (status) => {
          status = status.trim().toLowerCase();
          if (status !== 'publish' && status !== 'draft') {
            status = 'draft';
          }
          
          // Create the post
          try {
            const post = await client.createPost(title, content, status);
            console.log(chalk.green('Post created successfully!'));
            console.log(chalk.cyan(`ID: ${post.data.id} - ${post.data.title.raw}`));
            console.log(chalk.gray(`Status: ${post.data.status}`));
            if (post.data.link) {
              console.log(chalk.gray(`URL: ${post.data.link}`));
            }
            resolve(post);
          } catch (error) {
            console.error(chalk.red('Error creating post:'), error.message);
            resolve(null);
          }
        });
      });
    });
  });
}

/**
 * Process user commands
 */
async function processCommand(command) {
  // Split command and arguments
  const parts = command.trim().split(' ');
  const cmd = parts[0].toLowerCase();
  const args = parts.slice(1);
  
  try {
    switch (cmd) {
      case 'discover':
        console.log(chalk.yellow('Discovering available endpoints...'));
        const endpoints = await client.discoverEndpoints();
        console.log(chalk.green('Available endpoints:'));
        endpoints.endpoints.forEach(endpoint => {
          console.log(chalk.cyan(`Path: ${endpoint.path}`));
          console.log(chalk.gray(`Methods: ${endpoint.methods.join(', ')}`));
          console.log(chalk.gray(`Namespace: ${endpoint.namespace}`));
          console.log(); // Empty line for better readability
        });
        break;
        
      case 'posts':
        console.log(chalk.yellow('Fetching recent posts...'));
        const count = args[0] ? parseInt(args[0]) : 5;
        const posts = await client.getPosts({ per_page: count });
        console.log(chalk.green(`Found ${posts.data.length} posts:`));
        posts.data.forEach(post => {
          console.log(chalk.cyan(`ID: ${post.id} - ${post.title.rendered}`));
          if (post.link) {
            console.log(chalk.gray(`URL: ${post.link}`));
          }
          console.log(chalk.gray(`Status: ${post.status}`));
          console.log(chalk.gray(`Date: ${new Date(post.date).toLocaleString()}`));
          console.log(); // Empty line for better readability
        });
        break;
        
      case 'post':
        if (!args[0]) {
          console.log(chalk.red('Post ID required'));
          break;
        }
        const postId = args[0];
        console.log(chalk.yellow(`Fetching post with ID: ${postId}...`));
        
        try {
          const post = await client.callEndpoint(`/wp/v2/posts/${postId}`, 'GET');
          console.log(chalk.green(`Post: ${post.data.title.rendered}`));
          if (post.data.link) {
            console.log(chalk.gray(`URL: ${post.data.link}`));
          }
          console.log(chalk.gray(`Status: ${post.data.status}`));
          console.log(chalk.gray(`Date: ${new Date(post.data.date).toLocaleString()}`));
          console.log(chalk.white('\nContent:'));
          console.log(chalk.white(post.data.content.rendered.replace(/<[^>]*>/g, '')));
        } catch (error) {
          console.log(chalk.red('Error fetching post:'), error.message);
        }
        break;
        
      case 'create':
        await createPost();
        break;
        
      case 'categories':
        console.log(chalk.yellow('Fetching categories...'));
        const categories = await client.getCategories();
        console.log(chalk.green(`Found ${categories.data.length} categories:`));
        categories.data.forEach(category => {
          console.log(chalk.cyan(`ID: ${category.id} - ${category.name}`));
          console.log(chalk.gray(`Slug: ${category.slug}`));
          console.log(chalk.gray(`Count: ${category.count} posts`));
          console.log(); // Empty line for better readability
        });
        break;
        
      case 'tags':
        console.log(chalk.yellow('Fetching tags...'));
        const tags = await client.getTags();
        console.log(chalk.green(`Found ${tags.data.length} tags:`));
        tags.data.forEach(tag => {
          console.log(chalk.cyan(`ID: ${tag.id} - ${tag.name}`));
          console.log(chalk.gray(`Slug: ${tag.slug}`));
          console.log(chalk.gray(`Count: ${tag.count} posts`));
          console.log(); // Empty line for better readability
        });
        break;
        
      case 'info':
        console.log(chalk.yellow('Fetching site information...'));
        const info = await client.getSiteInfo();
        console.log(chalk.green('Site Information:'));
        console.log(chalk.cyan(`Name: ${info.data.name}`));
        console.log(chalk.cyan(`Description: ${info.data.description}`));
        console.log(chalk.cyan(`URL: ${info.data.url}`));
        break;
        
      case 'help':
        displayHelp();
        break;
        
      case 'exit':
      case 'quit':
        console.log(chalk.blue('Goodbye!'));
        rl.close();
        process.exit(0);
        break;
        
      default:
        console.log(chalk.yellow('Command not recognized. Type "help" for available commands.'));
    }
  } catch (error) {
    console.error(chalk.red('Error executing command:'), error.message);
  }
}

/**
 * Prompt for user command
 */
function promptForCommand() {
  rl.question(chalk.green('\nCommand: '), async (input) => {
    await processCommand(input);
    promptForCommand();
  });
}

/**
 * Initialize the example
 */
async function initExample() {
  console.log(chalk.green('WordPress MCP Client Example'));
  
  try {
    // Initialize the client
    console.log(chalk.yellow('Initializing client...'));
    const capabilities = await client.initialize();
    console.log(chalk.green('Client initialized!'));
    console.log(chalk.cyan('Server Information:'));
    console.log(chalk.white(`Name: ${capabilities.serverInfo.name}`));
    console.log(chalk.white(`Version: ${capabilities.serverInfo.version}`));
    
    console.log(chalk.cyan('\nServer Capabilities:'));
    console.log(chalk.white(`Protocol Version: ${capabilities.serverCapabilities.protocolVersion}`));
    console.log(chalk.white(`HTTP Transport: ${capabilities.serverCapabilities.transports.http}`));
    console.log(chalk.white(`SSE Transport: ${capabilities.serverCapabilities.transports.sse}`));
    
    // Display help
    console.log();
    displayHelp();
    
    // Start command prompt
    promptForCommand();
  } catch (error) {
    console.error(chalk.red('Error initializing client:'), error.message);
    rl.close();
    process.exit(1);
  }
}

// Start the example
initExample();
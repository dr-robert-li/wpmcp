#!/usr/bin/env node

/**
 * Simple WordPress MCP Client Demo
 * 
 * A non-interactive demonstration of the MCP client for WordPress.
 * This shows basic functionality without requiring user input.
 */

import { MCPClient } from '../src/index.js';
import chalk from 'chalk';

async function runDemo() {
  console.log(chalk.green('WordPress MCP Client - Simple Demo'));
  console.log(chalk.yellow('Initializing client...'));
  
  try {
    // Create and initialize client
    const client = new MCPClient({
      configFile: './wpmcp-config.json'
    });
    
    // Initialize the client
    const capabilities = await client.initialize();
    console.log(chalk.green('✓ Client initialized successfully'));
    console.log(chalk.cyan(`Server: ${capabilities.serverInfo.name} v${capabilities.serverInfo.version}`));
    console.log('');
    
    // Discover endpoints
    console.log(chalk.yellow('Discovering WordPress endpoints...'));
    const endpoints = await client.discoverEndpoints();
    console.log(chalk.green(`✓ Found ${endpoints.endpoints.length} endpoints`));
    
    // Display a few endpoints as examples
    const sampleEndpoints = endpoints.endpoints.slice(0, 3);
    sampleEndpoints.forEach(endpoint => {
      console.log(chalk.cyan(`  - ${endpoint.path} (${endpoint.methods.join(', ')})`));
    });
    console.log('');
    
    // Get recent posts
    console.log(chalk.yellow('Fetching recent posts...'));
    const posts = await client.getPosts({ per_page: 3 });
    console.log(chalk.green(`✓ Found ${posts.data.length} recent posts`));
    
    // Display post information
    posts.data.forEach(post => {
      console.log(chalk.cyan(`  - ${post.title.rendered} (ID: ${post.id})`));
      console.log(chalk.gray(`    Status: ${post.status}, Date: ${new Date(post.date).toLocaleString()}`));
    });
    console.log('');
    
    // Get site information
    console.log(chalk.yellow('Fetching site information...'));
    const siteInfo = await client.getSiteInfo();
    console.log(chalk.green('✓ Site information retrieved'));
    console.log(chalk.cyan(`  - Name: ${siteInfo.data.name}`));
    console.log(chalk.cyan(`  - Description: ${siteInfo.data.description}`));
    console.log(chalk.cyan(`  - URL: ${siteInfo.data.url}`));
    console.log('');
    
    // Get categories
    console.log(chalk.yellow('Fetching categories...'));
    const categories = await client.getCategories();
    console.log(chalk.green(`✓ Found ${categories.data.length} categories`));
    categories.data.forEach(category => {
      console.log(chalk.cyan(`  - ${category.name} (${category.count} posts)`));
    });
    console.log('');
    
    console.log(chalk.green('Demo completed successfully'));
    console.log(chalk.white('For more features, run the interactive example: npm run example'));
  } catch (error) {
    console.error(chalk.red('Error during demo:'), error.message);
  }
}

// Run the demo
runDemo();
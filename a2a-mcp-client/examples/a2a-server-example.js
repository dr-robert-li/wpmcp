#!/usr/bin/env node

/**
 * A2A Server Example for WordPress MCP Client
 * 
 * This example demonstrates how to create an A2A-compatible server
 * using the WordPress MCP client. It provides an A2A protocol interface
 * that other agents can use to interact with WordPress.
 * 
 * Usage:
 *   node a2a-server-example.js
 */

import { MCPClient } from '../src/index.js';
import { A2AMCPAdapter } from '../src/a2a-adapter.js';
import http from 'http';
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

// Get directory name in ESM
const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

// Configuration
const PORT = process.env.PORT || 3000;

// Create MCP client
const client = new MCPClient({
  configFile: path.join(__dirname, '..', 'wpmcp-config.json')
});

// Create A2A adapter
const adapter = new A2AMCPAdapter({
  client,
  agentCardPath: path.join(__dirname, '..', '.well-known', 'agent.json')
});

/**
 * Create a simple HTTP server that handles A2A protocol requests
 */
async function createServer() {
  // Initialize client and adapter
  await client.initialize();
  await adapter.initialize();
  
  console.log('MCP client initialized');
  console.log('A2A adapter initialized, agent card created');
  
  // Create HTTP server
  const server = http.createServer(async (req, res) => {
    // Set CORS headers
    res.setHeader('Access-Control-Allow-Origin', '*');
    res.setHeader('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
    res.setHeader('Access-Control-Allow-Headers', 'Content-Type, x-api-key');
    
    // Handle OPTIONS requests (CORS preflight)
    if (req.method === 'OPTIONS') {
      res.writeHead(204);
      res.end();
      return;
    }
    
    // Handle agent card request
    if (req.url === '/.well-known/agent.json' && req.method === 'GET') {
      const agentCardPath = path.join(__dirname, '..', '.well-known', 'agent.json');
      
      if (fs.existsSync(agentCardPath)) {
        res.writeHead(200, { 'Content-Type': 'application/json' });
        const agentCard = fs.readFileSync(agentCardPath);
        res.end(agentCard);
      } else {
        res.writeHead(404);
        res.end(JSON.stringify({ error: 'Agent card not found' }));
      }
      return;
    }
    
    // Handle A2A protocol requests
    if (req.url === '/a2a' && req.method === 'POST') {
      let body = '';
      
      req.on('data', chunk => {
        body += chunk.toString();
      });
      
      req.on('end', async () => {
        try {
          // Parse request
          const request = JSON.parse(body);
          
          // Process request
          const response = await adapter.handleRequest(request);
          
          // Send response
          res.writeHead(200, { 'Content-Type': 'application/json' });
          res.end(JSON.stringify(response));
        } catch (error) {
          console.error('Error processing request:', error);
          res.writeHead(500);
          res.end(JSON.stringify({
            jsonrpc: '2.0',
            id: null,
            error: {
              code: -32603,
              message: 'Internal server error'
            }
          }));
        }
      });
      return;
    }
    
    // Handle SSE connections for tasks
    if (req.url.startsWith('/a2a/tasks/') && req.url.endsWith('/events') && req.method === 'GET') {
      // Extract task ID
      const taskId = req.url.match(/\/a2a\/tasks\/([^\/]+)\/events/)[1];
      
      // Set up SSE connection
      res.writeHead(200, {
        'Content-Type': 'text/event-stream',
        'Cache-Control': 'no-cache',
        'Connection': 'keep-alive'
      });
      
      // Send initial connection event
      res.write(`event: connection\ndata: ${JSON.stringify({ status: 'connected' })}\n\n`);
      
      // Simulate task processing and events
      setTimeout(() => {
        // Send status update
        res.write(`event: TaskStatusUpdateEvent\ndata: ${JSON.stringify({
          taskId,
          status: 'working'
        })}\n\n`);
        
        // Simulate some processing time
        setTimeout(() => {
          // Send task completion
          res.write(`event: TaskStatusUpdateEvent\ndata: ${JSON.stringify({
            taskId,
            status: 'completed'
          })}\n\n`);
          
          // Send artifact update
          res.write(`event: TaskArtifactUpdateEvent\ndata: ${JSON.stringify({
            taskId,
            artifact: {
              id: `artifact-${Date.now()}`,
              type: 'text/plain',
              title: 'WordPress Operation Result',
              description: 'Result of WordPress operation',
              parts: [
                {
                  type: 'text',
                  text: 'Operation completed successfully.'
                }
              ]
            }
          })}\n\n`);
          
          // Close connection after a while
          setTimeout(() => res.end(), 1000);
        }, 2000);
      }, 1000);
      return;
    }
    
    // Default response for unhandled routes
    res.writeHead(404);
    res.end(JSON.stringify({
      error: 'Not found'
    }));
  });
  
  // Start server
  server.listen(PORT, () => {
    console.log(`A2A server for WordPress MCP running on port ${PORT}`);
    console.log(`Agent card available at: http://localhost:${PORT}/.well-known/agent.json`);
    console.log(`A2A endpoint available at: http://localhost:${PORT}/a2a`);
  });
}

// Start the server
createServer().catch(error => {
  console.error('Error starting server:', error);
  process.exit(1);
});
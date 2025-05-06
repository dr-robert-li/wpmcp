# A2A-MCP Client for WordPress

This client provides an interface for interacting with WordPress sites through the Model Context Protocol (MCP) standard. It supports both the A2A (Agent-to-Agent) protocol and direct MCP interactions.

## Features

- Communicate with WordPress MCP-enabled sites
- Support for both HTTP and SSE (Server-Sent Events) transports
- Integration with A2A protocol
- Direct access to WordPress resources via MCP tools

## Installation

```bash
cd a2a-mcp-client
npm install
```

## Configuration

The client uses a configuration file (`wpmcp-config.json`) that defines the MCP tools and server information. An example configuration file is provided at `wpmcp-config.example.json`. 

To get started:

1. Copy the example configuration:
   ```bash
   cp wpmcp-config.example.json wpmcp-config.json
   ```

2. Edit the new file to update with your WordPress site's URL and API key.

## Usage

### Basic Usage

```javascript
import { MCPClient } from './src/index.js';

// Create a client instance
const client = new MCPClient({
  configFile: './wpmcp-config.json'
});

// Initialize the client
await client.initialize();

// Discover available endpoints
const endpoints = await client.discoverEndpoints();
console.log(endpoints);

// Get recent posts
const posts = await client.getPosts({ per_page: 5 });
console.log(posts);
```

### Examples

The client comes with two examples to demonstrate functionality:

1. **Simple Demo** - A non-interactive demonstration that shows basic capabilities:
   ```bash
   npm run demo
   ```

2. **Interactive Example** - A more comprehensive, interactive CLI to explore all features:
   ```bash
   npm run example
   ```

## Available Tools

The client provides access to these WordPress MCP tools:

1. `wp_discover_endpoints` - Maps all available REST API endpoints
2. `wp_call_endpoint` - Executes REST API requests with specified parameters
3. `wp_get_resource` - Retrieves WordPress resources by URI

## A2A Protocol Integration

This client can be used as part of the A2A protocol ecosystem, allowing AI agents to interact with WordPress resources through standardized protocols.

The included A2A adapter exposes WordPress MCP capabilities as an A2A agent with the following features:

- **Agent Card** - Automatically generates a standard A2A agent card at `.well-known/agent.json`
- **JSON-RPC API** - Implements a standard A2A API endpoint for agent-to-agent interactions
- **Skills** - Exposes content management and site management skills to other agents
- **Task Management** - Handles agent tasks with proper state management and streaming

To start an A2A server exposing WordPress functionality:

```bash
node examples/a2a-server-example.js
```

## License

This project is licensed under the GNU General Public License v3.0 (GPL-3.0).
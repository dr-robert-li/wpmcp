# WordPress Model Context Protocol (WPMCP)

### Version
1.2.0

### Author
Dr. Robert Li

WPMCP is a WordPress plugin that implements the [Model Context Protocol (MCP)](https://modelcontextprotocol.io/) standard, enabling AI assistants to interact with WordPress sites through a standardized interface.

## Overview

WPMCP turns your WordPress site into an MCP server, allowing AI assistants and other MCP clients to:

- Discover available WordPress REST API endpoints
- Execute REST API requests with proper authentication
- Manage content, users, and site settings through natural language
- Access WordPress resources through a standardized resource model

## Features

- **MCP Protocol Implementation**: Fully implements the Model Context Protocol standard (version 2024-11-05)
- **WordPress REST API Integration**: Provides access to WordPress's powerful REST API
- **Secure Authentication**: Uses API key authentication to secure access
- **Endpoint Discovery**: Automatically maps available endpoints on your WordPress site
- **Resource Model**: Access WordPress content through a standardized resource model
- **Multiple Transport Layers**: Support for both HTTP and Server-Sent Events (SSE)
- **JSON-RPC 2.0**: Uses standard JSON-RPC 2.0 message format
- **Flexible Operations**: Support for GET, POST, PUT, DELETE, and PATCH methods
- **Granular Permissions**: Control which WordPress resources can be accessed
- **Self-Describing API**: Includes comprehensive descriptions and examples

## Installation

### WordPress Plugin Installation

1. Download the latest release ZIP file from the [Releases page](https://github.com/dr-robert-li/wpmcp/releases)
2. In your WordPress admin dashboard, go to Plugins → Add New → Upload Plugin
3. Choose the downloaded ZIP file and click "Install Now"
4. After installation, click "Activate Plugin"

Alternatively, you can install manually:

1. Download and unzip the plugin
2. Upload the `wpmcp` folder to your `/wp-content/plugins/` directory
3. Activate the plugin through the 'Plugins' menu in WordPress

## Configuration

### WordPress Plugin Configuration

1. After activating the plugin, go to Settings → WPMCP
2. Generate or enter an API key (required for security)
3. Select your preferred transport method (HTTP/SSE does not yet work - placeholders for now)
4. Select which WordPress resources can be accessed via the MCP API:
   - Posts
   - Pages
   - Categories
   - Tags
   - Comments
   - Users
   - Media
   - Plugins
   - Themes
   - Settings
5. Save your settings

The plugin settings page also displays your MCP endpoint URL, which you'll need when configuring MCP clients.

## Functionality

WPMCP provides three main functions through the MCP protocol:

### 1. Discover Endpoints (`wp_discover_endpoints`)

This function maps all available REST API endpoints on your WordPress site and returns their methods and namespaces. It allows AI assistants to understand what operations are possible without having to manually specify endpoints.

**Example Response:**
```json
{
  "endpoints": [
    {
      "path": "/wp/v2/posts",
      "namespace": "wp/v2",
      "methods": ["GET", "POST"],
      "uri": "wordpress:/wp/v2/posts"
    },
    {
      "path": "/wp/v2/pages",
      "namespace": "wp/v2",
      "methods": ["GET", "POST"],
      "uri": "wordpress:/wp/v2/pages"
    }
    // ... other endpoints
  ]
}
```

### 2. Call Endpoint (`wp_call_endpoint`)

This function executes specific REST API requests to the WordPress site using provided parameters. It handles both read and write operations to manage content, users, and site settings.

**Parameters:**
- `endpoint` (required): API endpoint path (e.g., "/wp/v2/posts")
- `method` (optional, default "GET"): HTTP method (GET, POST, PUT, DELETE, PATCH)
- `params` (optional): Request parameters or body data

**Example (Get Posts):**
```json
{
  "jsonrpc": "2.0",
  "id": 1,
  "method": "toolCall",
  "params": {
    "name": "wp_call_endpoint",
    "arguments": {
      "endpoint": "/wp/v2/posts",
      "method": "GET",
      "params": {
        "per_page": 5,
        "orderby": "date",
        "order": "desc"
      }
    }
  }
}
```

**Example (Create Post):**
```json
{
  "jsonrpc": "2.0",
  "id": 2,
  "method": "toolCall",
  "params": {
    "name": "wp_call_endpoint",
    "arguments": {
      "endpoint": "/wp/v2/posts",
      "method": "POST",
      "params": {
        "title": "Example Post Title",
        "content": "This is the content of the post.",
        "status": "draft"
      }
    }
  }
}
```

### 3. Get Resource (`wp_get_resource`)

This function retrieves WordPress resources using a standardized URI format. It provides a more abstract way to access WordPress content.

**Parameters:**
- `uri` (required): Resource URI (e.g., "wordpress:/posts/1")

**Example (Get Post):**
```json
{
  "jsonrpc": "2.0",
  "id": 3,
  "method": "toolCall",
  "params": {
    "name": "wp_get_resource",
    "arguments": {
      "uri": "wordpress:/posts/1"
    }
  }
}
```

## Configuring MCP Clients

WPMCP includes an example configuration file (`wpmcp-client-config.example.json`) that you can use to configure MCP clients to work with your WordPress site.

### Using the Configuration File

1. Copy the `wpmcp-client-config.example.json` file and rename it (e.g., to `wpmcp-config.json`)
2. Edit the file to update:
   - The server URL to point to your WordPress site's MCP endpoint
   - The API key to match the one you set in the plugin settings

### Configuration for Different MCP Clients

#### Claude Desktop

For Claude Desktop, add the configuration to your `claude_desktop_config.json` file:

```json
{
  "tools": [
    // ... other tools
    {
      "name": "wpmcp",
      "config": "./path/to/wpmcp-config.json"
    }
  ]
}
```

#### Anthropic API with Tool Use

When using the Anthropic API directly with tool use:

```json
{
  "tools": [
    {
      "name": "wp_discover_endpoints",
      "description": "Maps all available REST API endpoints on a WordPress site and returns their methods and namespaces.",
      "input_schema": {
        "type": "object",
        "properties": {},
        "required": []
      }
    },
    {
      "name": "wp_call_endpoint",
      "description": "Executes specific REST API requests to the WordPress site using provided parameters.",
      "input_schema": {
        "type": "object",
        "properties": {
          "endpoint": {
            "type": "string",
            "description": "API endpoint path (e.g., /wp/v2/posts)"
          },
          "method": {
            "type": "string",
            "enum": ["GET", "POST", "PUT", "DELETE", "PATCH"],
            "description": "HTTP method",
            "default": "GET"
          },
          "params": {
            "type": "object",
            "description": "Request parameters or body data"
          }
        },
        "required": ["endpoint"]
      }
    },
    {
      "name": "wp_get_resource",
      "description": "Retrieves a WordPress resource by its URI.",
      "input_schema": {
        "type": "object",
        "properties": {
          "uri": {
            "type": "string",
            "description": "Resource URI (e.g., wordpress:/posts/1)"
          }
        },
        "required": ["uri"]
      }
    }
  ]
}
```

## Usage Examples

Once configured, you can ask AI assistants to perform various WordPress operations:

### Content Management
```
Create a new draft post titled "The Future of AI" with these key points: [points]
```
```
Update the featured image on my latest post about machine learning
```
```
Show me all posts published in the last month
```

### User and Comment Management
```
Show me all pending comments on my latest post
```
```
List all users with editor role
```

### Site Configuration
```
What plugins are currently active on my site?
```
```
Check if any themes need updates
```

### Resource Access
```
Get the resource for post with ID 123
```
```
Show me the user resource for the admin user
```

## Direct API Usage

You can also interact with the WPMCP API directly using JSON-RPC 2.0:

### Initialize Connection
```bash
curl -X POST https://your-site.com/wp-json/wpmcp/v1/mcp \
  -H "Content-Type: application/json" \
  -H "X_API_Key: your-api-key" \
  -d '{
    "jsonrpc": "2.0",
    "id": 1,
    "method": "initialize",
    "params": {
      "clientCapabilities": {
        "protocolVersion": "2025-04-30",
        "transports": {
          "http": true,
          "sse": false
        }
      }
    }
  }'
```

### Public GET Request (No API Key Required)
```bash
curl -X POST https://your-site.com/wp-json/wpmcp/v1/mcp \
  -H "Content-Type: application/json" \
  -d '{
    "jsonrpc": "2.0",
    "id": 2,
    "method": "toolCall",
    "params": {
      "name": "wp_call_endpoint",
      "arguments": {
        "endpoint": "/wp/v2/posts",
        "method": "GET",
        "params": {
          "per_page": 5
        }
      }
    }
  }'
```

### Protected Request (API Key Required)
```bash
curl -X POST https://your-site.com/wp-json/wpmcp/v1/mcp \
  -H "Content-Type: application/json" \
  -H "X_API_Key: your-api-key" \
  -d '{
    "jsonrpc": "2.0",
    "id": 3,
    "method": "toolCall",
    "params": {
      "name": "wp_call_endpoint",
      "arguments": {
        "endpoint": "/wp/v2/posts",
        "method": "POST",
        "params": {
          "title": "New Post via API",
          "content": "This is a post created through the WPMCP API.",
          "status": "draft"
        }
      }
    }
  }'
```

### Get a Resource (API Key Required for Sensitive Resources)
```bash
curl -X POST https://your-site.com/wp-json/wpmcp/v1/mcp \
  -H "Content-Type: application/json" \
  -H "X_API_Key: your-api-key" \
  -d '{
    "jsonrpc": "2.0",
    "id": 4,
    "method": "toolCall",
    "params": {
      "name": "wp_get_resource",
      "arguments": {
        "uri": "wordpress:/users/1"
      }
    }
  }'
```

### Discover Resources
```bash
curl -X POST https://your-site.com/wp-json/wpmcp/v1/mcp \
  -H "Content-Type: application/json" \
  -H "X_API_Key: your-api-key" \
  -d '{
    "jsonrpc": "2.0",
    "id": 5,
    "method": "discoverResources",
    "params": {}
  }'
```

## Transport Methods

WPMCP supports two transport methods:

### HTTP

The default transport method is HTTP, which uses standard request-response cycles for communication.

Endpoint: `https://your-site.com/wp-json/wpmcp/v1/mcp`

### Server-Sent Events (SSE)

For applications that require real-time updates, WPMCP also supports Server-Sent Events (SSE).

Endpoint: `https://your-site.com/wp-json/wpmcp/v1/mcp/sse`

To use SSE, you need to:
1. Enable SSE in the plugin settings
2. Connect to the SSE endpoint
3. Listen for events from the server

## Security Considerations

- API key authentication is implemented with different levels of access:
  - Public GET requests to Posts, Pages, Categories, and Tags don't require an API key
  - All write operations (POST, PUT, DELETE, PATCH) require API key authentication
  - All requests to sensitive endpoints (Comments, Users, Media, Plugins, Themes, Settings) require API key authentication regardless of method
- Keep your API key secure and never commit it to version control
- Use HTTPS for all WordPress sites
- Regularly rotate API keys
- Be selective about which WordPress resources you allow access to
- Follow the principle of least privilege when assigning user roles
- Consider implementing IP whitelisting for additional security

## Authentication

WPMCP implements a tiered authentication system:

1. **Public Access (No API Key Required)**:
   - GET requests to Posts, Pages, Categories, and Tags
   - These requests use a read-only user context with limited permissions

2. **API Key Authentication (Required for)**:
   - All write operations (POST, PUT, DELETE, PATCH) to any endpoint
   - Any request (including GET) to sensitive endpoints:
     - Comments
     - Users
     - Media
     - Plugins
     - Themes
     - Settings

3. **Authentication Headers**:
   - The API key can be provided in the `X_API_Key` header (recommended)
   - Alternatively, it can be included in the request body as `api_key`

Example of a public request (no API key required):
```bash
curl -X GET http://your-site.com/wp-json/wpmcp/v1/mcp -H "Content-Type: application/json" -d '{
  "jsonrpc": "2.0",
  "id": 1,
  "method": "toolCall",
  "params": {
    "name": "wp_call_endpoint",
    "arguments": {
      "endpoint": "/wp/v2/posts",
      "method": "GET",
      "params": {
        "per_page": 5
      }
    }
  }
}'
```

Example of a request requiring API key authentication:
```bash
curl -X POST http://your-site.com/wp-json/wpmcp/v1/mcp -H "Content-Type: application/json" -H "X_API_Key: your-api-key" -d '{
  "jsonrpc": "2.0",
  "id": 2,
  "method": "toolCall",
  "params": {
    "name": "wp_call_endpoint",
    "arguments": {
      "endpoint": "/wp/v2/posts",
      "method": "POST",
      "params": {
        "title": "New Post",
        "content": "Post content",
        "status": "draft"
      }
    }
  }
}'
```

## Changelog

### Version 1.2.0
- Added A2A (Agent-to-Agent) adapter for MCP, enabling full CRUD functionality for WordPress content via A2A protocol. 
- Implemented complete Create, Read, Update, and Delete operations with natural language support. 
- Added examples for A2A server integration. 
- Fixed SSE transport issues.

### Version 1.1.0
- Added support for JSON-RPC 2.0 message format
- Implemented resource model with standardized URIs
- Added support for Server-Sent Events (SSE) transport
- Improved error handling and validation
- Added tool descriptions and examples
- Enhanced security features
- Implemented tiered authentication system with public access for read-only operations
- Fixed header authentication to properly handle WordPress header format

### Version 1.0.0
- Initial release
- Basic MCP implementation
- WordPress REST API integration
- API key authentication

## License

This project is licensed under the GNU General Public License v3.0 (GPL-3.0).

## Support

For support, please open an issue on the GitHub repository or contact the maintainer.
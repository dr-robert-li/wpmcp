# WordPress Model Context Protocol (WPMCP)

WPMCP is a WordPress plugin that implements the [Model Context Protocol (MCP)](https://modelcontextprotocol.io/) standard, enabling AI assistants to interact with WordPress sites through a standardized interface.

## Overview

WPMCP turns your WordPress site into an MCP server, allowing AI assistants and other MCP clients to:

- Discover available WordPress REST API endpoints
- Execute REST API requests with proper authentication
- Manage content, users, and site settings through natural language

## Features

- **MCP Protocol Implementation**: Fully implements the Model Context Protocol standard
- **WordPress REST API Integration**: Provides access to WordPress's powerful REST API
- **Secure Authentication**: Uses API key authentication to secure access
- **Endpoint Discovery**: Automatically maps available endpoints on your WordPress site
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
3. Select which WordPress resources can be accessed via the MCP API:
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
4. Save your settings

The plugin settings page also displays your MCP endpoint URL, which you'll need when configuring MCP clients.

## Functionality

WPMCP provides two main functions through the MCP protocol:

### 1. Discover Endpoints (`wp_discover_endpoints`)

This function maps all available REST API endpoints on your WordPress site and returns their methods and namespaces. It allows AI assistants to understand what operations are possible without having to manually specify endpoints.

**Example Response:**
```json
{
  "type": "success",
  "data": [
    {
      "path": "/wp/v2/posts",
      "namespace": "wp/v2",
      "methods": ["GET", "POST"]
    },
    {
      "path": "/wp/v2/pages",
      "namespace": "wp/v2",
      "methods": ["GET", "POST"]
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
  "type": "invoke",
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
```

**Example (Create Post):**
```json
{
  "type": "invoke",
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

## Direct API Usage

You can also interact with the WPMCP API directly:

### Discover Endpoints
```bash
curl -X POST https://your-site.com/wp-json/wpmcp/v1/data \
  -H "Content-Type: application/json" \
  -H "X-API-Key: your-api-key" \
  -d '{
    "type": "invoke",
    "name": "wp_discover_endpoints",
    "arguments": {}
  }'
```

### Call an Endpoint
```bash
curl -X POST https://your-site.com/wp-json/wpmcp/v1/data \
  -H "Content-Type: application/json" \
  -H "X-API-Key: your-api-key" \
  -d '{
    "type": "invoke",
    "name": "wp_call_endpoint",
    "arguments": {
      "endpoint": "/wp/v2/posts",
      "method": "GET",
      "params": {
        "per_page": 5
      }
    }
  }'
```

## Security Considerations

- Keep your API key secure and never commit it to version control
- Use HTTPS for all WordPress sites
- Regularly rotate API keys
- Be selective about which WordPress resources you allow access to
- Follow the principle of least privilege when assigning user roles

## License

This project is licensed under the GNU General Public License v3.0 (GPL-3.0).

## Support

For support, please open an issue on the GitHub repository or contact the maintainer.
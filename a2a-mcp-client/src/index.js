/**
 * A2A-MCP Client for WordPress
 * Main export file
 */

import { MCPClient } from './client.js';
import { HttpTransport } from './transports/http.js';
import { SseTransport } from './transports/sse.js';
import { A2AMCPAdapter } from './a2a-adapter.js';

export {
  MCPClient,
  HttpTransport,
  SseTransport,
  A2AMCPAdapter
};

export default MCPClient;
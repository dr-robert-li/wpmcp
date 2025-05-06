#!/usr/bin/env node

/**
 * WordPress MCP Natural Language Agent with LLM Integration
 * 
 * A demo agent that interacts with a WordPress site via MCP using natural language commands.
 * This agent can create, update, and retrieve posts through conversational interactions.
 * It uses LLM APIs (OpenAI, Anthropic, or Ollama) for enhanced natural language understanding.
 * Smashed out as an exercise in using Claude Code.
 * 
 * Usage:
 *   node wp-mcp-nl-agent.js
 * 
 * @author Dr. Robert Li
 * @version 1.0.0
 */

require('dotenv').config();
const readline = require('readline');
const fetch = require('node-fetch');
const chalk = require('chalk');
const { OpenAI } = require('openai');
const { Anthropic } = require('@anthropic-ai/sdk');

// Configuration
const config = {
  serverUrl: process.env.WP_MCP_SERVER_URL || 'http://localhost:10005/wp-json/wpmcp/v1/mcp',
  apiKey: process.env.WP_MCP_API_KEY || '123456789',
  requestCounter: 1,
  
  // LLM Configuration
  llmProvider: 'none', // 'openai', 'anthropic', 'ollama', or 'none'
  openai: {
    apiKey: process.env.OPENAI_API_KEY || 'your-openai-api-key-here',
    model: process.env.OPENAI_MODEL || 'gpt-4-turbo'
  },
  anthropic: {
    apiKey: process.env.ANTHROPIC_API_KEY || 'your-anthropic-api-key-here',
    model: process.env.ANTHROPIC_MODEL || 'claude-3-haiku-20240307'
  },
  ollama: {
    apiUrl: process.env.OLLAMA_API_URL || 'http://localhost:11434',
    model: process.env.OLLAMA_MODEL || 'llama3'
  }
};

// Create readline interface for user input
const rl = readline.createInterface({
  input: process.stdin,
  output: process.stdout
});

// Initialize LLM clients
let openaiClient = null;
let anthropicClient = null;

function initializeLLMClients() {
  if (process.env.OPENAI_API_KEY && process.env.OPENAI_API_KEY !== 'your-openai-api-key-here') {
    openaiClient = new OpenAI({
      apiKey: config.openai.apiKey
    });
    config.llmProvider = 'openai';
    console.log(chalk.green('OpenAI API initialized.'));
  }
  
  if (process.env.ANTHROPIC_API_KEY && process.env.ANTHROPIC_API_KEY !== 'your-anthropic-api-key-here') {
    try {
      anthropicClient = new Anthropic({
        apiKey: config.anthropic.apiKey
      });
      config.llmProvider = 'anthropic';
      console.log(chalk.green('Anthropic API initialized.'));
      
      // Provide model-specific tips
      console.log(chalk.yellow(`Using Anthropic model: ${config.anthropic.model}`));
      console.log(chalk.yellow('Anthropic models use specialized JSON handling to ensure proper parsing.'));
      
      // Debug message regarding parsing improvements
      if (process.env.LLM_DEBUG === 'true' || process.env.DEBUG_LLM === 'true') {
        console.log(chalk.cyan('JSON parsing for Anthropic responses includes:'));
        console.log(chalk.cyan('- Direct JSON.parse() attempt'));
        console.log(chalk.cyan('- Regex-based JSON extraction for malformed responses'));
        console.log(chalk.cyan('- Fallback to pattern matching when necessary'));
        console.log(chalk.cyan('- Detailed error logging in debug mode'));
      }
    } catch (error) {
      console.error(chalk.red('Error initializing Anthropic client:'), error.message);
      console.log(chalk.yellow('Falling back to pattern matching for command processing.'));
      config.llmProvider = 'none';
    }
  }
  
  if (config.llmProvider === 'none' && process.env.OLLAMA_API_URL) {
    // Ollama doesn't require an API key, just a URL
    config.llmProvider = 'ollama';
    console.log(chalk.green('Ollama API initialized.'));
  }
  
  if (config.llmProvider === 'none') {
    console.log(chalk.yellow('No LLM provider configured. Using pattern matching for command processing.'));
    console.log(chalk.yellow('Configure an LLM provider in .env file for enhanced natural language understanding.'));
  }
}

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
 * Explain an endpoint using the LLM
 */
async function explainEndpoint(endpoint, provider = config.llmProvider) {
  // If LLM is not configured, provide a basic explanation
  if (provider === 'none') {
    const basicExplanations = {
      '/wp/v2/posts': 'WordPress Posts API endpoint for creating, reading, updating, and deleting posts.',
      '/wp/v2/pages': 'WordPress Pages API endpoint for managing pages.',
      '/wp/v2/categories': 'WordPress Categories API endpoint for managing post categories.',
      '/wp/v2/tags': 'WordPress Tags API endpoint for managing post tags.',
      '/wp/v2/users': 'WordPress Users API endpoint for managing users.',
      '/wp/v2/media': 'WordPress Media API endpoint for managing media files.',
      '/wp/v2/comments': 'WordPress Comments API endpoint for managing comments.',
      '/wp/v2/taxonomies': 'WordPress Taxonomies API endpoint for retrieving taxonomy information.',
      '/wp/v2/settings': 'WordPress Settings API endpoint for managing site settings.'
    };
    
    return basicExplanations[endpoint] || 
      `WordPress API endpoint ${endpoint}. Use the documentation for more details.`;
  }
  
  try {
    // First, get any information we can about the endpoint
    let endpointInfo = null;
    try {
      const discoverResult = await discoverEndpoints();
      if (discoverResult.result && discoverResult.result.data && discoverResult.result.data.endpoints) {
        endpointInfo = discoverResult.result.data.endpoints.find(ep => 
          ep.path === endpoint || ep.uri.includes(endpoint)
        );
      }
    } catch (error) {
      console.log(chalk.yellow('Could not retrieve endpoint details. Generating explanation based on endpoint name only.'));
    }
    
    // Prepare information for the LLM
    const prompt = endpointInfo 
      ? `Explain the WordPress REST API endpoint with path "${endpoint}".
         
         Available information:
         - Methods: ${endpointInfo.methods.join(', ')}
         - Namespace: ${endpointInfo.namespace}
         
         Provide a clear, concise explanation of what this endpoint does, what methods are supported,
         what data it returns or accepts, and some common use cases.`
      : `Explain the WordPress REST API endpoint with path "${endpoint}".
         
         Provide a clear, concise explanation of what this endpoint likely does based on its path,
         what methods it might support, what data it might return or accept, and some possible use cases.`;
    
    // Use our existing LLM infrastructure
    if (provider === 'openai' && openaiClient) {
      const response = await openaiClient.chat.completions.create({
        model: config.openai.model,
        messages: [
          { role: 'user', content: prompt }
        ],
        max_tokens: 500
      });
      return response.choices[0].message.content;
    } 
    else if (provider === 'anthropic' && anthropicClient) {
      const response = await anthropicClient.messages.create({
        model: config.anthropic.model,
        messages: [{ role: 'user', content: prompt }],
        max_tokens: 500
      });
      return response.content[0].text;
    } 
    else if (provider === 'ollama') {
      const response = await fetch(`${config.ollama.apiUrl}/api/chat`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          model: config.ollama.model,
          messages: [
            { role: 'user', content: prompt }
          ],
          stream: false,
          options: {
            temperature: 0.7
          }
        })
      });
      
      if (!response.ok) {
        throw new Error(`Ollama API error: ${response.statusText}`);
      }
      
      const data = await response.json();
      return data.message.content;
    }
    
    // Fallback if none of the above worked or provider not recognized
    return `WordPress API endpoint ${endpoint}. No detailed information available.`;
    
  } catch (error) {
    console.error(chalk.red('Error generating endpoint explanation:'), error);
    return `WordPress API endpoint ${endpoint}. Could not generate explanation due to an error.`;
  }
}

/**
 * Call the LLM API to process natural language
 */
async function callLLM(input) {
  // Enable debug mode from environment or config
  const debugMode = process.env.LLM_DEBUG === 'true' || !!process.env.DEBUG_LLM;
  
  // Base system prompt template
  let systemPromptTemplate = `
You are a helpful assistant that interprets natural language commands for a WordPress site.
Analyze the user's input and determine which command they want to execute.
Return a JSON object with the following structure:

{
  "command": "one of: list_posts, get_post, create_post, update_post, delete_post, discover_endpoints, explain_endpoint, help, exit, unknown",
  "parameters": {
    // Include parameters specific to the command
    // For list_posts: count (number)
    // For get_post, update_post, delete_post: postId (number)
    // For create_post: title, content, status (optional)
    // For update_post: can include title, content, status to pre-fill values
    // For explain_endpoint: endpoint (string) - the path of the endpoint to explain
  }
}

CRITICAL: You MUST return a valid "command" field using EXACTLY one of these values: list_posts, get_post, create_post, update_post, delete_post, discover_endpoints, explain_endpoint, help, exit, unknown.

IMPORTANT: Extract as much information as possible from the command:
- If user wants to create a post with a specific title, include the title in parameters
- If content is specified, include it in parameters
- If user specifies a post status (publish/draft), include it in parameters
- If user wants to explain an endpoint (e.g., "explain /wp/v2/posts endpoint"), extract the endpoint path

For compound requests (like "create a post with title X and content Y"), extract all relevant parameters.

Only return valid JSON without any explanation, markdown, or additional text.
Your response will fail if it doesn't include both the "command" and "parameters" fields in valid JSON format.
`;

  // Anthropic-specific additions to improve JSON compliance
  const anthropicPromptAddition = `
CRITICAL FOR ANTHROPIC CLAUDE:
1. You MUST return ONLY a valid JSON object
2. DO NOT include any text before or after the JSON
3. DO NOT include markdown code block formatting
4. DO NOT include any explanations
5. Make sure all strings are properly escaped with double quotes
6. Ensure the response can be parsed directly by JSON.parse()
7. If content includes quotes, newlines, or special characters, they MUST be properly escaped in the JSON
8. Your ONLY job is to analyze commands, NOT to generate creative content
9. For "create_post" commands, DO NOT write content in this response; just identify parameters and structure
10. NEVER put full blog posts or articles in the JSON response; use brief placeholders instead
11. Parameters should NOT exceed 500 characters for any single field
`;

  // Get the appropriate system prompt based on the provider
  const systemPrompt = config.llmProvider === 'anthropic' 
    ? systemPromptTemplate + anthropicPromptAddition 
    : systemPromptTemplate;

  /**
 * Create a best-effort command interpretation for models that fail to return valid JSON
 */
function createFallbackCommandInterpretation(input) {
  input = input.toLowerCase();
  
  // Simplified logic to determine the most likely command
  let command = 'unknown';
  let parameters = {};
  
  // Check for list posts
  if (input.match(/show|list|get|display/) && input.match(/recent|latest|posts/)) {
    command = 'list_posts';
    // Try to extract count
    const countMatch = input.match(/(\d+)\s+posts/);
    if (countMatch) {
      parameters.count = parseInt(countMatch[1]);
    }
  }
  // Check for get post by ID
  else if (input.match(/get|show|display|view/) && input.match(/post/) && input.match(/id|#|number/)) {
    command = 'get_post';
    // Try to extract ID
    const idMatch = input.match(/(\d+)/);
    if (idMatch) {
      parameters.postId = parseInt(idMatch[1]);
    }
  }
  // Check for create post
  else if (input.match(/create|write|add|new|make/) && input.match(/post|article|content|something|blog/)) {
    command = 'create_post';
    
    // Check for "write something about X" or "create content about Y" patterns
    const topicMatch = input.match(/(?:write|create|make)\s+(?:something|content|a\s+post|an\s+article)\s+(?:about|on)\s+([^.,!?]+)/i);
    if (topicMatch) {
      // For "write something about X" we should suggest a title based on the topic
      const topic = topicMatch[1].trim();
      parameters.title = `Article about ${topic}`;
      
      // For content generation requests, handle differently
      if (input.match(/engaging|long\s+form|detailed|comprehensive|in-depth/i)) {
        // Flag that this is a content generation request
        parameters.generateContent = true;
        parameters.topic = topic;
      }
    }
    
    // Try to extract explicitly specified title
    const titleMatch = input.match(/title\s*[":]\s*["']([^"']+)["']|title\s+([^"'\n,\.]+)|with\s+title\s+["']([^"']+)["']|with\s+title\s+([^"'\n,\.]+)/i);
    if (titleMatch) {
      parameters.title = titleMatch[1] || titleMatch[2] || titleMatch[3] || titleMatch[4];
    }
    
    // Try to extract content if mentioned
    const contentMatch = input.match(/content\s*[":]\s*["']([^"']+)["']|with\s+content\s+["']([^"']+)["']|with\s+(?:the\s+)?content\s+([^"'\n,\.]+)/i);
    if (contentMatch) {
      parameters.content = contentMatch[1] || contentMatch[2] || contentMatch[3];
    }
    
    // Try to extract status
    const statusMatch = input.match(/status\s*[":]\s*["']?(publish|draft)["']?/i);
    if (statusMatch) {
      parameters.status = statusMatch[1].toLowerCase();
    }
    
    // Special handling for "create title and content yourself" requests
    if (input.match(/(?:create|come up with|generate)\s+(?:the\s+)?(?:title|content)\s+(?:yourself|for me|on your own)/i)) {
      parameters.generateContent = true;
      
      // If no topic is specified yet, try to find one
      if (!parameters.topic) {
        const aboutMatch = input.match(/about\s+([^.,!?]+)/i);
        if (aboutMatch) {
          parameters.topic = aboutMatch[1].trim();
        }
      }
    }
  }
  // Check for update post
  else if (input.match(/update|edit|modify|change/) && input.match(/post|article/)) {
    command = 'update_post';
    // Try to extract ID
    const idMatch = input.match(/(\d+)/);
    if (idMatch) {
      parameters.postId = parseInt(idMatch[1]);
    }
    
    // Try to extract title
    const titleMatch = input.match(/title\s*[":]\s*["']([^"']+)["']|with\s+title\s+["']([^"']+)["']|title\s+to\s+["']([^"']+)["']/i);
    if (titleMatch) {
      parameters.title = titleMatch[1] || titleMatch[2] || titleMatch[3];
    }
    
    // Try to extract content
    const contentMatch = input.match(/content\s*[":]\s*["']([^"']+)["']|with\s+content\s+["']([^"']+)["']|content\s+to\s+["']([^"']+)["']/i);
    if (contentMatch) {
      parameters.content = contentMatch[1] || contentMatch[2] || contentMatch[3];
    }
    
    // Try to extract status
    const statusMatch = input.match(/status\s*[":]\s*["']?(publish|draft)["']?/i);
    if (statusMatch) {
      parameters.status = statusMatch[1].toLowerCase();
    }
  }
  // Check for delete post
  else if (input.match(/delete|remove|trash/) && input.match(/post|article/)) {
    command = 'delete_post';
    // Try to extract ID
    const idMatch = input.match(/(\d+)/);
    if (idMatch) {
      parameters.postId = parseInt(idMatch[1]);
    }
  }
  // Check for discover endpoints
  else if (input.match(/discover|list|show/) && input.match(/endpoints|capabilities|api/)) {
    command = 'discover_endpoints';
  }
  // Check for explain endpoint
  else if (input.match(/explain|describe|detail|tell me about|what is|how (to use|does)/) && 
           input.match(/endpoint|api|\/wp|rest/i)) {
    command = 'explain_endpoint';
    
    // Try to extract endpoint path
    const endpointMatch = input.match(/\/\w+\/[\w\/]+|\/\w+\/v\d+\/[\w\/]+/);
    if (endpointMatch) {
      parameters.endpoint = endpointMatch[0];
    } else if (input.includes('posts')) {
      parameters.endpoint = '/wp/v2/posts';
    } else if (input.includes('pages')) {
      parameters.endpoint = '/wp/v2/pages';
    } else if (input.includes('users')) {
      parameters.endpoint = '/wp/v2/users';
    } else if (input.includes('media')) {
      parameters.endpoint = '/wp/v2/media';
    } else if (input.includes('comments')) {
      parameters.endpoint = '/wp/v2/comments';
    } else if (input.includes('categories')) {
      parameters.endpoint = '/wp/v2/categories';
    } else if (input.includes('tags')) {
      parameters.endpoint = '/wp/v2/tags';
    }
  }
  // Check for help
  else if (input.match(/help|assist|guide|what can you do/)) {
    command = 'help';
  }
  // Check for exit
  else if (input.match(/exit|quit|bye|goodbye/)) {
    command = 'exit';
  }
  
  return { command, parameters };
}

try {
    let parsedResult = null;
    
    if (config.llmProvider === 'openai') {
      const response = await openaiClient.chat.completions.create({
        model: config.openai.model,
        messages: [
          { role: 'system', content: systemPrompt },
          { role: 'user', content: input }
        ],
        response_format: { type: 'json_object' }
      });
      
      parsedResult = JSON.parse(response.choices[0].message.content);
    } 
    else if (config.llmProvider === 'anthropic') {
      // Enable debug flag based on environment variables
      const debugMode = process.env.LLM_DEBUG === 'true' || !!process.env.DEBUG_LLM;
      
      try {
        const response = await anthropicClient.messages.create({
          model: config.anthropic.model,
          system: systemPrompt,
          messages: [{ role: 'user', content: input }],
          max_tokens: 1000
        });
        
        const responseText = response.content[0].text;
        
        // Always show raw response when in debug mode
        if (debugMode || process.env.LLM_ALWAYS_SHOW_RESPONSE === 'true') {
          console.log(chalk.gray('=== ANTHROPIC RAW RESPONSE ==='));
          console.log(chalk.white(responseText));
        }
        
        try {
          // Attempt direct JSON parsing
          console.log(chalk.yellow('Attempting direct JSON parsing of Anthropic response...'));
          parsedResult = JSON.parse(responseText);
          console.log(chalk.green('✓ Anthropic JSON parsing successful'));
          
          if (debugMode) {
            console.log(chalk.gray('=== PARSED RESULT ==='));
            console.log(chalk.white(JSON.stringify(parsedResult, null, 2)));
          }
        } catch (e) {
          // If direct parsing fails, try to extract JSON from the response
          console.log(chalk.red('✗ Direct JSON parsing failed: ' + e.message));
          console.log(chalk.yellow('Attempting to extract JSON using regex...'));
          
          // Try to extract JSON-like structure using regex
          const jsonRegex = /\{(?:[^{}]|(?:\{(?:[^{}]|(?:\{[^{}]*\}))*\}))*\}/g;
          const matches = responseText.match(jsonRegex);
          
          if (matches && matches.length > 0) {
            console.log(chalk.yellow(`Found ${matches.length} possible JSON matches`));
            
            // Always show at least the first match when debugging or with the verbose flag
            if (debugMode || input.toLowerCase().includes('--debug') || input.toLowerCase().includes('--verbose')) {
              matches.forEach((match, i) => {
                console.log(chalk.gray(`=== MATCH ${i+1} ===`));
                console.log(chalk.white(match));
              });
            }
            
            try {
              // Try the first match that seems most promising
              parsedResult = JSON.parse(matches[0]);
              console.log(chalk.green('✓ JSON extraction successful'));
              
              if (debugMode) {
                console.log(chalk.gray('=== EXTRACTED RESULT ==='));
                console.log(chalk.white(JSON.stringify(parsedResult, null, 2)));
              }
            } catch (extractError) {
              console.log(chalk.red('✗ JSON extraction failed: ' + extractError.message));
              console.log(chalk.yellow('Using fallback command interpreter'));
              
              // Always print first part of the response when JSON extraction fails
              console.log(chalk.red('=== FIRST 500 CHARS OF CONTENT THAT FAILED PARSING ==='));
              console.log(chalk.white(responseText.substring(0, 500) + (responseText.length > 500 ? '...' : '')));
              
              parsedResult = createFallbackCommandInterpretation(input);
              
              if (debugMode) {
                console.log(chalk.gray('=== FALLBACK RESULT ==='));
                console.log(chalk.white(JSON.stringify(parsedResult, null, 2)));
                console.log(chalk.gray('=== FULL CONTENT THAT FAILED PARSING ==='));
                console.log(chalk.white(responseText));
              }
            }
          } else {
            // No JSON-like content found, use our fallback interpreter
            console.log(chalk.red('✗ No JSON structure found in Anthropic response'));
            console.log(chalk.yellow('Using fallback command interpreter'));
            
            // Always print first part of the response when JSON extraction fails
            console.log(chalk.red('=== FIRST 500 CHARS OF CONTENT THAT FAILED PARSING ==='));
            console.log(chalk.white(responseText.substring(0, 500) + (responseText.length > 500 ? '...' : '')));
            
            parsedResult = createFallbackCommandInterpretation(input);
            
            if (debugMode) {
              console.log(chalk.gray('=== FALLBACK RESULT ==='));
              console.log(chalk.white(JSON.stringify(parsedResult, null, 2)));
              console.log(chalk.gray('=== FULL CONTENT THAT FAILED PARSING ==='));
              console.log(chalk.white(responseText));
            }
          }
        }
      } catch (error) {
        console.error(chalk.red('Error with Anthropic API:'), error);
        return null;
      }
    } 
    else if (config.llmProvider === 'ollama') {
      console.log(chalk.cyan(`Using Ollama model: ${config.ollama.model}`));
      
      // Different models may require different prompting strategies
      let adaptedSystemPrompt = systemPrompt;
      
      // Add specific handling for different model families
      if (config.ollama.model.includes('deepseek')) {
        console.log(chalk.yellow('Using DeepSeek-specific prompt format'));
        // For deepseek models, simplify the prompt and ask explicitly for JSON
        adaptedSystemPrompt = `
You are a command interpreter for a WordPress site.
Analyze the user's command and return JSON in this exact format:
{"command": "COMMAND_VALUE", "parameters": {}}

The COMMAND_VALUE must be EXACTLY one of these:
list_posts, get_post, create_post, update_post, delete_post, discover_endpoints, explain_endpoint, help, exit, unknown

Parameters should include:
- For list_posts: count (number)
- For get_post, update_post, delete_post: postId (number)
- For create_post: title, content, status (optional)
- For update_post: can include title, content, status to pre-fill values
- For explain_endpoint: endpoint (string) - the path of the endpoint to explain

CRITICAL: You MUST specify a command field. It is the most important part of your response.
If you're unsure, use "unknown" as the command value, but never omit the command field.

IMPORTANT: Extract everything possible from the command:
- If a post title is specified, include it in parameters
- If content is mentioned, include it in parameters 
- If post status is specified, include it in parameters
- If user wants information about an endpoint, use explain_endpoint command

For compound requests (like "create a post with title X and content Y"), extract all parameters.

Return ONLY valid JSON with NO additional text, markdown, or explanations.
        `;
      } else if (config.ollama.model.includes('gemma') || config.ollama.model.includes('qwen')) {
        console.log(chalk.yellow('Using Gemma/Qwen-specific prompt format'));
        // For gemma and qwen models, make instructions more explicit
        adaptedSystemPrompt = `
You are a command interpreter for a WordPress site.
Your job is to convert natural language into a specific command format.

Return JSON in this exact format:
{"command": "COMMAND_VALUE", "parameters": {}}

The COMMAND_VALUE must be EXACTLY one of these:
list_posts, get_post, create_post, update_post, delete_post, discover_endpoints, explain_endpoint, help, exit, unknown

CRITICAL: You MUST specify a command field. It is the most important part of your response.
If you're unsure, use "unknown" as the command value, but never omit the command field.

Extract parameters from the request:
- For list_posts: count (number)  
- For get_post, update_post, delete_post: postId (number)
- For create_post: title, content, status (optional)
- For update_post: title, content, status to pre-fill values
- For explain_endpoint: endpoint (string)

IMPORTANT: You must ONLY return valid JSON. Do not include any explanation, thinking, or other text.
Your entire response must be parseable as JSON.
`;
      }
      
      if (debugMode) {
        console.log(chalk.gray('=== SYSTEM PROMPT ==='));
        console.log(chalk.gray(adaptedSystemPrompt));
        console.log(chalk.gray('=== USER INPUT ==='));
        console.log(chalk.gray(input));
      }
      
      // Prepare request data
      const requestData = {
        model: config.ollama.model,
        messages: [
          { role: 'system', content: adaptedSystemPrompt },
          { role: 'user', content: input }
        ],
        stream: false,
        options: {
          temperature: 0.1 // Lower temperature for more deterministic responses
        }
      };
      
      if (debugMode) {
        console.log(chalk.gray('=== API REQUEST ==='));
        console.log(chalk.gray(JSON.stringify(requestData, null, 2)));
      }
      
      console.log(chalk.yellow('Sending request to Ollama API...'));
      const startTime = Date.now();
      
      const response = await fetch(`${config.ollama.apiUrl}/api/chat`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(requestData)
      });
      
      const responseTime = Date.now() - startTime;
      console.log(chalk.yellow(`Ollama response received in ${responseTime}ms`));
      
      if (!response.ok) {
        throw new Error(`Ollama API error: ${response.statusText}`);
      }
      
      const data = await response.json();
      const content = data.message.content;
      
      // Always show raw response when LLM_ALWAYS_SHOW_RESPONSE is set
      if (debugMode || process.env.LLM_ALWAYS_SHOW_RESPONSE === 'true') {
        console.log(chalk.gray('=== RAW RESPONSE ==='));
        console.log(chalk.white(content));
      }
      
      // Store the original response for debug purposes
      const originalContent = content;
      
      try {
        // Attempt to parse directly
        console.log(chalk.yellow('Attempting direct JSON parsing...'));
        parsedResult = JSON.parse(content);
        console.log(chalk.green('✓ JSON parsing successful'));
        
        if (debugMode) {
          console.log(chalk.gray('=== PARSED RESULT ==='));
          console.log(chalk.white(JSON.stringify(parsedResult, null, 2)));
        }
      } catch (e) {
        // If direct parsing fails, try to extract JSON from the response
        console.log(chalk.red('✗ Direct JSON parsing failed: ' + e.message));
        console.log(chalk.yellow('Attempting to extract JSON using regex...'));
        
        // Try to extract JSON-like structure using regex
        const jsonRegex = /\{(?:[^{}]|(?:\{(?:[^{}]|(?:\{[^{}]*\}))*\}))*\}/g;
        const matches = content.match(jsonRegex);
        
        if (matches && matches.length > 0) {
          console.log(chalk.yellow(`Found ${matches.length} possible JSON matches`));
          
          if (debugMode) {
            matches.forEach((match, i) => {
              console.log(chalk.gray(`=== MATCH ${i+1} ===`));
              console.log(chalk.white(match));
            });
          }
          
          try {
            // Try the first match that seems most promising
            parsedResult = JSON.parse(matches[0]);
            console.log(chalk.green('✓ JSON extraction successful'));
            
            if (debugMode) {
              console.log(chalk.gray('=== EXTRACTED RESULT ==='));
              console.log(chalk.white(JSON.stringify(parsedResult, null, 2)));
            }
          } catch (extractError) {
            console.log(chalk.red('✗ JSON extraction failed: ' + extractError.message));
            console.log(chalk.yellow('Using fallback command interpreter'));
            parsedResult = createFallbackCommandInterpretation(input);
            
            if (debugMode) {
              console.log(chalk.gray('=== FALLBACK RESULT ==='));
              console.log(chalk.white(JSON.stringify(parsedResult, null, 2)));
              
              // Show raw content for debugging
              console.log(chalk.yellow('=== ORIGINAL CONTENT THAT FAILED PARSING ==='));
              console.log(chalk.white(originalContent));
            }
          }
        } else {
          // No JSON-like content found, use our fallback interpreter
          console.log(chalk.red('✗ No JSON structure found in response'));
          console.log(chalk.yellow('Using fallback command interpreter'));
          parsedResult = createFallbackCommandInterpretation(input);
          
          if (debugMode) {
            console.log(chalk.gray('=== FALLBACK RESULT ==='));
            console.log(chalk.white(JSON.stringify(parsedResult, null, 2)));
            
            // Show raw content for debugging
            console.log(chalk.yellow('=== ORIGINAL CONTENT THAT FAILED PARSING ==='));
            console.log(chalk.white(originalContent));
          }
        }
      }
    }
    
    return parsedResult;
  } catch (error) {
    console.error(chalk.red('Error processing with LLM:'), error);
    return null;
  }
}

/**
 * Process natural language commands
 */
async function processCommand(input, retryCount = 0) {
  // Basic commands that don't need LLM
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
  
  // Configure LLM provider
  if (command === 'config llm' || command === 'configure llm') {
    configureLLM();
    return true; // Indicate that we're handling input asynchronously
  }

  // Use LLM for command interpretation if available
  if (config.llmProvider !== 'none') {
    // If we're retrying, indicate attempt number
    if (retryCount > 0) {
      console.log(chalk.yellow(`Retry attempt ${retryCount}/3 using LLM (${config.llmProvider})...`));
    } else {
      console.log(chalk.yellow(`Processing your request using LLM (${config.llmProvider})...`));
    }
    
    // Enable debug flag based on environment variables or if the command contains "debug" keyword
    const debugThis = process.env.LLM_DEBUG === 'true' || 
                      process.env.DEBUG_LLM === 'true' || 
                      input.toLowerCase().includes('debug') ||
                      input.toLowerCase().includes('--verbose');
                      
    if (debugThis && !process.env.LLM_DEBUG) {
      process.env.LLM_DEBUG = 'true';
      console.log(chalk.green('Debug mode enabled for this request'));
    }
    
    const llmResult = await callLLM(input);
    
    // Reset debug flag if it was temporarily enabled
    if (debugThis && !process.env.DEBUG_LLM) {
      process.env.LLM_DEBUG = '';
    }
    
    // Enable debug mode from environment or command
    const debugMode = process.env.LLM_DEBUG === 'true' || !!process.env.DEBUG_LLM;

    if (llmResult) {
      if (llmResult.command) {
        // Command was successfully interpreted
        console.log(chalk.green(`LLM interpreted command: ${llmResult.command}`));
        if (Object.keys(llmResult.parameters || {}).length > 0) {
          console.log(chalk.green(`With parameters: ${JSON.stringify(llmResult.parameters)}`));
        }
        return await executeCommand(llmResult.command, llmResult.parameters || {});
      } else {
        // Command was undefined or null
        if (retryCount < 3) {
          // Still have retries left
          console.log(chalk.yellow(`Command not recognized. Retrying (${retryCount+1}/3)...`));
          
          // If debug is enabled, show the raw LLM output
          if (debugMode || input.toLowerCase().includes('--debug') || input.toLowerCase().includes('--verbose')) {
            console.log(chalk.yellow('=== RAW LLM RESPONSE ==='));
            console.log(chalk.white(JSON.stringify(llmResult, null, 2)));
          }
          
          // For retries, we might want to use a slightly modified input to help the LLM understand better
          let retryInput = input;
          if (retryCount === 1) {
            retryInput = `Please interpret this command for WordPress: ${input}`;
          } else if (retryCount === 2) {
            retryInput = `I need you to extract a command and parameters from this request: ${input}`;
          }
          
          // Retry with incremented counter
          return await processCommand(retryInput, retryCount + 1);
        } else {
          // Used all retries, now fall back to pattern matching
          console.log(chalk.red('LLM could not determine a valid command after 3 attempts.'));
          
          // If debug is enabled, show the raw LLM output
          if (debugMode || input.toLowerCase().includes('--debug') || input.toLowerCase().includes('--verbose')) {
            console.log(chalk.yellow('=== RAW LLM RESPONSE (FINAL ATTEMPT) ==='));
            console.log(chalk.white(JSON.stringify(llmResult, null, 2)));
          }
          
          console.log(chalk.yellow('Falling back to pattern matching.'));
        }
      }
    } else {
      if (retryCount < 3) {
        // Still have retries left
        console.log(chalk.yellow(`LLM failed to respond. Retrying (${retryCount+1}/3)...`));
        
        // If debug is enabled, say so
        if (debugMode || input.toLowerCase().includes('--debug') || input.toLowerCase().includes('--verbose')) {
          console.log(chalk.yellow('LLM returned no response or encountered an error.'));
        }
        
        // Retry with incremented counter
        return await processCommand(input, retryCount + 1);
      } else {
        // Used all retries, now fall back to pattern matching
        console.log(chalk.red('Failed to process with LLM after 3 attempts. Falling back to pattern matching.'));
        
        // If debug is enabled, say so
        if (debugMode || input.toLowerCase().includes('--debug') || input.toLowerCase().includes('--verbose')) {
          console.log(chalk.yellow('LLM returned no response or encountered an error.'));
          console.log(chalk.yellow('Run with DEBUG_LLM=true for more detailed information.'));
        }
      }
    }
    // Continue with pattern matching
  }
  
  // Pattern matching fallback
  
  // Get recent posts
  if (command.match(/show|list|get|display/) && command.match(/recent|latest|posts/)) {
    const countMatch = command.match(/(\d+)\s+posts/);
    const count = countMatch ? parseInt(countMatch[1]) : 5;
    return await executeCommand('list_posts', { count });
  }
  
  // Get post by ID
  if (command.match(/get|show|display|view/) && command.match(/post/) && command.match(/id\s*:\s*\d+|id\s+\d+|post\s+\d+/)) {
    const idMatch = command.match(/id\s*:\s*(\d+)|id\s+(\d+)|post\s+(\d+)/);
    const postId = idMatch[1] || idMatch[2] || idMatch[3];
    return await executeCommand('get_post', { postId });
  }
  
  // Create a new post
  if (command.match(/create|write|add|new/) && command.match(/post|article/)) {
    return await executeCommand('create_post');
  }
  
  // Update an existing post
  if (command.match(/update|edit|modify|change/) && command.match(/post/) && command.match(/id\s*:\s*\d+|id\s+\d+|post\s+\d+/)) {
    const idMatch = command.match(/id\s*:\s*(\d+)|id\s+(\d+)|post\s+(\d+)/);
    const postId = idMatch[1] || idMatch[2] || idMatch[3];
    return await executeCommand('update_post', { postId });
  }
  
  // Delete a post
  if (command.match(/delete|remove|trash/) && command.match(/post/) && command.match(/id\s*:\s*\d+|id\s+\d+|post\s+\d+/)) {
    const idMatch = command.match(/id\s*:\s*(\d+)|id\s+(\d+)|post\s+(\d+)/);
    const postId = idMatch[1] || idMatch[2] || idMatch[3];
    return await executeCommand('delete_post', { postId });
  }
  
  // Discover endpoints
  if (command.match(/discover|list|get|show/) && command.match(/endpoints|tools|capabilities/)) {
    return await executeCommand('discover_endpoints');
  }
  
  // Command not recognized
  console.log(chalk.yellow('Command not recognized. Type "help" for available commands.'));
}

/**
 * Check if a request is a multi-action request with multiple prefilled parameters
 */
function isMultiActionRequest(parameters = {}) {
  // If we have 2 or more prefilled parameters, it's likely a multi-action request
  const filledParamCount = Object.keys(parameters).filter(key => 
    parameters[key] !== undefined && parameters[key] !== null && parameters[key] !== ''
  ).length;
  
  return filledParamCount >= 2;
}

/**
 * Execute a parsed command
 */
async function executeCommand(command, parameters = {}) {
  switch (command) {
    case 'list_posts':
      console.log(chalk.yellow('Fetching recent posts...'));
      const count = parameters.count || 5;
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
      return false;
    
    case 'get_post':
      const postId = parameters.postId;
      console.log(chalk.yellow(`Fetching post with ID: ${postId}...`));
      
      const postResult = await getPost(postId);
      
      if (postResult.result && postResult.result.data) {
        const post = postResult.result.data;
        console.log(chalk.green(`Post: ${post.title.rendered}`));
        console.log(chalk.gray(`URL: ${post.link}`));
        console.log(chalk.gray(`Status: ${post.status}`));
        console.log(chalk.gray(`Date: ${new Date(post.date).toLocaleString()}`));
        console.log(chalk.white('\nContent:'));
        console.log(chalk.white(post.content.raw || 'No content'));
      } else {
        console.log(chalk.red('Error fetching post:'), postResult.error || 'Unknown error');
      }
      return false;
    
    case 'create_post':
      console.log(chalk.yellow('Creating a new post...'));
      
      // Check for pre-filled parameters from LLM
      const prefilledTitle = parameters.title || '';
      const prefilledContent = parameters.content || '';
      const prefilledStatus = parameters.status || '';
      const generateContent = parameters.generateContent || false;
      const topic = parameters.topic || '';
      
      // Special handling for content generation requests
      if (generateContent) {
        console.log(chalk.yellow('This appears to be a content generation request.'));
        
        // If we have a topic, suggest it as the starting point
        if (topic) {
          console.log(chalk.cyan(`Topic: ${topic}`));
          
          // If there's a pre-filled title based on the topic, suggest it
          if (prefilledTitle && prefilledTitle.includes(topic)) {
            console.log(chalk.cyan(`Suggested title: "${prefilledTitle}"`));
          }
        }
        
        // Ask if the user wants to create content manually
        rl.question(chalk.green('Would you like to create a new post with AI-generated content? (yes/no): '), async (response) => {
          if (response.toLowerCase() === 'yes' || response.toLowerCase() === 'y') {
            // Proceed with post creation, starting with title
            const titlePrompt = prefilledTitle ? 
              `Title (${prefilledTitle}): ` : 
              'Enter title for your post: ';
              
            rl.question(chalk.cyan(titlePrompt), (titleInput) => {
              const title = titleInput.trim() || prefilledTitle;
              
              if (!title) {
                console.log(chalk.red('Title cannot be empty. Post creation cancelled.'));
                promptForCommand();
                return;
              }
              
              // Now prompt for content
              console.log(chalk.yellow('Please enter content for your post. Content generation was requested, but you need to provide it manually.'));
              console.log(chalk.yellow('Use the LLM to generate content in a separate workflow if needed.'));
              
              rl.question(chalk.cyan('Content: '), (contentInput) => {
                const content = contentInput.trim();
                
                if (!content) {
                  console.log(chalk.red('Content cannot be empty. Post creation cancelled.'));
                  promptForCommand();
                  return;
                }
                
                // Finally ask for status
                const statusPrompt = prefilledStatus ? 
                  `Status (publish/draft) (${prefilledStatus}): ` : 
                  'Status (publish/draft): ';
                  
                rl.question(chalk.cyan(statusPrompt), async (statusInput) => {
                  // Use input or pre-filled value or default to draft
                  let status = statusInput.trim() || prefilledStatus || 'draft';
                  
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
          } else {
            console.log(chalk.blue('Post creation cancelled.'));
            promptForCommand();
          }
        });
        
        return true; // Indicate async handling
      }
      
      // Standard flow for non-content-generation requests
      // Check if this is a multi-action request with multiple prefilled parameters
      const isCreateMultiAction = isMultiActionRequest(parameters);
      
      // Display pre-filled values
      if (prefilledTitle) {
        console.log(chalk.cyan(`Pre-filled title: "${prefilledTitle}"`));
      }
      if (prefilledContent) {
        console.log(chalk.cyan(`Pre-filled content: ${prefilledContent.length > 30 ? prefilledContent.substring(0, 30) + '...' : prefilledContent}`));
      }
      if (prefilledStatus) {
        console.log(chalk.cyan(`Pre-filled status: ${prefilledStatus}`));
      }
      
      // For multi-action requests, ask if user wants to auto-accept prefilled values
      if (isCreateMultiAction && (prefilledTitle && prefilledContent)) {
        // If we have all essential values, offer to auto-accept
        const confirmMessage = `This appears to be a multi-action request. Do you want to auto-accept the prefilled values? (yes/no): `;
        
        rl.question(chalk.green(confirmMessage), async (confirmation) => {
          if (confirmation.toLowerCase() === 'yes' || confirmation.toLowerCase() === 'y') {
            // Auto-accept all prefilled values
            console.log(chalk.green('Using all prefilled values automatically.'));
            
            // Set default status if not provided
            let status = prefilledStatus || 'draft';
            if (status !== 'publish' && status !== 'draft') {
              status = 'draft';
            }
            
            const result = await createPost(prefilledTitle, prefilledContent, status);
            
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
            return;
          } else {
            // Proceed with standard prompts for each field
            console.log(chalk.yellow('Proceeding with manual confirmation for each field.'));
            promptForPostDetails();
          }
        });
        
        return true; // Indicate async handling
      } else {
        // Not a multi-action request, proceed with standard prompts
        promptForPostDetails();
      }
      
      // Helper function to prompt for post details
      function promptForPostDetails() {
        // Ask for title (with pre-filled value if available)
        const titlePrompt = prefilledTitle ? 
          `Title (${prefilledTitle}): ` : 
          'Title: ';
          
        rl.question(chalk.cyan(titlePrompt), async (titleInput) => {
          // Use input or pre-filled value
          const title = titleInput.trim() || prefilledTitle;
          
          if (!title) {
            console.log(chalk.red('Title cannot be empty. Post creation cancelled.'));
            promptForCommand();
            return;
          }
          
          // Ask for content (with pre-filled value if available)
          const contentPrompt = prefilledContent ? 
            `Content (pre-filled): ` : 
            'Content: ';
            
          rl.question(chalk.cyan(contentPrompt), async (contentInput) => {
            // Use input or pre-filled value
            const content = contentInput.trim() || prefilledContent;
            
            if (!content) {
              console.log(chalk.red('Content cannot be empty. Post creation cancelled.'));
              promptForCommand();
              return;
            }
            
            // Ask for status (with pre-filled value if available)
            const statusPrompt = prefilledStatus ? 
              `Status (publish/draft) (${prefilledStatus}): ` : 
              'Status (publish/draft): ';
              
            rl.question(chalk.cyan(statusPrompt), async (statusInput) => {
              // Use input or pre-filled value or default to draft
              let status = statusInput.trim() || prefilledStatus || 'draft';
              
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
      }
      
      return true; // Indicate async handling
    
    case 'update_post':
      const updatePostId = parameters.postId;
      
      if (!updatePostId) {
        console.log(chalk.red('Error: Post ID is required for updating a post.'));
        console.log(chalk.yellow('Use command like: "update post 123" or "edit post with ID 123"'));
        return false;
      }
      
      console.log(chalk.yellow(`Updating post with ID: ${updatePostId}...`));
      
      // Check for pre-filled values
      const updatedTitle = parameters.title || '';
      const updatedContent = parameters.content || '';
      const updatedStatus = parameters.status || '';
      
      // Check if this is a multi-action request with multiple prefilled parameters
      const isUpdateMultiAction = isMultiActionRequest(parameters);
      
      // Display pre-filled values if present
      if (updatedTitle) {
        console.log(chalk.cyan(`Pre-filled new title: "${updatedTitle}"`));
      }
      if (updatedContent) {
        console.log(chalk.cyan(`Pre-filled new content: ${updatedContent.length > 30 ? updatedContent.substring(0, 30) + '...' : updatedContent}`));
      }
      if (updatedStatus) {
        console.log(chalk.cyan(`Pre-filled new status: "${updatedStatus}"`));
      }
      
      // First, get the current post
      const getResult = await getPost(updatePostId);
      
      if (!getResult.result || !getResult.result.data) {
        console.log(chalk.red('Error fetching post:'), getResult.error || 'Unknown error');
        return false;
      }
      
      const post = getResult.result.data;
      console.log(chalk.green(`Editing post: ${post.title.rendered}`));
      
      // For multi-action requests, ask if user wants to auto-accept prefilled values
      if (isUpdateMultiAction && (updatedTitle || updatedContent || updatedStatus)) {
        const confirmMessage = `This appears to be a multi-action request. Do you want to auto-accept the prefilled values? (yes/no): `;
        
        rl.question(chalk.green(confirmMessage), async (confirmation) => {
          if (confirmation.toLowerCase() === 'yes' || confirmation.toLowerCase() === 'y') {
            // Auto-accept all prefilled values
            console.log(chalk.green('Using all prefilled values automatically.'));
            
            // Use prefilled or current values
            const title = updatedTitle || post.title.raw;
            const content = updatedContent || post.content.raw;
            let status = updatedStatus || post.status;
            
            if (status !== 'publish' && status !== 'draft' && status !== post.status) {
              status = post.status;
            }
            
            const updates = {
              title: title,
              content: content,
              status: status
            };
            
            // Show summary of changes
            console.log(chalk.cyan('Making the following changes:'));
            if (title !== post.title.raw) {
              console.log(chalk.white(`- Title: "${post.title.raw}" → "${title}"`));
            }
            if (content !== post.content.raw) {
              console.log(chalk.white('- Content updated'));
            }
            if (status !== post.status) {
              console.log(chalk.white(`- Status: "${post.status}" → "${status}"`));
            }
            
            const result = await updatePost(updatePostId, updates);
            
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
          } else {
            // Proceed with standard prompts for each field
            console.log(chalk.yellow('Proceeding with manual confirmation for each field.'));
            promptForPostUpdates();
          }
        });
        
        return true; // Indicate async handling
      } else {
        // Not a multi-action request, proceed with standard prompts
        promptForPostUpdates();
      }
      
      // Helper function to prompt for post updates
      function promptForPostUpdates() {
        // Ask for new title or keep current
        const updateTitlePrompt = updatedTitle ?
          `New title (current: "${post.title.raw}") [Enter for current, or use "${updatedTitle}"]: ` :
          `New title (current: "${post.title.raw}") [press Enter to keep current]: `;
          
        rl.question(chalk.cyan(updateTitlePrompt), async (titleInput) => {
          // Use input or pre-filled value or keep current
          let title;
          if (titleInput.trim()) {
            title = titleInput.trim();
          } else if (updatedTitle) {
            console.log(chalk.cyan(`Using pre-filled title: "${updatedTitle}"`));
            title = updatedTitle;
          } else {
            title = post.title.raw;
          }
          
          // Ask for new content or keep current
          const updateContentPrompt = updatedContent ?
            'New content [Enter for current, or use pre-filled content]: ' :
            'New content [press Enter to keep current]:\n';
            
          rl.question(chalk.cyan(updateContentPrompt), async (contentInput) => {
            // Use input or pre-filled value or keep current
            let content;
            if (contentInput.trim()) {
              content = contentInput.trim();
            } else if (updatedContent) {
              console.log(chalk.cyan('Using pre-filled content'));
              content = updatedContent;
            } else {
              content = post.content.raw;
            }
            
            // Ask for new status or keep current
            const updateStatusPrompt = updatedStatus ?
              `New status (current: "${post.status}") [Enter for current, use "${updatedStatus}", or publish/draft]: ` :
              `New status (current: "${post.status}") [publish/draft or press Enter to keep current]: `;
              
            rl.question(chalk.cyan(updateStatusPrompt), async (statusInput) => {
              // Use input or pre-filled value or keep current
              let status;
              if (statusInput.trim()) {
                status = statusInput.trim().toLowerCase();
                if (status !== 'publish' && status !== 'draft') {
                  status = post.status;
                  console.log(chalk.yellow(`Invalid status. Using current status: ${status}`));
                }
              } else if (updatedStatus) {
                console.log(chalk.cyan(`Using pre-filled status: "${updatedStatus}"`));
                status = updatedStatus;
              } else {
                status = post.status;
              }
              
              const updates = {
                title: title,
                content: content,
                status: status
              };
              
              // Show summary of changes
              console.log(chalk.cyan('Making the following changes:'));
              if (title !== post.title.raw) {
                console.log(chalk.white(`- Title: "${post.title.raw}" → "${title}"`));
              }
              if (content !== post.content.raw) {
                console.log(chalk.white('- Content updated'));
              }
              if (status !== post.status) {
                console.log(chalk.white(`- Status: "${post.status}" → "${status}"`));
              }
              
              const result = await updatePost(updatePostId, updates);
              
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
      }
      
      return true; // Indicate async handling
    
    case 'delete_post':
      const deletePostId = parameters.postId;
      
      if (!deletePostId) {
        console.log(chalk.red('Error: Post ID is required for deleting a post.'));
        console.log(chalk.yellow('Use command like: "delete post 123" or "remove post with ID 123"'));
        return false;
      }
      
      console.log(chalk.yellow(`Deleting post with ID: ${deletePostId}...`));
      
      // Check if this is a multi-action request with force parameter
      const isDeleteMultiAction = isMultiActionRequest(parameters);
      
      // If force parameter is present (for automated deletion)
      if (parameters.force === true || parameters.confirm === true) {
        console.log(chalk.yellow('Force delete parameter detected. Proceeding without confirmation.'));
        
        const result = await deletePost(deletePostId);
        
        if (result.result && result.result.data) {
          console.log(chalk.green(`Post #${deletePostId} deleted successfully!`));
        } else {
          console.log(chalk.red('Error deleting post:'), result.error || 'Unknown error');
        }
        
        return false;
      }
      
      // First fetch the post to show details before deletion
      try {
        const postCheck = await getPost(deletePostId);
        if (postCheck.result && postCheck.result.data) {
          const post = postCheck.result.data;
          console.log(chalk.cyan(`Post to delete: ${post.title.rendered}`));
          console.log(chalk.gray(`Status: ${post.status}`));
          console.log(chalk.gray(`Date: ${new Date(post.date).toLocaleString()}`));
          
          // For multi-action requests, offer to auto-confirm deletion
          if (isDeleteMultiAction) {
            const confirmMessage = `This appears to be a multi-action request. Do you want to proceed with deletion without further confirmation? (yes/no): `;
            
            rl.question(chalk.green(confirmMessage), async (confirmation) => {
              if (confirmation.toLowerCase() === 'yes' || confirmation.toLowerCase() === 'y') {
                // Auto-confirm deletion
                console.log(chalk.green('Proceeding with deletion automatically.'));
                
                const result = await deletePost(deletePostId);
                
                if (result.result && result.result.data) {
                  console.log(chalk.green(`Post #${deletePostId} deleted successfully!`));
                } else {
                  console.log(chalk.red('Error deleting post:'), result.error || 'Unknown error');
                }
                
                promptForCommand();
              } else {
                // Proceed with standard confirmation
                confirmDeletion();
              }
            });
            
            return true; // Indicate async handling
          } else {
            // Not a multi-action request, proceed with standard confirmation
            confirmDeletion();
          }
        } else {
          // Post not found, just ask for standard confirmation
          confirmDeletion();
        }
      } catch (error) {
        // Just continue with standard confirmation if we can't get the post details
        confirmDeletion();
      }
      
      // Helper function to confirm deletion
      function confirmDeletion() {
        rl.question(chalk.red(`Are you sure you want to delete post #${deletePostId}? (yes/no): `), async (answer) => {
          if (answer.toLowerCase() !== 'yes' && answer.toLowerCase() !== 'y') {
            console.log(chalk.blue('Deletion cancelled.'));
            promptForCommand();
            return;
          }
          
          const result = await deletePost(deletePostId);
          
          if (result.result && result.result.data) {
            console.log(chalk.green(`Post #${deletePostId} deleted successfully!`));
          } else {
            console.log(chalk.red('Error deleting post:'), result.error || 'Unknown error');
          }
          
          promptForCommand();
        });
      }
      
      return true; // Indicate async handling
    
    case 'discover_endpoints':
      console.log(chalk.yellow('Discovering available endpoints...'));
      
      const endpointsResult = await discoverEndpoints();
      
      if (endpointsResult.result && endpointsResult.result.data) {
        console.log(chalk.green('Available endpoints:'));
        endpointsResult.result.data.endpoints.forEach(endpoint => {
          console.log(chalk.cyan(`Path: ${endpoint.path}`));
          console.log(chalk.gray(`Methods: ${endpoint.methods.join(', ')}`));
          console.log(chalk.gray(`Namespace: ${endpoint.namespace}`));
          console.log(chalk.gray(`URI: ${endpoint.uri}`));
          console.log(chalk.gray(`Use "explain ${endpoint.path}" for more details`));
          console.log(); // Empty line for better readability
        });
      } else {
        console.log(chalk.red('Error discovering endpoints:'), endpointsResult.error || 'Unknown error');
      }
      return false;
    
    case 'explain_endpoint':
      // Check if endpoint is provided
      if (!parameters.endpoint) {
        // If no endpoint is specified, ask user for one
        console.log(chalk.yellow('Which endpoint would you like to know more about?'));
        console.log(chalk.yellow('For example: /wp/v2/posts, /wp/v2/pages, etc.'));
        
        // Use the discover endpoint to show available endpoints
        try {
          const result = await discoverEndpoints();
          if (result.result && result.result.data) {
            console.log(chalk.green('\nAvailable endpoints:'));
            // Display just a few common endpoints to avoid overwhelming
            const commonEndpoints = result.result.data.endpoints
              .filter(ep => ep.path.startsWith('/wp/v2/'))
              .slice(0, 5);
              
            commonEndpoints.forEach(endpoint => {
              console.log(chalk.cyan(`- ${endpoint.path}`));
            });
            
            if (result.result.data.endpoints.length > commonEndpoints.length) {
              console.log(chalk.gray(`... and ${result.result.data.endpoints.length - commonEndpoints.length} more`));
              console.log(chalk.gray('Use "discover endpoints" to see all available endpoints'));
            }
          }
        } catch (error) {
          // Ignore errors here
        }
        
        rl.question(chalk.cyan('Endpoint path: '), async (endpoint) => {
          if (endpoint.trim()) {
            // Add leading slash if missing
            if (!endpoint.startsWith('/')) {
              endpoint = '/' + endpoint;
            }
            
            console.log(chalk.yellow(`Explaining endpoint: ${endpoint}...`));
            const explanation = await explainEndpoint(endpoint);
            console.log(chalk.green('\nEndpoint Explanation:'));
            console.log(chalk.white(explanation));
          } else {
            console.log(chalk.red('No endpoint specified. Explanation cancelled.'));
          }
          
          promptForCommand();
        });
        return true;
      }
      
      console.log(chalk.yellow(`Explaining endpoint: ${parameters.endpoint}...`));
      const explanation = await explainEndpoint(parameters.endpoint);
      console.log(chalk.green('\nEndpoint Explanation:'));
      console.log(chalk.white(explanation));
      return false;
    
    case 'help':
      displayHelp();
      return false;
    
    case 'exit':
      console.log(chalk.blue('Goodbye!'));
      rl.close();
      process.exit(0);
      return false;
    
    default:
      console.log(chalk.yellow('Command not recognized. Type "help" for available commands.'));
      return false;
  }
}

/**
 * Configure LLM provider
 */
function configureLLM() {
  console.log(chalk.green('LLM Provider Configuration'));
  console.log(chalk.cyan('\nAvailable providers:'));
  console.log(chalk.white('1. OpenAI (GPT-4, GPT-3.5)'));
  console.log(chalk.white('2. Anthropic (Claude)'));
  console.log(chalk.white('3. Ollama (Local LLMs)'));
  console.log(chalk.white('4. None (Pattern matching only)'));
  console.log(chalk.cyan(`\nCurrent provider: ${config.llmProvider}`));
  
  rl.question(chalk.green('\nSelect a provider (1-4): '), (choice) => {
    switch (choice) {
      case '1': // OpenAI
        rl.question(chalk.cyan('Enter your OpenAI API key: '), (apiKey) => {
          if (apiKey && apiKey.trim()) {
            rl.question(chalk.cyan('Enter model name (default: gpt-4-turbo): '), (model) => {
              config.openai.apiKey = apiKey.trim();
              config.openai.model = model.trim() || 'gpt-4-turbo';
              config.llmProvider = 'openai';
              
              try {
                openaiClient = new OpenAI({
                  apiKey: config.openai.apiKey
                });
                console.log(chalk.green('OpenAI configuration saved!'));
              } catch (error) {
                console.log(chalk.red('Error initializing OpenAI client:'), error.message);
              }
              
              promptForCommand();
            });
          } else {
            console.log(chalk.red('API key is required. Configuration cancelled.'));
            promptForCommand();
          }
        });
        break;
      
      case '2': // Anthropic
        rl.question(chalk.cyan('Enter your Anthropic API key: '), (apiKey) => {
          if (apiKey && apiKey.trim()) {
            console.log(chalk.cyan('\nRecommended models:'));
            console.log(chalk.white('- claude-3-haiku-20240307    (fastest)'));
            console.log(chalk.white('- claude-3-sonnet-20240229   (balanced)'));
            console.log(chalk.white('- claude-3-opus-20240229     (most capable)'));
            
            rl.question(chalk.cyan('\nEnter model name (default: claude-3-haiku-20240307): '), (model) => {
              config.anthropic.apiKey = apiKey.trim();
              config.anthropic.model = model.trim() || 'claude-3-haiku-20240307';
              config.llmProvider = 'anthropic';
              
              try {
                anthropicClient = new Anthropic({
                  apiKey: config.anthropic.apiKey
                });
                console.log(chalk.green('Anthropic configuration saved!'));
                console.log(chalk.yellow(`Using model: ${config.anthropic.model}`));
                
                // Provide model-specific tips
                console.log(chalk.cyan('Note: Anthropic models now use enhanced JSON parsing:'));
                console.log(chalk.white('- Robust error handling for malformed responses'));
                console.log(chalk.white('- Fallback extraction for non-standard JSON'));
                console.log(chalk.white('- Model-specific prompting for better JSON compliance'));
                
                // Show debug tip
                console.log(chalk.cyan('\nTip: Use "--debug" flag with commands or set DEBUG_LLM=true for detailed logs'));
              } catch (error) {
                console.log(chalk.red('Error initializing Anthropic client:'), error.message);
                console.log(chalk.yellow('Possible solutions:'));
                console.log(chalk.white('- Check that your API key is valid and correctly entered'));
                console.log(chalk.white('- Ensure you have proper network connectivity to Anthropic API'));
                console.log(chalk.white('- Try again or use a different LLM provider'));
                config.llmProvider = 'none';
              }
              
              promptForCommand();
            });
          } else {
            console.log(chalk.red('API key is required. Configuration cancelled.'));
            promptForCommand();
          }
        });
        break;
      
      case '3': // Ollama
        rl.question(chalk.cyan('Enter Ollama API URL (default: http://localhost:11434): '), (apiUrl) => {
          console.log(chalk.cyan('\nRecommended models:'));
          console.log(chalk.white('- llama3 or llama2      (Meta Llama models)'));
          console.log(chalk.white('- mistral or mixtral    (Mistral models)'));
          console.log(chalk.white('- gemma:7b              (Google Gemma models)'));
          console.log(chalk.white('- deepseek-r1:7b        (DeepSeek models)'));
          console.log(chalk.white('- deepseek-v3:7b        (DeepSeek models)'));
          console.log(chalk.white('- qwen:14b              (Qwen models)'));
          
          rl.question(chalk.cyan('\nEnter model name (default: llama3): '), (model) => {
            config.ollama.apiUrl = apiUrl.trim() || 'http://localhost:11434';
            config.ollama.model = model.trim() || 'llama3';
            config.llmProvider = 'ollama';
            
            // Provide model-specific tips
            if (model.includes('deepseek')) {
              console.log(chalk.yellow('Note: DeepSeek models may sometimes produce non-JSON responses.'));
              console.log(chalk.yellow('The agent will attempt to extract useful data or fall back to pattern matching.'));
            } else if (model.includes('gemma')) {
              console.log(chalk.yellow('Note: Gemma models work best with simple, clear instructions.'));
            }
            
            console.log(chalk.green('Ollama configuration saved!'));
            promptForCommand();
          });
        });
        break;
      
      case '4': // None
        config.llmProvider = 'none';
        console.log(chalk.green('Using pattern matching for command interpretation.'));
        promptForCommand();
        break;
      
      default:
        console.log(chalk.red('Invalid choice. Configuration cancelled.'));
        promptForCommand();
    }
  });
  
  return true; // Indicate that we're handling input asynchronously
}

/**
 * Display help information
 */
function displayHelp() {
  console.log(chalk.green('WordPress MCP Natural Language Agent with LLM Integration - Help'));
  console.log(chalk.cyan('\nAvailable commands:'));
  console.log(chalk.white('- "show recent posts" or "list latest posts" - Display recent posts'));
  console.log(chalk.white('- "show 10 posts" - Display a specific number of recent posts'));
  console.log(chalk.white('- "get post id: 123" or "show post 123" - Display a specific post'));
  console.log(chalk.white('- "create post" or "add new article" - Create a new post'));
  console.log(chalk.white('- "update post id: 123" or "edit post 123" - Update an existing post'));
  console.log(chalk.white('- "delete post id: 123" or "remove post 123" - Delete a post'));
  console.log(chalk.white('- "discover endpoints" or "show capabilities" - List available endpoints'));
  console.log(chalk.white('- "explain /wp/v2/posts" or "what does the posts endpoint do" - Get endpoint documentation'));
  console.log(chalk.white('- "config llm" or "configure llm" - Configure LLM provider (OpenAI, Anthropic, Ollama)'));
  console.log(chalk.white('- Add "--debug" or "--verbose" to any command - Show detailed LLM processing information'));
  console.log(chalk.white('- "help" or "?" - Display this help information'));
  console.log(chalk.white('- "exit" or "quit" - Exit the agent'));
  
  console.log(chalk.cyan('\nLLM Integration:'));
  console.log(chalk.white('This agent supports natural language understanding using various LLM providers:'));
  console.log(chalk.white('- OpenAI (GPT-4, GPT-3.5) - Requires an API key'));
  console.log(chalk.white('- Anthropic (Claude) - Requires an API key'));
  console.log(chalk.white('- Ollama (Local LLMs) - Requires Ollama to be running locally'));
  console.log(chalk.white('  • Supports multiple model families: Llama, Mistral, Gemma, DeepSeek, Qwen, etc.'));
  console.log(chalk.white('  • Includes enhanced handling for models with different response formats'));
  console.log(chalk.white('- Pattern matching - No API key required (fallback)'));
  
  console.log(chalk.cyan('\nMulti-Action Requests:'));
  console.log(chalk.white('The agent can recognize and process multi-action requests:'));
  console.log(chalk.white('- When multiple parameters are detected in a natural language request'));
  console.log(chalk.white('- Example: "Create a post with title X and content Y with status publish"'));
  console.log(chalk.white('- You will be offered the option to auto-accept pre-filled values'));
  console.log(chalk.white('- Streamlines workflow for complex or compound requests'));
  
  console.log(chalk.cyan(`\nCurrent LLM provider: ${config.llmProvider}`));
  if (config.llmProvider === 'ollama') {
    console.log(chalk.cyan(`Current Ollama model: ${config.ollama.model}`));
  }
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
  console.log(chalk.green('WordPress MCP Natural Language Agent with LLM Integration'));
  console.log(chalk.cyan('WordPress MCP Server: ') + config.serverUrl);
  
  // Initialize LLM clients if configured
  initializeLLMClients();
  
  console.log(chalk.white('\nType "help" for available commands, "config llm" to configure an LLM provider, or "exit" to quit.'));
  
  promptForCommand();
}

// Start the agent
initAgent();
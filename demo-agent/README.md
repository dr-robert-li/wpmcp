# WordPress MCP Natural Language Agent with LLM Integration

A demo agent that interacts with a WordPress site via the Model Context Protocol (MCP) using natural language commands. This agent allows you to create, read, update, and delete WordPress posts through simple conversational interactions. It features integration with Large Language Models (OpenAI, Anthropic, and Ollama) for enhanced natural language understanding.

## Features

- **Natural Language Interface**: Control WordPress with simple, human-like commands
- **LLM Integration**: Support for OpenAI (GPT-4/3.5), Anthropic (Claude), and Ollama (Llama, Mistral, DeepSeek, Gemma, Qwen, and other local LLMs)
- **Multi-Action Processing**: Recognizes and streamlines complex requests with auto-acceptance of prefilled values
- **Auto-Retry Mechanism**: Automatically retries failed LLM requests up to 3 times with adaptive prompting
- **Robust JSON Handling**: Enhanced JSON parsing with fallback mechanisms for all LLM providers, especially optimized for Anthropic Claude
- **Flexible Configuration**: Configure LLM providers through the command line or environment variables
- **Post Management**: Create, read, update, and delete posts
- **MCP Integration**: Communicates with WordPress using the Model Context Protocol
- **Interactive Command Line**: User-friendly command line interface with color-coded responses
- **Endpoint Discovery**: View available WordPress REST API endpoints
- **Endpoint Documentation**: Get AI-powered explanations of any WordPress API endpoint

## Installation

Before using the agent, you need to install its dependencies:

```bash
# Navigate to the agent directory
cd /path/to/wp-mcp-agent

# Install dependencies
npm install
```

## Configuration

### WordPress MCP Configuration

The agent is configured to connect to an example WordPress site running at `http://localhost:10005` with the API key `123456789`. You can configure the WordPress MCP connection in several ways:

1. **Environment Variables**: Create a `.env` file based on the provided `.env.example` template:
   ```
   WP_MCP_SERVER_URL=http://localhost:10005/wp-json/wpmcp/v1/mcp
   WP_MCP_API_KEY=123456789
   ```

2. **Direct Code Modification**: Update the configuration in the `wp-mcp-nl-agent.js` file:
   ```javascript
   // Configuration
   const config = {
     serverUrl: 'http://localhost:10005/wp-json/wpmcp/v1/mcp',
     apiKey: '123456789',
     requestCounter: 1,
     // LLM configurations...
   };
   ```

### LLM Provider Configuration

The agent supports three LLM providers:

1. **OpenAI Configuration**:
   ```
   OPENAI_API_KEY=your-openai-api-key-here
   OPENAI_MODEL=gpt-4-turbo
   ```

2. **Anthropic Configuration**:
   ```
   ANTHROPIC_API_KEY=your-anthropic-api-key-here
   ANTHROPIC_MODEL=claude-3-haiku-20240307
   ```
   
   The agent includes specialized JSON handling for Anthropic Claude models:
   - Enhanced system prompts for better JSON compliance
   - Robust error handling for malformed responses
   - Regex-based JSON extraction when direct parsing fails
   - Automatic fallback to pattern matching when needed

3. **Ollama Configuration** (for local LLMs):
   ```
   OLLAMA_API_URL=http://localhost:11434
   OLLAMA_MODEL=llama3  # Or other models like mistral, gemma:7b, deepseek-r1:7b, etc.
   ```
   
   The agent includes enhanced support for various Ollama models:
   - **Llama models**: llama3, llama2, etc.
   - **Mistral models**: mistral, mixtral
   - **Google models**: gemma:7b
   - **DeepSeek models**: deepseek-r1:7b, deepseek-v3:7b
   - **Qwen models**: qwen:14b
   
   Each model family has specific optimizations to handle differences in JSON formatting and response styles.

You can also configure the LLM provider directly through the agent interface by using the `config llm` command when the agent is running.

## Usage

### Multi-Action Request Processing

The agent can recognize complex multi-part requests and streamline their processing:

1. **Automatic Detection**: When LLM recognizes multiple parameters in a request (like a title, content, and status), the agent identifies it as a multi-action request
2. **Pre-filled Values Display**: All extracted parameters are displayed to the user
3. **Auto-Accept Option**: User is prompted whether to auto-accept all pre-filled values or review them individually
4. **Streamlined Workflow**: For commands like "Create a post with title X and content Y in draft status", all parameters can be processed in a single step

This feature is particularly useful for:
- Creating posts with specific title, content, and status in one command
- Updating posts with multiple fields in one command
- Deleting posts with forced confirmation in multi-step sequences

Example:
```
What would you like to do? create a post with title "Quick Update" and content "This is a quick status update" with status draft
Processing your request using LLM...
Creating a new post...
Pre-filled title: "Quick Update"
Pre-filled content: This is a quick status update
Pre-filled status: draft
This appears to be a multi-action request. Do you want to auto-accept the prefilled values? (yes/no): yes
Using all prefilled values automatically.
Post created successfully!
ID: 45 - Quick Update
URL: http://localhost:10005/quick-update/
Status: draft
```

### Debugging Options

The agent provides several debugging features to help you understand how it processes requests:

1. **Command-Line Debugging**:
   - Add `--debug` or `--verbose` to any command to see detailed processing information
   - Example: `show my recent posts --debug`
   
2. **Environment Variables**:
   - `DEBUG_LLM=true`: Enable detailed LLM debugging for all requests
   - `LLM_ALWAYS_SHOW_RESPONSE=true`: Always show raw LLM responses

3. **Debug Output**:
   - System prompts used
   - Raw request data
   - Raw response from the LLM
   - JSON parsing attempts and results
   - Fallback processing details

4. **Enhanced JSON Handling and Debugging**:
   - Multi-stage JSON parsing for all LLM providers:
     1. Direct JSON.parse() attempt first
     2. Regex-based JSON extraction for malformed responses
     3. Fallback to pattern matching when JSON can't be extracted
   - Detailed logging of each parsing stage:
   ```
   Attempting direct JSON parsing of Anthropic response...
   ✗ Direct JSON parsing failed: SyntaxError: Unterminated string in JSON at position 5021
   Attempting to extract JSON using regex...
   Found 2 possible JSON matches
   ✓ JSON extraction successful
   ```
   - Provides visibility into how the agent handles various response formats
   - Shows complete raw responses when --debug flag is used
   - Provider-specific optimizations, especially for Anthropic Claude models

5. **Handling Unrecognized Commands**:
   - When an LLM returns a response that can't be parsed as a valid command
   - Shows raw LLM response content when debug is enabled
   - Automatically retries up to 3 times with modified prompts
   - Example output:
   ```
   Command not recognized. Retrying (1/3)...
   === RAW LLM RESPONSE ===
   {"thoughts": "I need to analyze this input...", "result": null}
   Retry attempt 1/3 using LLM (ollama)...
   Command not recognized. Retrying (2/3)...
   Retry attempt 2/3 using LLM (ollama)...
   LLM could not determine a valid command after 3 attempts.
   === RAW LLM RESPONSE (FINAL ATTEMPT) ===
   {"options": ["create_post", "update_post"], "thinking": "This seems like..."}
   Falling back to pattern matching.
   ```
   - Helps diagnose issues with models that don't follow the expected format
   - Increases success rate with challenging commands or less-capable models

### Starting the Regular Agent

To start the standard agent (without LLM integration):

```bash
npm start
```

Or directly with Node.js:

```bash
node wp-mcp-agent.js
```

### Starting the LLM-Enhanced Agent

To start the agent with LLM integration for enhanced natural language understanding:

```bash
npm run nl-agent
```

Or directly with Node.js:

```bash
node wp-mcp-nl-agent.js
```

## Available Commands

The agent understands the following types of commands:

### Listing Posts

```
show recent posts
list latest posts
show 10 posts
```

### Viewing a Specific Post

```
get post id: 123
show post 123
display post id 123
view post 123
```

### Creating Posts

```
create post
write new post
add post
new article
```

After entering one of these commands, the agent will prompt you for:
- Title
- Content
- Status (publish/draft)

### Updating Posts

```
update post id: 123
edit post 123
modify post id 123
change post 123
```

After entering one of these commands, the agent will:
1. Show the current post details
2. Prompt you for a new title (press Enter to keep current)
3. Prompt you for new content (press Enter to keep current)
4. Prompt you for a new status (press Enter to keep current)

### Deleting Posts

```
delete post id: 123
remove post 123
trash post id 123
```

The agent will ask for confirmation before deleting a post.

### Discovering Endpoints

```
discover endpoints
list endpoints
show capabilities
get endpoints
```

### Explaining Endpoints

```
explain /wp/v2/posts
tell me about the posts endpoint
what does the /wp/v2/media endpoint do
how to use the categories endpoint
```

### LLM Configuration (NL Agent Only)

```
config llm
configure llm
```

These commands let you interactively configure which LLM provider to use (OpenAI, Anthropic, Ollama, or none).

### Help and Exit

```
help
?
exit
quit
```

## Example Sessions

### Standard Agent

```
WordPress MCP Natural Language Agent
Connected to: http://localhost:10005/wp-json/wpmcp/v1/mcp
Type "help" for available commands or "exit" to quit.

What would you like to do? show recent posts
Fetching recent posts...
Found 5 posts:
ID: 40 - Post Created via MCP
URL: http://localhost:10005/post-created-via-mcp/
Status: publish
Date: 5/3/2025, 6:29:32 AM

ID: 37 - Test Post
URL: http://localhost:10005/test-post/
Status: publish
Date: 4/30/2025, 7:05:30 AM

ID: 16 - Test Post Created by AI Agent
URL: http://localhost:10005/test-post-created-by-ai-agent/
Status: publish
Date: 4/28/2025, 2:27:42 AM

ID: 1 - Hello world!
URL: http://localhost:10005/hello-world/
Status: publish
Date: 4/28/2025, 12:43:34 AM

What would you like to do? create post
Creating a new post...
Title: My First Post via MCP Agent
Content: This is a post created through the natural language agent interface. The agent uses the Model Context Protocol to communicate with WordPress.
Status (publish/draft): publish
Post created successfully!
ID: 41 - My First Post via MCP Agent
URL: http://localhost:10005/my-first-post-via-mcp-agent/
Status: publish

What would you like to do? exit
Goodbye!
```

### LLM-Enhanced Agent

```
WordPress MCP Natural Language Agent with LLM Integration
WordPress MCP Server: http://localhost:10005/wp-json/wpmcp/v1/mcp
OpenAI API initialized.

Type "help" for available commands, "config llm" to configure an LLM provider, or "exit" to quit.

What would you like to do? show me my latest posts
Processing your request using LLM...
Fetching recent posts...
Found 5 posts:
ID: 40 - Post Created via MCP
URL: http://localhost:10005/post-created-via-mcp/
Status: publish
Date: 5/3/2025, 6:29:32 AM

ID: 37 - Test Post
URL: http://localhost:10005/test-post/
Status: publish
Date: 4/30/2025, 7:05:30 AM

What would you like to do? write a new article about WordPress and AI integration with title "AI Revolution in WordPress" and status draft
Processing your request using LLM...
Creating a new post...
Pre-filled title: "AI Revolution in WordPress"
Pre-filled content: WordPress continues to evolve as a versatile content management system, and one of the most exciting developments is its integration with artificial intelligence. This post explores how AI is transforming WordPress websites.
Pre-filled status: draft
This appears to be a multi-action request. Do you want to auto-accept the prefilled values? (yes/no): yes
Using all prefilled values automatically.
Post created successfully!
ID: 42 - AI Revolution in WordPress
URL: http://localhost:10005/ai-revolution-in-wordpress/
Status: draft

What would you like to do? config llm
LLM Provider Configuration

Available providers:
1. OpenAI (GPT-4, GPT-3.5)
2. Anthropic (Claude)
3. Ollama (Local LLMs)
4. None (Pattern matching only)

Current provider: openai

Select a provider (1-4): 3
Enter Ollama API URL (default: http://localhost:11434): 

Recommended models:
- llama3 or llama2      (Meta Llama models)
- mistral or mixtral    (Mistral models)
- gemma:7b              (Google Gemma models)
- deepseek-r1:7b        (DeepSeek models)
- deepseek-v3:7b        (DeepSeek models)
- qwen:14b              (Qwen models)

Enter model name (default: llama3): deepseek-r1:7b
Note: DeepSeek models may sometimes produce non-JSON responses.
The agent will attempt to extract useful data or fall back to pattern matching.
Ollama configuration saved!

What would you like to do? show my latest posts --debug
Debug mode enabled for this request
Using Ollama model: deepseek-r1:7b
Using DeepSeek-specific prompt format
Sending request to Ollama API...
Ollama response received in 1524ms
=== RAW RESPONSE ===
<think>
Okay, I need to analyze the user's command to determine what they want to do. The command is "show my latest posts --debug".

This seems to be a request to display recent posts. The "--debug" part is probably a flag for debugging, but for my command interpretation, I'll focus on the main action.

Based on the expected command format, this would be "list_posts". There's no specific count mentioned, so I'll leave that as the default.
</think>

{"command": "list_posts", "parameters": {}}
Attempting direct JSON parsing...
✗ Direct JSON parsing failed: SyntaxError: Unexpected token < in JSON at position 0
Attempting to extract JSON using regex...
Found 1 possible JSON matches
✓ JSON extraction successful
LLM interpreted command: list_posts
Fetching recent posts...
Found 5 posts:
ID: 40 - Post Created via MCP
URL: http://localhost:10005/post-created-via-mcp/
Status: publish
Date: 5/3/2025, 6:29:32 AM

ID: 37 - Test Post
URL: http://localhost:10005/test-post/
Status: publish
Date: 4/30/2025, 7:05:30 AM

What would you like to do? explain /wp/v2/posts
Explaining endpoint: /wp/v2/posts...

Endpoint Explanation:
The /wp/v2/posts endpoint is a core WordPress REST API endpoint that allows you to interact with blog posts. It supports these HTTP methods:

GET: Retrieve a list of posts or a specific post when used with an ID
POST: Create a new post
PUT/PATCH: Update an existing post
DELETE: Remove a post

When retrieving posts (GET), you can filter and sort using query parameters like:
- per_page: Number of posts to return (default: 10)
- page: Page of results
- search: Search term
- orderby: Sort field (date, title, etc.)
- order: Sort direction (asc, desc)

Creating or updating posts (POST/PUT) requires these main parameters:
- title: The post title
- content: The post content
- status: Post status (publish, draft, etc.)
- categories: Array of category IDs
- tags: Array of tag IDs

Common use cases include:
- Building a custom frontend that displays posts
- Creating a mobile app that interacts with WordPress
- Automating post creation
- Building integrations with other systems

What would you like to do? exit
Goodbye!
```

## Extending the Agent

### Adding More WordPress Functionality

You can extend the agent by adding new command patterns in the `processCommand` function or by implementing additional WordPress operations using the MCP API.

### Enhancing LLM Integration

The agent's LLM integration can be enhanced in several ways:

1. **Improved Prompts**: Modify the system prompt in the `callLLM` function to provide better context or handling of specific commands
2. **Additional LLM Providers**: Add support for other LLM providers by extending the configuration and API integration
3. **More Model-Specific Optimizations**:
   - The agent already includes optimizations for several model families (llama, deepseek, gemma, qwen)
   - Additional model families can be supported by extending the model detection logic in the `callLLM` function
   - Model-specific prompting strategies can be added to improve response quality
4. **Context Awareness**: Implement conversation history tracking to make the agent more context-aware
5. **Streaming Responses**: Add streaming support for a more responsive interface
6. **Fallback Handling**: The agent includes fallback handling for models that don't produce valid JSON
   - This can be extended to provide more robust error recovery
   - The `createFallbackCommandInterpretation` function can be enhanced with more sophisticated parsing

## Notes

- This agent is intended as a demonstration of MCP capabilities and LLM integration and is not a production-ready tool
- All WordPress operations are performed through the WordPress MCP API
- The agent uses the JSON-RPC 2.0 format for all MCP API calls
- API keys for LLM providers are stored in memory and are not persisted between sessions
- Consider implementing proper API key storage and management for production use

## License

This project is licensed under the GNU General Public License v3.0 (GPL-3.0).
<?php
/**
 * Handles MCP Prompts functionality.
 *
 * @since      2.0.0
 * @package    WPMCP
 * @subpackage WPMCP/includes
 */

class WPMCP_Prompts_Handler {
    
    /**
     * Available prompts.
     *
     * @var array
     */
    private $prompts = array();
    
    /**
     * Initialize the class.
     */
    public function __construct() {
        // Define available prompts
        $this->prompts = array(
            'create_post' => array(
                'name' => 'create_post',
                'description' => 'Create a new WordPress post',
                'arguments' => array(
                    array(
                        'name' => 'title',
                        'description' => 'Post title',
                        'required' => true
                    ),
                    array(
                        'name' => 'content',
                        'description' => 'Post content',
                        'required' => true
                    ),
                    array(
                        'name' => 'excerpt',
                        'description' => 'Post excerpt',
                        'required' => false
                    ),
                    array(
                        'name' => 'status',
                        'description' => 'Post status (publish, draft, etc.)',
                        'required' => false
                    )
                )
            ),
            'analyze_content' => array(
                'name' => 'analyze_content',
                'description' => 'Analyze WordPress content',
                'arguments' => array(
                    array(
                        'name' => 'content_type',
                        'description' => 'Type of content to analyze (post, page, etc.)',
                        'required' => true
                    ),
                    array(
                        'name' => 'content_id',
                        'description' => 'ID of the content to analyze',
                        'required' => true
                    )
                )
            ),
            'seo_optimize' => array(
                'name' => 'seo_optimize',
                'description' => 'Generate SEO recommendations for content',
                'arguments' => array(
                    array(
                        'name' => 'content',
                        'description' => 'Content to optimize',
                        'required' => true
                    ),
                    array(
                        'name' => 'keywords',
                        'description' => 'Target keywords',
                        'required' => false
                    )
                )
            ),
            'generate_excerpt' => array(
                'name' => 'generate_excerpt',
                'description' => 'Generate an excerpt from post content',
                'arguments' => array(
                    array(
                        'name' => 'content',
                        'description' => 'Post content',
                        'required' => true
                    ),
                    array(
                        'name' => 'length',
                        'description' => 'Desired excerpt length in words',
                        'required' => false
                    )
                )
            )
        );
    }
    
    /**
     * List available prompts.
     *
     * @param array $params Request parameters.
     * @return array Response with prompts.
     */
    public function list_prompts($params = array()) {
        $prompts_list = array_values($this->prompts);
        
        return array(
            'prompts' => $prompts_list
        );
    }
    
    /**
     * Get a specific prompt with messages.
     *
     * @param string $name Prompt name.
     * @param array $arguments Prompt arguments.
     * @return array|WP_Error Prompt messages or error.
     */
    public function get_prompt($name, $arguments = array()) {
        if (!isset($this->prompts[$name])) {
            return new WP_Error(
                'prompt_not_found',
                'Prompt not found: ' . $name,
                array('status' => 404, 'code' => -32601)
            );
        }
        
        $prompt = $this->prompts[$name];
        
        // Validate required arguments
        foreach ($prompt['arguments'] as $arg) {
            if ($arg['required'] && (!isset($arguments[$arg['name']]) || empty($arguments[$arg['name']]))) {
                return new WP_Error(
                    'missing_argument',
                    'Missing required argument: ' . $arg['name'],
                    array('status' => 400, 'code' => -32602)
                );
            }
        }
        
        // Generate prompt messages based on the prompt type
        switch ($name) {
            case 'create_post':
                return $this->generate_create_post_prompt($arguments);
                
            case 'analyze_content':
                return $this->generate_analyze_content_prompt($arguments);
                
            case 'seo_optimize':
                return $this->generate_seo_optimize_prompt($arguments);
                
            case 'generate_excerpt':
                return $this->generate_excerpt_prompt($arguments);
                
            default:
                return new WP_Error(
                    'prompt_not_implemented',
                    'Prompt implementation not found: ' . $name,
                    array('status' => 501, 'code' => -32603)
                );
        }
    }
    
    /**
     * Generate create post prompt messages.
     *
     * @param array $arguments Prompt arguments.
     * @return array Prompt messages.
     */
    private function generate_create_post_prompt($arguments) {
        $title = $arguments['title'];
        $content = $arguments['content'];
        $excerpt = isset($arguments['excerpt']) ? $arguments['excerpt'] : '';
        $status = isset($arguments['status']) ? $arguments['status'] : 'draft';
        
        return array(
            'description' => "Create a new WordPress post with title: {$title}",
            'messages' => array(
                array(
                    'role' => 'user',
                    'content' => array(
                        'type' => 'text',
                        'text' => "I'd like to create a new WordPress post with the following details:\n\nTitle: {$title}\nContent: {$content}" . 
                                 ($excerpt ? "\nExcerpt: {$excerpt}" : "") . 
                                 "\nStatus: {$status}\n\nPlease format this as a well-structured post."
                    )
                )
            )
        );
    }
    
    /**
     * Generate analyze content prompt messages.
     *
     * @param array $arguments Prompt arguments.
     * @return array Prompt messages.
     */
    private function generate_analyze_content_prompt($arguments) {
        $content_type = $arguments['content_type'];
        $content_id = $arguments['content_id'];
        
        // Get the content
        $content = '';
        $title = '';
        
        if ($content_type === 'post' || $content_type === 'page') {
            $post = get_post($content_id);
            if ($post) {
                $content = $post->post_content;
                $title = $post->post_title;
            }
        }
        
        return array(
            'description' => "Analyze {$content_type} content with ID: {$content_id}",
            'messages' => array(
                array(
                    'role' => 'user',
                    'content' => array(
                        'type' => 'text',
                        'text' => "Please analyze the following WordPress {$content_type} content:\n\nTitle: {$title}\n\nContent:\n{$content}\n\nProvide insights on readability, structure, engagement potential, and suggestions for improvement."
                    )
                )
            )
        );
    }
    
    /**
     * Generate SEO optimize prompt messages.
     *
     * @param array $arguments Prompt arguments.
     * @return array Prompt messages.
     */
    private function generate_seo_optimize_prompt($arguments) {
        $content = $arguments['content'];
        $keywords = isset($arguments['keywords']) ? $arguments['keywords'] : '';
        
        return array(
            'description' => "Generate SEO recommendations for content" . ($keywords ? " targeting keywords: {$keywords}" : ""),
            'messages' => array(
                array(
                    'role' => 'user',
                    'content' => array(
                        'type' => 'text',
                        'text' => "Please provide SEO optimization recommendations for the following content:" . 
                                 ($keywords ? "\n\nTarget keywords: {$keywords}" : "") . 
                                 "\n\nContent:\n{$content}\n\nInclude suggestions for title, meta description, headings, content structure, keyword density, and internal linking."
                    )
                )
            )
        );
    }
    
    /**
     * Generate excerpt prompt messages.
     *
     * @param array $arguments Prompt arguments.
     * @return array Prompt messages.
     */
    private function generate_excerpt_prompt($arguments) {
        $content = $arguments['content'];
        $length = isset($arguments['length']) ? $arguments['length'] : 55; // WordPress default
        
        return array(
            'description' => "Generate an excerpt from post content (target length: {$length} words)",
            'messages' => array(
                array(
                    'role' => 'user',
                    'content' => array(
                        'type' => 'text',
                        'text' => "Please generate a compelling excerpt of approximately {$length} words from the following post content. The excerpt should capture the essence of the content and encourage readers to continue reading.\n\nContent:\n{$content}"
                    )
                )
            )
        );
    }
}

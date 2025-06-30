<?php
// includes/integrations/class-spiralengine-openai.php

/**
 * Spiral Engine OpenAI Integration
 * 
 * Handles OpenAI API integration including model management, token tracking,
 * prompt templates, and AI-powered features.
 *
 * @package    Spiral_Engine
 * @subpackage Spiral_Engine/integrations
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class SPIRALENGINE_OpenAI {
    
    /**
     * API configuration
     */
    private $api_key;
    private $api_base_url = 'https://api.openai.com/v1';
    private $config;
    
    /**
     * Rate limiting
     */
    private $rate_limiter;
    
    /**
     * Token tracker
     */
    private $token_tracker;
    
    /**
     * Time manager instance
     */
    private $time_manager;
    
    /**
     * Prompt templates
     */
    private $prompt_templates;
    
    /**
     * Available models
     */
    private $models = array(
        'gpt-4' => array(
            'name' => 'GPT-4',
            'description' => 'Most capable model for complex tasks',
            'max_tokens' => 8192,
            'cost_per_1k_prompt' => 0.03,
            'cost_per_1k_completion' => 0.06
        ),
        'gpt-4-turbo-preview' => array(
            'name' => 'GPT-4 Turbo',
            'description' => 'Faster GPT-4 with longer context',
            'max_tokens' => 128000,
            'cost_per_1k_prompt' => 0.01,
            'cost_per_1k_completion' => 0.03
        ),
        'gpt-3.5-turbo' => array(
            'name' => 'GPT-3.5 Turbo',
            'description' => 'Fast and cost-effective',
            'max_tokens' => 4096,
            'cost_per_1k_prompt' => 0.0015,
            'cost_per_1k_completion' => 0.002
        ),
        'gpt-3.5-turbo-16k' => array(
            'name' => 'GPT-3.5 Turbo 16K',
            'description' => 'Extended context window',
            'max_tokens' => 16384,
            'cost_per_1k_prompt' => 0.003,
            'cost_per_1k_completion' => 0.004
        )
    );
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->time_manager = new SPIRALENGINE_Time_Zone_Manager();
        $this->load_configuration();
        $this->init_components();
        $this->init_hooks();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // AJAX handlers
        add_action('wp_ajax_spiralengine_ai_generate', array($this, 'ajax_generate'));
        add_action('wp_ajax_spiralengine_ai_analyze', array($this, 'ajax_analyze'));
        add_action('wp_ajax_spiralengine_ai_suggest', array($this, 'ajax_suggest'));
        add_action('wp_ajax_spiralengine_ai_chat', array($this, 'ajax_chat'));
        add_action('wp_ajax_spiralengine_ai_get_usage', array($this, 'ajax_get_usage'));
        
        // Token refresh
        add_action('spiralengine_refresh_ai_tokens', array($this, 'refresh_token_limits'));
        
        // Cleanup
        add_action('spiralengine_cleanup_ai_logs', array($this, 'cleanup_old_logs'));
    }
    
    /**
     * Load configuration
     */
    private function load_configuration() {
        $settings = get_option('spiralengine_api_settings', array());
        
        // Get API key
        $this->api_key = $settings['openai_api_key'] ?? '';
        
        // Decrypt if encrypted
        if (!empty($this->api_key) && strpos($this->api_key, 'enc:') === 0) {
            $encryption = new SPIRALENGINE_Flexible_Encryption();
            $this->api_key = $encryption->decrypt(substr($this->api_key, 4));
        }
        
        // Load config
        $this->config = array(
            'default_model' => $settings['openai_model'] ?? 'gpt-3.5-turbo',
            'temperature' => floatval($settings['openai_temperature'] ?? 0.7),
            'max_tokens' => intval($settings['openai_max_tokens'] ?? 2000),
            'enable_rate_limiting' => $settings['enable_rate_limiting'] ?? true,
            'requests_per_minute' => intval($settings['requests_per_minute'] ?? 60),
            'tokens_per_minute' => intval($settings['tokens_per_minute'] ?? 60000),
            'timeout' => 30,
            'retry_attempts' => 3,
            'retry_delay' => 1
        );
    }
    
    /**
     * Initialize components
     */
    private function init_components() {
        // Initialize rate limiter
        $this->rate_limiter = new SPIRALENGINE_AI_Rate_Limiter(
            $this->config['requests_per_minute'],
            $this->config['tokens_per_minute']
        );
        
        // Initialize token tracker
        $this->token_tracker = new SPIRALENGINE_Token_Tracker();
        
        // Load prompt templates
        $this->load_prompt_templates();
    }
    
    /**
     * Load prompt templates
     */
    private function load_prompt_templates() {
        $this->prompt_templates = array(
            'assessment_analysis' => array(
                'system' => "You are a mental health assessment analyst. Analyze the user's responses and provide insights focused on patterns, strengths, and areas for growth. Be supportive and constructive.",
                'user' => "Please analyze these assessment responses:\n\n{responses}\n\nProvide insights on:\n1. Key patterns observed\n2. Strengths identified\n3. Areas for potential growth\n4. Recommended focus areas"
            ),
            
            'episode_analysis' => array(
                'system' => "You are a mental health pattern analyst specializing in {episode_type} episodes. Analyze the episode data to identify triggers, patterns, and effective coping strategies.",
                'user' => "Analyze this {episode_type} episode:\n\nDate/Time: {datetime}\nDuration: {duration}\nIntensity: {intensity}/10\nTriggers: {triggers}\nSymptoms: {symptoms}\nThoughts: {thoughts}\nCoping Strategies: {coping_strategies}\n\nProvide:\n1. Trigger pattern analysis\n2. Symptom progression insights\n3. Coping strategy effectiveness\n4. Recommendations for future episodes"
            ),
            
            'coping_suggestions' => array(
                'system' => "You are a mental wellness coach. Suggest personalized coping strategies based on the user's specific situation and history. Focus on evidence-based techniques.",
                'user' => "Based on this information:\n\nEpisode Type: {episode_type}\nCurrent Triggers: {triggers}\nPast Effective Strategies: {past_strategies}\nUser Preferences: {preferences}\n\nSuggest 5 specific coping strategies that would be most helpful right now."
            ),
            
            'pattern_recognition' => array(
                'system' => "You are a behavioral pattern recognition specialist. Identify patterns in the user's mental health data and provide actionable insights.",
                'user' => "Analyze these episodes for patterns:\n\n{episodes_data}\n\nIdentify:\n1. Temporal patterns (time of day, day of week, etc.)\n2. Trigger patterns\n3. Escalation patterns\n4. Recovery patterns\n5. Predictive indicators"
            ),
            
            'progress_summary' => array(
                'system' => "You are a supportive mental health progress analyst. Create encouraging summaries that highlight growth while acknowledging challenges.",
                'user' => "Create a progress summary for:\n\nTime Period: {period}\nTotal Episodes: {total_episodes}\nAverage Intensity: {avg_intensity}\nMost Common Triggers: {common_triggers}\nMost Used Strategies: {common_strategies}\nGoals Achieved: {goals_achieved}\n\nProvide an encouraging summary focusing on progress made and next steps."
            ),
            
            'goal_suggestions' => array(
                'system' => "You are a mental health goal-setting coach. Suggest SMART goals based on the user's current state and progress.",
                'user' => "Based on this user data:\n\nCurrent Challenges: {challenges}\nRecent Progress: {progress}\nStrengths: {strengths}\nPreferences: {preferences}\n\nSuggest 3 SMART goals for the next 30 days."
            ),
            
            'crisis_support' => array(
                'system' => "You are a crisis support assistant. Provide immediate, calming support while encouraging professional help when needed. Never provide medical advice.",
                'user' => "User is experiencing:\n\nSituation: {situation}\nIntensity: {intensity}/10\nCurrent State: {current_state}\n\nProvide:\n1. Immediate grounding techniques\n2. Safety affirmations\n3. Clear next steps\n4. Professional resources reminder"
            ),
            
            'insight_generation' => array(
                'system' => "You are an insightful mental health data analyst. Generate meaningful insights from user data that can lead to breakthroughs in understanding.",
                'user' => "Analyze this comprehensive data:\n\n{comprehensive_data}\n\nGenerate insights on:\n1. Hidden connections\n2. Breakthrough opportunities\n3. Strength-based strategies\n4. Personalized recommendations"
            )
        );
        
        // Allow filtering of templates
        $this->prompt_templates = apply_filters('spiralengine_ai_prompt_templates', $this->prompt_templates);
    }
    
    /**
     * Make API request
     */
    private function make_request($endpoint, $data, $method = 'POST') {
        if (empty($this->api_key)) {
            return new WP_Error('no_api_key', __('OpenAI API key not configured', 'spiralengine'));
        }
        
        // Check rate limits
        if ($this->config['enable_rate_limiting']) {
            $rate_check = $this->rate_limiter->check_limits($data);
            if (is_wp_error($rate_check)) {
                return $rate_check;
            }
        }
        
        $url = $this->api_base_url . '/' . $endpoint;
        
        $args = array(
            'method' => $method,
            'timeout' => $this->config['timeout'],
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json'
            )
        );
        
        if ($method === 'POST' && !empty($data)) {
            $args['body'] = json_encode($data);
        }
        
        // Retry logic
        $attempts = 0;
        $last_error = null;
        
        while ($attempts < $this->config['retry_attempts']) {
            $attempts++;
            
            $response = wp_remote_request($url, $args);
            
            if (!is_wp_error($response)) {
                $status = wp_remote_retrieve_response_code($response);
                
                if ($status === 200) {
                    $body = json_decode(wp_remote_retrieve_body($response), true);
                    
                    // Track tokens
                    if (isset($body['usage'])) {
                        $this->token_tracker->track_usage(
                            $data['model'] ?? $this->config['default_model'],
                            $body['usage']
                        );
                        
                        // Update rate limiter
                        if ($this->config['enable_rate_limiting']) {
                            $this->rate_limiter->record_usage($body['usage']);
                        }
                    }
                    
                    // Log successful request
                    $this->log_request($endpoint, $data, $body, 'success');
                    
                    return $body;
                    
                } elseif ($status === 429) {
                    // Rate limit exceeded
                    $retry_after = wp_remote_retrieve_header($response, 'retry-after');
                    $wait_time = $retry_after ? intval($retry_after) : pow(2, $attempts);
                    
                    if ($attempts < $this->config['retry_attempts']) {
                        sleep($wait_time);
                        continue;
                    }
                }
                
                $last_error = new WP_Error(
                    'api_error',
                    sprintf(__('API returned status %d', 'spiralengine'), $status),
                    json_decode(wp_remote_retrieve_body($response), true)
                );
                
            } else {
                $last_error = $response;
            }
            
            // Wait before retry
            if ($attempts < $this->config['retry_attempts']) {
                sleep($this->config['retry_delay'] * $attempts);
            }
        }
        
        // Log failed request
        $this->log_request($endpoint, $data, $last_error, 'error');
        
        return $last_error;
    }
    
    /**
     * Generate completion
     */
    public function generate_completion($prompt, $options = array()) {
        $defaults = array(
            'model' => $this->config['default_model'],
            'temperature' => $this->config['temperature'],
            'max_tokens' => $this->config['max_tokens'],
            'user' => get_current_user_id()
        );
        
        $options = wp_parse_args($options, $defaults);
        
        // Prepare messages
        $messages = array();
        
        if (isset($prompt['system'])) {
            $messages[] = array(
                'role' => 'system',
                'content' => $prompt['system']
            );
        }
        
        if (isset($prompt['user'])) {
            $messages[] = array(
                'role' => 'user',
                'content' => $prompt['user']
            );
        } elseif (is_string($prompt)) {
            $messages[] = array(
                'role' => 'user',
                'content' => $prompt
            );
        }
        
        // Add conversation history if provided
        if (!empty($options['history'])) {
            $messages = array_merge($options['history'], $messages);
        }
        
        // API request data
        $data = array(
            'model' => $options['model'],
            'messages' => $messages,
            'temperature' => $options['temperature'],
            'max_tokens' => $options['max_tokens'],
            'user' => 'spiralengine_user_' . $options['user']
        );
        
        // Add optional parameters
        if (isset($options['top_p'])) {
            $data['top_p'] = $options['top_p'];
        }
        
        if (isset($options['frequency_penalty'])) {
            $data['frequency_penalty'] = $options['frequency_penalty'];
        }
        
        if (isset($options['presence_penalty'])) {
            $data['presence_penalty'] = $options['presence_penalty'];
        }
        
        if (isset($options['stop'])) {
            $data['stop'] = $options['stop'];
        }
        
        // Make request
        $response = $this->make_request('chat/completions', $data);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        // Extract completion
        if (isset($response['choices'][0]['message']['content'])) {
            return array(
                'content' => $response['choices'][0]['message']['content'],
                'usage' => $response['usage'] ?? array(),
                'model' => $response['model'] ?? $options['model'],
                'finish_reason' => $response['choices'][0]['finish_reason'] ?? 'unknown'
            );
        }
        
        return new WP_Error('invalid_response', __('Invalid API response', 'spiralengine'));
    }
    
    /**
     * Generate embedding
     */
    public function generate_embedding($text, $model = 'text-embedding-ada-002') {
        $data = array(
            'model' => $model,
            'input' => $text
        );
        
        $response = $this->make_request('embeddings', $data);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        if (isset($response['data'][0]['embedding'])) {
            return array(
                'embedding' => $response['data'][0]['embedding'],
                'usage' => $response['usage'] ?? array()
            );
        }
        
        return new WP_Error('invalid_response', __('Invalid API response', 'spiralengine'));
    }
    
    /**
     * Analyze assessment
     */
    public function analyze_assessment($assessment_data) {
        $template = $this->prompt_templates['assessment_analysis'];
        
        // Format responses
        $responses_text = '';
        foreach ($assessment_data['responses'] as $question => $response) {
            $responses_text .= "Q: $question\nA: $response\n\n";
        }
        
        $prompt = array(
            'system' => $template['system'],
            'user' => str_replace('{responses}', $responses_text, $template['user'])
        );
        
        return $this->generate_completion($prompt, array(
            'temperature' => 0.7,
            'max_tokens' => 1000
        ));
    }
    
    /**
     * Analyze episode
     */
    public function analyze_episode($episode_data) {
        $template = $this->prompt_templates['episode_analysis'];
        
        // Replace placeholders
        $replacements = array(
            '{episode_type}' => $episode_data['type'],
            '{datetime}' => $episode_data['datetime'],
            '{duration}' => $episode_data['duration'],
            '{intensity}' => $episode_data['intensity'],
            '{triggers}' => implode(', ', $episode_data['triggers']),
            '{symptoms}' => implode(', ', $episode_data['symptoms']),
            '{thoughts}' => $episode_data['thoughts'],
            '{coping_strategies}' => implode(', ', $episode_data['coping_strategies'])
        );
        
        $system = str_replace(array_keys($replacements), array_values($replacements), $template['system']);
        $user = str_replace(array_keys($replacements), array_values($replacements), $template['user']);
        
        $prompt = array(
            'system' => $system,
            'user' => $user
        );
        
        return $this->generate_completion($prompt, array(
            'temperature' => 0.6,
            'max_tokens' => 1200
        ));
    }
    
    /**
     * Get coping suggestions
     */
    public function get_coping_suggestions($context) {
        $template = $this->prompt_templates['coping_suggestions'];
        
        $replacements = array(
            '{episode_type}' => $context['episode_type'],
            '{triggers}' => implode(', ', $context['triggers']),
            '{past_strategies}' => implode(', ', $context['past_strategies']),
            '{preferences}' => implode(', ', $context['preferences'])
        );
        
        $user = str_replace(array_keys($replacements), array_values($replacements), $template['user']);
        
        $prompt = array(
            'system' => $template['system'],
            'user' => $user
        );
        
        return $this->generate_completion($prompt, array(
            'temperature' => 0.8,
            'max_tokens' => 800
        ));
    }
    
    /**
     * Recognize patterns
     */
    public function recognize_patterns($episodes_data) {
        $template = $this->prompt_templates['pattern_recognition'];
        
        // Format episodes data
        $episodes_text = json_encode($episodes_data, JSON_PRETTY_PRINT);
        
        $prompt = array(
            'system' => $template['system'],
            'user' => str_replace('{episodes_data}', $episodes_text, $template['user'])
        );
        
        return $this->generate_completion($prompt, array(
            'temperature' => 0.5,
            'max_tokens' => 1500
        ));
    }
    
    /**
     * Generate progress summary
     */
    public function generate_progress_summary($progress_data) {
        $template = $this->prompt_templates['progress_summary'];
        
        $replacements = array(
            '{period}' => $progress_data['period'],
            '{total_episodes}' => $progress_data['total_episodes'],
            '{avg_intensity}' => $progress_data['avg_intensity'],
            '{common_triggers}' => implode(', ', $progress_data['common_triggers']),
            '{common_strategies}' => implode(', ', $progress_data['common_strategies']),
            '{goals_achieved}' => implode(', ', $progress_data['goals_achieved'])
        );
        
        $user = str_replace(array_keys($replacements), array_values($replacements), $template['user']);
        
        $prompt = array(
            'system' => $template['system'],
            'user' => $user
        );
        
        return $this->generate_completion($prompt, array(
            'temperature' => 0.7,
            'max_tokens' => 1000
        ));
    }
    
    /**
     * Suggest goals
     */
    public function suggest_goals($user_data) {
        $template = $this->prompt_templates['goal_suggestions'];
        
        $replacements = array(
            '{challenges}' => implode(', ', $user_data['challenges']),
            '{progress}' => implode(', ', $user_data['progress']),
            '{strengths}' => implode(', ', $user_data['strengths']),
            '{preferences}' => implode(', ', $user_data['preferences'])
        );
        
        $user = str_replace(array_keys($replacements), array_values($replacements), $template['user']);
        
        $prompt = array(
            'system' => $template['system'],
            'user' => $user
        );
        
        return $this->generate_completion($prompt, array(
            'temperature' => 0.8,
            'max_tokens' => 800
        ));
    }
    
    /**
     * Chat conversation
     */
    public function chat($message, $conversation_id = null, $context = array()) {
        // Get conversation history
        $history = array();
        if ($conversation_id) {
            $history = $this->get_conversation_history($conversation_id);
        }
        
        // Add system message if needed
        if (empty($history) && !empty($context['system_prompt'])) {
            $history[] = array(
                'role' => 'system',
                'content' => $context['system_prompt']
            );
        }
        
        // Add user message
        $history[] = array(
            'role' => 'user',
            'content' => $message
        );
        
        // Generate response
        $data = array(
            'model' => $context['model'] ?? $this->config['default_model'],
            'messages' => $history,
            'temperature' => $context['temperature'] ?? 0.7,
            'max_tokens' => $context['max_tokens'] ?? 1000
        );
        
        $response = $this->make_request('chat/completions', $data);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        // Extract response
        if (isset($response['choices'][0]['message'])) {
            $assistant_message = $response['choices'][0]['message'];
            
            // Save to conversation history
            if ($conversation_id) {
                $this->save_conversation_message($conversation_id, 'user', $message);
                $this->save_conversation_message($conversation_id, 'assistant', $assistant_message['content']);
            }
            
            return array(
                'message' => $assistant_message['content'],
                'usage' => $response['usage'] ?? array(),
                'conversation_id' => $conversation_id
            );
        }
        
        return new WP_Error('invalid_response', __('Invalid API response', 'spiralengine'));
    }
    
    /**
     * Get usage statistics
     */
    public function get_usage_stats($period = 'today') {
        return $this->token_tracker->get_usage_stats($period);
    }
    
    /**
     * Get model info
     */
    public function get_model_info($model = null) {
        if ($model && isset($this->models[$model])) {
            return $this->models[$model];
        }
        
        return $this->models;
    }
    
    /**
     * Estimate cost
     */
    public function estimate_cost($prompt_tokens, $completion_tokens, $model = null) {
        $model = $model ?? $this->config['default_model'];
        
        if (!isset($this->models[$model])) {
            return 0;
        }
        
        $model_info = $this->models[$model];
        
        $prompt_cost = ($prompt_tokens / 1000) * $model_info['cost_per_1k_prompt'];
        $completion_cost = ($completion_tokens / 1000) * $model_info['cost_per_1k_completion'];
        
        return $prompt_cost + $completion_cost;
    }
    
    /**
     * AJAX handlers
     */
    public function ajax_generate() {
        check_ajax_referer('spiralengine_ai', 'nonce');
        
        if (!current_user_can('spiralengine_use_ai')) {
            wp_die(__('Insufficient permissions', 'spiralengine'));
        }
        
        $prompt = wp_kses_post($_POST['prompt'] ?? '');
        $options = $_POST['options'] ?? array();
        
        if (empty($prompt)) {
            wp_send_json_error(__('Prompt is required', 'spiralengine'));
        }
        
        $result = $this->generate_completion($prompt, $options);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        } else {
            wp_send_json_success($result);
        }
    }
    
    public function ajax_analyze() {
        check_ajax_referer('spiralengine_ai', 'nonce');
        
        if (!current_user_can('spiralengine_use_ai')) {
            wp_die(__('Insufficient permissions', 'spiralengine'));
        }
        
        $type = sanitize_text_field($_POST['type'] ?? '');
        $data = $_POST['data'] ?? array();
        
        switch ($type) {
            case 'assessment':
                $result = $this->analyze_assessment($data);
                break;
                
            case 'episode':
                $result = $this->analyze_episode($data);
                break;
                
            case 'patterns':
                $result = $this->recognize_patterns($data);
                break;
                
            case 'progress':
                $result = $this->generate_progress_summary($data);
                break;
                
            default:
                wp_send_json_error(__('Invalid analysis type', 'spiralengine'));
        }
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        } else {
            wp_send_json_success($result);
        }
    }
    
    public function ajax_suggest() {
        check_ajax_referer('spiralengine_ai', 'nonce');
        
        if (!current_user_can('spiralengine_use_ai')) {
            wp_die(__('Insufficient permissions', 'spiralengine'));
        }
        
        $type = sanitize_text_field($_POST['type'] ?? '');
        $context = $_POST['context'] ?? array();
        
        switch ($type) {
            case 'coping':
                $result = $this->get_coping_suggestions($context);
                break;
                
            case 'goals':
                $result = $this->suggest_goals($context);
                break;
                
            default:
                wp_send_json_error(__('Invalid suggestion type', 'spiralengine'));
        }
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        } else {
            wp_send_json_success($result);
        }
    }
    
    public function ajax_chat() {
        check_ajax_referer('spiralengine_ai', 'nonce');
        
        if (!current_user_can('spiralengine_use_ai')) {
            wp_die(__('Insufficient permissions', 'spiralengine'));
        }
        
        $message = wp_kses_post($_POST['message'] ?? '');
        $conversation_id = sanitize_text_field($_POST['conversation_id'] ?? '');
        $context = $_POST['context'] ?? array();
        
        if (empty($message)) {
            wp_send_json_error(__('Message is required', 'spiralengine'));
        }
        
        // Generate conversation ID if not provided
        if (empty($conversation_id)) {
            $conversation_id = 'conv_' . wp_generate_password(16, false);
        }
        
        $result = $this->chat($message, $conversation_id, $context);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        } else {
            wp_send_json_success($result);
        }
    }
    
    public function ajax_get_usage() {
        check_ajax_referer('spiralengine_ai', 'nonce');
        
        if (!current_user_can('spiralengine_view_analytics')) {
            wp_die(__('Insufficient permissions', 'spiralengine'));
        }
        
        $period = sanitize_text_field($_POST['period'] ?? 'today');
        $stats = $this->get_usage_stats($period);
        
        wp_send_json_success($stats);
    }
    
    /**
     * Helper methods
     */
    private function log_request($endpoint, $request_data, $response_data, $status) {
        global $wpdb;
        
        // Remove sensitive data
        if (isset($request_data['messages'])) {
            $request_data['messages'] = '[REDACTED]';
        }
        
        $wpdb->insert(
            $wpdb->prefix . 'spiralengine_ai_logs',
            array(
                'user_id' => get_current_user_id(),
                'endpoint' => $endpoint,
                'model' => $request_data['model'] ?? $this->config['default_model'],
                'prompt_tokens' => $response_data['usage']['prompt_tokens'] ?? 0,
                'completion_tokens' => $response_data['usage']['completion_tokens'] ?? 0,
                'total_tokens' => $response_data['usage']['total_tokens'] ?? 0,
                'status' => $status,
                'error_message' => is_wp_error($response_data) ? $response_data->get_error_message() : '',
                'created_at' => current_time('mysql')
            ),
            array('%d', '%s', '%s', '%d', '%d', '%d', '%s', '%s', '%s')
        );
    }
    
    private function get_conversation_history($conversation_id) {
        $history = get_transient('spiralengine_ai_conversation_' . $conversation_id);
        return $history ? $history : array();
    }
    
    private function save_conversation_message($conversation_id, $role, $content) {
        $history = $this->get_conversation_history($conversation_id);
        
        $history[] = array(
            'role' => $role,
            'content' => $content,
            'timestamp' => current_time('mysql')
        );
        
        // Keep only last 20 messages
        if (count($history) > 20) {
            $history = array_slice($history, -20);
        }
        
        set_transient('spiralengine_ai_conversation_' . $conversation_id, $history, DAY_IN_SECONDS);
    }
    
    public function cleanup_old_logs() {
        global $wpdb;
        
        $retention_days = get_option('spiralengine_ai_log_retention', 30);
        
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->prefix}spiralengine_ai_logs 
             WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
            $retention_days
        ));
    }
    
    public function refresh_token_limits() {
        $this->rate_limiter->reset_limits();
    }
}

/**
 * Rate Limiter Class
 */
class SPIRALENGINE_AI_Rate_Limiter {
    private $requests_per_minute;
    private $tokens_per_minute;
    
    public function __construct($requests_per_minute, $tokens_per_minute) {
        $this->requests_per_minute = $requests_per_minute;
        $this->tokens_per_minute = $tokens_per_minute;
    }
    
    public function check_limits($request_data) {
        $current_minute = date('Y-m-d H:i');
        
        // Get current usage
        $requests = get_transient('spiralengine_ai_requests_' . $current_minute) ?: 0;
        $tokens = get_transient('spiralengine_ai_tokens_' . $current_minute) ?: 0;
        
        // Check request limit
        if ($requests >= $this->requests_per_minute) {
            return new WP_Error('rate_limit_requests', __('Request rate limit exceeded', 'spiralengine'));
        }
        
        // Estimate tokens (rough calculation)
        $estimated_tokens = strlen($request_data['messages'][0]['content'] ?? '') * 0.75;
        
        if ($tokens + $estimated_tokens > $this->tokens_per_minute) {
            return new WP_Error('rate_limit_tokens', __('Token rate limit exceeded', 'spiralengine'));
        }
        
        return true;
    }
    
    public function record_usage($usage) {
        $current_minute = date('Y-m-d H:i');
        
        // Update requests
        $requests = get_transient('spiralengine_ai_requests_' . $current_minute) ?: 0;
        set_transient('spiralengine_ai_requests_' . $current_minute, $requests + 1, 120);
        
        // Update tokens
        $tokens = get_transient('spiralengine_ai_tokens_' . $current_minute) ?: 0;
        set_transient('spiralengine_ai_tokens_' . $current_minute, $tokens + $usage['total_tokens'], 120);
    }
    
    public function reset_limits() {
        // Clear all rate limit transients
        global $wpdb;
        
        $wpdb->query(
            "DELETE FROM {$wpdb->options} 
             WHERE option_name LIKE '_transient_spiralengine_ai_requests_%' 
             OR option_name LIKE '_transient_spiralengine_ai_tokens_%'"
        );
    }
}

/**
 * Token Tracker Class
 */
class SPIRALENGINE_Token_Tracker {
    public function track_usage($model, $usage) {
        $user_id = get_current_user_id();
        
        // Update user's token usage
        $user_usage = get_user_meta($user_id, 'spiralengine_ai_usage', true) ?: array();
        
        $today = date('Y-m-d');
        if (!isset($user_usage[$today])) {
            $user_usage[$today] = array();
        }
        
        if (!isset($user_usage[$today][$model])) {
            $user_usage[$today][$model] = array(
                'prompt_tokens' => 0,
                'completion_tokens' => 0,
                'total_tokens' => 0,
                'requests' => 0
            );
        }
        
        $user_usage[$today][$model]['prompt_tokens'] += $usage['prompt_tokens'];
        $user_usage[$today][$model]['completion_tokens'] += $usage['completion_tokens'];
        $user_usage[$today][$model]['total_tokens'] += $usage['total_tokens'];
        $user_usage[$today][$model]['requests']++;
        
        update_user_meta($user_id, 'spiralengine_ai_usage', $user_usage);
        
        // Update global usage
        $this->update_global_usage($model, $usage);
    }
    
    private function update_global_usage($model, $usage) {
        $global_usage = get_option('spiralengine_ai_global_usage', array());
        
        $today = date('Y-m-d');
        if (!isset($global_usage[$today])) {
            $global_usage[$today] = array();
        }
        
        if (!isset($global_usage[$today][$model])) {
            $global_usage[$today][$model] = array(
                'prompt_tokens' => 0,
                'completion_tokens' => 0,
                'total_tokens' => 0,
                'requests' => 0,
                'users' => array()
            );
        }
        
        $global_usage[$today][$model]['prompt_tokens'] += $usage['prompt_tokens'];
        $global_usage[$today][$model]['completion_tokens'] += $usage['completion_tokens'];
        $global_usage[$today][$model]['total_tokens'] += $usage['total_tokens'];
        $global_usage[$today][$model]['requests']++;
        $global_usage[$today][$model]['users'][get_current_user_id()] = true;
        
        // Keep only last 90 days
        $cutoff = date('Y-m-d', strtotime('-90 days'));
        foreach ($global_usage as $date => $data) {
            if ($date < $cutoff) {
                unset($global_usage[$date]);
            }
        }
        
        update_option('spiralengine_ai_global_usage', $global_usage);
    }
    
    public function get_usage_stats($period = 'today') {
        $stats = array(
            'user' => array(),
            'global' => array()
        );
        
        // Get user stats
        $user_id = get_current_user_id();
        $user_usage = get_user_meta($user_id, 'spiralengine_ai_usage', true) ?: array();
        
        switch ($period) {
            case 'today':
                $stats['user'] = $user_usage[date('Y-m-d')] ?? array();
                break;
                
            case 'week':
                $stats['user'] = $this->aggregate_usage($user_usage, 7);
                break;
                
            case 'month':
                $stats['user'] = $this->aggregate_usage($user_usage, 30);
                break;
        }
        
        // Get global stats
        $global_usage = get_option('spiralengine_ai_global_usage', array());
        
        switch ($period) {
            case 'today':
                $stats['global'] = $global_usage[date('Y-m-d')] ?? array();
                break;
                
            case 'week':
                $stats['global'] = $this->aggregate_usage($global_usage, 7);
                break;
                
            case 'month':
                $stats['global'] = $this->aggregate_usage($global_usage, 30);
                break;
        }
        
        // Calculate costs
        $openai = new SPIRALENGINE_OpenAI();
        
        foreach ($stats['user'] as $model => $usage) {
            $stats['user'][$model]['estimated_cost'] = $openai->estimate_cost(
                $usage['prompt_tokens'],
                $usage['completion_tokens'],
                $model
            );
        }
        
        foreach ($stats['global'] as $model => $usage) {
            if (is_array($usage) && isset($usage['prompt_tokens'])) {
                $stats['global'][$model]['estimated_cost'] = $openai->estimate_cost(
                    $usage['prompt_tokens'],
                    $usage['completion_tokens'],
                    $model
                );
                
                if (isset($usage['users'])) {
                    $stats['global'][$model]['unique_users'] = count($usage['users']);
                }
            }
        }
        
        return $stats;
    }
    
    private function aggregate_usage($usage_data, $days) {
        $aggregated = array();
        
        $start_date = date('Y-m-d', strtotime("-{$days} days"));
        
        foreach ($usage_data as $date => $models) {
            if ($date >= $start_date) {
                foreach ($models as $model => $usage) {
                    if (!isset($aggregated[$model])) {
                        $aggregated[$model] = array(
                            'prompt_tokens' => 0,
                            'completion_tokens' => 0,
                            'total_tokens' => 0,
                            'requests' => 0
                        );
                    }
                    
                    if (is_array($usage)) {
                        $aggregated[$model]['prompt_tokens'] += $usage['prompt_tokens'] ?? 0;
                        $aggregated[$model]['completion_tokens'] += $usage['completion_tokens'] ?? 0;
                        $aggregated[$model]['total_tokens'] += $usage['total_tokens'] ?? 0;
                        $aggregated[$model]['requests'] += $usage['requests'] ?? 0;
                        
                        if (isset($usage['users'])) {
                            if (!isset($aggregated[$model]['users'])) {
                                $aggregated[$model]['users'] = array();
                            }
                            $aggregated[$model]['users'] = array_merge($aggregated[$model]['users'], $usage['users']);
                        }
                    }
                }
            }
        }
        
        return $aggregated;
    }
}

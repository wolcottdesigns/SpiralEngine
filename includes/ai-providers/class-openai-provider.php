<?php
/**
 * SpiralEngine OpenAI Provider
 * 
 * @package    SpiralEngine
 * @subpackage AI/Providers
 * @file       includes/ai-providers/class-openai-provider.php
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * OpenAI provider implementation
 * 
 * Integrates with OpenAI's GPT models for AI analysis and insights
 */
class SpiralEngine_OpenAI_Provider extends SpiralEngine_AI_Provider {
    
    /**
     * OpenAI API endpoint
     *
     * @var string
     */
    private $api_endpoint = 'https://api.openai.com/v1/';
    
    /**
     * System prompts for different analysis types
     *
     * @var array
     */
    private $system_prompts = array();
    
    /**
     * Initialize the provider
     *
     * @return void
     */
    protected function initialize() {
        $this->provider_id = 'openai';
        $this->provider_name = __('OpenAI GPT', 'spiralengine');
        
        // Set available models
        $this->models = array(
            'gpt-4-turbo' => array(
                'name' => 'GPT-4 Turbo',
                'description' => __('Most capable model, best for complex analysis', 'spiralengine'),
                'input_cost' => 0.01, // per 1k tokens
                'output_cost' => 0.03, // per 1k tokens
                'max_tokens' => 128000,
                'default' => true
            ),
            'gpt-4' => array(
                'name' => 'GPT-4',
                'description' => __('High capability model', 'spiralengine'),
                'input_cost' => 0.03,
                'output_cost' => 0.06,
                'max_tokens' => 8192
            ),
            'gpt-3.5-turbo' => array(
                'name' => 'GPT-3.5 Turbo',
                'description' => __('Fast and cost-effective', 'spiralengine'),
                'input_cost' => 0.001,
                'output_cost' => 0.002,
                'max_tokens' => 16385
            )
        );
        
        // Initialize system prompts
        $this->init_system_prompts();
        
        // Set rate limits
        $this->rate_limits = array(
            'requests_per_minute' => 60,
            'tokens_per_minute' => 90000,
            'requests_per_day' => 10000
        );
    }
    
    /**
     * Initialize system prompts for different analysis types
     *
     * @return void
     */
    private function init_system_prompts() {
        $this->system_prompts = array(
            'episode_analysis' => "You are a compassionate mental health AI assistant analyzing user episodes. 
                Your role is to:
                1. Identify patterns and triggers in the episode data
                2. Validate the severity rating (1-10) based on the content
                3. Provide supportive insights without diagnosing
                4. Suggest evidence-based coping strategies
                5. Recognize any concerning patterns that may need professional attention
                
                Always maintain a supportive, non-judgmental tone. Focus on empowerment and practical strategies.
                Format your response as JSON with the following structure:
                {
                    'severity_assessment': {
                        'suggested_severity': 1-10,
                        'severity_rationale': 'explanation'
                    },
                    'patterns': ['pattern1', 'pattern2'],
                    'triggers': ['trigger1', 'trigger2'],
                    'insights': ['insight1', 'insight2'],
                    'coping_suggestions': ['suggestion1', 'suggestion2'],
                    'professional_support_recommended': true/false,
                    'encouragement': 'supportive message'
                }",
                
            'pattern_analysis' => "You are an expert in mental health pattern recognition. Analyze the provided episodes to:
                1. Identify recurring patterns across time
                2. Find correlations between different factors
                3. Detect trends in severity and frequency
                4. Recognize trigger-response patterns
                5. Identify progress or regression trends
                
                Focus on actionable insights that can help the user understand their mental health journey.
                Format response as JSON with:
                {
                    'recurring_patterns': [
                        {
                            'pattern': 'description',
                            'frequency': 'how often',
                            'triggers': ['associated triggers'],
                            'impact': 'severity impact'
                        }
                    ],
                    'correlations': [
                        {
                            'factor1': 'name',
                            'factor2': 'name',
                            'correlation_strength': 'strong/moderate/weak',
                            'description': 'explanation'
                        }
                    ],
                    'trends': {
                        'severity_trend': 'improving/stable/worsening',
                        'frequency_trend': 'decreasing/stable/increasing',
                        'description': 'detailed explanation'
                    },
                    'key_insights': ['insight1', 'insight2'],
                    'recommendations': ['recommendation1', 'recommendation2']
                }",
                
            'insight_generation' => "You are a thoughtful mental health insights generator. Based on the user's data:
                1. Provide personalized insights about their mental health journey
                2. Highlight positive progress and strengths
                3. Identify areas for potential growth
                4. Connect patterns to actionable strategies
                5. Offer perspective on their overall wellbeing
                
                Be encouraging while remaining honest and helpful.
                Format as JSON:
                {
                    'strengths': ['strength1', 'strength2'],
                    'progress_highlights': ['achievement1', 'achievement2'],
                    'growth_opportunities': [
                        {
                            'area': 'description',
                            'suggestion': 'how to improve',
                            'rationale': 'why this matters'
                        }
                    ],
                    'personalized_insights': ['insight1', 'insight2'],
                    'wellbeing_summary': 'overall assessment',
                    'next_steps': ['step1', 'step2']
                }",
                
            'recommendations' => "You are a mental health recommendation system. Provide personalized suggestions based on:
                1. User's current patterns and triggers
                2. Their goals and preferences
                3. Evidence-based interventions
                4. Their progress and setbacks
                5. Available coping strategies
                
                Focus on practical, achievable recommendations.
                Format as JSON:
                {
                    'immediate_actions': [
                        {
                            'action': 'description',
                            'rationale': 'why this helps',
                            'how_to': 'implementation steps'
                        }
                    ],
                    'coping_strategies': [
                        {
                            'strategy': 'name',
                            'when_to_use': 'situations',
                            'expected_benefit': 'outcome'
                        }
                    ],
                    'lifestyle_suggestions': ['suggestion1', 'suggestion2'],
                    'skill_building': [
                        {
                            'skill': 'name',
                            'importance': 'why needed',
                            'resources': 'how to learn'
                        }
                    ],
                    'professional_resources': ['resource1', 'resource2']
                }"
        );
    }
    
    /**
     * Get default configuration
     *
     * @return array
     */
    protected function get_default_config() {
        return array(
            'api_key' => '',
            'model' => 'gpt-4-turbo',
            'temperature' => 0.7,
            'max_tokens' => 2000,
            'top_p' => 1,
            'frequency_penalty' => 0,
            'presence_penalty' => 0,
            'timeout' => 30
        );
    }
    
    /**
     * Analyze content using OpenAI
     *
     * @param array $content Content to analyze
     * @param array $params Analysis parameters
     * @return array Analysis results
     */
    public function analyze(array $content, array $params = array()) {
        // Check availability
        if (!$this->check_availability()) {
            return array('error' => __('OpenAI service is not configured', 'spiralengine'));
        }
        
        // Check rate limits
        if (!$this->check_rate_limit('requests_per_minute')) {
            return array('error' => __('Rate limit exceeded. Please try again in a minute.', 'spiralengine'));
        }
        
        // Prepare the prompt
        $prompt = $this->prepare_prompt($content, $params);
        
        // Get system prompt based on analysis type
        $system_prompt = $this->get_system_prompt($params['type']);
        
        // Prepare API request
        $request_data = array(
            'model' => $this->config['model'],
            'messages' => array(
                array(
                    'role' => 'system',
                    'content' => $system_prompt
                ),
                array(
                    'role' => 'user',
                    'content' => $prompt
                )
            ),
            'temperature' => floatval($this->config['temperature']),
            'max_tokens' => intval($this->config['max_tokens']),
            'top_p' => floatval($this->config['top_p']),
            'frequency_penalty' => floatval($this->config['frequency_penalty']),
            'presence_penalty' => floatval($this->config['presence_penalty']),
            'response_format' => array('type' => 'json_object')
        );
        
        // Make API request
        $response = $this->make_api_request('chat/completions', $request_data);
        
        if (is_wp_error($response)) {
            return array('error' => $response->get_error_message());
        }
        
        // Process response
        $result = $this->process_response($response, $params);
        
        // Log usage if enabled
        if ($this->config['log_usage']) {
            $this->log_usage(array(
                'model' => $this->config['model'],
                'tokens' => $response['usage']['total_tokens'],
                'cost' => $this->calculate_cost($response['usage']),
                'type' => $params['type'],
                'metadata' => array(
                    'prompt_tokens' => $response['usage']['prompt_tokens'],
                    'completion_tokens' => $response['usage']['completion_tokens']
                )
            ));
        }
        
        // Update rate limits
        $this->increment_rate_limit('requests_per_minute');
        $this->increment_rate_limit('tokens_per_minute', $response['usage']['total_tokens']);
        
        return $result;
    }
    
    /**
     * Get available models
     *
     * @return array
     */
    public function get_models() {
        return $this->models;
    }
    
    /**
     * Estimate cost for analysis
     *
     * @param array $params Analysis parameters
     * @return float Estimated cost in USD
     */
    public function estimate_cost(array $params) {
        $model = isset($params['model']) ? $params['model'] : $this->config['model'];
        $estimated_tokens = isset($params['estimated_tokens']) ? $params['estimated_tokens'] : 1000;
        
        if (!isset($this->models[$model])) {
            return 0;
        }
        
        $model_info = $this->models[$model];
        
        // Estimate 70% input, 30% output for typical analysis
        $input_tokens = $estimated_tokens * 0.7;
        $output_tokens = $estimated_tokens * 0.3;
        
        $input_cost = ($input_tokens / 1000) * $model_info['input_cost'];
        $output_cost = ($output_tokens / 1000) * $model_info['output_cost'];
        
        return round($input_cost + $output_cost, 4);
    }
    
    /**
     * Check if provider is available
     *
     * @return bool
     */
    public function check_availability() {
        // Check if API key is configured
        if (empty($this->config['api_key'])) {
            return false;
        }
        
        // Optionally verify API key with a test request
        $cache_key = 'spiralengine_openai_availability';
        $cached = get_transient($cache_key);
        
        if ($cached !== false) {
            return $cached === 'available';
        }
        
        // Make a lightweight test request
        $test_response = $this->make_api_request('models', array(), 'GET');
        
        $is_available = !is_wp_error($test_response);
        set_transient($cache_key, $is_available ? 'available' : 'unavailable', 3600);
        
        return $is_available;
    }
    
    /**
     * Make API request to OpenAI
     *
     * @param string $endpoint API endpoint
     * @param array $data Request data
     * @param string $method HTTP method
     * @return array|WP_Error Response or error
     */
    private function make_api_request($endpoint, $data = array(), $method = 'POST') {
        $url = $this->api_endpoint . $endpoint;
        
        $headers = array(
            'Authorization' => 'Bearer ' . $this->config['api_key'],
            'Content-Type' => 'application/json'
        );
        
        $args = array(
            'headers' => $headers,
            'timeout' => $this->config['timeout'],
            'method' => $method
        );
        
        if ($method === 'POST' && !empty($data)) {
            $args['body'] = json_encode($data);
        }
        
        $response = wp_remote_request($url, $args);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return new WP_Error('json_decode_error', __('Invalid JSON response from OpenAI', 'spiralengine'));
        }
        
        if (isset($data['error'])) {
            return new WP_Error('openai_error', $data['error']['message']);
        }
        
        return $data;
    }
    
    /**
     * Prepare prompt for OpenAI
     *
     * @param array $content Content to analyze
     * @param array $params Parameters
     * @return string Formatted prompt
     */
    private function prepare_prompt($content, $params) {
        $prompt_parts = array();
        
        // Add context based on analysis type
        switch ($params['type']) {
            case 'episode_analysis':
                $prompt_parts[] = "Please analyze the following mental health episode:";
                $prompt_parts[] = $this->format_episode_for_prompt($content);
                break;
                
            case 'pattern_analysis':
                $prompt_parts[] = "Please analyze patterns in the following mental health episodes:";
                $prompt_parts[] = $this->format_episodes_for_prompt($content);
                break;
                
            case 'insight_generation':
                $prompt_parts[] = "Please generate insights for this user's mental health journey:";
                $prompt_parts[] = $this->format_user_data_for_prompt($content);
                break;
                
            case 'recommendations':
                $prompt_parts[] = "Please provide personalized mental health recommendations based on:";
                $prompt_parts[] = $this->format_recommendation_context($content);
                break;
        }
        
        // Add specific instructions if provided
        if (!empty($params['instructions'])) {
            $prompt_parts[] = "\nAdditional instructions: " . $params['instructions'];
        }
        
        return implode("\n\n", $prompt_parts);
    }
    
    /**
     * Format episode for prompt
     *
     * @param array $episode Episode data
     * @return string Formatted episode
     */
    private function format_episode_for_prompt($episode) {
        $formatted = array();
        
        $formatted[] = "Widget Type: " . $episode['widget_type'];
        $formatted[] = "Severity: " . $episode['severity'] . "/10";
        $formatted[] = "Timestamp: " . $episode['timestamp'];
        
        if (!empty($episode['data'])) {
            $formatted[] = "Episode Data:";
            foreach ($episode['data'] as $key => $value) {
                if (is_array($value)) {
                    $formatted[] = "- $key: " . json_encode($value);
                } else {
                    $formatted[] = "- $key: $value";
                }
            }
        }
        
        if (!empty($episode['context'])) {
            $formatted[] = "Context:";
            $formatted[] = "- Time of day: " . $episode['context']['time_of_day'];
            $formatted[] = "- Day of week: " . $episode['context']['day_of_week'];
            $formatted[] = "- Recent episodes (24h): " . $episode['context']['recent_episodes'];
        }
        
        return implode("\n", $formatted);
    }
    
    /**
     * Format multiple episodes for prompt
     *
     * @param array $data Episode data
     * @return string Formatted episodes
     */
    private function format_episodes_for_prompt($data) {
        $formatted = array();
        
        $formatted[] = "Total episodes: " . $data['episode_count'];
        $formatted[] = "Timespan: " . $data['timespan']['days'] . " days";
        $formatted[] = "Date range: " . $data['timespan']['start'] . " to " . $data['timespan']['end'];
        
        $formatted[] = "\nEpisodes:";
        
        foreach ($data['episodes'] as $index => $episode) {
            $formatted[] = "\n--- Episode " . ($index + 1) . " ---";
            $formatted[] = $this->format_episode_for_prompt($episode);
        }
        
        return implode("\n", $formatted);
    }
    
    /**
     * Format user data for prompt
     *
     * @param array $data User data
     * @return string Formatted data
     */
    private function format_user_data_for_prompt($data) {
        $formatted = array();
        
        // User profile
        if (!empty($data['user_profile'])) {
            $formatted[] = "User Profile:";
            $formatted[] = "- Member since: " . $data['user_profile']['member_since'];
            $formatted[] = "- Membership tier: " . $data['user_profile']['tier'];
            
            if (!empty($data['user_profile']['goals'])) {
                $formatted[] = "- Active goals: " . count($data['user_profile']['goals']);
            }
        }
        
        // Statistics
        if (!empty($data['stats'])) {
            $formatted[] = "\nStatistics:";
            $formatted[] = "- Total episodes: " . $data['stats']['total_episodes'];
            $formatted[] = "- Average severity: " . round($data['stats']['average_severity'], 1);
            $formatted[] = "- Most used widget: " . $data['stats']['most_used_widget'];
        }
        
        // Recent episodes summary
        if (!empty($data['episodes'])) {
            $formatted[] = "\nRecent episodes: " . count($data['episodes']);
            
            // Group by widget type
            $by_widget = array();
            foreach ($data['episodes'] as $episode) {
                $widget = $episode['widget_id'];
                if (!isset($by_widget[$widget])) {
                    $by_widget[$widget] = 0;
                }
                $by_widget[$widget]++;
            }
            
            $formatted[] = "By widget type:";
            foreach ($by_widget as $widget => $count) {
                $formatted[] = "- $widget: $count episodes";
            }
        }
        
        return implode("\n", $formatted);
    }
    
    /**
     * Format recommendation context
     *
     * @param array $content Content data
     * @return string Formatted context
     */
    private function format_recommendation_context($content) {
        $formatted = array();
        
        $formatted[] = "Context: " . $content['context'];
        
        // Add user profile summary
        if (!empty($content['user_profile'])) {
            $formatted[] = $this->format_user_data_for_prompt(array('user_profile' => $content['user_profile']));
        }
        
        // Add context-specific data
        switch ($content['context']) {
            case 'coping_skills':
                if (!empty($content['used_skills'])) {
                    $formatted[] = "\nCoping skills used: " . count($content['used_skills']);
                }
                if (!empty($content['skill_effectiveness'])) {
                    $formatted[] = "Effectiveness data available";
                }
                break;
                
            case 'goals':
                if (!empty($content['current_goals'])) {
                    $formatted[] = "\nActive goals: " . count($content['current_goals']);
                    foreach ($content['current_goals'] as $goal) {
                        $formatted[] = "- " . ($goal['title'] ?? 'Untitled goal');
                    }
                }
                break;
                
            case 'triggers':
                if (!empty($content['common_triggers'])) {
                    $formatted[] = "\nTrigger data points: " . count($content['common_triggers']);
                }
                break;
        }
        
        // Add recent episode summary
        if (!empty($content['recent_episodes'])) {
            $formatted[] = "\nRecent activity (7 days): " . count($content['recent_episodes']) . " episodes";
        }
        
        return implode("\n", $formatted);
    }
    
    /**
     * Get system prompt for analysis type
     *
     * @param string $type Analysis type
     * @return string System prompt
     */
    private function get_system_prompt($type) {
        return isset($this->system_prompts[$type]) 
            ? $this->system_prompts[$type] 
            : $this->system_prompts['episode_analysis'];
    }
    
    /**
     * Process OpenAI response
     *
     * @param array $response API response
     * @param array $params Original parameters
     * @return array Processed result
     */
    private function process_response($response, $params) {
        if (!isset($response['choices'][0]['message']['content'])) {
            return array('error' => __('Invalid response format from OpenAI', 'spiralengine'));
        }
        
        $content = $response['choices'][0]['message']['content'];
        
        // Try to parse as JSON
        $parsed = json_decode($content, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            // If not valid JSON, return as text insight
            return array(
                'type' => $params['type'],
                'content' => $content,
                'format' => 'text',
                'model' => $response['model'],
                'usage' => $response['usage']
            );
        }
        
        // Return structured response
        return array(
            'type' => $params['type'],
            'data' => $parsed,
            'format' => 'structured',
            'model' => $response['model'],
            'usage' => $response['usage'],
            'timestamp' => current_time('mysql')
        );
    }
    
    /**
     * Calculate cost from usage data
     *
     * @param array $usage Usage data from OpenAI
     * @return float Cost in USD
     */
    private function calculate_cost($usage) {
        $model = $this->config['model'];
        
        if (!isset($this->models[$model])) {
            return 0;
        }
        
        $model_info = $this->models[$model];
        
        $input_cost = ($usage['prompt_tokens'] / 1000) * $model_info['input_cost'];
        $output_cost = ($usage['completion_tokens'] / 1000) * $model_info['output_cost'];
        
        return round($input_cost + $output_cost, 6);
    }
}

// Register the provider
add_action('spiralengine_register_ai_providers', function($ai_service) {
    $ai_service->register_provider('openai', 'SpiralEngine_OpenAI_Provider');
});


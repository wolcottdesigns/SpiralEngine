<?php
/**
 * SpiralEngine Mock AI Provider
 * 
 * @package    SpiralEngine
 * @subpackage AI/Providers
 * @file       includes/ai-providers/class-mock-provider.php
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Mock AI provider for testing and development
 * 
 * Provides simulated AI responses without requiring API keys
 */
class SpiralEngine_Mock_Provider extends SpiralEngine_AI_Provider {
    
    /**
     * Mock response templates
     *
     * @var array
     */
    private $response_templates = array();
    
    /**
     * Initialize the provider
     *
     * @return void
     */
    protected function initialize() {
        $this->provider_id = 'mock';
        $this->provider_name = __('Mock AI Provider (Testing)', 'spiralengine');
        
        // Set available models
        $this->models = array(
            'mock-advanced' => array(
                'name' => 'Mock Advanced Model',
                'description' => __('Simulates advanced AI responses', 'spiralengine'),
                'input_cost' => 0,
                'output_cost' => 0,
                'max_tokens' => 10000,
                'default' => true
            ),
            'mock-basic' => array(
                'name' => 'Mock Basic Model',
                'description' => __('Simulates basic AI responses', 'spiralengine'),
                'input_cost' => 0,
                'output_cost' => 0,
                'max_tokens' => 5000
            )
        );
        
        // Initialize response templates
        $this->init_response_templates();
    }
    
    /**
     * Initialize response templates
     *
     * @return void
     */
    private function init_response_templates() {
        $this->response_templates = array(
            'episode_analysis' => array(
                'severity_assessment' => array(
                    'suggested_severity' => 6,
                    'severity_rationale' => 'Based on the content and context, this appears to be a moderate episode.'
                ),
                'patterns' => array(
                    'Time-based pattern detected',
                    'Stress-related trigger identified'
                ),
                'triggers' => array(
                    'Work stress',
                    'Sleep disruption'
                ),
                'insights' => array(
                    'Your episodes tend to occur more frequently during weekday mornings',
                    'There appears to be a correlation between work deadlines and episode severity'
                ),
                'coping_suggestions' => array(
                    'Try the 5-4-3-2-1 grounding technique when you feel an episode starting',
                    'Consider scheduling brief breaks during high-stress work periods'
                ),
                'professional_support_recommended' => false,
                'encouragement' => 'You\'re doing great by tracking your episodes. This awareness is a powerful tool for improvement.'
            ),
            
            'pattern_analysis' => array(
                'recurring_patterns' => array(
                    array(
                        'pattern' => 'Morning anxiety spike',
                        'frequency' => '4-5 times per week',
                        'triggers' => array('Work anticipation', 'Morning routine rush'),
                        'impact' => 'Moderate increase in severity'
                    ),
                    array(
                        'pattern' => 'Weekend recovery',
                        'frequency' => 'Weekly',
                        'triggers' => array('Reduced obligations'),
                        'impact' => 'Significant severity decrease'
                    )
                ),
                'correlations' => array(
                    array(
                        'factor1' => 'Sleep quality',
                        'factor2' => 'Episode severity',
                        'correlation_strength' => 'strong',
                        'description' => 'Poor sleep quality strongly correlates with increased episode severity the following day'
                    )
                ),
                'trends' => array(
                    'severity_trend' => 'improving',
                    'frequency_trend' => 'stable',
                    'description' => 'Overall severity showing gradual improvement while frequency remains consistent'
                ),
                'key_insights' => array(
                    'Your coping strategies are becoming more effective over time',
                    'Maintaining consistent sleep schedule could further reduce episode severity'
                ),
                'recommendations' => array(
                    'Prioritize sleep hygiene to leverage the strong sleep-severity correlation',
                    'Continue using successful coping strategies, especially grounding techniques'
                )
            ),
            
            'recommendations' => array(
                'immediate_actions' => array(
                    array(
                        'action' => 'Take 5 deep breaths',
                        'rationale' => 'Activates parasympathetic nervous system for immediate calming',
                        'how_to' => 'Breathe in for 4 counts, hold for 4, out for 6'
                    ),
                    array(
                        'action' => 'Step outside for fresh air',
                        'rationale' => 'Change of environment can interrupt negative thought patterns',
                        'how_to' => 'Take a 5-minute walk or simply stand outside'
                    )
                ),
                'coping_strategies' => array(
                    array(
                        'strategy' => 'Progressive Muscle Relaxation',
                        'when_to_use' => 'When feeling physical tension or anxiety',
                        'expected_benefit' => 'Reduces physical symptoms of stress'
                    ),
                    array(
                        'strategy' => 'Mindful journaling',
                        'when_to_use' => 'End of day reflection',
                        'expected_benefit' => 'Processes emotions and identifies patterns'
                    )
                ),
                'lifestyle_suggestions' => array(
                    'Establish a consistent sleep schedule',
                    'Incorporate 20 minutes of daily physical activity'
                ),
                'skill_building' => array(
                    array(
                        'skill' => 'Emotion regulation',
                        'importance' => 'Core skill for managing episode intensity',
                        'resources' => 'DBT workbooks or online courses'
                    )
                ),
                'professional_resources' => array(
                    'Consider CBT therapy for long-term pattern change',
                    'Explore mindfulness-based stress reduction programs'
                )
            )
        );
    }
    
    /**
     * Get default configuration
     *
     * @return array
     */
    protected function get_default_config() {
        return array(
            'response_delay' => 1, // Simulate API delay
            'randomize_responses' => true,
            'error_rate' => 0, // Percentage chance of simulated error
            'model' => 'mock-advanced'
        );
    }
    
    /**
     * Analyze content using mock AI
     *
     * @param array $content Content to analyze
     * @param array $params Analysis parameters
     * @return array Analysis results
     */
    public function analyze(array $content, array $params = array()) {
        // Simulate processing delay
        if ($this->config['response_delay'] > 0) {
            sleep($this->config['response_delay']);
        }
        
        // Simulate random errors if configured
        if ($this->config['error_rate'] > 0 && rand(1, 100) <= $this->config['error_rate']) {
            return array('error' => __('Simulated API error for testing', 'spiralengine'));
        }
        
        // Generate mock response based on analysis type
        $type = isset($params['type']) ? $params['type'] : 'episode_analysis';
        
        switch ($type) {
            case 'episode_analysis':
                return $this->generate_episode_analysis($content, $params);
                
            case 'pattern_analysis':
                return $this->generate_pattern_analysis($content, $params);
                
            case 'insight_generation':
                return $this->generate_insights($content, $params);
                
            case 'recommendations':
                return $this->generate_recommendations($content, $params);
                
            case 'input_analysis':
                return $this->generate_input_analysis($content, $params);
                
            case 'outcome_prediction':
                return $this->generate_outcome_prediction($content, $params);
                
            case 'predictive_analysis':
                return $this->generate_predictive_analysis($content, $params);
                
            default:
                return $this->generate_generic_response($content, $params);
        }
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
     * Estimate cost for analysis (always 0 for mock)
     *
     * @param array $params Analysis parameters
     * @return float Estimated cost in USD
     */
    public function estimate_cost(array $params) {
        return 0;
    }
    
    /**
     * Check if provider is available (always true for mock)
     *
     * @return bool
     */
    public function check_availability() {
        return true;
    }
    
    /**
     * Generate episode analysis
     *
     * @param array $content Content
     * @param array $params Parameters
     * @return array Analysis
     */
    private function generate_episode_analysis($content, $params) {
        $template = $this->response_templates['episode_analysis'];
        
        // Customize based on content
        if (isset($content['severity'])) {
            $severity = intval($content['severity']);
            $template['severity_assessment']['suggested_severity'] = $severity;
            
            if ($severity >= 8) {
                $template['professional_support_recommended'] = true;
                $template['encouragement'] = 'This seems like a difficult time. Remember that seeking support is a sign of strength.';
            }
        }
        
        // Add some randomization if configured
        if ($this->config['randomize_responses']) {
            $template = $this->randomize_template($template);
        }
        
        return array(
            'type' => 'episode_analysis',
            'data' => $template,
            'format' => 'structured',
            'model' => $this->config['model'],
            'usage' => array(
                'prompt_tokens' => rand(200, 400),
                'completion_tokens' => rand(300, 500),
                'total_tokens' => rand(500, 900)
            ),
            'timestamp' => current_time('mysql')
        );
    }
    
    /**
     * Generate pattern analysis
     *
     * @param array $content Content
     * @param array $params Parameters
     * @return array Analysis
     */
    private function generate_pattern_analysis($content, $params) {
        $template = $this->response_templates['pattern_analysis'];
        
        // Customize based on episode count
        if (isset($content['episode_count'])) {
            if ($content['episode_count'] < 20) {
                $template['trends']['description'] = 'Limited data for comprehensive trend analysis, but initial patterns are emerging';
            }
        }
        
        // Add time-based customization
        if (isset($content['timespan'])) {
            $days = $content['timespan']['days'] ?? 30;
            $template['key_insights'][] = sprintf('Analysis based on %d days of data', $days);
        }
        
        return array(
            'type' => 'pattern_analysis',
            'data' => $template,
            'format' => 'structured',
            'model' => $this->config['model'],
            'usage' => array(
                'prompt_tokens' => rand(500, 800),
                'completion_tokens' => rand(600, 900),
                'total_tokens' => rand(1100, 1700)
            ),
            'timestamp' => current_time('mysql')
        );
    }
    
    /**
     * Generate insights
     *
     * @param array $content Content
     * @param array $params Parameters
     * @return array Insights
     */
    private function generate_insights($content, $params) {
        $insights = array(
            'strengths' => array(
                'Consistent tracking shows commitment to wellness',
                'Developing awareness of personal patterns'
            ),
            'progress_highlights' => array(
                'Severity decreased by 15% compared to last month',
                'Successfully used coping strategies 8 times this week'
            ),
            'growth_opportunities' => array(
                array(
                    'area' => 'Sleep consistency',
                    'suggestion' => 'Establish a regular bedtime routine',
                    'rationale' => 'Better sleep correlates with reduced episode severity'
                ),
                array(
                    'area' => 'Social connection',
                    'suggestion' => 'Schedule regular check-ins with support network',
                    'rationale' => 'Social support enhances resilience'
                )
            ),
            'personalized_insights' => array(
                'Your episodes are 40% less severe on days with morning exercise',
                'Journaling appears to be your most effective coping strategy'
            ),
            'wellbeing_summary' => 'Overall showing positive trajectory with room for optimization in sleep and social areas',
            'next_steps' => array(
                'Focus on sleep hygiene this week',
                'Try one new coping skill from your list'
            )
        );
        
        // Customize based on focus areas
        if (isset($params['focus_areas'])) {
            if (in_array('triggers', $params['focus_areas'])) {
                $insights['personalized_insights'][] = 'Work stress remains your primary trigger';
            }
        }
        
        return array(
            'type' => 'insight_generation',
            'data' => $insights,
            'format' => 'structured',
            'model' => $this->config['model'],
            'usage' => array(
                'prompt_tokens' => rand(400, 600),
                'completion_tokens' => rand(500, 700),
                'total_tokens' => rand(900, 1300)
            ),
            'timestamp' => current_time('mysql')
        );
    }
    
    /**
     * Generate recommendations
     *
     * @param array $content Content
     * @param array $params Parameters
     * @return array Recommendations
     */
    private function generate_recommendations($content, $params) {
        $template = $this->response_templates['recommendations'];
        
        // Customize based on context
        if (isset($params['context'])) {
            switch ($params['context']) {
                case 'coping_skills':
                    $template['immediate_actions'][0] = array(
                        'action' => 'Practice your most effective coping skill',
                        'rationale' => 'Build on what already works for you',
                        'how_to' => 'Use the technique that helped last time'
                    );
                    break;
                    
                case 'goals':
                    $template['lifestyle_suggestions'] = array(
                        'Set one small, achievable goal for this week',
                        'Break larger goals into daily micro-habits'
                    );
                    break;
                    
                case 'predictive':
                    $template['immediate_actions'][0] = array(
                        'action' => 'Prepare for potential triggers',
                        'rationale' => 'Patterns suggest increased risk in next 48 hours',
                        'how_to' => 'Review your coping strategy list and have tools ready'
                    );
                    break;
            }
        }
        
        return array(
            'type' => 'recommendations',
            'data' => $template,
            'format' => 'structured',
            'model' => $this->config['model'],
            'usage' => array(
                'prompt_tokens' => rand(300, 500),
                'completion_tokens' => rand(400, 600),
                'total_tokens' => rand(700, 1100)
            ),
            'timestamp' => current_time('mysql')
        );
    }
    
    /**
     * Generate input analysis
     *
     * @param array $content Content
     * @param array $params Parameters
     * @return array Analysis
     */
    private function generate_input_analysis($content, $params) {
        $analysis = array();
        
        if (isset($params['analysis_focus'])) {
            switch ($params['analysis_focus']) {
                case 'cognitive_distortions':
                    $analysis = array(
                        'patterns' => array(
                            'All-or-nothing thinking detected',
                            'Possible catastrophizing'
                        ),
                        'coping_suggestions' => array(
                            'Try to find the middle ground in this situation',
                            'Ask yourself: What evidence supports a less extreme view?'
                        )
                    );
                    break;
                    
                case 'sentiment':
                    $analysis = array(
                        'sentiment' => $this->generate_random_sentiment(),
                        'emotions' => array(
                            'primary' => 'anxiety',
                            'secondary' => array('frustration', 'hope')
                        ),
                        'themes' => array('work stress', 'self-improvement', 'relationships')
                    );
                    break;
                    
                default:
                    $analysis = array(
                        'summary' => 'Input analyzed successfully',
                        'key_points' => array('Point 1', 'Point 2')
                    );
            }
        }
        
        return array(
            'type' => 'input_analysis',
            'data' => $analysis,
            'format' => 'structured',
            'model' => $this->config['model'],
            'usage' => array(
                'prompt_tokens' => rand(100, 200),
                'completion_tokens' => rand(150, 250),
                'total_tokens' => rand(250, 450)
            ),
            'timestamp' => current_time('mysql')
        );
    }
    
    /**
     * Generate outcome prediction
     *
     * @param array $content Content
     * @param array $params Parameters
     * @return array Prediction
     */
    private function generate_outcome_prediction($content, $params) {
        $prediction = array(
            'confidence' => 0.75,
            'prediction' => 'positive_outcome',
            'timeframe' => '7_days',
            'factors' => array(
                'Historical success with similar approaches',
                'Current motivation level appears high',
                'Support systems in place'
            ),
            'recommendations' => array(
                'Continue current approach with minor adjustments',
                'Monitor progress daily for best results'
            )
        );
        
        // Vary confidence based on data
        if (isset($content['historical_data']) && count($content['historical_data']) > 20) {
            $prediction['confidence'] = 0.85;
        }
        
        return array(
            'type' => 'outcome_prediction',
            'data' => $prediction,
            'format' => 'structured',
            'model' => $this->config['model'],
            'usage' => array(
                'prompt_tokens' => rand(300, 400),
                'completion_tokens' => rand(200, 300),
                'total_tokens' => rand(500, 700)
            ),
            'timestamp' => current_time('mysql')
        );
    }
    
    /**
     * Generate predictive analysis
     *
     * @param array $content Content
     * @param array $params Parameters
     * @return array Predictive analysis
     */
    private function generate_predictive_analysis($content, $params) {
        $predictions = array(
            'predictions' => array(
                'escalation' => array(
                    'confidence' => 0.72,
                    'timeframe' => '48_hours',
                    'details' => array(
                        'expected_severity_increase' => 2,
                        'risk_factors' => array('Recent pattern changes', 'Upcoming stressors')
                    ),
                    'reasoning' => 'Based on historical patterns and current trajectory'
                ),
                'pattern_recurrence' => array(
                    'confidence' => 0.68,
                    'timeframe' => '7_days',
                    'details' => array(
                        'expected_pattern' => 'Weekly cycle',
                        'typical_triggers' => array('Monday stress', 'Weekend transitions')
                    ),
                    'reasoning' => 'Cyclical patterns detected in past 30 days'
                )
            ),
            'risk_assessment' => array(
                array(
                    'type' => 'severity_spike',
                    'level' => 'moderate',
                    'likelihood' => 0.65,
                    'timeframe' => '3_days'
                ),
                array(
                    'type' => 'coping_fatigue',
                    'level' => 'low',
                    'likelihood' => 0.45,
                    'timeframe' => '1_week'
                )
            ),
            'preventive_measures' => array(
                'Increase self-care activities in next 48 hours',
                'Prepare coping strategies for identified risk periods',
                'Consider scheduling support check-ins'
            )
        );
        
        return array(
            'type' => 'predictive_analysis',
            'data' => $predictions,
            'format' => 'structured',
            'model' => $this->config['model'],
            'usage' => array(
                'prompt_tokens' => rand(600, 800),
                'completion_tokens' => rand(700, 900),
                'total_tokens' => rand(1300, 1700)
            ),
            'timestamp' => current_time('mysql')
        );
    }
    
    /**
     * Generate generic response
     *
     * @param array $content Content
     * @param array $params Parameters
     * @return array Response
     */
    private function generate_generic_response($content, $params) {
        return array(
            'type' => $params['type'] ?? 'generic',
            'data' => array(
                'message' => 'Analysis completed successfully',
                'summary' => 'Mock AI processed your request',
                'details' => array(
                    'content_received' => !empty($content),
                    'params_received' => !empty($params)
                )
            ),
            'format' => 'structured',
            'model' => $this->config['model'],
            'usage' => array(
                'prompt_tokens' => rand(100, 300),
                'completion_tokens' => rand(100, 300),
                'total_tokens' => rand(200, 600)
            ),
            'timestamp' => current_time('mysql')
        );
    }
    
    /**
     * Randomize template responses
     *
     * @param array $template Template to randomize
     * @return array Randomized template
     */
    private function randomize_template($template) {
        // Randomly shuffle arrays
        foreach ($template as $key => &$value) {
            if (is_array($value) && !$this->is_associative_array($value)) {
                shuffle($value);
                
                // Randomly slice arrays to vary response length
                if (count($value) > 2 && rand(0, 1)) {
                    $value = array_slice($value, 0, rand(2, count($value)));
                }
            }
        }
        
        return $template;
    }
    
    /**
     * Check if array is associative
     *
     * @param array $array Array to check
     * @return bool Is associative
     */
    private function is_associative_array($array) {
        return count(array_filter(array_keys($array), 'is_string')) > 0;
    }
    
    /**
     * Generate random sentiment
     *
     * @return string Sentiment
     */
    private function generate_random_sentiment() {
        $sentiments = array('positive', 'negative', 'neutral', 'mixed');
        $weights = array(25, 25, 30, 20); // Weighted probability
        
        $rand = rand(1, 100);
        $cumulative = 0;
        
        for ($i = 0; $i < count($sentiments); $i++) {
            $cumulative += $weights[$i];
            if ($rand <= $cumulative) {
                return $sentiments[$i];
            }
        }
        
        return 'neutral';
    }
    
    /**
     * Get mock usage statistics
     *
     * @return array Usage stats
     */
    public function get_mock_usage_stats() {
        return array(
            'total_requests' => get_option('spiralengine_mock_requests', 0),
            'total_tokens' => get_option('spiralengine_mock_tokens', 0),
            'average_response_time' => $this->config['response_delay'],
            'error_rate' => $this->config['error_rate'] . '%'
        );
    }
}

// Register the mock provider
add_action('spiralengine_register_ai_providers', function($ai_service) {
    $ai_service->register_provider('mock', 'SpiralEngine_Mock_Provider');
});

// Auto-enable mock provider in development environments
add_filter('spiralengine_ai_default_provider', function($provider) {
    if (defined('WP_DEBUG') && WP_DEBUG) {
        return 'mock';
    }
    return $provider;
});


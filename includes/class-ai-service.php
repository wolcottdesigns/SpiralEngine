<?php
/**
 * SpiralEngine AI Service Base Class
 * 
 * @package    SpiralEngine
 * @subpackage AI
 * @file       includes/class-ai-service.php
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Abstract base class for AI providers
 * 
 * Provides a consistent interface for different AI providers (OpenAI, Anthropic, etc.)
 * and handles common functionality like caching, rate limiting, and error handling.
 */
abstract class SpiralEngine_AI_Provider {
    
    /**
     * Provider identifier
     *
     * @var string
     */
    protected $provider_id;
    
    /**
     * Provider display name
     *
     * @var string
     */
    protected $provider_name;
    
    /**
     * API configuration
     *
     * @var array
     */
    protected $config = array();
    
    /**
     * Available models for this provider
     *
     * @var array
     */
    protected $models = array();
    
    /**
     * Rate limiting settings
     *
     * @var array
     */
    protected $rate_limits = array(
        'requests_per_minute' => 60,
        'tokens_per_minute' => 90000,
        'requests_per_day' => 1000
    );
    
    /**
     * Constructor
     *
     * @param array $config Provider configuration
     */
    public function __construct($config = array()) {
        $this->config = wp_parse_args($config, $this->get_default_config());
        $this->initialize();
    }
    
    /**
     * Initialize the provider
     * 
     * @return void
     */
    abstract protected function initialize();
    
    /**
     * Get default configuration
     *
     * @return array
     */
    abstract protected function get_default_config();
    
    /**
     * Analyze content using AI
     *
     * @param array $content Content to analyze
     * @param array $params Analysis parameters
     * @return array Analysis results
     */
    abstract public function analyze(array $content, array $params = array());
    
    /**
     * Get available models
     *
     * @return array
     */
    abstract public function get_models();
    
    /**
     * Estimate cost for analysis
     *
     * @param array $params Analysis parameters
     * @return float Estimated cost in USD
     */
    abstract public function estimate_cost(array $params);
    
    /**
     * Check if provider is available
     *
     * @return bool
     */
    abstract public function check_availability();
    
    /**
     * Get provider ID
     *
     * @return string
     */
    public function get_provider_id() {
        return $this->provider_id;
    }
    
    /**
     * Get provider name
     *
     * @return string
     */
    public function get_provider_name() {
        return $this->provider_name;
    }
    
    /**
     * Check rate limit
     *
     * @param string $limit_type Type of limit to check
     * @return bool Whether request is allowed
     */
    protected function check_rate_limit($limit_type = 'requests_per_minute') {
        $cache_key = 'spiralengine_ai_rate_' . $this->provider_id . '_' . $limit_type;
        $current_count = get_transient($cache_key);
        
        if ($current_count === false) {
            $current_count = 0;
        }
        
        $limit = isset($this->rate_limits[$limit_type]) ? $this->rate_limits[$limit_type] : PHP_INT_MAX;
        
        if ($current_count >= $limit) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Increment rate limit counter
     *
     * @param string $limit_type Type of limit to increment
     * @param int $count Amount to increment
     * @return void
     */
    protected function increment_rate_limit($limit_type = 'requests_per_minute', $count = 1) {
        $cache_key = 'spiralengine_ai_rate_' . $this->provider_id . '_' . $limit_type;
        $current_count = get_transient($cache_key);
        
        if ($current_count === false) {
            $current_count = 0;
        }
        
        $current_count += $count;
        
        // Set expiration based on limit type
        $expiration = 60; // Default to 1 minute
        if (strpos($limit_type, '_per_day') !== false) {
            $expiration = 86400; // 24 hours
        } elseif (strpos($limit_type, '_per_hour') !== false) {
            $expiration = 3600; // 1 hour
        }
        
        set_transient($cache_key, $current_count, $expiration);
    }
    
    /**
     * Get cached result
     *
     * @param string $cache_key Cache key
     * @return mixed Cached result or false
     */
    protected function get_cached_result($cache_key) {
        return get_transient('spiralengine_ai_cache_' . $cache_key);
    }
    
    /**
     * Set cached result
     *
     * @param string $cache_key Cache key
     * @param mixed $result Result to cache
     * @param int $expiration Cache expiration in seconds
     * @return bool
     */
    protected function set_cached_result($cache_key, $result, $expiration = 3600) {
        return set_transient('spiralengine_ai_cache_' . $cache_key, $result, $expiration);
    }
    
    /**
     * Log AI usage
     *
     * @param array $params Usage parameters
     * @return void
     */
    protected function log_usage($params) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'spiralengine_ai_usage';
        
        // Create table if not exists
        $this->ensure_usage_table_exists();
        
        $wpdb->insert(
            $table_name,
            array(
                'provider' => $this->provider_id,
                'user_id' => get_current_user_id(),
                'model' => isset($params['model']) ? $params['model'] : '',
                'tokens_used' => isset($params['tokens']) ? $params['tokens'] : 0,
                'cost' => isset($params['cost']) ? $params['cost'] : 0,
                'request_type' => isset($params['type']) ? $params['type'] : 'analysis',
                'metadata' => json_encode(isset($params['metadata']) ? $params['metadata'] : array()),
                'created_at' => current_time('mysql')
            ),
            array('%s', '%d', '%s', '%d', '%f', '%s', '%s', '%s')
        );
    }
    
    /**
     * Ensure AI usage table exists
     *
     * @return void
     */
    protected function ensure_usage_table_exists() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'spiralengine_ai_usage';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            provider varchar(50) NOT NULL,
            user_id bigint(20) UNSIGNED NOT NULL,
            model varchar(100),
            tokens_used int(11) DEFAULT 0,
            cost decimal(10,6) DEFAULT 0,
            request_type varchar(50),
            metadata JSON,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_provider_user (provider, user_id),
            INDEX idx_created (created_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
}

/**
 * Main AI Service class
 * 
 * Manages AI providers and provides a unified interface for AI operations
 */
class SpiralEngine_AI_Service {
    
    /**
     * Singleton instance
     *
     * @var SpiralEngine_AI_Service
     */
    private static $instance = null;
    
    /**
     * Registered AI providers
     *
     * @var array
     */
    private $providers = array();
    
    /**
     * Active provider
     *
     * @var SpiralEngine_AI_Provider
     */
    private $active_provider = null;
    
    /**
     * AI configuration
     *
     * @var array
     */
    private $config = array();
    
    /**
     * Get singleton instance
     *
     * @return SpiralEngine_AI_Service
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->load_config();
        $this->load_providers();
        $this->set_active_provider();
    }
    
    /**
     * Load AI configuration
     *
     * @return void
     */
    private function load_config() {
        $this->config = array(
            'enabled' => get_option('spiralengine_ai_enabled', true),
            'default_provider' => get_option('spiralengine_ai_default_provider', 'openai'),
            'cache_results' => get_option('spiralengine_ai_cache_results', true),
            'cache_duration' => get_option('spiralengine_ai_cache_duration', 3600),
            'rate_limiting' => get_option('spiralengine_ai_rate_limiting', true),
            'log_usage' => get_option('spiralengine_ai_log_usage', true),
            'privacy_mode' => get_option('spiralengine_ai_privacy_mode', false)
        );
    }
    
    /**
     * Load available AI providers
     *
     * @return void
     */
    private function load_providers() {
        $provider_dir = SPIRALENGINE_PLUGIN_DIR . 'includes/ai-providers/';
        
        // Load built-in providers
        $provider_files = array(
            'openai' => 'class-openai-provider.php',
            'anthropic' => 'class-anthropic-provider.php',
            'mock' => 'class-mock-provider.php'
        );
        
        foreach ($provider_files as $provider_id => $file) {
            $file_path = $provider_dir . $file;
            if (file_exists($file_path)) {
                require_once $file_path;
            }
        }
        
        // Allow third-party providers to register
        do_action('spiralengine_register_ai_providers', $this);
    }
    
    /**
     * Register an AI provider
     *
     * @param string $provider_id Provider identifier
     * @param string $provider_class Provider class name
     * @return bool
     */
    public function register_provider($provider_id, $provider_class) {
        if (!class_exists($provider_class)) {
            return false;
        }
        
        if (!is_subclass_of($provider_class, 'SpiralEngine_AI_Provider')) {
            return false;
        }
        
        $this->providers[$provider_id] = $provider_class;
        return true;
    }
    
    /**
     * Set active provider
     *
     * @param string|null $provider_id Provider ID or null for default
     * @return bool
     */
    public function set_active_provider($provider_id = null) {
        if (null === $provider_id) {
            $provider_id = $this->config['default_provider'];
        }
        
        if (!isset($this->providers[$provider_id])) {
            return false;
        }
        
        $provider_class = $this->providers[$provider_id];
        $config = $this->get_provider_config($provider_id);
        
        $this->active_provider = new $provider_class($config);
        
        return true;
    }
    
    /**
     * Get provider configuration
     *
     * @param string $provider_id Provider ID
     * @return array
     */
    private function get_provider_config($provider_id) {
        $config = array();
        
        // Get provider-specific settings
        $config['api_key'] = get_option('spiralengine_ai_' . $provider_id . '_api_key', '');
        $config['model'] = get_option('spiralengine_ai_' . $provider_id . '_model', '');
        $config['temperature'] = get_option('spiralengine_ai_' . $provider_id . '_temperature', 0.7);
        $config['max_tokens'] = get_option('spiralengine_ai_' . $provider_id . '_max_tokens', 1000);
        
        // Apply filters for customization
        return apply_filters('spiralengine_ai_provider_config', $config, $provider_id);
    }
    
    /**
     * Analyze episode data
     *
     * @param array $episode_data Episode data
     * @param array $params Analysis parameters
     * @return array Analysis results
     */
    public function analyze_episode($episode_data, $params = array()) {
        if (!$this->config['enabled'] || !$this->active_provider) {
            return array('error' => __('AI service is not available', 'spiralengine'));
        }
        
        // Check user permissions
        if (!$this->user_can_use_ai()) {
            return array('error' => __('Your membership tier does not include AI features', 'spiralengine'));
        }
        
        // Prepare content for analysis
        $content = $this->prepare_episode_content($episode_data);
        
        // Add default analysis parameters
        $params = wp_parse_args($params, array(
            'type' => 'episode_analysis',
            'include_patterns' => true,
            'include_insights' => true,
            'include_recommendations' => true
        ));
        
        // Check cache if enabled
        if ($this->config['cache_results']) {
            $cache_key = $this->generate_cache_key($content, $params);
            $cached_result = $this->active_provider->get_cached_result($cache_key);
            
            if ($cached_result !== false) {
                return $cached_result;
            }
        }
        
        // Perform analysis
        try {
            $result = $this->active_provider->analyze($content, $params);
            
            // Cache result if enabled
            if ($this->config['cache_results'] && !isset($result['error'])) {
                $this->active_provider->set_cached_result(
                    $cache_key,
                    $result,
                    $this->config['cache_duration']
                );
            }
            
            return $result;
            
        } catch (Exception $e) {
            return array('error' => $e->getMessage());
        }
    }
    
    /**
     * Analyze patterns across multiple episodes
     *
     * @param array $episodes Array of episodes
     * @param array $params Analysis parameters
     * @return array Pattern analysis results
     */
    public function analyze_patterns($episodes, $params = array()) {
        if (!$this->config['enabled'] || !$this->active_provider) {
            return array('error' => __('AI service is not available', 'spiralengine'));
        }
        
        // Check user permissions
        if (!$this->user_can_use_ai('pattern_analysis')) {
            return array('error' => __('Pattern analysis requires Gold tier or higher', 'spiralengine'));
        }
        
        // Prepare content for pattern analysis
        $content = $this->prepare_pattern_content($episodes);
        
        // Add default parameters
        $params = wp_parse_args($params, array(
            'type' => 'pattern_analysis',
            'timeframe' => '30_days',
            'min_occurrences' => 3,
            'include_correlations' => true,
            'include_predictions' => true
        ));
        
        // Perform analysis
        try {
            return $this->active_provider->analyze($content, $params);
        } catch (Exception $e) {
            return array('error' => $e->getMessage());
        }
    }
    
    /**
     * Generate insights for user
     *
     * @param int $user_id User ID
     * @param array $params Insight parameters
     * @return array Generated insights
     */
    public function generate_insights($user_id, $params = array()) {
        if (!$this->config['enabled'] || !$this->active_provider) {
            return array('error' => __('AI service is not available', 'spiralengine'));
        }
        
        // Check user permissions
        if (!$this->user_can_use_ai('insights', $user_id)) {
            return array('error' => __('AI insights require Silver tier or higher', 'spiralengine'));
        }
        
        // Get user's recent episodes
        $episodes = $this->get_user_episodes_for_analysis($user_id, $params);
        
        if (empty($episodes)) {
            return array('error' => __('Not enough data for insight generation', 'spiralengine'));
        }
        
        // Prepare content
        $content = array(
            'user_profile' => $this->get_user_profile($user_id),
            'episodes' => $episodes,
            'stats' => $this->calculate_user_stats($user_id)
        );
        
        // Add parameters
        $params = wp_parse_args($params, array(
            'type' => 'insight_generation',
            'focus_areas' => array('triggers', 'patterns', 'progress', 'recommendations'),
            'insight_depth' => 'comprehensive'
        ));
        
        // Generate insights
        try {
            return $this->active_provider->analyze($content, $params);
        } catch (Exception $e) {
            return array('error' => $e->getMessage());
        }
    }
    
    /**
     * Get personalized recommendations
     *
     * @param int $user_id User ID
     * @param string $context Context for recommendations
     * @return array Recommendations
     */
    public function get_recommendations($user_id, $context = 'general') {
        if (!$this->config['enabled'] || !$this->active_provider) {
            return array('error' => __('AI service is not available', 'spiralengine'));
        }
        
        // Check user permissions
        if (!$this->user_can_use_ai('recommendations', $user_id)) {
            return array('error' => __('AI recommendations require Gold tier or higher', 'spiralengine'));
        }
        
        // Prepare content based on context
        $content = $this->prepare_recommendation_content($user_id, $context);
        
        $params = array(
            'type' => 'recommendations',
            'context' => $context,
            'max_recommendations' => 5,
            'include_rationale' => true
        );
        
        // Get recommendations
        try {
            return $this->active_provider->analyze($content, $params);
        } catch (Exception $e) {
            return array('error' => $e->getMessage());
        }
    }
    
    /**
     * Check if user can use AI features
     *
     * @param string $feature Specific feature to check
     * @param int|null $user_id User ID or current user
     * @return bool
     */
    private function user_can_use_ai($feature = 'basic', $user_id = null) {
        if (null === $user_id) {
            $user_id = get_current_user_id();
        }
        
        $membership = new SpiralEngine_Membership($user_id);
        $tier = $membership->get_tier();
        
        // Define AI feature tiers
        $feature_tiers = array(
            'basic' => array('silver', 'gold', 'platinum'),
            'pattern_analysis' => array('gold', 'platinum'),
            'insights' => array('silver', 'gold', 'platinum'),
            'recommendations' => array('gold', 'platinum'),
            'predictive' => array('platinum')
        );
        
        // Check if feature is available for user's tier
        if (isset($feature_tiers[$feature])) {
            return in_array($tier, $feature_tiers[$feature]);
        }
        
        // Default to requiring at least Silver
        return in_array($tier, array('silver', 'gold', 'platinum'));
    }
    
    /**
     * Prepare episode content for AI analysis
     *
     * @param array $episode_data Episode data
     * @return array Prepared content
     */
    private function prepare_episode_content($episode_data) {
        // Remove sensitive information if privacy mode is enabled
        if ($this->config['privacy_mode']) {
            $episode_data = $this->anonymize_data($episode_data);
        }
        
        return array(
            'widget_type' => isset($episode_data['widget_id']) ? $episode_data['widget_id'] : '',
            'severity' => isset($episode_data['severity']) ? $episode_data['severity'] : 0,
            'data' => isset($episode_data['data']) ? $episode_data['data'] : array(),
            'timestamp' => isset($episode_data['created_at']) ? $episode_data['created_at'] : current_time('mysql'),
            'context' => $this->get_episode_context($episode_data)
        );
    }
    
    /**
     * Prepare pattern content for analysis
     *
     * @param array $episodes Episodes to analyze
     * @return array Prepared content
     */
    private function prepare_pattern_content($episodes) {
        $content = array(
            'episode_count' => count($episodes),
            'timespan' => $this->calculate_timespan($episodes),
            'episodes' => array()
        );
        
        foreach ($episodes as $episode) {
            $content['episodes'][] = $this->prepare_episode_content($episode);
        }
        
        return $content;
    }
    
    /**
     * Get user episodes for analysis
     *
     * @param int $user_id User ID
     * @param array $params Parameters
     * @return array Episodes
     */
    private function get_user_episodes_for_analysis($user_id, $params) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'spiralengine_episodes';
        $days = isset($params['days']) ? intval($params['days']) : 30;
        
        $query = $wpdb->prepare(
            "SELECT * FROM $table_name 
            WHERE user_id = %d 
            AND created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)
            ORDER BY created_at DESC
            LIMIT 100",
            $user_id,
            $days
        );
        
        return $wpdb->get_results($query, ARRAY_A);
    }
    
    /**
     * Get user profile for AI context
     *
     * @param int $user_id User ID
     * @return array User profile
     */
    private function get_user_profile($user_id) {
        $user = get_user_by('id', $user_id);
        
        return array(
            'member_since' => $user->user_registered,
            'tier' => get_user_meta($user_id, 'spiralengine_tier', true),
            'preferences' => get_user_meta($user_id, 'spiralengine_preferences', true),
            'goals' => $this->get_user_goals($user_id)
        );
    }
    
    /**
     * Calculate user statistics
     *
     * @param int $user_id User ID
     * @return array Statistics
     */
    private function calculate_user_stats($user_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'spiralengine_episodes';
        
        // Get basic stats
        $stats = array(
            'total_episodes' => $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $table_name WHERE user_id = %d",
                $user_id
            )),
            'average_severity' => $wpdb->get_var($wpdb->prepare(
                "SELECT AVG(severity) FROM $table_name WHERE user_id = %d",
                $user_id
            )),
            'most_used_widget' => $wpdb->get_var($wpdb->prepare(
                "SELECT widget_id FROM $table_name 
                WHERE user_id = %d 
                GROUP BY widget_id 
                ORDER BY COUNT(*) DESC 
                LIMIT 1",
                $user_id
            ))
        );
        
        return $stats;
    }
    
    /**
     * Generate cache key
     *
     * @param array $content Content
     * @param array $params Parameters
     * @return string Cache key
     */
    private function generate_cache_key($content, $params) {
        return md5(serialize($content) . serialize($params));
    }
    
    /**
     * Anonymize data for privacy
     *
     * @param array $data Data to anonymize
     * @return array Anonymized data
     */
    private function anonymize_data($data) {
        // Remove or hash personally identifiable information
        $fields_to_remove = array('name', 'email', 'phone', 'address');
        
        foreach ($fields_to_remove as $field) {
            if (isset($data[$field])) {
                unset($data[$field]);
            }
        }
        
        return $data;
    }
    
    /**
     * Get episode context
     *
     * @param array $episode Episode data
     * @return array Context information
     */
    private function get_episode_context($episode) {
        return array(
            'time_of_day' => date('H:i', strtotime($episode['created_at'])),
            'day_of_week' => date('l', strtotime($episode['created_at'])),
            'recent_episodes' => $this->get_recent_episode_count($episode['user_id'])
        );
    }
    
    /**
     * Get recent episode count
     *
     * @param int $user_id User ID
     * @return int Count
     */
    private function get_recent_episode_count($user_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'spiralengine_episodes';
        
        return $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name 
            WHERE user_id = %d 
            AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)",
            $user_id
        ));
    }
    
    /**
     * Calculate timespan of episodes
     *
     * @param array $episodes Episodes
     * @return array Timespan info
     */
    private function calculate_timespan($episodes) {
        if (empty($episodes)) {
            return array('days' => 0);
        }
        
        $dates = array_column($episodes, 'created_at');
        $earliest = min($dates);
        $latest = max($dates);
        
        $diff = strtotime($latest) - strtotime($earliest);
        
        return array(
            'days' => floor($diff / 86400),
            'start' => $earliest,
            'end' => $latest
        );
    }
    
    /**
     * Get user goals
     *
     * @param int $user_id User ID
     * @return array Active goals
     */
    private function get_user_goals($user_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'spiralengine_episodes';
        
        $goals = $wpdb->get_results($wpdb->prepare(
            "SELECT data FROM $table_name 
            WHERE user_id = %d 
            AND widget_id = 'goal-setting'
            AND JSON_EXTRACT(data, '$.status') = 'active'
            ORDER BY created_at DESC
            LIMIT 10",
            $user_id
        ), ARRAY_A);
        
        return array_map(function($goal) {
            return json_decode($goal['data'], true);
        }, $goals);
    }
    
    /**
     * Prepare recommendation content
     *
     * @param int $user_id User ID
     * @param string $context Context
     * @return array Content for recommendations
     */
    private function prepare_recommendation_content($user_id, $context) {
        $content = array(
            'user_profile' => $this->get_user_profile($user_id),
            'recent_episodes' => $this->get_user_episodes_for_analysis($user_id, array('days' => 7)),
            'stats' => $this->calculate_user_stats($user_id),
            'context' => $context
        );
        
        // Add context-specific data
        switch ($context) {
            case 'coping_skills':
                $content['used_skills'] = $this->get_used_coping_skills($user_id);
                $content['skill_effectiveness'] = $this->calculate_skill_effectiveness($user_id);
                break;
                
            case 'goals':
                $content['current_goals'] = $this->get_user_goals($user_id);
                $content['goal_progress'] = $this->calculate_goal_progress($user_id);
                break;
                
            case 'triggers':
                $content['common_triggers'] = $this->get_common_triggers($user_id);
                $content['trigger_patterns'] = $this->analyze_trigger_patterns($user_id);
                break;
        }
        
        return $content;
    }
    
    /**
     * Get used coping skills
     *
     * @param int $user_id User ID
     * @return array Used skills
     */
    private function get_used_coping_skills($user_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'spiralengine_episodes';
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT data FROM $table_name 
            WHERE user_id = %d 
            AND widget_id = 'coping-logger'
            ORDER BY created_at DESC
            LIMIT 50",
            $user_id
        ), ARRAY_A);
    }
    
    /**
     * Calculate skill effectiveness
     *
     * @param int $user_id User ID
     * @return array Effectiveness scores
     */
    private function calculate_skill_effectiveness($user_id) {
        // Implementation would analyze coping skill usage and outcomes
        return array();
    }
    
    /**
     * Calculate goal progress
     *
     * @param int $user_id User ID
     * @return array Progress data
     */
    private function calculate_goal_progress($user_id) {
        // Implementation would track goal milestones and completion
        return array();
    }
    
    /**
     * Get common triggers
     *
     * @param int $user_id User ID
     * @return array Common triggers
     */
    private function get_common_triggers($user_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'spiralengine_episodes';
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT data FROM $table_name 
            WHERE user_id = %d 
            AND widget_id = 'trigger-tracker'
            ORDER BY created_at DESC
            LIMIT 50",
            $user_id
        ), ARRAY_A);
    }
    
    /**
     * Analyze trigger patterns
     *
     * @param int $user_id User ID
     * @return array Trigger patterns
     */
    private function analyze_trigger_patterns($user_id) {
        // Implementation would identify recurring trigger patterns
        return array();
    }
    
    /**
     * Get AI usage statistics
     *
     * @param array $filters Filters for statistics
     * @return array Usage statistics
     */
    public function get_usage_stats($filters = array()) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'spiralengine_ai_usage';
        
        $where_clauses = array('1=1');
        $where_values = array();
        
        if (!empty($filters['provider'])) {
            $where_clauses[] = 'provider = %s';
            $where_values[] = $filters['provider'];
        }
        
        if (!empty($filters['user_id'])) {
            $where_clauses[] = 'user_id = %d';
            $where_values[] = $filters['user_id'];
        }
        
        if (!empty($filters['date_from'])) {
            $where_clauses[] = 'created_at >= %s';
            $where_values[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $where_clauses[] = 'created_at <= %s';
            $where_values[] = $filters['date_to'];
        }
        
        $where_sql = implode(' AND ', $where_clauses);
        
        $query = "SELECT 
            COUNT(*) as total_requests,
            SUM(tokens_used) as total_tokens,
            SUM(cost) as total_cost,
            AVG(tokens_used) as avg_tokens_per_request,
            AVG(cost) as avg_cost_per_request,
            provider,
            request_type
            FROM $table_name
            WHERE $where_sql
            GROUP BY provider, request_type";
        
        if (!empty($where_values)) {
            $query = $wpdb->prepare($query, $where_values);
        }
        
        return $wpdb->get_results($query, ARRAY_A);
    }
    
    /**
     * Get available providers
     *
     * @return array Provider information
     */
    public function get_available_providers() {
        $available = array();
        
        foreach ($this->providers as $provider_id => $provider_class) {
            $provider = new $provider_class($this->get_provider_config($provider_id));
            
            if ($provider->check_availability()) {
                $available[$provider_id] = array(
                    'id' => $provider_id,
                    'name' => $provider->get_provider_name(),
                    'models' => $provider->get_models(),
                    'active' => ($this->active_provider && $this->active_provider->get_provider_id() === $provider_id)
                );
            }
        }
        
        return $available;
    }
    
    /**
     * Estimate cost for operation
     *
     * @param string $operation Operation type
     * @param array $params Operation parameters
     * @return array Cost estimate
     */
    public function estimate_cost($operation, $params = array()) {
        if (!$this->active_provider) {
            return array('error' => __('No active AI provider', 'spiralengine'));
        }
        
        // Estimate based on operation type
        $token_estimates = array(
            'episode_analysis' => 500,
            'pattern_analysis' => 2000,
            'insight_generation' => 1500,
            'recommendations' => 1000
        );
        
        $estimated_tokens = isset($token_estimates[$operation]) ? $token_estimates[$operation] : 1000;
        
        $params['estimated_tokens'] = $estimated_tokens;
        $params['operation'] = $operation;
        
        return array(
            'provider' => $this->active_provider->get_provider_id(),
            'estimated_cost' => $this->active_provider->estimate_cost($params),
            'estimated_tokens' => $estimated_tokens,
            'currency' => 'USD'
        );
    }
}


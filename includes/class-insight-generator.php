<?php
/**
 * SpiralEngine Insight Generator
 * 
 * @package    SpiralEngine
 * @subpackage AI
 * @file       includes/class-insight-generator.php
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Insight Generator class
 * 
 * Generates AI-powered insights and recommendations for users
 */
class SpiralEngine_Insight_Generator {
    
    /**
     * AI Service instance
     *
     * @var SpiralEngine_AI_Service
     */
    private $ai_service;
    
    /**
     * Pattern Analyzer instance
     *
     * @var SpiralEngine_Pattern_Analyzer
     */
    private $pattern_analyzer;
    
    /**
     * Insight types available
     *
     * @var array
     */
    private $insight_types = array(
        'daily' => array(
            'name' => 'Daily Insights',
            'frequency' => 'daily',
            'min_episodes' => 1,
            'tier_required' => 'silver'
        ),
        'weekly' => array(
            'name' => 'Weekly Summary',
            'frequency' => 'weekly',
            'min_episodes' => 5,
            'tier_required' => 'silver'
        ),
        'monthly' => array(
            'name' => 'Monthly Analysis',
            'frequency' => 'monthly',
            'min_episodes' => 20,
            'tier_required' => 'gold'
        ),
        'milestone' => array(
            'name' => 'Milestone Review',
            'frequency' => 'on_demand',
            'min_episodes' => 50,
            'tier_required' => 'gold'
        ),
        'predictive' => array(
            'name' => 'Predictive Insights',
            'frequency' => 'weekly',
            'min_episodes' => 30,
            'tier_required' => 'platinum'
        )
    );
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->ai_service = SpiralEngine_AI_Service::get_instance();
        $this->pattern_analyzer = new SpiralEngine_Pattern_Analyzer();
    }
    
    /**
     * Generate insights for a user
     *
     * @param int $user_id User ID
     * @param string $type Insight type
     * @param array $options Generation options
     * @return array Generated insights
     */
    public function generate_insights($user_id, $type = 'daily', $options = array()) {
        // Validate insight type
        if (!isset($this->insight_types[$type])) {
            return array('error' => __('Invalid insight type', 'spiralengine'));
        }
        
        $insight_config = $this->insight_types[$type];
        
        // Check user permissions
        $membership = new SpiralEngine_Membership($user_id);
        if (!$this->user_can_access_insight_type($membership->get_tier(), $insight_config['tier_required'])) {
            return array(
                'error' => sprintf(
                    __('%s insights require %s tier or higher', 'spiralengine'),
                    $insight_config['name'],
                    ucfirst($insight_config['tier_required'])
                )
            );
        }
        
        // Check for cached insights
        $cache_key = $this->get_cache_key($user_id, $type, $options);
        $cached = get_transient($cache_key);
        
        if ($cached !== false && empty($options['force_refresh'])) {
            return $cached;
        }
        
        // Generate fresh insights
        $insights = $this->generate_fresh_insights($user_id, $type, $options);
        
        // Cache the results
        if (!isset($insights['error'])) {
            $cache_duration = $this->get_cache_duration($type);
            set_transient($cache_key, $insights, $cache_duration);
        }
        
        return $insights;
    }
    
    /**
     * Generate fresh insights
     *
     * @param int $user_id User ID
     * @param string $type Insight type
     * @param array $options Options
     * @return array Fresh insights
     */
    private function generate_fresh_insights($user_id, $type, $options) {
        // Get relevant data based on insight type
        $data = $this->gather_insight_data($user_id, $type, $options);
        
        if (isset($data['error'])) {
            return $data;
        }
        
        // Check minimum episodes requirement
        if ($data['episode_count'] < $this->insight_types[$type]['min_episodes']) {
            return array(
                'error' => sprintf(
                    __('Need at least %d episodes for %s', 'spiralengine'),
                    $this->insight_types[$type]['min_episodes'],
                    $this->insight_types[$type]['name']
                )
            );
        }
        
        // Generate insights based on type
        switch ($type) {
            case 'daily':
                return $this->generate_daily_insights($user_id, $data, $options);
                
            case 'weekly':
                return $this->generate_weekly_insights($user_id, $data, $options);
                
            case 'monthly':
                return $this->generate_monthly_insights($user_id, $data, $options);
                
            case 'milestone':
                return $this->generate_milestone_insights($user_id, $data, $options);
                
            case 'predictive':
                return $this->generate_predictive_insights($user_id, $data, $options);
                
            default:
                return array('error' => __('Insight type not implemented', 'spiralengine'));
        }
    }
    
    /**
     * Generate daily insights
     *
     * @param int $user_id User ID
     * @param array $data User data
     * @param array $options Options
     * @return array Daily insights
     */
    private function generate_daily_insights($user_id, $data, $options) {
        $insights = array(
            'type' => 'daily',
            'date' => current_time('Y-m-d'),
            'user_id' => $user_id,
            'data' => array()
        );
        
        // Today's summary
        $insights['data']['summary'] = $this->generate_daily_summary($data);
        
        // Mood and severity analysis
        $insights['data']['mood_analysis'] = $this->analyze_daily_mood($data);
        
        // Notable events
        $insights['data']['notable_events'] = $this->identify_notable_events($data);
        
        // Coping effectiveness
        if ($this->has_coping_data($data)) {
            $insights['data']['coping_effectiveness'] = $this->analyze_coping_effectiveness($data);
        }
        
        // AI-generated insights
        if ($this->should_use_ai($options)) {
            $ai_insights = $this->ai_service->generate_insights($user_id, array(
                'type' => 'daily',
                'data' => $data,
                'focus_areas' => array('mood', 'triggers', 'progress')
            ));
            
            if (!isset($ai_insights['error'])) {
                $insights['data']['ai_insights'] = $ai_insights;
            }
        }
        
        // Recommendations for tomorrow
        $insights['data']['recommendations'] = $this->generate_daily_recommendations($data);
        
        // Encouragement message
        $insights['data']['encouragement'] = $this->generate_encouragement($data);
        
        return $insights;
    }
    
    /**
     * Generate weekly insights
     *
     * @param int $user_id User ID
     * @param array $data User data
     * @param array $options Options
     * @return array Weekly insights
     */
    private function generate_weekly_insights($user_id, $data, $options) {
        $insights = array(
            'type' => 'weekly',
            'week' => date('Y-W'),
            'user_id' => $user_id,
            'data' => array()
        );
        
        // Week overview
        $insights['data']['overview'] = $this->generate_weekly_overview($data);
        
        // Pattern analysis
        $pattern_results = $this->pattern_analyzer->analyze_user_patterns($user_id, array(
            'days' => 7,
            'include_ai' => false // We'll use AI separately
        ));
        
        if (!isset($pattern_results['error'])) {
            $insights['data']['patterns'] = $this->summarize_patterns($pattern_results);
        }
        
        // Progress tracking
        $insights['data']['progress'] = $this->track_weekly_progress($data);
        
        // Trigger analysis
        $insights['data']['triggers'] = $this->analyze_weekly_triggers($data);
        
        // Sleep quality trends
        if ($this->has_sleep_data($data)) {
            $insights['data']['sleep_analysis'] = $this->analyze_sleep_patterns($data);
        }
        
        // AI-generated insights
        if ($this->should_use_ai($options)) {
            $ai_insights = $this->ai_service->generate_insights($user_id, array(
                'type' => 'weekly',
                'data' => $data,
                'patterns' => $pattern_results,
                'focus_areas' => array('patterns', 'progress', 'recommendations')
            ));
            
            if (!isset($ai_insights['error'])) {
                $insights['data']['ai_insights'] = $ai_insights;
            }
        }
        
        // Week ahead planning
        $insights['data']['week_ahead'] = $this->plan_week_ahead($data, $pattern_results);
        
        // Achievement recognition
        $insights['data']['achievements'] = $this->identify_weekly_achievements($data);
        
        return $insights;
    }
    
    /**
     * Generate monthly insights
     *
     * @param int $user_id User ID
     * @param array $data User data
     * @param array $options Options
     * @return array Monthly insights
     */
    private function generate_monthly_insights($user_id, $data, $options) {
        $insights = array(
            'type' => 'monthly',
            'month' => date('Y-m'),
            'user_id' => $user_id,
            'data' => array()
        );
        
        // Comprehensive pattern analysis
        $pattern_results = $this->pattern_analyzer->analyze_user_patterns($user_id, array(
            'days' => 30,
            'include_ai' => true
        ));
        
        if (!isset($pattern_results['error'])) {
            $insights['data']['comprehensive_patterns'] = $pattern_results;
        }
        
        // Month-over-month comparison
        $insights['data']['comparison'] = $this->compare_to_previous_month($user_id, $data);
        
        // Goal progress
        $insights['data']['goal_progress'] = $this->analyze_goal_progress($user_id, $data);
        
        // Medication effectiveness (if applicable)
        if ($this->has_medication_data($data)) {
            $insights['data']['medication_analysis'] = $this->analyze_medication_effectiveness($data);
        }
        
        // Detailed trigger analysis
        $insights['data']['trigger_deep_dive'] = $this->deep_dive_triggers($data);
        
        // Coping skill development
        $insights['data']['skill_development'] = $this->analyze_skill_development($data);
        
        // AI-powered comprehensive analysis
        if ($this->should_use_ai($options)) {
            $ai_insights = $this->ai_service->generate_insights($user_id, array(
                'type' => 'monthly',
                'data' => $data,
                'patterns' => $pattern_results,
                'focus_areas' => array('comprehensive', 'growth', 'recommendations', 'predictions')
            ));
            
            if (!isset($ai_insights['error'])) {
                $insights['data']['ai_comprehensive'] = $ai_insights;
            }
        }
        
        // Next month recommendations
        $insights['data']['next_month_plan'] = $this->create_monthly_plan($data, $pattern_results);
        
        // Monthly report card
        $insights['data']['report_card'] = $this->generate_monthly_report_card($data);
        
        return $insights;
    }
    
    /**
     * Generate milestone insights
     *
     * @param int $user_id User ID
     * @param array $data User data
     * @param array $options Options
     * @return array Milestone insights
     */
    private function generate_milestone_insights($user_id, $data, $options) {
        $milestone = isset($options['milestone']) ? $options['milestone'] : $this->detect_milestone($user_id);
        
        $insights = array(
            'type' => 'milestone',
            'milestone' => $milestone,
            'user_id' => $user_id,
            'data' => array()
        );
        
        // Journey overview
        $insights['data']['journey_overview'] = $this->create_journey_overview($user_id, $data);
        
        // Major achievements
        $insights['data']['achievements'] = $this->compile_major_achievements($user_id, $data);
        
        // Transformation analysis
        $insights['data']['transformation'] = $this->analyze_transformation($user_id, $data);
        
        // Skill mastery
        $insights['data']['skill_mastery'] = $this->evaluate_skill_mastery($data);
        
        // Resilience score
        $insights['data']['resilience'] = $this->calculate_resilience_score($data);
        
        // AI-powered reflection
        if ($this->should_use_ai($options)) {
            $ai_insights = $this->ai_service->generate_insights($user_id, array(
                'type' => 'milestone',
                'milestone' => $milestone,
                'data' => $data,
                'focus_areas' => array('journey', 'growth', 'strengths', 'future')
            ));
            
            if (!isset($ai_insights['error'])) {
                $insights['data']['ai_reflection'] = $ai_insights;
            }
        }
        
        // Future roadmap
        $insights['data']['future_roadmap'] = $this->create_future_roadmap($user_id, $data);
        
        // Celebration message
        $insights['data']['celebration'] = $this->generate_milestone_celebration($milestone, $data);
        
        return $insights;
    }
    
    /**
     * Generate predictive insights
     *
     * @param int $user_id User ID
     * @param array $data User data
     * @param array $options Options
     * @return array Predictive insights
     */
    private function generate_predictive_insights($user_id, $data, $options) {
        $insights = array(
            'type' => 'predictive',
            'generated_at' => current_time('mysql'),
            'user_id' => $user_id,
            'data' => array()
        );
        
        // Pattern-based predictions
        $pattern_results = $this->pattern_analyzer->analyze_user_patterns($user_id, array(
            'days' => 60,
            'include_ai' => true
        ));
        
        if (!isset($pattern_results['error'])) {
            $insights['data']['pattern_predictions'] = $this->generate_pattern_predictions($pattern_results);
        }
        
        // Risk assessment
        $insights['data']['risk_assessment'] = $this->assess_future_risks($data, $pattern_results);
        
        // Opportunity identification
        $insights['data']['opportunities'] = $this->identify_growth_opportunities($data);
        
        // Seasonal predictions
        $insights['data']['seasonal'] = $this->predict_seasonal_patterns($user_id, $data);
        
        // Goal achievement probability
        $insights['data']['goal_predictions'] = $this->predict_goal_achievement($user_id, $data);
        
        // AI-powered predictions
        $ai_predictions = $this->ai_service->get_recommendations($user_id, 'predictive');
        
        if (!isset($ai_predictions['error'])) {
            $insights['data']['ai_predictions'] = $ai_predictions;
        }
        
        // Early warning system
        $insights['data']['early_warnings'] = $this->generate_early_warnings($data, $pattern_results);
        
        // Preventive recommendations
        $insights['data']['preventive_measures'] = $this->suggest_preventive_measures($data, $insights['data']);
        
        // Confidence levels
        $insights['data']['confidence'] = $this->calculate_prediction_confidence($data);
        
        return $insights;
    }
    
    /**
     * Gather data for insight generation
     *
     * @param int $user_id User ID
     * @param string $type Insight type
     * @param array $options Options
     * @return array Gathered data
     */
    private function gather_insight_data($user_id, $type, $options) {
        global $wpdb;
        
        $days = $this->get_data_period($type, $options);
        $table_name = $wpdb->prefix . 'spiralengine_episodes';
        
        // Get episodes
        $episodes = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name 
            WHERE user_id = %d 
            AND created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)
            ORDER BY created_at DESC",
            $user_id,
            $days
        ), ARRAY_A);
        
        // Decode JSON fields
        foreach ($episodes as &$episode) {
            $episode['data'] = json_decode($episode['data'], true);
            $episode['metadata'] = json_decode($episode['metadata'], true);
        }
        
        // Get user profile
        $user_profile = $this->get_user_profile($user_id);
        
        // Get goals if applicable
        $goals = array();
        if (in_array($type, array('weekly', 'monthly', 'milestone'))) {
            $goals = $this->get_user_goals($user_id);
        }
        
        return array(
            'episodes' => $episodes,
            'episode_count' => count($episodes),
            'period_days' => $days,
            'user_profile' => $user_profile,
            'goals' => $goals,
            'stats' => $this->calculate_period_stats($episodes)
        );
    }
    
    /**
     * Get data period for insight type
     *
     * @param string $type Insight type
     * @param array $options Options
     * @return int Days
     */
    private function get_data_period($type, $options) {
        if (isset($options['days'])) {
            return intval($options['days']);
        }
        
        switch ($type) {
            case 'daily':
                return 1;
            case 'weekly':
                return 7;
            case 'monthly':
                return 30;
            case 'milestone':
                return 365; // Full year for milestone
            case 'predictive':
                return 60; // 2 months for predictions
            default:
                return 30;
        }
    }
    
    /**
     * Get user profile
     *
     * @param int $user_id User ID
     * @return array User profile
     */
    private function get_user_profile($user_id) {
        $user = get_user_by('id', $user_id);
        $membership = new SpiralEngine_Membership($user_id);
        
        return array(
            'user_id' => $user_id,
            'display_name' => $user->display_name,
            'member_since' => $user->user_registered,
            'tier' => $membership->get_tier(),
            'preferences' => get_user_meta($user_id, 'spiralengine_preferences', true),
            'timezone' => get_user_meta($user_id, 'spiralengine_timezone', true)
        );
    }
    
    /**
     * Get user goals
     *
     * @param int $user_id User ID
     * @return array User goals
     */
    private function get_user_goals($user_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'spiralengine_episodes';
        
        $goals = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name 
            WHERE user_id = %d 
            AND widget_id = 'goal-setting'
            AND JSON_EXTRACT(data, '$.status') IN ('active', 'paused')
            ORDER BY created_at DESC",
            $user_id
        ), ARRAY_A);
        
        foreach ($goals as &$goal) {
            $goal['data'] = json_decode($goal['data'], true);
        }
        
        return $goals;
    }
    
    /**
     * Calculate period statistics
     *
     * @param array $episodes Episodes
     * @return array Statistics
     */
    private function calculate_period_stats($episodes) {
        if (empty($episodes)) {
            return array();
        }
        
        $severities = array_column($episodes, 'severity');
        $by_widget = array();
        
        foreach ($episodes as $episode) {
            $widget = $episode['widget_id'];
            if (!isset($by_widget[$widget])) {
                $by_widget[$widget] = 0;
            }
            $by_widget[$widget]++;
        }
        
        return array(
            'total_episodes' => count($episodes),
            'avg_severity' => round(array_sum($severities) / count($severities), 2),
            'max_severity' => max($severities),
            'min_severity' => min($severities),
            'widget_usage' => $by_widget,
            'most_used_widget' => array_search(max($by_widget), $by_widget)
        );
    }
    
    /**
     * Generate daily summary
     *
     * @param array $data Data
     * @return array Summary
     */
    private function generate_daily_summary($data) {
        $today_episodes = array_filter($data['episodes'], function($ep) {
            return date('Y-m-d', strtotime($ep['created_at'])) === date('Y-m-d');
        });
        
        $summary = array(
            'episode_count' => count($today_episodes),
            'widgets_used' => array_unique(array_column($today_episodes, 'widget_id'))
        );
        
        if (!empty($today_episodes)) {
            $severities = array_column($today_episodes, 'severity');
            $summary['avg_severity'] = round(array_sum($severities) / count($severities), 2);
            $summary['severity_range'] = array(
                'min' => min($severities),
                'max' => max($severities)
            );
        }
        
        return $summary;
    }
    
    /**
     * Analyze daily mood
     *
     * @param array $data Data
     * @return array Mood analysis
     */
    private function analyze_daily_mood($data) {
        $mood_episodes = array_filter($data['episodes'], function($ep) {
            return $ep['widget_id'] === 'mood-tracker' && 
                   date('Y-m-d', strtotime($ep['created_at'])) === date('Y-m-d');
        });
        
        if (empty($mood_episodes)) {
            return array('message' => __('No mood data for today', 'spiralengine'));
        }
        
        $moods = array();
        foreach ($mood_episodes as $episode) {
            if (isset($episode['data']['mood'])) {
                $moods[] = $episode['data']['mood'];
            }
        }
        
        return array(
            'moods_logged' => $moods,
            'dominant_mood' => $this->identify_dominant_mood($moods),
            'mood_stability' => $this->calculate_mood_stability($moods)
        );
    }
    
    /**
     * Identify notable events
     *
     * @param array $data Data
     * @return array Notable events
     */
    private function identify_notable_events($data) {
        $notable = array();
        
        // High severity episodes
        $high_severity = array_filter($data['episodes'], function($ep) {
            return $ep['severity'] >= 8 && 
                   date('Y-m-d', strtotime($ep['created_at'])) === date('Y-m-d');
        });
        
        if (!empty($high_severity)) {
            $notable[] = array(
                'type' => 'high_severity',
                'count' => count($high_severity),
                'message' => sprintf(
                    __('%d high severity episode(s) today', 'spiralengine'),
                    count($high_severity)
                )
            );
        }
        
        // First time using a widget
        $widgets_today = array_unique(array_column(
            array_filter($data['episodes'], function($ep) {
                return date('Y-m-d', strtotime($ep['created_at'])) === date('Y-m-d');
            }),
            'widget_id'
        ));
        
        foreach ($widgets_today as $widget) {
            if ($this->is_first_time_widget($data['user_profile']['user_id'], $widget)) {
                $notable[] = array(
                    'type' => 'new_widget',
                    'widget' => $widget,
                    'message' => sprintf(__('First time using %s', 'spiralengine'), $widget)
                );
            }
        }
        
        return $notable;
    }
    
    /**
     * Check if user has coping data
     *
     * @param array $data Data
     * @return bool Has coping data
     */
    private function has_coping_data($data) {
        return !empty(array_filter($data['episodes'], function($ep) {
            return $ep['widget_id'] === 'coping-logger';
        }));
    }
    
    /**
     * Analyze coping effectiveness
     *
     * @param array $data Data
     * @return array Coping effectiveness
     */
    private function analyze_coping_effectiveness($data) {
        $coping_episodes = array_filter($data['episodes'], function($ep) {
            return $ep['widget_id'] === 'coping-logger' && 
                   date('Y-m-d', strtotime($ep['created_at'])) === date('Y-m-d');
        });
        
        if (empty($coping_episodes)) {
            return array('message' => __('No coping skills used today', 'spiralengine'));
        }
        
        $effectiveness_scores = array();
        $skills_used = array();
        
        foreach ($coping_episodes as $episode) {
            if (isset($episode['data']['effectiveness'])) {
                $effectiveness_scores[] = $episode['data']['effectiveness'];
            }
            if (isset($episode['data']['skill_category'])) {
                $skills_used[] = $episode['data']['skill_category'];
            }
        }
        
        return array(
            'skills_used' => array_unique($skills_used),
            'avg_effectiveness' => !empty($effectiveness_scores) ? 
                round(array_sum($effectiveness_scores) / count($effectiveness_scores), 2) : 0,
            'most_effective' => $this->find_most_effective_skill($coping_episodes),
            'recommendation' => $this->recommend_coping_skill($data)
        );
    }
    
    /**
     * Should use AI for insights
     *
     * @param array $options Options
     * @return bool Use AI
     */
    private function should_use_ai($options) {
        return !isset($options['use_ai']) || $options['use_ai'] !== false;
    }
    
    /**
     * Generate daily recommendations
     *
     * @param array $data Data
     * @return array Recommendations
     */
    private function generate_daily_recommendations($data) {
        $recommendations = array();
        
        // Based on today's severity
        if (!empty($data['stats']) && $data['stats']['avg_severity'] > 6) {
            $recommendations[] = array(
                'type' => 'self_care',
                'priority' => 'high',
                'message' => __('Consider taking extra time for self-care activities today', 'spiralengine')
            );
        }
        
        // Based on time patterns
        $current_hour = date('G');
        if ($current_hour >= 20 && $this->has_late_night_pattern($data)) {
            $recommendations[] = array(
                'type' => 'sleep',
                'priority' => 'medium',
                'message' => __('Try to establish a calming bedtime routine tonight', 'spiralengine')
            );
        }
        
        // Coping skill recommendation
        if (!$this->has_coping_data($data)) {
            $recommendations[] = array(
                'type' => 'coping',
                'priority' => 'medium',
                'message' => __('Try logging a coping skill today to track what helps', 'spiralengine')
            );
        }
        
        return $recommendations;
    }
    
    /**
     * Generate encouragement message
     *
     * @param array $data Data
     * @return string Encouragement
     */
    private function generate_encouragement($data) {
        $messages = array(
            'neutral' => array(
                __('You\'re doing great by tracking your mental health.', 'spiralengine'),
                __('Every small step counts on your journey.', 'spiralengine'),
                __('Remember to be kind to yourself today.', 'spiralengine')
            ),
            'difficult' => array(
                __('Tough days don\'t last forever. You\'ve got this.', 'spiralengine'),
                __('It\'s okay to have hard days. Tomorrow is a fresh start.', 'spiralengine'),
                __('You\'re stronger than you know. Keep going.', 'spiralengine')
            ),
            'positive' => array(
                __('Great job today! Keep up the positive momentum.', 'spiralengine'),
                __('You\'re making excellent progress. Celebrate the small wins!', 'spiralengine'),
                __('Your dedication to self-care is inspiring!', 'spiralengine')
            )
        );
        
        // Determine message type based on severity
        $avg_severity = isset($data['stats']['avg_severity']) ? $data['stats']['avg_severity'] : 5;
        
        if ($avg_severity >= 7) {
            $type = 'difficult';
        } elseif ($avg_severity <= 4) {
            $type = 'positive';
        } else {
            $type = 'neutral';
        }
        
        return $messages[$type][array_rand($messages[$type])];
    }
    
    /**
     * Check if user can access insight type
     *
     * @param string $user_tier User's tier
     * @param string $required_tier Required tier
     * @return bool Can access
     */
    private function user_can_access_insight_type($user_tier, $required_tier) {
        $tier_hierarchy = array(
            'free' => 0,
            'bronze' => 1,
            'silver' => 2,
            'gold' => 3,
            'platinum' => 4
        );
        
        $user_level = isset($tier_hierarchy[$user_tier]) ? $tier_hierarchy[$user_tier] : 0;
        $required_level = isset($tier_hierarchy[$required_tier]) ? $tier_hierarchy[$required_tier] : 0;
        
        return $user_level >= $required_level;
    }
    
    /**
     * Get cache key for insights
     *
     * @param int $user_id User ID
     * @param string $type Insight type
     * @param array $options Options
     * @return string Cache key
     */
    private function get_cache_key($user_id, $type, $options) {
        $key_parts = array(
            'spiralengine_insights',
            $user_id,
            $type,
            date('Y-m-d')
        );
        
        if ($type === 'weekly') {
            $key_parts[] = date('W');
        } elseif ($type === 'monthly') {
            $key_parts[] = date('m');
        }
        
        return implode('_', $key_parts);
    }
    
    /**
     * Get cache duration for insight type
     *
     * @param string $type Insight type
     * @return int Duration in seconds
     */
    private function get_cache_duration($type) {
        switch ($type) {
            case 'daily':
                return 3600; // 1 hour
            case 'weekly':
                return 21600; // 6 hours
            case 'monthly':
                return 43200; // 12 hours
            case 'milestone':
                return 86400; // 24 hours
            case 'predictive':
                return 21600; // 6 hours
            default:
                return 3600;
        }
    }
    
    /**
     * Find most effective coping skill
     *
     * @param array $coping_episodes Coping episodes
     * @return array Most effective skill
     */
    private function find_most_effective_skill($coping_episodes) {
        $skill_effectiveness = array();
        
        foreach ($coping_episodes as $episode) {
            if (isset($episode['data']['skill_category']) && isset($episode['data']['effectiveness'])) {
                $skill = $episode['data']['skill_category'];
                if (!isset($skill_effectiveness[$skill])) {
                    $skill_effectiveness[$skill] = array();
                }
                $skill_effectiveness[$skill][] = $episode['data']['effectiveness'];
            }
        }
        
        $avg_effectiveness = array();
        foreach ($skill_effectiveness as $skill => $scores) {
            $avg_effectiveness[$skill] = array_sum($scores) / count($scores);
        }
        
        if (empty($avg_effectiveness)) {
            return null;
        }
        
        $best_skill = array_search(max($avg_effectiveness), $avg_effectiveness);
        
        return array(
            'skill' => $best_skill,
            'effectiveness' => round($avg_effectiveness[$best_skill], 2)
        );
    }
    
    /**
     * Recommend coping skill
     *
     * @param array $data User data
     * @return string Recommendation
     */
    private function recommend_coping_skill($data) {
        // Get current severity level
        $current_severity = isset($data['stats']['avg_severity']) ? $data['stats']['avg_severity'] : 5;
        
        // Recommend based on severity
        if ($current_severity >= 7) {
            return __('Try grounding techniques or deep breathing exercises', 'spiralengine');
        } elseif ($current_severity >= 5) {
            return __('Consider mindfulness or progressive muscle relaxation', 'spiralengine');
        } else {
            return __('Maintain your routine with regular self-care activities', 'spiralengine');
        }
    }
    
    /**
     * Check for late night pattern
     *
     * @param array $data User data
     * @return bool Has late night pattern
     */
    private function has_late_night_pattern($data) {
        $late_night_episodes = array_filter($data['episodes'], function($ep) {
            $hour = date('G', strtotime($ep['created_at']));
            return $hour >= 22 || $hour <= 2;
        });
        
        return count($late_night_episodes) > count($data['episodes']) * 0.3;
    }
    
    /**
     * Check if first time using widget
     *
     * @param int $user_id User ID
     * @param string $widget Widget ID
     * @return bool Is first time
     */
    private function is_first_time_widget($user_id, $widget) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'spiralengine_episodes';
        
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name 
            WHERE user_id = %d AND widget_id = %s",
            $user_id,
            $widget
        ));
        
        return $count == 1;
    }
    
    /**
     * Identify dominant mood
     *
     * @param array $moods Mood entries
     * @return string Dominant mood
     */
    private function identify_dominant_mood($moods) {
        if (empty($moods)) {
            return 'unknown';
        }
        
        $mood_counts = array_count_values($moods);
        return array_search(max($mood_counts), $mood_counts);
    }
    
    /**
     * Calculate mood stability
     *
     * @param array $moods Mood entries
     * @return string Stability level
     */
    private function calculate_mood_stability($moods) {
        if (count($moods) < 2) {
            return 'insufficient_data';
        }
        
        $unique_moods = count(array_unique($moods));
        $stability_ratio = $unique_moods / count($moods);
        
        if ($stability_ratio <= 0.3) {
            return 'stable';
        } elseif ($stability_ratio <= 0.6) {
            return 'moderate';
        } else {
            return 'variable';
        }
    }
    
    /**
     * Generate weekly overview
     *
     * @param array $data User data
     * @return array Overview
     */
    private function generate_weekly_overview($data) {
        $week_start = date('Y-m-d', strtotime('monday this week'));
        $week_episodes = array_filter($data['episodes'], function($ep) use ($week_start) {
            return strtotime($ep['created_at']) >= strtotime($week_start);
        });
        
        $overview = array(
            'episode_count' => count($week_episodes),
            'days_logged' => count(array_unique(array_map(function($ep) {
                return date('Y-m-d', strtotime($ep['created_at']));
            }, $week_episodes))),
            'most_active_day' => $this->find_most_active_day($week_episodes)
        );
        
        if (!empty($week_episodes)) {
            $severities = array_column($week_episodes, 'severity');
            $overview['severity_stats'] = array(
                'average' => round(array_sum($severities) / count($severities), 2),
                'highest' => max($severities),
                'lowest' => min($severities),
                'trend' => $this->calculate_weekly_trend($week_episodes)
            );
        }
        
        return $overview;
    }
    
    /**
     * Find most active day
     *
     * @param array $episodes Episodes
     * @return array Most active day
     */
    private function find_most_active_day($episodes) {
        $by_day = array();
        
        foreach ($episodes as $episode) {
            $day = date('l', strtotime($episode['created_at']));
            if (!isset($by_day[$day])) {
                $by_day[$day] = 0;
            }
            $by_day[$day]++;
        }
        
        if (empty($by_day)) {
            return null;
        }
        
        $max_day = array_search(max($by_day), $by_day);
        
        return array(
            'day' => $max_day,
            'episodes' => $by_day[$max_day]
        );
    }
    
    /**
     * Calculate weekly trend
     *
     * @param array $episodes Episodes
     * @return string Trend
     */
    private function calculate_weekly_trend($episodes) {
        // Group by day and calculate average severity
        $by_day = array();
        
        foreach ($episodes as $episode) {
            $day = date('Y-m-d', strtotime($episode['created_at']));
            if (!isset($by_day[$day])) {
                $by_day[$day] = array();
            }
            $by_day[$day][] = $episode['severity'];
        }
        
        if (count($by_day) < 3) {
            return 'insufficient_data';
        }
        
        // Calculate daily averages
        $daily_averages = array();
        foreach ($by_day as $day => $severities) {
            $daily_averages[$day] = array_sum($severities) / count($severities);
        }
        
        // Simple linear regression to determine trend
        $days = array_keys($daily_averages);
        $first_half = array_slice($daily_averages, 0, floor(count($daily_averages) / 2));
        $second_half = array_slice($daily_averages, floor(count($daily_averages) / 2));
        
        $first_avg = array_sum($first_half) / count($first_half);
        $second_avg = array_sum($second_half) / count($second_half);
        
        if ($second_avg < $first_avg - 0.5) {
            return 'improving';
        } elseif ($second_avg > $first_avg + 0.5) {
            return 'worsening';
        } else {
            return 'stable';
        }
    }
    
    /**
     * Get insights by type for user
     *
     * @param int $user_id User ID
     * @param string $type Insight type
     * @param int $limit Number of insights
     * @return array Insights
     */
    public function get_user_insights($user_id, $type = null, $limit = 10) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'spiralengine_insights';
        
        // Create table if not exists
        $this->ensure_insights_table_exists();
        
        $where_clauses = array('user_id = %d');
        $where_values = array($user_id);
        
        if ($type !== null) {
            $where_clauses[] = 'insight_type = %s';
            $where_values[] = $type;
        }
        
        $where_sql = implode(' AND ', $where_clauses);
        
        $query = $wpdb->prepare(
            "SELECT * FROM $table_name 
            WHERE $where_sql 
            ORDER BY created_at DESC 
            LIMIT %d",
            array_merge($where_values, array($limit))
        );
        
        $insights = $wpdb->get_results($query, ARRAY_A);
        
        // Decode JSON data
        foreach ($insights as &$insight) {
            $insight['data'] = json_decode($insight['data'], true);
        }
        
        return $insights;
    }
    
    /**
     * Save generated insights
     *
     * @param array $insights Generated insights
     * @return bool Success
     */
    public function save_insights($insights) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'spiralengine_insights';
        
        return $wpdb->insert(
            $table_name,
            array(
                'user_id' => $insights['user_id'],
                'insight_type' => $insights['type'],
                'data' => json_encode($insights['data']),
                'created_at' => current_time('mysql')
            ),
            array('%d', '%s', '%s', '%s')
        );
    }
    
    /**
     * Ensure insights table exists
     *
     * @return void
     */
    private function ensure_insights_table_exists() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'spiralengine_insights';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id bigint(20) UNSIGNED NOT NULL,
            insight_type varchar(50) NOT NULL,
            data JSON NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user_type (user_id, insight_type),
            INDEX idx_created (created_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Summarize patterns for weekly insights
     *
     * @param array $pattern_results Pattern analysis results
     * @return array Pattern summary
     */
    private function summarize_patterns($pattern_results) {
        $summary = array(
            'key_patterns' => array(),
            'changes_from_last_week' => array()
        );
        
        // Extract key patterns
        if (isset($pattern_results['patterns']['temporal']['peak_times'])) {
            $summary['key_patterns']['peak_activity'] = $pattern_results['patterns']['temporal']['peak_times'];
        }
        
        if (isset($pattern_results['patterns']['trigger']['common_triggers'])) {
            $summary['key_patterns']['top_triggers'] = array_slice(
                $pattern_results['patterns']['trigger']['common_triggers'],
                0,
                3,
                true
            );
        }
        
        if (isset($pattern_results['statistical']['trends']['severity']['trend'])) {
            $summary['severity_trend'] = $pattern_results['statistical']['trends']['severity']['trend'];
        }
        
        return $summary;
    }
    
    /**
     * Track weekly progress
     *
     * @param array $data User data
     * @return array Progress tracking
     */
    private function track_weekly_progress($data) {
        $progress = array(
            'episodes_trend' => $this->compare_to_previous_week($data, 'episodes'),
            'severity_trend' => $this->compare_to_previous_week($data, 'severity'),
            'coping_usage' => $this->track_coping_progress($data),
            'goal_progress' => $this->track_goal_progress_weekly($data)
        );
        
        // Calculate overall progress score
        $progress['overall_score'] = $this->calculate_weekly_progress_score($progress);
        
        return $progress;
    }
    
    /**
     * Compare to previous week
     *
     * @param array $data User data
     * @param string $metric Metric to compare
     * @return array Comparison
     */
    private function compare_to_previous_week($data, $metric) {
        $this_week = array_filter($data['episodes'], function($ep) {
            return strtotime($ep['created_at']) >= strtotime('monday this week');
        });
        
        $last_week = array_filter($data['episodes'], function($ep) {
            $time = strtotime($ep['created_at']);
            return $time >= strtotime('monday last week') && 
                   $time < strtotime('monday this week');
        });
        
        switch ($metric) {
            case 'episodes':
                return array(
                    'this_week' => count($this_week),
                    'last_week' => count($last_week),
                    'change' => count($this_week) - count($last_week),
                    'percentage_change' => count($last_week) > 0 ? 
                        round(((count($this_week) - count($last_week)) / count($last_week)) * 100, 1) : 0
                );
                
            case 'severity':
                $this_week_avg = !empty($this_week) ? 
                    array_sum(array_column($this_week, 'severity')) / count($this_week) : 0;
                $last_week_avg = !empty($last_week) ? 
                    array_sum(array_column($last_week, 'severity')) / count($last_week) : 0;
                
                return array(
                    'this_week' => round($this_week_avg, 2),
                    'last_week' => round($last_week_avg, 2),
                    'change' => round($this_week_avg - $last_week_avg, 2),
                    'improved' => $this_week_avg < $last_week_avg
                );
        }
        
        return array();
    }
    
    /**
     * Track coping progress
     *
     * @param array $data User data
     * @return array Coping progress
     */
    private function track_coping_progress($data) {
        $coping_episodes = array_filter($data['episodes'], function($ep) {
            return $ep['widget_id'] === 'coping-logger';
        });
        
        $this_week_coping = array_filter($coping_episodes, function($ep) {
            return strtotime($ep['created_at']) >= strtotime('monday this week');
        });
        
        $effectiveness_scores = array();
        foreach ($this_week_coping as $episode) {
            if (isset($episode['data']['effectiveness'])) {
                $effectiveness_scores[] = $episode['data']['effectiveness'];
            }
        }
        
        return array(
            'skills_used_count' => count($this_week_coping),
            'avg_effectiveness' => !empty($effectiveness_scores) ? 
                round(array_sum($effectiveness_scores) / count($effectiveness_scores), 2) : 0,
            'improvement' => $this->calculate_coping_improvement($coping_episodes)
        );
    }
    
    /**
     * Calculate coping improvement
     *
     * @param array $coping_episodes All coping episodes
     * @return string Improvement level
     */
    private function calculate_coping_improvement($coping_episodes) {
        if (count($coping_episodes) < 10) {
            return 'insufficient_data';
        }
        
        // Compare first half to second half effectiveness
        $first_half = array_slice($coping_episodes, 0, floor(count($coping_episodes) / 2));
        $second_half = array_slice($coping_episodes, floor(count($coping_episodes) / 2));
        
        $first_effectiveness = array();
        $second_effectiveness = array();
        
        foreach ($first_half as $ep) {
            if (isset($ep['data']['effectiveness'])) {
                $first_effectiveness[] = $ep['data']['effectiveness'];
            }
        }
        
        foreach ($second_half as $ep) {
            if (isset($ep['data']['effectiveness'])) {
                $second_effectiveness[] = $ep['data']['effectiveness'];
            }
        }
        
        if (empty($first_effectiveness) || empty($second_effectiveness)) {
            return 'no_effectiveness_data';
        }
        
        $first_avg = array_sum($first_effectiveness) / count($first_effectiveness);
        $second_avg = array_sum($second_effectiveness) / count($second_effectiveness);
        
        if ($second_avg > $first_avg + 1) {
            return 'significant_improvement';
        } elseif ($second_avg > $first_avg) {
            return 'moderate_improvement';
        } elseif ($second_avg < $first_avg - 1) {
            return 'declining';
        } else {
            return 'stable';
        }
    }
    
    /**
     * Track goal progress weekly
     *
     * @param array $data User data
     * @return array Goal progress
     */
    private function track_goal_progress_weekly($data) {
        if (empty($data['goals'])) {
            return array('message' => __('No active goals', 'spiralengine'));
        }
        
        $progress = array(
            'active_goals' => count($data['goals']),
            'milestones_completed' => 0,
            'goals_by_status' => array()
        );
        
        foreach ($data['goals'] as $goal) {
            $status = isset($goal['data']['status']) ? $goal['data']['status'] : 'active';
            
            if (!isset($progress['goals_by_status'][$status])) {
                $progress['goals_by_status'][$status] = 0;
            }
            $progress['goals_by_status'][$status]++;
            
            // Count completed milestones
            if (isset($goal['data']['milestones'])) {
                foreach ($goal['data']['milestones'] as $milestone) {
                    if (isset($milestone['completed']) && $milestone['completed']) {
                        $progress['milestones_completed']++;
                    }
                }
            }
        }
        
        return $progress;
    }
    
    /**
     * Calculate weekly progress score
     *
     * @param array $progress Progress data
     * @return int Score (0-100)
     */
    private function calculate_weekly_progress_score($progress) {
        $score = 50; // Start at neutral
        
        // Episode frequency
        if (isset($progress['episodes_trend']['change'])) {
            if ($progress['episodes_trend']['change'] < -2) {
                $score += 10; // Fewer episodes is good
            } elseif ($progress['episodes_trend']['change'] > 2) {
                $score -= 10;
            }
        }
        
        // Severity improvement
        if (isset($progress['severity_trend']['improved']) && $progress['severity_trend']['improved']) {
            $score += 20;
        }
        
        // Coping usage
        if (isset($progress['coping_usage']['skills_used_count'])) {
            if ($progress['coping_usage']['skills_used_count'] >= 5) {
                $score += 10;
            }
            
            if (isset($progress['coping_usage']['avg_effectiveness']) && 
                $progress['coping_usage']['avg_effectiveness'] >= 7) {
                $score += 10;
            }
        }
        
        // Goal progress
        if (isset($progress['goal_progress']['milestones_completed']) && 
            $progress['goal_progress']['milestones_completed'] > 0) {
            $score += 10;
        }
        
        return max(0, min(100, $score));
    }
    
    /**
     * Schedule insight generation
     *
     * @param int $user_id User ID
     * @param string $type Insight type
     * @param string $schedule When to generate
     * @return bool Success
     */
    public function schedule_insight_generation($user_id, $type, $schedule = 'daily') {
        $hook = 'spiralengine_generate_' . $type . '_insights';
        $args = array($user_id);
        
        // Clear existing schedule
        wp_clear_scheduled_hook($hook, $args);
        
        // Schedule new generation
        switch ($schedule) {
            case 'daily':
                wp_schedule_event(
                    strtotime('tomorrow 6:00am'),
                    'daily',
                    $hook,
                    $args
                );
                break;
                
            case 'weekly':
                wp_schedule_event(
                    strtotime('next monday 6:00am'),
                    'weekly',
                    $hook,
                    $args
                );
                break;
                
            case 'monthly':
                wp_schedule_event(
                    strtotime('first day of next month 6:00am'),
                    'monthly',
                    $hook,
                    $args
                );
                break;
        }
        
        return true;
    }
    
    /**
     * Get insight recommendations
     *
     * @param int $user_id User ID
     * @param array $context Context for recommendations
     * @return array Recommendations
     */
    public function get_insight_recommendations($user_id, $context = array()) {
        // Get recent insights
        $recent_insights = $this->get_user_insights($user_id, null, 5);
        
        if (empty($recent_insights)) {
            return array(
                'message' => __('Start tracking to receive personalized recommendations', 'spiralengine'),
                'actions' => array(
                    array(
                        'type' => 'start_tracking',
                        'message' => __('Log your first episode to begin', 'spiralengine')
                    )
                )
            );
        }
        
        // Analyze recent insights for patterns
        $recommendations = array();
        
        foreach ($recent_insights as $insight) {
            if (isset($insight['data']['recommendations'])) {
                foreach ($insight['data']['recommendations'] as $rec) {
                    $recommendations[] = $rec;
                }
            }
        }
        
        // Deduplicate and prioritize
        $recommendations = $this->prioritize_recommendations($recommendations);
        
        // Add context-specific recommendations
        if (!empty($context)) {
            $contextual_recs = $this->get_contextual_recommendations($user_id, $context);
            $recommendations = array_merge($recommendations, $contextual_recs);
        }
        
        return array(
            'recommendations' => array_slice($recommendations, 0, 5),
            'generated_at' => current_time('mysql')
        );
    }
    
    /**
     * Prioritize recommendations
     *
     * @param array $recommendations All recommendations
     * @return array Prioritized recommendations
     */
    private function prioritize_recommendations($recommendations) {
        // Remove duplicates
        $unique = array();
        $seen = array();
        
        foreach ($recommendations as $rec) {
            $key = md5(serialize($rec));
            if (!isset($seen[$key])) {
                $unique[] = $rec;
                $seen[$key] = true;
            }
        }
        
        // Sort by priority
        usort($unique, function($a, $b) {
            $priority_order = array('high' => 3, 'medium' => 2, 'low' => 1);
            
            $a_priority = isset($a['priority']) && isset($priority_order[$a['priority']]) ? 
                         $priority_order[$a['priority']] : 0;
            $b_priority = isset($b['priority']) && isset($priority_order[$b['priority']]) ? 
                         $priority_order[$b['priority']] : 0;
            
            return $b_priority - $a_priority;
        });
        
        return $unique;
    }
    
    /**
     * Get contextual recommendations
     *
     * @param int $user_id User ID
     * @param array $context Context data
     * @return array Contextual recommendations
     */
    private function get_contextual_recommendations($user_id, $context) {
        $recommendations = array();
        
        // Time-based recommendations
        if (isset($context['time_of_day'])) {
            $hour = intval($context['time_of_day']);
            
            if ($hour >= 22 || $hour <= 6) {
                $recommendations[] = array(
                    'type' => 'sleep',
                    'priority' => 'high',
                    'message' => __('Consider a sleep hygiene routine for better rest', 'spiralengine'),
                    'context' => 'nighttime'
                );
            } elseif ($hour >= 6 && $hour <= 9) {
                $recommendations[] = array(
                    'type' => 'morning_routine',
                    'priority' => 'medium',
                    'message' => __('Start your day with a mindfulness practice', 'spiralengine'),
                    'context' => 'morning'
                );
            }
        }
        
        // Severity-based recommendations
        if (isset($context['current_severity']) && $context['current_severity'] >= 7) {
            $recommendations[] = array(
                'type' => 'crisis_support',
                'priority' => 'high',
                'message' => __('Consider reaching out to your support network', 'spiralengine'),
                'context' => 'high_severity'
            );
        }
        
        return $recommendations;
    }
}

// Hook for scheduled insight generation
add_action('spiralengine_generate_daily_insights', function($user_id) {
    $generator = new SpiralEngine_Insight_Generator();
    $insights = $generator->generate_insights($user_id, 'daily');
    
    if (!isset($insights['error'])) {
        $generator->save_insights($insights);
        
        // Notify user if enabled
        do_action('spiralengine_insights_generated', $user_id, 'daily', $insights);
    }
});

add_action('spiralengine_generate_weekly_insights', function($user_id) {
    $generator = new SpiralEngine_Insight_Generator();
    $insights = $generator->generate_insights($user_id, 'weekly');
    
    if (!isset($insights['error'])) {
        $generator->save_insights($insights);
        
        // Notify user if enabled
        do_action('spiralengine_insights_generated', $user_id, 'weekly', $insights);
    }
});

add_action('spiralengine_generate_monthly_insights', function($user_id) {
    $generator = new SpiralEngine_Insight_Generator();
    $insights = $generator->generate_insights($user_id, 'monthly');
    
    if (!isset($insights['error'])) {
        $generator->save_insights($insights);
        
        // Notify user if enabled
        do_action('spiralengine_insights_generated', $user_id, 'monthly', $insights);
    }
});


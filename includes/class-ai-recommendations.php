<?php
/**
 * SpiralEngine AI Recommendations System
 * 
 * @package    SpiralEngine
 * @subpackage AI
 * @file       includes/class-ai-recommendations.php
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * AI Recommendations class
 * 
 * Provides personalized AI-powered recommendations for users
 */
class SpiralEngine_AI_Recommendations {
    
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
     * Insight Generator instance
     *
     * @var SpiralEngine_Insight_Generator
     */
    private $insight_generator;
    
    /**
     * Recommendation types
     *
     * @var array
     */
    private $recommendation_types = array(
        'immediate' => array(
            'name' => 'Immediate Actions',
            'description' => 'Things you can do right now',
            'timeframe' => 'now',
            'min_tier' => 'silver'
        ),
        'daily' => array(
            'name' => 'Daily Practices',
            'description' => 'Recommendations for today',
            'timeframe' => 'today',
            'min_tier' => 'silver'
        ),
        'weekly' => array(
            'name' => 'Weekly Goals',
            'description' => 'Focus areas for this week',
            'timeframe' => 'week',
            'min_tier' => 'gold'
        ),
        'crisis' => array(
            'name' => 'Crisis Support',
            'description' => 'Help for difficult moments',
            'timeframe' => 'immediate',
            'min_tier' => 'free'
        ),
        'preventive' => array(
            'name' => 'Preventive Care',
            'description' => 'Proactive wellness strategies',
            'timeframe' => 'ongoing',
            'min_tier' => 'gold'
        ),
        'growth' => array(
            'name' => 'Personal Growth',
            'description' => 'Long-term development',
            'timeframe' => 'month',
            'min_tier' => 'platinum'
        )
    );
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->ai_service = SpiralEngine_AI_Service::get_instance();
        $this->pattern_analyzer = new SpiralEngine_Pattern_Analyzer();
        $this->insight_generator = new SpiralEngine_Insight_Generator();
        
        $this->init_hooks();
    }
    
    /**
     * Initialize hooks
     *
     * @return void
     */
    private function init_hooks() {
        // Dashboard widget
        add_action('spiralengine_dashboard_widgets', array($this, 'add_recommendations_widget'));
        
        // AJAX endpoints
        add_action('wp_ajax_spiralengine_get_recommendations', array($this, 'ajax_get_recommendations'));
        add_action('wp_ajax_spiralengine_rate_recommendation', array($this, 'ajax_rate_recommendation'));
        add_action('wp_ajax_spiralengine_dismiss_recommendation', array($this, 'ajax_dismiss_recommendation'));
        
        // Scheduled recommendation generation
        add_action('spiralengine_generate_recommendations', array($this, 'generate_scheduled_recommendations'));
        
        // Real-time recommendation triggers
        add_action('spiralengine_high_severity_episode', array($this, 'trigger_crisis_recommendations'), 10, 2);
        add_action('spiralengine_pattern_detected', array($this, 'trigger_pattern_recommendations'), 10, 3);
    }
    
    /**
     * Get recommendations for user
     *
     * @param int $user_id User ID
     * @param string $type Recommendation type
     * @param array $context Additional context
     * @return array Recommendations
     */
    public function get_recommendations($user_id, $type = 'daily', $context = array()) {
        // Validate recommendation type
        if (!isset($this->recommendation_types[$type])) {
            return array('error' => __('Invalid recommendation type', 'spiralengine'));
        }
        
        // Check user permissions
        $membership = new SpiralEngine_Membership($user_id);
        if (!$this->user_can_access_recommendations($membership->get_tier(), $this->recommendation_types[$type]['min_tier'])) {
            return array(
                'error' => sprintf(
                    __('%s recommendations require %s tier or higher', 'spiralengine'),
                    $this->recommendation_types[$type]['name'],
                    ucfirst($this->recommendation_types[$type]['min_tier'])
                )
            );
        }
        
        // Check cache
        $cache_key = $this->get_cache_key($user_id, $type, $context);
        $cached = get_transient($cache_key);
        
        if ($cached !== false && empty($context['force_refresh'])) {
            return $cached;
        }
        
        // Generate fresh recommendations
        $recommendations = $this->generate_recommendations($user_id, $type, $context);
        
        // Cache results
        if (!isset($recommendations['error'])) {
            $cache_duration = $this->get_cache_duration($type);
            set_transient($cache_key, $recommendations, $cache_duration);
        }
        
        return $recommendations;
    }
    
    /**
     * Generate recommendations
     *
     * @param int $user_id User ID
     * @param string $type Recommendation type
     * @param array $context Context
     * @return array Generated recommendations
     */
    private function generate_recommendations($user_id, $type, $context) {
        // Gather user data
        $user_data = $this->gather_user_data($user_id, $type, $context);
        
        if (isset($user_data['error'])) {
            return $user_data;
        }
        
        // Generate recommendations based on type
        switch ($type) {
            case 'immediate':
                return $this->generate_immediate_recommendations($user_id, $user_data, $context);
                
            case 'daily':
                return $this->generate_daily_recommendations($user_id, $user_data, $context);
                
            case 'weekly':
                return $this->generate_weekly_recommendations($user_id, $user_data, $context);
                
            case 'crisis':
                return $this->generate_crisis_recommendations($user_id, $user_data, $context);
                
            case 'preventive':
                return $this->generate_preventive_recommendations($user_id, $user_data, $context);
                
            case 'growth':
                return $this->generate_growth_recommendations($user_id, $user_data, $context);
                
            default:
                return array('error' => __('Recommendation type not implemented', 'spiralengine'));
        }
    }
    
    /**
     * Generate immediate recommendations
     *
     * @param int $user_id User ID
     * @param array $user_data User data
     * @param array $context Context
     * @return array Immediate recommendations
     */
    private function generate_immediate_recommendations($user_id, $user_data, $context) {
        $recommendations = array(
            'type' => 'immediate',
            'generated_at' => current_time('mysql'),
            'user_id' => $user_id,
            'recommendations' => array()
        );
        
        // Check current state
        $current_state = $this->assess_current_state($user_data);
        
        // Get AI recommendations
        $ai_recs = $this->ai_service->get_recommendations($user_id, 'immediate');
        
        if (!isset($ai_recs['error']) && isset($ai_recs['data']['immediate_actions'])) {
            foreach ($ai_recs['data']['immediate_actions'] as $action) {
                $recommendations['recommendations'][] = array(
                    'id' => $this->generate_recommendation_id(),
                    'title' => $action['action'],
                    'description' => $action['rationale'],
                    'type' => 'ai_generated',
                    'priority' => $this->calculate_priority($action, $current_state),
                    'category' => $this->categorize_action($action),
                    'implementation' => $action['how_to'] ?? '',
                    'duration' => $this->estimate_duration($action),
                    'difficulty' => 'easy'
                );
            }
        }
        
        // Add state-based recommendations
        $state_recs = $this->get_state_based_recommendations($current_state);
        foreach ($state_recs as $rec) {
            $recommendations['recommendations'][] = $rec;
        }
        
        // Sort by priority
        usort($recommendations['recommendations'], function($a, $b) {
            $priority_order = array('critical' => 4, 'high' => 3, 'medium' => 2, 'low' => 1);
            $a_val = $priority_order[$a['priority']] ?? 0;
            $b_val = $priority_order[$b['priority']] ?? 0;
            return $b_val - $a_val;
        });
        
        // Limit recommendations
        $recommendations['recommendations'] = array_slice($recommendations['recommendations'], 0, 5);
        
        return $recommendations;
    }
    
    /**
     * Generate daily recommendations
     *
     * @param int $user_id User ID
     * @param array $user_data User data
     * @param array $context Context
     * @return array Daily recommendations
     */
    private function generate_daily_recommendations($user_id, $user_data, $context) {
        $recommendations = array(
            'type' => 'daily',
            'date' => current_time('Y-m-d'),
            'user_id' => $user_id,
            'recommendations' => array()
        );
        
        // Get insights for today
        $daily_insights = $this->insight_generator->get_user_insights($user_id, 'daily', 1);
        
        // Get patterns
        $patterns = $this->pattern_analyzer->get_cached_results($user_id);
        
        // AI-powered daily recommendations
        $ai_params = array(
            'timeframe' => 'daily',
            'focus_areas' => $this->determine_daily_focus($user_data, $patterns),
            'include_preventive' => true
        );
        
        $ai_recs = $this->ai_service->get_recommendations($user_id, 'daily');
        
        // Process AI recommendations
        if (!isset($ai_recs['error'])) {
            $this->process_ai_recommendations($ai_recs, $recommendations);
        }
        
        // Add time-specific recommendations
        $time_recs = $this->get_time_based_recommendations($user_id);
        foreach ($time_recs as $rec) {
            $recommendations['recommendations'][] = $rec;
        }
        
        // Add pattern-based recommendations
        if ($patterns && isset($patterns['patterns'])) {
            $pattern_recs = $this->get_pattern_based_daily_recommendations($patterns);
            foreach ($pattern_recs as $rec) {
                $recommendations['recommendations'][] = $rec;
            }
        }
        
        // Add wellness check recommendations
        $wellness_recs = $this->get_wellness_recommendations($user_data);
        foreach ($wellness_recs as $rec) {
            $recommendations['recommendations'][] = $rec;
        }
        
        // Organize by time of day
        $recommendations['schedule'] = $this->organize_daily_schedule($recommendations['recommendations']);
        
        return $recommendations;
    }
    
    /**
     * Generate weekly recommendations
     *
     * @param int $user_id User ID
     * @param array $user_data User data
     * @param array $context Context
     * @return array Weekly recommendations
     */
    private function generate_weekly_recommendations($user_id, $user_data, $context) {
        $recommendations = array(
            'type' => 'weekly',
            'week' => date('Y-W'),
            'user_id' => $user_id,
            'recommendations' => array(),
            'focus_areas' => array()
        );
        
        // Get weekly insights
        $weekly_insights = $this->insight_generator->get_user_insights($user_id, 'weekly', 1);
        
        // Comprehensive pattern analysis
        $patterns = $this->pattern_analyzer->analyze_user_patterns($user_id, array(
            'days' => 30,
            'include_ai' => true
        ));
        
        // AI-powered weekly planning
        $ai_recs = $this->ai_service->get_recommendations($user_id, 'weekly_planning');
        
        if (!isset($ai_recs['error'])) {
            // Extract focus areas
            if (isset($ai_recs['data']['focus_areas'])) {
                $recommendations['focus_areas'] = $ai_recs['data']['focus_areas'];
            }
            
            // Process recommendations
            $this->process_ai_recommendations($ai_recs, $recommendations);
        }
        
        // Goal-oriented recommendations
        $goal_recs = $this->get_goal_oriented_recommendations($user_id, $user_data);
        foreach ($goal_recs as $rec) {
            $recommendations['recommendations'][] = $rec;
        }
        
        // Skill development recommendations
        $skill_recs = $this->get_skill_development_recommendations($user_id, $patterns);
        foreach ($skill_recs as $rec) {
            $recommendations['recommendations'][] = $rec;
        }
        
        // Weekly challenges
        $recommendations['weekly_challenge'] = $this->generate_weekly_challenge($user_id, $patterns);
        
        // Milestone tracking
        $recommendations['milestones'] = $this->get_weekly_milestones($user_id);
        
        return $recommendations;
    }
    
    /**
     * Generate crisis recommendations
     *
     * @param int $user_id User ID
     * @param array $user_data User data
     * @param array $context Context
     * @return array Crisis recommendations
     */
    private function generate_crisis_recommendations($user_id, $user_data, $context) {
        $recommendations = array(
            'type' => 'crisis',
            'generated_at' => current_time('mysql'),
            'user_id' => $user_id,
            'severity_level' => $context['severity'] ?? 'high',
            'recommendations' => array()
        );
        
        // Immediate safety recommendations
        $recommendations['recommendations'][] = array(
            'id' => $this->generate_recommendation_id(),
            'title' => __('Immediate Grounding Exercise', 'spiralengine'),
            'description' => __('Use the 5-4-3-2-1 technique: Name 5 things you can see, 4 you can touch, 3 you can hear, 2 you can smell, 1 you can taste.', 'spiralengine'),
            'type' => 'crisis_immediate',
            'priority' => 'critical',
            'category' => 'grounding',
            'duration' => '2-3 minutes',
            'difficulty' => 'easy'
        );
        
        // Breathing exercise
        $recommendations['recommendations'][] = array(
            'id' => $this->generate_recommendation_id(),
            'title' => __('Box Breathing', 'spiralengine'),
            'description' => __('Breathe in for 4 counts, hold for 4, out for 4, hold for 4. Repeat 4-5 times.', 'spiralengine'),
            'type' => 'crisis_immediate',
            'priority' => 'critical',
            'category' => 'breathing',
            'duration' => '3-5 minutes',
            'difficulty' => 'easy'
        );
        
        // Get user's effective coping skills
        $effective_skills = $this->get_user_effective_coping_skills($user_id);
        
        if (!empty($effective_skills)) {
            foreach (array_slice($effective_skills, 0, 2) as $skill) {
                $recommendations['recommendations'][] = array(
                    'id' => $this->generate_recommendation_id(),
                    'title' => sprintf(__('Use %s', 'spiralengine'), $skill['name']),
                    'description' => sprintf(__('This has helped you before (%.0f%% effective)', 'spiralengine'), $skill['effectiveness']),
                    'type' => 'crisis_coping',
                    'priority' => 'high',
                    'category' => 'coping_skill',
                    'skill_id' => $skill['id'],
                    'past_effectiveness' => $skill['effectiveness']
                );
            }
        }
        
        // Support resources
        $recommendations['support_resources'] = $this->get_crisis_resources($user_id);
        
        // Safety plan reminder
        if ($this->user_has_safety_plan($user_id)) {
            $recommendations['safety_plan'] = array(
                'available' => true,
                'last_updated' => $this->get_safety_plan_date($user_id),
                'link' => $this->get_safety_plan_link($user_id)
            );
        }
        
        return $recommendations;
    }
    
    /**
     * Generate preventive recommendations
     *
     * @param int $user_id User ID
     * @param array $user_data User data
     * @param array $context Context
     * @return array Preventive recommendations
     */
    private function generate_preventive_recommendations($user_id, $user_data, $context) {
        $recommendations = array(
            'type' => 'preventive',
            'generated_at' => current_time('mysql'),
            'user_id' => $user_id,
            'recommendations' => array()
        );
        
        // Get predictive insights
        $predictions = $this->insight_generator->generate_insights($user_id, 'predictive');
        
        if (!isset($predictions['error']) && isset($predictions['data']['risk_assessment'])) {
            // Generate preventive measures for identified risks
            foreach ($predictions['data']['risk_assessment'] as $risk) {
                $preventive_measures = $this->get_preventive_measures_for_risk($risk);
                foreach ($preventive_measures as $measure) {
                    $recommendations['recommendations'][] = array_merge($measure, array(
                        'risk_addressed' => $risk['type'],
                        'risk_level' => $risk['level']
                    ));
                }
            }
        }
        
        // Pattern-based prevention
        $patterns = $this->pattern_analyzer->get_cached_results($user_id);
        if ($patterns) {
            $pattern_preventions = $this->get_pattern_prevention_recommendations($patterns);
            foreach ($pattern_preventions as $rec) {
                $recommendations['recommendations'][] = $rec;
            }
        }
        
        // Wellness maintenance
        $wellness_preventions = $this->get_wellness_maintenance_recommendations($user_data);
        foreach ($wellness_preventions as $rec) {
            $recommendations['recommendations'][] = $rec;
        }
        
        // Sort by impact
        usort($recommendations['recommendations'], function($a, $b) {
            $a_impact = $a['expected_impact'] ?? 50;
            $b_impact = $b['expected_impact'] ?? 50;
            return $b_impact - $a_impact;
        });
        
        return $recommendations;
    }
    
    /**
     * Generate growth recommendations
     *
     * @param int $user_id User ID
     * @param array $user_data User data
     * @param array $context Context
     * @return array Growth recommendations
     */
    private function generate_growth_recommendations($user_id, $user_data, $context) {
        $recommendations = array(
            'type' => 'growth',
            'generated_at' => current_time('mysql'),
            'user_id' => $user_id,
            'recommendations' => array(),
            'growth_areas' => array()
        );
        
        // Comprehensive AI analysis for growth opportunities
        $ai_params = array(
            'type' => 'growth_analysis',
            'timeframe' => '90_days',
            'include_strengths' => true,
            'include_opportunities' => true
        );
        
        $ai_analysis = $this->ai_service->generate_insights($user_id, $ai_params);
        
        if (!isset($ai_analysis['error'])) {
            // Extract growth areas
            if (isset($ai_analysis['data']['growth_opportunities'])) {
                $recommendations['growth_areas'] = $ai_analysis['data']['growth_opportunities'];
            }
            
            // Generate recommendations for each growth area
            foreach ($recommendations['growth_areas'] as $area) {
                $area_recs = $this->generate_growth_area_recommendations($area, $user_data);
                foreach ($area_recs as $rec) {
                    $recommendations['recommendations'][] = $rec;
                }
            }
        }
        
        // Skill mastery recommendations
        $skill_mastery = $this->get_skill_mastery_recommendations($user_id);
        foreach ($skill_mastery as $rec) {
            $recommendations['recommendations'][] = $rec;
        }
        
        // Advanced goals
        $advanced_goals = $this->get_advanced_goal_recommendations($user_id, $user_data);
        foreach ($advanced_goals as $rec) {
            $recommendations['recommendations'][] = $rec;
        }
        
        // Personal development resources
        $recommendations['resources'] = $this->get_growth_resources($user_id);
        
        // Growth roadmap
        $recommendations['roadmap'] = $this->create_growth_roadmap($recommendations['recommendations']);
        
        return $recommendations;
    }
    
    /**
     * Gather user data for recommendations
     *
     * @param int $user_id User ID
     * @param string $type Recommendation type
     * @param array $context Context
     * @return array User data
     */
    private function gather_user_data($user_id, $type, $context) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'spiralengine_episodes';
        
        // Determine data period based on recommendation type
        $days = $this->get_data_period_for_type($type);
        
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
        
        // Get recent insights
        $recent_insights = $this->insight_generator->get_user_insights($user_id, null, 5);
        
        // Get active goals
        $active_goals = $this->get_user_active_goals($user_id);
        
        return array(
            'episodes' => $episodes,
            'episode_count' => count($episodes),
            'user_profile' => $user_profile,
            'recent_insights' => $recent_insights,
            'active_goals' => $active_goals,
            'current_state' => $this->assess_current_state(array('episodes' => $episodes))
        );
    }
    
    /**
     * Get data period for recommendation type
     *
     * @param string $type Recommendation type
     * @return int Days
     */
    private function get_data_period_for_type($type) {
        switch ($type) {
            case 'immediate':
                return 1;
            case 'daily':
                return 7;
            case 'weekly':
                return 30;
            case 'crisis':
                return 3;
            case 'preventive':
                return 60;
            case 'growth':
                return 90;
            default:
                return 30;
        }
    }
    
    /**
     * Assess current state
     *
     * @param array $user_data User data
     * @return array Current state assessment
     */
    private function assess_current_state($user_data) {
        $state = array(
            'severity_level' => 'normal',
            'trend' => 'stable',
            'risk_factors' => array(),
            'protective_factors' => array()
        );
        
        if (empty($user_data['episodes'])) {
            return $state;
        }
        
        // Recent episodes (last 24 hours)
        $recent_episodes = array_filter($user_data['episodes'], function($ep) {
            return strtotime($ep['created_at']) > strtotime('-24 hours');
        });
        
        if (!empty($recent_episodes)) {
            $recent_severities = array_column($recent_episodes, 'severity');
            $avg_severity = array_sum($recent_severities) / count($recent_severities);
            
            if ($avg_severity >= 8) {
                $state['severity_level'] = 'critical';
            } elseif ($avg_severity >= 6) {
                $state['severity_level'] = 'high';
            } elseif ($avg_severity >= 4) {
                $state['severity_level'] = 'moderate';
            } else {
                $state['severity_level'] = 'low';
            }
        }
        
        // Trend analysis
        if (count($user_data['episodes']) >= 5) {
            $first_half = array_slice($user_data['episodes'], 0, floor(count($user_data['episodes']) / 2));
            $second_half = array_slice($user_data['episodes'], floor(count($user_data['episodes']) / 2));
            
            $first_avg = array_sum(array_column($first_half, 'severity')) / count($first_half);
            $second_avg = array_sum(array_column($second_half, 'severity')) / count($second_half);
            
            if ($second_avg > $first_avg + 1) {
                $state['trend'] = 'worsening';
                $state['risk_factors'][] = 'escalating_severity';
            } elseif ($second_avg < $first_avg - 1) {
                $state['trend'] = 'improving';
                $state['protective_factors'][] = 'decreasing_severity';
            }
        }
        
        // Check for protective factors
        $coping_episodes = array_filter($user_data['episodes'], function($ep) {
            return $ep['widget_id'] === 'coping-logger';
        });
        
        if (count($coping_episodes) > count($user_data['episodes']) * 0.2) {
            $state['protective_factors'][] = 'active_coping';
        }
        
        return $state;
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
            'tier' => $membership->get_tier(),
            'member_since' => $user->user_registered,
            'preferences' => get_user_meta($user_id, 'spiralengine_preferences', true),
            'timezone' => get_user_meta($user_id, 'spiralengine_timezone', true),
            'notification_settings' => get_user_meta($user_id, 'spiralengine_notifications', true)
        );
    }
    
    /**
     * Get user's active goals
     *
     * @param int $user_id User ID
     * @return array Active goals
     */
    private function get_user_active_goals($user_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'spiralengine_episodes';
        
        $goals = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name 
            WHERE user_id = %d 
            AND widget_id = 'goal-setting'
            AND JSON_EXTRACT(data, '$.status') = 'active'
            ORDER BY created_at DESC",
            $user_id
        ), ARRAY_A);
        
        foreach ($goals as &$goal) {
            $goal['data'] = json_decode($goal['data'], true);
        }
        
        return $goals;
    }
    
    /**
     * Calculate priority for action
     *
     * @param array $action Action data
     * @param array $current_state Current state
     * @return string Priority level
     */
    private function calculate_priority($action, $current_state) {
        // Base priority on current severity
        if ($current_state['severity_level'] === 'critical') {
            return 'critical';
        } elseif ($current_state['severity_level'] === 'high') {
            return 'high';
        }
        
        // Check action urgency indicators
        $action_text = strtolower($action['action']);
        if (strpos($action_text, 'immediate') !== false || 
            strpos($action_text, 'now') !== false) {
            return 'high';
        }
        
        return 'medium';
    }
    
    /**
     * Categorize action
     *
     * @param array $action Action data
     * @return string Category
     */
    private function categorize_action($action) {
        $action_text = strtolower($action['action']);
        
        $categories = array(
            'breathing' => array('breath', 'breathing'),
            'movement' => array('walk', 'move', 'exercise', 'stretch'),
            'grounding' => array('ground', '5-4-3-2-1', 'senses'),
            'social' => array('call', 'friend', 'support', 'talk'),
            'creative' => array('write', 'draw', 'music', 'art'),
            'mindfulness' => array('meditat', 'mindful', 'present'),
            'self_care' => array('self-care', 'bath', 'tea', 'comfort')
        );
        
        foreach ($categories as $category => $keywords) {
            foreach ($keywords as $keyword) {
                if (strpos($action_text, $keyword) !== false) {
                    return $category;
                }
            }
        }
        
        return 'general';
    }
    
    /**
     * Estimate duration for action
     *
     * @param array $action Action data
     * @return string Duration estimate
     */
    private function estimate_duration($action) {
        $action_text = strtolower($action['action']);
        
        if (strpos($action_text, 'quick') !== false || 
            strpos($action_text, 'brief') !== false) {
            return '1-2 minutes';
        } elseif (strpos($action_text, 'walk') !== false) {
            return '10-15 minutes';
        } elseif (strpos($action_text, 'meditat') !== false) {
            return '5-10 minutes';
        } else {
            return '5 minutes';
        }
    }
    
    /**
     * Generate recommendation ID
     *
     * @return string Unique ID
     */
    private function generate_recommendation_id() {
        return 'rec_' . wp_generate_password(12, false);
    }
    
    /**
     * Get state-based recommendations
     *
     * @param array $current_state Current state
     * @return array Recommendations
     */
    private function get_state_based_recommendations($current_state) {
        $recommendations = array();
        
        // High severity recommendations
        if ($current_state['severity_level'] === 'high' || $current_state['severity_level'] === 'critical') {
            $recommendations[] = array(
                'id' => $this->generate_recommendation_id(),
                'title' => __('Contact Your Support Person', 'spiralengine'),
                'description' => __('Reach out to someone you trust for support', 'spiralengine'),
                'type' => 'support',
                'priority' => 'high',
                'category' => 'social',
                'duration' => '15-30 minutes',
                'difficulty' => 'easy'
            );
        }
        
        // Worsening trend recommendations
        if ($current_state['trend'] === 'worsening') {
            $recommendations[] = array(
                'id' => $this->generate_recommendation_id(),
                'title' => __('Schedule Self-Care Time', 'spiralengine'),
                'description' => __('Block out 30 minutes today for an activity you enjoy', 'spiralengine'),
                'type' => 'preventive',
                'priority' => 'medium',
                'category' => 'self_care',
                'duration' => '30 minutes',
                'difficulty' => 'easy'
            );
        }
        
        return $recommendations;
    }
    
    /**
     * Process AI recommendations
     *
     * @param array $ai_recs AI recommendations
     * @param array &$recommendations Recommendations array to update
     * @return void
     */
    private function process_ai_recommendations($ai_recs, &$recommendations) {
        if (isset($ai_recs['data']['immediate_actions'])) {
            foreach ($ai_recs['data']['immediate_actions'] as $action) {
                $recommendations['recommendations'][] = array(
                    'id' => $this->generate_recommendation_id(),
                    'title' => $action['action'],
                    'description' => $action['rationale'],
                    'type' => 'ai_generated',
                    'priority' => 'medium',
                    'category' => $this->categorize_action($action),
                    'implementation' => $action['how_to'] ?? '',
                    'source' => 'ai'
                );
            }
        }
        
        if (isset($ai_recs['data']['coping_strategies'])) {
            foreach ($ai_recs['data']['coping_strategies'] as $strategy) {
                $recommendations['recommendations'][] = array(
                    'id' => $this->generate_recommendation_id(),
                    'title' => $strategy['strategy'],
                    'description' => $strategy['expected_benefit'],
                    'type' => 'coping_strategy',
                    'priority' => 'medium',
                    'category' => 'coping',
                    'when_to_use' => $strategy['when_to_use'],
                    'source' => 'ai'
                );
            }
        }
    }
    
    /**
     * Determine daily focus areas
     *
     * @param array $user_data User data
     * @param array|null $patterns Pattern data
     * @return array Focus areas
     */
    private function determine_daily_focus($user_data, $patterns) {
        $focus_areas = array();
        
        // Based on current state
        if ($user_data['current_state']['severity_level'] === 'high') {
            $focus_areas[] = 'crisis_management';
        }
        
        // Based on day of week
        $day = date('l');
        if ($day === 'Monday') {
            $focus_areas[] = 'week_preparation';
        } elseif ($day === 'Friday') {
            $focus_areas[] = 'week_reflection';
        }
        
        // Based on patterns
        if ($patterns && isset($patterns['patterns']['temporal']['peak_times'])) {
            $focus_areas[] = 'peak_time_management';
        }
        
        // Default areas
        if (empty($focus_areas)) {
            $focus_areas = array('general_wellness', 'coping_skills', 'self_care');
        }
        
        return $focus_areas;
    }
    
    /**
     * Get time-based recommendations
     *
     * @param int $user_id User ID
     * @return array Time-based recommendations
     */
    private function get_time_based_recommendations($user_id) {
        $recommendations = array();
        $hour = date('G');
        
        // Morning recommendations (6-10 AM)
        if ($hour >= 6 && $hour <= 10) {
            $recommendations[] = array(
                'id' => $this->generate_recommendation_id(),
                'title' => __('Morning Mindfulness', 'spiralengine'),
                'description' => __('Start your day with 5 minutes of mindful breathing', 'spiralengine'),
                'type' => 'routine',
                'priority' => 'medium',
                'category' => 'mindfulness',
                'time_slot' => 'morning',
                'duration' => '5 minutes'
            );
        }
        
        // Afternoon recommendations (14-17 PM)
        elseif ($hour >= 14 && $hour <= 17) {
            $recommendations[] = array(
                'id' => $this->generate_recommendation_id(),
                'title' => __('Afternoon Energy Boost', 'spiralengine'),
                'description' => __('Take a 10-minute walk or do light stretching', 'spiralengine'),
                'type' => 'routine',
                'priority' => 'low',
                'category' => 'movement',
                'time_slot' => 'afternoon',
                'duration' => '10 minutes'
            );
        }
        
        // Evening recommendations (20-23 PM)
        elseif ($hour >= 20 && $hour <= 23) {
            $recommendations[] = array(
                'id' => $this->generate_recommendation_id(),
                'title' => __('Evening Wind-Down', 'spiralengine'),
                'description' => __('Practice progressive muscle relaxation before bed', 'spiralengine'),
                'type' => 'routine',
                'priority' => 'medium',
                'category' => 'relaxation',
                'time_slot' => 'evening',
                'duration' => '10-15 minutes'
            );
        }
        
        return $recommendations;
    }
    
    /**
     * Get pattern-based daily recommendations
     *
     * @param array $patterns Pattern data
     * @return array Pattern-based recommendations
     */
    private function get_pattern_based_daily_recommendations($patterns) {
        $recommendations = array();
        
        // Trigger pattern recommendations
        if (isset($patterns['patterns']['trigger']['common_triggers'])) {
            $top_trigger = array_key_first($patterns['patterns']['trigger']['common_triggers']);
            
            $recommendations[] = array(
                'id' => $this->generate_recommendation_id(),
                'title' => sprintf(__('Prepare for %s', 'spiralengine'), $top_trigger),
                'description' => __('This is one of your common triggers. Have a coping strategy ready.', 'spiralengine'),
                'type' => 'preventive',
                'priority' => 'high',
                'category' => 'trigger_management',
                'trigger' => $top_trigger
            );
        }
        
        // Peak time recommendations
        if (isset($patterns['patterns']['temporal']['peak_times'])) {
            $peak_hour = $patterns['patterns']['temporal']['peak_times']['hour']['value'];
            
            $recommendations[] = array(
                'id' => $this->generate_recommendation_id(),
                'title' => __('Peak Time Preparation', 'spiralengine'),
                'description' => sprintf(
                    __('You often experience episodes around %02d:00. Plan calming activities for this time.', 'spiralengine'),
                    $peak_hour
                ),
                'type' => 'preventive',
                'priority' => 'medium',
                'category' => 'timing',
                'peak_hour' => $peak_hour
            );
        }
        
        return $recommendations;
    }
    
    /**
     * Get wellness recommendations
     *
     * @param array $user_data User data
     * @return array Wellness recommendations
     */
    private function get_wellness_recommendations($user_data) {
        $recommendations = array();
        
        // Check if user has logged meals
        $meal_logged = $this->check_wellness_aspect($user_data, 'nutrition');
        if (!$meal_logged) {
            $recommendations[] = array(
                'id' => $this->generate_recommendation_id(),
                'title' => __('Nourish Your Body', 'spiralengine'),
                'description' => __('Remember to eat regular, balanced meals', 'spiralengine'),
                'type' => 'wellness',
                'priority' => 'low',
                'category' => 'nutrition'
            );
        }
        
        // Check sleep patterns
        $sleep_quality = $this->check_wellness_aspect($user_data, 'sleep');
        if ($sleep_quality === 'poor') {
            $recommendations[] = array(
                'id' => $this->generate_recommendation_id(),
                'title' => __('Improve Sleep Hygiene', 'spiralengine'),
                'description' => __('Create a consistent bedtime routine', 'spiralengine'),
                'type' => 'wellness',
                'priority' => 'medium',
                'category' => 'sleep'
            );
        }
        
        return $recommendations;
    }
    
    /**
     * Check wellness aspect
     *
     * @param array $user_data User data
     * @param string $aspect Wellness aspect
     * @return mixed Aspect status
     */
    private function check_wellness_aspect($user_data, $aspect) {
        // This would check user's logged data for the specific aspect
        // For now, return default values
        switch ($aspect) {
            case 'nutrition':
                return false; // Not logged
            case 'sleep':
                return 'unknown';
            default:
                return null;
        }
    }
    
    /**
     * Organize daily schedule
     *
     * @param array $recommendations Recommendations
     * @return array Organized schedule
     */
    private function organize_daily_schedule($recommendations) {
        $schedule = array(
            'morning' => array(),
            'afternoon' => array(),
            'evening' => array(),
            'anytime' => array()
        );
        
        foreach ($recommendations as $rec) {
            if (isset($rec['time_slot'])) {
                $schedule[$rec['time_slot']][] = $rec;
            } else {
                $schedule['anytime'][] = $rec;
            }
        }
        
        return $schedule;
    }
    
    /**
     * Add recommendations widget to dashboard
     *
     * @return void
     */
    public function add_recommendations_widget() {
        ?>
        <div class="spiralengine-widget" id="recommendations-widget">
            <h3><?php _e('AI Recommendations', 'spiralengine'); ?></h3>
            <div class="recommendations-container">
                <div class="loading">
                    <span class="spinner is-active"></span>
                    <?php _e('Loading recommendations...', 'spiralengine'); ?>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * AJAX handler for getting recommendations
     *
     * @return void
     */
    public function ajax_get_recommendations() {
        check_ajax_referer('spiralengine_ai_nonce', 'nonce');
        
        $user_id = get_current_user_id();
        $type = isset($_POST['type']) ? sanitize_text_field($_POST['type']) : 'daily';
        $context = isset($_POST['context']) ? json_decode(stripslashes($_POST['context']), true) : array();
        
        $recommendations = $this->get_recommendations($user_id, $type, $context);
        
        wp_send_json_success($recommendations);
    }
    
    /**
     * AJAX handler for rating recommendation
     *
     * @return void
     */
    public function ajax_rate_recommendation() {
        check_ajax_referer('spiralengine_ai_nonce', 'nonce');
        
        $user_id = get_current_user_id();
        $recommendation_id = isset($_POST['recommendation_id']) ? sanitize_text_field($_POST['recommendation_id']) : '';
        $rating = isset($_POST['rating']) ? intval($_POST['rating']) : 0;
        $feedback = isset($_POST['feedback']) ? sanitize_textarea_field($_POST['feedback']) : '';
        
        if (empty($recommendation_id) || $rating < 1 || $rating > 5) {
            wp_send_json_error(__('Invalid rating data', 'spiralengine'));
        }
        
        // Store rating
        $this->store_recommendation_rating($user_id, $recommendation_id, $rating, $feedback);
        
        wp_send_json_success(array(
            'message' => __('Thank you for your feedback!', 'spiralengine')
        ));
    }
    
    /**
     * AJAX handler for dismissing recommendation
     *
     * @return void
     */
    public function ajax_dismiss_recommendation() {
        check_ajax_referer('spiralengine_ai_nonce', 'nonce');
        
        $user_id = get_current_user_id();
        $recommendation_id = isset($_POST['recommendation_id']) ? sanitize_text_field($_POST['recommendation_id']) : '';
        $reason = isset($_POST['reason']) ? sanitize_text_field($_POST['reason']) : '';
        
        if (empty($recommendation_id)) {
            wp_send_json_error(__('Invalid recommendation ID', 'spiralengine'));
        }
        
        // Store dismissal
        $this->store_recommendation_dismissal($user_id, $recommendation_id, $reason);
        
        wp_send_json_success();
    }
    
    /**
     * Trigger crisis recommendations
     *
     * @param int $episode_id Episode ID
     * @param array $episode_data Episode data
     * @return void
     */
    public function trigger_crisis_recommendations($episode_id, $episode_data) {
        if ($episode_data['severity'] >= 8) {
            $user_id = $episode_data['user_id'];
            
            // Generate crisis recommendations
            $recommendations = $this->get_recommendations($user_id, 'crisis', array(
                'severity' => $episode_data['severity'],
                'trigger' => 'high_severity_episode'
            ));
            
            // Notify user if enabled
            if ($this->should_notify_crisis_recommendations($user_id)) {
                do_action('spiralengine_notify_crisis_recommendations', $user_id, $recommendations);
            }
        }
    }
    
    /**
     * Trigger pattern-based recommendations
     *
     * @param int $user_id User ID
     * @param string $pattern_type Pattern type
     * @param array $pattern_data Pattern data
     * @return void
     */
    public function trigger_pattern_recommendations($user_id, $pattern_type, $pattern_data) {
        // Generate contextual recommendations based on detected pattern
        $context = array(
            'pattern_type' => $pattern_type,
            'pattern_data' => $pattern_data,
            'trigger' => 'pattern_detection'
        );
        
        $recommendations = $this->get_recommendations($user_id, 'preventive', $context);
        
        // Store for user's next visit
        $this->queue_recommendations($user_id, $recommendations);
    }
    
    /**
     * Generate scheduled recommendations
     *
     * @param int $user_id User ID
     * @return void
     */
    public function generate_scheduled_recommendations($user_id) {
        // Generate daily recommendations
        $daily_recs = $this->get_recommendations($user_id, 'daily', array('scheduled' => true));
        
        if (!isset($daily_recs['error'])) {
            // Store recommendations
            $this->store_scheduled_recommendations($user_id, $daily_recs);
            
            // Notify user if enabled
            if ($this->should_notify_daily_recommendations($user_id)) {
                do_action('spiralengine_notify_daily_recommendations', $user_id, $daily_recs);
            }
        }
    }
    
    /**
     * Get cache key
     *
     * @param int $user_id User ID
     * @param string $type Recommendation type
     * @param array $context Context
     * @return string Cache key
     */
    private function get_cache_key($user_id, $type, $context) {
        $key_parts = array(
            'spiralengine_recommendations',
            $user_id,
            $type,
            md5(serialize($context))
        );
        
        return implode('_', $key_parts);
    }
    
    /**
     * Get cache duration
     *
     * @param string $type Recommendation type
     * @return int Duration in seconds
     */
    private function get_cache_duration($type) {
        switch ($type) {
            case 'immediate':
                return 300; // 5 minutes
            case 'daily':
                return 3600; // 1 hour
            case 'weekly':
                return 21600; // 6 hours
            case 'crisis':
                return 0; // Don't cache crisis recommendations
            case 'preventive':
                return 7200; // 2 hours
            case 'growth':
                return 43200; // 12 hours
            default:
                return 3600;
        }
    }
    
    /**
     * Check if user can access recommendations
     *
     * @param string $user_tier User's tier
     * @param string $required_tier Required tier
     * @return bool Can access
     */
    private function user_can_access_recommendations($user_tier, $required_tier) {
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
     * Get user's effective coping skills
     *
     * @param int $user_id User ID
     * @return array Effective skills
     */
    private function get_user_effective_coping_skills($user_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'spiralengine_episodes';
        
        $skills = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                JSON_EXTRACT(data, '$.skill_category') as skill_name,
                JSON_EXTRACT(data, '$.skill_id') as skill_id,
                AVG(CAST(JSON_EXTRACT(data, '$.effectiveness') AS UNSIGNED)) as avg_effectiveness,
                COUNT(*) as usage_count
            FROM $table_name
            WHERE user_id = %d 
            AND widget_id = 'coping-logger'
            AND JSON_EXTRACT(data, '$.effectiveness') IS NOT NULL
            GROUP BY skill_name, skill_id
            HAVING avg_effectiveness >= 7
            ORDER BY avg_effectiveness DESC
            LIMIT 5",
            $user_id
        ), ARRAY_A);
        
        $effective_skills = array();
        foreach ($skills as $skill) {
            $effective_skills[] = array(
                'id' => trim($skill['skill_id'], '"'),
                'name' => trim($skill['skill_name'], '"'),
                'effectiveness' => floatval($skill['avg_effectiveness']),
                'usage_count' => intval($skill['usage_count'])
            );
        }
        
        return $effective_skills;
    }
    
    /**
     * Get crisis resources
     *
     * @param int $user_id User ID
     * @return array Crisis resources
     */
    private function get_crisis_resources($user_id) {
        $resources = array(
            'hotlines' => array(
                array(
                    'name' => __('National Crisis Hotline', 'spiralengine'),
                    'number' => '988',
                    'available' => '24/7'
                )
            ),
            'contacts' => $this->get_user_support_contacts($user_id),
            'local_resources' => $this->get_local_crisis_resources($user_id)
        );
        
        return $resources;
    }
    
    /**
     * Get user's support contacts
     *
     * @param int $user_id User ID
     * @return array Support contacts
     */
    private function get_user_support_contacts($user_id) {
        $contacts = get_user_meta($user_id, 'spiralengine_support_contacts', true);
        
        if (!is_array($contacts)) {
            return array();
        }
        
        return $contacts;
    }
    
    /**
     * Get local crisis resources
     *
     * @param int $user_id User ID
     * @return array Local resources
     */
    private function get_local_crisis_resources($user_id) {
        // This would integrate with a crisis resource database
        // For now, return general resources
        return array(
            array(
                'type' => 'website',
                'name' => __('Crisis Text Line', 'spiralengine'),
                'access' => __('Text HOME to 741741', 'spiralengine')
            )
        );
    }
    
    /**
     * Check if user has safety plan
     *
     * @param int $user_id User ID
     * @return bool Has safety plan
     */
    private function user_has_safety_plan($user_id) {
        return get_user_meta($user_id, 'spiralengine_safety_plan', true) !== '';
    }
    
    /**
     * Get safety plan date
     *
     * @param int $user_id User ID
     * @return string Date
     */
    private function get_safety_plan_date($user_id) {
        return get_user_meta($user_id, 'spiralengine_safety_plan_date', true);
    }
    
    /**
     * Get safety plan link
     *
     * @param int $user_id User ID
     * @return string Link
     */
    private function get_safety_plan_link($user_id) {
        return add_query_arg(array(
            'page' => 'spiralengine-safety-plan'
        ), admin_url('admin.php'));
    }
    
    /**
     * Store recommendation rating
     *
     * @param int $user_id User ID
     * @param string $recommendation_id Recommendation ID
     * @param int $rating Rating
     * @param string $feedback Feedback
     * @return bool Success
     */
    private function store_recommendation_rating($user_id, $recommendation_id, $rating, $feedback) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'spiralengine_recommendation_feedback';
        
        // Create table if not exists
        $this->ensure_feedback_table_exists();
        
        return $wpdb->insert(
            $table_name,
            array(
                'user_id' => $user_id,
                'recommendation_id' => $recommendation_id,
                'rating' => $rating,
                'feedback' => $feedback,
                'created_at' => current_time('mysql')
            ),
            array('%d', '%s', '%d', '%s', '%s')
        );
    }
    
    /**
     * Store recommendation dismissal
     *
     * @param int $user_id User ID
     * @param string $recommendation_id Recommendation ID
     * @param string $reason Dismissal reason
     * @return bool Success
     */
    private function store_recommendation_dismissal($user_id, $recommendation_id, $reason) {
        $dismissed = get_user_meta($user_id, 'spiralengine_dismissed_recommendations', true);
        
        if (!is_array($dismissed)) {
            $dismissed = array();
        }
        
        $dismissed[$recommendation_id] = array(
            'dismissed_at' => current_time('mysql'),
            'reason' => $reason
        );
        
        return update_user_meta($user_id, 'spiralengine_dismissed_recommendations', $dismissed);
    }
    
    /**
     * Ensure feedback table exists
     *
     * @return void
     */
    private function ensure_feedback_table_exists() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'spiralengine_recommendation_feedback';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id bigint(20) UNSIGNED NOT NULL,
            recommendation_id varchar(50) NOT NULL,
            rating tinyint(1) NOT NULL,
            feedback text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user_rec (user_id, recommendation_id),
            INDEX idx_created (created_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Should notify crisis recommendations
     *
     * @param int $user_id User ID
     * @return bool Should notify
     */
    private function should_notify_crisis_recommendations($user_id) {
        $settings = get_user_meta($user_id, 'spiralengine_notifications', true);
        return isset($settings['crisis_recommendations']) ? $settings['crisis_recommendations'] : true;
    }
    
    /**
     * Should notify daily recommendations
     *
     * @param int $user_id User ID
     * @return bool Should notify
     */
    private function should_notify_daily_recommendations($user_id) {
        $settings = get_user_meta($user_id, 'spiralengine_notifications', true);
        return isset($settings['daily_recommendations']) ? $settings['daily_recommendations'] : true;
    }
    
    /**
     * Queue recommendations for user
     *
     * @param int $user_id User ID
     * @param array $recommendations Recommendations
     * @return bool Success
     */
    private function queue_recommendations($user_id, $recommendations) {
        $queue = get_user_meta($user_id, 'spiralengine_recommendation_queue', true);
        
        if (!is_array($queue)) {
            $queue = array();
        }
        
        $queue[] = array(
            'recommendations' => $recommendations,
            'queued_at' => current_time('mysql'),
            'expires_at' => date('Y-m-d H:i:s', strtotime('+7 days'))
        );
        
        // Keep only last 10 queued items
        $queue = array_slice($queue, -10);
        
        return update_user_meta($user_id, 'spiralengine_recommendation_queue', $queue);
    }
    
    /**
     * Store scheduled recommendations
     *
     * @param int $user_id User ID
     * @param array $recommendations Recommendations
     * @return bool Success
     */
    private function store_scheduled_recommendations($user_id, $recommendations) {
        return update_user_meta(
            $user_id,
            'spiralengine_scheduled_recommendations_' . date('Y-m-d'),
            $recommendations
        );
    }
}

// Initialize AI recommendations
add_action('init', function() {
    if (get_option('spiralengine_ai_enabled', true)) {
        new SpiralEngine_AI_Recommendations();
    }
});

// Schedule daily recommendations
add_action('init', function() {
    if (!wp_next_scheduled('spiralengine_generate_daily_recommendations')) {
        wp_schedule_event(
            strtotime('tomorrow 7:00am'),
            'daily',
            'spiralengine_generate_daily_recommendations'
        );
    }
});

// Process daily recommendation generation for all users
add_action('spiralengine_generate_daily_recommendations', function() {
    $users = get_users(array(
        'meta_key' => 'spiralengine_tier',
        'meta_value' => array('silver', 'gold', 'platinum'),
        'meta_compare' => 'IN'
    ));
    
    foreach ($users as $user) {
        do_action('spiralengine_generate_recommendations', $user->ID);
    }
});


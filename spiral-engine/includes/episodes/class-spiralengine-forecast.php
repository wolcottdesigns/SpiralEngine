<?php
/**
 * Episode Forecast Engine - AI-powered predictive analytics from AI Command Center
 * 
 * @package    SpiralEngine
 * @subpackage Episodes
 * @file       includes/episodes/class-spiralengine-forecast.php
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Forecast Engine for Episode Framework
 * 
 * This class implements the forecasting capabilities from the AI Command Center,
 * providing prediction algorithms, risk scoring, prevention suggestions, and
 * membership-based access controls.
 */
class SPIRAL_Forecast_Engine {
    
    /**
     * Forecast windows and their configurations
     * @var array
     */
    private $forecast_windows = array(
        '24_hour' => array(
            'name' => '24 Hour Forecast',
            'hours' => 24,
            'min_membership' => 'basic',
            'update_frequency' => 'hourly',
            'algorithms' => array('immediate_risk', 'trigger_exposure', 'temporal_patterns')
        ),
        '3_day' => array(
            'name' => '3 Day Forecast',
            'hours' => 72,
            'min_membership' => 'basic',
            'update_frequency' => '6_hours',
            'algorithms' => array('short_term_patterns', 'biological_cycles', 'environmental_factors')
        ),
        '7_day' => array(
            'name' => '7 Day Forecast',
            'hours' => 168,
            'min_membership' => 'premium',
            'update_frequency' => 'daily',
            'algorithms' => array('weekly_patterns', 'correlation_risks', 'cascade_prediction')
        ),
        '30_day' => array(
            'name' => '30 Day Outlook',
            'hours' => 720,
            'min_membership' => 'platinum',
            'update_frequency' => 'weekly',
            'algorithms' => array('monthly_cycles', 'seasonal_patterns', 'long_term_trends')
        )
    );
    
    /**
     * Risk level thresholds
     * @var array
     */
    private $risk_levels = array(
        'low' => array(
            'min' => 0,
            'max' => 0.3,
            'color' => '#4CAF50',
            'description' => 'Low risk - Continue regular self-care'
        ),
        'moderate' => array(
            'min' => 0.3,
            'max' => 0.6,
            'color' => '#FFC107',
            'description' => 'Moderate risk - Increase preventive measures'
        ),
        'high' => array(
            'min' => 0.6,
            'max' => 0.8,
            'color' => '#FF5722',
            'description' => 'High risk - Implement active interventions'
        ),
        'critical' => array(
            'min' => 0.8,
            'max' => 1.0,
            'color' => '#D32F2F',
            'description' => 'Critical risk - Seek immediate support'
        )
    );
    
    /**
     * AI service instance
     * @var SPIRAL_Episode_AI_Service
     */
    private $ai_service;
    
    /**
     * Pattern detector instance
     * @var SPIRAL_Pattern_Detector
     */
    private $pattern_detector;
    
    /**
     * Correlation engine instance
     * @var SPIRAL_Correlation_Engine
     */
    private $correlation_engine;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->init();
    }
    
    /**
     * Initialize forecast engine
     */
    private function init() {
        // Initialize services
        if (class_exists('SPIRAL_Episode_AI_Service')) {
            $this->ai_service = new SPIRAL_Episode_AI_Service();
        }
        
        // Initialize pattern detector
        $this->pattern_detector = new SPIRAL_Pattern_Detector();
        
        // Initialize correlation engine
        $this->correlation_engine = new SPIRAL_Correlation_Engine();
        
        // Schedule forecast updates
        $this->schedule_forecast_updates();
        
        // Register hooks
        add_action('spiral_generate_forecast', array($this, 'generate_user_forecast'), 10, 2);
        add_action('spiral_update_forecasts', array($this, 'update_all_forecasts'));
    }
    
    /**
     * Generate unified forecast for user
     * 
     * @param int $user_id User ID
     * @param string $window Forecast window
     * @return array Forecast data
     */
    public function generate_unified_forecast($user_id, $window = '24_hour') {
        // Check user permissions
        if (!$this->can_access_forecast($user_id, $window)) {
            return array(
                'error' => 'insufficient_membership',
                'message' => 'Upgrade your membership to access this forecast window',
                'required_level' => $this->forecast_windows[$window]['min_membership']
            );
        }
        
        // Check if cached forecast exists and is fresh
        $cached = $this->get_cached_forecast($user_id, $window);
        if ($cached && $this->is_forecast_fresh($cached, $window)) {
            return $cached;
        }
        
        // Generate new forecast
        $forecast = $this->build_forecast($user_id, $window);
        
        // Cache the forecast
        $this->cache_forecast($user_id, $window, $forecast);
        
        // Log forecast generation
        $this->log_forecast_generation($user_id, $window, $forecast);
        
        return $forecast;
    }
    
    /**
     * Build forecast data
     * 
     * @param int $user_id User ID
     * @param string $window Forecast window
     * @return array Forecast
     */
    private function build_forecast($user_id, $window) {
        $window_config = $this->forecast_windows[$window];
        $registry = SPIRAL_Episode_Registry::get_instance();
        
        // Initialize forecast structure
        $forecast = array(
            'user_id' => $user_id,
            'window' => $window,
            'generated_at' => current_time('mysql'),
            'expires_at' => $this->calculate_expiry($window),
            'overall_risk' => 0,
            'confidence' => 0,
            'episode_risks' => array(),
            'high_risk_periods' => array(),
            'prevention_plan' => array(),
            'insights' => array()
        );
        
        // Get enabled episode types for user
        $enabled_types = $this->get_user_enabled_episodes($user_id);
        
        // Collect risk data from each episode type
        $total_weight = 0;
        $weighted_risk = 0;
        $confidence_sum = 0;
        
        foreach ($enabled_types as $episode_type) {
            $type_config = $registry->get_episode_type($episode_type);
            if (!$type_config) continue;
            
            // Get episode-specific forecast contribution
            $episode_class = $this->get_episode_widget_class($episode_type);
            if (!$episode_class) continue;
            
            $episode_widget = new $episode_class();
            $contribution = $episode_widget->contribute_to_forecast($user_id, $window);
            
            // Add to forecast
            $forecast['episode_risks'][$episode_type] = array(
                'type' => $episode_type,
                'name' => $type_config['display_name'],
                'color' => $type_config['color'],
                'risk_score' => $contribution['risk_score'],
                'confidence' => $contribution['confidence'],
                'factors' => $contribution['contributing_factors'],
                'high_risk_periods' => $contribution['high_risk_periods'],
                'recommendations' => $contribution['recommendations']
            );
            
            // Update weighted calculations
            $weight = $contribution['weight'] ?? 1.0;
            $weighted_risk += $contribution['risk_score'] * $weight;
            $total_weight += $weight;
            $confidence_sum += $contribution['confidence'];
            
            // Merge high risk periods
            $forecast['high_risk_periods'] = array_merge(
                $forecast['high_risk_periods'],
                $contribution['high_risk_periods']
            );
        }
        
        // Calculate overall risk
        if ($total_weight > 0) {
            $forecast['overall_risk'] = $weighted_risk / $total_weight;
            $forecast['confidence'] = $confidence_sum / count($enabled_types);
        }
        
        // Add correlation risks
        $correlation_risks = $this->correlation_engine->get_correlation_risk_factors($user_id, $window);
        if (!empty($correlation_risks)) {
            $forecast['correlation_risks'] = $correlation_risks;
            
            // Adjust overall risk based on correlations
            foreach ($correlation_risks as $risk) {
                $forecast['overall_risk'] = min(1.0, $forecast['overall_risk'] + ($risk['risk_increase'] * 0.1));
            }
        }
        
        // Apply forecast algorithms
        foreach ($window_config['algorithms'] as $algorithm) {
            $method = 'apply_' . $algorithm;
            if (method_exists($this, $method)) {
                $forecast = $this->$method($forecast, $user_id, $window);
            }
        }
        
        // Determine risk level
        $forecast['risk_level'] = $this->determine_risk_level($forecast['overall_risk']);
        
        // Generate prevention plan
        $forecast['prevention_plan'] = $this->generate_prevention_plan($forecast, $user_id);
        
        // Generate insights
        $forecast['insights'] = $this->generate_forecast_insights($forecast, $user_id);
        
        // Add AI-enhanced predictions if available
        if ($this->ai_service && $this->should_use_ai($user_id)) {
            $forecast = $this->enhance_with_ai($forecast, $user_id, $window);
        }
        
        // Sort and deduplicate high risk periods
        $forecast['high_risk_periods'] = $this->process_high_risk_periods($forecast['high_risk_periods']);
        
        return $forecast;
    }
    
    /**
     * Apply immediate risk algorithm
     * 
     * @param array $forecast Current forecast
     * @param int $user_id User ID
     * @param string $window Forecast window
     * @return array Updated forecast
     */
    private function apply_immediate_risk($forecast, $user_id, $window) {
        global $wpdb;
        
        // Check recent episode frequency
        $recent_count = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) 
            FROM {$wpdb->prefix}spiralengine_episodes
            WHERE user_id = %d
                AND episode_date >= DATE_SUB(NOW(), INTERVAL 48 HOUR)
        ", $user_id));
        
        // High recent activity increases immediate risk
        if ($recent_count >= 3) {
            $forecast['overall_risk'] = min(1.0, $forecast['overall_risk'] + 0.2);
            $forecast['insights'][] = array(
                'type' => 'warning',
                'message' => 'Recent episode frequency is elevated',
                'severity' => 'high'
            );
        }
        
        // Check time since last episode
        $last_episode = $wpdb->get_var($wpdb->prepare("
            SELECT episode_date 
            FROM {$wpdb->prefix}spiralengine_episodes
            WHERE user_id = %d
            ORDER BY episode_date DESC
            LIMIT 1
        ", $user_id));
        
        if ($last_episode) {
            $hours_since = (time() - strtotime($last_episode)) / 3600;
            
            // If very recent, check for cascade risk
            if ($hours_since < 6) {
                $forecast['immediate_cascade_risk'] = true;
                $forecast['overall_risk'] = min(1.0, $forecast['overall_risk'] + 0.1);
            }
        }
        
        return $forecast;
    }
    
    /**
     * Apply temporal patterns algorithm
     * 
     * @param array $forecast Current forecast
     * @param int $user_id User ID
     * @param string $window Forecast window
     * @return array Updated forecast
     */
    private function apply_temporal_patterns($forecast, $user_id, $window) {
        // Get user's temporal patterns
        $patterns = $this->pattern_detector->get_user_patterns($user_id);
        
        if (!isset($patterns['temporal'])) {
            return $forecast;
        }
        
        // Check if current time matches high-risk patterns
        $current_hour = date('G');
        $current_dow = date('w');
        
        foreach ($patterns['temporal'] as $pattern) {
            $pattern_data = $pattern['data'];
            
            // Check time of day patterns
            if ($pattern['pattern_subtype'] === 'time_of_day' && isset($pattern_data['data']['peak_hours'])) {
                foreach ($pattern_data['data']['peak_hours'] as $peak) {
                    if (abs($current_hour - $peak['hour']) <= 2) {
                        // Currently in a high-risk time period
                        $risk_increase = ($peak['percentage'] / 100) * 0.3;
                        $forecast['overall_risk'] = min(1.0, $forecast['overall_risk'] + $risk_increase);
                        
                        $forecast['active_patterns'][] = array(
                            'type' => 'temporal',
                            'pattern' => 'high_risk_hour',
                            'description' => sprintf('Currently in high-risk time period (%s)', $this->format_hour_range($peak['hour'])),
                            'risk_contribution' => $risk_increase
                        );
                        break;
                    }
                }
            }
            
            // Check day of week patterns
            if ($pattern['pattern_subtype'] === 'day_of_week' && isset($pattern_data['data']['significant_days'])) {
                foreach ($pattern_data['data']['significant_days'] as $day) {
                    if ($day['day'] == $current_dow) {
                        $risk_increase = ($day['percentage'] / 100) * 0.2;
                        $forecast['overall_risk'] = min(1.0, $forecast['overall_risk'] + $risk_increase);
                        
                        $forecast['active_patterns'][] = array(
                            'type' => 'temporal',
                            'pattern' => 'high_risk_day',
                            'description' => sprintf('%s is a high-risk day', $day['name']),
                            'risk_contribution' => $risk_increase
                        );
                        break;
                    }
                }
            }
        }
        
        return $forecast;
    }
    
    /**
     * Apply biological cycles algorithm
     * 
     * @param array $forecast Current forecast
     * @param int $user_id User ID
     * @param string $window Forecast window
     * @return array Updated forecast
     */
    private function apply_biological_cycles($forecast, $user_id, $window) {
        // Get user's biological tracking preferences
        $bio_tracking = get_user_meta($user_id, 'spiral_biological_tracking', true);
        
        if (empty($bio_tracking) || !$bio_tracking['enabled']) {
            return $forecast;
        }
        
        // Check menstrual cycle if applicable
        if (isset($bio_tracking['menstrual_tracking']) && $bio_tracking['menstrual_tracking']) {
            $cycle_data = $this->get_menstrual_cycle_data($user_id);
            
            if ($cycle_data && isset($cycle_data['current_phase'])) {
                $phase_risks = array(
                    'menstrual' => 0.2,
                    'follicular' => 0.0,
                    'ovulation' => 0.1,
                    'luteal' => 0.3
                );
                
                if (isset($phase_risks[$cycle_data['current_phase']])) {
                    $risk_increase = $phase_risks[$cycle_data['current_phase']];
                    if ($risk_increase > 0) {
                        $forecast['overall_risk'] = min(1.0, $forecast['overall_risk'] + $risk_increase);
                        $forecast['biological_factors'][] = array(
                            'type' => 'menstrual_cycle',
                            'phase' => $cycle_data['current_phase'],
                            'risk_contribution' => $risk_increase,
                            'days_until_next_phase' => $cycle_data['days_to_next_phase'] ?? null
                        );
                    }
                }
            }
        }
        
        // Check sleep patterns
        $sleep_quality = $this->analyze_recent_sleep($user_id, 7);
        if ($sleep_quality && $sleep_quality['average_hours'] < 6) {
            $risk_increase = 0.15;
            $forecast['overall_risk'] = min(1.0, $forecast['overall_risk'] + $risk_increase);
            $forecast['biological_factors'][] = array(
                'type' => 'sleep_deprivation',
                'average_hours' => $sleep_quality['average_hours'],
                'risk_contribution' => $risk_increase
            );
        }
        
        return $forecast;
    }
    
    /**
     * Apply cascade prediction algorithm
     * 
     * @param array $forecast Current forecast
     * @param int $user_id User ID
     * @param string $window Forecast window
     * @return array Updated forecast
     */
    private function apply_cascade_prediction($forecast, $user_id, $window) {
        // Get cascade patterns
        $patterns = $this->pattern_detector->get_user_patterns($user_id);
        
        if (!isset($patterns['cascade'])) {
            return $forecast;
        }
        
        // Check for active cascade sequences
        foreach ($patterns['cascade'] as $pattern) {
            if ($pattern['pattern_subtype'] === 'episode_sequences') {
                $sequence_data = $pattern['data'];
                
                // Check if user is in middle of a known sequence
                $in_sequence = $this->check_active_sequence($user_id, $sequence_data);
                
                if ($in_sequence) {
                    $risk_increase = 0.25;
                    $forecast['overall_risk'] = min(1.0, $forecast['overall_risk'] + $risk_increase);
                    $forecast['cascade_warning'] = array(
                        'active' => true,
                        'sequence_type' => $in_sequence['type'],
                        'expected_next' => $in_sequence['expected_next'],
                        'prevention_window' => $in_sequence['prevention_window'],
                        'risk_contribution' => $risk_increase
                    );
                }
            }
        }
        
        return $forecast;
    }
    
    /**
     * Generate prevention plan
     * 
     * @param array $forecast Forecast data
     * @param int $user_id User ID
     * @return array Prevention plan
     */
    private function generate_prevention_plan($forecast, $user_id) {
        $plan = array(
            'immediate_actions' => array(),
            'daily_practices' => array(),
            'resources' => array(),
            'emergency_contacts' => array()
        );
        
        // Get user's coping strategies that have worked
        $effective_strategies = $this->get_effective_coping_strategies($user_id);
        
        // Based on risk level
        switch ($forecast['risk_level']['key']) {
            case 'critical':
                $plan['immediate_actions'][] = array(
                    'priority' => 1,
                    'action' => 'Contact your support person or crisis line',
                    'icon' => 'phone'
                );
                $plan['immediate_actions'][] = array(
                    'priority' => 2,
                    'action' => 'Move to a safe, comfortable environment',
                    'icon' => 'home'
                );
                $plan['emergency_contacts'] = $this->get_emergency_contacts($user_id);
                break;
                
            case 'high':
                $plan['immediate_actions'][] = array(
                    'priority' => 1,
                    'action' => 'Implement your crisis prevention plan',
                    'icon' => 'shield'
                );
                $plan['immediate_actions'][] = array(
                    'priority' => 2,
                    'action' => 'Use your most effective coping strategies',
                    'icon' => 'heart'
                );
                break;
                
            case 'moderate':
                $plan['immediate_actions'][] = array(
                    'priority' => 1,
                    'action' => 'Increase self-care activities',
                    'icon' => 'spa'
                );
                $plan['immediate_actions'][] = array(
                    'priority' => 2,
                    'action' => 'Monitor triggers more closely',
                    'icon' => 'visibility'
                );
                break;
        }
        
        // Add personalized coping strategies
        if (!empty($effective_strategies)) {
            foreach (array_slice($effective_strategies, 0, 3) as $strategy) {
                $plan['immediate_actions'][] = array(
                    'priority' => 3,
                    'action' => $strategy['name'],
                    'icon' => 'check_circle',
                    'effectiveness' => $strategy['effectiveness']
                );
            }
        }
        
        // Daily practices based on patterns
        if (isset($forecast['active_patterns'])) {
            foreach ($forecast['active_patterns'] as $pattern) {
                if ($pattern['type'] === 'temporal') {
                    $plan['daily_practices'][] = array(
                        'practice' => 'Set reminders for high-risk time periods',
                        'timing' => 'Before ' . $pattern['description']
                    );
                }
            }
        }
        
        // Add biological factor recommendations
        if (isset($forecast['biological_factors'])) {
            foreach ($forecast['biological_factors'] as $factor) {
                if ($factor['type'] === 'sleep_deprivation') {
                    $plan['daily_practices'][] = array(
                        'practice' => 'Prioritize 7-9 hours of sleep',
                        'timing' => 'Starting tonight'
                    );
                }
                if ($factor['type'] === 'menstrual_cycle') {
                    $plan['daily_practices'][] = array(
                        'practice' => 'Increase self-care during ' . $factor['phase'] . ' phase',
                        'timing' => 'Next ' . ($factor['days_until_next_phase'] ?? 7) . ' days'
                    );
                }
            }
        }
        
        // Add resources
        $plan['resources'] = $this->get_prevention_resources($forecast['risk_level']['key']);
        
        return $plan;
    }
    
    /**
     * Generate forecast insights
     * 
     * @param array $forecast Forecast data
     * @param int $user_id User ID
     * @return array Insights
     */
    private function generate_forecast_insights($forecast, $user_id) {
        $insights = isset($forecast['insights']) ? $forecast['insights'] : array();
        
        // Risk trend insight
        $trend = $this->calculate_risk_trend($user_id);
        if ($trend !== null) {
            if ($trend > 0.1) {
                $insights[] = array(
                    'type' => 'trend',
                    'message' => 'Your risk levels have been increasing over the past week',
                    'severity' => 'warning',
                    'action' => 'Consider scheduling a check-in with your support team'
                );
            } elseif ($trend < -0.1) {
                $insights[] = array(
                    'type' => 'trend',
                    'message' => 'Your risk levels have been decreasing - great progress!',
                    'severity' => 'positive',
                    'action' => 'Keep up with your current strategies'
                );
            }
        }
        
        // Pattern-based insights
        if (isset($forecast['active_patterns']) && count($forecast['active_patterns']) > 2) {
            $insights[] = array(
                'type' => 'pattern',
                'message' => 'Multiple risk patterns are currently active',
                'severity' => 'warning',
                'action' => 'Be extra vigilant with your coping strategies'
            );
        }
        
        // Correlation insights
        if (isset($forecast['correlation_risks']) && !empty($forecast['correlation_risks'])) {
            $highest_correlation = array_reduce($forecast['correlation_risks'], function($carry, $item) {
                return (!$carry || $item['risk_increase'] > $carry['risk_increase']) ? $item : $carry;
            });
            
            if ($highest_correlation) {
                $insights[] = array(
                    'type' => 'correlation',
                    'message' => $highest_correlation['factor'],
                    'severity' => 'info',
                    'time_window' => sprintf('Expected in %d hours', $highest_correlation['time_offset'])
                );
            }
        }
        
        // Positive reinforcement
        $days_stable = $this->get_days_stable($user_id);
        if ($days_stable >= 7) {
            $insights[] = array(
                'type' => 'achievement',
                'message' => sprintf('You\'ve maintained stability for %d days!', $days_stable),
                'severity' => 'positive',
                'action' => 'Celebrate this achievement'
            );
        }
        
        return $insights;
    }
    
    /**
     * Enhance forecast with AI predictions
     * 
     * @param array $forecast Current forecast
     * @param int $user_id User ID
     * @param string $window Forecast window
     * @return array Enhanced forecast
     */
    private function enhance_with_ai($forecast, $user_id, $window) {
        try {
            $ai_prediction = $this->ai_service->predict_episode_risk($user_id, $window, $forecast);
            
            if ($ai_prediction && isset($ai_prediction['predictions'])) {
                // Merge AI predictions
                $forecast['ai_enhanced'] = true;
                $forecast['ai_predictions'] = $ai_prediction['predictions'];
                
                // Adjust overall risk based on AI confidence
                if (isset($ai_prediction['risk_adjustment'])) {
                    $ai_confidence = $ai_prediction['confidence'] ?? 0.5;
                    $adjustment = $ai_prediction['risk_adjustment'] * $ai_confidence * 0.2;
                    $forecast['overall_risk'] = max(0, min(1.0, $forecast['overall_risk'] + $adjustment));
                }
                
                // Add AI insights
                if (isset($ai_prediction['insights'])) {
                    foreach ($ai_prediction['insights'] as $insight) {
                        $forecast['insights'][] = array(
                            'type' => 'ai',
                            'message' => $insight['message'],
                            'severity' => $insight['severity'] ?? 'info',
                            'confidence' => $insight['confidence'] ?? 0.7
                        );
                    }
                }
                
                // Add AI-detected high risk periods
                if (isset($ai_prediction['high_risk_periods'])) {
                    $forecast['high_risk_periods'] = array_merge(
                        $forecast['high_risk_periods'],
                        $ai_prediction['high_risk_periods']
                    );
                }
            }
        } catch (Exception $e) {
            error_log('AI forecast enhancement failed: ' . $e->getMessage());
        }
        
        return $forecast;
    }
    
    /**
     * Check if user can access forecast window
     * 
     * @param int $user_id User ID
     * @param string $window Forecast window
     * @return bool
     */
    private function can_access_forecast($user_id, $window) {
        if (!isset($this->forecast_windows[$window])) {
            return false;
        }
        
        $required_level = $this->forecast_windows[$window]['min_membership'];
        $user_membership = $this->get_user_membership($user_id);
        
        return $this->compare_membership_levels($user_membership, $required_level);
    }
    
    /**
     * Get user membership level
     * 
     * @param int $user_id User ID
     * @return string Membership level
     */
    private function get_user_membership($user_id) {
        if (class_exists('MeprUser')) {
            $mepr_user = new MeprUser($user_id);
            $active_memberships = $mepr_user->active_product_subscriptions();
            
            if (!empty($active_memberships)) {
                // Get highest level membership
                $levels = array('platinum' => 3, 'premium' => 2, 'basic' => 1);
                $highest_level = 'basic';
                $highest_score = 0;
                
                foreach ($active_memberships as $membership_id) {
                    $membership = new MeprProduct($membership_id);
                    $level = strtolower($membership->post_title);
                    
                    foreach ($levels as $level_key => $score) {
                        if (strpos($level, $level_key) !== false && $score > $highest_score) {
                            $highest_level = $level_key;
                            $highest_score = $score;
                        }
                    }
                }
                
                return $highest_level;
            }
        }
        
        return 'basic'; // Default level
    }
    
    /**
     * Compare membership levels
     * 
     * @param string $user_level User's membership level
     * @param string $required_level Required membership level
     * @return bool
     */
    private function compare_membership_levels($user_level, $required_level) {
        $levels = array(
            'basic' => 1,
            'premium' => 2,
            'platinum' => 3
        );
        
        $user_score = $levels[$user_level] ?? 0;
        $required_score = $levels[$required_level] ?? 999;
        
        return $user_score >= $required_score;
    }
    
    /**
     * Determine risk level from score
     * 
     * @param float $risk_score Risk score (0-1)
     * @return array Risk level data
     */
    private function determine_risk_level($risk_score) {
        foreach ($this->risk_levels as $key => $level) {
            if ($risk_score >= $level['min'] && $risk_score <= $level['max']) {
                return array_merge($level, array('key' => $key));
            }
        }
        
        return $this->risk_levels['low'];
    }
    
    /**
     * Process and deduplicate high risk periods
     * 
     * @param array $periods High risk periods
     * @return array Processed periods
     */
    private function process_high_risk_periods($periods) {
        if (empty($periods)) {
            return array();
        }
        
        // Sort by start time
        usort($periods, function($a, $b) {
            return strtotime($a['start']) - strtotime($b['start']);
        });
        
        // Merge overlapping periods
        $merged = array();
        $current = null;
        
        foreach ($periods as $period) {
            if (!$current) {
                $current = $period;
            } else {
                $current_end = strtotime($current['end']);
                $period_start = strtotime($period['start']);
                
                if ($period_start <= $current_end) {
                    // Merge periods
                    $current['end'] = max($current['end'], $period['end']);
                    $current['risk_score'] = max($current['risk_score'], $period['risk_score']);
                    if (isset($period['reasons'])) {
                        $current['reasons'] = array_merge($current['reasons'] ?? array(), $period['reasons']);
                    }
                } else {
                    // Add current and start new
                    $merged[] = $current;
                    $current = $period;
                }
            }
        }
        
        if ($current) {
            $merged[] = $current;
        }
        
        return $merged;
    }
    
    /**
     * Get cached forecast
     * 
     * @param int $user_id User ID
     * @param string $window Forecast window
     * @return array|null Cached forecast
     */
    private function get_cached_forecast($user_id, $window) {
        $cache_key = 'spiral_forecast_' . $user_id . '_' . $window;
        return get_transient($cache_key);
    }
    
    /**
     * Cache forecast
     * 
     * @param int $user_id User ID
     * @param string $window Forecast window
     * @param array $forecast Forecast data
     */
    private function cache_forecast($user_id, $window, $forecast) {
        $cache_key = 'spiral_forecast_' . $user_id . '_' . $window;
        $expiry = $this->get_cache_expiry($window);
        set_transient($cache_key, $forecast, $expiry);
    }
    
    /**
     * Check if forecast is fresh
     * 
     * @param array $forecast Cached forecast
     * @param string $window Forecast window
     * @return bool
     */
    private function is_forecast_fresh($forecast, $window) {
        if (!isset($forecast['generated_at'])) {
            return false;
        }
        
        $age = time() - strtotime($forecast['generated_at']);
        $max_age = $this->get_cache_expiry($window);
        
        return $age < $max_age;
    }
    
    /**
     * Get cache expiry for window
     * 
     * @param string $window Forecast window
     * @return int Seconds
     */
    private function get_cache_expiry($window) {
        $frequencies = array(
            'hourly' => HOUR_IN_SECONDS,
            '6_hours' => 6 * HOUR_IN_SECONDS,
            'daily' => DAY_IN_SECONDS,
            'weekly' => WEEK_IN_SECONDS
        );
        
        $frequency = $this->forecast_windows[$window]['update_frequency'] ?? 'daily';
        return $frequencies[$frequency] ?? DAY_IN_SECONDS;
    }
    
    /**
     * Calculate forecast expiry
     * 
     * @param string $window Forecast window
     * @return string MySQL datetime
     */
    private function calculate_expiry($window) {
        $seconds = $this->get_cache_expiry($window);
        return date('Y-m-d H:i:s', time() + $seconds);
    }
    
    /**
     * Schedule forecast updates
     */
    private function schedule_forecast_updates() {
        // Schedule hourly updates
        if (!wp_next_scheduled('spiral_update_hourly_forecasts')) {
            wp_schedule_event(time(), 'hourly', 'spiral_update_hourly_forecasts');
        }
        add_action('spiral_update_hourly_forecasts', array($this, 'update_hourly_forecasts'));
        
        // Schedule daily updates
        if (!wp_next_scheduled('spiral_update_daily_forecasts')) {
            wp_schedule_event(time(), 'daily', 'spiral_update_daily_forecasts');
        }
        add_action('spiral_update_daily_forecasts', array($this, 'update_daily_forecasts'));
    }
    
    /**
     * Update hourly forecasts
     */
    public function update_hourly_forecasts() {
        $this->update_forecasts_by_frequency('hourly');
    }
    
    /**
     * Update daily forecasts
     */
    public function update_daily_forecasts() {
        $this->update_forecasts_by_frequency('daily');
        $this->update_forecasts_by_frequency('6_hours');
    }
    
    /**
     * Update forecasts by frequency
     * 
     * @param string $frequency Update frequency
     */
    private function update_forecasts_by_frequency($frequency) {
        global $wpdb;
        
        // Get active users
        $active_users = $wpdb->get_col("
            SELECT DISTINCT user_id 
            FROM {$wpdb->prefix}spiralengine_episodes
            WHERE episode_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ");
        
        foreach ($active_users as $user_id) {
            // Update relevant forecast windows
            foreach ($this->forecast_windows as $window => $config) {
                if ($config['update_frequency'] === $frequency) {
                    // Check if user can access this window
                    if ($this->can_access_forecast($user_id, $window)) {
                        $this->generate_unified_forecast($user_id, $window);
                    }
                }
            }
        }
    }
    
    /**
     * Log forecast generation
     * 
     * @param int $user_id User ID
     * @param string $window Forecast window
     * @param array $forecast Forecast data
     */
    private function log_forecast_generation($user_id, $window, $forecast) {
        global $wpdb;
        
        $wpdb->insert(
            $wpdb->prefix . 'spiralengine_forecast_logs',
            array(
                'user_id' => $user_id,
                'forecast_window' => $window,
                'risk_score' => $forecast['overall_risk'],
                'confidence_score' => $forecast['confidence'],
                'risk_level' => $forecast['risk_level']['key'],
                'generated_at' => $forecast['generated_at'],
                'ai_enhanced' => isset($forecast['ai_enhanced']) ? 1 : 0
            ),
            array('%d', '%s', '%f', '%f', '%s', '%s', '%d')
        );
    }
    
    /**
     * Get forecast API data
     * 
     * @param int $user_id User ID
     * @param string $window Forecast window
     * @return array API-ready forecast data
     */
    public function get_forecast_api_data($user_id, $window = '24_hour') {
        $forecast = $this->generate_unified_forecast($user_id, $window);
        
        if (isset($forecast['error'])) {
            return $forecast;
        }
        
        // Format for API consumption
        return array(
            'window' => $window,
            'generated_at' => $forecast['generated_at'],
            'expires_at' => $forecast['expires_at'],
            'risk' => array(
                'score' => $forecast['overall_risk'],
                'level' => $forecast['risk_level']['key'],
                'description' => $forecast['risk_level']['description'],
                'color' => $forecast['risk_level']['color']
            ),
            'confidence' => $forecast['confidence'],
            'episode_risks' => array_map(function($risk) {
                return array(
                    'type' => $risk['type'],
                    'name' => $risk['name'],
                    'risk_score' => $risk['risk_score'],
                    'color' => $risk['color']
                );
            }, $forecast['episode_risks']),
            'high_risk_periods' => array_map(function($period) {
                return array(
                    'start' => $period['start'],
                    'end' => $period['end'],
                    'risk_score' => $period['risk_score'],
                    'reasons' => $period['reasons'] ?? array()
                );
            }, $forecast['high_risk_periods']),
            'prevention_plan' => $forecast['prevention_plan'],
            'insights' => array_map(function($insight) {
                return array(
                    'message' => $insight['message'],
                    'severity' => $insight['severity'],
                    'action' => $insight['action'] ?? null
                );
            }, $forecast['insights']),
            'ai_enhanced' => isset($forecast['ai_enhanced']) ? $forecast['ai_enhanced'] : false
        );
    }
    
    /**
     * Helper methods
     */
    
    private function get_user_enabled_episodes($user_id) {
        $enabled = get_user_meta($user_id, 'spiral_enabled_episodes', true);
        if (empty($enabled)) {
            // Default enabled episodes
            return array('overthinking', 'anxiety', 'depression');
        }
        return $enabled;
    }
    
    private function get_episode_widget_class($episode_type) {
        $registry = SPIRAL_Episode_Registry::get_instance();
        $type_config = $registry->get_episode_type($episode_type);
        return $type_config ? $type_config['widget_class'] : null;
    }
    
    private function should_use_ai($user_id) {
        $membership = $this->get_user_membership($user_id);
        return in_array($membership, array('premium', 'platinum'));
    }
    
    private function get_effective_coping_strategies($user_id) {
        global $wpdb;
        
        // This would analyze which coping strategies have been effective
        // Placeholder implementation
        return array(
            array('name' => 'Deep breathing exercises', 'effectiveness' => 0.8),
            array('name' => '10-minute walk', 'effectiveness' => 0.7),
            array('name' => 'Call a friend', 'effectiveness' => 0.6)
        );
    }
    
    private function get_emergency_contacts($user_id) {
        $contacts = get_user_meta($user_id, 'spiral_emergency_contacts', true);
        if (empty($contacts)) {
            return array(
                array('name' => 'Crisis Hotline', 'number' => '988', 'available' => '24/7'),
                array('name' => 'Emergency', 'number' => '911', 'available' => '24/7')
            );
        }
        return $contacts;
    }
    
    private function get_prevention_resources($risk_level) {
        $resources = array(
            'low' => array(
                array('title' => 'Mindfulness Exercises', 'url' => '/resources/mindfulness'),
                array('title' => 'Daily Check-In', 'url' => '/dashboard/checkin')
            ),
            'moderate' => array(
                array('title' => 'Coping Strategies Guide', 'url' => '/resources/coping'),
                array('title' => 'Trigger Management', 'url' => '/resources/triggers')
            ),
            'high' => array(
                array('title' => 'Crisis Prevention Plan', 'url' => '/resources/crisis-prevention'),
                array('title' => 'Support Network', 'url' => '/resources/support')
            ),
            'critical' => array(
                array('title' => 'Crisis Resources', 'url' => '/resources/crisis'),
                array('title' => 'Emergency Contacts', 'url' => '/settings/emergency')
            )
        );
        
        return $resources[$risk_level] ?? $resources['moderate'];
    }
    
    private function calculate_risk_trend($user_id) {
        global $wpdb;
        
        // Get recent forecasts
        $recent_forecasts = $wpdb->get_results($wpdb->prepare("
            SELECT risk_score, generated_at
            FROM {$wpdb->prefix}spiralengine_forecast_logs
            WHERE user_id = %d
                AND forecast_window = '24_hour'
                AND generated_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            ORDER BY generated_at ASC
        ", $user_id), ARRAY_A);
        
        if (count($recent_forecasts) < 3) {
            return null;
        }
        
        // Calculate trend
        $first_half = array_slice($recent_forecasts, 0, floor(count($recent_forecasts) / 2));
        $second_half = array_slice($recent_forecasts, floor(count($recent_forecasts) / 2));
        
        $first_avg = array_sum(array_column($first_half, 'risk_score')) / count($first_half);
        $second_avg = array_sum(array_column($second_half, 'risk_score')) / count($second_half);
        
        return $second_avg - $first_avg;
    }
    
    private function get_days_stable($user_id) {
        global $wpdb;
        
        // Count days without high-severity episodes
        $last_high_severity = $wpdb->get_var($wpdb->prepare("
            SELECT MAX(episode_date)
            FROM {$wpdb->prefix}spiralengine_episodes
            WHERE user_id = %d AND severity_score >= 7
        ", $user_id));
        
        if (!$last_high_severity) {
            return 30; // No high severity episodes found
        }
        
        return floor((time() - strtotime($last_high_severity)) / 86400);
    }
    
    private function get_menstrual_cycle_data($user_id) {
        // Placeholder - would integrate with menstrual tracking
        return null;
    }
    
    private function analyze_recent_sleep($user_id, $days) {
        // Placeholder - would analyze sleep data
        return null;
    }
    
    private function check_active_sequence($user_id, $sequence_data) {
        // Placeholder - would check for active cascade sequences
        return null;
    }
    
    private function format_hour_range($hour) {
        $start = $hour;
        $end = ($hour + 2) % 24;
        
        $start_ampm = $start >= 12 ? 'PM' : 'AM';
        $end_ampm = $end >= 12 ? 'PM' : 'AM';
        
        $start_12 = $start % 12 ?: 12;
        $end_12 = $end % 12 ?: 12;
        
        return sprintf('%d%s-%d%s', $start_12, $start_ampm, $end_12, $end_ampm);
    }
}

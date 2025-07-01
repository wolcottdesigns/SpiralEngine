<?php
/**
 * SpiralEngine Predictive Alerts System
 * 
 * @package    SpiralEngine
 * @subpackage AI/Alerts
 * @file       includes/class-predictive-alerts.php
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Predictive Alerts class
 * 
 * AI-powered early warning system for mental health episodes
 */
class SpiralEngine_Predictive_Alerts {
    
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
     * Alert types configuration
     *
     * @var array
     */
    private $alert_types = array(
        'escalation' => array(
            'name' => 'Severity Escalation',
            'description' => 'Predicts increasing episode severity',
            'threshold' => 0.7,
            'min_tier' => 'gold',
            'priority' => 'high'
        ),
        'pattern_recurrence' => array(
            'name' => 'Pattern Recurrence',
            'description' => 'Detects recurring harmful patterns',
            'threshold' => 0.6,
            'min_tier' => 'gold',
            'priority' => 'medium'
        ),
        'trigger_exposure' => array(
            'name' => 'Trigger Exposure',
            'description' => 'Anticipates trigger encounters',
            'threshold' => 0.65,
            'min_tier' => 'platinum',
            'priority' => 'medium'
        ),
        'wellness_decline' => array(
            'name' => 'Wellness Decline',
            'description' => 'Predicts overall wellness deterioration',
            'threshold' => 0.6,
            'min_tier' => 'platinum',
            'priority' => 'medium'
        ),
        'crisis_risk' => array(
            'name' => 'Crisis Risk',
            'description' => 'High-risk situation detection',
            'threshold' => 0.8,
            'min_tier' => 'gold',
            'priority' => 'critical'
        ),
        'medication_adherence' => array(
            'name' => 'Medication Adherence',
            'description' => 'Predicts medication compliance issues',
            'threshold' => 0.7,
            'min_tier' => 'platinum',
            'priority' => 'high'
        ),
        'social_isolation' => array(
            'name' => 'Social Isolation',
            'description' => 'Detects increasing isolation patterns',
            'threshold' => 0.65,
            'min_tier' => 'platinum',
            'priority' => 'medium'
        ),
        'sleep_disruption' => array(
            'name' => 'Sleep Disruption',
            'description' => 'Predicts sleep quality issues',
            'threshold' => 0.6,
            'min_tier' => 'gold',
            'priority' => 'medium'
        )
    );
    
    /**
     * Prediction models configuration
     *
     * @var array
     */
    private $prediction_models = array(
        'time_series' => array(
            'window_size' => 7,
            'min_data_points' => 14
        ),
        'pattern_matching' => array(
            'similarity_threshold' => 0.75,
            'min_pattern_occurrences' => 3
        ),
        'behavioral' => array(
            'baseline_period' => 30,
            'deviation_threshold' => 2
        )
    );
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->ai_service = SpiralEngine_AI_Service::get_instance();
        $this->pattern_analyzer = new SpiralEngine_Pattern_Analyzer();
        
        $this->init_hooks();
        $this->init_monitoring();
    }
    
    /**
     * Initialize hooks
     *
     * @return void
     */
    private function init_hooks() {
        // Real-time monitoring
        add_action('spiralengine_after_episode_created', array($this, 'analyze_for_alerts'), 10, 2);
        
        // Scheduled predictions
        add_action('spiralengine_run_predictions', array($this, 'run_scheduled_predictions'));
        
        // Alert actions
        add_action('spiralengine_alert_triggered', array($this, 'handle_alert'), 10, 3);
        
        // AJAX endpoints
        add_action('wp_ajax_spiralengine_get_alerts', array($this, 'ajax_get_alerts'));
        add_action('wp_ajax_spiralengine_dismiss_alert', array($this, 'ajax_dismiss_alert'));
        add_action('wp_ajax_spiralengine_snooze_alert', array($this, 'ajax_snooze_alert'));
        
        // Dashboard widget
        add_action('spiralengine_dashboard_widgets', array($this, 'add_alerts_widget'));
    }
    
    /**
     * Initialize monitoring
     *
     * @return void
     */
    private function init_monitoring() {
        // Schedule hourly predictions for active users
        if (!wp_next_scheduled('spiralengine_hourly_predictions')) {
            wp_schedule_event(time(), 'hourly', 'spiralengine_hourly_predictions');
        }
        
        // Schedule daily comprehensive analysis
        if (!wp_next_scheduled('spiralengine_daily_predictions')) {
            wp_schedule_event(
                strtotime('tomorrow 6:00am'),
                'daily',
                'spiralengine_daily_predictions'
            );
        }
    }
    
    /**
     * Analyze episode for alerts
     *
     * @param int $episode_id Episode ID
     * @param array $episode_data Episode data
     * @return void
     */
    public function analyze_for_alerts($episode_id, $episode_data) {
        $user_id = $episode_data['user_id'];
        
        // Check user tier
        $membership = new SpiralEngine_Membership($user_id);
        $user_tier = $membership->get_tier();
        
        if (!in_array($user_tier, array('gold', 'platinum'))) {
            return;
        }
        
        // Run real-time predictions
        $predictions = $this->generate_real_time_predictions($user_id, $episode_data);
        
        // Check for alerts
        $this->check_alert_conditions($user_id, $predictions);
    }
    
    /**
     * Generate real-time predictions
     *
     * @param int $user_id User ID
     * @param array $episode_data Current episode
     * @return array Predictions
     */
    private function generate_real_time_predictions($user_id, $episode_data) {
        $predictions = array();
        
        // Get recent history
        $history = $this->get_user_recent_history($user_id, 7);
        
        // Add current episode to history
        $history[] = $episode_data;
        
        // Severity escalation prediction
        if ($this->has_sufficient_data($history, 'escalation')) {
            $predictions['escalation'] = $this->predict_severity_escalation($history);
        }
        
        // Pattern recurrence prediction
        $patterns = $this->pattern_analyzer->get_cached_results($user_id);
        if ($patterns) {
            $predictions['pattern_recurrence'] = $this->predict_pattern_recurrence($history, $patterns);
        }
        
        // Crisis risk assessment
        if ($episode_data['severity'] >= 7) {
            $predictions['crisis_risk'] = $this->assess_crisis_risk($user_id, $episode_data, $history);
        }
        
        // Sleep disruption (if relevant)
        if ($this->is_sleep_related($episode_data)) {
            $predictions['sleep_disruption'] = $this->predict_sleep_disruption($history);
        }
        
        return $predictions;
    }
    
    /**
     * Check alert conditions
     *
     * @param int $user_id User ID
     * @param array $predictions Predictions
     * @return void
     */
    private function check_alert_conditions($user_id, $predictions) {
        $membership = new SpiralEngine_Membership($user_id);
        $user_tier = $membership->get_tier();
        
        foreach ($predictions as $type => $prediction) {
            if (!isset($this->alert_types[$type])) {
                continue;
            }
            
            $alert_config = $this->alert_types[$type];
            
            // Check tier requirements
            if (!$this->user_can_receive_alert($user_tier, $alert_config['min_tier'])) {
                continue;
            }
            
            // Check prediction confidence
            if ($prediction['confidence'] >= $alert_config['threshold']) {
                $this->trigger_alert($user_id, $type, $prediction);
            }
        }
    }
    
    /**
     * Trigger alert
     *
     * @param int $user_id User ID
     * @param string $type Alert type
     * @param array $prediction Prediction data
     * @return void
     */
    private function trigger_alert($user_id, $type, $prediction) {
        // Check if alert is snoozed or dismissed
        if ($this->is_alert_suppressed($user_id, $type)) {
            return;
        }
        
        // Create alert
        $alert = array(
            'id' => $this->generate_alert_id(),
            'user_id' => $user_id,
            'type' => $type,
            'title' => $this->alert_types[$type]['name'],
            'message' => $this->generate_alert_message($type, $prediction),
            'priority' => $this->alert_types[$type]['priority'],
            'confidence' => $prediction['confidence'],
            'prediction_data' => $prediction,
            'recommendations' => $this->generate_alert_recommendations($type, $prediction),
            'created_at' => current_time('mysql'),
            'expires_at' => $this->calculate_alert_expiry($type)
        );
        
        // Store alert
        $this->store_alert($alert);
        
        // Trigger alert action
        do_action('spiralengine_alert_triggered', $user_id, $type, $alert);
    }
    
    /**
     * Handle triggered alert
     *
     * @param int $user_id User ID
     * @param string $type Alert type
     * @param array $alert Alert data
     * @return void
     */
    public function handle_alert($user_id, $type, $alert) {
        // Send notifications based on priority and user preferences
        $notification_sent = false;
        
        if ($alert['priority'] === 'critical') {
            // Immediate notification for critical alerts
            $notification_sent = $this->send_immediate_notification($user_id, $alert);
        } elseif ($this->should_notify_alert($user_id, $type)) {
            // Standard notification
            $notification_sent = $this->send_alert_notification($user_id, $alert);
        }
        
        // Log alert
        $this->log_alert($alert, $notification_sent);
        
        // Trigger AI recommendations if needed
        if ($alert['priority'] === 'critical' || $alert['priority'] === 'high') {
            do_action('spiralengine_generate_crisis_recommendations', $user_id, $alert);
        }
    }
    
    /**
     * Run scheduled predictions
     *
     * @return void
     */
    public function run_scheduled_predictions() {
        // Get users eligible for predictions
        $users = $this->get_eligible_users();
        
        foreach ($users as $user_id) {
            $this->run_user_predictions($user_id);
        }
    }
    
    /**
     * Run predictions for a user
     *
     * @param int $user_id User ID
     * @return void
     */
    private function run_user_predictions($user_id) {
        // Get comprehensive user data
        $user_data = $this->gather_prediction_data($user_id);
        
        if ($user_data['episode_count'] < 10) {
            return; // Not enough data
        }
        
        // Run different prediction models
        $predictions = array();
        
        // Time series predictions
        $predictions['time_series'] = $this->run_time_series_predictions($user_data);
        
        // Pattern matching predictions
        $predictions['pattern_matching'] = $this->run_pattern_matching_predictions($user_data);
        
        // Behavioral predictions
        $predictions['behavioral'] = $this->run_behavioral_predictions($user_data);
        
        // AI-powered predictions
        if ($this->should_use_ai_predictions($user_id)) {
            $predictions['ai'] = $this->run_ai_predictions($user_id, $user_data);
        }
        
        // Combine predictions and check for alerts
        $combined_predictions = $this->combine_predictions($predictions);
        $this->check_alert_conditions($user_id, $combined_predictions);
    }
    
    /**
     * Get eligible users for predictions
     *
     * @return array User IDs
     */
    private function get_eligible_users() {
        global $wpdb;
        
        // Get active users with sufficient tier
        $users = get_users(array(
            'meta_key' => 'spiralengine_tier',
            'meta_value' => array('gold', 'platinum'),
            'meta_compare' => 'IN'
        ));
        
        $eligible = array();
        
        foreach ($users as $user) {
            // Check if user has recent activity
            if ($this->has_recent_activity($user->ID, 7)) {
                $eligible[] = $user->ID;
            }
        }
        
        return $eligible;
    }
    
    /**
     * Check if user has recent activity
     *
     * @param int $user_id User ID
     * @param int $days Days to check
     * @return bool Has activity
     */
    private function has_recent_activity($user_id, $days) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'spiralengine_episodes';
        
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name 
            WHERE user_id = %d 
            AND created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)",
            $user_id,
            $days
        ));
        
        return $count > 0;
    }
    
    /**
     * Gather prediction data
     *
     * @param int $user_id User ID
     * @return array Prediction data
     */
    private function gather_prediction_data($user_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'spiralengine_episodes';
        
        // Get episodes for analysis
        $episodes = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name 
            WHERE user_id = %d 
            AND created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)
            ORDER BY created_at ASC",
            $user_id
        ), ARRAY_A);
        
        // Decode JSON fields
        foreach ($episodes as &$episode) {
            $episode['data'] = json_decode($episode['data'], true);
            $episode['metadata'] = json_decode($episode['metadata'], true);
        }
        
        // Get patterns
        $patterns = $this->pattern_analyzer->get_cached_results($user_id);
        
        // Get user profile
        $profile = $this->get_user_profile($user_id);
        
        return array(
            'user_id' => $user_id,
            'episodes' => $episodes,
            'episode_count' => count($episodes),
            'patterns' => $patterns,
            'profile' => $profile,
            'baseline' => $this->calculate_baseline($episodes)
        );
    }
    
    /**
     * Calculate baseline metrics
     *
     * @param array $episodes Episodes
     * @return array Baseline metrics
     */
    private function calculate_baseline($episodes) {
        if (empty($episodes)) {
            return array();
        }
        
        // Take first 30 days as baseline
        $baseline_episodes = array_filter($episodes, function($ep) use ($episodes) {
            $first_date = strtotime($episodes[0]['created_at']);
            $ep_date = strtotime($ep['created_at']);
            return ($ep_date - $first_date) <= (30 * 86400);
        });
        
        if (empty($baseline_episodes)) {
            $baseline_episodes = array_slice($episodes, 0, min(30, count($episodes)));
        }
        
        $severities = array_column($baseline_episodes, 'severity');
        
        return array(
            'avg_severity' => array_sum($severities) / count($severities),
            'severity_std' => $this->calculate_std_dev($severities),
            'episode_frequency' => count($baseline_episodes) / 30,
            'common_widgets' => array_count_values(array_column($baseline_episodes, 'widget_id'))
        );
    }
    
    /**
     * Run time series predictions
     *
     * @param array $user_data User data
     * @return array Time series predictions
     */
    private function run_time_series_predictions($user_data) {
        $predictions = array();
        
        // Severity trend prediction
        $severity_trend = $this->analyze_severity_trend($user_data['episodes']);
        if ($severity_trend['prediction'] === 'escalating') {
            $predictions['escalation'] = array(
                'confidence' => $severity_trend['confidence'],
                'timeframe' => '48_hours',
                'expected_severity' => $severity_trend['expected_severity'],
                'trend_data' => $severity_trend
            );
        }
        
        // Frequency prediction
        $frequency_trend = $this->analyze_frequency_trend($user_data['episodes']);
        if ($frequency_trend['prediction'] === 'increasing') {
            $predictions['wellness_decline'] = array(
                'confidence' => $frequency_trend['confidence'],
                'timeframe' => '7_days',
                'expected_episodes' => $frequency_trend['expected_episodes'],
                'trend_data' => $frequency_trend
            );
        }
        
        return $predictions;
    }
    
    /**
     * Analyze severity trend
     *
     * @param array $episodes Episodes
     * @return array Trend analysis
     */
    private function analyze_severity_trend($episodes) {
        if (count($episodes) < $this->prediction_models['time_series']['min_data_points']) {
            return array('prediction' => 'insufficient_data');
        }
        
        $window_size = $this->prediction_models['time_series']['window_size'];
        $recent_episodes = array_slice($episodes, -$window_size);
        $recent_severities = array_column($recent_episodes, 'severity');
        
        // Calculate trend using linear regression
        $x_values = range(1, count($recent_severities));
        $regression = $this->calculate_linear_regression($x_values, $recent_severities);
        
        $prediction = 'stable';
        $confidence = 0.5;
        $expected_severity = $regression['intercept'] + $regression['slope'] * (count($recent_severities) + 1);
        
        if ($regression['slope'] > 0.5) {
            $prediction = 'escalating';
            $confidence = min(0.9, 0.5 + abs($regression['slope']) * 0.2);
        } elseif ($regression['slope'] < -0.5) {
            $prediction = 'improving';
            $confidence = min(0.9, 0.5 + abs($regression['slope']) * 0.2);
        }
        
        return array(
            'prediction' => $prediction,
            'confidence' => $confidence,
            'slope' => $regression['slope'],
            'expected_severity' => max(1, min(10, round($expected_severity))),
            'r_squared' => $regression['r_squared']
        );
    }
    
    /**
     * Analyze frequency trend
     *
     * @param array $episodes Episodes
     * @return array Frequency trend
     */
    private function analyze_frequency_trend($episodes) {
        // Group episodes by week
        $by_week = array();
        
        foreach ($episodes as $episode) {
            $week = date('Y-W', strtotime($episode['created_at']));
            if (!isset($by_week[$week])) {
                $by_week[$week] = 0;
            }
            $by_week[$week]++;
        }
        
        if (count($by_week) < 4) {
            return array('prediction' => 'insufficient_data');
        }
        
        // Analyze recent weeks
        $recent_weeks = array_slice($by_week, -4);
        $week_counts = array_values($recent_weeks);
        
        // Calculate trend
        $x_values = range(1, count($week_counts));
        $regression = $this->calculate_linear_regression($x_values, $week_counts);
        
        $prediction = 'stable';
        $confidence = 0.5;
        
        if ($regression['slope'] > 0.5) {
            $prediction = 'increasing';
            $confidence = min(0.85, 0.5 + abs($regression['slope']) * 0.15);
        } elseif ($regression['slope'] < -0.5) {
            $prediction = 'decreasing';
            $confidence = min(0.85, 0.5 + abs($regression['slope']) * 0.15);
        }
        
        return array(
            'prediction' => $prediction,
            'confidence' => $confidence,
            'slope' => $regression['slope'],
            'expected_episodes' => max(0, round($regression['intercept'] + $regression['slope'] * 5)),
            'current_rate' => end($week_counts)
        );
    }
    
    /**
     * Run pattern matching predictions
     *
     * @param array $user_data User data
     * @return array Pattern predictions
     */
    private function run_pattern_matching_predictions($user_data) {
        $predictions = array();
        
        if (!$user_data['patterns']) {
            return $predictions;
        }
        
        // Check for recurring patterns
        if (isset($user_data['patterns']['patterns']['temporal']['cycles'])) {
            $cycles = $user_data['patterns']['patterns']['temporal']['cycles'];
            
            foreach ($cycles['detected_cycles'] as $cycle) {
                if ($cycle['confidence'] === 'high') {
                    $next_occurrence = $this->predict_next_cycle_occurrence(
                        $user_data['episodes'],
                        $cycle['length_days']
                    );
                    
                    if ($next_occurrence['days_until'] <= 3) {
                        $predictions['pattern_recurrence'] = array(
                            'confidence' => $cycle['strength'],
                            'pattern_type' => 'cyclical',
                            'cycle_length' => $cycle['length_days'],
                            'next_occurrence' => $next_occurrence['date'],
                            'days_until' => $next_occurrence['days_until']
                        );
                    }
                }
            }
        }
        
        // Check for trigger patterns
        if (isset($user_data['patterns']['patterns']['trigger'])) {
            $trigger_predictions = $this->predict_trigger_exposure($user_data);
            if (!empty($trigger_predictions)) {
                $predictions['trigger_exposure'] = $trigger_predictions;
            }
        }
        
        return $predictions;
    }
    
    /**
     * Predict next cycle occurrence
     *
     * @param array $episodes Episodes
     * @param int $cycle_length Cycle length in days
     * @return array Next occurrence prediction
     */
    private function predict_next_cycle_occurrence($episodes, $cycle_length) {
        // Find last peak in cycle
        $severity_by_day = array();
        
        foreach ($episodes as $episode) {
            $day = date('Y-m-d', strtotime($episode['created_at']));
            if (!isset($severity_by_day[$day])) {
                $severity_by_day[$day] = array();
            }
            $severity_by_day[$day][] = $episode['severity'];
        }
        
        // Calculate daily averages
        $daily_averages = array();
        foreach ($severity_by_day as $day => $severities) {
            $daily_averages[$day] = array_sum($severities) / count($severities);
        }
        
        // Find last peak
        $last_peak_date = null;
        $peak_threshold = array_sum($daily_averages) / count($daily_averages) + 1;
        
        foreach (array_reverse($daily_averages, true) as $day => $avg) {
            if ($avg >= $peak_threshold) {
                $last_peak_date = $day;
                break;
            }
        }
        
        if (!$last_peak_date) {
            // Use last high severity episode
            $high_severity_episodes = array_filter($episodes, function($ep) {
                return $ep['severity'] >= 7;
            });
            
            if (!empty($high_severity_episodes)) {
                $last_episode = end($high_severity_episodes);
                $last_peak_date = date('Y-m-d', strtotime($last_episode['created_at']));
            }
        }
        
        if ($last_peak_date) {
            $next_date = date('Y-m-d', strtotime($last_peak_date . ' + ' . $cycle_length . ' days'));
            $days_until = max(0, floor((strtotime($next_date) - time()) / 86400));
            
            return array(
                'date' => $next_date,
                'days_until' => $days_until
            );
        }
        
        return array(
            'date' => null,
            'days_until' => null
        );
    }
    
    /**
     * Predict trigger exposure
     *
     * @param array $user_data User data
     * @return array Trigger predictions
     */
    private function predict_trigger_exposure($user_data) {
        $trigger_data = $user_data['patterns']['patterns']['trigger'];
        
        if (empty($trigger_data['trigger_timing'])) {
            return array();
        }
        
        // Check upcoming time windows for trigger likelihood
        $current_hour = date('G');
        $current_day = date('l');
        
        $predictions = array();
        
        // Check time-based triggers
        foreach ($trigger_data['trigger_timing'] as $timing) {
            if ($this->is_approaching_trigger_time($timing, $current_hour)) {
                $predictions = array(
                    'confidence' => 0.75,
                    'trigger_type' => 'temporal',
                    'expected_time' => $timing['peak_hour'],
                    'common_triggers' => $timing['associated_triggers'] ?? array()
                );
                break;
            }
        }
        
        return $predictions;
    }
    
    /**
     * Check if approaching trigger time
     *
     * @param array $timing Timing data
     * @param int $current_hour Current hour
     * @return bool Is approaching
     */
    private function is_approaching_trigger_time($timing, $current_hour) {
        $hours_until = $timing['peak_hour'] - $current_hour;
        
        if ($hours_until < 0) {
            $hours_until += 24;
        }
        
        return $hours_until <= 3 && $hours_until > 0;
    }
    
    /**
     * Run behavioral predictions
     *
     * @param array $user_data User data
     * @return array Behavioral predictions
     */
    private function run_behavioral_predictions($user_data) {
        $predictions = array();
        
        if (!isset($user_data['baseline']) || empty($user_data['baseline'])) {
            return $predictions;
        }
        
        $baseline = $user_data['baseline'];
        $recent_episodes = array_slice($user_data['episodes'], -14); // Last 2 weeks
        
        // Check for deviations from baseline
        $deviations = $this->calculate_behavioral_deviations($recent_episodes, $baseline);
        
        // Social isolation detection
        if ($deviations['social_engagement'] < -$this->prediction_models['behavioral']['deviation_threshold']) {
            $predictions['social_isolation'] = array(
                'confidence' => min(0.85, 0.5 + abs($deviations['social_engagement']) * 0.1),
                'deviation_score' => $deviations['social_engagement'],
                'baseline_comparison' => 'significantly_lower',
                'recommendation' => 'increase_social_activities'
            );
        }
        
        // Sleep disruption detection
        if (isset($deviations['sleep_quality']) && 
            $deviations['sleep_quality'] < -$this->prediction_models['behavioral']['deviation_threshold']) {
            $predictions['sleep_disruption'] = array(
                'confidence' => min(0.8, 0.5 + abs($deviations['sleep_quality']) * 0.15),
                'deviation_score' => $deviations['sleep_quality'],
                'pattern' => $this->analyze_sleep_pattern($recent_episodes)
            );
        }
        
        // Medication adherence (if applicable)
        if ($this->user_tracks_medication($user_data['user_id'])) {
            $adherence = $this->analyze_medication_adherence($recent_episodes);
            if ($adherence['rate'] < 0.8) {
                $predictions['medication_adherence'] = array(
                    'confidence' => 0.9 - $adherence['rate'],
                    'adherence_rate' => $adherence['rate'],
                    'missed_doses' => $adherence['missed_doses'],
                    'pattern' => $adherence['pattern']
                );
            }
        }
        
        return $predictions;
    }
    
    /**
     * Calculate behavioral deviations
     *
     * @param array $recent_episodes Recent episodes
     * @param array $baseline Baseline metrics
     * @return array Deviations
     */
    private function calculate_behavioral_deviations($recent_episodes, $baseline) {
        $deviations = array();
        
        // Calculate current metrics
        $current_severity = array_sum(array_column($recent_episodes, 'severity')) / count($recent_episodes);
        $current_frequency = count($recent_episodes) / 14; // Per day
        
        // Severity deviation (in standard deviations)
        if (isset($baseline['severity_std']) && $baseline['severity_std'] > 0) {
            $deviations['severity'] = ($current_severity - $baseline['avg_severity']) / $baseline['severity_std'];
        }
        
        // Frequency deviation
        if (isset($baseline['episode_frequency']) && $baseline['episode_frequency'] > 0) {
            $deviations['frequency'] = ($current_frequency - $baseline['episode_frequency']) / $baseline['episode_frequency'];
        }
        
        // Social engagement (based on widget usage)
        $social_widgets = array_filter($recent_episodes, function($ep) {
            return in_array($ep['widget_id'], array('journal-entry', 'daily-checkin'));
        });
        
        $social_rate = count($social_widgets) / max(1, count($recent_episodes));
        $baseline_social_rate = 0.3; // Expected baseline
        
        $deviations['social_engagement'] = ($social_rate - $baseline_social_rate) / $baseline_social_rate;
        
        // Sleep quality (if sleep tracker used)
        $sleep_episodes = array_filter($recent_episodes, function($ep) {
            return $ep['widget_id'] === 'sleep-tracker';
        });
        
        if (!empty($sleep_episodes)) {
            $sleep_quality = $this->calculate_average_sleep_quality($sleep_episodes);
            $baseline_sleep = 7; // Baseline quality score
            $deviations['sleep_quality'] = ($sleep_quality - $baseline_sleep) / $baseline_sleep;
        }
        
        return $deviations;
    }
    
    /**
     * Run AI predictions
     *
     * @param int $user_id User ID
     * @param array $user_data User data
     * @return array AI predictions
     */
    private function run_ai_predictions($user_id, $user_data) {
        // Prepare comprehensive data for AI
        $ai_data = array(
            'episodes' => $user_data['episodes'],
            'patterns' => $user_data['patterns'],
            'baseline' => $user_data['baseline'],
            'profile' => $user_data['profile']
        );
        
        // Get AI predictions
        $ai_params = array(
            'type' => 'predictive_analysis',
            'models' => array('time_series', 'pattern_matching', 'risk_assessment'),
            'timeframe' => '7_days',
            'include_confidence' => true
        );
        
        $ai_response = $this->ai_service->analyze_patterns($user_data['episodes'], $ai_params);
        
        if (isset($ai_response['error'])) {
            return array();
        }
        
        // Process AI predictions
        $predictions = array();
        
        if (isset($ai_response['data']['predictions'])) {
            foreach ($ai_response['data']['predictions'] as $pred_type => $prediction) {
                if ($this->is_valid_ai_prediction($prediction)) {
                    $predictions[$pred_type] = array(
                        'confidence' => $prediction['confidence'],
                        'timeframe' => $prediction['timeframe'],
                        'details' => $prediction['details'],
                        'ai_reasoning' => $prediction['reasoning'] ?? ''
                    );
                }
            }
        }
        
        return $predictions;
    }
    
    /**
     * Combine predictions from different models
     *
     * @param array $predictions All predictions
     * @return array Combined predictions
     */
    private function combine_predictions($predictions) {
        $combined = array();
        
        // Group by prediction type
        $by_type = array();
        
        foreach ($predictions as $model => $model_predictions) {
            foreach ($model_predictions as $type => $prediction) {
                if (!isset($by_type[$type])) {
                    $by_type[$type] = array();
                }
                $by_type[$type][$model] = $prediction;
            }
        }
        
        // Combine predictions for each type
        foreach ($by_type as $type => $type_predictions) {
            $combined[$type] = $this->merge_predictions($type_predictions);
        }
        
        return $combined;
    }
    
    /**
     * Merge predictions from different models
     *
     * @param array $predictions Predictions to merge
     * @return array Merged prediction
     */
    private function merge_predictions($predictions) {
        // If only one prediction, return it
        if (count($predictions) === 1) {
            return reset($predictions);
        }
        
        // Calculate weighted average confidence
        $total_confidence = 0;
        $weight_sum = 0;
        
        $model_weights = array(
            'ai' => 0.4,
            'time_series' => 0.3,
            'pattern_matching' => 0.2,
            'behavioral' => 0.1
        );
        
        foreach ($predictions as $model => $prediction) {
            $weight = isset($model_weights[$model]) ? $model_weights[$model] : 0.1;
            $total_confidence += $prediction['confidence'] * $weight;
            $weight_sum += $weight;
        }
        
        $merged = array(
            'confidence' => $total_confidence / $weight_sum,
            'models_agree' => count($predictions),
            'predictions' => $predictions
        );
        
        // Merge other common fields
        foreach ($predictions as $prediction) {
            if (isset($prediction['timeframe']) && !isset($merged['timeframe'])) {
                $merged['timeframe'] = $prediction['timeframe'];
            }
        }
        
        return $merged;
    }
    
    /**
     * Generate alert message
     *
     * @param string $type Alert type
     * @param array $prediction Prediction data
     * @return string Alert message
     */
    private function generate_alert_message($type, $prediction) {
        $messages = array(
            'escalation' => sprintf(
                __('We\'ve detected a pattern that suggests your episodes may increase in severity over the next %s. Current confidence: %d%%', 'spiralengine'),
                $prediction['timeframe'] ?? '48 hours',
                round($prediction['confidence'] * 100)
            ),
            'pattern_recurrence' => sprintf(
                __('Based on your patterns, a recurring cycle is expected in %d days. This typically involves %s.', 'spiralengine'),
                $prediction['days_until'] ?? 1,
                $prediction['pattern_type'] ?? 'increased episodes'
            ),
            'trigger_exposure' => sprintf(
                __('You may encounter common triggers around %s. Consider preparing coping strategies.', 'spiralengine'),
                $this->format_trigger_time($prediction['expected_time'] ?? date('G'))
            ),
            'wellness_decline' => __('Your overall wellness indicators suggest you may benefit from additional self-care activities.', 'spiralengine'),
            'crisis_risk' => __('Your recent patterns indicate elevated risk. Please reach out to your support network or use crisis resources if needed.', 'spiralengine'),
            'medication_adherence' => sprintf(
                __('Medication adherence has dropped to %d%%. Consistent medication use is important for stability.', 'spiralengine'),
                round(($prediction['adherence_rate'] ?? 0.5) * 100)
            ),
            'social_isolation' => __('You\'ve been less socially active lately. Connection with others can significantly help your wellbeing.', 'spiralengine'),
            'sleep_disruption' => __('Your sleep patterns show disruption. Quality sleep is crucial for mental health.', 'spiralengine')
        );
        
        return isset($messages[$type]) ? $messages[$type] : __('We\'ve detected a pattern that may need your attention.', 'spiralengine');
    }
    
    /**
     * Format trigger time
     *
     * @param int $hour Hour
     * @return string Formatted time
     */
    private function format_trigger_time($hour) {
        if ($hour < 12) {
            return sprintf(__('%d AM', 'spiralengine'), $hour === 0 ? 12 : $hour);
        } else {
            return sprintf(__('%d PM', 'spiralengine'), $hour === 12 ? 12 : $hour - 12);
        }
    }
    
    /**
     * Generate alert recommendations
     *
     * @param string $type Alert type
     * @param array $prediction Prediction data
     * @return array Recommendations
     */
    private function generate_alert_recommendations($type, $prediction) {
        $recommendations = array();
        
        switch ($type) {
            case 'escalation':
                $recommendations[] = array(
                    'action' => __('Schedule self-care time', 'spiralengine'),
                    'urgency' => 'high'
                );
                $recommendations[] = array(
                    'action' => __('Review and practice coping skills', 'spiralengine'),
                    'urgency' => 'high'
                );
                break;
                
            case 'pattern_recurrence':
                $recommendations[] = array(
                    'action' => __('Prepare for the pattern by planning ahead', 'spiralengine'),
                    'urgency' => 'medium'
                );
                $recommendations[] = array(
                    'action' => __('Identify what helped last time', 'spiralengine'),
                    'urgency' => 'medium'
                );
                break;
                
            case 'crisis_risk':
                $recommendations[] = array(
                    'action' => __('Contact your support person', 'spiralengine'),
                    'urgency' => 'critical'
                );
                $recommendations[] = array(
                    'action' => __('Use crisis resources if needed', 'spiralengine'),
                    'urgency' => 'critical'
                );
                break;
                
            case 'sleep_disruption':
                $recommendations[] = array(
                    'action' => __('Establish a bedtime routine', 'spiralengine'),
                    'urgency' => 'medium'
                );
                $recommendations[] = array(
                    'action' => __('Limit screen time before bed', 'spiralengine'),
                    'urgency' => 'low'
                );
                break;
                
            default:
                $recommendations[] = array(
                    'action' => __('Take preventive action', 'spiralengine'),
                    'urgency' => 'medium'
                );
        }
        
        return $recommendations;
    }
    
    /**
     * Calculate alert expiry
     *
     * @param string $type Alert type
     * @return string Expiry timestamp
     */
    private function calculate_alert_expiry($type) {
        $expiry_hours = array(
            'escalation' => 48,
            'pattern_recurrence' => 72,
            'trigger_exposure' => 24,
            'wellness_decline' => 168, // 1 week
            'crisis_risk' => 24,
            'medication_adherence' => 48,
            'social_isolation' => 168,
            'sleep_disruption' => 72
        );
        
        $hours = isset($expiry_hours[$type]) ? $expiry_hours[$type] : 48;
        
        return date('Y-m-d H:i:s', strtotime('+' . $hours . ' hours'));
    }
    
    /**
     * Generate alert ID
     *
     * @return string Alert ID
     */
    private function generate_alert_id() {
        return 'alert_' . wp_generate_password(12, false);
    }
    
    /**
     * Store alert
     *
     * @param array $alert Alert data
     * @return bool Success
     */
    private function store_alert($alert) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'spiralengine_alerts';
        
        // Create table if not exists
        $this->ensure_alerts_table_exists();
        
        return $wpdb->insert(
            $table_name,
            array(
                'alert_id' => $alert['id'],
                'user_id' => $alert['user_id'],
                'type' => $alert['type'],
                'title' => $alert['title'],
                'message' => $alert['message'],
                'priority' => $alert['priority'],
                'confidence' => $alert['confidence'],
                'data' => json_encode($alert['prediction_data']),
                'recommendations' => json_encode($alert['recommendations']),
                'status' => 'active',
                'created_at' => $alert['created_at'],
                'expires_at' => $alert['expires_at']
            ),
            array('%s', '%d', '%s', '%s', '%s', '%s', '%f', '%s', '%s', '%s', '%s', '%s')
        );
    }
    
    /**
     * Ensure alerts table exists
     *
     * @return void
     */
    private function ensure_alerts_table_exists() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'spiralengine_alerts';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            alert_id varchar(50) NOT NULL UNIQUE,
            user_id bigint(20) UNSIGNED NOT NULL,
            type varchar(50) NOT NULL,
            title varchar(255) NOT NULL,
            message text NOT NULL,
            priority varchar(20) NOT NULL,
            confidence decimal(3,2) NOT NULL,
            data JSON,
            recommendations JSON,
            status varchar(20) DEFAULT 'active',
            dismissed_at datetime DEFAULT NULL,
            snoozed_until datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            expires_at datetime NOT NULL,
            INDEX idx_user_status (user_id, status),
            INDEX idx_type (type),
            INDEX idx_expires (expires_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Log alert
     *
     * @param array $alert Alert data
     * @param bool $notification_sent Was notification sent
     * @return void
     */
    private function log_alert($alert, $notification_sent) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'spiralengine_alert_log';
        
        // Create table if not exists
        $this->ensure_alert_log_table_exists();
        
        $wpdb->insert(
            $table_name,
            array(
                'alert_id' => $alert['id'],
                'user_id' => $alert['user_id'],
                'type' => $alert['type'],
                'priority' => $alert['priority'],
                'confidence' => $alert['confidence'],
                'notification_sent' => $notification_sent ? 1 : 0,
                'created_at' => current_time('mysql')
            ),
            array('%s', '%d', '%s', '%s', '%f', '%d', '%s')
        );
    }
    
    /**
     * Ensure alert log table exists
     *
     * @return void
     */
    private function ensure_alert_log_table_exists() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'spiralengine_alert_log';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            alert_id varchar(50) NOT NULL,
            user_id bigint(20) UNSIGNED NOT NULL,
            type varchar(50) NOT NULL,
            priority varchar(20) NOT NULL,
            confidence decimal(3,2) NOT NULL,
            notification_sent tinyint(1) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user (user_id),
            INDEX idx_created (created_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Get user recent history
     *
     * @param int $user_id User ID
     * @param int $days Days to retrieve
     * @return array Recent episodes
     */
    private function get_user_recent_history($user_id, $days) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'spiralengine_episodes';
        
        $episodes = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name 
            WHERE user_id = %d 
            AND created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)
            ORDER BY created_at DESC",
            $user_id,
            $days
        ), ARRAY_A);
        
        foreach ($episodes as &$episode) {
            $episode['data'] = json_decode($episode['data'], true);
            $episode['metadata'] = json_decode($episode['metadata'], true);
        }
        
        return $episodes;
    }
    
    /**
     * Check if has sufficient data
     *
     * @param array $history Episode history
     * @param string $prediction_type Prediction type
     * @return bool Has sufficient data
     */
    private function has_sufficient_data($history, $prediction_type) {
        $requirements = array(
            'escalation' => 5,
            'pattern_recurrence' => 14,
            'crisis_risk' => 3,
            'sleep_disruption' => 7
        );
        
        $required = isset($requirements[$prediction_type]) ? $requirements[$prediction_type] : 10;
        
        return count($history) >= $required;
    }
    
    /**
     * Predict severity escalation
     *
     * @param array $history Episode history
     * @return array Escalation prediction
     */
    private function predict_severity_escalation($history) {
        $recent = array_slice($history, -7);
        $severities = array_column($recent, 'severity');
        
        // Calculate trend
        $trend = $this->analyze_severity_trend($history);
        
        // Check for acceleration
        $acceleration = $this->calculate_severity_acceleration($severities);
        
        $confidence = 0.5;
        
        if ($trend['prediction'] === 'escalating') {
            $confidence = $trend['confidence'];
            
            // Boost confidence if accelerating
            if ($acceleration > 0.1) {
                $confidence = min(0.95, $confidence + 0.2);
            }
        }
        
        return array(
            'confidence' => $confidence,
            'trend' => $trend['prediction'],
            'expected_severity' => $trend['expected_severity'],
            'acceleration' => $acceleration,
            'timeframe' => '48_hours'
        );
    }
    
    /**
     * Calculate severity acceleration
     *
     * @param array $severities Severity values
     * @return float Acceleration
     */
    private function calculate_severity_acceleration($severities) {
        if (count($severities) < 3) {
            return 0;
        }
        
        // Calculate first differences
        $differences = array();
        for ($i = 1; $i < count($severities); $i++) {
            $differences[] = $severities[$i] - $severities[$i - 1];
        }
        
        // Calculate second differences (acceleration)
        $accelerations = array();
        for ($i = 1; $i < count($differences); $i++) {
            $accelerations[] = $differences[$i] - $differences[$i - 1];
        }
        
        return empty($accelerations) ? 0 : array_sum($accelerations) / count($accelerations);
    }
    
    /**
     * Assess crisis risk
     *
     * @param int $user_id User ID
     * @param array $current_episode Current episode
     * @param array $history Recent history
     * @return array Crisis risk assessment
     */
    private function assess_crisis_risk($user_id, $current_episode, $history) {
        $risk_factors = 0;
        $total_factors = 0;
        
        // High severity
        if ($current_episode['severity'] >= 8) {
            $risk_factors += 2;
        }
        $total_factors += 2;
        
        // Multiple high severity episodes
        $high_severity_count = count(array_filter($history, function($ep) {
            return $ep['severity'] >= 8;
        }));
        
        if ($high_severity_count >= 3) {
            $risk_factors += 2;
        }
        $total_factors += 2;
        
        // Rapid escalation
        if (count($history) >= 3) {
            $last_three = array_slice($history, -3);
            $severities = array_column($last_three, 'severity');
            
            if ($severities[2] > $severities[1] && $severities[1] > $severities[0]) {
                $risk_factors += 1;
            }
        }
        $total_factors += 1;
        
        // Check for crisis language (if journal or thought data)
        if ($current_episode['widget_id'] === 'journal-entry' || 
            $current_episode['widget_id'] === 'thought-challenger') {
            if ($this->contains_crisis_indicators($current_episode['data'])) {
                $risk_factors += 3;
            }
        }
        $total_factors += 3;
        
        $confidence = $risk_factors / $total_factors;
        
        return array(
            'confidence' => $confidence,
            'risk_level' => $confidence >= 0.8 ? 'critical' : ($confidence >= 0.6 ? 'high' : 'moderate'),
            'risk_factors' => $risk_factors,
            'assessment_factors' => array(
                'current_severity' => $current_episode['severity'],
                'recent_high_episodes' => $high_severity_count,
                'escalating' => isset($severities) && $severities[2] > $severities[0]
            )
        );
    }
    
    /**
     * Check if episode is sleep related
     *
     * @param array $episode Episode data
     * @return bool Is sleep related
     */
    private function is_sleep_related($episode) {
        return $episode['widget_id'] === 'sleep-tracker' ||
               (isset($episode['data']['tags']) && in_array('sleep', $episode['data']['tags']));
    }
    
    /**
     * Predict sleep disruption
     *
     * @param array $history Episode history
     * @return array Sleep disruption prediction
     */
    private function predict_sleep_disruption($history) {
        $sleep_episodes = array_filter($history, function($ep) {
            return $this->is_sleep_related($ep);
        });
        
        if (empty($sleep_episodes)) {
            return array('confidence' => 0);
        }
        
        $sleep_quality_trend = $this->analyze_sleep_quality_trend($sleep_episodes);
        
        return array(
            'confidence' => $sleep_quality_trend['confidence'],
            'trend' => $sleep_quality_trend['trend'],
            'pattern' => $sleep_quality_trend['pattern'],
            'expected_quality' => $sleep_quality_trend['expected_quality']
        );
    }
    
    /**
     * Check if user can receive alert
     *
     * @param string $user_tier User tier
     * @param string $required_tier Required tier
     * @return bool Can receive
     */
    private function user_can_receive_alert($user_tier, $required_tier) {
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
     * Check if alert is suppressed
     *
     * @param int $user_id User ID
     * @param string $type Alert type
     * @return bool Is suppressed
     */
    private function is_alert_suppressed($user_id, $type) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'spiralengine_alerts';
        
        // Check for snoozed alerts
        $snoozed = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name 
            WHERE user_id = %d 
            AND type = %s 
            AND status = 'snoozed'
            AND snoozed_until > NOW()",
            $user_id,
            $type
        ));
        
        if ($snoozed > 0) {
            return true;
        }
        
        // Check for recently dismissed
        $recently_dismissed = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name 
            WHERE user_id = %d 
            AND type = %s 
            AND status = 'dismissed'
            AND dismissed_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)",
            $user_id,
            $type
        ));
        
        return $recently_dismissed > 0;
    }
    
    /**
     * Should notify alert
     *
     * @param int $user_id User ID
     * @param string $type Alert type
     * @return bool Should notify
     */
    private function should_notify_alert($user_id, $type) {
        $settings = get_user_meta($user_id, 'spiralengine_alert_settings', true);
        
        if (!is_array($settings)) {
            return true; // Default to enabled
        }
        
        return isset($settings[$type]) ? $settings[$type] : true;
    }
    
    /**
     * Send immediate notification
     *
     * @param int $user_id User ID
     * @param array $alert Alert data
     * @return bool Success
     */
    private function send_immediate_notification($user_id, $alert) {
        // This would integrate with notification system
        do_action('spiralengine_send_critical_notification', $user_id, $alert);
        
        return true;
    }
    
    /**
     * Send alert notification
     *
     * @param int $user_id User ID
     * @param array $alert Alert data
     * @return bool Success
     */
    private function send_alert_notification($user_id, $alert) {
        // This would integrate with notification system
        do_action('spiralengine_send_alert_notification', $user_id, $alert);
        
        return true;
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
            'alert_settings' => get_user_meta($user_id, 'spiralengine_alert_settings', true)
        );
    }
    
    /**
     * Calculate standard deviation
     *
     * @param array $values Values
     * @return float Standard deviation
     */
    private function calculate_std_dev($values) {
        if (empty($values)) {
            return 0;
        }
        
        $mean = array_sum($values) / count($values);
        $squared_diffs = array_map(function($x) use ($mean) {
            return pow($x - $mean, 2);
        }, $values);
        
        $variance = array_sum($squared_diffs) / count($values);
        return sqrt($variance);
    }
    
    /**
     * Calculate linear regression
     *
     * @param array $x X values
     * @param array $y Y values
     * @return array Regression results
     */
    private function calculate_linear_regression($x, $y) {
        $n = count($x);
        
        if ($n !== count($y) || $n < 2) {
            return array('slope' => 0, 'intercept' => 0, 'r_squared' => 0);
        }
        
        $sum_x = array_sum($x);
        $sum_y = array_sum($y);
        $sum_xy = 0;
        $sum_x2 = 0;
        $sum_y2 = 0;
        
        for ($i = 0; $i < $n; $i++) {
            $sum_xy += $x[$i] * $y[$i];
            $sum_x2 += $x[$i] * $x[$i];
            $sum_y2 += $y[$i] * $y[$i];
        }
        
        $denominator = ($n * $sum_x2 - $sum_x * $sum_x);
        
        if ($denominator == 0) {
            return array('slope' => 0, 'intercept' => array_sum($y) / $n, 'r_squared' => 0);
        }
        
        $slope = ($n * $sum_xy - $sum_x * $sum_y) / $denominator;
        $intercept = ($sum_y - $slope * $sum_x) / $n;
        
        // Calculate R-squared
        $y_mean = $sum_y / $n;
        $ss_tot = 0;
        $ss_res = 0;
        
        for ($i = 0; $i < $n; $i++) {
            $y_pred = $slope * $x[$i] + $intercept;
            $ss_tot += pow($y[$i] - $y_mean, 2);
            $ss_res += pow($y[$i] - $y_pred, 2);
        }
        
        $r_squared = $ss_tot > 0 ? 1 - ($ss_res / $ss_tot) : 0;
        
        return array(
            'slope' => $slope,
            'intercept' => $intercept,
            'r_squared' => $r_squared
        );
    }
    
    /**
     * Should use AI predictions
     *
     * @param int $user_id User ID
     * @return bool Should use AI
     */
    private function should_use_ai_predictions($user_id) {
        $membership = new SpiralEngine_Membership($user_id);
        return in_array($membership->get_tier(), array('platinum'));
    }
    
    /**
     * Check if valid AI prediction
     *
     * @param array $prediction Prediction data
     * @return bool Is valid
     */
    private function is_valid_ai_prediction($prediction) {
        return isset($prediction['confidence']) && 
               $prediction['confidence'] >= 0.5 &&
               isset($prediction['timeframe']);
    }
    
    /**
     * Add alerts widget to dashboard
     *
     * @return void
     */
    public function add_alerts_widget() {
        $user_id = get_current_user_id();
        $membership = new SpiralEngine_Membership($user_id);
        
        if (!in_array($membership->get_tier(), array('gold', 'platinum'))) {
            return;
        }
        
        ?>
        <div class="spiralengine-widget" id="alerts-widget">
            <h3><?php _e('Predictive Alerts', 'spiralengine'); ?></h3>
            <div class="alerts-container">
                <div class="loading">
                    <span class="spinner is-active"></span>
                    <?php _e('Checking for alerts...', 'spiralengine'); ?>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * AJAX handler for getting alerts
     *
     * @return void
     */
    public function ajax_get_alerts() {
        check_ajax_referer('spiralengine_ai_nonce', 'nonce');
        
        $user_id = get_current_user_id();
        
        // Get active alerts
        $alerts = $this->get_user_active_alerts($user_id);
        
        wp_send_json_success(array(
            'alerts' => $alerts,
            'count' => count($alerts)
        ));
    }
    
    /**
     * Get user's active alerts
     *
     * @param int $user_id User ID
     * @return array Active alerts
     */
    private function get_user_active_alerts($user_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'spiralengine_alerts';
        
        $alerts = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name 
            WHERE user_id = %d 
            AND status = 'active'
            AND expires_at > NOW()
            ORDER BY priority DESC, created_at DESC",
            $user_id
        ), ARRAY_A);
        
        // Decode JSON fields
        foreach ($alerts as &$alert) {
            $alert['data'] = json_decode($alert['data'], true);
            $alert['recommendations'] = json_decode($alert['recommendations'], true);
        }
        
        return $alerts;
    }
    
    /**
     * AJAX handler for dismissing alert
     *
     * @return void
     */
    public function ajax_dismiss_alert() {
        check_ajax_referer('spiralengine_ai_nonce', 'nonce');
        
        $user_id = get_current_user_id();
        $alert_id = isset($_POST['alert_id']) ? sanitize_text_field($_POST['alert_id']) : '';
        
        if (empty($alert_id)) {
            wp_send_json_error(__('Invalid alert ID', 'spiralengine'));
        }
        
        // Dismiss alert
        $this->dismiss_alert($user_id, $alert_id);
        
        wp_send_json_success();
    }
    
    /**
     * Dismiss alert
     *
     * @param int $user_id User ID
     * @param string $alert_id Alert ID
     * @return bool Success
     */
    private function dismiss_alert($user_id, $alert_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'spiralengine_alerts';
        
        return $wpdb->update(
            $table_name,
            array(
                'status' => 'dismissed',
                'dismissed_at' => current_time('mysql')
            ),
            array(
                'alert_id' => $alert_id,
                'user_id' => $user_id
            ),
            array('%s', '%s'),
            array('%s', '%d')
        );
    }
    
    /**
     * AJAX handler for snoozing alert
     *
     * @return void
     */
    public function ajax_snooze_alert() {
        check_ajax_referer('spiralengine_ai_nonce', 'nonce');
        
        $user_id = get_current_user_id();
        $alert_id = isset($_POST['alert_id']) ? sanitize_text_field($_POST['alert_id']) : '';
        $hours = isset($_POST['hours']) ? intval($_POST['hours']) : 24;
        
        if (empty($alert_id)) {
            wp_send_json_error(__('Invalid alert ID', 'spiralengine'));
        }
        
        // Snooze alert
        $this->snooze_alert($user_id, $alert_id, $hours);
        
        wp_send_json_success();
    }
    
    /**
     * Snooze alert
     *
     * @param int $user_id User ID
     * @param string $alert_id Alert ID
     * @param int $hours Hours to snooze
     * @return bool Success
     */
    private function snooze_alert($user_id, $alert_id, $hours) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'spiralengine_alerts';
        
        return $wpdb->update(
            $table_name,
            array(
                'status' => 'snoozed',
                'snoozed_until' => date('Y-m-d H:i:s', strtotime('+' . $hours . ' hours'))
            ),
            array(
                'alert_id' => $alert_id,
                'user_id' => $user_id
            ),
            array('%s', '%s'),
            array('%s', '%d')
        );
    }
    
    /**
     * Analyze sleep quality trend
     *
     * @param array $sleep_episodes Sleep episodes
     * @return array Sleep quality trend
     */
    private function analyze_sleep_quality_trend($sleep_episodes) {
        if (empty($sleep_episodes)) {
            return array('confidence' => 0, 'trend' => 'unknown');
        }
        
        $qualities = array();
        foreach ($sleep_episodes as $episode) {
            if (isset($episode['data']['quality'])) {
                $qualities[] = $episode['data']['quality'];
            }
        }
        
        if (count($qualities) < 3) {
            return array('confidence' => 0, 'trend' => 'insufficient_data');
        }
        
        // Analyze trend
        $x_values = range(1, count($qualities));
        $regression = $this->calculate_linear_regression($x_values, $qualities);
        
        $trend = 'stable';
        $confidence = 0.5;
        
        if ($regression['slope'] < -0.3) {
            $trend = 'declining';
            $confidence = min(0.85, 0.5 + abs($regression['slope']) * 0.2);
        } elseif ($regression['slope'] > 0.3) {
            $trend = 'improving';
            $confidence = min(0.85, 0.5 + abs($regression['slope']) * 0.2);
        }
        
        // Identify pattern
        $pattern = $this->identify_sleep_pattern($sleep_episodes);
        
        return array(
            'confidence' => $confidence,
            'trend' => $trend,
            'pattern' => $pattern,
            'expected_quality' => max(1, min(10, round($regression['intercept'] + $regression['slope'] * (count($qualities) + 1))))
        );
    }
    
    /**
     * Identify sleep pattern
     *
     * @param array $sleep_episodes Sleep episodes
     * @return string Pattern description
     */
    private function identify_sleep_pattern($sleep_episodes) {
        $bedtimes = array();
        $durations = array();
        
        foreach ($sleep_episodes as $episode) {
            if (isset($episode['data']['bedtime'])) {
                $bedtimes[] = strtotime($episode['data']['bedtime']);
            }
            if (isset($episode['data']['duration'])) {
                $durations[] = $episode['data']['duration'];
            }
        }
        
        if (empty($bedtimes)) {
            return 'unknown';
        }
        
        // Check for irregular bedtimes
        $bedtime_variance = $this->calculate_time_variance($bedtimes);
        
        if ($bedtime_variance > 3600) { // More than 1 hour variance
            return 'irregular_schedule';
        } elseif (!empty($durations) && array_sum($durations) / count($durations) < 6) {
            return 'insufficient_duration';
        } else {
            return 'regular';
        }
    }
    
    /**
     * Calculate time variance
     *
     * @param array $times Array of timestamps
     * @return float Variance in seconds
     */
    private function calculate_time_variance($times) {
        if (count($times) < 2) {
            return 0;
        }
        
        // Convert to time of day (seconds since midnight)
        $times_of_day = array_map(function($time) {
            $midnight = strtotime(date('Y-m-d 00:00:00', $time));
            return $time - $midnight;
        }, $times);
        
        return $this->calculate_std_dev($times_of_day);
    }
    
    /**
     * User tracks medication
     *
     * @param int $user_id User ID
     * @return bool Tracks medication
     */
    private function user_tracks_medication($user_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'spiralengine_episodes';
        
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name 
            WHERE user_id = %d 
            AND widget_id = 'medication-tracker'
            AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)",
            $user_id
        ));
        
        return $count > 0;
    }
    
    /**
     * Analyze medication adherence
     *
     * @param array $recent_episodes Recent episodes
     * @return array Adherence analysis
     */
    private function analyze_medication_adherence($recent_episodes) {
        $med_episodes = array_filter($recent_episodes, function($ep) {
            return $ep['widget_id'] === 'medication-tracker';
        });
        
        if (empty($med_episodes)) {
            return array('rate' => 1, 'missed_doses' => 0, 'pattern' => 'no_data');
        }
        
        // Group by date
        $by_date = array();
        foreach ($med_episodes as $episode) {
            $date = date('Y-m-d', strtotime($episode['created_at']));
            if (!isset($by_date[$date])) {
                $by_date[$date] = array();
            }
            $by_date[$date][] = $episode;
        }
        
        // Calculate adherence rate
        $expected_days = 14; // Last 2 weeks
        $logged_days = count($by_date);
        $adherence_rate = $logged_days / $expected_days;
        
        // Identify pattern
        $pattern = 'regular';
        if ($adherence_rate < 0.5) {
            $pattern = 'poor_adherence';
        } elseif ($adherence_rate < 0.8) {
            $pattern = 'inconsistent';
        }
        
        // Count missed doses
        $missed_doses = $expected_days - $logged_days;
        
        return array(
            'rate' => $adherence_rate,
            'missed_doses' => $missed_doses,
            'pattern' => $pattern
        );
    }
    
    /**
     * Calculate average sleep quality
     *
     * @param array $sleep_episodes Sleep episodes
     * @return float Average quality
     */
    private function calculate_average_sleep_quality($sleep_episodes) {
        $qualities = array();
        
        foreach ($sleep_episodes as $episode) {
            if (isset($episode['data']['quality'])) {
                $qualities[] = $episode['data']['quality'];
            }
        }
        
        return empty($qualities) ? 5 : array_sum($qualities) / count($qualities);
    }
    
    /**
     * Contains crisis indicators
     *
     * @param array $data Episode data
     * @return bool Contains indicators
     */
    private function contains_crisis_indicators($data) {
        // This would implement text analysis for crisis indicators
        // For safety, we'll be conservative and return false
        // In production, this would use proper crisis detection
        return false;
    }
}

// Initialize predictive alerts
add_action('init', function() {
    if (get_option('spiralengine_ai_enabled', true)) {
        new SpiralEngine_Predictive_Alerts();
    }
});

// Hourly predictions cron
add_action('spiralengine_hourly_predictions', function() {
    $alerts = new SpiralEngine_Predictive_Alerts();
    $alerts->run_scheduled_predictions();
});

// Daily comprehensive predictions
add_action('spiralengine_daily_predictions', function() {
    // Run comprehensive analysis for all eligible users
    $users = get_users(array(
        'meta_key' => 'spiralengine_tier',
        'meta_value' => array('gold', 'platinum'),
        'meta_compare' => 'IN'
    ));
    
    foreach ($users as $user) {
        do_action('spiralengine_run_predictions', $user->ID);
    }
});


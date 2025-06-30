<?php
/**
 * Episode Pattern Detection - AI-powered pattern analysis from Analytics Center
 * 
 * @package    SpiralEngine
 * @subpackage Episodes
 * @file       includes/episodes/class-spiralengine-patterns.php
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Pattern Detection System
 * 
 * This class implements the pattern detection algorithms from the Analytics Center,
 * including time-based patterns, trigger patterns, severity patterns, and clustering
 * algorithms. It works with the AI Command Center for advanced analysis.
 */
class SPIRAL_Pattern_Detector {
    
    /**
     * Episode type being analyzed
     * @var string
     */
    private $episode_type;
    
    /**
     * Pattern types and their configurations
     * @var array
     */
    private $pattern_types = array(
        'temporal' => array(
            'name' => 'Time-based Patterns',
            'min_occurrences' => 3,
            'confidence_threshold' => 0.7,
            'algorithms' => array('time_of_day', 'day_of_week', 'monthly_cycle', 'seasonal')
        ),
        'trigger' => array(
            'name' => 'Trigger Patterns',
            'min_occurrences' => 3,
            'confidence_threshold' => 0.65,
            'algorithms' => array('common_triggers', 'trigger_sequences', 'trigger_combinations')
        ),
        'severity' => array(
            'name' => 'Severity Patterns',
            'min_occurrences' => 5,
            'confidence_threshold' => 0.6,
            'algorithms' => array('escalation', 'de_escalation', 'severity_cycles')
        ),
        'biological' => array(
            'name' => 'Biological Patterns',
            'min_occurrences' => 3,
            'confidence_threshold' => 0.7,
            'algorithms' => array('sleep_correlation', 'menstrual_correlation', 'substance_correlation')
        ),
        'environmental' => array(
            'name' => 'Environmental Patterns',
            'min_occurrences' => 4,
            'confidence_threshold' => 0.65,
            'algorithms' => array('location_based', 'weather_correlation', 'social_context')
        ),
        'cascade' => array(
            'name' => 'Cascade Patterns',
            'min_occurrences' => 2,
            'confidence_threshold' => 0.75,
            'algorithms' => array('episode_sequences', 'domino_effects', 'recovery_patterns')
        )
    );
    
    /**
     * AI service instance
     * @var SPIRAL_Episode_AI_Service
     */
    private $ai_service;
    
    /**
     * Constructor
     * 
     * @param string $episode_type Episode type to analyze
     */
    public function __construct($episode_type = null) {
        $this->episode_type = $episode_type;
        $this->init();
    }
    
    /**
     * Initialize pattern detector
     */
    private function init() {
        // Initialize AI service if available
        if (class_exists('SPIRAL_Episode_AI_Service')) {
            $this->ai_service = new SPIRAL_Episode_AI_Service();
        }
        
        // Schedule pattern analysis
        add_action('spiral_analyze_patterns', array($this, 'analyze_episode_patterns'), 10, 1);
        
        // Schedule daily pattern analysis
        if (!wp_next_scheduled('spiral_daily_pattern_analysis')) {
            wp_schedule_event(time(), 'daily', 'spiral_daily_pattern_analysis');
        }
        add_action('spiral_daily_pattern_analysis', array($this, 'run_daily_pattern_analysis'));
    }
    
    /**
     * Analyze patterns for a new episode
     * 
     * @param int $episode_id Episode ID
     */
    public function analyze_episode_patterns($episode_id) {
        $episode = $this->get_episode($episode_id);
        if (!$episode) {
            return;
        }
        
        $user_id = $episode['user_id'];
        $episode_type = $episode['episode_type'];
        
        // Get user's episode history
        $history = $this->get_episode_history($user_id, $episode_type, 90); // 90 days
        
        if (count($history) < 3) {
            return; // Not enough data for pattern analysis
        }
        
        // Run pattern detection for each type
        $detected_patterns = array();
        
        foreach ($this->pattern_types as $pattern_type => $config) {
            $patterns = $this->detect_patterns($pattern_type, $history, $episode);
            if (!empty($patterns)) {
                $detected_patterns[$pattern_type] = $patterns;
            }
        }
        
        // Save detected patterns
        if (!empty($detected_patterns)) {
            $this->save_patterns($user_id, $episode_type, $detected_patterns);
            
            // Run AI analysis if available
            if ($this->ai_service) {
                $this->run_ai_pattern_analysis($user_id, $episode_type, $detected_patterns);
            }
        }
    }
    
    /**
     * Detect patterns of a specific type
     * 
     * @param string $pattern_type Pattern type
     * @param array $history Episode history
     * @param array $current_episode Current episode
     * @return array Detected patterns
     */
    private function detect_patterns($pattern_type, $history, $current_episode) {
        $config = $this->pattern_types[$pattern_type];
        $patterns = array();
        
        foreach ($config['algorithms'] as $algorithm) {
            $method = 'detect_' . $algorithm;
            if (method_exists($this, $method)) {
                $result = $this->$method($history, $current_episode);
                if ($result && $result['confidence'] >= $config['confidence_threshold']) {
                    $patterns[] = $result;
                }
            }
        }
        
        return $patterns;
    }
    
    /**
     * Detect time of day patterns
     * 
     * @param array $history Episode history
     * @param array $current_episode Current episode
     * @return array|null Pattern data
     */
    private function detect_time_of_day($history, $current_episode) {
        // Group episodes by hour of day
        $hour_groups = array();
        foreach ($history as $episode) {
            $hour = date('G', strtotime($episode['episode_date']));
            $hour_groups[$hour][] = $episode;
        }
        
        // Find significant hour clusters
        $total_episodes = count($history);
        $significant_hours = array();
        
        foreach ($hour_groups as $hour => $episodes) {
            $count = count($episodes);
            $percentage = ($count / $total_episodes) * 100;
            
            // Check if this hour is significant (>20% of episodes)
            if ($percentage > 20) {
                $avg_severity = array_sum(array_column($episodes, 'severity_score')) / $count;
                $significant_hours[] = array(
                    'hour' => $hour,
                    'count' => $count,
                    'percentage' => $percentage,
                    'average_severity' => $avg_severity
                );
            }
        }
        
        if (empty($significant_hours)) {
            return null;
        }
        
        // Sort by percentage
        usort($significant_hours, function($a, $b) {
            return $b['percentage'] - $a['percentage'];
        });
        
        $primary_hour = $significant_hours[0];
        
        return array(
            'type' => 'time_of_day',
            'pattern' => sprintf(
                '%d%% of episodes occur around %s',
                round($primary_hour['percentage']),
                $this->format_hour_range($primary_hour['hour'])
            ),
            'data' => array(
                'peak_hours' => array_slice($significant_hours, 0, 3),
                'current_hour' => date('G', strtotime($current_episode['episode_date']))
            ),
            'confidence' => $this->calculate_time_pattern_confidence($significant_hours, $total_episodes),
            'significance' => $primary_hour['percentage'] / 100
        );
    }
    
    /**
     * Detect day of week patterns
     * 
     * @param array $history Episode history
     * @param array $current_episode Current episode
     * @return array|null Pattern data
     */
    private function detect_day_of_week($history, $current_episode) {
        // Group by day of week
        $day_groups = array();
        $day_names = array('Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday');
        
        foreach ($history as $episode) {
            $dow = date('w', strtotime($episode['episode_date']));
            $day_groups[$dow][] = $episode;
        }
        
        $total_episodes = count($history);
        $significant_days = array();
        
        foreach ($day_groups as $dow => $episodes) {
            $count = count($episodes);
            $percentage = ($count / $total_episodes) * 100;
            
            // Check if this day is significant (>20% of episodes)
            if ($percentage > 20) {
                $significant_days[] = array(
                    'day' => $dow,
                    'name' => $day_names[$dow],
                    'count' => $count,
                    'percentage' => $percentage
                );
            }
        }
        
        if (empty($significant_days)) {
            return null;
        }
        
        // Check for weekday/weekend pattern
        $weekday_count = 0;
        $weekend_count = 0;
        
        foreach ($day_groups as $dow => $episodes) {
            if ($dow == 0 || $dow == 6) {
                $weekend_count += count($episodes);
            } else {
                $weekday_count += count($episodes);
            }
        }
        
        $weekday_percentage = ($weekday_count / $total_episodes) * 100;
        $weekend_percentage = ($weekend_count / $total_episodes) * 100;
        
        $pattern_description = '';
        if ($weekday_percentage > 70) {
            $pattern_description = 'Episodes occur primarily on weekdays';
        } elseif ($weekend_percentage > 40) {
            $pattern_description = 'Episodes are more frequent on weekends';
        } else {
            $primary_day = $significant_days[0];
            $pattern_description = sprintf(
                '%d%% of episodes occur on %s',
                round($primary_day['percentage']),
                $primary_day['name']
            );
        }
        
        return array(
            'type' => 'day_of_week',
            'pattern' => $pattern_description,
            'data' => array(
                'significant_days' => $significant_days,
                'weekday_percentage' => $weekday_percentage,
                'weekend_percentage' => $weekend_percentage,
                'current_day' => date('w', strtotime($current_episode['episode_date']))
            ),
            'confidence' => $this->calculate_day_pattern_confidence($significant_days, $total_episodes),
            'significance' => max($weekday_percentage, $weekend_percentage) / 100
        );
    }
    
    /**
     * Detect common triggers pattern
     * 
     * @param array $history Episode history
     * @param array $current_episode Current episode
     * @return array|null Pattern data
     */
    private function detect_common_triggers($history, $current_episode) {
        // Count trigger occurrences
        $trigger_counts = array();
        $episodes_with_triggers = 0;
        
        foreach ($history as $episode) {
            if (!empty($episode['trigger_category'])) {
                $episodes_with_triggers++;
                if (!isset($trigger_counts[$episode['trigger_category']])) {
                    $trigger_counts[$episode['trigger_category']] = array(
                        'count' => 0,
                        'total_severity' => 0,
                        'episodes' => array()
                    );
                }
                $trigger_counts[$episode['trigger_category']]['count']++;
                $trigger_counts[$episode['trigger_category']]['total_severity'] += $episode['severity_score'];
                $trigger_counts[$episode['trigger_category']]['episodes'][] = $episode['episode_id'];
            }
        }
        
        if (empty($trigger_counts)) {
            return null;
        }
        
        // Find significant triggers
        $significant_triggers = array();
        foreach ($trigger_counts as $trigger => $data) {
            $percentage = ($data['count'] / $episodes_with_triggers) * 100;
            if ($percentage > 15 || $data['count'] >= 3) {
                $significant_triggers[] = array(
                    'trigger' => $trigger,
                    'count' => $data['count'],
                    'percentage' => $percentage,
                    'average_severity' => $data['total_severity'] / $data['count']
                );
            }
        }
        
        if (empty($significant_triggers)) {
            return null;
        }
        
        // Sort by count
        usort($significant_triggers, function($a, $b) {
            return $b['count'] - $a['count'];
        });
        
        $top_triggers = array_slice($significant_triggers, 0, 3);
        $pattern_parts = array();
        foreach ($top_triggers as $trigger) {
            $pattern_parts[] = sprintf(
                '%s (%d%%)',
                $trigger['trigger'],
                round($trigger['percentage'])
            );
        }
        
        return array(
            'type' => 'common_triggers',
            'pattern' => 'Most common triggers: ' . implode(', ', $pattern_parts),
            'data' => array(
                'triggers' => $significant_triggers,
                'current_trigger' => $current_episode['trigger_category'] ?? null
            ),
            'confidence' => $this->calculate_trigger_pattern_confidence($significant_triggers, $episodes_with_triggers),
            'significance' => $top_triggers[0]['percentage'] / 100
        );
    }
    
    /**
     * Detect severity escalation patterns
     * 
     * @param array $history Episode history
     * @param array $current_episode Current episode
     * @return array|null Pattern data
     */
    private function detect_escalation($history, $current_episode) {
        // Sort by date
        usort($history, function($a, $b) {
            return strtotime($a['episode_date']) - strtotime($b['episode_date']);
        });
        
        // Look for escalation sequences
        $escalations = array();
        $current_sequence = array();
        $last_severity = null;
        
        foreach ($history as $episode) {
            if ($last_severity !== null) {
                if ($episode['severity_score'] > $last_severity) {
                    // Escalation continues
                    $current_sequence[] = $episode;
                } else {
                    // Escalation broken
                    if (count($current_sequence) >= 2) {
                        $escalations[] = $current_sequence;
                    }
                    $current_sequence = array($episode);
                }
            } else {
                $current_sequence[] = $episode;
            }
            $last_severity = $episode['severity_score'];
        }
        
        // Check final sequence
        if (count($current_sequence) >= 2) {
            $escalations[] = $current_sequence;
        }
        
        if (empty($escalations)) {
            return null;
        }
        
        // Analyze escalation patterns
        $avg_escalation_length = array_sum(array_map('count', $escalations)) / count($escalations);
        $max_escalation_length = max(array_map('count', $escalations));
        
        // Check if current episode is part of escalation
        $in_escalation = false;
        if (count($history) >= 2) {
            $prev_episode = $history[count($history) - 2];
            if ($current_episode['severity_score'] > $prev_episode['severity_score']) {
                $in_escalation = true;
            }
        }
        
        return array(
            'type' => 'severity_escalation',
            'pattern' => sprintf(
                'Episodes tend to escalate in severity over %d consecutive occurrences',
                round($avg_escalation_length)
            ),
            'data' => array(
                'escalation_count' => count($escalations),
                'average_length' => $avg_escalation_length,
                'max_length' => $max_escalation_length,
                'currently_escalating' => $in_escalation
            ),
            'confidence' => min(0.5 + (count($escalations) * 0.1), 0.9),
            'significance' => count($escalations) / count($history)
        );
    }
    
    /**
     * Detect sleep correlation patterns
     * 
     * @param array $history Episode history
     * @param array $current_episode Current episode
     * @return array|null Pattern data
     */
    private function detect_sleep_correlation($history, $current_episode) {
        // Get sleep data from episode details
        $sleep_correlations = array();
        
        foreach ($history as $episode) {
            $details = $this->get_episode_details($episode['episode_id'], $episode['episode_type']);
            if ($details && isset($details['sleep_hours'])) {
                $sleep_correlations[] = array(
                    'sleep_hours' => $details['sleep_hours'],
                    'sleep_quality' => $details['sleep_quality'] ?? null,
                    'severity' => $episode['severity_score']
                );
            }
        }
        
        if (count($sleep_correlations) < 5) {
            return null;
        }
        
        // Analyze sleep patterns
        $poor_sleep_episodes = array_filter($sleep_correlations, function($data) {
            $hours = $this->parse_sleep_hours($data['sleep_hours']);
            return $hours < 6 || $data['sleep_quality'] === 'poor';
        });
        
        $poor_sleep_percentage = (count($poor_sleep_episodes) / count($sleep_correlations)) * 100;
        
        if ($poor_sleep_percentage < 30) {
            return null;
        }
        
        // Calculate severity correlation
        $poor_sleep_avg_severity = array_sum(array_column($poor_sleep_episodes, 'severity')) / count($poor_sleep_episodes);
        $good_sleep_episodes = array_diff_key($sleep_correlations, $poor_sleep_episodes);
        $good_sleep_avg_severity = count($good_sleep_episodes) > 0 
            ? array_sum(array_column($good_sleep_episodes, 'severity')) / count($good_sleep_episodes)
            : 0;
        
        $severity_increase = (($poor_sleep_avg_severity - $good_sleep_avg_severity) / $good_sleep_avg_severity) * 100;
        
        return array(
            'type' => 'sleep_correlation',
            'pattern' => sprintf(
                'Poor sleep (<%d hours) increases episode severity by %.0f%%',
                6,
                abs($severity_increase)
            ),
            'data' => array(
                'poor_sleep_percentage' => $poor_sleep_percentage,
                'severity_increase' => $severity_increase,
                'poor_sleep_avg_severity' => $poor_sleep_avg_severity,
                'good_sleep_avg_severity' => $good_sleep_avg_severity
            ),
            'confidence' => min(0.5 + (count($sleep_correlations) * 0.05), 0.9),
            'significance' => abs($severity_increase) / 100
        );
    }
    
    /**
     * Calculate risk score for forecasting
     * 
     * @param int $user_id User ID
     * @param string $forecast_window Forecast window
     * @param array $risk_factors Additional risk factors
     * @return float Risk score (0-1)
     */
    public function calculate_risk_score($user_id, $forecast_window, $risk_factors = array()) {
        // Get user patterns
        $patterns = $this->get_user_patterns($user_id, $this->episode_type);
        
        if (empty($patterns)) {
            return 0.3; // Base risk when no patterns detected
        }
        
        $risk_score = 0;
        $weight_sum = 0;
        
        // Temporal risk
        if (isset($patterns['temporal'])) {
            $temporal_risk = $this->calculate_temporal_risk($patterns['temporal'], $forecast_window);
            $risk_score += $temporal_risk * 0.3;
            $weight_sum += 0.3;
        }
        
        // Trigger risk
        if (isset($patterns['trigger'])) {
            $trigger_risk = $this->calculate_trigger_risk($patterns['trigger'], $risk_factors);
            $risk_score += $trigger_risk * 0.25;
            $weight_sum += 0.25;
        }
        
        // Severity trend risk
        if (isset($patterns['severity'])) {
            $severity_risk = $this->calculate_severity_risk($patterns['severity']);
            $risk_score += $severity_risk * 0.2;
            $weight_sum += 0.2;
        }
        
        // Biological risk
        if (isset($patterns['biological'])) {
            $biological_risk = $this->calculate_biological_risk($patterns['biological'], $risk_factors);
            $risk_score += $biological_risk * 0.15;
            $weight_sum += 0.15;
        }
        
        // Cascade risk
        if (isset($patterns['cascade'])) {
            $cascade_risk = $this->calculate_cascade_risk($patterns['cascade'], $user_id);
            $risk_score += $cascade_risk * 0.1;
            $weight_sum += 0.1;
        }
        
        // Normalize if weights don't sum to 1
        if ($weight_sum > 0) {
            $risk_score = $risk_score / $weight_sum;
        }
        
        return min($risk_score, 1.0);
    }
    
    /**
     * Get recent frequency of episodes
     * 
     * @param int $user_id User ID
     * @param int $days Number of days to look back
     * @return float Frequency score
     */
    public function get_recent_frequency($user_id, $days) {
        global $wpdb;
        
        $count = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) 
            FROM {$wpdb->prefix}spiralengine_episodes
            WHERE user_id = %d
                AND episode_type = %s
                AND episode_date >= DATE_SUB(NOW(), INTERVAL %d DAY)
        ", $user_id, $this->episode_type, $days));
        
        // Calculate frequency score (episodes per week)
        $weeks = $days / 7;
        $frequency = $count / $weeks;
        
        // Normalize to 0-1 scale (assuming >7/week is maximum)
        return min($frequency / 7, 1.0);
    }
    
    /**
     * Get severity trend
     * 
     * @param int $user_id User ID
     * @param int $days Number of days to analyze
     * @return float Trend score (-1 to 1, negative = improving, positive = worsening)
     */
    public function get_severity_trend($user_id, $days) {
        global $wpdb;
        
        // Get episodes in chronological order
        $episodes = $wpdb->get_results($wpdb->prepare("
            SELECT episode_date, severity_score
            FROM {$wpdb->prefix}spiralengine_episodes
            WHERE user_id = %d
                AND episode_type = %s
                AND episode_date >= DATE_SUB(NOW(), INTERVAL %d DAY)
            ORDER BY episode_date ASC
        ", $user_id, $this->episode_type, $days), ARRAY_A);
        
        if (count($episodes) < 3) {
            return 0; // Not enough data for trend
        }
        
        // Calculate linear regression
        $x_values = array();
        $y_values = array();
        $start_time = strtotime($episodes[0]['episode_date']);
        
        foreach ($episodes as $episode) {
            $x_values[] = (strtotime($episode['episode_date']) - $start_time) / 86400; // Days since start
            $y_values[] = $episode['severity_score'];
        }
        
        $slope = $this->calculate_linear_regression_slope($x_values, $y_values);
        
        // Normalize slope to -1 to 1 range
        return max(-1, min(1, $slope / 2));
    }
    
    /**
     * Get confidence level for predictions
     * 
     * @return float Confidence score (0-1)
     */
    public function get_confidence_level() {
        // Base confidence on amount and quality of data
        global $wpdb;
        
        $user_id = get_current_user_id();
        
        // Episode count factor
        $episode_count = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) 
            FROM {$wpdb->prefix}spiralengine_episodes
            WHERE user_id = %d AND episode_type = %s
        ", $user_id, $this->episode_type));
        
        $count_confidence = min($episode_count / 50, 1.0) * 0.3;
        
        // Pattern count factor
        $pattern_count = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) 
            FROM {$wpdb->prefix}spiralengine_patterns
            WHERE user_id = %d 
                AND pattern_type IN ('temporal', 'trigger', 'severity')
                AND confidence_score >= 0.7
        ", $user_id));
        
        $pattern_confidence = min($pattern_count / 10, 1.0) * 0.4;
        
        // Data recency factor
        $recent_episodes = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) 
            FROM {$wpdb->prefix}spiralengine_episodes
            WHERE user_id = %d 
                AND episode_type = %s
                AND episode_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ", $user_id, $this->episode_type));
        
        $recency_confidence = min($recent_episodes / 10, 1.0) * 0.3;
        
        return $count_confidence + $pattern_confidence + $recency_confidence;
    }
    
    /**
     * Save detected patterns
     * 
     * @param int $user_id User ID
     * @param string $episode_type Episode type
     * @param array $patterns Detected patterns
     */
    private function save_patterns($user_id, $episode_type, $patterns) {
        global $wpdb;
        
        foreach ($patterns as $pattern_type => $type_patterns) {
            foreach ($type_patterns as $pattern) {
                // Check if pattern already exists
                $existing = $wpdb->get_var($wpdb->prepare("
                    SELECT pattern_id 
                    FROM {$wpdb->prefix}spiralengine_patterns
                    WHERE user_id = %d
                        AND pattern_type = %s
                        AND pattern_subtype = %s
                ", $user_id, $pattern_type, $pattern['type']));
                
                if ($existing) {
                    // Update existing pattern
                    $wpdb->update(
                        $wpdb->prefix . 'spiralengine_patterns',
                        array(
                            'pattern_data' => json_encode($pattern),
                            'confidence_score' => $pattern['confidence'],
                            'occurrence_count' => $wpdb->get_var("SELECT occurrence_count + 1"),
                            'last_detected' => current_time('mysql')
                        ),
                        array('pattern_id' => $existing)
                    );
                } else {
                    // Insert new pattern
                    $wpdb->insert(
                        $wpdb->prefix . 'spiralengine_patterns',
                        array(
                            'user_id' => $user_id,
                            'pattern_type' => $pattern_type,
                            'pattern_subtype' => $pattern['type'],
                            'pattern_data' => json_encode($pattern),
                            'confidence_score' => $pattern['confidence'],
                            'occurrence_count' => 1,
                            'correlated_episodes' => json_encode(array($episode_type)),
                            'first_detected' => current_time('mysql'),
                            'last_detected' => current_time('mysql')
                        )
                    );
                    
                    // Fire action for new pattern
                    do_action('spiral_pattern_detected', $user_id, $pattern_type, $pattern);
                }
            }
        }
    }
    
    /**
     * Get user patterns
     * 
     * @param int $user_id User ID
     * @param string $episode_type Episode type
     * @return array Patterns grouped by type
     */
    public function get_user_patterns($user_id, $episode_type = null) {
        global $wpdb;
        
        $query = "SELECT * FROM {$wpdb->prefix}spiralengine_patterns WHERE user_id = %d";
        $params = array($user_id);
        
        if ($episode_type) {
            $query .= " AND JSON_CONTAINS(correlated_episodes, %s)";
            $params[] = json_encode($episode_type);
        }
        
        $query .= " AND confidence_score >= 0.6 ORDER BY confidence_score DESC";
        
        $results = $wpdb->get_results($wpdb->prepare($query, $params), ARRAY_A);
        
        // Group by pattern type
        $grouped = array();
        foreach ($results as $pattern) {
            $pattern['data'] = json_decode($pattern['pattern_data'], true);
            $grouped[$pattern['pattern_type']][] = $pattern;
        }
        
        return $grouped;
    }
    
    /**
     * Helper: Format hour range
     * 
     * @param int $hour Hour (0-23)
     * @return string Formatted range
     */
    private function format_hour_range($hour) {
        $start = $hour;
        $end = ($hour + 2) % 24;
        
        $start_ampm = $start >= 12 ? 'PM' : 'AM';
        $end_ampm = $end >= 12 ? 'PM' : 'AM';
        
        $start_12 = $start % 12 ?: 12;
        $end_12 = $end % 12 ?: 12;
        
        return sprintf('%d%s-%d%s', $start_12, $start_ampm, $end_12, $end_ampm);
    }
    
    /**
     * Helper: Calculate time pattern confidence
     * 
     * @param array $significant_hours Significant hour data
     * @param int $total_episodes Total episode count
     * @return float Confidence score
     */
    private function calculate_time_pattern_confidence($significant_hours, $total_episodes) {
        if (empty($significant_hours)) {
            return 0;
        }
        
        // Base confidence on concentration of episodes
        $top_percentage = $significant_hours[0]['percentage'];
        $confidence = 0.5;
        
        if ($top_percentage > 50) $confidence = 0.9;
        elseif ($top_percentage > 40) $confidence = 0.8;
        elseif ($top_percentage > 30) $confidence = 0.7;
        elseif ($top_percentage > 20) $confidence = 0.6;
        
        // Adjust for sample size
        if ($total_episodes < 10) $confidence *= 0.8;
        elseif ($total_episodes < 20) $confidence *= 0.9;
        
        return $confidence;
    }
    
    /**
     * Helper: Calculate day pattern confidence
     * 
     * @param array $significant_days Significant day data
     * @param int $total_episodes Total episode count
     * @return float Confidence score
     */
    private function calculate_day_pattern_confidence($significant_days, $total_episodes) {
        if (empty($significant_days)) {
            return 0;
        }
        
        // Similar to time pattern confidence
        $top_percentage = $significant_days[0]['percentage'];
        $confidence = 0.5;
        
        if ($top_percentage > 40) $confidence = 0.9;
        elseif ($top_percentage > 30) $confidence = 0.8;
        elseif ($top_percentage > 25) $confidence = 0.7;
        elseif ($top_percentage > 20) $confidence = 0.6;
        
        // Adjust for sample size
        if ($total_episodes < 15) $confidence *= 0.85;
        elseif ($total_episodes < 30) $confidence *= 0.95;
        
        return $confidence;
    }
    
    /**
     * Helper: Calculate trigger pattern confidence
     * 
     * @param array $significant_triggers Significant trigger data
     * @param int $episodes_with_triggers Episodes with triggers
     * @return float Confidence score
     */
    private function calculate_trigger_pattern_confidence($significant_triggers, $episodes_with_triggers) {
        if (empty($significant_triggers) || $episodes_with_triggers < 5) {
            return 0;
        }
        
        $top_percentage = $significant_triggers[0]['percentage'];
        $confidence = 0.5;
        
        if ($top_percentage > 50) $confidence = 0.85;
        elseif ($top_percentage > 35) $confidence = 0.75;
        elseif ($top_percentage > 25) $confidence = 0.65;
        
        // Boost confidence if multiple triggers show pattern
        if (count($significant_triggers) >= 3) {
            $confidence += 0.1;
        }
        
        return min($confidence, 0.95);
    }
    
    /**
     * Helper: Parse sleep hours from various formats
     * 
     * @param string $sleep_hours Sleep hours string
     * @return float Hours as float
     */
    private function parse_sleep_hours($sleep_hours) {
        // Handle formats like "6-7", "<6", "7+"
        if (strpos($sleep_hours, '-') !== false) {
            $parts = explode('-', $sleep_hours);
            return (floatval($parts[0]) + floatval($parts[1])) / 2;
        } elseif (strpos($sleep_hours, '<') !== false) {
            return floatval(str_replace('<', '', $sleep_hours)) - 1;
        } elseif (strpos($sleep_hours, '>') !== false || strpos($sleep_hours, '+') !== false) {
            return floatval(preg_replace('/[>+]/', '', $sleep_hours)) + 1;
        }
        
        return floatval($sleep_hours);
    }
    
    /**
     * Helper: Calculate linear regression slope
     * 
     * @param array $x X values
     * @param array $y Y values
     * @return float Slope
     */
    private function calculate_linear_regression_slope($x, $y) {
        $n = count($x);
        if ($n < 2) return 0;
        
        $sum_x = array_sum($x);
        $sum_y = array_sum($y);
        $sum_xy = 0;
        $sum_xx = 0;
        
        for ($i = 0; $i < $n; $i++) {
            $sum_xy += $x[$i] * $y[$i];
            $sum_xx += $x[$i] * $x[$i];
        }
        
        $denominator = ($n * $sum_xx) - ($sum_x * $sum_x);
        if ($denominator == 0) return 0;
        
        return (($n * $sum_xy) - ($sum_x * $sum_y)) / $denominator;
    }
    
    /**
     * Helper: Get episode details
     * 
     * @param int $episode_id Episode ID
     * @param string $episode_type Episode type
     * @return array|null Details
     */
    private function get_episode_details($episode_id, $episode_type) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'spiralengine_' . $episode_type . '_details';
        
        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            return null;
        }
        
        return $wpdb->get_row($wpdb->prepare("
            SELECT * FROM $table_name WHERE episode_id = %d
        ", $episode_id), ARRAY_A);
    }
    
    /**
     * Helper: Get episode
     * 
     * @param int $episode_id Episode ID
     * @return array|null Episode data
     */
    private function get_episode($episode_id) {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare("
            SELECT * FROM {$wpdb->prefix}spiralengine_episodes
            WHERE episode_id = %d
        ", $episode_id), ARRAY_A);
    }
    
    /**
     * Helper: Get episode history
     * 
     * @param int $user_id User ID
     * @param string $episode_type Episode type
     * @param int $days Days to look back
     * @return array Episodes
     */
    private function get_episode_history($user_id, $episode_type, $days) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare("
            SELECT * FROM {$wpdb->prefix}spiralengine_episodes
            WHERE user_id = %d
                AND episode_type = %s
                AND episode_date >= DATE_SUB(NOW(), INTERVAL %d DAY)
            ORDER BY episode_date DESC
        ", $user_id, $episode_type, $days), ARRAY_A);
    }
    
    /**
     * Run daily pattern analysis for all users
     */
    public function run_daily_pattern_analysis() {
        global $wpdb;
        
        // Get active users
        $active_users = $wpdb->get_col("
            SELECT DISTINCT user_id 
            FROM {$wpdb->prefix}spiralengine_episodes
            WHERE episode_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        ");
        
        foreach ($active_users as $user_id) {
            $this->analyze_user_patterns($user_id);
        }
        
        // Clean up old patterns
        $this->cleanup_old_patterns();
    }
    
    /**
     * Analyze patterns for a user
     * 
     * @param int $user_id User ID
     */
    private function analyze_user_patterns($user_id) {
        $episode_types = array('overthinking', 'anxiety', 'ptsd', 'depression', 'caregiver', 'panic', 'dissociation');
        
        foreach ($episode_types as $type) {
            $this->episode_type = $type;
            
            // Get recent episodes
            $recent_episodes = $this->get_episode_history($user_id, $type, 90);
            
            if (count($recent_episodes) >= 3) {
                // Re-run pattern detection
                $detected_patterns = array();
                
                foreach ($this->pattern_types as $pattern_type => $config) {
                    $patterns = $this->detect_patterns($pattern_type, $recent_episodes, $recent_episodes[0]);
                    if (!empty($patterns)) {
                        $detected_patterns[$pattern_type] = $patterns;
                    }
                }
                
                if (!empty($detected_patterns)) {
                    $this->save_patterns($user_id, $type, $detected_patterns);
                }
            }
        }
    }
    
    /**
     * Clean up old patterns
     */
    private function cleanup_old_patterns() {
        global $wpdb;
        
        // Remove patterns not detected in 180 days
        $wpdb->query("
            DELETE FROM {$wpdb->prefix}spiralengine_patterns
            WHERE last_detected < DATE_SUB(NOW(), INTERVAL 180 DAY)
                AND confidence_score < 0.7
        ");
    }
    
    /**
     * Risk calculation helpers
     */
    private function calculate_temporal_risk($temporal_patterns, $forecast_window) {
        // Implementation would calculate risk based on temporal patterns
        // and the forecast window
        return 0.5; // Placeholder
    }
    
    private function calculate_trigger_risk($trigger_patterns, $risk_factors) {
        // Implementation would assess trigger exposure risk
        return 0.5; // Placeholder
    }
    
    private function calculate_severity_risk($severity_patterns) {
        // Implementation would evaluate severity trends
        return 0.5; // Placeholder
    }
    
    private function calculate_biological_risk($biological_patterns, $risk_factors) {
        // Implementation would assess biological factor risks
        return 0.5; // Placeholder
    }
    
    private function calculate_cascade_risk($cascade_patterns, $user_id) {
        // Implementation would evaluate cascade/domino effect risks
        return 0.5; // Placeholder
    }
    
    /**
     * Estimate trigger exposure
     * 
     * @param int $user_id User ID
     * @return float Exposure estimate
     */
    private function estimate_trigger_exposure($user_id) {
        // Placeholder - would analyze user's typical trigger exposure
        return 0.5;
    }
    
    /**
     * Get biological risk factors
     * 
     * @param int $user_id User ID
     * @return array Risk factors
     */
    private function get_biological_risk_factors($user_id) {
        // Placeholder - would assess biological risks
        return array();
    }
    
    /**
     * Get temporal risk factors
     * 
     * @param int $user_id User ID
     * @return array Risk factors
     */
    private function get_temporal_risk_factors($user_id) {
        // Placeholder - would identify time-based risks
        return array();
    }
    
    /**
     * Identify high risk periods
     * 
     * @param int $user_id User ID
     * @param string $forecast_window Forecast window
     * @return array High risk periods
     */
    private function identify_high_risk_periods($user_id, $forecast_window) {
        // Placeholder - would identify specific high-risk times
        return array();
    }
    
    /**
     * Get prevention recommendations
     * 
     * @param array $risk_factors Risk factors
     * @return array Recommendations
     */
    private function get_prevention_recommendations($risk_factors) {
        // Placeholder - would generate personalized recommendations
        return array();
    }
    
    /**
     * Run AI pattern analysis
     * 
     * @param int $user_id User ID
     * @param string $episode_type Episode type
     * @param array $detected_patterns Detected patterns
     */
    private function run_ai_pattern_analysis($user_id, $episode_type, $detected_patterns) {
        if (!$this->ai_service) {
            return;
        }
        
        try {
            $ai_patterns = $this->ai_service->analyze_patterns($user_id, $episode_type);
            
            if (!empty($ai_patterns) && !empty($ai_patterns['patterns'])) {
                foreach ($ai_patterns['patterns'] as $pattern) {
                    if ($pattern['confidence'] >= 0.7) {
                        $this->save_ai_pattern($user_id, $episode_type, $pattern);
                    }
                }
            }
        } catch (Exception $e) {
            error_log('AI pattern analysis failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Save AI-detected pattern
     * 
     * @param int $user_id User ID
     * @param string $episode_type Episode type
     * @param array $pattern AI pattern data
     */
    private function save_ai_pattern($user_id, $episode_type, $pattern) {
        global $wpdb;
        
        $wpdb->insert(
            $wpdb->prefix . 'spiralengine_ai_patterns',
            array(
                'user_id' => $user_id,
                'episode_type' => $episode_type,
                'pattern_type' => $pattern['type'],
                'pattern_data' => json_encode($pattern),
                'confidence_score' => $pattern['confidence'],
                'evidence_count' => $pattern['evidence_count'] ?? 0,
                'ai_model' => 'gpt-4',
                'discovered_at' => current_time('mysql')
            )
        );
    }
}

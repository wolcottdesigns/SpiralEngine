<?php
/**
 * SpiralEngine Pattern Analyzer
 * 
 * @package    SpiralEngine
 * @subpackage AI
 * @file       includes/class-pattern-analyzer.php
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Pattern Analyzer class
 * 
 * Analyzes mental health episodes to identify patterns, trends, and correlations
 */
class SpiralEngine_Pattern_Analyzer {
    
    /**
     * AI Service instance
     *
     * @var SpiralEngine_AI_Service
     */
    private $ai_service;
    
    /**
     * Minimum episodes required for pattern analysis
     *
     * @var int
     */
    private $min_episodes = 5;
    
    /**
     * Pattern types to analyze
     *
     * @var array
     */
    private $pattern_types = array(
        'temporal',      // Time-based patterns
        'trigger',       // Trigger patterns
        'severity',      // Severity patterns
        'widget',        // Widget usage patterns
        'improvement',   // Progress patterns
        'correlation'    // Multi-factor correlations
    );
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->ai_service = SpiralEngine_AI_Service::get_instance();
    }
    
    /**
     * Analyze patterns for a user
     *
     * @param int $user_id User ID
     * @param array $options Analysis options
     * @return array Pattern analysis results
     */
    public function analyze_user_patterns($user_id, $options = array()) {
        // Default options
        $options = wp_parse_args($options, array(
            'days' => 30,
            'min_episodes' => $this->min_episodes,
            'pattern_types' => $this->pattern_types,
            'include_ai' => true,
            'include_statistical' => true,
            'widgets' => array() // Empty array means all widgets
        ));
        
        // Get episodes for analysis
        $episodes = $this->get_episodes_for_analysis($user_id, $options);
        
        if (count($episodes) < $options['min_episodes']) {
            return array(
                'error' => __('Not enough episodes for pattern analysis', 'spiralengine'),
                'required' => $options['min_episodes'],
                'found' => count($episodes)
            );
        }
        
        $results = array(
            'user_id' => $user_id,
            'period' => array(
                'start' => $options['days'] . ' days ago',
                'end' => 'today',
                'episode_count' => count($episodes)
            ),
            'patterns' => array()
        );
        
        // Statistical analysis (always performed)
        if ($options['include_statistical']) {
            $results['statistical'] = $this->perform_statistical_analysis($episodes, $options);
        }
        
        // Pattern detection for each type
        foreach ($options['pattern_types'] as $pattern_type) {
            $method = 'analyze_' . $pattern_type . '_patterns';
            if (method_exists($this, $method)) {
                $results['patterns'][$pattern_type] = $this->$method($episodes, $options);
            }
        }
        
        // AI-powered analysis
        if ($options['include_ai']) {
            $ai_results = $this->perform_ai_analysis($episodes, $results, $options);
            if (!isset($ai_results['error'])) {
                $results['ai_analysis'] = $ai_results;
            }
        }
        
        // Generate summary
        $results['summary'] = $this->generate_pattern_summary($results);
        
        // Cache results
        $this->cache_results($user_id, $results);
        
        return $results;
    }
    
    /**
     * Get episodes for analysis
     *
     * @param int $user_id User ID
     * @param array $options Options
     * @return array Episodes
     */
    private function get_episodes_for_analysis($user_id, $options) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'spiralengine_episodes';
        
        $where_clauses = array(
            'user_id = %d',
            'created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)'
        );
        $where_values = array($user_id, $options['days']);
        
        // Filter by widgets if specified
        if (!empty($options['widgets'])) {
            $placeholders = array_fill(0, count($options['widgets']), '%s');
            $where_clauses[] = 'widget_id IN (' . implode(',', $placeholders) . ')';
            $where_values = array_merge($where_values, $options['widgets']);
        }
        
        $where_sql = implode(' AND ', $where_clauses);
        
        $query = $wpdb->prepare(
            "SELECT * FROM $table_name 
            WHERE $where_sql 
            ORDER BY created_at ASC",
            $where_values
        );
        
        $episodes = $wpdb->get_results($query, ARRAY_A);
        
        // Decode JSON data
        foreach ($episodes as &$episode) {
            $episode['data'] = json_decode($episode['data'], true);
            $episode['metadata'] = json_decode($episode['metadata'], true);
        }
        
        return $episodes;
    }
    
    /**
     * Perform statistical analysis
     *
     * @param array $episodes Episodes
     * @param array $options Options
     * @return array Statistical results
     */
    private function perform_statistical_analysis($episodes, $options) {
        $stats = array(
            'basic' => $this->calculate_basic_stats($episodes),
            'distributions' => $this->calculate_distributions($episodes),
            'trends' => $this->calculate_trends($episodes),
            'frequencies' => $this->calculate_frequencies($episodes)
        );
        
        return $stats;
    }
    
    /**
     * Calculate basic statistics
     *
     * @param array $episodes Episodes
     * @return array Basic stats
     */
    private function calculate_basic_stats($episodes) {
        $severities = array_column($episodes, 'severity');
        
        $stats = array(
            'total_episodes' => count($episodes),
            'severity' => array(
                'mean' => round(array_sum($severities) / count($severities), 2),
                'median' => $this->calculate_median($severities),
                'mode' => $this->calculate_mode($severities),
                'min' => min($severities),
                'max' => max($severities),
                'std_dev' => $this->calculate_std_dev($severities)
            ),
            'episodes_per_day' => $this->calculate_episodes_per_day($episodes),
            'most_active_time' => $this->calculate_most_active_time($episodes),
            'widget_usage' => $this->calculate_widget_usage($episodes)
        );
        
        return $stats;
    }
    
    /**
     * Analyze temporal patterns
     *
     * @param array $episodes Episodes
     * @param array $options Options
     * @return array Temporal patterns
     */
    private function analyze_temporal_patterns($episodes, $options) {
        $patterns = array(
            'daily' => $this->analyze_daily_patterns($episodes),
            'weekly' => $this->analyze_weekly_patterns($episodes),
            'time_of_day' => $this->analyze_time_patterns($episodes),
            'episode_clustering' => $this->analyze_episode_clustering($episodes)
        );
        
        // Identify peak times
        $patterns['peak_times'] = $this->identify_peak_times($patterns);
        
        // Detect cyclical patterns
        $patterns['cycles'] = $this->detect_cycles($episodes);
        
        return $patterns;
    }
    
    /**
     * Analyze trigger patterns
     *
     * @param array $episodes Episodes
     * @param array $options Options
     * @return array Trigger patterns
     */
    private function analyze_trigger_patterns($episodes, $options) {
        $trigger_episodes = array_filter($episodes, function($episode) {
            return $episode['widget_id'] === 'trigger-tracker';
        });
        
        if (empty($trigger_episodes)) {
            return array('message' => 'No trigger data available');
        }
        
        $patterns = array(
            'common_triggers' => $this->identify_common_triggers($trigger_episodes),
            'trigger_severity' => $this->analyze_trigger_severity($trigger_episodes),
            'trigger_sequences' => $this->analyze_trigger_sequences($trigger_episodes),
            'trigger_timing' => $this->analyze_trigger_timing($trigger_episodes),
            'coping_effectiveness' => $this->analyze_coping_effectiveness($trigger_episodes)
        );
        
        return $patterns;
    }
    
    /**
     * Analyze severity patterns
     *
     * @param array $episodes Episodes
     * @param array $options Options
     * @return array Severity patterns
     */
    private function analyze_severity_patterns($episodes, $options) {
        $patterns = array(
            'severity_trends' => $this->calculate_severity_trends($episodes),
            'severity_triggers' => $this->identify_severity_triggers($episodes),
            'recovery_patterns' => $this->analyze_recovery_patterns($episodes),
            'escalation_patterns' => $this->analyze_escalation_patterns($episodes),
            'stability_periods' => $this->identify_stability_periods($episodes)
        );
        
        return $patterns;
    }
    
    /**
     * Analyze widget usage patterns
     *
     * @param array $episodes Episodes
     * @param array $options Options
     * @return array Widget patterns
     */
    private function analyze_widget_patterns($episodes, $options) {
        $patterns = array(
            'usage_frequency' => $this->calculate_widget_frequency($episodes),
            'widget_sequences' => $this->analyze_widget_sequences($episodes),
            'widget_effectiveness' => $this->analyze_widget_effectiveness($episodes),
            'preferred_widgets' => $this->identify_preferred_widgets($episodes),
            'widget_timing' => $this->analyze_widget_timing($episodes)
        );
        
        return $patterns;
    }
    
    /**
     * Analyze improvement patterns
     *
     * @param array $episodes Episodes
     * @param array $options Options
     * @return array Improvement patterns
     */
    private function analyze_improvement_patterns($episodes, $options) {
        $patterns = array(
            'overall_trend' => $this->calculate_overall_trend($episodes),
            'improvement_periods' => $this->identify_improvement_periods($episodes),
            'success_factors' => $this->identify_success_factors($episodes),
            'setback_patterns' => $this->analyze_setback_patterns($episodes),
            'milestone_progress' => $this->track_milestone_progress($episodes)
        );
        
        return $patterns;
    }
    
    /**
     * Analyze correlations between factors
     *
     * @param array $episodes Episodes
     * @param array $options Options
     * @return array Correlations
     */
    private function analyze_correlation_patterns($episodes, $options) {
        $correlations = array(
            'trigger_severity' => $this->correlate_triggers_severity($episodes),
            'time_severity' => $this->correlate_time_severity($episodes),
            'widget_outcomes' => $this->correlate_widget_outcomes($episodes),
            'environmental' => $this->analyze_environmental_correlations($episodes),
            'behavioral' => $this->analyze_behavioral_correlations($episodes)
        );
        
        return $correlations;
    }
    
    /**
     * Perform AI analysis on patterns
     *
     * @param array $episodes Episodes
     * @param array $statistical_results Statistical results
     * @param array $options Options
     * @return array AI analysis results
     */
    private function perform_ai_analysis($episodes, $statistical_results, $options) {
        // Check if user can use AI pattern analysis
        $membership = new SpiralEngine_Membership($episodes[0]['user_id']);
        if (!in_array($membership->get_tier(), array('gold', 'platinum'))) {
            return array('error' => __('AI pattern analysis requires Gold tier or higher', 'spiralengine'));
        }
        
        // Prepare data for AI
        $ai_params = array(
            'type' => 'pattern_analysis',
            'timeframe' => $options['days'] . '_days',
            'include_predictions' => true,
            'include_recommendations' => true
        );
        
        // Perform AI analysis
        $ai_results = $this->ai_service->analyze_patterns($episodes, $ai_params);
        
        return $ai_results;
    }
    
    /**
     * Calculate median
     *
     * @param array $values Values
     * @return float Median
     */
    private function calculate_median($values) {
        sort($values);
        $count = count($values);
        $middle = floor(($count - 1) / 2);
        
        if ($count % 2) {
            return $values[$middle];
        } else {
            return ($values[$middle] + $values[$middle + 1]) / 2;
        }
    }
    
    /**
     * Calculate mode
     *
     * @param array $values Values
     * @return mixed Mode value
     */
    private function calculate_mode($values) {
        $value_counts = array_count_values($values);
        $max_count = max($value_counts);
        
        $modes = array_keys($value_counts, $max_count);
        return count($modes) === 1 ? $modes[0] : $modes;
    }
    
    /**
     * Calculate standard deviation
     *
     * @param array $values Values
     * @return float Standard deviation
     */
    private function calculate_std_dev($values) {
        $mean = array_sum($values) / count($values);
        $squared_diffs = array_map(function($x) use ($mean) {
            return pow($x - $mean, 2);
        }, $values);
        
        $variance = array_sum($squared_diffs) / count($values);
        return round(sqrt($variance), 2);
    }
    
    /**
     * Calculate episodes per day
     *
     * @param array $episodes Episodes
     * @return array Episodes per day
     */
    private function calculate_episodes_per_day($episodes) {
        $by_date = array();
        
        foreach ($episodes as $episode) {
            $date = date('Y-m-d', strtotime($episode['created_at']));
            if (!isset($by_date[$date])) {
                $by_date[$date] = 0;
            }
            $by_date[$date]++;
        }
        
        return array(
            'average' => round(array_sum($by_date) / count($by_date), 2),
            'max' => max($by_date),
            'min' => min($by_date),
            'by_date' => $by_date
        );
    }
    
    /**
     * Calculate most active time
     *
     * @param array $episodes Episodes
     * @return array Most active time info
     */
    private function calculate_most_active_time($episodes) {
        $by_hour = array_fill(0, 24, 0);
        
        foreach ($episodes as $episode) {
            $hour = intval(date('G', strtotime($episode['created_at'])));
            $by_hour[$hour]++;
        }
        
        $max_hour = array_search(max($by_hour), $by_hour);
        
        return array(
            'hour' => $max_hour,
            'time_range' => sprintf('%02d:00-%02d:00', $max_hour, ($max_hour + 1) % 24),
            'count' => $by_hour[$max_hour],
            'distribution' => $by_hour
        );
    }
    
    /**
     * Calculate widget usage
     *
     * @param array $episodes Episodes
     * @return array Widget usage stats
     */
    private function calculate_widget_usage($episodes) {
        $widget_counts = array();
        
        foreach ($episodes as $episode) {
            $widget = $episode['widget_id'];
            if (!isset($widget_counts[$widget])) {
                $widget_counts[$widget] = 0;
            }
            $widget_counts[$widget]++;
        }
        
        arsort($widget_counts);
        
        return $widget_counts;
    }
    
    /**
     * Calculate distributions
     *
     * @param array $episodes Episodes
     * @return array Distribution data
     */
    private function calculate_distributions($episodes) {
        $distributions = array(
            'severity' => $this->calculate_severity_distribution($episodes),
            'hourly' => $this->calculate_hourly_distribution($episodes),
            'daily' => $this->calculate_daily_distribution($episodes),
            'weekly' => $this->calculate_weekly_distribution($episodes)
        );
        
        return $distributions;
    }
    
    /**
     * Calculate severity distribution
     *
     * @param array $episodes Episodes
     * @return array Severity distribution
     */
    private function calculate_severity_distribution($episodes) {
        $distribution = array_fill(1, 10, 0);
        
        foreach ($episodes as $episode) {
            $severity = intval($episode['severity']);
            if ($severity >= 1 && $severity <= 10) {
                $distribution[$severity]++;
            }
        }
        
        // Convert to percentages
        $total = array_sum($distribution);
        foreach ($distribution as $severity => &$count) {
            $count = array(
                'count' => $count,
                'percentage' => round(($count / $total) * 100, 1)
            );
        }
        
        return $distribution;
    }
    
    /**
     * Calculate hourly distribution
     *
     * @param array $episodes Episodes
     * @return array Hourly distribution
     */
    private function calculate_hourly_distribution($episodes) {
        $distribution = array_fill(0, 24, 0);
        
        foreach ($episodes as $episode) {
            $hour = intval(date('G', strtotime($episode['created_at'])));
            $distribution[$hour]++;
        }
        
        return $distribution;
    }
    
    /**
     * Calculate daily distribution
     *
     * @param array $episodes Episodes
     * @return array Daily distribution
     */
    private function calculate_daily_distribution($episodes) {
        $distribution = array_fill(0, 7, 0);
        $day_names = array('Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday');
        
        foreach ($episodes as $episode) {
            $day = intval(date('w', strtotime($episode['created_at'])));
            $distribution[$day]++;
        }
        
        $result = array();
        foreach ($distribution as $day => $count) {
            $result[$day_names[$day]] = $count;
        }
        
        return $result;
    }
    
    /**
     * Calculate weekly distribution
     *
     * @param array $episodes Episodes
     * @return array Weekly distribution
     */
    private function calculate_weekly_distribution($episodes) {
        $distribution = array();
        
        foreach ($episodes as $episode) {
            $week = date('Y-W', strtotime($episode['created_at']));
            if (!isset($distribution[$week])) {
                $distribution[$week] = 0;
            }
            $distribution[$week]++;
        }
        
        return $distribution;
    }
    
    /**
     * Calculate trends
     *
     * @param array $episodes Episodes
     * @return array Trend data
     */
    private function calculate_trends($episodes) {
        $trends = array(
            'severity' => $this->calculate_severity_trend($episodes),
            'frequency' => $this->calculate_frequency_trend($episodes),
            'improvement' => $this->calculate_improvement_score($episodes)
        );
        
        return $trends;
    }
    
    /**
     * Calculate severity trend
     *
     * @param array $episodes Episodes
     * @return array Severity trend
     */
    private function calculate_severity_trend($episodes) {
        if (count($episodes) < 2) {
            return array('trend' => 'insufficient_data');
        }
        
        // Calculate moving average
        $window_size = min(7, floor(count($episodes) / 3));
        $moving_averages = array();
        
        for ($i = $window_size - 1; $i < count($episodes); $i++) {
            $window = array_slice($episodes, $i - $window_size + 1, $window_size);
            $severities = array_column($window, 'severity');
            $moving_averages[] = array_sum($severities) / count($severities);
        }
        
        // Determine trend
        if (count($moving_averages) >= 2) {
            $first_third = array_slice($moving_averages, 0, floor(count($moving_averages) / 3));
            $last_third = array_slice($moving_averages, -floor(count($moving_averages) / 3));
            
            $first_avg = array_sum($first_third) / count($first_third);
            $last_avg = array_sum($last_third) / count($last_third);
            
            $change = $last_avg - $first_avg;
            
            if ($change < -0.5) {
                $trend = 'improving';
            } elseif ($change > 0.5) {
                $trend = 'worsening';
            } else {
                $trend = 'stable';
            }
        } else {
            $trend = 'stable';
        }
        
        return array(
            'trend' => $trend,
            'change' => isset($change) ? round($change, 2) : 0,
            'moving_averages' => $moving_averages
        );
    }
    
    /**
     * Calculate frequency trend
     *
     * @param array $episodes Episodes
     * @return array Frequency trend
     */
    private function calculate_frequency_trend($episodes) {
        $by_week = array();
        
        foreach ($episodes as $episode) {
            $week = date('Y-W', strtotime($episode['created_at']));
            if (!isset($by_week[$week])) {
                $by_week[$week] = 0;
            }
            $by_week[$week]++;
        }
        
        if (count($by_week) < 2) {
            return array('trend' => 'insufficient_data');
        }
        
        $weeks = array_keys($by_week);
        $first_week = $by_week[$weeks[0]];
        $last_week = $by_week[$weeks[count($weeks) - 1]];
        
        $change = $last_week - $first_week;
        
        if ($change < -2) {
            $trend = 'decreasing';
        } elseif ($change > 2) {
            $trend = 'increasing';
        } else {
            $trend = 'stable';
        }
        
        return array(
            'trend' => $trend,
            'first_week_count' => $first_week,
            'last_week_count' => $last_week,
            'weekly_counts' => $by_week
        );
    }
    
    /**
     * Calculate improvement score
     *
     * @param array $episodes Episodes
     * @return float Improvement score
     */
    private function calculate_improvement_score($episodes) {
        // Factors: decreasing severity, decreasing frequency, increasing coping
        $score = 50; // Start at neutral
        
        // Severity trend
        $severity_trend = $this->calculate_severity_trend($episodes);
        if ($severity_trend['trend'] === 'improving') {
            $score += 20;
        } elseif ($severity_trend['trend'] === 'worsening') {
            $score -= 20;
        }
        
        // Frequency trend
        $frequency_trend = $this->calculate_frequency_trend($episodes);
        if ($frequency_trend['trend'] === 'decreasing') {
            $score += 15;
        } elseif ($frequency_trend['trend'] === 'increasing') {
            $score -= 15;
        }
        
        // Coping skill usage
        $coping_episodes = array_filter($episodes, function($ep) {
            return $ep['widget_id'] === 'coping-logger';
        });
        
        if (count($coping_episodes) > count($episodes) * 0.3) {
            $score += 15; // Good coping engagement
        }
        
        return max(0, min(100, $score));
    }
    
    /**
     * Calculate frequencies
     *
     * @param array $episodes Episodes
     * @return array Frequency data
     */
    private function calculate_frequencies($episodes) {
        $total_days = $this->calculate_total_days($episodes);
        
        return array(
            'episodes_per_day' => round(count($episodes) / $total_days, 2),
            'episodes_per_week' => round((count($episodes) / $total_days) * 7, 2),
            'high_severity_rate' => $this->calculate_high_severity_rate($episodes),
            'widget_frequencies' => $this->calculate_widget_frequencies($episodes, $total_days)
        );
    }
    
    /**
     * Calculate total days spanned by episodes
     *
     * @param array $episodes Episodes
     * @return int Total days
     */
    private function calculate_total_days($episodes) {
        if (empty($episodes)) {
            return 0;
        }
        
        $first = strtotime($episodes[0]['created_at']);
        $last = strtotime($episodes[count($episodes) - 1]['created_at']);
        
        return max(1, floor(($last - $first) / 86400) + 1);
    }
    
    /**
     * Calculate high severity rate
     *
     * @param array $episodes Episodes
     * @return float Rate of high severity episodes
     */
    private function calculate_high_severity_rate($episodes) {
        $high_severity = array_filter($episodes, function($ep) {
            return $ep['severity'] >= 7;
        });
        
        return round((count($high_severity) / count($episodes)) * 100, 1);
    }
    
    /**
     * Calculate widget frequencies
     *
     * @param array $episodes Episodes
     * @param int $total_days Total days
     * @return array Widget frequencies
     */
    private function calculate_widget_frequencies($episodes, $total_days) {
        $widget_counts = $this->calculate_widget_usage($episodes);
        $frequencies = array();
        
        foreach ($widget_counts as $widget => $count) {
            $frequencies[$widget] = array(
                'total' => $count,
                'per_day' => round($count / $total_days, 2),
                'per_week' => round(($count / $total_days) * 7, 2),
                'percentage' => round(($count / count($episodes)) * 100, 1)
            );
        }
        
        return $frequencies;
    }
    
    /**
     * Analyze daily patterns
     *
     * @param array $episodes Episodes
     * @return array Daily patterns
     */
    private function analyze_daily_patterns($episodes) {
        $by_date = array();
        
        foreach ($episodes as $episode) {
            $date = date('Y-m-d', strtotime($episode['created_at']));
            if (!isset($by_date[$date])) {
                $by_date[$date] = array(
                    'count' => 0,
                    'total_severity' => 0,
                    'episodes' => array()
                );
            }
            
            $by_date[$date]['count']++;
            $by_date[$date]['total_severity'] += $episode['severity'];
            $by_date[$date]['episodes'][] = $episode['id'];
        }
        
        // Calculate daily averages
        foreach ($by_date as &$day_data) {
            $day_data['avg_severity'] = round($day_data['total_severity'] / $day_data['count'], 2);
        }
        
        return $by_date;
    }
    
    /**
     * Analyze weekly patterns
     *
     * @param array $episodes Episodes
     * @return array Weekly patterns
     */
    private function analyze_weekly_patterns($episodes) {
        $by_week = array();
        
        foreach ($episodes as $episode) {
            $week = date('Y-W', strtotime($episode['created_at']));
            if (!isset($by_week[$week])) {
                $by_week[$week] = array(
                    'count' => 0,
                    'total_severity' => 0,
                    'high_severity_count' => 0
                );
            }
            
            $by_week[$week]['count']++;
            $by_week[$week]['total_severity'] += $episode['severity'];
            
            if ($episode['severity'] >= 7) {
                $by_week[$week]['high_severity_count']++;
            }
        }
        
        // Calculate weekly metrics
        foreach ($by_week as &$week_data) {
            $week_data['avg_severity'] = round($week_data['total_severity'] / $week_data['count'], 2);
            $week_data['high_severity_rate'] = round(($week_data['high_severity_count'] / $week_data['count']) * 100, 1);
        }
        
        return $by_week;
    }
    
    /**
     * Analyze time patterns
     *
     * @param array $episodes Episodes
     * @return array Time patterns
     */
    private function analyze_time_patterns($episodes) {
        $time_periods = array(
            'early_morning' => array('start' => 4, 'end' => 8, 'episodes' => array()),
            'morning' => array('start' => 8, 'end' => 12, 'episodes' => array()),
            'afternoon' => array('start' => 12, 'end' => 17, 'episodes' => array()),
            'evening' => array('start' => 17, 'end' => 21, 'episodes' => array()),
            'night' => array('start' => 21, 'end' => 24, 'episodes' => array()),
            'late_night' => array('start' => 0, 'end' => 4, 'episodes' => array())
        );
        
        foreach ($episodes as $episode) {
            $hour = intval(date('G', strtotime($episode['created_at'])));
            
            foreach ($time_periods as $period => &$data) {
                if (($data['start'] <= $hour && $hour < $data['end']) ||
                    ($period === 'late_night' && ($hour >= 0 && $hour < 4))) {
                    $data['episodes'][] = $episode;
                    break;
                }
            }
        }
        
        // Calculate metrics for each period
        foreach ($time_periods as &$period_data) {
            $count = count($period_data['episodes']);
            $period_data['count'] = $count;
            $period_data['percentage'] = round(($count / count($episodes)) * 100, 1);
            
            if ($count > 0) {
                $severities = array_column($period_data['episodes'], 'severity');
                $period_data['avg_severity'] = round(array_sum($severities) / $count, 2);
            } else {
                $period_data['avg_severity'] = 0;
            }
            
            // Remove episode details to save memory
            unset($period_data['episodes']);
        }
        
        return $time_periods;
    }
    
    /**
     * Analyze episode clustering
     *
     * @param array $episodes Episodes
     * @return array Clustering data
     */
    private function analyze_episode_clustering($episodes) {
        $clusters = array();
        $cluster_threshold = 3600; // 1 hour in seconds
        
        $current_cluster = array($episodes[0]);
        
        for ($i = 1; $i < count($episodes); $i++) {
            $time_diff = strtotime($episodes[$i]['created_at']) - strtotime($episodes[$i-1]['created_at']);
            
            if ($time_diff <= $cluster_threshold) {
                $current_cluster[] = $episodes[$i];
            } else {
                if (count($current_cluster) >= 2) {
                    $clusters[] = $this->analyze_cluster($current_cluster);
                }
                $current_cluster = array($episodes[$i]);
            }
        }
        
        // Don't forget the last cluster
        if (count($current_cluster) >= 2) {
            $clusters[] = $this->analyze_cluster($current_cluster);
        }
        
        return array(
            'cluster_count' => count($clusters),
            'clusters' => $clusters,
            'clustering_rate' => round((count($clusters) * 2) / count($episodes) * 100, 1)
        );
    }
    
    /**
     * Analyze a cluster of episodes
     *
     * @param array $cluster Episode cluster
     * @return array Cluster analysis
     */
    private function analyze_cluster($cluster) {
        $severities = array_column($cluster, 'severity');
        
        return array(
            'size' => count($cluster),
            'duration_minutes' => round((strtotime($cluster[count($cluster)-1]['created_at']) - 
                                       strtotime($cluster[0]['created_at'])) / 60),
            'avg_severity' => round(array_sum($severities) / count($severities), 2),
            'max_severity' => max($severities),
            'start_time' => $cluster[0]['created_at'],
            'widgets_used' => array_unique(array_column($cluster, 'widget_id'))
        );
    }
    
    /**
     * Identify peak times
     *
     * @param array $patterns Pattern data
     * @return array Peak times
     */
    private function identify_peak_times($patterns) {
        $peak_times = array();
        
        // Find peak hour
        $hourly = $this->calculate_hourly_distribution($patterns['daily']);
        $peak_hour = array_search(max($hourly), $hourly);
        $peak_times['hour'] = array(
            'value' => $peak_hour,
            'label' => sprintf('%02d:00-%02d:00', $peak_hour, ($peak_hour + 1) % 24),
            'episode_count' => $hourly[$peak_hour]
        );
        
        // Find peak day of week
        if (isset($patterns['weekly'])) {
            $daily_dist = $this->calculate_daily_distribution($patterns['weekly']);
            $peak_day = array_search(max($daily_dist), $daily_dist);
            $peak_times['day_of_week'] = array(
                'value' => $peak_day,
                'label' => array_keys($daily_dist)[$peak_day],
                'episode_count' => max($daily_dist)
            );
        }
        
        // Find peak time period
        $time_periods = $patterns['time_of_day'];
        $peak_period = '';
        $max_count = 0;
        
        foreach ($time_periods as $period => $data) {
            if ($data['count'] > $max_count) {
                $max_count = $data['count'];
                $peak_period = $period;
            }
        }
        
        $peak_times['time_period'] = array(
            'value' => $peak_period,
            'label' => ucfirst(str_replace('_', ' ', $peak_period)),
            'episode_count' => $max_count
        );
        
        return $peak_times;
    }
    
    /**
     * Detect cyclical patterns
     *
     * @param array $episodes Episodes
     * @return array Cycle information
     */
    private function detect_cycles($episodes) {
        // Simple cycle detection - look for repeating patterns in severity
        $severities = array_column($episodes, 'severity');
        
        if (count($severities) < 14) {
            return array('message' => 'Not enough data for cycle detection');
        }
        
        // Check for weekly cycles
        $weekly_pattern = $this->check_weekly_cycle($episodes);
        
        // Check for other periodic patterns
        $cycles = array(
            'weekly' => $weekly_pattern,
            'detected_cycles' => array()
        );
        
        // Look for custom cycles (3-30 days)
        for ($cycle_length = 3; $cycle_length <= min(30, floor(count($episodes) / 2)); $cycle_length++) {
            $cycle_strength = $this->calculate_cycle_strength($severities, $cycle_length);
            
            if ($cycle_strength > 0.6) { // Threshold for cycle detection
                $cycles['detected_cycles'][] = array(
                    'length_days' => $cycle_length,
                    'strength' => round($cycle_strength, 2),
                    'confidence' => $this->calculate_cycle_confidence($cycle_strength, count($episodes))
                );
            }
        }
        
        return $cycles;
    }
    
    /**
     * Check for weekly cycle
     *
     * @param array $episodes Episodes
     * @return array Weekly cycle data
     */
    private function check_weekly_cycle($episodes) {
        $by_day_of_week = array_fill(0, 7, array());
        
        foreach ($episodes as $episode) {
            $day = intval(date('w', strtotime($episode['created_at'])));
            $by_day_of_week[$day][] = $episode['severity'];
        }
        
        $avg_by_day = array();
        foreach ($by_day_of_week as $day => $severities) {
            if (!empty($severities)) {
                $avg_by_day[$day] = array_sum($severities) / count($severities);
            } else {
                $avg_by_day[$day] = 0;
            }
        }
        
        // Check if there's significant variation
        $variation = $this->calculate_std_dev($avg_by_day);
        
        return array(
            'has_weekly_pattern' => $variation > 1,
            'variation' => round($variation, 2),
            'pattern' => $avg_by_day
        );
    }
    
    /**
     * Calculate cycle strength
     *
     * @param array $values Values to check
     * @param int $cycle_length Cycle length to test
     * @return float Cycle strength (0-1)
     */
    private function calculate_cycle_strength($values, $cycle_length) {
        if (count($values) < $cycle_length * 2) {
            return 0;
        }
        
        $correlations = array();
        
        for ($offset = 0; $offset < $cycle_length; $offset++) {
            $series1 = array();
            $series2 = array();
            
            for ($i = $offset; $i < count($values) - $cycle_length; $i += $cycle_length) {
                if (isset($values[$i]) && isset($values[$i + $cycle_length])) {
                    $series1[] = $values[$i];
                    $series2[] = $values[$i + $cycle_length];
                }
            }
            
            if (count($series1) >= 2) {
                $correlations[] = $this->calculate_correlation($series1, $series2);
            }
        }
        
        return empty($correlations) ? 0 : array_sum($correlations) / count($correlations);
    }
    
    /**
     * Calculate correlation between two series
     *
     * @param array $x First series
     * @param array $y Second series
     * @return float Correlation coefficient
     */
    private function calculate_correlation($x, $y) {
        $n = count($x);
        if ($n !== count($y) || $n < 2) {
            return 0;
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
        
        $denominator = sqrt(($n * $sum_x2 - $sum_x * $sum_x) * ($n * $sum_y2 - $sum_y * $sum_y));
        
        if ($denominator == 0) {
            return 0;
        }
        
        return ($n * $sum_xy - $sum_x * $sum_y) / $denominator;
    }
    
    /**
     * Calculate cycle confidence
     *
     * @param float $strength Cycle strength
     * @param int $data_points Number of data points
     * @return string Confidence level
     */
    private function calculate_cycle_confidence($strength, $data_points) {
        $confidence_score = $strength * min(1, $data_points / 100);
        
        if ($confidence_score > 0.8) {
            return 'high';
        } elseif ($confidence_score > 0.6) {
            return 'moderate';
        } else {
            return 'low';
        }
    }
    
    /**
     * Identify common triggers
     *
     * @param array $trigger_episodes Trigger episodes
     * @return array Common triggers
     */
    private function identify_common_triggers($trigger_episodes) {
        $trigger_counts = array();
        
        foreach ($trigger_episodes as $episode) {
            if (isset($episode['data']['trigger_category'])) {
                $category = $episode['data']['trigger_category'];
                if (!isset($trigger_counts[$category])) {
                    $trigger_counts[$category] = 0;
                }
                $trigger_counts[$category]++;
            }
        }
        
        arsort($trigger_counts);
        
        $total = array_sum($trigger_counts);
        $common_triggers = array();
        
        foreach ($trigger_counts as $trigger => $count) {
            $common_triggers[$trigger] = array(
                'count' => $count,
                'percentage' => round(($count / $total) * 100, 1)
            );
        }
        
        return $common_triggers;
    }
    
    /**
     * Generate pattern summary
     *
     * @param array $results Analysis results
     * @return array Summary
     */
    private function generate_pattern_summary($results) {
        $summary = array(
            'key_findings' => array(),
            'recommendations' => array(),
            'areas_of_concern' => array(),
            'positive_trends' => array()
        );
        
        // Analyze statistical results
        if (isset($results['statistical'])) {
            $stats = $results['statistical'];
            
            // Check severity
            if ($stats['basic']['severity']['mean'] > 7) {
                $summary['areas_of_concern'][] = __('High average severity indicates significant distress', 'spiralengine');
            }
            
            // Check frequency
            if ($stats['basic']['episodes_per_day']['average'] > 3) {
                $summary['areas_of_concern'][] = __('High episode frequency may indicate need for additional support', 'spiralengine');
            }
            
            // Check trends
            if (isset($stats['trends']['severity']['trend'])) {
                if ($stats['trends']['severity']['trend'] === 'improving') {
                    $summary['positive_trends'][] = __('Severity levels are showing improvement', 'spiralengine');
                } elseif ($stats['trends']['severity']['trend'] === 'worsening') {
                    $summary['areas_of_concern'][] = __('Severity levels are increasing over time', 'spiralengine');
                }
            }
        }
        
        // Add key findings based on patterns
        if (!empty($results['patterns'])) {
            foreach ($results['patterns'] as $pattern_type => $pattern_data) {
                if (!empty($pattern_data) && !isset($pattern_data['error'])) {
                    $summary['key_findings'][] = $this->summarize_pattern($pattern_type, $pattern_data);
                }
            }
        }
        
        // Generate recommendations
        $summary['recommendations'] = $this->generate_recommendations($results);
        
        return $summary;
    }
    
    /**
     * Summarize a specific pattern
     *
     * @param string $pattern_type Pattern type
     * @param array $pattern_data Pattern data
     * @return string Summary
     */
    private function summarize_pattern($pattern_type, $pattern_data) {
        switch ($pattern_type) {
            case 'temporal':
                if (isset($pattern_data['peak_times'])) {
                    return sprintf(
                        __('Peak activity occurs during %s', 'spiralengine'),
                        $pattern_data['peak_times']['time_period']['label']
                    );
                }
                break;
                
            case 'trigger':
                if (isset($pattern_data['common_triggers']) && !empty($pattern_data['common_triggers'])) {
                    $top_trigger = array_key_first($pattern_data['common_triggers']);
                    return sprintf(
                        __('Most common trigger: %s (%s%% of episodes)', 'spiralengine'),
                        $top_trigger,
                        $pattern_data['common_triggers'][$top_trigger]['percentage']
                    );
                }
                break;
                
            case 'improvement':
                if (isset($pattern_data['overall_trend'])) {
                    return sprintf(
                        __('Overall improvement score: %d/100', 'spiralengine'),
                        $pattern_data['overall_trend']
                    );
                }
                break;
        }
        
        return '';
    }
    
    /**
     * Generate recommendations based on patterns
     *
     * @param array $results Analysis results
     * @return array Recommendations
     */
    private function generate_recommendations($results) {
        $recommendations = array();
        
        // Time-based recommendations
        if (isset($results['patterns']['temporal']['peak_times'])) {
            $peak_hour = $results['patterns']['temporal']['peak_times']['hour']['value'];
            
            if ($peak_hour >= 22 || $peak_hour <= 2) {
                $recommendations[] = array(
                    'type' => 'sleep',
                    'message' => __('Episodes frequently occur late at night. Consider establishing a calming bedtime routine.', 'spiralengine'),
                    'priority' => 'high'
                );
            }
        }
        
        // Severity-based recommendations
        if (isset($results['statistical']['basic']['severity']['mean']) && 
            $results['statistical']['basic']['severity']['mean'] > 6) {
            $recommendations[] = array(
                'type' => 'support',
                'message' => __('High severity levels suggest professional support could be beneficial.', 'spiralengine'),
                'priority' => 'high'
            );
        }
        
        // Coping recommendations
        $coping_rate = 0;
        if (isset($results['statistical']['basic']['widget_usage']['coping-logger'])) {
            $total_episodes = $results['period']['episode_count'];
            $coping_episodes = $results['statistical']['basic']['widget_usage']['coping-logger'];
            $coping_rate = ($coping_episodes / $total_episodes) * 100;
        }
        
        if ($coping_rate < 20) {
            $recommendations[] = array(
                'type' => 'coping',
                'message' => __('Try using the Coping Skills Logger more frequently to track what helps you feel better.', 'spiralengine'),
                'priority' => 'medium'
            );
        }
        
        return $recommendations;
    }
    
    /**
     * Cache analysis results
     *
     * @param int $user_id User ID
     * @param array $results Results to cache
     * @return bool Success
     */
    private function cache_results($user_id, $results) {
        $cache_key = 'spiralengine_patterns_' . $user_id;
        $cache_duration = 3600; // 1 hour
        
        return set_transient($cache_key, $results, $cache_duration);
    }
    
    /**
     * Get cached results
     *
     * @param int $user_id User ID
     * @return array|false Cached results or false
     */
    public function get_cached_results($user_id) {
        $cache_key = 'spiralengine_patterns_' . $user_id;
        return get_transient($cache_key);
    }
}


<?php
/**
 * Episode Correlation Engine - Detects and analyzes correlations between episode types
 * 
 * @package    SpiralEngine
 * @subpackage Episodes
 * @file       includes/episodes/class-spiralengine-correlations.php
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Correlation Engine for Episode Framework
 * 
 * This class handles detection, analysis, and storage of correlations between
 * different episode types. It identifies patterns like anxiety leading to
 * overthinking, or poor sleep correlating with depression episodes.
 */
class SPIRAL_Correlation_Engine {
    
    /**
     * Minimum episodes required for correlation analysis
     * @var int
     */
    private $min_episodes_for_correlation = 5;
    
    /**
     * Time window for correlation detection (days)
     * @var int
     */
    private $correlation_window_days = 7;
    
    /**
     * Minimum correlation strength to save
     * @var float
     */
    private $min_correlation_strength = 0.3;
    
    /**
     * Correlation types
     * @var array
     */
    private $correlation_types = array(
        'precedes' => array(
            'name' => 'Precedes',
            'description' => 'Episode A typically occurs before Episode B',
            'time_range' => array(1, 72) // 1-72 hours
        ),
        'follows' => array(
            'name' => 'Follows',
            'description' => 'Episode A typically occurs after Episode B',
            'time_range' => array(1, 72)
        ),
        'concurrent' => array(
            'name' => 'Concurrent',
            'description' => 'Episodes occur at the same time',
            'time_range' => array(0, 4) // 0-4 hours
        ),
        'triggers' => array(
            'name' => 'Triggers',
            'description' => 'Episode A appears to trigger Episode B',
            'time_range' => array(0, 24)
        ),
        'triggered_by' => array(
            'name' => 'Triggered By',
            'description' => 'Episode A is triggered by Episode B',
            'time_range' => array(0, 24)
        )
    );
    
    /**
     * AI service instance
     * @var SPIRAL_Episode_AI_Service
     */
    private $ai_service;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->init();
    }
    
    /**
     * Initialize correlation engine
     */
    private function init() {
        // Initialize AI service if available
        if (class_exists('SPIRAL_Episode_AI_Service')) {
            $this->ai_service = new SPIRAL_Episode_AI_Service();
        }
        
        // Schedule correlation detection
        add_action('spiral_check_correlations', array($this, 'detect_correlations'), 10, 1);
        
        // Schedule daily correlation analysis
        if (!wp_next_scheduled('spiral_daily_correlation_analysis')) {
            wp_schedule_event(time(), 'daily', 'spiral_daily_correlation_analysis');
        }
        add_action('spiral_daily_correlation_analysis', array($this, 'run_daily_analysis'));
    }
    
    /**
     * Detect correlations after new episode
     * 
     * @param int $episode_id Episode ID
     */
    public function detect_correlations($episode_id) {
        $episode = $this->get_episode($episode_id);
        if (!$episode) {
            return;
        }
        
        $user_id = $episode['user_id'];
        $episode_type = $episode['episode_type'];
        
        // Check if user has enough episodes for correlation
        if (!$this->has_enough_episodes($user_id)) {
            return;
        }
        
        // Get other episodes within correlation window
        $nearby_episodes = $this->get_nearby_episodes(
            $user_id,
            $episode['episode_date'],
            $this->correlation_window_days
        );
        
        if (empty($nearby_episodes)) {
            return;
        }
        
        // Get episode registry
        $registry = SPIRAL_Episode_Registry::get_instance();
        
        foreach ($nearby_episodes as $other_episode) {
            // Skip same episode
            if ($other_episode['episode_id'] === $episode_id) {
                continue;
            }
            
            // Check if these types can correlate
            if (!$registry->can_correlate($episode_type, $other_episode['episode_type'])) {
                continue;
            }
            
            // Calculate correlation
            $correlation = $this->calculate_correlation($episode, $other_episode);
            
            // Save if significant
            if ($correlation['strength'] >= $this->min_correlation_strength) {
                $this->save_correlation($correlation);
                
                // Check if this creates a pattern
                $this->check_correlation_pattern($user_id, $correlation);
            }
        }
        
        // Run AI analysis if available
        if ($this->ai_service) {
            $this->run_ai_correlation_analysis($user_id, $episode_id);
        }
    }
    
    /**
     * Calculate correlation between two episodes
     * 
     * @param array $episode1 First episode
     * @param array $episode2 Second episode
     * @return array Correlation data
     */
    private function calculate_correlation($episode1, $episode2) {
        // Calculate time difference
        $time_diff = $this->calculate_time_difference(
            $episode1['episode_date'],
            $episode2['episode_date']
        );
        
        // Determine correlation type
        $correlation_type = $this->determine_correlation_type($time_diff);
        
        // Calculate correlation strength
        $strength = $this->calculate_correlation_strength(
            $episode1,
            $episode2,
            $time_diff,
            $correlation_type
        );
        
        // Calculate confidence
        $confidence = $this->calculate_confidence(
            $episode1['user_id'],
            $episode1['episode_type'],
            $episode2['episode_type'],
            $correlation_type
        );
        
        return array(
            'user_id' => $episode1['user_id'],
            'primary_episode_id' => $episode1['episode_id'],
            'primary_type' => $episode1['episode_type'],
            'related_episode_id' => $episode2['episode_id'],
            'related_type' => $episode2['episode_type'],
            'correlation_type' => $correlation_type,
            'time_offset_hours' => $time_diff['hours'],
            'correlation_strength' => $strength,
            'confidence_score' => $confidence,
            'factors' => $this->analyze_correlation_factors($episode1, $episode2)
        );
    }
    
    /**
     * Calculate time difference between episodes
     * 
     * @param string $date1 First date
     * @param string $date2 Second date
     * @return array Time difference data
     */
    private function calculate_time_difference($date1, $date2) {
        $datetime1 = new DateTime($date1);
        $datetime2 = new DateTime($date2);
        $interval = $datetime1->diff($datetime2);
        
        $hours = ($interval->days * 24) + $interval->h + ($interval->i / 60);
        
        return array(
            'hours' => $interval->invert ? -$hours : $hours,
            'absolute_hours' => abs($hours),
            'days' => $interval->days,
            'direction' => $interval->invert ? 'before' : 'after'
        );
    }
    
    /**
     * Determine correlation type based on time difference
     * 
     * @param array $time_diff Time difference data
     * @return string Correlation type
     */
    private function determine_correlation_type($time_diff) {
        $hours = abs($time_diff['hours']);
        
        // Concurrent - within 4 hours
        if ($hours <= 4) {
            return 'concurrent';
        }
        
        // Directional correlations
        if ($time_diff['hours'] < 0) {
            // Episode 1 occurred before Episode 2
            if ($hours <= 24) {
                return 'triggers';
            } else {
                return 'precedes';
            }
        } else {
            // Episode 1 occurred after Episode 2
            if ($hours <= 24) {
                return 'triggered_by';
            } else {
                return 'follows';
            }
        }
    }
    
    /**
     * Calculate correlation strength
     * 
     * @param array $episode1 First episode
     * @param array $episode2 Second episode
     * @param array $time_diff Time difference
     * @param string $correlation_type Correlation type
     * @return float Strength score
     */
    private function calculate_correlation_strength($episode1, $episode2, $time_diff, $correlation_type) {
        $strength = 0;
        
        // Base strength from registry
        $registry = SPIRAL_Episode_Registry::get_instance();
        $base_strength = $registry->get_correlation_strength(
            $episode1['episode_type'],
            $episode2['episode_type']
        );
        $strength += $base_strength * 0.4;
        
        // Time proximity factor
        $time_factor = $this->calculate_time_proximity_factor($time_diff['absolute_hours']);
        $strength += $time_factor * 0.2;
        
        // Severity correlation
        $severity_correlation = $this->calculate_severity_correlation(
            $episode1['severity_score'],
            $episode2['severity_score']
        );
        $strength += $severity_correlation * 0.2;
        
        // Trigger similarity
        if ($episode1['trigger_category'] && $episode2['trigger_category']) {
            if ($episode1['trigger_category'] === $episode2['trigger_category']) {
                $strength += 0.1;
            }
        }
        
        // Historical pattern strength
        $historical_strength = $this->get_historical_correlation_strength(
            $episode1['user_id'],
            $episode1['episode_type'],
            $episode2['episode_type']
        );
        $strength += $historical_strength * 0.1;
        
        return min($strength, 1.0);
    }
    
    /**
     * Calculate time proximity factor
     * 
     * @param float $hours Hours between episodes
     * @return float Proximity factor (0-1)
     */
    private function calculate_time_proximity_factor($hours) {
        if ($hours <= 1) return 1.0;
        if ($hours <= 4) return 0.9;
        if ($hours <= 12) return 0.8;
        if ($hours <= 24) return 0.7;
        if ($hours <= 48) return 0.5;
        if ($hours <= 72) return 0.3;
        return 0.1;
    }
    
    /**
     * Calculate severity correlation
     * 
     * @param float $severity1 First episode severity
     * @param float $severity2 Second episode severity
     * @return float Correlation score
     */
    private function calculate_severity_correlation($severity1, $severity2) {
        $diff = abs($severity1 - $severity2);
        
        if ($diff <= 1) return 0.9;
        if ($diff <= 2) return 0.7;
        if ($diff <= 3) return 0.5;
        if ($diff <= 4) return 0.3;
        return 0.1;
    }
    
    /**
     * Calculate confidence score
     * 
     * @param int $user_id User ID
     * @param string $type1 First episode type
     * @param string $type2 Second episode type
     * @param string $correlation_type Correlation type
     * @return float Confidence score
     */
    private function calculate_confidence($user_id, $type1, $type2, $correlation_type) {
        global $wpdb;
        
        // Count previous occurrences
        $count = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) 
            FROM {$wpdb->prefix}spiralengine_episode_correlations
            WHERE user_id = %d
                AND primary_type = %s
                AND related_type = %s
                AND correlation_type = %s
        ", $user_id, $type1, $type2, $correlation_type));
        
        // Base confidence on occurrence count
        if ($count >= 10) return 0.95;
        if ($count >= 7) return 0.85;
        if ($count >= 5) return 0.75;
        if ($count >= 3) return 0.65;
        if ($count >= 2) return 0.55;
        return 0.45;
    }
    
    /**
     * Analyze correlation factors
     * 
     * @param array $episode1 First episode
     * @param array $episode2 Second episode
     * @return array Correlation factors
     */
    private function analyze_correlation_factors($episode1, $episode2) {
        $factors = array();
        
        // Time of day similarity
        $hour1 = date('G', strtotime($episode1['episode_date']));
        $hour2 = date('G', strtotime($episode2['episode_date']));
        if (abs($hour1 - $hour2) <= 2) {
            $factors[] = 'similar_time_of_day';
        }
        
        // Day of week
        $dow1 = date('w', strtotime($episode1['episode_date']));
        $dow2 = date('w', strtotime($episode2['episode_date']));
        if ($dow1 === $dow2) {
            $factors[] = 'same_day_of_week';
        }
        
        // Location similarity
        if ($episode1['location'] && $episode2['location'] && 
            $episode1['location'] === $episode2['location']) {
            $factors[] = 'same_location';
        }
        
        // Biological factors
        if ($episode1['has_biological_factors'] && $episode2['has_biological_factors']) {
            $factors[] = 'biological_factors_present';
        }
        
        return $factors;
    }
    
    /**
     * Save correlation to database
     * 
     * @param array $correlation Correlation data
     * @return int|false Correlation ID or false
     */
    private function save_correlation($correlation) {
        global $wpdb;
        
        // Check if correlation already exists
        $existing = $wpdb->get_var($wpdb->prepare("
            SELECT correlation_id 
            FROM {$wpdb->prefix}spiralengine_episode_correlations
            WHERE primary_episode_id = %d AND related_episode_id = %d
        ", $correlation['primary_episode_id'], $correlation['related_episode_id']));
        
        if ($existing) {
            // Update existing correlation
            return $wpdb->update(
                $wpdb->prefix . 'spiralengine_episode_correlations',
                array(
                    'correlation_strength' => $correlation['correlation_strength'],
                    'confidence_score' => $correlation['confidence_score']
                ),
                array('correlation_id' => $existing),
                array('%s', '%s'),
                array('%d')
            );
        }
        
        // Insert new correlation
        $result = $wpdb->insert(
            $wpdb->prefix . 'spiralengine_episode_correlations',
            array(
                'user_id' => $correlation['user_id'],
                'primary_episode_id' => $correlation['primary_episode_id'],
                'primary_type' => $correlation['primary_type'],
                'related_episode_id' => $correlation['related_episode_id'],
                'related_type' => $correlation['related_type'],
                'correlation_type' => $correlation['correlation_type'],
                'time_offset_hours' => $correlation['time_offset_hours'],
                'correlation_strength' => $correlation['correlation_strength'],
                'confidence_score' => $correlation['confidence_score'],
                'discovered_date' => current_time('mysql')
            ),
            array('%d', '%d', '%s', '%d', '%s', '%s', '%d', '%s', '%s', '%s')
        );
        
        if ($result) {
            $correlation_id = $wpdb->insert_id;
            
            // Fire action for new correlation
            do_action('spiral_correlation_discovered', $correlation_id, $correlation);
            
            return $correlation_id;
        }
        
        return false;
    }
    
    /**
     * Check if correlation creates a pattern
     * 
     * @param int $user_id User ID
     * @param array $correlation Correlation data
     */
    private function check_correlation_pattern($user_id, $correlation) {
        global $wpdb;
        
        // Count similar correlations
        $count = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) 
            FROM {$wpdb->prefix}spiralengine_episode_correlations
            WHERE user_id = %d
                AND primary_type = %s
                AND related_type = %s
                AND correlation_type = %s
                AND correlation_strength >= %f
        ", 
            $user_id,
            $correlation['primary_type'],
            $correlation['related_type'],
            $correlation['correlation_type'],
            $this->min_correlation_strength
        ));
        
        // Pattern thresholds
        $pattern_thresholds = array(
            3 => 'emerging',
            5 => 'established',
            10 => 'strong',
            20 => 'persistent'
        );
        
        foreach ($pattern_thresholds as $threshold => $pattern_type) {
            if ($count === $threshold) {
                $this->create_correlation_pattern($user_id, $correlation, $pattern_type);
                break;
            }
        }
    }
    
    /**
     * Create correlation pattern
     * 
     * @param int $user_id User ID
     * @param array $correlation Correlation data
     * @param string $pattern_type Pattern type
     */
    private function create_correlation_pattern($user_id, $correlation, $pattern_type) {
        global $wpdb;
        
        $pattern_data = array(
            'pattern_type' => $pattern_type,
            'correlation_count' => $wpdb->get_var($wpdb->prepare("
                SELECT COUNT(*) FROM {$wpdb->prefix}spiralengine_episode_correlations
                WHERE user_id = %d AND primary_type = %s AND related_type = %s
            ", $user_id, $correlation['primary_type'], $correlation['related_type'])),
            'average_strength' => $wpdb->get_var($wpdb->prepare("
                SELECT AVG(correlation_strength) FROM {$wpdb->prefix}spiralengine_episode_correlations
                WHERE user_id = %d AND primary_type = %s AND related_type = %s
            ", $user_id, $correlation['primary_type'], $correlation['related_type'])),
            'average_time_offset' => $wpdb->get_var($wpdb->prepare("
                SELECT AVG(time_offset_hours) FROM {$wpdb->prefix}spiralengine_episode_correlations
                WHERE user_id = %d AND primary_type = %s AND related_type = %s
            ", $user_id, $correlation['primary_type'], $correlation['related_type']))
        );
        
        // Save pattern
        $wpdb->insert(
            $wpdb->prefix . 'spiralengine_patterns',
            array(
                'user_id' => $user_id,
                'pattern_type' => 'correlation',
                'pattern_subtype' => $correlation['primary_type'] . '_to_' . $correlation['related_type'],
                'pattern_data' => json_encode($pattern_data),
                'confidence_score' => $correlation['confidence_score'],
                'occurrence_count' => $pattern_data['correlation_count'],
                'first_detected' => current_time('mysql'),
                'last_detected' => current_time('mysql')
            )
        );
        
        // Notify user of pattern
        do_action('spiral_correlation_pattern_detected', $user_id, $correlation, $pattern_type);
    }
    
    /**
     * Get user correlations
     * 
     * @param int $user_id User ID
     * @param int $limit Result limit
     * @return array Correlations
     */
    public function get_user_correlations($user_id, $limit = 10) {
        global $wpdb;
        
        $results = $wpdb->get_results($wpdb->prepare("
            SELECT 
                c.*,
                e1.episode_date as primary_date,
                e1.severity_score as primary_severity,
                e2.episode_date as related_date,
                e2.severity_score as related_severity,
                COUNT(*) OVER (PARTITION BY c.primary_type, c.related_type) as occurrence_count
            FROM {$wpdb->prefix}spiralengine_episode_correlations c
            JOIN {$wpdb->prefix}spiralengine_episodes e1 ON c.primary_episode_id = e1.episode_id
            JOIN {$wpdb->prefix}spiralengine_episodes e2 ON c.related_episode_id = e2.episode_id
            WHERE c.user_id = %d
            ORDER BY c.correlation_strength DESC, c.discovered_date DESC
            LIMIT %d
        ", $user_id, $limit), ARRAY_A);
        
        return $this->format_correlation_insights($results);
    }
    
    /**
     * Format correlation insights
     * 
     * @param array $correlations Raw correlations
     * @return array Formatted insights
     */
    private function format_correlation_insights($correlations) {
        $insights = array();
        $registry = SPIRAL_Episode_Registry::get_instance();
        
        // Group by correlation pair
        $grouped = array();
        foreach ($correlations as $correlation) {
            $key = $correlation['primary_type'] . '_' . $correlation['related_type'] . '_' . $correlation['correlation_type'];
            if (!isset($grouped[$key])) {
                $grouped[$key] = array(
                    'correlations' => array(),
                    'summary' => array(
                        'primary_type' => $correlation['primary_type'],
                        'related_type' => $correlation['related_type'],
                        'correlation_type' => $correlation['correlation_type'],
                        'occurrence_count' => 0,
                        'average_strength' => 0,
                        'average_time_offset' => 0
                    )
                );
            }
            $grouped[$key]['correlations'][] = $correlation;
        }
        
        // Process each group
        foreach ($grouped as $group) {
            $summary = $group['summary'];
            $correlations = $group['correlations'];
            
            // Calculate averages
            $total_strength = 0;
            $total_offset = 0;
            foreach ($correlations as $corr) {
                $total_strength += $corr['correlation_strength'];
                $total_offset += abs($corr['time_offset_hours']);
            }
            
            $summary['occurrence_count'] = count($correlations);
            $summary['average_strength'] = $total_strength / count($correlations);
            $summary['average_time_offset'] = $total_offset / count($correlations);
            
            // Get type information
            $primary_info = $registry->get_episode_type($summary['primary_type']);
            $related_info = $registry->get_episode_type($summary['related_type']);
            
            // Create insight
            $insight = array(
                'title' => $this->generate_correlation_title($summary, $primary_info, $related_info),
                'description' => $this->generate_correlation_description($summary, $primary_info, $related_info),
                'strength' => $summary['average_strength'],
                'confidence' => $this->calculate_insight_confidence($summary),
                'action_items' => $this->generate_action_items($summary, $primary_info, $related_info),
                'data' => $summary
            );
            
            $insights[] = $insight;
        }
        
        // Sort by strength and confidence
        usort($insights, function($a, $b) {
            $score_a = $a['strength'] * $a['confidence'];
            $score_b = $b['strength'] * $b['confidence'];
            return $score_b <=> $score_a;
        });
        
        return $insights;
    }
    
    /**
     * Generate correlation title
     * 
     * @param array $summary Correlation summary
     * @param array $primary_info Primary type info
     * @param array $related_info Related type info
     * @return string Title
     */
    private function generate_correlation_title($summary, $primary_info, $related_info) {
        $templates = array(
            'triggers' => '%s Often Triggers %s',
            'triggered_by' => '%s Often Triggered by %s',
            'precedes' => '%s Frequently Precedes %s',
            'follows' => '%s Frequently Follows %s',
            'concurrent' => '%s and %s Often Occur Together'
        );
        
        $template = $templates[$summary['correlation_type']] ?? '%s Correlates with %s';
        
        return sprintf(
            $template,
            $primary_info['display_name'],
            $related_info['display_name']
        );
    }
    
    /**
     * Generate correlation description
     * 
     * @param array $summary Correlation summary
     * @param array $primary_info Primary type info
     * @param array $related_info Related type info
     * @return string Description
     */
    private function generate_correlation_description($summary, $primary_info, $related_info) {
        $strength_text = $this->get_strength_text($summary['average_strength']);
        $time_text = $this->format_time_offset($summary['average_time_offset']);
        
        $templates = array(
            'triggers' => 'There is a %s correlation where %s episodes appear to trigger %s episodes, typically within %s.',
            'triggered_by' => 'There is a %s correlation where %s episodes are triggered by %s episodes, typically within %s.',
            'precedes' => 'There is a %s pattern where %s episodes occur before %s episodes, usually by about %s.',
            'follows' => 'There is a %s pattern where %s episodes follow %s episodes, typically after %s.',
            'concurrent' => 'There is a %s correlation where %s and %s episodes occur at nearly the same time.'
        );
        
        $template = $templates[$summary['correlation_type']] ?? 'There is a %s correlation between %s and %s episodes.';
        
        if ($summary['correlation_type'] === 'concurrent') {
            return sprintf(
                $template,
                $strength_text,
                $primary_info['display_name'],
                $related_info['display_name']
            );
        }
        
        return sprintf(
            $template,
            $strength_text,
            $primary_info['display_name'],
            $related_info['display_name'],
            $time_text
        );
    }
    
    /**
     * Get strength text
     * 
     * @param float $strength Correlation strength
     * @return string Strength description
     */
    private function get_strength_text($strength) {
        if ($strength >= 0.8) return __('very strong', 'spiral');
        if ($strength >= 0.6) return __('strong', 'spiral');
        if ($strength >= 0.4) return __('moderate', 'spiral');
        return __('weak', 'spiral');
    }
    
    /**
     * Format time offset
     * 
     * @param float $hours Average hours
     * @return string Formatted time
     */
    private function format_time_offset($hours) {
        if ($hours < 1) return __('less than an hour', 'spiral');
        if ($hours < 2) return __('about an hour', 'spiral');
        if ($hours < 24) return sprintf(__('%d hours', 'spiral'), round($hours));
        if ($hours < 48) return __('about a day', 'spiral');
        return sprintf(__('%d days', 'spiral'), round($hours / 24));
    }
    
    /**
     * Generate action items
     * 
     * @param array $summary Correlation summary
     * @param array $primary_info Primary type info
     * @param array $related_info Related type info
     * @return array Action items
     */
    private function generate_action_items($summary, $primary_info, $related_info) {
        $actions = array();
        
        if ($summary['correlation_type'] === 'triggers' || $summary['correlation_type'] === 'precedes') {
            $actions[] = sprintf(
                __('Monitor for early signs of %s to prevent %s', 'spiral'),
                $primary_info['display_name'],
                $related_info['display_name']
            );
            
            $actions[] = sprintf(
                __('Implement coping strategies for %s as soon as you notice %s symptoms', 'spiral'),
                $related_info['display_name'],
                $primary_info['display_name']
            );
        }
        
        if ($summary['correlation_type'] === 'concurrent') {
            $actions[] = sprintf(
                __('Address both %s and %s together with comprehensive strategies', 'spiral'),
                $primary_info['display_name'],
                $related_info['display_name']
            );
        }
        
        // Add time-based recommendations
        if ($summary['average_time_offset'] < 24) {
            $actions[] = __('Consider keeping a detailed log of triggers and symptoms throughout the day', 'spiral');
        } else {
            $actions[] = __('Look for patterns in your weekly routine that might contribute to this correlation', 'spiral');
        }
        
        return $actions;
    }
    
    /**
     * Calculate insight confidence
     * 
     * @param array $summary Correlation summary
     * @return float Confidence score
     */
    private function calculate_insight_confidence($summary) {
        $confidence = 0.5; // Base confidence
        
        // Occurrence factor
        if ($summary['occurrence_count'] >= 20) $confidence += 0.3;
        elseif ($summary['occurrence_count'] >= 10) $confidence += 0.2;
        elseif ($summary['occurrence_count'] >= 5) $confidence += 0.1;
        
        // Strength factor
        $confidence += $summary['average_strength'] * 0.2;
        
        return min($confidence, 1.0);
    }
    
    /**
     * Contribute to unified forecast
     * 
     * @param int $user_id User ID
     * @param string $forecast_window Forecast window
     * @return array Risk factors
     */
    public function get_correlation_risk_factors($user_id, $forecast_window) {
        $correlations = $this->get_strong_correlations($user_id);
        $risk_factors = array();
        
        foreach ($correlations as $correlation) {
            // Get risk for primary type
            $primary_risk = $this->get_episode_risk($user_id, $correlation['primary_type'], $forecast_window);
            
            if ($primary_risk > 0.5) {
                $risk_factors[] = array(
                    'factor' => sprintf(
                        '%s episodes may trigger %s (%.0f%% correlation)',
                        $correlation['primary_type'],
                        $correlation['related_type'],
                        $correlation['strength'] * 100
                    ),
                    'risk_increase' => $primary_risk * $correlation['strength'],
                    'time_offset' => $correlation['average_offset_hours'],
                    'confidence' => $correlation['confidence']
                );
            }
        }
        
        return $risk_factors;
    }
    
    /**
     * Get strong correlations for user
     * 
     * @param int $user_id User ID
     * @return array Strong correlations
     */
    private function get_strong_correlations($user_id) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare("
            SELECT 
                primary_type,
                related_type,
                correlation_type,
                AVG(correlation_strength) as strength,
                AVG(confidence_score) as confidence,
                AVG(time_offset_hours) as average_offset_hours,
                COUNT(*) as occurrence_count
            FROM {$wpdb->prefix}spiralengine_episode_correlations
            WHERE user_id = %d
                AND correlation_strength >= 0.5
                AND discovered_date >= DATE_SUB(NOW(), INTERVAL 90 DAY)
            GROUP BY primary_type, related_type, correlation_type
            HAVING occurrence_count >= 3
            ORDER BY strength DESC
        ", $user_id), ARRAY_A);
    }
    
    /**
     * Get episode risk
     * 
     * @param int $user_id User ID
     * @param string $episode_type Episode type
     * @param string $forecast_window Forecast window
     * @return float Risk score
     */
    private function get_episode_risk($user_id, $episode_type, $forecast_window) {
        // This would integrate with the pattern detector
        $pattern_detector = new SPIRAL_Pattern_Detector($episode_type);
        return $pattern_detector->calculate_risk_score($user_id, $forecast_window);
    }
    
    /**
     * Run AI correlation analysis
     * 
     * @param int $user_id User ID
     * @param int $episode_id Episode ID
     */
    private function run_ai_correlation_analysis($user_id, $episode_id) {
        if (!$this->ai_service) {
            return;
        }
        
        try {
            $correlations = $this->ai_service->detect_correlations($user_id, $this->correlation_window_days);
            
            if (!empty($correlations)) {
                foreach ($correlations as $correlation) {
                    if ($correlation['confidence'] >= 0.7) {
                        $this->save_ai_correlation($user_id, $correlation);
                    }
                }
            }
        } catch (Exception $e) {
            error_log('AI correlation analysis failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Save AI-detected correlation
     * 
     * @param int $user_id User ID
     * @param array $correlation AI correlation data
     */
    private function save_ai_correlation($user_id, $correlation) {
        global $wpdb;
        
        $wpdb->insert(
            $wpdb->prefix . 'spiralengine_ai_correlations',
            array(
                'user_id' => $user_id,
                'primary_type' => $correlation['primary_type'],
                'related_type' => $correlation['related_type'],
                'correlation_strength' => $correlation['strength'],
                'time_offset_hours' => $correlation['time_offset'],
                'direction' => $correlation['direction'],
                'evidence' => json_encode($correlation['evidence']),
                'ai_confidence' => $correlation['confidence'],
                'discovered_at' => current_time('mysql')
            )
        );
    }
    
    /**
     * Get episode data
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
     * Get nearby episodes
     * 
     * @param int $user_id User ID
     * @param string $episode_date Episode date
     * @param int $window_days Window in days
     * @return array Episodes
     */
    private function get_nearby_episodes($user_id, $episode_date, $window_days) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare("
            SELECT * FROM {$wpdb->prefix}spiralengine_episodes
            WHERE user_id = %d
                AND episode_date BETWEEN 
                    DATE_SUB(%s, INTERVAL %d DAY) 
                    AND DATE_ADD(%s, INTERVAL %d DAY)
            ORDER BY episode_date ASC
        ", $user_id, $episode_date, $window_days, $episode_date, $window_days), ARRAY_A);
    }
    
    /**
     * Check if user has enough episodes
     * 
     * @param int $user_id User ID
     * @return bool
     */
    private function has_enough_episodes($user_id) {
        global $wpdb;
        
        $count = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) FROM {$wpdb->prefix}spiralengine_episodes
            WHERE user_id = %d
        ", $user_id));
        
        return $count >= $this->min_episodes_for_correlation;
    }
    
    /**
     * Get historical correlation strength
     * 
     * @param int $user_id User ID
     * @param string $type1 First type
     * @param string $type2 Second type
     * @return float Historical strength
     */
    private function get_historical_correlation_strength($user_id, $type1, $type2) {
        global $wpdb;
        
        $avg_strength = $wpdb->get_var($wpdb->prepare("
            SELECT AVG(correlation_strength)
            FROM {$wpdb->prefix}spiralengine_episode_correlations
            WHERE user_id = %d
                AND ((primary_type = %s AND related_type = %s)
                    OR (primary_type = %s AND related_type = %s))
        ", $user_id, $type1, $type2, $type2, $type1));
        
        return $avg_strength ? floatval($avg_strength) : 0;
    }
    
    /**
     * Run daily correlation analysis
     */
    public function run_daily_analysis() {
        global $wpdb;
        
        // Get active users
        $active_users = $wpdb->get_col("
            SELECT DISTINCT user_id 
            FROM {$wpdb->prefix}spiralengine_episodes
            WHERE episode_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        ");
        
        foreach ($active_users as $user_id) {
            $this->analyze_user_correlations($user_id);
        }
        
        // Clean up old correlations
        $this->cleanup_old_correlations();
    }
    
    /**
     * Analyze user correlations
     * 
     * @param int $user_id User ID
     */
    private function analyze_user_correlations($user_id) {
        // Re-analyze recent episodes for missed correlations
        $recent_episodes = $this->get_recent_episodes($user_id, 30);
        
        foreach ($recent_episodes as $episode) {
            $this->detect_correlations($episode['episode_id']);
        }
    }
    
    /**
     * Get recent episodes
     * 
     * @param int $user_id User ID
     * @param int $days Number of days
     * @return array Episodes
     */
    private function get_recent_episodes($user_id, $days) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare("
            SELECT episode_id FROM {$wpdb->prefix}spiralengine_episodes
            WHERE user_id = %d 
                AND episode_date >= DATE_SUB(NOW(), INTERVAL %d DAY)
            ORDER BY episode_date DESC
        ", $user_id, $days), ARRAY_A);
    }
    
    /**
     * Clean up old correlations
     */
    private function cleanup_old_correlations() {
        global $wpdb;
        
        // Remove weak correlations older than 180 days
        $wpdb->query("
            DELETE FROM {$wpdb->prefix}spiralengine_episode_correlations
            WHERE correlation_strength < 0.4
                AND discovered_date < DATE_SUB(NOW(), INTERVAL 180 DAY)
        ");
    }
}

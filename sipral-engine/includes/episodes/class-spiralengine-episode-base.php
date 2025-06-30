<?php
/**
 * Episode Base Class - Foundation for all episode widget types
 * 
 * @package    SpiralEngine
 * @subpackage Episodes
 * @file       includes/episodes/class-spiralengine-episode-base.php
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Base class for all episode widgets
 * 
 * This class provides the foundation for all episode logging widgets including
 * overthinking, anxiety, PTSD, depression, caregiver, panic, and dissociation.
 * It handles common functionality like saving, validation, correlation support,
 * and pattern integration.
 */
abstract class SPIRAL_Episode_Widget_Base extends SPIRAL_Widget_Base {
    
    /**
     * Episode type identifier - must be unique
     * @var string
     */
    protected $episode_type = '';
    
    /**
     * Episode configuration
     * @var array
     */
    protected $episode_config = array(
        'supports_quick_log' => true,
        'supports_detailed_log' => true,
        'correlatable' => true,
        'exportable' => true,
        'ai_enabled' => true
    );
    
    /**
     * Correlation settings
     * @var array
     */
    protected $correlation_config = array(
        'can_correlate_with' => array(), // Other episode types
        'correlation_strength' => 1.0,   // Weight in unified analysis
        'shares_triggers' => true,       // Can share trigger data
        'shares_biologicals' => true     // Can share biological data
    );
    
    /**
     * Constructor
     */
    public function __construct() {
        parent::__construct();
        $this->init_episode_framework();
    }
    
    /**
     * Initialize episode framework components
     */
    protected function init_episode_framework() {
        // Register hooks for episode framework
        add_action('spiral_episode_saved', array($this, 'handle_episode_saved'), 10, 2);
        add_filter('spiral_episode_validation', array($this, 'apply_validation_rules'), 10, 2);
        add_filter('spiral_episode_severity_calculation', array($this, 'apply_severity_rules'), 10, 2);
    }
    
    /**
     * Required abstract methods for all episode widgets
     */
    abstract public function get_episode_questions();
    abstract public function validate_episode_data($data);
    abstract public function calculate_severity_score($responses);
    abstract public function get_quick_log_fields();
    abstract public function save_episode_details($episode_id, $data);
    
    /**
     * Save episode to unified table
     * 
     * @param array $data Episode data
     * @return int|false Episode ID on success, false on failure
     */
    public function save_episode($data) {
        global $wpdb;
        
        // Validate data
        if (!$this->validate_episode_data($data)) {
            return false;
        }
        
        // Check user permissions
        if (!$this->can_user_log_episode()) {
            return false;
        }
        
        // Calculate severity
        $data['severity_score'] = $this->calculate_severity_score($data);
        
        // Prepare base episode data
        $episode_data = array(
            'user_id' => get_current_user_id(),
            'episode_type' => $this->episode_type,
            'episode_date' => isset($data['episode_date']) ? $data['episode_date'] : current_time('mysql'),
            'severity_score' => $data['severity_score'],
            'duration_minutes' => isset($data['duration']) ? intval($data['duration']) : null,
            'trigger_category' => isset($data['trigger_category']) ? sanitize_text_field($data['trigger_category']) : null,
            'trigger_details' => isset($data['trigger_details']) ? $this->encrypt_sensitive_data($data['trigger_details']) : null,
            'location' => isset($data['location']) ? sanitize_text_field($data['location']) : null,
            'has_biological_factors' => $this->has_biological_factors($data),
            'has_coping_data' => isset($data['coping_strategies']) && !empty($data['coping_strategies']),
            'has_pattern_data' => true, // Will be analyzed post-save
            'metadata_json' => json_encode($this->prepare_metadata($data)),
            'widget_version' => $this->widget_version,
            'logged_via' => isset($data['logged_via']) ? $data['logged_via'] : 'detailed'
        );
        
        // Save to unified episode table
        $result = $wpdb->insert(
            $wpdb->prefix . 'spiralengine_episodes',
            $episode_data,
            array(
                '%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s',
                '%d', '%d', '%d', '%s', '%s', '%s'
            )
        );
        
        if ($result === false) {
            $this->log_error('Failed to save episode', array(
                'error' => $wpdb->last_error,
                'data' => $episode_data
            ));
            return false;
        }
        
        $episode_id = $wpdb->insert_id;
        
        // Save type-specific details
        if (!$this->save_episode_details($episode_id, $data)) {
            // Rollback main episode if details fail
            $wpdb->delete(
                $wpdb->prefix . 'spiralengine_episodes',
                array('episode_id' => $episode_id),
                array('%d')
            );
            return false;
        }
        
        // Trigger post-save actions
        do_action('spiral_episode_saved', $episode_id, $this->episode_type);
        
        // Trigger correlation check
        if ($this->episode_config['correlatable']) {
            wp_schedule_single_event(time() + 5, 'spiral_check_correlations', array($episode_id));
        }
        
        // Trigger pattern analysis
        if ($this->episode_config['ai_enabled']) {
            wp_schedule_single_event(time() + 10, 'spiral_analyze_patterns', array($episode_id));
        }
        
        return $episode_id;
    }
    
    /**
     * Get episodes for correlation
     * 
     * @param int $user_id User ID
     * @param array $date_range Date range array
     * @return array Correlation data
     */
    public function get_correlation_data($user_id, $date_range) {
        return array(
            'episode_type' => $this->episode_type,
            'episodes' => $this->get_user_episodes($user_id, $date_range),
            'triggers' => $this->get_trigger_patterns($user_id),
            'severity_timeline' => $this->get_severity_timeline($user_id, $date_range),
            'biological_factors' => $this->get_biological_correlations($user_id)
        );
    }
    
    /**
     * Get user episodes within date range
     * 
     * @param int $user_id User ID
     * @param array $date_range Date range
     * @return array Episodes
     */
    protected function get_user_episodes($user_id, $date_range) {
        global $wpdb;
        
        $query = $wpdb->prepare("
            SELECT e.*, ed.*
            FROM {$wpdb->prefix}spiralengine_episodes e
            LEFT JOIN {$wpdb->prefix}spiralengine_{$this->episode_type}_details ed 
                ON e.episode_id = ed.episode_id
            WHERE e.user_id = %d 
                AND e.episode_type = %s
                AND e.episode_date BETWEEN %s AND %s
            ORDER BY e.episode_date DESC
        ",
            $user_id,
            $this->episode_type,
            $date_range['start'],
            $date_range['end']
        );
        
        $episodes = $wpdb->get_results($query, ARRAY_A);
        
        // Decrypt sensitive data
        foreach ($episodes as &$episode) {
            if (!empty($episode['trigger_details'])) {
                $episode['trigger_details'] = $this->decrypt_sensitive_data($episode['trigger_details']);
            }
            if (!empty($episode['metadata_json'])) {
                $episode['metadata'] = json_decode($episode['metadata_json'], true);
            }
        }
        
        return $episodes;
    }
    
    /**
     * Get trigger patterns for user
     * 
     * @param int $user_id User ID
     * @return array Trigger patterns
     */
    protected function get_trigger_patterns($user_id) {
        global $wpdb;
        
        $query = $wpdb->prepare("
            SELECT 
                trigger_category,
                COUNT(*) as occurrence_count,
                AVG(severity_score) as avg_severity,
                GROUP_CONCAT(DISTINCT DATE_FORMAT(episode_date, '%%w')) as day_patterns,
                GROUP_CONCAT(DISTINCT HOUR(episode_date)) as hour_patterns
            FROM {$wpdb->prefix}spiralengine_episodes
            WHERE user_id = %d 
                AND episode_type = %s
                AND trigger_category IS NOT NULL
                AND episode_date >= DATE_SUB(NOW(), INTERVAL 90 DAY)
            GROUP BY trigger_category
            HAVING occurrence_count >= 3
            ORDER BY occurrence_count DESC
        ",
            $user_id,
            $this->episode_type
        );
        
        return $wpdb->get_results($query, ARRAY_A);
    }
    
    /**
     * Get severity timeline
     * 
     * @param int $user_id User ID
     * @param array $date_range Date range
     * @return array Severity timeline data
     */
    protected function get_severity_timeline($user_id, $date_range) {
        global $wpdb;
        
        $query = $wpdb->prepare("
            SELECT 
                DATE(episode_date) as date,
                AVG(severity_score) as avg_severity,
                COUNT(*) as episode_count,
                MAX(severity_score) as max_severity
            FROM {$wpdb->prefix}spiralengine_episodes
            WHERE user_id = %d 
                AND episode_type = %s
                AND episode_date BETWEEN %s AND %s
            GROUP BY DATE(episode_date)
            ORDER BY date ASC
        ",
            $user_id,
            $this->episode_type,
            $date_range['start'],
            $date_range['end']
        );
        
        return $wpdb->get_results($query, ARRAY_A);
    }
    
    /**
     * Get biological correlations
     * 
     * @param int $user_id User ID
     * @return array Biological correlation data
     */
    protected function get_biological_correlations($user_id) {
        global $wpdb;
        
        // Get user's biological data preferences
        $user_meta = get_user_meta($user_id, 'spiral_biological_tracking', true);
        if (empty($user_meta) || !$user_meta['enabled']) {
            return array();
        }
        
        $correlations = array();
        
        // Sleep correlations
        $correlations['sleep'] = $this->analyze_sleep_correlations($user_id);
        
        // Menstrual cycle correlations (if applicable)
        if (isset($user_meta['menstrual_tracking']) && $user_meta['menstrual_tracking']) {
            $correlations['menstrual'] = $this->analyze_menstrual_correlations($user_id);
        }
        
        // Substance use correlations
        $correlations['substances'] = $this->analyze_substance_correlations($user_id);
        
        // Exercise correlations
        $correlations['exercise'] = $this->analyze_exercise_correlations($user_id);
        
        return $correlations;
    }
    
    /**
     * Contribute to unified forecast
     * 
     * @param int $user_id User ID
     * @param string $forecast_window Forecast window (7_day, 30_day)
     * @return array Forecast contribution
     */
    public function contribute_to_forecast($user_id, $forecast_window) {
        $pattern_detector = new SPIRAL_Pattern_Detector($this->episode_type);
        
        // Calculate risk factors
        $risk_factors = array(
            'recent_frequency' => $pattern_detector->get_recent_frequency($user_id, 14),
            'severity_trend' => $pattern_detector->get_severity_trend($user_id, 30),
            'trigger_exposure' => $this->estimate_trigger_exposure($user_id),
            'biological_risks' => $this->get_biological_risk_factors($user_id),
            'temporal_risks' => $this->get_temporal_risk_factors($user_id)
        );
        
        // Calculate risk score
        $risk_score = $pattern_detector->calculate_risk_score($user_id, $forecast_window, $risk_factors);
        
        return array(
            'episode_type' => $this->episode_type,
            'risk_score' => $risk_score,
            'confidence' => $pattern_detector->get_confidence_level(),
            'contributing_factors' => $risk_factors,
            'weight' => $this->correlation_config['correlation_strength'],
            'high_risk_periods' => $this->identify_high_risk_periods($user_id, $forecast_window),
            'recommendations' => $this->get_prevention_recommendations($risk_factors)
        );
    }
    
    /**
     * Check if user can log episodes
     * 
     * @return bool
     */
    protected function can_user_log_episode() {
        if (!is_user_logged_in()) {
            return false;
        }
        
        // Check membership level
        $user_id = get_current_user_id();
        $membership = $this->get_user_membership($user_id);
        
        // All membership levels can log episodes
        return !empty($membership);
    }
    
    /**
     * Prepare metadata for storage
     * 
     * @param array $data Raw episode data
     * @return array Prepared metadata
     */
    protected function prepare_metadata($data) {
        // Remove sensitive fields from metadata
        $sensitive_fields = array('trigger_details', 'thought_content', 'personal_notes');
        
        $metadata = array();
        foreach ($data as $key => $value) {
            if (!in_array($key, $sensitive_fields)) {
                $metadata[$key] = $value;
            }
        }
        
        // Add system metadata
        $metadata['logged_at'] = current_time('mysql');
        $metadata['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $metadata['ip_hash'] = md5($_SERVER['REMOTE_ADDR'] ?? '');
        
        return $metadata;
    }
    
    /**
     * Check if episode has biological factors
     * 
     * @param array $data Episode data
     * @return bool
     */
    protected function has_biological_factors($data) {
        $biological_fields = array(
            'sleep_hours', 'sleep_quality', 'menstrual_phase',
            'medication_taken', 'substance_use', 'physical_activity'
        );
        
        foreach ($biological_fields as $field) {
            if (!empty($data[$field])) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Encrypt sensitive data
     * 
     * @param string $data Data to encrypt
     * @return string Encrypted data
     */
    protected function encrypt_sensitive_data($data) {
        if (empty($data)) {
            return $data;
        }
        
        // Use WordPress salts for encryption
        $key = wp_salt('auth');
        $encrypted = openssl_encrypt($data, 'AES-256-CBC', $key, 0, substr($key, 0, 16));
        
        return base64_encode($encrypted);
    }
    
    /**
     * Decrypt sensitive data
     * 
     * @param string $data Encrypted data
     * @return string Decrypted data
     */
    protected function decrypt_sensitive_data($data) {
        if (empty($data)) {
            return $data;
        }
        
        $key = wp_salt('auth');
        $encrypted = base64_decode($data);
        $decrypted = openssl_decrypt($encrypted, 'AES-256-CBC', $key, 0, substr($key, 0, 16));
        
        return $decrypted;
    }
    
    /**
     * Handle episode saved event
     * 
     * @param int $episode_id Episode ID
     * @param string $episode_type Episode type
     */
    public function handle_episode_saved($episode_id, $episode_type) {
        if ($episode_type !== $this->episode_type) {
            return;
        }
        
        // Update user stats
        $this->update_user_episode_stats($episode_id);
        
        // Check for achievements
        $this->check_episode_achievements($episode_id);
        
        // Send notifications if needed
        $this->send_episode_notifications($episode_id);
    }
    
    /**
     * Log error
     * 
     * @param string $message Error message
     * @param array $context Context data
     */
    protected function log_error($message, $context = array()) {
        error_log(sprintf(
            '[SPIRAL Episode %s] %s | Context: %s',
            $this->episode_type,
            $message,
            json_encode($context)
        ));
    }
}

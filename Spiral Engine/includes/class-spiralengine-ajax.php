<?php
/**
 * SpiralEngine AJAX Handler
 * 
 * @package    SpiralEngine
 * @subpackage Core
 * @since      1.0.0
 */

// includes/class-spiralengine-ajax.php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * AJAX Handler Class
 * 
 * Handles all AJAX requests for dashboard updates, widget interactions,
 * and real-time validation
 */
class SpiralEngine_AJAX {
    
    /**
     * Instance of this class
     * 
     * @var SpiralEngine_AJAX
     */
    private static $instance = null;
    
    /**
     * Time zone manager instance
     * 
     * @var SPIRALENGINE_Time_Zone_Manager
     */
    private $time_manager;
    
    /**
     * Security handler instance
     * 
     * @var SpiralEngine_Security
     */
    private $security;
    
    /**
     * Database handler instance
     * 
     * @var SpiralEngine_Database
     */
    private $database;
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->time_manager = new SPIRALENGINE_Time_Zone_Manager();
        $this->security = new SpiralEngine_Security();
        $this->database = new SpiralEngine_Database();
        
        $this->register_ajax_handlers();
    }
    
    /**
     * Get instance
     * 
     * @return SpiralEngine_AJAX
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Register AJAX handlers
     */
    private function register_ajax_handlers() {
        // Dashboard updates
        add_action('wp_ajax_spiralengine_get_dashboard_data', array($this, 'get_dashboard_data'));
        add_action('wp_ajax_spiralengine_refresh_dashboard_widget', array($this, 'refresh_dashboard_widget'));
        
        // Widget interactions
        add_action('wp_ajax_spiralengine_save_widget_data', array($this, 'save_widget_data'));
        add_action('wp_ajax_spiralengine_load_widget_content', array($this, 'load_widget_content'));
        add_action('wp_ajax_spiralengine_validate_widget_field', array($this, 'validate_widget_field'));
        
        // Episode logging
        add_action('wp_ajax_spiralengine_quick_log_episode', array($this, 'quick_log_episode'));
        add_action('wp_ajax_spiralengine_save_episode', array($this, 'save_episode'));
        add_action('wp_ajax_spiralengine_get_recent_episodes', array($this, 'get_recent_episodes'));
        
        // Assessment
        add_action('wp_ajax_spiralengine_save_assessment', array($this, 'save_assessment'));
        add_action('wp_ajax_spiralengine_get_assessment_history', array($this, 'get_assessment_history'));
        
        // Real-time validation
        add_action('wp_ajax_spiralengine_check_username', array($this, 'check_username_availability'));
        add_action('wp_ajax_spiralengine_check_email', array($this, 'check_email_availability'));
        add_action('wp_ajax_spiralengine_validate_field', array($this, 'validate_field'));
        
        // User settings
        add_action('wp_ajax_spiralengine_save_user_settings', array($this, 'save_user_settings'));
        add_action('wp_ajax_spiralengine_save_timezone', array($this, 'save_timezone'));
        
        // Analytics
        add_action('wp_ajax_spiralengine_get_analytics_data', array($this, 'get_analytics_data'));
        add_action('wp_ajax_spiralengine_get_chart_data', array($this, 'get_chart_data'));
        
        // Patterns and insights
        add_action('wp_ajax_spiralengine_get_patterns', array($this, 'get_patterns'));
        add_action('wp_ajax_spiralengine_get_insights', array($this, 'get_insights'));
        add_action('wp_ajax_spiralengine_dismiss_insight', array($this, 'dismiss_insight'));
        
        // Public AJAX handlers (for signup)
        add_action('wp_ajax_nopriv_spiralengine_check_username', array($this, 'check_username_availability'));
        add_action('wp_ajax_nopriv_spiralengine_check_email', array($this, 'check_email_availability'));
        add_action('wp_ajax_nopriv_spiralengine_register_user', array($this, 'register_user'));
    }
    
    /**
     * Get dashboard data
     */
    public function get_dashboard_data() {
        // Verify nonce
        if (!$this->security->verify_nonce($_POST['nonce'], 'spiralengine_dashboard')) {
            wp_send_json_error(array('message' => __('Security check failed', 'spiralengine')));
        }
        
        $user_id = get_current_user_id();
        if (!$user_id) {
            wp_send_json_error(array('message' => __('User not logged in', 'spiralengine')));
        }
        
        try {
            // Get dashboard data
            $data = array(
                'user_stats' => $this->get_user_statistics($user_id),
                'recent_episodes' => $this->get_user_recent_episodes($user_id, 5),
                'active_patterns' => $this->get_active_patterns($user_id),
                'upcoming_forecasts' => $this->get_upcoming_forecasts($user_id),
                'insights' => $this->get_user_insights($user_id, 3),
                'widget_updates' => $this->get_widget_updates($user_id),
                'system_health' => $this->get_system_health_status(),
                'timestamp' => current_time('c', true)
            );
            
            // Convert times to user's timezone
            $data = $this->convert_dashboard_times($data, $user_id);
            
            wp_send_json_success($data);
            
        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }
    
    /**
     * Refresh specific dashboard widget
     */
    public function refresh_dashboard_widget() {
        // Verify nonce
        if (!$this->security->verify_nonce($_POST['nonce'], 'spiralengine_dashboard')) {
            wp_send_json_error(array('message' => __('Security check failed', 'spiralengine')));
        }
        
        $widget_id = sanitize_text_field($_POST['widget_id']);
        $user_id = get_current_user_id();
        
        if (!$user_id) {
            wp_send_json_error(array('message' => __('User not logged in', 'spiralengine')));
        }
        
        try {
            $data = array();
            
            switch ($widget_id) {
                case 'system_health':
                    $data = $this->get_system_health_status();
                    break;
                    
                case 'user_activity':
                    $data = $this->get_user_activity_data();
                    break;
                    
                case 'episode_timeline':
                    $data = $this->get_user_recent_episodes($user_id, 10);
                    break;
                    
                case 'pattern_detection':
                    $data = $this->get_active_patterns($user_id);
                    break;
                    
                case 'insights':
                    $data = $this->get_user_insights($user_id, 5);
                    break;
                    
                case 'analytics':
                    $period = sanitize_text_field($_POST['period'] ?? 'week');
                    $data = $this->get_analytics_summary($user_id, $period);
                    break;
                    
                default:
                    wp_send_json_error(array('message' => __('Invalid widget ID', 'spiralengine')));
                    return;
            }
            
            $data['timestamp'] = current_time('c', true);
            $data['refresh_in'] = $this->get_widget_refresh_interval($widget_id);
            
            wp_send_json_success($data);
            
        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }
    
    /**
     * Save widget data
     */
    public function save_widget_data() {
        // Verify nonce
        if (!$this->security->verify_nonce($_POST['nonce'], 'spiralengine_widget')) {
            wp_send_json_error(array('message' => __('Security check failed', 'spiralengine')));
        }
        
        $widget_id = sanitize_text_field($_POST['widget_id']);
        $widget_data = $_POST['data'] ?? array();
        $user_id = get_current_user_id();
        
        if (!$user_id) {
            wp_send_json_error(array('message' => __('User not logged in', 'spiralengine')));
        }
        
        try {
            // Sanitize widget data
            $sanitized_data = $this->sanitize_widget_data($widget_data, $widget_id);
            
            // Validate data
            $validation = $this->validate_widget_data($sanitized_data, $widget_id);
            if (is_wp_error($validation)) {
                wp_send_json_error(array(
                    'message' => $validation->get_error_message(),
                    'errors' => $validation->get_error_data()
                ));
                return;
            }
            
            // Save to database
            $result = $this->database->save_widget_data($user_id, $widget_id, $sanitized_data);
            
            if ($result) {
                wp_send_json_success(array(
                    'message' => __('Widget data saved successfully', 'spiralengine'),
                    'data' => $sanitized_data
                ));
            } else {
                wp_send_json_error(array('message' => __('Failed to save widget data', 'spiralengine')));
            }
            
        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }
    
    /**
     * Load widget content
     */
    public function load_widget_content() {
        // Verify nonce
        if (!$this->security->verify_nonce($_POST['nonce'], 'spiralengine_widget')) {
            wp_send_json_error(array('message' => __('Security check failed', 'spiralengine')));
        }
        
        $widget_id = sanitize_text_field($_POST['widget_id']);
        $mode = sanitize_text_field($_POST['mode'] ?? 'full');
        $user_id = get_current_user_id();
        
        try {
            // Check widget access
            $access_control = new SpiralEngine_Access_Control();
            if (!$access_control->can_access_widget($user_id, $widget_id)) {
                wp_send_json_error(array(
                    'message' => __('You do not have access to this widget', 'spiralengine'),
                    'upgrade_required' => true
                ));
                return;
            }
            
            // Get widget instance
            $widget_studio = SpiralEngine_Widget_Studio::get_instance();
            $widget = $widget_studio->get_widget($widget_id);
            
            if (!$widget) {
                wp_send_json_error(array('message' => __('Widget not found', 'spiralengine')));
                return;
            }
            
            // Render widget content
            ob_start();
            $widget->render($mode);
            $content = ob_get_clean();
            
            wp_send_json_success(array(
                'content' => $content,
                'mode' => $mode,
                'widget_id' => $widget_id
            ));
            
        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }
    
    /**
     * Quick log episode
     */
    public function quick_log_episode() {
        // Verify nonce
        if (!$this->security->verify_nonce($_POST['nonce'], 'spiralengine_episode')) {
            wp_send_json_error(array('message' => __('Security check failed', 'spiralengine')));
        }
        
        $user_id = get_current_user_id();
        if (!$user_id) {
            wp_send_json_error(array('message' => __('User not logged in', 'spiralengine')));
        }
        
        try {
            // Get and sanitize data
            $episode_data = array(
                'type' => sanitize_text_field($_POST['type']),
                'severity' => absint($_POST['severity']),
                'trigger' => sanitize_text_field($_POST['trigger'] ?? ''),
                'user_id' => $user_id,
                'timestamp' => current_time('mysql', true),
                'quick_log' => true
            );
            
            // Validate
            if (!in_array($episode_data['type'], array('overthinking', 'anxiety', 'depression', 'ptsd', 'caregiver', 'panic', 'dissociation'))) {
                wp_send_json_error(array('message' => __('Invalid episode type', 'spiralengine')));
                return;
            }
            
            if ($episode_data['severity'] < 1 || $episode_data['severity'] > 10) {
                wp_send_json_error(array('message' => __('Invalid severity level', 'spiralengine')));
                return;
            }
            
            // Save episode
            $episode_id = $this->database->save_episode($episode_data);
            
            if ($episode_id) {
                // Check for patterns
                $pattern_engine = new SpiralEngine_Patterns();
                $patterns = $pattern_engine->check_immediate_patterns($user_id, $episode_data);
                
                // Get formatted time for response
                $formatted_time = $this->time_manager->format_user_time($episode_data['timestamp'], $user_id);
                
                wp_send_json_success(array(
                    'message' => __('Episode logged successfully', 'spiralengine'),
                    'episode_id' => $episode_id,
                    'timestamp' => $formatted_time,
                    'patterns' => $patterns
                ));
            } else {
                wp_send_json_error(array('message' => __('Failed to log episode', 'spiralengine')));
            }
            
        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }
    
    /**
     * Save full episode
     */
    public function save_episode() {
        // Verify nonce
        if (!$this->security->verify_nonce($_POST['nonce'], 'spiralengine_episode')) {
            wp_send_json_error(array('message' => __('Security check failed', 'spiralengine')));
        }
        
        $user_id = get_current_user_id();
        if (!$user_id) {
            wp_send_json_error(array('message' => __('User not logged in', 'spiralengine')));
        }
        
        try {
            // Sanitize all fields
            $episode_data = $this->sanitize_episode_data($_POST);
            $episode_data['user_id'] = $user_id;
            $episode_data['timestamp'] = current_time('mysql', true);
            
            // Validate
            $validation = $this->validate_episode_data($episode_data);
            if (is_wp_error($validation)) {
                wp_send_json_error(array(
                    'message' => $validation->get_error_message(),
                    'errors' => $validation->get_error_data()
                ));
                return;
            }
            
            // Save episode
            $episode_id = $this->database->save_episode($episode_data);
            
            if ($episode_id) {
                // Process correlations
                $correlation_engine = new SpiralEngine_Correlations();
                $correlations = $correlation_engine->process_episode($episode_id, $episode_data);
                
                // Check patterns
                $pattern_engine = new SpiralEngine_Patterns();
                $patterns = $pattern_engine->analyze_episode($episode_id, $episode_data);
                
                // Generate insights if applicable
                $insights = array();
                if ($this->should_generate_insights($user_id)) {
                    $insight_engine = new SpiralEngine_Insights();
                    $insights = $insight_engine->generate_for_episode($episode_id, $episode_data);
                }
                
                wp_send_json_success(array(
                    'message' => __('Episode saved successfully', 'spiralengine'),
                    'episode_id' => $episode_id,
                    'correlations' => $correlations,
                    'patterns' => $patterns,
                    'insights' => $insights
                ));
            } else {
                wp_send_json_error(array('message' => __('Failed to save episode', 'spiralengine')));
            }
            
        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }
    
    /**
     * Get recent episodes
     */
    public function get_recent_episodes() {
        // Verify nonce
        if (!$this->security->verify_nonce($_POST['nonce'], 'spiralengine_ajax')) {
            wp_send_json_error(array('message' => __('Security check failed', 'spiralengine')));
        }
        
        $user_id = get_current_user_id();
        if (!$user_id) {
            wp_send_json_error(array('message' => __('User not logged in', 'spiralengine')));
        }
        
        try {
            $limit = absint($_POST['limit'] ?? 5);
            $type = sanitize_text_field($_POST['type'] ?? '');
            
            $episodes = $this->get_user_recent_episodes($user_id, $limit, $type);
            
            wp_send_json_success(array(
                'episodes' => $episodes,
                'total' => count($episodes)
            ));
            
        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }
    
    /**
     * Save assessment
     */
    public function save_assessment() {
        // Verify nonce
        if (!$this->security->verify_nonce($_POST['nonce'], 'spiralengine_assessment')) {
            wp_send_json_error(array('message' => __('Security check failed', 'spiralengine')));
        }
        
        $user_id = get_current_user_id();
        if (!$user_id) {
            wp_send_json_error(array('message' => __('User not logged in', 'spiralengine')));
        }
        
        try {
            // Get responses
            $responses = array_map('absint', $_POST['responses'] ?? array());
            
            // Validate responses
            if (count($responses) !== 6) {
                wp_send_json_error(array('message' => __('All 6 questions must be answered', 'spiralengine')));
                return;
            }
            
            foreach ($responses as $response) {
                if ($response < 0 || $response > 3) {
                    wp_send_json_error(array('message' => __('Invalid response value', 'spiralengine')));
                    return;
                }
            }
            
            // Calculate score
            $total_score = array_sum($responses);
            $risk_level = $this->calculate_risk_level($total_score);
            
            // Save assessment
            $assessment_data = array(
                'user_id' => $user_id,
                'responses' => $responses,
                'total_score' => $total_score,
                'risk_level' => $risk_level,
                'timestamp' => current_time('mysql', true)
            );
            
            $assessment_id = $this->database->save_assessment($assessment_data);
            
            if ($assessment_id) {
                // Check if crisis intervention needed
                $crisis_response = null;
                if ($total_score >= 13) {
                    $crisis_handler = new SpiralEngine_Crisis();
                    $crisis_response = $crisis_handler->handle_high_risk_assessment($user_id, $assessment_data);
                }
                
                wp_send_json_success(array(
                    'message' => __('Assessment saved successfully', 'spiralengine'),
                    'assessment_id' => $assessment_id,
                    'total_score' => $total_score,
                    'risk_level' => $risk_level,
                    'crisis_response' => $crisis_response
                ));
            } else {
                wp_send_json_error(array('message' => __('Failed to save assessment', 'spiralengine')));
            }
            
        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }
    
    /**
     * Check username availability
     */
    public function check_username_availability() {
        // Verify nonce
        if (!$this->security->verify_nonce($_POST['nonce'], 'spiralengine_validation')) {
            wp_send_json_error(array('message' => __('Security check failed', 'spiralengine')));
        }
        
        $username = sanitize_user($_POST['username']);
        
        if (strlen($username) < 3 || strlen($username) > 20) {
            wp_send_json_error(array('message' => __('Username must be 3-20 characters', 'spiralengine')));
            return;
        }
        
        if (username_exists($username)) {
            wp_send_json_error(array('message' => __('Username already taken', 'spiralengine')));
        } else {
            wp_send_json_success(array('message' => __('Username available', 'spiralengine')));
        }
    }
    
    /**
     * Check email availability
     */
    public function check_email_availability() {
        // Verify nonce
        if (!$this->security->verify_nonce($_POST['nonce'], 'spiralengine_validation')) {
            wp_send_json_error(array('message' => __('Security check failed', 'spiralengine')));
        }
        
        $email = sanitize_email($_POST['email']);
        
        if (!is_email($email)) {
            wp_send_json_error(array('message' => __('Invalid email format', 'spiralengine')));
            return;
        }
        
        if (email_exists($email)) {
            wp_send_json_error(array('message' => __('Email already registered', 'spiralengine')));
        } else {
            wp_send_json_success(array('message' => __('Email available', 'spiralengine')));
        }
    }
    
    /**
     * Register new user (public AJAX)
     */
    public function register_user() {
        // Verify nonce
        if (!$this->security->verify_nonce($_POST['nonce'], 'spiralengine_signup')) {
            wp_send_json_error(array('message' => __('Security check failed', 'spiralengine')));
        }
        
        try {
            // Sanitize user data
            $user_data = array(
                'username' => sanitize_user($_POST['username']),
                'email' => sanitize_email($_POST['email']),
                'password' => $_POST['password'], // Will be hashed by wp_insert_user
                'biological_sex' => sanitize_text_field($_POST['biological_sex']),
                'age' => absint($_POST['age']),
                'assessment_responses' => array_map('absint', $_POST['assessment_responses'] ?? array()),
                'privacy_consent' => !empty($_POST['privacy_consent']),
                'terms_consent' => !empty($_POST['terms_consent'])
            );
            
            // Validate all fields
            $validation = $this->validate_registration_data($user_data);
            if (is_wp_error($validation)) {
                wp_send_json_error(array(
                    'message' => $validation->get_error_message(),
                    'errors' => $validation->get_error_data()
                ));
                return;
            }
            
            // Create user
            $user_id = wp_insert_user(array(
                'user_login' => $user_data['username'],
                'user_email' => $user_data['email'],
                'user_pass' => $user_data['password'],
                'role' => 'subscriber'
            ));
            
            if (is_wp_error($user_id)) {
                wp_send_json_error(array('message' => $user_id->get_error_message()));
                return;
            }
            
            // Save additional user data
            update_user_meta($user_id, 'spiralengine_biological_sex', $user_data['biological_sex']);
            update_user_meta($user_id, 'spiralengine_age', $user_data['age']);
            update_user_meta($user_id, 'spiralengine_privacy_consent', current_time('mysql'));
            update_user_meta($user_id, 'spiralengine_terms_consent', current_time('mysql'));
            
            // Save initial assessment
            $total_score = array_sum($user_data['assessment_responses']);
            $risk_level = $this->calculate_risk_level($total_score);
            
            $assessment_data = array(
                'user_id' => $user_id,
                'responses' => $user_data['assessment_responses'],
                'total_score' => $total_score,
                'risk_level' => $risk_level,
                'timestamp' => current_time('mysql', true),
                'is_initial' => true
            );
            
            $this->database->save_assessment($assessment_data);
            
            // Auto-login user
            wp_set_current_user($user_id);
            wp_set_auth_cookie($user_id, true);
            
            // Trigger welcome email
            do_action('spiralengine_user_registered', $user_id, $user_data);
            
            wp_send_json_success(array(
                'message' => __('Registration successful', 'spiralengine'),
                'redirect' => home_url('/dashboard/'),
                'user_id' => $user_id
            ));
            
        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }
    
    /**
     * Save user timezone
     */
    public function save_timezone() {
        // Verify nonce
        if (!$this->security->verify_nonce($_POST['nonce'], 'spiralengine_timezone')) {
            wp_send_json_error(array('message' => __('Security check failed', 'spiralengine')));
        }
        
        $user_id = get_current_user_id();
        if (!$user_id) {
            wp_send_json_error(array('message' => __('User not logged in', 'spiralengine')));
        }
        
        $timezone = sanitize_text_field($_POST['timezone']);
        
        if (in_array($timezone, timezone_identifiers_list())) {
            update_user_meta($user_id, 'spiralengine_timezone', $timezone);
            wp_send_json_success(array(
                'message' => __('Timezone updated', 'spiralengine'),
                'timezone' => $timezone
            ));
        } else {
            wp_send_json_error(array('message' => __('Invalid timezone', 'spiralengine')));
        }
    }
    
    /**
     * Get analytics data
     */
    public function get_analytics_data() {
        // Verify nonce
        if (!$this->security->verify_nonce($_POST['nonce'], 'spiralengine_analytics')) {
            wp_send_json_error(array('message' => __('Security check failed', 'spiralengine')));
        }
        
        $user_id = get_current_user_id();
        if (!$user_id) {
            wp_send_json_error(array('message' => __('User not logged in', 'spiralengine')));
        }
        
        try {
            $type = sanitize_text_field($_POST['type']);
            $period = sanitize_text_field($_POST['period'] ?? 'month');
            $start_date = sanitize_text_field($_POST['start_date'] ?? '');
            $end_date = sanitize_text_field($_POST['end_date'] ?? '');
            
            $analytics = new SpiralEngine_Analytics();
            
            switch ($type) {
                case 'episodes':
                    $data = $analytics->get_episode_analytics($user_id, $period, $start_date, $end_date);
                    break;
                    
                case 'patterns':
                    $data = $analytics->get_pattern_analytics($user_id, $period, $start_date, $end_date);
                    break;
                    
                case 'progress':
                    $data = $analytics->get_progress_analytics($user_id, $period, $start_date, $end_date);
                    break;
                    
                case 'correlations':
                    $data = $analytics->get_correlation_analytics($user_id, $period, $start_date, $end_date);
                    break;
                    
                default:
                    wp_send_json_error(array('message' => __('Invalid analytics type', 'spiralengine')));
                    return;
            }
            
            wp_send_json_success($data);
            
        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }
    
    /**
     * Get user statistics
     * 
     * @param int $user_id User ID
     * @return array
     */
    private function get_user_statistics($user_id) {
        return array(
            'total_episodes' => $this->database->get_user_episode_count($user_id),
            'current_streak' => $this->database->get_user_streak($user_id),
            'risk_level' => $this->database->get_user_current_risk_level($user_id),
            'patterns_detected' => $this->database->get_user_pattern_count($user_id),
            'insights_available' => $this->database->get_user_unread_insights_count($user_id)
        );
    }
    
    /**
     * Get user recent episodes
     * 
     * @param int $user_id User ID
     * @param int $limit Limit
     * @param string $type Episode type filter
     * @return array
     */
    private function get_user_recent_episodes($user_id, $limit = 5, $type = '') {
        $episodes = $this->database->get_episodes(array(
            'user_id' => $user_id,
            'type' => $type,
            'limit' => $limit,
            'orderby' => 'timestamp',
            'order' => 'DESC'
        ));
        
        // Format times for user
        foreach ($episodes as &$episode) {
            $episode['formatted_time'] = $this->time_manager->format_user_time($episode['timestamp'], $user_id);
            $episode['relative_time'] = $this->time_manager->get_relative_time($episode['timestamp'], $user_id);
        }
        
        return $episodes;
    }
    
    /**
     * Get active patterns
     * 
     * @param int $user_id User ID
     * @return array
     */
    private function get_active_patterns($user_id) {
        $pattern_engine = new SpiralEngine_Patterns();
        return $pattern_engine->get_active_patterns($user_id);
    }
    
    /**
     * Get upcoming forecasts
     * 
     * @param int $user_id User ID
     * @return array
     */
    private function get_upcoming_forecasts($user_id) {
        $forecast_engine = new SpiralEngine_Forecast();
        return $forecast_engine->get_upcoming_risks($user_id, 7);
    }
    
    /**
     * Get user insights
     * 
     * @param int $user_id User ID
     * @param int $limit Limit
     * @return array
     */
    private function get_user_insights($user_id, $limit = 3) {
        return $this->database->get_user_insights($user_id, array(
            'unread_only' => true,
            'limit' => $limit,
            'orderby' => 'priority',
            'order' => 'DESC'
        ));
    }
    
    /**
     * Get widget updates
     * 
     * @param int $user_id User ID
     * @return array
     */
    private function get_widget_updates($user_id) {
        $widget_studio = SpiralEngine_Widget_Studio::get_instance();
        return $widget_studio->get_widget_updates($user_id);
    }
    
    /**
     * Get system health status
     * 
     * @return array
     */
    private function get_system_health_status() {
        return array(
            'status' => 'healthy',
            'database' => $this->database->check_health(),
            'cache' => wp_cache_get('test', 'spiralengine') !== false,
            'api' => true,
            'last_cron' => get_option('spiralengine_last_cron_run')
        );
    }
    
    /**
     * Get widget refresh interval
     * 
     * @param string $widget_id Widget ID
     * @return int Seconds
     */
    private function get_widget_refresh_interval($widget_id) {
        $intervals = array(
            'system_health' => 5,
            'user_activity' => 30,
            'episode_timeline' => 60,
            'pattern_detection' => 300,
            'insights' => 120,
            'analytics' => 300
        );
        
        return $intervals[$widget_id] ?? 60;
    }
    
    /**
     * Convert dashboard times to user timezone
     * 
     * @param array $data Dashboard data
     * @param int $user_id User ID
     * @return array
     */
    private function convert_dashboard_times($data, $user_id) {
        // Convert episode times
        if (isset($data['recent_episodes'])) {
            foreach ($data['recent_episodes'] as &$episode) {
                if (isset($episode['timestamp'])) {
                    $episode['formatted_time'] = $this->time_manager->format_user_time($episode['timestamp'], $user_id);
                    $episode['relative_time'] = $this->time_manager->get_relative_time($episode['timestamp'], $user_id);
                }
            }
        }
        
        // Convert insight times
        if (isset($data['insights'])) {
            foreach ($data['insights'] as &$insight) {
                if (isset($insight['created_at'])) {
                    $insight['formatted_time'] = $this->time_manager->format_user_time($insight['created_at'], $user_id);
                }
            }
        }
        
        return $data;
    }
    
    /**
     * Sanitize widget data
     * 
     * @param array $data Raw widget data
     * @param string $widget_id Widget ID
     * @return array
     */
    private function sanitize_widget_data($data, $widget_id) {
        // Get widget-specific sanitization rules
        $widget_studio = SpiralEngine_Widget_Studio::get_instance();
        $widget = $widget_studio->get_widget($widget_id);
        
        if ($widget && method_exists($widget, 'sanitize_data')) {
            return $widget->sanitize_data($data);
        }
        
        // Default sanitization
        return array_map(array($this->security, 'sanitize_input'), $data);
    }
    
    /**
     * Validate widget data
     * 
     * @param array $data Widget data
     * @param string $widget_id Widget ID
     * @return true|WP_Error
     */
    private function validate_widget_data($data, $widget_id) {
        $widget_studio = SpiralEngine_Widget_Studio::get_instance();
        $widget = $widget_studio->get_widget($widget_id);
        
        if ($widget && method_exists($widget, 'validate_data')) {
            return $widget->validate_data($data);
        }
        
        return true;
    }
    
    /**
     * Sanitize episode data
     * 
     * @param array $data Raw episode data
     * @return array
     */
    private function sanitize_episode_data($data) {
        return array(
            'type' => sanitize_text_field($data['type'] ?? ''),
            'severity' => absint($data['severity'] ?? 0),
            'duration' => absint($data['duration'] ?? 0),
            'trigger' => sanitize_text_field($data['trigger'] ?? ''),
            'coping_strategies' => array_map('sanitize_text_field', $data['coping_strategies'] ?? array()),
            'mood_before' => absint($data['mood_before'] ?? 0),
            'mood_after' => absint($data['mood_after'] ?? 0),
            'thoughts' => sanitize_textarea_field($data['thoughts'] ?? ''),
            'physical_symptoms' => array_map('sanitize_text_field', $data['physical_symptoms'] ?? array()),
            'environmental_factors' => array_map('sanitize_text_field', $data['environmental_factors'] ?? array()),
            'biological_factors' => array_map('sanitize_text_field', $data['biological_factors'] ?? array())
        );
    }
    
    /**
     * Validate episode data
     * 
     * @param array $data Episode data
     * @return true|WP_Error
     */
    private function validate_episode_data($data) {
        $errors = new WP_Error();
        
        if (!in_array($data['type'], array('overthinking', 'anxiety', 'depression', 'ptsd', 'caregiver', 'panic', 'dissociation'))) {
            $errors->add('type', __('Invalid episode type', 'spiralengine'));
        }
        
        if ($data['severity'] < 1 || $data['severity'] > 10) {
            $errors->add('severity', __('Severity must be between 1 and 10', 'spiralengine'));
        }
        
        if ($data['mood_before'] && ($data['mood_before'] < 1 || $data['mood_before'] > 10)) {
            $errors->add('mood_before', __('Mood must be between 1 and 10', 'spiralengine'));
        }
        
        if ($data['mood_after'] && ($data['mood_after'] < 1 || $data['mood_after'] > 10)) {
            $errors->add('mood_after', __('Mood must be between 1 and 10', 'spiralengine'));
        }
        
        return $errors->has_errors() ? $errors : true;
    }
    
    /**
     * Calculate risk level from assessment score
     * 
     * @param int $score Total score
     * @return string
     */
    private function calculate_risk_level($score) {
        if ($score <= 6) {
            return 'low';
        } elseif ($score <= 12) {
            return 'medium';
        } else {
            return 'high';
        }
    }
    
    /**
     * Check if should generate insights
     * 
     * @param int $user_id User ID
     * @return bool
     */
    private function should_generate_insights($user_id) {
        // Check user tier
        $access_control = new SpiralEngine_Access_Control();
        if (!$access_control->can_access_feature('insights', $user_id)) {
            return false;
        }
        
        // Check if enough data
        $episode_count = $this->database->get_user_episode_count($user_id);
        return $episode_count >= 3;
    }
    
    /**
     * Validate registration data
     * 
     * @param array $data Registration data
     * @return true|WP_Error
     */
    private function validate_registration_data($data) {
        $errors = new WP_Error();
        
        // Username validation
        if (strlen($data['username']) < 3 || strlen($data['username']) > 20) {
            $errors->add('username', __('Username must be 3-20 characters', 'spiralengine'));
        }
        if (username_exists($data['username'])) {
            $errors->add('username', __('Username already taken', 'spiralengine'));
        }
        
        // Email validation
        if (!is_email($data['email'])) {
            $errors->add('email', __('Invalid email format', 'spiralengine'));
        }
        if (email_exists($data['email'])) {
            $errors->add('email', __('Email already registered', 'spiralengine'));
        }
        
        // Password validation
        if (strlen($data['password']) < 8) {
            $errors->add('password', __('Password must be at least 8 characters', 'spiralengine'));
        }
        
        // Biological sex validation
        if (!in_array($data['biological_sex'], array('female', 'male'))) {
            $errors->add('biological_sex', __('Please select biological sex', 'spiralengine'));
        }
        
        // Age validation
        if ($data['age'] < 13 || $data['age'] > 120) {
            $errors->add('age', __('Age must be between 13 and 120', 'spiralengine'));
        }
        
        // Assessment validation
        if (count($data['assessment_responses']) !== 6) {
            $errors->add('assessment', __('All assessment questions must be answered', 'spiralengine'));
        }
        
        // Consent validation
        if (!$data['privacy_consent']) {
            $errors->add('privacy_consent', __('You must agree to the privacy policy', 'spiralengine'));
        }
        if (!$data['terms_consent']) {
            $errors->add('terms_consent', __('You must agree to the terms of service', 'spiralengine'));
        }
        
        return $errors->has_errors() ? $errors : true;
    }
}

// Initialize AJAX handler
SpiralEngine_AJAX::get_instance();

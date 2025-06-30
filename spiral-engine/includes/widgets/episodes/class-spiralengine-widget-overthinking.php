<?php
// includes/widgets/episodes/class-spiralengine-widget-overthinking.php

/**
 * Overthinking Episode Logger Widget
 * 
 * First implementation of the Episode Framework Architecture
 * Provides comprehensive overthinking/spiraling episode tracking
 * 
 * @package SpiralEngine
 * @subpackage Widgets/Episodes
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Overthinking Episode Logger Widget Class
 */
class SpiralEngine_Widget_Overthinking extends SPIRAL_Episode_Widget_Base {
    
    /**
     * Widget identification
     */
    protected $widget_id = 'overthinking_logger';
    protected $widget_name = 'Overthinking Episode Logger';
    protected $widget_version = '1.0.0';
    protected $episode_type = 'overthinking';
    
    /**
     * Episode configuration
     */
    protected $episode_config = array(
        'supports_quick_log' => true,
        'supports_detailed_log' => true,  
        'correlatable' => true,
        'exportable' => true,
        'ai_enabled' => true
    );
    
    /**
     * Correlation configuration
     */
    protected $correlation_config = array(
        'can_correlate_with' => array('anxiety', 'ptsd', 'depression'),
        'correlation_strength' => 1.0,
        'shares_triggers' => true,
        'shares_biologicals' => true
    );
    
    /**
     * Constructor
     */
    public function __construct() {
        parent::__construct();
        
        // Register this episode type with the framework
        $this->register_episode_type();
        
        // Register AJAX handlers
        add_action('wp_ajax_overthinking_log_quick', array($this, 'handle_quick_log'));
        add_action('wp_ajax_overthinking_log_detailed', array($this, 'handle_detailed_log'));
        add_action('wp_ajax_overthinking_check_patterns', array($this, 'check_patterns'));
        add_action('wp_ajax_overthinking_get_correlations', array($this, 'get_correlations'));
    }
    
    /**
     * Register with the episode registry
     */
    private function register_episode_type() {
        if (class_exists('SPIRAL_Episode_Registry')) {
            $registry = SPIRAL_Episode_Registry::get_instance();
            
            $registry->register_episode_type('overthinking', array(
                'class' => get_class($this),
                'name' => 'Overthinking',
                'description' => 'Rumination and thought spirals',
                'color' => '#6B46C1',
                'icon' => 'dashicons-admin-page',
                'weight' => 1.0,
                'correlations' => array(
                    'anxiety' => 0.7,      // Strong correlation
                    'depression' => 0.5,   // Moderate correlation
                    'ptsd' => 0.4         // Weaker correlation
                )
            ));
        }
    }
    
    /**
     * Get episode questions for detailed logging
     */
    public function get_episode_questions() {
        return array(
            'basic_info' => array(
                'title' => __('Episode Information', 'spiral'),
                'fields' => array(
                    'severity' => array(
                        'type' => 'slider',
                        'label' => __('How severe is this overthinking episode?', 'spiral'),
                        'min' => 1,
                        'max' => 10,
                        'required' => true,
                        'help' => __('1 = Mild, manageable | 10 = Severe, overwhelming', 'spiral')
                    ),
                    'duration' => array(
                        'type' => 'select',
                        'label' => __('How long has this episode lasted?', 'spiral'),
                        'options' => array(
                            '< 15 min' => __('Less than 15 minutes', 'spiral'),
                            '15-30 min' => __('15-30 minutes', 'spiral'),
                            '30-60 min' => __('30-60 minutes', 'spiral'),
                            '1-2 hours' => __('1-2 hours', 'spiral'),
                            '2-4 hours' => __('2-4 hours', 'spiral'),
                            '> 4 hours' => __('More than 4 hours', 'spiral')
                        ),
                        'required' => true
                    ),
                    'overthinking_type' => array(
                        'type' => 'select',
                        'label' => __('What type of overthinking is this?', 'spiral'),
                        'options' => array(
                            'rumination' => __('Rumination (past-focused)', 'spiral'),
                            'worry' => __('Worry (future-focused)', 'spiral'),
                            'analysis_paralysis' => __('Analysis paralysis', 'spiral'),
                            'catastrophizing' => __('Catastrophizing', 'spiral'),
                            'mixed' => __('Mixed/Multiple types', 'spiral')
                        ),
                        'required' => true
                    )
                )
            ),
            'thought_patterns' => array(
                'title' => __('Thought Patterns', 'spiral'),
                'fields' => array(
                    'primary_thought' => array(
                        'type' => 'textarea',
                        'label' => __('What is the main thought/worry?', 'spiral'),
                        'required' => true,
                        'encrypted' => true,
                        'placeholder' => __('Brief description of what you\'re overthinking about...', 'spiral')
                    ),
                    'thought_speed' => array(
                        'type' => 'select',
                        'label' => __('How fast are your thoughts moving?', 'spiral'),
                        'options' => array(
                            'slow' => __('Slow, stuck on one thing', 'spiral'),
                            'moderate' => __('Moderate, cycling through related thoughts', 'spiral'),
                            'fast' => __('Fast, jumping between thoughts', 'spiral'),
                            'racing' => __('Racing, can\'t keep up', 'spiral')
                        )
                    ),
                    'thought_stickiness' => array(
                        'type' => 'select',
                        'label' => __('How "sticky" are these thoughts?', 'spiral'),
                        'options' => array(
                            'low' => __('Can redirect with effort', 'spiral'),
                            'medium' => __('Keeps coming back', 'spiral'),
                            'high' => __('Very hard to stop', 'spiral'),
                            'extreme' => __('Feels impossible to stop', 'spiral')
                        )
                    ),
                    'reality_testing' => array(
                        'type' => 'select',
                        'label' => __('How realistic do these thoughts feel?', 'spiral'),
                        'options' => array(
                            'unrealistic' => __('I know they\'re unrealistic', 'spiral'),
                            'mixed' => __('Part realistic, part not', 'spiral'),
                            'realistic' => __('They feel very realistic', 'spiral'),
                            'unsure' => __('Can\'t tell anymore', 'spiral')
                        )
                    )
                )
            ),
            'triggers' => array(
                'title' => __('Triggers & Context', 'spiral'),
                'fields' => array(
                    'trigger_category' => array(
                        'type' => 'select',
                        'label' => __('What triggered this episode?', 'spiral'),
                        'options' => array(
                            'relationship' => __('Relationship/Social', 'spiral'),
                            'work' => __('Work/Career', 'spiral'),
                            'health' => __('Health concerns', 'spiral'),
                            'financial' => __('Financial worries', 'spiral'),
                            'existential' => __('Life meaning/purpose', 'spiral'),
                            'mistake' => __('Past mistake/regret', 'spiral'),
                            'unknown' => __('Unknown/No clear trigger', 'spiral')
                        )
                    ),
                    'trigger_details' => array(
                        'type' => 'textarea',
                        'label' => __('Trigger details (optional)', 'spiral'),
                        'encrypted' => true,
                        'required' => false
                    ),
                    'location' => array(
                        'type' => 'select',
                        'label' => __('Where are you?', 'spiral'),
                        'options' => array(
                            'home' => __('Home', 'spiral'),
                            'work' => __('Work', 'spiral'),
                            'public' => __('Public place', 'spiral'),
                            'transit' => __('In transit', 'spiral'),
                            'other' => __('Other', 'spiral')
                        )
                    )
                )
            ),
            'pre_episode_state' => array(
                'title' => __('Pre-Episode State', 'spiral'),
                'membership' => 'explorer',
                'fields' => array(
                    'sleep_hours' => array(
                        'type' => 'select',
                        'label' => __('Sleep last night?', 'spiral'),
                        'options' => array(
                            '< 4' => __('Less than 4 hours', 'spiral'),
                            '4-6' => __('4-6 hours', 'spiral'),
                            '6-8' => __('6-8 hours', 'spiral'),
                            '> 8' => __('More than 8 hours', 'spiral')
                        )
                    ),
                    'sleep_quality' => array(
                        'type' => 'select',
                        'label' => __('Sleep quality?', 'spiral'),
                        'options' => array(
                            'poor' => __('Poor', 'spiral'),
                            'fair' => __('Fair', 'spiral'),
                            'good' => __('Good', 'spiral'),
                            'excellent' => __('Excellent', 'spiral')
                        )
                    ),
                    'stress_level_before' => array(
                        'type' => 'slider',
                        'label' => __('Stress level before episode?', 'spiral'),
                        'min' => 1,
                        'max' => 10
                    ),
                    'mood_before' => array(
                        'type' => 'select',
                        'label' => __('Mood before episode?', 'spiral'),
                        'options' => array(
                            'anxious' => __('Anxious', 'spiral'),
                            'sad' => __('Sad', 'spiral'),
                            'neutral' => __('Neutral', 'spiral'),
                            'good' => __('Good', 'spiral'),
                            'irritable' => __('Irritable', 'spiral')
                        )
                    )
                )
            ),
            'biological_factors' => array(
                'title' => __('Biological Factors', 'spiral'),
                'membership' => 'explorer',
                'gender_aware' => true,
                'fields' => array(
                    'caffeine_today' => array(
                        'type' => 'select',
                        'label' => __('Caffeine consumption today?', 'spiral'),
                        'options' => array(
                            'none' => __('None', 'spiral'),
                            '1-2 cups' => __('1-2 cups', 'spiral'),
                            '3-4 cups' => __('3-4 cups', 'spiral'),
                            '> 4 cups' => __('More than 4 cups', 'spiral')
                        )
                    ),
                    'physical_activity' => array(
                        'type' => 'select',
                        'label' => __('Physical activity today?', 'spiral'),
                        'options' => array(
                            'none' => __('None', 'spiral'),
                            'light' => __('Light (walk, stretch)', 'spiral'),
                            'moderate' => __('Moderate (30+ min)', 'spiral'),
                            'vigorous' => __('Vigorous (intense)', 'spiral')
                        )
                    ),
                    'menstrual_phase' => array(
                        'type' => 'select',
                        'label' => __('Menstrual cycle phase?', 'spiral'),
                        'gender_specific' => 'female',
                        'options' => array(
                            'menstrual' => __('Menstrual (period)', 'spiral'),
                            'follicular' => __('Follicular (post-period)', 'spiral'),
                            'ovulation' => __('Ovulation', 'spiral'),
                            'luteal' => __('Luteal (pre-period)', 'spiral'),
                            'unknown' => __('Unknown/Irregular', 'spiral'),
                            'na' => __('N/A', 'spiral')
                        )
                    )
                )
            ),
            'impact_assessment' => array(
                'title' => __('Impact Assessment', 'spiral'),
                'membership' => 'navigator',
                'fields' => array(
                    'functional_impact' => array(
                        'type' => 'slider',
                        'label' => __('How much is this affecting your functioning?', 'spiral'),
                        'min' => 1,
                        'max' => 10,
                        'help' => __('1 = No impact | 10 = Complete shutdown', 'spiral')
                    ),
                    'activities_interrupted' => array(
                        'type' => 'checkbox_group',
                        'label' => __('What activities has this interrupted?', 'spiral'),
                        'options' => array(
                            'work' => __('Work/Study', 'spiral'),
                            'social' => __('Social activities', 'spiral'),
                            'self_care' => __('Self-care', 'spiral'),
                            'sleep' => __('Sleep', 'spiral'),
                            'eating' => __('Eating', 'spiral'),
                            'recreation' => __('Recreation/Hobbies', 'spiral')
                        )
                    ),
                    'communication_impact' => array(
                        'type' => 'textarea',
                        'label' => __('How has this affected your communication/relationships?', 'spiral'),
                        'encrypted' => true
                    )
                )
            ),
            'coping_strategies' => array(
                'title' => __('Coping Strategies', 'spiral'),
                'membership' => 'navigator',
                'fields' => array(
                    'coping_strategies_used' => array(
                        'type' => 'checkbox_group',
                        'label' => __('What coping strategies have you tried?', 'spiral'),
                        'options' => array(
                            'breathing' => __('Deep breathing', 'spiral'),
                            'grounding' => __('Grounding techniques', 'spiral'),
                            'distraction' => __('Distraction', 'spiral'),
                            'talking' => __('Talking to someone', 'spiral'),
                            'writing' => __('Writing/Journaling', 'spiral'),
                            'movement' => __('Physical movement', 'spiral'),
                            'meditation' => __('Meditation/Mindfulness', 'spiral'),
                            'none' => __('None yet', 'spiral')
                        )
                    ),
                    'effectiveness' => array(
                        'type' => 'slider',
                        'label' => __('How effective were the strategies?', 'spiral'),
                        'min' => 1,
                        'max' => 10,
                        'conditional' => array(
                            'field' => 'coping_strategies_used',
                            'not_value' => 'none'
                        )
                    ),
                    'what_helped_most' => array(
                        'type' => 'textarea',
                        'label' => __('What helped the most?', 'spiral'),
                        'encrypted' => true,
                        'conditional' => array(
                            'field' => 'coping_strategies_used',
                            'not_value' => 'none'
                        )
                    )
                )
            ),
            'additional_notes' => array(
                'title' => __('Additional Notes', 'spiral'),
                'membership' => 'voyager',
                'fields' => array(
                    'cognitive_distortions' => array(
                        'type' => 'checkbox_group',
                        'label' => __('Cognitive distortions present?', 'spiral'),
                        'options' => array(
                            'all_or_nothing' => __('All-or-nothing thinking', 'spiral'),
                            'mind_reading' => __('Mind reading', 'spiral'),
                            'fortune_telling' => __('Fortune telling', 'spiral'),
                            'catastrophizing' => __('Catastrophizing', 'spiral'),
                            'personalization' => __('Personalization', 'spiral'),
                            'should_statements' => __('Should statements', 'spiral'),
                            'emotional_reasoning' => __('Emotional reasoning', 'spiral')
                        )
                    ),
                    'insights_gained' => array(
                        'type' => 'textarea',
                        'label' => __('Any insights or realizations?', 'spiral'),
                        'encrypted' => true
                    ),
                    'support_needed' => array(
                        'type' => 'textarea',
                        'label' => __('What support do you need right now?', 'spiral'),
                        'encrypted' => true
                    )
                )
            )
        );
    }
    
    /**
     * Get quick log fields
     */
    public function get_quick_log_fields() {
        return array(
            'severity' => array(
                'type' => 'slider',
                'label' => __('Severity', 'spiral'),
                'min' => 1,
                'max' => 10,
                'required' => true
            ),
            'primary_thought' => array(
                'type' => 'textarea',
                'label' => __('Main thought/worry', 'spiral'),
                'required' => true,
                'encrypted' => true,
                'maxlength' => 200,
                'placeholder' => __('What are you overthinking about?', 'spiral')
            ),
            'duration' => array(
                'type' => 'select',
                'label' => __('Duration', 'spiral'),
                'options' => array(
                    '< 15 min' => __('< 15 min', 'spiral'),
                    '15-30 min' => __('15-30 min', 'spiral'),
                    '30-60 min' => __('30-60 min', 'spiral'),
                    '> 60 min' => __('> 1 hour', 'spiral')
                ),
                'required' => true
            )
        );
    }
    
    /**
     * Validate episode data
     */
    public function validate_episode_data($data) {
        $errors = array();
        
        // Required fields validation
        if (empty($data['severity']) || $data['severity'] < 1 || $data['severity'] > 10) {
            $errors[] = __('Severity must be between 1 and 10', 'spiral');
        }
        
        if (empty($data['primary_thought'])) {
            $errors[] = __('Primary thought is required', 'spiral');
        }
        
        if (empty($errors)) {
            return true;
        }
        
        return $errors;
    }
    
    /**
     * Calculate severity score
     */
    public function calculate_severity_score($responses) {
        $score = 0;
        $weights = array(
            'severity' => 0.3,
            'duration' => 0.2,
            'thought_stickiness' => 0.15,
            'functional_impact' => 0.2,
            'thought_speed' => 0.15
        );
        
        // Base severity
        if (isset($responses['severity'])) {
            $score += $responses['severity'] * $weights['severity'];
        }
        
        // Duration impact
        if (isset($responses['duration'])) {
            $duration_scores = array(
                '< 15 min' => 2,
                '15-30 min' => 4,
                '30-60 min' => 6,
                '1-2 hours' => 7,
                '2-4 hours' => 8,
                '> 4 hours' => 10
            );
            $score += ($duration_scores[$responses['duration']] ?? 5) * $weights['duration'];
        }
        
        // Thought stickiness
        if (isset($responses['thought_stickiness'])) {
            $stickiness_scores = array(
                'low' => 2,
                'medium' => 5,
                'high' => 7,
                'extreme' => 10
            );
            $score += ($stickiness_scores[$responses['thought_stickiness']] ?? 5) * $weights['thought_stickiness'];
        }
        
        // Functional impact
        if (isset($responses['functional_impact'])) {
            $score += $responses['functional_impact'] * $weights['functional_impact'];
        }
        
        // Thought speed
        if (isset($responses['thought_speed'])) {
            $speed_scores = array(
                'slow' => 3,
                'moderate' => 5,
                'fast' => 7,
                'racing' => 9
            );
            $score += ($speed_scores[$responses['thought_speed']] ?? 5) * $weights['thought_speed'];
        }
        
        return round($score, 1);
    }
    
    /**
     * Save episode details
     */
    protected function save_episode_details($episode_id, $data) {
        global $wpdb;
        
        // Prepare details data
        $details = array(
            'episode_id' => $episode_id,
            'overthinking_type' => $data['overthinking_type'] ?? null,
            'thought_speed' => $data['thought_speed'] ?? null,
            'thought_stickiness' => $data['thought_stickiness'] ?? null,
            'reality_testing' => $data['reality_testing'] ?? null,
            'cognitive_distortions' => isset($data['cognitive_distortions']) ? json_encode($data['cognitive_distortions']) : null,
            'thought_content' => $data['primary_thought'] ?? null,
            'sleep_hours' => $data['sleep_hours'] ?? null,
            'sleep_quality' => $data['sleep_quality'] ?? null,
            'physical_activity' => $data['physical_activity'] ?? null,
            'caffeine_today' => $data['caffeine_today'] ?? null,
            'stress_level_before' => $data['stress_level_before'] ?? null,
            'mood_before' => $data['mood_before'] ?? null,
            'functional_impact' => $data['functional_impact'] ?? null,
            'activities_interrupted' => isset($data['activities_interrupted']) ? json_encode($data['activities_interrupted']) : null,
            'communication_impact' => $data['communication_impact'] ?? null,
            'coping_strategies_used' => isset($data['coping_strategies_used']) ? json_encode($data['coping_strategies_used']) : null,
            'effectiveness' => $data['effectiveness'] ?? null,
            'what_helped_most' => $data['what_helped_most'] ?? null
        );
        
        // Save to overthinking-specific table
        return $wpdb->insert(
            $wpdb->prefix . 'spiralengine_overthinking_details',
            $details
        );
    }
    
    /**
     * Handle quick log AJAX
     */
    public function handle_quick_log() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'overthinking_quick_log')) {
            wp_send_json_error(array('message' => __('Security check failed', 'spiral')));
        }
        
        // Check user
        $user_id = get_current_user_id();
        if (!$user_id) {
            wp_send_json_error(array('message' => __('Please log in', 'spiral')));
        }
        
        // Prepare data
        $data = array(
            'severity' => intval($_POST['severity']),
            'primary_thought' => sanitize_textarea_field($_POST['primary_thought']),
            'duration' => sanitize_text_field($_POST['duration']),
            'logged_via' => 'quick'
        );
        
        // Save episode
        $episode_id = $this->save_episode($data);
        
        if ($episode_id) {
            // Check for patterns
            $patterns = $this->detect_patterns($user_id, $episode_id);
            
            // Get correlations if enabled
            $correlations = array();
            if (class_exists('SPIRAL_Correlation_Engine')) {
                $correlation_engine = new SPIRAL_Correlation_Engine();
                $correlation_engine->detect_correlations($episode_id, 'overthinking');
                $correlations = $correlation_engine->get_recent_correlations($user_id);
            }
            
            wp_send_json_success(array(
                'message' => __('Episode logged successfully', 'spiral'),
                'episode_id' => $episode_id,
                'patterns' => $patterns,
                'correlations' => $correlations
            ));
        }
        
        wp_send_json_error(array('message' => __('Failed to save episode', 'spiral')));
    }
    
    /**
     * Handle detailed log AJAX
     */
    public function handle_detailed_log() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'overthinking_detailed_log')) {
            wp_send_json_error(array('message' => __('Security check failed', 'spiral')));
        }
        
        // Check user
        $user_id = get_current_user_id();
        if (!$user_id) {
            wp_send_json_error(array('message' => __('Please log in', 'spiral')));
        }
        
        // Parse form data
        parse_str($_POST['form_data'], $form_data);
        
        // Prepare episode data
        $data = array(
            'logged_via' => 'detailed'
        );
        
        // Extract all fields from form
        foreach ($form_data as $key => $value) {
            if (strpos($key, 'overthinking_') === 0) {
                $field_name = str_replace('overthinking_', '', $key);
                $data[$field_name] = is_array($value) ? $value : sanitize_text_field($value);
            }
        }
        
        // Validate data
        $validation = $this->validate_episode_data($data);
        if ($validation !== true) {
            wp_send_json_error(array('message' => implode(', ', $validation)));
        }
        
        // Save episode
        $episode_id = $this->save_episode($data);
        
        if ($episode_id) {
            // Detect patterns
            $patterns = $this->detect_patterns($user_id, $episode_id);
            
            // Get AI analysis if enabled
            $ai_analysis = null;
            if ($this->should_run_ai_analysis($user_id)) {
                $ai_analysis = $this->get_ai_analysis($episode_id, $data);
            }
            
            // Check correlations
            $correlations = array();
            if (class_exists('SPIRAL_Correlation_Engine')) {
                $correlation_engine = new SPIRAL_Correlation_Engine();
                $correlation_engine->detect_correlations($episode_id, 'overthinking');
                $correlations = $correlation_engine->get_user_correlations($user_id, 5);
            }
            
            wp_send_json_success(array(
                'message' => __('Episode logged successfully', 'spiral'),
                'episode_id' => $episode_id,
                'patterns' => $patterns,
                'ai_analysis' => $ai_analysis,
                'correlations' => $correlations
            ));
        }
        
        wp_send_json_error(array('message' => __('Failed to save episode', 'spiral')));
    }
    
    /**
     * Detect patterns
     */
    private function detect_patterns($user_id, $episode_id) {
        $patterns = array();
        
        // Get recent episodes
        $recent_episodes = $this->get_user_episodes($user_id, array(
            'days' => 30,
            'limit' => 20
        ));
        
        // Time pattern detection
        $time_pattern = $this->detect_time_patterns($recent_episodes);
        if ($time_pattern) {
            $patterns[] = $time_pattern;
        }
        
        // Trigger pattern detection
        $trigger_pattern = $this->detect_trigger_patterns($recent_episodes);
        if ($trigger_pattern) {
            $patterns[] = $trigger_pattern;
        }
        
        // Severity trend detection
        $severity_trend = $this->detect_severity_trend($recent_episodes);
        if ($severity_trend) {
            $patterns[] = $severity_trend;
        }
        
        // Save detected patterns
        if (!empty($patterns)) {
            $this->save_patterns($user_id, $patterns);
        }
        
        return $patterns;
    }
    
    /**
     * Check if AI analysis should run
     */
    private function should_run_ai_analysis($user_id) {
        // Check membership level
        $membership = $this->get_user_membership($user_id);
        if (!in_array($membership, array('navigator', 'voyager'))) {
            return false;
        }
        
        // Check if AI is enabled globally
        $ai_enabled = get_option('spiralengine_ai_enabled', false);
        if (!$ai_enabled) {
            return false;
        }
        
        // Check user's AI preference
        $user_ai_enabled = get_user_meta($user_id, 'spiral_ai_analysis_enabled', true);
        if ($user_ai_enabled === 'false') {
            return false;
        }
        
        return true;
    }
    
    /**
     * Get AI analysis for episode
     */
    private function get_ai_analysis($episode_id, $data) {
        if (!class_exists('SPIRAL_AI_Service')) {
            return null;
        }
        
        $ai_service = new SPIRAL_AI_Service();
        
        // Prepare context for AI
        $context = array(
            'episode_type' => 'overthinking',
            'severity' => $data['severity'],
            'duration' => $data['duration'],
            'overthinking_type' => $data['overthinking_type'] ?? 'unknown',
            'thought_speed' => $data['thought_speed'] ?? null,
            'thought_stickiness' => $data['thought_stickiness'] ?? null,
            'trigger_category' => $data['trigger_category'] ?? null,
            'coping_strategies_used' => $data['coping_strategies_used'] ?? array()
        );
        
        // Get AI insights
        return $ai_service->analyze_episode($context);
    }
    
    /**
     * Get correlations for display
     */
    public function get_correlations() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'overthinking_correlations')) {
            wp_send_json_error(array('message' => __('Security check failed', 'spiral')));
        }
        
        $user_id = get_current_user_id();
        if (!$user_id) {
            wp_send_json_error(array('message' => __('Please log in', 'spiral')));
        }
        
        // Check permissions
        $access_control = new SpiralEngine_Access_Control();
        if (!$access_control->can_view_correlations($user_id)) {
            wp_send_json_error(array('message' => __('Upgrade to Explorer or higher to view correlations', 'spiral')));
        }
        
        // Get correlations
        if (class_exists('SPIRAL_Correlation_Engine')) {
            $correlation_engine = new SPIRAL_Correlation_Engine();
            $correlations = $correlation_engine->get_user_correlations($user_id, 10);
            
            wp_send_json_success(array(
                'correlations' => $correlations
            ));
        }
        
        wp_send_json_error(array('message' => __('Correlation engine not available', 'spiral')));
    }
    
    /**
     * Render widget preview
     */
    public function render_preview() {
        $user_id = get_current_user_id();
        $last_episode = $this->get_last_episode($user_id);
        $episode_count = $this->get_episode_count($user_id);
        $trend = $this->calculate_trend($user_id);
        
        ob_start();
        include SPIRALENGINE_PLUGIN_DIR . 'templates/widgets/episodes/overthinking-preview.php';
        return ob_get_clean();
    }
    
    /**
     * Render full widget
     */
    public function render_widget($mode = 'full') {
        $user_id = get_current_user_id();
        
        if (!$user_id) {
            return '<div class="spiral-login-required">' . __('Please log in to use the Overthinking Logger', 'spiral') . '</div>';
        }
        
        // Enqueue scripts and styles
        wp_enqueue_script('spiralengine-overthinking-widget');
        wp_enqueue_style('spiralengine-overthinking-widget');
        
        // Get user data
        $membership = $this->get_user_membership($user_id);
        $recent_episodes = $this->get_user_episodes($user_id, array('days' => 7, 'limit' => 5));
        
        ob_start();
        
        if ($mode === 'quick') {
            include SPIRALENGINE_PLUGIN_DIR . 'templates/widgets/episodes/overthinking-quick.php';
        } else {
            include SPIRALENGINE_PLUGIN_DIR . 'templates/widgets/episodes/overthinking-detailed.php';
        }
        
        return ob_get_clean();
    }
}

// Initialize widget
new SpiralEngine_Widget_Overthinking();

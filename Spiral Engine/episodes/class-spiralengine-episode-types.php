<?php
/**
 * Episode Types Registry - Central registry for all episode types
 * 
 * @package    SpiralEngine
 * @subpackage Episodes
 * @file       includes/episodes/class-spiralengine-episode-types.php
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Episode Registry System
 * 
 * This singleton class manages all registered episode types in the system.
 * It handles registration, correlation matrix updates, and provides access
 * to episode type information across the platform.
 */
class SPIRAL_Episode_Registry {
    
    /**
     * Singleton instance
     * @var SPIRAL_Episode_Registry
     */
    private static $instance = null;
    
    /**
     * Registered episode types
     * @var array
     */
    private $registered_episodes = array();
    
    /**
     * Correlation matrix between episode types
     * @var array
     */
    private $correlation_matrix = array();
    
    /**
     * Episode type definitions
     * @var array
     */
    private $episode_definitions = array();
    
    /**
     * Private constructor for singleton
     */
    private function __construct() {
        $this->init();
    }
    
    /**
     * Get singleton instance
     * 
     * @return SPIRAL_Episode_Registry
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Initialize registry
     */
    private function init() {
        // Define core episode types
        $this->define_core_episode_types();
        
        // Load saved correlations
        $this->load_correlation_matrix();
        
        // Register hooks
        add_action('init', array($this, 'register_default_types'), 5);
        add_action('spiral_register_episode_types', array($this, 'allow_custom_types'), 20);
    }
    
    /**
     * Define core episode types
     */
    private function define_core_episode_types() {
        $this->episode_definitions = array(
            'overthinking' => array(
                'name' => __('Overthinking', 'spiral'),
                'description' => __('Rumination and thought spirals', 'spiral'),
                'color' => '#6B46C1',
                'icon' => 'dashicons-admin-page',
                'severity_labels' => array(
                    1 => __('Mild rumination', 'spiral'),
                    3 => __('Moderate spiraling', 'spiral'),
                    5 => __('Significant overthinking', 'spiral'),
                    7 => __('Severe thought loops', 'spiral'),
                    10 => __('Overwhelming rumination', 'spiral')
                ),
                'quick_log_fields' => array('severity', 'primary_thought'),
                'supports_biologicals' => true,
                'ai_prompts' => array(
                    'pattern' => 'Analyze overthinking patterns focusing on triggers, timing, and thought content themes.',
                    'insight' => 'Provide supportive insight about overthinking episode and coping strategies.'
                )
            ),
            
            'anxiety' => array(
                'name' => __('Anxiety', 'spiral'),
                'description' => __('Anxiety and worry episodes', 'spiral'),
                'color' => '#FF6B6B',
                'icon' => 'dashicons-heart',
                'severity_labels' => array(
                    1 => __('Mild unease', 'spiral'),
                    3 => __('Moderate anxiety', 'spiral'),
                    5 => __('Significant anxiety', 'spiral'),
                    7 => __('Severe anxiety', 'spiral'),
                    10 => __('Panic level anxiety', 'spiral')
                ),
                'quick_log_fields' => array('severity', 'physical_symptoms'),
                'supports_biologicals' => true,
                'ai_prompts' => array(
                    'pattern' => 'Analyze anxiety patterns including physical symptoms, triggers, and environmental factors.',
                    'insight' => 'Provide calming insights and grounding techniques for anxiety management.'
                )
            ),
            
            'ptsd' => array(
                'name' => __('PTSD', 'spiral'),
                'description' => __('PTSD symptoms and triggers', 'spiral'),
                'color' => '#4ECDC4',
                'icon' => 'dashicons-shield-alt',
                'severity_labels' => array(
                    1 => __('Mild activation', 'spiral'),
                    3 => __('Moderate symptoms', 'spiral'),
                    5 => __('Significant distress', 'spiral'),
                    7 => __('Severe symptoms', 'spiral'),
                    10 => __('Crisis level', 'spiral')
                ),
                'quick_log_fields' => array('severity', 'trigger_type'),
                'supports_biologicals' => true,
                'requires_confirmation' => true,
                'ai_prompts' => array(
                    'pattern' => 'Analyze PTSD symptom patterns with focus on triggers and safety.',
                    'insight' => 'Provide trauma-informed support and grounding techniques.'
                )
            ),
            
            'depression' => array(
                'name' => __('Depression', 'spiral'),
                'description' => __('Depression and low mood', 'spiral'),
                'color' => '#45B7D1',
                'icon' => 'dashicons-cloud',
                'severity_labels' => array(
                    1 => __('Mild low mood', 'spiral'),
                    3 => __('Moderate depression', 'spiral'),
                    5 => __('Significant depression', 'spiral'),
                    7 => __('Severe depression', 'spiral'),
                    10 => __('Critical depression', 'spiral')
                ),
                'quick_log_fields' => array('severity', 'energy_level'),
                'supports_biologicals' => true,
                'ai_prompts' => array(
                    'pattern' => 'Analyze depression patterns including energy, motivation, and mood changes.',
                    'insight' => 'Provide supportive insights for managing depression symptoms.'
                )
            ),
            
            'caregiver' => array(
                'name' => __('Caregiver Stress', 'spiral'),
                'description' => __('Caregiver burnout and stress', 'spiral'),
                'color' => '#96CEB4',
                'icon' => 'dashicons-groups',
                'severity_labels' => array(
                    1 => __('Mild stress', 'spiral'),
                    3 => __('Moderate strain', 'spiral'),
                    5 => __('Significant burden', 'spiral'),
                    7 => __('Severe burnout', 'spiral'),
                    10 => __('Crisis burnout', 'spiral')
                ),
                'quick_log_fields' => array('severity', 'care_recipient'),
                'supports_biologicals' => true,
                'ai_prompts' => array(
                    'pattern' => 'Analyze caregiver stress patterns and burnout indicators.',
                    'insight' => 'Provide self-care strategies and boundary setting guidance.'
                )
            ),
            
            'panic' => array(
                'name' => __('Panic Attack', 'spiral'),
                'description' => __('Panic attacks and acute anxiety', 'spiral'),
                'color' => '#FFA07A',
                'icon' => 'dashicons-warning',
                'severity_labels' => array(
                    1 => __('Mild panic', 'spiral'),
                    3 => __('Building panic', 'spiral'),
                    5 => __('Moderate attack', 'spiral'),
                    7 => __('Severe attack', 'spiral'),
                    10 => __('Extreme panic', 'spiral')
                ),
                'quick_log_fields' => array('severity', 'duration', 'location'),
                'supports_biologicals' => true,
                'ai_prompts' => array(
                    'pattern' => 'Analyze panic attack patterns, triggers, and duration.',
                    'insight' => 'Provide immediate coping strategies and breathing techniques.'
                )
            ),
            
            'dissociation' => array(
                'name' => __('Dissociation', 'spiral'),
                'description' => __('Dissociation and disconnection', 'spiral'),
                'color' => '#DDA0DD',
                'icon' => 'dashicons-visibility',
                'severity_labels' => array(
                    1 => __('Mild spacing out', 'spiral'),
                    3 => __('Moderate disconnection', 'spiral'),
                    5 => __('Significant dissociation', 'spiral'),
                    7 => __('Severe dissociation', 'spiral'),
                    10 => __('Complete disconnection', 'spiral')
                ),
                'quick_log_fields' => array('severity', 'type', 'duration'),
                'supports_biologicals' => true,
                'ai_prompts' => array(
                    'pattern' => 'Analyze dissociation patterns and grounding effectiveness.',
                    'insight' => 'Provide grounding techniques and reconnection strategies.'
                )
            )
        );
    }
    
    /**
     * Register default episode types
     */
    public function register_default_types() {
        // Register overthinking first as it's the primary type
        $this->register_episode_type('overthinking', array(
            'class' => 'Overthinking_Widget_Full',
            'name' => $this->episode_definitions['overthinking']['name'],
            'description' => $this->episode_definitions['overthinking']['description'],
            'color' => $this->episode_definitions['overthinking']['color'],
            'icon' => $this->episode_definitions['overthinking']['icon'],
            'weight' => 1.0,
            'correlations' => array(
                'anxiety' => 0.7,      // Strong correlation
                'depression' => 0.5,   // Moderate correlation
                'ptsd' => 0.4,        // Weaker correlation
                'panic' => 0.6        // Moderate-strong correlation
            ),
            'enabled' => true
        ));
        
        // Register anxiety
        $this->register_episode_type('anxiety', array(
            'class' => 'Anxiety_Widget_Full',
            'name' => $this->episode_definitions['anxiety']['name'],
            'description' => $this->episode_definitions['anxiety']['description'],
            'color' => $this->episode_definitions['anxiety']['color'],
            'icon' => $this->episode_definitions['anxiety']['icon'],
            'weight' => 0.9,
            'correlations' => array(
                'overthinking' => 0.7,
                'panic' => 0.8,       // Strong correlation
                'ptsd' => 0.5,
                'depression' => 0.4
            ),
            'enabled' => true
        ));
        
        // Register PTSD
        $this->register_episode_type('ptsd', array(
            'class' => 'PTSD_Widget_Full',
            'name' => $this->episode_definitions['ptsd']['name'],
            'description' => $this->episode_definitions['ptsd']['description'],
            'color' => $this->episode_definitions['ptsd']['color'],
            'icon' => $this->episode_definitions['ptsd']['icon'],
            'weight' => 1.1, // Slightly higher weight due to severity
            'correlations' => array(
                'anxiety' => 0.5,
                'depression' => 0.6,
                'dissociation' => 0.7, // Strong correlation
                'panic' => 0.6
            ),
            'enabled' => true
        ));
        
        // Register depression
        $this->register_episode_type('depression', array(
            'class' => 'Depression_Widget_Full',
            'name' => $this->episode_definitions['depression']['name'],
            'description' => $this->episode_definitions['depression']['description'],
            'color' => $this->episode_definitions['depression']['color'],
            'icon' => $this->episode_definitions['depression']['icon'],
            'weight' => 1.0,
            'correlations' => array(
                'overthinking' => 0.5,
                'anxiety' => 0.4,
                'ptsd' => 0.6,
                'caregiver' => 0.5
            ),
            'enabled' => true
        ));
        
        // Register caregiver
        $this->register_episode_type('caregiver', array(
            'class' => 'Caregiver_Widget_Full',
            'name' => $this->episode_definitions['caregiver']['name'],
            'description' => $this->episode_definitions['caregiver']['description'],
            'color' => $this->episode_definitions['caregiver']['color'],
            'icon' => $this->episode_definitions['caregiver']['icon'],
            'weight' => 0.8,
            'correlations' => array(
                'depression' => 0.5,
                'anxiety' => 0.6,
                'overthinking' => 0.4
            ),
            'enabled' => true
        ));
        
        // Register panic
        $this->register_episode_type('panic', array(
            'class' => 'Panic_Widget_Full',
            'name' => $this->episode_definitions['panic']['name'],
            'description' => $this->episode_definitions['panic']['description'],
            'color' => $this->episode_definitions['panic']['color'],
            'icon' => $this->episode_definitions['panic']['icon'],
            'weight' => 1.2, // Higher weight due to acute nature
            'correlations' => array(
                'anxiety' => 0.8,      // Very strong correlation
                'overthinking' => 0.6,
                'ptsd' => 0.6
            ),
            'enabled' => false // Disabled by default, requires activation
        ));
        
        // Register dissociation
        $this->register_episode_type('dissociation', array(
            'class' => 'Dissociation_Widget_Full',
            'name' => $this->episode_definitions['dissociation']['name'],
            'description' => $this->episode_definitions['dissociation']['description'],
            'color' => $this->episode_definitions['dissociation']['color'],
            'icon' => $this->episode_definitions['dissociation']['icon'],
            'weight' => 1.0,
            'correlations' => array(
                'ptsd' => 0.7,        // Strong correlation
                'anxiety' => 0.5,
                'depression' => 0.4
            ),
            'enabled' => false // Disabled by default, requires activation
        ));
    }
    
    /**
     * Register an episode type
     * 
     * @param string $type Episode type identifier
     * @param array $config Configuration array
     */
    public function register_episode_type($type, $config) {
        // Validate required fields
        $required = array('class', 'name', 'description', 'color', 'icon');
        foreach ($required as $field) {
            if (!isset($config[$field])) {
                throw new Exception("Missing required field: $field for episode type: $type");
            }
        }
        
        // Merge with definition if exists
        if (isset($this->episode_definitions[$type])) {
            $config = array_merge($this->episode_definitions[$type], $config);
        }
        
        // Register the episode type
        $this->registered_episodes[$type] = array(
            'widget_class' => $config['class'],
            'display_name' => $config['name'],
            'description' => $config['description'],
            'color' => $config['color'],
            'icon' => $config['icon'],
            'severity_weight' => isset($config['weight']) ? $config['weight'] : 1.0,
            'correlatable_with' => isset($config['correlations']) ? $config['correlations'] : array(),
            'enabled' => isset($config['enabled']) ? $config['enabled'] : true,
            'severity_labels' => isset($config['severity_labels']) ? $config['severity_labels'] : array(),
            'quick_log_fields' => isset($config['quick_log_fields']) ? $config['quick_log_fields'] : array(),
            'supports_biologicals' => isset($config['supports_biologicals']) ? $config['supports_biologicals'] : true,
            'ai_prompts' => isset($config['ai_prompts']) ? $config['ai_prompts'] : array()
        );
        
        // Update correlation matrix
        if (isset($config['correlations']) && !empty($config['correlations'])) {
            $this->update_correlation_matrix($type, $config['correlations']);
        }
        
        // Fire registration hook
        do_action('spiral_episode_type_registered', $type, $config);
    }
    
    /**
     * Update correlation matrix
     * 
     * @param string $type Episode type
     * @param array $correlations Correlation strengths
     */
    private function update_correlation_matrix($type, $correlations) {
        if (!isset($this->correlation_matrix[$type])) {
            $this->correlation_matrix[$type] = array();
        }
        
        foreach ($correlations as $related_type => $strength) {
            // Set bidirectional correlation
            $this->correlation_matrix[$type][$related_type] = $strength;
            
            if (!isset($this->correlation_matrix[$related_type])) {
                $this->correlation_matrix[$related_type] = array();
            }
            $this->correlation_matrix[$related_type][$type] = $strength;
        }
        
        // Save updated matrix
        $this->save_correlation_matrix();
    }
    
    /**
     * Get all registered episode types
     * 
     * @param bool $enabled_only Return only enabled types
     * @return array
     */
    public function get_episode_types($enabled_only = true) {
        if (!$enabled_only) {
            return $this->registered_episodes;
        }
        
        return array_filter($this->registered_episodes, function($type) {
            return $type['enabled'];
        });
    }
    
    /**
     * Get episode type configuration
     * 
     * @param string $type Episode type
     * @return array|null
     */
    public function get_episode_type($type) {
        return isset($this->registered_episodes[$type]) ? $this->registered_episodes[$type] : null;
    }
    
    /**
     * Check if episode type is registered
     * 
     * @param string $type Episode type
     * @return bool
     */
    public function is_registered($type) {
        return isset($this->registered_episodes[$type]);
    }
    
    /**
     * Check if episode type is enabled
     * 
     * @param string $type Episode type
     * @return bool
     */
    public function is_enabled($type) {
        return isset($this->registered_episodes[$type]) && $this->registered_episodes[$type]['enabled'];
    }
    
    /**
     * Check if types can correlate
     * 
     * @param string $type1 First episode type
     * @param string $type2 Second episode type
     * @return bool
     */
    public function can_correlate($type1, $type2) {
        return isset($this->correlation_matrix[$type1][$type2]) && 
               $this->correlation_matrix[$type1][$type2] > 0;
    }
    
    /**
     * Get correlation strength between types
     * 
     * @param string $type1 First episode type
     * @param string $type2 Second episode type
     * @return float
     */
    public function get_correlation_strength($type1, $type2) {
        if (!$this->can_correlate($type1, $type2)) {
            return 0;
        }
        
        return $this->correlation_matrix[$type1][$type2];
    }
    
    /**
     * Get all correlations for a type
     * 
     * @param string $type Episode type
     * @return array
     */
    public function get_type_correlations($type) {
        return isset($this->correlation_matrix[$type]) ? $this->correlation_matrix[$type] : array();
    }
    
    /**
     * Get correlation matrix
     * 
     * @return array
     */
    public function get_correlation_matrix() {
        return $this->correlation_matrix;
    }
    
    /**
     * Enable episode type
     * 
     * @param string $type Episode type
     * @return bool
     */
    public function enable_type($type) {
        if (!$this->is_registered($type)) {
            return false;
        }
        
        $this->registered_episodes[$type]['enabled'] = true;
        
        // Fire hook
        do_action('spiral_episode_type_enabled', $type);
        
        return true;
    }
    
    /**
     * Disable episode type
     * 
     * @param string $type Episode type
     * @return bool
     */
    public function disable_type($type) {
        if (!$this->is_registered($type)) {
            return false;
        }
        
        $this->registered_episodes[$type]['enabled'] = false;
        
        // Fire hook
        do_action('spiral_episode_type_disabled', $type);
        
        return true;
    }
    
    /**
     * Get episode color
     * 
     * @param string $type Episode type
     * @return string
     */
    public function get_episode_color($type) {
        return $this->is_registered($type) ? $this->registered_episodes[$type]['color'] : '#999999';
    }
    
    /**
     * Get episode icon
     * 
     * @param string $type Episode type
     * @return string
     */
    public function get_episode_icon($type) {
        return $this->is_registered($type) ? $this->registered_episodes[$type]['icon'] : 'dashicons-marker';
    }
    
    /**
     * Get severity labels for type
     * 
     * @param string $type Episode type
     * @return array
     */
    public function get_severity_labels($type) {
        return $this->is_registered($type) ? $this->registered_episodes[$type]['severity_labels'] : array();
    }
    
    /**
     * Load correlation matrix from database
     */
    private function load_correlation_matrix() {
        $saved_matrix = get_option('spiral_episode_correlation_matrix', array());
        if (!empty($saved_matrix)) {
            $this->correlation_matrix = $saved_matrix;
        }
    }
    
    /**
     * Save correlation matrix to database
     */
    private function save_correlation_matrix() {
        update_option('spiral_episode_correlation_matrix', $this->correlation_matrix);
    }
    
    /**
     * Allow custom episode types to register
     */
    public function allow_custom_types() {
        // This hook allows third-party plugins to register custom episode types
        do_action('spiral_register_custom_episode_types', $this);
    }
    
    /**
     * Get episode type statistics
     * 
     * @return array
     */
    public function get_type_statistics() {
        global $wpdb;
        
        $stats = array();
        
        foreach ($this->get_episode_types() as $type => $config) {
            $count = $wpdb->get_var($wpdb->prepare("
                SELECT COUNT(*) 
                FROM {$wpdb->prefix}spiralengine_episodes 
                WHERE episode_type = %s
            ", $type));
            
            $avg_severity = $wpdb->get_var($wpdb->prepare("
                SELECT AVG(severity_score) 
                FROM {$wpdb->prefix}spiralengine_episodes 
                WHERE episode_type = %s
            ", $type));
            
            $stats[$type] = array(
                'total_episodes' => intval($count),
                'average_severity' => round(floatval($avg_severity), 1),
                'color' => $config['color'],
                'icon' => $config['icon'],
                'name' => $config['display_name']
            );
        }
        
        return $stats;
    }
}

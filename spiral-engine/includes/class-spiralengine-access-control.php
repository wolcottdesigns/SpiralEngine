<?php
/**
 * SPIRAL Engine Access Control
 * 
 * @package    SPIRAL_Engine
 * @subpackage Core
 * @file       includes/class-spiralengine-access-control.php
 * @since      1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Access Control Class
 * 
 * Manages feature access based on membership tiers with caching
 * and intelligent permission checking.
 */
class SpiralEngine_Access_Control {
    
    /**
     * Instance of this class
     * 
     * @var SpiralEngine_Access_Control
     */
    private static $instance = null;
    
    /**
     * MemberPress integration
     * 
     * @var SpiralEngine_MemberPress
     */
    private $memberpress;
    
    /**
     * Feature access cache
     * 
     * @var array
     */
    private $access_cache = array();
    
    /**
     * Feature matrix from Dashboard Command Center specifications
     * 
     * @var array
     */
    private $feature_matrix = array(
        // Core Features
        'basic_logging' => array(
            'tiers' => array('discovery', 'explorer', 'pioneer', 'navigator', 'voyager'),
            'name' => 'Basic Episode Logging',
            'description' => 'Log your episodes with essential details'
        ),
        'dashboard_access' => array(
            'tiers' => array('discovery', 'explorer', 'pioneer', 'navigator', 'voyager'),
            'name' => 'Personal Dashboard',
            'description' => 'View your personal statistics and recent activity'
        ),
        'quick_logger' => array(
            'tiers' => array('discovery', 'explorer', 'pioneer', 'navigator', 'voyager'),
            'name' => 'Quick Episode Logger',
            'description' => 'Fast episode entry from dashboard'
        ),
        
        // Explorer Features
        'pattern_detection' => array(
            'tiers' => array('explorer', 'pioneer', 'navigator', 'voyager'),
            'name' => 'Pattern Detection',
            'description' => 'Discover when and why episodes occur',
            'teaser' => true
        ),
        'basic_correlations' => array(
            'tiers' => array('explorer', 'pioneer', 'navigator', 'voyager'),
            'name' => 'Episode Correlations',
            'description' => 'See how different episode types connect',
            'teaser' => true
        ),
        'timeline_view' => array(
            'tiers' => array('explorer', 'pioneer', 'navigator', 'voyager'),
            'name' => 'Personal Timeline',
            'description' => 'Visual timeline of your episodes'
        ),
        'severity_trends' => array(
            'tiers' => array('explorer', 'pioneer', 'navigator', 'voyager'),
            'name' => 'Severity Trends',
            'description' => 'Track episode severity over time'
        ),
        
        // Pioneer Features
        'advanced_analytics' => array(
            'tiers' => array('pioneer', 'navigator', 'voyager'),
            'name' => 'Advanced Analytics',
            'description' => 'Deep insights into your mental health patterns'
        ),
        'trigger_analysis' => array(
            'tiers' => array('pioneer', 'navigator', 'voyager'),
            'name' => 'Trigger Analysis',
            'description' => 'Identify and track episode triggers'
        ),
        'personalized_insights' => array(
            'tiers' => array('pioneer', 'navigator', 'voyager'),
            'name' => 'Personalized Insights',
            'description' => 'Recommendations based on your data'
        ),
        'biological_tracking' => array(
            'tiers' => array('pioneer', 'navigator', 'voyager'),
            'name' => 'Biological Factor Tracking',
            'description' => 'Track hormonal and biological influences'
        ),
        
        // Navigator Features
        'ai_forecast' => array(
            'tiers' => array('navigator', 'voyager'),
            'name' => 'AI-Powered Forecasts',
            'description' => 'Predict episode risk with AI',
            'teaser' => true
        ),
        'unified_forecast' => array(
            'tiers' => array('navigator', 'voyager'),
            'name' => 'Unified Episode Forecast',
            'description' => 'Combined predictions across all episode types'
        ),
        'export_data' => array(
            'tiers' => array('navigator', 'voyager'),
            'name' => 'Data Export',
            'description' => 'Export your data in multiple formats'
        ),
        'advanced_reports' => array(
            'tiers' => array('navigator', 'voyager'),
            'name' => 'Professional Reports',
            'description' => 'Generate detailed reports for healthcare providers'
        ),
        'caregiver_access' => array(
            'tiers' => array('navigator', 'voyager'),
            'name' => 'Caregiver Portal',
            'description' => 'Share access with trusted caregivers'
        ),
        
        // Voyager Features
        'api_access' => array(
            'tiers' => array('voyager'),
            'name' => 'API Access',
            'description' => 'Integrate with external applications'
        ),
        'custom_widgets' => array(
            'tiers' => array('voyager'),
            'name' => 'Custom Widget Creation',
            'description' => 'Build your own tracking widgets'
        ),
        'white_label' => array(
            'tiers' => array('voyager'),
            'name' => 'White Label Options',
            'description' => 'Customize branding for your practice'
        ),
        'bulk_operations' => array(
            'tiers' => array('voyager'),
            'name' => 'Bulk Data Operations',
            'description' => 'Import and manage data in bulk'
        ),
        'priority_support' => array(
            'tiers' => array('voyager'),
            'name' => 'Priority Support',
            'description' => '24/7 priority support access'
        )
    );
    
    /**
     * Widget section restrictions
     * 
     * @var array
     */
    private $widget_section_matrix = array(
        // Episode Logger Sections
        'quick_logger' => array(
            'tiers' => array('discovery', 'explorer', 'pioneer', 'navigator', 'voyager'),
            'restriction_type' => 'none'
        ),
        'episode_basics' => array(
            'tiers' => array('discovery', 'explorer', 'pioneer', 'navigator', 'voyager'),
            'restriction_type' => 'none'
        ),
        'pattern_analysis' => array(
            'tiers' => array('explorer', 'pioneer', 'navigator', 'voyager'),
            'restriction_type' => 'blur',
            'preview_content' => true
        ),
        'correlation_insights' => array(
            'tiers' => array('explorer', 'pioneer', 'navigator', 'voyager'),
            'restriction_type' => 'blur',
            'preview_content' => true
        ),
        'ai_insights' => array(
            'tiers' => array('navigator', 'voyager'),
            'restriction_type' => 'blur',
            'show_sample' => true
        ),
        'forecast_section' => array(
            'tiers' => array('navigator', 'voyager'),
            'restriction_type' => 'blur',
            'show_sample' => true
        ),
        'api_settings' => array(
            'tiers' => array('voyager'),
            'restriction_type' => 'hide'
        )
    );
    
    /**
     * Get instance
     * 
     * @return SpiralEngine_Access_Control
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->memberpress = SpiralEngine_MemberPress::get_instance();
        $this->init_hooks();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Clear cache on membership changes
        add_action('spiralengine_membership_updated', array($this, 'handle_membership_update'), 10, 3);
        
        // Widget access filters
        add_filter('spiralengine_widget_section_access', array($this, 'check_widget_section_access'), 10, 3);
        add_filter('spiralengine_feature_access', array($this, 'check_feature_access'), 10, 2);
        
        // AJAX handlers
        add_action('wp_ajax_spiralengine_check_feature_access', array($this, 'ajax_check_feature_access'));
        add_action('wp_ajax_nopriv_spiralengine_check_feature_access', array($this, 'ajax_check_feature_access'));
    }
    
    /**
     * Check if user has access to feature
     * 
     * @param string $feature Feature key
     * @param int    $user_id User ID (optional, defaults to current user)
     * @return array Access information
     */
    public function check_feature_access($feature, $user_id = null) {
        if (null === $user_id) {
            $user_id = get_current_user_id();
        }
        
        // Check cache first
        $cache_key = $user_id . '_' . $feature;
        if (isset($this->access_cache[$cache_key])) {
            return $this->access_cache[$cache_key];
        }
        
        // Default response
        $access = array(
            'has_access' => false,
            'reason' => 'unknown_feature',
            'message' => __('This feature is not available', 'spiralengine'),
            'required_tier' => null,
            'upgrade_url' => null
        );
        
        // Check if feature exists
        if (!isset($this->feature_matrix[$feature])) {
            $this->access_cache[$cache_key] = $access;
            return $access;
        }
        
        $feature_info = $this->feature_matrix[$feature];
        $user_tier = $this->memberpress->get_user_tier($user_id);
        
        // Check if user's tier has access
        if (in_array($user_tier, $feature_info['tiers'])) {
            $access = array(
                'has_access' => true,
                'feature_name' => $feature_info['name'],
                'description' => $feature_info['description']
            );
        } else {
            // Find minimum required tier
            $required_tier = $this->get_minimum_tier($feature_info['tiers']);
            
            $access = array(
                'has_access' => false,
                'reason' => 'insufficient_tier',
                'message' => sprintf(
                    __('%s requires %s membership or higher', 'spiralengine'),
                    $feature_info['name'],
                    $this->memberpress->get_tier_info($required_tier)['name']
                ),
                'required_tier' => $required_tier,
                'feature_name' => $feature_info['name'],
                'description' => $feature_info['description'],
                'upgrade_url' => $this->memberpress->get_upgrade_url($required_tier),
                'show_teaser' => isset($feature_info['teaser']) ? $feature_info['teaser'] : false
            );
        }
        
        // Cache result
        $this->access_cache[$cache_key] = $access;
        
        return $access;
    }
    
    /**
     * Check widget section access
     * 
     * @param string $section Section key
     * @param int    $user_id User ID
     * @return array
     */
    public function check_widget_section_access($access, $section, $user_id) {
        if (!isset($this->widget_section_matrix[$section])) {
            return array(
                'has_access' => true,
                'restriction_type' => 'none'
            );
        }
        
        $section_info = $this->widget_section_matrix[$section];
        $user_tier = $this->memberpress->get_user_tier($user_id);
        
        if (in_array($user_tier, $section_info['tiers'])) {
            return array(
                'has_access' => true,
                'restriction_type' => 'none'
            );
        }
        
        // Find minimum required tier
        $required_tier = $this->get_minimum_tier($section_info['tiers']);
        
        return array(
            'has_access' => false,
            'restriction_type' => $section_info['restriction_type'],
            'required_tier' => $required_tier,
            'upgrade_url' => $this->memberpress->get_upgrade_url($required_tier),
            'preview_content' => isset($section_info['preview_content']) ? $section_info['preview_content'] : false,
            'show_sample' => isset($section_info['show_sample']) ? $section_info['show_sample'] : false
        );
    }
    
    /**
     * Get features available for tier
     * 
     * @param string $tier Tier key
     * @return array
     */
    public function get_tier_features($tier) {
        $features = array();
        
        foreach ($this->feature_matrix as $feature_key => $feature_info) {
            if (in_array($tier, $feature_info['tiers'])) {
                $features[$feature_key] = array(
                    'name' => $feature_info['name'],
                    'description' => $feature_info['description']
                );
            }
        }
        
        return $features;
    }
    
    /**
     * Get feature comparison matrix
     * 
     * @return array
     */
    public function get_feature_comparison() {
        $tiers = $this->memberpress->get_all_tiers();
        $comparison = array();
        
        foreach ($this->feature_matrix as $feature_key => $feature_info) {
            $comparison[$feature_key] = array(
                'name' => $feature_info['name'],
                'description' => $feature_info['description'],
                'tiers' => array()
            );
            
            foreach ($tiers as $tier_key => $tier_info) {
                $comparison[$feature_key]['tiers'][$tier_key] = in_array($tier_key, $feature_info['tiers']);
            }
        }
        
        return $comparison;
    }
    
    /**
     * Get minimum tier from array
     * 
     * @param array $tiers
     * @return string
     */
    private function get_minimum_tier($tiers) {
        $all_tiers = $this->memberpress->get_all_tiers();
        $min_level = PHP_INT_MAX;
        $min_tier = 'discovery';
        
        foreach ($tiers as $tier) {
            if (isset($all_tiers[$tier]) && $all_tiers[$tier]['level'] < $min_level) {
                $min_level = $all_tiers[$tier]['level'];
                $min_tier = $tier;
            }
        }
        
        return $min_tier;
    }
    
    /**
     * Clear user cache
     * 
     * @param int $user_id
     */
    public function clear_user_cache($user_id) {
        foreach ($this->access_cache as $key => $value) {
            if (strpos($key, $user_id . '_') === 0) {
                unset($this->access_cache[$key]);
            }
        }
    }
    
    /**
     * Clear all caches
     */
    public function clear_all_caches() {
        $this->access_cache = array();
    }
    
    /**
     * Handle membership update
     * 
     * @param int    $user_id
     * @param string $new_tier
     * @param string $action
     */
    public function handle_membership_update($user_id, $new_tier, $action) {
        $this->clear_user_cache($user_id);
        
        // Log access changes
        $this->log_access_change($user_id, $new_tier, $action);
    }
    
    /**
     * Log access change
     * 
     * @param int    $user_id
     * @param string $new_tier
     * @param string $action
     */
    private function log_access_change($user_id, $new_tier, $action) {
        global $wpdb;
        
        $features_gained = array();
        $features_lost = array();
        
        // Get old tier from log or meta
        $old_tier = get_user_meta($user_id, '_spiralengine_previous_tier', true);
        
        if ($old_tier && $old_tier !== $new_tier) {
            $old_features = $this->get_tier_features($old_tier);
            $new_features = $this->get_tier_features($new_tier);
            
            $features_gained = array_diff_key($new_features, $old_features);
            $features_lost = array_diff_key($old_features, $new_features);
        }
        
        // Update previous tier
        update_user_meta($user_id, '_spiralengine_previous_tier', $new_tier);
        
        // Log changes
        $table_name = $wpdb->prefix . 'spiralengine_access_log';
        
        $wpdb->insert(
            $table_name,
            array(
                'user_id' => $user_id,
                'action' => $action,
                'old_tier' => $old_tier,
                'new_tier' => $new_tier,
                'features_gained' => json_encode(array_keys($features_gained)),
                'features_lost' => json_encode(array_keys($features_lost)),
                'timestamp' => current_time('mysql')
            ),
            array('%d', '%s', '%s', '%s', '%s', '%s', '%s')
        );
    }
    
    /**
     * Ajax check feature access
     */
    public function ajax_check_feature_access() {
        // Check nonce
        if (!check_ajax_referer('spiralengine_ajax_nonce', 'nonce', false)) {
            wp_send_json_error('Security check failed');
        }
        
        $feature = isset($_POST['feature']) ? sanitize_text_field($_POST['feature']) : '';
        $user_id = get_current_user_id();
        
        if (empty($feature)) {
            wp_send_json_error('Invalid feature');
        }
        
        $access = $this->check_feature_access($feature, $user_id);
        
        wp_send_json_success($access);
    }
    
    /**
     * Get user's available features
     * 
     * @param int $user_id
     * @return array
     */
    public function get_user_features($user_id = null) {
        if (null === $user_id) {
            $user_id = get_current_user_id();
        }
        
        $user_tier = $this->memberpress->get_user_tier($user_id);
        return $this->get_tier_features($user_tier);
    }
    
    /**
     * Check multiple features at once
     * 
     * @param array $features
     * @param int   $user_id
     * @return array
     */
    public function check_multiple_features($features, $user_id = null) {
        if (null === $user_id) {
            $user_id = get_current_user_id();
        }
        
        $results = array();
        
        foreach ($features as $feature) {
            $results[$feature] = $this->check_feature_access($feature, $user_id);
        }
        
        return $results;
    }
}

// Initialize
add_action('init', array('SpiralEngine_Access_Control', 'get_instance'), 10);

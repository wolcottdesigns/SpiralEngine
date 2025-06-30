<?php
/**
 * SPIRAL Engine Feature Gates
 * 
 * @package    SPIRAL_Engine
 * @subpackage Core
 * @file       includes/class-spiralengine-feature-gates.php
 * @since      1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Feature Gates Class
 * 
 * Implements comprehensive feature gating system with dynamic assignment,
 * widget section control, and API restriction management.
 */
class SpiralEngine_Feature_Gates {
    
    /**
     * Instance of this class
     * 
     * @var SpiralEngine_Feature_Gates
     */
    private static $instance = null;
    
    /**
     * Access control instance
     * 
     * @var SpiralEngine_Access_Control
     */
    private $access_control;
    
    /**
     * MemberPress instance
     * 
     * @var SpiralEngine_MemberPress
     */
    private $memberpress;
    
    /**
     * Feature gate definitions
     * 
     * @var array
     */
    private $feature_gates = array(
        // Widget Features
        'widgets' => array(
            'episode_loggers' => array(
                'quick_logger' => array(
                    'tiers' => array('discovery', 'explorer', 'pioneer', 'navigator', 'voyager'),
                    'gate_type' => 'none'
                ),
                'detailed_logger' => array(
                    'tiers' => array('discovery', 'explorer', 'pioneer', 'navigator', 'voyager'),
                    'gate_type' => 'none'
                ),
                'pattern_view' => array(
                    'tiers' => array('explorer', 'pioneer', 'navigator', 'voyager'),
                    'gate_type' => 'visual',
                    'restriction' => 'blur'
                ),
                'correlation_view' => array(
                    'tiers' => array('explorer', 'pioneer', 'navigator', 'voyager'),
                    'gate_type' => 'visual',
                    'restriction' => 'blur'
                ),
                'ai_insights' => array(
                    'tiers' => array('navigator', 'voyager'),
                    'gate_type' => 'visual',
                    'restriction' => 'blur',
                    'sample_content' => true
                ),
                'forecast_view' => array(
                    'tiers' => array('navigator', 'voyager'),
                    'gate_type' => 'visual',
                    'restriction' => 'blur',
                    'sample_content' => true
                )
            ),
            'analytics' => array(
                'basic_stats' => array(
                    'tiers' => array('discovery', 'explorer', 'pioneer', 'navigator', 'voyager'),
                    'gate_type' => 'none'
                ),
                'advanced_analytics' => array(
                    'tiers' => array('pioneer', 'navigator', 'voyager'),
                    'gate_type' => 'functional',
                    'fallback' => 'basic_stats'
                ),
                'export_functionality' => array(
                    'tiers' => array('navigator', 'voyager'),
                    'gate_type' => 'functional',
                    'disabled_message' => 'Data export requires Navigator membership'
                ),
                'custom_reports' => array(
                    'tiers' => array('voyager'),
                    'gate_type' => 'functional',
                    'disabled_message' => 'Custom reports require Voyager membership'
                )
            ),
            'dashboard' => array(
                'widgets_limit' => array(
                    'discovery' => 3,
                    'explorer' => 5,
                    'pioneer' => 8,
                    'navigator' => 12,
                    'voyager' => 'unlimited'
                ),
                'refresh_rate' => array(
                    'discovery' => 300, // 5 minutes
                    'explorer' => 180, // 3 minutes
                    'pioneer' => 120,  // 2 minutes
                    'navigator' => 60,  // 1 minute
                    'voyager' => 30    // 30 seconds
                )
            )
        ),
        
        // API Features
        'api' => array(
            'endpoints' => array(
                'read_episodes' => array(
                    'tiers' => array('voyager'),
                    'rate_limit' => array(
                        'voyager' => 1000 // per hour
                    )
                ),
                'write_episodes' => array(
                    'tiers' => array('voyager'),
                    'rate_limit' => array(
                        'voyager' => 500 // per hour
                    )
                ),
                'analytics_api' => array(
                    'tiers' => array('voyager'),
                    'rate_limit' => array(
                        'voyager' => 100 // per hour
                    )
                ),
                'webhooks' => array(
                    'tiers' => array('voyager'),
                    'limits' => array(
                        'voyager' => 10 // max webhooks
                    )
                )
            ),
            'authentication' => array(
                'api_keys' => array(
                    'tiers' => array('voyager'),
                    'limits' => array(
                        'voyager' => 5 // max API keys
                    )
                ),
                'oauth' => array(
                    'tiers' => array('voyager'),
                    'enabled' => true
                )
            )
        ),
        
        // Data Features
        'data' => array(
            'storage_limits' => array(
                'discovery' => array(
                    'episodes' => 100,
                    'files' => 0,
                    'total_size' => '10MB'
                ),
                'explorer' => array(
                    'episodes' => 1000,
                    'files' => 10,
                    'total_size' => '100MB'
                ),
                'pioneer' => array(
                    'episodes' => 10000,
                    'files' => 100,
                    'total_size' => '1GB'
                ),
                'navigator' => array(
                    'episodes' => 100000,
                    'files' => 1000,
                    'total_size' => '10GB'
                ),
                'voyager' => array(
                    'episodes' => 'unlimited',
                    'files' => 'unlimited',
                    'total_size' => 'unlimited'
                )
            ),
            'retention' => array(
                'discovery' => 90,      // days
                'explorer' => 365,      // days
                'pioneer' => 730,       // days (2 years)
                'navigator' => 1825,    // days (5 years)
                'voyager' => 'forever'
            )
        ),
        
        // AI Features
        'ai' => array(
            'tokens_per_month' => array(
                'discovery' => 0,
                'explorer' => 10000,
                'pioneer' => 50000,
                'navigator' => 200000,
                'voyager' => 1000000
            ),
            'models' => array(
                'basic_analysis' => array(
                    'tiers' => array('explorer', 'pioneer', 'navigator', 'voyager'),
                    'model' => 'gpt-3.5-turbo'
                ),
                'advanced_analysis' => array(
                    'tiers' => array('navigator', 'voyager'),
                    'model' => 'gpt-4'
                ),
                'custom_prompts' => array(
                    'tiers' => array('voyager'),
                    'enabled' => true
                )
            )
        )
    );
    
    /**
     * Dynamic feature assignments
     * 
     * @var array
     */
    private $dynamic_assignments = array();
    
    /**
     * Get instance
     * 
     * @return SpiralEngine_Feature_Gates
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
        $this->access_control = SpiralEngine_Access_Control::get_instance();
        $this->memberpress = SpiralEngine_MemberPress::get_instance();
        
        $this->init_hooks();
        $this->load_dynamic_assignments();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Gate enforcement
        add_filter('spiralengine_can_access_feature', array($this, 'enforce_gate'), 10, 3);
        add_filter('spiralengine_widget_section_render', array($this, 'gate_widget_section'), 10, 4);
        add_filter('spiralengine_api_access', array($this, 'gate_api_access'), 10, 3);
        
        // Dynamic assignment
        add_action('spiralengine_membership_updated', array($this, 'update_dynamic_assignments'), 10, 3);
        
        // Resource limits
        add_filter('spiralengine_check_storage_limit', array($this, 'check_storage_limit'), 10, 2);
        add_filter('spiralengine_check_api_rate_limit', array($this, 'check_api_rate_limit'), 10, 3);
        
        // Admin interface
        add_action('admin_menu', array($this, 'add_gates_page'), 20);
        
        // AJAX handlers
        add_action('wp_ajax_spiralengine_update_feature_gate', array($this, 'ajax_update_gate'));
        add_action('wp_ajax_spiralengine_test_feature_gate', array($this, 'ajax_test_gate'));
    }
    
    /**
     * Load dynamic assignments
     */
    private function load_dynamic_assignments() {
        $this->dynamic_assignments = get_option('spiralengine_dynamic_gates', array());
    }
    
    /**
     * Enforce feature gate
     * 
     * @param bool   $can_access Default access
     * @param string $feature Feature key
    * @param int    $user_id User ID
     * @return bool
     */
    public function enforce_gate($can_access, $feature, $user_id) {
        // Check if feature has a gate
        $gate = $this->find_feature_gate($feature);
        
        if (!$gate) {
            return $can_access;
        }
        
        // Check dynamic assignments first
        if ($this->has_dynamic_assignment($user_id, $feature)) {
            return true;
        }
        
        // Get user tier
        $user_tier = $this->memberpress->get_user_tier($user_id);
        
        // Check if tier has access
        if (isset($gate['tiers']) && in_array($user_tier, $gate['tiers'])) {
            return true;
        }
        
        // Check for tier-specific limits
        if (isset($gate[$user_tier])) {
            return $this->check_tier_limit($feature, $user_tier, $user_id);
        }
        
        return false;
    }
    
    /**
     * Gate widget section rendering
     * 
     * @param string $content Section content
     * @param string $section Section key
     * @param array  $widget Widget data
     * @param int    $user_id User ID
     * @return string
     */
    public function gate_widget_section($content, $section, $widget, $user_id) {
        // Find section gate
        $widget_type = $widget['type'];
        $gate_path = "widgets.{$widget_type}.{$section}";
        $gate = $this->get_gate_by_path($gate_path);
        
        if (!$gate) {
            return $content;
        }
        
        // Check access
        $user_tier = $this->memberpress->get_user_tier($user_id);
        $has_access = in_array($user_tier, $gate['tiers']);
        
        if ($has_access) {
            return $content;
        }
        
        // Apply gate type
        switch ($gate['gate_type']) {
            case 'visual':
                return $this->apply_visual_gate($content, $gate, $user_tier);
                
            case 'functional':
                return $this->apply_functional_gate($content, $gate, $user_tier);
                
            case 'hide':
                return ''; // Complete removal
                
            default:
                return $content;
        }
    }
    
    /**
     * Apply visual gate (blur, overlay, etc.)
     * 
     * @param string $content
     * @param array  $gate
     * @param string $user_tier
     * @return string
     */
    private function apply_visual_gate($content, $gate, $user_tier) {
        $restriction = isset($gate['restriction']) ? $gate['restriction'] : 'blur';
        $required_tier = $this->get_minimum_tier_from_array($gate['tiers']);
        $tier_info = $this->memberpress->get_tier_info($required_tier);
        
        ob_start();
        ?>
        <div class="spiralengine-gated-content spiralengine-<?php echo esc_attr($restriction); ?>">
            <div class="sp-gated-overlay">
                <div class="sp-gate-message">
                    <span class="dashicons <?php echo esc_attr($tier_info['icon']); ?>"></span>
                    <h4><?php esc_html_e('Premium Feature', 'spiralengine'); ?></h4>
                    <p>
                        <?php printf(
                            esc_html__('This feature requires %s membership', 'spiralengine'),
                            esc_html($tier_info['name'])
                        ); ?>
                    </p>
                    <a href="<?php echo esc_url($this->memberpress->get_upgrade_url($required_tier)); ?>" 
                       class="sp-gate-upgrade-btn">
                        <?php esc_html_e('Upgrade Now', 'spiralengine'); ?>
                    </a>
                </div>
            </div>
            
            <?php if (isset($gate['sample_content']) && $gate['sample_content']): ?>
                <div class="sp-gated-sample">
                    <?php echo wp_kses_post($this->get_sample_content($gate)); ?>
                </div>
            <?php else: ?>
                <div class="sp-gated-original">
                    <?php echo $content; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
        
        return ob_get_clean();
    }
    
    /**
     * Apply functional gate (disable functionality)
     * 
     * @param string $content
     * @param array  $gate
     * @param string $user_tier
     * @return string
     */
    private function apply_functional_gate($content, $gate, $user_tier) {
        $message = isset($gate['disabled_message']) ? $gate['disabled_message'] : 
                   __('This feature is not available with your current membership', 'spiralengine');
        
        $fallback = isset($gate['fallback']) ? $gate['fallback'] : null;
        
        if ($fallback) {
            // Return fallback content
            return apply_filters('spiralengine_gate_fallback_content', '', $fallback, $user_tier);
        }
        
        // Return disabled state
        return sprintf(
            '<div class="spiralengine-feature-disabled">
                <p class="sp-disabled-message">
                    <span class="dashicons dashicons-lock"></span>
                    %s
                </p>
            </div>',
            esc_html($message)
        );
    }
    
    /**
     * Gate API access
     * 
     * @param bool   $allowed
     * @param string $endpoint
     * @param int    $user_id
     * @return bool|WP_Error
     */
    public function gate_api_access($allowed, $endpoint, $user_id) {
        // Find API gate
        $gate_path = "api.endpoints.{$endpoint}";
        $gate = $this->get_gate_by_path($gate_path);
        
        if (!$gate) {
            return $allowed;
        }
        
        $user_tier = $this->memberpress->get_user_tier($user_id);
        
        // Check tier access
        if (!in_array($user_tier, $gate['tiers'])) {
            return new WP_Error(
                'insufficient_tier',
                __('API access requires Voyager membership', 'spiralengine'),
                array('status' => 403)
            );
        }
        
        // Check rate limits
        if (isset($gate['rate_limit'][$user_tier])) {
            $rate_check = $this->check_rate_limit($user_id, $endpoint, $gate['rate_limit'][$user_tier]);
            
            if (is_wp_error($rate_check)) {
                return $rate_check;
            }
        }
        
        return true;
    }
    
    /**
     * Check storage limit
     * 
     * @param bool $allowed
     * @param int  $user_id
     * @return bool
     */
    public function check_storage_limit($allowed, $user_id) {
        $user_tier = $this->memberpress->get_user_tier($user_id);
        $limits = $this->feature_gates['data']['storage_limits'][$user_tier];
        
        if ($limits['episodes'] === 'unlimited') {
            return true;
        }
        
        // Check episode count
        $episode_count = $this->get_user_episode_count($user_id);
        
        if ($episode_count >= $limits['episodes']) {
            add_filter('spiralengine_storage_limit_message', function() use ($limits) {
                return sprintf(
                    __('You have reached your episode limit of %d. Upgrade to store more episodes.', 'spiralengine'),
                    $limits['episodes']
                );
            });
            return false;
        }
        
        return true;
    }
    
    /**
     * Check API rate limit
     * 
     * @param bool   $allowed
     * @param string $endpoint
     * @param int    $user_id
     * @return bool|WP_Error
     */
    public function check_api_rate_limit($allowed, $endpoint, $user_id) {
        $user_tier = $this->memberpress->get_user_tier($user_id);
        
        // Get endpoint gate
        $gate_path = "api.endpoints.{$endpoint}";
        $gate = $this->get_gate_by_path($gate_path);
        
        if (!$gate || !isset($gate['rate_limit'][$user_tier])) {
            return $allowed;
        }
        
        $limit = $gate['rate_limit'][$user_tier];
        $rate_check = $this->check_rate_limit($user_id, $endpoint, $limit);
        
        return $rate_check;
    }
    
    /**
     * Check rate limit
     * 
     * @param int    $user_id
     * @param string $endpoint
     * @param int    $limit
     * @return bool|WP_Error
     */
    private function check_rate_limit($user_id, $endpoint, $limit) {
        $cache_key = "spiralengine_rate_{$user_id}_{$endpoint}";
        $current_count = get_transient($cache_key);
        
        if ($current_count === false) {
            $current_count = 0;
        }
        
        if ($current_count >= $limit) {
            return new WP_Error(
                'rate_limit_exceeded',
                sprintf(
                    __('Rate limit exceeded. Maximum %d requests per hour.', 'spiralengine'),
                    $limit
                ),
                array('status' => 429)
            );
        }
        
        // Increment count
        set_transient($cache_key, $current_count + 1, HOUR_IN_SECONDS);
        
        return true;
    }
    
    /**
     * Find feature gate
     * 
     * @param string $feature
     * @return array|null
     */
    private function find_feature_gate($feature) {
        // Search through gate definitions
        foreach ($this->feature_gates as $category => $gates) {
            if (isset($gates[$feature])) {
                return $gates[$feature];
            }
            
            // Deep search
            foreach ($gates as $subcategory => $subgates) {
                if (is_array($subgates) && isset($subgates[$feature])) {
                    return $subgates[$feature];
                }
            }
        }
        
        return null;
    }
    
    /**
     * Get gate by path
     * 
     * @param string $path Dot notation path
     * @return array|null
     */
    private function get_gate_by_path($path) {
        $parts = explode('.', $path);
        $current = $this->feature_gates;
        
        foreach ($parts as $part) {
            if (!isset($current[$part])) {
                return null;
            }
            $current = $current[$part];
        }
        
        return $current;
    }
    
    /**
     * Check if user has dynamic assignment
     * 
     * @param int    $user_id
     * @param string $feature
     * @return bool
     */
    private function has_dynamic_assignment($user_id, $feature) {
        if (!isset($this->dynamic_assignments[$feature])) {
            return false;
        }
        
        $assignments = $this->dynamic_assignments[$feature];
        
        // Check user-specific assignment
        if (isset($assignments['users']) && in_array($user_id, $assignments['users'])) {
            // Check expiration
            if (isset($assignments['user_expiry'][$user_id])) {
                $expiry = $assignments['user_expiry'][$user_id];
                if ($expiry && $expiry < time()) {
                    // Remove expired assignment
                    $this->remove_user_assignment($user_id, $feature);
                    return false;
                }
            }
            return true;
        }
        
        // Check role-based assignment
        if (isset($assignments['roles'])) {
            $user = get_user_by('id', $user_id);
            foreach ($assignments['roles'] as $role) {
                if (in_array($role, $user->roles)) {
                    return true;
                }
            }
        }
        
        return false;
    }
    
    /**
     * Add dynamic assignment
     * 
     * @param string $feature
     * @param string $type 'user' or 'role'
     * @param mixed  $target User ID or role name
     * @param int    $expiry_days Days until expiration (0 = no expiry)
     */
    public function add_dynamic_assignment($feature, $type, $target, $expiry_days = 0) {
        if (!isset($this->dynamic_assignments[$feature])) {
            $this->dynamic_assignments[$feature] = array(
                'users' => array(),
                'roles' => array(),
                'user_expiry' => array()
            );
        }
        
        if ($type === 'user') {
            $user_id = intval($target);
            if (!in_array($user_id, $this->dynamic_assignments[$feature]['users'])) {
                $this->dynamic_assignments[$feature]['users'][] = $user_id;
            }
            
            // Set expiry if specified
            if ($expiry_days > 0) {
                $expiry_time = time() + ($expiry_days * DAY_IN_SECONDS);
                $this->dynamic_assignments[$feature]['user_expiry'][$user_id] = $expiry_time;
            }
        } elseif ($type === 'role') {
            if (!in_array($target, $this->dynamic_assignments[$feature]['roles'])) {
                $this->dynamic_assignments[$feature]['roles'][] = $target;
            }
        }
        
        // Save assignments
        update_option('spiralengine_dynamic_gates', $this->dynamic_assignments);
        
        // Clear caches
        $this->clear_gate_caches();
    }
    
    /**
     * Remove user assignment
     * 
     * @param int    $user_id
     * @param string $feature
     */
    private function remove_user_assignment($user_id, $feature) {
        if (!isset($this->dynamic_assignments[$feature])) {
            return;
        }
        
        $key = array_search($user_id, $this->dynamic_assignments[$feature]['users']);
        if ($key !== false) {
            unset($this->dynamic_assignments[$feature]['users'][$key]);
            $this->dynamic_assignments[$feature]['users'] = array_values($this->dynamic_assignments[$feature]['users']);
        }
        
        if (isset($this->dynamic_assignments[$feature]['user_expiry'][$user_id])) {
            unset($this->dynamic_assignments[$feature]['user_expiry'][$user_id]);
        }
        
        // Save assignments
        update_option('spiralengine_dynamic_gates', $this->dynamic_assignments);
    }
    
    /**
     * Update dynamic assignments on membership change
     * 
     * @param int    $user_id
     * @param string $new_tier
     * @param string $action
     */
    public function update_dynamic_assignments($user_id, $new_tier, $action) {
        // Remove temporary assignments on upgrade
        if ($action === 'upgraded') {
            foreach ($this->dynamic_assignments as $feature => $assignments) {
                if (isset($assignments['user_expiry'][$user_id])) {
                    $this->remove_user_assignment($user_id, $feature);
                }
            }
        }
    }
    
    /**
     * Get user episode count
     * 
     * @param int $user_id
     * @return int
     */
    private function get_user_episode_count($user_id) {
        global $wpdb;
        
        // This would connect to the episode tracking system
        $table_name = $wpdb->prefix . 'spiralengine_episodes';
        
        return $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE user_id = %d",
            $user_id
        ));
    }
    
    /**
     * Get minimum tier from array
     * 
     * @param array $tiers
     * @return string
     */
    private function get_minimum_tier_from_array($tiers) {
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
     * Clear gate caches
     */
    private function clear_gate_caches() {
        // Clear access control caches
        $this->access_control->clear_all_caches();
        
        // Clear any transients
        global $wpdb;
        $wpdb->query("DELETE FROM $wpdb->options WHERE option_name LIKE '_transient_spiralengine_gate_%'");
    }
    
    /**
     * Get sample content for gated section
     * 
     * @param array $gate
     * @return string
     */
    private function get_sample_content($gate) {
        // This would return sample/demo content for the gated feature
        return apply_filters('spiralengine_gate_sample_content', '', $gate);
    }
    
    /**
     * Add gates management page
     */
    public function add_gates_page() {
        add_submenu_page(
            'spiralengine',
            __('Feature Gates', 'spiralengine'),
            __('Feature Gates', 'spiralengine'),
            'manage_options',
            'spiralengine-feature-gates',
            array($this, 'render_gates_page')
        );
    }
    
    /**
     * Render gates management page
     */
    public function render_gates_page() {
        include SPIRALENGINE_PATH . 'admin/views/feature-gates.php';
    }
    
    /**
     * Ajax update gate
     */
    public function ajax_update_gate() {
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }
        
        // Check nonce
        if (!check_ajax_referer('spiralengine_gates_nonce', 'nonce', false)) {
            wp_send_json_error('Security check failed');
        }
        
        $feature = isset($_POST['feature']) ? sanitize_text_field($_POST['feature']) : '';
        $type = isset($_POST['type']) ? sanitize_text_field($_POST['type']) : '';
        $target = isset($_POST['target']) ? sanitize_text_field($_POST['target']) : '';
        $expiry = isset($_POST['expiry']) ? intval($_POST['expiry']) : 0;
        
        if (empty($feature) || empty($type) || empty($target)) {
            wp_send_json_error('Invalid parameters');
        }
        
        // Add assignment
        $this->add_dynamic_assignment($feature, $type, $target, $expiry);
        
        wp_send_json_success(array(
            'message' => __('Feature gate updated successfully', 'spiralengine')
        ));
    }
    
    /**
     * Ajax test gate
     */
    public function ajax_test_gate() {
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }
        
        // Check nonce
        if (!check_ajax_referer('spiralengine_gates_nonce', 'nonce', false)) {
            wp_send_json_error('Security check failed');
        }
        
        $feature = isset($_POST['feature']) ? sanitize_text_field($_POST['feature']) : '';
        $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
        
        if (empty($feature) || !$user_id) {
            wp_send_json_error('Invalid parameters');
        }
        
        // Test gate
        $can_access = apply_filters('spiralengine_can_access_feature', false, $feature, $user_id);
        
        // Get user info
        $user = get_user_by('id', $user_id);
        $user_tier = $this->memberpress->get_user_tier($user_id);
        
        wp_send_json_success(array(
            'can_access' => $can_access,
            'user_tier' => $user_tier,
            'user_email' => $user->user_email,
            'dynamic_assignment' => $this->has_dynamic_assignment($user_id, $feature)
        ));
    }
    
    /**
     * Get all gates
     * 
     * @return array
     */
    public function get_all_gates() {
        return $this->feature_gates;
    }
    
    /**
     * Get dynamic assignments
     * 
     * @return array
     */
    public function get_dynamic_assignments() {
        return $this->dynamic_assignments;
    }
}

// Initialize
add_action('init', array('SpiralEngine_Feature_Gates', 'get_instance'), 15);

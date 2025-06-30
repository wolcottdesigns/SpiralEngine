<?php
// includes/widgets/class-spiralengine-widget-restrictions.php

/**
 * SPIRAL Engine Widget Restrictions
 *
 * Implements visual restriction system with blur, lock, and overlay effects
 * Handles membership tier checking and upgrade prompts
 *
 * @package SPIRAL_Engine
 * @subpackage Widgets
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class SpiralEngine_Widget_Restrictions {
    
    /**
     * Singleton instance
     */
    private static $instance = null;
    
    /**
     * Restriction types
     */
    const RESTRICTION_BLUR = 'blur';
    const RESTRICTION_LOCK = 'lock';
    const RESTRICTION_OVERLAY = 'overlay';
    
    /**
     * Membership tiers hierarchy
     */
    private $membership_hierarchy = array(
        'all' => 0,
        'explorer' => 1,
        'navigator' => 2,
        'voyager' => 3
    );
    
    /**
     * Default restriction messages
     */
    private $default_messages = array();
    
    /**
     * Episode-specific messages
     */
    private $episode_messages = array();
    
    /**
     * MemberPress integration
     */
    private $memberpress_enabled = false;
    private $membership_mapping = array();
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->init();
        $this->setup_hooks();
    }
    
    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Initialize
     */
    private function init() {
        // Setup default messages
        $this->setup_default_messages();
        
        // Setup episode-specific messages
        $this->setup_episode_messages();
        
        // Check for MemberPress
        $this->check_memberpress();
        
        // Load membership mapping
        $this->load_membership_mapping();
    }
    
    /**
     * Setup hooks
     */
    private function setup_hooks() {
        // Enqueue styles
        add_action('wp_enqueue_scripts', array($this, 'enqueue_styles'));
        
        // AJAX handlers for restriction checks
        add_action('wp_ajax_spiralengine_check_access', array($this, 'ajax_check_access'));
        add_action('wp_ajax_nopriv_spiralengine_check_access', array($this, 'ajax_check_access_nopriv'));
        
        // Filter for custom restriction messages
        add_filter('spiralengine_restriction_message', array($this, 'filter_restriction_message'), 10, 3);
        
        // Shortcode for restriction testing
        add_shortcode('spiralengine_test_restriction', array($this, 'test_restriction_shortcode'));
    }
    
    /**
     * Setup default restriction messages
     */
    private function setup_default_messages() {
        $this->default_messages = array(
            'login_required' => array(
                'title' => __('Login Required', 'spiralengine'),
                'message' => __('Please log in to access this content', 'spiralengine'),
                'cta' => __('Log In', 'spiralengine'),
                'cta_url' => wp_login_url(get_permalink())
            ),
            'membership_required' => array(
                'explorer' => array(
                    'title' => __('Explorer Content', 'spiralengine'),
                    'message' => __('Unlock deeper insights with Explorer membership', 'spiralengine'),
                    'features' => array(
                        __('Pattern detection and analysis', 'spiralengine'),
                        __('Advanced correlations', 'spiralengine'),
                        __('Historical data trends', 'spiralengine'),
                        __('Personalized insights', 'spiralengine')
                    ),
                    'cta' => __('Upgrade to Explorer', 'spiralengine')
                ),
                'navigator' => array(
                    'title' => __('Navigator Content', 'spiralengine'),
                    'message' => __('Access AI-powered predictions with Navigator membership', 'spiralengine'),
                    'features' => array(
                        __('AI-powered forecasting', 'spiralengine'),
                        __('Unified episode analysis', 'spiralengine'),
                        __('Advanced recommendations', 'spiralengine'),
                        __('API access', 'spiralengine')
                    ),
                    'cta' => __('Upgrade to Navigator', 'spiralengine')
                ),
                'voyager' => array(
                    'title' => __('Voyager Exclusive', 'spiralengine'),
                    'message' => __('Experience the full power of SPIRAL Engine', 'spiralengine'),
                    'features' => array(
                        __('All platform features', 'spiralengine'),
                        __('Priority support', 'spiralengine'),
                        __('Beta features access', 'spiralengine'),
                        __('Custom integrations', 'spiralengine')
                    ),
                    'cta' => __('Upgrade to Voyager', 'spiralengine')
                )
            ),
            'section_restricted' => array(
                'title' => __('Premium Section', 'spiralengine'),
                'message' => __('This section requires a higher membership level', 'spiralengine'),
                'cta' => __('Upgrade Membership', 'spiralengine')
            )
        );
    }
    
    /**
     * Setup episode-specific messages
     */
    private function setup_episode_messages() {
        $this->episode_messages = array(
            'pattern_detection' => array(
                'title' => __('Unlock Your {episode_type} Patterns', 'spiralengine'),
                'message' => __('Discover when and why your {episode_type} episodes occur', 'spiralengine'),
                'features' => array(
                    __('Time-based pattern analysis', 'spiralengine'),
                    __('Trigger identification', 'spiralengine'),
                    __('Severity trends', 'spiralengine'),
                    __('Predictive insights', 'spiralengine')
                ),
                'cta' => __('Upgrade to Explorer', 'spiralengine')
            ),
            'correlation_insights' => array(
                'title' => __('See How Episodes Connect', 'spiralengine'),
                'message' => __('Understand relationships between different episode types', 'spiralengine'),
                'features' => array(
                    __('Cross-episode correlations', 'spiralengine'),
                    __('Cascade effect detection', 'spiralengine'),
                    __('Combined triggers', 'spiralengine'),
                    __('Holistic view', 'spiralengine')
                ),
                'cta' => __('Unlock with Explorer', 'spiralengine')
            ),
            'unified_forecast' => array(
                'title' => __('AI-Powered Predictions', 'spiralengine'),
                'message' => __('Get personalized forecasts across all episode types', 'spiralengine'),
                'features' => array(
                    __('7-day risk assessment', 'spiralengine'),
                    __('Combined episode forecast', 'spiralengine'),
                    __('Preventive recommendations', 'spiralengine'),
                    __('Early warning system', 'spiralengine')
                ),
                'cta' => __('Activate with Navigator', 'spiralengine')
            )
        );
    }
    
    /**
     * Check for MemberPress
     */
    private function check_memberpress() {
        $this->memberpress_enabled = class_exists('MeprUser') && function_exists('mepr_get_current_users_product_ids');
    }
    
    /**
     * Load membership mapping
     */
    private function load_membership_mapping() {
        // Get saved mapping or use defaults
        $saved_mapping = get_option('spiralengine_membership_mapping', array());
        
        $this->membership_mapping = wp_parse_args($saved_mapping, array(
            'explorer' => array(),  // Array of MemberPress membership IDs
            'navigator' => array(),
            'voyager' => array()
        ));
    }
    
    /**
     * Check user access to widget
     */
    public function check_widget_access($widget_config, $user_id = null) {
        if ($user_id === null) {
            $user_id = get_current_user_id();
        }
        
        // Check if login required
        if (!$user_id && $widget_config['membership_required'] !== 'all') {
            return array(
                'access' => false,
                'reason' => 'login_required',
                'restriction_type' => self::RESTRICTION_OVERLAY,
                'message_data' => $this->default_messages['login_required']
            );
        }
        
        // Check membership requirement
        $required_membership = $widget_config['membership_required'] ?? 'all';
        if ($required_membership === 'all') {
            return array('access' => true);
        }
        
        $user_membership = $this->get_user_membership($user_id);
        
        if (!$this->has_required_membership($user_membership, $required_membership)) {
            return array(
                'access' => false,
                'reason' => 'membership_required',
                'required' => $required_membership,
                'restriction_type' => $widget_config['restriction_type'] ?? self::RESTRICTION_BLUR,
                'show_teaser' => $widget_config['show_teaser'] ?? true,
                'message_data' => $this->get_membership_message($required_membership)
            );
        }
        
        return array('access' => true);
    }
    
    /**
     * Check section access
     */
    public function check_section_access($section_config, $user_id = null) {
        if ($user_id === null) {
            $user_id = get_current_user_id();
        }
        
        // No restriction on section
        if (empty($section_config['membership'])) {
            return array('access' => true);
        }
        
        $user_membership = $this->get_user_membership($user_id);
        $required_membership = $section_config['membership'];
        
        if (!$this->has_required_membership($user_membership, $required_membership)) {
            return array(
                'access' => false,
                'reason' => 'section_restricted',
                'required' => $required_membership,
                'restriction_type' => $section_config['restriction_type'] ?? self::RESTRICTION_BLUR,
                'message' => $section_config['message'] ?? $this->default_messages['section_restricted']['message']
            );
        }
        
        return array('access' => true);
    }
    
    /**
     * Check episode widget access
     */
    public function check_episode_access($episode_type, $feature, $user_id = null) {
        if ($user_id === null) {
            $user_id = get_current_user_id();
        }
        
        $feature_requirements = array(
            'pattern_detection' => 'explorer',
            'correlation_insights' => 'explorer',
            'unified_forecast' => 'navigator',
            'api_access' => 'voyager'
        );
        
        if (!isset($feature_requirements[$feature])) {
            return array('access' => true);
        }
        
        $required_membership = $feature_requirements[$feature];
        $user_membership = $this->get_user_membership($user_id);
        
        if (!$this->has_required_membership($user_membership, $required_membership)) {
            $message_data = $this->episode_messages[$feature] ?? $this->default_messages['membership_required'][$required_membership];
            
            // Replace episode type placeholder
            if (is_array($message_data)) {
                $message_data['title'] = str_replace('{episode_type}', ucfirst($episode_type), $message_data['title']);
                $message_data['message'] = str_replace('{episode_type}', $episode_type, $message_data['message']);
            }
            
            return array(
                'access' => false,
                'reason' => 'feature_restricted',
                'required' => $required_membership,
                'feature' => $feature,
                'message_data' => $message_data
            );
        }
        
        // Check if user has enough data for the feature
        if ($feature === 'pattern_detection' || $feature === 'unified_forecast') {
            $episode_count = $this->get_user_episode_count($user_id, $episode_type);
            $min_required = ($feature === 'pattern_detection') ? 5 : 10;
            
            if ($episode_count < $min_required) {
                return array(
                    'access' => false,
                    'reason' => 'insufficient_data',
                    'message' => sprintf(
                        __('You need at least %d %s episodes logged to access %s', 'spiralengine'),
                        $min_required,
                        $episode_type,
                        str_replace('_', ' ', $feature)
                    )
                );
            }
        }
        
        return array('access' => true);
    }
    
    /**
     * Get user membership level
     */
    public function get_user_membership($user_id = null) {
        if ($user_id === null) {
            $user_id = get_current_user_id();
        }
        
        if (!$user_id) {
            return 'none';
        }
        
        // Check MemberPress if available
        if ($this->memberpress_enabled) {
            return $this->get_memberpress_membership($user_id);
        }
        
        // Check user meta for custom membership
        $membership = get_user_meta($user_id, 'spiralengine_membership', true);
        if ($membership && isset($this->membership_hierarchy[$membership])) {
            return $membership;
        }
        
        // Default to 'all' for logged in users
        return 'all';
    }
    
    /**
     * Get MemberPress membership level
     */
    private function get_memberpress_membership($user_id) {
        if (!function_exists('mepr_get_user_active_memberships')) {
            return 'all';
        }
        
        $active_memberships = mepr_get_user_active_memberships($user_id);
        
        // Check from highest to lowest tier
        foreach (array('voyager', 'navigator', 'explorer') as $tier) {
            if (empty($this->membership_mapping[$tier])) {
                continue;
            }
            
            foreach ($active_memberships as $membership) {
                if (in_array($membership->membership_id, $this->membership_mapping[$tier])) {
                    return $tier;
                }
            }
        }
        
        return 'all';
    }
    
    /**
     * Check if user has required membership
     */
    private function has_required_membership($user_membership, $required_membership) {
        if ($required_membership === 'all') {
            return true;
        }
        
        $user_level = $this->membership_hierarchy[$user_membership] ?? 0;
        $required_level = $this->membership_hierarchy[$required_membership] ?? 999;
        
        return $user_level >= $required_level;
    }
    
    /**
     * Get membership message
     */
    private function get_membership_message($required_membership) {
        $message_data = $this->default_messages['membership_required'][$required_membership] ?? array();
        
        // Add upgrade URL
        $message_data['cta_url'] = $this->get_upgrade_url($required_membership);
        
        return $message_data;
    }
    
    /**
     * Get upgrade URL
     */
    public function get_upgrade_url($target_membership) {
        // Check for custom upgrade URLs
        $custom_url = get_option('spiralengine_upgrade_url_' . $target_membership);
        if ($custom_url) {
            return $custom_url;
        }
        
        // Default upgrade URL
        return home_url('/membership-upgrade/?level=' . $target_membership);
    }
    
    /**
     * Render restriction overlay
     */
    public function render_restriction($access_info, $widget_id = '') {
        $restriction_type = $access_info['restriction_type'] ?? self::RESTRICTION_BLUR;
        $message_data = $access_info['message_data'] ?? array();
        
        ?>
        <div class="sp-restriction-container sp-restriction-<?php echo esc_attr($restriction_type); ?>" 
             data-widget-id="<?php echo esc_attr($widget_id); ?>">
            
            <?php if ($restriction_type === self::RESTRICTION_BLUR && !empty($access_info['show_teaser'])): ?>
                <div class="sp-restriction-teaser">
                    <!-- Teaser content rendered by widget -->
                </div>
            <?php endif; ?>
            
            <div class="sp-restriction-overlay">
                <div class="sp-restriction-content">
                    <?php $this->render_restriction_icon($restriction_type); ?>
                    
                    <?php if (!empty($message_data['title'])): ?>
                        <h3 class="sp-restriction-title"><?php echo esc_html($message_data['title']); ?></h3>
                    <?php endif; ?>
                    
                    <?php if (!empty($message_data['message'])): ?>
                        <p class="sp-restriction-message"><?php echo esc_html($message_data['message']); ?></p>
                    <?php endif; ?>
                    
                    <?php if (!empty($message_data['features'])): ?>
                        <ul class="sp-restriction-features">
                            <?php foreach ($message_data['features'] as $feature): ?>
                                <li><?php echo esc_html($feature); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                    
                    <?php if (!empty($message_data['cta']) && !empty($message_data['cta_url'])): ?>
                        <a href="<?php echo esc_url($message_data['cta_url']); ?>" 
                           class="sp-restriction-cta sp-button sp-button-primary">
                            <?php echo esc_html($message_data['cta']); ?>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render restriction icon
     */
    private function render_restriction_icon($restriction_type) {
        $icons = array(
            self::RESTRICTION_BLUR => 'dashicons-visibility',
            self::RESTRICTION_LOCK => 'dashicons-lock',
            self::RESTRICTION_OVERLAY => 'dashicons-shield'
        );
        
        $icon = $icons[$restriction_type] ?? 'dashicons-lock';
        ?>
        <span class="sp-restriction-icon dashicons <?php echo esc_attr($icon); ?>"></span>
        <?php
    }
    
    /**
     * Add restriction CSS classes
     */
    public function get_restriction_classes($access_info) {
        $classes = array('sp-widget-restricted');
        
        if (isset($access_info['restriction_type'])) {
            $classes[] = 'sp-restriction-' . $access_info['restriction_type'];
        }
        
        if (!empty($access_info['show_teaser'])) {
            $classes[] = 'sp-has-teaser';
        }
        
        if (isset($access_info['reason'])) {
            $classes[] = 'sp-restriction-reason-' . $access_info['reason'];
        }
        
        return implode(' ', $classes);
    }
    
    /**
     * Generate inline restriction styles
     */
    public function get_inline_styles($widget_id, $restriction_config = array()) {
        $styles = array();
        
        // Blur intensity
        if (isset($restriction_config['blur_intensity'])) {
            $styles[] = sprintf(
                '#%s .sp-restriction-teaser { filter: blur(%dpx); }',
                $widget_id,
                intval($restriction_config['blur_intensity'])
            );
        }
        
        // Overlay opacity
        if (isset($restriction_config['overlay_opacity'])) {
            $styles[] = sprintf(
                '#%s .sp-restriction-overlay { opacity: %s; }',
                $widget_id,
                floatval($restriction_config['overlay_opacity'])
            );
        }
        
        // Custom colors
        if (isset($restriction_config['overlay_color'])) {
            $styles[] = sprintf(
                '#%s .sp-restriction-overlay { background-color: %s; }',
                $widget_id,
                esc_attr($restriction_config['overlay_color'])
            );
        }
        
        return !empty($styles) ? '<style>' . implode("\n", $styles) . '</style>' : '';
    }
    
    /**
     * AJAX check access
     */
    public function ajax_check_access() {
        check_ajax_referer('spiralengine_nonce', 'nonce');
        
        $widget_id = sanitize_text_field($_POST['widget_id']);
        $section_id = sanitize_text_field($_POST['section_id'] ?? '');
        
        // Get widget config
        $registry = SpiralEngine_Widget_Registry::get_instance();
        $widget = $registry->get_widget_by_id($widget_id);
        
        if (!$widget) {
            wp_send_json_error('Widget not found');
        }
        
        // Check access
        if ($section_id) {
            $section_config = $widget['config']['section_restrictions'][$section_id] ?? array();
            $access = $this->check_section_access($section_config);
        } else {
            $access = $this->check_widget_access($widget['config']);
        }
        
        wp_send_json_success($access);
    }
    
    /**
     * AJAX check access for non-logged in users
     */
    public function ajax_check_access_nopriv() {
        check_ajax_referer('spiralengine_nonce', 'nonce');
        
        // Non-logged in users only have access to 'all' content
        wp_send_json_success(array(
            'access' => false,
            'reason' => 'login_required',
            'message_data' => $this->default_messages['login_required']
        ));
    }
    
    /**
     * Filter restriction message
     */
    public function filter_restriction_message($message, $restriction_type, $context) {
        // Allow customization of restriction messages
        return $message;
    }
    
    /**
     * Test restriction shortcode
     */
    public function test_restriction_shortcode($atts) {
        $atts = shortcode_atts(array(
            'type' => 'blur',
            'membership' => 'explorer',
            'show_teaser' => 'true'
        ), $atts);
        
        ob_start();
        
        $access_info = array(
            'access' => false,
            'restriction_type' => $atts['type'],
            'show_teaser' => $atts['show_teaser'] === 'true',
            'message_data' => $this->get_membership_message($atts['membership'])
        );
        
        ?>
        <div class="sp-widget sp-test-restriction">
            <h3>Restriction Test: <?php echo ucfirst($atts['type']); ?></h3>
            <?php $this->render_restriction($access_info, 'test-widget'); ?>
        </div>
        <?php
        
        return ob_get_clean();
    }
    
    /**
     * Get user episode count
     */
    private function get_user_episode_count($user_id, $episode_type) {
        global $wpdb;
        
        return $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}spiralengine_episodes 
             WHERE user_id = %d AND episode_type = %s",
            $user_id,
            $episode_type
        ));
    }
    
    /**
     * Enqueue styles
     */
    public function enqueue_styles() {
        wp_enqueue_style(
            'spiralengine-widget-restrictions',
            SPIRALENGINE_PLUGIN_URL . 'assets/css/spiralengine-widgets.css',
            array(),
            SPIRALENGINE_VERSION
        );
    }
    
    /**
     * Get all restriction types
     */
    public static function get_restriction_types() {
        return array(
            self::RESTRICTION_BLUR => __('Blur', 'spiralengine'),
            self::RESTRICTION_LOCK => __('Lock', 'spiralengine'),
            self::RESTRICTION_OVERLAY => __('Overlay', 'spiralengine')
        );
    }
    
    /**
     * Admin settings for membership mapping
     */
    public function render_membership_mapping_settings() {
        if (!$this->memberpress_enabled) {
            ?>
            <p><?php _e('MemberPress is not active. Manual membership management will be used.', 'spiralengine'); ?></p>
            <?php
            return;
        }
        
        // Get all MemberPress memberships
        $memberships = get_posts(array(
            'post_type' => 'memberpressproduct',
            'posts_per_page' => -1,
            'post_status' => 'publish'
        ));
        
        ?>
        <h3><?php _e('MemberPress Membership Mapping', 'spiralengine'); ?></h3>
        <p><?php _e('Map your MemberPress memberships to SPIRAL Engine tiers:', 'spiralengine'); ?></p>
        
        <?php foreach (array('explorer', 'navigator', 'voyager') as $tier): ?>
            <h4><?php echo ucfirst($tier); ?> Tier</h4>
            <select name="spiralengine_membership_mapping[<?php echo $tier; ?>][]" multiple size="5">
                <?php foreach ($memberships as $membership): ?>
                    <option value="<?php echo $membership->ID; ?>" 
                            <?php selected(in_array($membership->ID, $this->membership_mapping[$tier])); ?>>
                        <?php echo esc_html($membership->post_title); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <p class="description">
                <?php _e('Hold Ctrl/Cmd to select multiple memberships', 'spiralengine'); ?>
            </p>
        <?php endforeach; ?>
        <?php
    }
    
    /**
     * Save membership mapping settings
     */
    public function save_membership_mapping($mapping) {
        update_option('spiralengine_membership_mapping', $mapping);
        $this->membership_mapping = $mapping;
    }
}

// Initialize the restrictions system
add_action('init', function() {
    SpiralEngine_Widget_Restrictions::get_instance();
});

<?php
// includes/widgets/class-spiralengine-widget-base.php

/**
 * SPIRAL Engine Widget Base Class
 *
 * Abstract base class for all widgets in the Widget Studio
 * Implements dual mode support, membership checking, and episode framework
 *
 * @package SPIRAL_Engine
 * @subpackage Widgets
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

abstract class SpiralEngine_Widget_Base {
    
    /**
     * Widget properties
     */
    protected $widget_uuid;
    protected $widget_id;
    protected $widget_name;
    protected $widget_description;
    protected $widget_type;
    protected $widget_category;
    protected $widget_version;
    protected $widget_author;
    protected $widget_icon;
    protected $widget_status = 'active';
    
    /**
     * Display modes
     */
    protected $display_mode = 'preview'; // 'preview' or 'full'
    protected $supports_preview = true;
    protected $supports_full = true;
    
    /**
     * Membership properties
     */
    protected $membership_required = 'all'; // all, explorer, navigator, voyager
    protected $section_restrictions = array();
    
    /**
     * Episode framework properties
     */
    protected $is_episode_widget = false;
    protected $episode_type = null;
    protected $episode_config = array();
    protected $correlation_enabled = false;
    protected $forecast_enabled = false;
    
    /**
     * Visual restriction properties
     */
    protected $restriction_type = 'blur'; // blur, lock, overlay
    protected $restriction_message = '';
    protected $show_teaser = true;
    
    /**
     * Cache properties
     */
    protected $cache_enabled = true;
    protected $cache_duration = 3600; // 1 hour
    
    /**
     * Widget sections
     */
    protected $sections = array();
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->widget_uuid = $this->generate_uuid();
        $this->init();
        $this->setup_hooks();
        
        // Register with Widget Studio if not already registered
        if (!$this->is_registered()) {
            $this->register();
        }
    }
    
    /**
     * Initialize widget - must be implemented by child classes
     */
    abstract protected function init();
    
    /**
     * Render widget content - must be implemented by child classes
     */
    abstract public function render();
    
    /**
     * Get widget configuration
     */
    abstract public function get_config();
    
    /**
     * Setup WordPress hooks
     */
    protected function setup_hooks() {
        // Widget rendering hooks
        add_action('spiralengine_render_widget_' . $this->widget_id, array($this, 'render_widget'), 10, 2);
        
        // AJAX handlers
        add_action('wp_ajax_spiralengine_widget_' . $this->widget_id, array($this, 'handle_ajax'));
        add_action('wp_ajax_nopriv_spiralengine_widget_' . $this->widget_id, array($this, 'handle_ajax_nopriv'));
        
        // Episode framework hooks if applicable
        if ($this->is_episode_widget) {
            add_action('spiralengine_episode_logged', array($this, 'handle_episode_logged'), 10, 3);
            add_filter('spiralengine_correlation_data', array($this, 'provide_correlation_data'), 10, 2);
            add_filter('spiralengine_forecast_data', array($this, 'contribute_to_forecast'), 10, 2);
        }
    }
    
    /**
     * Generate unique widget UUID
     */
    protected function generate_uuid() {
        return wp_generate_uuid4();
    }
    
    /**
     * Check if widget is registered
     */
    protected function is_registered() {
        $registry = SpiralEngine_Widget_Registry::get_instance();
        return $registry->is_registered($this->widget_uuid);
    }
    
    /**
     * Register widget with the studio
     */
    protected function register() {
        $registry = SpiralEngine_Widget_Registry::get_instance();
        
        $config = array(
            'widget_uuid' => $this->widget_uuid,
            'widget_id' => $this->widget_id,
            'widget_name' => $this->widget_name,
            'widget_description' => $this->widget_description,
            'widget_type' => $this->widget_type,
            'widget_category' => $this->widget_category,
            'widget_version' => $this->widget_version,
            'widget_author' => $this->widget_author,
            'widget_icon' => $this->widget_icon,
            'widget_class' => get_class($this),
            'supports_preview' => $this->supports_preview,
            'supports_full' => $this->supports_full,
            'membership_required' => $this->membership_required,
            'section_restrictions' => $this->section_restrictions,
            'is_episode_widget' => $this->is_episode_widget,
            'episode_type' => $this->episode_type,
            'episode_config' => $this->episode_config,
            'sections' => $this->get_sections()
        );
        
        // Register with appropriate manager
        if ($this->is_episode_widget) {
            $episode_manager = new Episode_Widget_Manager();
            $episode_manager->register_episode_widget($config);
        } else {
            $registry->register_widget($config);
        }
    }
    
    /**
     * Main render method with access control
     */
    public function render_widget($args = array(), $mode = 'preview') {
        // Set display mode
        $this->display_mode = $mode;
        
        // Check access permissions
        $access_check = $this->check_access();
        if (!$access_check['access']) {
            $this->render_restricted($access_check);
            return;
        }
        
        // Get cached content if available
        if ($this->cache_enabled) {
            $cached = $this->get_cached_content();
            if ($cached !== false) {
                echo $cached;
                return;
            }
        }
        
        // Start output buffering
        ob_start();
        
        // Render widget wrapper
        $this->render_wrapper_start();
        
        // Render based on mode
        if ($mode === 'preview' && $this->supports_preview) {
            $this->render_preview();
        } elseif ($mode === 'full' && $this->supports_full) {
            $this->render_full();
        } else {
            $this->render();
        }
        
        // Close wrapper
        $this->render_wrapper_end();
        
        // Get content and cache if enabled
        $content = ob_get_clean();
        if ($this->cache_enabled) {
            $this->cache_content($content);
        }
        
        echo $content;
    }
    
    /**
     * Render preview mode
     */
    protected function render_preview() {
        ?>
        <div class="sp-widget-preview">
            <div class="sp-widget-header">
                <span class="sp-widget-icon <?php echo esc_attr($this->widget_icon); ?>"></span>
                <h3 class="sp-widget-title"><?php echo esc_html($this->widget_name); ?></h3>
            </div>
            <div class="sp-widget-preview-content">
                <?php $this->render_preview_content(); ?>
            </div>
            <?php if ($this->is_episode_widget): ?>
                <?php $this->render_episode_stats(); ?>
                <?php $this->render_correlation_indicator(); ?>
            <?php endif; ?>
            <div class="sp-widget-actions">
                <a href="<?php echo $this->get_full_widget_url(); ?>" class="sp-widget-view-full">
                    View Full Widget
                </a>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render preview content - override in child classes
     */
    protected function render_preview_content() {
        ?>
        <p><?php echo esc_html($this->widget_description); ?></p>
        <?php
    }
    
    /**
     * Render full mode
     */
    protected function render_full() {
        ?>
        <div class="sp-widget-full">
            <div class="sp-widget-header">
                <span class="sp-widget-icon <?php echo esc_attr($this->widget_icon); ?>"></span>
                <h2 class="sp-widget-title"><?php echo esc_html($this->widget_name); ?></h2>
            </div>
            <div class="sp-widget-full-content">
                <?php $this->render_sections(); ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render widget sections with restrictions
     */
    protected function render_sections() {
        foreach ($this->sections as $section_id => $section) {
            $section_access = $this->check_section_access($section_id);
            
            if (!$section_access['access']) {
                $this->render_section_restricted($section_id, $section, $section_access);
                continue;
            }
            
            $this->render_section($section_id, $section);
        }
    }
    
    /**
     * Render individual section
     */
    protected function render_section($section_id, $section) {
        ?>
        <div class="sp-widget-section sp-section-<?php echo esc_attr($section_id); ?>" 
             data-section-id="<?php echo esc_attr($section_id); ?>">
            <?php if (!empty($section['title'])): ?>
                <h3 class="sp-section-title"><?php echo esc_html($section['title']); ?></h3>
            <?php endif; ?>
            <div class="sp-section-content">
                <?php 
                if (method_exists($this, 'render_section_' . $section_id)) {
                    $this->{'render_section_' . $section_id}();
                } else {
                    echo '<p>Section content not implemented.</p>';
                }
                ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Check widget access
     */
    protected function check_access() {
        if (!is_user_logged_in() && $this->membership_required !== 'all') {
            return array(
                'access' => false,
                'reason' => 'login_required',
                'message' => 'Please log in to access this widget'
            );
        }
        
        if ($this->membership_required === 'all') {
            return array('access' => true);
        }
        
        $user_membership = $this->get_user_membership();
        $allowed_memberships = $this->get_allowed_memberships();
        
        if (!in_array($user_membership, $allowed_memberships)) {
            return array(
                'access' => false,
                'reason' => 'membership_required',
                'required' => $this->membership_required,
                'message' => $this->get_membership_message()
            );
        }
        
        return array('access' => true);
    }
    
    /**
     * Check section access
     */
    protected function check_section_access($section_id) {
        if (!isset($this->section_restrictions[$section_id])) {
            return array('access' => true);
        }
        
        $restriction = $this->section_restrictions[$section_id];
        $user_membership = $this->get_user_membership();
        $allowed = $this->get_allowed_memberships($restriction['membership']);
        
        if (!in_array($user_membership, $allowed)) {
            return array(
                'access' => false,
                'reason' => 'section_restricted',
                'required' => $restriction['membership'],
                'restriction_type' => $restriction['type'] ?? 'blur',
                'message' => $restriction['message'] ?? 'Upgrade to access this section'
            );
        }
        
        return array('access' => true);
    }
    
    /**
     * Render restricted widget
     */
    protected function render_restricted($access_info) {
        $classes = array(
            'sp-widget-restricted',
            'sp-restriction-' . $this->restriction_type
        );
        ?>
        <div class="<?php echo esc_attr(implode(' ', $classes)); ?>">
            <?php if ($this->show_teaser && $this->restriction_type === 'blur'): ?>
                <div class="sp-widget-teaser">
                    <?php $this->render_preview_content(); ?>
                </div>
            <?php endif; ?>
            <div class="sp-restriction-overlay">
                <div class="sp-restriction-content">
                    <span class="sp-lock-icon dashicons dashicons-lock"></span>
                    <h3><?php echo esc_html($this->widget_name); ?></h3>
                    <p><?php echo esc_html($access_info['message']); ?></p>
                    <?php $this->render_upgrade_button($access_info['required']); ?>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render section restriction
     */
    protected function render_section_restricted($section_id, $section, $access_info) {
        $classes = array(
            'sp-widget-section',
            'sp-section-restricted',
            'sp-restriction-' . $access_info['restriction_type']
        );
        ?>
        <div class="<?php echo esc_attr(implode(' ', $classes)); ?>" 
             data-section-id="<?php echo esc_attr($section_id); ?>">
            <?php if ($access_info['restriction_type'] === 'blur' && $this->show_teaser): ?>
                <div class="sp-section-teaser">
                    <h3 class="sp-section-title"><?php echo esc_html($section['title']); ?></h3>
                    <div class="sp-teaser-content">
                        <?php $this->render_section_teaser($section_id); ?>
                    </div>
                </div>
            <?php endif; ?>
            <div class="sp-restriction-overlay">
                <div class="sp-restriction-content">
                    <span class="sp-lock-icon dashicons dashicons-lock"></span>
                    <p><?php echo esc_html($access_info['message']); ?></p>
                    <?php $this->render_upgrade_button($access_info['required']); ?>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render section teaser content
     */
    protected function render_section_teaser($section_id) {
        echo '<p>Premium content available with upgrade.</p>';
    }
    
    /**
     * Episode widget specific methods
     */
    
    /**
     * Render episode statistics
     */
    protected function render_episode_stats() {
        if (!$this->is_episode_widget) return;
        
        $stats = $this->get_episode_stats();
        ?>
        <div class="sp-episode-stats">
            <span class="sp-stat-item">
                <span class="sp-stat-value"><?php echo esc_html($stats['count_week']); ?></span>
                <span class="sp-stat-label">This Week</span>
            </span>
            <span class="sp-stat-item">
                <span class="sp-stat-value"><?php echo esc_html(number_format($stats['avg_severity'], 1)); ?></span>
                <span class="sp-stat-label">Avg Severity</span>
            </span>
        </div>
        <?php
    }
    
    /**
     * Render correlation indicator
     */
    protected function render_correlation_indicator() {
        if (!$this->correlation_enabled) return;
        
        $correlations = $this->get_active_correlations();
        if (empty($correlations)) return;
        ?>
        <div class="sp-correlation-badge" title="Active correlations with other episode types">
            <span class="dashicons dashicons-networking"></span>
            <span class="sp-correlation-count"><?php echo count($correlations); ?></span>
        </div>
        <?php
    }
    
    /**
     * Get episode statistics
     */
    protected function get_episode_stats() {
        // Override in episode widgets
        return array(
            'count_week' => 0,
            'avg_severity' => 0.0
        );
    }
    
    /**
     * Get active correlations
     */
    protected function get_active_correlations() {
        // Override in episode widgets
        return array();
    }
    
    /**
     * Episode framework interface methods
     */
    
    /**
     * Handle episode logged event
     */
    public function handle_episode_logged($episode_id, $episode_type, $user_id) {
        // Override in episode widgets
    }
    
    /**
     * Provide correlation data
     */
    public function provide_correlation_data($data, $args) {
        // Override in episode widgets
        return $data;
    }
    
    /**
     * Contribute to unified forecast
     */
    public function contribute_to_forecast($forecast_data, $args) {
        // Override in episode widgets
        return $forecast_data;
    }
    
    /**
     * Helper methods
     */
    
    /**
     * Get user membership level
     */
    protected function get_user_membership() {
        if (!is_user_logged_in()) {
            return 'none';
        }
        
        $user_id = get_current_user_id();
        
        // Check MemberPress memberships
        if (function_exists('mepr_get_user_active_memberships')) {
            $memberships = mepr_get_user_active_memberships($user_id);
            
            // Map MemberPress memberships to our tiers
            // This would need to be configured based on actual MemberPress setup
            $membership_map = array(
                'voyager_membership_id' => 'voyager',
                'navigator_membership_id' => 'navigator',
                'explorer_membership_id' => 'explorer'
            );
            
            foreach ($memberships as $membership) {
                if (isset($membership_map[$membership->membership_id])) {
                    return $membership_map[$membership->membership_id];
                }
            }
        }
        
        return 'all';
    }
    
    /**
     * Get allowed memberships for a requirement
     */
    protected function get_allowed_memberships($required = null) {
        if ($required === null) {
            $required = $this->membership_required;
        }
        
        $hierarchy = array(
            'all' => array('all', 'explorer', 'navigator', 'voyager'),
            'explorer' => array('explorer', 'navigator', 'voyager'),
            'navigator' => array('navigator', 'voyager'),
            'voyager' => array('voyager')
        );
        
        return $hierarchy[$required] ?? array();
    }
    
    /**
     * Get membership upgrade message
     */
    protected function get_membership_message() {
        $messages = array(
            'explorer' => 'Unlock this widget with Explorer membership',
            'navigator' => 'This widget requires Navigator membership',
            'voyager' => 'Exclusive content for Voyager members'
        );
        
        return $messages[$this->membership_required] ?? 'Membership required';
    }
    
    /**
     * Render upgrade button
     */
    protected function render_upgrade_button($required_membership) {
        $upgrade_url = home_url('/membership-upgrade/?level=' . $required_membership);
        ?>
        <a href="<?php echo esc_url($upgrade_url); ?>" class="sp-upgrade-button">
            Upgrade to <?php echo ucfirst($required_membership); ?>
        </a>
        <?php
    }
    
    /**
     * Get full widget URL
     */
    protected function get_full_widget_url() {
        return home_url('/widgets/' . $this->widget_id);
    }
    
    /**
     * Wrapper methods
     */
    
    /**
     * Render widget wrapper start
     */
    protected function render_wrapper_start() {
        $classes = array(
            'sp-widget',
            'sp-widget-' . $this->widget_id,
            'sp-widget-type-' . $this->widget_type,
            'sp-widget-mode-' . $this->display_mode
        );
        
        if ($this->is_episode_widget) {
            $classes[] = 'sp-episode-widget';
            $classes[] = 'sp-episode-' . $this->episode_type;
        }
        ?>
        <div class="<?php echo esc_attr(implode(' ', $classes)); ?>" 
             data-widget-id="<?php echo esc_attr($this->widget_id); ?>"
             data-widget-uuid="<?php echo esc_attr($this->widget_uuid); ?>">
        <?php
    }
    
    /**
     * Render widget wrapper end
     */
    protected function render_wrapper_end() {
        ?>
        </div>
        <?php
    }
    
    /**
     * Cache methods
     */
    
    /**
     * Get cache key
     */
    protected function get_cache_key() {
        $user_id = get_current_user_id();
        return 'spiralengine_widget_' . $this->widget_id . '_' . $this->display_mode . '_' . $user_id;
    }
    
    /**
     * Get cached content
     */
    protected function get_cached_content() {
        return get_transient($this->get_cache_key());
    }
    
    /**
     * Cache content
     */
    protected function cache_content($content) {
        set_transient($this->get_cache_key(), $content, $this->cache_duration);
    }
    
    /**
     * Clear cache
     */
    public function clear_cache() {
        delete_transient($this->get_cache_key());
    }
    
    /**
     * AJAX handlers
     */
    
    /**
     * Handle AJAX requests for logged in users
     */
    public function handle_ajax() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'spiralengine_widget_' . $this->widget_id)) {
            wp_die('Security check failed');
        }
        
        $action = sanitize_text_field($_POST['widget_action']);
        
        if (method_exists($this, 'ajax_' . $action)) {
            $this->{'ajax_' . $action}();
        } else {
            wp_send_json_error('Invalid action');
        }
    }
    
    /**
     * Handle AJAX requests for non-logged in users
     */
    public function handle_ajax_nopriv() {
        wp_send_json_error('Please log in to use this feature');
    }
    
    /**
     * Get widget sections
     */
    public function get_sections() {
        return $this->sections;
    }
    
    /**
     * Set display mode
     */
    public function set_display_mode($mode) {
        if (in_array($mode, array('preview', 'full'))) {
            $this->display_mode = $mode;
        }
    }
    
    /**
     * Get widget data for API
     */
    public function get_api_data() {
        return array(
            'uuid' => $this->widget_uuid,
            'id' => $this->widget_id,
            'name' => $this->widget_name,
            'type' => $this->widget_type,
            'category' => $this->widget_category,
            'is_episode' => $this->is_episode_widget,
            'episode_type' => $this->episode_type,
            'supports_preview' => $this->supports_preview,
            'supports_full' => $this->supports_full,
            'membership_required' => $this->membership_required
        );
    }
}

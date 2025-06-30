<?php
/**
 * SPIRAL Engine MemberPress Integration
 * 
 * @package    SPIRAL_Engine
 * @subpackage Integrations
 * @file       includes/integrations/class-spiralengine-memberpress.php
 * @since      1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * MemberPress Integration Class
 * 
 * Handles all MemberPress Pro integration, membership tier mapping,
 * and subscription management for the SPIRAL Engine platform.
 */
class SpiralEngine_MemberPress {
    
    /**
     * Instance of this class
     * 
     * @var SpiralEngine_MemberPress
     */
    private static $instance = null;
    
    /**
     * MemberPress detected flag
     * 
     * @var bool
     */
    private $memberpress_active = false;
    
    /**
     * Membership tier mappings
     * 
     * @var array
     */
    private $tier_mappings = array();
    
    /**
     * Cache for user memberships
     * 
     * @var array
     */
    private $membership_cache = array();
    
    /**
     * Available membership tiers
     * 
     * @var array
     */
    private $membership_tiers = array(
        'discovery' => array(
            'name' => 'Discovery',
            'level' => 0,
            'color' => '#808080',
            'icon' => 'dashicons-search',
            'description' => 'Your journey to understanding begins here'
        ),
        'explorer' => array(
            'name' => 'Explorer', 
            'level' => 1,
            'color' => '#3B82F6',
            'icon' => 'dashicons-explore',
            'description' => 'Unlock pattern detection and correlation insights'
        ),
        'pioneer' => array(
            'name' => 'Pioneer',
            'level' => 2,
            'color' => '#8B5CF6',
            'icon' => 'dashicons-flag',
            'description' => 'Advanced analytics and personalized recommendations'
        ),
        'navigator' => array(
            'name' => 'Navigator',
            'level' => 3,
            'color' => '#F59E0B',
            'icon' => 'dashicons-location',
            'description' => 'AI-powered forecasts and export capabilities'
        ),
        'voyager' => array(
            'name' => 'Voyager',
            'level' => 4,
            'color' => '#EF4444',
            'icon' => 'dashicons-star-filled',
            'description' => 'Complete platform access with API integration'
        )
    );
    
    /**
     * Get instance
     * 
     * @return SpiralEngine_MemberPress
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
        $this->detect_memberpress();
        
        if ($this->memberpress_active) {
            $this->init_hooks();
            $this->load_tier_mappings();
        }
    }
    
    /**
     * Detect if MemberPress Pro is active
     */
    private function detect_memberpress() {
        if (defined('MEPR_VERSION') && class_exists('MeprUtils')) {
            $this->memberpress_active = true;
            
            // Check for Pro version
            if (defined('MEPR_EDITION') && MEPR_EDITION === 'pro') {
                $this->memberpress_pro = true;
            }
        }
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Admin hooks
        add_action('admin_menu', array($this, 'add_mapping_page'), 100);
        add_action('wp_ajax_spiralengine_save_tier_mappings', array($this, 'ajax_save_tier_mappings'));
        
        // User hooks
        add_action('mepr-event-subscription-created', array($this, 'handle_subscription_created'));
        add_action('mepr-event-subscription-expired', array($this, 'handle_subscription_expired'));
        add_action('mepr-event-subscription-upgraded', array($this, 'handle_subscription_upgraded'));
        add_action('mepr-event-subscription-downgraded', array($this, 'handle_subscription_downgraded'));
        
        // Cache clearing
        add_action('mepr-event-transaction-completed', array($this, 'clear_user_cache'));
        add_action('mepr-event-transaction-refunded', array($this, 'clear_user_cache'));
        
        // User deletion
        add_action('delete_user', array($this, 'handle_user_deletion'));
    }
    
    /**
     * Load tier mappings from database
     */
    private function load_tier_mappings() {
        $mappings = get_option('spiralengine_memberpress_mappings', array());
        
        if (!empty($mappings)) {
            $this->tier_mappings = $mappings;
        }
    }
    
    /**
     * Get user membership tier
     * 
     * @param int $user_id User ID
     * @return string Membership tier key
     */
    public function get_user_tier($user_id) {
        // Check cache first
        if (isset($this->membership_cache[$user_id])) {
            return $this->membership_cache[$user_id];
        }
        
        // Default to discovery
        $tier = 'discovery';
        
        if (!$this->memberpress_active) {
            $this->membership_cache[$user_id] = $tier;
            return $tier;
        }
        
        // Get user's active subscriptions
        $user = new MeprUser($user_id);
        $subscriptions = $user->active_product_subscriptions();
        
        if (empty($subscriptions)) {
            $this->membership_cache[$user_id] = $tier;
            return $tier;
        }
        
        // Find highest tier
        $highest_level = 0;
        
        foreach ($subscriptions as $subscription) {
            if (!$subscription->is_active()) {
                continue;
            }
            
            $membership_id = $subscription->product_id;
            
            // Check if this membership is mapped to a tier
            foreach ($this->tier_mappings as $tier_key => $mapped_ids) {
                if (in_array($membership_id, $mapped_ids)) {
                    $tier_info = $this->membership_tiers[$tier_key];
                    if ($tier_info['level'] > $highest_level) {
                        $highest_level = $tier_info['level'];
                        $tier = $tier_key;
                    }
                }
            }
        }
        
        // Cache the result
        $this->membership_cache[$user_id] = $tier;
        
        // Store in user meta for quick access
        update_user_meta($user_id, 'spiralengine_membership_tier', $tier);
        
        return $tier;
    }
    
    /**
     * Check if user has minimum tier
     * 
     * @param int    $user_id User ID
     * @param string $required_tier Required tier
     * @return bool
     */
    public function user_has_tier($user_id, $required_tier) {
        $user_tier = $this->get_user_tier($user_id);
        
        $user_level = $this->membership_tiers[$user_tier]['level'];
        $required_level = $this->membership_tiers[$required_tier]['level'];
        
        return $user_level >= $required_level;
    }
    
    /**
     * Get user's subscription expiration
     * 
     * @param int $user_id User ID
     * @return DateTime|null
     */
    public function get_user_expiration($user_id) {
        if (!$this->memberpress_active) {
            return null;
        }
        
        $user = new MeprUser($user_id);
        $subscriptions = $user->active_product_subscriptions();
        
        $latest_expiration = null;
        
        foreach ($subscriptions as $subscription) {
            if (!$subscription->is_active()) {
                continue;
            }
            
            $expiration = $subscription->get_expires_at();
            
            if ($expiration && (!$latest_expiration || $expiration > $latest_expiration)) {
                $latest_expiration = new DateTime($expiration);
            }
        }
        
        return $latest_expiration;
    }
    
    /**
     * Handle subscription created
     * 
     * @param MeprEvent $event
     */
    public function handle_subscription_created($event) {
        $subscription = $event->get_data();
        $user_id = $subscription->user_id;
        
        // Clear cache
        $this->clear_user_cache_by_id($user_id);
        
        // Update tier
        $new_tier = $this->get_user_tier($user_id);
        
        // Log activity
        $this->log_membership_change($user_id, 'created', $new_tier);
        
        // Fire action for other components
        do_action('spiralengine_membership_updated', $user_id, $new_tier, 'created');
    }
    
    /**
     * Handle subscription expired
     * 
     * @param MeprEvent $event
     */
    public function handle_subscription_expired($event) {
        $subscription = $event->get_data();
        $user_id = $subscription->user_id;
        
        // Clear cache
        $this->clear_user_cache_by_id($user_id);
        
        // Update tier
        $new_tier = $this->get_user_tier($user_id);
        
        // Log activity
        $this->log_membership_change($user_id, 'expired', $new_tier);
        
        // Fire action for other components
        do_action('spiralengine_membership_updated', $user_id, $new_tier, 'expired');
    }
    
    /**
     * Handle subscription upgraded
     * 
     * @param MeprEvent $event
     */
    public function handle_subscription_upgraded($event) {
        $subscription = $event->get_data();
        $user_id = $subscription->user_id;
        
        // Clear cache
        $this->clear_user_cache_by_id($user_id);
        
        // Update tier
        $new_tier = $this->get_user_tier($user_id);
        
        // Log activity
        $this->log_membership_change($user_id, 'upgraded', $new_tier);
        
        // Fire action for other components
        do_action('spiralengine_membership_updated', $user_id, $new_tier, 'upgraded');
    }
    
    /**
     * Handle subscription downgraded
     * 
     * @param MeprEvent $event
     */
    public function handle_subscription_downgraded($event) {
        $subscription = $event->get_data();
        $user_id = $subscription->user_id;
        
        // Clear cache
        $this->clear_user_cache_by_id($user_id);
        
        // Update tier
        $new_tier = $this->get_user_tier($user_id);
        
        // Log activity
        $this->log_membership_change($user_id, 'downgraded', $new_tier);
        
        // Fire action for other components
        do_action('spiralengine_membership_updated', $user_id, $new_tier, 'downgraded');
    }
    
    /**
     * Clear user cache
     * 
     * @param MeprEvent $event
     */
    public function clear_user_cache($event) {
        $transaction = $event->get_data();
        $this->clear_user_cache_by_id($transaction->user_id);
    }
    
    /**
     * Clear user cache by ID
     * 
     * @param int $user_id
     */
    private function clear_user_cache_by_id($user_id) {
        unset($this->membership_cache[$user_id]);
        delete_user_meta($user_id, 'spiralengine_membership_tier');
        
        // Clear access control cache
        $access_control = SpiralEngine_Access_Control::get_instance();
        $access_control->clear_user_cache($user_id);
    }
    
    /**
     * Handle user deletion
     * 
     * @param int $user_id
     */
    public function handle_user_deletion($user_id) {
        $this->clear_user_cache_by_id($user_id);
    }
    
    /**
     * Add mapping page to admin
     */
    public function add_mapping_page() {
        add_submenu_page(
            'spiralengine',
            __('MemberPress Integration', 'spiralengine'),
            __('MemberPress', 'spiralengine'),
            'manage_options',
            'spiralengine-memberpress',
            array($this, 'render_mapping_page')
        );
    }
    
    /**
     * Render mapping page
     */
    public function render_mapping_page() {
        // Get all MemberPress memberships
        $memberships = $this->get_memberpress_memberships();
        
        include SPIRALENGINE_PATH . 'admin/views/memberpress-mapping.php';
    }
    
    /**
     * Get all MemberPress memberships
     * 
     * @return array
     */
    private function get_memberpress_memberships() {
        if (!$this->memberpress_active) {
            return array();
        }
        
        $memberships = MeprCptModel::all('MeprProduct');
        $result = array();
        
        foreach ($memberships as $membership) {
            $result[] = array(
                'id' => $membership->ID,
                'title' => $membership->post_title,
                'status' => $membership->post_status,
                'price' => $membership->price,
                'period' => $membership->period,
                'period_type' => $membership->period_type
            );
        }
        
        return $result;
    }
    
    /**
     * Ajax save tier mappings
     */
    public function ajax_save_tier_mappings() {
        // Check nonce
        if (!check_ajax_referer('spiralengine_memberpress_nonce', 'nonce', false)) {
            wp_die('Security check failed');
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die('Permission denied');
        }
        
        $mappings = isset($_POST['mappings']) ? $_POST['mappings'] : array();
        
        // Sanitize mappings
        $clean_mappings = array();
        foreach ($this->membership_tiers as $tier_key => $tier_info) {
            if (isset($mappings[$tier_key]) && is_array($mappings[$tier_key])) {
                $clean_mappings[$tier_key] = array_map('intval', $mappings[$tier_key]);
            } else {
                $clean_mappings[$tier_key] = array();
            }
        }
        
        // Save mappings
        update_option('spiralengine_memberpress_mappings', $clean_mappings);
        
        // Update instance
        $this->tier_mappings = $clean_mappings;
        
        // Clear all user caches
        $this->clear_all_user_caches();
        
        wp_send_json_success(array(
            'message' => __('Membership mappings saved successfully', 'spiralengine')
        ));
    }
    
    /**
     * Clear all user caches
     */
    private function clear_all_user_caches() {
        global $wpdb;
        
        // Clear memory cache
        $this->membership_cache = array();
        
        // Clear user meta cache
        $wpdb->delete($wpdb->usermeta, array('meta_key' => 'spiralengine_membership_tier'));
        
        // Clear access control caches
        $access_control = SpiralEngine_Access_Control::get_instance();
        $access_control->clear_all_caches();
    }
    
    /**
     * Log membership change
     * 
     * @param int    $user_id User ID
     * @param string $action Action type
     * @param string $new_tier New tier
     */
    private function log_membership_change($user_id, $action, $new_tier) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'spiralengine_membership_log';
        
        $wpdb->insert(
            $table_name,
            array(
                'user_id' => $user_id,
                'action' => $action,
                'new_tier' => $new_tier,
                'timestamp' => current_time('mysql'),
                'ip_address' => $_SERVER['REMOTE_ADDR']
            ),
            array('%d', '%s', '%s', '%s', '%s')
        );
    }
    
    /**
     * Get tier info
     * 
     * @param string $tier Tier key
     * @return array|null
     */
    public function get_tier_info($tier) {
        return isset($this->membership_tiers[$tier]) ? $this->membership_tiers[$tier] : null;
    }
    
    /**
     * Get all tiers
     * 
     * @return array
     */
    public function get_all_tiers() {
        return $this->membership_tiers;
    }
    
    /**
     * Check if MemberPress is active
     * 
     * @return bool
     */
    public function is_active() {
        return $this->memberpress_active;
    }
    
    /**
     * Get upgrade URL for tier
     * 
     * @param string $target_tier Target tier
     * @return string
     */
    public function get_upgrade_url($target_tier) {
        // Get mapped membership IDs for this tier
        if (!isset($this->tier_mappings[$target_tier]) || empty($this->tier_mappings[$target_tier])) {
            return home_url('/membership/');
        }
        
        // Get first mapped membership
        $membership_id = $this->tier_mappings[$target_tier][0];
        
        // Generate MemberPress signup URL
        return MeprUtils::get_permalink($membership_id);
    }
    
    /**
     * Get user's membership status
     * 
     * @param int $user_id User ID
     * @return array
     */
    public function get_user_membership_status($user_id) {
        $tier = $this->get_user_tier($user_id);
        $expiration = $this->get_user_expiration($user_id);
        
        return array(
            'tier' => $tier,
            'tier_info' => $this->get_tier_info($tier),
            'expiration' => $expiration,
            'is_active' => ($tier !== 'discovery'),
            'days_remaining' => $expiration ? ceil(($expiration->getTimestamp() - time()) / 86400) : null
        );
    }
}

// Initialize
add_action('init', array('SpiralEngine_MemberPress', 'get_instance'), 5);

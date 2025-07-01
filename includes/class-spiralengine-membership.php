<?php
/**
 * SpiralEngine Membership System
 *
 * Handles tier management, capability checking, usage tracking, and Stripe integration
 *
 * @package SpiralEngine
 * @since 1.0.0
 */

// includes/class-spiralengine-membership.php

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Membership system class
 */
class SpiralEngine_Membership {
    
    /**
     * WordPress database object
     *
     * @var wpdb
     */
    private $wpdb;
    
    /**
     * Tier limits cache
     *
     * @var array
     */
    private $tier_limits_cache = [];
    
    /**
     * User memberships cache
     *
     * @var array
     */
    private $memberships_cache = [];
    
    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        
        // Initialize hooks
        $this->init_hooks();
        
        // Load tier limits
        $this->load_tier_limits();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Stripe webhook handler
        add_action('wp_ajax_nopriv_spiralengine_stripe_webhook', [$this, 'handle_stripe_webhook']);
        add_action('wp_ajax_spiralengine_stripe_webhook', [$this, 'handle_stripe_webhook']);
        
        // Clear cache on user update
        add_action('spiralengine_membership_updated', [$this, 'clear_membership_cache'], 10, 1);
        
        // Check capabilities
        add_filter('user_has_cap', [$this, 'filter_user_capabilities'], 10, 4);
        
        // Login redirect to dashboard
        add_filter('login_redirect', function($redirect, $request, $user) {
            if (!is_wp_error($user)) {
                return home_url('/dashboard/');
            }
            return $redirect;
        }, 10, 3);
    }
    
    /**
     * Load tier limits from options
     */
    private function load_tier_limits() {
        $this->tier_limits_cache = get_option('spiralengine_tier_limits', []);
    }
    
    /**
     * Get user tier
     *
     * @param int $user_id User ID (0 for current user)
     * @return string User tier
     */
    public function get_user_tier(int $user_id = 0): string {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        if (!$user_id) {
            return SPIRALENGINE_TIER_FREE;
        }
        
        $membership = $this->get_membership($user_id);
        
        if (!$membership) {
            return SPIRALENGINE_TIER_FREE;
        }
        
        return $membership['tier'];
    }
    
    /**
     * Get user membership
     *
     * @param int $user_id User ID
     * @return array|null Membership data or null
     */
    public function get_membership(int $user_id): ?array {
        // Check cache
        if (isset($this->memberships_cache[$user_id])) {
            return $this->memberships_cache[$user_id];
        }
        
        // Query database
        $table_name = $this->wpdb->prefix . 'spiralengine_memberships';
        $membership = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM $table_name WHERE user_id = %d AND status = %s",
                $user_id,
                SPIRALENGINE_STATUS_ACTIVE
            ),
            ARRAY_A
        );
        
        if ($membership) {
            // FIXED: Decode JSON fields - handle null values properly
            $membership['custom_limits'] = !empty($membership['custom_limits']) 
                ? json_decode($membership['custom_limits'], true) 
                : [];
            
            // Cache result
            $this->memberships_cache[$user_id] = $membership;
        }
        
        return $membership;
    }
    
    /**
     * Update user tier
     *
     * @param int $user_id User ID
     * @param string $tier New tier
     * @param array $options Update options
     * @return bool Success status
     */
    public function update_tier(int $user_id, string $tier, array $options = []): bool {
        // Validate tier
        $valid_tiers = [
            SPIRALENGINE_TIER_FREE,
            SPIRALENGINE_TIER_BRONZE,
            SPIRALENGINE_TIER_SILVER,
            SPIRALENGINE_TIER_GOLD,
            SPIRALENGINE_TIER_PLATINUM,
            SPIRALENGINE_TIER_CUSTOM
        ];
        
        if (!in_array($tier, $valid_tiers)) {
            return false;
        }
        
        // Get current membership
        $current = $this->get_membership($user_id);
        
        if (!$current) {
            // Create new membership
            return $this->create_membership($user_id, $tier, $options);
        }
        
        // Update existing membership
        $table_name = $this->wpdb->prefix . 'spiralengine_memberships';
        
        $update_data = [
            'tier' => $tier,
            'updated_at' => current_time('mysql')
        ];
        
        // Handle expiration
        if (isset($options['expires_at'])) {
            $update_data['expires_at'] = $options['expires_at'];
        }
        
        // Handle custom limits
        if (isset($options['custom_limits'])) {
            $update_data['custom_limits'] = json_encode($options['custom_limits']);
        }
        
        $result = $this->wpdb->update(
            $table_name,
            $update_data,
            ['user_id' => $user_id],
            array_merge(['%s', '%s'], isset($options['expires_at']) ? ['%s'] : [], isset($options['custom_limits']) ? ['%s'] : []),
            ['%d']
        );
        
        if ($result !== false) {
            // Clear cache
            $this->clear_membership_cache($user_id);
            
            // Log change
            SpiralEngine_Core::log_admin_action('tier_updated', 'user', $user_id, [
                'old_tier' => $current['tier'],
                'new_tier' => $tier,
                'admin_id' => get_current_user_id()
            ]);
            
            // Fire action
            do_action('spiralengine_membership_updated', $user_id, $tier, $current['tier']);
            
            return true;
        }
        
        return false;
    }
    
    /**
     * Create membership
     *
     * @param int $user_id User ID
     * @param string $tier Tier
     * @param array $options Creation options
     * @return bool Success status
     */
    private function create_membership(int $user_id, string $tier, array $options = []): bool {
        $table_name = $this->wpdb->prefix . 'spiralengine_memberships';
        
        $insert_data = [
            'user_id' => $user_id,
            'tier' => $tier,
            'status' => SPIRALENGINE_STATUS_ACTIVE,
            'starts_at' => current_time('mysql'),
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        ];
        
        if (isset($options['stripe_customer_id'])) {
            $insert_data['stripe_customer_id'] = $options['stripe_customer_id'];
        }
        
        if (isset($options['stripe_subscription_id'])) {
            $insert_data['stripe_subscription_id'] = $options['stripe_subscription_id'];
        }
        
        if (isset($options['expires_at'])) {
            $insert_data['expires_at'] = $options['expires_at'];
        }
        
        if (isset($options['custom_limits'])) {
            $insert_data['custom_limits'] = json_encode($options['custom_limits']);
        }
        
        $result = $this->wpdb->insert(
            $table_name,
            $insert_data,
            array_merge(['%d', '%s', '%s', '%s', '%s', '%s'], 
                isset($options['stripe_customer_id']) ? ['%s'] : [],
                isset($options['stripe_subscription_id']) ? ['%s'] : [],
                isset($options['expires_at']) ? ['%s'] : [],
                isset($options['custom_limits']) ? ['%s'] : []
            )
        );
        
        if ($result) {
            // Fire action
            do_action('spiralengine_membership_created', $user_id, $tier);
            
            return true;
        }
        
        return false;
    }
    
    /**
     * Check usage limit
     *
     * @param string $type Usage type
     * @param int $user_id User ID
     * @return bool Whether within limits
     */
    public function check_usage_limit(string $type, int $user_id = 0): bool {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        if (!$user_id) {
            return false;
        }
        
        $membership = $this->get_membership($user_id);
        if (!$membership) {
            return false;
        }
        
        $tier = $membership['tier'];
        $limits = $this->get_tier_limits($tier);
        
        // Check custom limits first
        if (!empty($membership['custom_limits'][$type])) {
            $limit = $membership['custom_limits'][$type];
        } elseif (isset($limits[$type])) {
            $limit = $limits[$type];
        } else {
            return true; // No limit defined
        }
        
        // Unlimited check
        if ($limit === 'unlimited') {
            return true;
        }
        
        // Get current usage
        $usage = $this->get_usage($type, $user_id);
        
        return $usage < $limit;
    }
    
    /**
     * Get usage
     *
     * @param string $type Usage type
     * @param int $user_id User ID
     * @return int Current usage
     */
    public function get_usage(string $type, int $user_id): int {
        switch ($type) {
            case 'episodes':
                return $this->get_episode_usage($user_id);
                
            case 'ai_analyses':
                return $this->get_ai_usage($user_id);
                
            default:
                return apply_filters('spiralengine_get_usage', 0, $type, $user_id);
        }
    }
    
    /**
     * Update usage
     *
     * @param string $type Usage type
     * @param int $user_id User ID
     * @param int $amount Amount to add
     */
    public function update_usage(string $type, int $user_id = 0, int $amount = 1) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        // Store usage update
        $usage_key = "spiralengine_usage_{$type}_{$user_id}_" . date('Y_m');
        $current = get_user_meta($user_id, $usage_key, true) ?: 0;
        update_user_meta($user_id, $usage_key, $current + $amount);
        
        // Fire action
        do_action('spiralengine_usage_updated', $type, $user_id, $amount);
    }
    
    /**
     * Get episode usage for current month
     *
     * @param int $user_id User ID
     * @return int Episode count
     */
    private function get_episode_usage(int $user_id): int {
        $table_name = $this->wpdb->prefix . 'spiralengine_episodes';
        
        $count = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT COUNT(*) FROM $table_name 
                WHERE user_id = %d 
                AND created_at >= %s",
                $user_id,
                date('Y-m-01 00:00:00')
            )
        );
        
        return intval($count);
    }
    
    /**
     * Get AI usage for current month
     *
     * @param int $user_id User ID
     * @return int AI analysis count
     */
    private function get_ai_usage(int $user_id): int {
        $usage_key = "spiralengine_usage_ai_analyses_{$user_id}_" . date('Y_m');
        return intval(get_user_meta($user_id, $usage_key, true));
    }
    
    /**
     * Get tier limits
     *
     * @param string $tier Tier name
     * @return array Tier limits
     */
    public function get_tier_limits(string $tier): array {
        return $this->tier_limits_cache[$tier] ?? [
            'episodes_per_month' => 50,
            'widgets' => ['overthinking'],
            'ai_analyses' => 5,
            'export_formats' => ['csv']
        ];
    }
    
    /**
     * Get available widgets for tier
     *
     * @param string $tier Tier name
     * @return array Available widget IDs
     */
    public function get_tier_widgets(string $tier): array {
        $limits = $this->get_tier_limits($tier);
        
        if (!isset($limits['widgets'])) {
            return [];
        }
        
        if ($limits['widgets'] === 'all') {
            // Get all registered widgets
            $core = SpiralEngine_Core::get_instance();
            $widgets = $core->get_widgets();
            return array_keys($widgets);
        }
        
        return $limits['widgets'];
    }
    
    /**
     * Check if user has access to widget
     *
     * @param string $widget_id Widget ID
     * @param int $user_id User ID
     * @return bool Whether has access
     */
    public function can_access_widget(string $widget_id, int $user_id = 0): bool {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        $tier = $this->get_user_tier($user_id);
        $available_widgets = $this->get_tier_widgets($tier);
        
        return in_array($widget_id, $available_widgets) || $available_widgets === 'all';
    }
    
    /**
     * Clear membership cache
     *
     * @param int $user_id User ID
     */
    public function clear_membership_cache(int $user_id) {
        unset($this->memberships_cache[$user_id]);
    }
    
    /**
     * Filter user capabilities
     *
     * @param array $allcaps All capabilities
     * @param array $caps Required capabilities
     * @param array $args Arguments
     * @param WP_User $user User object
     * @return array Modified capabilities
     */
    public function filter_user_capabilities($allcaps, $caps, $args, $user) {
        // Add tier-based capabilities
        $tier = $this->get_user_tier($user->ID);
        
        // Premium tier capabilities
        if (in_array($tier, [SPIRALENGINE_TIER_GOLD, SPIRALENGINE_TIER_PLATINUM])) {
            $allcaps['spiralengine_premium_features'] = true;
        }
        
        // Platinum exclusive capabilities
        if ($tier === SPIRALENGINE_TIER_PLATINUM) {
            $allcaps['spiralengine_platinum_features'] = true;
            $allcaps['spiralengine_unlimited_exports'] = true;
            $allcaps['spiralengine_unlimited_ai'] = true;
        }
        
        return $allcaps;
    }
    
    /**
     * Handle Stripe webhook
     */
    public function handle_stripe_webhook() {
        // Verify webhook signature
        $payload = @file_get_contents('php://input');
        $sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
        $endpoint_secret = get_option('spiralengine_stripe_webhook_secret');
        
        if (!$endpoint_secret) {
            wp_die('Webhook secret not configured', 400);
        }
        
        // Process webhook
        try {
            // In production, verify signature with Stripe SDK
            $event = json_decode($payload, true);
            
            switch ($event['type']) {
                case 'customer.subscription.created':
                case 'customer.subscription.updated':
                    $this->handle_subscription_update($event['data']['object']);
                    break;
                    
                case 'customer.subscription.deleted':
                    $this->handle_subscription_cancellation($event['data']['object']);
                    break;
                    
                case 'invoice.payment_succeeded':
                    $this->handle_payment_success($event['data']['object']);
                    break;
                    
                case 'invoice.payment_failed':
                    $this->handle_payment_failure($event['data']['object']);
                    break;
            }
            
            wp_die('Webhook processed', 200);
            
        } catch (Exception $e) {
            wp_die('Webhook error: ' . $e->getMessage(), 400);
        }
    }
    
    /**
     * Handle subscription update
     *
     * @param array $subscription Stripe subscription object
     */
    private function handle_subscription_update(array $subscription) {
        // Implementation for production
        do_action('spiralengine_stripe_subscription_updated', $subscription);
    }
    
    /**
     * Handle subscription cancellation
     *
     * @param array $subscription Stripe subscription object
     */
    private function handle_subscription_cancellation(array $subscription) {
        // Implementation for production
        do_action('spiralengine_stripe_subscription_cancelled', $subscription);
    }
    
    /**
     * Handle payment success
     *
     * @param array $invoice Stripe invoice object
     */
    private function handle_payment_success(array $invoice) {
        // Implementation for production
        do_action('spiralengine_stripe_payment_success', $invoice);
    }
    
    /**
     * Handle payment failure
     *
     * @param array $invoice Stripe invoice object
     */
    private function handle_payment_failure(array $invoice) {
        // Implementation for production
        do_action('spiralengine_stripe_payment_failed', $invoice);
    }
    
    /**
     * Check membership expirations
     */
    public function check_expirations() {
        $table_name = $this->wpdb->prefix . 'spiralengine_memberships';
        
        // Get expired memberships
        $expired = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT user_id FROM $table_name 
                WHERE status = %s 
                AND expires_at IS NOT NULL 
                AND expires_at < %s",
                SPIRALENGINE_STATUS_ACTIVE,
                current_time('mysql')
            ),
            ARRAY_A
        );
        
        foreach ($expired as $membership) {
            $this->expire_membership($membership['user_id']);
        }
    }
    
    /**
     * Expire membership
     *
     * @param int $user_id User ID
     */
    private function expire_membership(int $user_id) {
        $table_name = $this->wpdb->prefix . 'spiralengine_memberships';
        
        // Update status
        $this->wpdb->update(
            $table_name,
            [
                'status' => SPIRALENGINE_STATUS_EXPIRED,
                'updated_at' => current_time('mysql')
            ],
            ['user_id' => $user_id],
            ['%s', '%s'],
            ['%d']
        );
        
        // Clear cache
        $this->clear_membership_cache($user_id);
        
        // Log expiration
        SpiralEngine_Core::log_admin_action('membership_expired', 'user', $user_id, [
            'reason' => 'time_based_expiration'
        ]);
        
        // Fire action
        do_action('spiralengine_membership_expired', $user_id);
    }
}


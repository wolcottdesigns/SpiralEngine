<?php
/**
 * SpiralEngine Rate Limiter
 * 
 * @package    SpiralEngine
 * @subpackage API
 * @since      1.0.0
 */

// includes/api/class-spiralengine-rate-limiter.php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Rate Limiter Class
 * 
 * Implements tier-based rate limiting for API requests
 */
class SpiralEngine_Rate_Limiter {
    
    /**
     * Rate limit configurations per tier
     * 
     * @var array
     */
    private $tier_limits = array(
        'discovery' => array(
            'requests_per_hour' => 100,
            'burst_limit' => 20,
            'concurrent_requests' => 2
        ),
        'explorer' => array(
            'requests_per_hour' => 500,
            'burst_limit' => 50,
            'concurrent_requests' => 5
        ),
        'pioneer' => array(
            'requests_per_hour' => 1000,
            'burst_limit' => 100,
            'concurrent_requests' => 10
        ),
        'navigator' => array(
            'requests_per_hour' => 5000,
            'burst_limit' => 250,
            'concurrent_requests' => 20
        ),
        'voyager' => array(
            'requests_per_hour' => -1, // Unlimited
            'burst_limit' => 500,
            'concurrent_requests' => 50
        )
    );
    
    /**
     * Endpoint-specific multipliers
     * 
     * @var array
     */
    private $endpoint_costs = array(
        'episodes/quick-log' => 0.5,
        'insights/generate' => 5,
        'patterns/detect' => 3,
        'analytics/*' => 2,
        'users/me/export' => 10,
        'webhooks/test' => 2
    );
    
    /**
     * Cache prefix for rate limit data
     * 
     * @var string
     */
    private $cache_prefix = 'spiralengine_rate_limit_';
    
    /**
     * Ban duration in seconds
     * 
     * @var int
     */
    private $ban_duration = 3600; // 1 hour
    
    /**
     * Database handler
     * 
     * @var SpiralEngine_Database
     */
    private $database;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->database = new SpiralEngine_Database();
        
        // Clean up old rate limit data periodically
        add_action('spiralengine_hourly_cleanup', array($this, 'cleanup_old_data'));
    }
    
    /**
     * Check if request is within rate limits
     * 
     * @param int $user_id User ID
     * @param string $endpoint API endpoint
     * @return true|WP_Error True if allowed, WP_Error if rate limited
     */
    public function check_limit($user_id, $endpoint) {
        // Check if user is banned
        if ($this->is_banned($user_id)) {
            return new WP_Error(
                'spiralengine_rate_limit_banned',
                __('You have been temporarily banned due to excessive requests.', 'spiralengine'),
                array('status' => 429)
            );
        }
        
        // Get user tier
        $tier = $this->get_user_tier($user_id);
        $limits = $this->tier_limits[$tier];
        
        // Voyager tier has unlimited requests
        if ($limits['requests_per_hour'] === -1) {
            return true;
        }
        
        // Check concurrent requests
        $concurrent_check = $this->check_concurrent_requests($user_id, $limits['concurrent_requests']);
        if (is_wp_error($concurrent_check)) {
            return $concurrent_check;
        }
        
        // Get endpoint cost
        $cost = $this->get_endpoint_cost($endpoint);
        
        // Check hourly limit
        $hourly_check = $this->check_hourly_limit($user_id, $limits['requests_per_hour'], $cost);
        if (is_wp_error($hourly_check)) {
            return $hourly_check;
        }
        
        // Check burst limit
        $burst_check = $this->check_burst_limit($user_id, $limits['burst_limit'], $cost);
        if (is_wp_error($burst_check)) {
            return $burst_check;
        }
        
        // Record request
        $this->record_request($user_id, $endpoint, $cost);
        
        return true;
    }
    
    /**
     * Get rate limit info for user
     * 
     * @param int $user_id User ID
     * @param string $endpoint API endpoint
     * @return array
     */
    public function get_limit_info($user_id, $endpoint) {
        $tier = $this->get_user_tier($user_id);
        $limits = $this->tier_limits[$tier];
        
        if ($limits['requests_per_hour'] === -1) {
            return array(
                'limit' => 'unlimited',
                'remaining' => 'unlimited',
                'reset' => time() + 3600
            );
        }
        
        $hour_key = $this->get_hour_key();
        $used = $this->get_request_count($user_id, $hour_key);
        $remaining = max(0, $limits['requests_per_hour'] - $used);
        
        return array(
            'limit' => $limits['requests_per_hour'],
            'remaining' => $remaining,
            'reset' => strtotime('+1 hour', strtotime(date('Y-m-d H:00:00'))),
            'tier' => $tier,
            'burst_limit' => $limits['burst_limit'],
            'concurrent_limit' => $limits['concurrent_requests']
        );
    }
    
    /**
     * Check concurrent requests
     * 
     * @param int $user_id User ID
     * @param int $limit Concurrent request limit
     * @return true|WP_Error
     */
    private function check_concurrent_requests($user_id, $limit) {
        $key = $this->cache_prefix . 'concurrent_' . $user_id;
        $current = (int) get_transient($key);
        
        if ($current >= $limit) {
            return new WP_Error(
                'spiralengine_concurrent_limit',
                sprintf(
                    __('Too many concurrent requests. Maximum %d allowed.', 'spiralengine'),
                    $limit
                ),
                array('status' => 429)
            );
        }
        
        // Increment concurrent counter with 30 second expiry
        set_transient($key, $current + 1, 30);
        
        // Register shutdown function to decrement counter
        register_shutdown_function(array($this, 'decrement_concurrent'), $user_id);
        
        return true;
    }
    
    /**
     * Decrement concurrent request counter
     * 
     * @param int $user_id User ID
     */
    public function decrement_concurrent($user_id) {
        $key = $this->cache_prefix . 'concurrent_' . $user_id;
        $current = (int) get_transient($key);
        
        if ($current > 0) {
            set_transient($key, $current - 1, 30);
        } else {
            delete_transient($key);
        }
    }
    
    /**
     * Check hourly request limit
     * 
     * @param int $user_id User ID
     * @param int $limit Hourly limit
     * @param float $cost Request cost
     * @return true|WP_Error
     */
    private function check_hourly_limit($user_id, $limit, $cost) {
        $hour_key = $this->get_hour_key();
        $count = $this->get_request_count($user_id, $hour_key);
        
        if (($count + $cost) > $limit) {
            $reset_time = strtotime('+1 hour', strtotime(date('Y-m-d H:00:00')));
            
            return new WP_Error(
                'spiralengine_hourly_limit',
                sprintf(
                    __('Hourly rate limit exceeded. Limit resets at %s.', 'spiralengine'),
                    date('H:i', $reset_time)
                ),
                array(
                    'status' => 429,
                    'retry_after' => $reset_time - time()
                )
            );
        }
        
        return true;
    }
    
    /**
     * Check burst limit
     * 
     * @param int $user_id User ID
     * @param int $limit Burst limit
     * @param float $cost Request cost
     * @return true|WP_Error
     */
    private function check_burst_limit($user_id, $limit, $cost) {
        $minute_key = $this->get_minute_key();
        $burst_count = $this->get_burst_count($user_id, $minute_key);
        
        if (($burst_count + $cost) > $limit) {
            return new WP_Error(
                'spiralengine_burst_limit',
                __('Too many requests in a short time. Please slow down.', 'spiralengine'),
                array(
                    'status' => 429,
                    'retry_after' => 60
                )
            );
        }
        
        return true;
    }
    
    /**
     * Record API request
     * 
     * @param int $user_id User ID
     * @param string $endpoint Endpoint
     * @param float $cost Request cost
     */
    private function record_request($user_id, $endpoint, $cost) {
        global $wpdb;
        
        // Record in database for analytics
        $wpdb->insert(
            $wpdb->prefix . 'spiralengine_api_requests',
            array(
                'user_id' => $user_id,
                'endpoint' => $endpoint,
                'cost' => $cost,
                'timestamp' => current_time('mysql', true),
                'ip_address' => $this->get_client_ip()
            ),
            array('%d', '%s', '%f', '%s', '%s')
        );
        
        // Update hourly counter
        $hour_key = $this->get_hour_key();
        $this->increment_counter($user_id, $hour_key, $cost, 3600);
        
        // Update burst counter
        $minute_key = $this->get_minute_key();
        $this->increment_counter($user_id, $minute_key, $cost, 60);
        
        // Check for abuse patterns
        $this->check_abuse_patterns($user_id);
    }
    
    /**
     * Get request count for period
     * 
     * @param int $user_id User ID
     * @param string $period_key Period key
     * @return float
     */
    private function get_request_count($user_id, $period_key) {
        $key = $this->cache_prefix . $user_id . '_' . $period_key;
        return (float) get_transient($key);
    }
    
    /**
     * Get burst count
     * 
     * @param int $user_id User ID
     * @param string $minute_key Minute key
     * @return float
     */
    private function get_burst_count($user_id, $minute_key) {
        $key = $this->cache_prefix . 'burst_' . $user_id . '_' . $minute_key;
        return (float) get_transient($key);
    }
    
    /**
     * Increment counter
     * 
     * @param int $user_id User ID
     * @param string $period_key Period key
     * @param float $amount Amount to increment
     * @param int $expiry Expiry time in seconds
     */
    private function increment_counter($user_id, $period_key, $amount, $expiry) {
        $prefix = $expiry === 60 ? 'burst_' : '';
        $key = $this->cache_prefix . $prefix . $user_id . '_' . $period_key;
        
        $current = (float) get_transient($key);
        set_transient($key, $current + $amount, $expiry);
    }
    
    /**
     * Get endpoint cost
     * 
     * @param string $endpoint Endpoint
     * @return float
     */
    private function get_endpoint_cost($endpoint) {
        // Remove leading slash and API namespace
        $endpoint = trim(str_replace('/spiralengine/v1/', '', $endpoint), '/');
        
        // Check exact match first
        if (isset($this->endpoint_costs[$endpoint])) {
            return $this->endpoint_costs[$endpoint];
        }
        
        // Check wildcard matches
        foreach ($this->endpoint_costs as $pattern => $cost) {
            if (strpos($pattern, '*') !== false) {
                $regex = str_replace('*', '.*', $pattern);
                if (preg_match('/^' . $regex . '$/', $endpoint)) {
                    return $cost;
                }
            }
        }
        
        // Default cost
        return 1;
    }
    
    /**
     * Check for abuse patterns
     * 
     * @param int $user_id User ID
     */
    private function check_abuse_patterns($user_id) {
        global $wpdb;
        
        // Check for rapid repeated requests to same endpoint
        $repeated_requests = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}spiralengine_api_requests
            WHERE user_id = %d
            AND timestamp > DATE_SUB(NOW(), INTERVAL 1 MINUTE)
            GROUP BY endpoint
            ORDER BY COUNT(*) DESC
            LIMIT 1",
            $user_id
        ));
        
        if ($repeated_requests > 50) {
            $this->flag_potential_abuse($user_id, 'rapid_repeated_requests');
        }
        
        // Check for distributed requests pattern
        $unique_ips = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT ip_address) 
            FROM {$wpdb->prefix}spiralengine_api_requests
            WHERE user_id = %d
            AND timestamp > DATE_SUB(NOW(), INTERVAL 5 MINUTE)",
            $user_id
        ));
        
        if ($unique_ips > 10) {
            $this->flag_potential_abuse($user_id, 'distributed_requests');
        }
    }
    
    /**
     * Flag potential abuse
     * 
     * @param int $user_id User ID
     * @param string $reason Abuse reason
     */
    private function flag_potential_abuse($user_id, $reason) {
        global $wpdb;
        
        // Record abuse flag
        $wpdb->insert(
            $wpdb->prefix . 'spiralengine_api_abuse_flags',
            array(
                'user_id' => $user_id,
                'reason' => $reason,
                'timestamp' => current_time('mysql', true),
                'ip_address' => $this->get_client_ip()
            ),
            array('%d', '%s', '%s', '%s')
        );
        
        // Check if should ban
        $recent_flags = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}spiralengine_api_abuse_flags
            WHERE user_id = %d
            AND timestamp > DATE_SUB(NOW(), INTERVAL 1 HOUR)",
            $user_id
        ));
        
        if ($recent_flags >= 3) {
            $this->ban_user($user_id, $reason);
        }
    }
    
    /**
     * Ban user temporarily
     * 
     * @param int $user_id User ID
     * @param string $reason Ban reason
     */
    private function ban_user($user_id, $reason) {
        global $wpdb;
        
        // Set ban
        set_transient($this->cache_prefix . 'banned_' . $user_id, true, $this->ban_duration);
        
        // Record ban
        $wpdb->insert(
            $wpdb->prefix . 'spiralengine_api_bans',
            array(
                'user_id' => $user_id,
                'reason' => $reason,
                'banned_at' => current_time('mysql', true),
                'expires_at' => date('Y-m-d H:i:s', time() + $this->ban_duration),
                'ip_address' => $this->get_client_ip()
            ),
            array('%d', '%s', '%s', '%s', '%s')
        );
        
        // Notify user
        do_action('spiralengine_user_api_banned', $user_id, $reason);
        
        // Log for admin review
        error_log(sprintf(
            'SpiralEngine API: User %d banned for %s',
            $user_id,
            $reason
        ));
    }
    
    /**
     * Check if user is banned
     * 
     * @param int $user_id User ID
     * @return bool
     */
    private function is_banned($user_id) {
        return get_transient($this->cache_prefix . 'banned_' . $user_id) === true;
    }
    
    /**
     * Unban user
     * 
     * @param int $user_id User ID
     * @return bool
     */
    public function unban_user($user_id) {
        global $wpdb;
        
        // Remove ban
        delete_transient($this->cache_prefix . 'banned_' . $user_id);
        
        // Update database
        $wpdb->update(
            $wpdb->prefix . 'spiralengine_api_bans',
            array(
                'unbanned_at' => current_time('mysql', true),
                'unbanned_by' => get_current_user_id()
            ),
            array(
                'user_id' => $user_id,
                'unbanned_at' => null
            ),
            array('%s', '%d'),
            array('%d', '%s')
        );
        
        return true;
    }
    
    /**
     * Get user tier
     * 
     * @param int $user_id User ID
     * @return string
     */
    private function get_user_tier($user_id) {
        // Check cache first
        $cached_tier = wp_cache_get('spiralengine_user_tier_' . $user_id);
        if ($cached_tier !== false) {
            return $cached_tier;
        }
        
        // Default tier
        $tier = 'discovery';
        
        // Check MemberPress membership
        if (class_exists('MeprUser')) {
            $mepr_user = new MeprUser($user_id);
            $memberships = $mepr_user->active_product_subscriptions();
            
            if (!empty($memberships)) {
                $tier_mapping = array(
                    'voyager' => 5,
                    'navigator' => 4,
                    'pioneer' => 3,
                    'explorer' => 2,
                    'discovery' => 1
                );
                
                $highest_level = 1;
                
                foreach ($memberships as $membership_id) {
                    $membership = new MeprProduct($membership_id);
                    $slug = $membership->post_name;
                    
                    foreach ($tier_mapping as $tier_name => $level) {
                        if (strpos($slug, $tier_name) !== false && $level > $highest_level) {
                            $tier = $tier_name;
                            $highest_level = $level;
                        }
                    }
                }
            }
        }
        
        // Cache for 5 minutes
        wp_cache_set('spiralengine_user_tier_' . $user_id, $tier, '', 300);
        
        return $tier;
    }
    
    /**
     * Get current hour key
     * 
     * @return string
     */
    private function get_hour_key() {
        return date('Y-m-d-H');
    }
    
    /**
     * Get current minute key
     * 
     * @return string
     */
    private function get_minute_key() {
        return date('Y-m-d-H-i');
    }
    
    /**
     * Get client IP address
     * 
     * @return string
     */
    private function get_client_ip() {
        $ip_keys = array('HTTP_CF_CONNECTING_IP', 'HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR');
        
        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                
                // Handle comma-separated IPs
                if (strpos($ip, ',') !== false) {
                    $ips = explode(',', $ip);
                    $ip = trim($ips[0]);
                }
                
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
    
    /**
     * Clean up old rate limit data
     */
    public function cleanup_old_data() {
        global $wpdb;
        
        // Delete old request logs (keep 7 days)
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->prefix}spiralengine_api_requests
            WHERE timestamp < DATE_SUB(NOW(), INTERVAL %d DAY)",
            7
        ));
        
        // Delete old abuse flags (keep 30 days)
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->prefix}spiralengine_api_abuse_flags
            WHERE timestamp < DATE_SUB(NOW(), INTERVAL %d DAY)",
            30
        ));
        
        // Delete expired bans
        $wpdb->query(
            "DELETE FROM {$wpdb->prefix}spiralengine_api_bans
            WHERE expires_at < NOW()
            AND unbanned_at IS NULL"
        );
    }
    
    /**
     * Get rate limit statistics
     * 
     * @param int $user_id User ID
     * @param string $period Period (hour, day, week)
     * @return array
     */
    public function get_statistics($user_id, $period = 'day') {
        global $wpdb;
        
        $interval = $this->get_interval_for_period($period);
        
        $stats = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                COUNT(*) as total_requests,
                SUM(cost) as total_cost,
                COUNT(DISTINCT endpoint) as unique_endpoints,
                COUNT(DISTINCT ip_address) as unique_ips,
                AVG(cost) as avg_cost_per_request
            FROM {$wpdb->prefix}spiralengine_api_requests
            WHERE user_id = %d
            AND timestamp > DATE_SUB(NOW(), INTERVAL {$interval})",
            $user_id
        ), ARRAY_A);
        
        // Get most used endpoints
        $top_endpoints = $wpdb->get_results($wpdb->prepare(
            "SELECT endpoint, COUNT(*) as count, SUM(cost) as total_cost
            FROM {$wpdb->prefix}spiralengine_api_requests
            WHERE user_id = %d
            AND timestamp > DATE_SUB(NOW(), INTERVAL {$interval})
            GROUP BY endpoint
            ORDER BY count DESC
            LIMIT 10",
            $user_id
        ), ARRAY_A);
        
        $stats['top_endpoints'] = $top_endpoints;
        $stats['tier'] = $this->get_user_tier($user_id);
        $stats['is_banned'] = $this->is_banned($user_id);
        
        return $stats;
    }
    
    /**
     * Get interval for period
     * 
     * @param string $period Period name
     * @return string SQL interval
     */
    private function get_interval_for_period($period) {
        switch ($period) {
            case 'hour':
                return '1 HOUR';
            case 'day':
                return '1 DAY';
            case 'week':
                return '1 WEEK';
            case 'month':
                return '1 MONTH';
            default:
                return '1 DAY';
        }
    }
    
    /**
     * Override rate limit for user (admin only)
     * 
     * @param int $user_id User ID
     * @param array $custom_limits Custom limits
     * @return bool
     */
    public function set_custom_limits($user_id, $custom_limits) {
        if (!current_user_can('manage_options')) {
            return false;
        }
        
        update_user_meta($user_id, 'spiralengine_custom_rate_limits', $custom_limits);
        
        // Clear tier cache
        wp_cache_delete('spiralengine_user_tier_' . $user_id);
        
        return true;
    }
    
    /**
     * Get custom limits for user
     * 
     * @param int $user_id User ID
     * @return array|null
     */
    private function get_custom_limits($user_id) {
        return get_user_meta($user_id, 'spiralengine_custom_rate_limits', true);
    }
}

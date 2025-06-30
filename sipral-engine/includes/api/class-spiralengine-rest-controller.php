<?php
/**
 * SpiralEngine REST API Base Controller
 * 
 * @package    SpiralEngine
 * @subpackage API
 * @since      1.0.0
 */

// includes/api/class-spiralengine-rest-controller.php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Base REST API Controller
 * 
 * Provides common functionality for all REST API endpoints
 */
abstract class SpiralEngine_REST_Controller extends WP_REST_Controller {
    
    /**
     * Namespace for API routes
     * 
     * @var string
     */
    protected $namespace = 'spiralengine/v1';
    
    /**
     * User timezone manager instance
     * 
     * @var SPIRALENGINE_Time_Zone_Manager
     */
    protected $time_manager;
    
    /**
     * Rate limiter instance
     * 
     * @var SpiralEngine_Rate_Limiter
     */
    protected $rate_limiter;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->time_manager = new SPIRALENGINE_Time_Zone_Manager();
        $this->rate_limiter = new SpiralEngine_Rate_Limiter();
    }
    
    /**
     * Check if a given request has access to the endpoint
     * 
     * @param WP_REST_Request $request Full data about the request
     * @return bool|WP_Error True if the request has access, WP_Error object otherwise
     */
    public function permissions_check($request) {
        // Check if user is logged in
        if (!is_user_logged_in()) {
            return new WP_Error(
                'spiralengine_rest_unauthorized',
                __('You must be logged in to access this endpoint.', 'spiralengine'),
                array('status' => 401)
            );
        }
        
        // Check rate limiting
        $user_id = get_current_user_id();
        $rate_limit_check = $this->rate_limiter->check_limit($user_id, $request->get_route());
        
        if (is_wp_error($rate_limit_check)) {
            return $rate_limit_check;
        }
        
        // Check nonce for security
        $nonce = $request->get_header('X-WP-Nonce');
        if (!$nonce || !wp_verify_nonce($nonce, 'wp_rest')) {
            return new WP_Error(
                'spiralengine_rest_invalid_nonce',
                __('Invalid security token.', 'spiralengine'),
                array('status' => 403)
            );
        }
        
        return true;
    }
    
    /**
     * Get items permissions check
     * 
     * @param WP_REST_Request $request Full data about the request
     * @return bool|WP_Error
     */
    public function get_items_permissions_check($request) {
        return $this->permissions_check($request);
    }
    
    /**
     * Get item permissions check
     * 
     * @param WP_REST_Request $request Full data about the request
     * @return bool|WP_Error
     */
    public function get_item_permissions_check($request) {
        return $this->permissions_check($request);
    }
    
    /**
     * Create item permissions check
     * 
     * @param WP_REST_Request $request Full data about the request
     * @return bool|WP_Error
     */
    public function create_item_permissions_check($request) {
        return $this->permissions_check($request);
    }
    
    /**
     * Update item permissions check
     * 
     * @param WP_REST_Request $request Full data about the request
     * @return bool|WP_Error
     */
    public function update_item_permissions_check($request) {
        return $this->permissions_check($request);
    }
    
    /**
     * Delete item permissions check
     * 
     * @param WP_REST_Request $request Full data about the request
     * @return bool|WP_Error
     */
    public function delete_item_permissions_check($request) {
        return $this->permissions_check($request);
    }
    
    /**
     * Format response data with consistent structure
     * 
     * @param mixed $data Response data
     * @param int $total Total items if paginated
     * @param array $meta Additional metadata
     * @return array
     */
    protected function format_response($data, $total = null, $meta = array()) {
        $response = array(
            'success' => true,
            'data' => $data,
            'timestamp' => current_time('c', true), // ISO 8601 in UTC
            'timezone' => 'UTC'
        );
        
        if ($total !== null) {
            $response['total'] = $total;
        }
        
        if (!empty($meta)) {
            $response['meta'] = $meta;
        }
        
        return $response;
    }
    
    /**
     * Format error response
     * 
     * @param string $code Error code
     * @param string $message Error message
     * @param int $status HTTP status code
     * @return WP_Error
     */
    protected function format_error($code, $message, $status = 400) {
        return new WP_Error(
            'spiralengine_' . $code,
            $message,
            array('status' => $status)
        );
    }
    
    /**
     * Sanitize and validate pagination parameters
     * 
     * @param WP_REST_Request $request Request object
     * @return array
     */
    protected function get_pagination_params($request) {
        return array(
            'page' => absint($request->get_param('page')) ?: 1,
            'per_page' => absint($request->get_param('per_page')) ?: 10,
            'offset' => absint($request->get_param('offset')) ?: 0,
            'orderby' => sanitize_text_field($request->get_param('orderby')) ?: 'date',
            'order' => strtoupper(sanitize_text_field($request->get_param('order'))) ?: 'DESC'
        );
    }
    
    /**
     * Convert UTC timestamp to user's local time
     * 
     * @param string $utc_time UTC timestamp
     * @param int $user_id User ID
     * @return string Formatted time in user's timezone
     */
    protected function format_time_for_user($utc_time, $user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        return $this->time_manager->format_user_time($utc_time, $user_id, true);
    }
    
    /**
     * Add rate limit headers to response
     * 
     * @param WP_REST_Response $response Response object
     * @param int $user_id User ID
     * @param string $endpoint Endpoint
     * @return WP_REST_Response
     */
    protected function add_rate_limit_headers($response, $user_id, $endpoint) {
        $limit_info = $this->rate_limiter->get_limit_info($user_id, $endpoint);
        
        $response->header('X-RateLimit-Limit', $limit_info['limit']);
        $response->header('X-RateLimit-Remaining', $limit_info['remaining']);
        $response->header('X-RateLimit-Reset', $limit_info['reset']);
        
        return $response;
    }
    
    /**
     * Validate required fields
     * 
     * @param array $data Data to validate
     * @param array $required Required fields
     * @return true|WP_Error
     */
    protected function validate_required_fields($data, $required) {
        foreach ($required as $field) {
            if (empty($data[$field])) {
                return $this->format_error(
                    'missing_field',
                    sprintf(__('Field "%s" is required.', 'spiralengine'), $field),
                    400
                );
            }
        }
        
        return true;
    }
    
    /**
     * Get current user's membership tier
     * 
     * @return string
     */
    protected function get_user_tier() {
        $user_id = get_current_user_id();
        
        if (class_exists('MeprUser')) {
            $mepr_user = new MeprUser($user_id);
            $memberships = $mepr_user->active_product_subscriptions();
            
            // Map MemberPress memberships to tiers
            $tier_mapping = array(
                'voyager' => 5,
                'navigator' => 4,
                'pioneer' => 3,
                'explorer' => 2,
                'discovery' => 1
            );
            
            $highest_tier = 'discovery';
            $highest_level = 1;
            
            foreach ($memberships as $membership) {
                $membership_obj = new MeprProduct($membership);
                $slug = $membership_obj->post_name;
                
                foreach ($tier_mapping as $tier => $level) {
                    if (strpos($slug, $tier) !== false && $level > $highest_level) {
                        $highest_tier = $tier;
                        $highest_level = $level;
                        break;
                    }
                }
            }
            
            return $highest_tier;
        }
        
        return 'discovery'; // Default tier
    }
    
    /**
     * Check if user has access to feature based on tier
     * 
     * @param string $feature Feature name
     * @return bool
     */
    protected function user_can_access_feature($feature) {
        $tier = $this->get_user_tier();
        $access_control = new SpiralEngine_Access_Control();
        
        return $access_control->can_access_feature($tier, $feature);
    }
    
    /**
     * Log API request for analytics
     * 
     * @param string $endpoint Endpoint
     * @param string $method HTTP method
     * @param int $user_id User ID
     * @param array $params Request parameters
     */
    protected function log_api_request($endpoint, $method, $user_id, $params = array()) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'spiralengine_api_logs';
        
        $wpdb->insert(
            $table_name,
            array(
                'user_id' => $user_id,
                'endpoint' => $endpoint,
                'method' => $method,
                'params' => wp_json_encode($params),
                'ip_address' => $_SERVER['REMOTE_ADDR'],
                'user_agent' => $_SERVER['HTTP_USER_AGENT'],
                'timestamp' => current_time('mysql', true)
            ),
            array('%d', '%s', '%s', '%s', '%s', '%s', '%s')
        );
    }
    
    /**
     * Apply field filtering based on user's tier
     * 
     * @param array $data Data to filter
     * @param string $context Context (e.g., 'episode', 'assessment')
     * @return array
     */
    protected function apply_tier_filtering($data, $context) {
        $tier = $this->get_user_tier();
        
        // Define fields available per tier
        $tier_fields = array(
            'discovery' => array('basic_fields'),
            'explorer' => array('basic_fields', 'trend_fields'),
            'pioneer' => array('basic_fields', 'trend_fields', 'pattern_fields'),
            'navigator' => array('basic_fields', 'trend_fields', 'pattern_fields', 'ai_insights'),
            'voyager' => array('all_fields')
        );
        
        // Apply filtering based on context and tier
        if ($tier !== 'voyager') {
            // Remove restricted fields based on tier
            switch ($context) {
                case 'episode':
                    if (!in_array('pattern_fields', $tier_fields[$tier])) {
                        unset($data['patterns']);
                        unset($data['correlations']);
                    }
                    if (!in_array('ai_insights', $tier_fields[$tier])) {
                        unset($data['ai_analysis']);
                        unset($data['predictions']);
                    }
                    break;
                    
                case 'assessment':
                    if (!in_array('trend_fields', $tier_fields[$tier])) {
                        unset($data['trends']);
                        unset($data['historical_comparison']);
                    }
                    break;
            }
        }
        
        return $data;
    }
    
    /**
     * Cache response data
     * 
     * @param string $cache_key Cache key
     * @param mixed $data Data to cache
     * @param int $expiration Expiration time in seconds
     */
    protected function cache_response($cache_key, $data, $expiration = 300) {
        set_transient('spiralengine_api_' . $cache_key, $data, $expiration);
    }
    
    /**
     * Get cached response data
     * 
     * @param string $cache_key Cache key
     * @return mixed|false Cached data or false if not found
     */
    protected function get_cached_response($cache_key) {
        return get_transient('spiralengine_api_' . $cache_key);
    }
}

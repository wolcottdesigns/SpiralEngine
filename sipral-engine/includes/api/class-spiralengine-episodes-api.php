<?php
/**
 * Episodes REST API - Provides API endpoints for episode operations
 * 
 * @package    SpiralEngine
 * @subpackage Episodes/API
 * @file       includes/api/class-spiralengine-episodes-api.php
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Episodes API Class
 * 
 * This class implements REST API endpoints for the episode framework,
 * providing CRUD operations, correlation queries, pattern analysis,
 * forecasting, and rate limiting.
 */
class SPIRAL_Episodes_API {
    
    /**
     * API namespace
     * @var string
     */
    private $namespace = 'spiral/v1';
    
    /**
     * Rate limiting configuration
     * @var array
     */
    private $rate_limits = array(
        'default' => array(
            'requests' => 100,
            'window' => HOUR_IN_SECONDS
        ),
        'basic' => array(
            'requests' => 200,
            'window' => HOUR_IN_SECONDS
        ),
        'premium' => array(
            'requests' => 500,
            'window' => HOUR_IN_SECONDS
        ),
        'platinum' => array(
            'requests' => 1000,
            'window' => HOUR_IN_SECONDS
        )
    );
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('rest_api_init', array($this, 'register_routes'));
    }
    
    /**
     * Register API routes
     */
    public function register_routes() {
        // Episode CRUD routes
        register_rest_route($this->namespace, '/episodes', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'get_episodes'),
                'permission_callback' => array($this, 'check_permissions'),
                'args' => $this->get_episodes_args()
            ),
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array($this, 'create_episode'),
                'permission_callback' => array($this, 'check_permissions'),
                'args' => $this->create_episode_args()
            )
        ));
        
        register_rest_route($this->namespace, '/episodes/(?P<id>\d+)', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'get_episode'),
                'permission_callback' => array($this, 'check_permissions'),
                'args' => array(
                    'id' => array(
                        'validate_callback' => function($param) {
                            return is_numeric($param);
                        }
                    )
                )
            ),
            array(
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => array($this, 'update_episode'),
                'permission_callback' => array($this, 'check_permissions'),
                'args' => $this->update_episode_args()
            ),
            array(
                'methods' => WP_REST_Server::DELETABLE,
                'callback' => array($this, 'delete_episode'),
                'permission_callback' => array($this, 'check_permissions')
            )
        ));
        
        // Quick log route
        register_rest_route($this->namespace, '/episodes/quick-log', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array($this, 'quick_log_episode'),
            'permission_callback' => array($this, 'check_permissions'),
            'args' => $this->quick_log_args()
        ));
        
        // Correlation routes
        register_rest_route($this->namespace, '/correlations', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'get_correlations'),
            'permission_callback' => array($this, 'check_permissions'),
            'args' => array(
                'limit' => array(
                    'default' => 10,
                    'validate_callback' => function($param) {
                        return is_numeric($param) && $param > 0 && $param <= 50;
                    }
                )
            )
        ));
        
        register_rest_route($this->namespace, '/correlations/insights', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'get_correlation_insights'),
            'permission_callback' => array($this, 'check_permissions')
        ));
        
        // Pattern routes
        register_rest_route($this->namespace, '/patterns', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'get_patterns'),
            'permission_callback' => array($this, 'check_permissions'),
            'args' => array(
                'type' => array(
                    'validate_callback' => function($param) {
                        return in_array($param, array('temporal', 'trigger', 'severity', 'biological', 'environmental', 'cascade'));
                    }
                ),
                'episode_type' => array(
                    'validate_callback' => function($param) {
                        $registry = SPIRAL_Episode_Registry::get_instance();
                        return $registry->is_registered($param);
                    }
                )
            )
        ));
        
        register_rest_route($this->namespace, '/patterns/analyze', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array($this, 'analyze_patterns'),
            'permission_callback' => array($this, 'check_permissions'),
            'args' => array(
                'episode_type' => array(
                    'required' => true,
                    'validate_callback' => function($param) {
                        $registry = SPIRAL_Episode_Registry::get_instance();
                        return $registry->is_registered($param);
                    }
                ),
                'force' => array(
                    'default' => false,
                    'validate_callback' => function($param) {
                        return is_bool($param);
                    }
                )
            )
        ));
        
        // Forecast routes
        register_rest_route($this->namespace, '/forecast', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'get_forecast'),
            'permission_callback' => array($this, 'check_permissions'),
            'args' => array(
                'window' => array(
                    'default' => '24_hour',
                    'validate_callback' => function($param) {
                        return in_array($param, array('24_hour', '3_day', '7_day', '30_day'));
                    }
                )
            )
        ));
        
        register_rest_route($this->namespace, '/forecast/history', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'get_forecast_history'),
            'permission_callback' => array($this, 'check_premium_permissions'),
            'args' => array(
                'days' => array(
                    'default' => 7,
                    'validate_callback' => function($param) {
                        return is_numeric($param) && $param > 0 && $param <= 90;
                    }
                )
            )
        ));
        
        // Statistics routes
        register_rest_route($this->namespace, '/statistics', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'get_statistics'),
            'permission_callback' => array($this, 'check_permissions'),
            'args' => array(
                'period' => array(
                    'default' => '30_days',
                    'validate_callback' => function($param) {
                        return in_array($param, array('7_days', '30_days', '90_days', 'all_time'));
                    }
                )
            )
        ));
        
        // Episode types route
        register_rest_route($this->namespace, '/episode-types', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'get_episode_types'),
            'permission_callback' => array($this, 'check_permissions')
        ));
        
        // Batch operations
        register_rest_route($this->namespace, '/episodes/batch', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array($this, 'batch_create_episodes'),
            'permission_callback' => array($this, 'check_premium_permissions'),
            'args' => array(
                'episodes' => array(
                    'required' => true,
                    'validate_callback' => function($param) {
                        return is_array($param) && count($param) <= 50;
                    }
                )
            )
        ));
        
        // Export route
        register_rest_route($this->namespace, '/episodes/export', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'export_episodes'),
            'permission_callback' => array($this, 'check_permissions'),
            'args' => array(
                'format' => array(
                    'default' => 'json',
                    'validate_callback' => function($param) {
                        return in_array($param, array('json', 'csv'));
                    }
                ),
                'start_date' => array(
                    'validate_callback' => function($param) {
                        return strtotime($param) !== false;
                    }
                ),
                'end_date' => array(
                    'validate_callback' => function($param) {
                        return strtotime($param) !== false;
                    }
                )
            )
        ));
    }
    
    /**
     * Check permissions for API access
     * 
     * @param WP_REST_Request $request Request object
     * @return bool|WP_Error
     */
    public function check_permissions($request) {
        if (!is_user_logged_in()) {
            return new WP_Error('rest_forbidden', __('You must be logged in to access this endpoint.', 'spiral'), array('status' => 401));
        }
        
        // Check rate limiting
        $rate_limit_check = $this->check_rate_limit($request);
        if (is_wp_error($rate_limit_check)) {
            return $rate_limit_check;
        }
        
        return true;
    }
    
    /**
     * Check premium permissions
     * 
     * @param WP_REST_Request $request Request object
     * @return bool|WP_Error
     */
    public function check_premium_permissions($request) {
        $basic_check = $this->check_permissions($request);
        if (is_wp_error($basic_check)) {
            return $basic_check;
        }
        
        $user_id = get_current_user_id();
        $membership = $this->get_user_membership($user_id);
        
        if (!in_array($membership, array('premium', 'platinum'))) {
            return new WP_Error('rest_forbidden', __('This endpoint requires premium membership.', 'spiral'), array('status' => 403));
        }
        
        return true;
    }
    
    /**
     * Check rate limit
     * 
     * @param WP_REST_Request $request Request object
     * @return bool|WP_Error
     */
    private function check_rate_limit($request) {
        $user_id = get_current_user_id();
        $membership = $this->get_user_membership($user_id);
        
        $limit_config = $this->rate_limits[$membership] ?? $this->rate_limits['default'];
        $rate_key = 'spiral_api_rate_' . $user_id;
        
        $current_count = get_transient($rate_key);
        
        if ($current_count === false) {
            set_transient($rate_key, 1, $limit_config['window']);
            return true;
        }
        
        if ($current_count >= $limit_config['requests']) {
            return new WP_Error(
                'rate_limit_exceeded',
                sprintf(
                    __('Rate limit exceeded. Maximum %d requests per hour.', 'spiral'),
                    $limit_config['requests']
                ),
                array(
                    'status' => 429,
                    'retry_after' => $limit_config['window']
                )
            );
        }
        
        set_transient($rate_key, $current_count + 1, $limit_config['window']);
        return true;
    }
    
    /**
     * Get episodes endpoint
     * 
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_Error
     */
    public function get_episodes($request) {
        $user_id = get_current_user_id();
        $params = $request->get_params();
        
        global $wpdb;
        
        // Build query
        $query = "SELECT e.*, 
                    CASE 
                        WHEN e.trigger_details IS NOT NULL THEN 1
                        ELSE 0
                    END as has_details
                  FROM {$wpdb->prefix}spiralengine_episodes e
                  WHERE e.user_id = %d";
        
        $query_params = array($user_id);
        
        // Apply filters
        if (!empty($params['episode_type'])) {
            $query .= " AND e.episode_type = %s";
            $query_params[] = $params['episode_type'];
        }
        
        if (!empty($params['start_date'])) {
            $query .= " AND e.episode_date >= %s";
            $query_params[] = $params['start_date'];
        }
        
        if (!empty($params['end_date'])) {
            $query .= " AND e.episode_date <= %s";
            $query_params[] = $params['end_date'];
        }
        
        if (!empty($params['severity_min'])) {
            $query .= " AND e.severity_score >= %d";
            $query_params[] = $params['severity_min'];
        }
        
        // Order and pagination
        $query .= " ORDER BY e.episode_date DESC";
        
        $page = max(1, intval($params['page'] ?? 1));
        $per_page = min(100, max(1, intval($params['per_page'] ?? 10)));
        $offset = ($page - 1) * $per_page;
        
        $query .= " LIMIT %d OFFSET %d";
        $query_params[] = $per_page;
        $query_params[] = $offset;
        
        // Execute query
        $episodes = $wpdb->get_results($wpdb->prepare($query, $query_params), ARRAY_A);
        
        // Get total count for pagination
        $count_query = "SELECT COUNT(*) FROM {$wpdb->prefix}spiralengine_episodes WHERE user_id = %d";
        $count_params = array($user_id);
        
        if (!empty($params['episode_type'])) {
            $count_query .= " AND episode_type = %s";
            $count_params[] = $params['episode_type'];
        }
        
        $total = $wpdb->get_var($wpdb->prepare($count_query, $count_params));
        
        // Format episodes
        $formatted_episodes = array();
        foreach ($episodes as $episode) {
            $formatted_episodes[] = $this->format_episode($episode);
        }
        
        // Build response
        $response = new WP_REST_Response($formatted_episodes);
        $response->header('X-WP-Total', $total);
        $response->header('X-WP-TotalPages', ceil($total / $per_page));
        
        return $response;
    }
    
    /**
     * Get single episode
     * 
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_Error
     */
    public function get_episode($request) {
        $episode_id = $request->get_param('id');
        $user_id = get_current_user_id();
        
        global $wpdb;
        
        $episode = $wpdb->get_row($wpdb->prepare("
            SELECT * FROM {$wpdb->prefix}spiralengine_episodes
            WHERE episode_id = %d AND user_id = %d
        ", $episode_id, $user_id), ARRAY_A);
        
        if (!$episode) {
            return new WP_Error('episode_not_found', __('Episode not found.', 'spiral'), array('status' => 404));
        }
        
        // Get type-specific details
        $details = $this->get_episode_details($episode_id, $episode['episode_type']);
        if ($details) {
            $episode['details'] = $details;
        }
        
        return rest_ensure_response($this->format_episode($episode, true));
    }
    
    /**
     * Create episode
     * 
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_Error
     */
    public function create_episode($request) {
        $params = $request->get_params();
        $user_id = get_current_user_id();
        
        // Validate episode type
        $registry = SPIRAL_Episode_Registry::get_instance();
        if (!$registry->is_enabled($params['episode_type'])) {
            return new WP_Error('invalid_episode_type', __('Invalid or disabled episode type.', 'spiral'), array('status' => 400));
        }
        
        // Get episode widget class
        $type_config = $registry->get_episode_type($params['episode_type']);
        $widget_class = $type_config['widget_class'];
        
        if (!class_exists($widget_class)) {
            return new WP_Error('widget_not_found', __('Episode widget not found.', 'spiral'), array('status' => 500));
        }
        
        // Create episode using widget
        $widget = new $widget_class();
        
        // Prepare episode data
        $episode_data = array(
            'episode_date' => $params['episode_date'] ?? current_time('mysql'),
            'severity_score' => $params['severity_score'],
            'duration' => $params['duration'] ?? null,
            'trigger_category' => $params['trigger_category'] ?? null,
            'trigger_details' => $params['trigger_details'] ?? null,
            'location' => $params['location'] ?? null,
            'logged_via' => 'api'
        );
        
        // Add type-specific data
        if (isset($params['details']) && is_array($params['details'])) {
            $episode_data = array_merge($episode_data, $params['details']);
        }
        
        // Save episode
        $episode_id = $widget->save_episode($episode_data);
        
        if (!$episode_id) {
            return new WP_Error('save_failed', __('Failed to save episode.', 'spiral'), array('status' => 500));
        }
        
        // Get saved episode
        $saved_episode = $this->get_episode_by_id($episode_id);
        
        // Return response
        $response = rest_ensure_response($this->format_episode($saved_episode, true));
        $response->set_status(201);
        $response->header('Location', rest_url($this->namespace . '/episodes/' . $episode_id));
        
        return $response;
    }
    
    /**
     * Update episode
     * 
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_Error
     */
    public function update_episode($request) {
        $episode_id = $request->get_param('id');
        $user_id = get_current_user_id();
        $params = $request->get_params();
        
        global $wpdb;
        
        // Check if episode exists and belongs to user
        $episode = $wpdb->get_row($wpdb->prepare("
            SELECT * FROM {$wpdb->prefix}spiralengine_episodes
            WHERE episode_id = %d AND user_id = %d
        ", $episode_id, $user_id), ARRAY_A);
        
        if (!$episode) {
            return new WP_Error('episode_not_found', __('Episode not found.', 'spiral'), array('status' => 404));
        }
        
        // Prepare update data
        $update_data = array();
        $update_format = array();
        
        $allowed_fields = array(
            'episode_date' => '%s',
            'severity_score' => '%s',
            'duration_minutes' => '%d',
            'trigger_category' => '%s',
            'trigger_details' => '%s',
            'location' => '%s'
        );
        
        foreach ($allowed_fields as $field => $format) {
            if (isset($params[$field])) {
                $update_data[$field] = $params[$field];
                $update_format[] = $format;
            }
        }
        
        if (empty($update_data)) {
            return new WP_Error('no_data', __('No data to update.', 'spiral'), array('status' => 400));
        }
        
        // Update episode
        $result = $wpdb->update(
            $wpdb->prefix . 'spiralengine_episodes',
            $update_data,
            array('episode_id' => $episode_id),
            $update_format,
            array('%d')
        );
        
        if ($result === false) {
            return new WP_Error('update_failed', __('Failed to update episode.', 'spiral'), array('status' => 500));
        }
        
        // Update type-specific details if provided
        if (isset($params['details']) && is_array($params['details'])) {
            $this->update_episode_details($episode_id, $episode['episode_type'], $params['details']);
        }
        
        // Get updated episode
        $updated_episode = $this->get_episode_by_id($episode_id);
        
        return rest_ensure_response($this->format_episode($updated_episode, true));
    }
    
    /**
     * Delete episode
     * 
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_Error
     */
    public function delete_episode($request) {
        $episode_id = $request->get_param('id');
        $user_id = get_current_user_id();
        
        global $wpdb;
        
        // Check if episode exists and belongs to user
        $episode = $wpdb->get_row($wpdb->prepare("
            SELECT * FROM {$wpdb->prefix}spiralengine_episodes
            WHERE episode_id = %d AND user_id = %d
        ", $episode_id, $user_id), ARRAY_A);
        
        if (!$episode) {
            return new WP_Error('episode_not_found', __('Episode not found.', 'spiral'), array('status' => 404));
        }
        
        // Delete episode
        $result = $wpdb->delete(
            $wpdb->prefix . 'spiralengine_episodes',
            array('episode_id' => $episode_id),
            array('%d')
        );
        
        if (!$result) {
            return new WP_Error('delete_failed', __('Failed to delete episode.', 'spiral'), array('status' => 500));
        }
        
        // Delete type-specific details
        $this->delete_episode_details($episode_id, $episode['episode_type']);
        
        // Delete related correlations
        $wpdb->delete(
            $wpdb->prefix . 'spiralengine_episode_correlations',
            array('primary_episode_id' => $episode_id),
            array('%d')
        );
        
        $wpdb->delete(
            $wpdb->prefix . 'spiralengine_episode_correlations',
            array('related_episode_id' => $episode_id),
            array('%d')
        );
        
        return rest_ensure_response(array(
            'deleted' => true,
            'episode_id' => $episode_id
        ));
    }
    
    /**
     * Quick log episode
     * 
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_Error
     */
    public function quick_log_episode($request) {
        $params = $request->get_params();
        
        // Quick log has minimal required fields
        if (empty($params['episode_type']) || empty($params['severity_score'])) {
            return new WP_Error('missing_fields', __('Episode type and severity are required.', 'spiral'), array('status' => 400));
        }
        
        // Add logged_via flag
        $params['logged_via'] = 'quick_api';
        
        // Use regular create method
        return $this->create_episode($request);
    }
    
    /**
     * Get correlations
     * 
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_Error
     */
    public function get_correlations($request) {
        $user_id = get_current_user_id();
        $limit = $request->get_param('limit');
        
        $correlation_engine = new SPIRAL_Correlation_Engine();
        $correlations = $correlation_engine->get_user_correlations($user_id, $limit);
        
        return rest_ensure_response($correlations);
    }
    
    /**
     * Get correlation insights
     * 
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_Error
     */
    public function get_correlation_insights($request) {
        $user_id = get_current_user_id();
        
        $correlation_engine = new SPIRAL_Correlation_Engine();
        $correlations = $correlation_engine->get_user_correlations($user_id, 20);
        
        // Format as actionable insights
        $insights = array(
            'summary' => $this->generate_correlation_summary($correlations),
            'correlations' => $correlations,
            'recommendations' => $this->generate_correlation_recommendations($correlations)
        );
        
        return rest_ensure_response($insights);
    }
    
    /**
     * Get patterns
     * 
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_Error
     */
    public function get_patterns($request) {
        $user_id = get_current_user_id();
        $pattern_type = $request->get_param('type');
        $episode_type = $request->get_param('episode_type');
        
        $pattern_detector = new SPIRAL_Pattern_Detector($episode_type);
        $patterns = $pattern_detector->get_user_patterns($user_id, $episode_type);
        
        if ($pattern_type) {
            $patterns = isset($patterns[$pattern_type]) ? array($pattern_type => $patterns[$pattern_type]) : array();
        }
        
        return rest_ensure_response($patterns);
    }
    
    /**
     * Analyze patterns
     * 
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_Error
     */
    public function analyze_patterns($request) {
        $user_id = get_current_user_id();
        $episode_type = $request->get_param('episode_type');
        $force = $request->get_param('force');
        
        // Check if analysis is already running
        $lock_key = 'spiral_pattern_analysis_' . $user_id . '_' . $episode_type;
        if (!$force && get_transient($lock_key)) {
            return new WP_Error('analysis_running', __('Pattern analysis is already running.', 'spiral'), array('status' => 409));
        }
        
        // Set lock
        set_transient($lock_key, true, 300); // 5 minutes
        
        // Schedule analysis
        wp_schedule_single_event(time() + 5, 'spiral_analyze_user_patterns', array($user_id, $episode_type));
        
        return rest_ensure_response(array(
            'status' => 'scheduled',
            'message' => __('Pattern analysis has been scheduled.', 'spiral')
        ));
    }
    
    /**
     * Get forecast
     * 
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_Error
     */
    public function get_forecast($request) {
        $user_id = get_current_user_id();
        $window = $request->get_param('window');
        
        $forecast_engine = new SPIRAL_Forecast_Engine();
        $forecast = $forecast_engine->get_forecast_api_data($user_id, $window);
        
        if (isset($forecast['error'])) {
            return new WP_Error($forecast['error'], $forecast['message'], array('status' => 403));
        }
        
        return rest_ensure_response($forecast);
    }
    
    /**
     * Get forecast history
     * 
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_Error
     */
    public function get_forecast_history($request) {
        $user_id = get_current_user_id();
        $days = $request->get_param('days');
        
        global $wpdb;
        
        $history = $wpdb->get_results($wpdb->prepare("
            SELECT forecast_window, risk_score, confidence_score, risk_level, generated_at, ai_enhanced
            FROM {$wpdb->prefix}spiralengine_forecast_logs
            WHERE user_id = %d
                AND generated_at >= DATE_SUB(NOW(), INTERVAL %d DAY)
            ORDER BY generated_at DESC
        ", $user_id, $days), ARRAY_A);
        
        // Group by window
        $grouped = array();
        foreach ($history as $entry) {
            $grouped[$entry['forecast_window']][] = array(
                'risk_score' => floatval($entry['risk_score']),
                'confidence' => floatval($entry['confidence_score']),
                'risk_level' => $entry['risk_level'],
                'generated_at' => $entry['generated_at'],
                'ai_enhanced' => (bool)$entry['ai_enhanced']
            );
        }
        
        return rest_ensure_response($grouped);
    }
    
    /**
     * Get statistics
     * 
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_Error
     */
    public function get_statistics($request) {
        $user_id = get_current_user_id();
        $period = $request->get_param('period');
        
        global $wpdb;
        
        // Calculate date range
        $date_condition = "";
        switch ($period) {
            case '7_days':
                $date_condition = "AND episode_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
                break;
            case '30_days':
                $date_condition = "AND episode_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
                break;
            case '90_days':
                $date_condition = "AND episode_date >= DATE_SUB(NOW(), INTERVAL 90 DAY)";
                break;
        }
        
        // Get episode statistics
        $stats = $wpdb->get_results($wpdb->prepare("
            SELECT 
                episode_type,
                COUNT(*) as count,
                AVG(severity_score) as avg_severity,
                MAX(severity_score) as max_severity,
                MIN(severity_score) as min_severity,
                AVG(duration_minutes) as avg_duration
            FROM {$wpdb->prefix}spiralengine_episodes
            WHERE user_id = %d $date_condition
            GROUP BY episode_type
        ", $user_id), ARRAY_A);
        
        // Get overall statistics
        $overall = $wpdb->get_row($wpdb->prepare("
            SELECT 
                COUNT(*) as total_episodes,
                AVG(severity_score) as avg_severity,
                COUNT(DISTINCT DATE(episode_date)) as active_days
            FROM {$wpdb->prefix}spiralengine_episodes
            WHERE user_id = %d $date_condition
        ", $user_id), ARRAY_A);
        
        // Get trend data
        $trend = $wpdb->get_results($wpdb->prepare("
            SELECT 
                DATE(episode_date) as date,
                COUNT(*) as count,
                AVG(severity_score) as avg_severity
            FROM {$wpdb->prefix}spiralengine_episodes
            WHERE user_id = %d $date_condition
            GROUP BY DATE(episode_date)
            ORDER BY date ASC
        ", $user_id), ARRAY_A);
        
        $registry = SPIRAL_Episode_Registry::get_instance();
        
        // Format statistics
        $formatted_stats = array(
            'period' => $period,
            'overall' => array(
                'total_episodes' => intval($overall['total_episodes']),
                'average_severity' => round(floatval($overall['avg_severity']), 1),
                'active_days' => intval($overall['active_days'])
            ),
            'by_type' => array(),
            'trend' => array_map(function($day) {
                return array(
                    'date' => $day['date'],
                    'episodes' => intval($day['count']),
                    'severity' => round(floatval($day['avg_severity']), 1)
                );
            }, $trend)
        );
        
        foreach ($stats as $stat) {
            $type_info = $registry->get_episode_type($stat['episode_type']);
            $formatted_stats['by_type'][] = array(
                'type' => $stat['episode_type'],
                'name' => $type_info['display_name'] ?? $stat['episode_type'],
                'color' => $type_info['color'] ?? '#999999',
                'count' => intval($stat['count']),
                'average_severity' => round(floatval($stat['avg_severity']), 1),
                'max_severity' => intval($stat['max_severity']),
                'min_severity' => intval($stat['min_severity']),
                'average_duration' => round(floatval($stat['avg_duration']), 0)
            );
        }
        
        return rest_ensure_response($formatted_stats);
    }
    
    /**
     * Get episode types
     * 
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_Error
     */
    public function get_episode_types($request) {
        $registry = SPIRAL_Episode_Registry::get_instance();
        $types = $registry->get_episode_types(true); // Only enabled types
        
        $formatted_types = array();
        foreach ($types as $key => $type) {
            $formatted_types[] = array(
                'key' => $key,
                'name' => $type['display_name'],
                'description' => $type['description'],
                'color' => $type['color'],
                'icon' => $type['icon'],
                'quick_log_fields' => $type['quick_log_fields'],
                'severity_labels' => $type['severity_labels']
            );
        }
        
        return rest_ensure_response($formatted_types);
    }
    
    /**
     * Batch create episodes
     * 
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_Error
     */
    public function batch_create_episodes($request) {
        $episodes = $request->get_param('episodes');
        $results = array(
            'created' => array(),
            'failed' => array()
        );
        
        foreach ($episodes as $index => $episode_data) {
            // Create request for each episode
            $episode_request = new WP_REST_Request('POST', $this->namespace . '/episodes');
            $episode_request->set_body_params($episode_data);
            
            $response = $this->create_episode($episode_request);
            
            if (is_wp_error($response)) {
                $results['failed'][] = array(
                    'index' => $index,
                    'error' => $response->get_error_message()
                );
            } else {
                $results['created'][] = $response->get_data();
            }
        }
        
        return rest_ensure_response($results);
    }
    
    /**
     * Export episodes
     * 
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_Error
     */
    public function export_episodes($request) {
        $user_id = get_current_user_id();
        $format = $request->get_param('format');
        $start_date = $request->get_param('start_date');
        $end_date = $request->get_param('end_date');
        
        global $wpdb;
        
        // Build query
        $query = "SELECT * FROM {$wpdb->prefix}spiralengine_episodes WHERE user_id = %d";
        $params = array($user_id);
        
        if ($start_date) {
            $query .= " AND episode_date >= %s";
            $params[] = $start_date;
        }
        
        if ($end_date) {
            $query .= " AND episode_date <= %s";
            $params[] = $end_date;
        }
        
        $query .= " ORDER BY episode_date DESC";
        
        $episodes = $wpdb->get_results($wpdb->prepare($query, $params), ARRAY_A);
        
        // Format episodes
        $formatted_episodes = array();
        foreach ($episodes as $episode) {
            // Decrypt sensitive data for export
            if (!empty($episode['trigger_details'])) {
                $episode['trigger_details'] = $this->decrypt_sensitive_data($episode['trigger_details']);
            }
            $formatted_episodes[] = $this->format_episode($episode, false);
        }
        
        if ($format === 'csv') {
            return $this->export_as_csv($formatted_episodes);
        }
        
        return rest_ensure_response($formatted_episodes);
    }
    
    /**
     * Helper: Format episode for API response
     * 
     * @param array $episode Raw episode data
     * @param bool $include_details Include sensitive details
     * @return array Formatted episode
     */
    private function format_episode($episode, $include_details = false) {
        $formatted = array(
            'id' => intval($episode['episode_id']),
            'type' => $episode['episode_type'],
            'date' => $episode['episode_date'],
            'severity' => floatval($episode['severity_score']),
            'duration' => intval($episode['duration_minutes']),
            'trigger_category' => $episode['trigger_category'],
            'location' => $episode['location'],
            'has_biological_factors' => (bool)$episode['has_biological_factors'],
            'has_coping_data' => (bool)$episode['has_coping_data'],
            'logged_via' => $episode['logged_via']
        );
        
        if ($include_details && !empty($episode['trigger_details'])) {
            $formatted['trigger_details'] = $this->decrypt_sensitive_data($episode['trigger_details']);
        }
        
        if (isset($episode['details'])) {
            $formatted['details'] = $episode['details'];
        }
        
        if (!empty($episode['metadata_json'])) {
            $metadata = json_decode($episode['metadata_json'], true);
            if (isset($metadata['coping_strategies'])) {
                $formatted['coping_strategies'] = $metadata['coping_strategies'];
            }
        }
        
        return $formatted;
    }
    
    /**
     * Helper: Get episode by ID
     * 
     * @param int $episode_id Episode ID
     * @return array|null Episode data
     */
    private function get_episode_by_id($episode_id) {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare("
            SELECT * FROM {$wpdb->prefix}spiralengine_episodes
            WHERE episode_id = %d
        ", $episode_id), ARRAY_A);
    }
    
    /**
     * Helper: Get episode details
     * 
     * @param int $episode_id Episode ID
     * @param string $episode_type Episode type
     * @return array|null Details
     */
    private function get_episode_details($episode_id, $episode_type) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'spiralengine_' . $episode_type . '_details';
        
        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            return null;
        }
        
        return $wpdb->get_row($wpdb->prepare("
            SELECT * FROM $table_name WHERE episode_id = %d
        ", $episode_id), ARRAY_A);
    }
    
    /**
     * Helper: Update episode details
     * 
     * @param int $episode_id Episode ID
     * @param string $episode_type Episode type
     * @param array $details Detail data
     */
    private function update_episode_details($episode_id, $episode_type, $details) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'spiralengine_' . $episode_type . '_details';
        
        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            return;
        }
        
        // Update or insert
        $existing = $wpdb->get_var($wpdb->prepare("
            SELECT episode_id FROM $table_name WHERE episode_id = %d
        ", $episode_id));
        
        if ($existing) {
            $wpdb->update($table_name, $details, array('episode_id' => $episode_id));
        } else {
            $details['episode_id'] = $episode_id;
            $wpdb->insert($table_name, $details);
        }
    }
    
    /**
     * Helper: Delete episode details
     * 
     * @param int $episode_id Episode ID
     * @param string $episode_type Episode type
     */
    private function delete_episode_details($episode_id, $episode_type) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'spiralengine_' . $episode_type . '_details';
        
        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            return;
        }
        
        $wpdb->delete($table_name, array('episode_id' => $episode_id), array('%d'));
    }
    
    /**
     * Helper: Export as CSV
     * 
     * @param array $episodes Episodes to export
     * @return WP_REST_Response
     */
    private function export_as_csv($episodes) {
        $csv_data = array();
        
        // Headers
        $headers = array(
            'Date',
            'Type',
            'Severity',
            'Duration (minutes)',
            'Trigger Category',
            'Location',
            'Has Biological Factors',
            'Has Coping Data'
        );
        $csv_data[] = $headers;
        
        // Data rows
        foreach ($episodes as $episode) {
            $csv_data[] = array(
                $episode['date'],
                $episode['type'],
                $episode['severity'],
                $episode['duration'] ?? '',
                $episode['trigger_category'] ?? '',
                $episode['location'] ?? '',
                $episode['has_biological_factors'] ? 'Yes' : 'No',
                $episode['has_coping_data'] ? 'Yes' : 'No'
            );
        }
        
        // Convert to CSV string
        $output = fopen('php://temp', 'r+');
        foreach ($csv_data as $row) {
            fputcsv($output, $row);
        }
        rewind($output);
        $csv_string = stream_get_contents($output);
        fclose($output);
        
        $response = new WP_REST_Response($csv_string);
        $response->header('Content-Type', 'text/csv');
        $response->header('Content-Disposition', 'attachment; filename="episodes_export.csv"');
        
        return $response;
    }
    
    /**
     * Helper: Get user membership
     * 
     * @param int $user_id User ID
     * @return string Membership level
     */
    private function get_user_membership($user_id) {
        if (class_exists('MeprUser')) {
            $mepr_user = new MeprUser($user_id);
            $active_memberships = $mepr_user->active_product_subscriptions();
            
            if (!empty($active_memberships)) {
                // Get highest level membership
                $levels = array('platinum' => 3, 'premium' => 2, 'basic' => 1);
                $highest_level = 'basic';
                $highest_score = 0;
                
                foreach ($active_memberships as $membership_id) {
                    $membership = new MeprProduct($membership_id);
                    $level = strtolower($membership->post_title);
                    
                    foreach ($levels as $level_key => $score) {
                        if (strpos($level, $level_key) !== false && $score > $highest_score) {
                            $highest_level = $level_key;
                            $highest_score = $score;
                        }
                    }
                }
                
                return $highest_level;
            }
        }
        
        return 'default';
    }
    
    /**
     * Helper: Decrypt sensitive data
     * 
     * @param string $data Encrypted data
     * @return string Decrypted data
     */
    private function decrypt_sensitive_data($data) {
        if (empty($data)) {
            return $data;
        }
        
        $key = wp_salt('auth');
        $encrypted = base64_decode($data);
        $decrypted = openssl_decrypt($encrypted, 'AES-256-CBC', $key, 0, substr($key, 0, 16));
        
        return $decrypted;
    }
    
    /**
     * Helper: Generate correlation summary
     * 
     * @param array $correlations Correlation data
     * @return array Summary
     */
    private function generate_correlation_summary($correlations) {
        if (empty($correlations)) {
            return array(
                'total_correlations' => 0,
                'strongest_correlation' => null,
                'most_common_pattern' => null
            );
        }
        
        $strongest = array_reduce($correlations, function($carry, $item) {
            return (!$carry || $item['strength'] > $carry['strength']) ? $item : $carry;
        });
        
        return array(
            'total_correlations' => count($correlations),
            'strongest_correlation' => $strongest['title'] ?? null,
            'average_confidence' => array_sum(array_column($correlations, 'confidence')) / count($correlations)
        );
    }
    
    /**
     * Helper: Generate correlation recommendations
     * 
     * @param array $correlations Correlation data
     * @return array Recommendations
     */
    private function generate_correlation_recommendations($correlations) {
        $recommendations = array();
        
        foreach ($correlations as $correlation) {
            if (!empty($correlation['action_items'])) {
                foreach ($correlation['action_items'] as $action) {
                    $recommendations[] = array(
                        'priority' => $correlation['strength'] > 0.7 ? 'high' : 'medium',
                        'action' => $action,
                        'related_to' => $correlation['title']
                    );
                }
            }
        }
        
        // Sort by priority
        usort($recommendations, function($a, $b) {
            return ($a['priority'] === 'high' && $b['priority'] !== 'high') ? -1 : 1;
        });
        
        return array_slice($recommendations, 0, 5); // Top 5 recommendations
    }
    
    /**
     * Argument definitions
     */
    
    private function get_episodes_args() {
        return array(
            'episode_type' => array(
                'validate_callback' => function($param) {
                    if (empty($param)) return true;
                    $registry = SPIRAL_Episode_Registry::get_instance();
                    return $registry->is_registered($param);
                }
            ),
            'start_date' => array(
                'validate_callback' => function($param) {
                    return empty($param) || strtotime($param) !== false;
                }
            ),
            'end_date' => array(
                'validate_callback' => function($param) {
                    return empty($param) || strtotime($param) !== false;
                }
            ),
            'severity_min' => array(
                'validate_callback' => function($param) {
                    return empty($param) || (is_numeric($param) && $param >= 1 && $param <= 10);
                }
            ),
            'page' => array(
                'default' => 1,
                'validate_callback' => function($param) {
                    return is_numeric($param) && $param > 0;
                }
            ),
            'per_page' => array(
                'default' => 10,
                'validate_callback' => function($param) {
                    return is_numeric($param) && $param > 0 && $param <= 100;
                }
            )
        );
    }
    
    private function create_episode_args() {
        return array(
            'episode_type' => array(
                'required' => true,
                'validate_callback' => function($param) {
                    $registry = SPIRAL_Episode_Registry::get_instance();
                    return $registry->is_registered($param);
                }
            ),
            'severity_score' => array(
                'required' => true,
                'validate_callback' => function($param) {
                    return is_numeric($param) && $param >= 1 && $param <= 10;
                }
            ),
            'episode_date' => array(
                'validate_callback' => function($param) {
                    return empty($param) || strtotime($param) !== false;
                }
            ),
            'duration' => array(
                'validate_callback' => function($param) {
                    return empty($param) || (is_numeric($param) && $param > 0);
                }
            ),
            'trigger_category' => array(
                'sanitize_callback' => 'sanitize_text_field'
            ),
            'trigger_details' => array(
                'sanitize_callback' => 'sanitize_textarea_field'
            ),
            'location' => array(
                'sanitize_callback' => 'sanitize_text_field'
            ),
            'details' => array(
                'validate_callback' => function($param) {
                    return empty($param) || is_array($param);
                }
            )
        );
    }
    
    private function update_episode_args() {
        $args = $this->create_episode_args();
        
        // Make fields optional for update
        foreach ($args as $key => &$config) {
            unset($config['required']);
        }
        
        return $args;
    }
    
    private function quick_log_args() {
        return array(
            'episode_type' => array(
                'required' => true,
                'validate_callback' => function($param) {
                    $registry = SPIRAL_Episode_Registry::get_instance();
                    return $registry->is_registered($param);
                }
            ),
            'severity_score' => array(
                'required' => true,
                'validate_callback' => function($param) {
                    return is_numeric($param) && $param >= 1 && $param <= 10;
                }
            ),
            'quick_note' => array(
                'sanitize_callback' => 'sanitize_textarea_field'
            )
        );
    }
}

// Initialize API
new SPIRAL_Episodes_API();

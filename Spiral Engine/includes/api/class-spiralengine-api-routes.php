<?php
/**
 * SpiralEngine REST API Routes
 * 
 * @package    SpiralEngine
 * @subpackage API
 * @since      1.0.0
 */

// includes/api/class-spiralengine-api-routes.php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * REST API Routes Registration
 * 
 * Registers all REST API endpoints for the SpiralEngine plugin
 */
class SpiralEngine_API_Routes {
    
    /**
     * Instance of this class
     * 
     * @var SpiralEngine_API_Routes
     */
    private static $instance = null;
    
    /**
     * API namespace
     * 
     * @var string
     */
    private $namespace = 'spiralengine/v1';
    
    /**
     * Controller instances
     * 
     * @var array
     */
    private $controllers = array();
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->load_controllers();
        add_action('rest_api_init', array($this, 'register_routes'));
    }
    
    /**
     * Get instance
     * 
     * @return SpiralEngine_API_Routes
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Load controller classes
     */
    private function load_controllers() {
        $this->controllers = array(
            'assessments' => new SpiralEngine_Assessments_Controller(),
            'episodes' => new SpiralEngine_Episodes_Controller(),
            'insights' => new SpiralEngine_Insights_Controller(),
            'patterns' => new SpiralEngine_Patterns_Controller(),
            'users' => new SpiralEngine_Users_Controller(),
            'widgets' => new SpiralEngine_Widgets_Controller(),
            'analytics' => new SpiralEngine_Analytics_Controller(),
            'webhooks' => new SpiralEngine_Webhooks_Controller()
        );
    }
    
    /**
     * Register REST API routes
     */
    public function register_routes() {
        // Assessment endpoints
        $this->register_assessment_routes();
        
        // Episode endpoints
        $this->register_episode_routes();
        
        // Insight endpoints
        $this->register_insight_routes();
        
        // Pattern endpoints
        $this->register_pattern_routes();
        
        // User endpoints
        $this->register_user_routes();
        
        // Widget endpoints
        $this->register_widget_routes();
        
        // Analytics endpoints
        $this->register_analytics_routes();
        
        // Webhook endpoints
        $this->register_webhook_routes();
        
        // System endpoints
        $this->register_system_routes();
    }
    
    /**
     * Register assessment routes
     */
    private function register_assessment_routes() {
        // Get all assessments
        register_rest_route($this->namespace, '/assessments', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this->controllers['assessments'], 'get_items'),
                'permission_callback' => array($this->controllers['assessments'], 'get_items_permissions_check'),
                'args' => $this->get_collection_params()
            ),
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array($this->controllers['assessments'], 'create_item'),
                'permission_callback' => array($this->controllers['assessments'], 'create_item_permissions_check'),
                'args' => $this->get_assessment_create_params()
            )
        ));
        
        // Single assessment
        register_rest_route($this->namespace, '/assessments/(?P<id>\d+)', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this->controllers['assessments'], 'get_item'),
                'permission_callback' => array($this->controllers['assessments'], 'get_item_permissions_check'),
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
                'callback' => array($this->controllers['assessments'], 'update_item'),
                'permission_callback' => array($this->controllers['assessments'], 'update_item_permissions_check'),
                'args' => $this->get_assessment_update_params()
            ),
            array(
                'methods' => WP_REST_Server::DELETABLE,
                'callback' => array($this->controllers['assessments'], 'delete_item'),
                'permission_callback' => array($this->controllers['assessments'], 'delete_item_permissions_check')
            )
        ));
        
        // Assessment history
        register_rest_route($this->namespace, '/assessments/history', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this->controllers['assessments'], 'get_history'),
            'permission_callback' => array($this->controllers['assessments'], 'get_items_permissions_check'),
            'args' => array(
                'user_id' => array(
                    'type' => 'integer',
                    'required' => false,
                    'default' => 0
                ),
                'limit' => array(
                    'type' => 'integer',
                    'default' => 10,
                    'minimum' => 1,
                    'maximum' => 100
                )
            )
        ));
        
        // Current assessment
        register_rest_route($this->namespace, '/assessments/current', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this->controllers['assessments'], 'get_current'),
            'permission_callback' => array($this->controllers['assessments'], 'get_item_permissions_check')
        ));
    }
    
    /**
     * Register episode routes
     */
    private function register_episode_routes() {
        // Get all episodes
        register_rest_route($this->namespace, '/episodes', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this->controllers['episodes'], 'get_items'),
                'permission_callback' => array($this->controllers['episodes'], 'get_items_permissions_check'),
                'args' => array_merge(
                    $this->get_collection_params(),
                    array(
                        'type' => array(
                            'type' => 'string',
                            'enum' => array('overthinking', 'anxiety', 'depression', 'ptsd', 'caregiver', 'panic', 'dissociation'),
                            'required' => false
                        ),
                        'start_date' => array(
                            'type' => 'string',
                            'format' => 'date-time',
                            'required' => false
                        ),
                        'end_date' => array(
                            'type' => 'string',
                            'format' => 'date-time',
                            'required' => false
                        ),
                        'severity_min' => array(
                            'type' => 'integer',
                            'minimum' => 1,
                            'maximum' => 10,
                            'required' => false
                        ),
                        'severity_max' => array(
                            'type' => 'integer',
                            'minimum' => 1,
                            'maximum' => 10,
                            'required' => false
                        )
                    )
                )
            ),
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array($this->controllers['episodes'], 'create_item'),
                'permission_callback' => array($this->controllers['episodes'], 'create_item_permissions_check'),
                'args' => $this->get_episode_create_params()
            )
        ));
        
        // Single episode
        register_rest_route($this->namespace, '/episodes/(?P<id>\d+)', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this->controllers['episodes'], 'get_item'),
                'permission_callback' => array($this->controllers['episodes'], 'get_item_permissions_check')
            ),
            array(
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => array($this->controllers['episodes'], 'update_item'),
                'permission_callback' => array($this->controllers['episodes'], 'update_item_permissions_check'),
                'args' => $this->get_episode_update_params()
            ),
            array(
                'methods' => WP_REST_Server::DELETABLE,
                'callback' => array($this->controllers['episodes'], 'delete_item'),
                'permission_callback' => array($this->controllers['episodes'], 'delete_item_permissions_check')
            )
        ));
        
        // Episode correlations
        register_rest_route($this->namespace, '/episodes/(?P<id>\d+)/correlations', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this->controllers['episodes'], 'get_correlations'),
            'permission_callback' => array($this->controllers['episodes'], 'get_item_permissions_check')
        ));
        
        // Episode patterns
        register_rest_route($this->namespace, '/episodes/(?P<id>\d+)/patterns', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this->controllers['episodes'], 'get_patterns'),
            'permission_callback' => array($this->controllers['episodes'], 'get_item_permissions_check')
        ));
        
        // Quick log endpoint
        register_rest_route($this->namespace, '/episodes/quick-log', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array($this->controllers['episodes'], 'quick_log'),
            'permission_callback' => array($this->controllers['episodes'], 'create_item_permissions_check'),
            'args' => array(
                'type' => array(
                    'type' => 'string',
                    'required' => true,
                    'enum' => array('overthinking', 'anxiety', 'depression', 'ptsd', 'caregiver', 'panic', 'dissociation')
                ),
                'severity' => array(
                    'type' => 'integer',
                    'required' => true,
                    'minimum' => 1,
                    'maximum' => 10
                ),
                'trigger' => array(
                    'type' => 'string',
                    'required' => false
                )
            )
        ));
    }
    
    /**
     * Register insight routes
     */
    private function register_insight_routes() {
        // Get insights
        register_rest_route($this->namespace, '/insights', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this->controllers['insights'], 'get_items'),
            'permission_callback' => array($this->controllers['insights'], 'get_items_permissions_check'),
            'args' => array_merge(
                $this->get_collection_params(),
                array(
                    'type' => array(
                        'type' => 'string',
                        'enum' => array('pattern', 'correlation', 'prediction', 'recommendation'),
                        'required' => false
                    ),
                    'priority' => array(
                        'type' => 'string',
                        'enum' => array('high', 'medium', 'low'),
                        'required' => false
                    ),
                    'unread_only' => array(
                        'type' => 'boolean',
                        'default' => false
                    )
                )
            )
        ));
        
        // Single insight
        register_rest_route($this->namespace, '/insights/(?P<id>\d+)', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this->controllers['insights'], 'get_item'),
                'permission_callback' => array($this->controllers['insights'], 'get_item_permissions_check')
            ),
            array(
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => array($this->controllers['insights'], 'update_item'),
                'permission_callback' => array($this->controllers['insights'], 'update_item_permissions_check'),
                'args' => array(
                    'read' => array(
                        'type' => 'boolean',
                        'required' => false
                    ),
                    'dismissed' => array(
                        'type' => 'boolean',
                        'required' => false
                    )
                )
            )
        ));
        
        // Generate insights
        register_rest_route($this->namespace, '/insights/generate', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array($this->controllers['insights'], 'generate'),
            'permission_callback' => array($this->controllers['insights'], 'create_item_permissions_check'),
            'args' => array(
                'force' => array(
                    'type' => 'boolean',
                    'default' => false
                )
            )
        ));
    }
    
    /**
     * Register pattern routes
     */
    private function register_pattern_routes() {
        // Get patterns
        register_rest_route($this->namespace, '/patterns', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this->controllers['patterns'], 'get_items'),
            'permission_callback' => array($this->controllers['patterns'], 'get_items_permissions_check'),
            'args' => array_merge(
                $this->get_collection_params(),
                array(
                    'type' => array(
                        'type' => 'string',
                        'enum' => array('time', 'trigger', 'severity', 'biological'),
                        'required' => false
                    ),
                    'significance' => array(
                        'type' => 'number',
                        'minimum' => 0,
                        'maximum' => 1,
                        'required' => false
                    )
                )
            )
        ));
        
        // Single pattern
        register_rest_route($this->namespace, '/patterns/(?P<id>\d+)', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this->controllers['patterns'], 'get_item'),
            'permission_callback' => array($this->controllers['patterns'], 'get_item_permissions_check')
        ));
        
        // Detect patterns
        register_rest_route($this->namespace, '/patterns/detect', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array($this->controllers['patterns'], 'detect'),
            'permission_callback' => array($this->controllers['patterns'], 'create_item_permissions_check'),
            'args' => array(
                'episode_type' => array(
                    'type' => 'string',
                    'required' => false
                ),
                'date_range' => array(
                    'type' => 'integer',
                    'default' => 30,
                    'minimum' => 7,
                    'maximum' => 365
                )
            )
        ));
        
        // Pattern forecast
        register_rest_route($this->namespace, '/patterns/forecast', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this->controllers['patterns'], 'forecast'),
            'permission_callback' => array($this->controllers['patterns'], 'get_items_permissions_check'),
            'args' => array(
                'days' => array(
                    'type' => 'integer',
                    'default' => 7,
                    'minimum' => 1,
                    'maximum' => 30
                )
            )
        ));
    }
    
    /**
     * Register user routes
     */
    private function register_user_routes() {
        // Current user profile
        register_rest_route($this->namespace, '/users/me', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this->controllers['users'], 'get_current_user'),
                'permission_callback' => array($this->controllers['users'], 'get_item_permissions_check')
            ),
            array(
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => array($this->controllers['users'], 'update_current_user'),
                'permission_callback' => array($this->controllers['users'], 'update_item_permissions_check'),
                'args' => $this->get_user_update_params()
            )
        ));
        
        // User settings
        register_rest_route($this->namespace, '/users/me/settings', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this->controllers['users'], 'get_settings'),
                'permission_callback' => array($this->controllers['users'], 'get_item_permissions_check')
            ),
            array(
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => array($this->controllers['users'], 'update_settings'),
                'permission_callback' => array($this->controllers['users'], 'update_item_permissions_check'),
                'args' => $this->get_settings_params()
            )
        ));
        
        // User statistics
        register_rest_route($this->namespace, '/users/me/stats', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this->controllers['users'], 'get_stats'),
            'permission_callback' => array($this->controllers['users'], 'get_item_permissions_check'),
            'args' => array(
                'period' => array(
                    'type' => 'string',
                    'enum' => array('week', 'month', 'year', 'all'),
                    'default' => 'month'
                )
            )
        ));
        
        // User timeline
        register_rest_route($this->namespace, '/users/me/timeline', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this->controllers['users'], 'get_timeline'),
            'permission_callback' => array($this->controllers['users'], 'get_item_permissions_check'),
            'args' => array_merge(
                $this->get_collection_params(),
                array(
                    'types' => array(
                        'type' => 'array',
                        'items' => array(
                            'type' => 'string',
                            'enum' => array('episode', 'assessment', 'insight', 'milestone')
                        ),
                        'default' => array('episode', 'assessment', 'insight', 'milestone')
                    )
                )
            )
        ));
        
        // Privacy data export
        register_rest_route($this->namespace, '/users/me/export', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array($this->controllers['users'], 'export_data'),
            'permission_callback' => array($this->controllers['users'], 'create_item_permissions_check'),
            'args' => array(
                'format' => array(
                    'type' => 'string',
                    'enum' => array('json', 'csv', 'pdf'),
                    'default' => 'json'
                )
            )
        ));
    }
    
    /**
     * Register widget routes
     */
    private function register_widget_routes() {
        // Get all widgets
        register_rest_route($this->namespace, '/widgets', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this->controllers['widgets'], 'get_items'),
            'permission_callback' => array($this->controllers['widgets'], 'get_items_permissions_check'),
            'args' => array(
                'status' => array(
                    'type' => 'string',
                    'enum' => array('active', 'inactive', 'all'),
                    'default' => 'active'
                ),
                'type' => array(
                    'type' => 'string',
                    'required' => false
                ),
                'accessible_only' => array(
                    'type' => 'boolean',
                    'default' => true
                )
            )
        ));
        
        // Single widget
        register_rest_route($this->namespace, '/widgets/(?P<id>[a-zA-Z0-9_-]+)', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this->controllers['widgets'], 'get_item'),
            'permission_callback' => array($this->controllers['widgets'], 'get_item_permissions_check')
        ));
        
        // Widget data
        register_rest_route($this->namespace, '/widgets/(?P<id>[a-zA-Z0-9_-]+)/data', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this->controllers['widgets'], 'get_widget_data'),
                'permission_callback' => array($this->controllers['widgets'], 'get_item_permissions_check')
            ),
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array($this->controllers['widgets'], 'save_widget_data'),
                'permission_callback' => array($this->controllers['widgets'], 'create_item_permissions_check')
            )
        ));
        
        // Widget preview
        register_rest_route($this->namespace, '/widgets/(?P<id>[a-zA-Z0-9_-]+)/preview', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this->controllers['widgets'], 'get_preview'),
            'permission_callback' => array($this->controllers['widgets'], 'get_item_permissions_check')
        ));
    }
    
    /**
     * Register analytics routes
     */
    private function register_analytics_routes() {
        // Episode analytics
        register_rest_route($this->namespace, '/analytics/episodes', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this->controllers['analytics'], 'get_episode_analytics'),
            'permission_callback' => array($this->controllers['analytics'], 'get_items_permissions_check'),
            'args' => $this->get_analytics_params()
        ));
        
        // Pattern analytics
        register_rest_route($this->namespace, '/analytics/patterns', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this->controllers['analytics'], 'get_pattern_analytics'),
            'permission_callback' => array($this->controllers['analytics'], 'get_items_permissions_check'),
            'args' => $this->get_analytics_params()
        ));
        
        // Correlation analytics
        register_rest_route($this->namespace, '/analytics/correlations', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this->controllers['analytics'], 'get_correlation_analytics'),
            'permission_callback' => array($this->controllers['analytics'], 'get_items_permissions_check'),
            'args' => $this->get_analytics_params()
        ));
        
        // Progress analytics
        register_rest_route($this->namespace, '/analytics/progress', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this->controllers['analytics'], 'get_progress_analytics'),
            'permission_callback' => array($this->controllers['analytics'], 'get_items_permissions_check'),
            'args' => $this->get_analytics_params()
        ));
    }
    
    /**
     * Register webhook routes
     */
    private function register_webhook_routes() {
        // Get webhooks
        register_rest_route($this->namespace, '/webhooks', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this->controllers['webhooks'], 'get_items'),
                'permission_callback' => array($this->controllers['webhooks'], 'admin_permissions_check')
            ),
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array($this->controllers['webhooks'], 'create_item'),
                'permission_callback' => array($this->controllers['webhooks'], 'admin_permissions_check'),
                'args' => $this->get_webhook_create_params()
            )
        ));
        
        // Single webhook
        register_rest_route($this->namespace, '/webhooks/(?P<id>\d+)', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this->controllers['webhooks'], 'get_item'),
                'permission_callback' => array($this->controllers['webhooks'], 'admin_permissions_check')
            ),
            array(
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => array($this->controllers['webhooks'], 'update_item'),
                'permission_callback' => array($this->controllers['webhooks'], 'admin_permissions_check'),
                'args' => $this->get_webhook_update_params()
            ),
            array(
                'methods' => WP_REST_Server::DELETABLE,
                'callback' => array($this->controllers['webhooks'], 'delete_item'),
                'permission_callback' => array($this->controllers['webhooks'], 'admin_permissions_check')
            )
        ));
        
        // Test webhook
        register_rest_route($this->namespace, '/webhooks/(?P<id>\d+)/test', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array($this->controllers['webhooks'], 'test_webhook'),
            'permission_callback' => array($this->controllers['webhooks'], 'admin_permissions_check')
        ));
    }
    
    /**
     * Register system routes
     */
    private function register_system_routes() {
        // System health
        register_rest_route($this->namespace, '/system/health', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'get_system_health'),
            'permission_callback' => '__return_true'
        ));
        
        // API info
        register_rest_route($this->namespace, '/info', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'get_api_info'),
            'permission_callback' => '__return_true'
        ));
    }
    
    /**
     * Get collection parameters
     * 
     * @return array
     */
    private function get_collection_params() {
        return array(
            'page' => array(
                'type' => 'integer',
                'default' => 1,
                'minimum' => 1
            ),
            'per_page' => array(
                'type' => 'integer',
                'default' => 10,
                'minimum' => 1,
                'maximum' => 100
            ),
            'search' => array(
                'type' => 'string',
                'required' => false
            ),
            'orderby' => array(
                'type' => 'string',
                'default' => 'date',
                'enum' => array('date', 'id', 'title', 'modified')
            ),
            'order' => array(
                'type' => 'string',
                'default' => 'desc',
                'enum' => array('asc', 'desc')
            )
        );
    }
    
    /**
     * Get assessment create parameters
     * 
     * @return array
     */
    private function get_assessment_create_params() {
        return array(
            'responses' => array(
                'type' => 'array',
                'required' => true,
                'items' => array(
                    'type' => 'integer',
                    'minimum' => 0,
                    'maximum' => 3
                ),
                'minItems' => 6,
                'maxItems' => 6
            ),
            'notes' => array(
                'type' => 'string',
                'required' => false
            )
        );
    }
    
    /**
     * Get assessment update parameters
     * 
     * @return array
     */
    private function get_assessment_update_params() {
        return array(
            'notes' => array(
                'type' => 'string',
                'required' => false
            )
        );
    }
    
    /**
     * Get episode create parameters
     * 
     * @return array
     */
    private function get_episode_create_params() {
        return array(
            'type' => array(
                'type' => 'string',
                'required' => true,
                'enum' => array('overthinking', 'anxiety', 'depression', 'ptsd', 'caregiver', 'panic', 'dissociation')
            ),
            'severity' => array(
                'type' => 'integer',
                'required' => true,
                'minimum' => 1,
                'maximum' => 10
            ),
            'duration' => array(
                'type' => 'integer',
                'required' => false,
                'minimum' => 0
            ),
            'trigger' => array(
                'type' => 'string',
                'required' => false
            ),
            'coping_strategies' => array(
                'type' => 'array',
                'required' => false,
                'items' => array(
                    'type' => 'string'
                )
            ),
            'mood_before' => array(
                'type' => 'integer',
                'required' => false,
                'minimum' => 1,
                'maximum' => 10
            ),
            'mood_after' => array(
                'type' => 'integer',
                'required' => false,
                'minimum' => 1,
                'maximum' => 10
            ),
            'thoughts' => array(
                'type' => 'string',
                'required' => false
            ),
            'physical_symptoms' => array(
                'type' => 'array',
                'required' => false,
                'items' => array(
                    'type' => 'string'
                )
            ),
            'environmental_factors' => array(
                'type' => 'object',
                'required' => false
            ),
            'biological_factors' => array(
                'type' => 'object',
                'required' => false
            )
        );
    }
    
    /**
     * Get episode update parameters
     * 
     * @return array
     */
    private function get_episode_update_params() {
        $params = $this->get_episode_create_params();
        foreach ($params as $key => &$param) {
            $param['required'] = false;
        }
        return $params;
    }
    
    /**
     * Get user update parameters
     * 
     * @return array
     */
    private function get_user_update_params() {
        return array(
            'display_name' => array(
                'type' => 'string',
                'required' => false
            ),
            'bio' => array(
                'type' => 'string',
                'required' => false
            ),
            'biological_sex' => array(
                'type' => 'string',
                'enum' => array('female', 'male'),
                'required' => false
            ),
            'age' => array(
                'type' => 'integer',
                'minimum' => 13,
                'maximum' => 120,
                'required' => false
            ),
            'timezone' => array(
                'type' => 'string',
                'required' => false
            )
        );
    }
    
    /**
     * Get settings parameters
     * 
     * @return array
     */
    private function get_settings_params() {
        return array(
            'notifications' => array(
                'type' => 'object',
                'required' => false
            ),
            'privacy' => array(
                'type' => 'object',
                'required' => false
            ),
            'display' => array(
                'type' => 'object',
                'required' => false
            ),
            'widget_preferences' => array(
                'type' => 'object',
                'required' => false
            )
        );
    }
    
    /**
     * Get analytics parameters
     * 
     * @return array
     */
    private function get_analytics_params() {
        return array(
            'period' => array(
                'type' => 'string',
                'enum' => array('day', 'week', 'month', 'year', 'custom'),
                'default' => 'month'
            ),
            'start_date' => array(
                'type' => 'string',
                'format' => 'date',
                'required' => false
            ),
            'end_date' => array(
                'type' => 'string',
                'format' => 'date',
                'required' => false
            ),
            'groupby' => array(
                'type' => 'string',
                'enum' => array('day', 'week', 'month'),
                'default' => 'day'
            ),
            'metrics' => array(
                'type' => 'array',
                'items' => array(
                    'type' => 'string'
                ),
                'default' => array('count', 'severity', 'duration')
            )
        );
    }
    
    /**
     * Get webhook create parameters
     * 
     * @return array
     */
    private function get_webhook_create_params() {
        return array(
            'name' => array(
                'type' => 'string',
                'required' => true
            ),
            'url' => array(
                'type' => 'string',
                'format' => 'uri',
                'required' => true
            ),
            'events' => array(
                'type' => 'array',
                'required' => true,
                'items' => array(
                    'type' => 'string',
                    'enum' => array(
                        'episode.created',
                        'episode.updated',
                        'assessment.completed',
                        'insight.generated',
                        'pattern.detected',
                        'user.registered',
                        'user.updated'
                    )
                )
            ),
            'secret' => array(
                'type' => 'string',
                'required' => false
            ),
            'active' => array(
                'type' => 'boolean',
                'default' => true
            )
        );
    }
    
    /**
     * Get webhook update parameters
     * 
     * @return array
     */
    private function get_webhook_update_params() {
        $params = $this->get_webhook_create_params();
        foreach ($params as $key => &$param) {
            $param['required'] = false;
        }
        return $params;
    }
    
    /**
     * Get system health
     * 
     * @return WP_REST_Response
     */
    public function get_system_health() {
        $health = array(
            'status' => 'healthy',
            'version' => SPIRALENGINE_VERSION,
            'php_version' => PHP_VERSION,
            'wordpress_version' => get_bloginfo('version'),
            'database' => true,
            'cache' => wp_cache_get('test', 'spiralengine') !== false
        );
        
        return new WP_REST_Response($health, 200);
    }
    
    /**
     * Get API info
     * 
     * @return WP_REST_Response
     */
    public function get_api_info() {
        $info = array(
            'version' => 'v1',
            'endpoints' => array(
                'assessments' => rest_url($this->namespace . '/assessments'),
                'episodes' => rest_url($this->namespace . '/episodes'),
                'insights' => rest_url($this->namespace . '/insights'),
                'patterns' => rest_url($this->namespace . '/patterns'),
                'users' => rest_url($this->namespace . '/users'),
                'widgets' => rest_url($this->namespace . '/widgets'),
                'analytics' => rest_url($this->namespace . '/analytics'),
                'webhooks' => rest_url($this->namespace . '/webhooks')
            ),
            'authentication' => 'WordPress REST API nonce',
            'rate_limits' => array(
                'discovery' => '100 requests/hour',
                'explorer' => '500 requests/hour',
                'pioneer' => '1000 requests/hour',
                'navigator' => '5000 requests/hour',
                'voyager' => 'unlimited'
            )
        );
        
        return new WP_REST_Response($info, 200);
    }
}

// Initialize API routes
SpiralEngine_API_Routes::get_instance();

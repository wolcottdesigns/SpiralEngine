<?php
/**
 * Spiral Engine Core Class
 * 
 * Main plugin class that handles loading dependencies and initializing the plugin
 * 
 * @package    SpiralEngine
 * @subpackage Core
 * @since      1.0.0
 */

// includes/class-spiralengine-core.php

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * The core plugin class
 */
class SpiralEngine_Core {
    
    /**
     * The single instance of the class
     * 
     * @var SpiralEngine_Core
     */
    private static $instance = null;
    
    /**
     * Database handler instance
     * 
     * @var SpiralEngine_Database
     */
    public $database;
    
    /**
     * Security handler instance
     * 
     * @var SpiralEngine_Security
     */
    public $security;
    
    /**
     * Time zone manager instance
     * 
     * @var SPIRALENGINE_Time_Zone_Manager
     */
    public $timezone_manager;
    
    /**
     * System configuration manager
     * 
     * @var System_Configuration_Manager
     */
    public $config_manager;
    
    /**
     * Master control instance
     * 
     * @var SPIRALENGINE_Master_Control
     */
    public $master_control;
    
    /**
     * Privacy manager instance
     * 
     * @var SPIRAL_Privacy_Manager
     */
    public $privacy_manager;
    
    /**
     * Is MemberPress active?
     * 
     * @var boolean
     */
    private $memberpress_active = false;
    
    /**
     * Get singleton instance
     * 
     * @return SpiralEngine_Core
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor - private to enforce singleton
     */
    private function __construct() {
        $this->setup_autoloader();
        $this->check_dependencies();
        $this->load_dependencies();
        $this->init_hooks();
    }
    
    /**
     * Prevent cloning
     */
    private function __clone() {}
    
    /**
     * Prevent unserializing
     */
    public function __wakeup() {
        throw new Exception('Cannot unserialize singleton');
    }
    
    /**
     * Setup class autoloader
     */
    private function setup_autoloader() {
        spl_autoload_register(array($this, 'autoload'));
    }
    
    /**
     * Autoload plugin classes
     * 
     * @param string $class_name The class to load
     */
    public function autoload($class_name) {
        // Only autoload our classes
        if (strpos($class_name, 'SpiralEngine_') !== 0 && 
            strpos($class_name, 'SPIRALENGINE_') !== 0 &&
            strpos($class_name, 'SPIRAL_') !== 0) {
            return;
        }
        
        // Convert class name to file name
        $file_name = 'class-' . strtolower(str_replace('_', '-', $class_name)) . '.php';
        
        // Check in various directories
        $directories = array(
            SPIRALENGINE_PLUGIN_DIR . 'includes/',
            SPIRALENGINE_PLUGIN_DIR . 'includes/admin/',
            SPIRALENGINE_PLUGIN_DIR . 'includes/frontend/',
            SPIRALENGINE_PLUGIN_DIR . 'includes/api/',
            SPIRALENGINE_PLUGIN_DIR . 'includes/widgets/'
        );
        
        foreach ($directories as $directory) {
            $file_path = $directory . $file_name;
            if (file_exists($file_path)) {
                require_once $file_path;
                return;
            }
        }
    }
    
    /**
     * Check for plugin dependencies
     */
    private function check_dependencies() {
        // Check if MemberPress is active (optional dependency)
        if (class_exists('MeprCtrl')) {
            $this->memberpress_active = true;
        }
    }
    
    /**
     * Load all dependencies in correct order
     */
    private function load_dependencies() {
        // Load core dependencies first
        require_once SPIRALENGINE_PLUGIN_DIR . 'includes/class-spiralengine-database.php';
        require_once SPIRALENGINE_PLUGIN_DIR . 'includes/class-spiralengine-security.php';
        
        // Initialize core components
        $this->database = new SpiralEngine_Database();
        $this->security = SpiralEngine_Security::get_instance();
        
        // Load additional managers
        $this->load_managers();
        
        // Load admin classes if in admin
        if (is_admin()) {
            $this->load_admin_classes();
        }
        
        // Load frontend classes
        $this->load_frontend_classes();
        
        // Load API endpoints
        $this->load_api_classes();
    }
    
    /**
     * Load manager classes
     */
    private function load_managers() {
        // Time Zone Manager
        require_once SPIRALENGINE_PLUGIN_DIR . 'includes/class-spiralengine-time-zone-manager.php';
        $this->timezone_manager = new SPIRALENGINE_Time_Zone_Manager();
        
        // System Configuration Manager
        require_once SPIRALENGINE_PLUGIN_DIR . 'includes/class-system-configuration-manager.php';
        $this->config_manager = new System_Configuration_Manager();
        
        // Master Control
        require_once SPIRALENGINE_PLUGIN_DIR . 'includes/class-spiralengine-master-control.php';
        $this->master_control = SPIRALENGINE_Master_Control::getInstance();
        
        // Privacy Manager
        require_once SPIRALENGINE_PLUGIN_DIR . 'includes/class-spiral-privacy-manager.php';
        $this->privacy_manager = new SPIRAL_Privacy_Manager();
    }
    
    /**
     * Load admin classes
     */
    private function load_admin_classes() {
        // Admin menu
        require_once SPIRALENGINE_PLUGIN_DIR . 'includes/admin/class-spiralengine-admin-menu.php';
        new SpiralEngine_Admin_Menu();
        
        // Dashboard
        require_once SPIRALENGINE_PLUGIN_DIR . 'includes/admin/class-spiralengine-dashboard.php';
        new SpiralEngine_Dashboard();
        
        // Settings pages
        require_once SPIRALENGINE_PLUGIN_DIR . 'includes/admin/class-spiralengine-settings.php';
        new SpiralEngine_Settings();
    }
    
    /**
     * Load frontend classes
     */
    private function load_frontend_classes() {
        // Frontend scripts and styles
        require_once SPIRALENGINE_PLUGIN_DIR . 'includes/frontend/class-spiralengine-frontend.php';
        new SpiralEngine_Frontend();
        
        // Shortcodes
        require_once SPIRALENGINE_PLUGIN_DIR . 'includes/frontend/class-spiralengine-shortcodes.php';
        new SpiralEngine_Shortcodes();
    }
    
    /**
     * Load API classes
     */
    private function load_api_classes() {
        // REST API endpoints
        require_once SPIRALENGINE_PLUGIN_DIR . 'includes/api/class-spiralengine-rest-api.php';
        new SpiralEngine_REST_API();
        
        // AJAX handlers
        require_once SPIRALENGINE_PLUGIN_DIR . 'includes/api/class-spiralengine-ajax.php';
        new SpiralEngine_AJAX();
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // Core action hooks
        add_action('init', array($this, 'init'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));
        
        // AJAX hooks for timezone detection
        add_action('wp_ajax_spiralengine_save_timezone', array($this->timezone_manager, 'ajax_save_timezone'));
        add_action('wp_ajax_nopriv_spiralengine_save_timezone', array($this->timezone_manager, 'ajax_save_timezone'));
        
        // Cron hooks
        add_action('spiralengine_hourly_tasks', array($this, 'run_hourly_tasks'));
        add_action('spiralengine_daily_tasks', array($this, 'run_daily_tasks'));
        
        // Schedule cron events
        $this->schedule_cron_events();
        
        // Filter hooks
        add_filter('cron_schedules', array($this, 'add_cron_schedules'));
    }
    
    /**
     * Initialize plugin
     */
    public function init() {
        // Register custom post types if needed
        $this->register_post_types();
        
        // Register taxonomies if needed
        $this->register_taxonomies();
        
        // Flush rewrite rules if needed
        if (get_option('spiralengine_flush_rewrite_rules')) {
            flush_rewrite_rules();
            delete_option('spiralengine_flush_rewrite_rules');
        }
    }
    
    /**
     * Register custom post types
     */
    private function register_post_types() {
        // Register widget post type for Widget Studio
        register_post_type('spiralengine_widget', array(
            'labels' => array(
                'name' => __('Spiral Widgets', 'spiral-engine'),
                'singular_name' => __('Widget', 'spiral-engine')
            ),
            'public' => false,
            'show_ui' => false,
            'capability_type' => 'post',
            'supports' => array('title', 'editor', 'custom-fields')
        ));
    }
    
    /**
     * Register taxonomies
     */
    private function register_taxonomies() {
        // Register widget categories
        register_taxonomy('spiralengine_widget_cat', 'spiralengine_widget', array(
            'labels' => array(
                'name' => __('Widget Categories', 'spiral-engine'),
                'singular_name' => __('Widget Category', 'spiral-engine')
            ),
            'public' => false,
            'show_ui' => false,
            'hierarchical' => true
        ));
    }
    
    /**
     * Enqueue frontend scripts and styles
     */
    public function enqueue_scripts() {
        // Core styles
        wp_enqueue_style(
            'spiralengine-core',
            SPIRALENGINE_PLUGIN_URL . 'assets/css/spiralengine-core.css',
            array(),
            SPIRALENGINE_VERSION
        );
        
        // Core scripts
        wp_enqueue_script(
            'spiralengine-core',
            SPIRALENGINE_PLUGIN_URL . 'assets/js/spiralengine-core.js',
            array('jquery'),
            SPIRALENGINE_VERSION,
            true
        );
        
        // Timezone detection script
        wp_enqueue_script(
            'spiralengine-timezone',
            SPIRALENGINE_PLUGIN_URL . 'assets/js/spiralengine-timezone.js',
            array('jquery'),
            SPIRALENGINE_VERSION,
            true
        );
        
        // Localize scripts
        wp_localize_script('spiralengine-core', 'spiralengine', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('spiralengine_nonce'),
            'user' => array(
                'logged_in' => is_user_logged_in(),
                'id' => get_current_user_id()
            ),
            'timezone' => $this->timezone_manager->get_user_timezone(),
            'i18n' => array(
                'loading' => __('Loading...', 'spiral-engine'),
                'error' => __('An error occurred', 'spiral-engine'),
                'confirm' => __('Are you sure?', 'spiral-engine')
            )
        ));
    }
    
    /**
     * Enqueue admin scripts and styles
     */
    public function admin_enqueue_scripts($hook) {
        // Only load on our admin pages
        if (strpos($hook, 'spiral-engine') === false) {
            return;
        }
        
        // Admin styles
        wp_enqueue_style(
            'spiralengine-admin',
            SPIRALENGINE_PLUGIN_URL . 'assets/css/admin/spiralengine-admin.css',
            array(),
            SPIRALENGINE_VERSION
        );
        
        // Admin scripts
        wp_enqueue_script(
            'spiralengine-admin',
            SPIRALENGINE_PLUGIN_URL . 'assets/js/admin/spiralengine-admin.js',
            array('jquery', 'wp-color-picker'),
            SPIRALENGINE_VERSION,
            true
        );
        
        // Chart.js for analytics
        wp_enqueue_script(
            'chartjs',
            'https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js',
            array(),
            '3.9.1',
            true
        );
        
        // Localize admin scripts
        wp_localize_script('spiralengine-admin', 'spiralengine_admin', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('spiralengine_admin_nonce'),
            'confirm_delete' => __('Are you sure you want to delete this?', 'spiral-engine'),
            'saving' => __('Saving...', 'spiral-engine'),
            'saved' => __('Saved successfully', 'spiral-engine')
        ));
    }
    
    /**
     * Schedule cron events
     */
    private function schedule_cron_events() {
        // Schedule hourly tasks
        if (!wp_next_scheduled('spiralengine_hourly_tasks')) {
            wp_schedule_event(time(), 'hourly', 'spiralengine_hourly_tasks');
        }
        
        // Schedule daily tasks
        if (!wp_next_scheduled('spiralengine_daily_tasks')) {
            wp_schedule_event(time(), 'daily', 'spiralengine_daily_tasks');
        }
    }
    
    /**
     * Add custom cron schedules
     */
    public function add_cron_schedules($schedules) {
        // Add 5 minute schedule
        $schedules['five_minutes'] = array(
            'interval' => 300,
            'display' => __('Every 5 Minutes', 'spiral-engine')
        );
        
        return $schedules;
    }
    
    /**
     * Run hourly maintenance tasks
     */
    public function run_hourly_tasks() {
        // Clean up expired sessions
        $this->database->cleanup_expired_sessions();
        
        // Update system health metrics
        $this->master_control->update_health_metrics();
        
        // Process pending privacy requests
        $this->privacy_manager->process_pending_requests();
    }
    
    /**
     * Run daily maintenance tasks
     */
    public function run_daily_tasks() {
        // Database optimization
        $this->database->optimize_tables();
        
        // Clean old logs
        $this->database->cleanup_old_logs();
        
        // Process data retention policies
        $this->privacy_manager->enforce_retention_policies();
        
        // Update compliance status
        $this->privacy_manager->update_compliance_status();
    }
    
    /**
     * Check if MemberPress is active
     * 
     * @return boolean
     */
    public function is_memberpress_active() {
        return $this->memberpress_active;
    }
    
    /**
     * Get database instance
     * 
     * @return SpiralEngine_Database
     */
    public function get_database() {
        return $this->database;
    }
    
    /**
     * Get security instance
     * 
     * @return SpiralEngine_Security
     */
    public function get_security() {
        return $this->security;
    }
}

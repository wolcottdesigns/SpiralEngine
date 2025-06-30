<?php
// includes/class-spiralengine-core.php

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @since      1.0.0
 * @package    SpiralEngine
 * @subpackage SpiralEngine/includes
 * @author     BrainCave Software <support@braincavesoftware.com>
 */
class SpiralEngine_Core {

    /**
     * The loader that's responsible for maintaining and registering all hooks that power
     * the plugin.
     *
     * @since    1.0.0
     * @access   protected
     * @var      SpiralEngine_Loader    $loader    Maintains and registers all hooks for the plugin.
     */
    protected $loader;

    /**
     * The unique identifier of this plugin.
     *
     * @since    1.0.0
     * @access   protected
     * @var      string    $plugin_name    The string used to uniquely identify this plugin.
     */
    protected $plugin_name;

    /**
     * The current version of the plugin.
     *
     * @since    1.0.0
     * @access   protected
     * @var      string    $version    The current version of the plugin.
     */
    protected $version;

    /**
     * Single instance of class
     *
     * @var SpiralEngine_Core
     */
    private static $instance = null;

    /**
     * Plugin managers and handlers
     */
    private $managers = array();

    /**
     * Define the core functionality of the plugin.
     *
     * Set the plugin name and the plugin version that can be used throughout the plugin.
     * Load the dependencies, define the locale, and set the hooks for the admin area and
     * the public-facing side of the site.
     *
     * @since    1.0.0
     */
    public function __construct() {
        if (defined('SPIRALENGINE_VERSION')) {
            $this->version = SPIRALENGINE_VERSION;
        } else {
            $this->version = '1.0.0';
        }
        $this->plugin_name = 'spiral-engine';

        $this->load_dependencies();
        $this->set_locale();
        $this->define_admin_hooks();
        $this->define_public_hooks();
        $this->load_managers();
        $this->initialize_api();
    }

    /**
     * Get single instance of class
     *
     * @return SpiralEngine_Core
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Load the required dependencies for this plugin.
     *
     * Include the following files that make up the plugin:
     *
     * - SpiralEngine_Loader. Orchestrates the hooks of the plugin.
     * - SpiralEngine_i18n. Defines internationalization functionality.
     * - SpiralEngine_Admin. Defines all hooks for the admin area.
     * - SpiralEngine_Public. Defines all hooks for the public side of the site.
     *
     * Create an instance of the loader which will be used to register the hooks
     * with WordPress.
     *
     * @since    1.0.0
     * @access   private
     */
    private function load_dependencies() {
        /**
         * The class responsible for orchestrating the actions and filters of the
         * core plugin.
         */
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-spiralengine-loader.php';

        /**
         * The class responsible for defining internationalization functionality
         * of the plugin.
         */
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-spiralengine-i18n.php';

        /**
         * The class responsible for defining all actions that occur in the admin area.
         */
        require_once plugin_dir_path(dirname(__FILE__)) . 'admin/class-spiralengine-admin.php';

        /**
         * The class responsible for defining all actions that occur in the public-facing
         * side of the site.
         */
        require_once plugin_dir_path(dirname(__FILE__)) . 'public/class-spiralengine-public.php';

        /**
         * Load utility classes
         */
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-spiralengine-activator.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-spiralengine-deactivator.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-spiralengine-database.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-spiralengine-install.php';

        /**
         * Load AI system classes - FIXED: Load these BEFORE other classes that might depend on them
         */
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-spiralengine-ai-engine.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/ai/class-spiralengine-ai-interface.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/ai/class-spiralengine-ai-processor.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/ai/class-spiralengine-ai-trainer.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/ai/class-spiralengine-pattern-recognizer.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/ai/class-spiralengine-insight-generator.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/ai/class-spiralengine-nlp-processor.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/ai/class-spiralengine-prediction-engine.php';

        $this->loader = new SpiralEngine_Loader();
    }

    /**
     * Load manager classes after base dependencies
     * 
     * @since    1.0.0
     * @access   private
     */
    private function load_managers() {
        /**
         * Load system management classes
         */
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-spiralengine-time-zone-manager.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-system-configuration-manager.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-spiralengine-master-control.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-spiral-privacy-manager.php';

        /**
         * Load analytics and reporting
         */
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-spiralengine-analytics.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-spiralengine-reporting.php';

        /**
         * Load user management
         */
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-spiralengine-user-manager.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-spiralengine-membership.php';

        /**
         * Load assessment system
         */
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-spiralengine-assessment.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-spiralengine-assessment-types.php';

        /**
         * Load widget system
         */
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-spiralengine-widget-factory.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-spiralengine-widget-loader.php';

        /**
         * Load notification system
         */
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-spiralengine-notifications.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-spiralengine-email-manager.php';

        /**
         * Initialize managers
         */
        $this->initialize_managers();
    }

    /**
     * Initialize manager instances
     *
     * @since    1.0.0
     * @access   private
     */
    private function initialize_managers() {
        // System managers
        $this->managers['timezone'] = new SpiralEngine_Time_Zone_Manager();
        $this->managers['config'] = new System_Configuration_Manager();
        $this->managers['master_control'] = SpiralEngine_Master_Control::getInstance();
        $this->managers['privacy'] = new Spiral_Privacy_Manager();
        
        // Core managers
        $this->managers['analytics'] = new SpiralEngine_Analytics();
        $this->managers['reporting'] = new SpiralEngine_Reporting();
        $this->managers['user'] = new SpiralEngine_User_Manager();
        $this->managers['membership'] = new SpiralEngine_Membership();
        $this->managers['assessment'] = new SpiralEngine_Assessment();
        $this->managers['notifications'] = new SpiralEngine_Notifications();
        $this->managers['email'] = new SpiralEngine_Email_Manager();
        
        // Widget system
        $this->managers['widget_factory'] = new SpiralEngine_Widget_Factory();
        $this->managers['widget_loader'] = new SpiralEngine_Widget_Loader();
        
        // AI system - Already loaded via dependency loading
        $this->managers['ai_engine'] = SpiralEngine_AI_Engine::getInstance();
    }

    /**
     * Load frontend-specific classes
     *
     * @since    1.0.0
     * @access   private
     */
    private function load_frontend_classes() {
        if (!is_admin()) {
            require_once plugin_dir_path(dirname(__FILE__)) . 'includes/frontend/class-spiralengine-frontend.php';
            require_once plugin_dir_path(dirname(__FILE__)) . 'includes/frontend/class-spiralengine-shortcodes.php';
            
            // Initialize frontend
            $frontend = new SpiralEngine_Frontend($this->version);
            $frontend->init();
            
            // Initialize shortcodes
            new SpiralEngine_Shortcodes($this->version);
        }
    }

    /**
     * Load admin-specific classes
     *
     * @since    1.0.0
     * @access   private
     */
    private function load_admin_classes() {
        if (is_admin()) {
            require_once plugin_dir_path(dirname(__FILE__)) . 'admin/class-spiralengine-admin-menu.php';
            require_once plugin_dir_path(dirname(__FILE__)) . 'admin/class-spiralengine-admin-settings.php';
            require_once plugin_dir_path(dirname(__FILE__)) . 'admin/class-spiralengine-admin-dashboard.php';
            require_once plugin_dir_path(dirname(__FILE__)) . 'admin/class-spiralengine-admin-reports.php';
            require_once plugin_dir_path(dirname(__FILE__)) . 'admin/class-spiralengine-admin-users.php';
            require_once plugin_dir_path(dirname(__FILE__)) . 'admin/class-spiralengine-admin-tools.php';
            
            // Initialize admin components
            new SpiralEngine_Admin_Menu();
            new SpiralEngine_Admin_Settings();
            new SpiralEngine_Admin_Dashboard();
        }
    }

    /**
     * Initialize API classes
     *
     * @since    1.0.0
     * @access   private
     */
    private function initialize_api() {
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/api/class-spiralengine-rest-api.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/api/class-spiralengine-api-routes.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/api/class-spiralengine-api-authentication.php';
        
        // Initialize REST API
        new SpiralEngine_REST_API();
        new SpiralEngine_API_Routes();
        new SpiralEngine_API_Authentication();
    }

    /**
     * Define the locale for this plugin for internationalization.
     *
     * Uses the SpiralEngine_i18n class in order to set the domain and to register the hook
     * with WordPress.
     *
     * @since    1.0.0
     * @access   private
     */
    private function set_locale() {
        $plugin_i18n = new SpiralEngine_i18n();
        $this->loader->add_action('plugins_loaded', $plugin_i18n, 'load_plugin_textdomain');
    }

    /**
     * Register all of the hooks related to the admin area functionality
     * of the plugin.
     *
     * @since    1.0.0
     * @access   private
     */
    private function define_admin_hooks() {
        $plugin_admin = new SpiralEngine_Admin($this->get_plugin_name(), $this->get_version());

        $this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_styles');
        $this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts');
        
        // Admin menu and pages
        $this->loader->add_action('admin_menu', $plugin_admin, 'add_admin_menu');
        $this->loader->add_action('admin_init', $plugin_admin, 'register_settings');
        
        // Dashboard widgets
        $this->loader->add_action('wp_dashboard_setup', $plugin_admin, 'add_dashboard_widgets');
        
        // User columns
        $this->loader->add_filter('manage_users_columns', $plugin_admin, 'add_user_columns');
        $this->loader->add_action('manage_users_custom_column', $plugin_admin, 'show_user_column_content', 10, 3);
        
        // AJAX handlers
        $this->loader->add_action('wp_ajax_spiralengine_admin_action', $plugin_admin, 'handle_admin_ajax');
        
        // Admin notices
        $this->loader->add_action('admin_notices', $plugin_admin, 'display_admin_notices');
        
        // Load admin classes after init
        $this->loader->add_action('init', $this, 'load_admin_classes');
    }

    /**
     * Register all of the hooks related to the public-facing functionality
     * of the plugin.
     *
     * @since    1.0.0
     * @access   private
     */
    private function define_public_hooks() {
        $plugin_public = new SpiralEngine_Public($this->get_plugin_name(), $this->get_version());

        $this->loader->add_action('wp_enqueue_scripts', $plugin_public, 'enqueue_styles');
        $this->loader->add_action('wp_enqueue_scripts', $plugin_public, 'enqueue_scripts');
        
        // Template handling
        $this->loader->add_filter('template_include', $plugin_public, 'template_loader');
        $this->loader->add_filter('the_content', $plugin_public, 'filter_content');
        
        // User actions
        $this->loader->add_action('init', $plugin_public, 'handle_user_actions');
        $this->loader->add_action('wp_login', $plugin_public, 'handle_user_login', 10, 2);
        
        // AJAX handlers
        $this->loader->add_action('wp_ajax_spiralengine_public_action', $plugin_public, 'handle_public_ajax');
        $this->loader->add_action('wp_ajax_nopriv_spiralengine_public_action', $plugin_public, 'handle_public_ajax_nopriv');
        
        // Load frontend classes after init
        $this->loader->add_action('init', $this, 'load_frontend_classes');
    }

    /**
     * Run the loader to execute all of the hooks with WordPress.
     *
     * @since    1.0.0
     */
    public function run() {
        $this->loader->run();
    }

    /**
     * The name of the plugin used to uniquely identify it within the context of
     * WordPress and to define internationalization functionality.
     *
     * @since     1.0.0
     * @return    string    The name of the plugin.
     */
    public function get_plugin_name() {
        return $this->plugin_name;
    }

    /**
     * The reference to the class that orchestrates the hooks with the plugin.
     *
     * @since     1.0.0
     * @return    SpiralEngine_Loader    Orchestrates the hooks of the plugin.
     */
    public function get_loader() {
        return $this->loader;
    }

    /**
     * Retrieve the version number of the plugin.
     *
     * @since     1.0.0
     * @return    string    The version number of the plugin.
     */
    public function get_version() {
        return $this->version;
    }

    /**
     * Get manager instance
     *
     * @param string $manager Manager name
     * @return object|null Manager instance or null
     */
    public function get_manager($manager) {
        return isset($this->managers[$manager]) ? $this->managers[$manager] : null;
    }

    /**
     * Check if plugin is ready
     *
     * @return bool Plugin ready status
     */
    public function is_ready() {
        // Check if database is installed
        $db = SpiralEngine_Database::getInstance();
        if (!$db->tables_exist()) {
            return false;
        }
        
        // Check if required options are set
        $required_options = array(
            'spiralengine_installed',
            'spiralengine_version'
        );
        
        foreach ($required_options as $option) {
            if (get_option($option) === false) {
                return false;
            }
        }
        
        return true;
    }

    /**
     * Get plugin status
     *
     * @return array Plugin status information
     */
    public function get_status() {
        return array(
            'version' => $this->version,
            'database_version' => get_option('spiralengine_db_version'),
            'installed' => get_option('spiralengine_installed'),
            'ready' => $this->is_ready(),
            'managers_loaded' => count($this->managers),
            'hooks_registered' => $this->loader->get_hook_count()
        );
    }

    /**
     * Handle plugin upgrade
     *
     * @param string $old_version Previous version
     */
    public function handle_upgrade($old_version) {
        // Version-specific upgrades
        if (version_compare($old_version, '1.1.0', '<')) {
            $this->upgrade_to_1_1_0();
        }
        
        if (version_compare($old_version, '1.2.0', '<')) {
            $this->upgrade_to_1_2_0();
        }
        
        // Update version
        update_option('spiralengine_version', $this->version);
        
        // Run install to ensure all tables exist
        $installer = new SpiralEngine_Install();
        $installer->install();
        
        // Clear caches
        $this->clear_all_caches();
    }

    /**
     * Upgrade to version 1.1.0
     */
    private function upgrade_to_1_1_0() {
        // Add new database columns, settings, etc.
        global $wpdb;
        
        // Example: Add new column to assessments table
        $table_name = $wpdb->prefix . 'spiralengine_assessments';
        $wpdb->query("ALTER TABLE $table_name ADD COLUMN IF NOT EXISTS metadata TEXT NULL");
    }

    /**
     * Upgrade to version 1.2.0
     */
    private function upgrade_to_1_2_0() {
        // Add AI-related settings
        add_option('spiralengine_ai_enabled', true);
        add_option('spiralengine_ai_provider', 'openai');
    }

    /**
     * Clear all plugin caches
     */
    private function clear_all_caches() {
        // Clear WordPress object cache
        wp_cache_flush();
        
        // Clear transients
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_spiralengine_%'");
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_spiralengine_%'");
        
        // Clear any custom caches
        do_action('spiralengine_clear_caches');
    }

    /**
     * Get plugin information
     *
     * @return array Plugin information
     */
    public function get_info() {
        return array(
            'name' => $this->plugin_name,
            'version' => $this->version,
            'author' => 'BrainCave Software',
            'website' => 'https://braincavesoftware.com',
            'support' => 'https://support.braincavesoftware.com',
            'documentation' => 'https://docs.braincavesoftware.com/spiral-engine'
        );
    }
}

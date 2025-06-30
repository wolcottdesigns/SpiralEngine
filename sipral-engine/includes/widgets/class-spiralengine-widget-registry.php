<?php
// includes/widgets/class-spiralengine-widget-registry.php

/**
 * SPIRAL Engine Widget Registry
 *
 * Central registry for all widgets with database storage,
 * activation tracking, and versioning support
 *
 * @package SPIRAL_Engine
 * @subpackage Widgets
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class SpiralEngine_Widget_Registry {
    
    /**
     * Singleton instance
     */
    private static $instance = null;
    
    /**
     * Registered widgets array
     */
    private $widgets = array();
    
    /**
     * Episode widgets array
     */
    private $episode_widgets = array();
    
    /**
     * Widget dependencies
     */
    private $dependencies = array();
    
    /**
     * Widget versions
     */
    private $versions = array();
    
    /**
     * Database table name
     */
    private $table_name;
    
    /**
     * Constructor
     */
    private function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'spiralengine_widgets';
        
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
     * Initialize registry
     */
    private function init() {
        // Load widgets from database
        $this->load_widgets();
        
        // Check for updates
        $this->check_widget_updates();
    }
    
    /**
     * Setup hooks
     */
    private function setup_hooks() {
        // Database table creation
        add_action('spiralengine_install', array($this, 'create_tables'));
        
        // Widget activation/deactivation
        add_action('spiralengine_activate_widget', array($this, 'handle_widget_activation'), 10, 1);
        add_action('spiralengine_deactivate_widget', array($this, 'handle_widget_deactivation'), 10, 1);
        
        // Cleanup on uninstall
        register_uninstall_hook(SPIRALENGINE_PLUGIN_FILE, array(__CLASS__, 'uninstall'));
    }
    
    /**
     * Create database tables
     */
    public function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Main widgets table
        $sql = "CREATE TABLE IF NOT EXISTS {$this->table_name} (
            widget_uuid VARCHAR(36) PRIMARY KEY,
            widget_id VARCHAR(100) UNIQUE NOT NULL,
            widget_name VARCHAR(255) NOT NULL,
            widget_description TEXT,
            widget_type VARCHAR(50) NOT NULL,
            widget_category VARCHAR(50),
            widget_class VARCHAR(255) NOT NULL,
            widget_version VARCHAR(20),
            widget_author VARCHAR(255),
            widget_icon VARCHAR(100),
            is_episode_widget BOOLEAN DEFAULT FALSE,
            episode_type VARCHAR(50),
            status ENUM('active', 'inactive', 'error') DEFAULT 'inactive',
            activation_count INT DEFAULT 0,
            last_activated TIMESTAMP NULL,
            settings_json JSON,
            dependencies_json JSON,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_widget_id (widget_id),
            INDEX idx_status (status),
            INDEX idx_type (widget_type),
            INDEX idx_category (widget_category),
            INDEX idx_episode (is_episode_widget, episode_type)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        // Widget activity log table
        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}spiralengine_widget_activity (
            activity_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            widget_uuid VARCHAR(36) NOT NULL,
            action VARCHAR(50) NOT NULL,
            user_id BIGINT UNSIGNED,
            data JSON,
            timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_widget (widget_uuid),
            INDEX idx_action (action),
            INDEX idx_timestamp (timestamp),
            FOREIGN KEY (widget_uuid) REFERENCES {$this->table_name}(widget_uuid) ON DELETE CASCADE
        ) $charset_collate;";
        
        dbDelta($sql);
        
        // Widget views tracking table
        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}spiralengine_widget_views (
            view_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            widget_id VARCHAR(100) NOT NULL,
            user_id BIGINT UNSIGNED,
            view_mode ENUM('preview', 'full') DEFAULT 'preview',
            load_time INT,
            viewed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_widget_views (widget_id),
            INDEX idx_user (user_id),
            INDEX idx_viewed (viewed_at)
        ) $charset_collate;";
        
        dbDelta($sql);
        
        // Episode widgets table
        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}spiralengine_episode_widgets (
            widget_uuid VARCHAR(36) PRIMARY KEY,
            episode_type VARCHAR(50) UNIQUE NOT NULL,
            display_name VARCHAR(100) NOT NULL,
            widget_class VARCHAR(255) NOT NULL,
            color VARCHAR(7),
            icon VARCHAR(50),
            correlation_config JSON,
            forecast_config JSON,
            is_active BOOLEAN DEFAULT TRUE,
            user_count INT DEFAULT 0,
            episode_count INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_episode_type (episode_type),
            INDEX idx_active (is_active),
            FOREIGN KEY (widget_uuid) REFERENCES {$this->table_name}(widget_uuid) ON DELETE CASCADE
        ) $charset_collate;";
        
        dbDelta($sql);
        
        // Correlation configuration table
        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}spiralengine_correlation_config (
            config_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            primary_type VARCHAR(50) NOT NULL,
            related_type VARCHAR(50) NOT NULL,
            correlation_strength DECIMAL(3,2) DEFAULT 0.5,
            is_bidirectional BOOLEAN DEFAULT TRUE,
            min_occurrences INT DEFAULT 3,
            time_window_days INT DEFAULT 7,
            confidence_threshold DECIMAL(3,2) DEFAULT 0.6,
            auto_detect BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY type_pair (primary_type, related_type),
            INDEX idx_primary (primary_type)
        ) $charset_collate;";
        
        dbDelta($sql);
        
        // Widget correlation stats
        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}spiralengine_widget_correlation_stats (
            stat_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            primary_widget VARCHAR(36) NOT NULL,
            related_widget VARCHAR(36) NOT NULL,
            correlation_count INT DEFAULT 0,
            avg_strength DECIMAL(3,2),
            last_correlation TIMESTAMP,
            user_count INT DEFAULT 0,
            UNIQUE KEY widget_pair (primary_widget, related_widget)
        ) $charset_collate;";
        
        dbDelta($sql);
    }
    
    /**
     * Load widgets from database
     */
    private function load_widgets() {
        global $wpdb;
        
        $results = $wpdb->get_results(
            "SELECT * FROM {$this->table_name} ORDER BY widget_name ASC",
            ARRAY_A
        );
        
        foreach ($results as $widget) {
            $widget_uuid = $widget['widget_uuid'];
            
            // Parse JSON fields
            $widget['settings'] = json_decode($widget['settings_json'], true) ?: array();
            $widget['dependencies'] = json_decode($widget['dependencies_json'], true) ?: array();
            
            // Store in registry
            $this->widgets[$widget_uuid] = array(
                'uuid' => $widget_uuid,
                'config' => $widget,
                'instance' => null,
                'status' => $widget['status']
            );
            
            // Track episode widgets separately
            if ($widget['is_episode_widget']) {
                $this->episode_widgets[$widget['episode_type']] = $widget_uuid;
            }
            
            // Store dependencies
            if (!empty($widget['dependencies'])) {
                $this->dependencies[$widget_uuid] = $widget['dependencies'];
            }
            
            // Store version
            if (!empty($widget['widget_version'])) {
                $this->versions[$widget_uuid] = $widget['widget_version'];
            }
        }
    }
    
    /**
     * Register a widget
     */
    public function register_widget($config) {
        global $wpdb;
        
        // Validate required fields
        $required = array('widget_id', 'widget_name', 'widget_class');
        foreach ($required as $field) {
            if (empty($config[$field])) {
                return new WP_Error('missing_field', "Required field {$field} is missing");
            }
        }
        
        // Generate UUID if not provided
        if (empty($config['widget_uuid'])) {
            $config['widget_uuid'] = wp_generate_uuid4();
        }
        
        $widget_uuid = $config['widget_uuid'];
        
        // Check if widget already exists
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT widget_uuid FROM {$this->table_name} WHERE widget_id = %s",
            $config['widget_id']
        ));
        
        if ($existing) {
            // Update existing widget
            return $this->update_widget($existing, $config);
        }
        
        // Prepare data for insertion
        $data = array(
            'widget_uuid' => $widget_uuid,
            'widget_id' => $config['widget_id'],
            'widget_name' => $config['widget_name'],
            'widget_description' => $config['widget_description'] ?? '',
            'widget_type' => $config['widget_type'] ?? 'general',
            'widget_category' => $config['widget_category'] ?? 'uncategorized',
            'widget_class' => $config['widget_class'],
            'widget_version' => $config['widget_version'] ?? '1.0.0',
            'widget_author' => $config['widget_author'] ?? '',
            'widget_icon' => $config['widget_icon'] ?? 'dashicons-admin-generic',
            'is_episode_widget' => !empty($config['is_episode_widget']),
            'episode_type' => $config['episode_type'] ?? null,
            'status' => 'inactive',
            'settings_json' => json_encode($config['settings'] ?? array()),
            'dependencies_json' => json_encode($config['dependencies'] ?? array())
        );
        
        // Insert into database
        $result = $wpdb->insert($this->table_name, $data);
        
        if ($result === false) {
            return new WP_Error('db_error', 'Failed to register widget in database');
        }
        
        // Add to registry
        $this->widgets[$widget_uuid] = array(
            'uuid' => $widget_uuid,
            'config' => $config,
            'instance' => null,
            'status' => 'inactive'
        );
        
        // Handle episode widget registration
        if (!empty($config['is_episode_widget'])) {
            $this->register_episode_widget($widget_uuid, $config);
        }
        
        // Log registration
        $this->log_activity($widget_uuid, 'registered', array(
            'version' => $config['widget_version']
        ));
        
        // Trigger action
        do_action('spiralengine_widget_registered', $widget_uuid, $config);
        
        return $widget_uuid;
    }
    
    /**
     * Update widget
     */
    private function update_widget($widget_uuid, $config) {
        global $wpdb;
        
        // Get current widget data
        $current = $this->get_widget($widget_uuid);
        if (!$current) {
            return new WP_Error('not_found', 'Widget not found');
        }
        
        // Check version
        $current_version = $current['config']['widget_version'] ?? '0.0.0';
        $new_version = $config['widget_version'] ?? '0.0.0';
        
        if (version_compare($new_version, $current_version, '>')) {
            // Version upgrade
            $this->handle_widget_upgrade($widget_uuid, $current_version, $new_version);
        }
        
        // Prepare update data
        $update_data = array(
            'widget_name' => $config['widget_name'],
            'widget_description' => $config['widget_description'] ?? $current['config']['widget_description'],
            'widget_type' => $config['widget_type'] ?? $current['config']['widget_type'],
            'widget_category' => $config['widget_category'] ?? $current['config']['widget_category'],
            'widget_version' => $new_version,
            'widget_author' => $config['widget_author'] ?? $current['config']['widget_author'],
            'widget_icon' => $config['widget_icon'] ?? $current['config']['widget_icon'],
            'settings_json' => json_encode($config['settings'] ?? array()),
            'dependencies_json' => json_encode($config['dependencies'] ?? array())
        );
        
        // Update database
        $result = $wpdb->update(
            $this->table_name,
            $update_data,
            array('widget_uuid' => $widget_uuid)
        );
        
        if ($result === false) {
            return new WP_Error('db_error', 'Failed to update widget');
        }
        
        // Update registry
        $this->widgets[$widget_uuid]['config'] = array_merge(
            $this->widgets[$widget_uuid]['config'],
            $config
        );
        
        // Log update
        $this->log_activity($widget_uuid, 'updated', array(
            'from_version' => $current_version,
            'to_version' => $new_version
        ));
        
        // Clear caches
        $this->clear_widget_cache($widget_uuid);
        
        return $widget_uuid;
    }
    
    /**
     * Unregister widget
     */
    public function unregister_widget($widget_uuid) {
        global $wpdb;
        
        // Check if widget exists
        if (!isset($this->widgets[$widget_uuid])) {
            return new WP_Error('not_found', 'Widget not found');
        }
        
        // Check dependencies
        $dependents = $this->get_dependent_widgets($widget_uuid);
        if (!empty($dependents)) {
            return new WP_Error('has_dependents', 'Cannot unregister widget with active dependents');
        }
        
        // Remove from database
        $result = $wpdb->delete(
            $this->table_name,
            array('widget_uuid' => $widget_uuid)
        );
        
        if ($result === false) {
            return new WP_Error('db_error', 'Failed to remove widget from database');
        }
        
        // Remove from registry
        unset($this->widgets[$widget_uuid]);
        
        // Remove from episode widgets if applicable
        $episode_type = array_search($widget_uuid, $this->episode_widgets);
        if ($episode_type !== false) {
            unset($this->episode_widgets[$episode_type]);
        }
        
        // Log removal
        $this->log_activity($widget_uuid, 'unregistered');
        
        // Trigger action
        do_action('spiralengine_widget_unregistered', $widget_uuid);
        
        return true;
    }
    
    /**
     * Register episode widget
     */
    private function register_episode_widget($widget_uuid, $config) {
        global $wpdb;
        
        $episode_data = array(
            'widget_uuid' => $widget_uuid,
            'episode_type' => $config['episode_type'],
            'display_name' => $config['widget_name'],
            'widget_class' => $config['widget_class'],
            'color' => $config['episode_config']['color'] ?? '#6B46C1',
            'icon' => $config['widget_icon'],
            'correlation_config' => json_encode($config['episode_config']['correlations'] ?? array()),
            'forecast_config' => json_encode($config['episode_config']['unified_forecast'] ?? array())
        );
        
        $wpdb->replace(
            $wpdb->prefix . 'spiralengine_episode_widgets',
            $episode_data
        );
        
        $this->episode_widgets[$config['episode_type']] = $widget_uuid;
    }
    
    /**
     * Activate widget
     */
    public function activate_widget($widget_uuid) {
        global $wpdb;
        
        // Check if widget exists
        if (!isset($this->widgets[$widget_uuid])) {
            return false;
        }
        
        // Check dependencies
        if (!$this->check_widget_dependencies($widget_uuid)) {
            return new WP_Error('missing_dependencies', 'Widget dependencies not met');
        }
        
        // Update status
        $result = $wpdb->update(
            $this->table_name,
            array(
                'status' => 'active',
                'activation_count' => array('activation_count + 1'),
                'last_activated' => current_time('mysql')
            ),
            array('widget_uuid' => $widget_uuid)
        );
        
        if ($result === false) {
            return false;
        }
        
        // Update registry
        $this->widgets[$widget_uuid]['status'] = 'active';
        
        // Instantiate widget class if available
        $class_name = $this->widgets[$widget_uuid]['config']['widget_class'];
        if (class_exists($class_name)) {
            $this->widgets[$widget_uuid]['instance'] = new $class_name();
        }
        
        // Log activation
        $this->log_activity($widget_uuid, 'activated');
        
        // Trigger action
        do_action('spiralengine_widget_activated', $widget_uuid);
        
        return true;
    }
    
    /**
     * Deactivate widget
     */
    public function deactivate_widget($widget_uuid) {
        global $wpdb;
        
        // Check if widget exists
        if (!isset($this->widgets[$widget_uuid])) {
            return false;
        }
        
        // Update status
        $result = $wpdb->update(
            $this->table_name,
            array('status' => 'inactive'),
            array('widget_uuid' => $widget_uuid)
        );
        
        if ($result === false) {
            return false;
        }
        
        // Update registry
        $this->widgets[$widget_uuid]['status'] = 'inactive';
        $this->widgets[$widget_uuid]['instance'] = null;
        
        // Log deactivation
        $this->log_activity($widget_uuid, 'deactivated');
        
        // Trigger action
        do_action('spiralengine_widget_deactivated', $widget_uuid);
        
        return true;
    }
    
    /**
     * Check widget dependencies
     */
    private function check_widget_dependencies($widget_uuid) {
        if (!isset($this->dependencies[$widget_uuid])) {
            return true;
        }
        
        foreach ($this->dependencies[$widget_uuid] as $dependency) {
            // Check if dependency is a widget
            if (isset($dependency['widget'])) {
                $dep_widget = $this->get_widget_by_id($dependency['widget']);
                if (!$dep_widget || $dep_widget['status'] !== 'active') {
                    return false;
                }
            }
            
            // Check if dependency is a plugin
            if (isset($dependency['plugin'])) {
                if (!is_plugin_active($dependency['plugin'])) {
                    return false;
                }
            }
            
            // Check version requirements
            if (isset($dependency['version'])) {
                $dep_version = $this->get_widget_version($dependency['widget']);
                if (version_compare($dep_version, $dependency['version'], '<')) {
                    return false;
                }
            }
        }
        
        return true;
    }
    
    /**
     * Get dependent widgets
     */
    private function get_dependent_widgets($widget_uuid) {
        $dependents = array();
        
        foreach ($this->dependencies as $uuid => $deps) {
            foreach ($deps as $dep) {
                if (isset($dep['widget']) && $dep['widget'] === $this->widgets[$widget_uuid]['config']['widget_id']) {
                    $dependents[] = $uuid;
                    break;
                }
            }
        }
        
        return $dependents;
    }
    
    /**
     * Check for widget updates
     */
    private function check_widget_updates() {
        // This would connect to a widget repository or update server
        // For now, just trigger a filter for external update checks
        $updates = apply_filters('spiralengine_check_widget_updates', array());
        
        foreach ($updates as $widget_id => $update_info) {
            $widget = $this->get_widget_by_id($widget_id);
            if ($widget && version_compare($update_info['version'], $widget['config']['widget_version'], '>')) {
                // Store update info
                set_transient('spiralengine_widget_update_' . $widget['uuid'], $update_info, DAY_IN_SECONDS);
            }
        }
    }
    
    /**
     * Handle widget upgrade
     */
    private function handle_widget_upgrade($widget_uuid, $old_version, $new_version) {
        // Run upgrade routines
        do_action('spiralengine_widget_upgrade', $widget_uuid, $old_version, $new_version);
        
        // Log upgrade
        $this->log_activity($widget_uuid, 'upgraded', array(
            'from_version' => $old_version,
            'to_version' => $new_version
        ));
    }
    
    /**
     * Get widget by UUID
     */
    public function get_widget($widget_uuid) {
        return isset($this->widgets[$widget_uuid]) ? $this->widgets[$widget_uuid] : null;
    }
    
    /**
     * Get widget by ID
     */
    public function get_widget_by_id($widget_id) {
        foreach ($this->widgets as $widget) {
            if ($widget['config']['widget_id'] === $widget_id) {
                return $widget;
            }
        }
        return null;
    }
    
    /**
     * Get all widgets
     */
    public function get_all_widgets() {
        return $this->widgets;
    }
    
    /**
     * Get widgets by type
     */
    public function get_widgets_by_type($type) {
        $filtered = array();
        
        foreach ($this->widgets as $uuid => $widget) {
            if ($widget['config']['widget_type'] === $type) {
                $filtered[$uuid] = $widget;
            }
        }
        
        return $filtered;
    }
    
    /**
     * Get widget version
     */
    public function get_widget_version($widget_id) {
        $widget = $this->get_widget_by_id($widget_id);
        return $widget ? $widget['config']['widget_version'] : null;
    }
    
    /**
     * Check if widget is registered
     */
    public function is_registered($widget_uuid) {
        return isset($this->widgets[$widget_uuid]);
    }
    
    /**
     * Get widget instance
     */
    public function get_widget_instance($widget_uuid) {
        if (!isset($this->widgets[$widget_uuid])) {
            return null;
        }
        
        // Create instance if not exists and widget is active
        if ($this->widgets[$widget_uuid]['instance'] === null && 
            $this->widgets[$widget_uuid]['status'] === 'active') {
            
            $class_name = $this->widgets[$widget_uuid]['config']['widget_class'];
            if (class_exists($class_name)) {
                $this->widgets[$widget_uuid]['instance'] = new $class_name();
            }
        }
        
        return $this->widgets[$widget_uuid]['instance'];
    }
    
    /**
     * Update widget settings
     */
    public function update_widget_settings($widget_uuid, $settings) {
        global $wpdb;
        
        if (!isset($this->widgets[$widget_uuid])) {
            return false;
        }
        
        // Update database
        $result = $wpdb->update(
            $this->table_name,
            array('settings_json' => json_encode($settings)),
            array('widget_uuid' => $widget_uuid)
        );
        
        if ($result === false) {
            return false;
        }
        
        // Update registry
        $this->widgets[$widget_uuid]['config']['settings'] = $settings;
        
        // Clear cache
        $this->clear_widget_cache($widget_uuid);
        
        // Trigger action
        do_action('spiralengine_widget_settings_updated', $widget_uuid, $settings);
        
        return true;
    }
    
    /**
     * Get widget settings
     */
    public function get_widget_settings($widget_uuid) {
        if (!isset($this->widgets[$widget_uuid])) {
            return array();
        }
        
        return $this->widgets[$widget_uuid]['config']['settings'] ?? array();
    }
    
    /**
     * Clear widget cache
     */
    private function clear_widget_cache($widget_uuid) {
        // Clear specific widget cache
        delete_transient('spiralengine_widget_' . $widget_uuid);
        
        // Clear registry cache
        delete_transient('spiralengine_widget_registry');
        
        // Clear any widget-specific caches
        do_action('spiralengine_clear_widget_cache', $widget_uuid);
    }
    
    /**
     * Log widget activity
     */
    private function log_activity($widget_uuid, $action, $data = array()) {
        global $wpdb;
        
        $wpdb->insert(
            $wpdb->prefix . 'spiralengine_widget_activity',
            array(
                'widget_uuid' => $widget_uuid,
                'action' => $action,
                'user_id' => get_current_user_id(),
                'data' => json_encode($data)
            )
        );
    }
    
    /**
     * Handle widget activation hook
     */
    public function handle_widget_activation($widget_uuid) {
        // Additional activation logic
        $widget = $this->get_widget($widget_uuid);
        if ($widget && isset($widget['instance']) && method_exists($widget['instance'], 'activate')) {
            $widget['instance']->activate();
        }
    }
    
    /**
     * Handle widget deactivation hook
     */
    public function handle_widget_deactivation($widget_uuid) {
        // Additional deactivation logic
        $widget = $this->get_widget($widget_uuid);
        if ($widget && isset($widget['instance']) && method_exists($widget['instance'], 'deactivate')) {
            $widget['instance']->deactivate();
        }
    }
    
    /**
     * Export widget configuration
     */
    public function export_widget_config($widget_uuid) {
        $widget = $this->get_widget($widget_uuid);
        if (!$widget) {
            return null;
        }
        
        return array(
            'widget' => $widget['config'],
            'settings' => $this->get_widget_settings($widget_uuid),
            'dependencies' => $this->dependencies[$widget_uuid] ?? array(),
            'version' => $widget['config']['widget_version']
        );
    }
    
    /**
     * Import widget configuration
     */
    public function import_widget_config($config_data) {
        if (!isset($config_data['widget'])) {
            return new WP_Error('invalid_config', 'Invalid configuration data');
        }
        
        // Register or update widget
        $result = $this->register_widget($config_data['widget']);
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        // Update settings if provided
        if (isset($config_data['settings'])) {
            $this->update_widget_settings($result, $config_data['settings']);
        }
        
        return $result;
    }
    
    /**
     * Search widgets
     */
    public function search_widgets($query, $args = array()) {
        $defaults = array(
            'type' => '',
            'category' => '',
            'status' => '',
            'episode_only' => false
        );
        
        $args = wp_parse_args($args, $defaults);
        $results = array();
        
        foreach ($this->widgets as $uuid => $widget) {
            // Check search query
            $searchable = $widget['config']['widget_name'] . ' ' . 
                         $widget['config']['widget_description'] . ' ' .
                         $widget['config']['widget_id'];
            
            if (stripos($searchable, $query) === false) {
                continue;
            }
            
            // Apply filters
            if ($args['type'] && $widget['config']['widget_type'] !== $args['type']) {
                continue;
            }
            
            if ($args['category'] && $widget['config']['widget_category'] !== $args['category']) {
                continue;
            }
            
            if ($args['status'] && $widget['status'] !== $args['status']) {
                continue;
            }
            
            if ($args['episode_only'] && !$widget['config']['is_episode_widget']) {
                continue;
            }
            
            $results[$uuid] = $widget;
        }
        
        return $results;
    }
    
    /**
     * Get registry statistics
     */
    public function get_statistics() {
        global $wpdb;
        
        $stats = array(
            'total_widgets' => count($this->widgets),
            'active_widgets' => 0,
            'inactive_widgets' => 0,
            'error_widgets' => 0,
            'episode_widgets' => count($this->episode_widgets),
            'categories' => array(),
            'types' => array()
        );
        
        foreach ($this->widgets as $widget) {
            // Count by status
            $status = $widget['status'];
            if ($status === 'active') {
                $stats['active_widgets']++;
            } elseif ($status === 'inactive') {
                $stats['inactive_widgets']++;
            } else {
                $stats['error_widgets']++;
            }
            
            // Count by category
            $category = $widget['config']['widget_category'];
            if (!isset($stats['categories'][$category])) {
                $stats['categories'][$category] = 0;
            }
            $stats['categories'][$category]++;
            
            // Count by type
            $type = $widget['config']['widget_type'];
            if (!isset($stats['types'][$type])) {
                $stats['types'][$type] = 0;
            }
            $stats['types'][$type]++;
        }
        
        return $stats;
    }
    
    /**
     * Cleanup old activity logs
     */
    public function cleanup_activity_logs($days = 30) {
        global $wpdb;
        
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->prefix}spiralengine_widget_activity 
             WHERE timestamp < DATE_SUB(NOW(), INTERVAL %d DAY)",
            $days
        ));
    }
    
    /**
     * Uninstall method
     */
    public static function uninstall() {
        global $wpdb;
        
        // Remove tables
        $tables = array(
            'spiralengine_widgets',
            'spiralengine_widget_activity',
            'spiralengine_widget_views',
            'spiralengine_episode_widgets',
            'spiralengine_correlation_config',
            'spiralengine_widget_correlation_stats'
        );
        
        foreach ($tables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}{$table}");
        }
        
        // Remove options
        delete_option('spiralengine_widget_configs');
        delete_option('spiralengine_widget_performance');
        
        // Clear transients
        $wpdb->query(
            "DELETE FROM {$wpdb->options} 
             WHERE option_name LIKE '%spiralengine_widget%'"
        );
    }
}

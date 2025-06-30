<?php
// includes/widgets/class-spiralengine-widget-studio.php

/**
 * SPIRAL Engine Widget Studio
 *
 * Central management system for all widgets (1-1000)
 * Handles registration, discovery, configuration, and access control
 *
 * @package SPIRAL_Engine
 * @subpackage Widgets
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class SpiralEngine_Widget_Studio {
    
    /**
     * Singleton instance
     */
    private static $instance = null;
    
    /**
     * Widget registry instance
     */
    private $registry;
    
    /**
     * Episode widget manager
     */
    private $episode_manager;
    
    /**
     * Widget configurations
     */
    private $widget_configs = array();
    
    /**
     * Performance tracking
     */
    private $performance_data = array();
    
    /**
     * Active widgets cache
     */
    private $active_widgets = null;
    
    /**
     * Constructor
     */
    private function __construct() {
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
     * Initialize the studio
     */
    private function init() {
        // Get registry instance
        $this->registry = SpiralEngine_Widget_Registry::get_instance();
        
        // Initialize episode manager
        $this->episode_manager = new Episode_Widget_Manager();
        
        // Load widget configurations
        $this->load_widget_configs();
        
        // Discover and load widgets
        $this->discover_widgets();
    }
    
    /**
     * Setup WordPress hooks
     */
    private function setup_hooks() {
        // Admin menu
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // AJAX handlers
        add_action('wp_ajax_spiralengine_get_widgets', array($this, 'ajax_get_widgets'));
        add_action('wp_ajax_spiralengine_save_widget_config', array($this, 'ajax_save_widget_config'));
        add_action('wp_ajax_spiralengine_activate_widget', array($this, 'ajax_activate_widget'));
        add_action('wp_ajax_spiralengine_deactivate_widget', array($this, 'ajax_deactivate_widget'));
        add_action('wp_ajax_spiralengine_get_widget_stats', array($this, 'ajax_get_widget_stats'));
        add_action('wp_ajax_spiralengine_import_widget', array($this, 'ajax_import_widget'));
        add_action('wp_ajax_spiralengine_update_correlation_matrix', array($this, 'ajax_update_correlation_matrix'));
        
        // Widget discovery on plugins loaded
        add_action('plugins_loaded', array($this, 'discover_widgets'), 99);
        
        // Performance tracking
        add_action('spiralengine_widget_rendered', array($this, 'track_widget_performance'), 10, 3);
        
        // Enqueue scripts
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_submenu_page(
            'spiralengine-dashboard',
            'Widget Studio',
            'Widget Studio',
            'manage_options',
            'spiralengine-widget-studio',
            array($this, 'render_admin_page')
        );
    }
    
    /**
     * Discover and load widgets
     */
    public function discover_widgets() {
        // Core widget directories
        $widget_dirs = array(
            SPIRALENGINE_PLUGIN_DIR . 'includes/widgets/library/',
            SPIRALENGINE_PLUGIN_DIR . 'includes/widgets/episode-widgets/',
            get_stylesheet_directory() . '/spiralengine-widgets/',
            WP_CONTENT_DIR . '/spiralengine-widgets/'
        );
        
        // Allow themes and plugins to add widget directories
        $widget_dirs = apply_filters('spiralengine_widget_directories', $widget_dirs);
        
        foreach ($widget_dirs as $dir) {
            if (is_dir($dir)) {
                $this->scan_widget_directory($dir);
            }
        }
        
        // Allow direct widget registration
        do_action('spiralengine_register_widgets', $this->registry);
    }
    
    /**
     * Scan directory for widgets
     */
    private function scan_widget_directory($dir) {
        $files = glob($dir . '*/class-widget-*.php');
        
        foreach ($files as $file) {
            // Get widget info from file
            $widget_data = $this->get_widget_file_data($file);
            
            if ($widget_data && $widget_data['valid']) {
                // Include the file
                require_once $file;
                
                // Instantiate if class exists
                if (class_exists($widget_data['class_name'])) {
                    new $widget_data['class_name']();
                }
            }
        }
    }
    
    /**
     * Get widget data from file headers
     */
    private function get_widget_file_data($file) {
        $headers = get_file_data($file, array(
            'widget_name' => 'Widget Name',
            'widget_id' => 'Widget ID',
            'widget_type' => 'Widget Type',
            'widget_category' => 'Category',
            'widget_version' => 'Version',
            'widget_author' => 'Author',
            'class_name' => 'Class Name'
        ));
        
        // Validate required fields
        if (empty($headers['widget_id']) || empty($headers['class_name'])) {
            return false;
        }
        
        $headers['valid'] = true;
        return $headers;
    }
    
    /**
     * Load widget configurations from database
     */
    private function load_widget_configs() {
        $configs = get_option('spiralengine_widget_configs', array());
        $this->widget_configs = is_array($configs) ? $configs : array();
    }
    
    /**
     * Save widget configurations
     */
    private function save_widget_configs() {
        update_option('spiralengine_widget_configs', $this->widget_configs);
    }
    
    /**
     * Get widget configuration
     */
    public function get_widget_config($widget_id) {
        return isset($this->widget_configs[$widget_id]) ? $this->widget_configs[$widget_id] : array();
    }
    
    /**
     * Update widget configuration
     */
    public function update_widget_config($widget_id, $config) {
        $this->widget_configs[$widget_id] = $config;
        $this->save_widget_configs();
        
        // Clear widget cache
        $this->clear_widget_cache($widget_id);
        
        // Trigger update hook
        do_action('spiralengine_widget_config_updated', $widget_id, $config);
    }
    
    /**
     * Get all active widgets
     */
    public function get_active_widgets() {
        if ($this->active_widgets === null) {
            $all_widgets = $this->registry->get_all_widgets();
            $this->active_widgets = array();
            
            foreach ($all_widgets as $widget_uuid => $widget) {
                if ($widget['status'] === 'active') {
                    $this->active_widgets[$widget_uuid] = $widget;
                }
            }
        }
        
        return $this->active_widgets;
    }
    
    /**
     * Get widgets by category
     */
    public function get_widgets_by_category($category) {
        $widgets = $this->get_active_widgets();
        $filtered = array();
        
        foreach ($widgets as $uuid => $widget) {
            if ($widget['config']['widget_category'] === $category) {
                $filtered[$uuid] = $widget;
            }
        }
        
        return $filtered;
    }
    
    /**
     * Get episode widgets
     */
    public function get_episode_widgets() {
        return $this->episode_manager->get_episode_widgets();
    }
    
    /**
     * Activate widget
     */
    public function activate_widget($widget_uuid) {
        $result = $this->registry->activate_widget($widget_uuid);
        
        if ($result) {
            // Clear caches
            $this->active_widgets = null;
            delete_transient('spiralengine_active_widgets');
            
            // Log activation
            $this->log_widget_activity('activated', $widget_uuid);
        }
        
        return $result;
    }
    
    /**
     * Deactivate widget
     */
    public function deactivate_widget($widget_uuid) {
        $result = $this->registry->deactivate_widget($widget_uuid);
        
        if ($result) {
            // Clear caches
            $this->active_widgets = null;
            delete_transient('spiralengine_active_widgets');
            
            // Log deactivation
            $this->log_widget_activity('deactivated', $widget_uuid);
        }
        
        return $result;
    }
    
    /**
     * Import widget
     */
    public function import_widget($widget_data) {
        // Validate widget data
        $validator = new SpiralEngine_Widget_Validator();
        $validation = $validator->validate_widget_import($widget_data);
        
        if (!$validation['valid']) {
            return array(
                'success' => false,
                'message' => 'Widget validation failed',
                'errors' => $validation['errors']
            );
        }
        
        // Process widget files
        $processor = new SpiralEngine_Widget_Processor();
        $result = $processor->process_widget_import($widget_data);
        
        if ($result['success']) {
            // Register widget
            $this->registry->register_widget($result['config']);
            
            // Log import
            $this->log_widget_activity('imported', $result['widget_uuid']);
        }
        
        return $result;
    }
    
    /**
     * Track widget performance
     */
    public function track_widget_performance($widget_id, $mode, $render_time) {
        if (!isset($this->performance_data[$widget_id])) {
            $this->performance_data[$widget_id] = array(
                'renders' => 0,
                'total_time' => 0,
                'avg_time' => 0,
                'modes' => array()
            );
        }
        
        $this->performance_data[$widget_id]['renders']++;
        $this->performance_data[$widget_id]['total_time'] += $render_time;
        $this->performance_data[$widget_id]['avg_time'] = 
            $this->performance_data[$widget_id]['total_time'] / 
            $this->performance_data[$widget_id]['renders'];
        
        if (!isset($this->performance_data[$widget_id]['modes'][$mode])) {
            $this->performance_data[$widget_id]['modes'][$mode] = 0;
        }
        $this->performance_data[$widget_id]['modes'][$mode]++;
        
        // Save performance data periodically
        if ($this->performance_data[$widget_id]['renders'] % 100 === 0) {
            $this->save_performance_data();
        }
    }
    
    /**
     * Save performance data
     */
    private function save_performance_data() {
        update_option('spiralengine_widget_performance', $this->performance_data);
    }
    
    /**
     * Get widget statistics
     */
    public function get_widget_statistics($widget_id = null) {
        global $wpdb;
        
        if ($widget_id) {
            // Get stats for specific widget
            $stats = array(
                'total_views' => $this->get_widget_view_count($widget_id),
                'unique_users' => $this->get_widget_unique_users($widget_id),
                'performance' => $this->performance_data[$widget_id] ?? array(),
                'last_30_days' => $this->get_widget_trend($widget_id, 30)
            );
        } else {
            // Get overall stats
            $stats = array(
                'total_widgets' => count($this->registry->get_all_widgets()),
                'active_widgets' => count($this->get_active_widgets()),
                'episode_widgets' => count($this->get_episode_widgets()),
                'total_views' => $this->get_total_widget_views(),
                'popular_widgets' => $this->get_popular_widgets(10)
            );
        }
        
        return $stats;
    }
    
    /**
     * Clear widget cache
     */
    private function clear_widget_cache($widget_id) {
        global $wpdb;
        
        // Clear transients
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->options} 
             WHERE option_name LIKE %s",
            '%spiralengine_widget_' . $widget_id . '%'
        ));
        
        // Clear object cache
        wp_cache_delete('spiralengine_widget_' . $widget_id, 'widgets');
    }
    
    /**
     * Log widget activity
     */
    private function log_widget_activity($action, $widget_uuid, $data = array()) {
        global $wpdb;
        
        $wpdb->insert(
            $wpdb->prefix . 'spiralengine_widget_activity',
            array(
                'widget_uuid' => $widget_uuid,
                'action' => $action,
                'user_id' => get_current_user_id(),
                'data' => json_encode($data),
                'timestamp' => current_time('mysql')
            )
        );
    }
    
    /**
     * AJAX Handlers
     */
    
    /**
     * Get widgets via AJAX
     */
    public function ajax_get_widgets() {
        check_ajax_referer('spiralengine_admin', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $category = isset($_POST['category']) ? sanitize_text_field($_POST['category']) : '';
        
        if ($category) {
            $widgets = $this->get_widgets_by_category($category);
        } else {
            $widgets = $this->get_active_widgets();
        }
        
        wp_send_json_success($widgets);
    }
    
    /**
     * Save widget configuration via AJAX
     */
    public function ajax_save_widget_config() {
        check_ajax_referer('spiralengine_admin', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $widget_id = sanitize_text_field($_POST['widget_id']);
        $config = json_decode(stripslashes($_POST['config']), true);
        
        if (!$widget_id || !is_array($config)) {
            wp_send_json_error('Invalid data');
        }
        
        $this->update_widget_config($widget_id, $config);
        
        wp_send_json_success(array(
            'message' => 'Widget configuration saved successfully'
        ));
    }
    
    /**
     * Activate widget via AJAX
     */
    public function ajax_activate_widget() {
        check_ajax_referer('spiralengine_admin', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $widget_uuid = sanitize_text_field($_POST['widget_uuid']);
        
        if ($this->activate_widget($widget_uuid)) {
            wp_send_json_success(array(
                'message' => 'Widget activated successfully'
            ));
        } else {
            wp_send_json_error('Failed to activate widget');
        }
    }
    
    /**
     * Deactivate widget via AJAX
     */
    public function ajax_deactivate_widget() {
        check_ajax_referer('spiralengine_admin', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $widget_uuid = sanitize_text_field($_POST['widget_uuid']);
        
        if ($this->deactivate_widget($widget_uuid)) {
            wp_send_json_success(array(
                'message' => 'Widget deactivated successfully'
            ));
        } else {
            wp_send_json_error('Failed to deactivate widget');
        }
    }
    
    /**
     * Get widget statistics via AJAX
     */
    public function ajax_get_widget_stats() {
        check_ajax_referer('spiralengine_admin', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $widget_id = isset($_POST['widget_id']) ? sanitize_text_field($_POST['widget_id']) : null;
        $stats = $this->get_widget_statistics($widget_id);
        
        wp_send_json_success($stats);
    }
    
    /**
     * Import widget via AJAX
     */
    public function ajax_import_widget() {
        check_ajax_referer('spiralengine_admin', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        // Handle file upload
        if (!isset($_FILES['widget_package'])) {
            wp_send_json_error('No widget package uploaded');
        }
        
        $upload = wp_handle_upload($_FILES['widget_package'], array(
            'test_form' => false
        ));
        
        if (isset($upload['error'])) {
            wp_send_json_error($upload['error']);
        }
        
        // Process widget import
        $result = $this->import_widget($upload);
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result['message']);
        }
    }
    
    /**
     * Update correlation matrix via AJAX
     */
    public function ajax_update_correlation_matrix() {
        check_ajax_referer('spiralengine_admin', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $matrix_data = json_decode(stripslashes($_POST['matrix_data']), true);
        
        if (!is_array($matrix_data)) {
            wp_send_json_error('Invalid matrix data');
        }
        
        $result = $this->episode_manager->update_correlation_matrix($matrix_data);
        
        if ($result['success']) {
            wp_send_json_success(array(
                'message' => 'Correlation matrix updated successfully'
            ));
        } else {
            wp_send_json_error($result['error']);
        }
    }
    
    /**
     * Render admin page
     */
    public function render_admin_page() {
        $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'widgets';
        ?>
        <div class="wrap spiralengine-widget-studio">
            <h1>Widget Studio</h1>
            
            <nav class="nav-tab-wrapper">
                <a href="?page=spiralengine-widget-studio&tab=widgets" 
                   class="nav-tab <?php echo $active_tab === 'widgets' ? 'nav-tab-active' : ''; ?>">
                    All Widgets
                </a>
                <a href="?page=spiralengine-widget-studio&tab=episode" 
                   class="nav-tab <?php echo $active_tab === 'episode' ? 'nav-tab-active' : ''; ?>">
                    Episode Manager
                </a>
                <a href="?page=spiralengine-widget-studio&tab=import" 
                   class="nav-tab <?php echo $active_tab === 'import' ? 'nav-tab-active' : ''; ?>">
                    Import Widget
                </a>
                <a href="?page=spiralengine-widget-studio&tab=performance" 
                   class="nav-tab <?php echo $active_tab === 'performance' ? 'nav-tab-active' : ''; ?>">
                    Performance
                </a>
            </nav>
            
            <div class="tab-content">
                <?php
                switch ($active_tab) {
                    case 'widgets':
                        $this->render_widgets_tab();
                        break;
                    case 'episode':
                        $this->render_episode_tab();
                        break;
                    case 'import':
                        $this->render_import_tab();
                        break;
                    case 'performance':
                        $this->render_performance_tab();
                        break;
                }
                ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render widgets tab
     */
    private function render_widgets_tab() {
        $widgets = $this->get_active_widgets();
        $categories = $this->get_widget_categories();
        ?>
        <div class="spiralengine-studio-content">
            <div class="studio-toolbar">
                <button class="button button-primary" id="create-widget">
                    <span class="dashicons dashicons-plus"></span> Create Widget
                </button>
                <button class="button" id="import-widget">
                    <span class="dashicons dashicons-upload"></span> Import
                </button>
                <button class="button" id="widget-templates">
                    <span class="dashicons dashicons-media-document"></span> Templates
                </button>
                
                <div class="widget-filters">
                    <select id="widget-category-filter">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $cat_id => $cat_name): ?>
                            <option value="<?php echo esc_attr($cat_id); ?>">
                                <?php echo esc_html($cat_name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="studio-layout">
                <div class="widget-list-panel">
                    <h3>Widget List</h3>
                    <div class="widget-list">
                        <?php foreach ($categories as $cat_id => $cat_name): ?>
                            <?php $cat_widgets = $this->get_widgets_by_category($cat_id); ?>
                            <?php if (!empty($cat_widgets)): ?>
                                <div class="widget-category">
                                    <h4>
                                        <span class="dashicons dashicons-arrow-down"></span>
                                        <?php echo esc_html($cat_name); ?>
                                    </h4>
                                    <ul class="widget-items">
                                        <?php foreach ($cat_widgets as $widget): ?>
                                            <li class="widget-item" 
                                                data-widget-uuid="<?php echo esc_attr($widget['uuid']); ?>">
                                                <span class="widget-icon <?php echo esc_attr($widget['config']['widget_icon']); ?>"></span>
                                                <span class="widget-name"><?php echo esc_html($widget['config']['widget_name']); ?></span>
                                                <span class="widget-status status-<?php echo esc_attr($widget['status']); ?>">
                                                    <?php echo ucfirst($widget['status']); ?>
                                                </span>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <div class="widget-editor-panel">
                    <div class="widget-editor-placeholder">
                        <p>Select a widget to edit its configuration</p>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render episode manager tab
     */
    private function render_episode_tab() {
        $episode_widgets = $this->get_episode_widgets();
        ?>
        <div class="spiralengine-episode-manager">
            <h2>Episode Framework Manager</h2>
            
            <div class="episode-types-section">
                <h3>Registered Episode Types</h3>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Type</th>
                            <th>Widget</th>
                            <th>Status</th>
                            <th>Users</th>
                            <th>Episodes</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($episode_widgets as $type => $widget): ?>
                            <tr>
                                <td>
                                    <span class="episode-color" style="background-color: <?php echo esc_attr($widget['config']['color']); ?>"></span>
                                    <?php echo esc_html($type); ?>
                                </td>
                                <td><?php echo esc_html($widget['config']['widget_name']); ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo esc_attr($widget['status']); ?>">
                                        <?php echo ucfirst($widget['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo number_format($widget['stats']['user_count']); ?></td>
                                <td><?php echo number_format($widget['stats']['episode_count']); ?></td>
                                <td>
                                    <button class="button button-small configure-correlations" 
                                            data-episode-type="<?php echo esc_attr($type); ?>">
                                        Configure
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="correlation-matrix-section">
                <h3>Correlation Matrix</h3>
                <div id="correlation-matrix-container">
                    <!-- Correlation matrix will be loaded here via JavaScript -->
                </div>
                <button class="button button-primary" id="update-correlation-matrix">
                    Update Matrix
                </button>
                <button class="button" id="test-correlations">
                    Test Correlations
                </button>
                <button class="button" id="export-episode-config">
                    Export Configuration
                </button>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render import tab
     */
    private function render_import_tab() {
        ?>
        <div class="spiralengine-import-widget">
            <h2>Import Widget</h2>
            
            <div class="import-section">
                <h3>Upload Widget Package</h3>
                <form id="widget-import-form" enctype="multipart/form-data">
                    <div class="import-dropzone">
                        <p>Drop widget files here or click to browse</p>
                        <input type="file" name="widget_package" id="widget-package" 
                               accept=".zip" style="display: none;">
                        <button type="button" class="button" id="browse-widget">
                            Browse Files
                        </button>
                    </div>
                    
                    <div class="import-options">
                        <label>
                            <input type="checkbox" name="validate_framework" checked>
                            Validate Episode Framework compliance
                        </label>
                        <label>
                            <input type="checkbox" name="check_correlations" checked>
                            Check correlation interfaces
                        </label>
                        <label>
                            <input type="checkbox" name="verify_unified" checked>
                            Verify unified data methods
                        </label>
                    </div>
                    
                    <div class="import-actions">
                        <button type="button" class="button button-primary" id="validate-widget" disabled>
                            Validate Widget
                        </button>
                        <button type="submit" class="button button-primary" id="import-widget" disabled>
                            Import Widget
                        </button>
                    </div>
                </form>
            </div>
            
            <div class="import-results" style="display: none;">
                <h3>Import Results</h3>
                <div id="import-results-content"></div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render performance tab
     */
    private function render_performance_tab() {
        $stats = $this->get_widget_statistics();
        ?>
        <div class="spiralengine-performance">
            <h2>Widget Performance</h2>
            
            <div class="performance-overview">
                <div class="stat-card">
                    <h3>Total Widgets</h3>
                    <div class="stat-value"><?php echo $stats['total_widgets']; ?></div>
                </div>
                <div class="stat-card">
                    <h3>Active Widgets</h3>
                    <div class="stat-value"><?php echo $stats['active_widgets']; ?></div>
                </div>
                <div class="stat-card">
                    <h3>Episode Widgets</h3>
                    <div class="stat-value"><?php echo $stats['episode_widgets']; ?></div>
                </div>
                <div class="stat-card">
                    <h3>Total Views</h3>
                    <div class="stat-value"><?php echo number_format($stats['total_views']); ?></div>
                </div>
            </div>
            
            <div class="popular-widgets">
                <h3>Most Popular Widgets</h3>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Widget</th>
                            <th>Views</th>
                            <th>Unique Users</th>
                            <th>Avg Load Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($stats['popular_widgets'] as $widget): ?>
                            <tr>
                                <td><?php echo esc_html($widget['name']); ?></td>
                                <td><?php echo number_format($widget['views']); ?></td>
                                <td><?php echo number_format($widget['users']); ?></td>
                                <td><?php echo number_format($widget['avg_time'], 2); ?>ms</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }
    
    /**
     * Get widget categories
     */
    private function get_widget_categories() {
        return array(
            'episode_loggers' => 'Episode Loggers',
            'analytics' => 'Analytics',
            'forms' => 'Forms',
            'content' => 'Content',
            'utilities' => 'Utilities'
        );
    }
    
    /**
     * Helper methods for statistics
     */
    private function get_widget_view_count($widget_id) {
        global $wpdb;
        return $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}spiralengine_widget_views 
             WHERE widget_id = %s",
            $widget_id
        ));
    }
    
    private function get_widget_unique_users($widget_id) {
        global $wpdb;
        return $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT user_id) FROM {$wpdb->prefix}spiralengine_widget_views 
             WHERE widget_id = %s",
            $widget_id
        ));
    }
    
    private function get_total_widget_views() {
        global $wpdb;
        return $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}spiralengine_widget_views"
        );
    }
    
    private function get_widget_trend($widget_id, $days) {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT DATE(viewed_at) as date, COUNT(*) as views 
             FROM {$wpdb->prefix}spiralengine_widget_views 
             WHERE widget_id = %s 
             AND viewed_at >= DATE_SUB(NOW(), INTERVAL %d DAY)
             GROUP BY DATE(viewed_at)
             ORDER BY date ASC",
            $widget_id,
            $days
        ));
    }
    
    private function get_popular_widgets($limit = 10) {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT w.widget_id, w.widget_name as name, 
                    COUNT(v.view_id) as views,
                    COUNT(DISTINCT v.user_id) as users,
                    AVG(v.load_time) as avg_time
             FROM {$wpdb->prefix}spiralengine_widgets w
             LEFT JOIN {$wpdb->prefix}spiralengine_widget_views v ON w.widget_id = v.widget_id
             GROUP BY w.widget_id
             ORDER BY views DESC
             LIMIT %d",
            $limit
        ), ARRAY_A);
    }
    
    /**
     * Enqueue admin scripts
     */
    public function enqueue_admin_scripts($hook) {
        if (strpos($hook, 'spiralengine-widget-studio') === false) {
            return;
        }
        
        wp_enqueue_script(
            'spiralengine-widget-studio',
            SPIRALENGINE_PLUGIN_URL . 'assets/js/widget-studio.js',
            array('jquery', 'wp-util'),
            SPIRALENGINE_VERSION,
            true
        );
        
        wp_localize_script('spiralengine-widget-studio', 'spiralengineStudio', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('spiralengine_admin'),
            'strings' => array(
                'confirmDeactivate' => __('Are you sure you want to deactivate this widget?', 'spiralengine'),
                'importSuccess' => __('Widget imported successfully!', 'spiralengine'),
                'importError' => __('Widget import failed. Please check the file and try again.', 'spiralengine')
            )
        ));
        
        wp_enqueue_style(
            'spiralengine-widget-studio',
            SPIRALENGINE_PLUGIN_URL . 'assets/css/widget-studio.css',
            array(),
            SPIRALENGINE_VERSION
        );
    }
}

/**
 * Episode Widget Manager Class
 */
class Episode_Widget_Manager {
    
    private $episode_registry;
    private $correlation_matrix = array();
    
    public function __construct() {
        $this->init();
    }
    
    private function init() {
        // Initialize episode registry
        $this->episode_registry = SPIRAL_Episode_Registry::get_instance();
        $this->load_correlation_matrix();
    }
    
    /**
     * Register an episode widget
     */
    public function register_episode_widget($widget_config) {
        // Standard widget registration
        $registry = SpiralEngine_Widget_Registry::get_instance();
        $widget_uuid = $registry->register_widget($widget_config);
        
        // Episode-specific registration
        if (isset($widget_config['episode_type'])) {
            $this->register_episode_type($widget_config);
            $this->setup_correlations($widget_config);
            $this->configure_unified_settings($widget_config);
        }
        
        return $widget_uuid;
    }
    
    /**
     * Register episode type
     */
    private function register_episode_type($config) {
        $this->episode_registry->register_episode_type($config['episode_type'], array(
            'class' => $config['widget_class'],
            'name' => $config['widget_name'],
            'description' => $config['widget_description'],
            'color' => $config['episode_config']['color'] ?? '#6B46C1',
            'icon' => $config['widget_icon'],
            'weight' => $config['episode_config']['correlation_weight'] ?? 1.0,
            'correlations' => $config['episode_config']['correlations'] ?? array()
        ));
    }
    
    /**
     * Setup correlations
     */
    private function setup_correlations($config) {
        if (!isset($config['episode_config']['correlations'])) {
            return;
        }
        
        $episode_type = $config['episode_type'];
        $correlations = $config['episode_config']['correlations'];
        
        foreach ($correlations as $related_type => $strength) {
            $this->add_correlation($episode_type, $related_type, $strength);
        }
    }
    
    /**
     * Configure unified settings
     */
    private function configure_unified_settings($config) {
        if (!isset($config['episode_config']['unified_forecast'])) {
            return;
        }
        
        $settings = $config['episode_config']['unified_forecast'];
        update_option('spiralengine_unified_' . $config['episode_type'], $settings);
    }
    
    /**
     * Get episode widgets
     */
    public function get_episode_widgets() {
        $registry = SpiralEngine_Widget_Registry::get_instance();
        $all_widgets = $registry->get_all_widgets();
        $episode_widgets = array();
        
        foreach ($all_widgets as $uuid => $widget) {
            if (isset($widget['config']['is_episode_widget']) && $widget['config']['is_episode_widget']) {
                $episode_type = $widget['config']['episode_type'];
                $episode_widgets[$episode_type] = array(
                    'uuid' => $uuid,
                    'config' => $widget['config'],
                    'status' => $widget['status'],
                    'stats' => $this->get_episode_widget_stats($episode_type)
                );
            }
        }
        
        return $episode_widgets;
    }
    
    /**
     * Get episode widget statistics
     */
    private function get_episode_widget_stats($episode_type) {
        global $wpdb;
        
        $stats = array(
            'user_count' => $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(DISTINCT user_id) 
                 FROM {$wpdb->prefix}spiralengine_episodes 
                 WHERE episode_type = %s",
                $episode_type
            )),
            'episode_count' => $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) 
                 FROM {$wpdb->prefix}spiralengine_episodes 
                 WHERE episode_type = %s",
                $episode_type
            ))
        );
        
        return $stats;
    }
    
    /**
     * Load correlation matrix
     */
    private function load_correlation_matrix() {
        global $wpdb;
        
        $correlations = $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}spiralengine_correlation_config",
            ARRAY_A
        );
        
        foreach ($correlations as $correlation) {
            $primary = $correlation['primary_type'];
            $related = $correlation['related_type'];
            
            if (!isset($this->correlation_matrix[$primary])) {
                $this->correlation_matrix[$primary] = array();
            }
            
            $this->correlation_matrix[$primary][$related] = $correlation['correlation_strength'];
        }
    }
    
    /**
     * Add correlation
     */
    private function add_correlation($primary_type, $related_type, $strength) {
        if (!isset($this->correlation_matrix[$primary_type])) {
            $this->correlation_matrix[$primary_type] = array();
        }
        
        $this->correlation_matrix[$primary_type][$related_type] = $strength;
    }
    
    /**
     * Update correlation matrix
     */
    public function update_correlation_matrix($matrix_data) {
        global $wpdb;
        
        $wpdb->query('START TRANSACTION');
        
        try {
            foreach ($matrix_data as $primary => $correlations) {
                foreach ($correlations as $related => $strength) {
                    $wpdb->replace(
                        $wpdb->prefix . 'spiralengine_correlation_config',
                        array(
                            'primary_type' => $primary,
                            'related_type' => $related,
                            'correlation_strength' => $strength
                        )
                    );
                }
            }
            
            $wpdb->query('COMMIT');
            
            // Clear caches
            $this->clear_correlation_caches();
            
            // Notify widgets
            do_action('spiral_correlation_matrix_updated', $matrix_data);
            
            return array('success' => true);
            
        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            return array('success' => false, 'error' => $e->getMessage());
        }
    }
    
    /**
     * Clear correlation caches
     */
    private function clear_correlation_caches() {
        delete_transient('spiralengine_correlation_matrix');
        wp_cache_delete('correlation_matrix', 'spiralengine');
    }
}

// Initialize the Widget Studio
add_action('plugins_loaded', function() {
    SpiralEngine_Widget_Studio::get_instance();
}, 10);


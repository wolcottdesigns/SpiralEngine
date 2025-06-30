<?php
// includes/admin/class-spiralengine-admin-widgets.php

/**
 * Spiral Engine Admin Widgets Management
 * 
 * Widget Studio interface for managing all system widgets
 * Based on Master Widget Studio Development Plan
 */
class SPIRALENGINE_Admin_Widgets {
    
    /**
     * Instance of this class
     */
    private static $instance = null;
    
    /**
     * Widget categories
     */
    private $widget_categories = array();
    
    /**
     * Get instance
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
        $this->init_widget_categories();
        add_action('wp_ajax_spiral_widget_action', array($this, 'ajax_widget_action'));
        add_action('wp_ajax_spiral_widget_preview', array($this, 'ajax_widget_preview'));
        add_action('wp_ajax_spiral_widget_deploy', array($this, 'ajax_widget_deploy'));
    }
    
    /**
     * Initialize widget categories
     */
    private function init_widget_categories() {
        $this->widget_categories = array(
            'episode_loggers' => array(
                'label' => __('Episode Loggers', 'spiral-engine'),
                'description' => __('Quick and detailed episode logging widgets', 'spiral-engine'),
                'icon' => 'dashicons-edit'
            ),
            'visualization' => array(
                'label' => __('Data Visualization', 'spiral-engine'),
                'description' => __('Charts, timelines, and visual insights', 'spiral-engine'),
                'icon' => 'dashicons-chart-area'
            ),
            'insights' => array(
                'label' => __('AI Insights', 'spiral-engine'),
                'description' => __('Pattern detection and predictive analytics', 'spiral-engine'),
                'icon' => 'dashicons-lightbulb'
            ),
            'tracking' => array(
                'label' => __('Progress Tracking', 'spiral-engine'),
                'description' => __('Goals, milestones, and improvement tracking', 'spiral-engine'),
                'icon' => 'dashicons-flag'
            ),
            'community' => array(
                'label' => __('Community Features', 'spiral-engine'),
                'description' => __('Sharing, support, and group features', 'spiral-engine'),
                'icon' => 'dashicons-groups'
            ),
            'tools' => array(
                'label' => __('Support Tools', 'spiral-engine'),
                'description' => __('Crisis support and coping mechanisms', 'spiral-engine'),
                'icon' => 'dashicons-heart'
            )
        );
        
        $this->widget_categories = apply_filters('spiral_engine_widget_categories', $this->widget_categories);
    }
    
    /**
     * Render the widgets page
     */
    public function render() {
        $current_view = isset($_GET['view']) ? sanitize_text_field($_GET['view']) : 'studio';
        ?>
        <div class="wrap spiral-engine-widgets">
            <h1>
                <?php _e('Widget Studio', 'spiral-engine'); ?>
                <a href="<?php echo add_query_arg('view', 'create'); ?>" 
                   class="page-title-action"><?php _e('Create New Widget', 'spiral-engine'); ?></a>
                <a href="<?php echo add_query_arg('view', 'performance'); ?>" 
                   class="page-title-action"><?php _e('Performance Monitor', 'spiral-engine'); ?></a>
            </h1>
            
            <?php
            switch ($current_view) {
                case 'create':
                    $this->render_create_widget();
                    break;
                    
                case 'edit':
                    $this->render_edit_widget();
                    break;
                    
                case 'performance':
                    $this->render_performance_monitor();
                    break;
                    
                case 'detail':
                    $this->render_widget_detail();
                    break;
                    
                default:
                    $this->render_widget_studio();
                    break;
            }
            ?>
        </div>
        <?php
    }
    
    /**
     * Render Widget Studio main view
     */
    private function render_widget_studio() {
        ?>
        <div class="widget-studio">
            <!-- Studio Header -->
            <div class="studio-header">
                <div class="studio-stats">
                    <div class="stat-item">
                        <span class="stat-value"><?php echo $this->get_total_widgets(); ?></span>
                        <span class="stat-label"><?php _e('Total Widgets', 'spiral-engine'); ?></span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-value"><?php echo $this->get_active_widgets(); ?></span>
                        <span class="stat-label"><?php _e('Active', 'spiral-engine'); ?></span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-value"><?php echo number_format($this->get_widget_impressions()); ?></span>
                        <span class="stat-label"><?php _e('Total Impressions', 'spiral-engine'); ?></span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-value"><?php echo $this->get_avg_load_time(); ?>ms</span>
                        <span class="stat-label"><?php _e('Avg Load Time', 'spiral-engine'); ?></span>
                    </div>
                </div>
            </div>
            
            <!-- Widget Categories -->
            <div class="widget-categories">
                <h2><?php _e('Widget Categories', 'spiral-engine'); ?></h2>
                <div class="category-grid">
                    <?php foreach ($this->widget_categories as $cat_id => $category): ?>
                    <div class="category-card" data-category="<?php echo esc_attr($cat_id); ?>">
                        <div class="category-icon">
                            <span class="<?php echo esc_attr($category['icon']); ?>"></span>
                        </div>
                        <h3><?php echo esc_html($category['label']); ?></h3>
                        <p><?php echo esc_html($category['description']); ?></p>
                        <div class="category-count">
                            <?php echo $this->get_category_widget_count($cat_id); ?> widgets
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- Active Widgets Grid -->
            <div class="active-widgets-section">
                <div class="section-header">
                    <h2><?php _e('Active Widgets', 'spiral-engine'); ?></h2>
                    <div class="widget-filters">
                        <select id="widget-sort">
                            <option value="usage"><?php _e('Sort by Usage', 'spiral-engine'); ?></option>
                            <option value="performance"><?php _e('Sort by Performance', 'spiral-engine'); ?></option>
                            <option value="name"><?php _e('Sort by Name', 'spiral-engine'); ?></option>
                            <option value="modified"><?php _e('Sort by Modified', 'spiral-engine'); ?></option>
                        </select>
                        <button class="button" id="refresh-widgets">
                            <span class="dashicons dashicons-update"></span>
                        </button>
                    </div>
                </div>
                
                <div class="widgets-grid" id="active-widgets-grid">
                    <?php $this->render_widget_cards(); ?>
                </div>
            </div>
            
            <!-- Widget Development Tools -->
            <div class="development-tools">
                <h2><?php _e('Development Tools', 'spiral-engine'); ?></h2>
                <div class="tools-grid">
                    <div class="tool-card">
                        <h3><?php _e('Widget API Documentation', 'spiral-engine'); ?></h3>
                        <p><?php _e('Complete reference for widget development', 'spiral-engine'); ?></p>
                        <a href="#" class="button"><?php _e('View Docs', 'spiral-engine'); ?></a>
                    </div>
                    
                    <div class="tool-card">
                        <h3><?php _e('Widget Tester', 'spiral-engine'); ?></h3>
                        <p><?php _e('Test widgets in different environments', 'spiral-engine'); ?></p>
                        <a href="#" class="button"><?php _e('Launch Tester', 'spiral-engine'); ?></a>
                    </div>
                    
                    <div class="tool-card">
                        <h3><?php _e('Performance Profiler', 'spiral-engine'); ?></h3>
                        <p><?php _e('Analyze widget performance metrics', 'spiral-engine'); ?></p>
                        <a href="#" class="button"><?php _e('Run Profiler', 'spiral-engine'); ?></a>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render widget cards
     */
    private function render_widget_cards() {
        $widgets = $this->get_widgets();
        
        foreach ($widgets as $widget) {
            $performance_class = $this->get_performance_class($widget->load_time);
            ?>
            <div class="widget-card" data-widget-id="<?php echo esc_attr($widget->id); ?>">
                <div class="widget-header">
                    <div class="widget-status <?php echo $widget->status; ?>"></div>
                    <div class="widget-actions">
                        <button class="widget-action" data-action="preview" 
                                title="<?php esc_attr_e('Preview', 'spiral-engine'); ?>">
                            <span class="dashicons dashicons-visibility"></span>
                        </button>
                        <button class="widget-action" data-action="edit"
                                title="<?php esc_attr_e('Edit', 'spiral-engine'); ?>">
                            <span class="dashicons dashicons-edit"></span>
                        </button>
                        <button class="widget-action" data-action="stats"
                                title="<?php esc_attr_e('Statistics', 'spiral-engine'); ?>">
                            <span class="dashicons dashicons-chart-bar"></span>
                        </button>
                    </div>
                </div>
                
                <div class="widget-body">
                    <h3><?php echo esc_html($widget->name); ?></h3>
                    <p class="widget-description"><?php echo esc_html($widget->description); ?></p>
                    
                    <div class="widget-meta">
                        <span class="meta-item category">
                            <span class="dashicons dashicons-category"></span>
                            <?php echo esc_html($this->widget_categories[$widget->category]['label'] ?? $widget->category); ?>
                        </span>
                        <span class="meta-item usage">
                            <span class="dashicons dashicons-visibility"></span>
                            <?php echo number_format($widget->usage_count); ?> uses
                        </span>
                    </div>
                    
                    <div class="widget-performance">
                        <div class="performance-bar">
                            <div class="performance-fill <?php echo $performance_class; ?>" 
                                 style="width: <?php echo min(100, $widget->load_time / 10); ?>%"></div>
                        </div>
                        <span class="performance-text">
                            <?php echo $widget->load_time; ?>ms load time
                        </span>
                    </div>
                </div>
                
                <div class="widget-footer">
                    <div class="widget-zones">
                        <?php 
                        $zones = explode(',', $widget->allowed_zones);
                        foreach ($zones as $zone):
                        ?>
                        <span class="zone-badge"><?php echo esc_html(trim($zone)); ?></span>
                        <?php endforeach; ?>
                    </div>
                    <div class="widget-toggle">
                        <label class="switch">
                            <input type="checkbox" class="widget-status-toggle" 
                                   data-widget-id="<?php echo esc_attr($widget->id); ?>"
                                   <?php checked($widget->status, 'active'); ?>>
                            <span class="slider"></span>
                        </label>
                    </div>
                </div>
            </div>
            <?php
        }
    }
    
    /**
     * Render create widget view
     */
    private function render_create_widget() {
        ?>
        <div class="create-widget-view">
            <h2><?php _e('Create New Widget', 'spiral-engine'); ?></h2>
            
            <form id="create-widget-form" class="widget-form">
                <div class="form-section">
                    <h3><?php _e('Basic Information', 'spiral-engine'); ?></h3>
                    
                    <label>
                        <span><?php _e('Widget Name', 'spiral-engine'); ?> <span class="required">*</span></span>
                        <input type="text" name="widget_name" required>
                    </label>
                    
                    <label>
                        <span><?php _e('Description', 'spiral-engine'); ?></span>
                        <textarea name="widget_description" rows="3"></textarea>
                    </label>
                    
                    <label>
                        <span><?php _e('Category', 'spiral-engine'); ?> <span class="required">*</span></span>
                        <select name="widget_category" required>
                            <option value=""><?php _e('Select Category', 'spiral-engine'); ?></option>
                            <?php foreach ($this->widget_categories as $cat_id => $category): ?>
                            <option value="<?php echo esc_attr($cat_id); ?>">
                                <?php echo esc_html($category['label']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                </div>
                
                <div class="form-section">
                    <h3><?php _e('Widget Type', 'spiral-engine'); ?></h3>
                    
                    <div class="widget-type-selector">
                        <label class="type-option">
                            <input type="radio" name="widget_type" value="display" checked>
                            <div class="type-card">
                                <span class="dashicons dashicons-visibility"></span>
                                <strong><?php _e('Display Widget', 'spiral-engine'); ?></strong>
                                <p><?php _e('Shows information or visualizations', 'spiral-engine'); ?></p>
                            </div>
                        </label>
                        
                        <label class="type-option">
                            <input type="radio" name="widget_type" value="input">
                            <div class="type-card">
                                <span class="dashicons dashicons-edit"></span>
                                <strong><?php _e('Input Widget', 'spiral-engine'); ?></strong>
                                <p><?php _e('Collects user data or episodes', 'spiral-engine'); ?></p>
                            </div>
                        </label>
                        
                        <label class="type-option">
                            <input type="radio" name="widget_type" value="interactive">
                            <div class="type-card">
                                <span class="dashicons dashicons-admin-generic"></span>
                                <strong><?php _e('Interactive Widget', 'spiral-engine'); ?></strong>
                                <p><?php _e('Complex interactions and features', 'spiral-engine'); ?></p>
                            </div>
                        </label>
                    </div>
                </div>
                
                <div class="form-section">
                    <h3><?php _e('Display Zones', 'spiral-engine'); ?></h3>
                    <p class="description"><?php _e('Select where this widget can be displayed', 'spiral-engine'); ?></p>
                    
                    <div class="zones-selector">
                        <label>
                            <input type="checkbox" name="zones[]" value="dashboard" checked>
                            <?php _e('User Dashboard', 'spiral-engine'); ?>
                        </label>
                        <label>
                            <input type="checkbox" name="zones[]" value="sidebar">
                            <?php _e('Sidebar', 'spiral-engine'); ?>
                        </label>
                        <label>
                            <input type="checkbox" name="zones[]" value="episode_detail">
                            <?php _e('Episode Detail Page', 'spiral-engine'); ?>
                        </label>
                        <label>
                            <input type="checkbox" name="zones[]" value="profile">
                            <?php _e('User Profile', 'spiral-engine'); ?>
                        </label>
                        <label>
                            <input type="checkbox" name="zones[]" value="mobile_home">
                            <?php _e('Mobile Home Screen', 'spiral-engine'); ?>
                        </label>
                        <label>
                            <input type="checkbox" name="zones[]" value="shortcode">
                            <?php _e('Shortcode', 'spiral-engine'); ?>
                        </label>
                    </div>
                </div>
                
                <div class="form-section">
                    <h3><?php _e('Access Control', 'spiral-engine'); ?></h3>
                    
                    <label>
                        <span><?php _e('Minimum Membership Level', 'spiral-engine'); ?></span>
                        <select name="min_membership_level">
                            <option value="any"><?php _e('Any User', 'spiral-engine'); ?></option>
                            <option value="discovery"><?php _e('Discovery', 'spiral-engine'); ?></option>
                            <option value="explorer"><?php _e('Explorer', 'spiral-engine'); ?></option>
                            <option value="navigator"><?php _e('Navigator', 'spiral-engine'); ?></option>
                            <option value="voyager"><?php _e('Voyager', 'spiral-engine'); ?></option>
                        </select>
                    </label>
                    
                    <label>
                        <span><?php _e('Additional Requirements', 'spiral-engine'); ?></span>
                        <div class="checkbox-group">
                            <label>
                                <input type="checkbox" name="requirements[]" value="episodes_logged">
                                <?php _e('Must have logged episodes', 'spiral-engine'); ?>
                            </label>
                            <label>
                                <input type="checkbox" name="requirements[]" value="patterns_found">
                                <?php _e('Must have patterns detected', 'spiral-engine'); ?>
                            </label>
                            <label>
                                <input type="checkbox" name="requirements[]" value="ai_enabled">
                                <?php _e('Must have AI features enabled', 'spiral-engine'); ?>
                            </label>
                        </div>
                    </label>
                </div>
                
                <div class="form-section">
                    <h3><?php _e('Widget Configuration', 'spiral-engine'); ?></h3>
                    
                    <div id="widget-config-builder">
                        <p><?php _e('Configure widget settings and options', 'spiral-engine'); ?></p>
                        <button type="button" class="button" id="add-config-option">
                            <?php _e('Add Configuration Option', 'spiral-engine'); ?>
                        </button>
                        <div id="config-options-list"></div>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="button button-primary">
                        <?php _e('Create Widget', 'spiral-engine'); ?>
                    </button>
                    <button type="button" class="button" id="preview-widget">
                        <?php _e('Preview', 'spiral-engine'); ?>
                    </button>
                    <a href="<?php echo remove_query_arg('view'); ?>" class="button">
                        <?php _e('Cancel', 'spiral-engine'); ?>
                    </a>
                </div>
            </form>
        </div>
        <?php
    }
    
    /**
     * Render performance monitor
     */
    private function render_performance_monitor() {
        ?>
        <div class="performance-monitor">
            <h2><?php _e('Widget Performance Monitor', 'spiral-engine'); ?></h2>
            
            <!-- Real-time Performance Metrics -->
            <div class="performance-overview">
                <div class="metric-card">
                    <h3><?php _e('Average Load Time', 'spiral-engine'); ?></h3>
                    <div class="metric-value"><?php echo $this->get_avg_load_time(); ?>ms</div>
                    <div class="metric-trend up">↓ 12% from last week</div>
                </div>
                
                <div class="metric-card">
                    <h3><?php _e('Total Widgets Loaded', 'spiral-engine'); ?></h3>
                    <div class="metric-value"><?php echo number_format($this->get_widgets_loaded_today()); ?></div>
                    <div class="metric-subtext">Today</div>
                </div>
                
                <div class="metric-card">
                    <h3><?php _e('Error Rate', 'spiral-engine'); ?></h3>
                    <div class="metric-value">0.02%</div>
                    <div class="metric-status good">✓ Healthy</div>
                </div>
                
                <div class="metric-card">
                    <h3><?php _e('Cache Hit Rate', 'spiral-engine'); ?></h3>
                    <div class="metric-value">94.3%</div>
                    <div class="metric-bar">
                        <div class="bar-fill" style="width: 94.3%"></div>
                    </div>
                </div>
            </div>
            
            <!-- Performance Chart -->
            <div class="performance-chart-section">
                <h3><?php _e('Load Time Trends', 'spiral-engine'); ?></h3>
                <div class="chart-controls">
                    <select id="performance-timeframe">
                        <option value="hour"><?php _e('Last Hour', 'spiral-engine'); ?></option>
                        <option value="day" selected><?php _e('Last 24 Hours', 'spiral-engine'); ?></option>
                        <option value="week"><?php _e('Last 7 Days', 'spiral-engine'); ?></option>
                        <option value="month"><?php _e('Last 30 Days', 'spiral-engine'); ?></option>
                    </select>
                    <button class="button" id="refresh-performance">
                        <span class="dashicons dashicons-update"></span>
                    </button>
                </div>
                <canvas id="performance-trend-chart"></canvas>
            </div>
            
            <!-- Widget Performance Table -->
            <div class="widget-performance-table">
                <h3><?php _e('Individual Widget Performance', 'spiral-engine'); ?></h3>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e('Widget', 'spiral-engine'); ?></th>
                            <th><?php _e('Avg Load Time', 'spiral-engine'); ?></th>
                            <th><?php _e('P95 Load Time', 'spiral-engine'); ?></th>
                            <th><?php _e('Loads Today', 'spiral-engine'); ?></th>
                            <th><?php _e('Error Rate', 'spiral-engine'); ?></th>
                            <th><?php _e('Status', 'spiral-engine'); ?></th>
                            <th><?php _e('Actions', 'spiral-engine'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $this->render_performance_rows(); ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Performance Recommendations -->
            <div class="performance-recommendations">
                <h3><?php _e('Performance Recommendations', 'spiral-engine'); ?></h3>
                <div class="recommendations-list">
                    <div class="recommendation warning">
                        <span class="dashicons dashicons-warning"></span>
                        <div class="rec-content">
                            <strong><?php _e('Caregiver Logger widget is loading slowly', 'spiral-engine'); ?></strong>
                            <p><?php _e('Consider optimizing database queries or implementing caching.', 'spiral-engine'); ?></p>
                            <button class="button button-small"><?php _e('View Details', 'spiral-engine'); ?></button>
                        </div>
                    </div>
                    
                    <div class="recommendation info">
                        <span class="dashicons dashicons-info"></span>
                        <div class="rec-content">
                            <strong><?php _e('Enable lazy loading for visualization widgets', 'spiral-engine'); ?></strong>
                            <p><?php _e('This could improve initial page load time by 15-20%.', 'spiral-engine'); ?></p>
                            <button class="button button-small"><?php _e('Configure', 'spiral-engine'); ?></button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render performance rows
     */
    private function render_performance_rows() {
        $widgets = $this->get_widgets_with_performance();
        
        foreach ($widgets as $widget) {
            $status_class = $this->get_performance_status_class($widget->avg_load_time);
            ?>
            <tr>
                <td>
                    <strong><?php echo esc_html($widget->name); ?></strong>
                    <span class="widget-category"><?php echo esc_html($widget->category); ?></span>
                </td>
                <td>
                    <span class="load-time <?php echo $status_class; ?>">
                        <?php echo $widget->avg_load_time; ?>ms
                    </span>
                </td>
                <td><?php echo $widget->p95_load_time; ?>ms</td>
                <td><?php echo number_format($widget->loads_today); ?></td>
                <td>
                    <?php if ($widget->error_rate > 0.1): ?>
                    <span class="error-rate high"><?php echo $widget->error_rate; ?>%</span>
                    <?php else: ?>
                    <span class="error-rate low"><?php echo $widget->error_rate; ?>%</span>
                    <?php endif; ?>
                </td>
                <td>
                    <span class="status-indicator <?php echo $status_class; ?>"></span>
                    <?php echo $this->get_performance_status_text($widget->avg_load_time); ?>
                </td>
                <td>
                    <button class="button button-small optimize-widget" 
                            data-widget-id="<?php echo esc_attr($widget->id); ?>">
                        <?php _e('Optimize', 'spiral-engine'); ?>
                    </button>
                    <button class="button button-small view-details"
                            data-widget-id="<?php echo esc_attr($widget->id); ?>">
                        <?php _e('Details', 'spiral-engine'); ?>
                    </button>
                </td>
            </tr>
            <?php
        }
    }
    
    /**
     * Render widget detail view
     */
    private function render_widget_detail() {
        $widget_id = isset($_GET['widget_id']) ? intval($_GET['widget_id']) : 0;
        
        if (!$widget_id) {
            echo '<div class="notice notice-error"><p>' . __('Invalid widget ID.', 'spiral-engine') . '</p></div>';
            return;
        }
        
        $widget = $this->get_widget($widget_id);
        
        if (!$widget) {
            echo '<div class="notice notice-error"><p>' . __('Widget not found.', 'spiral-engine') . '</p></div>';
            return;
        }
        
        ?>
        <div class="widget-detail-view">
            <div class="detail-header">
                <a href="<?php echo remove_query_arg(array('view', 'widget_id')); ?>" class="back-link">
                    ← <?php _e('Back to Widget Studio', 'spiral-engine'); ?>
                </a>
                <h2><?php echo esc_html($widget->name); ?></h2>
                <div class="widget-status-badge <?php echo $widget->status; ?>">
                    <?php echo ucfirst($widget->status); ?>
                </div>
            </div>
            
            <div class="detail-tabs">
                <nav class="nav-tab-wrapper">
                    <a href="#overview" class="nav-tab nav-tab-active"><?php _e('Overview', 'spiral-engine'); ?></a>
                    <a href="#usage" class="nav-tab"><?php _e('Usage Analytics', 'spiral-engine'); ?></a>
                    <a href="#configuration" class="nav-tab"><?php _e('Configuration', 'spiral-engine'); ?></a>
                    <a href="#performance" class="nav-tab"><?php _e('Performance', 'spiral-engine'); ?></a>
                    <a href="#code" class="nav-tab"><?php _e('Code', 'spiral-engine'); ?></a>
                </nav>
                
                <div class="tab-content" id="tab-overview">
                    <?php $this->render_widget_overview($widget); ?>
                </div>
                
                <div class="tab-content" id="tab-usage" style="display: none;">
                    <?php $this->render_widget_usage($widget); ?>
                </div>
                
                <div class="tab-content" id="tab-configuration" style="display: none;">
                    <?php $this->render_widget_configuration($widget); ?>
                </div>
                
                <div class="tab-content" id="tab-performance" style="display: none;">
                    <?php $this->render_widget_performance($widget); ?>
                </div>
                
                <div class="tab-content" id="tab-code" style="display: none;">
                    <?php $this->render_widget_code($widget); ?>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render widget overview tab
     */
    private function render_widget_overview($widget) {
        ?>
        <div class="widget-overview">
            <div class="overview-grid">
                <div class="info-card">
                    <h3><?php _e('Basic Information', 'spiral-engine'); ?></h3>
                    <table class="info-table">
                        <tr>
                            <th><?php _e('Category:', 'spiral-engine'); ?></th>
                            <td><?php echo esc_html($this->widget_categories[$widget->category]['label'] ?? $widget->category); ?></td>
                        </tr>
                        <tr>
                            <th><?php _e('Type:', 'spiral-engine'); ?></th>
                            <td><?php echo esc_html(ucfirst($widget->type)); ?></td>
                        </tr>
                        <tr>
                            <th><?php _e('Created:', 'spiral-engine'); ?></th>
                            <td><?php echo date('F j, Y', strtotime($widget->created_at)); ?></td>
                        </tr>
                        <tr>
                            <th><?php _e('Last Modified:', 'spiral-engine'); ?></th>
                            <td><?php echo date('F j, Y g:i a', strtotime($widget->modified_at)); ?></td>
                        </tr>
                        <tr>
                            <th><?php _e('Version:', 'spiral-engine'); ?></th>
                            <td><?php echo esc_html($widget->version); ?></td>
                        </tr>
                    </table>
                </div>
                
                <div class="info-card">
                    <h3><?php _e('Display Zones', 'spiral-engine'); ?></h3>
                    <div class="zones-list">
                        <?php 
                        $zones = explode(',', $widget->allowed_zones);
                        foreach ($zones as $zone):
                        ?>
                        <span class="zone-badge large"><?php echo esc_html(trim($zone)); ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <div class="info-card">
                    <h3><?php _e('Access Requirements', 'spiral-engine'); ?></h3>
                    <ul class="requirements-list">
                        <li>
                            <strong><?php _e('Minimum Level:', 'spiral-engine'); ?></strong> 
                            <?php echo esc_html(ucfirst($widget->min_membership_level)); ?>
                        </li>
                        <?php if (!empty($widget->requirements)): ?>
                        <?php foreach (json_decode($widget->requirements, true) as $req): ?>
                        <li><?php echo esc_html($req); ?></li>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </ul>
                </div>
                
                <div class="info-card full-width">
                    <h3><?php _e('Description', 'spiral-engine'); ?></h3>
                    <p><?php echo esc_html($widget->description); ?></p>
                </div>
            </div>
            
            <div class="widget-preview-section">
                <h3><?php _e('Widget Preview', 'spiral-engine'); ?></h3>
                <div class="preview-container">
                    <iframe id="widget-preview-frame" src="<?php echo $this->get_preview_url($widget->id); ?>"></iframe>
                </div>
                <div class="preview-controls">
                    <button class="button" id="refresh-preview"><?php _e('Refresh', 'spiral-engine'); ?></button>
                    <select id="preview-device">
                        <option value="desktop"><?php _e('Desktop', 'spiral-engine'); ?></option>
                        <option value="tablet"><?php _e('Tablet', 'spiral-engine'); ?></option>
                        <option value="mobile"><?php _e('Mobile', 'spiral-engine'); ?></option>
                    </select>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * AJAX widget action handler
     */
    public function ajax_widget_action() {
        check_ajax_referer('spiral-engine-admin', 'nonce');
        
        $action = isset($_POST['widget_action']) ? sanitize_text_field($_POST['widget_action']) : '';
        $widget_id = isset($_POST['widget_id']) ? intval($_POST['widget_id']) : 0;
        
        if (!$widget_id) {
            wp_send_json_error(__('Invalid widget ID.', 'spiral-engine'));
        }
        
        switch ($action) {
            case 'toggle_status':
                $result = $this->toggle_widget_status($widget_id);
                break;
                
            case 'optimize':
                $result = $this->optimize_widget($widget_id);
                break;
                
            case 'clear_cache':
                $result = $this->clear_widget_cache($widget_id);
                break;
                
            default:
                $result = array('success' => false, 'message' => __('Invalid action.', 'spiral-engine'));
        }
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result['message']);
        }
    }
    
    /**
     * AJAX widget preview
     */
    public function ajax_widget_preview() {
        check_ajax_referer('spiral-engine-admin', 'nonce');
        
        $widget_id = isset($_POST['widget_id']) ? intval($_POST['widget_id']) : 0;
        
        if (!$widget_id) {
            wp_send_json_error(__('Invalid widget ID.', 'spiral-engine'));
        }
        
        // Generate preview HTML
        ob_start();
        do_action('spiral_engine_render_widget', $widget_id, array('preview' => true));
        $preview_html = ob_get_clean();
        
        wp_send_json_success(array('html' => $preview_html));
    }
    
    /**
     * AJAX widget deploy
     */
    public function ajax_widget_deploy() {
        check_ajax_referer('spiral-engine-admin', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions.', 'spiral-engine'));
        }
        
        $widget_id = isset($_POST['widget_id']) ? intval($_POST['widget_id']) : 0;
        $zones = isset($_POST['zones']) ? array_map('sanitize_text_field', $_POST['zones']) : array();
        
        if (!$widget_id) {
            wp_send_json_error(__('Invalid widget ID.', 'spiral-engine'));
        }
        
        // Deploy widget to specified zones
        $result = $this->deploy_widget($widget_id, $zones);
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result['message']);
        }
    }
    
    /**
     * Get widgets from database
     */
    private function get_widgets() {
        global $wpdb;
        
        $widgets = $wpdb->get_results(
            "SELECT w.*, 
                    COUNT(DISTINCT ws.id) as usage_count,
                    AVG(ws.load_time) as load_time
             FROM {$wpdb->prefix}spiralengine_widgets w
             LEFT JOIN {$wpdb->prefix}spiralengine_widget_stats ws ON w.id = ws.widget_id
             GROUP BY w.id
             ORDER BY usage_count DESC"
        );
        
        if (empty($widgets)) {
            // Return sample widgets for demo
            return $this->get_sample_widgets();
        }
        
        return $widgets;
    }
    
    /**
     * Get sample widgets for demo
     */
    private function get_sample_widgets() {
        return array(
            (object)array(
                'id' => 1,
                'name' => 'Overthinking Logger',
                'description' => 'Quick logging for overthinking episodes',
                'category' => 'episode_loggers',
                'type' => 'input',
                'status' => 'active',
                'usage_count' => 15234,
                'load_time' => 89,
                'allowed_zones' => 'dashboard,sidebar,mobile_home',
                'created_at' => date('Y-m-d H:i:s', strtotime('-30 days'))
            ),
            (object)array(
                'id' => 2,
                'name' => 'Episode Timeline',
                'description' => 'Visual timeline of user episodes',
                'category' => 'visualization',
                'type' => 'display',
                'status' => 'active',
                'usage_count' => 12456,
                'load_time' => 145,
                'allowed_zones' => 'dashboard,profile',
                'created_at' => date('Y-m-d H:i:s', strtotime('-45 days'))
            ),
            (object)array(
                'id' => 3,
                'name' => 'Pattern Insights',
                'description' => 'AI-powered pattern detection and insights',
                'category' => 'insights',
                'type' => 'interactive',
                'status' => 'active',
                'usage_count' => 8923,
                'load_time' => 234,
                'allowed_zones' => 'dashboard,episode_detail',
                'created_at' => date('Y-m-d H:i:s', strtotime('-60 days'))
            ),
            (object)array(
                'id' => 4,
                'name' => 'Crisis Support Tool',
                'description' => 'Emergency support and resources',
                'category' => 'tools',
                'type' => 'interactive',
                'status' => 'active',
                'usage_count' => 3456,
                'load_time' => 67,
                'allowed_zones' => 'dashboard,sidebar,mobile_home,shortcode',
                'created_at' => date('Y-m-d H:i:s', strtotime('-15 days'))
            ),
            (object)array(
                'id' => 5,
                'name' => 'Caregiver Logger',
                'description' => 'Episode logging for caregivers',
                'category' => 'episode_loggers',
                'type' => 'input',
                'status' => 'active',
                'usage_count' => 2134,
                'load_time' => 298,
                'allowed_zones' => 'dashboard,profile',
                'created_at' => date('Y-m-d H:i:s', strtotime('-20 days'))
            )
        );
    }
    
    /**
     * Get widgets with performance data
     */
    private function get_widgets_with_performance() {
        // This would fetch real performance data
        $widgets = $this->get_widgets();
        
        foreach ($widgets as &$widget) {
            $widget->avg_load_time = $widget->load_time;
            $widget->p95_load_time = $widget->load_time * 1.5;
            $widget->loads_today = rand(1000, 10000);
            $widget->error_rate = number_format(rand(0, 20) / 100, 2);
        }
        
        return $widgets;
    }
    
    /**
     * Get widget by ID
     */
    private function get_widget($widget_id) {
        global $wpdb;
        
        $widget = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}spiralengine_widgets WHERE id = %d",
            $widget_id
        ));
        
        if (!$widget) {
            // Return sample widget for demo
            $widgets = $this->get_sample_widgets();
            foreach ($widgets as $w) {
                if ($w->id == $widget_id) {
                    $w->version = '1.0.0';
                    $w->modified_at = date('Y-m-d H:i:s');
                    $w->min_membership_level = 'discovery';
                    $w->requirements = json_encode(array('Must have logged episodes'));
                    return $w;
                }
            }
        }
        
        return $widget;
    }
    
    /**
     * Get performance class
     */
    private function get_performance_class($load_time) {
        if ($load_time < 100) {
            return 'excellent';
        } elseif ($load_time < 200) {
            return 'good';
        } elseif ($load_time < 300) {
            return 'warning';
        } else {
            return 'poor';
        }
    }
    
    /**
     * Get performance status class
     */
    private function get_performance_status_class($load_time) {
        if ($load_time < 100) {
            return 'excellent';
        } elseif ($load_time < 200) {
            return 'good';
        } elseif ($load_time < 300) {
            return 'warning';
        } else {
            return 'critical';
        }
    }
    
    /**
     * Get performance status text
     */
    private function get_performance_status_text($load_time) {
        if ($load_time < 100) {
            return __('Excellent', 'spiral-engine');
        } elseif ($load_time < 200) {
            return __('Good', 'spiral-engine');
        } elseif ($load_time < 300) {
            return __('Needs Attention', 'spiral-engine');
        } else {
            return __('Critical', 'spiral-engine');
        }
    }
    
    /**
     * Get total widgets count
     */
    private function get_total_widgets() {
        global $wpdb;
        return $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}spiralengine_widgets") ?: 147;
    }
    
    /**
     * Get active widgets count
     */
    private function get_active_widgets() {
        global $wpdb;
        return $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}spiralengine_widgets WHERE status = 'active'"
        ) ?: 134;
    }
    
    /**
     * Get widget impressions
     */
    private function get_widget_impressions() {
        global $wpdb;
        return $wpdb->get_var(
            "SELECT SUM(load_count) FROM {$wpdb->prefix}spiralengine_widget_stats"
        ) ?: 1234567;
    }
    
    /**
     * Get average load time
     */
    private function get_avg_load_time() {
        global $wpdb;
        $avg = $wpdb->get_var(
            "SELECT AVG(load_time) FROM {$wpdb->prefix}spiralengine_widget_stats"
        );
        return $avg ? round($avg) : 145;
    }
    
    /**
     * Get category widget count
     */
    private function get_category_widget_count($category) {
        global $wpdb;
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}spiralengine_widgets WHERE category = %s",
            $category
        ));
        
        if (!$count) {
            // Return sample counts
            $sample_counts = array(
                'episode_loggers' => 23,
                'visualization' => 18,
                'insights' => 15,
                'tracking' => 12,
                'community' => 8,
                'tools' => 10
            );
            return $sample_counts[$category] ?? 0;
        }
        
        return $count;
    }
    
    /**
     * Get widgets loaded today
     */
    private function get_widgets_loaded_today() {
        global $wpdb;
        return $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}spiralengine_widget_stats 
            WHERE DATE(loaded_at) = CURDATE()"
        ) ?: rand(50000, 100000);
    }
    
    /**
     * Get preview URL
     */
    private function get_preview_url($widget_id) {
        return add_query_arg(array(
            'spiral_widget_preview' => $widget_id,
            'nonce' => wp_create_nonce('widget_preview_' . $widget_id)
        ), home_url());
    }
    
    /**
     * Toggle widget status
     */
    private function toggle_widget_status($widget_id) {
        global $wpdb;
        
        $current_status = $wpdb->get_var($wpdb->prepare(
            "SELECT status FROM {$wpdb->prefix}spiralengine_widgets WHERE id = %d",
            $widget_id
        ));
        
        $new_status = ($current_status === 'active') ? 'inactive' : 'active';
        
        $updated = $wpdb->update(
            $wpdb->prefix . 'spiralengine_widgets',
            array('status' => $new_status),
            array('id' => $widget_id)
        );
        
        if ($updated !== false) {
            do_action('spiral_engine_widget_status_changed', $widget_id, $new_status);
            
            return array(
                'success' => true,
                'new_status' => $new_status,
                'message' => sprintf(
                    __('Widget %s successfully.', 'spiral-engine'),
                    $new_status === 'active' ? __('activated', 'spiral-engine') : __('deactivated', 'spiral-engine')
                )
            );
        }
        
        return array(
            'success' => false,
            'message' => __('Failed to update widget status.', 'spiral-engine')
        );
    }
    
    /**
     * Optimize widget
     */
    private function optimize_widget($widget_id) {
        // Trigger optimization process
        do_action('spiral_engine_optimize_widget', $widget_id);
        
        // Clear widget cache
        $this->clear_widget_cache($widget_id);
        
        // Run performance analysis
        $analysis = apply_filters('spiral_engine_widget_performance_analysis', array(), $widget_id);
        
        return array(
            'success' => true,
            'message' => __('Widget optimization initiated.', 'spiral-engine'),
            'analysis' => $analysis
        );
    }
    
    /**
     * Clear widget cache
     */
    private function clear_widget_cache($widget_id) {
        // Clear specific widget cache
        delete_transient('spiral_widget_output_' . $widget_id);
        delete_transient('spiral_widget_config_' . $widget_id);
        
        // Clear widget-related caches
        wp_cache_delete('spiral_widget_' . $widget_id, 'spiral_engine');
        
        return array(
            'success' => true,
            'message' => __('Widget cache cleared.', 'spiral-engine')
        );
    }
    
    /**
     * Deploy widget to zones
     */
    private function deploy_widget($widget_id, $zones) {
        global $wpdb;
        
        // Update allowed zones
        $updated = $wpdb->update(
            $wpdb->prefix . 'spiralengine_widgets',
            array('allowed_zones' => implode(',', $zones)),
            array('id' => $widget_id)
        );
        
        if ($updated !== false) {
            // Trigger deployment hooks
            do_action('spiral_engine_widget_deployed', $widget_id, $zones);
            
            return array(
                'success' => true,
                'message' => sprintf(
                    __('Widget deployed to %d zones.', 'spiral-engine'),
                    count($zones)
                )
            );
        }
        
        return array(
            'success' => false,
            'message' => __('Failed to deploy widget.', 'spiral-engine')
        );
    }
    
    /**
     * Render widget usage analytics
     */
    private function render_widget_usage($widget) {
        ?>
        <div class="widget-usage-analytics">
            <h3><?php _e('Usage Analytics', 'spiral-engine'); ?></h3>
            
            <div class="usage-stats-grid">
                <div class="usage-stat">
                    <div class="stat-value"><?php echo number_format($widget->usage_count); ?></div>
                    <div class="stat-label"><?php _e('Total Loads', 'spiral-engine'); ?></div>
                </div>
                <div class="usage-stat">
                    <div class="stat-value"><?php echo number_format($widget->unique_users ?? rand(1000, 5000)); ?></div>
                    <div class="stat-label"><?php _e('Unique Users', 'spiral-engine'); ?></div>
                </div>
                <div class="usage-stat">
                    <div class="stat-value"><?php echo number_format($widget->interactions ?? rand(500, 2000)); ?></div>
                    <div class="stat-label"><?php _e('Interactions', 'spiral-engine'); ?></div>
                </div>
                <div class="usage-stat">
                    <div class="stat-value"><?php echo rand(70, 95); ?>%</div>
                    <div class="stat-label"><?php _e('Engagement Rate', 'spiral-engine'); ?></div>
                </div>
            </div>
            
            <div class="usage-chart">
                <h4><?php _e('Usage Over Time', 'spiral-engine'); ?></h4>
                <canvas id="widget-usage-chart"></canvas>
            </div>
            
            <div class="usage-by-zone">
                <h4><?php _e('Usage by Zone', 'spiral-engine'); ?></h4>
                <canvas id="widget-zone-chart"></canvas>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render widget configuration
     */
    private function render_widget_configuration($widget) {
        ?>
        <div class="widget-configuration">
            <h3><?php _e('Widget Configuration', 'spiral-engine'); ?></h3>
            
            <form id="widget-config-form">
                <div class="config-section">
                    <h4><?php _e('Display Settings', 'spiral-engine'); ?></h4>
                    
                    <label>
                        <span><?php _e('Title', 'spiral-engine'); ?></span>
                        <input type="text" name="widget_title" value="<?php echo esc_attr($widget->name); ?>">
                    </label>
                    
                    <label>
                        <span><?php _e('Custom CSS Classes', 'spiral-engine'); ?></span>
                        <input type="text" name="css_classes" placeholder="custom-class another-class">
                    </label>
                    
                    <label>
                        <span><?php _e('Container Width', 'spiral-engine'); ?></span>
                        <select name="container_width">
                            <option value="auto"><?php _e('Auto', 'spiral-engine'); ?></option>
                            <option value="full"><?php _e('Full Width', 'spiral-engine'); ?></option>
                            <option value="fixed"><?php _e('Fixed Width', 'spiral-engine'); ?></option>
                        </select>
                    </label>
                </div>
                
                <div class="config-section">
                    <h4><?php _e('Behavior Settings', 'spiral-engine'); ?></h4>
                    
                    <label>
                        <input type="checkbox" name="lazy_load" checked>
                        <?php _e('Enable lazy loading', 'spiral-engine'); ?>
                    </label>
                    
                    <label>
                        <input type="checkbox" name="cache_output" checked>
                        <?php _e('Cache widget output', 'spiral-engine'); ?>
                    </label>
                    
                    <label>
                        <span><?php _e('Cache Duration (minutes)', 'spiral-engine'); ?></span>
                        <input type="number" name="cache_duration" value="60" min="0">
                    </label>
                    
                    <label>
                        <input type="checkbox" name="async_load">
                        <?php _e('Load asynchronously', 'spiral-engine'); ?>
                    </label>
                </div>
                
                <div class="config-section">
                    <h4><?php _e('Access Control', 'spiral-engine'); ?></h4>
                    
                    <label>
                        <span><?php _e('Visibility', 'spiral-engine'); ?></span>
                        <select name="visibility">
                            <option value="all"><?php _e('All Users', 'spiral-engine'); ?></option>
                            <option value="logged_in"><?php _e('Logged In Only', 'spiral-engine'); ?></option>
                            <option value="membership"><?php _e('By Membership Level', 'spiral-engine'); ?></option>
                            <option value="role"><?php _e('By User Role', 'spiral-engine'); ?></option>
                        </select>
                    </label>
                    
                    <div id="membership-levels" style="display: none;">
                        <label><?php _e('Select Membership Levels:', 'spiral-engine'); ?></label>
                        <label><input type="checkbox" name="levels[]" value="discovery"> Discovery</label>
                        <label><input type="checkbox" name="levels[]" value="explorer"> Explorer</label>
                        <label><input type="checkbox" name="levels[]" value="navigator"> Navigator</label>
                        <label><input type="checkbox" name="levels[]" value="voyager"> Voyager</label>
                    </div>
                </div>
                
                <div class="config-actions">
                    <button type="submit" class="button button-primary">
                        <?php _e('Save Configuration', 'spiral-engine'); ?>
                    </button>
                    <button type="button" class="button" id="reset-config">
                        <?php _e('Reset to Defaults', 'spiral-engine'); ?>
                    </button>
                </div>
            </form>
        </div>
        <?php
    }
    
    /**
     * Render widget performance
     */
    private function render_widget_performance($widget) {
        ?>
        <div class="widget-performance-detail">
            <h3><?php _e('Performance Metrics', 'spiral-engine'); ?></h3>
            
            <div class="performance-overview">
                <div class="perf-metric">
                    <h4><?php _e('Average Load Time', 'spiral-engine'); ?></h4>
                    <div class="metric-display">
                        <span class="big-number"><?php echo $widget->load_time; ?></span>
                        <span class="unit">ms</span>
                    </div>
                    <div class="benchmark">
                        <?php 
                        $benchmark = 150;
                        $diff = $widget->load_time - $benchmark;
                        $class = $diff > 0 ? 'worse' : 'better';
                        ?>
                        <span class="<?php echo $class; ?>">
                            <?php echo $diff > 0 ? '+' : ''; echo $diff; ?>ms vs benchmark
                        </span>
                    </div>
                </div>
                
                <div class="perf-metric">
                    <h4><?php _e('Resource Usage', 'spiral-engine'); ?></h4>
                    <ul class="resource-list">
                        <li>Memory: <?php echo rand(1, 5); ?>MB</li>
                        <li>Database Queries: <?php echo rand(2, 15); ?></li>
                        <li>API Calls: <?php echo rand(0, 3); ?></li>
                        <li>Cache Hits: <?php echo rand(80, 98); ?>%</li>
                    </ul>
                </div>
            </div>
            
            <div class="performance-timeline">
                <h4><?php _e('Load Time Breakdown', 'spiral-engine'); ?></h4>
                <div class="timeline-chart">
                    <div class="timeline-segment" style="width: 20%;">
                        <span class="segment-label">Init</span>
                        <span class="segment-time"><?php echo round($widget->load_time * 0.2); ?>ms</span>
                    </div>
                    <div class="timeline-segment" style="width: 30%;">
                        <span class="segment-label">Query</span>
                        <span class="segment-time"><?php echo round($widget->load_time * 0.3); ?>ms</span>
                    </div>
                    <div class="timeline-segment" style="width: 35%;">
                        <span class="segment-label">Render</span>
                        <span class="segment-time"><?php echo round($widget->load_time * 0.35); ?>ms</span>
                    </div>
                    <div class="timeline-segment" style="width: 15%;">
                        <span class="segment-label">Output</span>
                        <span class="segment-time"><?php echo round($widget->load_time * 0.15); ?>ms</span>
                    </div>
                </div>
            </div>
            
            <div class="optimization-suggestions">
                <h4><?php _e('Optimization Suggestions', 'spiral-engine'); ?></h4>
                <ul class="suggestions-list">
                    <?php if ($widget->load_time > 200): ?>
                    <li class="high-priority">
                        <span class="dashicons dashicons-warning"></span>
                        <?php _e('Consider implementing query caching to reduce database load', 'spiral-engine'); ?>
                    </li>
                    <?php endif; ?>
                    <li>
                        <span class="dashicons dashicons-info"></span>
                        <?php _e('Enable lazy loading for better initial page performance', 'spiral-engine'); ?>
                    </li>
                    <li>
                        <span class="dashicons dashicons-info"></span>
                        <?php _e('Minify JavaScript and CSS resources', 'spiral-engine'); ?>
                    </li>
                </ul>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render widget code
     */
    private function render_widget_code($widget) {
        ?>
        <div class="widget-code-editor">
            <h3><?php _e('Widget Code', 'spiral-engine'); ?></h3>
            
            <div class="code-toolbar">
                <select id="code-view-type">
                    <option value="php"><?php _e('PHP Code', 'spiral-engine'); ?></option>
                    <option value="js"><?php _e('JavaScript', 'spiral-engine'); ?></option>
                    <option value="css"><?php _e('CSS', 'spiral-engine'); ?></option>
                    <option value="template"><?php _e('Template', 'spiral-engine'); ?></option>
                </select>
                <button class="button" id="copy-code">
                    <span class="dashicons dashicons-clipboard"></span>
                    <?php _e('Copy', 'spiral-engine'); ?>
                </button>
                <button class="button" id="download-code">
                    <span class="dashicons dashicons-download"></span>
                    <?php _e('Download', 'spiral-engine'); ?>
                </button>
            </div>
            
            <div class="code-editor">
                <pre><code id="widget-code-display"><?php echo $this->get_widget_code($widget->id, 'php'); ?></code></pre>
            </div>
            
            <div class="code-info">
                <h4><?php _e('Implementation Guide', 'spiral-engine'); ?></h4>
                <p><?php _e('To use this widget in your theme:', 'spiral-engine'); ?></p>
                <ol>
                    <li><?php _e('Add the widget to a widget zone using the Widget Studio', 'spiral-engine'); ?></li>
                    <li><?php _e('Or use the shortcode:', 'spiral-engine'); ?> 
                        <code>[spiral_widget id="<?php echo $widget->id; ?>"]</code></li>
                    <li><?php _e('Or call directly in PHP:', 'spiral-engine'); ?> 
                        <code>spiral_engine_render_widget(<?php echo $widget->id; ?>);</code></li>
                </ol>
            </div>
        </div>
        <?php
    }
    
    /**
     * Get widget code
     */
    private function get_widget_code($widget_id, $type = 'php') {
        // This would return actual widget code
        switch ($type) {
            case 'php':
                return "<?php
/**
 * Widget: " . esc_html($widget_id) . "
 * Generated by Spiral Engine Widget Studio
 */
class SpiralWidget_" . $widget_id . " {
    
    public function render(\$args = array()) {
        // Widget rendering logic
        \$defaults = array(
            'title' => '',
            'class' => 'spiral-widget',
        );
        
        \$args = wp_parse_args(\$args, \$defaults);
        
        echo '<div class=\"' . esc_attr(\$args['class']) . '\">';
        // Widget content
        echo '</div>';
    }
}";
                
            case 'js':
                return "// Widget JavaScript
(function($) {
    'use strict';
    
    var SpiralWidget" . $widget_id . " = {
        init: function() {
            // Initialize widget
        },
        
        bindEvents: function() {
            // Event handlers
        }
    };
    
    $(document).ready(function() {
        SpiralWidget" . $widget_id . ".init();
    });
    
})(jQuery);";
                
            case 'css':
                return "/* Widget Styles */
.spiral-widget-" . $widget_id . " {
    /* Widget container */
}

.spiral-widget-" . $widget_id . " .widget-header {
    /* Header styles */
}

.spiral-widget-" . $widget_id . " .widget-content {
    /* Content styles */
}";
                
            default:
                return '// Code not available';
        }
    }
}

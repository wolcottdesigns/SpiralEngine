<?php
/**
 * SpiralEngine Dashboard Class
 * 
 * @package    SpiralEngine
 * @subpackage Includes
 * @file       includes/class-dashboard.php
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class SpiralEngine_Dashboard
 * 
 * Handles the frontend dashboard functionality
 */
class SpiralEngine_Dashboard {
    
    /**
     * Single instance of the class
     *
     * @var SpiralEngine_Dashboard
     */
    private static $instance = null;
    
    /**
     * Current user object
     *
     * @var WP_User
     */
    private $user;
    
    /**
     * Current user membership
     *
     * @var array
     */
    private $membership;
    
    /**
     * Membership instance
     *
     * @var SpiralEngine_Membership
     */
    private $membership_class;
    
    /**
     * Active dashboard tab
     *
     * @var string
     */
    private $active_tab;
    
    /**
     * Available dashboard tabs
     *
     * @var array
     */
    private $tabs = array();
    
    /**
     * Dashboard data cache
     *
     * @var array
     */
    private $data_cache = array();
    
    /**
     * Get single instance of the class
     *
     * @return SpiralEngine_Dashboard
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
        $this->init();
    }
    
    /**
     * Initialize dashboard
     */
    private function init() {
        // Set current user
        $this->user = wp_get_current_user();
        
        // Get membership instance
        $this->membership_class = new SpiralEngine_Membership();
        
        // Get user membership - FIXED: Use correct method name
        $this->membership = $this->membership_class->get_membership($this->user->ID);
        
        // Set active tab
        $this->active_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'overview';
        
        // Register default tabs
        $this->register_default_tabs();
        
        // Add hooks
        add_action('init', array($this, 'handle_dashboard_actions'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
        add_shortcode('spiralengine_dashboard', array($this, 'render_dashboard'));
        
        // AJAX handlers
        add_action('wp_ajax_spiralengine_get_dashboard_data', array($this, 'ajax_get_dashboard_data'));
        add_action('wp_ajax_spiralengine_update_dashboard_preferences', array($this, 'ajax_update_preferences'));
        add_action('wp_ajax_spiralengine_refresh_dashboard_section', array($this, 'ajax_refresh_section'));
    }
    
    /**
     * Create free membership for user
     *
     * @param int $user_id
     * @return bool
     */
    private function create_free_membership($user_id) {
        return $this->membership_class->update_tier($user_id, SPIRALENGINE_TIER_FREE);
    }
    
    /**
     * Check if user has required tier
     *
     * @param int $user_id
     * @param string $required_tier
     * @return bool
     */
    private function user_has_tier($user_id, $required_tier) {
        $user_tier = $this->membership_class->get_user_tier($user_id);
        
        $tier_hierarchy = [
            'free' => 0,
            'bronze' => 1,
            'silver' => 2,
            'gold' => 3,
            'platinum' => 4,
            'custom' => 5
        ];
        
        $user_level = isset($tier_hierarchy[$user_tier]) ? $tier_hierarchy[$user_tier] : 0;
        $required_level = isset($tier_hierarchy[$required_tier]) ? $tier_hierarchy[$required_tier] : 0;
        
        return $user_level >= $required_level;
    }
    
    /**
     * Register default dashboard tabs
     */
    private function register_default_tabs() {
        // Overview tab - available to all
        $this->register_tab('overview', array(
            'title' => __('Overview', 'spiralengine'),
            'capability' => 'read',
            'icon' => 'dashicons-dashboard',
            'priority' => 10,
            'template' => 'dashboard-overview.php'
        ));
        
        // Track tab - available to all
        $this->register_tab('track', array(
            'title' => __('Track', 'spiralengine'),
            'capability' => 'read',
            'icon' => 'dashicons-edit',
            'priority' => 20,
            'template' => 'dashboard-tabs/track.php'
        ));
        
        // Insights tab - Silver and above
        $this->register_tab('insights', array(
            'title' => __('Insights', 'spiralengine'),
            'capability' => 'spiralengine_view_insights',
            'icon' => 'dashicons-chart-line',
            'priority' => 30,
            'template' => 'dashboard-tabs/insights.php',
            'min_tier' => 'silver'
        ));
        
        // History tab - available to all
        $this->register_tab('history', array(
            'title' => __('History', 'spiralengine'),
            'capability' => 'read',
            'icon' => 'dashicons-backup',
            'priority' => 40,
            'template' => 'dashboard-tabs/history.php'
        ));
        
        // Goals tab - Silver and above
        $this->register_tab('goals', array(
            'title' => __('Goals', 'spiralengine'),
            'capability' => 'spiralengine_manage_goals',
            'icon' => 'dashicons-flag',
            'priority' => 50,
            'template' => 'dashboard-tabs/goals.php',
            'min_tier' => 'silver'
        ));
        
        // Export tab - available to all
        $this->register_tab('export', array(
            'title' => __('Export', 'spiralengine'),
            'capability' => 'read',
            'icon' => 'dashicons-download',
            'priority' => 60,
            'template' => 'dashboard-tabs/export.php'
        ));
        
        // AI tab - Gold and above
        $this->register_tab('ai', array(
            'title' => __('AI Assistant', 'spiralengine'),
            'capability' => 'spiralengine_use_ai',
            'icon' => 'dashicons-lightbulb',
            'priority' => 70,
            'template' => 'dashboard-tabs/ai.php',
            'min_tier' => 'gold'
        ));
        
        // Settings tab - available to all
        $this->register_tab('settings', array(
            'title' => __('Settings', 'spiralengine'),
            'capability' => 'read',
            'icon' => 'dashicons-admin-generic',
            'priority' => 80,
            'template' => 'dashboard-tabs/settings.php'
        ));
        
        // Upgrade tab - for non-platinum users
        if ($this->membership && $this->membership['tier'] !== 'platinum' && $this->membership['tier'] !== 'custom') {
            $this->register_tab('upgrade', array(
                'title' => __('Upgrade', 'spiralengine'),
                'capability' => 'read',
                'icon' => 'dashicons-star-filled',
                'priority' => 90,
                'template' => 'dashboard-tabs/upgrade.php',
                'highlight' => true
            ));
        }
    }
    
    /**
     * Register a dashboard tab
     *
     * @param string $id Tab ID
     * @param array $args Tab configuration
     */
    public function register_tab($id, $args) {
        $defaults = array(
            'title' => '',
            'capability' => 'read',
            'icon' => 'dashicons-admin-page',
            'priority' => 50,
            'template' => '',
            'callback' => '',
            'min_tier' => 'free',
            'highlight' => false
        );
        
        $args = wp_parse_args($args, $defaults);
        $args['id'] = $id;
        
        // Check if user has required tier - FIXED: Use local method
        if (!$this->user_has_tier($this->user->ID, $args['min_tier'])) {
            return;
        }
        
        // Check capability
        if (!current_user_can($args['capability'])) {
            return;
        }
        
        $this->tabs[$id] = $args;
    }
    
    /**
     * Get sorted tabs
     *
     * @return array
     */
    private function get_sorted_tabs() {
        uasort($this->tabs, function($a, $b) {
            return $a['priority'] - $b['priority'];
        });
        return $this->tabs;
    }
    
    /**
     * Enqueue dashboard assets
     */
    public function enqueue_assets() {
        if (!$this->is_dashboard_page()) {
            return;
        }
        
        // Styles
        wp_enqueue_style(
            'spiralengine-dashboard',
            SPIRALENGINE_PLUGIN_URL . 'assets/css/dashboard.css',
            array('dashicons'),
            SPIRALENGINE_VERSION
        );
        
        // Chart.js for visualizations
        wp_enqueue_script(
            'chartjs',
            'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.js',
            array(),
            '4.4.0',
            true
        );
        
        // Dashboard scripts
        wp_enqueue_script(
            'spiralengine-dashboard',
            SPIRALENGINE_PLUGIN_URL . 'assets/js/dashboard.js',
            array('jquery', 'chartjs', 'wp-api', 'wp-util'),
            SPIRALENGINE_VERSION,
            true
        );
        
        // AI features script if eligible  
        if ($this->user_has_tier($this->user->ID, 'silver')) {
            wp_enqueue_script(
                'spiralengine-ai',
                SPIRALENGINE_PLUGIN_URL . 'js/spiralengine-ai.js',
                array('spiralengine-dashboard'),
                SPIRALENGINE_VERSION,
                true
            );
            
            wp_enqueue_style(
                'spiralengine-ai',
                SPIRALENGINE_PLUGIN_URL . 'css/spiralengine-ai.css',
                array('spiralengine-dashboard'),
                SPIRALENGINE_VERSION
            );
        }
        
        // Localize script
        wp_localize_script('spiralengine-dashboard', 'spiralengine_dashboard', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'api_url' => rest_url('spiralengine/v1'),
            'nonce' => wp_create_nonce('spiralengine_dashboard'),
            'user_id' => $this->user->ID,
            'tier' => $this->membership ? $this->membership['tier'] : 'free',
            'active_tab' => $this->active_tab,
            'tabs' => array_keys($this->tabs),
            'i18n' => array(
                'loading' => __('Loading...', 'spiralengine'),
                'error' => __('An error occurred. Please try again.', 'spiralengine'),
                'saved' => __('Settings saved successfully.', 'spiralengine'),
                'confirm_delete' => __('Are you sure you want to delete this?', 'spiralengine'),
                'no_data' => __('No data available', 'spiralengine')
            )
        ));
    }
    
    /**
     * Render dashboard shortcode
     *
     * @return string
     */
    public function render_dashboard($atts = array()) {
        // Check if user is logged in
        if (!is_user_logged_in()) {
            return $this->render_login_form();
        }
        
        // Check if user has membership, create if not
        if (!$this->membership) {
            $this->create_free_membership($this->user->ID);
            $this->membership = $this->membership_class->get_membership($this->user->ID);
        }
        
        ob_start();
        ?>
        <div id="spiralengine-dashboard" class="spiralengine-dashboard-wrapper" data-tier="<?php echo esc_attr($this->membership['tier']); ?>">
            
            <!-- Dashboard Header -->
            <div class="spiralengine-dashboard-header">
                <div class="dashboard-header-content">
                    <div class="user-info">
                        <div class="user-avatar">
                            <?php echo get_avatar($this->user->ID, 60); ?>
                        </div>
                        <div class="user-details">
                            <h2><?php echo esc_html($this->user->display_name); ?></h2>
                            <div class="user-meta">
                                <span class="user-tier tier-<?php echo esc_attr($this->membership['tier']); ?>">
                                    <?php echo esc_html(ucfirst($this->membership['tier'])); ?> Member
                                </span>
                                <span class="user-joined">
                                    <?php printf(__('Member since %s', 'spiralengine'), 
                                        date_i18n(get_option('date_format'), strtotime($this->membership['created_at']))
                                    ); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="dashboard-actions">
                        <?php $this->render_quick_actions(); ?>
                    </div>
                </div>
            </div>
            
            <!-- Dashboard Navigation -->
            <nav class="spiralengine-dashboard-nav">
                <ul class="dashboard-tabs">
                    <?php foreach ($this->get_sorted_tabs() as $tab_id => $tab): ?>
                        <li class="dashboard-tab <?php echo $this->active_tab === $tab_id ? 'active' : ''; ?> <?php echo $tab['highlight'] ? 'highlight' : ''; ?>">
                            <a href="<?php echo esc_url(add_query_arg('tab', $tab_id)); ?>" 
                               data-tab="<?php echo esc_attr($tab_id); ?>">
                                <span class="dashicons <?php echo esc_attr($tab['icon']); ?>"></span>
                                <span class="tab-title"><?php echo esc_html($tab['title']); ?></span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </nav>
            
            <!-- Dashboard Content -->
            <div class="spiralengine-dashboard-content">
                <div class="dashboard-main">
                    <?php $this->render_tab_content(); ?>
                </div>
                
                <?php if ($this->should_show_sidebar()): ?>
                <aside class="dashboard-sidebar">
                    <?php $this->render_sidebar(); ?>
                </aside>
                <?php endif; ?>
            </div>
            
            <!-- Dashboard Footer -->
            <div class="spiralengine-dashboard-footer">
                <?php $this->render_footer(); ?>
            </div>
            
        </div>
        <?php
        
        return ob_get_clean();
    }
    
    /**
     * Render quick actions
     */
    private function render_quick_actions() {
        ?>
        <button type="button" class="button button-primary quick-track-btn">
            <span class="dashicons dashicons-plus-alt"></span>
            <?php _e('Quick Track', 'spiralengine'); ?>
        </button>
        
        <?php if ($this->user_has_tier($this->user->ID, 'silver')): ?>
        <button type="button" class="button get-insights-btn">
            <span class="dashicons dashicons-lightbulb"></span>
            <?php _e('Get Insights', 'spiralengine'); ?>
        </button>
        <?php endif; ?>
        
        <div class="dropdown-wrapper">
            <button type="button" class="button more-actions-btn">
                <span class="dashicons dashicons-menu"></span>
            </button>
            <div class="dropdown-menu">
                <a href="#" class="refresh-dashboard"><?php _e('Refresh Data', 'spiralengine'); ?></a>
                <a href="#" class="export-current"><?php _e('Export Current View', 'spiralengine'); ?></a>
                <a href="#" class="print-dashboard"><?php _e('Print Dashboard', 'spiralengine'); ?></a>
                <?php if (current_user_can('manage_options')): ?>
                <hr>
                <a href="<?php echo admin_url('admin.php?page=spiralengine'); ?>"><?php _e('Admin Panel', 'spiralengine'); ?></a>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render tab content
     */
    private function render_tab_content() {
        $tab = isset($this->tabs[$this->active_tab]) ? $this->tabs[$this->active_tab] : null;
        
        if (!$tab) {
            echo '<div class="notice notice-error"><p>' . __('Invalid tab selected.', 'spiralengine') . '</p></div>';
            return;
        }
        
        // Use custom callback if provided
        if (!empty($tab['callback']) && is_callable($tab['callback'])) {
            call_user_func($tab['callback']);
            return;
        }
        
        // Load template file
        if (!empty($tab['template'])) {
            $template_path = SPIRALENGINE_PLUGIN_DIR . 'templates/' . $tab['template'];
            
            if ($this->active_tab === 'overview') {
                // Render overview content inline
                $this->render_overview_tab();
            } elseif (file_exists($template_path)) {
                // Make dashboard data available to templates
                $dashboard = $this;
                $user = $this->user;
                $membership = $this->membership;
                
                include $template_path;
            } else {
                echo '<div class="notice notice-error"><p>' . 
                     sprintf(__('Template file not found: %s', 'spiralengine'), $tab['template']) . 
                     '</p></div>';
            }
        }
    }
    
    /**
     * Render overview tab
     */
    private function render_overview_tab() {
        // Get dashboard metrics
        $metrics = $this->get_dashboard_metrics();
        $recent_episodes = $this->get_recent_episodes(5);
        $insights = $this->user_has_tier($this->user->ID, 'silver') ? 
                    $this->get_latest_insights() : array();
        $recommendations = $this->user_has_tier($this->user->ID, 'silver') ? 
                          $this->get_active_recommendations() : array();
        ?>
        
        <div class="dashboard-overview">
            <h2><?php _e('Dashboard Overview', 'spiralengine'); ?></h2>
            
            <!-- Key Metrics -->
            <div class="metrics-grid">
                <div class="metric-card">
                    <div class="metric-icon">
                        <span class="dashicons dashicons-calendar-alt"></span>
                    </div>
                    <div class="metric-content">
                        <h3><?php echo esc_html($metrics['episodes_today']); ?></h3>
                        <p><?php _e('Episodes Today', 'spiralengine'); ?></p>
                    </div>
                </div>
                
                <div class="metric-card">
                    <div class="metric-icon">
                        <span class="dashicons dashicons-chart-line"></span>
                    </div>
                    <div class="metric-content">
                        <h3><?php echo esc_html($metrics['current_streak']); ?></h3>
                        <p><?php _e('Day Streak', 'spiralengine'); ?></p>
                    </div>
                </div>
                
                <div class="metric-card">
                    <div class="metric-icon">
                        <span class="dashicons dashicons-awards"></span>
                    </div>
                    <div class="metric-content">
                        <h3><?php echo esc_html($metrics['total_episodes']); ?></h3>
                        <p><?php _e('Total Episodes', 'spiralengine'); ?></p>
                    </div>
                </div>
                
                <div class="metric-card">
                    <div class="metric-icon">
                        <span class="dashicons dashicons-heart"></span>
                    </div>
                    <div class="metric-content">
                        <h3><?php echo esc_html($metrics['avg_severity']); ?></h3>
                        <p><?php _e('Avg Severity', 'spiralengine'); ?></p>
                    </div>
                </div>
            </div>
            
            <?php if ($this->user_has_tier($this->user->ID, 'silver')): ?>
            <!-- AI Insights Summary -->
            <div class="insights-summary">
                <h3>
                    <span class="dashicons dashicons-lightbulb"></span>
                    <?php _e('Latest Insights', 'spiralengine'); ?>
                </h3>
                <?php if (!empty($insights)): ?>
                    <div class="insights-content">
                        <?php foreach ($insights as $insight): ?>
                            <div class="insight-card">
                                <div class="insight-header">
                                    <span class="insight-type"><?php echo esc_html($insight['type']); ?></span>
                                    <span class="insight-date"><?php echo esc_html($insight['date']); ?></span>
                                </div>
                                <p><?php echo esc_html($insight['summary']); ?></p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="no-data"><?php _e('No insights available yet. Keep tracking to generate insights!', 'spiralengine'); ?></p>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <!-- Recent Activity -->
            <div class="recent-activity">
                <h3>
                    <span class="dashicons dashicons-clock"></span>
                    <?php _e('Recent Episodes', 'spiralengine'); ?>
                </h3>
                <?php if (!empty($recent_episodes)): ?>
                    <div class="episodes-timeline">
                        <?php foreach ($recent_episodes as $episode): ?>
                            <div class="timeline-item">
                                <div class="timeline-marker severity-<?php echo esc_attr($episode->severity); ?>"></div>
                                <div class="timeline-content">
                                    <h4><?php echo esc_html($episode->widget_name); ?></h4>
                                    <p><?php echo esc_html($episode->summary); ?></p>
                                    <span class="timeline-date">
                                        <?php echo esc_html(human_time_diff(strtotime($episode->created_at), current_time('timestamp'))); ?> ago
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <a href="<?php echo esc_url(add_query_arg('tab', 'history')); ?>" class="view-all-link">
                        <?php _e('View All Episodes', 'spiralengine'); ?> →
                    </a>
                <?php else: ?>
                    <p class="no-data"><?php _e('No episodes tracked yet. Start tracking to see your activity!', 'spiralengine'); ?></p>
                    <a href="<?php echo esc_url(add_query_arg('tab', 'track')); ?>" class="button button-primary">
                        <?php _e('Track Your First Episode', 'spiralengine'); ?>
                    </a>
                <?php endif; ?>
            </div>
            
            <?php if ($this->user_has_tier($this->user->ID, 'silver')): ?>
            <!-- Active Recommendations -->
            <div class="active-recommendations">
                <h3>
                    <span class="dashicons dashicons-star-filled"></span>
                    <?php _e('Recommendations', 'spiralengine'); ?>
                </h3>
                <?php if (!empty($recommendations)): ?>
                    <div class="recommendations-grid">
                        <?php foreach ($recommendations as $rec): ?>
                            <div class="recommendation-card" data-id="<?php echo esc_attr($rec['id']); ?>">
                                <div class="rec-header">
                                    <span class="rec-type"><?php echo esc_html($rec['type']); ?></span>
                                    <span class="rec-priority priority-<?php echo esc_attr($rec['priority']); ?>">
                                        <?php echo esc_html($rec['priority']); ?>
                                    </span>
                                </div>
                                <h4><?php echo esc_html($rec['title']); ?></h4>
                                <p><?php echo esc_html($rec['description']); ?></p>
                                <div class="rec-actions">
                                    <button class="apply-recommendation" data-id="<?php echo esc_attr($rec['id']); ?>">
                                        <?php _e('Apply', 'spiralengine'); ?>
                                    </button>
                                    <button class="dismiss-recommendation" data-id="<?php echo esc_attr($rec['id']); ?>">
                                        <?php _e('Dismiss', 'spiralengine'); ?>
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="no-data"><?php _e('No active recommendations.', 'spiralengine'); ?></p>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <!-- Progress Charts -->
            <div class="progress-section">
                <h3>
                    <span class="dashicons dashicons-chart-area"></span>
                    <?php _e('Your Progress', 'spiralengine'); ?>
                </h3>
                <div class="charts-grid">
                    <div class="chart-container">
                        <canvas id="severity-trend-chart"></canvas>
                    </div>
                    <div class="chart-container">
                        <canvas id="episode-frequency-chart"></canvas>
                    </div>
                </div>
            </div>
            
        </div>
        <?php
    }
    
    /**
     * Check if sidebar should be shown
     *
     * @return bool
     */
    private function should_show_sidebar() {
        $sidebar_tabs = array('overview', 'track', 'insights');
        return in_array($this->active_tab, $sidebar_tabs);
    }
    
    /**
     * Render sidebar
     */
    private function render_sidebar() {
        ?>
        <div class="sidebar-widgets">
            <?php
            // Quick Stats Widget
            $this->render_quick_stats_widget();
            
            // Alerts Widget (Silver+)
            if ($this->user_has_tier($this->user->ID, 'silver')) {
                $this->render_alerts_widget();
            }
            
            // Goals Progress Widget (Silver+)
            if ($this->user_has_tier($this->user->ID, 'silver')) {
                $this->render_goals_widget();
            }
            
            // Resources Widget
            $this->render_resources_widget();
            ?>
        </div>
        <?php
    }
    
    /**
     * Render quick stats widget
     */
    private function render_quick_stats_widget() {
        $stats = $this->get_quick_stats();
        ?>
        <div class="sidebar-widget widget-quick-stats">
            <h3><?php _e('Quick Stats', 'spiralengine'); ?></h3>
            <ul class="stats-list">
                <li>
                    <span class="stat-label"><?php _e('This Week:', 'spiralengine'); ?></span>
                    <span class="stat-value"><?php echo esc_html($stats['episodes_week']); ?> episodes</span>
                </li>
                <li>
                    <span class="stat-label"><?php _e('This Month:', 'spiralengine'); ?></span>
                    <span class="stat-value"><?php echo esc_html($stats['episodes_month']); ?> episodes</span>
                </li>
                <li>
                    <span class="stat-label"><?php _e('Best Streak:', 'spiralengine'); ?></span>
                    <span class="stat-value"><?php echo esc_html($stats['best_streak']); ?> days</span>
                </li>
                <li>
                    <span class="stat-label"><?php _e('Top Trigger:', 'spiralengine'); ?></span>
                    <span class="stat-value"><?php echo esc_html($stats['top_trigger']); ?></span>
                </li>
            </ul>
        </div>
        <?php
    }
    
    /**
     * Render alerts widget
     */
    private function render_alerts_widget() {
        $alerts = $this->get_active_alerts();
        ?>
        <div class="sidebar-widget widget-alerts">
            <h3><?php _e('Active Alerts', 'spiralengine'); ?></h3>
            <?php if (!empty($alerts)): ?>
                <div class="alerts-list">
                    <?php foreach ($alerts as $alert): ?>
                        <div class="alert-item priority-<?php echo esc_attr($alert['priority']); ?>">
                            <div class="alert-icon">
                                <span class="dashicons dashicons-warning"></span>
                            </div>
                            <div class="alert-content">
                                <p><?php echo esc_html($alert['message']); ?></p>
                                <div class="alert-actions">
                                    <a href="#" class="dismiss-alert" data-id="<?php echo esc_attr($alert['id']); ?>">
                                        <?php _e('Dismiss', 'spiralengine'); ?>
                                    </a>
                                    <a href="#" class="snooze-alert" data-id="<?php echo esc_attr($alert['id']); ?>">
                                        <?php _e('Snooze', 'spiralengine'); ?>
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="no-alerts"><?php _e('No active alerts.', 'spiralengine'); ?></p>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Render goals widget
     */
    private function render_goals_widget() {
        $goals = $this->get_active_goals();
        ?>
        <div class="sidebar-widget widget-goals">
            <h3><?php _e('Active Goals', 'spiralengine'); ?></h3>
            <?php if (!empty($goals)): ?>
                <div class="goals-list">
                    <?php foreach ($goals as $goal): ?>
                        <div class="goal-item">
                            <h4><?php echo esc_html($goal['title']); ?></h4>
                            <div class="goal-progress">
                                <div class="progress-bar">
                                    <div class="progress-fill" style="width: <?php echo esc_attr($goal['progress']); ?>%"></div>
                                </div>
                                <span class="progress-text"><?php echo esc_html($goal['progress']); ?>%</span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <a href="<?php echo esc_url(add_query_arg('tab', 'goals')); ?>" class="view-all-goals">
                    <?php _e('Manage Goals', 'spiralengine'); ?> →
                </a>
            <?php else: ?>
                <p class="no-goals"><?php _e('No active goals.', 'spiralengine'); ?></p>
                <a href="<?php echo esc_url(add_query_arg('tab', 'goals')); ?>" class="button">
                    <?php _e('Create Goal', 'spiralengine'); ?>
                </a>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Render resources widget
     */
    private function render_resources_widget() {
        ?>
        <div class="sidebar-widget widget-resources">
            <h3><?php _e('Resources', 'spiralengine'); ?></h3>
            <ul class="resources-list">
                <li>
                    <a href="#" class="resource-link" data-resource="getting-started">
                        <span class="dashicons dashicons-welcome-learn-more"></span>
                        <?php _e('Getting Started Guide', 'spiralengine'); ?>
                    </a>
                </li>
                <li>
                    <a href="#" class="resource-link" data-resource="crisis-support">
                        <span class="dashicons dashicons-sos"></span>
                        <?php _e('Crisis Support', 'spiralengine'); ?>
                    </a>
                </li>
                <li>
                    <a href="#" class="resource-link" data-resource="community">
                        <span class="dashicons dashicons-groups"></span>
                        <?php _e('Community Forum', 'spiralengine'); ?>
                    </a>
                </li>
                <li>
                    <a href="#" class="resource-link" data-resource="help">
                        <span class="dashicons dashicons-editor-help"></span>
                        <?php _e('Help & Support', 'spiralengine'); ?>
                    </a>
                </li>
            </ul>
        </div>
        <?php
    }
    
    /**
     * Render dashboard footer
     */
    private function render_footer() {
        ?>
        <div class="footer-content">
            <div class="footer-left">
                <p><?php _e('© 2024 SpiralEngine. Your data is private and secure.', 'spiralengine'); ?></p>
            </div>
            <div class="footer-right">
                <a href="#" class="footer-link" data-modal="privacy"><?php _e('Privacy Policy', 'spiralengine'); ?></a>
                <a href="#" class="footer-link" data-modal="terms"><?php _e('Terms of Service', 'spiralengine'); ?></a>
                <a href="#" class="footer-link" data-modal="contact"><?php _e('Contact Support', 'spiralengine'); ?></a>
            </div>
        </div>
        <?php
    }
    
    /**
     * Get dashboard metrics
     *
     * @return array
     */
    private function get_dashboard_metrics() {
        global $wpdb;
        
        $cache_key = 'spiralengine_dashboard_metrics_' . $this->user->ID;
        $metrics = get_transient($cache_key);
        
        if (false === $metrics) {
            $table_name = $wpdb->prefix . 'spiralengine_episodes';
            
            // Episodes today
            $episodes_today = $wpdb->get_var($wpdb->prepare("
                SELECT COUNT(*) FROM {$table_name}
                WHERE user_id = %d AND DATE(created_at) = CURDATE()
            ", $this->user->ID));
            
            // Total episodes
            $total_episodes = $wpdb->get_var($wpdb->prepare("
                SELECT COUNT(*) FROM {$table_name}
                WHERE user_id = %d
            ", $this->user->ID));
            
            // Average severity
            $avg_severity = $wpdb->get_var($wpdb->prepare("
                SELECT AVG(severity) FROM {$table_name}
                WHERE user_id = %d
            ", $this->user->ID));
            
            // Current streak
            $current_streak = $this->calculate_current_streak();
            
            $metrics = array(
                'episodes_today' => intval($episodes_today),
                'total_episodes' => intval($total_episodes),
                'avg_severity' => round(floatval($avg_severity), 1),
                'current_streak' => $current_streak
            );
            
            set_transient($cache_key, $metrics, HOUR_IN_SECONDS);
        }
        
        return $metrics;
    }
    
    /**
     * Calculate current tracking streak
     *
     * @return int
     */
    private function calculate_current_streak() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'spiralengine_episodes';
        
        $dates = $wpdb->get_col($wpdb->prepare("
            SELECT DISTINCT DATE(created_at) as track_date
            FROM {$table_name}
            WHERE user_id = %d
            ORDER BY track_date DESC
            LIMIT 30
        ", $this->user->ID));
        
        if (empty($dates)) {
            return 0;
        }
        
        $streak = 0;
        $current_date = new DateTime();
        $current_date->setTime(0, 0, 0);
        
        foreach ($dates as $date) {
            $track_date = new DateTime($date);
            $diff = $current_date->diff($track_date)->days;
            
            if ($diff === $streak) {
                $streak++;
                $current_date->modify('-1 day');
            } else {
                break;
            }
        }
        
        return $streak;
    }
    
    /**
     * Get recent episodes
     *
     * @param int $limit
     * @return array
     */
    private function get_recent_episodes($limit = 5) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'spiralengine_episodes';
        
        $episodes = $wpdb->get_results($wpdb->prepare("
            SELECT * FROM {$table_name}
            WHERE user_id = %d
            ORDER BY created_at DESC
            LIMIT %d
        ", $this->user->ID, $limit));
        
        // Process episodes to add widget names and summaries
        foreach ($episodes as &$episode) {
            // Get widget from core
            $core = SpiralEngine_Core::get_instance();
            $widget = $core->get_widget($episode->widget_id);
            $episode->widget_name = $widget ? $widget->get_name() : $episode->widget_id;
            
            $data = json_decode($episode->data, true);
            $episode->summary = $this->generate_episode_summary($episode->widget_id, $data);
        }
        
        return $episodes;
    }
    
    /**
     * Generate episode summary
     *
     * @param string $widget_id
     * @param array $data
     * @return string
     */
    private function generate_episode_summary($widget_id, $data) {
        // This would be customized per widget type
        $summary = '';
        
        switch ($widget_id) {
            case 'overthinking-logger':
                $summary = isset($data['thought']) ? 
                    wp_trim_words($data['thought'], 10) : 
                    __('Overthinking episode tracked', 'spiralengine');
                break;
                
            case 'mood-tracker':
                $summary = isset($data['mood']) ? 
                    sprintf(__('Mood: %s', 'spiralengine'), $data['mood']) : 
                    __('Mood tracked', 'spiralengine');
                break;
                
            default:
                $summary = __('Episode tracked', 'spiralengine');
        }
        
        return $summary;
    }
    
    /**
     * Get latest insights
     *
     * @return array
     */
    private function get_latest_insights() {
        // TODO: Implement when insights table is created
        // For now, return mock data
        return array();
    }
    
    /**
     * Get active recommendations
     *
     * @return array
     */
    private function get_active_recommendations() {
        // TODO: Implement when AI recommendations are available
        // For now, return mock data
        return array();
    }
    
    /**
     * Get quick stats
     *
     * @return array
     */
    private function get_quick_stats() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'spiralengine_episodes';
        
        // Episodes this week
        $episodes_week = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) FROM {$table_name}
            WHERE user_id = %d AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        ", $this->user->ID));
        
        // Episodes this month
        $episodes_month = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) FROM {$table_name}
            WHERE user_id = %d AND MONTH(created_at) = MONTH(CURRENT_DATE())
            AND YEAR(created_at) = YEAR(CURRENT_DATE())
        ", $this->user->ID));
        
        // Best streak (stored in user meta)
        $best_streak = get_user_meta($this->user->ID, 'spiralengine_best_streak', true) ?: 0;
        
        // Top trigger
        $top_trigger = $wpdb->get_var($wpdb->prepare("
            SELECT JSON_EXTRACT(data, '$.trigger') as trigger
            FROM {$table_name}
            WHERE user_id = %d AND JSON_EXTRACT(data, '$.trigger') IS NOT NULL
            GROUP BY trigger
            ORDER BY COUNT(*) DESC
            LIMIT 1
        ", $this->user->ID)) ?: __('None identified', 'spiralengine');
        
        return array(
            'episodes_week' => intval($episodes_week),
            'episodes_month' => intval($episodes_month),
            'best_streak' => intval($best_streak),
            'top_trigger' => trim($top_trigger, '"')
        );
    }
    
    /**
     * Get active alerts
     *
     * @return array
     */
    private function get_active_alerts() {
        if (!$this->user_has_tier($this->user->ID, 'silver')) {
            return array();
        }
        
        // TODO: Implement when predictive alerts are available
        // For now, return empty array
        return array();
    }
    
    /**
     * Get active goals
     *
     * @return array
     */
    private function get_active_goals() {
        if (!$this->user_has_tier($this->user->ID, 'silver')) {
            return array();
        }
        
        // TODO: Implement when goals table is created
        // For now, return empty array
        return array();
    }
    
    /**
     * Calculate goal progress
     *
     * @param int $goal_id
     * @param array $goal_data
     * @return int
     */
    private function calculate_goal_progress($goal_id, $goal_data) {
        // This would be implemented based on goal type and milestones
        // For now, return a random progress for demonstration
        return rand(10, 90);
    }
    
    /**
     * Handle dashboard actions
     */
    public function handle_dashboard_actions() {
        if (!isset($_POST['spiralengine_action'])) {
            return;
        }
        
        $action = sanitize_key($_POST['spiralengine_action']);
        
        // Verify nonce
        if (!wp_verify_nonce($_POST['_wpnonce'], 'spiralengine_dashboard_' . $action)) {
            return;
        }
        
        switch ($action) {
            case 'update_preferences':
                $this->handle_update_preferences();
                break;
                
            case 'export_data':
                $this->handle_export_data();
                break;
                
            // Add more action handlers as needed
        }
    }
    
    /**
     * AJAX: Get dashboard data
     */
    public function ajax_get_dashboard_data() {
        check_ajax_referer('spiralengine_dashboard', 'nonce');
        
        $section = isset($_POST['section']) ? sanitize_key($_POST['section']) : 'all';
        $data = array();
        
        switch ($section) {
            case 'metrics':
                $data = $this->get_dashboard_metrics();
                break;
                
            case 'recent_episodes':
                $data = $this->get_recent_episodes(10);
                break;
                
            case 'insights':
                $data = $this->get_latest_insights();
                break;
                
            case 'recommendations':
                $data = $this->get_active_recommendations();
                break;
                
            case 'all':
            default:
                $data = array(
                    'metrics' => $this->get_dashboard_metrics(),
                    'recent_episodes' => $this->get_recent_episodes(5),
                    'insights' => $this->get_latest_insights(),
                    'recommendations' => $this->get_active_recommendations()
                );
        }
        
        wp_send_json_success($data);
    }
    
    /**
     * AJAX: Update dashboard preferences
     */
    public function ajax_update_preferences() {
        check_ajax_referer('spiralengine_dashboard', 'nonce');
        
        $preferences = isset($_POST['preferences']) ? wp_unslash($_POST['preferences']) : array();
        
        // Validate and sanitize preferences
        $allowed_preferences = array('theme', 'layout', 'widgets', 'notifications');
        $clean_preferences = array();
        
        foreach ($allowed_preferences as $key) {
            if (isset($preferences[$key])) {
                $clean_preferences[$key] = sanitize_text_field($preferences[$key]);
            }
        }
        
        // Save preferences
        update_user_meta($this->user->ID, 'spiralengine_dashboard_preferences', $clean_preferences);
        
        wp_send_json_success(array(
            'message' => __('Preferences updated successfully.', 'spiralengine')
        ));
    }
    
    /**
     * AJAX: Refresh dashboard section
     */
    public function ajax_refresh_section() {
        check_ajax_referer('spiralengine_dashboard', 'nonce');
        
        $section = isset($_POST['section']) ? sanitize_key($_POST['section']) : '';
        
        if (empty($section)) {
            wp_send_json_error(__('Invalid section.', 'spiralengine'));
        }
        
        ob_start();
        
        switch ($section) {
            case 'metrics':
                $this->render_metrics_section();
                break;
                
            case 'recent_activity':
                $this->render_recent_activity_section();
                break;
                
            case 'insights':
                $this->render_insights_section();
                break;
                
            default:
                wp_send_json_error(__('Unknown section.', 'spiralengine'));
        }
        
        $html = ob_get_clean();
        
        wp_send_json_success(array(
            'html' => $html
        ));
    }
    
    /**
     * Check if current page is dashboard
     *
     * @return bool
     */
    private function is_dashboard_page() {
        global $post;
        
        if (!$post) {
            return false;
        }
        
        return has_shortcode($post->post_content, 'spiralengine_dashboard');
    }
    
    /**
     * Render login form
     *
     * @return string
     */
    private function render_login_form() {
        ob_start();
        ?>
        <div class="spiralengine-login-prompt">
            <h2><?php _e('Please Log In', 'spiralengine'); ?></h2>
            <p><?php _e('You need to be logged in to access your mental health dashboard.', 'spiralengine'); ?></p>
            <?php
            wp_login_form(array(
                'redirect' => get_permalink(),
                'form_id' => 'spiralengine-login-form',
                'label_log_in' => __('Access Dashboard', 'spiralengine')
            ));
            ?>
            <p class="register-link">
                <?php _e("Don't have an account?", 'spiralengine'); ?>
                <a href="<?php echo wp_registration_url(); ?>"><?php _e('Register here', 'spiralengine'); ?></a>
            </p>
        </div>
        <?php
        return ob_get_clean();
    }
}

// Initialize dashboard
SpiralEngine_Dashboard::get_instance();

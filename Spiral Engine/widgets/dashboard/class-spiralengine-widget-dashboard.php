<?php
/**
 * Spiral Engine - Member Dashboard Widget
 * 
 * @package     SpiralEngine
 * @subpackage  Widgets/Dashboard
 * @since       1.0.0
 * 
 * File: includes/widgets/dashboard/class-spiralengine-widget-dashboard.php
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Member Dashboard Widget Class
 * 
 * Provides the complete personalized dashboard experience with episode tracking,
 * widget previews, and real-time updates.
 * 
 * @since 1.0.0
 */
class SpiralEngine_Widget_Dashboard extends SpiralEngine_Widget_Base {
    
    /**
     * Widget configuration
     */
    protected $widget_id = 'member_dashboard';
    protected $widget_name = 'Member Dashboard';
    protected $widget_desc = 'Complete personalized dashboard with unified episode tracking';
    protected $widget_icon = 'dashicons-dashboard';
    
    /**
     * Widget settings
     */
    protected $locations = array('page'); // Full page widget only
    protected $widget_type = 'full'; // Not preview - this IS the dashboard
    protected $required_membership = array(); // Available to all logged-in users
    protected $requires_api = array('episode_framework'); // Requires episode data access
    
    /**
     * Episode integration flags
     */
    protected $integrates_episodes = true;
    protected $supports_correlations = true;
    protected $supports_unified_forecast = true;
    
    /**
     * Component instances
     */
    private $preview_loader;
    private $grid_manager;
    private $settings_manager;
    private $episode_aggregator;
    private $correlation_display;
    private $forecast_display;
    
    /**
     * Constructor
     */
    public function __construct() {
        parent::__construct();
        
        // Initialize components
        $this->init_components();
        
        // Register AJAX handlers
        add_action('wp_ajax_spiralengine_dashboard_save_layout', array($this, 'ajax_save_layout'));
        add_action('wp_ajax_spiralengine_dashboard_get_timeline', array($this, 'ajax_get_timeline'));
        add_action('wp_ajax_spiralengine_dashboard_filter_episodes', array($this, 'ajax_filter_episodes'));
        add_action('wp_ajax_spiralengine_dashboard_get_correlations', array($this, 'ajax_get_correlations'));
        add_action('wp_ajax_spiralengine_dashboard_refresh_forecast', array($this, 'ajax_refresh_forecast'));
        add_action('wp_ajax_spiralengine_dashboard_quick_log', array($this, 'ajax_quick_log'));
        add_action('wp_ajax_spiralengine_dashboard_export_data', array($this, 'ajax_export_data'));
        
        // Enqueue assets
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
    }
    
    /**
     * Initialize component instances
     */
    private function init_components() {
        require_once SPIRALENGINE_PATH . 'includes/widgets/dashboard/components/class-preview-widget-loader.php';
        require_once SPIRALENGINE_PATH . 'includes/widgets/dashboard/components/class-grid-manager.php';
        require_once SPIRALENGINE_PATH . 'includes/widgets/dashboard/components/class-settings-manager.php';
        require_once SPIRALENGINE_PATH . 'includes/widgets/dashboard/components/class-episode-aggregator.php';
        require_once SPIRALENGINE_PATH . 'includes/widgets/dashboard/components/class-correlation-display.php';
        require_once SPIRALENGINE_PATH . 'includes/widgets/dashboard/components/class-forecast-display.php';
        
        $this->preview_loader = new SpiralEngine_Preview_Widget_Loader();
        $this->grid_manager = new SpiralEngine_Grid_Manager();
        $this->settings_manager = new SpiralEngine_Dashboard_Settings_Manager();
        $this->episode_aggregator = new SpiralEngine_Episode_Aggregator();
        $this->correlation_display = new SpiralEngine_Correlation_Display();
        $this->forecast_display = new SpiralEngine_Forecast_Display();
    }
    
    /**
     * Enqueue widget assets
     */
    public function enqueue_assets() {
        if (!$this->is_widget_page()) {
            return;
        }
        
        // Styles
        wp_enqueue_style(
            'spiralengine-dashboard-widget',
            SPIRALENGINE_URL . 'assets/css/spiralengine-dashboard-widget.css',
            array(),
            SPIRALENGINE_VERSION
        );
        
        // Scripts
        wp_enqueue_script(
            'spiralengine-dashboard-widget',
            SPIRALENGINE_URL . 'assets/js/spiralengine-dashboard-widget.js',
            array('jquery', 'jquery-ui-sortable', 'chart-js'),
            SPIRALENGINE_VERSION,
            true
        );
        
        // Localize script
        wp_localize_script('spiralengine-dashboard-widget', 'spiralDashboard', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('spiralengine_dashboard_nonce'),
            'user_id' => get_current_user_id(),
            'strings' => array(
                'save_success' => __('Layout saved successfully', 'spiralengine'),
                'save_error' => __('Error saving layout', 'spiralengine'),
                'loading' => __('Loading...', 'spiralengine'),
                'confirm_reset' => __('Are you sure you want to reset your dashboard layout?', 'spiralengine'),
                'export_success' => __('Data exported successfully', 'spiralengine'),
                'quick_log_success' => __('Episode logged successfully', 'spiralengine')
            )
        ));
        
        // Chart.js for visualizations
        wp_enqueue_script('chart-js', 'https://cdn.jsdelivr.net/npm/chart.js', array(), '3.9.1');
    }
    
    /**
     * Render the widget
     */
    public function render($args = array()) {
        // Check if user is logged in
        if (!is_user_logged_in()) {
            $this->render_login_prompt();
            return;
        }
        
        // Get user data
        $user_id = get_current_user_id();
        $user_settings = $this->settings_manager->get_user_settings($user_id);
        $layout = $this->grid_manager->get_user_layout($user_id);
        
        // Get dashboard data
        $dashboard_data = $this->get_dashboard_data($user_id);
        
        // Include main template
        include SPIRALENGINE_PATH . 'templates/widgets/dashboard/dashboard-main.php';
    }
    
    /**
     * Get all dashboard data
     */
    private function get_dashboard_data($user_id) {
        return array(
            'user_stats' => $this->get_user_stats($user_id),
            'spiral_score' => $this->get_spiral_score($user_id),
            'recent_episodes' => $this->episode_aggregator->get_dashboard_episodes($user_id, 7),
            'correlations' => $this->episode_aggregator->get_correlation_insights($user_id),
            'forecast' => $this->episode_aggregator->get_unified_forecast($user_id),
            'achievements' => $this->get_user_achievements($user_id),
            'preview_widgets' => $this->preview_loader->get_preview_widgets($user_id),
            'membership_level' => $this->get_user_membership_level($user_id)
        );
    }
    
    /**
     * Get user statistics
     */
    private function get_user_stats($user_id) {
        global $wpdb;
        
        $stats = array(
            'total_episodes' => 0,
            'episodes_this_week' => 0,
            'episodes_this_month' => 0,
            'spiral_assessments' => 0,
            'active_days' => 0,
            'current_streak' => 0,
            'longest_streak' => 0,
            'member_since' => get_user_meta($user_id, 'spiralengine_member_since', true)
        );
        
        // Get episode counts
        $stats['total_episodes'] = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) FROM {$wpdb->prefix}spiralengine_episodes 
            WHERE user_id = %d
        ", $user_id));
        
        // Episodes this week
        $stats['episodes_this_week'] = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) FROM {$wpdb->prefix}spiralengine_episodes 
            WHERE user_id = %d AND episode_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        ", $user_id));
        
        // Episodes this month
        $stats['episodes_this_month'] = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) FROM {$wpdb->prefix}spiralengine_episodes 
            WHERE user_id = %d AND MONTH(episode_date) = MONTH(CURRENT_DATE())
            AND YEAR(episode_date) = YEAR(CURRENT_DATE())
        ", $user_id));
        
        // Calculate streaks
        $this->calculate_user_streaks($user_id, $stats);
        
        return $stats;
    }
    
    /**
     * Get SPIRAL score data
     */
    private function get_spiral_score($user_id) {
        global $wpdb;
        
        // Get latest assessment
        $latest = $wpdb->get_row($wpdb->prepare("
            SELECT * FROM {$wpdb->prefix}spiralengine_assessments 
            WHERE user_id = %d 
            ORDER BY assessment_date DESC 
            LIMIT 1
        ", $user_id));
        
        if (!$latest) {
            return null;
        }
        
        // Get score history for chart
        $history = $wpdb->get_results($wpdb->prepare("
            SELECT spiral_score, assessment_date 
            FROM {$wpdb->prefix}spiralengine_assessments 
            WHERE user_id = %d 
            ORDER BY assessment_date DESC 
            LIMIT 10
        ", $user_id));
        
        return array(
            'current_score' => $latest->spiral_score,
            'previous_score' => isset($history[1]) ? $history[1]->spiral_score : null,
            'assessment_date' => $latest->assessment_date,
            'history' => array_reverse($history),
            'components' => json_decode($latest->component_scores, true)
        );
    }
    
    /**
     * Get user achievements
     */
    private function get_user_achievements($user_id) {
        global $wpdb;
        
        $achievements = $wpdb->get_results($wpdb->prepare("
            SELECT a.*, ua.earned_date 
            FROM {$wpdb->prefix}spiralengine_achievements a
            LEFT JOIN {$wpdb->prefix}spiralengine_user_achievements ua 
                ON a.achievement_id = ua.achievement_id AND ua.user_id = %d
            WHERE a.is_active = 1
            ORDER BY ua.earned_date DESC, a.display_order ASC
        ", $user_id));
        
        // Separate earned and available
        $earned = array();
        $available = array();
        
        foreach ($achievements as $achievement) {
            if ($achievement->earned_date) {
                $earned[] = $achievement;
            } else {
                $available[] = $achievement;
            }
        }
        
        return array(
            'earned' => $earned,
            'available' => $available,
            'total' => count($achievements),
            'earned_count' => count($earned)
        );
    }
    
    /**
     * AJAX handler: Save dashboard layout
     */
    public function ajax_save_layout() {
        check_ajax_referer('spiralengine_dashboard_nonce', 'nonce');
        
        $user_id = get_current_user_id();
        $layout = isset($_POST['layout']) ? $_POST['layout'] : array();
        
        $result = $this->grid_manager->save_user_layout($user_id, $layout);
        
        wp_send_json_success(array(
            'message' => __('Layout saved successfully', 'spiralengine')
        ));
    }
    
    /**
     * AJAX handler: Get episode timeline data
     */
    public function ajax_get_timeline() {
        check_ajax_referer('spiralengine_dashboard_nonce', 'nonce');
        
        $user_id = get_current_user_id();
        $days = isset($_POST['days']) ? intval($_POST['days']) : 30;
        $type = isset($_POST['type']) ? sanitize_text_field($_POST['type']) : 'all';
        
        $episodes = $this->episode_aggregator->get_dashboard_episodes($user_id, $days);
        
        // Filter by type if requested
        if ($type !== 'all') {
            $episodes = array_filter($episodes, function($episode) use ($type) {
                return $episode['episode_type'] === $type;
            });
        }
        
        // Get correlations for these episodes
        $episode_ids = array_column($episodes, 'episode_id');
        $correlations = $this->get_episode_correlations($episode_ids);
        
        wp_send_json_success(array(
            'episodes' => $episodes,
            'correlations' => $correlations
        ));
    }
    
    /**
     * AJAX handler: Quick log episode
     */
    public function ajax_quick_log() {
        check_ajax_referer('spiralengine_dashboard_nonce', 'nonce');
        
        $user_id = get_current_user_id();
        $episode_type = sanitize_text_field($_POST['episode_type']);
        $severity = intval($_POST['severity']);
        $thought = isset($_POST['thought']) ? sanitize_textarea_field($_POST['thought']) : '';
        
        // Validate episode type
        $registry = SpiralEngine_Episode_Registry::get_instance();
        if (!$registry->is_registered($episode_type)) {
            wp_send_json_error(array(
                'message' => __('Invalid episode type', 'spiralengine')
            ));
        }
        
        // Save episode
        global $wpdb;
        $result = $wpdb->insert(
            $wpdb->prefix . 'spiralengine_episodes',
            array(
                'user_id' => $user_id,
                'episode_type' => $episode_type,
                'severity_score' => $severity,
                'primary_thought' => $thought,
                'logged_via' => 'dashboard_quick',
                'episode_date' => current_time('mysql')
            ),
            array('%d', '%s', '%d', '%s', '%s', '%s')
        );
        
        if ($result) {
            $episode_id = $wpdb->insert_id;
            
            // Check for correlations
            do_action('spiralengine_episode_logged', $episode_id, $episode_type, $user_id);
            
            wp_send_json_success(array(
                'episode_id' => $episode_id,
                'message' => __('Episode logged successfully', 'spiralengine'),
                'refresh_widgets' => array('episode_timeline', 'unified_forecast')
            ));
        }
        
        wp_send_json_error(array(
            'message' => __('Failed to save episode', 'spiralengine')
        ));
    }
    
    /**
     * AJAX handler: Refresh forecast
     */
    public function ajax_refresh_forecast() {
        check_ajax_referer('spiralengine_dashboard_nonce', 'nonce');
        
        $user_id = get_current_user_id();
        $window = isset($_POST['window']) ? sanitize_text_field($_POST['window']) : '7_day';
        
        // Check membership for forecast access
        if (!$this->user_can_view_forecast($user_id)) {
            wp_send_json_error(array(
                'message' => __('Upgrade to Navigator membership to access forecasts', 'spiralengine'),
                'upgrade_url' => home_url('/membership/')
            ));
        }
        
        $forecast = $this->episode_aggregator->get_unified_forecast($user_id, $window);
        
        wp_send_json_success($forecast);
    }
    
    /**
     * Check if user can view forecasts
     */
    private function user_can_view_forecast($user_id) {
        $membership = $this->get_user_membership_level($user_id);
        return in_array($membership, array('navigator', 'legend'));
    }
    
    /**
     * Get user membership level
     */
    private function get_user_membership_level($user_id) {
        // Integration with MemberPress
        if (function_exists('mepr_get_current_users_membership_level')) {
            return mepr_get_current_users_membership_level($user_id);
        }
        
        // Default to free tier
        return 'seeker';
    }
    
    /**
     * Calculate user streaks
     */
    private function calculate_user_streaks($user_id, &$stats) {
        global $wpdb;
        
        // Get all episode dates
        $dates = $wpdb->get_col($wpdb->prepare("
            SELECT DISTINCT DATE(episode_date) as log_date
            FROM {$wpdb->prefix}spiralengine_episodes
            WHERE user_id = %d
            ORDER BY log_date DESC
        ", $user_id));
        
        if (empty($dates)) {
            return;
        }
        
        // Calculate current streak
        $current_streak = 0;
        $today = new DateTime();
        $check_date = clone $today;
        
        foreach ($dates as $date) {
            $episode_date = new DateTime($date);
            
            if ($check_date->format('Y-m-d') === $episode_date->format('Y-m-d')) {
                $current_streak++;
                $check_date->modify('-1 day');
            } else {
                break;
            }
        }
        
        $stats['current_streak'] = $current_streak;
        $stats['active_days'] = count($dates);
        
        // Calculate longest streak
        $longest_streak = 0;
        $temp_streak = 1;
        
        for ($i = 1; $i < count($dates); $i++) {
            $prev_date = new DateTime($dates[$i - 1]);
            $curr_date = new DateTime($dates[$i]);
            
            $diff = $prev_date->diff($curr_date)->days;
            
            if ($diff === 1) {
                $temp_streak++;
            } else {
                $longest_streak = max($longest_streak, $temp_streak);
                $temp_streak = 1;
            }
        }
        
        $stats['longest_streak'] = max($longest_streak, $temp_streak);
    }
    
    /**
     * Get episode correlations
     */
    private function get_episode_correlations($episode_ids) {
        if (empty($episode_ids)) {
            return array();
        }
        
        global $wpdb;
        
        $placeholders = implode(',', array_fill(0, count($episode_ids), '%d'));
        
        return $wpdb->get_results($wpdb->prepare("
            SELECT * FROM {$wpdb->prefix}spiralengine_episode_correlations
            WHERE primary_episode_id IN ($placeholders)
            OR related_episode_id IN ($placeholders)
            ORDER BY correlation_strength DESC
        ", array_merge($episode_ids, $episode_ids)));
    }
    
    /**
     * Render login prompt
     */
    private function render_login_prompt() {
        ?>
        <div class="spiralengine-widget spiralengine-dashboard-login-prompt">
            <div class="sp-login-content">
                <h2><?php _e('Welcome to Your SPIRAL Dashboard', 'spiralengine'); ?></h2>
                <p><?php _e('Please log in to access your personalized dashboard and tracking tools.', 'spiralengine'); ?></p>
                <a href="<?php echo wp_login_url(get_permalink()); ?>" class="sp-button sp-button-primary">
                    <?php _e('Log In', 'spiralengine'); ?>
                </a>
                <p class="sp-signup-link">
                    <?php _e("Don't have an account?", 'spiralengine'); ?>
                    <a href="<?php echo home_url('/signup/'); ?>"><?php _e('Sign up here', 'spiralengine'); ?></a>
                </p>
            </div>
        </div>
        <?php
    }
    
    /**
     * Check if current page has widget
     */
    private function is_widget_page() {
        // Check if we're on a page with our shortcode
        global $post;
        
        if (!$post) {
            return false;
        }
        
        return has_shortcode($post->post_content, 'spiralengine_dashboard') || 
               strpos($post->post_content, 'spiralengine-widget-dashboard') !== false;
    }
}

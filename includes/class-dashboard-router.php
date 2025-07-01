<?php
/**
 * SpiralEngine Dashboard Router
 * 
 * @package    SpiralEngine
 * @subpackage Includes
 * @file       includes/class-dashboard-router.php
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class SpiralEngine_Dashboard_Router
 * 
 * Handles dashboard routing and navigation
 */
class SpiralEngine_Dashboard_Router {
    
    /**
     * Single instance of the class
     *
     * @var SpiralEngine_Dashboard_Router
     */
    private static $instance = null;
    
    /**
     * Registered routes
     *
     * @var array
     */
    private $routes = array();
    
    /**
     * Route hooks
     *
     * @var array
     */
    private $route_hooks = array();
    
    /**
     * Current route
     *
     * @var string
     */
    private $current_route = '';
    
    /**
     * Route parameters
     *
     * @var array
     */
    private $route_params = array();
    
    /**
     * Get single instance
     *
     * @return SpiralEngine_Dashboard_Router
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
     * Initialize router
     */
    private function init() {
        // Parse current route
        $this->parse_current_route();
        
        // Register default routes
        $this->register_default_routes();
        
        // Add hooks
        add_action('init', array($this, 'handle_route_actions'));
        add_filter('body_class', array($this, 'add_route_body_classes'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_route_assets'));
        
        // AJAX handlers
        add_action('wp_ajax_spiralengine_navigate', array($this, 'ajax_navigate'));
        add_action('wp_ajax_spiralengine_get_route_content', array($this, 'ajax_get_route_content'));
    }
    
    /**
     * Register default routes
     */
    private function register_default_routes() {
        // Overview route
        $this->register_route('overview', array(
            'title' => __('Dashboard Overview', 'spiralengine'),
            'template' => 'dashboard-overview.php',
            'capability' => 'read',
            'scripts' => array('spiralengine-overview'),
            'styles' => array('spiralengine-overview')
        ));
        
        // Track routes
        $this->register_route('track', array(
            'title' => __('Track Episode', 'spiralengine'),
            'template' => 'dashboard-tabs/track.php',
            'capability' => 'read'
        ));
        
        $this->register_route('track/:widget', array(
            'title' => __('Track %s', 'spiralengine'),
            'callback' => array($this, 'render_widget_form'),
            'capability' => 'read',
            'dynamic_title' => true
        ));
        
        // Insights routes
        $this->register_route('insights', array(
            'title' => __('Insights & Analytics', 'spiralengine'),
            'template' => 'dashboard-tabs/insights.php',
            'capability' => 'spiralengine_view_insights',
            'min_tier' => 'silver'
        ));
        
        $this->register_route('insights/:period', array(
            'title' => __('%s Insights', 'spiralengine'),
            'callback' => array($this, 'render_period_insights'),
            'capability' => 'spiralengine_view_insights',
            'min_tier' => 'silver',
            'dynamic_title' => true
        ));
        
        // History routes
        $this->register_route('history', array(
            'title' => __('Episode History', 'spiralengine'),
            'template' => 'dashboard-tabs/history.php',
            'capability' => 'read'
        ));
        
        $this->register_route('history/episode/:id', array(
            'title' => __('Episode Details', 'spiralengine'),
            'callback' => array($this, 'render_episode_details'),
            'capability' => 'read'
        ));
        
        // Goals routes
        $this->register_route('goals', array(
            'title' => __('Goals & Progress', 'spiralengine'),
            'template' => 'dashboard-tabs/goals.php',
            'capability' => 'spiralengine_manage_goals',
            'min_tier' => 'silver'
        ));
        
        $this->register_route('goals/new', array(
            'title' => __('Create New Goal', 'spiralengine'),
            'callback' => array($this, 'render_new_goal_form'),
            'capability' => 'spiralengine_manage_goals',
            'min_tier' => 'silver'
        ));
        
        $this->register_route('goals/:id', array(
            'title' => __('Goal Details', 'spiralengine'),
            'callback' => array($this, 'render_goal_details'),
            'capability' => 'spiralengine_manage_goals',
            'min_tier' => 'silver'
        ));
        
        // Export routes
        $this->register_route('export', array(
            'title' => __('Export Data', 'spiralengine'),
            'template' => 'dashboard-tabs/export.php',
            'capability' => 'read'
        ));
        
        $this->register_route('export/preview/:format', array(
            'title' => __('Export Preview', 'spiralengine'),
            'callback' => array($this, 'render_export_preview'),
            'capability' => 'read'
        ));
        
        // AI routes
        $this->register_route('ai', array(
            'title' => __('AI Assistant', 'spiralengine'),
            'template' => 'dashboard-tabs/ai.php',
            'capability' => 'spiralengine_use_ai',
            'min_tier' => 'gold'
        ));
        
        $this->register_route('ai/chat', array(
            'title' => __('AI Chat', 'spiralengine'),
            'callback' => array($this, 'render_ai_chat'),
            'capability' => 'spiralengine_use_ai',
            'min_tier' => 'gold'
        ));
        
        // Settings routes
        $this->register_route('settings', array(
            'title' => __('Settings', 'spiralengine'),
            'template' => 'dashboard-tabs/settings.php',
            'capability' => 'read'
        ));
        
        $this->register_route('settings/:section', array(
            'title' => __('%s Settings', 'spiralengine'),
            'callback' => array($this, 'render_settings_section'),
            'capability' => 'read',
            'dynamic_title' => true
        ));
        
        // Upgrade route
        $this->register_route('upgrade', array(
            'title' => __('Upgrade Membership', 'spiralengine'),
            'template' => 'dashboard-tabs/upgrade.php',
            'capability' => 'read'
        ));
        
        // Help routes
        $this->register_route('help', array(
            'title' => __('Help & Support', 'spiralengine'),
            'callback' => array($this, 'render_help_page'),
            'capability' => 'read'
        ));
        
        $this->register_route('help/:topic', array(
            'title' => __('Help: %s', 'spiralengine'),
            'callback' => array($this, 'render_help_topic'),
            'capability' => 'read',
            'dynamic_title' => true
        ));
    }
    
    /**
     * Register a route
     *
     * @param string $pattern Route pattern
     * @param array $config Route configuration
     */
    public function register_route($pattern, $config) {
        $defaults = array(
            'title' => '',
            'template' => '',
            'callback' => '',
            'capability' => 'read',
            'min_tier' => 'free',
            'scripts' => array(),
            'styles' => array(),
            'dynamic_title' => false,
            'cache' => true
        );
        
        $config = wp_parse_args($config, $defaults);
        $config['pattern'] = $pattern;
        
        // Parse pattern for parameters
        $config['regex'] = $this->pattern_to_regex($pattern);
        $config['params'] = $this->extract_params($pattern);
        
        $this->routes[$pattern] = $config;
    }
    
    /**
     * Convert route pattern to regex
     *
     * @param string $pattern
     * @return string
     */
    private function pattern_to_regex($pattern) {
        $pattern = preg_replace('/\//', '\\/', $pattern);
        $pattern = preg_replace('/:(\w+)/', '(?P<$1>[^\/]+)', $pattern);
        return '/^' . $pattern . '$/';
    }
    
    /**
     * Extract parameters from pattern
     *
     * @param string $pattern
     * @return array
     */
    private function extract_params($pattern) {
        preg_match_all('/:(\w+)/', $pattern, $matches);
        return $matches[1];
    }
    
    /**
     * Parse current route
     */
    private function parse_current_route() {
        $tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'overview';
        $action = isset($_GET['action']) ? sanitize_key($_GET['action']) : '';
        $id = isset($_GET['id']) ? sanitize_key($_GET['id']) : '';
        
        // Build route path
        $path_parts = array($tab);
        
        if ($action) {
            $path_parts[] = $action;
        }
        
        if ($id) {
            $path_parts[] = $id;
        }
        
        $this->current_route = implode('/', $path_parts);
        
        // Match against registered routes
        $this->match_route($this->current_route);
    }
    
    /**
     * Match route against patterns
     *
     * @param string $path
     * @return array|false
     */
    private function match_route($path) {
        foreach ($this->routes as $pattern => $config) {
            if (preg_match($config['regex'], $path, $matches)) {
                // Extract named parameters
                $params = array();
                foreach ($config['params'] as $param) {
                    if (isset($matches[$param])) {
                        $params[$param] = $matches[$param];
                    }
                }
                
                $this->route_params = $params;
                return $config;
            }
        }
        
        return false;
    }
    
    /**
     * Get current route config
     *
     * @return array|false
     */
    public function get_current_route() {
        return $this->match_route($this->current_route);
    }
    
    /**
     * Get route URL
     *
     * @param string $route
     * @param array $params
     * @return string
     */
    public function get_route_url($route, $params = array()) {
        $base_url = get_permalink();
        
        // Parse route for building URL
        $parts = explode('/', $route);
        $query_args = array();
        
        if (!empty($parts[0])) {
            $query_args['tab'] = $parts[0];
        }
        
        if (isset($parts[1])) {
            $query_args['action'] = $parts[1];
        }
        
        if (isset($parts[2])) {
            $query_args['id'] = $parts[2];
        }
        
        // Merge additional parameters
        $query_args = array_merge($query_args, $params);
        
        return add_query_arg($query_args, $base_url);
    }
    
    /**
     * Check if user can access route
     *
     * @param array $route_config
     * @return bool
     */
    public function can_access_route($route_config) {
        $user_id = get_current_user_id();
        
        // Check capability
        if (!current_user_can($route_config['capability'])) {
            return false;
        }
        
        // Check tier requirement
        if ($route_config['min_tier'] !== 'free') {
            if (!SpiralEngine_Membership::user_has_tier($user_id, $route_config['min_tier'])) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Render route content
     *
     * @param array $route_config
     * @return string
     */
    public function render_route($route_config = null) {
        if (!$route_config) {
            $route_config = $this->get_current_route();
        }
        
        if (!$route_config) {
            return $this->render_404();
        }
        
        // Check access
        if (!$this->can_access_route($route_config)) {
            return $this->render_access_denied($route_config);
        }
        
        // Fire route hook
        do_action('spiralengine_before_route', $route_config, $this->route_params);
        
        ob_start();
        
        // Use callback if provided
        if (!empty($route_config['callback']) && is_callable($route_config['callback'])) {
            call_user_func($route_config['callback'], $this->route_params);
        }
        // Use template if provided
        elseif (!empty($route_config['template'])) {
            $template_path = SPIRALENGINE_PLUGIN_DIR . 'templates/' . $route_config['template'];
            
            if (file_exists($template_path)) {
                // Make variables available to template
                $router = $this;
                $params = $this->route_params;
                $user = wp_get_current_user();
                $membership = SpiralEngine_Membership::get_user_membership($user->ID);
                
                include $template_path;
            } else {
                echo '<div class="notice notice-error"><p>' . 
                     sprintf(__('Template not found: %s', 'spiralengine'), $route_config['template']) . 
                     '</p></div>';
            }
        }
        
        $content = ob_get_clean();
        
        // Fire after route hook
        do_action('spiralengine_after_route', $route_config, $this->route_params);
        
        return $content;
    }
    
    /**
     * Render 404 page
     *
     * @return string
     */
    private function render_404() {
        ob_start();
        ?>
        <div class="spiralengine-404">
            <h2><?php _e('Page Not Found', 'spiralengine'); ?></h2>
            <p><?php _e('The page you are looking for does not exist.', 'spiralengine'); ?></p>
            <a href="<?php echo esc_url($this->get_route_url('overview')); ?>" class="button">
                <?php _e('Return to Dashboard', 'spiralengine'); ?>
            </a>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Render access denied page
     *
     * @param array $route_config
     * @return string
     */
    private function render_access_denied($route_config) {
        ob_start();
        ?>
        <div class="spiralengine-access-denied">
            <h2><?php _e('Access Denied', 'spiralengine'); ?></h2>
            
            <?php if ($route_config['min_tier'] !== 'free'): ?>
                <p><?php printf(
                    __('This feature requires a %s membership or higher.', 'spiralengine'),
                    ucfirst($route_config['min_tier'])
                ); ?></p>
                <a href="<?php echo esc_url($this->get_route_url('upgrade')); ?>" class="button button-primary">
                    <?php _e('Upgrade Your Membership', 'spiralengine'); ?>
                </a>
            <?php else: ?>
                <p><?php _e('You do not have permission to access this page.', 'spiralengine'); ?></p>
            <?php endif; ?>
            
            <a href="<?php echo esc_url($this->get_route_url('overview')); ?>" class="button">
                <?php _e('Return to Dashboard', 'spiralengine'); ?>
            </a>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Add route body classes
     *
     * @param array $classes
     * @return array
     */
    public function add_route_body_classes($classes) {
        if (!$this->is_dashboard_page()) {
            return $classes;
        }
        
        $route = $this->get_current_route();
        
        if ($route) {
            // Add route-based classes
            $classes[] = 'spiralengine-route';
            $classes[] = 'spiralengine-route-' . str_replace('/', '-', $this->current_route);
            
            // Add tier-based class
            $user_id = get_current_user_id();
            $membership = SpiralEngine_Membership::get_user_membership($user_id);
            if ($membership) {
                $classes[] = 'spiralengine-tier-' . $membership->tier;
            }
        }
        
        return $classes;
    }
    
    /**
     * Enqueue route-specific assets
     */
    public function enqueue_route_assets() {
        if (!$this->is_dashboard_page()) {
            return;
        }
        
        $route = $this->get_current_route();
        
        if (!$route) {
            return;
        }
        
        // Enqueue route-specific scripts
        foreach ($route['scripts'] as $script) {
            wp_enqueue_script($script);
        }
        
        // Enqueue route-specific styles
        foreach ($route['styles'] as $style) {
            wp_enqueue_style($style);
        }
    }
    
    /**
     * Handle route actions
     */
    public function handle_route_actions() {
        if (!isset($_POST['spiralengine_route_action'])) {
            return;
        }
        
        $action = sanitize_key($_POST['spiralengine_route_action']);
        $route = $this->get_current_route();
        
        if (!$route) {
            return;
        }
        
        // Verify nonce
        if (!wp_verify_nonce($_POST['_wpnonce'], 'spiralengine_route_' . $action)) {
            return;
        }
        
        // Fire route action
        do_action('spiralengine_route_action_' . $action, $route, $this->route_params);
    }
    
    /**
     * AJAX: Navigate to route
     */
    public function ajax_navigate() {
        check_ajax_referer('spiralengine_dashboard', 'nonce');
        
        $route = isset($_POST['route']) ? sanitize_text_field($_POST['route']) : '';
        $params = isset($_POST['params']) ? wp_unslash($_POST['params']) : array();
        
        if (empty($route)) {
            wp_send_json_error(__('Invalid route.', 'spiralengine'));
        }
        
        // Get route config
        $route_config = $this->match_route($route);
        
        if (!$route_config) {
            wp_send_json_error(__('Route not found.', 'spiralengine'));
        }
        
        // Check access
        if (!$this->can_access_route($route_config)) {
            wp_send_json_error(__('Access denied.', 'spiralengine'));
        }
        
        // Get route URL
        $url = $this->get_route_url($route, $params);
        
        wp_send_json_success(array(
            'url' => $url,
            'title' => $this->get_route_title($route_config, $params)
        ));
    }
    
    /**
     * AJAX: Get route content
     */
    public function ajax_get_route_content() {
        check_ajax_referer('spiralengine_dashboard', 'nonce');
        
        $route = isset($_POST['route']) ? sanitize_text_field($_POST['route']) : '';
        
        if (empty($route)) {
            wp_send_json_error(__('Invalid route.', 'spiralengine'));
        }
        
        // Set current route
        $this->current_route = $route;
        $route_config = $this->match_route($route);
        
        if (!$route_config) {
            wp_send_json_error(__('Route not found.', 'spiralengine'));
        }
        
        // Render route content
        $content = $this->render_route($route_config);
        
        wp_send_json_success(array(
            'content' => $content,
            'title' => $this->get_route_title($route_config, $this->route_params),
            'route' => $route
        ));
    }
    
    /**
     * Get route title
     *
     * @param array $route_config
     * @param array $params
     * @return string
     */
    private function get_route_title($route_config, $params = array()) {
        $title = $route_config['title'];
        
        // Handle dynamic titles
        if ($route_config['dynamic_title'] && !empty($params)) {
            $replacements = array();
            foreach ($params as $key => $value) {
                $replacements['%' . $key] = ucwords(str_replace('-', ' ', $value));
            }
            
            // If title contains %s, replace with first param value
            if (strpos($title, '%s') !== false) {
                $title = sprintf($title, reset($replacements));
            } else {
                $title = str_replace(array_keys($replacements), array_values($replacements), $title);
            }
        }
        
        return $title;
    }
    
    /**
     * Route callback: Render widget form
     *
     * @param array $params
     */
    public function render_widget_form($params) {
        $widget_id = $params['widget'] ?? '';
        
        if (empty($widget_id)) {
            echo '<div class="notice notice-error"><p>' . __('Widget not specified.', 'spiralengine') . '</p></div>';
            return;
        }
        
        $widget = SpiralEngine_Widget_Loader::get_widget($widget_id);
        
        if (!$widget) {
            echo '<div class="notice notice-error"><p>' . __('Widget not found.', 'spiralengine') . '</p></div>';
            return;
        }
        
        ?>
        <div class="widget-form-page">
            <h2><?php echo esc_html($widget->get_name()); ?></h2>
            <div class="widget-description">
                <?php echo wp_kses_post($widget->get_description()); ?>
            </div>
            <div class="widget-form-wrapper">
                <?php echo $widget->render_form(); ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Route callback: Render period insights
     *
     * @param array $params
     */
    public function render_period_insights($params) {
        $period = $params['period'] ?? 'week';
        
        $valid_periods = array('day', 'week', 'month', 'year');
        if (!in_array($period, $valid_periods)) {
            $period = 'week';
        }
        
        // Get insights for period
        $insight_generator = SpiralEngine_Insight_Generator::get_instance();
        $insights = $insight_generator->get_insights_for_period(get_current_user_id(), $period);
        
        ?>
        <div class="period-insights">
            <h2><?php printf(__('%s Insights', 'spiralengine'), ucfirst($period)); ?></h2>
            
            <div class="period-selector">
                <?php foreach ($valid_periods as $p): ?>
                    <a href="<?php echo esc_url($this->get_route_url('insights/' . $p)); ?>" 
                       class="period-link <?php echo $p === $period ? 'active' : ''; ?>">
                        <?php echo ucfirst($p); ?>
                    </a>
                <?php endforeach; ?>
            </div>
            
            <?php if (!empty($insights)): ?>
                <div class="insights-grid">
                    <?php foreach ($insights as $insight): ?>
                        <div class="insight-card">
                            <?php // Render insight content ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="no-insights"><?php _e('No insights available for this period.', 'spiralengine'); ?></p>
            <?php endif; ?>
        </div>
        <?php
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
     * Get breadcrumbs for current route
     *
     * @return array
     */
    public function get_breadcrumbs() {
        $breadcrumbs = array();
        
        // Always add dashboard home
        $breadcrumbs[] = array(
            'title' => __('Dashboard', 'spiralengine'),
            'url' => $this->get_route_url('overview')
        );
        
        // Add current route parts
        $parts = explode('/', $this->current_route);
        $path = '';
        
        foreach ($parts as $i => $part) {
            if ($part === 'overview') continue;
            
            $path .= ($path ? '/' : '') . $part;
            $route_config = $this->match_route($path);
            
            if ($route_config) {
                $breadcrumbs[] = array(
                    'title' => $this->get_route_title($route_config, $this->route_params),
                    'url' => ($i === count($parts) - 1) ? '' : $this->get_route_url($path)
                );
            }
        }
        
        return $breadcrumbs;
    }
}

// Initialize router
SpiralEngine_Dashboard_Router::get_instance();


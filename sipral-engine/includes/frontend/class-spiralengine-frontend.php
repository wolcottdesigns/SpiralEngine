<?php
// includes/frontend/class-spiralengine-frontend.php

/**
 * SpiralEngine Frontend Class
 *
 * Handles frontend functionality initialization and coordination
 *
 * @package    SpiralEngine
 * @subpackage SpiralEngine/includes/frontend
 */

if (!defined('ABSPATH')) {
    exit;
}

class SpiralEngine_Frontend {
    
    /**
     * Frontend version
     *
     * @var string
     */
    private $version;
    
    /**
     * Database instance
     *
     * @var SpiralEngine_Database
     */
    private $db;
    
    /**
     * Active user ID
     *
     * @var int
     */
    private $user_id;
    
    /**
     * Frontend settings
     *
     * @var array
     */
    private $settings;
    
    /**
     * Constructor
     *
     * @param string $version Plugin version
     */
    public function __construct($version) {
        $this->version = $version;
        $this->db = SpiralEngine_Database::getInstance();
        $this->user_id = get_current_user_id();
        $this->load_settings();
    }
    
    /**
     * Initialize frontend
     */
    public function init() {
        $this->register_hooks();
        $this->register_scripts_and_styles();
        $this->initialize_components();
    }
    
    /**
     * Load frontend settings
     */
    private function load_settings() {
        $this->settings = get_option('spiralengine_frontend_settings', array(
            'enable_ajax' => true,
            'enable_animations' => true,
            'enable_tooltips' => true,
            'theme_mode' => 'auto', // auto, light, dark
            'date_format' => 'F j, Y',
            'time_format' => 'g:i A', // 12-hour format enforced
            'enable_sounds' => false,
            'enable_keyboard_shortcuts' => true
        ));
    }
    
    /**
     * Register WordPress hooks
     */
    private function register_hooks() {
        // Frontend display hooks
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_head', array($this, 'add_frontend_vars'));
        add_action('wp_footer', array($this, 'render_frontend_templates'));
        
        // AJAX handlers
        add_action('wp_ajax_spiralengine_frontend_action', array($this, 'handle_frontend_ajax'));
        add_action('wp_ajax_nopriv_spiralengine_frontend_action', array($this, 'handle_frontend_ajax_nopriv'));
        
        // Content filters
        add_filter('the_content', array($this, 'process_content'));
        add_filter('body_class', array($this, 'add_body_classes'));
        
        // User experience hooks
        add_action('wp_login', array($this, 'handle_user_login'), 10, 2);
        add_action('wp_logout', array($this, 'handle_user_logout'));
        
        // Widget area registration
        add_action('widgets_init', array($this, 'register_widget_areas'));
    }
    
    /**
     * Register scripts and styles
     */
    private function register_scripts_and_styles() {
        // Register scripts
        wp_register_script(
            'spiralengine-frontend',
            SPIRALENGINE_PLUGIN_URL . 'assets/js/frontend.js',
            array('jquery', 'wp-api', 'wp-i18n'),
            $this->version,
            true
        );
        
        wp_register_script(
            'spiralengine-charts',
            SPIRALENGINE_PLUGIN_URL . 'assets/js/charts.js',
            array('spiralengine-frontend'),
            $this->version,
            true
        );
        
        wp_register_script(
            'spiralengine-animations',
            SPIRALENGINE_PLUGIN_URL . 'assets/js/animations.js',
            array('spiralengine-frontend'),
            $this->version,
            true
        );
        
        // Register styles
        wp_register_style(
            'spiralengine-frontend',
            SPIRALENGINE_PLUGIN_URL . 'assets/css/frontend.css',
            array(),
            $this->version
        );
        
        wp_register_style(
            'spiralengine-theme',
            SPIRALENGINE_PLUGIN_URL . 'assets/css/theme.css',
            array('spiralengine-frontend'),
            $this->version
        );
    }
    
    /**
     * Initialize frontend components
     */
    private function initialize_components() {
        // Initialize only if needed components
        if ($this->is_spiralengine_page()) {
            $this->init_dashboard();
            $this->init_widgets();
            $this->init_real_time_updates();
        }
    }
    
    /**
     * Enqueue scripts and styles
     */
    public function enqueue_scripts() {
        // Only enqueue on relevant pages
        if (!$this->should_load_assets()) {
            return;
        }
        
        // Enqueue styles
        wp_enqueue_style('spiralengine-frontend');
        
        // Add theme-specific style
        if ($this->settings['theme_mode'] !== 'light') {
            wp_enqueue_style('spiralengine-theme');
        }
        
        // Enqueue scripts
        wp_enqueue_script('spiralengine-frontend');
        
        // Conditionally load additional scripts
        if ($this->settings['enable_animations']) {
            wp_enqueue_script('spiralengine-animations');
        }
        
        if ($this->needs_charts()) {
            wp_enqueue_script('spiralengine-charts');
        }
        
        // Localize script
        wp_localize_script('spiralengine-frontend', 'spiralengine_frontend', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'api_url' => home_url('/wp-json/spiralengine/v1/'),
            'nonce' => wp_create_nonce('spiralengine_frontend'),
            'user_id' => $this->user_id,
            'settings' => $this->get_public_settings(),
            'i18n' => $this->get_frontend_strings()
        ));
    }
    
    /**
     * Add frontend JavaScript variables
     */
    public function add_frontend_vars() {
        if (!$this->should_load_assets()) {
            return;
        }
        ?>
        <script type="text/javascript">
            var SpiralEngine = {
                version: '<?php echo esc_js($this->version); ?>',
                user: {
                    id: <?php echo intval($this->user_id); ?>,
                    loggedIn: <?php echo is_user_logged_in() ? 'true' : 'false'; ?>
                },
                config: {
                    dateFormat: '<?php echo esc_js($this->settings['date_format']); ?>',
                    timeFormat: '<?php echo esc_js($this->settings['time_format']); ?>',
                    theme: '<?php echo esc_js($this->settings['theme_mode']); ?>',
                    animations: <?php echo $this->settings['enable_animations'] ? 'true' : 'false'; ?>
                }
            };
        </script>
        <?php
    }
    
    /**
     * Render frontend templates
     */
    public function render_frontend_templates() {
        if (!$this->should_load_assets()) {
            return;
        }
        
        // Load template files
        $templates = array(
            'modal',
            'notification',
            'widget-container',
            'loading',
            'error'
        );
        
        foreach ($templates as $template) {
            $template_file = SPIRALENGINE_PLUGIN_DIR . 'templates/frontend/' . $template . '.php';
            if (file_exists($template_file)) {
                echo '<script type="text/template" id="spiralengine-' . $template . '-template">';
                include $template_file;
                echo '</script>';
            }
        }
    }
    
    /**
     * Handle frontend AJAX requests
     */
    public function handle_frontend_ajax() {
        check_ajax_referer('spiralengine_frontend', 'nonce');
        
        $action = sanitize_text_field($_POST['frontend_action'] ?? '');
        $data = $_POST['data'] ?? array();
        
        $response = array('success' => false);
        
        switch ($action) {
            case 'load_widget':
                $response = $this->ajax_load_widget($data);
                break;
                
            case 'save_preference':
                $response = $this->ajax_save_preference($data);
                break;
                
            case 'get_user_data':
                $response = $this->ajax_get_user_data($data);
                break;
                
            case 'track_event':
                $response = $this->ajax_track_event($data);
                break;
                
            default:
                $response = apply_filters('spiralengine_frontend_ajax_' . $action, $response, $data);
        }
        
        wp_send_json($response);
    }
    
    /**
     * Handle non-privileged AJAX requests
     */
    public function handle_frontend_ajax_nopriv() {
        check_ajax_referer('spiralengine_frontend', 'nonce');
        
        // Limited actions for non-logged-in users
        $action = sanitize_text_field($_POST['frontend_action'] ?? '');
        
        $allowed_actions = array('load_public_widget', 'track_event');
        
        if (!in_array($action, $allowed_actions)) {
            wp_send_json_error('Unauthorized action');
        }
        
        $this->handle_frontend_ajax();
    }
    
    /**
     * Process content for SpiralEngine elements
     *
     * @param string $content Post content
     * @return string Processed content
     */
    public function process_content($content) {
        // Only process on single posts/pages
        if (!is_singular()) {
            return $content;
        }
        
        // Check for SpiralEngine blocks or shortcodes
        if (has_blocks($content)) {
            $content = $this->process_blocks($content);
        }
        
        // Process any remaining shortcodes
        $content = do_shortcode($content);
        
        return $content;
    }
    
    /**
     * Add body classes
     *
     * @param array $classes Existing classes
     * @return array Modified classes
     */
    public function add_body_classes($classes) {
        if ($this->is_spiralengine_page()) {
            $classes[] = 'spiralengine-active';
            $classes[] = 'spiralengine-theme-' . $this->settings['theme_mode'];
            
            if ($this->settings['enable_animations']) {
                $classes[] = 'spiralengine-animations';
            }
            
            if (is_user_logged_in()) {
                $classes[] = 'spiralengine-user-logged-in';
                
                // Add membership level class
                $membership = $this->get_user_membership_level();
                if ($membership) {
                    $classes[] = 'spiralengine-member-' . sanitize_html_class($membership);
                }
            }
        }
        
        return $classes;
    }
    
    /**
     * Handle user login
     *
     * @param string $user_login Username
     * @param WP_User $user User object
     */
    public function handle_user_login($user_login, $user) {
        // Update last login time
        update_user_meta($user->ID, 'spiralengine_last_login', current_time('mysql'));
        
        // Check for first login
        $first_login = get_user_meta($user->ID, 'spiralengine_first_login', true);
        if (!$first_login) {
            update_user_meta($user->ID, 'spiralengine_first_login', current_time('mysql'));
            
            // Trigger welcome sequence
            do_action('spiralengine_user_first_login', $user->ID);
        }
        
        // Initialize user session data
        $this->init_user_session($user->ID);
    }
    
    /**
     * Handle user logout
     */
    public function handle_user_logout() {
        if ($this->user_id) {
            // Save session data
            $this->save_user_session($this->user_id);
            
            // Clear user-specific caches
            wp_cache_delete('spiralengine_user_' . $this->user_id, 'spiralengine');
        }
    }
    
    /**
     * Register widget areas
     */
    public function register_widget_areas() {
        register_sidebar(array(
            'name' => __('SpiralEngine Dashboard', 'spiral-engine'),
            'id' => 'spiralengine-dashboard',
            'description' => __('Widgets for the SpiralEngine dashboard', 'spiral-engine'),
            'before_widget' => '<div id="%1$s" class="spiralengine-widget %2$s">',
            'after_widget' => '</div>',
            'before_title' => '<h3 class="spiralengine-widget-title">',
            'after_title' => '</h3>'
        ));
        
        register_sidebar(array(
            'name' => __('SpiralEngine Sidebar', 'spiral-engine'),
            'id' => 'spiralengine-sidebar',
            'description' => __('Sidebar widgets for SpiralEngine pages', 'spiral-engine'),
            'before_widget' => '<div id="%1$s" class="spiralengine-sidebar-widget %2$s">',
            'after_widget' => '</div>',
            'before_title' => '<h4 class="spiralengine-sidebar-title">',
            'after_title' => '</h4>'
        ));
    }
    
    /**
     * Check if current page should load SpiralEngine
     *
     * @return bool Should load
     */
    private function should_load_assets() {
        // Always load on SpiralEngine pages
        if ($this->is_spiralengine_page()) {
            return true;
        }
        
        // Check if post/page has SpiralEngine shortcodes
        if (is_singular()) {
            global $post;
            if ($post && (
                has_shortcode($post->post_content, 'spiralengine') ||
                has_block('spiralengine/', $post)
            )) {
                return true;
            }
        }
        
        // Allow filtering
        return apply_filters('spiralengine_should_load_frontend', false);
    }
    
    /**
     * Check if current page is a SpiralEngine page
     *
     * @return bool Is SpiralEngine page
     */
    private function is_spiralengine_page() {
        // Check if it's a designated SpiralEngine page
        $spiralengine_pages = get_option('spiralengine_pages', array());
        
        if (is_page() && in_array(get_the_ID(), $spiralengine_pages)) {
            return true;
        }
        
        // Check URL structure
        if (strpos($_SERVER['REQUEST_URI'], '/spiralengine/') !== false) {
            return true;
        }
        
        // Check query var
        if (get_query_var('spiralengine')) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Check if charts are needed
     *
     * @return bool Needs charts
     */
    private function needs_charts() {
        // Check if page has chart widgets or shortcodes
        if (is_singular()) {
            global $post;
            if ($post && (
                has_shortcode($post->post_content, 'spiralengine_chart') ||
                has_block('spiralengine/chart', $post)
            )) {
                return true;
            }
        }
        
        // Check if user dashboard
        if ($this->is_spiralengine_page() && get_query_var('spiralengine') === 'dashboard') {
            return true;
        }
        
        return false;
    }
    
    /**
     * Get public settings
     *
     * @return array Public settings
     */
    private function get_public_settings() {
        return array(
            'animations' => $this->settings['enable_animations'],
            'tooltips' => $this->settings['enable_tooltips'],
            'sounds' => $this->settings['enable_sounds'],
            'shortcuts' => $this->settings['enable_keyboard_shortcuts'],
            'theme' => $this->settings['theme_mode']
        );
    }
    
    /**
     * Get frontend strings for i18n
     *
     * @return array Translatable strings
     */
    private function get_frontend_strings() {
        return array(
            'loading' => __('Loading...', 'spiral-engine'),
            'error' => __('An error occurred', 'spiral-engine'),
            'retry' => __('Retry', 'spiral-engine'),
            'cancel' => __('Cancel', 'spiral-engine'),
            'save' => __('Save', 'spiral-engine'),
            'saved' => __('Saved!', 'spiral-engine'),
            'confirm' => __('Are you sure?', 'spiral-engine'),
            'success' => __('Success!', 'spiral-engine'),
            'warning' => __('Warning', 'spiral-engine'),
            'info' => __('Information', 'spiral-engine')
        );
    }
    
    /**
     * Initialize dashboard
     */
    private function init_dashboard() {
        if (!is_user_logged_in()) {
            return;
        }
        
        // Load user's dashboard configuration
        $dashboard_config = get_user_meta($this->user_id, 'spiralengine_dashboard_config', true);
        
        if (!$dashboard_config) {
            // Set default dashboard
            $dashboard_config = $this->get_default_dashboard_config();
            update_user_meta($this->user_id, 'spiralengine_dashboard_config', $dashboard_config);
        }
        
        // Make config available to JavaScript
        wp_localize_script('spiralengine-frontend', 'spiralengine_dashboard', $dashboard_config);
    }
    
    /**
     * Initialize widgets
     */
    private function init_widgets() {
        // Get active widgets for current user
        $active_widgets = get_user_meta($this->user_id, 'spiralengine_active_widgets', true);
        
        if (!$active_widgets) {
            $active_widgets = $this->get_default_widgets();
        }
        
        // Load widget data
        foreach ($active_widgets as $widget_id) {
            $this->preload_widget_data($widget_id);
        }
    }
    
    /**
     * Initialize real-time updates
     */
    private function init_real_time_updates() {
        if (!$this->settings['enable_ajax']) {
            return;
        }
        
        // Set up WebSocket connection if available
        if (get_option('spiralengine_websocket_enabled')) {
            wp_enqueue_script('spiralengine-websocket');
            
            wp_localize_script('spiralengine-websocket', 'spiralengine_ws', array(
                'url' => get_option('spiralengine_websocket_url'),
                'auth' => $this->generate_ws_token()
            ));
        }
    }
    
    /**
     * AJAX: Load widget
     *
     * @param array $data Request data
     * @return array Response
     */
    private function ajax_load_widget($data) {
        $widget_id = sanitize_text_field($data['widget_id'] ?? '');
        
        if (!$widget_id) {
            return array('success' => false, 'error' => 'Invalid widget ID');
        }
        
        // Check permissions
        if (!$this->can_access_widget($widget_id)) {
            return array('success' => false, 'error' => 'Access denied');
        }
        
        // Load widget data
        $widget_data = $this->get_widget_data($widget_id);
        
        if (!$widget_data) {
            return array('success' => false, 'error' => 'Widget not found');
        }
        
        // Render widget HTML
        ob_start();
        $this->render_widget($widget_id, $widget_data);
        $html = ob_get_clean();
        
        return array(
            'success' => true,
            'html' => $html,
            'data' => $widget_data,
            'config' => $this->get_widget_config($widget_id)
        );
    }
    
    /**
     * AJAX: Save preference
     *
     * @param array $data Request data
     * @return array Response
     */
    private function ajax_save_preference($data) {
        if (!is_user_logged_in()) {
            return array('success' => false, 'error' => 'Not logged in');
        }
        
        $key = sanitize_key($data['key'] ?? '');
        $value = $data['value'] ?? '';
        
        if (!$key) {
            return array('success' => false, 'error' => 'Invalid preference key');
        }
        
        // Validate preference
        if (!$this->is_valid_preference($key, $value)) {
            return array('success' => false, 'error' => 'Invalid preference value');
        }
        
        // Save preference
        update_user_meta($this->user_id, 'spiralengine_pref_' . $key, $value);
        
        // Clear cache
        wp_cache_delete('spiralengine_user_prefs_' . $this->user_id, 'spiralengine');
        
        return array(
            'success' => true,
            'key' => $key,
            'value' => $value
        );
    }
    
    /**
     * AJAX: Get user data
     *
     * @param array $data Request data
     * @return array Response
     */
    private function ajax_get_user_data($data) {
        if (!is_user_logged_in()) {
            return array('success' => false, 'error' => 'Not logged in');
        }
        
        $type = sanitize_text_field($data['type'] ?? '');
        
        $allowed_types = array('profile', 'stats', 'progress', 'achievements');
        
        if (!in_array($type, $allowed_types)) {
            return array('success' => false, 'error' => 'Invalid data type');
        }
        
        $user_data = $this->get_user_data_by_type($type);
        
        return array(
            'success' => true,
            'type' => $type,
            'data' => $user_data
        );
    }
    
    /**
     * AJAX: Track event
     *
     * @param array $data Request data
     * @return array Response
     */
    private function ajax_track_event($data) {
        $event = sanitize_text_field($data['event'] ?? '');
        $properties = $data['properties'] ?? array();
        
        if (!$event) {
            return array('success' => false, 'error' => 'Invalid event');
        }
        
        // Track the event
        do_action('spiralengine_track_event', $event, $properties, $this->user_id);
        
        return array('success' => true);
    }
    
    /**
     * Process blocks in content
     *
     * @param string $content Content with blocks
     * @return string Processed content
     */
    private function process_blocks($content) {
        $blocks = parse_blocks($content);
        
        foreach ($blocks as &$block) {
            if (strpos($block['blockName'], 'spiralengine/') === 0) {
                $block = $this->process_spiralengine_block($block);
            }
        }
        
        return serialize_blocks($blocks);
    }
    
    /**
     * Process SpiralEngine block
     *
     * @param array $block Block data
     * @return array Processed block
     */
    private function process_spiralengine_block($block) {
        // Add user-specific data to block attributes
        if (is_user_logged_in()) {
            $block['attrs']['userId'] = $this->user_id;
            $block['attrs']['userLevel'] = $this->get_user_membership_level();
        }
        
        // Process based on block type
        $block_type = str_replace('spiralengine/', '', $block['blockName']);
        
        switch ($block_type) {
            case 'dashboard':
                $block['attrs']['widgets'] = $this->get_dashboard_widgets();
                break;
                
            case 'assessment':
                $block['attrs']['assessmentData'] = $this->get_assessment_data($block['attrs']['type'] ?? '');
                break;
                
            case 'progress':
                $block['attrs']['progressData'] = $this->get_progress_data();
                break;
        }
        
        return $block;
    }
    
    /**
     * Get user membership level
     *
     * @return string|null Membership level
     */
    private function get_user_membership_level() {
        if (!$this->user_id) {
            return null;
        }
        
        // Check MemberPress
        if (function_exists('mepr_get_current_membership')) {
            $membership = mepr_get_current_membership();
            if ($membership) {
                return strtolower($membership->post_title);
            }
        }
        
        // Fallback to user meta
        return get_user_meta($this->user_id, 'spiralengine_membership_level', true);
    }
    
    /**
     * Initialize user session
     *
     * @param int $user_id User ID
     */
    private function init_user_session($user_id) {
        // Set session start time
        set_transient('spiralengine_session_' . $user_id, array(
            'start' => time(),
            'last_activity' => time(),
            'page_views' => 0
        ), HOUR_IN_SECONDS);
    }
    
    /**
     * Save user session data
     *
     * @param int $user_id User ID
     */
    private function save_user_session($user_id) {
        $session = get_transient('spiralengine_session_' . $user_id);
        
        if ($session) {
            // Save session summary
            global $wpdb;
            
            $table_name = $wpdb->prefix . 'spiralengine_user_sessions';
            
            $wpdb->insert(
                $table_name,
                array(
                    'user_id' => $user_id,
                    'start_time' => date('Y-m-d H:i:s', $session['start']),
                    'end_time' => current_time('mysql'),
                    'duration' => time() - $session['start'],
                    'page_views' => $session['page_views']
                ),
                array('%d', '%s', '%s', '%d', '%d')
            );
            
            // Delete transient
            delete_transient('spiralengine_session_' . $user_id);
        }
    }
    
    /**
     * Get default dashboard configuration
     *
     * @return array Dashboard config
     */
    private function get_default_dashboard_config() {
        return array(
            'layout' => 'grid',
            'columns' => 3,
            'widgets' => array(
                array('id' => 'welcome', 'position' => 1, 'size' => 'full'),
                array('id' => 'quick_assessment', 'position' => 2, 'size' => 'half'),
                array('id' => 'recent_progress', 'position' => 3, 'size' => 'half'),
                array('id' => 'insights', 'position' => 4, 'size' => 'third'),
                array('id' => 'achievements', 'position' => 5, 'size' => 'third'),
                array('id' => 'next_steps', 'position' => 6, 'size' => 'third')
            )
        );
    }
    
    /**
     * Get default widgets
     *
     * @return array Widget IDs
     */
    private function get_default_widgets() {
        $membership = $this->get_user_membership_level();
        
        $widgets = array('welcome', 'quick_assessment', 'recent_progress');
        
        // Add membership-specific widgets
        switch ($membership) {
            case 'voyager':
                $widgets = array_merge($widgets, array('ai_insights', 'advanced_analytics', 'predictions'));
                break;
                
            case 'navigator':
                $widgets = array_merge($widgets, array('ai_insights', 'analytics'));
                break;
                
            case 'explorer':
                $widgets[] = 'basic_insights';
                break;
        }
        
        return $widgets;
    }
    
    /**
     * Preload widget data
     *
     * @param string $widget_id Widget ID
     */
    private function preload_widget_data($widget_id) {
        $cache_key = 'spiralengine_widget_' . $widget_id . '_user_' . $this->user_id;
        $cached = wp_cache_get($cache_key, 'spiralengine');
        
        if ($cached === false) {
            $data = $this->get_widget_data($widget_id);
            wp_cache_set($cache_key, $data, 'spiralengine', 300); // 5 minutes
        }
    }
    
    /**
     * Generate WebSocket token
     *
     * @return string Token
     */
    private function generate_ws_token() {
        $data = array(
            'user_id' => $this->user_id,
            'timestamp' => time(),
            'nonce' => wp_create_nonce('spiralengine_ws')
        );
        
        return base64_encode(json_encode($data));
    }
    
    /**
     * Can access widget
     *
     * @param string $widget_id Widget ID
     * @return bool Can access
     */
    private function can_access_widget($widget_id) {
        // Public widgets
        $public_widgets = array('welcome', 'basic_info', 'public_stats');
        
        if (in_array($widget_id, $public_widgets)) {
            return true;
        }
        
        // Must be logged in for other widgets
        if (!is_user_logged_in()) {
            return false;
        }
        
        // Check membership level
        $membership_widgets = array(
            'discovery' => array('quick_assessment', 'recent_progress'),
            'explorer' => array('quick_assessment', 'recent_progress', 'basic_insights'),
            'navigator' => array('quick_assessment', 'recent_progress', 'basic_insights', 'ai_insights', 'analytics'),
            'voyager' => 'all' // Access to all widgets
        );
        
        $membership = $this->get_user_membership_level();
        
        if ($membership === 'voyager' || (isset($membership_widgets[$membership]) && $membership_widgets[$membership] === 'all')) {
            return true;
        }
        
        return isset($membership_widgets[$membership]) && in_array($widget_id, $membership_widgets[$membership]);
    }
    
    /**
     * Get widget data
     *
     * @param string $widget_id Widget ID
     * @return array|null Widget data
     */
    private function get_widget_data($widget_id) {
        $widget_data = apply_filters('spiralengine_widget_data_' . $widget_id, null, $this->user_id);
        
        if ($widget_data !== null) {
            return $widget_data;
        }
        
        // Default widget data
        switch ($widget_id) {
            case 'welcome':
                return array(
                    'title' => sprintf(__('Welcome back, %s!', 'spiral-engine'), wp_get_current_user()->display_name),
                    'content' => __('Ready to continue your wellness journey?', 'spiral-engine')
                );
                
            case 'quick_assessment':
                return array(
                    'title' => __('Quick Check-In', 'spiral-engine'),
                    'type' => 'mood',
                    'last_assessment' => $this->get_last_assessment('mood')
                );
                
            case 'recent_progress':
                return array(
                    'title' => __('Your Progress', 'spiral-engine'),
                    'data' => $this->get_recent_progress_data()
                );
                
            default:
                return null;
        }
    }
    
    /**
     * Render widget
     *
     * @param string $widget_id Widget ID
     * @param array $data Widget data
     */
    private function render_widget($widget_id, $data) {
        $template_file = SPIRALENGINE_PLUGIN_DIR . 'templates/widgets/' . $widget_id . '.php';
        
        if (file_exists($template_file)) {
            include $template_file;
        } else {
            // Fallback to generic widget template
            include SPIRALENGINE_PLUGIN_DIR . 'templates/widgets/generic.php';
        }
    }
    
    /**
     * Get widget configuration
     *
     * @param string $widget_id Widget ID
     * @return array Widget config
     */
    private function get_widget_config($widget_id) {
        $configs = array(
            'welcome' => array(
                'refreshable' => false,
                'collapsible' => true,
                'removable' => false
            ),
            'quick_assessment' => array(
                'refreshable' => true,
                'collapsible' => true,
                'removable' => true,
                'refresh_interval' => 0
            ),
            'recent_progress' => array(
                'refreshable' => true,
                'collapsible' => true,
                'removable' => true,
                'refresh_interval' => 300 // 5 minutes
            )
        );
        
        return isset($configs[$widget_id]) ? $configs[$widget_id] : array(
            'refreshable' => true,
            'collapsible' => true,
            'removable' => true
        );
    }
    
    /**
     * Is valid preference
     *
     * @param string $key Preference key
     * @param mixed $value Preference value
     * @return bool Is valid
     */
    private function is_valid_preference($key, $value) {
        $valid_prefs = array(
            'theme' => array('auto', 'light', 'dark'),
            'notifications' => array('all', 'important', 'none'),
            'email_frequency' => array('immediate', 'daily', 'weekly', 'never'),
            'language' => array_keys(get_available_languages()),
            'timezone' => timezone_identifiers_list()
        );
        
        if (!isset($valid_prefs[$key])) {
            // Allow any preference not in the list
            return true;
        }
        
        return in_array($value, $valid_prefs[$key]);
    }
    
    /**
     * Get user data by type
     *
     * @param string $type Data type
     * @return array User data
     */
    private function get_user_data_by_type($type) {
        switch ($type) {
            case 'profile':
                return $this->get_user_profile_data();
                
            case 'stats':
                return $this->get_user_stats();
                
            case 'progress':
                return $this->get_user_progress();
                
            case 'achievements':
                return $this->get_user_achievements();
                
            default:
                return array();
        }
    }
    
    /**
     * Get user profile data
     *
     * @return array Profile data
     */
    private function get_user_profile_data() {
        $user = wp_get_current_user();
        
        return array(
            'id' => $user->ID,
            'name' => $user->display_name,
            'email' => $user->user_email,
            'avatar' => get_avatar_url($user->ID),
            'member_since' => $user->user_registered,
            'membership' => $this->get_user_membership_level(),
            'preferences' => $this->get_user_preferences()
        );
    }
    
    /**
     * Get user stats
     *
     * @return array User statistics
     */
    private function get_user_stats() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'spiralengine_user_stats';
        
        $stats = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE user_id = %d",
            $this->user_id
        ), ARRAY_A);
        
        if (!$stats) {
            $stats = array(
                'total_assessments' => 0,
                'streak_days' => 0,
                'total_points' => 0,
                'achievements_earned' => 0
            );
        }
        
        return $stats;
    }
    
    /**
     * Get user progress
     *
     * @return array Progress data
     */
    private function get_user_progress() {
        // Get last 30 days of progress
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'spiralengine_progress';
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name 
            WHERE user_id = %d 
            AND created_at > %s 
            ORDER BY created_at DESC",
            $this->user_id,
            date('Y-m-d', strtotime('-30 days'))
        ), ARRAY_A);
    }
    
    /**
     * Get user achievements
     *
     * @return array Achievements
     */
    private function get_user_achievements() {
        return get_user_meta($this->user_id, 'spiralengine_achievements', true) ?: array();
    }
    
    /**
     * Get user preferences
     *
     * @return array Preferences
     */
    private function get_user_preferences() {
        $cache_key = 'spiralengine_user_prefs_' . $this->user_id;
        $cached = wp_cache_get($cache_key, 'spiralengine');
        
        if ($cached !== false) {
            return $cached;
        }
        
        global $wpdb;
        
        $prefs = $wpdb->get_results($wpdb->prepare(
            "SELECT meta_key, meta_value FROM {$wpdb->usermeta} 
            WHERE user_id = %d 
            AND meta_key LIKE 'spiralengine_pref_%'",
            $this->user_id
        ));
        
        $preferences = array();
        foreach ($prefs as $pref) {
            $key = str_replace('spiralengine_pref_', '', $pref->meta_key);
            $preferences[$key] = maybe_unserialize($pref->meta_value);
        }
        
        wp_cache_set($cache_key, $preferences, 'spiralengine', 300);
        
        return $preferences;
    }
    
    /**
     * Get dashboard widgets
     *
     * @return array Widget data
     */
    private function get_dashboard_widgets() {
        $config = get_user_meta($this->user_id, 'spiralengine_dashboard_config', true);
        
        if (!$config || !isset($config['widgets'])) {
            $config = $this->get_default_dashboard_config();
        }
        
        $widgets = array();
        foreach ($config['widgets'] as $widget_config) {
            if ($this->can_access_widget($widget_config['id'])) {
                $widgets[] = array_merge($widget_config, array(
                    'data' => $this->get_widget_data($widget_config['id'])
                ));
            }
        }
        
        return $widgets;
    }
    
    /**
     * Get assessment data
     *
     * @param string $type Assessment type
     * @return array Assessment data
     */
    private function get_assessment_data($type = '') {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'spiralengine_assessments';
        
        $query = "SELECT * FROM $table_name WHERE user_id = %d";
        $params = array($this->user_id);
        
        if ($type) {
            $query .= " AND assessment_type = %s";
            $params[] = $type;
        }
        
        $query .= " ORDER BY completed_at DESC LIMIT 10";
        
        return $wpdb->get_results($wpdb->prepare($query, $params), ARRAY_A);
    }
    
    /**
     * Get progress data
     *
     * @return array Progress data
     */
    private function get_progress_data() {
        return array(
            'daily' => $this->get_daily_progress(),
            'weekly' => $this->get_weekly_progress(),
            'monthly' => $this->get_monthly_progress()
        );
    }
    
    /**
     * Get last assessment
     *
     * @param string $type Assessment type
     * @return array|null Assessment data
     */
    private function get_last_assessment($type) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'spiralengine_assessments';
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name 
            WHERE user_id = %d AND assessment_type = %s 
            ORDER BY completed_at DESC LIMIT 1",
            $this->user_id,
            $type
        ), ARRAY_A);
    }
    
    /**
     * Get recent progress data
     *
     * @return array Progress data
     */
    private function get_recent_progress_data() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'spiralengine_progress';
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name 
            WHERE user_id = %d 
            AND created_at > %s 
            ORDER BY created_at DESC 
            LIMIT 7",
            $this->user_id,
            date('Y-m-d', strtotime('-7 days'))
        ), ARRAY_A);
    }
    
    /**
     * Get daily progress
     *
     * @return array Daily progress
     */
    private function get_daily_progress() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'spiralengine_progress';
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT 
                COUNT(*) as activities,
                AVG(score) as avg_score,
                MAX(score) as best_score
            FROM $table_name 
            WHERE user_id = %d 
            AND DATE(created_at) = %s",
            $this->user_id,
            current_time('Y-m-d')
        ), ARRAY_A);
    }
    
    /**
     * Get weekly progress
     *
     * @return array Weekly progress
     */
    private function get_weekly_progress() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'spiralengine_progress';
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT 
                DATE(created_at) as date,
                COUNT(*) as activities,
                AVG(score) as avg_score
            FROM $table_name 
            WHERE user_id = %d 
            AND created_at > %s 
            GROUP BY DATE(created_at)
            ORDER BY date DESC",
            $this->user_id,
            date('Y-m-d', strtotime('-7 days'))
        ), ARRAY_A);
    }
    
    /**
     * Get monthly progress
     *
     * @return array Monthly progress
     */
    private function get_monthly_progress() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'spiralengine_progress';
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT 
                WEEK(created_at) as week,
                COUNT(*) as activities,
                AVG(score) as avg_score
            FROM $table_name 
            WHERE user_id = %d 
            AND created_at > %s 
            GROUP BY WEEK(created_at)
            ORDER BY week DESC",
            $this->user_id,
            date('Y-m-d', strtotime('-30 days'))
        ), ARRAY_A);
    }
}

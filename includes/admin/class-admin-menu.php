<?php
/**
 * SpiralEngine Admin Menu System
 *
 * @package    SpiralEngine
 * @subpackage Admin
 * @file       includes/admin/class-admin-menu.php
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class SpiralEngine_Admin_Menu
 *
 * Handles all admin menu registration and management
 */
class SpiralEngine_Admin_Menu {
    
    /**
     * Instance of this class
     *
     * @var SpiralEngine_Admin_Menu
     */
    private static $instance = null;
    
    /**
     * Menu slug constants
     */
    const MAIN_MENU_SLUG = 'spiralengine';
    const DASHBOARD_SLUG = 'spiralengine';
    const USERS_SLUG = 'spiralengine-users';
    const EPISODES_SLUG = 'spiralengine-episodes';
    const WIDGETS_SLUG = 'spiralengine-widgets';
    const ANALYTICS_SLUG = 'spiralengine-analytics';
    const SETTINGS_SLUG = 'spiralengine-settings';
    const AI_CONSOLE_SLUG = 'spiralengine-ai-console';
    const BILLING_SLUG = 'spiralengine-billing';
    const SYSTEM_HEALTH_SLUG = 'spiralengine-system-health';
    const DEVELOPER_SLUG = 'spiralengine-developer';
    const IMPORT_EXPORT_SLUG = 'spiralengine-import-export';
    
    /**
     * Menu capability requirements
     *
     * @var array
     */
    private $menu_capabilities = array(
        'dashboard' => 'manage_options',
        'users' => 'spiralengine_manage_users',
        'episodes' => 'spiralengine_view_episodes',
        'widgets' => 'spiralengine_manage_widgets',
        'analytics' => 'spiralengine_view_analytics',
        'settings' => 'spiralengine_manage_settings',
        'ai_console' => 'spiralengine_manage_ai',
        'billing' => 'spiralengine_manage_billing',
        'system_health' => 'spiralengine_view_system',
        'developer' => 'spiralengine_developer_access',
        'import_export' => 'spiralengine_import_export'
    );
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->init();
    }
    
    /**
     * Get instance
     *
     * @return SpiralEngine_Admin_Menu
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Initialize admin menu
     */
    public function init() {
        add_action('admin_menu', array($this, 'register_menus'));
        add_action('admin_menu', array($this, 'adjust_menu_order'), 999);
        add_action('admin_bar_menu', array($this, 'add_admin_bar_menu'), 100);
        add_filter('parent_file', array($this, 'highlight_parent_menu'));
        add_filter('submenu_file', array($this, 'highlight_submenu'));
        add_action('admin_init', array($this, 'redirect_legacy_pages'));
    }
    
    /**
     * Register admin menus
     */
    public function register_menus() {
        // Main menu
        add_menu_page(
            __('SpiralEngine', 'spiralengine'),
            __('SpiralEngine', 'spiralengine'),
            $this->menu_capabilities['dashboard'],
            self::MAIN_MENU_SLUG,
            array($this, 'render_dashboard_page'),
            $this->get_menu_icon(),
            30
        );
        
        // Dashboard (rename the first submenu item)
        add_submenu_page(
            self::MAIN_MENU_SLUG,
            __('Dashboard', 'spiralengine'),
            __('Dashboard', 'spiralengine'),
            $this->menu_capabilities['dashboard'],
            self::MAIN_MENU_SLUG,
            array($this, 'render_dashboard_page')
        );
        
        // Users Management
        add_submenu_page(
            self::MAIN_MENU_SLUG,
            __('Users', 'spiralengine'),
            __('Users', 'spiralengine'),
            $this->menu_capabilities['users'],
            self::USERS_SLUG,
            array($this, 'render_users_page')
        );
        
        // Episodes Management
        add_submenu_page(
            self::MAIN_MENU_SLUG,
            __('Episodes', 'spiralengine'),
            __('Episodes', 'spiralengine'),
            $this->menu_capabilities['episodes'],
            self::EPISODES_SLUG,
            array($this, 'render_episodes_page')
        );
        
        // Widget Management
        add_submenu_page(
            self::MAIN_MENU_SLUG,
            __('Widgets', 'spiralengine'),
            __('Widgets', 'spiralengine'),
            $this->menu_capabilities['widgets'],
            self::WIDGETS_SLUG,
            array($this, 'render_widgets_page')
        );
        
        // Analytics
        add_submenu_page(
            self::MAIN_MENU_SLUG,
            __('Analytics', 'spiralengine'),
            __('Analytics', 'spiralengine'),
            $this->menu_capabilities['analytics'],
            self::ANALYTICS_SLUG,
            array($this, 'render_analytics_page')
        );
        
        // AI Console
        if ($this->has_ai_providers()) {
            add_submenu_page(
                self::MAIN_MENU_SLUG,
                __('AI Console', 'spiralengine'),
                __('AI Console', 'spiralengine'),
                $this->menu_capabilities['ai_console'],
                self::AI_CONSOLE_SLUG,
                array($this, 'render_ai_console_page')
            );
        }
        
        // Billing (if Stripe is configured)
        if ($this->has_stripe_configured()) {
            add_submenu_page(
                self::MAIN_MENU_SLUG,
                __('Billing', 'spiralengine'),
                __('Billing', 'spiralengine'),
                $this->menu_capabilities['billing'],
                self::BILLING_SLUG,
                array($this, 'render_billing_page')
            );
        }
        
        // Import/Export
        add_submenu_page(
            self::MAIN_MENU_SLUG,
            __('Import/Export', 'spiralengine'),
            __('Import/Export', 'spiralengine'),
            $this->menu_capabilities['import_export'],
            self::IMPORT_EXPORT_SLUG,
            array($this, 'render_import_export_page')
        );
        
        // System Health
        add_submenu_page(
            self::MAIN_MENU_SLUG,
            __('System Health', 'spiralengine'),
            __('System Health', 'spiralengine'),
            $this->menu_capabilities['system_health'],
            self::SYSTEM_HEALTH_SLUG,
            array($this, 'render_system_health_page')
        );
        
        // Developer Tools (hidden by default)
        if ($this->is_developer_mode()) {
            add_submenu_page(
                self::MAIN_MENU_SLUG,
                __('Developer', 'spiralengine'),
                __('Developer', 'spiralengine') . ' 🔧',
                $this->menu_capabilities['developer'],
                self::DEVELOPER_SLUG,
                array($this, 'render_developer_page')
            );
        }
        
        // Settings (last item)
        add_submenu_page(
            self::MAIN_MENU_SLUG,
            __('Settings', 'spiralengine'),
            __('Settings', 'spiralengine'),
            $this->menu_capabilities['settings'],
            self::SETTINGS_SLUG,
            array($this, 'render_settings_page')
        );
    }
    
    /**
     * Add admin bar menu items
     *
     * @param WP_Admin_Bar $wp_admin_bar
     */
    public function add_admin_bar_menu($wp_admin_bar) {
        if (!current_user_can($this->menu_capabilities['dashboard'])) {
            return;
        }
        
        // Parent node
        $wp_admin_bar->add_node(array(
            'id' => 'spiralengine',
            'title' => '<span class="ab-icon dashicons dashicons-chart-area"></span>' . __('SpiralEngine', 'spiralengine'),
            'href' => admin_url('admin.php?page=' . self::MAIN_MENU_SLUG),
            'meta' => array(
                'title' => __('SpiralEngine Quick Access', 'spiralengine')
            )
        ));
        
        // Quick Stats
        $stats = $this->get_quick_stats();
        if ($stats) {
            $wp_admin_bar->add_node(array(
                'id' => 'spiralengine-stats',
                'parent' => 'spiralengine',
                'title' => sprintf(
                    __('%d Users | %d Episodes Today', 'spiralengine'),
                    $stats['total_users'],
                    $stats['episodes_today']
                ),
                'href' => admin_url('admin.php?page=' . self::ANALYTICS_SLUG),
                'meta' => array(
                    'class' => 'spiralengine-admin-bar-stats'
                )
            ));
        }
        
        // Add New Episode (for testing)
        if (current_user_can('spiralengine_create_episodes')) {
            $wp_admin_bar->add_node(array(
                'id' => 'spiralengine-new-episode',
                'parent' => 'spiralengine',
                'title' => __('New Test Episode', 'spiralengine'),
                'href' => admin_url('admin.php?page=' . self::EPISODES_SLUG . '&action=new'),
                'meta' => array(
                    'title' => __('Create a test episode', 'spiralengine')
                )
            ));
        }
        
        // View Frontend
        $wp_admin_bar->add_node(array(
            'id' => 'spiralengine-view-frontend',
            'parent' => 'spiralengine',
            'title' => __('View Dashboard', 'spiralengine'),
            'href' => home_url('/spiralengine-dashboard/'),
            'meta' => array(
                'target' => '_blank',
                'title' => __('View user dashboard', 'spiralengine')
            )
        ));
        
        // Clear Cache
        if (current_user_can('spiralengine_manage_settings')) {
            $wp_admin_bar->add_node(array(
                'id' => 'spiralengine-clear-cache',
                'parent' => 'spiralengine',
                'title' => __('Clear Cache', 'spiralengine'),
                'href' => wp_nonce_url(
                    admin_url('admin.php?page=' . self::MAIN_MENU_SLUG . '&action=clear_cache'),
                    'spiralengine_clear_cache'
                ),
                'meta' => array(
                    'title' => __('Clear all SpiralEngine caches', 'spiralengine')
                )
            ));
        }
    }
    
    /**
     * Get menu icon SVG
     *
     * @return string
     */
    private function get_menu_icon() {
        $svg = '<svg viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path fill="currentColor" d="M10 2C5.58 2 2 5.58 2 10s3.58 8 8 8 8-3.58 8-8-3.58-8-8-8zm0 14c-3.31 0-6-2.69-6-6s2.69-6 6-6 6 2.69 6 6-2.69 6-6 6zm0-10c-2.21 0-4 1.79-4 4s1.79 4 4 4 4-1.79 4-4-1.79-4-4-4zm0 6c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2z"/></svg>';
        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }
    
    /**
     * Check if AI providers are configured
     *
     * @return bool
     */
    private function has_ai_providers() {
        $ai_settings = get_option('spiralengine_ai_settings', array());
        return !empty($ai_settings['providers']);
    }
    
    /**
     * Check if Stripe is configured
     *
     * @return bool
     */
    private function has_stripe_configured() {
        $payment_settings = get_option('spiralengine_payment_settings', array());
        return !empty($payment_settings['stripe_publishable_key']) && !empty($payment_settings['stripe_secret_key']);
    }
    
    /**
     * Check if developer mode is enabled
     *
     * @return bool
     */
    private function is_developer_mode() {
        return defined('SPIRALENGINE_DEVELOPER_MODE') && SPIRALENGINE_DEVELOPER_MODE;
    }
    
    /**
     * Get quick stats for admin bar
     *
     * @return array
     */
    private function get_quick_stats() {
        $cache_key = 'spiralengine_admin_quick_stats';
        $stats = get_transient($cache_key);
        
        if (false === $stats) {
            global $wpdb;
            
            // Get total users with memberships
            $total_users = $wpdb->get_var(
                "SELECT COUNT(DISTINCT user_id) FROM {$wpdb->prefix}spiralengine_memberships WHERE status = 'active'"
            );
            
            // Get episodes today
            $episodes_today = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}spiralengine_episodes 
                WHERE DATE(created_at) = %s",
                current_time('Y-m-d')
            ));
            
            $stats = array(
                'total_users' => intval($total_users),
                'episodes_today' => intval($episodes_today)
            );
            
            set_transient($cache_key, $stats, HOUR_IN_SECONDS);
        }
        
        return $stats;
    }
    
    /**
     * Highlight parent menu for custom pages
     *
     * @param string $parent_file
     * @return string
     */
    public function highlight_parent_menu($parent_file) {
        global $plugin_page;
        
        $spiralengine_pages = array(
            self::DASHBOARD_SLUG,
            self::USERS_SLUG,
            self::EPISODES_SLUG,
            self::WIDGETS_SLUG,
            self::ANALYTICS_SLUG,
            self::SETTINGS_SLUG,
            self::AI_CONSOLE_SLUG,
            self::BILLING_SLUG,
            self::SYSTEM_HEALTH_SLUG,
            self::DEVELOPER_SLUG,
            self::IMPORT_EXPORT_SLUG
        );
        
        if (in_array($plugin_page, $spiralengine_pages)) {
            $parent_file = self::MAIN_MENU_SLUG;
        }
        
        return $parent_file;
    }
    
    /**
     * Highlight submenu for custom pages
     *
     * @param string $submenu_file
     * @return string
     */
    public function highlight_submenu($submenu_file) {
        global $plugin_page;
        
        // Handle special cases where submenu should be highlighted differently
        if ($plugin_page === self::DASHBOARD_SLUG) {
            $submenu_file = self::MAIN_MENU_SLUG;
        }
        
        return $submenu_file;
    }
    
    /**
     * Redirect legacy admin pages
     */
    public function redirect_legacy_pages() {
        global $pagenow;
        
        if ($pagenow === 'admin.php' && isset($_GET['page'])) {
            $legacy_redirects = array(
                'spiralengine-admin' => self::DASHBOARD_SLUG,
                'spiralengine_users' => self::USERS_SLUG,
                'spiralengine_episodes' => self::EPISODES_SLUG
            );
            
            if (isset($legacy_redirects[$_GET['page']])) {
                wp_redirect(admin_url('admin.php?page=' . $legacy_redirects[$_GET['page']]));
                exit;
            }
        }
    }
    
    /**
     * Adjust menu order
     *
     * @param array $menu_order
     * @return array
     */
    public function adjust_menu_order($menu_order) {
        global $submenu;
        
        // Ensure our submenu items are in the correct order
        if (isset($submenu[self::MAIN_MENU_SLUG])) {
            $new_order = array();
            $order_map = array(
                self::MAIN_MENU_SLUG => 0,
                self::USERS_SLUG => 1,
                self::EPISODES_SLUG => 2,
                self::WIDGETS_SLUG => 3,
                self::ANALYTICS_SLUG => 4,
                self::AI_CONSOLE_SLUG => 5,
                self::BILLING_SLUG => 6,
                self::IMPORT_EXPORT_SLUG => 7,
                self::SYSTEM_HEALTH_SLUG => 8,
                self::DEVELOPER_SLUG => 9,
                self::SETTINGS_SLUG => 99
            );
            
            // Sort submenu items
            usort($submenu[self::MAIN_MENU_SLUG], function($a, $b) use ($order_map) {
                $a_order = isset($order_map[$a[2]]) ? $order_map[$a[2]] : 50;
                $b_order = isset($order_map[$b[2]]) ? $order_map[$b[2]] : 50;
                return $a_order - $b_order;
            });
        }
        
        return $menu_order;
    }
    
    /**
     * Render dashboard page
     */
    public function render_dashboard_page() {
        $analytics = SpiralEngine_Admin_Analytics::get_instance();
        $analytics->render_dashboard();
    }
    
    /**
     * Render users page
     */
    public function render_users_page() {
        $user_management = SpiralEngine_User_Management::get_instance();
        $user_management->render_page();
    }
    
    /**
     * Render episodes page
     */
    public function render_episodes_page() {
        // Use existing episodes view
        include SPIRALENGINE_PLUGIN_DIR . 'admin/views/episodes.php';
    }
    
    /**
     * Render widgets page
     */
    public function render_widgets_page() {
        $widget_management = SpiralEngine_Widget_Management::get_instance();
        $widget_management->render_page();
    }
    
    /**
     * Render analytics page
     */
    public function render_analytics_page() {
        $analytics = SpiralEngine_Admin_Analytics::get_instance();
        $analytics->render_full_page();
    }
    
    /**
     * Render AI console page
     */
    public function render_ai_console_page() {
        echo '<div class="wrap">';
        echo '<h1>' . __('AI Console', 'spiralengine') . '</h1>';
        echo '<p>' . __('AI console functionality coming soon.', 'spiralengine') . '</p>';
        echo '</div>';
    }
    
    /**
     * Render billing page
     */
    public function render_billing_page() {
        echo '<div class="wrap">';
        echo '<h1>' . __('Billing Management', 'spiralengine') . '</h1>';
        echo '<p>' . __('Billing management functionality coming soon.', 'spiralengine') . '</p>';
        echo '</div>';
    }
    
    /**
     * Render system health page
     */
    public function render_system_health_page() {
        $system_health = new SpiralEngine_System_Health();
        $system_health->render_page();
    }
    
    /**
     * Render developer page
     */
    public function render_developer_page() {
        echo '<div class="wrap">';
        echo '<h1>' . __('Developer Tools', 'spiralengine') . '</h1>';
        echo '<p>' . __('Developer tools functionality coming soon.', 'spiralengine') . '</p>';
        echo '</div>';
    }
    
    /**
     * Render import/export page
     */
    public function render_import_export_page() {
        $import_export = new SpiralEngine_Import_Export();
        $import_export->render_page();
    }
    
    /**
     * Render settings page
     */
    public function render_settings_page() {
        $settings = SpiralEngine_Admin_Settings::get_instance();
        $settings->render_page();
    }
}


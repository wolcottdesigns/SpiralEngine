<?php
// includes/admin/class-spiralengine-admin-menu.php

/**
 * Spiral Engine Admin Menu Handler
 * 
 * Creates the admin menu structure with role-based visibility
 * and quick action integration based on Master Control Center specs
 */
class SPIRALENGINE_Admin_Menu {
    
    /**
     * Instance of this class
     */
    private static $instance = null;
    
    /**
     * Menu capability requirements
     */
    private $capabilities = array(
        'dashboard' => 'manage_options',
        'analytics' => 'manage_options',
        'episodes' => 'edit_posts',
        'widgets' => 'manage_options',
        'users' => 'list_users',
        'ai' => 'manage_options',
        'security' => 'manage_options',
        'system' => 'manage_options',
        'master' => 'manage_network' // Super admin only
    );
    
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
     * Initialize admin menu
     */
    public function init() {
        add_action('admin_menu', array($this, 'create_admin_menu'));
        add_action('admin_bar_menu', array($this, 'add_admin_bar_menu'), 100);
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        add_filter('admin_body_class', array($this, 'add_admin_body_classes'));
    }
    
    /**
     * Create admin menu structure
     */
    public function create_admin_menu() {
        // Main Spiral Engine menu
        add_menu_page(
            __('Spiral Engine Dashboard', 'spiral-engine'),
            __('Spiral Engine', 'spiral-engine'),
            $this->capabilities['dashboard'],
            'spiral-engine',
            array($this, 'render_dashboard'),
            $this->get_menu_icon(),
            3 // Position after Dashboard
        );
        
        // Dashboard submenu (rename the first item)
        add_submenu_page(
            'spiral-engine',
            __('Command Center', 'spiral-engine'),
            __('Command Center', 'spiral-engine'),
            $this->capabilities['dashboard'],
            'spiral-engine',
            array($this, 'render_dashboard')
        );
        
        // Analytics Review Center
        add_submenu_page(
            'spiral-engine',
            __('Analytics Review Center', 'spiral-engine'),
            __('Analytics', 'spiral-engine'),
            $this->capabilities['analytics'],
            'spiral-engine-analytics',
            array($this, 'render_analytics')
        );
        
        // Episode Management
        add_submenu_page(
            'spiral-engine',
            __('Episode Management', 'spiral-engine'),
            __('Episodes', 'spiral-engine'),
            $this->capabilities['episodes'],
            'spiral-engine-episodes',
            array($this, 'render_episodes')
        );
        
        // Widget Studio
        add_submenu_page(
            'spiral-engine',
            __('Widget Studio', 'spiral-engine'),
            __('Widget Studio', 'spiral-engine'),
            $this->capabilities['widgets'],
            'spiral-engine-widgets',
            array($this, 'render_widgets')
        );
        
        // User Management Center
        add_submenu_page(
            'spiral-engine',
            __('User Management Center', 'spiral-engine'),
            __('Users', 'spiral-engine'),
            $this->capabilities['users'],
            'spiral-engine-users',
            array($this, 'render_users')
        );
        
        // AI Command Center
        add_submenu_page(
            'spiral-engine',
            __('AI Command Center', 'spiral-engine'),
            __('AI Center', 'spiral-engine'),
            $this->capabilities['ai'],
            'spiral-engine-ai',
            array($this, 'render_ai')
        );
        
        // Security Command Center
        add_submenu_page(
            'spiral-engine',
            __('Security Command Center', 'spiral-engine'),
            __('Security', 'spiral-engine'),
            $this->capabilities['security'],
            'spiral-engine-security',
            array($this, 'render_security')
        );
        
        // System Tools Center
        add_submenu_page(
            'spiral-engine',
            __('System Tools Center', 'spiral-engine'),
            __('System Tools', 'spiral-engine'),
            $this->capabilities['system'],
            'spiral-engine-system',
            array($this, 'render_system')
        );
        
        // Master Control Center (Super Admin only)
        if (is_super_admin()) {
            add_submenu_page(
                'spiral-engine',
                __('Master Control Center', 'spiral-engine'),
                __('Master Control', 'spiral-engine'),
                $this->capabilities['master'],
                'spiral-engine-master',
                array($this, 'render_master')
            );
        }
    }
    
    /**
     * Add quick actions to admin bar
     */
    public function add_admin_bar_menu($wp_admin_bar) {
        if (!current_user_can($this->capabilities['dashboard'])) {
            return;
        }
        
        // Main Spiral Engine node
        $wp_admin_bar->add_node(array(
            'id' => 'spiral-engine-admin-bar',
            'title' => '<span class="ab-icon"></span><span class="ab-label">' . __('Spiral Engine', 'spiral-engine') . '</span>',
            'href' => admin_url('admin.php?page=spiral-engine'),
            'meta' => array(
                'class' => 'spiral-engine-admin-bar-menu'
            )
        ));
        
        // Quick actions
        $quick_actions = array(
            'health-check' => array(
                'title' => __('System Health Check', 'spiral-engine'),
                'href' => '#',
                'meta' => array('class' => 'spiral-quick-action', 'data-action' => 'health-check')
            ),
            'clear-cache' => array(
                'title' => __('Clear All Caches', 'spiral-engine'),
                'href' => '#',
                'meta' => array('class' => 'spiral-quick-action', 'data-action' => 'clear-cache')
            ),
            'episode-stats' => array(
                'title' => __('Today\'s Episodes', 'spiral-engine'),
                'href' => admin_url('admin.php?page=spiral-engine-analytics&view=episodes')
            ),
            'active-users' => array(
                'title' => __('Active Users Now', 'spiral-engine'),
                'href' => admin_url('admin.php?page=spiral-engine-analytics&view=users')
            )
        );
        
        foreach ($quick_actions as $id => $action) {
            $wp_admin_bar->add_node(array(
                'id' => 'spiral-engine-' . $id,
                'parent' => 'spiral-engine-admin-bar',
                'title' => $action['title'],
                'href' => $action['href'],
                'meta' => isset($action['meta']) ? $action['meta'] : array()
            ));
        }
        
        // Emergency actions for super admin
        if (is_super_admin()) {
            $wp_admin_bar->add_node(array(
                'id' => 'spiral-engine-emergency',
                'parent' => 'spiral-engine-admin-bar',
                'title' => '<span style="color: #ff6b6b;">' . __('Emergency Controls', 'spiral-engine') . '</span>',
                'href' => admin_url('admin.php?page=spiral-engine-master&section=emergency'),
                'meta' => array('class' => 'spiral-emergency-link')
            ));
        }
    }
    
    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook) {
        // Check if we're on a Spiral Engine page
        if (strpos($hook, 'spiral-engine') === false) {
            return;
        }
        
        // Core admin styles
        wp_enqueue_style(
            'spiralengine-admin',
            SPIRALENGINE_URL . 'assets/admin/css/spiralengine-admin.css',
            array(),
            SPIRALENGINE_VERSION
        );
        
        // Chart.js for analytics
        if (strpos($hook, 'analytics') !== false || strpos($hook, 'spiral-engine_page_spiral-engine') !== false) {
            wp_enqueue_script(
                'chart-js',
                'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js',
                array(),
                '4.4.0',
                true
            );
        }
        
        // Core admin scripts
        wp_enqueue_script(
            'spiralengine-admin',
            SPIRALENGINE_URL . 'assets/admin/js/spiralengine-admin.js',
            array('jquery', 'wp-api'),
            SPIRALENGINE_VERSION,
            true
        );
        
        // Localize script
        wp_localize_script('spiralengine-admin', 'spiralengineAdmin', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'apiUrl' => home_url('/wp-json/spiral-engine/v1/'),
            'nonce' => wp_create_nonce('spiral-engine-admin'),
            'strings' => array(
                'confirmEmergencyShutdown' => __('Are you sure you want to initiate emergency shutdown? This action requires confirmation.', 'spiral-engine'),
                'systemHealthOk' => __('All systems operational', 'spiral-engine'),
                'cacheCleared' => __('All caches cleared successfully', 'spiral-engine')
            ),
            'refreshIntervals' => array(
                'critical' => 5000,    // 5 seconds
                'performance' => 30000, // 30 seconds
                'analytics' => 60000    // 60 seconds
            )
        ));
    }
    
    /**
     * Add admin body classes
     */
    public function add_admin_body_classes($classes) {
        $screen = get_current_screen();
        
        if (strpos($screen->id, 'spiral-engine') !== false) {
            $classes .= ' spiral-engine-admin';
            
            // Add dark mode class if enabled
            $user_preferences = get_user_meta(get_current_user_id(), 'spiral_engine_preferences', true);
            if (!empty($user_preferences['dark_mode'])) {
                $classes .= ' spiral-engine-dark-mode';
            }
            
            // Add specific page class
            $classes .= ' spiral-engine-' . str_replace('spiral-engine-', '', $screen->id);
        }
        
        return $classes;
    }
    
    /**
     * Get menu icon SVG
     */
    private function get_menu_icon() {
        $icon_svg = '<svg width="20" height="20" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
            <path fill="black" d="M10 0C4.477 0 0 4.477 0 10s4.477 10 10 10 10-4.477 10-10S15.523 0 10 0zm0 18c-4.411 0-8-3.589-8-8s3.589-8 8-8 8 3.589 8 8-3.589 8-8 8zm0-14c-3.309 0-6 2.691-6 6s2.691 6 6 6 6-2.691 6-6-2.691-6-6-6zm0 10c-2.206 0-4-1.794-4-4s1.794-4 4-4 4 1.794 4 4-1.794 4-4 4z"/>
        </svg>';
        
        return 'data:image/svg+xml;base64,' . base64_encode($icon_svg);
    }
    
    /**
     * Render methods for each page
     */
    public function render_dashboard() {
        $dashboard = SPIRALENGINE_Admin_Dashboard::get_instance();
        $dashboard->render();
    }
    
    public function render_analytics() {
        $analytics = SPIRALENGINE_Admin_Analytics::get_instance();
        $analytics->render();
    }
    
    public function render_episodes() {
        $episodes = SPIRALENGINE_Admin_Episodes::get_instance();
        $episodes->render();
    }
    
    public function render_widgets() {
        $widgets = SPIRALENGINE_Admin_Widgets::get_instance();
        $widgets->render();
    }
    
    public function render_users() {
        echo '<div class="wrap"><h1>' . __('User Management Center', 'spiral-engine') . '</h1>';
        echo '<p>' . __('User management interface coming soon.', 'spiral-engine') . '</p></div>';
    }
    
    public function render_ai() {
        echo '<div class="wrap"><h1>' . __('AI Command Center', 'spiral-engine') . '</h1>';
        echo '<p>' . __('AI management interface coming soon.', 'spiral-engine') . '</p></div>';
    }
    
    public function render_security() {
        echo '<div class="wrap"><h1>' . __('Security Command Center', 'spiral-engine') . '</h1>';
        echo '<p>' . __('Security management interface coming soon.', 'spiral-engine') . '</p></div>';
    }
    
    public function render_system() {
        echo '<div class="wrap"><h1>' . __('System Tools Center', 'spiral-engine') . '</h1>';
        echo '<p>' . __('System tools interface coming soon.', 'spiral-engine') . '</p></div>';
    }
    
    public function render_master() {
        echo '<div class="wrap"><h1>' . __('Master Control Center', 'spiral-engine') . '</h1>';
        echo '<p>' . __('Master control interface coming soon.', 'spiral-engine') . '</p></div>';
    }
}


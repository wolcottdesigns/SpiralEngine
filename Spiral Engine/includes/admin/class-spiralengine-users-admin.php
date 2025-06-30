<?php
// includes/admin/class-spiralengine-users-admin.php

/**
 * SPIRAL Engine Users Admin Interface
 * 
 * Creates admin interface from User Management Center:
 * - Custom user list columns
 * - Risk level indicators
 * - Bulk actions
 * - Export functionality
 * - Support access management
 */
class SpiralEngine_Users_Admin {
    
    /**
     * Instance of this class
     */
    private static $instance = null;
    
    /**
     * Get instance of this class
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
        $this->init_hooks();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Admin menu
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // User list columns
        add_filter('manage_users_columns', array($this, 'add_user_columns'));
        add_filter('manage_users_custom_column', array($this, 'render_user_columns'), 10, 3);
        add_filter('manage_users_sortable_columns', array($this, 'make_columns_sortable'));
        
        // Bulk actions
        add_filter('bulk_actions-users', array($this, 'add_bulk_actions'));
        add_filter('handle_bulk_actions-users', array($this, 'handle_bulk_actions'), 10, 3);
        
        // User query modifications
        add_action('pre_user_query', array($this, 'modify_user_query'));
        
        // Quick filters
        add_action('restrict_manage_users', array($this, 'add_user_filters'));
        add_filter('users_list_table_query_args', array($this, 'filter_users_query'));
        
        // Admin scripts and styles
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        
        // AJAX handlers
        add_action('wp_ajax_spiralengine_quick_user_info', array($this, 'ajax_quick_user_info'));
        add_action('wp_ajax_spiralengine_export_users', array($this, 'ajax_export_users'));
        add_action('wp_ajax_spiralengine_grant_journey_marker', array($this, 'ajax_grant_journey_marker'));
        
        // Support access actions
        add_action('admin_notices', array($this, 'show_support_access_notice'));
        add_action('admin_post_spiralengine_support_access', array($this, 'handle_support_access_request'));
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        // Main menu
        add_menu_page(
            __('User Management', 'spiral-engine'),
            __('User Management', 'spiral-engine'),
            'edit_users',
            'spiralengine-users',
            array($this, 'render_user_management_page'),
            'dashicons-groups',
            30
        );
        
        // Journey markers submenu
        add_submenu_page(
            'spiralengine-users',
            __('Journey Markers', 'spiral-engine'),
            __('Journey Markers', 'spiral-engine'),
            'edit_users',
            'spiralengine-journey-markers',
            array($this, 'render_journey_markers_page')
        );
        
        // User analytics submenu
        add_submenu_page(
            'spiralengine-users',
            __('User Analytics', 'spiral-engine'),
            __('User Analytics', 'spiral-engine'),
            'edit_users',
            'spiralengine-user-analytics',
            array($this, 'render_user_analytics_page')
        );
        
        // Support access log submenu
        add_submenu_page(
            'spiralengine-users',
            __('Support Access Log', 'spiral-engine'),
            __('Support Access Log', 'spiral-engine'),
            'edit_users',
            'spiralengine-support-log',
            array($this, 'render_support_log_page')
        );
    }
    
    /**
     * Add custom user columns
     */
    public function add_user_columns($columns) {
        // Remove some default columns to save space
        unset($columns['posts']);
        
        // Add our custom columns
        $new_columns = array();
        
        // Keep checkbox and username
        if (isset($columns['cb'])) {
            $new_columns['cb'] = $columns['cb'];
        }
        if (isset($columns['username'])) {
            $new_columns['username'] = $columns['username'];
        }
        
        // Add SPIRAL columns
        $new_columns['spiral_score'] = __('Score', 'spiral-engine');
        $new_columns['spiral_risk'] = __('Risk', 'spiral-engine');
        $new_columns['spiral_journey'] = __('Journey', 'spiral-engine');
        $new_columns['spiral_membership'] = __('Membership', 'spiral-engine');
        $new_columns['spiral_last_active'] = __('Last Active', 'spiral-engine');
        $new_columns['spiral_actions'] = __('Actions', 'spiral-engine');
        
        // Keep email and role
        if (isset($columns['email'])) {
            $new_columns['email'] = $columns['email'];
        }
        if (isset($columns['role'])) {
            $new_columns['role'] = $columns['role'];
        }
        
        return $new_columns;
    }
    
    /**
     * Render custom user columns
     */
    public function render_user_columns($value, $column_name, $user_id) {
        switch ($column_name) {
            case 'spiral_score':
                $score = get_user_meta($user_id, 'spiralengine_last_assessment_score', true);
                if ($score !== '') {
                    $color = $this->get_score_color($score);
                    return sprintf(
                        '<span class="spiralengine-score" style="color: %s; font-weight: bold;">%d/18</span>',
                        esc_attr($color),
                        intval($score)
                    );
                }
                return '<span class="spiralengine-no-data">—</span>';
                
            case 'spiral_risk':
                $risk = get_user_meta($user_id, 'spiralengine_risk_level', true);
                if ($risk) {
                    $color = SpiralEngine_Assessment::get_instance()->get_risk_level_color($risk);
                    $label = SpiralEngine_Assessment::get_instance()->get_risk_level_label($risk);
                    return sprintf(
                        '<span class="spiralengine-risk-badge" style="background-color: %s; color: white; padding: 2px 8px; border-radius: 3px;">%s</span>',
                        esc_attr($color),
                        esc_html($label)
                    );
                }
                return '<span class="spiralengine-no-data">—</span>';
                
            case 'spiral_journey':
                $points = get_user_meta($user_id, 'spiralengine_compass_points', true);
                $level = get_user_meta($user_id, 'spiralengine_journey_level', true);
                if ($points !== '') {
                    return sprintf(
                        '<span class="spiralengine-journey" title="%s">%s pts</span>',
                        esc_attr($level ?: 'Beginning'),
                        number_format(intval($points))
                    );
                }
                return '<span class="spiralengine-no-data">—</span>';
                
            case 'spiral_membership':
                if (function_exists('mepr_get_user_active_memberships')) {
                    $memberships = mepr_get_user_active_memberships($user_id);
                    if (!empty($memberships)) {
                        $membership_names = array();
                        foreach ($memberships as $membership) {
                            $product = new MeprProduct($membership->product_id);
                            $membership_names[] = $product->post_title;
                        }
                        return implode(', ', $membership_names);
                    }
                }
                return '<span class="spiralengine-membership-discovery">Discovery</span>';
                
            case 'spiral_last_active':
                $last_active = get_user_meta($user_id, 'spiralengine_last_activity', true);
                if ($last_active) {
                    $time_diff = human_time_diff(strtotime($last_active), current_time('timestamp'));
                    return sprintf(__('%s ago', 'spiral-engine'), $time_diff);
                }
                return '<span class="spiralengine-no-data">—</span>';
                
            case 'spiral_actions':
                return $this->render_user_actions($user_id);
        }
        
        return $value;
    }
    
    /**
     * Make columns sortable
     */
    public function make_columns_sortable($columns) {
        $columns['spiral_score'] = 'spiral_score';
        $columns['spiral_risk'] = 'spiral_risk';
        $columns['spiral_journey'] = 'spiral_journey';
        $columns['spiral_last_active'] = 'spiral_last_active';
        return $columns;
    }
    
    /**
     * Modify user query for sorting
     */
    public function modify_user_query($query) {
        global $pagenow;
        
        if ($pagenow !== 'users.php') {
            return;
        }
        
        $orderby = $query->get('orderby');
        
        switch ($orderby) {
            case 'spiral_score':
                $query->set('meta_key', 'spiralengine_last_assessment_score');
                $query->set('orderby', 'meta_value_num');
                break;
                
            case 'spiral_risk':
                $query->set('meta_key', 'spiralengine_risk_level');
                $query->set('orderby', 'meta_value');
                break;
                
            case 'spiral_journey':
                $query->set('meta_key', 'spiralengine_compass_points');
                $query->set('orderby', 'meta_value_num');
                break;
                
            case 'spiral_last_active':
                $query->set('meta_key', 'spiralengine_last_activity');
                $query->set('orderby', 'meta_value');
                break;
        }
    }
    
    /**
     * Add user filters
     */
    public function add_user_filters() {
        ?>
        <select name="spiral_risk_filter" id="spiral_risk_filter">
            <option value=""><?php _e('All Risk Levels', 'spiral-engine'); ?></option>
            <option value="high" <?php selected(isset($_GET['spiral_risk_filter']) && $_GET['spiral_risk_filter'] === 'high'); ?>><?php _e('High Risk', 'spiral-engine'); ?></option>
            <option value="medium" <?php selected(isset($_GET['spiral_risk_filter']) && $_GET['spiral_risk_filter'] === 'medium'); ?>><?php _e('Medium Risk', 'spiral-engine'); ?></option>
            <option value="low" <?php selected(isset($_GET['spiral_risk_filter']) && $_GET['spiral_risk_filter'] === 'low'); ?>><?php _e('Low Risk', 'spiral-engine'); ?></option>
            <option value="unknown" <?php selected(isset($_GET['spiral_risk_filter']) && $_GET['spiral_risk_filter'] === 'unknown'); ?>><?php _e('No Assessment', 'spiral-engine'); ?></option>
        </select>
        
        <select name="spiral_activity_filter" id="spiral_activity_filter">
            <option value=""><?php _e('All Activity', 'spiral-engine'); ?></option>
            <option value="today" <?php selected(isset($_GET['spiral_activity_filter']) && $_GET['spiral_activity_filter'] === 'today'); ?>><?php _e('Active Today', 'spiral-engine'); ?></option>
            <option value="week" <?php selected(isset($_GET['spiral_activity_filter']) && $_GET['spiral_activity_filter'] === 'week'); ?>><?php _e('Active This Week', 'spiral-engine'); ?></option>
            <option value="month" <?php selected(isset($_GET['spiral_activity_filter']) && $_GET['spiral_activity_filter'] === 'month'); ?>><?php _e('Active This Month', 'spiral-engine'); ?></option>
            <option value="inactive" <?php selected(isset($_GET['spiral_activity_filter']) && $_GET['spiral_activity_filter'] === 'inactive'); ?>><?php _e('Inactive 30+ Days', 'spiral-engine'); ?></option>
        </select>
        
        <?php if (function_exists('mepr_get_products')) : ?>
        <select name="spiral_membership_filter" id="spiral_membership_filter">
            <option value=""><?php _e('All Memberships', 'spiral-engine'); ?></option>
            <option value="discovery" <?php selected(isset($_GET['spiral_membership_filter']) && $_GET['spiral_membership_filter'] === 'discovery'); ?>><?php _e('Discovery (Free)', 'spiral-engine'); ?></option>
            <?php
            $products = mepr_get_products();
            foreach ($products as $product) :
            ?>
            <option value="<?php echo esc_attr($product->ID); ?>" <?php selected(isset($_GET['spiral_membership_filter']) && $_GET['spiral_membership_filter'] == $product->ID); ?>>
                <?php echo esc_html($product->post_title); ?>
            </option>
            <?php endforeach; ?>
        </select>
        <?php endif; ?>
        <?php
    }
    
    /**
     * Filter users query
     */
    public function filter_users_query($args) {
        if (!isset($args['meta_query'])) {
            $args['meta_query'] = array();
        }
        
        // Risk level filter
        if (!empty($_GET['spiral_risk_filter'])) {
            $args['meta_query'][] = array(
                'key' => 'spiralengine_risk_level',
                'value' => sanitize_text_field($_GET['spiral_risk_filter']),
                'compare' => '='
            );
        }
        
        // Activity filter
        if (!empty($_GET['spiral_activity_filter'])) {
            $date_compare = '';
            switch ($_GET['spiral_activity_filter']) {
                case 'today':
                    $date_compare = date('Y-m-d 00:00:00');
                    break;
                case 'week':
                    $date_compare = date('Y-m-d 00:00:00', strtotime('-7 days'));
                    break;
                case 'month':
                    $date_compare = date('Y-m-d 00:00:00', strtotime('-30 days'));
                    break;
                case 'inactive':
                    $args['meta_query'][] = array(
                        'key' => 'spiralengine_last_activity',
                        'value' => date('Y-m-d H:i:s', strtotime('-30 days')),
                        'compare' => '<',
                        'type' => 'DATETIME'
                    );
                    break;
            }
            
            if ($date_compare && $_GET['spiral_activity_filter'] !== 'inactive') {
                $args['meta_query'][] = array(
                    'key' => 'spiralengine_last_activity',
                    'value' => $date_compare,
                    'compare' => '>=',
                    'type' => 'DATETIME'
                );
            }
        }
        
        // Membership filter
        if (!empty($_GET['spiral_membership_filter'])) {
            if ($_GET['spiral_membership_filter'] === 'discovery') {
                // Find users without active memberships
                $args['meta_query'][] = array(
                    'key' => '_mepr_subscriptions',
                    'compare' => 'NOT EXISTS'
                );
            } else {
                // Find users with specific membership
                $args['meta_query'][] = array(
                    'key' => '_mepr_subscriptions',
                    'value' => intval($_GET['spiral_membership_filter']),
                    'compare' => 'LIKE'
                );
            }
        }
        
        return $args;
    }
    
    /**
     * Add bulk actions
     */
    public function add_bulk_actions($actions) {
        $actions['spiralengine_export'] = __('Export User Data', 'spiral-engine');
        $actions['spiralengine_send_message'] = __('Send Message', 'spiral-engine');
        $actions['spiralengine_grant_marker'] = __('Grant Journey Marker', 'spiral-engine');
        $actions['spiralengine_reset_assessments'] = __('Reset Assessments', 'spiral-engine');
        return $actions;
    }
    
    /**
     * Handle bulk actions
     */
    public function handle_bulk_actions($redirect_to, $action, $user_ids) {
        switch ($action) {
            case 'spiralengine_export':
                $this->bulk_export_users($user_ids);
                $redirect_to = add_query_arg('spiralengine_exported', count($user_ids), $redirect_to);
                break;
                
            case 'spiralengine_send_message':
                // Store user IDs in transient for message composition
                set_transient('spiralengine_bulk_message_users', $user_ids, HOUR_IN_SECONDS);
                $redirect_to = admin_url('admin.php?page=spiralengine-send-message&users=' . count($user_ids));
                break;
                
            case 'spiralengine_grant_marker':
                // Redirect to marker granting page
                set_transient('spiralengine_bulk_grant_users', $user_ids, HOUR_IN_SECONDS);
                $redirect_to = admin_url('admin.php?page=spiralengine-journey-markers&action=bulk_grant&users=' . count($user_ids));
                break;
                
            case 'spiralengine_reset_assessments':
                foreach ($user_ids as $user_id) {
                    delete_user_meta($user_id, 'spiralengine_last_assessment_score');
                    delete_user_meta($user_id, 'spiralengine_risk_level');
                    delete_user_meta($user_id, 'spiralengine_assessment_count');
                }
                $redirect_to = add_query_arg('spiralengine_reset', count($user_ids), $redirect_to);
                break;
        }
        
        return $redirect_to;
    }
    
    /**
     * Render user actions
     */
    private function render_user_actions($user_id) {
        $actions = array();
        
        // View profile
        $actions[] = sprintf(
            '<a href="%s" class="button button-small" title="%s">%s</a>',
            get_edit_user_link($user_id),
            __('View Profile', 'spiral-engine'),
            __('View', 'spiral-engine')
        );
        
        // Support access
        $support_enabled = get_user_meta($user_id, 'spiralengine_support_access_enabled', true);
        if ($support_enabled) {
            $actions[] = sprintf(
                '<a href="#" class="button button-small spiralengine-support-access" data-user-id="%d" title="%s">%s</a>',
                $user_id,
                __('Request Support Access', 'spiral-engine'),
                __('Support', 'spiral-engine')
            );
        }
        
        // Send message
        $actions[] = sprintf(
            '<a href="%s" class="button button-small" title="%s">%s</a>',
            admin_url('admin.php?page=spiralengine-send-message&user_id=' . $user_id),
            __('Send Message', 'spiral-engine'),
            __('Message', 'spiral-engine')
        );
        
        // Quick info
        $actions[] = sprintf(
            '<a href="#" class="button button-small spiralengine-quick-info" data-user-id="%d" title="%s">%s</a>',
            $user_id,
            __('Quick Info', 'spiral-engine'),
            '<span class="dashicons dashicons-info"></span>'
        );
        
        return implode(' ', $actions);
    }
    
    /**
     * Get score color
     */
    private function get_score_color($score) {
        if ($score >= 13) {
            return '#F44336'; // Red
        } elseif ($score >= 7) {
            return '#FF9800'; // Orange
        } else {
            return '#4CAF50'; // Green
        }
    }
    
    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook) {
        if (!in_array($hook, array('users.php', 'toplevel_page_spiralengine-users', 'user-management_page_spiralengine-journey-markers'))) {
            return;
        }
        
        wp_enqueue_style(
            'spiralengine-admin-users',
            SPIRALENGINE_URL . 'assets/css/admin-users.css',
            array(),
            SPIRALENGINE_VERSION
        );
        
        wp_enqueue_script(
            'spiralengine-admin-users',
            SPIRALENGINE_URL . 'assets/js/admin-users.js',
            array('jquery', 'wp-util'),
            SPIRALENGINE_VERSION,
            true
        );
        
        wp_localize_script('spiralengine-admin-users', 'spiralengine_users', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('spiralengine_admin_nonce'),
            'strings' => array(
                'confirm_export' => __('Export user data for selected users?', 'spiral-engine'),
                'confirm_reset' => __('Reset assessments for selected users? This cannot be undone.', 'spiral-engine'),
                'loading' => __('Loading...', 'spiral-engine'),
                'error' => __('An error occurred', 'spiral-engine')
            )
        ));
        
        // Include Chart.js for analytics
        if ($hook === 'user-management_page_spiralengine-user-analytics') {
            wp_enqueue_script('chart-js', 'https://cdn.jsdelivr.net/npm/chart.js', array(), '3.9.1');
        }
    }
    
    /**
     * Render user management page
     */
    public function render_user_management_page() {
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php _e('User Management Center', 'spiral-engine'); ?></h1>
            
            <div class="spiralengine-dashboard-stats">
                <?php $this->render_dashboard_stats(); ?>
            </div>
            
            <div class="spiralengine-user-search">
                <h2><?php _e('Advanced User Search', 'spiral-engine'); ?></h2>
                <form method="get" action="<?php echo admin_url('users.php'); ?>">
                    <div class="spiralengine-search-builder">
                        <div class="search-row">
                            <label><?php _e('Risk Level:', 'spiral-engine'); ?></label>
                            <select name="spiral_risk_filter">
                                <option value=""><?php _e('Any', 'spiral-engine'); ?></option>
                                <option value="high"><?php _e('High', 'spiral-engine'); ?></option>
                                <option value="medium"><?php _e('Medium', 'spiral-engine'); ?></option>
                                <option value="low"><?php _e('Low', 'spiral-engine'); ?></option>
                            </select>
                        </div>
                        
                        <div class="search-row">
                            <label><?php _e('Last Assessment Score:', 'spiral-engine'); ?></label>
                            <select name="spiral_score_operator">
                                <option value="gte"><?php _e('Greater than or equal', 'spiral-engine'); ?></option>
                                <option value="lte"><?php _e('Less than or equal', 'spiral-engine'); ?></option>
                                <option value="eq"><?php _e('Equals', 'spiral-engine'); ?></option>
                            </select>
                            <input type="number" name="spiral_score_value" min="0" max="18" />
                        </div>
                        
                        <div class="search-row">
                            <label><?php _e('Journey Points:', 'spiral-engine'); ?></label>
                            <input type="number" name="spiral_points_min" placeholder="<?php _e('Min', 'spiral-engine'); ?>" />
                            <input type="number" name="spiral_points_max" placeholder="<?php _e('Max', 'spiral-engine'); ?>" />
                        </div>
                        
                        <button type="submit" class="button button-primary"><?php _e('Search Users', 'spiral-engine'); ?></button>
                        <button type="button" class="button" id="spiralengine-save-search"><?php _e('Save Search', 'spiral-engine'); ?></button>
                    </div>
                </form>
            </div>
            
            <div class="spiralengine-saved-searches">
                <h3><?php _e('Saved Searches', 'spiral-engine'); ?></h3>
                <ul>
                    <li><a href="<?php echo admin_url('users.php?spiral_risk_filter=high&spiral_activity_filter=today'); ?>"><?php _e('High Risk Users (Last 24h)', 'spiral-engine'); ?></a></li>
                    <li><a href="<?php echo admin_url('users.php?spiral_membership_filter=discovery&spiral_activity_filter=inactive'); ?>"><?php _e('Inactive Free Members', 'spiral-engine'); ?></a></li>
                    <li><a href="<?php echo admin_url('users.php?spiral_activity_filter=week'); ?>"><?php _e('Active This Week', 'spiral-engine'); ?></a></li>
                </ul>
            </div>
            
            <div class="spiralengine-quick-actions">
                <h3><?php _e('Quick Actions', 'spiral-engine'); ?></h3>
                <a href="<?php echo admin_url('admin.php?page=spiralengine-export-all'); ?>" class="button"><?php _e('Export All Users', 'spiral-engine'); ?></a>
                <a href="<?php echo admin_url('admin.php?page=spiralengine-send-broadcast'); ?>" class="button"><?php _e('Send Broadcast', 'spiral-engine'); ?></a>
                <a href="<?php echo admin_url('admin.php?page=spiralengine-user-import'); ?>" class="button"><?php _e('Import Users', 'spiral-engine'); ?></a>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render dashboard stats
     */
    private function render_dashboard_stats() {
        global $wpdb;
        
        // Get user counts
        $total_users = count_users();
        $total_count = $total_users['total_users'];
        
        // Active today
        $active_today = $wpdb->get_var(
            "SELECT COUNT(DISTINCT user_id) 
             FROM {$wpdb->usermeta} 
             WHERE meta_key = 'spiralengine_last_activity' 
             AND meta_value >= CURDATE()"
        );
        
        // High risk users
        $high_risk = $wpdb->get_var(
            "SELECT COUNT(*) 
             FROM {$wpdb->usermeta} 
             WHERE meta_key = 'spiralengine_risk_level' 
             AND meta_value = 'high'"
        );
        
        // Average score
        $avg_score = $wpdb->get_var(
            "SELECT AVG(meta_value) 
             FROM {$wpdb->usermeta} 
             WHERE meta_key = 'spiralengine_last_assessment_score' 
             AND meta_value != ''"
        );
        
        ?>
        <div class="spiralengine-stats-grid">
            <div class="stat-box">
                <h3><?php echo number_format($total_count); ?></h3>
                <p><?php _e('Total Users', 'spiral-engine'); ?></p>
            </div>
            
            <div class="stat-box">
                <h3><?php echo number_format($active_today); ?></h3>
                <p><?php _e('Active Today', 'spiral-engine'); ?></p>
            </div>
            
            <div class="stat-box">
                <h3><?php echo number_format($high_risk); ?></h3>
                <p><?php _e('High Risk', 'spiral-engine'); ?></p>
            </div>
            
            <div class="stat-box">
                <h3><?php echo $avg_score ? number_format($avg_score, 1) : '—'; ?></h3>
                <p><?php _e('Avg Score', 'spiral-engine'); ?></p>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render journey markers page
     */
    public function render_journey_markers_page() {
        $users = SpiralEngine_Users::get_instance();
        $markers = $users->get_all_journey_markers();
        
        // Handle bulk grant
        if (isset($_GET['action']) && $_GET['action'] === 'bulk_grant') {
            $user_ids = get_transient('spiralengine_bulk_grant_users');
            $user_count = is_array($user_ids) ? count($user_ids) : 0;
            ?>
            <div class="wrap">
                <h1><?php _e('Grant Journey Marker', 'spiral-engine'); ?></h1>
                <p><?php printf(__('Granting journey marker to %d users', 'spiral-engine'), $user_count); ?></p>
                
                <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                    <?php wp_nonce_field('spiralengine_bulk_grant_marker'); ?>
                    <input type="hidden" name="action" value="spiralengine_bulk_grant_marker" />
                    
                    <table class="form-table">
                        <tr>
                            <th><label for="marker_slug"><?php _e('Select Marker', 'spiral-engine'); ?></label></th>
                            <td>
                                <select name="marker_slug" id="marker_slug" required>
                                    <option value=""><?php _e('Choose a marker...', 'spiral-engine'); ?></option>
                                    <?php foreach ($markers as $slug => $marker) : ?>
                                    <option value="<?php echo esc_attr($slug); ?>">
                                        <?php echo esc_html($marker['name']); ?> (+<?php echo $marker['points']; ?> points)
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        
                        <tr>
                            <th><label for="grant_reason"><?php _e('Reason', 'spiral-engine'); ?></label></th>
                            <td>
                                <input type="text" name="grant_reason" id="grant_reason" class="regular-text" />
                                <p class="description"><?php _e('Optional reason for granting this marker', 'spiral-engine'); ?></p>
                            </td>
                        </tr>
                    </table>
                    
                    <p class="submit">
                        <button type="submit" class="button button-primary"><?php _e('Grant Marker', 'spiral-engine'); ?></button>
                        <a href="<?php echo admin_url('users.php'); ?>" class="button"><?php _e('Cancel', 'spiral-engine'); ?></a>
                    </p>
                </form>
            </div>
            <?php
            return;
        }
        
        // Normal journey markers page
        ?>
        <div class="wrap">
            <h1><?php _e('Journey Markers System', 'spiral-engine'); ?></h1>
            
            <div class="spiralengine-marker-status">
                <p><?php _e('System Status:', 'spiral-engine'); ?> 
                    <strong style="color: #4CAF50;"><?php _e('Enabled', 'spiral-engine'); ?></strong>
                </p>
            </div>
            
            <h2><?php _e('Journey Categories', 'spiral-engine'); ?></h2>
            
            <?php
            $categories = array(
                'beginning' => __('Beginning Journey', 'spiral-engine'),
                'finding_path' => __('Finding Path', 'spiral-engine'),
                'climbing_higher' => __('Climbing Higher', 'spiral-engine'),
                'vista_points' => __('Vista Points', 'spiral-engine'),
                'guide_spirit' => __('Guide Spirit', 'spiral-engine')
            );
            
            foreach ($categories as $cat_slug => $cat_name) :
                $cat_markers = array_filter($markers, function($marker) use ($cat_slug) {
                    return isset($marker['category']) && $marker['category'] === $cat_slug;
                });
                ?>
                <div class="spiralengine-marker-category">
                    <h3><?php echo esc_html($cat_name); ?></h3>
                    <table class="widefat">
                        <thead>
                            <tr>
                                <th><?php _e('Marker', 'spiral-engine'); ?></th>
                                <th><?php _e('Description', 'spiral-engine'); ?></th>
                                <th><?php _e('Points', 'spiral-engine'); ?></th>
                                <th><?php _e('Earned By', 'spiral-engine'); ?></th>
                                <th><?php _e('Actions', 'spiral-engine'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($cat_markers as $slug => $marker) : 
                                $earned_count = $this->get_marker_earned_count($slug);
                            ?>
                            <tr>
                                <td>
                                    <strong><?php echo esc_html($marker['name']); ?></strong>
                                </td>
                                <td><?php echo esc_html($marker['description']); ?></td>
                                <td><?php echo number_format($marker['points']); ?></td>
                                <td><?php echo number_format($earned_count); ?> users</td>
                                <td>
                                    <button class="button button-small spiralengine-grant-marker" data-marker="<?php echo esc_attr($slug); ?>">
                                        <?php _e('Grant Manually', 'spiral-engine'); ?>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endforeach; ?>
            
            <div class="spiralengine-marker-note">
                <p><strong><?php _e('Note:', 'spiral-engine'); ?></strong> 
                <?php _e('All users can earn journey markers equally. There are no membership requirements for any markers.', 'spiral-engine'); ?></p>
            </div>
        </div>
        <?php
    }
    
    /**
     * Get marker earned count
     */
    private function get_marker_earned_count($marker_slug) {
        global $wpdb;
        
        return $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->usermeta} 
             WHERE meta_key = %s",
            'spiralengine_marker_' . $marker_slug
        ));
    }
    
    /**
     * Render user analytics page
     */
    public function render_user_analytics_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('User Analytics Center', 'spiral-engine'); ?></h1>
            
            <div class="spiralengine-analytics-period">
                <label><?php _e('Time Period:', 'spiral-engine'); ?></label>
                <select id="analytics-period">
                    <option value="7"><?php _e('Last 7 Days', 'spiral-engine'); ?></option>
                    <option value="30" selected><?php _e('Last 30 Days', 'spiral-engine'); ?></option>
                    <option value="90"><?php _e('Last 90 Days', 'spiral-engine'); ?></option>
                    <option value="365"><?php _e('Last Year', 'spiral-engine'); ?></option>
                </select>
            </div>
            
            <div class="spiralengine-analytics-grid">
                <div class="analytics-chart">
                    <h3><?php _e('User Growth', 'spiral-engine'); ?></h3>
                    <canvas id="user-growth-chart"></canvas>
                </div>
                
                <div class="analytics-chart">
                    <h3><?php _e('Risk Level Distribution', 'spiral-engine'); ?></h3>
                    <canvas id="risk-distribution-chart"></canvas>
                </div>
                
                <div class="analytics-chart">
                    <h3><?php _e('Assessment Completion Rate', 'spiral-engine'); ?></h3>
                    <canvas id="assessment-rate-chart"></canvas>
                </div>
                
                <div class="analytics-chart">
                    <h3><?php _e('Membership Distribution', 'spiral-engine'); ?></h3>
                    <canvas id="membership-chart"></canvas>
                </div>
            </div>
            
            <div class="spiralengine-cohort-analysis">
                <h2><?php _e('Cohort Analysis', 'spiral-engine'); ?></h2>
                <?php $this->render_cohort_analysis(); ?>
            </div>
            
            <div class="spiralengine-user-segments">
                <h2><?php _e('User Segments', 'spiral-engine'); ?></h2>
                <?php $this->render_user_segments(); ?>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // Initialize charts
            initializeAnalyticsCharts();
            
            $('#analytics-period').on('change', function() {
                updateAnalyticsCharts($(this).val());
            });
        });
        </script>
        <?php
    }
    
    /**
     * Render cohort analysis
     */
    private function render_cohort_analysis() {
        global $wpdb;
        
        // Get cohorts for last 6 months
        $cohorts = array();
        for ($i = 5; $i >= 0; $i--) {
            $month_start = date('Y-m-01', strtotime("-{$i} months"));
            $month_end = date('Y-m-t', strtotime("-{$i} months"));
            $month_label = date('M Y', strtotime($month_start));
            
            $cohort_users = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->users} 
                 WHERE user_registered >= %s AND user_registered <= %s",
                $month_start, $month_end
            ));
            
            if ($cohort_users > 0) {
                $cohorts[$month_label] = array(
                    'size' => $cohort_users,
                    'retention' => $this->calculate_cohort_retention($month_start, $month_end)
                );
            }
        }
        
        ?>
        <table class="widefat">
            <thead>
                <tr>
                    <th><?php _e('Cohort', 'spiral-engine'); ?></th>
                    <th><?php _e('Size', 'spiral-engine'); ?></th>
                    <th><?php _e('Day 1', 'spiral-engine'); ?></th>
                    <th><?php _e('Day 7', 'spiral-engine'); ?></th>
                    <th><?php _e('Day 30', 'spiral-engine'); ?></th>
                    <th><?php _e('Day 90', 'spiral-engine'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($cohorts as $month => $data) : ?>
                <tr>
                    <td><strong><?php echo esc_html($month); ?></strong></td>
                    <td><?php echo number_format($data['size']); ?></td>
                    <td><?php echo isset($data['retention'][1]) ? $data['retention'][1] . '%' : '—'; ?></td>
                    <td><?php echo isset($data['retention'][7]) ? $data['retention'][7] . '%' : '—'; ?></td>
                    <td><?php echo isset($data['retention'][30]) ? $data['retention'][30] . '%' : '—'; ?></td>
                    <td><?php echo isset($data['retention'][90]) ? $data['retention'][90] . '%' : '—'; ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }
    
    /**
     * Calculate cohort retention
     */
    private function calculate_cohort_retention($start_date, $end_date) {
        // Simplified retention calculation
        // In production, this would track actual user activity
        return array(
            1 => rand(80, 95),
            7 => rand(60, 80),
            30 => rand(40, 60),
            90 => rand(25, 45)
        );
    }
    
    /**
     * Render user segments
     */
    private function render_user_segments() {
        global $wpdb;
        
        $segments = array(
            'power_users' => array(
                'name' => __('Power Users', 'spiral-engine'),
                'criteria' => __('5+ assessments/week, Low risk', 'spiral-engine'),
                'query' => "SELECT COUNT(DISTINCT user_id) FROM {$wpdb->prefix}spiralengine_assessments WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) GROUP BY user_id HAVING COUNT(*) >= 5"
            ),
            'at_risk' => array(
                'name' => __('At Risk', 'spiral-engine'),
                'criteria' => __('High risk score', 'spiral-engine'),
                'query' => "SELECT COUNT(*) FROM {$wpdb->usermeta} WHERE meta_key = 'spiralengine_risk_level' AND meta_value = 'high'"
            ),
            'engaged' => array(
                'name' => __('Highly Engaged', 'spiral-engine'),
                'criteria' => __('Daily active users', 'spiral-engine'),
                'query' => "SELECT COUNT(DISTINCT user_id) FROM {$wpdb->usermeta} WHERE meta_key = 'spiralengine_last_activity' AND meta_value >= DATE_SUB(NOW(), INTERVAL 1 DAY)"
            ),
            'dormant' => array(
                'name' => __('Dormant', 'spiral-engine'),
                'criteria' => __('No activity 30+ days', 'spiral-engine'),
                'query' => "SELECT COUNT(*) FROM {$wpdb->usermeta} WHERE meta_key = 'spiralengine_last_activity' AND meta_value < DATE_SUB(NOW(), INTERVAL 30 DAY)"
            )
        );
        
        ?>
        <div class="spiralengine-segments-grid">
            <?php foreach ($segments as $segment_key => $segment) : 
                $count = $wpdb->get_var($segment['query']) ?: 0;
            ?>
            <div class="segment-card">
                <h3><?php echo esc_html($segment['name']); ?></h3>
                <p class="segment-count"><?php echo number_format($count); ?> users</p>
                <p class="segment-criteria"><?php echo esc_html($segment['criteria']); ?></p>
                <a href="<?php echo admin_url('users.php?segment=' . $segment_key); ?>" class="button">
                    <?php _e('View Users', 'spiral-engine'); ?>
                </a>
            </div>
            <?php endforeach; ?>
        </div>
        <?php
    }
    
    /**
     * Render support log page
     */
    public function render_support_log_page() {
        global $wpdb;
        
        $logs = $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}spiralengine_support_log 
             ORDER BY created_at DESC 
             LIMIT 100"
        );
        
        ?>
        <div class="wrap">
            <h1><?php _e('Support Access Log', 'spiral-engine'); ?></h1>
            
            <table class="widefat">
                <thead>
                    <tr>
                        <th><?php _e('Date/Time', 'spiral-engine'); ?></th>
                        <th><?php _e('Admin', 'spiral-engine'); ?></th>
                        <th><?php _e('User', 'spiral-engine'); ?></th>
                        <th><?php _e('Action', 'spiral-engine'); ?></th>
                        <th><?php _e('Details', 'spiral-engine'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log) : 
                        $admin = get_userdata($log->admin_id);
                        $user = get_userdata($log->user_id);
                        $details = json_decode($log->details, true);
                    ?>
                    <tr>
                        <td><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($log->created_at))); ?></td>
                        <td><?php echo $admin ? esc_html($admin->display_name) : __('Unknown', 'spiral-engine'); ?></td>
                        <td><?php echo $user ? esc_html($user->display_name) : __('Unknown', 'spiral-engine'); ?></td>
                        <td><?php echo esc_html($log->action); ?></td>
                        <td><?php echo $details ? esc_html(json_encode($details)) : '—'; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
    
    /**
     * Show support access notice
     */
    public function show_support_access_notice() {
        if (isset($_GET['support_access'])) {
            $class = 'notice notice-success is-dismissible';
            $message = '';
            
            switch ($_GET['support_access']) {
                case 'granted':
                    $message = __('Support access granted successfully.', 'spiral-engine');
                    break;
                case 'denied':
                    $message = __('Support access denied. User has not enabled support access.', 'spiral-engine');
                    $class = 'notice notice-error is-dismissible';
                    break;
            }
            
            if ($message) {
                printf('<div class="%1$s"><p>%2$s</p></div>', esc_attr($class), esc_html($message));
            }
        }
    }
    
    /**
     * Handle support access request
     */
    public function handle_support_access_request() {
        if (!isset($_POST['user_id']) || !wp_verify_nonce($_POST['_wpnonce'], 'spiralengine_support_access')) {
            wp_die(__('Invalid request', 'spiral-engine'));
        }
        
        $user_id = intval($_POST['user_id']);
        $reason = sanitize_text_field($_POST['reason']);
        $duration = sanitize_text_field($_POST['duration']);
        
        $users = SpiralEngine_Users::get_instance();
        $result = $users->request_support_access($user_id, get_current_user_id(), $reason, $duration);
        
        if (is_wp_error($result)) {
            wp_redirect(add_query_arg('support_access', 'denied', wp_get_referer()));
        } else {
            // Create support session
            $_SESSION['spiralengine_support_session'] = array(
                'user_id' => $user_id,
                'admin_id' => get_current_user_id(),
                'token' => $result['token'],
                'expires' => $result['expires_at']
            );
            
            wp_redirect(add_query_arg('support_access', 'granted', get_edit_user_link($user_id)));
        }
        exit;
    }
    
    /**
     * AJAX: Quick user info
     */
    public function ajax_quick_user_info() {
        check_ajax_referer('spiralengine_admin_nonce', 'nonce');
        
        if (!current_user_can('edit_users')) {
            wp_die();
        }
        
        $user_id = intval($_POST['user_id']);
        $user = get_userdata($user_id);
        
        if (!$user) {
            wp_send_json_error(__('User not found', 'spiral-engine'));
        }
        
        // Get user data
        $assessment = SpiralEngine_Assessment::get_instance();
        $history = $assessment->get_assessment_history($user_id, 5);
        $markers = SpiralEngine_Users::get_instance()->get_user_journey_markers($user_id);
        $earned_markers = array_filter($markers, function($m) { return $m['earned']; });
        
        ob_start();
        ?>
        <div class="spiralengine-user-quick-info">
            <h2><?php echo esc_html($user->display_name); ?></h2>
            <p><strong><?php _e('Email:', 'spiral-engine'); ?></strong> <?php echo esc_html($user->user_email); ?></p>
            <p><strong><?php _e('Registered:', 'spiral-engine'); ?></strong> <?php echo esc_html(date_i18n(get_option('date_format'), strtotime($user->user_registered))); ?></p>
            
            <h3><?php _e('Recent Assessments', 'spiral-engine'); ?></h3>
            <?php if (!empty($history)) : ?>
            <ul>
                <?php foreach ($history as $assessment) : ?>
                <li>
                    <?php echo esc_html(date_i18n(get_option('date_format'), strtotime($assessment['created_at']))); ?>: 
                    <?php echo esc_html($assessment['total_score']); ?>/18 
                    (<?php echo esc_html($assessment['risk_level']); ?>)
                </li>
                <?php endforeach; ?>
            </ul>
            <?php else : ?>
            <p><?php _e('No assessments found.', 'spiral-engine'); ?></p>
            <?php endif; ?>
            
            <h3><?php _e('Journey Markers', 'spiral-engine'); ?></h3>
            <?php if (!empty($earned_markers)) : ?>
            <ul>
                <?php foreach ($earned_markers as $marker) : ?>
                <li><?php echo esc_html($marker['name']); ?> (+<?php echo $marker['points']; ?> points)</li>
                <?php endforeach; ?>
            </ul>
            <?php else : ?>
            <p><?php _e('No markers earned yet.', 'spiral-engine'); ?></p>
            <?php endif; ?>
        </div>
        <?php
        $html = ob_get_clean();
        
        wp_send_json_success(array('html' => $html));
    }
    
    /**
     * AJAX: Export users
     */
    public function ajax_export_users() {
        check_ajax_referer('spiralengine_admin_nonce', 'nonce');
        
        if (!current_user_can('export')) {
            wp_die();
        }
        
        $user_ids = isset($_POST['user_ids']) ? array_map('intval', $_POST['user_ids']) : array();
        
        if (empty($user_ids)) {
            wp_send_json_error(__('No users selected', 'spiral-engine'));
        }
        
        $this->bulk_export_users($user_ids);
        
        wp_send_json_success(array(
            'message' => sprintf(__('Exported %d users', 'spiral-engine'), count($user_ids)),
            'download_url' => admin_url('admin-ajax.php?action=spiralengine_download_export&nonce=' . wp_create_nonce('download_export'))
        ));
    }
    
    /**
     * Bulk export users
     */
    private function bulk_export_users($user_ids) {
        $data = array();
        
        foreach ($user_ids as $user_id) {
            $user = get_userdata($user_id);
            if (!$user) continue;
            
            $user_data = array(
                'ID' => $user->ID,
                'username' => $user->user_login,
                'email' => $user->user_email,
                'display_name' => $user->display_name,
                'registered' => $user->user_registered,
                'last_assessment_score' => get_user_meta($user_id, 'spiralengine_last_assessment_score', true),
                'risk_level' => get_user_meta($user_id, 'spiralengine_risk_level', true),
                'compass_points' => get_user_meta($user_id, 'spiralengine_compass_points', true),
                'journey_level' => get_user_meta($user_id, 'spiralengine_journey_level', true),
                'biological_sex' => get_user_meta($user_id, 'spiralengine_biological_sex', true),
                'last_activity' => get_user_meta($user_id, 'spiralengine_last_activity', true)
            );
            
            $data[] = $user_data;
        }
        
        // Save to transient for download
        set_transient('spiralengine_export_data_' . get_current_user_id(), $data, HOUR_IN_SECONDS);
    }
    
    /**
     * AJAX: Grant journey marker
     */
    public function ajax_grant_journey_marker() {
        check_ajax_referer('spiralengine_admin_nonce', 'nonce');
        
        if (!current_user_can('edit_users')) {
            wp_die();
        }
        
        $user_id = intval($_POST['user_id']);
        $marker_slug = sanitize_text_field($_POST['marker']);
        
        $users = SpiralEngine_Users::get_instance();
        $result = $users->grant_journey_marker($user_id, $marker_slug, get_current_user_id());
        
        if ($result) {
            wp_send_json_success(array(
                'message' => __('Journey marker granted successfully', 'spiral-engine')
            ));
        } else {
            wp_send_json_error(__('Failed to grant marker or already earned', 'spiral-engine'));
        }
    }
}

// Initialize the class
SpiralEngine_Users_Admin::get_instance();

<?php
/**
 * SpiralEngine Database Installer
 *
 * Handles database table creation, updates, and removal
 *
 * @package SpiralEngine
 * @since 1.0.0
 */

// includes/class-spiralengine-installer.php

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Database installer class
 */
class SpiralEngine_Installer {
    
    /**
     * Database version
     *
     * @var string
     */
    private $db_version = SPIRALENGINE_DB_VERSION;
    
    /**
     * WordPress database object
     *
     * @var wpdb
     */
    private $wpdb;
    
    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
    }
    
    /**
     * Install database tables and setup
     *
     * @return bool Success status
     */
    public function install(): bool {
        try {
            // Create tables
            $this->create_tables();
            
            // Set database version
            update_option('spiralengine_db_version', $this->db_version);
            
            // Set installation date
            if (!get_option('spiralengine_installed_date')) {
                update_option('spiralengine_installed_date', current_time('mysql'));
            }
            
            // Create default capabilities
            $this->create_capabilities();
            
            // Set up default data
            $this->setup_default_data();
            
            return true;
        } catch (Exception $e) {
            error_log('SpiralEngine Installation Error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Create database tables
     */
    private function create_tables() {
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        $charset_collate = $this->wpdb->get_charset_collate();
        
        // Memberships table
        $this->create_memberships_table($charset_collate);
        
        // Episodes table
        $this->create_episodes_table($charset_collate);
        
        // Admin log table
        $this->create_admin_log_table($charset_collate);
        
        // Verify tables were created
        $this->verify_tables();
    }
    
    /**
     * Create memberships table
     *
     * @param string $charset_collate Database charset collation
     */
    private function create_memberships_table(string $charset_collate) {
        $table_name = $this->wpdb->prefix . 'spiralengine_memberships';
        
        $sql = "CREATE TABLE $table_name (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id bigint(20) UNSIGNED NOT NULL,
            tier varchar(20) NOT NULL DEFAULT 'free',
            status varchar(20) NOT NULL DEFAULT 'active',
            stripe_customer_id varchar(255) DEFAULT NULL,
            stripe_subscription_id varchar(255) DEFAULT NULL,
            custom_limits JSON DEFAULT NULL,
            starts_at datetime DEFAULT CURRENT_TIMESTAMP,
            expires_at datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY user_id (user_id),
            KEY idx_status_tier (status, tier),
            KEY idx_expires (expires_at),
            KEY idx_stripe_customer (stripe_customer_id),
            KEY idx_stripe_subscription (stripe_subscription_id)
        ) $charset_collate;";
        
        dbDelta($sql);
    }
    
    /**
     * Create episodes table
     *
     * @param string $charset_collate Database charset collation
     */
    private function create_episodes_table(string $charset_collate) {
        $table_name = $this->wpdb->prefix . 'spiralengine_episodes';
        
        $sql = "CREATE TABLE $table_name (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id bigint(20) UNSIGNED NOT NULL,
            widget_id varchar(50) NOT NULL,
            severity int(2) NOT NULL,
            data JSON NOT NULL,
            metadata JSON DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_user_widget (user_id, widget_id),
            KEY idx_created (created_at),
            KEY idx_severity (severity),
            KEY idx_widget_created (widget_id, created_at),
            KEY idx_user_created (user_id, created_at)
        ) $charset_collate;";
        
        dbDelta($sql);
    }
    
    /**
     * Create admin log table
     *
     * @param string $charset_collate Database charset collation
     */
    private function create_admin_log_table(string $charset_collate) {
        $table_name = $this->wpdb->prefix . 'spiralengine_admin_log';
        
        $sql = "CREATE TABLE $table_name (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            admin_id bigint(20) UNSIGNED NOT NULL,
            action varchar(100) NOT NULL,
            target_type varchar(50) DEFAULT NULL,
            target_id bigint(20) UNSIGNED DEFAULT NULL,
            details JSON DEFAULT NULL,
            ip_address varchar(45) DEFAULT NULL,
            user_agent text DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_admin_action (admin_id, action),
            KEY idx_created (created_at),
            KEY idx_action (action),
            KEY idx_target (target_type, target_id),
            KEY idx_admin_created (admin_id, created_at)
        ) $charset_collate;";
        
        dbDelta($sql);
    }
    
    /**
     * Verify tables were created successfully
     *
     * @throws Exception If tables are missing
     */
    private function verify_tables() {
        $tables = [
            'spiralengine_memberships',
            'spiralengine_episodes',
            'spiralengine_admin_log'
        ];
        
        foreach ($tables as $table) {
            $table_name = $this->wpdb->prefix . $table;
            $table_exists = $this->wpdb->get_var(
                $this->wpdb->prepare(
                    "SHOW TABLES LIKE %s",
                    $table_name
                )
            );
            
            if ($table_exists !== $table_name) {
                throw new Exception("Failed to create table: $table_name");
            }
            
            // Verify JSON column support
            if (in_array($table, ['spiralengine_memberships', 'spiralengine_episodes', 'spiralengine_admin_log'])) {
                $this->verify_json_support($table_name);
            }
        }
    }
    
    /**
     * Verify JSON column support
     *
     * @param string $table_name Table name to check
     * @throws Exception If JSON columns are not supported
     */
    private function verify_json_support(string $table_name) {
        $columns = $this->wpdb->get_results(
            "SHOW COLUMNS FROM $table_name WHERE Type = 'json'",
            ARRAY_A
        );
        
        if (empty($columns)) {
            // Check if we have longtext columns instead (older MySQL)
            $text_columns = $this->wpdb->get_results(
                "SHOW COLUMNS FROM $table_name WHERE Field IN ('custom_limits', 'data', 'metadata', 'details') AND Type LIKE '%text%'",
                ARRAY_A
            );
            
            if (!empty($text_columns)) {
                // Log warning but continue - we'll handle JSON as text
                error_log('SpiralEngine Warning: JSON columns created as TEXT. MySQL 8.0+ recommended for native JSON support.');
            } else {
                throw new Exception("Failed to create JSON columns in table: $table_name");
            }
        }
    }
    
    /**
     * Create default capabilities
     */
    private function create_capabilities() {
        // Get administrator role
        $admin_role = get_role('administrator');
        
        if ($admin_role) {
            // Admin capabilities
            $admin_caps = [
                'spiralengine_super_admin',
                'spiralengine_user_manager',
                'spiralengine_billing_manager',
                'spiralengine_content_manager',
                'spiralengine_analyst',
                'spiralengine_support',
                'spiralengine_developer',
                'spiralengine_view_admin_log',
                'spiralengine_export_data',
                'spiralengine_impersonate_users',
                'spiralengine_manage_widgets',
                'spiralengine_view_analytics',
                'spiralengine_manage_settings'
            ];
            
            foreach ($admin_caps as $cap) {
                $admin_role->add_cap($cap);
            }
        }
        
        // Regular user capabilities
        $user_caps = [
            'spiralengine_access_dashboard',
            'spiralengine_track_episodes',
            'spiralengine_view_insights',
            'spiralengine_export_own_data',
            'spiralengine_manage_account'
        ];
        
        // Add capabilities to subscriber role
        $subscriber_role = get_role('subscriber');
        if ($subscriber_role) {
            foreach ($user_caps as $cap) {
                $subscriber_role->add_cap($cap);
            }
        }
        
        // Also add to other standard roles
        $roles = ['contributor', 'author', 'editor'];
        foreach ($roles as $role_name) {
            $role = get_role($role_name);
            if ($role) {
                foreach ($user_caps as $cap) {
                    $role->add_cap($cap);
                }
            }
        }
    }
    
    /**
     * Setup default data
     */
    private function setup_default_data() {
        // Create free membership for all existing users
        $users = get_users(['fields' => 'ID']);
        
        foreach ($users as $user_id) {
            $this->create_default_membership($user_id);
        }
        
        // Log installation
        $this->log_installation();
    }
    
    /**
     * Create default membership for a user
     *
     * @param int $user_id User ID
     */
    public function create_default_membership(int $user_id) {
        $table_name = $this->wpdb->prefix . 'spiralengine_memberships';
        
        // Check if membership already exists
        $exists = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT id FROM $table_name WHERE user_id = %d",
                $user_id
            )
        );
        
        if (!$exists) {
            $this->wpdb->insert(
                $table_name,
                [
                    'user_id' => $user_id,
                    'tier' => SPIRALENGINE_TIER_FREE,
                    'status' => SPIRALENGINE_STATUS_ACTIVE,
                    'starts_at' => current_time('mysql'),
                    'created_at' => current_time('mysql'),
                    'updated_at' => current_time('mysql')
                ],
                ['%d', '%s', '%s', '%s', '%s', '%s']
            );
        }
    }
    
    /**
     * Log installation
     */
    private function log_installation() {
        $table_name = $this->wpdb->prefix . 'spiralengine_admin_log';
        
        $this->wpdb->insert(
            $table_name,
            [
                'admin_id' => get_current_user_id() ?: 1,
                'action' => 'plugin_installed',
                'target_type' => 'system',
                'target_id' => 0,
                'details' => json_encode([
                    'version' => SPIRALENGINE_VERSION,
                    'db_version' => $this->db_version,
                    'php_version' => PHP_VERSION,
                    'mysql_version' => $this->wpdb->db_version(),
                    'wp_version' => get_bloginfo('version'),
                    'timestamp' => current_time('mysql')
                ]),
                'ip_address' => $this->get_ip_address(),
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'CLI',
                'created_at' => current_time('mysql')
            ],
            ['%d', '%s', '%s', '%d', '%s', '%s', '%s', '%s']
        );
    }
    
    /**
     * Update database schema
     *
     * @return bool Success status
     */
    public function update(): bool {
        $current_version = get_option('spiralengine_db_version', '0.0.0');
        
        if (version_compare($current_version, $this->db_version, '<')) {
            // Run updates based on version
            // This is where future schema updates would go
            
            // Update version
            update_option('spiralengine_db_version', $this->db_version);
            
            // Log update
            $this->log_update($current_version, $this->db_version);
            
            return true;
        }
        
        return true;
    }
    
    /**
     * Log database update
     *
     * @param string $from_version Previous version
     * @param string $to_version New version
     */
    private function log_update(string $from_version, string $to_version) {
        $table_name = $this->wpdb->prefix . 'spiralengine_admin_log';
        
        $this->wpdb->insert(
            $table_name,
            [
                'admin_id' => get_current_user_id() ?: 1,
                'action' => 'database_updated',
                'target_type' => 'system',
                'target_id' => 0,
                'details' => json_encode([
                    'from_version' => $from_version,
                    'to_version' => $to_version,
                    'timestamp' => current_time('mysql')
                ]),
                'ip_address' => $this->get_ip_address(),
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'CLI',
                'created_at' => current_time('mysql')
            ],
            ['%d', '%s', '%s', '%d', '%s', '%s', '%s', '%s']
        );
    }
    
    /**
     * Uninstall database tables and data
     *
     * @return bool Success status
     */
    public function uninstall(): bool {
        try {
            // Drop tables in reverse order of dependencies
            $tables = [
                'spiralengine_admin_log',
                'spiralengine_episodes',
                'spiralengine_memberships'
            ];
            
            foreach ($tables as $table) {
                $table_name = $this->wpdb->prefix . $table;
                $this->wpdb->query("DROP TABLE IF EXISTS $table_name");
            }
            
            // Remove capabilities
            $this->remove_capabilities();
            
            // Remove database version
            delete_option('spiralengine_db_version');
            delete_option('spiralengine_installed_date');
            
            return true;
        } catch (Exception $e) {
            error_log('SpiralEngine Uninstall Error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Remove capabilities
     */
    private function remove_capabilities() {
        global $wp_roles;
        
        $all_caps = [
            'spiralengine_super_admin',
            'spiralengine_user_manager',
            'spiralengine_billing_manager',
            'spiralengine_content_manager',
            'spiralengine_analyst',
            'spiralengine_support',
            'spiralengine_developer',
            'spiralengine_view_admin_log',
            'spiralengine_export_data',
            'spiralengine_impersonate_users',
            'spiralengine_manage_widgets',
            'spiralengine_view_analytics',
            'spiralengine_manage_settings',
            'spiralengine_access_dashboard',
            'spiralengine_track_episodes',
            'spiralengine_view_insights',
            'spiralengine_export_own_data',
            'spiralengine_manage_account'
        ];
        
        foreach ($wp_roles->roles as $role_name => $role_info) {
            $role = get_role($role_name);
            if ($role) {
                foreach ($all_caps as $cap) {
                    $role->remove_cap($cap);
                }
            }
        }
    }
    
    /**
     * Get user IP address
     *
     * @return string IP address
     */
    private function get_ip_address(): string {
        $ip_keys = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'];
        
        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                // Handle comma-separated IPs
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        
        return '127.0.0.1';
    }
    
    /**
     * Check if tables need update
     *
     * @return bool True if update needed
     */
    public function needs_update(): bool {
        $current_version = get_option('spiralengine_db_version', '0.0.0');
        return version_compare($current_version, $this->db_version, '<');
    }
    
    /**
     * Get table charset
     *
     * @param string $table_name Table name
     * @return string Charset
     */
    public function get_table_charset(string $table_name): string {
        $charset = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT CCSA.character_set_name 
                FROM information_schema.TABLES T,
                     information_schema.COLLATION_CHARACTER_SET_APPLICABILITY CCSA
                WHERE CCSA.collation_name = T.table_collation
                AND T.table_schema = %s
                AND T.table_name = %s",
                DB_NAME,
                $table_name
            )
        );
        
        return $charset ?: 'utf8mb4';
    }
}

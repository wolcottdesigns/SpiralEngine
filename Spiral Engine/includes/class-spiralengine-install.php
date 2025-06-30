<?php
/**
 * Spiral Engine Install Class
 * 
 * Handles plugin activation, deactivation, and database setup
 * 
 * @package    SpiralEngine
 * @subpackage Install
 * @since      1.0.0
 */

// includes/class-spiralengine-install.php

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Installation and activation class
 */
class SpiralEngine_Install {
    
    /**
     * Plugin activation
     */
    public static function activate() {
        // Create database tables
        self::create_tables();
        
        // Create WordPress pages
        self::create_pages();
        
        // Set default options
        self::set_default_options();
        
        // Create default data
        self::create_default_data();
        
        // Store database version
        update_option('spiralengine_db_version', '1.0.0');
        
        // Set flag to flush rewrite rules
        update_option('spiralengine_flush_rewrite_rules', true);
        
        // Clear any cached data
        wp_cache_flush();
    }
    
    /**
     * Plugin deactivation
     */
    public static function deactivate() {
        // Remove scheduled cron events
        wp_clear_scheduled_hook('spiralengine_hourly_tasks');
        wp_clear_scheduled_hook('spiralengine_daily_tasks');
        
        // Clear transients
        self::clear_transients();
        
        // Flush cache
        wp_cache_flush();
    }
    
    /**
     * Create all database tables
     */
    private static function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        // System Tools Center Tables
        
        // Assessments table
        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}spiralengine_assessments (
            assessment_id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id bigint(20) UNSIGNED NOT NULL,
            assessment_type varchar(50) NOT NULL,
            responses longtext NOT NULL,
            results longtext,
            status varchar(20) DEFAULT 'completed',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (assessment_id),
            KEY user_id (user_id),
            KEY assessment_type (assessment_type),
            KEY created_at (created_at)
        ) $charset_collate;";
        dbDelta($sql);
        
        // Episodes table
        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}spiralengine_episodes (
            episode_id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id bigint(20) UNSIGNED NOT NULL,
            episode_type varchar(50) NOT NULL,
            severity tinyint(2) DEFAULT 5,
            status varchar(20) DEFAULT 'active',
            episode_data longtext,
            triggers longtext,
            context longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            resolved_at datetime NULL,
            PRIMARY KEY (episode_id),
            KEY user_id (user_id),
            KEY episode_type (episode_type),
            KEY status (status),
            KEY created_at (created_at)
        ) $charset_collate;";
        dbDelta($sql);
        
        // Correlations table
        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}spiralengine_correlations (
            correlation_id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id bigint(20) UNSIGNED NOT NULL,
            source_type varchar(50) NOT NULL,
            source_id bigint(20) UNSIGNED NOT NULL,
            target_type varchar(50) NOT NULL,
            target_id bigint(20) UNSIGNED NOT NULL,
            correlation_strength decimal(5,2) DEFAULT 0.00,
            correlation_data longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (correlation_id),
            KEY user_id (user_id),
            KEY source_lookup (source_type, source_id),
            KEY target_lookup (target_type, target_id)
        ) $charset_collate;";
        dbDelta($sql);
        
        // Widgets table
        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}spiralengine_widgets (
            widget_id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            widget_key varchar(100) NOT NULL,
            widget_name varchar(255) NOT NULL,
            widget_type varchar(50) NOT NULL,
            category varchar(100),
            description text,
            configuration longtext,
            permissions longtext,
            preview_settings longtext,
            status varchar(20) DEFAULT 'active',
            display_order int(11) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (widget_id),
            UNIQUE KEY widget_key (widget_key),
            KEY widget_type (widget_type),
            KEY category (category),
            KEY status (status)
        ) $charset_collate;";
        dbDelta($sql);
        
        // Widget access table
        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}spiralengine_widget_access (
            access_id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            widget_id bigint(20) UNSIGNED NOT NULL,
            membership_level varchar(50),
            user_role varchar(50),
            access_type varchar(20) DEFAULT 'view',
            conditions longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (access_id),
            KEY widget_id (widget_id),
            KEY membership_level (membership_level),
            KEY user_role (user_role)
        ) $charset_collate;";
        dbDelta($sql);
        
        // User settings table
        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}spiralengine_user_settings (
            setting_id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id bigint(20) UNSIGNED NOT NULL,
            setting_key varchar(100) NOT NULL,
            setting_value longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (setting_id),
            UNIQUE KEY user_setting (user_id, setting_key),
            KEY user_id (user_id),
            KEY setting_key (setting_key)
        ) $charset_collate;";
        dbDelta($sql);
        
        // Insights table
        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}spiralengine_insights (
            insight_id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id bigint(20) UNSIGNED NOT NULL,
            insight_type varchar(50) NOT NULL,
            insight_data longtext NOT NULL,
            confidence_score decimal(5,2) DEFAULT 0.00,
            is_actionable tinyint(1) DEFAULT 0,
            status varchar(20) DEFAULT 'active',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            expires_at datetime NULL,
            PRIMARY KEY (insight_id),
            KEY user_id (user_id),
            KEY insight_type (insight_type),
            KEY status (status),
            KEY created_at (created_at)
        ) $charset_collate;";
        dbDelta($sql);
        
        // System health table
        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}spiralengine_system_health (
            health_id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            check_type varchar(50) NOT NULL,
            check_key varchar(100) NOT NULL,
            status varchar(20) NOT NULL,
            details longtext,
            last_checked datetime DEFAULT CURRENT_TIMESTAMP,
            next_check datetime NULL,
            PRIMARY KEY (health_id),
            UNIQUE KEY check_key (check_type, check_key),
            KEY status (status),
            KEY next_check (next_check)
        ) $charset_collate;";
        dbDelta($sql);
        
        // Master Control Center Tables
        
        // Master control settings
        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}spiralengine_master_control (
            control_id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            control_key varchar(100) NOT NULL,
            control_value longtext,
            control_type varchar(50) NOT NULL,
            is_locked tinyint(1) DEFAULT 0,
            last_modified datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            modified_by bigint(20) UNSIGNED,
            PRIMARY KEY (control_id),
            UNIQUE KEY control_key (control_key),
            KEY control_type (control_type)
        ) $charset_collate;";
        dbDelta($sql);
        
        // System modes
        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}spiralengine_system_modes (
            mode_id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            mode_name varchar(50) NOT NULL,
            mode_config longtext NOT NULL,
            is_active tinyint(1) DEFAULT 0,
            activated_at datetime NULL,
            activated_by bigint(20) UNSIGNED NULL,
            scheduled_until datetime NULL,
            PRIMARY KEY (mode_id),
            UNIQUE KEY mode_name (mode_name),
            KEY is_active (is_active)
        ) $charset_collate;";
        dbDelta($sql);
        
        // Resource limits
        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}spiralengine_resource_limits (
            limit_id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            resource_type varchar(50) NOT NULL,
            resource_key varchar(100) NOT NULL,
            limit_value int NOT NULL,
            current_usage int DEFAULT 0,
            soft_limit int,
            hard_limit int NOT NULL,
            enforcement_mode enum('soft','hard','none') DEFAULT 'soft',
            PRIMARY KEY (limit_id),
            UNIQUE KEY resource_key (resource_type, resource_key),
            KEY enforcement_mode (enforcement_mode)
        ) $charset_collate;";
        dbDelta($sql);
        
        // Emergency protocols
        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}spiralengine_emergency_protocols (
            protocol_id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            protocol_name varchar(100) NOT NULL,
            protocol_type varchar(50) NOT NULL,
            trigger_conditions longtext,
            action_sequence longtext NOT NULL,
            auto_execute tinyint(1) DEFAULT 0,
            last_executed datetime NULL,
            execution_count int DEFAULT 0,
            PRIMARY KEY (protocol_id),
            UNIQUE KEY protocol_name (protocol_name),
            KEY protocol_type (protocol_type)
        ) $charset_collate;";
        dbDelta($sql);
        
        // Cross-center commands
        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}spiralengine_center_commands (
            command_id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            command_type varchar(50) NOT NULL,
            target_centers text NOT NULL,
            command_data longtext NOT NULL,
            status enum('pending','executing','completed','failed') DEFAULT 'pending',
            scheduled_at datetime NULL,
            executed_at datetime NULL,
            executed_by bigint(20) UNSIGNED,
            result longtext,
            PRIMARY KEY (command_id),
            KEY status (status),
            KEY scheduled_at (scheduled_at)
        ) $charset_collate;";
        dbDelta($sql);
        
        // System health metrics
        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}spiralengine_health_metrics (
            metric_id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            metric_type varchar(50) NOT NULL,
            metric_key varchar(100) NOT NULL,
            metric_value decimal(10,2) NOT NULL,
            threshold_warning decimal(10,2),
            threshold_critical decimal(10,2),
            status enum('healthy','warning','critical') DEFAULT 'healthy',
            recorded_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (metric_id),
            KEY metric_lookup (metric_type, metric_key, recorded_at),
            KEY status (status)
        ) $charset_collate;";
        dbDelta($sql);
        
        // Security Command Center Tables
        
        // Privacy consent tracking
        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}spiralengine_privacy_consent (
            consent_id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id bigint(20) UNSIGNED NOT NULL,
            consent_type varchar(50) NOT NULL,
            consent_given tinyint(1) NOT NULL DEFAULT 0,
            consent_text text NOT NULL,
            consent_version varchar(20) NOT NULL,
            ip_address varchar(45) NOT NULL,
            timestamp datetime DEFAULT CURRENT_TIMESTAMP,
            withdrawn_at datetime NULL,
            PRIMARY KEY (consent_id),
            KEY user_id (user_id),
            KEY consent_type (consent_type),
            KEY timestamp (timestamp)
        ) $charset_collate;";
        dbDelta($sql);
        
        // Privacy requests
        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}spiralengine_privacy_requests (
            request_id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id bigint(20) UNSIGNED NOT NULL,
            request_type varchar(50) NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'pending',
            requested_at datetime DEFAULT CURRENT_TIMESTAMP,
            completed_at datetime NULL,
            data_file varchar(255) NULL,
            notes text,
            PRIMARY KEY (request_id),
            KEY user_id (user_id),
            KEY status (status),
            KEY requested_at (requested_at)
        ) $charset_collate;";
        dbDelta($sql);
        
        // Privacy audit log
        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}spiralengine_privacy_audit (
            audit_id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id bigint(20) UNSIGNED NULL,
            action varchar(100) NOT NULL,
            details text,
            ip_address varchar(45) NOT NULL,
            user_agent varchar(255) NOT NULL,
            timestamp datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (audit_id),
            KEY user_id (user_id),
            KEY action (action),
            KEY timestamp (timestamp)
        ) $charset_collate;";
        dbDelta($sql);
    }
    
    /**
     * Create WordPress pages
     */
    private static function create_pages() {
        $pages = array(
            'spiral-engine-dashboard' => array(
                'title' => __('Spiral Engine Dashboard', 'spiral-engine'),
                'content' => '[spiralengine_dashboard]',
                'option' => 'spiralengine_dashboard_page_id'
            ),
            'spiral-engine-privacy' => array(
                'title' => __('Privacy Center', 'spiral-engine'),
                'content' => '[spiralengine_privacy_portal]',
                'option' => 'spiralengine_privacy_page_id'
            ),
            'spiral-engine-assessments' => array(
                'title' => __('Assessments', 'spiral-engine'),
                'content' => '[spiralengine_assessments]',
                'option' => 'spiralengine_assessments_page_id'
            ),
            'spiral-engine-insights' => array(
                'title' => __('My Insights', 'spiral-engine'),
                'content' => '[spiralengine_insights]',
                'option' => 'spiralengine_insights_page_id'
            )
        );
        
        foreach ($pages as $slug => $page) {
            $page_id = wp_insert_post(array(
                'post_title' => $page['title'],
                'post_content' => $page['content'],
                'post_status' => 'publish',
                'post_type' => 'page',
                'post_name' => $slug,
                'comment_status' => 'closed',
                'ping_status' => 'closed'
            ));
            
            if ($page_id && !is_wp_error($page_id)) {
                update_option($page['option'], $page_id);
            }
        }
    }
    
    /**
     * Set default plugin options
     */
    private static function set_default_options() {
        // System configuration
        add_option('spiralengine_system_mode', 'production');
        add_option('spiralengine_default_timezone', 'America/New_York');
        add_option('spiralengine_time_format', 'g:i A'); // 12-hour format
        add_option('spiralengine_date_format', 'F j, Y');
        
        // Privacy settings (balanced by default)
        add_option('spiralengine_privacy_mode', 'balanced');
        add_option('spiralengine_consent_management', true);
        add_option('spiralengine_encryption_enabled', true);
        add_option('spiralengine_encryption_level', 'balanced');
        add_option('spiralengine_audit_logging', 'standard');
        add_option('spiralengine_cookie_consent', true);
        add_option('spiralengine_consent_version', '1.0');
        
        // Email configuration
        add_option('spiralengine_email_method', 'wp_mail');
        add_option('spiralengine_email_from_name', get_bloginfo('name'));
        add_option('spiralengine_email_from_address', get_option('admin_email'));
        
        // Resource limits
        add_option('spiralengine_memory_limit', 256);
        add_option('spiralengine_execution_time', 300);
        add_option('spiralengine_api_rate_limit', 100);
        
        // Features
        add_option('spiralengine_features', array(
            'assessments' => true,
            'episodes' => true,
            'insights' => true,
            'ai_integration' => true,
            'crisis_detection' => true,
            'real_time_updates' => true,
            'widget_system' => true
        ));
        
        // Development settings
        add_option('spiralengine_development_mode', false);
        add_option('spiralengine_debug_mode', false);
        add_option('spiralengine_log_level', 'error');
    }
    
    /**
     * Create default data
     */
    private static function create_default_data() {
        global $wpdb;
        
        // Create default system modes
        $modes = array(
            array(
                'mode_name' => 'production',
                'mode_config' => json_encode(array(
                    'error_display' => false,
                    'caching' => true,
                    'debugging' => false,
                    'api_limits' => 'enforced'
                )),
                'is_active' => 1
            ),
            array(
                'mode_name' => 'development',
                'mode_config' => json_encode(array(
                    'error_display' => true,
                    'caching' => false,
                    'debugging' => true,
                    'api_limits' => 'relaxed'
                )),
                'is_active' => 0
            ),
            array(
                'mode_name' => 'maintenance',
                'mode_config' => json_encode(array(
                    'user_access' => 'blocked',
                    'admin_access' => 'allowed',
                    'background_jobs' => true,
                    'maintenance_page' => true
                )),
                'is_active' => 0
            ),
            array(
                'mode_name' => 'emergency',
                'mode_config' => json_encode(array(
                    'priority_support' => true,
                    'monitoring' => 'real-time',
                    'features' => 'essential',
                    'alerts' => 'maximum'
                )),
                'is_active' => 0
            )
        );
        
        foreach ($modes as $mode) {
            $wpdb->insert(
                $wpdb->prefix . 'spiralengine_system_modes',
                $mode
            );
        }
        
        // Create default emergency protocols
        $protocols = array(
            array(
                'protocol_name' => 'emergency_shutdown',
                'protocol_type' => 'system',
                'action_sequence' => json_encode(array(
                    'notify_admins',
                    'stop_background_jobs',
                    'disable_api_endpoints',
                    'database_read_only',
                    'enable_maintenance_page',
                    'create_checkpoint'
                )),
                'auto_execute' => 0
            ),
            array(
                'protocol_name' => 'crisis_mode',
                'protocol_type' => 'support',
                'action_sequence' => json_encode(array(
                    'alert_crisis_team',
                    'enable_priority_queue',
                    'increase_monitoring',
                    'activate_crisis_widgets',
                    'log_all_activities'
                )),
                'auto_execute' => 0
            ),
            array(
                'protocol_name' => 'security_lockdown',
                'protocol_type' => 'security',
                'action_sequence' => json_encode(array(
                    'block_new_registrations',
                    'force_password_reset',
                    'enable_2fa',
                    'audit_all_access',
                    'notify_security_team'
                )),
                'auto_execute' => 0
            )
        );
        
        foreach ($protocols as $protocol) {
            $wpdb->insert(
                $wpdb->prefix . 'spiralengine_emergency_protocols',
                $protocol
            );
        }
        
        // Create default resource limits
        $limits = array(
            array(
                'resource_type' => 'memory',
                'resource_key' => 'per_widget',
                'limit_value' => 128,
                'soft_limit' => 100,
                'hard_limit' => 500
            ),
            array(
                'resource_type' => 'api',
                'resource_key' => 'requests_per_minute',
                'limit_value' => 60,
                'soft_limit' => 50,
                'hard_limit' => 100
            ),
            array(
                'resource_type' => 'database',
                'resource_key' => 'queries_per_page',
                'limit_value' => 50,
                'soft_limit' => 40,
                'hard_limit' => 100
            )
        );
        
        foreach ($limits as $limit) {
            $wpdb->insert(
                $wpdb->prefix . 'spiralengine_resource_limits',
                $limit
            );
        }
    }
    
    /**
     * Clear all transients
     */
    private static function clear_transients() {
        global $wpdb;
        
        // Delete all transients with our prefix
        $wpdb->query(
            "DELETE FROM {$wpdb->options} 
            WHERE option_name LIKE '_transient_spiralengine_%' 
            OR option_name LIKE '_transient_timeout_spiralengine_%'"
        );
    }
}
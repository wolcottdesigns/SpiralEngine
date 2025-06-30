<?php
/**
 * Spiral Engine Uninstall
 * 
 * Handles cleanup when the plugin is uninstalled
 * 
 * @package    SpiralEngine
 * @since      1.0.0
 */

// uninstall.php

// If uninstall not called from WordPress, then exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

/**
 * Main uninstall function
 */
function spiralengine_uninstall() {
    global $wpdb;
    
    // Check if we should remove data (respecting user preference)
    $remove_data = get_option('spiralengine_remove_data_on_uninstall', false);
    
    if (!$remove_data) {
        // User wants to keep data, so just remove non-critical options
        spiralengine_remove_options();
        return;
    }
    
    // User wants complete removal
    spiralengine_remove_all_data();
}

/**
 * Remove all plugin data
 */
function spiralengine_remove_all_data() {
    // Remove database tables
    spiralengine_remove_tables();
    
    // Delete all plugin options
    spiralengine_remove_options();
    
    // Remove all user meta
    spiralengine_remove_user_meta();
    
    // Delete created pages
    spiralengine_remove_pages();
    
    // Clear all transients
    spiralengine_clear_transients();
    
    // Remove custom post types data
    spiralengine_remove_custom_posts();
    
    // Clean up any files
    spiralengine_cleanup_files();
    
    // Remove capabilities
    spiralengine_remove_capabilities();
    
    // Clear any cached data
    wp_cache_flush();
}

/**
 * Remove all database tables
 */
function spiralengine_remove_tables() {
    global $wpdb;
    
    // List of all tables to remove
    $tables = array(
        // System Tools Center tables
        'spiralengine_assessments',
        'spiralengine_episodes',
        'spiralengine_correlations',
        'spiralengine_widgets',
        'spiralengine_widget_access',
        'spiralengine_user_settings',
        'spiralengine_insights',
        'spiralengine_system_health',
        
        // Master Control Center tables
        'spiralengine_master_control',
        'spiralengine_system_modes',
        'spiralengine_resource_limits',
        'spiralengine_emergency_protocols',
        'spiralengine_center_commands',
        'spiralengine_health_metrics',
        
        // Security Command Center tables
        'spiralengine_privacy_consent',
        'spiralengine_privacy_requests',
        'spiralengine_privacy_audit'
    );
    
    // Drop each table
    foreach ($tables as $table) {
        $table_name = $wpdb->prefix . $table;
        $wpdb->query("DROP TABLE IF EXISTS {$table_name}");
    }
}

/**
 * Delete all plugin options from wp_options
 */
function spiralengine_remove_options() {
    global $wpdb;
    
    // Core options
    $options = array(
        'spiralengine_db_version',
        'spiralengine_flush_rewrite_rules',
        'spiralengine_system_mode',
        'spiralengine_default_timezone',
        'spiralengine_time_format',
        'spiralengine_date_format',
        'spiralengine_privacy_mode',
        'spiralengine_consent_management',
        'spiralengine_encryption_enabled',
        'spiralengine_encryption_level',
        'spiralengine_audit_logging',
        'spiralengine_cookie_consent',
        'spiralengine_consent_version',
        'spiralengine_email_method',
        'spiralengine_email_from_name',
        'spiralengine_email_from_address',
        'spiralengine_memory_limit',
        'spiralengine_execution_time',
        'spiralengine_api_rate_limit',
        'spiralengine_features',
        'spiralengine_development_mode',
        'spiralengine_debug_mode',
        'spiralengine_log_level',
        'spiralengine_remove_data_on_uninstall',
        
        // Page IDs
        'spiralengine_dashboard_page_id',
        'spiralengine_privacy_page_id',
        'spiralengine_assessments_page_id',
        'spiralengine_insights_page_id',
        
        // Widget options
        'widget_spiralengine_dashboard',
        'widget_spiralengine_insights',
        'widget_spiralengine_privacy'
    );
    
    // Delete each option
    foreach ($options as $option) {
        delete_option($option);
    }
    
    // Delete options with dynamic names using direct SQL
    $wpdb->query(
        "DELETE FROM {$wpdb->options} 
        WHERE option_name LIKE 'spiralengine_%'"
    );
}

/**
 * Remove all user meta created by the plugin
 */
function spiralengine_remove_user_meta() {
    global $wpdb;
    
    // List of user meta keys to remove
    $meta_keys = array(
        'spiralengine_timezone',
        'spiralengine_last_login',
        'spiralengine_consent_given',
        'spiralengine_privacy_preferences',
        'spiralengine_dashboard_widgets',
        'spiralengine_notification_preferences'
    );
    
    // Delete each meta key for all users
    foreach ($meta_keys as $meta_key) {
        $wpdb->delete(
            $wpdb->usermeta,
            array('meta_key' => $meta_key),
            array('%s')
        );
    }
    
    // Delete any dynamic user meta
    $wpdb->query(
        "DELETE FROM {$wpdb->usermeta} 
        WHERE meta_key LIKE 'spiralengine_%'"
    );
}

/**
 * Delete created pages
 */
function spiralengine_remove_pages() {
    // Page option names
    $page_options = array(
        'spiralengine_dashboard_page_id',
        'spiralengine_privacy_page_id',
        'spiralengine_assessments_page_id',
        'spiralengine_insights_page_id'
    );
    
    foreach ($page_options as $option) {
        $page_id = get_option($option);
        if ($page_id) {
            // Force delete the page (bypass trash)
            wp_delete_post($page_id, true);
        }
    }
}

/**
 * Clear all transients with spiralengine_ prefix
 */
function spiralengine_clear_transients() {
    global $wpdb;
    
    // Delete transients
    $wpdb->query(
        "DELETE FROM {$wpdb->options} 
        WHERE option_name LIKE '_transient_spiralengine_%' 
        OR option_name LIKE '_transient_timeout_spiralengine_%'"
    );
    
    // Delete site transients
    $wpdb->query(
        "DELETE FROM {$wpdb->options} 
        WHERE option_name LIKE '_site_transient_spiralengine_%' 
        OR option_name LIKE '_site_transient_timeout_spiralengine_%'"
    );
}

/**
 * Remove custom post types and their data
 */
function spiralengine_remove_custom_posts() {
    global $wpdb;
    
    // Get all widget posts
    $widget_posts = get_posts(array(
        'post_type' => 'spiralengine_widget',
        'numberposts' => -1,
        'post_status' => 'any'
    ));
    
    // Delete each post
    foreach ($widget_posts as $post) {
        wp_delete_post($post->ID, true);
    }
    
    // Clean up any orphaned postmeta
    $wpdb->query(
        "DELETE pm FROM {$wpdb->postmeta} pm
        LEFT JOIN {$wpdb->posts} p ON p.ID = pm.post_id
        WHERE p.ID IS NULL"
    );
    
    // Remove terms from custom taxonomies
    $terms = get_terms(array(
        'taxonomy' => 'spiralengine_widget_cat',
        'hide_empty' => false
    ));
    
    foreach ($terms as $term) {
        wp_delete_term($term->term_id, 'spiralengine_widget_cat');
    }
}

/**
 * Clean up any files created by the plugin
 */
function spiralengine_cleanup_files() {
    // Get upload directory
    $upload_dir = wp_upload_dir();
    $spiralengine_dir = $upload_dir['basedir'] . '/spiralengine';
    
    // Remove the directory if it exists
    if (is_dir($spiralengine_dir)) {
        spiralengine_remove_directory($spiralengine_dir);
    }
    
    // Clean up any backup files
    $backup_dir = $upload_dir['basedir'] . '/spiralengine-backups';
    if (is_dir($backup_dir)) {
        spiralengine_remove_directory($backup_dir);
    }
    
    // Clean up any log files
    $log_file = WP_CONTENT_DIR . '/spiralengine-debug.log';
    if (file_exists($log_file)) {
        @unlink($log_file);
    }
}

/**
 * Recursively remove a directory
 * 
 * @param string $dir Directory path
 */
function spiralengine_remove_directory($dir) {
    if (!is_dir($dir)) {
        return;
    }
    
    $files = array_diff(scandir($dir), array('.', '..'));
    
    foreach ($files as $file) {
        $path = $dir . '/' . $file;
        if (is_dir($path)) {
            spiralengine_remove_directory($path);
        } else {
            @unlink($path);
        }
    }
    
    @rmdir($dir);
}

/**
 * Remove custom capabilities
 */
function spiralengine_remove_capabilities() {
    // Get all roles
    $roles = wp_roles()->roles;
    
    // List of capabilities to remove
    $capabilities = array(
        'spiralengine_manage_settings',
        'spiralengine_view_analytics',
        'spiralengine_manage_users',
        'spiralengine_manage_widgets',
        'spiralengine_access_master_control',
        'spiralengine_manage_security',
        'spiralengine_export_data',
        'spiralengine_manage_assessments'
    );
    
    // Remove capabilities from each role
    foreach ($roles as $role_name => $role_info) {
        $role = get_role($role_name);
        if ($role) {
            foreach ($capabilities as $cap) {
                $role->remove_cap($cap);
            }
        }
    }
}

/**
 * Log uninstall process (if logging is still available)
 */
function spiralengine_log_uninstall() {
    global $wpdb;
    
    // Try to log the uninstall if the table still exists
    $table_exists = $wpdb->get_var(
        "SHOW TABLES LIKE '{$wpdb->prefix}spiralengine_privacy_audit'"
    );
    
    if ($table_exists) {
        $wpdb->insert(
            $wpdb->prefix . 'spiralengine_privacy_audit',
            array(
                'user_id' => get_current_user_id(),
                'action' => 'plugin_uninstalled',
                'details' => json_encode(array(
                    'timestamp' => current_time('mysql'),
                    'data_removed' => get_option('spiralengine_remove_data_on_uninstall', false)
                )),
                'ip_address' => isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0',
                'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? substr($_SERVER['HTTP_USER_AGENT'], 0, 255) : '',
                'timestamp' => current_time('mysql', true)
            ),
            array('%d', '%s', '%s', '%s', '%s', '%s')
        );
    }
}

/**
 * Send notification email about uninstall (optional)
 */
function spiralengine_notify_uninstall() {
    $admin_email = get_option('admin_email');
    $site_name = get_bloginfo('name');
    
    $subject = sprintf(__('[%s] Spiral Engine Plugin Uninstalled', 'spiral-engine'), $site_name);
    
    $message = sprintf(
        __("The Spiral Engine plugin has been uninstalled from %s.\n\n", 'spiral-engine'),
        $site_name
    );
    
    if (get_option('spiralengine_remove_data_on_uninstall', false)) {
        $message .= __("All plugin data has been removed.\n", 'spiral-engine');
    } else {
        $message .= __("Plugin data has been preserved for future use.\n", 'spiral-engine');
    }
    
    $message .= sprintf(
        __("\nUninstalled by: %s\nTime: %s\n", 'spiral-engine'),
        wp_get_current_user()->display_name,
        current_time('mysql')
    );
    
    // Send notification (optional - uncomment if desired)
    // wp_mail($admin_email, $subject, $message);
}

// Execute uninstall
try {
    // Log the uninstall attempt
    spiralengine_log_uninstall();
    
    // Perform the uninstall
    spiralengine_uninstall();
    
    // Send notification (optional)
    // spiralengine_notify_uninstall();
    
} catch (Exception $e) {
    // If something goes wrong, at least try to remove the main options
    error_log('Spiral Engine uninstall error: ' . $e->getMessage());
    spiralengine_remove_options();
}

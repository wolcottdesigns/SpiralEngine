<?php
// includes/tools/class-spiralengine-maintenance.php

/**
 * Spiral Engine Maintenance Tools
 * 
 * Provides maintenance utilities including database optimization, data cleanup,
 * performance tools, and repair functions.
 *
 * @package    Spiral_Engine
 * @subpackage Spiral_Engine/tools
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class SPIRALENGINE_Maintenance {
    
    /**
     * Time manager instance
     */
    private $time_manager;
    
    /**
     * Maintenance mode settings
     */
    private $maintenance_mode = array();
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->time_manager = new SPIRALENGINE_Time_Zone_Manager();
        $this->load_maintenance_settings();
        $this->init_hooks();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Maintenance mode
        add_action('init', array($this, 'check_maintenance_mode'));
        add_action('admin_init', array($this, 'check_maintenance_mode_admin'));
        
        // AJAX handlers
        add_action('wp_ajax_spiralengine_optimize_database', array($this, 'ajax_optimize_database'));
        add_action('wp_ajax_spiralengine_clean_data', array($this, 'ajax_clean_data'));
        add_action('wp_ajax_spiralengine_repair_tables', array($this, 'ajax_repair_tables'));
        add_action('wp_ajax_spiralengine_clear_cache', array($this, 'ajax_clear_cache'));
        add_action('wp_ajax_spiralengine_fix_permissions', array($this, 'ajax_fix_permissions'));
        add_action('wp_ajax_spiralengine_toggle_maintenance', array($this, 'ajax_toggle_maintenance'));
        add_action('wp_ajax_spiralengine_run_diagnostics', array($this, 'ajax_run_diagnostics'));
        
        // Scheduled maintenance
        add_action('spiralengine_scheduled_maintenance', array($this, 'run_scheduled_maintenance'));
    }
    
    /**
     * Load maintenance settings
     */
    private function load_maintenance_settings() {
        $this->maintenance_mode = array(
            'enabled' => get_option('spiralengine_maintenance_mode', false),
            'message' => get_option('spiralengine_maintenance_message', __('Site under maintenance. Back soon!', 'spiralengine')),
            'return_time' => get_option('spiralengine_maintenance_return_time', ''),
            'allowed_ips' => get_option('spiralengine_maintenance_allowed_ips', array()),
            'allowed_roles' => get_option('spiralengine_maintenance_allowed_roles', array('administrator'))
        );
    }
    
    /**
     * Check maintenance mode
     */
    public function check_maintenance_mode() {
        if (!$this->maintenance_mode['enabled']) {
            return;
        }
        
        // Skip for allowed IPs
        $user_ip = $_SERVER['REMOTE_ADDR'];
        if (in_array($user_ip, $this->maintenance_mode['allowed_ips'])) {
            return;
        }
        
        // Skip for logged in users with allowed roles
        if (is_user_logged_in()) {
            $user = wp_get_current_user();
            foreach ($this->maintenance_mode['allowed_roles'] as $role) {
                if (in_array($role, $user->roles)) {
                    return;
                }
            }
        }
        
        // Skip for admin pages
        if (is_admin()) {
            return;
        }
        
        // Show maintenance page
        $this->show_maintenance_page();
    }
    
    /**
     * Show maintenance page
     */
    private function show_maintenance_page() {
        // Set 503 status
        status_header(503);
        header('Retry-After: 3600');
        
        // Format return time if set
        $return_time_display = '';
        if (!empty($this->maintenance_mode['return_time'])) {
            $return_time_display = $this->time_manager->format_user_time($this->maintenance_mode['return_time']);
        }
        
        // Load maintenance template
        include SPIRALENGINE_PLUGIN_DIR . 'templates/maintenance-mode.php';
        exit;
    }
    
    /**
     * Database optimization
     */
    public function optimize_database($options = array()) {
        global $wpdb;
        
        $results = array(
            'tables_optimized' => 0,
            'space_saved' => 0,
            'errors' => array(),
            'details' => array()
        );
        
        try {
            // Get all database tables
            $tables = $wpdb->get_results("SHOW TABLE STATUS", ARRAY_A);
            
            foreach ($tables as $table) {
                $table_name = $table['Name'];
                
                // Skip non-SPIRAL Engine tables unless specified
                if (empty($options['all_tables']) && strpos($table_name, $wpdb->prefix . 'spiralengine_') !== 0) {
                    continue;
                }
                
                // Get table size before optimization
                $size_before = $table['Data_length'] + $table['Index_length'];
                
                // Optimize table
                $result = $wpdb->query("OPTIMIZE TABLE `$table_name`");
                
                if ($result !== false) {
                    // Get table size after optimization
                    $table_info_after = $wpdb->get_row("SHOW TABLE STATUS LIKE '$table_name'", ARRAY_A);
                    $size_after = $table_info_after['Data_length'] + $table_info_after['Index_length'];
                    
                    $space_saved = $size_before - $size_after;
                    
                    $results['tables_optimized']++;
                    $results['space_saved'] += $space_saved;
                    
                    $results['details'][$table_name] = array(
                        'size_before' => $size_before,
                        'size_after' => $size_after,
                        'space_saved' => $space_saved,
                        'fragmentation_before' => $table['Data_free'],
                        'fragmentation_after' => $table_info_after['Data_free']
                    );
                    
                    // Analyze table
                    $wpdb->query("ANALYZE TABLE `$table_name`");
                    
                } else {
                    $results['errors'][] = sprintf(__('Failed to optimize table: %s', 'spiralengine'), $table_name);
                }
            }
            
            // Update optimization timestamp
            update_option('spiralengine_last_optimization', current_time('mysql'));
            
            // Log optimization
            $this->log_maintenance_action('database_optimization', $results);
            
        } catch (Exception $e) {
            $results['errors'][] = $e->getMessage();
        }
        
        return $results;
    }
    
    /**
     * Clean data
     */
    public function clean_data($type = 'all', $options = array()) {
        $results = array(
            'records_cleaned' => 0,
            'space_freed' => 0,
            'errors' => array(),
            'details' => array()
        );
        
        try {
            switch ($type) {
                case 'orphans':
                    $results['details']['orphans'] = $this->clean_orphaned_data();
                    break;
                    
                case 'temp':
                    $results['details']['temp'] = $this->clean_temp_data();
                    break;
                    
                case 'logs':
                    $results['details']['logs'] = $this->clean_old_logs($options);
                    break;
                    
                case 'cache':
                    $results['details']['cache'] = $this->clean_expired_cache();
                    break;
                    
                case 'sessions':
                    $results['details']['sessions'] = $this->clean_expired_sessions();
                    break;
                    
                case 'all':
                    $results['details']['orphans'] = $this->clean_orphaned_data();
                    $results['details']['temp'] = $this->clean_temp_data();
                    $results['details']['logs'] = $this->clean_old_logs($options);
                    $results['details']['cache'] = $this->clean_expired_cache();
                    $results['details']['sessions'] = $this->clean_expired_sessions();
                    break;
                    
                default:
                    throw new Exception(__('Invalid cleanup type', 'spiralengine'));
            }
            
            // Calculate totals
            foreach ($results['details'] as $detail) {
                if (isset($detail['records_cleaned'])) {
                    $results['records_cleaned'] += $detail['records_cleaned'];
                }
                if (isset($detail['space_freed'])) {
                    $results['space_freed'] += $detail['space_freed'];
                }
            }
            
            // Log cleanup
            $this->log_maintenance_action('data_cleanup', $results);
            
        } catch (Exception $e) {
            $results['errors'][] = $e->getMessage();
        }
        
        return $results;
    }
    
    /**
     * Clean orphaned data
     */
    private function clean_orphaned_data() {
        global $wpdb;
        
        $cleaned = array(
            'records_cleaned' => 0,
            'types' => array()
        );
        
        // Clean orphaned user meta
        $orphaned_meta = $wpdb->query(
            "DELETE um FROM {$wpdb->usermeta} um
             LEFT JOIN {$wpdb->users} u ON um.user_id = u.ID
             WHERE u.ID IS NULL AND um.meta_key LIKE 'spiralengine_%'"
        );
        
        $cleaned['types']['user_meta'] = $orphaned_meta;
        $cleaned['records_cleaned'] += $orphaned_meta;
        
        // Clean orphaned assessments
        $orphaned_assessments = $wpdb->query(
            "DELETE a FROM {$wpdb->prefix}spiralengine_assessments a
             LEFT JOIN {$wpdb->users} u ON a.user_id = u.ID
             WHERE u.ID IS NULL"
        );
        
        $cleaned['types']['assessments'] = $orphaned_assessments;
        $cleaned['records_cleaned'] += $orphaned_assessments;
        
        // Clean orphaned episodes
        $episode_tables = array(
            'spiralengine_episodes_overthinking',
            'spiralengine_episodes_anxiety',
            'spiralengine_episodes_depression',
            'spiralengine_episodes_ptsd'
        );
        
        foreach ($episode_tables as $table) {
            $full_table = $wpdb->prefix . $table;
            if ($wpdb->get_var("SHOW TABLES LIKE '$full_table'")) {
                $orphaned = $wpdb->query(
                    "DELETE e FROM $full_table e
                     LEFT JOIN {$wpdb->users} u ON e.user_id = u.ID
                     WHERE u.ID IS NULL"
                );
                
                $cleaned['types'][$table] = $orphaned;
                $cleaned['records_cleaned'] += $orphaned;
            }
        }
        
        // Clean orphaned AI data
        if ($wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}spiralengine_ai_logs'")) {
            $orphaned_ai = $wpdb->query(
                "DELETE al FROM {$wpdb->prefix}spiralengine_ai_logs al
                 LEFT JOIN {$wpdb->users} u ON al.user_id = u.ID
                 WHERE u.ID IS NULL"
            );
            
            $cleaned['types']['ai_logs'] = $orphaned_ai;
            $cleaned['records_cleaned'] += $orphaned_ai;
        }
        
        return $cleaned;
    }
    
    /**
     * Clean temporary data
     */
    private function clean_temp_data() {
        global $wpdb;
        
        $cleaned = array(
            'records_cleaned' => 0,
            'space_freed' => 0
        );
        
        // Clean expired transients
        $expired_transients = $wpdb->query(
            "DELETE FROM {$wpdb->options} 
             WHERE option_name LIKE '_transient_timeout_spiralengine_%' 
             AND option_value < " . time()
        );
        
        $orphaned_transients = $wpdb->query(
            "DELETE FROM {$wpdb->options} 
             WHERE option_name LIKE '_transient_spiralengine_%' 
             AND option_name NOT IN (
                 SELECT CONCAT('_transient_', SUBSTRING(option_name, 19)) 
                 FROM (SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_spiralengine_%') AS t
             )"
        );
        
        $cleaned['records_cleaned'] = $expired_transients + $orphaned_transients;
        
        // Clean temporary files
        $temp_dir = wp_upload_dir()['basedir'] . '/spiralengine-temp/';
        if (is_dir($temp_dir)) {
            $files = glob($temp_dir . '*');
            foreach ($files as $file) {
                if (is_file($file) && filemtime($file) < strtotime('-24 hours')) {
                    $cleaned['space_freed'] += filesize($file);
                    unlink($file);
                }
            }
        }
        
        return $cleaned;
    }
    
    /**
     * Clean old logs
     */
    private function clean_old_logs($options = array()) {
        global $wpdb;
        
        $retention_days = isset($options['retention_days']) ? $options['retention_days'] : 30;
        $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$retention_days} days"));
        
        $cleaned = array(
            'records_cleaned' => 0,
            'types' => array()
        );
        
        // Clean cron logs
        if ($wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}spiralengine_cron_log'")) {
            $cron_logs = $wpdb->query($wpdb->prepare(
                "DELETE FROM {$wpdb->prefix}spiralengine_cron_log WHERE created_at < %s",
                $cutoff_date
            ));
            
            $cleaned['types']['cron_logs'] = $cron_logs;
            $cleaned['records_cleaned'] += $cron_logs;
        }
        
        // Clean audit logs
        if ($wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}spiralengine_audit_log'")) {
            $audit_logs = $wpdb->query($wpdb->prepare(
                "DELETE FROM {$wpdb->prefix}spiralengine_audit_log WHERE created_at < %s",
                $cutoff_date
            ));
            
            $cleaned['types']['audit_logs'] = $audit_logs;
            $cleaned['records_cleaned'] += $audit_logs;
        }
        
        // Clean error logs
        if ($wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}spiralengine_error_log'")) {
            $error_logs = $wpdb->query($wpdb->prepare(
                "DELETE FROM {$wpdb->prefix}spiralengine_error_log WHERE created_at < %s",
                $cutoff_date
            ));
            
            $cleaned['types']['error_logs'] = $error_logs;
            $cleaned['records_cleaned'] += $error_logs;
        }
        
        // Clean file logs
        $log_dir = SPIRALENGINE_PLUGIN_DIR . 'logs/';
        if (is_dir($log_dir)) {
            $files = glob($log_dir . '*.log*');
            foreach ($files as $file) {
                if (is_file($file) && filemtime($file) < strtotime("-{$retention_days} days")) {
                    unlink($file);
                }
            }
        }
        
        return $cleaned;
    }
    
    /**
     * Repair tables
     */
    public function repair_tables($tables = array()) {
        global $wpdb;
        
        $results = array(
            'tables_repaired' => 0,
            'errors' => array(),
            'details' => array()
        );
        
        try {
            // If no specific tables, get all SPIRAL Engine tables
            if (empty($tables)) {
                $tables = $wpdb->get_col(
                    "SELECT table_name 
                     FROM information_schema.tables 
                     WHERE table_schema = '" . DB_NAME . "' 
                     AND table_name LIKE '{$wpdb->prefix}spiralengine_%'"
                );
            }
            
            foreach ($tables as $table) {
                // Check table
                $check_result = $wpdb->get_results("CHECK TABLE `$table`", ARRAY_A);
                $needs_repair = false;
                
                foreach ($check_result as $row) {
                    if ($row['Msg_text'] !== 'OK' && $row['Msg_text'] !== 'Table is already up to date') {
                        $needs_repair = true;
                        break;
                    }
                }
                
                if ($needs_repair) {
                    // Repair table
                    $repair_result = $wpdb->get_results("REPAIR TABLE `$table`", ARRAY_A);
                    
                    $repair_status = 'unknown';
                    foreach ($repair_result as $row) {
                        if ($row['Msg_type'] === 'status') {
                            $repair_status = $row['Msg_text'];
                        }
                    }
                    
                    if ($repair_status === 'OK') {
                        $results['tables_repaired']++;
                        $results['details'][$table] = array(
                            'status' => 'repaired',
                            'message' => __('Table repaired successfully', 'spiralengine')
                        );
                    } else {
                        $results['errors'][] = sprintf(__('Failed to repair table %s: %s', 'spiralengine'), $table, $repair_status);
                        $results['details'][$table] = array(
                            'status' => 'error',
                            'message' => $repair_status
                        );
                    }
                } else {
                    $results['details'][$table] = array(
                        'status' => 'ok',
                        'message' => __('Table is healthy', 'spiralengine')
                    );
                }
            }
            
            // Log repair action
            $this->log_maintenance_action('table_repair', $results);
            
        } catch (Exception $e) {
            $results['errors'][] = $e->getMessage();
        }
        
        return $results;
    }
    
    /**
     * Clear all caches
     */
    public function clear_all_caches() {
        $results = array(
            'caches_cleared' => array(),
            'errors' => array()
        );
        
        try {
            // WordPress object cache
            if (wp_cache_flush()) {
                $results['caches_cleared'][] = 'object_cache';
            }
            
            // Transients
            $this->clear_transient_cache();
            $results['caches_cleared'][] = 'transients';
            
            // SPIRAL Engine caches
            $this->clear_pattern_cache();
            $results['caches_cleared'][] = 'pattern_cache';
            
            $this->clear_ai_cache();
            $results['caches_cleared'][] = 'ai_cache';
            
            $this->clear_widget_cache();
            $results['caches_cleared'][] = 'widget_cache';
            
            $this->clear_user_cache();
            $results['caches_cleared'][] = 'user_cache';
            
            // Page cache if available
            if ($this->clear_page_cache()) {
                $results['caches_cleared'][] = 'page_cache';
            }
            
            // CDN cache if configured
            if ($this->clear_cdn_cache()) {
                $results['caches_cleared'][] = 'cdn_cache';
            }
            
            // Update last cache clear time
            update_option('spiralengine_last_cache_clear', current_time('mysql'));
            
            // Log cache clear
            $this->log_maintenance_action('cache_clear', $results);
            
        } catch (Exception $e) {
            $results['errors'][] = $e->getMessage();
        }
        
        return $results;
    }
    
    /**
     * Fix file permissions
     */
    public function fix_permissions() {
        $results = array(
            'fixed' => 0,
            'errors' => array(),
            'details' => array()
        );
        
        try {
            // Define required permissions
            $directories = array(
                wp_upload_dir()['basedir'] . '/spiralengine' => 0755,
                wp_upload_dir()['basedir'] . '/spiralengine-backups' => 0755,
                wp_upload_dir()['basedir'] . '/spiralengine-exports' => 0755,
                wp_upload_dir()['basedir'] . '/spiralengine-temp' => 0755,
                SPIRALENGINE_PLUGIN_DIR . 'logs' => 0755,
                SPIRALENGINE_PLUGIN_DIR . 'cache' => 0755
            );
            
            foreach ($directories as $dir => $perms) {
                if (!file_exists($dir)) {
                    if (wp_mkdir_p($dir)) {
                        $results['details'][$dir] = 'created';
                        $results['fixed']++;
                    } else {
                        $results['errors'][] = sprintf(__('Could not create directory: %s', 'spiralengine'), $dir);
                    }
                }
                
                if (file_exists($dir)) {
                    $current_perms = fileperms($dir) & 0777;
                    if ($current_perms !== $perms) {
                        if (chmod($dir, $perms)) {
                            $results['details'][$dir] = sprintf('permissions changed from %o to %o', $current_perms, $perms);
                            $results['fixed']++;
                        } else {
                            $results['errors'][] = sprintf(__('Could not change permissions for: %s', 'spiralengine'), $dir);
                        }
                    } else {
                        $results['details'][$dir] = 'already correct';
                    }
                }
            }
            
            // Fix .htaccess files
            $this->create_htaccess_files();
            
            // Log permission fix
            $this->log_maintenance_action('fix_permissions', $results);
            
        } catch (Exception $e) {
            $results['errors'][] = $e->getMessage();
        }
        
        return $results;
    }
    
    /**
     * Run diagnostics
     */
    public function run_diagnostics() {
        $diagnostics = array(
            'timestamp' => current_time('mysql'),
            'checks' => array()
        );
        
        // PHP version and extensions
        $diagnostics['checks']['php'] = $this->check_php_requirements();
        
        // Database
        $diagnostics['checks']['database'] = $this->check_database_requirements();
        
        // File system
        $diagnostics['checks']['filesystem'] = $this->check_filesystem_requirements();
        
        // WordPress compatibility
        $diagnostics['checks']['wordpress'] = $this->check_wordpress_compatibility();
        
        // Plugin conflicts
        $diagnostics['checks']['conflicts'] = $this->check_plugin_conflicts();
        
        // Performance
        $diagnostics['checks']['performance'] = $this->check_performance_metrics();
        
        // Security
        $diagnostics['checks']['security'] = $this->check_security_status();
        
        // Store diagnostics
        set_transient('spiralengine_diagnostics', $diagnostics, HOUR_IN_SECONDS);
        
        return $diagnostics;
    }
    
    /**
     * Check PHP requirements
     */
    private function check_php_requirements() {
        $requirements = array(
            'version' => array(
                'required' => '7.4.0',
                'current' => PHP_VERSION,
                'status' => version_compare(PHP_VERSION, '7.4.0', '>=') ? 'ok' : 'error'
            ),
            'extensions' => array()
        );
        
        $required_extensions = array(
            'mysqli' => 'Database connectivity',
            'json' => 'JSON processing',
            'mbstring' => 'Multibyte string support',
            'openssl' => 'Encryption support',
            'curl' => 'API connectivity',
            'zip' => 'Backup compression',
            'gd' => 'Image processing'
        );
        
        foreach ($required_extensions as $ext => $description) {
            $requirements['extensions'][$ext] = array(
                'description' => $description,
                'installed' => extension_loaded($ext),
                'status' => extension_loaded($ext) ? 'ok' : 'warning'
            );
        }
        
        // Memory limit
        $memory_limit = $this->parse_size(ini_get('memory_limit'));
        $requirements['memory_limit'] = array(
            'required' => '256M',
            'current' => ini_get('memory_limit'),
            'status' => $memory_limit >= 256 * 1024 * 1024 ? 'ok' : 'warning'
        );
        
        // Max execution time
        $max_execution = ini_get('max_execution_time');
        $requirements['max_execution_time'] = array(
            'required' => '180',
            'current' => $max_execution,
            'status' => $max_execution >= 180 || $max_execution == 0 ? 'ok' : 'warning'
        );
        
        return $requirements;
    }
    
    /**
     * Check database requirements
     */
    private function check_database_requirements() {
        global $wpdb;
        
        $requirements = array(
            'version' => array(),
            'tables' => array(),
            'charset' => array()
        );
        
        // MySQL version
        $mysql_version = $wpdb->db_version();
        $requirements['version'] = array(
            'required' => '5.7.0',
            'current' => $mysql_version,
            'status' => version_compare($mysql_version, '5.7.0', '>=') ? 'ok' : 'warning'
        );
        
        // Check tables
        $required_tables = array(
            'spiralengine_assessments',
            'spiralengine_user_activity',
            'spiralengine_ai_logs',
            'spiralengine_cron_log'
        );
        
        foreach ($required_tables as $table) {
            $full_table = $wpdb->prefix . $table;
            $exists = $wpdb->get_var("SHOW TABLES LIKE '$full_table'") === $full_table;
            
            $requirements['tables'][$table] = array(
                'exists' => $exists,
                'status' => $exists ? 'ok' : 'error'
            );
        }
        
        // Charset
        $charset = $wpdb->get_charset_collate();
        $requirements['charset'] = array(
            'current' => $charset,
            'status' => strpos($charset, 'utf8mb4') !== false ? 'ok' : 'warning'
        );
        
        return $requirements;
    }
    
    /**
     * Toggle maintenance mode
     */
    public function toggle_maintenance_mode($enabled, $options = array()) {
        $this->maintenance_mode['enabled'] = $enabled;
        
        if (!empty($options['message'])) {
            $this->maintenance_mode['message'] = sanitize_textarea_field($options['message']);
        }
        
        if (!empty($options['return_time'])) {
            $this->maintenance_mode['return_time'] = sanitize_text_field($options['return_time']);
        }
        
        if (!empty($options['allowed_ips'])) {
            $this->maintenance_mode['allowed_ips'] = array_map('sanitize_text_field', $options['allowed_ips']);
        }
        
        // Save settings
        update_option('spiralengine_maintenance_mode', $this->maintenance_mode['enabled']);
        update_option('spiralengine_maintenance_message', $this->maintenance_mode['message']);
        update_option('spiralengine_maintenance_return_time', $this->maintenance_mode['return_time']);
        update_option('spiralengine_maintenance_allowed_ips', $this->maintenance_mode['allowed_ips']);
        
        // Log action
        $this->log_maintenance_action('maintenance_mode_toggle', array(
            'enabled' => $enabled,
            'options' => $options
        ));
        
        return true;
    }
    
    /**
     * AJAX handlers
     */
    public function ajax_optimize_database() {
        check_ajax_referer('spiralengine_maintenance', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'spiralengine'));
        }
        
        $options = $_POST['options'] ?? array();
        $result = $this->optimize_database($options);
        
        if (!empty($result['errors'])) {
            wp_send_json_error(array(
                'message' => implode(', ', $result['errors']),
                'result' => $result
            ));
        } else {
            wp_send_json_success($result);
        }
    }
    
    public function ajax_clean_data() {
        check_ajax_referer('spiralengine_maintenance', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'spiralengine'));
        }
        
        $type = sanitize_text_field($_POST['type'] ?? 'all');
        $options = $_POST['options'] ?? array();
        
        $result = $this->clean_data($type, $options);
        
        if (!empty($result['errors'])) {
            wp_send_json_error(array(
                'message' => implode(', ', $result['errors']),
                'result' => $result
            ));
        } else {
            wp_send_json_success($result);
        }
    }
    
    public function ajax_repair_tables() {
        check_ajax_referer('spiralengine_maintenance', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'spiralengine'));
        }
        
        $tables = $_POST['tables'] ?? array();
        $result = $this->repair_tables($tables);
        
        if (!empty($result['errors'])) {
            wp_send_json_error(array(
                'message' => implode(', ', $result['errors']),
                'result' => $result
            ));
        } else {
            wp_send_json_success($result);
        }
    }
    
    public function ajax_clear_cache() {
        check_ajax_referer('spiralengine_maintenance', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'spiralengine'));
        }
        
        $result = $this->clear_all_caches();
        
        if (!empty($result['errors'])) {
            wp_send_json_error(array(
                'message' => implode(', ', $result['errors']),
                'result' => $result
            ));
        } else {
            wp_send_json_success($result);
        }
    }
    
    public function ajax_fix_permissions() {
        check_ajax_referer('spiralengine_maintenance', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'spiralengine'));
        }
        
        $result = $this->fix_permissions();
        
        if (!empty($result['errors'])) {
            wp_send_json_error(array(
                'message' => implode(', ', $result['errors']),
                'result' => $result
            ));
        } else {
            wp_send_json_success($result);
        }
    }
    
    public function ajax_toggle_maintenance() {
        check_ajax_referer('spiralengine_maintenance', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'spiralengine'));
        }
        
        $enabled = !empty($_POST['enabled']);
        $options = $_POST['options'] ?? array();
        
        if ($this->toggle_maintenance_mode($enabled, $options)) {
            wp_send_json_success(array(
                'message' => $enabled ? __('Maintenance mode enabled', 'spiralengine') : __('Maintenance mode disabled', 'spiralengine')
            ));
        } else {
            wp_send_json_error(__('Failed to toggle maintenance mode', 'spiralengine'));
        }
    }
    
    public function ajax_run_diagnostics() {
        check_ajax_referer('spiralengine_maintenance', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'spiralengine'));
        }
        
        $result = $this->run_diagnostics();
        wp_send_json_success($result);
    }
    
    /**
     * Helper methods
     */
    private function log_maintenance_action($action, $data = array()) {
        global $wpdb;
        
        $wpdb->insert(
            $wpdb->prefix . 'spiralengine_maintenance_log',
            array(
                'action' => $action,
                'user_id' => get_current_user_id(),
                'data' => json_encode($data),
                'created_at' => current_time('mysql')
            ),
            array('%s', '%d', '%s', '%s')
        );
    }
    
    private function clear_pattern_cache() {
        global $wpdb;
        
        $wpdb->query(
            "DELETE FROM {$wpdb->options} 
             WHERE option_name LIKE '_transient_spiralengine_pattern_%' 
             OR option_name LIKE '_transient_timeout_spiralengine_pattern_%'"
        );
    }
    
    private function clear_ai_cache() {
        global $wpdb;
        
        $wpdb->query(
            "DELETE FROM {$wpdb->options} 
             WHERE option_name LIKE '_transient_spiralengine_ai_%' 
             OR option_name LIKE '_transient_timeout_spiralengine_ai_%'"
        );
        
        // Clear AI cache table if exists
        if ($wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}spiralengine_ai_cache'")) {
            $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}spiralengine_ai_cache");
        }
    }
    
    private function clear_widget_cache() {
        global $wpdb;
        
        $wpdb->query(
            "DELETE FROM {$wpdb->options} 
             WHERE option_name LIKE '_transient_spiralengine_widget_%' 
             OR option_name LIKE '_transient_timeout_spiralengine_widget_%'"
        );
    }
    
    private function clear_user_cache() {
        global $wpdb;
        
        $wpdb->query(
            "DELETE FROM {$wpdb->options} 
             WHERE option_name LIKE '_transient_spiralengine_user_%' 
             OR option_name LIKE '_transient_timeout_spiralengine_user_%'"
        );
    }
    
    private function clear_page_cache() {
        // Check for popular caching plugins
        if (function_exists('wp_cache_clear_cache')) {
            wp_cache_clear_cache();
            return true;
        }
        
        if (function_exists('w3tc_flush_all')) {
            w3tc_flush_all();
            return true;
        }
        
        if (function_exists('rocket_clean_domain')) {
            rocket_clean_domain();
            return true;
        }
        
        return false;
    }
    
    private function clear_cdn_cache() {
        // Cloudflare
        $cloudflare_zone = get_option('spiralengine_cloudflare_zone_id');
        if ($cloudflare_zone) {
            $this->purge_cloudflare_cache($cloudflare_zone);
            return true;
        }
        
        // Add other CDN providers as needed
        
        return false;
    }
    
    private function parse_size($size) {
        $unit = preg_replace('/[^bkmgtpezy]/i', '', $size);
        $size = preg_replace('/[^0-9\.]/', '', $size);
        
        if ($unit) {
            return round($size * pow(1024, stripos('bkmgtpezy', $unit[0])));
        } else {
            return round($size);
        }
    }
    
    private function create_htaccess_files() {
        $directories = array(
            wp_upload_dir()['basedir'] . '/spiralengine-backups',
            wp_upload_dir()['basedir'] . '/spiralengine-exports',
            SPIRALENGINE_PLUGIN_DIR . 'logs'
        );
        
        $htaccess_content = "Order Deny,Allow\nDeny from all";
        
        foreach ($directories as $dir) {
            if (is_dir($dir)) {
                $htaccess_file = $dir . '/.htaccess';
                if (!file_exists($htaccess_file)) {
                    file_put_contents($htaccess_file, $htaccess_content);
                }
            }
        }
    }
}

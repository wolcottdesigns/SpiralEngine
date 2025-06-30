<?php
// includes/tools/class-spiralengine-diagnostics.php

/**
 * Spiral Engine Diagnostics System
 * 
 * Implements comprehensive diagnostics including system health checks,
 * conflict detection, error logging, and debug tools.
 *
 * @package    Spiral_Engine
 * @subpackage Spiral_Engine/tools
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class SPIRALENGINE_Diagnostics {
    
    /**
     * Time manager instance
     */
    private $time_manager;
    
    /**
     * Debug mode
     */
    private $debug_mode;
    
    /**
     * Error log limit
     */
    private $error_log_limit = 1000;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->time_manager = new SPIRALENGINE_Time_Zone_Manager();
        $this->debug_mode = defined('SPIRALENGINE_DEBUG') && SPIRALENGINE_DEBUG;
        $this->init_hooks();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Error handling
        if ($this->debug_mode) {
            set_error_handler(array($this, 'error_handler'));
            register_shutdown_function(array($this, 'shutdown_handler'));
        }
        
        // AJAX handlers
        add_action('wp_ajax_spiralengine_run_diagnostics', array($this, 'ajax_run_diagnostics'));
        add_action('wp_ajax_spiralengine_check_system_health', array($this, 'ajax_check_system_health'));
        add_action('wp_ajax_spiralengine_detect_conflicts', array($this, 'ajax_detect_conflicts'));
        add_action('wp_ajax_spiralengine_view_error_log', array($this, 'ajax_view_error_log'));
        add_action('wp_ajax_spiralengine_clear_error_log', array($this, 'ajax_clear_error_log'));
        add_action('wp_ajax_spiralengine_export_diagnostics', array($this, 'ajax_export_diagnostics'));
        add_action('wp_ajax_spiralengine_test_component', array($this, 'ajax_test_component'));
        
        // Debug bar integration
        add_filter('debug_bar_panels', array($this, 'add_debug_bar_panel'));
        
        // Admin notices for critical issues
        add_action('admin_notices', array($this, 'show_critical_notices'));
    }
    
    /**
     * Run full diagnostics
     */
    public function run_full_diagnostics() {
        $diagnostics = array(
            'timestamp' => current_time('mysql'),
            'timestamp_utc' => gmdate('Y-m-d H:i:s'),
            'summary' => array(
                'status' => 'healthy',
                'issues' => 0,
                'warnings' => 0
            ),
            'checks' => array()
        );
        
        // System requirements
        $diagnostics['checks']['system'] = $this->check_system_requirements();
        
        // Database health
        $diagnostics['checks']['database'] = $this->check_database_health();
        
        // File system
        $diagnostics['checks']['filesystem'] = $this->check_filesystem_health();
        
        // WordPress environment
        $diagnostics['checks']['wordpress'] = $this->check_wordpress_environment();
        
        // Plugin conflicts
        $diagnostics['checks']['conflicts'] = $this->detect_plugin_conflicts();
        
        // Performance metrics
        $diagnostics['checks']['performance'] = $this->check_performance_metrics();
        
        // Security audit
        $diagnostics['checks']['security'] = $this->check_security_status();
        
        // API connectivity
        $diagnostics['checks']['api'] = $this->check_api_connectivity();
        
        // Error log analysis
        $diagnostics['checks']['errors'] = $this->analyze_error_logs();
        
        // Calculate summary
        foreach ($diagnostics['checks'] as $check) {
            if (isset($check['issues'])) {
                $diagnostics['summary']['issues'] += count($check['issues']);
            }
            if (isset($check['warnings'])) {
                $diagnostics['summary']['warnings'] += count($check['warnings']);
            }
            if (isset($check['status']) && $check['status'] === 'critical') {
                $diagnostics['summary']['status'] = 'critical';
            } elseif (isset($check['status']) && $check['status'] === 'warning' && $diagnostics['summary']['status'] !== 'critical') {
                $diagnostics['summary']['status'] = 'warning';
            }
        }
        
        // Store diagnostics
        set_transient('spiralengine_diagnostics', $diagnostics, HOUR_IN_SECONDS);
        
        // Log diagnostics run
        $this->log_diagnostics_run($diagnostics);
        
        return $diagnostics;
    }
    
    /**
     * Check system requirements
     */
    public function check_system_requirements() {
        $requirements = array(
            'status' => 'ok',
            'issues' => array(),
            'warnings' => array(),
            'info' => array()
        );
        
        // PHP Version
        $php_version = PHP_VERSION;
        $min_php = '7.4.0';
        $recommended_php = '8.0.0';
        
        if (version_compare($php_version, $min_php, '<')) {
            $requirements['issues'][] = sprintf(
                __('PHP version %s is below minimum requirement of %s', 'spiralengine'),
                $php_version,
                $min_php
            );
            $requirements['status'] = 'critical';
        } elseif (version_compare($php_version, $recommended_php, '<')) {
            $requirements['warnings'][] = sprintf(
                __('PHP version %s is below recommended version %s', 'spiralengine'),
                $php_version,
                $recommended_php
            );
            if ($requirements['status'] !== 'critical') {
                $requirements['status'] = 'warning';
            }
        }
        
        $requirements['info']['php_version'] = $php_version;
        
        // PHP Extensions
        $required_extensions = array(
            'mysqli' => __('Database connectivity', 'spiralengine'),
            'json' => __('JSON processing', 'spiralengine'),
            'mbstring' => __('Multibyte string support', 'spiralengine'),
            'openssl' => __('Encryption support', 'spiralengine'),
            'curl' => __('API connectivity', 'spiralengine'),
            'zip' => __('Backup compression', 'spiralengine'),
            'gd' => __('Image processing', 'spiralengine'),
            'xml' => __('XML processing', 'spiralengine')
        );
        
        $requirements['info']['extensions'] = array();
        
        foreach ($required_extensions as $ext => $description) {
            $installed = extension_loaded($ext);
            $requirements['info']['extensions'][$ext] = array(
                'installed' => $installed,
                'description' => $description
            );
            
            if (!$installed) {
                $requirements['warnings'][] = sprintf(
                    __('PHP extension "%s" is not installed (%s)', 'spiralengine'),
                    $ext,
                    $description
                );
                if ($requirements['status'] === 'ok') {
                    $requirements['status'] = 'warning';
                }
            }
        }
        
        // Memory limit
        $memory_limit = $this->parse_size(ini_get('memory_limit'));
        $min_memory = 256 * MB_IN_BYTES;
        $recommended_memory = 512 * MB_IN_BYTES;
        
        $requirements['info']['memory_limit'] = ini_get('memory_limit');
        
        if ($memory_limit < $min_memory) {
            $requirements['issues'][] = sprintf(
                __('Memory limit %s is below minimum requirement of %s', 'spiralengine'),
                ini_get('memory_limit'),
                '256M'
            );
            $requirements['status'] = 'critical';
        } elseif ($memory_limit < $recommended_memory) {
            $requirements['warnings'][] = sprintf(
                __('Memory limit %s is below recommended %s', 'spiralengine'),
                ini_get('memory_limit'),
                '512M'
            );
            if ($requirements['status'] === 'ok') {
                $requirements['status'] = 'warning';
            }
        }
        
        // Max execution time
        $max_execution = ini_get('max_execution_time');
        $requirements['info']['max_execution_time'] = $max_execution;
        
        if ($max_execution > 0 && $max_execution < 180) {
            $requirements['warnings'][] = sprintf(
                __('Max execution time %s seconds is below recommended 180 seconds', 'spiralengine'),
                $max_execution
            );
            if ($requirements['status'] === 'ok') {
                $requirements['status'] = 'warning';
            }
        }
        
        // Upload limits
        $upload_max = $this->parse_size(ini_get('upload_max_filesize'));
        $post_max = $this->parse_size(ini_get('post_max_size'));
        
        $requirements['info']['upload_max_filesize'] = ini_get('upload_max_filesize');
        $requirements['info']['post_max_size'] = ini_get('post_max_size');
        
        if ($upload_max < 64 * MB_IN_BYTES) {
            $requirements['warnings'][] = sprintf(
                __('Upload max filesize %s is below recommended 64M', 'spiralengine'),
                ini_get('upload_max_filesize')
            );
        }
        
        if ($post_max < 64 * MB_IN_BYTES) {
            $requirements['warnings'][] = sprintf(
                __('Post max size %s is below recommended 64M', 'spiralengine'),
                ini_get('post_max_size')
            );
        }
        
        return $requirements;
    }
    
    /**
     * Check database health
     */
    public function check_database_health() {
        global $wpdb;
        
        $health = array(
            'status' => 'ok',
            'issues' => array(),
            'warnings' => array(),
            'info' => array()
        );
        
        // Database version
        $mysql_version = $wpdb->db_version();
        $min_mysql = '5.7.0';
        $recommended_mysql = '8.0.0';
        
        $health['info']['mysql_version'] = $mysql_version;
        $health['info']['database_name'] = DB_NAME;
        $health['info']['table_prefix'] = $wpdb->prefix;
        
        if (version_compare($mysql_version, $min_mysql, '<')) {
            $health['issues'][] = sprintf(
                __('MySQL version %s is below minimum requirement of %s', 'spiralengine'),
                $mysql_version,
                $min_mysql
            );
            $health['status'] = 'critical';
        } elseif (version_compare($mysql_version, $recommended_mysql, '<')) {
            $health['warnings'][] = sprintf(
                __('MySQL version %s is below recommended version %s', 'spiralengine'),
                $mysql_version,
                $recommended_mysql
            );
            if ($health['status'] === 'ok') {
                $health['status'] = 'warning';
            }
        }
        
        // Check tables
        $required_tables = array(
            'spiralengine_assessments',
            'spiralengine_user_activity',
            'spiralengine_ai_logs',
            'spiralengine_episodes_overthinking',
            'spiralengine_episodes_anxiety',
            'spiralengine_episodes_depression',
            'spiralengine_episodes_ptsd',
            'spiralengine_cron_log',
            'spiralengine_audit_log'
        );
        
        $health['info']['tables'] = array();
        $missing_tables = array();
        
        foreach ($required_tables as $table) {
            $full_table = $wpdb->prefix . $table;
            $exists = $wpdb->get_var("SHOW TABLES LIKE '$full_table'") === $full_table;
            
            $health['info']['tables'][$table] = array(
                'exists' => $exists,
                'full_name' => $full_table
            );
            
            if (!$exists) {
                $missing_tables[] = $table;
            }
        }
        
        if (!empty($missing_tables)) {
            $health['issues'][] = sprintf(
                __('Missing database tables: %s', 'spiralengine'),
                implode(', ', $missing_tables)
            );
            $health['status'] = 'critical';
        }
        
        // Check table status
        $tables = $wpdb->get_results(
            "SELECT table_name, engine, table_collation, data_free 
             FROM information_schema.tables 
             WHERE table_schema = '" . DB_NAME . "' 
             AND table_name LIKE '{$wpdb->prefix}spiralengine_%'",
            ARRAY_A
        );
        
        foreach ($tables as $table) {
            // Check engine
            if ($table['engine'] !== 'InnoDB') {
                $health['warnings'][] = sprintf(
                    __('Table %s is using %s engine instead of recommended InnoDB', 'spiralengine'),
                    $table['table_name'],
                    $table['engine']
                );
            }
            
            // Check fragmentation
            if ($table['data_free'] > 100 * MB_IN_BYTES) {
                $health['warnings'][] = sprintf(
                    __('Table %s has %s of fragmentation', 'spiralengine'),
                    $table['table_name'],
                    size_format($table['data_free'])
                );
            }
        }
        
        // Check charset
        $charset = $wpdb->get_charset_collate();
        $health['info']['charset'] = $charset;
        
        if (strpos($charset, 'utf8mb4') === false) {
            $health['warnings'][] = __('Database is not using utf8mb4 charset which may cause issues with emoji support', 'spiralengine');
        }
        
        // Check connection
        if (!$wpdb->check_connection(false)) {
            $health['issues'][] = __('Database connection test failed', 'spiralengine');
            $health['status'] = 'critical';
        }
        
        return $health;
    }
    
    /**
     * Check filesystem health
     */
    public function check_filesystem_health() {
        $health = array(
            'status' => 'ok',
            'issues' => array(),
            'warnings' => array(),
            'info' => array()
        );
        
        // Required directories
        $directories = array(
            'uploads' => wp_upload_dir()['basedir'] . '/spiralengine',
            'backups' => wp_upload_dir()['basedir'] . '/spiralengine-backups',
            'exports' => wp_upload_dir()['basedir'] . '/spiralengine-exports',
            'temp' => wp_upload_dir()['basedir'] . '/spiralengine-temp',
            'logs' => SPIRALENGINE_PLUGIN_DIR . 'logs',
            'cache' => SPIRALENGINE_PLUGIN_DIR . 'cache'
        );
        
        $health['info']['directories'] = array();
        
        foreach ($directories as $key => $dir) {
            $exists = file_exists($dir);
            $writable = $exists ? is_writable($dir) : false;
            
            $health['info']['directories'][$key] = array(
                'path' => $dir,
                'exists' => $exists,
                'writable' => $writable
            );
            
            if (!$exists) {
                // Try to create
                if (!wp_mkdir_p($dir)) {
                    $health['issues'][] = sprintf(
                        __('Required directory does not exist and cannot be created: %s', 'spiralengine'),
                        $dir
                    );
                    $health['status'] = 'critical';
                }
            } elseif (!$writable) {
                $health['issues'][] = sprintf(
                    __('Directory is not writable: %s', 'spiralengine'),
                    $dir
                );
                $health['status'] = 'critical';
            }
        }
        
        // Check disk space
        $free_space = disk_free_space(ABSPATH);
        $total_space = disk_total_space(ABSPATH);
        $used_percentage = ($total_space - $free_space) / $total_space * 100;
        
        $health['info']['disk_space'] = array(
            'free' => $free_space,
            'total' => $total_space,
            'used_percentage' => round($used_percentage, 2)
        );
        
        if ($free_space < 100 * MB_IN_BYTES) {
            $health['issues'][] = sprintf(
                __('Low disk space: %s free', 'spiralengine'),
                size_format($free_space)
            );
            $health['status'] = 'critical';
        } elseif ($free_space < 500 * MB_IN_BYTES) {
            $health['warnings'][] = sprintf(
                __('Disk space running low: %s free', 'spiralengine'),
                size_format($free_space)
            );
            if ($health['status'] === 'ok') {
                $health['status'] = 'warning';
            }
        }
        
        // Check file permissions
        $permission_issues = $this->check_file_permissions();
        if (!empty($permission_issues)) {
            $health['warnings'] = array_merge($health['warnings'], $permission_issues);
            if ($health['status'] === 'ok') {
                $health['status'] = 'warning';
            }
        }
        
        return $health;
    }
    
    /**
     * Check WordPress environment
     */
    public function check_wordpress_environment() {
        $environment = array(
            'status' => 'ok',
            'issues' => array(),
            'warnings' => array(),
            'info' => array()
        );
        
        // WordPress version
        $wp_version = get_bloginfo('version');
        $min_wp = '5.5';
        $recommended_wp = '6.0';
        
        $environment['info']['wordpress_version'] = $wp_version;
        
        if (version_compare($wp_version, $min_wp, '<')) {
            $environment['issues'][] = sprintf(
                __('WordPress version %s is below minimum requirement of %s', 'spiralengine'),
                $wp_version,
                $min_wp
            );
            $environment['status'] = 'critical';
        } elseif (version_compare($wp_version, $recommended_wp, '<')) {
            $environment['warnings'][] = sprintf(
                __('WordPress version %s is below recommended version %s', 'spiralengine'),
                $wp_version,
                $recommended_wp
            );
            if ($environment['status'] === 'ok') {
                $environment['status'] = 'warning';
            }
        }
        
        // Site info
        $environment['info']['site_url'] = get_site_url();
        $environment['info']['home_url'] = get_home_url();
        $environment['info']['multisite'] = is_multisite();
        $environment['info']['debug_mode'] = WP_DEBUG;
        $environment['info']['debug_display'] = WP_DEBUG_DISPLAY;
        $environment['info']['debug_log'] = WP_DEBUG_LOG;
        
        // Theme compatibility
        $theme = wp_get_theme();
        $environment['info']['active_theme'] = array(
            'name' => $theme->get('Name'),
            'version' => $theme->get('Version'),
            'author' => $theme->get('Author'),
            'template' => $theme->get_template()
        );
        
        // Active plugins
        $active_plugins = get_option('active_plugins', array());
        $environment['info']['active_plugins_count'] = count($active_plugins);
        
        // MemberPress check
        $memberpress_active = in_array('memberpress/memberpress.php', $active_plugins);
        $environment['info']['memberpress_active'] = $memberpress_active;
        
        if (!$memberpress_active) {
            $environment['warnings'][] = __('MemberPress is not active. Some features may not work properly.', 'spiralengine');
            if ($environment['status'] === 'ok') {
                $environment['status'] = 'warning';
            }
        }
        
        // Cron status
        $environment['info']['cron_disabled'] = defined('DISABLE_WP_CRON') && DISABLE_WP_CRON;
        
        if (defined('DISABLE_WP_CRON') && DISABLE_WP_CRON) {
            $environment['warnings'][] = __('WordPress cron is disabled. Scheduled tasks may not run properly.', 'spiralengine');
            if ($environment['status'] === 'ok') {
                $environment['status'] = 'warning';
            }
        }
        
        // Check AJAX
        $environment['info']['ajax_url'] = admin_url('admin-ajax.php');
        
        // REST API
        $rest_url = get_rest_url();
        $environment['info']['rest_api_url'] = $rest_url;
        
        // Test REST API
        if (!$this->test_rest_api()) {
            $environment['warnings'][] = __('REST API is not accessible or responding properly', 'spiralengine');
            if ($environment['status'] === 'ok') {
                $environment['status'] = 'warning';
            }
        }
        
        return $environment;
    }
    
    /**
     * Detect plugin conflicts
     */
    public function detect_plugin_conflicts() {
        $conflicts = array(
            'status' => 'ok',
            'issues' => array(),
            'warnings' => array(),
            'info' => array()
        );
        
        // Known conflicting plugins
        $known_conflicts = array(
            'wordfence/wordfence.php' => array(
                'name' => 'Wordfence Security',
                'issue' => __('May block AI API requests if not configured properly', 'spiralengine'),
                'solution' => __('Add OpenAI API endpoints to Wordfence whitelist', 'spiralengine')
            ),
            'better-wp-security/better-wp-security.php' => array(
                'name' => 'iThemes Security',
                'issue' => __('May interfere with file permissions and API calls', 'spiralengine'),
                'solution' => __('Configure security settings to allow SPIRAL Engine operations', 'spiralengine')
            ),
            'wp-optimize/wp-optimize.php' => array(
                'name' => 'WP-Optimize',
                'issue' => __('May clean SPIRAL Engine database tables if not excluded', 'spiralengine'),
                'solution' => __('Exclude SPIRAL Engine tables from optimization', 'spiralengine')
            )
        );
        
        $active_plugins = get_option('active_plugins', array());
        $conflicts['info']['active_plugins'] = array();
        $detected_conflicts = array();
        
        foreach ($active_plugins as $plugin) {
            $plugin_data = get_plugin_data(WP_PLUGIN_DIR . '/' . $plugin);
            
            $conflicts['info']['active_plugins'][] = array(
                'file' => $plugin,
                'name' => $plugin_data['Name'],
                'version' => $plugin_data['Version']
            );
            
            // Check for known conflicts
            if (isset($known_conflicts[$plugin])) {
                $conflict = $known_conflicts[$plugin];
                $detected_conflicts[] = $conflict;
                
                $conflicts['warnings'][] = sprintf(
                    __('%s detected: %s', 'spiralengine'),
                    $conflict['name'],
                    $conflict['issue']
                );
            }
        }
        
        if (!empty($detected_conflicts)) {
            $conflicts['info']['detected_conflicts'] = $detected_conflicts;
            $conflicts['status'] = 'warning';
        }
        
        // Check for duplicate functionality
        $this->check_duplicate_functionality($conflicts, $active_plugins);
        
        // Hook conflicts
        $hook_conflicts = $this->check_hook_conflicts();
        if (!empty($hook_conflicts)) {
            $conflicts['warnings'] = array_merge($conflicts['warnings'], $hook_conflicts);
            $conflicts['status'] = 'warning';
        }
        
        return $conflicts;
    }
    
    /**
     * Check performance metrics
     */
    public function check_performance_metrics() {
        global $wpdb;
        
        $performance = array(
            'status' => 'ok',
            'issues' => array(),
            'warnings' => array(),
            'metrics' => array()
        );
        
        // Database query performance
        $slow_queries = $wpdb->get_results(
            "SELECT COUNT(*) as count, AVG(execution_time) as avg_time, MAX(execution_time) as max_time 
             FROM {$wpdb->prefix}spiralengine_query_log 
             WHERE execution_time > 1 
             AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)"
        );
        
        if (!empty($slow_queries[0]->count)) {
            $performance['metrics']['slow_queries'] = array(
                'count' => $slow_queries[0]->count,
                'avg_time' => round($slow_queries[0]->avg_time, 2),
                'max_time' => round($slow_queries[0]->max_time, 2)
            );
            
            if ($slow_queries[0]->count > 100) {
                $performance['warnings'][] = sprintf(
                    __('%d slow queries detected in the last 24 hours', 'spiralengine'),
                    $slow_queries[0]->count
                );
                $performance['status'] = 'warning';
            }
        }
        
        // Memory usage
        $memory_usage = memory_get_usage(true);
        $memory_peak = memory_get_peak_usage(true);
        $memory_limit = $this->parse_size(ini_get('memory_limit'));
        
        $performance['metrics']['memory'] = array(
            'current' => $memory_usage,
            'peak' => $memory_peak,
            'limit' => $memory_limit,
            'usage_percentage' => round(($memory_usage / $memory_limit) * 100, 2)
        );
        
        if ($memory_usage > $memory_limit * 0.8) {
            $performance['warnings'][] = sprintf(
                __('High memory usage: %s of %s (%.1f%%)', 'spiralengine'),
                size_format($memory_usage),
                size_format($memory_limit),
                ($memory_usage / $memory_limit) * 100
            );
            $performance['status'] = 'warning';
        }
        
        // Database size
        $db_size = $wpdb->get_var(
            "SELECT SUM(data_length + index_length) 
             FROM information_schema.tables 
             WHERE table_schema = '" . DB_NAME . "' 
             AND table_name LIKE '{$wpdb->prefix}spiralengine_%'"
        );
        
        $performance['metrics']['database_size'] = $db_size;
        
        if ($db_size > 500 * MB_IN_BYTES) {
            $performance['warnings'][] = sprintf(
                __('Large database size: %s', 'spiralengine'),
                size_format($db_size)
            );
        }
        
        // Cache hit rate
        $cache_stats = $this->get_cache_statistics();
        $performance['metrics']['cache'] = $cache_stats;
        
        if ($cache_stats['hit_rate'] < 80) {
            $performance['warnings'][] = sprintf(
                __('Low cache hit rate: %.1f%%', 'spiralengine'),
                $cache_stats['hit_rate']
            );
        }
        
        // API response times
        $api_stats = $this->get_api_statistics();
        $performance['metrics']['api'] = $api_stats;
        
        if ($api_stats['avg_response_time'] > 2000) {
            $performance['warnings'][] = sprintf(
                __('Slow API response time: %.2f seconds average', 'spiralengine'),
                $api_stats['avg_response_time'] / 1000
            );
        }
        
        return $performance;
    }
    
    /**
     * Check security status
     */
    public function check_security_status() {
        $security = array(
            'status' => 'ok',
            'issues' => array(),
            'warnings' => array(),
            'info' => array()
        );
        
        // SSL check
        $security['info']['ssl_enabled'] = is_ssl();
        
        if (!is_ssl()) {
            $security['warnings'][] = __('SSL is not enabled. User data may not be encrypted in transit.', 'spiralengine');
            $security['status'] = 'warning';
        }
        
        // File permissions
        $permission_issues = $this->check_security_permissions();
        if (!empty($permission_issues)) {
            $security['issues'] = array_merge($security['issues'], $permission_issues);
            $security['status'] = 'critical';
        }
        
        // API key security
        $api_keys = array(
            'openai' => get_option('spiralengine_openai_api_key'),
            'encryption' => get_option('spiralengine_encryption_key')
        );
        
        foreach ($api_keys as $key => $value) {
            if (!empty($value)) {
                // Check if stored encrypted
                if (strpos($value, 'enc:') !== 0) {
                    $security['warnings'][] = sprintf(
                        __('%s API key is not stored encrypted', 'spiralengine'),
                        ucfirst($key)
                    );
                    $security['status'] = 'warning';
                }
            }
        }
        
        // Database security
        $db_prefix = $GLOBALS['wpdb']->prefix;
        if ($db_prefix === 'wp_') {
            $security['warnings'][] = __('Using default database prefix "wp_" is a security risk', 'spiralengine');
        }
        
        // Admin user check
        $admin_user = get_user_by('login', 'admin');
        if ($admin_user) {
            $security['warnings'][] = __('Default "admin" username exists, which is a security risk', 'spiralengine');
            $security['status'] = 'warning';
        }
        
        // Failed login attempts
        $failed_logins = $this->get_failed_login_stats();
        if ($failed_logins['count'] > 100) {
            $security['warnings'][] = sprintf(
                __('%d failed login attempts in the last 24 hours', 'spiralengine'),
                $failed_logins['count']
            );
        }
        
        // Check for exposed files
        $exposed_files = $this->check_exposed_files();
        if (!empty($exposed_files)) {
            $security['issues'] = array_merge($security['issues'], $exposed_files);
            $security['status'] = 'critical';
        }
        
        return $security;
    }
    
    /**
     * Check API connectivity
     */
    public function check_api_connectivity() {
        $api_status = array(
            'status' => 'ok',
            'issues' => array(),
            'warnings' => array(),
            'endpoints' => array()
        );
        
        // OpenAI API
        $openai_result = $this->test_openai_api();
        $api_status['endpoints']['openai'] = $openai_result;
        
        if (!$openai_result['connected']) {
            $api_status['issues'][] = sprintf(
                __('OpenAI API connection failed: %s', 'spiralengine'),
                $openai_result['error']
            );
            $api_status['status'] = 'critical';
        }
        
        // WordPress.org API
        $wp_api_result = $this->test_wordpress_api();
        $api_status['endpoints']['wordpress'] = $wp_api_result;
        
        if (!$wp_api_result['connected']) {
            $api_status['warnings'][] = __('WordPress.org API is not accessible', 'spiralengine');
            if ($api_status['status'] === 'ok') {
                $api_status['status'] = 'warning';
            }
        }
        
        // License server (if applicable)
        $license_result = $this->test_license_server();
        $api_status['endpoints']['license'] = $license_result;
        
        if (!$license_result['connected']) {
            $api_status['warnings'][] = __('License server is not accessible', 'spiralengine');
        }
        
        // External services
        $external_services = apply_filters('spiralengine_external_services', array());
        foreach ($external_services as $service_name => $service_url) {
            $result = $this->test_external_service($service_url);
            $api_status['endpoints'][$service_name] = $result;
            
            if (!$result['connected']) {
                $api_status['warnings'][] = sprintf(
                    __('%s service is not accessible', 'spiralengine'),
                    $service_name
                );
            }
        }
        
        return $api_status;
    }
    
    /**
     * Analyze error logs
     */
    public function analyze_error_logs() {
        global $wpdb;
        
        $analysis = array(
            'status' => 'ok',
            'issues' => array(),
            'warnings' => array(),
            'summary' => array()
        );
        
        // Get recent errors
        $recent_errors = $wpdb->get_results(
            "SELECT error_type, COUNT(*) as count, MAX(created_at) as last_occurrence 
             FROM {$wpdb->prefix}spiralengine_error_log 
             WHERE created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR) 
             GROUP BY error_type 
             ORDER BY count DESC 
             LIMIT 10"
        );
        
        $analysis['summary']['recent_errors'] = array();
        $total_errors = 0;
        
        foreach ($recent_errors as $error) {
            $analysis['summary']['recent_errors'][] = array(
                'type' => $error->error_type,
                'count' => $error->count,
                'last_occurrence' => $this->time_manager->format_user_time($error->last_occurrence)
            );
            
            $total_errors += $error->count;
            
            // Check for critical error patterns
            if (stripos($error->error_type, 'fatal') !== false && $error->count > 5) {
                $analysis['issues'][] = sprintf(
                    __('%d fatal errors of type "%s" in the last 24 hours', 'spiralengine'),
                    $error->count,
                    $error->error_type
                );
                $analysis['status'] = 'critical';
            }
        }
        
        $analysis['summary']['total_errors_24h'] = $total_errors;
        
        if ($total_errors > 1000) {
            $analysis['issues'][] = sprintf(
                __('High error rate: %d errors in the last 24 hours', 'spiralengine'),
                $total_errors
            );
            $analysis['status'] = 'critical';
        } elseif ($total_errors > 100) {
            $analysis['warnings'][] = sprintf(
                __('Elevated error rate: %d errors in the last 24 hours', 'spiralengine'),
                $total_errors
            );
            if ($analysis['status'] === 'ok') {
                $analysis['status'] = 'warning';
            }
        }
        
        // Check for recurring errors
        $recurring_errors = $wpdb->get_results(
            "SELECT error_message, COUNT(DISTINCT DATE(created_at)) as days_occurred, COUNT(*) as total_count 
             FROM {$wpdb->prefix}spiralengine_error_log 
             WHERE created_at > DATE_SUB(NOW(), INTERVAL 7 DAY) 
             GROUP BY error_message 
             HAVING days_occurred > 3 
             ORDER BY total_count DESC 
             LIMIT 5"
        );
        
        if (!empty($recurring_errors)) {
            $analysis['summary']['recurring_errors'] = array();
            
            foreach ($recurring_errors as $error) {
                $analysis['summary']['recurring_errors'][] = array(
                    'message' => substr($error->error_message, 0, 100) . '...',
                    'days_occurred' => $error->days_occurred,
                    'total_count' => $error->total_count
                );
                
                if ($error->total_count > 50) {
                    $analysis['warnings'][] = sprintf(
                        __('Recurring error occurring %d times over %d days', 'spiralengine'),
                        $error->total_count,
                        $error->days_occurred
                    );
                }
            }
        }
        
        return $analysis;
    }
    
    /**
     * Log error
     */
    public function log_error($error_type, $error_message, $error_data = array()) {
        global $wpdb;
        
        // Limit error message length
        $error_message = substr($error_message, 0, 500);
        
        // Insert into database
        $wpdb->insert(
            $wpdb->prefix . 'spiralengine_error_log',
            array(
                'error_type' => $error_type,
                'error_message' => $error_message,
                'error_data' => json_encode($error_data),
                'user_id' => get_current_user_id(),
                'url' => isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '',
                'created_at' => current_time('mysql')
            ),
            array('%s', '%s', '%s', '%d', '%s', '%s')
        );
        
        // Clean old logs if limit exceeded
        $count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}spiralengine_error_log");
        
        if ($count > $this->error_log_limit) {
            $wpdb->query(
                "DELETE FROM {$wpdb->prefix}spiralengine_error_log 
                 ORDER BY created_at ASC 
                 LIMIT " . ($count - $this->error_log_limit)
            );
        }
        
        // Also log to file if debug mode
        if ($this->debug_mode) {
            $this->log_to_file($error_type, $error_message, $error_data);
        }
    }
    
    /**
     * Error handler
     */
    public function error_handler($errno, $errstr, $errfile, $errline) {
        // Skip if error reporting is disabled
        if (!(error_reporting() & $errno)) {
            return false;
        }
        
        // Only handle SPIRAL Engine related errors
        if (strpos($errfile, 'spiralengine') === false) {
            return false;
        }
        
        $error_types = array(
            E_ERROR => 'Fatal Error',
            E_WARNING => 'Warning',
            E_PARSE => 'Parse Error',
            E_NOTICE => 'Notice',
            E_STRICT => 'Strict Notice',
            E_DEPRECATED => 'Deprecated'
        );
        
        $error_type = isset($error_types[$errno]) ? $error_types[$errno] : 'Unknown Error';
        
        $this->log_error($error_type, $errstr, array(
            'file' => $errfile,
            'line' => $errline,
            'errno' => $errno
        ));
        
        // Let PHP handle the error as well
        return false;
    }
    
    /**
     * Shutdown handler
     */
    public function shutdown_handler() {
        $error = error_get_last();
        
        if ($error !== null && $error['type'] === E_ERROR) {
            // Only handle SPIRAL Engine related fatal errors
            if (strpos($error['file'], 'spiralengine') !== false) {
                $this->log_error('Fatal Error', $error['message'], array(
                    'file' => $error['file'],
                    'line' => $error['line']
                ));
            }
        }
    }
    
    /**
     * Generate diagnostics report
     */
    public function generate_diagnostics_report() {
        $diagnostics = $this->run_full_diagnostics();
        
        $report = "SPIRAL ENGINE DIAGNOSTICS REPORT\n";
        $report .= "================================\n\n";
        $report .= "Generated: " . $this->time_manager->format_user_time(current_time('mysql')) . "\n";
        $report .= "Status: " . strtoupper($diagnostics['summary']['status']) . "\n";
        $report .= "Issues: " . $diagnostics['summary']['issues'] . "\n";
        $report .= "Warnings: " . $diagnostics['summary']['warnings'] . "\n\n";
        
        foreach ($diagnostics['checks'] as $check_name => $check_data) {
            $report .= strtoupper($check_name) . " CHECK\n";
            $report .= str_repeat('-', strlen($check_name) + 6) . "\n";
            $report .= "Status: " . $check_data['status'] . "\n";
            
            if (!empty($check_data['issues'])) {
                $report .= "\nIssues:\n";
                foreach ($check_data['issues'] as $issue) {
                    $report .= "- " . $issue . "\n";
                }
            }
            
            if (!empty($check_data['warnings'])) {
                $report .= "\nWarnings:\n";
                foreach ($check_data['warnings'] as $warning) {
                    $report .= "- " . $warning . "\n";
                }
            }
            
            $report .= "\n";
        }
        
        return $report;
    }
    
    /**
     * AJAX handlers
     */
    public function ajax_run_diagnostics() {
        check_ajax_referer('spiralengine_diagnostics', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'spiralengine'));
        }
        
        $diagnostics = $this->run_full_diagnostics();
        wp_send_json_success($diagnostics);
    }
    
    public function ajax_check_system_health() {
        check_ajax_referer('spiralengine_diagnostics', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'spiralengine'));
        }
        
        $health = array(
            'system' => $this->check_system_requirements(),
            'database' => $this->check_database_health(),
            'filesystem' => $this->check_filesystem_health()
        );
        
        wp_send_json_success($health);
    }
    
    public function ajax_detect_conflicts() {
        check_ajax_referer('spiralengine_diagnostics', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'spiralengine'));
        }
        
        $conflicts = $this->detect_plugin_conflicts();
        wp_send_json_success($conflicts);
    }
    
    public function ajax_view_error_log() {
        check_ajax_referer('spiralengine_diagnostics', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'spiralengine'));
        }
        
        global $wpdb;
        
        $page = intval($_POST['page'] ?? 1);
        $per_page = 50;
        $offset = ($page - 1) * $per_page;
        
        $errors = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}spiralengine_error_log 
             ORDER BY created_at DESC 
             LIMIT %d OFFSET %d",
            $per_page,
            $offset
        ));
        
        $total = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}spiralengine_error_log");
        
        // Format times for display
        foreach ($errors as &$error) {
            $error->created_at_display = $this->time_manager->format_user_time($error->created_at);
            $error->error_data = json_decode($error->error_data, true);
        }
        
        wp_send_json_success(array(
            'errors' => $errors,
            'total' => $total,
            'pages' => ceil($total / $per_page),
            'current_page' => $page
        ));
    }
    
    public function ajax_clear_error_log() {
        check_ajax_referer('spiralengine_diagnostics', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'spiralengine'));
        }
        
        global $wpdb;
        
        $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}spiralengine_error_log");
        
        wp_send_json_success(array(
            'message' => __('Error log cleared successfully', 'spiralengine')
        ));
    }
    
    public function ajax_export_diagnostics() {
        check_ajax_referer('spiralengine_diagnostics', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'spiralengine'));
        }
        
        $report = $this->generate_diagnostics_report();
        
        // Generate filename
        $filename = 'spiralengine-diagnostics-' . date('Y-m-d-His') . '.txt';
        
        // Send headers
        header('Content-Type: text/plain');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($report));
        
        echo $report;
        exit;
    }
    
    public function ajax_test_component() {
        check_ajax_referer('spiralengine_diagnostics', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'spiralengine'));
        }
        
        $component = sanitize_text_field($_POST['component']);
        
        switch ($component) {
            case 'database':
                $result = $this->test_database_operations();
                break;
                
            case 'filesystem':
                $result = $this->test_filesystem_operations();
                break;
                
            case 'api':
                $result = $this->test_api_operations();
                break;
                
            case 'encryption':
                $result = $this->test_encryption_operations();
                break;
                
            default:
                $result = array('success' => false, 'message' => __('Unknown component', 'spiralengine'));
        }
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result['message']);
        }
    }
    
    /**
     * Helper methods
     */
    private function parse_size($size) {
        $unit = preg_replace('/[^bkmgtpezy]/i', '', $size);
        $size = preg_replace('/[^0-9\.]/', '', $size);
        
        if ($unit) {
            return round($size * pow(1024, stripos('bkmgtpezy', $unit[0])));
        } else {
            return round($size);
        }
    }
    
    private function test_rest_api() {
        $response = wp_remote_get(get_rest_url(null, 'spiralengine/v1/test'));
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $status = wp_remote_retrieve_response_code($response);
        return $status === 200 || $status === 404; // 404 is ok if endpoint doesn't exist yet
    }
    
    private function test_openai_api() {
        $api_key = get_option('spiralengine_openai_api_key');
        
        if (empty($api_key)) {
            return array(
                'connected' => false,
                'error' => __('API key not configured', 'spiralengine')
            );
        }
        
        // Decrypt API key if encrypted
        if (strpos($api_key, 'enc:') === 0) {
            $encryption = new SPIRALENGINE_Flexible_Encryption();
            $api_key = $encryption->decrypt(substr($api_key, 4));
        }
        
        $response = wp_remote_get('https://api.openai.com/v1/models', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key
            ),
            'timeout' => 10
        ));
        
        if (is_wp_error($response)) {
            return array(
                'connected' => false,
                'error' => $response->get_error_message()
            );
        }
        
        $status = wp_remote_retrieve_response_code($response);
        
        if ($status === 200) {
            return array(
                'connected' => true,
                'response_time' => 0 // Could measure actual time
            );
        } else {
            return array(
                'connected' => false,
                'error' => sprintf(__('API returned status %d', 'spiralengine'), $status)
            );
        }
    }
    
    private function log_diagnostics_run($diagnostics) {
        global $wpdb;
        
        $wpdb->insert(
            $wpdb->prefix . 'spiralengine_diagnostics_log',
            array(
                'status' => $diagnostics['summary']['status'],
                'issues' => $diagnostics['summary']['issues'],
                'warnings' => $diagnostics['summary']['warnings'],
                'data' => json_encode($diagnostics),
                'created_at' => current_time('mysql')
            ),
            array('%s', '%d', '%d', '%s', '%s')
        );
    }
    
    private function log_to_file($error_type, $error_message, $error_data) {
        $log_file = SPIRALENGINE_PLUGIN_DIR . 'logs/error.log';
        
        $log_entry = sprintf(
            "[%s] %s: %s | Data: %s\n",
            current_time('mysql'),
            $error_type,
            $error_message,
            json_encode($error_data)
        );
        
        error_log($log_entry, 3, $log_file);
    }
}

<?php
// includes/class-spiralengine-cron.php

/**
 * Spiral Engine Cron Manager
 * 
 * Handles all scheduled tasks including daily maintenance, weekly analysis,
 * monthly cleanup, and custom task management.
 *
 * @package    Spiral_Engine
 * @subpackage Spiral_Engine/includes
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class SPIRALENGINE_Cron {
    
    /**
     * Time manager instance
     */
    private $time_manager;
    
    /**
     * Registered cron jobs
     */
    private $cron_jobs = array();
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->time_manager = new SPIRALENGINE_Time_Zone_Manager();
        $this->register_cron_jobs();
        $this->init_hooks();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Register custom schedules
        add_filter('cron_schedules', array($this, 'add_custom_schedules'));
        
        // Schedule events on activation
        register_activation_hook(SPIRALENGINE_PLUGIN_FILE, array($this, 'schedule_events'));
        
        // Clear events on deactivation
        register_deactivation_hook(SPIRALENGINE_PLUGIN_FILE, array($this, 'clear_scheduled_events'));
        
        // Register cron actions
        $this->register_cron_actions();
        
        // Admin actions
        add_action('wp_ajax_spiralengine_run_cron_job', array($this, 'ajax_run_cron_job'));
        add_action('wp_ajax_spiralengine_get_cron_status', array($this, 'ajax_get_cron_status'));
        add_action('wp_ajax_spiralengine_reschedule_cron', array($this, 'ajax_reschedule_cron'));
    }
    
    /**
     * Register cron jobs
     */
    private function register_cron_jobs() {
        $this->cron_jobs = array(
            // Daily tasks
            'spiralengine_daily_maintenance' => array(
                'hook' => 'spiralengine_daily_maintenance',
                'schedule' => 'daily',
                'time' => '02:00',
                'callback' => array($this, 'run_daily_maintenance'),
                'description' => __('Daily maintenance tasks', 'spiralengine')
            ),
            
            'spiralengine_daily_cache_clear' => array(
                'hook' => 'spiralengine_daily_cache_clear',
                'schedule' => 'daily',
                'time' => '02:00',
                'callback' => array($this, 'run_cache_clear'),
                'description' => __('Clear expired cache', 'spiralengine')
            ),
            
            'spiralengine_daily_log_rotation' => array(
                'hook' => 'spiralengine_daily_log_rotation',
                'schedule' => 'daily',
                'time' => '01:00',
                'callback' => array($this, 'run_log_rotation'),
                'description' => __('Rotate system logs', 'spiralengine')
            ),
            
            // Weekly tasks
            'spiralengine_weekly_analysis' => array(
                'hook' => 'spiralengine_weekly_analysis',
                'schedule' => 'weekly',
                'time' => '03:00',
                'day' => 'sunday',
                'callback' => array($this, 'run_weekly_analysis'),
                'description' => __('Weekly data analysis', 'spiralengine')
            ),
            
            'spiralengine_weekly_orphan_cleanup' => array(
                'hook' => 'spiralengine_weekly_orphan_cleanup',
                'schedule' => 'weekly',
                'time' => '03:00',
                'day' => 'sunday',
                'callback' => array($this, 'run_orphan_cleanup'),
                'description' => __('Clean orphaned data', 'spiralengine')
            ),
            
            'spiralengine_weekly_optimization' => array(
                'hook' => 'spiralengine_weekly_optimization',
                'schedule' => 'weekly',
                'time' => '03:00',
                'day' => 'sunday',
                'callback' => array($this, 'run_database_optimization'),
                'description' => __('Optimize database tables', 'spiralengine')
            ),
            
            // Monthly tasks
            'spiralengine_monthly_cleanup' => array(
                'hook' => 'spiralengine_monthly_cleanup',
                'schedule' => 'monthly',
                'time' => '03:00',
                'day' => 1,
                'callback' => array($this, 'run_monthly_cleanup'),
                'description' => __('Monthly data cleanup', 'spiralengine')
            ),
            
            'spiralengine_monthly_report' => array(
                'hook' => 'spiralengine_monthly_report',
                'schedule' => 'monthly',
                'time' => '08:00',
                'day' => 1,
                'callback' => array($this, 'generate_monthly_report'),
                'description' => __('Generate monthly reports', 'spiralengine')
            ),
            
            'spiralengine_monthly_index_rebuild' => array(
                'hook' => 'spiralengine_monthly_index_rebuild',
                'schedule' => 'monthly',
                'time' => '03:00',
                'day' => 1,
                'callback' => array($this, 'rebuild_indexes'),
                'description' => __('Rebuild database indexes', 'spiralengine')
            ),
            
            // Hourly tasks
            'spiralengine_hourly_health_check' => array(
                'hook' => 'spiralengine_hourly_health_check',
                'schedule' => 'hourly',
                'callback' => array($this, 'run_health_check'),
                'description' => __('System health check', 'spiralengine')
            ),
            
            'spiralengine_hourly_queue_processor' => array(
                'hook' => 'spiralengine_hourly_queue_processor',
                'schedule' => 'hourly',
                'callback' => array($this, 'process_queues'),
                'description' => __('Process background queues', 'spiralengine')
            ),
            
            // Custom schedules
            'spiralengine_ai_token_refresh' => array(
                'hook' => 'spiralengine_ai_token_refresh',
                'schedule' => 'spiralengine_every_6_hours',
                'callback' => array($this, 'refresh_ai_tokens'),
                'description' => __('Refresh AI token pools', 'spiralengine')
            ),
            
            'spiralengine_pattern_analysis' => array(
                'hook' => 'spiralengine_pattern_analysis',
                'schedule' => 'spiralengine_every_12_hours',
                'callback' => array($this, 'run_pattern_analysis'),
                'description' => __('Analyze user patterns', 'spiralengine')
            )
        );
    }
    
    /**
     * Add custom schedules
     */
    public function add_custom_schedules($schedules) {
        $schedules['spiralengine_every_6_hours'] = array(
            'interval' => 6 * HOUR_IN_SECONDS,
            'display' => __('Every 6 hours', 'spiralengine')
        );
        
        $schedules['spiralengine_every_12_hours'] = array(
            'interval' => 12 * HOUR_IN_SECONDS,
            'display' => __('Every 12 hours', 'spiralengine')
        );
        
        $schedules['spiralengine_every_15_minutes'] = array(
            'interval' => 15 * MINUTE_IN_SECONDS,
            'display' => __('Every 15 minutes', 'spiralengine')
        );
        
        $schedules['spiralengine_every_30_minutes'] = array(
            'interval' => 30 * MINUTE_IN_SECONDS,
            'display' => __('Every 30 minutes', 'spiralengine')
        );
        
        return $schedules;
    }
    
    /**
     * Register cron actions
     */
    private function register_cron_actions() {
        foreach ($this->cron_jobs as $job) {
            if (isset($job['callback']) && is_callable($job['callback'])) {
                add_action($job['hook'], $job['callback']);
            }
        }
    }
    
    /**
     * Schedule events
     */
    public function schedule_events() {
        foreach ($this->cron_jobs as $job) {
            // Clear existing schedule
            wp_clear_scheduled_hook($job['hook']);
            
            // Get first run time
            $timestamp = $this->get_next_scheduled_time($job);
            
            // Schedule event
            if ($timestamp) {
                wp_schedule_event($timestamp, $job['schedule'], $job['hook']);
            }
        }
    }
    
    /**
     * Clear scheduled events
     */
    public function clear_scheduled_events() {
        foreach ($this->cron_jobs as $job) {
            wp_clear_scheduled_hook($job['hook']);
        }
    }
    
    /**
     * Get next scheduled time
     */
    private function get_next_scheduled_time($job) {
        $schedule = $job['schedule'];
        $time = $job['time'] ?? '00:00';
        
        // Convert time to UTC
        $user_timezone = wp_timezone_string();
        $scheduled_time = new DateTime($time, new DateTimeZone($user_timezone));
        $scheduled_time->setTimezone(new DateTimeZone('UTC'));
        
        $now = new DateTime('now', new DateTimeZone('UTC'));
        
        switch ($schedule) {
            case 'hourly':
                return strtotime('+1 hour', floor(time() / 3600) * 3600);
                
            case 'daily':
                $scheduled_time->setDate($now->format('Y'), $now->format('m'), $now->format('d'));
                if ($scheduled_time <= $now) {
                    $scheduled_time->modify('+1 day');
                }
                return $scheduled_time->getTimestamp();
                
            case 'weekly':
                $day = $job['day'] ?? 'sunday';
                $scheduled_time->modify('next ' . $day);
                return $scheduled_time->getTimestamp();
                
            case 'monthly':
                $day = $job['day'] ?? 1;
                $scheduled_time->setDate($now->format('Y'), $now->format('m'), $day);
                if ($scheduled_time <= $now) {
                    $scheduled_time->modify('+1 month');
                }
                return $scheduled_time->getTimestamp();
                
            default:
                // For custom schedules, run immediately then follow the interval
                return time() + 60; // Start in 1 minute
        }
    }
    
    /**
     * Daily maintenance tasks
     */
    public function run_daily_maintenance() {
        $start_time = microtime(true);
        $tasks_completed = array();
        
        try {
            // Clean temporary files
            $this->clean_temp_files();
            $tasks_completed[] = 'temp_files_cleaned';
            
            // Update statistics
            $this->update_daily_statistics();
            $tasks_completed[] = 'statistics_updated';
            
            // Process data retention
            $this->process_data_retention();
            $tasks_completed[] = 'data_retention_processed';
            
            // Check for updates
            $this->check_plugin_updates();
            $tasks_completed[] = 'updates_checked';
            
            // Run custom daily tasks
            do_action('spiralengine_daily_maintenance_tasks');
            
            // Log success
            $this->log_cron_execution('daily_maintenance', 'success', array(
                'tasks_completed' => $tasks_completed,
                'execution_time' => microtime(true) - $start_time
            ));
            
        } catch (Exception $e) {
            $this->log_cron_execution('daily_maintenance', 'error', array(
                'error' => $e->getMessage(),
                'tasks_completed' => $tasks_completed
            ));
        }
    }
    
    /**
     * Cache clearing
     */
    public function run_cache_clear() {
        global $wpdb;
        
        try {
            // Clear expired transients
            $wpdb->query(
                "DELETE FROM {$wpdb->options} 
                 WHERE option_name LIKE '_transient_timeout_spiralengine_%' 
                 AND option_value < " . time()
            );
            
            $wpdb->query(
                "DELETE FROM {$wpdb->options} 
                 WHERE option_name LIKE '_transient_spiralengine_%' 
                 AND option_name NOT IN (
                     SELECT CONCAT('_transient_', SUBSTRING(option_name, 19)) 
                     FROM (SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_spiralengine_%') AS t
                 )"
            );
            
            // Clear object cache
            wp_cache_flush();
            
            // Clear custom caches
            $this->clear_pattern_cache();
            $this->clear_ai_cache();
            
            $this->log_cron_execution('cache_clear', 'success');
            
        } catch (Exception $e) {
            $this->log_cron_execution('cache_clear', 'error', array('error' => $e->getMessage()));
        }
    }
    
    /**
     * Log rotation
     */
    public function run_log_rotation() {
        $log_dir = SPIRALENGINE_PLUGIN_DIR . 'logs/';
        $retention_days = get_option('spiralengine_log_retention_days', 30);
        
        try {
            // Rotate main log files
            $log_files = array('debug.log', 'error.log', 'cron.log', 'security.log');
            
            foreach ($log_files as $log_file) {
                $file_path = $log_dir . $log_file;
                
                if (file_exists($file_path) && filesize($file_path) > 10 * MB_IN_BYTES) {
                    // Archive current log
                    $archive_name = $log_dir . date('Y-m-d-His') . '-' . $log_file;
                    rename($file_path, $archive_name);
                    
                    // Compress if possible
                    if (function_exists('gzcompress')) {
                        $this->compress_file($archive_name);
                    }
                }
            }
            
            // Clean old logs
            $this->clean_old_logs($log_dir, $retention_days);
            
            $this->log_cron_execution('log_rotation', 'success');
            
        } catch (Exception $e) {
            $this->log_cron_execution('log_rotation', 'error', array('error' => $e->getMessage()));
        }
    }
    
    /**
     * Weekly analysis
     */
    public function run_weekly_analysis() {
        global $wpdb;
        
        try {
            $analysis_data = array();
            
            // User activity analysis
            $user_activity = $wpdb->get_results(
                "SELECT 
                    COUNT(DISTINCT user_id) as active_users,
                    COUNT(*) as total_activities,
                    AVG(activity_count) as avg_activities_per_user
                 FROM (
                     SELECT user_id, COUNT(*) as activity_count
                     FROM {$wpdb->prefix}spiralengine_user_activity
                     WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 WEEK)
                     GROUP BY user_id
                 ) as user_counts"
            );
            
            $analysis_data['user_activity'] = $user_activity[0];
            
            // Episode pattern analysis
            $episode_patterns = $this->analyze_episode_patterns();
            $analysis_data['episode_patterns'] = $episode_patterns;
            
            // Performance metrics
            $performance = $this->analyze_performance_metrics();
            $analysis_data['performance'] = $performance;
            
            // Store analysis results
            update_option('spiralengine_weekly_analysis_' . date('Y_W'), $analysis_data);
            
            // Send admin notification if enabled
            if (get_option('spiralengine_weekly_report_email', true)) {
                $this->send_weekly_report($analysis_data);
            }
            
            $this->log_cron_execution('weekly_analysis', 'success', $analysis_data);
            
        } catch (Exception $e) {
            $this->log_cron_execution('weekly_analysis', 'error', array('error' => $e->getMessage()));
        }
    }
    
    /**
     * Orphan cleanup
     */
    public function run_orphan_cleanup() {
        global $wpdb;
        
        try {
            $cleaned = array();
            
            // Clean orphaned user meta
            $orphaned_meta = $wpdb->query(
                "DELETE um FROM {$wpdb->usermeta} um
                 LEFT JOIN {$wpdb->users} u ON um.user_id = u.ID
                 WHERE u.ID IS NULL AND um.meta_key LIKE 'spiralengine_%'"
            );
            
            $cleaned['user_meta'] = $orphaned_meta;
            
            // Clean orphaned assessment data
            $orphaned_assessments = $wpdb->query(
                "DELETE a FROM {$wpdb->prefix}spiralengine_assessments a
                 LEFT JOIN {$wpdb->users} u ON a.user_id = u.ID
                 WHERE u.ID IS NULL"
            );
            
            $cleaned['assessments'] = $orphaned_assessments;
            
            // Clean orphaned episode data
            $episode_tables = array(
                'spiralengine_episodes_overthinking',
                'spiralengine_episodes_anxiety',
                'spiralengine_episodes_depression',
                'spiralengine_episodes_ptsd'
            );
            
            foreach ($episode_tables as $table) {
                if ($wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}$table'")) {
                    $orphaned = $wpdb->query(
                        "DELETE e FROM {$wpdb->prefix}$table e
                         LEFT JOIN {$wpdb->users} u ON e.user_id = u.ID
                         WHERE u.ID IS NULL"
                    );
                    
                    $cleaned[$table] = $orphaned;
                }
            }
            
            // Clean orphaned files
            $this->clean_orphaned_files();
            
            $this->log_cron_execution('orphan_cleanup', 'success', $cleaned);
            
        } catch (Exception $e) {
            $this->log_cron_execution('orphan_cleanup', 'error', array('error' => $e->getMessage()));
        }
    }
    
    /**
     * Database optimization
     */
    public function run_database_optimization() {
        global $wpdb;
        
        try {
            $optimized_tables = array();
            
            // Get all SPIRAL Engine tables
            $tables = $wpdb->get_col(
                "SELECT table_name 
                 FROM information_schema.tables 
                 WHERE table_schema = '" . DB_NAME . "' 
                 AND table_name LIKE '{$wpdb->prefix}spiralengine_%'"
            );
            
            foreach ($tables as $table) {
                // Optimize table
                $wpdb->query("OPTIMIZE TABLE $table");
                
                // Analyze table
                $wpdb->query("ANALYZE TABLE $table");
                
                $optimized_tables[] = $table;
            }
            
            // Update optimization timestamp
            update_option('spiralengine_last_optimization', current_time('mysql'));
            
            $this->log_cron_execution('database_optimization', 'success', array(
                'tables_optimized' => count($optimized_tables),
                'tables' => $optimized_tables
            ));
            
        } catch (Exception $e) {
            $this->log_cron_execution('database_optimization', 'error', array('error' => $e->getMessage()));
        }
    }
    
    /**
     * Monthly cleanup
     */
    public function run_monthly_cleanup() {
        global $wpdb;
        
        try {
            $cleanup_stats = array();
            
            // Clean old export files
            $exports_cleaned = $this->clean_old_exports();
            $cleanup_stats['exports'] = $exports_cleaned;
            
            // Clean old backup files based on retention policy
            $backups_cleaned = $this->clean_old_backups();
            $cleanup_stats['backups'] = $backups_cleaned;
            
            // Clean old log entries
            $logs_cleaned = $this->clean_old_database_logs();
            $cleanup_stats['logs'] = $logs_cleaned;
            
            // Clean expired AI cache
            $ai_cache_cleaned = $this->clean_expired_ai_cache();
            $cleanup_stats['ai_cache'] = $ai_cache_cleaned;
            
            // Clean old analytics data
            $analytics_cleaned = $this->clean_old_analytics();
            $cleanup_stats['analytics'] = $analytics_cleaned;
            
            // Run custom monthly cleanup
            do_action('spiralengine_monthly_cleanup', $cleanup_stats);
            
            $this->log_cron_execution('monthly_cleanup', 'success', $cleanup_stats);
            
        } catch (Exception $e) {
            $this->log_cron_execution('monthly_cleanup', 'error', array('error' => $e->getMessage()));
        }
    }
    
    /**
     * Generate monthly report
     */
    public function generate_monthly_report() {
        try {
            $report_data = array(
                'period' => date('F Y', strtotime('-1 month')),
                'generated_at' => current_time('mysql'),
                'metrics' => array()
            );
            
            // Gather metrics
            $report_data['metrics']['users'] = $this->get_user_metrics();
            $report_data['metrics']['episodes'] = $this->get_episode_metrics();
            $report_data['metrics']['assessments'] = $this->get_assessment_metrics();
            $report_data['metrics']['ai_usage'] = $this->get_ai_usage_metrics();
            $report_data['metrics']['system'] = $this->get_system_metrics();
            
            // Generate report
            $report_generator = new SPIRALENGINE_Report_Generator();
            $report_file = $report_generator->generate_monthly_report($report_data);
            
            // Send to administrators
            $this->send_monthly_report_email($report_file, $report_data);
            
            // Store report reference
            $this->store_report_reference($report_file, $report_data);
            
            $this->log_cron_execution('monthly_report', 'success', array(
                'report_file' => $report_file
            ));
            
        } catch (Exception $e) {
            $this->log_cron_execution('monthly_report', 'error', array('error' => $e->getMessage()));
        }
    }
    
    /**
     * Rebuild indexes
     */
    public function rebuild_indexes() {
        global $wpdb;
        
        try {
            $indexes_rebuilt = array();
            
            // Define indexes to check/rebuild
            $indexes = array(
                array(
                    'table' => 'spiralengine_user_activity',
                    'index' => 'idx_user_created',
                    'columns' => 'user_id, created_at'
                ),
                array(
                    'table' => 'spiralengine_assessments',
                    'index' => 'idx_user_type_created',
                    'columns' => 'user_id, assessment_type, created_at'
                ),
                array(
                    'table' => 'spiralengine_ai_logs',
                    'index' => 'idx_user_model_created',
                    'columns' => 'user_id, model, created_at'
                )
            );
            
            foreach ($indexes as $index_info) {
                $table = $wpdb->prefix . $index_info['table'];
                
                // Check if table exists
                if ($wpdb->get_var("SHOW TABLES LIKE '$table'")) {
                    // Drop and recreate index
                    $wpdb->query("ALTER TABLE $table DROP INDEX IF EXISTS {$index_info['index']}");
                    $wpdb->query("ALTER TABLE $table ADD INDEX {$index_info['index']} ({$index_info['columns']})");
                    
                    $indexes_rebuilt[] = $index_info['index'];
                }
            }
            
            $this->log_cron_execution('index_rebuild', 'success', array(
                'indexes_rebuilt' => $indexes_rebuilt
            ));
            
        } catch (Exception $e) {
            $this->log_cron_execution('index_rebuild', 'error', array('error' => $e->getMessage()));
        }
    }
    
    /**
     * Health check
     */
    public function run_health_check() {
        try {
            $health_status = array(
                'timestamp' => current_time('mysql'),
                'checks' => array()
            );
            
            // Database connectivity
            $health_status['checks']['database'] = $this->check_database_health();
            
            // File system
            $health_status['checks']['filesystem'] = $this->check_filesystem_health();
            
            // API connectivity
            $health_status['checks']['api'] = $this->check_api_health();
            
            // Memory usage
            $health_status['checks']['memory'] = $this->check_memory_health();
            
            // Plugin conflicts
            $health_status['checks']['conflicts'] = $this->check_plugin_conflicts();
            
            // Store health status
            set_transient('spiralengine_health_status', $health_status, HOUR_IN_SECONDS);
            
            // Alert if critical issues
            $this->alert_critical_issues($health_status);
            
            $this->log_cron_execution('health_check', 'success', $health_status);
            
        } catch (Exception $e) {
            $this->log_cron_execution('health_check', 'error', array('error' => $e->getMessage()));
        }
    }
    
    /**
     * Process queues
     */
    public function process_queues() {
        try {
            $queues_processed = array();
            
            // Email queue
            $emails_sent = $this->process_email_queue();
            $queues_processed['emails'] = $emails_sent;
            
            // Export queue
            $exports_processed = $this->process_export_queue();
            $queues_processed['exports'] = $exports_processed;
            
            // AI processing queue
            $ai_processed = $this->process_ai_queue();
            $queues_processed['ai'] = $ai_processed;
            
            // Notification queue
            $notifications_sent = $this->process_notification_queue();
            $queues_processed['notifications'] = $notifications_sent;
            
            $this->log_cron_execution('queue_processor', 'success', $queues_processed);
            
        } catch (Exception $e) {
            $this->log_cron_execution('queue_processor', 'error', array('error' => $e->getMessage()));
        }
    }
    
    /**
     * Get cron status
     */
    public function get_cron_status() {
        $status = array();
        
        foreach ($this->cron_jobs as $job_id => $job) {
            $next_run = wp_next_scheduled($job['hook']);
            $last_run = get_option('spiralengine_cron_last_run_' . $job_id, 0);
            
            $status[$job_id] = array(
                'hook' => $job['hook'],
                'schedule' => $job['schedule'],
                'description' => $job['description'],
                'next_run' => $next_run,
                'next_run_display' => $next_run ? $this->time_manager->format_user_time(gmdate('Y-m-d H:i:s', $next_run)) : __('Not scheduled', 'spiralengine'),
                'last_run' => $last_run,
                'last_run_display' => $last_run ? $this->time_manager->format_user_time($last_run) : __('Never', 'spiralengine'),
                'is_running' => get_transient('spiralengine_cron_running_' . $job_id),
                'last_status' => get_option('spiralengine_cron_last_status_' . $job_id, 'unknown')
            );
        }
        
        return $status;
    }
    
    /**
     * AJAX handlers
     */
    public function ajax_run_cron_job() {
        check_ajax_referer('spiralengine_cron', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'spiralengine'));
        }
        
        $job_id = sanitize_text_field($_POST['job_id']);
        
        if (!isset($this->cron_jobs[$job_id])) {
            wp_send_json_error(__('Invalid cron job', 'spiralengine'));
        }
        
        $job = $this->cron_jobs[$job_id];
        
        // Mark as running
        set_transient('spiralengine_cron_running_' . $job_id, true, HOUR_IN_SECONDS);
        
        try {
            // Run the job
            if (is_callable($job['callback'])) {
                call_user_func($job['callback']);
            }
            
            // Update last run time
            update_option('spiralengine_cron_last_run_' . $job_id, current_time('mysql'));
            update_option('spiralengine_cron_last_status_' . $job_id, 'success');
            
            delete_transient('spiralengine_cron_running_' . $job_id);
            
            wp_send_json_success(array(
                'message' => sprintf(__('Cron job "%s" executed successfully', 'spiralengine'), $job['description'])
            ));
            
        } catch (Exception $e) {
            update_option('spiralengine_cron_last_status_' . $job_id, 'error');
            delete_transient('spiralengine_cron_running_' . $job_id);
            
            wp_send_json_error($e->getMessage());
        }
    }
    
    public function ajax_get_cron_status() {
        check_ajax_referer('spiralengine_cron', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'spiralengine'));
        }
        
        $status = $this->get_cron_status();
        wp_send_json_success($status);
    }
    
    public function ajax_reschedule_cron() {
        check_ajax_referer('spiralengine_cron', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'spiralengine'));
        }
        
        $job_id = sanitize_text_field($_POST['job_id']);
        
        if (!isset($this->cron_jobs[$job_id])) {
            wp_send_json_error(__('Invalid cron job', 'spiralengine'));
        }
        
        $job = $this->cron_jobs[$job_id];
        
        // Clear existing schedule
        wp_clear_scheduled_hook($job['hook']);
        
        // Reschedule
        $timestamp = $this->get_next_scheduled_time($job);
        
        if ($timestamp && wp_schedule_event($timestamp, $job['schedule'], $job['hook'])) {
            wp_send_json_success(array(
                'message' => sprintf(__('Cron job "%s" rescheduled successfully', 'spiralengine'), $job['description']),
                'next_run' => $this->time_manager->format_user_time(gmdate('Y-m-d H:i:s', $timestamp))
            ));
        } else {
            wp_send_json_error(__('Failed to reschedule cron job', 'spiralengine'));
        }
    }
    
    /**
     * Helper methods
     */
    private function log_cron_execution($job_name, $status, $data = array()) {
        global $wpdb;
        
        $wpdb->insert(
            $wpdb->prefix . 'spiralengine_cron_log',
            array(
                'job_name' => $job_name,
                'status' => $status,
                'execution_time' => isset($data['execution_time']) ? $data['execution_time'] : 0,
                'data' => json_encode($data),
                'created_at' => current_time('mysql')
            ),
            array('%s', '%s', '%f', '%s', '%s')
        );
        
        // Update last run info
        update_option('spiralengine_cron_last_run_' . $job_name, current_time('mysql'));
        update_option('spiralengine_cron_last_status_' . $job_name, $status);
    }
    
    private function clean_temp_files() {
        $temp_dir = wp_upload_dir()['basedir'] . '/spiralengine-temp/';
        
        if (!is_dir($temp_dir)) {
            return;
        }
        
        $files = glob($temp_dir . '*');
        $now = time();
        
        foreach ($files as $file) {
            if (is_file($file)) {
                // Delete files older than 24 hours
                if ($now - filemtime($file) >= 24 * 3600) {
                    unlink($file);
                }
            }
        }
    }
    
    private function check_database_health() {
        global $wpdb;
        
        $health = array(
            'status' => 'healthy',
            'issues' => array()
        );
        
        // Check connection
        if (!$wpdb->check_connection()) {
            $health['status'] = 'critical';
            $health['issues'][] = __('Database connection lost', 'spiralengine');
            return $health;
        }
        
        // Check table status
        $tables = $wpdb->get_results("SHOW TABLE STATUS LIKE '{$wpdb->prefix}spiralengine_%'");
        
        foreach ($tables as $table) {
            if ($table->Engine !== 'InnoDB') {
                $health['status'] = 'warning';
                $health['issues'][] = sprintf(__('Table %s is not using InnoDB engine', 'spiralengine'), $table->Name);
            }
        }
        
        return $health;
    }
    
    private function process_data_retention() {
        $retention_settings = get_option('spiralengine_data_retention', array());
        
        foreach ($retention_settings as $data_type => $days) {
            if ($days > 0) {
                $this->apply_retention_policy($data_type, $days);
            }
        }
    }
    
    private function apply_retention_policy($data_type, $days) {
        global $wpdb;
        
        $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        switch ($data_type) {
            case 'assessments':
                $wpdb->query($wpdb->prepare(
                    "DELETE FROM {$wpdb->prefix}spiralengine_assessments 
                     WHERE created_at < %s AND is_archived = 1",
                    $cutoff_date
                ));
                break;
                
            case 'analytics':
                $wpdb->query($wpdb->prepare(
                    "DELETE FROM {$wpdb->prefix}spiralengine_analytics 
                     WHERE created_at < %s",
                    $cutoff_date
                ));
                break;
                
            case 'ai_logs':
                $wpdb->query($wpdb->prepare(
                    "DELETE FROM {$wpdb->prefix}spiralengine_ai_logs 
                     WHERE created_at < %s",
                    $cutoff_date
                ));
                break;
        }
    }
}

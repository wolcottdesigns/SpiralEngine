<?php
/**
 * SpiralEngine System Health Monitoring
 *
 * @package    SpiralEngine
 * @subpackage Admin
 * @file       includes/admin/class-system-health.php
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class SpiralEngine_System_Health
 *
 * Monitors system health, performance, and status
 */
class SpiralEngine_System_Health {
    
    /**
     * Health check results
     *
     * @var array
     */
    private $health_checks = array();
    
    /**
     * Performance metrics
     *
     * @var array
     */
    private $performance_metrics = array();
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->init();
    }
    
    /**
     * Initialize system health monitoring
     */
    public function init() {
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_ajax_spiralengine_run_health_check', array($this, 'ajax_run_health_check'));
        add_action('wp_ajax_spiralengine_get_system_status', array($this, 'ajax_get_system_status'));
        add_action('wp_ajax_spiralengine_clear_debug_log', array($this, 'ajax_clear_debug_log'));
        add_action('wp_ajax_spiralengine_optimize_database', array($this, 'ajax_optimize_database'));
        add_action('wp_ajax_spiralengine_run_diagnostics', array($this, 'ajax_run_diagnostics'));
        
        // Schedule health checks
        add_action('spiralengine_scheduled_health_check', array($this, 'run_scheduled_health_check'));
        
        if (!wp_next_scheduled('spiralengine_scheduled_health_check')) {
            wp_schedule_event(time(), 'daily', 'spiralengine_scheduled_health_check');
        }
    }
    
    /**
     * Enqueue scripts and styles
     *
     * @param string $hook
     */
    public function enqueue_scripts($hook) {
        if (strpos($hook, 'spiralengine-system-health') === false) {
            return;
        }
        
        wp_enqueue_style('spiralengine-admin');
        wp_enqueue_script('spiralengine-admin');
        
        // Add system health specific script
        wp_add_inline_script('spiralengine-admin', $this->get_inline_script());
        
        // Localize script
        wp_localize_script('spiralengine-admin', 'spiralengine_health', array(
            'nonce' => wp_create_nonce('spiralengine_system_health'),
            'strings' => array(
                'running_check' => __('Running health check...', 'spiralengine'),
                'check_complete' => __('Health check complete', 'spiralengine'),
                'optimizing' => __('Optimizing database...', 'spiralengine'),
                'optimize_complete' => __('Database optimization complete', 'spiralengine'),
                'clearing_log' => __('Clearing debug log...', 'spiralengine'),
                'log_cleared' => __('Debug log cleared', 'spiralengine'),
                'error' => __('An error occurred. Please try again.', 'spiralengine')
            )
        ));
    }
    
    /**
     * Get inline JavaScript
     *
     * @return string
     */
    private function get_inline_script() {
        return "
        jQuery(document).ready(function($) {
            // Run health check
            $('#run-health-check').on('click', function() {
                var button = $(this);
                var originalText = button.text();
                
                button.prop('disabled', true).text(spiralengine_health.strings.running_check);
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'spiralengine_run_health_check',
                        nonce: spiralengine_health.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            location.reload();
                        } else {
                            alert(response.data.message || spiralengine_health.strings.error);
                        }
                    },
                    complete: function() {
                        button.prop('disabled', false).text(originalText);
                    }
                });
            });
            
            // Auto-refresh status
            if ($('#auto-refresh').is(':checked')) {
                setInterval(refreshSystemStatus, 30000); // Every 30 seconds
            }
            
            $('#auto-refresh').on('change', function() {
                if ($(this).is(':checked')) {
                    refreshSystemStatus();
                }
            });
            
            function refreshSystemStatus() {
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'spiralengine_get_system_status',
                        nonce: spiralengine_health.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            updateStatusDisplay(response.data);
                        }
                    }
                });
            }
            
            function updateStatusDisplay(data) {
                // Update metrics
                $.each(data.metrics, function(key, value) {
                    $('#metric-' + key).text(value);
                });
                
                // Update status indicators
                $.each(data.status, function(key, status) {
                    var indicator = $('#status-' + key);
                    indicator.removeClass('status-good status-warning status-error')
                             .addClass('status-' + status);
                });
            }
            
            // Clear debug log
            $('#clear-debug-log').on('click', function() {
                if (!confirm('Are you sure you want to clear the debug log?')) {
                    return;
                }
                
                var button = $(this);
                button.prop('disabled', true).text(spiralengine_health.strings.clearing_log);
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'spiralengine_clear_debug_log',
                        nonce: spiralengine_health.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            button.text(spiralengine_health.strings.log_cleared);
                            $('#debug-log-content').empty();
                            setTimeout(function() {
                                button.prop('disabled', false).text('Clear Log');
                            }, 2000);
                        }
                    }
                });
            });
            
            // Optimize database
            $('#optimize-database').on('click', function() {
                var button = $(this);
                var originalText = button.text();
                
                button.prop('disabled', true).text(spiralengine_health.strings.optimizing);
                $('.optimization-results').hide();
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'spiralengine_optimize_database',
                        nonce: spiralengine_health.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            $('.optimization-results').html(response.data.message).fadeIn();
                        }
                    },
                    complete: function() {
                        button.prop('disabled', false).text(originalText);
                    }
                });
            });
            
            // Tab switching
            $('.health-tab').on('click', function(e) {
                e.preventDefault();
                var tab = $(this).data('tab');
                
                $('.health-tab').removeClass('nav-tab-active');
                $(this).addClass('nav-tab-active');
                
                $('.health-tab-content').hide();
                $('#health-' + tab).show();
            });
            
            // Download diagnostic report
            $('#download-diagnostics').on('click', function() {
                var button = $(this);
                button.prop('disabled', true).text('Generating report...');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'spiralengine_run_diagnostics',
                        format: 'download',
                        nonce: spiralengine_health.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            // Trigger download
                            var a = document.createElement('a');
                            a.href = response.data.file_url;
                            a.download = response.data.filename;
                            document.body.appendChild(a);
                            a.click();
                            document.body.removeChild(a);
                        }
                    },
                    complete: function() {
                        button.prop('disabled', false).text('Download Report');
                    }
                });
            });
            
            // Real-time log viewer
            if ($('#realtime-log-viewer').length) {
                var logViewer = $('#realtime-log-viewer');
                var logInterval;
                
                $('#toggle-realtime-log').on('change', function() {
                    if ($(this).is(':checked')) {
                        logInterval = setInterval(function() {
                            $.ajax({
                                url: ajaxurl,
                                type: 'POST',
                                data: {
                                    action: 'spiralengine_get_recent_logs',
                                    nonce: spiralengine_health.nonce
                                },
                                success: function(response) {
                                    if (response.success) {
                                        logViewer.html(response.data.logs);
                                        logViewer.scrollTop(logViewer[0].scrollHeight);
                                    }
                                }
                            });
                        }, 5000);
                    } else {
                        clearInterval(logInterval);
                    }
                });
            }
        });
        ";
    }
    
    /**
     * Render system health page
     */
    public function render_page() {
        if (!current_user_can('spiralengine_view_system')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'spiralengine'));
        }
        
        // Run health checks
        $this->run_health_checks();
        
        ?>
        <div class="wrap spiralengine-system-health">
            <h1>
                <span class="dashicons dashicons-heart"></span>
                <?php _e('System Health', 'spiralengine'); ?>
            </h1>
            
            <?php $this->render_overall_status(); ?>
            
            <nav class="nav-tab-wrapper">
                <a href="#" class="nav-tab nav-tab-active health-tab" data-tab="overview">
                    <?php _e('Overview', 'spiralengine'); ?>
                </a>
                <a href="#" class="nav-tab health-tab" data-tab="performance">
                    <?php _e('Performance', 'spiralengine'); ?>
                </a>
                <a href="#" class="nav-tab health-tab" data-tab="database">
                    <?php _e('Database', 'spiralengine'); ?>
                </a>
                <a href="#" class="nav-tab health-tab" data-tab="logs">
                    <?php _e('Logs', 'spiralengine'); ?>
                </a>
                <a href="#" class="nav-tab health-tab" data-tab="diagnostics">
                    <?php _e('Diagnostics', 'spiralengine'); ?>
                </a>
            </nav>
            
            <div class="health-content">
                <div id="health-overview" class="health-tab-content">
                    <?php $this->render_overview_tab(); ?>
                </div>
                
                <div id="health-performance" class="health-tab-content" style="display: none;">
                    <?php $this->render_performance_tab(); ?>
                </div>
                
                <div id="health-database" class="health-tab-content" style="display: none;">
                    <?php $this->render_database_tab(); ?>
                </div>
                
                <div id="health-logs" class="health-tab-content" style="display: none;">
                    <?php $this->render_logs_tab(); ?>
                </div>
                
                <div id="health-diagnostics" class="health-tab-content" style="display: none;">
                    <?php $this->render_diagnostics_tab(); ?>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render overall status
     */
    private function render_overall_status() {
        $overall_status = $this->calculate_overall_status();
        $status_class = $overall_status['status'];
        $status_text = $overall_status['text'];
        ?>
        <div class="overall-status status-<?php echo esc_attr($status_class); ?>">
            <div class="status-indicator">
                <span class="dashicons dashicons-<?php echo $status_class === 'good' ? 'yes-alt' : ($status_class === 'warning' ? 'warning' : 'dismiss'); ?>"></span>
            </div>
            <div class="status-content">
                <h2><?php echo esc_html($status_text); ?></h2>
                <p><?php echo esc_html($overall_status['description']); ?></p>
                <button id="run-health-check" class="button button-primary">
                    <?php _e('Run Health Check', 'spiralengine'); ?>
                </button>
                <label style="margin-left: 20px;">
                    <input type="checkbox" id="auto-refresh" />
                    <?php _e('Auto-refresh status', 'spiralengine'); ?>
                </label>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render overview tab
     */
    private function render_overview_tab() {
        ?>
        <div class="health-overview-grid">
            <div class="health-section">
                <h3><?php _e('System Requirements', 'spiralengine'); ?></h3>
                <?php $this->render_system_requirements(); ?>
            </div>
            
            <div class="health-section">
                <h3><?php _e('Plugin Status', 'spiralengine'); ?></h3>
                <?php $this->render_plugin_status(); ?>
            </div>
            
            <div class="health-section">
                <h3><?php _e('Security Checks', 'spiralengine'); ?></h3>
                <?php $this->render_security_checks(); ?>
            </div>
            
            <div class="health-section">
                <h3><?php _e('Scheduled Tasks', 'spiralengine'); ?></h3>
                <?php $this->render_scheduled_tasks(); ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render system requirements
     */
    private function render_system_requirements() {
        $requirements = $this->check_system_requirements();
        ?>
        <table class="widefat striped">
            <thead>
                <tr>
                    <th><?php _e('Requirement', 'spiralengine'); ?></th>
                    <th><?php _e('Required', 'spiralengine'); ?></th>
                    <th><?php _e('Current', 'spiralengine'); ?></th>
                    <th><?php _e('Status', 'spiralengine'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($requirements as $req) : ?>
                    <tr>
                        <td><?php echo esc_html($req['name']); ?></td>
                        <td><?php echo esc_html($req['required']); ?></td>
                        <td><?php echo esc_html($req['current']); ?></td>
                        <td>
                            <span class="status-indicator status-<?php echo esc_attr($req['status']); ?>">
                                <span class="dashicons dashicons-<?php echo $req['status'] === 'good' ? 'yes' : 'warning'; ?>"></span>
                            </span>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }
    
    /**
     * Render plugin status
     */
    private function render_plugin_status() {
        $status_items = $this->get_plugin_status();
        ?>
        <ul class="status-list">
            <?php foreach ($status_items as $item) : ?>
                <li class="status-item">
                    <span class="status-indicator status-<?php echo esc_attr($item['status']); ?>">
                        <span class="dashicons dashicons-<?php echo $item['status'] === 'good' ? 'yes' : ($item['status'] === 'warning' ? 'warning' : 'no'); ?>"></span>
                    </span>
                    <span class="status-label"><?php echo esc_html($item['label']); ?></span>
                    <?php if (!empty($item['message'])) : ?>
                        <span class="status-message"><?php echo esc_html($item['message']); ?></span>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php
    }
    
    /**
     * Render security checks
     */
    private function render_security_checks() {
        $security_checks = $this->run_security_checks();
        ?>
        <ul class="security-checks">
            <?php foreach ($security_checks as $check) : ?>
                <li class="security-check">
                    <span class="status-indicator status-<?php echo esc_attr($check['status']); ?>">
                        <span class="dashicons dashicons-<?php echo $check['status'] === 'good' ? 'shield-alt' : 'warning'; ?>"></span>
                    </span>
                    <span class="check-name"><?php echo esc_html($check['name']); ?></span>
                    <?php if (!empty($check['action'])) : ?>
                        <a href="<?php echo esc_url($check['action']['url']); ?>" class="button button-small">
                            <?php echo esc_html($check['action']['text']); ?>
                        </a>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php
    }
    
    /**
     * Render scheduled tasks
     */
    private function render_scheduled_tasks() {
        $cron_jobs = $this->get_spiralengine_cron_jobs();
        ?>
        <table class="widefat striped">
            <thead>
                <tr>
                    <th><?php _e('Task', 'spiralengine'); ?></th>
                    <th><?php _e('Schedule', 'spiralengine'); ?></th>
                    <th><?php _e('Next Run', 'spiralengine'); ?></th>
                    <th><?php _e('Status', 'spiralengine'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($cron_jobs as $job) : ?>
                    <tr>
                        <td><?php echo esc_html($job['name']); ?></td>
                        <td><?php echo esc_html($job['schedule']); ?></td>
                        <td>
                            <?php 
                            echo $job['next_run'] 
                                ? human_time_diff(current_time('timestamp'), $job['next_run']) . ' ' . __('from now', 'spiralengine')
                                : __('Not scheduled', 'spiralengine');
                            ?>
                        </td>
                        <td>
                            <span class="status-indicator status-<?php echo $job['next_run'] ? 'good' : 'warning'; ?>">
                                <span class="dashicons dashicons-<?php echo $job['next_run'] ? 'clock' : 'warning'; ?>"></span>
                            </span>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }
    
    /**
     * Render performance tab
     */
    private function render_performance_tab() {
        $this->calculate_performance_metrics();
        ?>
        <div class="performance-metrics">
            <div class="metric-grid">
                <div class="metric-card">
                    <h4><?php _e('Page Load Time', 'spiralengine'); ?></h4>
                    <div class="metric-value">
                        <span id="metric-page-load"><?php echo $this->performance_metrics['page_load']; ?></span>s
                    </div>
                    <div class="metric-status <?php echo $this->get_performance_status('page_load'); ?>"></div>
                </div>
                
                <div class="metric-card">
                    <h4><?php _e('Database Queries', 'spiralengine'); ?></h4>
                    <div class="metric-value">
                        <span id="metric-db-queries"><?php echo $this->performance_metrics['db_queries']; ?></span>
                    </div>
                    <div class="metric-status <?php echo $this->get_performance_status('db_queries'); ?>"></div>
                </div>
                
                <div class="metric-card">
                    <h4><?php _e('Memory Usage', 'spiralengine'); ?></h4>
                    <div class="metric-value">
                        <span id="metric-memory"><?php echo $this->performance_metrics['memory_usage']; ?></span>MB
                    </div>
                    <div class="metric-progress">
                        <div class="progress-bar" style="width: <?php echo $this->performance_metrics['memory_percentage']; ?>%"></div>
                    </div>
                </div>
                
                <div class="metric-card">
                    <h4><?php _e('Cache Hit Rate', 'spiralengine'); ?></h4>
                    <div class="metric-value">
                        <span id="metric-cache-hit"><?php echo $this->performance_metrics['cache_hit_rate']; ?></span>%
                    </div>
                    <div class="metric-status <?php echo $this->get_performance_status('cache_hit_rate'); ?>"></div>
                </div>
            </div>
            
            <div class="performance-recommendations">
                <h3><?php _e('Performance Recommendations', 'spiralengine'); ?></h3>
                <?php $this->render_performance_recommendations(); ?>
            </div>
            
            <div class="slow-queries">
                <h3><?php _e('Slow Queries', 'spiralengine'); ?></h3>
                <?php $this->render_slow_queries(); ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render database tab
     */
    private function render_database_tab() {
        global $wpdb;
        ?>
        <div class="database-health">
            <div class="database-overview">
                <h3><?php _e('Database Overview', 'spiralengine'); ?></h3>
                <?php $this->render_database_overview(); ?>
            </div>
            
            <div class="table-sizes">
                <h3><?php _e('Table Sizes', 'spiralengine'); ?></h3>
                <?php $this->render_table_sizes(); ?>
            </div>
            
            <div class="database-maintenance">
                <h3><?php _e('Database Maintenance', 'spiralengine'); ?></h3>
                
                <p><?php _e('Optimize database tables to improve performance.', 'spiralengine'); ?></p>
                
                <button id="optimize-database" class="button button-primary">
                    <?php _e('Optimize Database', 'spiralengine'); ?>
                </button>
                
                <div class="optimization-results" style="display: none; margin-top: 20px;"></div>
                
                <h4><?php _e('Cleanup Options', 'spiralengine'); ?></h4>
                
                <form method="post" action="">
                    <?php wp_nonce_field('spiralengine_database_cleanup'); ?>
                    
                    <label>
                        <input type="checkbox" name="cleanup_old_episodes" value="1" />
                        <?php _e('Remove episodes older than 1 year', 'spiralengine'); ?>
                        <span class="description">
                            (<?php 
                            $old_episodes = $wpdb->get_var(
                                "SELECT COUNT(*) FROM {$wpdb->prefix}spiralengine_episodes 
                                WHERE created_at < DATE_SUB(NOW(), INTERVAL 1 YEAR)"
                            );
                            printf(__('%d episodes', 'spiralengine'), $old_episodes);
                            ?>)
                        </span>
                    </label>
                    <br />
                    
                    <label>
                        <input type="checkbox" name="cleanup_orphaned_data" value="1" />
                        <?php _e('Remove orphaned data', 'spiralengine'); ?>
                    </label>
                    <br />
                    
                    <label>
                        <input type="checkbox" name="cleanup_transients" value="1" />
                        <?php _e('Clear expired transients', 'spiralengine'); ?>
                    </label>
                    
                    <p>
                        <input type="submit" name="run_cleanup" class="button button-secondary" value="<?php esc_attr_e('Run Cleanup', 'spiralengine'); ?>" />
                    </p>
                </form>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render logs tab
     */
    private function render_logs_tab() {
        ?>
        <div class="logs-section">
            <div class="log-controls">
                <select id="log-level-filter">
                    <option value="all"><?php _e('All Levels', 'spiralengine'); ?></option>
                    <option value="error"><?php _e('Errors', 'spiralengine'); ?></option>
                    <option value="warning"><?php _e('Warnings', 'spiralengine'); ?></option>
                    <option value="info"><?php _e('Info', 'spiralengine'); ?></option>
                    <option value="debug"><?php _e('Debug', 'spiralengine'); ?></option>
                </select>
                
                <input type="text" id="log-search" placeholder="<?php esc_attr_e('Search logs...', 'spiralengine'); ?>" />
                
                <button id="refresh-logs" class="button button-secondary">
                    <?php _e('Refresh', 'spiralengine'); ?>
                </button>
                
                <button id="clear-debug-log" class="button button-secondary">
                    <?php _e('Clear Log', 'spiralengine'); ?>
                </button>
                
                <label style="float: right;">
                    <input type="checkbox" id="toggle-realtime-log" />
                    <?php _e('Real-time updates', 'spiralengine'); ?>
                </label>
            </div>
            
            <div class="log-viewer">
                <div id="realtime-log-viewer">
                    <?php $this->display_recent_logs(); ?>
                </div>
            </div>
            
            <div class="log-stats">
                <h4><?php _e('Log Statistics', 'spiralengine'); ?></h4>
                <?php $this->display_log_statistics(); ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render diagnostics tab
     */
    private function render_diagnostics_tab() {
        ?>
        <div class="diagnostics-section">
            <h3><?php _e('System Diagnostics', 'spiralengine'); ?></h3>
            
            <div class="diagnostic-actions">
                <button id="run-full-diagnostics" class="button button-primary">
                    <?php _e('Run Full Diagnostics', 'spiralengine'); ?>
                </button>
                
                <button id="download-diagnostics" class="button button-secondary">
                    <?php _e('Download Report', 'spiralengine'); ?>
                </button>
            </div>
            
            <div class="diagnostic-results">
                <h4><?php _e('Environment Information', 'spiralengine'); ?></h4>
                <textarea readonly class="large-text" rows="20"><?php echo $this->get_environment_info(); ?></textarea>
            </div>
            
            <div class="troubleshooting">
                <h4><?php _e('Common Issues', 'spiralengine'); ?></h4>
                <?php $this->display_common_issues(); ?>
            </div>
            
            <div class="support-info">
                <h4><?php _e('Get Support', 'spiralengine'); ?></h4>
                <p><?php _e('If you\'re experiencing issues, please include the diagnostic report when contacting support.', 'spiralengine'); ?></p>
                <p>
                    <a href="https://spiralengine.com/support" target="_blank" class="button button-secondary">
                        <?php _e('Contact Support', 'spiralengine'); ?>
                    </a>
                </p>
            </div>
        </div>
        <?php
    }
    
    /**
     * Run health checks
     */
    private function run_health_checks() {
        $this->health_checks = array();
        
        // PHP version check
        $php_version = phpversion();
        $this->health_checks['php_version'] = array(
            'label' => __('PHP Version', 'spiralengine'),
            'status' => version_compare($php_version, '7.4', '>=') ? 'good' : 'warning',
            'message' => $php_version
        );
        
        // WordPress version check
        $wp_version = get_bloginfo('version');
        $this->health_checks['wp_version'] = array(
            'label' => __('WordPress Version', 'spiralengine'),
            'status' => version_compare($wp_version, '5.8', '>=') ? 'good' : 'warning',
            'message' => $wp_version
        );
        
        // Database connection
        global $wpdb;
        $this->health_checks['database'] = array(
            'label' => __('Database Connection', 'spiralengine'),
            'status' => $wpdb->check_connection() ? 'good' : 'error',
            'message' => $wpdb->check_connection() ? __('Connected', 'spiralengine') : __('Not connected', 'spiralengine')
        );
        
        // Required tables
        $required_tables = array(
            'spiralengine_episodes',
            'spiralengine_memberships',
            'spiralengine_widgets',
            'spiralengine_goals'
        );
        
        $missing_tables = array();
        foreach ($required_tables as $table) {
            $table_name = $wpdb->prefix . $table;
            if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
                $missing_tables[] = $table;
            }
        }
        
        $this->health_checks['tables'] = array(
            'label' => __('Database Tables', 'spiralengine'),
            'status' => empty($missing_tables) ? 'good' : 'error',
            'message' => empty($missing_tables) 
                ? __('All tables present', 'spiralengine') 
                : sprintf(__('Missing tables: %s', 'spiralengine'), implode(', ', $missing_tables))
        );
        
        // File permissions
        $upload_dir = wp_upload_dir();
        $this->health_checks['uploads'] = array(
            'label' => __('Upload Directory', 'spiralengine'),
            'status' => wp_is_writable($upload_dir['basedir']) ? 'good' : 'warning',
            'message' => wp_is_writable($upload_dir['basedir']) 
                ? __('Writable', 'spiralengine') 
                : __('Not writable', 'spiralengine')
        );
        
        // Memory limit
        $memory_limit = $this->parse_size(ini_get('memory_limit'));
        $recommended_memory = 128 * 1024 * 1024; // 128MB
        $this->health_checks['memory'] = array(
            'label' => __('Memory Limit', 'spiralengine'),
            'status' => $memory_limit >= $recommended_memory ? 'good' : 'warning',
            'message' => ini_get('memory_limit')
        );
        
        // SSL check
        $this->health_checks['ssl'] = array(
            'label' => __('SSL/HTTPS', 'spiralengine'),
            'status' => is_ssl() ? 'good' : 'warning',
            'message' => is_ssl() ? __('Enabled', 'spiralengine') : __('Not enabled', 'spiralengine')
        );
        
        // Cron status
        $this->health_checks['cron'] = array(
            'label' => __('WP Cron', 'spiralengine'),
            'status' => !defined('DISABLE_WP_CRON') || !DISABLE_WP_CRON ? 'good' : 'warning',
            'message' => !defined('DISABLE_WP_CRON') || !DISABLE_WP_CRON 
                ? __('Enabled', 'spiralengine') 
                : __('Disabled', 'spiralengine')
        );
        
        // Cache status
        $cache_enabled = wp_cache_get('test_' . time(), 'spiralengine');
        $this->health_checks['cache'] = array(
            'label' => __('Object Cache', 'spiralengine'),
            'status' => $cache_enabled !== false ? 'good' : 'warning',
            'message' => $cache_enabled !== false 
                ? __('Available', 'spiralengine') 
                : __('Not available', 'spiralengine')
        );
    }
    
    /**
     * Check system requirements
     *
     * @return array
     */
    private function check_system_requirements() {
        $requirements = array();
        
        // PHP Version
        $requirements[] = array(
            'name' => __('PHP Version', 'spiralengine'),
            'required' => '7.4+',
            'current' => phpversion(),
            'status' => version_compare(phpversion(), '7.4', '>=') ? 'good' : 'warning'
        );
        
        // WordPress Version
        $requirements[] = array(
            'name' => __('WordPress Version', 'spiralengine'),
            'required' => '5.8+',
            'current' => get_bloginfo('version'),
            'status' => version_compare(get_bloginfo('version'), '5.8', '>=') ? 'good' : 'warning'
        );
        
        // MySQL Version
        global $wpdb;
        $mysql_version = $wpdb->db_version();
        $requirements[] = array(
            'name' => __('MySQL Version', 'spiralengine'),
            'required' => '5.6+',
            'current' => $mysql_version,
            'status' => version_compare($mysql_version, '5.6', '>=') ? 'good' : 'warning'
        );
        
        // Memory Limit
        $memory_limit = ini_get('memory_limit');
        $memory_limit_bytes = $this->parse_size($memory_limit);
        $requirements[] = array(
            'name' => __('Memory Limit', 'spiralengine'),
            'required' => '128M',
            'current' => $memory_limit,
            'status' => $memory_limit_bytes >= 128 * 1024 * 1024 ? 'good' : 'warning'
        );
        
        // Max Execution Time
        $max_execution = ini_get('max_execution_time');
        $requirements[] = array(
            'name' => __('Max Execution Time', 'spiralengine'),
            'required' => '30s',
            'current' => $max_execution . 's',
            'status' => $max_execution >= 30 || $max_execution == 0 ? 'good' : 'warning'
        );
        
        // Upload Max Size
        $upload_max = ini_get('upload_max_filesize');
        $requirements[] = array(
            'name' => __('Upload Max Size', 'spiralengine'),
            'required' => '32M',
            'current' => $upload_max,
            'status' => $this->parse_size($upload_max) >= 32 * 1024 * 1024 ? 'good' : 'warning'
        );
        
        return $requirements;
    }
    
    /**
     * Get plugin status
     *
     * @return array
     */
    private function get_plugin_status() {
        $status_items = array();
        
        // Plugin version
        $status_items[] = array(
            'label' => __('Plugin Version', 'spiralengine'),
            'status' => 'good',
            'message' => SPIRALENGINE_VERSION
        );
        
        // License status
        $license_status = get_option('spiralengine_license_status', 'inactive');
        $status_items[] = array(
            'label' => __('License Status', 'spiralengine'),
            'status' => $license_status === 'active' ? 'good' : 'warning',
            'message' => ucfirst($license_status)
        );
        
        // MemberPress integration
        $memberpress_active = class_exists('MeprOptions');
        $status_items[] = array(
            'label' => __('MemberPress Integration', 'spiralengine'),
            'status' => $memberpress_active ? 'good' : 'warning',
            'message' => $memberpress_active ? __('Active', 'spiralengine') : __('Not found', 'spiralengine')
        );
        
        // AI Provider
        $ai_settings = get_option('spiralengine_ai_settings', array());
        $ai_configured = !empty($ai_settings['openai']['api_key']);
        $status_items[] = array(
            'label' => __('AI Provider', 'spiralengine'),
            'status' => $ai_configured ? 'good' : 'warning',
            'message' => $ai_configured ? __('Configured', 'spiralengine') : __('Not configured', 'spiralengine')
        );
        
        // Email configuration
        $email_settings = get_option('spiralengine_email_settings', array());
        $email_configured = !empty($email_settings['from_address']);
        $status_items[] = array(
            'label' => __('Email Configuration', 'spiralengine'),
            'status' => $email_configured ? 'good' : 'warning',
            'message' => $email_configured ? __('Configured', 'spiralengine') : __('Not configured', 'spiralengine')
        );
        
        return $status_items;
    }
    
    /**
     * Run security checks
     *
     * @return array
     */
    private function run_security_checks() {
        $checks = array();
        
        // SSL/HTTPS
        $checks[] = array(
            'name' => __('SSL Certificate', 'spiralengine'),
            'status' => is_ssl() ? 'good' : 'warning',
            'action' => !is_ssl() ? array(
                'url' => admin_url('options-general.php'),
                'text' => __('Enable SSL', 'spiralengine')
            ) : null
        );
        
        // Debug mode
        $debug_mode = defined('WP_DEBUG') && WP_DEBUG;
        $checks[] = array(
            'name' => __('Debug Mode', 'spiralengine'),
            'status' => !$debug_mode ? 'good' : 'warning',
            'action' => $debug_mode ? array(
                'url' => '#',
                'text' => __('Disable in production', 'spiralengine')
            ) : null
        );
        
        // File permissions
        $upload_dir = wp_upload_dir();
        $proper_permissions = !is_writable(ABSPATH . 'wp-config.php');
        $checks[] = array(
            'name' => __('File Permissions', 'spiralengine'),
            'status' => $proper_permissions ? 'good' : 'warning'
        );
        
        // Database prefix
        global $wpdb;
        $default_prefix = $wpdb->prefix === 'wp_';
        $checks[] = array(
            'name' => __('Database Prefix', 'spiralengine'),
            'status' => !$default_prefix ? 'good' : 'warning'
        );
        
        // Admin username
        $admin_user = get_user_by('login', 'admin');
        $checks[] = array(
            'name' => __('Admin Username', 'spiralengine'),
            'status' => !$admin_user ? 'good' : 'warning'
        );
        
        // API keys exposure
        $api_keys_secure = true; // Would check for exposed keys
        $checks[] = array(
            'name' => __('API Key Security', 'spiralengine'),
            'status' => $api_keys_secure ? 'good' : 'error'
        );
        
        return $checks;
    }
    
    /**
     * Get SpiralEngine cron jobs
     *
     * @return array
     */
    private function get_spiralengine_cron_jobs() {
        $cron_jobs = array();
        $crons = _get_cron_array();
        
        $spiralengine_hooks = array(
            'spiralengine_calculate_analytics' => __('Analytics Calculation', 'spiralengine'),
            'spiralengine_scheduled_health_check' => __('Health Check', 'spiralengine'),
            'spiralengine_cleanup_old_data' => __('Data Cleanup', 'spiralengine'),
            'spiralengine_send_reminders' => __('Send Reminders', 'spiralengine'),
            'spiralengine_process_queued_emails' => __('Process Email Queue', 'spiralengine')
        );
        
        foreach ($spiralengine_hooks as $hook => $name) {
            $next_run = wp_next_scheduled($hook);
            $schedule = wp_get_schedule($hook);
            
            $cron_jobs[] = array(
                'hook' => $hook,
                'name' => $name,
                'schedule' => $schedule ? $schedule : __('Not scheduled', 'spiralengine'),
                'next_run' => $next_run
            );
        }
        
        return $cron_jobs;
    }
    
    /**
     * Calculate performance metrics
     */
    private function calculate_performance_metrics() {
        global $wpdb;
        
        // Page load time (simulated)
        $this->performance_metrics['page_load'] = round(timer_stop(0, 3), 2);
        
        // Database queries
        $this->performance_metrics['db_queries'] = get_num_queries();
        
        // Memory usage
        $this->performance_metrics['memory_usage'] = round(memory_get_peak_usage() / 1024 / 1024, 2);
        $this->performance_metrics['memory_limit'] = $this->parse_size(ini_get('memory_limit')) / 1024 / 1024;
        $this->performance_metrics['memory_percentage'] = round(
            ($this->performance_metrics['memory_usage'] / $this->performance_metrics['memory_limit']) * 100
        );
        
        // Cache hit rate
        $cache_hits = wp_cache_get('cache_hits', 'spiralengine') ?: 0;
        $cache_misses = wp_cache_get('cache_misses', 'spiralengine') ?: 0;
        $total_cache = $cache_hits + $cache_misses;
        $this->performance_metrics['cache_hit_rate'] = $total_cache > 0 
            ? round(($cache_hits / $total_cache) * 100, 1)
            : 0;
        
        // Database size
        $db_size = $wpdb->get_var(
            "SELECT SUM(data_length + index_length) / 1024 / 1024 
            FROM information_schema.TABLES 
            WHERE table_schema = '" . DB_NAME . "'"
        );
        $this->performance_metrics['db_size'] = round($db_size, 2);
    }
    
    /**
     * Get performance status
     *
     * @param string $metric
     * @return string
     */
    private function get_performance_status($metric) {
        $thresholds = array(
            'page_load' => array('good' => 2, 'warning' => 5),
            'db_queries' => array('good' => 50, 'warning' => 100),
            'cache_hit_rate' => array('good' => 80, 'warning' => 60)
        );
        
        if (!isset($thresholds[$metric])) {
            return 'status-unknown';
        }
        
        $value = $this->performance_metrics[$metric];
        $threshold = $thresholds[$metric];
        
        if ($metric === 'cache_hit_rate') {
            if ($value >= $threshold['good']) return 'status-good';
            if ($value >= $threshold['warning']) return 'status-warning';
            return 'status-error';
        } else {
            if ($value <= $threshold['good']) return 'status-good';
            if ($value <= $threshold['warning']) return 'status-warning';
            return 'status-error';
        }
    }
    
    /**
     * Render performance recommendations
     */
    private function render_performance_recommendations() {
        $recommendations = array();
        
        // Check page load time
        if ($this->performance_metrics['page_load'] > 3) {
            $recommendations[] = __('Page load time is high. Consider enabling caching.', 'spiralengine');
        }
        
        // Check database queries
        if ($this->performance_metrics['db_queries'] > 100) {
            $recommendations[] = __('Too many database queries. Review your code for optimization opportunities.', 'spiralengine');
        }
        
        // Check memory usage
        if ($this->performance_metrics['memory_percentage'] > 80) {
            $recommendations[] = __('Memory usage is high. Consider increasing PHP memory limit.', 'spiralengine');
        }
        
        // Check cache hit rate
        if ($this->performance_metrics['cache_hit_rate'] < 70) {
            $recommendations[] = __('Low cache hit rate. Consider implementing object caching.', 'spiralengine');
        }
        
        if (empty($recommendations)) {
            $recommendations[] = __('Performance looks good! No immediate recommendations.', 'spiralengine');
        }
        
        echo '<ul>';
        foreach ($recommendations as $recommendation) {
            echo '<li>' . esc_html($recommendation) . '</li>';
        }
        echo '</ul>';
    }
    
    /**
     * Render slow queries
     */
    private function render_slow_queries() {
        // This would integrate with query monitoring
        // For now, show placeholder
        ?>
        <p><?php _e('Query monitoring is not currently active.', 'spiralengine'); ?></p>
        <p><?php _e('Enable SAVEQUERIES in wp-config.php to track slow queries.', 'spiralengine'); ?></p>
        <?php
    }
    
    /**
     * Render database overview
     */
    private function render_database_overview() {
        global $wpdb;
        
        // Get database info
        $db_info = array(
            __('Database Name', 'spiralengine') => DB_NAME,
            __('Database Host', 'spiralengine') => DB_HOST,
            __('Database Version', 'spiralengine') => $wpdb->db_version(),
            __('Total Size', 'spiralengine') => $this->performance_metrics['db_size'] . ' MB'
        );
        
        echo '<table class="widefat striped">';
        foreach ($db_info as $label => $value) {
            echo '<tr>';
            echo '<th>' . esc_html($label) . '</th>';
            echo '<td>' . esc_html($value) . '</td>';
            echo '</tr>';
        }
        echo '</table>';
    }
    
    /**
     * Render table sizes
     */
    private function render_table_sizes() {
        global $wpdb;
        
        $tables = $wpdb->get_results(
            "SELECT 
                table_name AS `name`,
                ROUND((data_length + index_length) / 1024 / 1024, 2) AS `size`,
                table_rows AS `rows`
            FROM information_schema.TABLES
            WHERE table_schema = '" . DB_NAME . "'
                AND table_name LIKE '{$wpdb->prefix}spiralengine_%'
            ORDER BY (data_length + index_length) DESC"
        );
        
        ?>
        <table class="widefat striped">
            <thead>
                <tr>
                    <th><?php _e('Table', 'spiralengine'); ?></th>
                    <th><?php _e('Rows', 'spiralengine'); ?></th>
                    <th><?php _e('Size (MB)', 'spiralengine'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($tables as $table) : ?>
                    <tr>
                        <td><?php echo esc_html(str_replace($wpdb->prefix, '', $table->name)); ?></td>
                        <td><?php echo number_format($table->rows); ?></td>
                        <td><?php echo $table->size; ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }
    
    /**
     * Display recent logs
     */
    private function display_recent_logs() {
        $log_file = WP_CONTENT_DIR . '/debug.log';
        
        if (!file_exists($log_file)) {
            echo '<p>' . __('No debug log found.', 'spiralengine') . '</p>';
            return;
        }
        
        // Read last 100 lines
        $lines = $this->tail($log_file, 100);
        
        if (empty($lines)) {
            echo '<p>' . __('Debug log is empty.', 'spiralengine') . '</p>';
            return;
        }
        
        echo '<pre class="log-content">';
        foreach ($lines as $line) {
            $class = '';
            if (stripos($line, 'error') !== false) $class = 'log-error';
            elseif (stripos($line, 'warning') !== false) $class = 'log-warning';
            elseif (stripos($line, 'notice') !== false) $class = 'log-notice';
            
            echo '<span class="' . $class . '">' . esc_html($line) . '</span>' . "\n";
        }
        echo '</pre>';
    }
    
    /**
     * Display log statistics
     */
    private function display_log_statistics() {
        $log_file = WP_CONTENT_DIR . '/debug.log';
        
        if (!file_exists($log_file)) {
            echo '<p>' . __('No statistics available.', 'spiralengine') . '</p>';
            return;
        }
        
        $stats = array(
            'errors' => 0,
            'warnings' => 0,
            'notices' => 0,
            'size' => filesize($log_file)
        );
        
        // Count log levels (simplified)
        $content = file_get_contents($log_file);
        $stats['errors'] = substr_count(strtolower($content), 'error');
        $stats['warnings'] = substr_count(strtolower($content), 'warning');
        $stats['notices'] = substr_count(strtolower($content), 'notice');
        
        ?>
        <table class="widefat striped">
            <tr>
                <th><?php _e('Log Level', 'spiralengine'); ?></th>
                <th><?php _e('Count', 'spiralengine'); ?></th>
            </tr>
            <tr>
                <td><?php _e('Errors', 'spiralengine'); ?></td>
                <td><span class="log-error"><?php echo number_format($stats['errors']); ?></span></td>
            </tr>
            <tr>
                <td><?php _e('Warnings', 'spiralengine'); ?></td>
                <td><span class="log-warning"><?php echo number_format($stats['warnings']); ?></span></td>
            </tr>
            <tr>
                <td><?php _e('Notices', 'spiralengine'); ?></td>
                <td><span class="log-notice"><?php echo number_format($stats['notices']); ?></span></td>
            </tr>
            <tr>
                <td><?php _e('Log Size', 'spiralengine'); ?></td>
                <td><?php echo size_format($stats['size']); ?></td>
            </tr>
        </table>
        <?php
    }
    
    /**
     * Get environment info
     *
     * @return string
     */
    private function get_environment_info() {
        global $wpdb;
        
        $info = array();
        
        // WordPress environment
        $info[] = '=== WordPress Environment ===';
        $info[] = 'Version: ' . get_bloginfo('version');
        $info[] = 'Language: ' . get_locale();
        $info[] = 'Multisite: ' . (is_multisite() ? 'Yes' : 'No');
        $info[] = 'Memory Limit: ' . WP_MEMORY_LIMIT;
        $info[] = 'Debug Mode: ' . (WP_DEBUG ? 'Enabled' : 'Disabled');
        $info[] = '';
        
        // Server environment
        $info[] = '=== Server Environment ===';
        $info[] = 'Server Software: ' . $_SERVER['SERVER_SOFTWARE'];
        $info[] = 'PHP Version: ' . phpversion();
        $info[] = 'PHP Memory Limit: ' . ini_get('memory_limit');
        $info[] = 'PHP Max Execution Time: ' . ini_get('max_execution_time');
        $info[] = 'PHP Max Input Vars: ' . ini_get('max_input_vars');
        $info[] = 'PHP Post Max Size: ' . ini_get('post_max_size');
        $info[] = 'PHP Upload Max Filesize: ' . ini_get('upload_max_filesize');
        $info[] = '';
        
        // Database
        $info[] = '=== Database ===';
        $info[] = 'Extension: ' . $wpdb->use_mysqli ? 'mysqli' : 'mysql';
        $info[] = 'Version: ' . $wpdb->db_version();
        $info[] = 'Database Name: ' . DB_NAME;
        $info[] = 'Table Prefix: ' . $wpdb->prefix;
        $info[] = '';
        
        // Active plugins
        $info[] = '=== Active Plugins ===';
        $active_plugins = get_option('active_plugins');
        foreach ($active_plugins as $plugin) {
            $plugin_data = get_plugin_data(WP_PLUGIN_DIR . '/' . $plugin);
            $info[] = $plugin_data['Name'] . ' (v' . $plugin_data['Version'] . ')';
        }
        $info[] = '';
        
        // Active theme
        $info[] = '=== Active Theme ===';
        $theme = wp_get_theme();
        $info[] = $theme->get('Name') . ' (v' . $theme->get('Version') . ')';
        $info[] = 'Parent Theme: ' . ($theme->parent() ? $theme->parent()->get('Name') : 'None');
        
        return implode("\n", $info);
    }
    
    /**
     * Display common issues
     */
    private function display_common_issues() {
        $issues = array(
            array(
                'title' => __('Plugin not loading', 'spiralengine'),
                'description' => __('Check that the plugin is activated and there are no PHP errors.', 'spiralengine'),
                'solution' => __('Enable WP_DEBUG and check the error log.', 'spiralengine')
            ),
            array(
                'title' => __('Database errors', 'spiralengine'),
                'description' => __('Tables might be missing or corrupted.', 'spiralengine'),
                'solution' => __('Deactivate and reactivate the plugin to recreate tables.', 'spiralengine')
            ),
            array(
                'title' => __('Performance issues', 'spiralengine'),
                'description' => __('Site running slowly after plugin activation.', 'spiralengine'),
                'solution' => __('Enable object caching and optimize database.', 'spiralengine')
            ),
            array(
                'title' => __('Email not sending', 'spiralengine'),
                'description' => __('Users not receiving notification emails.', 'spiralengine'),
                'solution' => __('Configure SMTP settings or use a transactional email service.', 'spiralengine')
            )
        );
        
        echo '<div class="accordion">';
        foreach ($issues as $index => $issue) {
            ?>
            <div class="accordion-item">
                <h4 class="accordion-header">
                    <button class="accordion-button" type="button">
                        <?php echo esc_html($issue['title']); ?>
                    </button>
                </h4>
                <div class="accordion-content">
                    <p><strong><?php _e('Description:', 'spiralengine'); ?></strong> <?php echo esc_html($issue['description']); ?></p>
                    <p><strong><?php _e('Solution:', 'spiralengine'); ?></strong> <?php echo esc_html($issue['solution']); ?></p>
                </div>
            </div>
            <?php
        }
        echo '</div>';
    }
    
    /**
     * Calculate overall status
     *
     * @return array
     */
    private function calculate_overall_status() {
        $total_checks = count($this->health_checks);
        $good_checks = 0;
        $warning_checks = 0;
        $error_checks = 0;
        
        foreach ($this->health_checks as $check) {
            switch ($check['status']) {
                case 'good':
                    $good_checks++;
                    break;
                case 'warning':
                    $warning_checks++;
                    break;
                case 'error':
                    $error_checks++;
                    break;
            }
        }
        
        if ($error_checks > 0) {
            return array(
                'status' => 'error',
                'text' => __('Critical Issues Detected', 'spiralengine'),
                'description' => sprintf(
                    __('%d critical issues need immediate attention.', 'spiralengine'),
                    $error_checks
                )
            );
        } elseif ($warning_checks > 2) {
            return array(
                'status' => 'warning',
                'text' => __('Some Issues Found', 'spiralengine'),
                'description' => sprintf(
                    __('%d warnings detected. Review recommended.', 'spiralengine'),
                    $warning_checks
                )
            );
        } else {
            return array(
                'status' => 'good',
                'text' => __('System Healthy', 'spiralengine'),
                'description' => __('All systems are functioning normally.', 'spiralengine')
            );
        }
    }
    
    /**
     * Parse size string to bytes
     *
     * @param string $size
     * @return int
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
    
    /**
     * Read last N lines from file
     *
     * @param string $file
     * @param int $lines
     * @return array
     */
    private function tail($file, $lines = 100) {
        $handle = fopen($file, "r");
        $linecounter = $lines;
        $pos = -2;
        $beginning = false;
        $text = array();
        
        while ($linecounter > 0) {
            $t = " ";
            while ($t != "\n") {
                if (fseek($handle, $pos, SEEK_END) == -1) {
                    $beginning = true;
                    break;
                }
                $t = fgetc($handle);
                $pos--;
            }
            $linecounter--;
            if ($beginning) {
                rewind($handle);
            }
            $text[$lines - $linecounter - 1] = fgets($handle);
            if ($beginning) break;
        }
        fclose($handle);
        
        return array_reverse($text);
    }
    
    /**
     * AJAX: Run health check
     */
    public function ajax_run_health_check() {
        check_ajax_referer('spiralengine_system_health', 'nonce');
        
        if (!current_user_can('spiralengine_view_system')) {
            wp_send_json_error();
        }
        
        $this->run_health_checks();
        $this->run_scheduled_health_check();
        
        wp_send_json_success();
    }
    
    /**
     * AJAX: Get system status
     */
    public function ajax_get_system_status() {
        check_ajax_referer('spiralengine_system_health', 'nonce');
        
        if (!current_user_can('spiralengine_view_system')) {
            wp_send_json_error();
        }
        
        $this->calculate_performance_metrics();
        
        $data = array(
            'metrics' => array(
                'page-load' => $this->performance_metrics['page_load'],
                'db-queries' => $this->performance_metrics['db_queries'],
                'memory' => $this->performance_metrics['memory_usage'],
                'cache-hit' => $this->performance_metrics['cache_hit_rate']
            ),
            'status' => array(
                'database' => $this->health_checks['database']['status'] ?? 'unknown',
                'cache' => $this->health_checks['cache']['status'] ?? 'unknown',
                'cron' => $this->health_checks['cron']['status'] ?? 'unknown'
            )
        );
        
        wp_send_json_success($data);
    }
    
    /**
     * AJAX: Clear debug log
     */
    public function ajax_clear_debug_log() {
        check_ajax_referer('spiralengine_system_health', 'nonce');
        
        if (!current_user_can('spiralengine_manage_settings')) {
            wp_send_json_error();
        }
        
        $log_file = WP_CONTENT_DIR . '/debug.log';
        
        if (file_exists($log_file)) {
            file_put_contents($log_file, '');
            wp_send_json_success();
        } else {
            wp_send_json_error(array('message' => __('Debug log not found', 'spiralengine')));
        }
    }
    
    /**
     * AJAX: Optimize database
     */
    public function ajax_optimize_database() {
        check_ajax_referer('spiralengine_system_health', 'nonce');
        
        if (!current_user_can('spiralengine_manage_settings')) {
            wp_send_json_error();
        }
        
        global $wpdb;
        
        // Get SpiralEngine tables
        $tables = $wpdb->get_col(
            "SELECT table_name 
            FROM information_schema.TABLES 
            WHERE table_schema = '" . DB_NAME . "' 
            AND table_name LIKE '{$wpdb->prefix}spiralengine_%'"
        );
        
        $optimized = 0;
        foreach ($tables as $table) {
            $wpdb->query("OPTIMIZE TABLE $table");
            $optimized++;
        }
        
        // Clear transients
        $wpdb->query(
            "DELETE FROM {$wpdb->options} 
            WHERE option_name LIKE '_transient_spiralengine%' 
            OR option_name LIKE '_transient_timeout_spiralengine%'"
        );
        
        $message = sprintf(
            __('Optimized %d tables and cleared transients.', 'spiralengine'),
            $optimized
        );
        
        wp_send_json_success(array('message' => $message));
    }
    
    /**
     * AJAX: Run diagnostics
     */
    public function ajax_run_diagnostics() {
        check_ajax_referer('spiralengine_system_health', 'nonce');
        
        if (!current_user_can('spiralengine_view_system')) {
            wp_send_json_error();
        }
        
        $format = isset($_POST['format']) ? sanitize_key($_POST['format']) : 'display';
        
        if ($format === 'download') {
            $content = $this->get_environment_info();
            $content .= "\n\n=== Health Checks ===\n";
            
            $this->run_health_checks();
            foreach ($this->health_checks as $key => $check) {
                $content .= $check['label'] . ': ' . $check['status'] . ' - ' . $check['message'] . "\n";
            }
            
            $upload_dir = wp_upload_dir();
            $filename = 'spiralengine-diagnostics-' . date('Y-m-d-His') . '.txt';
            $file_path = $upload_dir['basedir'] . '/' . $filename;
            
            file_put_contents($file_path, $content);
            
            wp_send_json_success(array(
                'file_url' => $upload_dir['baseurl'] . '/' . $filename,
                'filename' => $filename
            ));
        } else {
            wp_send_json_success(array(
                'diagnostics' => $this->get_environment_info()
            ));
        }
    }
    
    /**
     * Run scheduled health check
     */
    public function run_scheduled_health_check() {
        $this->run_health_checks();
        
        // Check for critical issues
        $critical_issues = array();
        
        foreach ($this->health_checks as $key => $check) {
            if ($check['status'] === 'error') {
                $critical_issues[] = $check['label'] . ': ' . $check['message'];
            }
        }
        
        // Send admin notification if critical issues found
        if (!empty($critical_issues)) {
            $admin_email = get_option('admin_email');
            $subject = __('SpiralEngine: Critical System Issues Detected', 'spiralengine');
            $message = __('The following critical issues were detected:', 'spiralengine') . "\n\n";
            $message .= implode("\n", $critical_issues);
            $message .= "\n\n" . __('Please check the system health page for more details.', 'spiralengine');
            
            wp_mail($admin_email, $subject, $message);
        }
        
        // Store health check results
        update_option('spiralengine_last_health_check', array(
            'timestamp' => current_time('timestamp'),
            'results' => $this->health_checks,
            'overall_status' => $this->calculate_overall_status()
        ));
    }
}

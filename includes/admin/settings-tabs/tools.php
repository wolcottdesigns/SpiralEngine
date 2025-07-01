<?php
/**
 * SpiralEngine Settings - Tools Tab
 * 
 * @package    SpiralEngine
 * @subpackage Includes/Admin/Settings
 * @file       includes/admin/settings-tabs/tools.php
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Get system status
$system_status = spiralengine_get_system_status();
$health_check = spiralengine_run_health_check();
?>

<div class="spiralengine-settings-panel" id="spiralengine-tools">
    <h2><?php _e('Tools & Utilities', 'spiralengine'); ?></h2>
    <p class="description"><?php _e('Maintenance tools, data management, and system utilities.', 'spiralengine'); ?></p>

    <!-- System Health -->
    <div class="spiralengine-health-check">
        <h3><?php _e('System Health', 'spiralengine'); ?></h3>
        <div class="health-status <?php echo $health_check['status']; ?>">
            <span class="status-icon">
                <?php if ($health_check['status'] === 'good'): ?>
                    ✓
                <?php elseif ($health_check['status'] === 'warning'): ?>
                    ⚠
                <?php else: ?>
                    ✗
                <?php endif; ?>
            </span>
            <span class="status-text">
                <?php
                if ($health_check['status'] === 'good') {
                    _e('All systems operational', 'spiralengine');
                } elseif ($health_check['status'] === 'warning') {
                    _e('Some issues detected', 'spiralengine');
                } else {
                    _e('Critical issues found', 'spiralengine');
                }
                ?>
            </span>
        </div>
        
        <?php if (!empty($health_check['issues'])): ?>
        <div class="health-issues">
            <h4><?php _e('Issues Found:', 'spiralengine'); ?></h4>
            <ul>
                <?php foreach ($health_check['issues'] as $issue): ?>
                <li class="issue-<?php echo esc_attr($issue['severity']); ?>">
                    <strong><?php echo esc_html($issue['title']); ?>:</strong>
                    <?php echo esc_html($issue['message']); ?>
                    <?php if (!empty($issue['action'])): ?>
                    <a href="<?php echo esc_url($issue['action']['url']); ?>" class="button button-small">
                        <?php echo esc_html($issue['action']['label']); ?>
                    </a>
                    <?php endif; ?>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>
        
        <p style="margin-top: 15px;">
            <button type="button" class="button button-secondary" id="run-health-check">
                <?php _e('Run Health Check', 'spiralengine'); ?>
            </button>
            <span class="spinner"></span>
        </p>
    </div>

    <!-- Data Management -->
    <h3><?php _e('Data Management', 'spiralengine'); ?></h3>
    <table class="form-table" role="presentation">
        <tbody>
            <!-- Export Data -->
            <tr>
                <th scope="row"><?php _e('Export Data', 'spiralengine'); ?></th>
                <td>
                    <p><?php _e('Export all SpiralEngine data for backup or migration.', 'spiralengine'); ?></p>
                    <p>
                        <button type="button" class="button button-primary" id="export-all-data">
                            <?php _e('Export All Data', 'spiralengine'); ?>
                        </button>
                        <span class="description"><?php _e('Creates a complete backup of all data', 'spiralengine'); ?></span>
                    </p>
                </td>
            </tr>
            
            <!-- Import Data -->
            <tr>
                <th scope="row"><?php _e('Import Data', 'spiralengine'); ?></th>
                <td>
                    <p><?php _e('Import SpiralEngine data from a backup file.', 'spiralengine'); ?></p>
                    <input type="file" id="import-file" accept=".json,.sql,.zip">
                    <p>
                        <button type="button" class="button button-secondary" id="import-data" disabled>
                            <?php _e('Import Data', 'spiralengine'); ?>
                        </button>
                        <span class="description"><?php _e('⚠️ This will overwrite existing data', 'spiralengine'); ?></span>
                    </p>
                </td>
            </tr>
            
            <!-- Reset Data -->
            <tr>
                <th scope="row"><?php _e('Reset Data', 'spiralengine'); ?></th>
                <td>
                    <p><?php _e('Remove specific types of data from the database.', 'spiralengine'); ?></p>
                    <fieldset>
                        <label>
                            <input type="checkbox" class="reset-option" value="episodes">
                            <?php _e('All Episodes', 'spiralengine'); ?>
                        </label><br>
                        <label>
                            <input type="checkbox" class="reset-option" value="goals">
                            <?php _e('All Goals', 'spiralengine'); ?>
                        </label><br>
                        <label>
                            <input type="checkbox" class="reset-option" value="analytics">
                            <?php _e('Analytics Data', 'spiralengine'); ?>
                        </label><br>
                        <label>
                            <input type="checkbox" class="reset-option" value="ai_data">
                            <?php _e('AI Analysis Data', 'spiralengine'); ?>
                        </label><br>
                        <label>
                            <input type="checkbox" class="reset-option" value="user_settings">
                            <?php _e('User Settings', 'spiralengine'); ?>
                        </label><br>
                        <label>
                            <input type="checkbox" class="reset-option" value="all">
                            <strong><?php _e('ALL DATA (Complete Reset)', 'spiralengine'); ?></strong>
                        </label>
                    </fieldset>
                    <p style="margin-top: 10px;">
                        <button type="button" class="button button-link-delete" id="reset-data" disabled>
                            <?php _e('Reset Selected Data', 'spiralengine'); ?>
                        </button>
                    </p>
                </td>
            </tr>
        </tbody>
    </table>

    <!-- Cache Management -->
    <h3><?php _e('Cache Management', 'spiralengine'); ?></h3>
    <table class="form-table" role="presentation">
        <tbody>
            <tr>
                <th scope="row"><?php _e('Cache Statistics', 'spiralengine'); ?></th>
                <td>
                    <div class="cache-stats">
                        <p><?php _e('Object Cache:', 'spiralengine'); ?> 
                            <strong><?php echo size_format($system_status['cache']['object_cache_size']); ?></strong>
                            (<?php echo number_format($system_status['cache']['object_cache_items']); ?> items)
                        </p>
                        <p><?php _e('Transient Cache:', 'spiralengine'); ?> 
                            <strong><?php echo size_format($system_status['cache']['transient_cache_size']); ?></strong>
                            (<?php echo number_format($system_status['cache']['transient_cache_items']); ?> items)
                        </p>
                        <p><?php _e('Page Cache:', 'spiralengine'); ?> 
                            <strong><?php echo size_format($system_status['cache']['page_cache_size']); ?></strong>
                        </p>
                    </div>
                </td>
            </tr>
            
            <tr>
                <th scope="row"><?php _e('Clear Cache', 'spiralengine'); ?></th>
                <td>
                    <button type="button" class="button button-secondary clear-cache" data-cache="all">
                        <?php _e('Clear All Cache', 'spiralengine'); ?>
                    </button>
                    <button type="button" class="button button-secondary clear-cache" data-cache="object">
                        <?php _e('Clear Object Cache', 'spiralengine'); ?>
                    </button>
                    <button type="button" class="button button-secondary clear-cache" data-cache="transient">
                        <?php _e('Clear Transients', 'spiralengine'); ?>
                    </button>
                    <button type="button" class="button button-secondary clear-cache" data-cache="api">
                        <?php _e('Clear API Cache', 'spiralengine'); ?>
                    </button>
                    <p class="cache-result" style="display: none;"></p>
                </td>
            </tr>
        </tbody>
    </table>

    <!-- Database Tools -->
    <h3><?php _e('Database Tools', 'spiralengine'); ?></h3>
    <table class="form-table" role="presentation">
        <tbody>
            <tr>
                <th scope="row"><?php _e('Database Info', 'spiralengine'); ?></th>
                <td>
                    <p><?php _e('Total database size:', 'spiralengine'); ?> 
                        <strong><?php echo size_format($system_status['database']['total_size']); ?></strong>
                    </p>
                    <p><?php _e('SpiralEngine tables size:', 'spiralengine'); ?> 
                        <strong><?php echo size_format($system_status['database']['spiralengine_size']); ?></strong>
                    </p>
                    <p><?php _e('Number of episodes:', 'spiralengine'); ?> 
                        <strong><?php echo number_format($system_status['database']['episode_count']); ?></strong>
                    </p>
                </td>
            </tr>
            
            <tr>
                <th scope="row"><?php _e('Database Operations', 'spiralengine'); ?></th>
                <td>
                    <button type="button" class="button button-secondary db-operation" data-operation="repair">
                        <?php _e('Repair Tables', 'spiralengine'); ?>
                    </button>
                    <button type="button" class="button button-secondary db-operation" data-operation="optimize">
                        <?php _e('Optimize Tables', 'spiralengine'); ?>
                    </button>
                    <button type="button" class="button button-secondary db-operation" data-operation="analyze">
                        <?php _e('Analyze Tables', 'spiralengine'); ?>
                    </button>
                    <p class="db-result" style="display: none;"></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row"><?php _e('Database Cleanup', 'spiralengine'); ?></th>
                <td>
                    <p><?php _e('Remove old or orphaned data:', 'spiralengine'); ?></p>
                    <label>
                        <input type="checkbox" id="cleanup-orphaned" checked>
                        <?php _e('Orphaned metadata', 'spiralengine'); ?>
                    </label><br>
                    <label>
                        <input type="checkbox" id="cleanup-expired" checked>
                        <?php _e('Expired transients', 'spiralengine'); ?>
                    </label><br>
                    <label>
                        <input type="checkbox" id="cleanup-revisions">
                        <?php _e('Old episode revisions', 'spiralengine'); ?>
                    </label><br>
                    <label>
                        <input type="checkbox" id="cleanup-logs">
                        <?php _e('Old log entries (30+ days)', 'spiralengine'); ?>
                    </label>
                    <p style="margin-top: 10px;">
                        <button type="button" class="button button-secondary" id="run-cleanup">
                            <?php _e('Run Cleanup', 'spiralengine'); ?>
                        </button>
                    </p>
                </td>
            </tr>
        </tbody>
    </table>

    <!-- Migration Tools -->
    <h3><?php _e('Migration Tools', 'spiralengine'); ?></h3>
    <table class="form-table" role="presentation">
        <tbody>
            <tr>
                <th scope="row"><?php _e('Import from Other Plugins', 'spiralengine'); ?></th>
                <td>
                    <select id="migration-source">
                        <option value=""><?php _e('Select source plugin', 'spiralengine'); ?></option>
                        <option value="mood-tracker"><?php _e('Mood Tracker Plugin', 'spiralengine'); ?></option>
                        <option value="mental-health-tracker"><?php _e('Mental Health Tracker', 'spiralengine'); ?></option>
                        <option value="csv"><?php _e('CSV File', 'spiralengine'); ?></option>
                        <option value="custom"><?php _e('Custom Format', 'spiralengine'); ?></option>
                    </select>
                    <button type="button" class="button button-secondary" id="start-migration" disabled>
                        <?php _e('Start Migration', 'spiralengine'); ?>
                    </button>
                </td>
            </tr>
            
            <tr>
                <th scope="row"><?php _e('Export for Migration', 'spiralengine'); ?></th>
                <td>
                    <p><?php _e('Export data in a format suitable for migration to another system.', 'spiralengine'); ?></p>
                    <select id="export-format">
                        <option value="json"><?php _e('JSON (Recommended)', 'spiralengine'); ?></option>
                        <option value="csv"><?php _e('CSV', 'spiralengine'); ?></option>
                        <option value="xml"><?php _e('XML', 'spiralengine'); ?></option>
                        <option value="sql"><?php _e('SQL Dump', 'spiralengine'); ?></option>
                    </select>
                    <button type="button" class="button button-secondary" id="export-for-migration">
                        <?php _e('Export', 'spiralengine'); ?>
                    </button>
                </td>
            </tr>
        </tbody>
    </table>

    <!-- Debug Tools -->
    <h3><?php _e('Debug Tools', 'spiralengine'); ?></h3>
    <table class="form-table" role="presentation">
        <tbody>
            <tr>
                <th scope="row"><?php _e('Debug Log', 'spiralengine'); ?></th>
                <td>
                    <p><?php _e('View and manage the debug log.', 'spiralengine'); ?></p>
                    <p>
                        <?php _e('Current log size:', 'spiralengine'); ?> 
                        <strong><?php echo size_format($system_status['debug']['log_size']); ?></strong>
                    </p>
                    <button type="button" class="button button-secondary" id="view-debug-log">
                        <?php _e('View Log', 'spiralengine'); ?>
                    </button>
                    <button type="button" class="button button-secondary" id="download-debug-log">
                        <?php _e('Download Log', 'spiralengine'); ?>
                    </button>
                    <button type="button" class="button button-link-delete" id="clear-debug-log">
                        <?php _e('Clear Log', 'spiralengine'); ?>
                    </button>
                </td>
            </tr>
            
            <tr>
                <th scope="row"><?php _e('System Report', 'spiralengine'); ?></th>
                <td>
                    <p><?php _e('Generate a comprehensive system report for troubleshooting.', 'spiralengine'); ?></p>
                    <button type="button" class="button button-primary" id="generate-system-report">
                        <?php _e('Generate System Report', 'spiralengine'); ?>
                    </button>
                    <button type="button" class="button button-secondary" id="copy-system-report" style="display: none;">
                        <?php _e('Copy to Clipboard', 'spiralengine'); ?>
                    </button>
                    <div id="system-report-container" style="display: none; margin-top: 10px;">
                        <textarea id="system-report" class="large-text code" rows="10" readonly></textarea>
                    </div>
                </td>
            </tr>
        </tbody>
    </table>

    <!-- Scheduled Tasks -->
    <h3><?php _e('Scheduled Tasks', 'spiralengine'); ?></h3>
    <?php
    $cron_jobs = spiralengine_get_cron_jobs();
    ?>
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th><?php _e('Task', 'spiralengine'); ?></th>
                <th><?php _e('Schedule', 'spiralengine'); ?></th>
                <th><?php _e('Next Run', 'spiralengine'); ?></th>
                <th><?php _e('Actions', 'spiralengine'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($cron_jobs as $job): ?>
            <tr>
                <td><?php echo esc_html($job['name']); ?></td>
                <td><?php echo esc_html($job['schedule']); ?></td>
                <td><?php echo esc_html($job['next_run']); ?></td>
                <td>
                    <button type="button" class="button button-small run-cron" data-hook="<?php echo esc_attr($job['hook']); ?>">
                        <?php _e('Run Now', 'spiralengine'); ?>
                    </button>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <script>
    jQuery(document).ready(function($) {
        // Run health check
        $('#run-health-check').on('click', function() {
            var $button = $(this);
            var $spinner = $button.siblings('.spinner');
            
            $button.prop('disabled', true);
            $spinner.addClass('is-active');
            
            $.post(ajaxurl, {
                action: 'spiralengine_run_health_check',
                nonce: '<?php echo wp_create_nonce('spiralengine_tools'); ?>'
            }, function(response) {
                location.reload();
            });
        });
        
        // Export all data
        $('#export-all-data').on('click', function() {
            if (confirm('<?php echo esc_js(__('This will export all SpiralEngine data. Continue?', 'spiralengine')); ?>')) {
                window.location.href = ajaxurl + '?action=spiralengine_export_all&nonce=<?php echo wp_create_nonce('spiralengine_export'); ?>';
            }
        });
        
        // Import data
        $('#import-file').on('change', function() {
            $('#import-data').prop('disabled', !this.files.length);
        });
        
        $('#import-data').on('click', function() {
            if (!confirm('<?php echo esc_js(__('This will overwrite existing data. Are you sure?', 'spiralengine')); ?>')) {
                return;
            }
            
            var file = $('#import-file')[0].files[0];
            var formData = new FormData();
            formData.append('action', 'spiralengine_import_data');
            formData.append('nonce', '<?php echo wp_create_nonce('spiralengine_import'); ?>');
            formData.append('import_file', file);
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        alert(response.data.message);
                        location.reload();
                    } else {
                        alert('Error: ' + response.data.message);
                    }
                }
            });
        });
        
        // Reset data options
        $('.reset-option').on('change', function() {
            $('#reset-data').prop('disabled', !$('.reset-option:checked').length);
            
            if ($(this).val() === 'all' && $(this).prop('checked')) {
                $('.reset-option').not(this).prop('checked', true);
            }
        });
        
        $('#reset-data').on('click', function() {
            var selected = $('.reset-option:checked').map(function() {
                return $(this).val();
            }).get();
            
            var message = '<?php echo esc_js(__('This will permanently delete the selected data:', 'spiralengine')); ?>\n\n';
            message += selected.join(', ') + '\n\n';
            message += '<?php echo esc_js(__('This action cannot be undone. Type DELETE to confirm:', 'spiralengine')); ?>';
            
            var confirmation = prompt(message);
            if (confirmation !== 'DELETE') {
                return;
            }
            
            $.post(ajaxurl, {
                action: 'spiralengine_reset_data',
                nonce: '<?php echo wp_create_nonce('spiralengine_reset'); ?>',
                data_types: selected
            }, function(response) {
                if (response.success) {
                    alert(response.data.message);
                    location.reload();
                } else {
                    alert('Error: ' + response.data.message);
                }
            });
        });
        
        // Clear cache
        $('.clear-cache').on('click', function() {
            var $button = $(this);
            var cacheType = $button.data('cache');
            
            $button.prop('disabled', true);
            
            $.post(ajaxurl, {
                action: 'spiralengine_clear_cache',
                cache_type: cacheType,
                nonce: '<?php echo wp_create_nonce('spiralengine_cache'); ?>'
            }, function(response) {
                $button.prop('disabled', false);
                $('.cache-result').html(response.data.message).show();
                
                setTimeout(function() {
                    $('.cache-result').fadeOut();
                }, 3000);
            });
        });
        
        // Database operations
        $('.db-operation').on('click', function() {
            var $button = $(this);
            var operation = $button.data('operation');
            
            $button.prop('disabled', true);
            
            $.post(ajaxurl, {
                action: 'spiralengine_db_operation',
                operation: operation,
                nonce: '<?php echo wp_create_nonce('spiralengine_db'); ?>'
            }, function(response) {
                $button.prop('disabled', false);
                $('.db-result').html(response.data.message).show();
                
                setTimeout(function() {
                    $('.db-result').fadeOut();
                }, 5000);
            });
        });
        
        // Database cleanup
        $('#run-cleanup').on('click', function() {
            var options = {
                orphaned: $('#cleanup-orphaned').prop('checked'),
                expired: $('#cleanup-expired').prop('checked'),
                revisions: $('#cleanup-revisions').prop('checked'),
                logs: $('#cleanup-logs').prop('checked')
            };
            
            $(this).prop('disabled', true);
            
            $.post(ajaxurl, {
                action: 'spiralengine_db_cleanup',
                options: options,
                nonce: '<?php echo wp_create_nonce('spiralengine_cleanup'); ?>'
            }, function(response) {
                alert(response.data.message);
                location.reload();
            });
        });
        
        // Migration
        $('#migration-source').on('change', function() {
            $('#start-migration').prop('disabled', !$(this).val());
        });
        
        $('#start-migration').on('click', function() {
            var source = $('#migration-source').val();
            window.location.href = 'admin.php?page=spiralengine-migration&source=' + source;
        });
        
        // Export for migration
        $('#export-for-migration').on('click', function() {
            var format = $('#export-format').val();
            window.location.href = ajaxurl + '?action=spiralengine_export_migration&format=' + format + '&nonce=<?php echo wp_create_nonce('spiralengine_export'); ?>';
        });
        
        // Debug log
        $('#view-debug-log').on('click', function() {
            window.open(ajaxurl + '?action=spiralengine_view_debug_log&nonce=<?php echo wp_create_nonce('spiralengine_debug'); ?>', 'debug_log', 'width=800,height=600');
        });
        
        $('#download-debug-log').on('click', function() {
            window.location.href = ajaxurl + '?action=spiralengine_download_debug_log&nonce=<?php echo wp_create_nonce('spiralengine_debug'); ?>';
        });
        
        $('#clear-debug-log').on('click', function() {
            if (confirm('<?php echo esc_js(__('Clear the debug log?', 'spiralengine')); ?>')) {
                $.post(ajaxurl, {
                    action: 'spiralengine_clear_debug_log',
                    nonce: '<?php echo wp_create_nonce('spiralengine_debug'); ?>'
                }, function(response) {
                    alert(response.data.message);
                    location.reload();
                });
            }
        });
        
        // System report
        $('#generate-system-report').on('click', function() {
            $(this).prop('disabled', true);
            
            $.post(ajaxurl, {
                action: 'spiralengine_generate_system_report',
                nonce: '<?php echo wp_create_nonce('spiralengine_report'); ?>'
            }, function(response) {
                $('#generate-system-report').prop('disabled', false);
                $('#system-report').val(response.data.report);
                $('#system-report-container').show();
                $('#copy-system-report').show();
            });
        });
        
        $('#copy-system-report').on('click', function() {
            $('#system-report').select();
            document.execCommand('copy');
            $(this).text('<?php echo esc_js(__('Copied!', 'spiralengine')); ?>');
            
            setTimeout(function() {
                $('#copy-system-report').text('<?php echo esc_js(__('Copy to Clipboard', 'spiralengine')); ?>');
            }, 2000);
        });
        
        // Run cron job
        $('.run-cron').on('click', function() {
            var $button = $(this);
            var hook = $button.data('hook');
            
            $button.prop('disabled', true).text('<?php echo esc_js(__('Running...', 'spiralengine')); ?>');
            
            $.post(ajaxurl, {
                action: 'spiralengine_run_cron',
                hook: hook,
                nonce: '<?php echo wp_create_nonce('spiralengine_cron'); ?>'
            }, function(response) {
                $button.prop('disabled', false).text('<?php echo esc_js(__('Run Now', 'spiralengine')); ?>');
                alert(response.data.message);
            });
        });
    });
    </script>
</div>


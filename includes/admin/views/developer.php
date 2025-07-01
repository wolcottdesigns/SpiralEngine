<?php
/**
 * Admin Developer View
 * 
 * @package    SpiralEngine
 * @subpackage Admin/Views
 * @file       includes/admin/views/developer.php
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Check if developer mode is enabled
$developer_mode = get_option('spiralengine_developer_mode', false);
if (!$developer_mode && !current_user_can('manage_options')) {
    wp_die(__('Developer mode is not enabled.', 'spiralengine'));
}

// Get developer data
$api_stats = spiralengine_get_api_statistics();
$webhook_logs = spiralengine_get_webhook_logs(10);
$error_logs = spiralengine_get_error_logs(20);
$performance_data = spiralengine_get_performance_data();
?>

<div class="wrap spiralengine-admin-developer">
    <h1><?php _e('Developer Tools', 'spiralengine'); ?></h1>
    
    <div class="spiralengine-developer-notice">
        <p><strong><?php _e('Warning:', 'spiralengine'); ?></strong> <?php _e('These tools are for development and debugging purposes. Use with caution on production sites.', 'spiralengine'); ?></p>
    </div>

    <!-- Developer Stats -->
    <div class="spiralengine-dev-stats">
        <div class="dev-stat-box">
            <div class="stat-value"><?php echo number_format($api_stats['total_calls']); ?></div>
            <div class="stat-label"><?php _e('API Calls', 'spiralengine'); ?></div>
            <div class="stat-meta"><?php _e('Last 24 hours', 'spiralengine'); ?></div>
        </div>
        
        <div class="dev-stat-box">
            <div class="stat-value"><?php echo round($performance_data['avg_response_time'], 2); ?>ms</div>
            <div class="stat-label"><?php _e('Avg Response Time', 'spiralengine'); ?></div>
            <div class="stat-meta"><?php _e('API endpoints', 'spiralengine'); ?></div>
        </div>
        
        <div class="dev-stat-box">
            <div class="stat-value"><?php echo number_format($error_logs['error_count']); ?></div>
            <div class="stat-label"><?php _e('Errors', 'spiralengine'); ?></div>
            <div class="stat-meta"><?php _e('Last 7 days', 'spiralengine'); ?></div>
        </div>
        
        <div class="dev-stat-box">
            <div class="stat-value"><?php echo spiralengine_format_bytes($performance_data['memory_usage']); ?></div>
            <div class="stat-label"><?php _e('Memory Usage', 'spiralengine'); ?></div>
            <div class="stat-meta"><?php _e('Current', 'spiralengine'); ?></div>
        </div>
    </div>

    <!-- Developer Tabs -->
    <nav class="nav-tab-wrapper wp-clearfix">
        <a href="#api" class="nav-tab nav-tab-active"><?php _e('API Testing', 'spiralengine'); ?></a>
        <a href="#webhooks" class="nav-tab"><?php _e('Webhooks', 'spiralengine'); ?></a>
        <a href="#database" class="nav-tab"><?php _e('Database', 'spiralengine'); ?></a>
        <a href="#logs" class="nav-tab"><?php _e('Error Logs', 'spiralengine'); ?></a>
        <a href="#hooks" class="nav-tab"><?php _e('Hooks & Filters', 'spiralengine'); ?></a>
        <a href="#console" class="nav-tab"><?php _e('Console', 'spiralengine'); ?></a>
    </nav>

    <!-- API Testing Tab -->
    <div id="api" class="tab-content active">
        <h2><?php _e('API Testing', 'spiralengine'); ?></h2>
        
        <div class="spiralengine-api-tester">
            <form id="api-test-form">
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="api_endpoint"><?php _e('Endpoint', 'spiralengine'); ?></label></th>
                        <td>
                            <select name="api_endpoint" id="api_endpoint" class="regular-text">
                                <optgroup label="<?php _e('Episodes', 'spiralengine'); ?>">
                                    <option value="/episodes">GET /episodes</option>
                                    <option value="/episodes" data-method="POST">POST /episodes</option>
                                    <option value="/episodes/{id}">GET /episodes/{id}</option>
                                    <option value="/episodes/{id}" data-method="PUT">PUT /episodes/{id}</option>
                                    <option value="/episodes/{id}" data-method="DELETE">DELETE /episodes/{id}</option>
                                </optgroup>
                                <optgroup label="<?php _e('Widgets', 'spiralengine'); ?>">
                                    <option value="/widgets">GET /widgets</option>
                                    <option value="/widgets/{id}">GET /widgets/{id}</option>
                                </optgroup>
                                <optgroup label="<?php _e('Analytics', 'spiralengine'); ?>">
                                    <option value="/analytics/overview">GET /analytics/overview</option>
                                    <option value="/analytics/patterns">GET /analytics/patterns</option>
                                </optgroup>
                                <optgroup label="<?php _e('AI', 'spiralengine'); ?>">
                                    <option value="/ai/analyze" data-method="POST">POST /ai/analyze</option>
                                    <option value="/ai/insights">GET /ai/insights</option>
                                </optgroup>
                            </select>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><label for="api_method"><?php _e('Method', 'spiralengine'); ?></label></th>
                        <td>
                            <select name="api_method" id="api_method">
                                <option value="GET">GET</option>
                                <option value="POST">POST</option>
                                <option value="PUT">PUT</option>
                                <option value="DELETE">DELETE</option>
                            </select>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><label for="api_params"><?php _e('Parameters', 'spiralengine'); ?></label></th>
                        <td>
                            <textarea name="api_params" id="api_params" rows="5" class="large-text code" placeholder='{"key": "value"}'></textarea>
                            <p class="description"><?php _e('JSON format for POST/PUT requests, query string for GET requests', 'spiralengine'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><label for="api_key"><?php _e('API Key', 'spiralengine'); ?></label></th>
                        <td>
                            <input type="text" name="api_key" id="api_key" class="regular-text" value="<?php echo esc_attr(spiralengine_get_test_api_key()); ?>">
                            <button type="button" class="button" id="generate-test-key"><?php _e('Generate Test Key', 'spiralengine'); ?></button>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <button type="submit" class="button button-primary"><?php _e('Send Request', 'spiralengine'); ?></button>
                    <button type="button" class="button" id="clear-response"><?php _e('Clear', 'spiralengine'); ?></button>
                </p>
            </form>
            
            <div class="api-response-section">
                <h3><?php _e('Response', 'spiralengine'); ?></h3>
                <div class="api-response-meta">
                    <span class="response-status"></span>
                    <span class="response-time"></span>
                    <span class="response-size"></span>
                </div>
                <pre id="api-response" class="api-response-body"></pre>
            </div>
        </div>
        
        <!-- API Documentation -->
        <div class="spiralengine-api-docs">
            <h3><?php _e('Quick Reference', 'spiralengine'); ?></h3>
            <div class="api-docs-grid">
                <div class="api-doc-item">
                    <h4><?php _e('Authentication', 'spiralengine'); ?></h4>
                    <code>Authorization: Bearer YOUR_API_KEY</code>
                </div>
                <div class="api-doc-item">
                    <h4><?php _e('Base URL', 'spiralengine'); ?></h4>
                    <code><?php echo home_url('/wp-json/spiralengine/v1'); ?></code>
                </div>
                <div class="api-doc-item">
                    <h4><?php _e('Rate Limits', 'spiralengine'); ?></h4>
                    <code>1000 requests/hour</code>
                </div>
                <div class="api-doc-item">
                    <h4><?php _e('Response Format', 'spiralengine'); ?></h4>
                    <code>application/json</code>
                </div>
            </div>
        </div>
    </div>

    <!-- Webhooks Tab -->
    <div id="webhooks" class="tab-content" style="display: none;">
        <h2><?php _e('Webhook Testing', 'spiralengine'); ?></h2>
        
        <div class="webhook-tester">
            <h3><?php _e('Send Test Webhook', 'spiralengine'); ?></h3>
            <form id="webhook-test-form">
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="webhook_url"><?php _e('Webhook URL', 'spiralengine'); ?></label></th>
                        <td>
                            <input type="url" name="webhook_url" id="webhook_url" class="large-text" placeholder="https://example.com/webhook">
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><label for="webhook_event"><?php _e('Event Type', 'spiralengine'); ?></label></th>
                        <td>
                            <select name="webhook_event" id="webhook_event">
                                <option value="episode.created"><?php _e('Episode Created', 'spiralengine'); ?></option>
                                <option value="episode.updated"><?php _e('Episode Updated', 'spiralengine'); ?></option>
                                <option value="user.registered"><?php _e('User Registered', 'spiralengine'); ?></option>
                                <option value="subscription.created"><?php _e('Subscription Created', 'spiralengine'); ?></option>
                                <option value="ai.analysis_complete"><?php _e('AI Analysis Complete', 'spiralengine'); ?></option>
                            </select>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><label for="webhook_payload"><?php _e('Payload', 'spiralengine'); ?></label></th>
                        <td>
                            <textarea name="webhook_payload" id="webhook_payload" rows="10" class="large-text code"></textarea>
                            <p class="description"><?php _e('Leave empty to use default test payload', 'spiralengine'); ?></p>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <button type="submit" class="button button-primary"><?php _e('Send Webhook', 'spiralengine'); ?></button>
                </p>
            </form>
        </div>
        
        <!-- Webhook Logs -->
        <div class="webhook-logs">
            <h3><?php _e('Recent Webhook Deliveries', 'spiralengine'); ?></h3>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Event', 'spiralengine'); ?></th>
                        <th><?php _e('URL', 'spiralengine'); ?></th>
                        <th><?php _e('Status', 'spiralengine'); ?></th>
                        <th><?php _e('Response Time', 'spiralengine'); ?></th>
                        <th><?php _e('Timestamp', 'spiralengine'); ?></th>
                        <th><?php _e('Actions', 'spiralengine'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($webhook_logs as $log): ?>
                    <tr>
                        <td><?php echo esc_html($log->event); ?></td>
                        <td><?php echo esc_html($log->url); ?></td>
                        <td>
                            <span class="webhook-status status-<?php echo $log->status >= 200 && $log->status < 300 ? 'success' : 'error'; ?>">
                                <?php echo esc_html($log->status); ?>
                            </span>
                        </td>
                        <td><?php echo esc_html($log->response_time); ?>ms</td>
                        <td><?php echo human_time_diff(strtotime($log->created_at), current_time('timestamp')) . ' ' . __('ago', 'spiralengine'); ?></td>
                        <td>
                            <button type="button" class="button button-small view-webhook-details" data-log-id="<?php echo esc_attr($log->id); ?>">
                                <?php _e('Details', 'spiralengine'); ?>
                            </button>
                            <button type="button" class="button button-small retry-webhook" data-log-id="<?php echo esc_attr($log->id); ?>">
                                <?php _e('Retry', 'spiralengine'); ?>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Database Tab -->
    <div id="database" class="tab-content" style="display: none;">
        <h2><?php _e('Database Inspector', 'spiralengine'); ?></h2>
        
        <div class="database-info">
            <h3><?php _e('Table Information', 'spiralengine'); ?></h3>
            <?php
            global $wpdb;
            $tables = array(
                'episodes' => $wpdb->prefix . 'spiralengine_episodes',
                'widgets' => $wpdb->prefix . 'spiralengine_widgets',
                'goals' => $wpdb->prefix . 'spiralengine_goals',
                'analytics' => $wpdb->prefix . 'spiralengine_analytics',
                'ai_insights' => $wpdb->prefix . 'spiralengine_ai_insights',
                'api_logs' => $wpdb->prefix . 'spiralengine_api_logs'
            );
            ?>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Table', 'spiralengine'); ?></th>
                        <th><?php _e('Rows', 'spiralengine'); ?></th>
                        <th><?php _e('Size', 'spiralengine'); ?></th>
                        <th><?php _e('Index Size', 'spiralengine'); ?></th>
                        <th><?php _e('Actions', 'spiralengine'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tables as $name => $table): 
                        $info = spiralengine_get_table_info($table);
                    ?>
                    <tr>
                        <td><code><?php echo esc_html($table); ?></code></td>
                        <td><?php echo number_format($info['rows']); ?></td>
                        <td><?php echo spiralengine_format_bytes($info['data_size']); ?></td>
                        <td><?php echo spiralengine_format_bytes($info['index_size']); ?></td>
                        <td>
                            <button type="button" class="button button-small view-structure" data-table="<?php echo esc_attr($table); ?>">
                                <?php _e('Structure', 'spiralengine'); ?>
                            </button>
                            <button type="button" class="button button-small query-table" data-table="<?php echo esc_attr($table); ?>">
                                <?php _e('Query', 'spiralengine'); ?>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Query Builder -->
        <div class="query-builder">
            <h3><?php _e('Query Builder', 'spiralengine'); ?></h3>
            <form id="query-form">
                <textarea name="query" id="db-query" rows="5" class="large-text code" placeholder="SELECT * FROM wp_spiralengine_episodes LIMIT 10"></textarea>
                <p class="description"><?php _e('⚠️ Be careful with UPDATE and DELETE queries!', 'spiralengine'); ?></p>
                <p class="submit">
                    <button type="submit" class="button button-primary"><?php _e('Execute Query', 'spiralengine'); ?></button>
                    <label>
                        <input type="checkbox" name="dry_run" id="dry-run" checked>
                        <?php _e('Dry run (show query without executing)', 'spiralengine'); ?>
                    </label>
                </p>
            </form>
            
            <div id="query-results" style="display: none;">
                <h4><?php _e('Results', 'spiralengine'); ?></h4>
                <div class="query-results-container"></div>
            </div>
        </div>
    </div>

    <!-- Error Logs Tab -->
    <div id="logs" class="tab-content" style="display: none;">
        <h2><?php _e('Error Logs', 'spiralengine'); ?></h2>
        
        <!-- Log Filters -->
        <div class="log-filters">
            <select id="log-level">
                <option value=""><?php _e('All Levels', 'spiralengine'); ?></option>
                <option value="error"><?php _e('Errors', 'spiralengine'); ?></option>
                <option value="warning"><?php _e('Warnings', 'spiralengine'); ?></option>
                <option value="notice"><?php _e('Notices', 'spiralengine'); ?></option>
                <option value="debug"><?php _e('Debug', 'spiralengine'); ?></option>
            </select>
            
            <select id="log-component">
                <option value=""><?php _e('All Components', 'spiralengine'); ?></option>
                <option value="api"><?php _e('API', 'spiralengine'); ?></option>
                <option value="webhook"><?php _e('Webhooks', 'spiralengine'); ?></option>
                <option value="ai"><?php _e('AI', 'spiralengine'); ?></option>
                <option value="payment"><?php _e('Payments', 'spiralengine'); ?></option>
                <option value="widget"><?php _e('Widgets', 'spiralengine'); ?></option>
            </select>
            
            <button type="button" class="button" id="refresh-logs"><?php _e('Refresh', 'spiralengine'); ?></button>
            <button type="button" class="button" id="clear-logs"><?php _e('Clear Logs', 'spiralengine'); ?></button>
        </div>
        
        <!-- Error Log Table -->
        <table class="wp-list-table widefat fixed striped" id="error-log-table">
            <thead>
                <tr>
                    <th><?php _e('Level', 'spiralengine'); ?></th>
                    <th><?php _e('Component', 'spiralengine'); ?></th>
                    <th><?php _e('Message', 'spiralengine'); ?></th>
                    <th><?php _e('File', 'spiralengine'); ?></th>
                    <th><?php _e('Time', 'spiralengine'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($error_logs['logs'] as $log): ?>
                <tr class="log-<?php echo esc_attr($log->level); ?>">
                    <td>
                        <span class="log-level level-<?php echo esc_attr($log->level); ?>">
                            <?php echo esc_html(strtoupper($log->level)); ?>
                        </span>
                    </td>
                    <td><?php echo esc_html($log->component); ?></td>
                    <td>
                        <?php echo esc_html($log->message); ?>
                        <?php if ($log->context): ?>
                        <button type="button" class="button button-small view-context" data-context='<?php echo esc_attr(json_encode($log->context)); ?>'>
                            <?php _e('Context', 'spiralengine'); ?>
                        </button>
                        <?php endif; ?>
                    </td>
                    <td>
                        <code><?php echo esc_html($log->file); ?>:<?php echo esc_html($log->line); ?></code>
                    </td>
                    <td><?php echo human_time_diff(strtotime($log->created_at), current_time('timestamp')) . ' ' . __('ago', 'spiralengine'); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Hooks & Filters Tab -->
    <div id="hooks" class="tab-content" style="display: none;">
        <h2><?php _e('Hooks & Filters', 'spiralengine'); ?></h2>
        
        <div class="hooks-info">
            <h3><?php _e('Available Hooks', 'spiralengine'); ?></h3>
            <?php
            $hooks = spiralengine_get_available_hooks();
            ?>
            
            <div class="hooks-grid">
                <?php foreach ($hooks as $category => $category_hooks): ?>
                <div class="hook-category">
                    <h4><?php echo esc_html(ucfirst($category)); ?></h4>
                    <div class="hook-list">
                        <?php foreach ($category_hooks as $hook): ?>
                        <div class="hook-item">
                            <code><?php echo esc_html($hook['name']); ?></code>
                            <span class="hook-type <?php echo esc_attr($hook['type']); ?>">
                                <?php echo esc_html($hook['type']); ?>
                            </span>
                            <p><?php echo esc_html($hook['description']); ?></p>
                            <?php if ($hook['params']): ?>
                            <span class="hook-params"><?php _e('Parameters:', 'spiralengine'); ?> <?php echo esc_html($hook['params']); ?></span>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- Hook Tester -->
        <div class="hook-tester">
            <h3><?php _e('Hook Tester', 'spiralengine'); ?></h3>
            <form id="hook-test-form">
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="test_hook"><?php _e('Hook Name', 'spiralengine'); ?></label></th>
                        <td>
                            <input type="text" name="test_hook" id="test_hook" class="regular-text" placeholder="spiralengine_after_episode_saved">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="test_params"><?php _e('Parameters', 'spiralengine'); ?></label></th>
                        <td>
                            <textarea name="test_params" id="test_params" rows="3" class="large-text code" placeholder='["param1", "param2"]'></textarea>
                        </td>
                    </tr>
                </table>
                <p class="submit">
                    <button type="submit" class="button button-primary"><?php _e('Trigger Hook', 'spiralengine'); ?></button>
                </p>
            </form>
        </div>
    </div>

    <!-- Console Tab -->
    <div id="console" class="tab-content" style="display: none;">
        <h2><?php _e('Developer Console', 'spiralengine'); ?></h2>
        
        <div class="dev-console">
            <div class="console-output" id="console-output"></div>
            <form id="console-form">
                <div class="console-input-wrapper">
                    <span class="console-prompt">&gt;</span>
                    <input type="text" id="console-input" class="console-input" placeholder="<?php esc_attr_e('Enter PHP code...', 'spiralengine'); ?>" autocomplete="off">
                </div>
            </form>
        </div>
        
        <div class="console-help">
            <h4><?php _e('Available Functions', 'spiralengine'); ?></h4>
            <ul>
                <li><code>spiralengine_get_episode($id)</code> - <?php _e('Get episode by ID', 'spiralengine'); ?></li>
                <li><code>spiralengine_get_widget($id)</code> - <?php _e('Get widget by ID', 'spiralengine'); ?></li>
                <li><code>spiralengine_get_user_stats($user_id)</code> - <?php _e('Get user statistics', 'spiralengine'); ?></li>
                <li><code>spiralengine_debug($data)</code> - <?php _e('Debug output', 'spiralengine'); ?></li>
            </ul>
        </div>
    </div>
</div>

<!-- Context Modal -->
<div id="context-modal" class="spiralengine-modal" style="display: none;">
    <div class="spiralengine-modal-content">
        <div class="spiralengine-modal-header">
            <h3><?php _e('Error Context', 'spiralengine'); ?></h3>
            <button type="button" class="spiralengine-modal-close">&times;</button>
        </div>
        <div class="spiralengine-modal-body">
            <pre id="context-data"></pre>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Tab switching
    $('.nav-tab').on('click', function(e) {
        e.preventDefault();
        var target = $(this).attr('href').substring(1);
        
        $('.nav-tab').removeClass('nav-tab-active');
        $(this).addClass('nav-tab-active');
        
        $('.tab-content').hide();
        $('#' + target).show();
    });
    
    // API Testing
    $('#api_endpoint').on('change', function() {
        var method = $(this).find(':selected').data('method') || 'GET';
        $('#api_method').val(method);
    });
    
    $('#api-test-form').on('submit', function(e) {
        e.preventDefault();
        
        var endpoint = $('#api_endpoint').val();
        var method = $('#api_method').val();
        var params = $('#api_params').val();
        var apiKey = $('#api_key').val();
        
        var startTime = Date.now();
        
        $.ajax({
            url: '<?php echo home_url('/wp-json/spiralengine/v1'); ?>' + endpoint,
            method: method,
            headers: {
                'Authorization': 'Bearer ' + apiKey,
                'Content-Type': 'application/json'
            },
            data: method !== 'GET' ? params : null,
            dataType: 'json',
            complete: function(xhr) {
                var endTime = Date.now();
                var responseTime = endTime - startTime;
                
                $('.response-status').text('Status: ' + xhr.status + ' ' + xhr.statusText);
                $('.response-time').text('Time: ' + responseTime + 'ms');
                $('.response-size').text('Size: ' + (xhr.responseText.length / 1024).toFixed(2) + 'KB');
                
                try {
                    var json = JSON.parse(xhr.responseText);
                    $('#api-response').text(JSON.stringify(json, null, 2));
                } catch(e) {
                    $('#api-response').text(xhr.responseText);
                }
            }
        });
    });
    
    $('#clear-response').on('click', function() {
        $('#api-response').empty();
        $('.response-status, .response-time, .response-size').empty();
    });
    
    // Generate test API key
    $('#generate-test-key').on('click', function() {
        $.post(ajaxurl, {
            action: 'spiralengine_generate_test_key',
            nonce: '<?php echo wp_create_nonce('spiralengine_dev'); ?>'
        }, function(response) {
            if (response.success) {
                $('#api_key').val(response.data.key);
            }
        });
    });
    
    // Webhook testing
    $('#webhook_event').on('change', function() {
        var event = $(this).val();
        var payload = spiralengine_get_sample_payload(event);
        $('#webhook_payload').val(JSON.stringify(payload, null, 2));
    });
    
    $('#webhook-test-form').on('submit', function(e) {
        e.preventDefault();
        
        $.post(ajaxurl, {
            action: 'spiralengine_test_webhook',
            url: $('#webhook_url').val(),
            event: $('#webhook_event').val(),
            payload: $('#webhook_payload').val(),
            nonce: '<?php echo wp_create_nonce('spiralengine_dev'); ?>'
        }, function(response) {
            if (response.success) {
                alert('Webhook sent successfully! Response: ' + response.data.status);
            } else {
                alert('Webhook failed: ' + response.data.message);
            }
        });
    });
    
    // View webhook details
    $('.view-webhook-details').on('click', function() {
        var logId = $(this).data('log-id');
        
        $.post(ajaxurl, {
            action: 'spiralengine_get_webhook_details',
            log_id: logId,
            nonce: '<?php echo wp_create_nonce('spiralengine_dev'); ?>'
        }, function(response) {
            if (response.success) {
                $('#context-data').text(JSON.stringify(response.data, null, 2));
                $('#context-modal').show();
            }
        });
    });
    
    // Database query
    $('#query-form').on('submit', function(e) {
        e.preventDefault();
        
        var query = $('#db-query').val();
        var dryRun = $('#dry-run').prop('checked');
        
        $.post(ajaxurl, {
            action: 'spiralengine_execute_query',
            query: query,
            dry_run: dryRun,
            nonce: '<?php echo wp_create_nonce('spiralengine_dev'); ?>'
        }, function(response) {
            if (response.success) {
                $('.query-results-container').html(response.data.html);
                $('#query-results').show();
            } else {
                alert('Query error: ' + response.data.message);
            }
        });
    });
    
    // View table structure
    $('.view-structure').on('click', function() {
        var table = $(this).data('table');
        
        $.post(ajaxurl, {
            action: 'spiralengine_get_table_structure',
            table: table,
            nonce: '<?php echo wp_create_nonce('spiralengine_dev'); ?>'
        }, function(response) {
            if (response.success) {
                $('.query-results-container').html(response.data.html);
                $('#query-results').show();
            }
        });
    });
    
    // Error log filters
    $('#log-level, #log-component').on('change', function() {
        var level = $('#log-level').val();
        var component = $('#log-component').val();
        
        $('#error-log-table tbody tr').each(function() {
            var show = true;
            
            if (level && !$(this).hasClass('log-' + level)) {
                show = false;
            }
            
            if (component && $(this).find('td:eq(1)').text().toLowerCase() !== component) {
                show = false;
            }
            
            $(this).toggle(show);
        });
    });
    
    // View error context
    $('.view-context').on('click', function() {
        var context = $(this).data('context');
        $('#context-data').text(JSON.stringify(context, null, 2));
        $('#context-modal').show();
    });
    
    // Clear logs
    $('#clear-logs').on('click', function() {
        if (confirm('<?php echo esc_js(__('Clear all error logs?', 'spiralengine')); ?>')) {
            $.post(ajaxurl, {
                action: 'spiralengine_clear_error_logs',
                nonce: '<?php echo wp_create_nonce('spiralengine_dev'); ?>'
            }, function(response) {
                if (response.success) {
                    location.reload();
                }
            });
        }
    });
    
    // Developer console
    var consoleHistory = [];
    var historyIndex = -1;
    
    $('#console-form').on('submit', function(e) {
        e.preventDefault();
        
        var command = $('#console-input').val();
        if (!command) return;
        
        consoleHistory.push(command);
        historyIndex = consoleHistory.length;
        
        $('#console-output').append('<div class="console-command">&gt; ' + escapeHtml(command) + '</div>');
        
        $.post(ajaxurl, {
            action: 'spiralengine_execute_console',
            command: command,
            nonce: '<?php echo wp_create_nonce('spiralengine_dev'); ?>'
        }, function(response) {
            if (response.success) {
                $('#console-output').append('<div class="console-result">' + response.data.output + '</div>');
            } else {
                $('#console-output').append('<div class="console-error">' + response.data.message + '</div>');
            }
            
            $('#console-output').scrollTop($('#console-output')[0].scrollHeight);
        });
        
        $('#console-input').val('');
    });
    
    // Console history navigation
    $('#console-input').on('keydown', function(e) {
        if (e.key === 'ArrowUp') {
            e.preventDefault();
            if (historyIndex > 0) {
                historyIndex--;
                $(this).val(consoleHistory[historyIndex]);
            }
        } else if (e.key === 'ArrowDown') {
            e.preventDefault();
            if (historyIndex < consoleHistory.length - 1) {
                historyIndex++;
                $(this).val(consoleHistory[historyIndex]);
            } else {
                historyIndex = consoleHistory.length;
                $(this).val('');
            }
        }
    });
    
    // Modal close
    $('.spiralengine-modal-close').on('click', function() {
        $(this).closest('.spiralengine-modal').hide();
    });
    
    // Helper functions
    function escapeHtml(text) {
        var map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        
        return text.replace(/[&<>"']/g, function(m) { return map[m]; });
    }
    
    function spiralengine_get_sample_payload(event) {
        var payloads = {
            'episode.created': {
                id: 123,
                user_id: 1,
                widget_id: 'mood-tracker',
                severity: 7,
                data: {
                    mood: 'anxious',
                    triggers: ['work', 'social'],
                    notes: 'Feeling overwhelmed today'
                },
                created_at: new Date().toISOString()
            },
            'user.registered': {
                id: 456,
                email: 'user@example.com',
                tier: 'free',
                registered_at: new Date().toISOString()
            },
            'subscription.created': {
                id: 789,
                user_id: 1,
                tier: 'gold',
                amount: 1999,
                currency: 'USD',
                created_at: new Date().toISOString()
            }
        };
        
        return payloads[event] || {};
    }
});
</script>


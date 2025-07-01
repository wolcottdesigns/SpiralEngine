<?php
/**
 * Admin Analytics View
 * 
 * @package    SpiralEngine
 * @subpackage Admin/Views
 * @file       includes/admin/views/analytics.php
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Get date range
$date_range = isset($_GET['range']) ? sanitize_text_field($_GET['range']) : '30days';
$custom_start = isset($_GET['start']) ? sanitize_text_field($_GET['start']) : '';
$custom_end = isset($_GET['end']) ? sanitize_text_field($_GET['end']) : '';

// Calculate date range
switch ($date_range) {
    case '7days':
        $start_date = date('Y-m-d', strtotime('-7 days'));
        $end_date = date('Y-m-d');
        break;
    case '30days':
        $start_date = date('Y-m-d', strtotime('-30 days'));
        $end_date = date('Y-m-d');
        break;
    case '90days':
        $start_date = date('Y-m-d', strtotime('-90 days'));
        $end_date = date('Y-m-d');
        break;
    case 'year':
        $start_date = date('Y-m-d', strtotime('-1 year'));
        $end_date = date('Y-m-d');
        break;
    case 'custom':
        $start_date = $custom_start ?: date('Y-m-d', strtotime('-30 days'));
        $end_date = $custom_end ?: date('Y-m-d');
        break;
    default:
        $start_date = date('Y-m-d', strtotime('-30 days'));
        $end_date = date('Y-m-d');
}

// Get analytics data
$analytics = spiralengine_get_analytics_data($start_date, $end_date);
$previous_analytics = spiralengine_get_analytics_data(
    date('Y-m-d', strtotime($start_date . ' -' . (strtotime($end_date) - strtotime($start_date)) / 86400 . ' days')),
    $start_date
);
?>

<div class="wrap spiralengine-admin-analytics">
    <h1><?php _e('Analytics', 'spiralengine'); ?></h1>
    
    <!-- Date Range Selector -->
    <div class="spiralengine-analytics-header">
        <form method="get" class="spiralengine-date-range-form">
            <input type="hidden" name="page" value="spiralengine-analytics">
            
            <select name="range" id="date-range-select" class="spiralengine-select">
                <option value="7days" <?php selected($date_range, '7days'); ?>><?php _e('Last 7 Days', 'spiralengine'); ?></option>
                <option value="30days" <?php selected($date_range, '30days'); ?>><?php _e('Last 30 Days', 'spiralengine'); ?></option>
                <option value="90days" <?php selected($date_range, '90days'); ?>><?php _e('Last 90 Days', 'spiralengine'); ?></option>
                <option value="year" <?php selected($date_range, 'year'); ?>><?php _e('Last Year', 'spiralengine'); ?></option>
                <option value="custom" <?php selected($date_range, 'custom'); ?>><?php _e('Custom Range', 'spiralengine'); ?></option>
            </select>
            
            <div class="custom-date-range" style="<?php echo $date_range !== 'custom' ? 'display:none;' : ''; ?>">
                <input type="date" name="start" value="<?php echo esc_attr($start_date); ?>" max="<?php echo date('Y-m-d'); ?>">
                <span><?php _e('to', 'spiralengine'); ?></span>
                <input type="date" name="end" value="<?php echo esc_attr($end_date); ?>" max="<?php echo date('Y-m-d'); ?>">
            </div>
            
            <button type="submit" class="button button-primary"><?php _e('Update', 'spiralengine'); ?></button>
            
            <div class="spiralengine-export-buttons">
                <button type="button" class="button" id="export-pdf">
                    <span class="dashicons dashicons-pdf"></span> <?php _e('Export PDF', 'spiralengine'); ?>
                </button>
                <button type="button" class="button" id="export-csv">
                    <span class="dashicons dashicons-media-spreadsheet"></span> <?php _e('Export CSV', 'spiralengine'); ?>
                </button>
            </div>
        </form>
    </div>

    <!-- Key Metrics -->
    <div class="spiralengine-key-metrics">
        <div class="metric-card">
            <h3><?php _e('Total Episodes', 'spiralengine'); ?></h3>
            <div class="metric-value"><?php echo number_format($analytics['total_episodes']); ?></div>
            <div class="metric-change <?php echo $analytics['episode_change'] >= 0 ? 'positive' : 'negative'; ?>">
                <?php 
                $change = round($analytics['episode_change'], 1);
                echo ($change >= 0 ? '+' : '') . $change . '%';
                ?>
                <span><?php _e('vs previous period', 'spiralengine'); ?></span>
            </div>
        </div>
        
        <div class="metric-card">
            <h3><?php _e('Active Users', 'spiralengine'); ?></h3>
            <div class="metric-value"><?php echo number_format($analytics['active_users']); ?></div>
            <div class="metric-change <?php echo $analytics['user_change'] >= 0 ? 'positive' : 'negative'; ?>">
                <?php 
                $change = round($analytics['user_change'], 1);
                echo ($change >= 0 ? '+' : '') . $change . '%';
                ?>
                <span><?php _e('vs previous period', 'spiralengine'); ?></span>
            </div>
        </div>
        
        <div class="metric-card">
            <h3><?php _e('Avg Episodes/User', 'spiralengine'); ?></h3>
            <div class="metric-value"><?php echo number_format($analytics['avg_episodes_per_user'], 1); ?></div>
            <div class="metric-change <?php echo $analytics['engagement_change'] >= 0 ? 'positive' : 'negative'; ?>">
                <?php 
                $change = round($analytics['engagement_change'], 1);
                echo ($change >= 0 ? '+' : '') . $change . '%';
                ?>
                <span><?php _e('engagement', 'spiralengine'); ?></span>
            </div>
        </div>
        
        <div class="metric-card">
            <h3><?php _e('Avg Severity', 'spiralengine'); ?></h3>
            <div class="metric-value"><?php echo number_format($analytics['avg_severity'], 1); ?></div>
            <div class="metric-subtext">
                <?php _e('Scale: 1-10', 'spiralengine'); ?>
            </div>
        </div>
    </div>

    <!-- Charts Section -->
    <div class="spiralengine-analytics-charts">
        <!-- Episode Trends -->
        <div class="analytics-chart-container full-width">
            <h2><?php _e('Episode Trends', 'spiralengine'); ?></h2>
            <div class="chart-controls">
                <button type="button" class="chart-type active" data-chart="episodes" data-type="line"><?php _e('Line', 'spiralengine'); ?></button>
                <button type="button" class="chart-type" data-chart="episodes" data-type="bar"><?php _e('Bar', 'spiralengine'); ?></button>
                <button type="button" class="chart-type" data-chart="episodes" data-type="area"><?php _e('Area', 'spiralengine'); ?></button>
            </div>
            <canvas id="episodes-chart"></canvas>
        </div>

        <!-- Widget Usage -->
        <div class="analytics-chart-container half-width">
            <h2><?php _e('Widget Usage', 'spiralengine'); ?></h2>
            <canvas id="widget-usage-chart"></canvas>
        </div>

        <!-- Severity Distribution -->
        <div class="analytics-chart-container half-width">
            <h2><?php _e('Severity Distribution', 'spiralengine'); ?></h2>
            <canvas id="severity-chart"></canvas>
        </div>

        <!-- Time of Day Analysis -->
        <div class="analytics-chart-container half-width">
            <h2><?php _e('Time of Day Analysis', 'spiralengine'); ?></h2>
            <canvas id="time-chart"></canvas>
        </div>

        <!-- Day of Week Analysis -->
        <div class="analytics-chart-container half-width">
            <h2><?php _e('Day of Week Patterns', 'spiralengine'); ?></h2>
            <canvas id="weekday-chart"></canvas>
        </div>
    </div>

    <!-- Detailed Tables -->
    <div class="spiralengine-analytics-tables">
        <!-- Top Users -->
        <div class="analytics-table-container">
            <h2><?php _e('Top Active Users', 'spiralengine'); ?></h2>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('User', 'spiralengine'); ?></th>
                        <th><?php _e('Episodes', 'spiralengine'); ?></th>
                        <th><?php _e('Avg Severity', 'spiralengine'); ?></th>
                        <th><?php _e('Last Active', 'spiralengine'); ?></th>
                        <th><?php _e('Membership', 'spiralengine'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($analytics['top_users'] as $user): ?>
                    <tr>
                        <td>
                            <a href="<?php echo admin_url('admin.php?page=spiralengine-users&action=view&id=' . $user->ID); ?>">
                                <?php echo esc_html($user->display_name); ?>
                            </a>
                        </td>
                        <td><?php echo number_format($user->episode_count); ?></td>
                        <td><?php echo number_format($user->avg_severity, 1); ?></td>
                        <td><?php echo human_time_diff(strtotime($user->last_active), current_time('timestamp')) . ' ' . __('ago', 'spiralengine'); ?></td>
                        <td><span class="tier-badge tier-<?php echo esc_attr($user->tier); ?>"><?php echo esc_html(ucfirst($user->tier)); ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Common Triggers -->
        <div class="analytics-table-container">
            <h2><?php _e('Common Triggers', 'spiralengine'); ?></h2>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Trigger', 'spiralengine'); ?></th>
                        <th><?php _e('Occurrences', 'spiralengine'); ?></th>
                        <th><?php _e('Avg Severity', 'spiralengine'); ?></th>
                        <th><?php _e('Trend', 'spiralengine'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($analytics['top_triggers'] as $trigger): ?>
                    <tr>
                        <td><?php echo esc_html($trigger->name); ?></td>
                        <td><?php echo number_format($trigger->count); ?></td>
                        <td><?php echo number_format($trigger->avg_severity, 1); ?></td>
                        <td>
                            <span class="trend-indicator <?php echo $trigger->trend > 0 ? 'trending-up' : 'trending-down'; ?>">
                                <?php echo $trigger->trend > 0 ? '↑' : '↓'; ?> <?php echo abs($trigger->trend); ?>%
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Insights Section -->
    <div class="spiralengine-analytics-insights">
        <h2><?php _e('Key Insights', 'spiralengine'); ?></h2>
        <div class="insights-grid">
            <?php foreach ($analytics['insights'] as $insight): ?>
            <div class="insight-card">
                <div class="insight-icon">
                    <?php echo spiralengine_get_insight_icon($insight->type); ?>
                </div>
                <div class="insight-content">
                    <h4><?php echo esc_html($insight->title); ?></h4>
                    <p><?php echo esc_html($insight->description); ?></p>
                    <?php if ($insight->action): ?>
                    <a href="<?php echo esc_url($insight->action_url); ?>" class="button button-small">
                        <?php echo esc_html($insight->action); ?>
                    </a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Date range selector
    $('#date-range-select').on('change', function() {
        if ($(this).val() === 'custom') {
            $('.custom-date-range').show();
        } else {
            $('.custom-date-range').hide();
            $(this).closest('form').submit();
        }
    });
    
    // Chart data
    var chartData = <?php echo json_encode($analytics['chart_data']); ?>;
    
    // Episode Trends Chart
    var episodesCtx = document.getElementById('episodes-chart').getContext('2d');
    var episodesChart = new Chart(episodesCtx, {
        type: 'line',
        data: {
            labels: chartData.dates,
            datasets: [{
                label: '<?php echo esc_js(__('Episodes', 'spiralengine')); ?>',
                data: chartData.episodes,
                borderColor: '#4A90E2',
                backgroundColor: 'rgba(74, 144, 226, 0.1)',
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });
    
    // Widget Usage Chart
    var widgetCtx = document.getElementById('widget-usage-chart').getContext('2d');
    var widgetChart = new Chart(widgetCtx, {
        type: 'doughnut',
        data: {
            labels: chartData.widget_names,
            datasets: [{
                data: chartData.widget_usage,
                backgroundColor: ['#4A90E2', '#7ED321', '#F5A623', '#BD10E0', '#50E3C2']
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom'
                }
            }
        }
    });
    
    // Severity Distribution Chart
    var severityCtx = document.getElementById('severity-chart').getContext('2d');
    var severityChart = new Chart(severityCtx, {
        type: 'bar',
        data: {
            labels: ['1', '2', '3', '4', '5', '6', '7', '8', '9', '10'],
            datasets: [{
                label: '<?php echo esc_js(__('Episodes', 'spiralengine')); ?>',
                data: chartData.severity_distribution,
                backgroundColor: '#4A90E2'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });
    
    // Time of Day Chart
    var timeCtx = document.getElementById('time-chart').getContext('2d');
    var timeChart = new Chart(timeCtx, {
        type: 'radar',
        data: {
            labels: chartData.hours,
            datasets: [{
                label: '<?php echo esc_js(__('Episodes', 'spiralengine')); ?>',
                data: chartData.time_distribution,
                borderColor: '#4A90E2',
                backgroundColor: 'rgba(74, 144, 226, 0.2)'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false
        }
    });
    
    // Day of Week Chart
    var weekdayCtx = document.getElementById('weekday-chart').getContext('2d');
    var weekdayChart = new Chart(weekdayCtx, {
        type: 'bar',
        data: {
            labels: ['<?php echo esc_js(__('Mon', 'spiralengine')); ?>', '<?php echo esc_js(__('Tue', 'spiralengine')); ?>', '<?php echo esc_js(__('Wed', 'spiralengine')); ?>', '<?php echo esc_js(__('Thu', 'spiralengine')); ?>', '<?php echo esc_js(__('Fri', 'spiralengine')); ?>', '<?php echo esc_js(__('Sat', 'spiralengine')); ?>', '<?php echo esc_js(__('Sun', 'spiralengine')); ?>'],
            datasets: [{
                label: '<?php echo esc_js(__('Average Episodes', 'spiralengine')); ?>',
                data: chartData.weekday_distribution,
                backgroundColor: '#7ED321'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });
    
    // Chart type switcher
    $('.chart-type').on('click', function() {
        var chart = $(this).data('chart');
        var type = $(this).data('type');
        
        $(this).siblings().removeClass('active');
        $(this).addClass('active');
        
        if (chart === 'episodes') {
            episodesChart.config.type = type;
            episodesChart.update();
        }
    });
    
    // Export functionality
    $('#export-pdf').on('click', function() {
        window.location.href = ajaxurl + '?action=spiralengine_export_analytics_pdf&start=' + '<?php echo $start_date; ?>' + '&end=' + '<?php echo $end_date; ?>' + '&nonce=<?php echo wp_create_nonce('spiralengine_export'); ?>';
    });
    
    $('#export-csv').on('click', function() {
        window.location.href = ajaxurl + '?action=spiralengine_export_analytics_csv&start=' + '<?php echo $start_date; ?>' + '&end=' + '<?php echo $end_date; ?>' + '&nonce=<?php echo wp_create_nonce('spiralengine_export'); ?>';
    });
});
</script>


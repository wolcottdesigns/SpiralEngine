<?php
/**
 * Admin Dashboard View
 * 
 * @package    SpiralEngine
 * @subpackage Admin/Views
 * @file       includes/admin/views/dashboard.php
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Get dashboard data
$stats = spiralengine_get_dashboard_stats();
$recent_activity = spiralengine_get_recent_activity(10);
$top_widgets = spiralengine_get_top_widgets(5);
$user_growth = spiralengine_get_user_growth_data();
$revenue_data = spiralengine_get_revenue_data();
?>

<div class="wrap spiralengine-admin-dashboard">
    <h1><?php _e('SpiralEngine Dashboard', 'spiralengine'); ?></h1>
    
    <!-- Welcome Panel -->
    <div class="spiralengine-welcome-panel">
        <div class="spiralengine-welcome-panel-content">
            <h2><?php _e('Welcome to SpiralEngine!', 'spiralengine'); ?></h2>
            <p class="about-description"><?php _e('Your mental health tracking platform is up and running. Here\'s what\'s happening:', 'spiralengine'); ?></p>
            
            <div class="spiralengine-welcome-panel-column-container">
                <div class="spiralengine-welcome-panel-column">
                    <h3><?php _e('Get Started', 'spiralengine'); ?></h3>
                    <a class="button button-primary button-hero" href="<?php echo admin_url('admin.php?page=spiralengine-widgets'); ?>">
                        <?php _e('Manage Widgets', 'spiralengine'); ?>
                    </a>
                    <p><?php _e('or', 'spiralengine'); ?> <a href="<?php echo admin_url('admin.php?page=spiralengine-settings'); ?>"><?php _e('configure settings', 'spiralengine'); ?></a></p>
                </div>
                
                <div class="spiralengine-welcome-panel-column">
                    <h3><?php _e('Next Steps', 'spiralengine'); ?></h3>
                    <ul>
                        <li><a href="<?php echo admin_url('admin.php?page=spiralengine-widgets&action=new'); ?>" class="welcome-icon welcome-add-page"><?php _e('Create a new widget', 'spiralengine'); ?></a></li>
                        <li><a href="<?php echo admin_url('admin.php?page=spiralengine-analytics'); ?>" class="welcome-icon welcome-view-site"><?php _e('View analytics', 'spiralengine'); ?></a></li>
                        <li><a href="<?php echo admin_url('admin.php?page=spiralengine-docs'); ?>" class="welcome-icon welcome-learn-more"><?php _e('Learn more', 'spiralengine'); ?></a></li>
                    </ul>
                </div>
                
                <div class="spiralengine-welcome-panel-column spiralengine-welcome-panel-last">
                    <h3><?php _e('Quick Stats', 'spiralengine'); ?></h3>
                    <ul>
                        <li><?php printf(__('<strong>%s</strong> active users', 'spiralengine'), number_format($stats['active_users'])); ?></li>
                        <li><?php printf(__('<strong>%s</strong> episodes today', 'spiralengine'), number_format($stats['episodes_today'])); ?></li>
                        <li><?php printf(__('<strong>%s</strong> widgets active', 'spiralengine'), number_format($stats['active_widgets'])); ?></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- Dashboard Stats -->
    <div class="spiralengine-dashboard-stats">
        <div class="spiralengine-stat-box">
            <div class="spiralengine-stat-number"><?php echo number_format($stats['total_users']); ?></div>
            <div class="spiralengine-stat-label"><?php _e('Total Users', 'spiralengine'); ?></div>
            <div class="spiralengine-stat-change <?php echo $stats['user_change'] >= 0 ? 'positive' : 'negative'; ?>">
                <?php echo $stats['user_change'] >= 0 ? '+' : ''; ?><?php echo $stats['user_change']; ?>% <?php _e('this month', 'spiralengine'); ?>
            </div>
        </div>
        
        <div class="spiralengine-stat-box">
            <div class="spiralengine-stat-number"><?php echo number_format($stats['total_episodes']); ?></div>
            <div class="spiralengine-stat-label"><?php _e('Total Episodes', 'spiralengine'); ?></div>
            <div class="spiralengine-stat-change <?php echo $stats['episode_change'] >= 0 ? 'positive' : 'negative'; ?>">
                <?php echo $stats['episode_change'] >= 0 ? '+' : ''; ?><?php echo $stats['episode_change']; ?>% <?php _e('this week', 'spiralengine'); ?>
            </div>
        </div>
        
        <div class="spiralengine-stat-box">
            <div class="spiralengine-stat-number">$<?php echo number_format($stats['monthly_revenue'], 2); ?></div>
            <div class="spiralengine-stat-label"><?php _e('Monthly Revenue', 'spiralengine'); ?></div>
            <div class="spiralengine-stat-change <?php echo $stats['revenue_change'] >= 0 ? 'positive' : 'negative'; ?>">
                <?php echo $stats['revenue_change'] >= 0 ? '+' : ''; ?><?php echo $stats['revenue_change']; ?>% <?php _e('vs last month', 'spiralengine'); ?>
            </div>
        </div>
        
        <div class="spiralengine-stat-box">
            <div class="spiralengine-stat-number"><?php echo round($stats['engagement_rate'], 1); ?>%</div>
            <div class="spiralengine-stat-label"><?php _e('Engagement Rate', 'spiralengine'); ?></div>
            <div class="spiralengine-stat-change">
                <?php echo number_format($stats['active_today']); ?> <?php _e('active today', 'spiralengine'); ?>
            </div>
        </div>
    </div>

    <!-- Main Dashboard Content -->
    <div class="spiralengine-dashboard-content">
        <div class="spiralengine-dashboard-main">
            <!-- User Growth Chart -->
            <div class="spiralengine-dashboard-widget">
                <h2><?php _e('User Growth', 'spiralengine'); ?></h2>
                <div class="spiralengine-chart-container">
                    <canvas id="user-growth-chart"></canvas>
                </div>
                <div class="spiralengine-chart-legend">
                    <span class="legend-item">
                        <span class="legend-color" style="background: #4A90E2;"></span>
                        <?php _e('Total Users', 'spiralengine'); ?>
                    </span>
                    <span class="legend-item">
                        <span class="legend-color" style="background: #7ED321;"></span>
                        <?php _e('Active Users', 'spiralengine'); ?>
                    </span>
                    <span class="legend-item">
                        <span class="legend-color" style="background: #F5A623;"></span>
                        <?php _e('New Users', 'spiralengine'); ?>
                    </span>
                </div>
            </div>

            <!-- Episode Activity -->
            <div class="spiralengine-dashboard-widget">
                <h2><?php _e('Episode Activity', 'spiralengine'); ?></h2>
                <div class="spiralengine-chart-container">
                    <canvas id="episode-activity-chart"></canvas>
                </div>
            </div>

            <!-- Top Widgets -->
            <div class="spiralengine-dashboard-widget spiralengine-half-widget">
                <h2><?php _e('Popular Widgets', 'spiralengine'); ?></h2>
                <table class="widefat">
                    <thead>
                        <tr>
                            <th><?php _e('Widget', 'spiralengine'); ?></th>
                            <th><?php _e('Usage', 'spiralengine'); ?></th>
                            <th><?php _e('Episodes', 'spiralengine'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($top_widgets as $widget): ?>
                        <tr>
                            <td>
                                <a href="<?php echo admin_url('admin.php?page=spiralengine-widgets&action=edit&id=' . $widget->id); ?>">
                                    <?php echo esc_html($widget->name); ?>
                                </a>
                            </td>
                            <td>
                                <div class="spiralengine-usage-bar">
                                    <div class="spiralengine-usage-fill" style="width: <?php echo $widget->usage_percent; ?>%"></div>
                                </div>
                                <span><?php echo $widget->usage_percent; ?>%</span>
                            </td>
                            <td><?php echo number_format($widget->episode_count); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Revenue Overview -->
            <div class="spiralengine-dashboard-widget spiralengine-half-widget">
                <h2><?php _e('Revenue Overview', 'spiralengine'); ?></h2>
                <div class="spiralengine-revenue-summary">
                    <div class="revenue-item">
                        <span class="revenue-label"><?php _e('MRR', 'spiralengine'); ?></span>
                        <span class="revenue-value">$<?php echo number_format($revenue_data['mrr'], 2); ?></span>
                    </div>
                    <div class="revenue-item">
                        <span class="revenue-label"><?php _e('ARR', 'spiralengine'); ?></span>
                        <span class="revenue-value">$<?php echo number_format($revenue_data['arr'], 2); ?></span>
                    </div>
                    <div class="revenue-item">
                        <span class="revenue-label"><?php _e('Avg. Revenue/User', 'spiralengine'); ?></span>
                        <span class="revenue-value">$<?php echo number_format($revenue_data['arpu'], 2); ?></span>
                    </div>
                    <div class="revenue-item">
                        <span class="revenue-label"><?php _e('Churn Rate', 'spiralengine'); ?></span>
                        <span class="revenue-value"><?php echo $revenue_data['churn_rate']; ?>%</span>
                    </div>
                </div>
                <canvas id="revenue-chart" style="margin-top: 20px;"></canvas>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="spiralengine-dashboard-sidebar">
            <!-- Recent Activity -->
            <div class="spiralengine-dashboard-widget">
                <h2><?php _e('Recent Activity', 'spiralengine'); ?></h2>
                <ul class="spiralengine-activity-list">
                    <?php foreach ($recent_activity as $activity): ?>
                    <li class="activity-<?php echo esc_attr($activity->type); ?>">
                        <span class="activity-icon">
                            <?php echo spiralengine_get_activity_icon($activity->type); ?>
                        </span>
                        <div class="activity-content">
                            <p><?php echo wp_kses_post($activity->message); ?></p>
                            <span class="activity-time"><?php echo human_time_diff(strtotime($activity->created_at), current_time('timestamp')) . ' ' . __('ago', 'spiralengine'); ?></span>
                        </div>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <a href="<?php echo admin_url('admin.php?page=spiralengine-activity'); ?>" class="button">
                    <?php _e('View All Activity', 'spiralengine'); ?>
                </a>
            </div>

            <!-- System Health -->
            <div class="spiralengine-dashboard-widget">
                <h2><?php _e('System Health', 'spiralengine'); ?></h2>
                <?php
                $health = spiralengine_get_system_health();
                $health_class = $health['status'] === 'good' ? 'health-good' : ($health['status'] === 'warning' ? 'health-warning' : 'health-critical');
                ?>
                <div class="spiralengine-health-status <?php echo $health_class; ?>">
                    <span class="health-icon">
                        <?php if ($health['status'] === 'good'): ?>✓
                        <?php elseif ($health['status'] === 'warning'): ?>⚠
                        <?php else: ?>✗<?php endif; ?>
                    </span>
                    <span class="health-text">
                        <?php
                        if ($health['status'] === 'good') {
                            _e('All systems operational', 'spiralengine');
                        } elseif ($health['status'] === 'warning') {
                            _e('Minor issues detected', 'spiralengine');
                        } else {
                            _e('Critical issues found', 'spiralengine');
                        }
                        ?>
                    </span>
                </div>
                
                <?php if (!empty($health['issues'])): ?>
                <ul class="spiralengine-health-issues">
                    <?php foreach ($health['issues'] as $issue): ?>
                    <li><?php echo esc_html($issue); ?></li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
                
                <a href="<?php echo admin_url('admin.php?page=spiralengine-settings&tab=tools'); ?>" class="button">
                    <?php _e('View Details', 'spiralengine'); ?>
                </a>
            </div>

            <!-- Quick Actions -->
            <div class="spiralengine-dashboard-widget">
                <h2><?php _e('Quick Actions', 'spiralengine'); ?></h2>
                <div class="spiralengine-quick-actions">
                    <a href="<?php echo admin_url('admin.php?page=spiralengine-widgets&action=new'); ?>" class="button button-primary">
                        <?php _e('Create Widget', 'spiralengine'); ?>
                    </a>
                    <a href="<?php echo admin_url('admin.php?page=spiralengine-users'); ?>" class="button">
                        <?php _e('View Users', 'spiralengine'); ?>
                    </a>
                    <a href="<?php echo admin_url('admin.php?page=spiralengine-episodes'); ?>" class="button">
                        <?php _e('Browse Episodes', 'spiralengine'); ?>
                    </a>
                    <a href="<?php echo admin_url('admin.php?page=spiralengine-settings&tab=tools&action=export'); ?>" class="button">
                        <?php _e('Export Data', 'spiralengine'); ?>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // User Growth Chart
    var userGrowthCtx = document.getElementById('user-growth-chart').getContext('2d');
    var userGrowthChart = new Chart(userGrowthCtx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode(array_column($user_growth, 'date')); ?>,
            datasets: [{
                label: 'Total Users',
                data: <?php echo json_encode(array_column($user_growth, 'total')); ?>,
                borderColor: '#4A90E2',
                backgroundColor: 'rgba(74, 144, 226, 0.1)',
                tension: 0.4
            }, {
                label: 'Active Users',
                data: <?php echo json_encode(array_column($user_growth, 'active')); ?>,
                borderColor: '#7ED321',
                backgroundColor: 'rgba(126, 211, 33, 0.1)',
                tension: 0.4
            }, {
                label: 'New Users',
                data: <?php echo json_encode(array_column($user_growth, 'new')); ?>,
                borderColor: '#F5A623',
                backgroundColor: 'rgba(245, 166, 35, 0.1)',
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
    
    // Episode Activity Chart
    var episodeCtx = document.getElementById('episode-activity-chart').getContext('2d');
    var episodeChart = new Chart(episodeCtx, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode(array_keys($stats['episode_activity'])); ?>,
            datasets: [{
                label: 'Episodes',
                data: <?php echo json_encode(array_values($stats['episode_activity'])); ?>,
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
    
    // Revenue Chart
    var revenueCtx = document.getElementById('revenue-chart').getContext('2d');
    var revenueChart = new Chart(revenueCtx, {
        type: 'doughnut',
        data: {
            labels: <?php echo json_encode(array_keys($revenue_data['by_tier'])); ?>,
            datasets: [{
                data: <?php echo json_encode(array_values($revenue_data['by_tier'])); ?>,
                backgroundColor: ['#4A90E2', '#7ED321', '#F5A623', '#BD10E0']
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
    
    // Auto-refresh dashboard every 5 minutes
    setInterval(function() {
        location.reload();
    }, 300000);
});
</script>


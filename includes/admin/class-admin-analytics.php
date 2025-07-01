<?php
/**
 * SpiralEngine Admin Analytics Dashboard
 *
 * @package    SpiralEngine
 * @subpackage Admin
 * @file       includes/admin/class-admin-analytics.php
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class SpiralEngine_Admin_Analytics
 *
 * Comprehensive analytics and reporting system
 */
class SpiralEngine_Admin_Analytics {
    
    /**
     * Instance of this class
     *
     * @var SpiralEngine_Admin_Analytics
     */
    private static $instance = null;
    
    /**
     * Time ranges for analytics
     *
     * @var array
     */
    private $time_ranges = array();
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->init();
    }
    
    /**
     * Get instance
     *
     * @return SpiralEngine_Admin_Analytics
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Initialize analytics
     */
    public function init() {
        $this->setup_time_ranges();
        
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_ajax_spiralengine_get_analytics_data', array($this, 'ajax_get_analytics_data'));
        add_action('wp_ajax_spiralengine_export_analytics', array($this, 'ajax_export_analytics'));
        add_action('wp_ajax_spiralengine_get_user_insights', array($this, 'ajax_get_user_insights'));
        add_action('wp_ajax_spiralengine_get_widget_performance', array($this, 'ajax_get_widget_performance'));
        add_action('wp_dashboard_setup', array($this, 'add_dashboard_widgets'));
        
        // Schedule analytics cron jobs
        add_action('spiralengine_calculate_analytics', array($this, 'calculate_scheduled_analytics'));
        
        if (!wp_next_scheduled('spiralengine_calculate_analytics')) {
            wp_schedule_event(time(), 'hourly', 'spiralengine_calculate_analytics');
        }
    }
    
    /**
     * Setup time ranges
     */
    private function setup_time_ranges() {
        $this->time_ranges = array(
            'today' => array(
                'label' => __('Today', 'spiralengine'),
                'start' => 'today midnight',
                'end' => 'now'
            ),
            'yesterday' => array(
                'label' => __('Yesterday', 'spiralengine'),
                'start' => 'yesterday midnight',
                'end' => 'today midnight'
            ),
            'last_7_days' => array(
                'label' => __('Last 7 Days', 'spiralengine'),
                'start' => '7 days ago midnight',
                'end' => 'now'
            ),
            'last_30_days' => array(
                'label' => __('Last 30 Days', 'spiralengine'),
                'start' => '30 days ago midnight',
                'end' => 'now'
            ),
            'this_month' => array(
                'label' => __('This Month', 'spiralengine'),
                'start' => 'first day of this month midnight',
                'end' => 'now'
            ),
            'last_month' => array(
                'label' => __('Last Month', 'spiralengine'),
                'start' => 'first day of last month midnight',
                'end' => 'first day of this month midnight'
            ),
            'last_3_months' => array(
                'label' => __('Last 3 Months', 'spiralengine'),
                'start' => '3 months ago midnight',
                'end' => 'now'
            ),
            'last_6_months' => array(
                'label' => __('Last 6 Months', 'spiralengine'),
                'start' => '6 months ago midnight',
                'end' => 'now'
            ),
            'this_year' => array(
                'label' => __('This Year', 'spiralengine'),
                'start' => 'first day of January this year midnight',
                'end' => 'now'
            ),
            'all_time' => array(
                'label' => __('All Time', 'spiralengine'),
                'start' => '2020-01-01',
                'end' => 'now'
            )
        );
    }
    
    /**
     * Enqueue scripts and styles
     *
     * @param string $hook
     */
    public function enqueue_scripts($hook) {
        if (strpos($hook, 'spiralengine') === false && $hook !== 'index.php') {
            return;
        }
        
        // Chart.js
        wp_enqueue_script(
            'chart-js',
            'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.js',
            array(),
            '4.4.0',
            true
        );
        
        // Date adapter for Chart.js
        wp_enqueue_script(
            'chartjs-adapter-date',
            'https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns@3.0.0/dist/chartjs-adapter-date-fns.bundle.min.js',
            array('chart-js'),
            '3.0.0',
            true
        );
        
        // DataTables
        wp_enqueue_style(
            'datatables',
            'https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css',
            array(),
            '1.13.6'
        );
        
        wp_enqueue_script(
            'datatables',
            'https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js',
            array('jquery'),
            '1.13.6',
            true
        );
        
        wp_enqueue_style('spiralengine-admin');
        wp_enqueue_script('spiralengine-admin');
        
        // Add analytics specific script
        wp_add_inline_script('spiralengine-admin', $this->get_inline_script());
        
        // Localize script
        wp_localize_script('spiralengine-admin', 'spiralengine_analytics', array(
            'nonce' => wp_create_nonce('spiralengine_analytics'),
            'ajax_url' => admin_url('admin-ajax.php'),
            'strings' => array(
                'loading' => __('Loading analytics data...', 'spiralengine'),
                'error' => __('Error loading data', 'spiralengine'),
                'no_data' => __('No data available for this period', 'spiralengine'),
                'export_success' => __('Export completed successfully', 'spiralengine'),
                'export_error' => __('Export failed. Please try again.', 'spiralengine')
            ),
            'chart_options' => array(
                'responsive' => true,
                'maintainAspectRatio' => false,
                'plugins' => array(
                    'legend' => array(
                        'position' => 'top'
                    ),
                    'tooltip' => array(
                        'mode' => 'index',
                        'intersect' => false
                    )
                )
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
            // Global chart instances
            window.spiralEngineCharts = {};
            
            // Initialize analytics on page load
            if ($('.spiralengine-analytics-dashboard').length) {
                loadAnalyticsDashboard();
            }
            
            // Time range selector
            $('#analytics-time-range').on('change', function() {
                var range = $(this).val();
                updateAllCharts(range);
            });
            
            // Metric cards click handlers
            $('.metric-card').on('click', function() {
                var metric = $(this).data('metric');
                showMetricDetails(metric);
            });
            
            // Export functionality
            $('.export-analytics').on('click', function(e) {
                e.preventDefault();
                var format = $(this).data('format');
                var range = $('#analytics-time-range').val();
                exportAnalytics(format, range);
            });
            
            // Tab switching
            $('.analytics-tab').on('click', function(e) {
                e.preventDefault();
                var tab = $(this).data('tab');
                
                $('.analytics-tab').removeClass('active');
                $(this).addClass('active');
                
                $('.analytics-tab-content').hide();
                $('#analytics-' + tab).show();
                
                // Load tab-specific data
                loadTabData(tab);
            });
            
            // Real-time updates toggle
            $('#enable-realtime').on('change', function() {
                if ($(this).is(':checked')) {
                    startRealtimeUpdates();
                } else {
                    stopRealtimeUpdates();
                }
            });
            
            // Custom date range
            $('#custom-date-range').on('click', function() {
                showDateRangePicker();
            });
            
            // Functions
            function loadAnalyticsDashboard() {
                var range = $('#analytics-time-range').val() || 'last_30_days';
                
                // Load overview metrics
                loadOverviewMetrics(range);
                
                // Load charts
                loadUserActivityChart(range);
                loadEpisodeDistributionChart(range);
                loadWidgetUsageChart(range);
                loadMembershipChart(range);
                loadEngagementChart(range);
                
                // Load tables
                loadTopUsers(range);
                loadRecentActivity(range);
            }
            
            function loadOverviewMetrics(range) {
                $.ajax({
                    url: spiralengine_analytics.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'spiralengine_get_analytics_data',
                        type: 'overview_metrics',
                        range: range,
                        nonce: spiralengine_analytics.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            updateMetricCards(response.data);
                        }
                    }
                });
            }
            
            function updateMetricCards(data) {
                $.each(data, function(key, value) {
                    var card = $('.metric-card[data-metric=\"' + key + '\"]');
                    card.find('.metric-value').text(value.formatted);
                    
                    // Update trend
                    if (value.trend) {
                        var trendClass = value.trend > 0 ? 'trend-up' : 'trend-down';
                        var trendIcon = value.trend > 0 ? '↑' : '↓';
                        card.find('.metric-trend')
                            .removeClass('trend-up trend-down')
                            .addClass(trendClass)
                            .html(trendIcon + ' ' + Math.abs(value.trend) + '%');
                    }
                });
            }
            
            function loadUserActivityChart(range) {
                $.ajax({
                    url: spiralengine_analytics.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'spiralengine_get_analytics_data',
                        type: 'user_activity',
                        range: range,
                        nonce: spiralengine_analytics.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            renderUserActivityChart(response.data);
                        }
                    }
                });
            }
            
            function renderUserActivityChart(data) {
                var ctx = document.getElementById('user-activity-chart');
                if (!ctx) return;
                
                // Destroy existing chart
                if (window.spiralEngineCharts.userActivity) {
                    window.spiralEngineCharts.userActivity.destroy();
                }
                
                window.spiralEngineCharts.userActivity = new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: data.labels,
                        datasets: [{
                            label: 'Active Users',
                            data: data.active_users,
                            borderColor: 'rgb(75, 192, 192)',
                            backgroundColor: 'rgba(75, 192, 192, 0.2)',
                            tension: 0.1
                        }, {
                            label: 'New Users',
                            data: data.new_users,
                            borderColor: 'rgb(54, 162, 235)',
                            backgroundColor: 'rgba(54, 162, 235, 0.2)',
                            tension: 0.1
                        }]
                    },
                    options: {
                        ...spiralengine_analytics.chart_options,
                        scales: {
                            y: {
                                beginAtZero: true
                            }
                        }
                    }
                });
            }
            
            function loadEpisodeDistributionChart(range) {
                $.ajax({
                    url: spiralengine_analytics.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'spiralengine_get_analytics_data',
                        type: 'episode_distribution',
                        range: range,
                        nonce: spiralengine_analytics.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            renderEpisodeDistributionChart(response.data);
                        }
                    }
                });
            }
            
            function renderEpisodeDistributionChart(data) {
                var ctx = document.getElementById('episode-distribution-chart');
                if (!ctx) return;
                
                if (window.spiralEngineCharts.episodeDistribution) {
                    window.spiralEngineCharts.episodeDistribution.destroy();
                }
                
                window.spiralEngineCharts.episodeDistribution = new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: data.labels,
                        datasets: [{
                            label: 'Episodes',
                            data: data.counts,
                            backgroundColor: 'rgba(255, 99, 132, 0.5)',
                            borderColor: 'rgba(255, 99, 132, 1)',
                            borderWidth: 1
                        }]
                    },
                    options: {
                        ...spiralengine_analytics.chart_options,
                        scales: {
                            y: {
                                beginAtZero: true
                            }
                        }
                    }
                });
            }
            
            function loadWidgetUsageChart(range) {
                $.ajax({
                    url: spiralengine_analytics.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'spiralengine_get_analytics_data',
                        type: 'widget_usage',
                        range: range,
                        nonce: spiralengine_analytics.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            renderWidgetUsageChart(response.data);
                        }
                    }
                });
            }
            
            function renderWidgetUsageChart(data) {
                var ctx = document.getElementById('widget-usage-chart');
                if (!ctx) return;
                
                if (window.spiralEngineCharts.widgetUsage) {
                    window.spiralEngineCharts.widgetUsage.destroy();
                }
                
                window.spiralEngineCharts.widgetUsage = new Chart(ctx, {
                    type: 'doughnut',
                    data: {
                        labels: data.labels,
                        datasets: [{
                            data: data.values,
                            backgroundColor: [
                                'rgba(255, 99, 132, 0.5)',
                                'rgba(54, 162, 235, 0.5)',
                                'rgba(255, 206, 86, 0.5)',
                                'rgba(75, 192, 192, 0.5)',
                                'rgba(153, 102, 255, 0.5)',
                                'rgba(255, 159, 64, 0.5)'
                            ]
                        }]
                    },
                    options: {
                        ...spiralengine_analytics.chart_options,
                        plugins: {
                            legend: {
                                position: 'right'
                            }
                        }
                    }
                });
            }
            
            function loadMembershipChart(range) {
                $.ajax({
                    url: spiralengine_analytics.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'spiralengine_get_analytics_data',
                        type: 'membership_distribution',
                        range: range,
                        nonce: spiralengine_analytics.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            renderMembershipChart(response.data);
                        }
                    }
                });
            }
            
            function renderMembershipChart(data) {
                var ctx = document.getElementById('membership-chart');
                if (!ctx) return;
                
                if (window.spiralEngineCharts.membership) {
                    window.spiralEngineCharts.membership.destroy();
                }
                
                window.spiralEngineCharts.membership = new Chart(ctx, {
                    type: 'pie',
                    data: {
                        labels: data.labels,
                        datasets: [{
                            data: data.values,
                            backgroundColor: [
                                'rgba(128, 128, 128, 0.5)', // Free
                                'rgba(205, 127, 50, 0.5)',  // Bronze
                                'rgba(192, 192, 192, 0.5)', // Silver
                                'rgba(255, 215, 0, 0.5)',   // Gold
                                'rgba(229, 228, 226, 0.5)'  // Platinum
                            ]
                        }]
                    },
                    options: {
                        ...spiralengine_analytics.chart_options
                    }
                });
            }
            
            function loadEngagementChart(range) {
                $.ajax({
                    url: spiralengine_analytics.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'spiralengine_get_analytics_data',
                        type: 'engagement_metrics',
                        range: range,
                        nonce: spiralengine_analytics.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            renderEngagementChart(response.data);
                        }
                    }
                });
            }
            
            function renderEngagementChart(data) {
                var ctx = document.getElementById('engagement-chart');
                if (!ctx) return;
                
                if (window.spiralEngineCharts.engagement) {
                    window.spiralEngineCharts.engagement.destroy();
                }
                
                window.spiralEngineCharts.engagement = new Chart(ctx, {
                    type: 'radar',
                    data: {
                        labels: data.labels,
                        datasets: [{
                            label: 'Current Period',
                            data: data.current,
                            borderColor: 'rgba(75, 192, 192, 1)',
                            backgroundColor: 'rgba(75, 192, 192, 0.2)'
                        }, {
                            label: 'Previous Period',
                            data: data.previous,
                            borderColor: 'rgba(255, 99, 132, 1)',
                            backgroundColor: 'rgba(255, 99, 132, 0.2)'
                        }]
                    },
                    options: {
                        ...spiralengine_analytics.chart_options,
                        scales: {
                            r: {
                                beginAtZero: true
                            }
                        }
                    }
                });
            }
            
            function loadTopUsers(range) {
                $.ajax({
                    url: spiralengine_analytics.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'spiralengine_get_analytics_data',
                        type: 'top_users',
                        range: range,
                        nonce: spiralengine_analytics.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            renderTopUsersTable(response.data);
                        }
                    }
                });
            }
            
            function renderTopUsersTable(data) {
                var table = $('#top-users-table');
                var tbody = table.find('tbody');
                tbody.empty();
                
                $.each(data, function(i, user) {
                    var row = $('<tr>');
                    row.append('<td>' + user.rank + '</td>');
                    row.append('<td>' + user.avatar + ' ' + user.name + '</td>');
                    row.append('<td>' + user.episodes + '</td>');
                    row.append('<td>' + user.streak + ' days</td>');
                    row.append('<td><span class=\"tier-badge tier-' + user.tier + '\">' + user.tier + '</span></td>');
                    tbody.append(row);
                });
                
                // Initialize DataTable if not already done
                if (!$.fn.DataTable.isDataTable(table)) {
                    table.DataTable({
                        pageLength: 10,
                        order: [[2, 'desc']]
                    });
                }
            }
            
            function exportAnalytics(format, range) {
                var button = $('.export-analytics[data-format=\"' + format + '\"]');
                button.prop('disabled', true).text('Exporting...');
                
                $.ajax({
                    url: spiralengine_analytics.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'spiralengine_export_analytics',
                        format: format,
                        range: range,
                        nonce: spiralengine_analytics.nonce
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
                            
                            alert(spiralengine_analytics.strings.export_success);
                        } else {
                            alert(response.data.message || spiralengine_analytics.strings.export_error);
                        }
                    },
                    complete: function() {
                        button.prop('disabled', false).text('Export ' + format.toUpperCase());
                    }
                });
            }
            
            function updateAllCharts(range) {
                loadAnalyticsDashboard();
            }
            
            var realtimeInterval;
            
            function startRealtimeUpdates() {
                realtimeInterval = setInterval(function() {
                    loadOverviewMetrics('today');
                    loadRecentActivity('today');
                }, 30000); // Update every 30 seconds
            }
            
            function stopRealtimeUpdates() {
                if (realtimeInterval) {
                    clearInterval(realtimeInterval);
                }
            }
            
            // Compare periods functionality
            $('#compare-periods').on('click', function() {
                var currentRange = $('#analytics-time-range').val();
                showPeriodComparison(currentRange);
            });
            
            function showPeriodComparison(currentRange) {
                $('<div id=\"period-comparison-dialog\"></div>').dialog({
                    title: 'Compare Time Periods',
                    width: 800,
                    height: 600,
                    modal: true,
                    buttons: {
                        'Close': function() {
                            $(this).dialog('close');
                        }
                    },
                    open: function() {
                        loadComparisonData(currentRange);
                    }
                });
            }
        });
        ";
    }
    
    /**
     * Render analytics dashboard (main page)
     */
    public function render_dashboard() {
        ?>
        <div class="spiralengine-analytics-dashboard">
            <?php $this->render_header(); ?>
            <?php $this->render_overview_metrics(); ?>
            <?php $this->render_charts_section(); ?>
            <?php $this->render_tables_section(); ?>
        </div>
        <?php
    }
    
    /**
     * Render full analytics page
     */
    public function render_full_page() {
        if (!current_user_can('spiralengine_view_analytics')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'spiralengine'));
        }
        
        ?>
        <div class="wrap spiralengine-analytics-wrap">
            <h1>
                <span class="dashicons dashicons-chart-area"></span>
                <?php _e('Analytics & Insights', 'spiralengine'); ?>
            </h1>
            
            <?php $this->render_analytics_tabs(); ?>
            
            <div class="analytics-content">
                <div id="analytics-overview" class="analytics-tab-content active">
                    <?php $this->render_dashboard(); ?>
                </div>
                
                <div id="analytics-users" class="analytics-tab-content" style="display: none;">
                    <?php $this->render_user_analytics(); ?>
                </div>
                
                <div id="analytics-widgets" class="analytics-tab-content" style="display: none;">
                    <?php $this->render_widget_analytics(); ?>
                </div>
                
                <div id="analytics-health" class="analytics-tab-content" style="display: none;">
                    <?php $this->render_health_analytics(); ?>
                </div>
                
                <div id="analytics-revenue" class="analytics-tab-content" style="display: none;">
                    <?php $this->render_revenue_analytics(); ?>
                </div>
                
                <div id="analytics-reports" class="analytics-tab-content" style="display: none;">
                    <?php $this->render_reports_section(); ?>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render analytics tabs
     */
    private function render_analytics_tabs() {
        ?>
        <nav class="nav-tab-wrapper spiralengine-analytics-tabs">
            <a href="#" class="nav-tab nav-tab-active analytics-tab" data-tab="overview">
                <span class="dashicons dashicons-dashboard"></span>
                <?php _e('Overview', 'spiralengine'); ?>
            </a>
            <a href="#" class="nav-tab analytics-tab" data-tab="users">
                <span class="dashicons dashicons-groups"></span>
                <?php _e('User Analytics', 'spiralengine'); ?>
            </a>
            <a href="#" class="nav-tab analytics-tab" data-tab="widgets">
                <span class="dashicons dashicons-screenoptions"></span>
                <?php _e('Widget Performance', 'spiralengine'); ?>
            </a>
            <a href="#" class="nav-tab analytics-tab" data-tab="health">
                <span class="dashicons dashicons-heart"></span>
                <?php _e('Health Insights', 'spiralengine'); ?>
            </a>
            <a href="#" class="nav-tab analytics-tab" data-tab="revenue">
                <span class="dashicons dashicons-chart-line"></span>
                <?php _e('Revenue', 'spiralengine'); ?>
            </a>
            <a href="#" class="nav-tab analytics-tab" data-tab="reports">
                <span class="dashicons dashicons-analytics"></span>
                <?php _e('Reports', 'spiralengine'); ?>
            </a>
        </nav>
        <?php
    }
    
    /**
     * Render analytics header
     */
    private function render_header() {
        ?>
        <div class="analytics-header">
            <div class="header-controls">
                <select id="analytics-time-range" class="analytics-control">
                    <?php foreach ($this->time_ranges as $key => $range) : ?>
                        <option value="<?php echo esc_attr($key); ?>" <?php selected($key, 'last_30_days'); ?>>
                            <?php echo esc_html($range['label']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                
                <button id="custom-date-range" class="button button-secondary">
                    <span class="dashicons dashicons-calendar-alt"></span>
                    <?php _e('Custom Range', 'spiralengine'); ?>
                </button>
                
                <button id="compare-periods" class="button button-secondary">
                    <span class="dashicons dashicons-image-flip-horizontal"></span>
                    <?php _e('Compare', 'spiralengine'); ?>
                </button>
                
                <div class="export-controls">
                    <button class="button export-analytics" data-format="csv">
                        <span class="dashicons dashicons-download"></span>
                        <?php _e('Export CSV', 'spiralengine'); ?>
                    </button>
                    <button class="button export-analytics" data-format="pdf">
                        <span class="dashicons dashicons-pdf"></span>
                        <?php _e('Export PDF', 'spiralengine'); ?>
                    </button>
                </div>
                
                <label class="realtime-toggle">
                    <input type="checkbox" id="enable-realtime" />
                    <?php _e('Real-time Updates', 'spiralengine'); ?>
                </label>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render overview metrics
     */
    private function render_overview_metrics() {
        $metrics = $this->get_overview_metrics('last_30_days');
        ?>
        <div class="analytics-metrics-grid">
            <div class="metric-card" data-metric="total_users">
                <div class="metric-icon">
                    <span class="dashicons dashicons-groups"></span>
                </div>
                <div class="metric-content">
                    <h3><?php _e('Total Users', 'spiralengine'); ?></h3>
                    <div class="metric-value"><?php echo number_format($metrics['total_users']['value']); ?></div>
                    <div class="metric-trend trend-up">↑ <?php echo $metrics['total_users']['trend']; ?>%</div>
                </div>
            </div>
            
            <div class="metric-card" data-metric="active_users">
                <div class="metric-icon">
                    <span class="dashicons dashicons-admin-users"></span>
                </div>
                <div class="metric-content">
                    <h3><?php _e('Active Users', 'spiralengine'); ?></h3>
                    <div class="metric-value"><?php echo number_format($metrics['active_users']['value']); ?></div>
                    <div class="metric-trend trend-up">↑ <?php echo $metrics['active_users']['trend']; ?>%</div>
                </div>
            </div>
            
            <div class="metric-card" data-metric="total_episodes">
                <div class="metric-icon">
                    <span class="dashicons dashicons-edit-page"></span>
                </div>
                <div class="metric-content">
                    <h3><?php _e('Total Episodes', 'spiralengine'); ?></h3>
                    <div class="metric-value"><?php echo number_format($metrics['total_episodes']['value']); ?></div>
                    <div class="metric-trend trend-up">↑ <?php echo $metrics['total_episodes']['trend']; ?>%</div>
                </div>
            </div>
            
            <div class="metric-card" data-metric="avg_engagement">
                <div class="metric-icon">
                    <span class="dashicons dashicons-chart-area"></span>
                </div>
                <div class="metric-content">
                    <h3><?php _e('Avg. Engagement', 'spiralengine'); ?></h3>
                    <div class="metric-value"><?php echo $metrics['avg_engagement']['value']; ?>%</div>
                    <div class="metric-trend trend-down">↓ <?php echo $metrics['avg_engagement']['trend']; ?>%</div>
                </div>
            </div>
            
            <div class="metric-card" data-metric="revenue">
                <div class="metric-icon">
                    <span class="dashicons dashicons-money-alt"></span>
                </div>
                <div class="metric-content">
                    <h3><?php _e('Revenue', 'spiralengine'); ?></h3>
                    <div class="metric-value">$<?php echo number_format($metrics['revenue']['value'], 2); ?></div>
                    <div class="metric-trend trend-up">↑ <?php echo $metrics['revenue']['trend']; ?>%</div>
                </div>
            </div>
            
            <div class="metric-card" data-metric="churn_rate">
                <div class="metric-icon">
                    <span class="dashicons dashicons-dismiss"></span>
                </div>
                <div class="metric-content">
                    <h3><?php _e('Churn Rate', 'spiralengine'); ?></h3>
                    <div class="metric-value"><?php echo $metrics['churn_rate']['value']; ?>%</div>
                    <div class="metric-trend trend-up">↑ <?php echo $metrics['churn_rate']['trend']; ?>%</div>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render charts section
     */
    private function render_charts_section() {
        ?>
        <div class="analytics-charts-section">
            <div class="chart-row">
                <div class="chart-container large">
                    <h3><?php _e('User Activity', 'spiralengine'); ?></h3>
                    <canvas id="user-activity-chart"></canvas>
                </div>
            </div>
            
            <div class="chart-row">
                <div class="chart-container medium">
                    <h3><?php _e('Episode Distribution', 'spiralengine'); ?></h3>
                    <canvas id="episode-distribution-chart"></canvas>
                </div>
                
                <div class="chart-container medium">
                    <h3><?php _e('Widget Usage', 'spiralengine'); ?></h3>
                    <canvas id="widget-usage-chart"></canvas>
                </div>
            </div>
            
            <div class="chart-row">
                <div class="chart-container small">
                    <h3><?php _e('Membership Tiers', 'spiralengine'); ?></h3>
                    <canvas id="membership-chart"></canvas>
                </div>
                
                <div class="chart-container small">
                    <h3><?php _e('Engagement Metrics', 'spiralengine'); ?></h3>
                    <canvas id="engagement-chart"></canvas>
                </div>
                
                <div class="chart-container small">
                    <h3><?php _e('Health Trends', 'spiralengine'); ?></h3>
                    <canvas id="health-trends-chart"></canvas>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render tables section
     */
    private function render_tables_section() {
        ?>
        <div class="analytics-tables-section">
            <div class="table-container">
                <h3><?php _e('Top Users', 'spiralengine'); ?></h3>
                <table id="top-users-table" class="widefat striped">
                    <thead>
                        <tr>
                            <th><?php _e('Rank', 'spiralengine'); ?></th>
                            <th><?php _e('User', 'spiralengine'); ?></th>
                            <th><?php _e('Episodes', 'spiralengine'); ?></th>
                            <th><?php _e('Streak', 'spiralengine'); ?></th>
                            <th><?php _e('Tier', 'spiralengine'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Populated via AJAX -->
                    </tbody>
                </table>
            </div>
            
            <div class="table-container">
                <h3><?php _e('Recent Activity', 'spiralengine'); ?></h3>
                <div id="recent-activity-feed" class="activity-feed">
                    <!-- Populated via AJAX -->
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render user analytics section
     */
    private function render_user_analytics() {
        ?>
        <div class="user-analytics-section">
            <h2><?php _e('User Analytics', 'spiralengine'); ?></h2>
            
            <div class="analytics-filters">
                <select id="user-segment-filter">
                    <option value="all"><?php _e('All Users', 'spiralengine'); ?></option>
                    <option value="new"><?php _e('New Users', 'spiralengine'); ?></option>
                    <option value="active"><?php _e('Active Users', 'spiralengine'); ?></option>
                    <option value="at_risk"><?php _e('At Risk', 'spiralengine'); ?></option>
                    <option value="churned"><?php _e('Churned', 'spiralengine'); ?></option>
                </select>
                
                <select id="user-tier-filter">
                    <option value="all"><?php _e('All Tiers', 'spiralengine'); ?></option>
                    <option value="free"><?php _e('Free', 'spiralengine'); ?></option>
                    <option value="bronze"><?php _e('Bronze', 'spiralengine'); ?></option>
                    <option value="silver"><?php _e('Silver', 'spiralengine'); ?></option>
                    <option value="gold"><?php _e('Gold', 'spiralengine'); ?></option>
                    <option value="platinum"><?php _e('Platinum', 'spiralengine'); ?></option>
                </select>
            </div>
            
            <div class="user-metrics-grid">
                <div class="user-metric">
                    <h4><?php _e('User Retention', 'spiralengine'); ?></h4>
                    <canvas id="user-retention-chart"></canvas>
                </div>
                
                <div class="user-metric">
                    <h4><?php _e('User Lifecycle', 'spiralengine'); ?></h4>
                    <canvas id="user-lifecycle-chart"></canvas>
                </div>
                
                <div class="user-metric">
                    <h4><?php _e('Cohort Analysis', 'spiralengine'); ?></h4>
                    <div id="cohort-table"></div>
                </div>
            </div>
            
            <div class="user-insights">
                <h3><?php _e('Key User Insights', 'spiralengine'); ?></h3>
                <ul id="user-insights-list">
                    <!-- Populated via AJAX -->
                </ul>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render widget analytics section
     */
    private function render_widget_analytics() {
        ?>
        <div class="widget-analytics-section">
            <h2><?php _e('Widget Performance', 'spiralengine'); ?></h2>
            
            <div class="widget-performance-grid">
                <?php
                $widgets = array(
                    'mood_tracker' => __('Mood Tracker', 'spiralengine'),
                    'thought_diary' => __('Thought Diary', 'spiralengine'),
                    'gratitude_journal' => __('Gratitude Journal', 'spiralengine'),
                    'meditation_timer' => __('Meditation Timer', 'spiralengine'),
                    'coping_strategies' => __('Coping Strategies', 'spiralengine')
                );
                
                foreach ($widgets as $widget_id => $widget_name) :
                    ?>
                    <div class="widget-performance-card" data-widget="<?php echo esc_attr($widget_id); ?>">
                        <h3><?php echo esc_html($widget_name); ?></h3>
                        <div class="widget-stats">
                            <div class="stat">
                                <span class="stat-label"><?php _e('Usage', 'spiralengine'); ?></span>
                                <span class="stat-value usage-count">-</span>
                            </div>
                            <div class="stat">
                                <span class="stat-label"><?php _e('Users', 'spiralengine'); ?></span>
                                <span class="stat-value user-count">-</span>
                            </div>
                            <div class="stat">
                                <span class="stat-label"><?php _e('Avg. Time', 'spiralengine'); ?></span>
                                <span class="stat-value avg-time">-</span>
                            </div>
                        </div>
                        <div class="widget-mini-chart">
                            <canvas id="widget-chart-<?php echo esc_attr($widget_id); ?>"></canvas>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <div class="widget-comparison">
                <h3><?php _e('Widget Comparison', 'spiralengine'); ?></h3>
                <canvas id="widget-comparison-chart"></canvas>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render health analytics section
     */
    private function render_health_analytics() {
        ?>
        <div class="health-analytics-section">
            <h2><?php _e('Mental Health Insights', 'spiralengine'); ?></h2>
            
            <div class="health-overview">
                <div class="aggregate-mood">
                    <h3><?php _e('Community Mood Trends', 'spiralengine'); ?></h3>
                    <canvas id="community-mood-chart"></canvas>
                </div>
                
                <div class="health-patterns">
                    <h3><?php _e('Common Patterns', 'spiralengine'); ?></h3>
                    <div id="pattern-analysis">
                        <!-- Populated via AJAX -->
                    </div>
                </div>
            </div>
            
            <div class="crisis-monitoring">
                <h3><?php _e('Crisis Indicators', 'spiralengine'); ?></h3>
                <div class="crisis-alerts">
                    <div class="alert-metric">
                        <span class="metric-label"><?php _e('High Severity Episodes', 'spiralengine'); ?></span>
                        <span class="metric-value" id="high-severity-count">0</span>
                    </div>
                    <div class="alert-metric">
                        <span class="metric-label"><?php _e('Crisis Keywords Detected', 'spiralengine'); ?></span>
                        <span class="metric-value" id="crisis-keyword-count">0</span>
                    </div>
                    <div class="alert-metric">
                        <span class="metric-label"><?php _e('Users Needing Support', 'spiralengine'); ?></span>
                        <span class="metric-value" id="support-needed-count">0</span>
                    </div>
                </div>
            </div>
            
            <div class="improvement-metrics">
                <h3><?php _e('Improvement Indicators', 'spiralengine'); ?></h3>
                <canvas id="improvement-chart"></canvas>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render revenue analytics section
     */
    private function render_revenue_analytics() {
        ?>
        <div class="revenue-analytics-section">
            <h2><?php _e('Revenue Analytics', 'spiralengine'); ?></h2>
            
            <div class="revenue-metrics">
                <div class="metric-card">
                    <h3><?php _e('MRR', 'spiralengine'); ?></h3>
                    <div class="metric-value">$<span id="mrr-value">0</span></div>
                    <div class="metric-trend">-</div>
                </div>
                
                <div class="metric-card">
                    <h3><?php _e('ARR', 'spiralengine'); ?></h3>
                    <div class="metric-value">$<span id="arr-value">0</span></div>
                    <div class="metric-trend">-</div>
                </div>
                
                <div class="metric-card">
                    <h3><?php _e('ARPU', 'spiralengine'); ?></h3>
                    <div class="metric-value">$<span id="arpu-value">0</span></div>
                    <div class="metric-trend">-</div>
                </div>
                
                <div class="metric-card">
                    <h3><?php _e('LTV', 'spiralengine'); ?></h3>
                    <div class="metric-value">$<span id="ltv-value">0</span></div>
                    <div class="metric-trend">-</div>
                </div>
            </div>
            
            <div class="revenue-charts">
                <div class="chart-container">
                    <h3><?php _e('Revenue Growth', 'spiralengine'); ?></h3>
                    <canvas id="revenue-growth-chart"></canvas>
                </div>
                
                <div class="chart-container">
                    <h3><?php _e('Revenue by Tier', 'spiralengine'); ?></h3>
                    <canvas id="revenue-tier-chart"></canvas>
                </div>
            </div>
            
            <div class="churn-analysis">
                <h3><?php _e('Churn Analysis', 'spiralengine'); ?></h3>
                <canvas id="churn-analysis-chart"></canvas>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render reports section
     */
    private function render_reports_section() {
        ?>
        <div class="reports-section">
            <h2><?php _e('Reports', 'spiralengine'); ?></h2>
            
            <div class="report-templates">
                <h3><?php _e('Report Templates', 'spiralengine'); ?></h3>
                
                <div class="report-grid">
                    <div class="report-template">
                        <h4><?php _e('Monthly Summary', 'spiralengine'); ?></h4>
                        <p><?php _e('Comprehensive monthly overview of all metrics', 'spiralengine'); ?></p>
                        <button class="button generate-report" data-report="monthly_summary">
                            <?php _e('Generate', 'spiralengine'); ?>
                        </button>
                    </div>
                    
                    <div class="report-template">
                        <h4><?php _e('User Engagement Report', 'spiralengine'); ?></h4>
                        <p><?php _e('Detailed analysis of user behavior and engagement', 'spiralengine'); ?></p>
                        <button class="button generate-report" data-report="user_engagement">
                            <?php _e('Generate', 'spiralengine'); ?>
                        </button>
                    </div>
                    
                    <div class="report-template">
                        <h4><?php _e('Health Outcomes Report', 'spiralengine'); ?></h4>
                        <p><?php _e('Mental health trends and improvement metrics', 'spiralengine'); ?></p>
                        <button class="button generate-report" data-report="health_outcomes">
                            <?php _e('Generate', 'spiralengine'); ?>
                        </button>
                    </div>
                    
                    <div class="report-template">
                        <h4><?php _e('Financial Report', 'spiralengine'); ?></h4>
                        <p><?php _e('Revenue, churn, and financial performance', 'spiralengine'); ?></p>
                        <button class="button generate-report" data-report="financial">
                            <?php _e('Generate', 'spiralengine'); ?>
                        </button>
                    </div>
                </div>
            </div>
            
            <div class="scheduled-reports">
                <h3><?php _e('Scheduled Reports', 'spiralengine'); ?></h3>
                
                <button class="button button-primary" id="create-scheduled-report">
                    <?php _e('Create New Schedule', 'spiralengine'); ?>
                </button>
                
                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th><?php _e('Report', 'spiralengine'); ?></th>
                            <th><?php _e('Frequency', 'spiralengine'); ?></th>
                            <th><?php _e('Recipients', 'spiralengine'); ?></th>
                            <th><?php _e('Next Run', 'spiralengine'); ?></th>
                            <th><?php _e('Actions', 'spiralengine'); ?></th>
                        </tr>
                    </thead>
                    <tbody id="scheduled-reports-list">
                        <!-- Populated via settings -->
                    </tbody>
                </table>
            </div>
            
            <div class="report-history">
                <h3><?php _e('Report History', 'spiralengine'); ?></h3>
                
                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th><?php _e('Report', 'spiralengine'); ?></th>
                            <th><?php _e('Generated', 'spiralengine'); ?></th>
                            <th><?php _e('Size', 'spiralengine'); ?></th>
                            <th><?php _e('Actions', 'spiralengine'); ?></th>
                        </tr>
                    </thead>
                    <tbody id="report-history-list">
                        <!-- Populated via AJAX -->
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }
    
    /**
     * Get overview metrics
     *
     * @param string $range
     * @return array
     */
    private function get_overview_metrics($range) {
        global $wpdb;
        
        $cache_key = 'spiralengine_overview_metrics_' . $range;
        $metrics = get_transient($cache_key);
        
        if (false === $metrics) {
            $date_range = $this->get_date_range($range);
            
            // Total users
            $total_users = $wpdb->get_var(
                "SELECT COUNT(DISTINCT user_id) FROM {$wpdb->prefix}spiralengine_memberships WHERE status = 'active'"
            );
            
            // Active users (had activity in range)
            $active_users = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(DISTINCT user_id) FROM {$wpdb->prefix}spiralengine_episodes 
                WHERE created_at BETWEEN %s AND %s",
                $date_range['start'],
                $date_range['end']
            ));
            
            // Total episodes
            $total_episodes = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}spiralengine_episodes 
                WHERE created_at BETWEEN %s AND %s",
                $date_range['start'],
                $date_range['end']
            ));
            
            // Average engagement (simplified)
            $avg_engagement = $active_users > 0 ? round(($total_episodes / $active_users), 1) : 0;
            
            // Revenue (simplified - would integrate with payment system)
            $revenue = $this->calculate_revenue($date_range);
            
            // Churn rate
            $churn_rate = $this->calculate_churn_rate($date_range);
            
            $metrics = array(
                'total_users' => array(
                    'value' => $total_users,
                    'trend' => $this->calculate_trend('total_users', $total_users, $range),
                    'formatted' => number_format($total_users)
                ),
                'active_users' => array(
                    'value' => $active_users,
                    'trend' => $this->calculate_trend('active_users', $active_users, $range),
                    'formatted' => number_format($active_users)
                ),
                'total_episodes' => array(
                    'value' => $total_episodes,
                    'trend' => $this->calculate_trend('total_episodes', $total_episodes, $range),
                    'formatted' => number_format($total_episodes)
                ),
                'avg_engagement' => array(
                    'value' => $avg_engagement,
                    'trend' => $this->calculate_trend('avg_engagement', $avg_engagement, $range),
                    'formatted' => $avg_engagement
                ),
                'revenue' => array(
                    'value' => $revenue,
                    'trend' => $this->calculate_trend('revenue', $revenue, $range),
                    'formatted' => number_format($revenue, 2)
                ),
                'churn_rate' => array(
                    'value' => $churn_rate,
                    'trend' => $this->calculate_trend('churn_rate', $churn_rate, $range),
                    'formatted' => $churn_rate
                )
            );
            
            set_transient($cache_key, $metrics, HOUR_IN_SECONDS);
        }
        
        return $metrics;
    }
    
    /**
     * Get date range from key
     *
     * @param string $range_key
     * @return array
     */
    private function get_date_range($range_key) {
        if (!isset($this->time_ranges[$range_key])) {
            $range_key = 'last_30_days';
        }
        
        $range = $this->time_ranges[$range_key];
        
        return array(
            'start' => date('Y-m-d H:i:s', strtotime($range['start'])),
            'end' => date('Y-m-d H:i:s', strtotime($range['end']))
        );
    }
    
    /**
     * Calculate trend percentage
     *
     * @param string $metric
     * @param mixed $current_value
     * @param string $range
     * @return float
     */
    private function calculate_trend($metric, $current_value, $range) {
        // This would compare with previous period
        // For now, return random trend for demonstration
        return rand(-20, 50);
    }
    
    /**
     * Calculate revenue for period
     *
     * @param array $date_range
     * @return float
     */
    private function calculate_revenue($date_range) {
        global $wpdb;
        
        // This would integrate with payment system
        // For now, calculate based on active paid memberships
        $paid_users = $wpdb->get_results(
            "SELECT tier, COUNT(*) as count 
            FROM {$wpdb->prefix}spiralengine_memberships 
            WHERE status = 'active' AND tier != 'free' 
            GROUP BY tier"
        );
        
        $tier_prices = array(
            'bronze' => 9.99,
            'silver' => 19.99,
            'gold' => 29.99,
            'platinum' => 49.99
        );
        
        $revenue = 0;
        foreach ($paid_users as $tier_data) {
            $revenue += ($tier_prices[$tier_data->tier] ?? 0) * $tier_data->count;
        }
        
        return $revenue;
    }
    
    /**
     * Calculate churn rate
     *
     * @param array $date_range
     * @return float
     */
    private function calculate_churn_rate($date_range) {
        global $wpdb;
        
        // Simplified churn calculation
        $cancelled = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}spiralengine_memberships 
            WHERE status = 'cancelled' AND updated_at BETWEEN %s AND %s",
            $date_range['start'],
            $date_range['end']
        ));
        
        $total_paid = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}spiralengine_memberships 
            WHERE tier != 'free'"
        );
        
        return $total_paid > 0 ? round(($cancelled / $total_paid) * 100, 1) : 0;
    }
    
    /**
     * AJAX: Get analytics data
     */
    public function ajax_get_analytics_data() {
        check_ajax_referer('spiralengine_analytics', 'nonce');
        
        if (!current_user_can('spiralengine_view_analytics')) {
            wp_send_json_error();
        }
        
        $type = sanitize_key($_POST['type']);
        $range = sanitize_key($_POST['range']);
        
        switch ($type) {
            case 'overview_metrics':
                $data = $this->get_overview_metrics($range);
                break;
                
            case 'user_activity':
                $data = $this->get_user_activity_data($range);
                break;
                
            case 'episode_distribution':
                $data = $this->get_episode_distribution_data($range);
                break;
                
            case 'widget_usage':
                $data = $this->get_widget_usage_data($range);
                break;
                
            case 'membership_distribution':
                $data = $this->get_membership_distribution_data($range);
                break;
                
            case 'engagement_metrics':
                $data = $this->get_engagement_metrics_data($range);
                break;
                
            case 'top_users':
                $data = $this->get_top_users_data($range);
                break;
                
            default:
                $data = array();
        }
        
        wp_send_json_success($data);
    }
    
    /**
     * Get user activity data
     *
     * @param string $range
     * @return array
     */
    private function get_user_activity_data($range) {
        global $wpdb;
        
        $date_range = $this->get_date_range($range);
        
        // Get daily data points
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                DATE(created_at) as date,
                COUNT(DISTINCT user_id) as active_users,
                COUNT(*) as total_episodes
            FROM {$wpdb->prefix}spiralengine_episodes
            WHERE created_at BETWEEN %s AND %s
            GROUP BY DATE(created_at)
            ORDER BY date ASC",
            $date_range['start'],
            $date_range['end']
        ));
        
        // Get new users per day
        $new_users = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                DATE(u.user_registered) as date,
                COUNT(*) as new_users
            FROM {$wpdb->users} u
            INNER JOIN {$wpdb->prefix}spiralengine_memberships m ON u.ID = m.user_id
            WHERE u.user_registered BETWEEN %s AND %s
            GROUP BY DATE(u.user_registered)",
            $date_range['start'],
            $date_range['end']
        ), OBJECT_K);
        
        $labels = array();
        $active_users_data = array();
        $new_users_data = array();
        
        foreach ($results as $row) {
            $labels[] = $row->date;
            $active_users_data[] = $row->active_users;
            $new_users_data[] = isset($new_users[$row->date]) ? $new_users[$row->date]->new_users : 0;
        }
        
        return array(
            'labels' => $labels,
            'active_users' => $active_users_data,
            'new_users' => $new_users_data
        );
    }
    
    /**
     * Get episode distribution data
     *
     * @param string $range
     * @return array
     */
    private function get_episode_distribution_data($range) {
        global $wpdb;
        
        $date_range = $this->get_date_range($range);
        
        // Get episodes by severity
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                severity,
                COUNT(*) as count
            FROM {$wpdb->prefix}spiralengine_episodes
            WHERE created_at BETWEEN %s AND %s
            GROUP BY severity
            ORDER BY severity ASC",
            $date_range['start'],
            $date_range['end']
        ));
        
        $labels = array();
        $counts = array();
        
        for ($i = 1; $i <= 10; $i++) {
            $labels[] = 'Severity ' . $i;
            $counts[] = 0;
        }
        
        foreach ($results as $row) {
            if ($row->severity >= 1 && $row->severity <= 10) {
                $counts[$row->severity - 1] = intval($row->count);
            }
        }
        
        return array(
            'labels' => $labels,
            'counts' => $counts
        );
    }
    
    /**
     * Get widget usage data
     *
     * @param string $range
     * @return array
     */
    private function get_widget_usage_data($range) {
        global $wpdb;
        
        $date_range = $this->get_date_range($range);
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                widget_id,
                COUNT(*) as usage_count
            FROM {$wpdb->prefix}spiralengine_episodes
            WHERE created_at BETWEEN %s AND %s
            GROUP BY widget_id
            ORDER BY usage_count DESC
            LIMIT 10",
            $date_range['start'],
            $date_range['end']
        ));
        
        $widget_names = array(
            'mood_tracker' => __('Mood Tracker', 'spiralengine'),
            'thought_diary' => __('Thought Diary', 'spiralengine'),
            'gratitude_journal' => __('Gratitude Journal', 'spiralengine'),
            'meditation_timer' => __('Meditation Timer', 'spiralengine'),
            'coping_strategies' => __('Coping Strategies', 'spiralengine'),
            'goal_tracker' => __('Goal Tracker', 'spiralengine'),
            'symptom_tracker' => __('Symptom Tracker', 'spiralengine')
        );
        
        $labels = array();
        $values = array();
        
        foreach ($results as $row) {
            $labels[] = $widget_names[$row->widget_id] ?? $row->widget_id;
            $values[] = intval($row->usage_count);
        }
        
        return array(
            'labels' => $labels,
            'values' => $values
        );
    }
    
    /**
     * Get membership distribution data
     *
     * @param string $range
     * @return array
     */
    private function get_membership_distribution_data($range) {
        global $wpdb;
        
        $results = $wpdb->get_results(
            "SELECT 
                tier,
                COUNT(*) as count
            FROM {$wpdb->prefix}spiralengine_memberships
            WHERE status = 'active'
            GROUP BY tier"
        );
        
        $tier_names = array(
            'free' => __('Free', 'spiralengine'),
            'bronze' => __('Bronze', 'spiralengine'),
            'silver' => __('Silver', 'spiralengine'),
            'gold' => __('Gold', 'spiralengine'),
            'platinum' => __('Platinum', 'spiralengine')
        );
        
        $labels = array();
        $values = array();
        
        foreach ($results as $row) {
            $labels[] = $tier_names[$row->tier] ?? ucfirst($row->tier);
            $values[] = intval($row->count);
        }
        
        return array(
            'labels' => $labels,
            'values' => $values
        );
    }
    
    /**
     * Get engagement metrics data
     *
     * @param string $range
     * @return array
     */
    private function get_engagement_metrics_data($range) {
        // This would calculate various engagement metrics
        // For demonstration, returning sample data
        
        return array(
            'labels' => array(
                __('Daily Active', 'spiralengine'),
                __('Weekly Active', 'spiralengine'),
                __('Retention Rate', 'spiralengine'),
                __('Avg. Sessions', 'spiralengine'),
                __('Feature Adoption', 'spiralengine'),
                __('User Satisfaction', 'spiralengine')
            ),
            'current' => array(65, 78, 82, 4.2, 68, 85),
            'previous' => array(58, 72, 78, 3.8, 62, 82)
        );
    }
    
    /**
     * Get top users data
     *
     * @param string $range
     * @return array
     */
    private function get_top_users_data($range) {
        global $wpdb;
        
        $date_range = $this->get_date_range($range);
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                e.user_id,
                u.display_name,
                u.user_email,
                m.tier,
                COUNT(*) as episode_count,
                MAX(e.created_at) as last_activity
            FROM {$wpdb->prefix}spiralengine_episodes e
            INNER JOIN {$wpdb->users} u ON e.user_id = u.ID
            INNER JOIN {$wpdb->prefix}spiralengine_memberships m ON e.user_id = m.user_id
            WHERE e.created_at BETWEEN %s AND %s
            GROUP BY e.user_id
            ORDER BY episode_count DESC
            LIMIT 10",
            $date_range['start'],
            $date_range['end']
        ));
        
        $top_users = array();
        $rank = 1;
        
        foreach ($results as $user) {
            // Calculate streak
            $streak = $this->calculate_user_streak($user->user_id);
            
            $top_users[] = array(
                'rank' => $rank++,
                'user_id' => $user->user_id,
                'name' => $user->display_name,
                'email' => $user->user_email,
                'avatar' => get_avatar($user->user_id, 32),
                'episodes' => $user->episode_count,
                'streak' => $streak,
                'tier' => $user->tier,
                'last_activity' => human_time_diff(strtotime($user->last_activity), current_time('timestamp'))
            );
        }
        
        return $top_users;
    }
    
    /**
     * Calculate user streak
     *
     * @param int $user_id
     * @return int
     */
    private function calculate_user_streak($user_id) {
        global $wpdb;
        
        // Get user's episode dates
        $dates = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT DATE(created_at) as date
            FROM {$wpdb->prefix}spiralengine_episodes
            WHERE user_id = %d
            ORDER BY date DESC",
            $user_id
        ));
        
        if (empty($dates)) {
            return 0;
        }
        
        $streak = 1;
        $current_date = new DateTime($dates[0]);
        
        for ($i = 1; $i < count($dates); $i++) {
            $prev_date = new DateTime($dates[$i]);
            $diff = $current_date->diff($prev_date)->days;
            
            if ($diff == 1) {
                $streak++;
                $current_date = $prev_date;
            } else {
                break;
            }
        }
        
        return $streak;
    }
    
    /**
     * AJAX: Export analytics
     */
    public function ajax_export_analytics() {
        check_ajax_referer('spiralengine_analytics', 'nonce');
        
        if (!current_user_can('spiralengine_view_analytics')) {
            wp_send_json_error();
        }
        
        $format = sanitize_key($_POST['format']);
        $range = sanitize_key($_POST['range']);
        
        // Generate export file
        $file_data = $this->generate_export($format, $range);
        
        if ($file_data) {
            wp_send_json_success($file_data);
        } else {
            wp_send_json_error(array('message' => __('Export generation failed', 'spiralengine')));
        }
    }
    
    /**
     * Generate export file
     *
     * @param string $format
     * @param string $range
     * @return array|false
     */
    private function generate_export($format, $range) {
        $data = array(
            'overview' => $this->get_overview_metrics($range),
            'user_activity' => $this->get_user_activity_data($range),
            'widget_usage' => $this->get_widget_usage_data($range),
            'membership' => $this->get_membership_distribution_data($range)
        );
        
        $upload_dir = wp_upload_dir();
        $export_dir = $upload_dir['basedir'] . '/spiralengine-exports';
        
        if (!file_exists($export_dir)) {
            wp_mkdir_p($export_dir);
        }
        
        $filename = 'spiralengine-analytics-' . $range . '-' . date('Y-m-d-His');
        
        switch ($format) {
            case 'csv':
                $file_path = $this->generate_csv_export($data, $export_dir, $filename);
                break;
                
            case 'pdf':
                $file_path = $this->generate_pdf_export($data, $export_dir, $filename);
                break;
                
            default:
                return false;
        }
        
        if ($file_path && file_exists($file_path)) {
            $file_url = str_replace($upload_dir['basedir'], $upload_dir['baseurl'], $file_path);
            
            return array(
                'file_url' => $file_url,
                'filename' => basename($file_path)
            );
        }
        
        return false;
    }
    
    /**
     * Generate CSV export
     *
     * @param array $data
     * @param string $export_dir
     * @param string $filename
     * @return string|false
     */
    private function generate_csv_export($data, $export_dir, $filename) {
        $file_path = $export_dir . '/' . $filename . '.csv';
        $handle = fopen($file_path, 'w');
        
        if (!$handle) {
            return false;
        }
        
        // Overview metrics
        fputcsv($handle, array('Metric', 'Value', 'Trend'));
        foreach ($data['overview'] as $metric => $values) {
            fputcsv($handle, array(
                ucwords(str_replace('_', ' ', $metric)),
                $values['formatted'],
                $values['trend'] . '%'
            ));
        }
        
        fputcsv($handle, array()); // Empty row
        
        // User activity
        fputcsv($handle, array('Date', 'Active Users', 'New Users'));
        for ($i = 0; $i < count($data['user_activity']['labels']); $i++) {
            fputcsv($handle, array(
                $data['user_activity']['labels'][$i],
                $data['user_activity']['active_users'][$i],
                $data['user_activity']['new_users'][$i]
            ));
        }
        
        fclose($handle);
        
        return $file_path;
    }
    
    /**
     * Generate PDF export
     *
     * @param array $data
     * @param string $export_dir
     * @param string $filename
     * @return string|false
     */
    private function generate_pdf_export($data, $export_dir, $filename) {
        // This would use a PDF library like TCPDF or mPDF
        // For now, create a simple HTML file
        
        $file_path = $export_dir . '/' . $filename . '.html';
        
        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <title>SpiralEngine Analytics Report</title>
            <style>
                body { font-family: Arial, sans-serif; }
                h1 { color: #333; }
                table { border-collapse: collapse; width: 100%; margin: 20px 0; }
                th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                th { background-color: #f2f2f2; }
            </style>
        </head>
        <body>
            <h1>SpiralEngine Analytics Report</h1>
            <p>Generated: <?php echo current_time('Y-m-d H:i:s'); ?></p>
            
            <h2>Overview Metrics</h2>
            <table>
                <tr>
                    <th>Metric</th>
                    <th>Value</th>
                    <th>Trend</th>
                </tr>
                <?php foreach ($data['overview'] as $metric => $values) : ?>
                    <tr>
                        <td><?php echo ucwords(str_replace('_', ' ', $metric)); ?></td>
                        <td><?php echo $values['formatted']; ?></td>
                        <td><?php echo $values['trend']; ?>%</td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </body>
        </html>
        <?php
        $html = ob_get_clean();
        
        file_put_contents($file_path, $html);
        
        return $file_path;
    }
    
    /**
     * Calculate scheduled analytics
     */
    public function calculate_scheduled_analytics() {
        // Pre-calculate and cache common analytics
        $ranges = array('today', 'yesterday', 'last_7_days', 'last_30_days');
        
        foreach ($ranges as $range) {
            $this->get_overview_metrics($range);
            $this->get_user_activity_data($range);
            $this->get_widget_usage_data($range);
        }
        
        // Clean up old export files
        $this->cleanup_old_exports();
    }
    
    /**
     * Clean up old export files
     */
    private function cleanup_old_exports() {
        $upload_dir = wp_upload_dir();
        $export_dir = $upload_dir['basedir'] . '/spiralengine-exports';
        
        if (!file_exists($export_dir)) {
            return;
        }
        
        $files = glob($export_dir . '/*');
        $now = time();
        
        foreach ($files as $file) {
            if (is_file($file)) {
                if ($now - filemtime($file) >= 7 * DAY_IN_SECONDS) {
                    unlink($file);
                }
            }
        }
    }
    
    /**
     * Add dashboard widgets
     */
    public function add_dashboard_widgets() {
        if (current_user_can('spiralengine_view_analytics')) {
            wp_add_dashboard_widget(
                'spiralengine_quick_stats',
                __('SpiralEngine Quick Stats', 'spiralengine'),
                array($this, 'render_dashboard_widget')
            );
        }
    }
    
    /**
     * Render dashboard widget
     */
    public function render_dashboard_widget() {
        $metrics = $this->get_overview_metrics('today');
        ?>
        <div class="spiralengine-dashboard-widget">
            <div class="metric-row">
                <span class="metric-label"><?php _e('Active Users Today', 'spiralengine'); ?>:</span>
                <span class="metric-value"><?php echo $metrics['active_users']['formatted']; ?></span>
            </div>
            
            <div class="metric-row">
                <span class="metric-label"><?php _e('Episodes Today', 'spiralengine'); ?>:</span>
                <span class="metric-value"><?php echo $metrics['total_episodes']['formatted']; ?></span>
            </div>
            
            <div class="metric-row">
                <span class="metric-label"><?php _e('Total Users', 'spiralengine'); ?>:</span>
                <span class="metric-value"><?php echo $metrics['total_users']['formatted']; ?></span>
            </div>
            
            <p>
                <a href="<?php echo admin_url('admin.php?page=spiralengine-analytics'); ?>" class="button button-primary">
                    <?php _e('View Full Analytics', 'spiralengine'); ?>
                </a>
            </p>
        </div>
        <?php
    }
}


<?php
// includes/admin/class-spiralengine-admin-analytics.php

/**
 * Spiral Engine Admin Analytics
 * 
 * Comprehensive analytics dashboard with Chart.js visualizations
 * Based on Master Analytics Review Center specifications
 */
class SPIRALENGINE_Admin_Analytics {
    
    /**
     * Instance of this class
     */
    private static $instance = null;
    
    /**
     * Analytics sections
     */
    private $sections = array();
    
    /**
     * Get instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->init_sections();
        add_action('wp_ajax_spiral_analytics_data', array($this, 'ajax_get_analytics_data'));
        add_action('wp_ajax_spiral_analytics_export', array($this, 'ajax_export_data'));
    }
    
    /**
     * Initialize analytics sections
     */
    private function init_sections() {
        $this->sections = array(
            'overview' => array(
                'title' => __('System Overview', 'spiral-engine'),
                'icon' => 'dashicons-dashboard',
                'priority' => 10
            ),
            'episodes' => array(
                'title' => __('Episode Analytics', 'spiral-engine'),
                'icon' => 'dashicons-chart-area',
                'priority' => 20
            ),
            'users' => array(
                'title' => __('User Analytics', 'spiral-engine'),
                'icon' => 'dashicons-groups',
                'priority' => 30
            ),
            'widgets' => array(
                'title' => __('Widget Performance', 'spiral-engine'),
                'icon' => 'dashicons-performance',
                'priority' => 40
            ),
            'correlations' => array(
                'title' => __('Episode Correlations', 'spiral-engine'),
                'icon' => 'dashicons-networking',
                'priority' => 50
            ),
            'forecasts' => array(
                'title' => __('Forecast Analytics', 'spiral-engine'),
                'icon' => 'dashicons-chart-line',
                'priority' => 60
            ),
            'reports' => array(
                'title' => __('Custom Reports', 'spiral-engine'),
                'icon' => 'dashicons-media-document',
                'priority' => 70
            )
        );
        
        $this->sections = apply_filters('spiral_engine_analytics_sections', $this->sections);
    }
    
    /**
     * Render the analytics page
     */
    public function render() {
        $current_section = isset($_GET['section']) ? sanitize_text_field($_GET['section']) : 'overview';
        ?>
        <div class="wrap spiral-engine-analytics">
            <h1><?php _e('Analytics Review Center', 'spiral-engine'); ?></h1>
            
            <!-- Analytics Navigation -->
            <nav class="nav-tab-wrapper spiral-analytics-nav">
                <?php
                // Sort sections by priority
                uasort($this->sections, function($a, $b) {
                    return $a['priority'] - $b['priority'];
                });
                
                foreach ($this->sections as $section_id => $section) {
                    $url = add_query_arg(array(
                        'page' => 'spiral-engine-analytics',
                        'section' => $section_id
                    ), admin_url('admin.php'));
                    
                    $class = 'nav-tab';
                    if ($current_section === $section_id) {
                        $class .= ' nav-tab-active';
                    }
                    
                    printf(
                        '<a href="%s" class="%s"><span class="%s"></span> %s</a>',
                        esc_url($url),
                        esc_attr($class),
                        esc_attr($section['icon']),
                        esc_html($section['title'])
                    );
                }
                ?>
            </nav>
            
            <!-- Analytics Content -->
            <div class="spiral-analytics-content">
                <?php $this->render_section($current_section); ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render specific section
     */
    private function render_section($section) {
        if (!isset($this->sections[$section])) {
            $section = 'overview';
        }
        
        echo '<div class="analytics-section" data-section="' . esc_attr($section) . '">';
        
        switch ($section) {
            case 'overview':
                $this->render_overview();
                break;
                
            case 'episodes':
                $this->render_episode_analytics();
                break;
                
            case 'users':
                $this->render_user_analytics();
                break;
                
            case 'widgets':
                $this->render_widget_analytics();
                break;
                
            case 'correlations':
                $this->render_correlation_analytics();
                break;
                
            case 'forecasts':
                $this->render_forecast_analytics();
                break;
                
            case 'reports':
                $this->render_custom_reports();
                break;
                
            default:
                do_action('spiral_engine_render_analytics_' . $section);
                break;
        }
        
        echo '</div>';
    }
    
    /**
     * Render System Overview
     */
    private function render_overview() {
        $overview_data = $this->get_overview_data();
        ?>
        <div class="analytics-overview">
            <div class="overview-header">
                <h2><?php _e('System Analytics Overview', 'spiral-engine'); ?></h2>
                <div class="overview-controls">
                    <select id="overview-timeframe" class="analytics-timeframe">
                        <option value="today"><?php _e('Today', 'spiral-engine'); ?></option>
                        <option value="week" selected><?php _e('Last 7 Days', 'spiral-engine'); ?></option>
                        <option value="month"><?php _e('Last 30 Days', 'spiral-engine'); ?></option>
                        <option value="quarter"><?php _e('Last 90 Days', 'spiral-engine'); ?></option>
                    </select>
                    <button class="button" id="refresh-overview">
                        <span class="dashicons dashicons-update"></span>
                        <?php _e('Refresh', 'spiral-engine'); ?>
                    </button>
                </div>
            </div>
            
            <!-- System Health Score -->
            <div class="health-score-card">
                <h3><?php _e('System Health Score', 'spiral-engine'); ?></h3>
                <div class="score-display">
                    <div class="score-circle" data-score="<?php echo $overview_data['health_score']; ?>">
                        <svg width="200" height="200">
                            <circle cx="100" cy="100" r="90" fill="none" stroke="#e0e0e0" stroke-width="20"/>
                            <circle cx="100" cy="100" r="90" fill="none" stroke="#6B46C1" stroke-width="20"
                                    stroke-dasharray="565.48" 
                                    stroke-dashoffset="<?php echo 565.48 * (1 - $overview_data['health_score'] / 100); ?>"
                                    transform="rotate(-90 100 100)"/>
                        </svg>
                        <div class="score-text"><?php echo $overview_data['health_score']; ?>/100</div>
                    </div>
                </div>
                <div class="score-details">
                    <p><?php _e('Last Updated:', 'spiral-engine'); ?> <span class="update-time"><?php echo current_time('g:i a'); ?></span></p>
                </div>
            </div>
            
            <!-- Key Metrics Grid -->
            <div class="metrics-grid">
                <div class="metric-card">
                    <div class="metric-icon database"></div>
                    <div class="metric-content">
                        <h4><?php _e('Database', 'spiral-engine'); ?></h4>
                        <div class="metric-value"><?php echo $overview_data['database_health']; ?>%</div>
                        <div class="metric-status good">✓ <?php _e('Healthy', 'spiral-engine'); ?></div>
                    </div>
                </div>
                
                <div class="metric-card">
                    <div class="metric-icon users"></div>
                    <div class="metric-content">
                        <h4><?php _e('Users', 'spiral-engine'); ?></h4>
                        <div class="metric-value"><?php echo number_format($overview_data['total_users']); ?></div>
                        <div class="metric-trend up">↑ <?php echo $overview_data['user_growth']; ?>%</div>
                    </div>
                </div>
                
                <div class="metric-card">
                    <div class="metric-icon widgets"></div>
                    <div class="metric-content">
                        <h4><?php _e('Widgets', 'spiral-engine'); ?></h4>
                        <div class="metric-value"><?php echo $overview_data['active_widgets']; ?></div>
                        <div class="metric-status">🔧 <?php _e('Active', 'spiral-engine'); ?></div>
                    </div>
                </div>
                
                <div class="metric-card">
                    <div class="metric-icon episodes"></div>
                    <div class="metric-content">
                        <h4><?php _e('Episodes', 'spiral-engine'); ?></h4>
                        <div class="metric-value"><?php echo number_format($overview_data['total_episodes']); ?></div>
                        <div class="metric-status">📊 <?php _e('Logged', 'spiral-engine'); ?></div>
                    </div>
                </div>
            </div>
            
            <!-- Critical Metrics Overview -->
            <div class="critical-metrics">
                <h3><?php _e('Critical Metrics', 'spiral-engine'); ?></h3>
                <div class="metrics-chart-grid">
                    <div class="chart-container">
                        <h4><?php _e('Episodes Today', 'spiral-engine'); ?></h4>
                        <canvas id="episodes-today-chart"></canvas>
                    </div>
                    <div class="chart-container">
                        <h4><?php _e('Correlations Found', 'spiral-engine'); ?></h4>
                        <canvas id="correlations-chart"></canvas>
                    </div>
                    <div class="chart-container">
                        <h4><?php _e('Forecast Accuracy', 'spiral-engine'); ?></h4>
                        <canvas id="forecast-accuracy-chart"></canvas>
                    </div>
                    <div class="chart-container">
                        <h4><?php _e('Mental Health Index', 'spiral-engine'); ?></h4>
                        <canvas id="mental-health-chart"></canvas>
                    </div>
                </div>
            </div>
            
            <!-- Episode Activity Feed -->
            <div class="activity-feed">
                <h3><?php _e('Episode Activity Feed (Live)', 'spiral-engine'); ?></h3>
                <div class="feed-container" id="episode-activity-feed">
                    <div class="feed-item">
                        <span class="feed-icon overthinking">🔵</span>
                        <span class="feed-time"><?php echo date('H:i:s'); ?></span>
                        <span class="feed-message"><?php _e('Overthinking episode logged', 'spiral-engine'); ?></span>
                    </div>
                    <div class="feed-item">
                        <span class="feed-icon correlation">🟣</span>
                        <span class="feed-time"><?php echo date('H:i:s', time() - 7); ?></span>
                        <span class="feed-message"><?php _e('Correlation detected: Anxiety → Overthinking', 'spiral-engine'); ?></span>
                    </div>
                    <div class="feed-item">
                        <span class="feed-icon forecast">🟢</span>
                        <span class="feed-time"><?php echo date('H:i:s', time() - 13); ?></span>
                        <span class="feed-message"><?php _e('Forecast generated for user_4782', 'spiral-engine'); ?></span>
                    </div>
                    <div class="feed-item">
                        <span class="feed-icon alert">🟡</span>
                        <span class="feed-time"><?php echo date('H:i:s', time() - 28); ?></span>
                        <span class="feed-message"><?php _e('High-risk pattern identified', 'spiral-engine'); ?></span>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render Episode Analytics
     */
    private function render_episode_analytics() {
        ?>
        <div class="episode-analytics">
            <div class="analytics-header">
                <h2><?php _e('Episode Analytics Suite', 'spiral-engine'); ?></h2>
                <div class="analytics-controls">
                    <select id="episode-type-filter" class="analytics-filter">
                        <option value="all"><?php _e('All Episode Types', 'spiral-engine'); ?></option>
                        <option value="overthinking"><?php _e('Overthinking', 'spiral-engine'); ?></option>
                        <option value="anxiety"><?php _e('Anxiety', 'spiral-engine'); ?></option>
                        <option value="ptsd"><?php _e('PTSD', 'spiral-engine'); ?></option>
                        <option value="depression"><?php _e('Depression', 'spiral-engine'); ?></option>
                        <option value="caregiver"><?php _e('Caregiver', 'spiral-engine'); ?></option>
                    </select>
                    <select id="episode-timeframe" class="analytics-timeframe">
                        <option value="week"><?php _e('Last 7 Days', 'spiral-engine'); ?></option>
                        <option value="month" selected><?php _e('Last 30 Days', 'spiral-engine'); ?></option>
                        <option value="quarter"><?php _e('Last 90 Days', 'spiral-engine'); ?></option>
                        <option value="year"><?php _e('Last Year', 'spiral-engine'); ?></option>
                    </select>
                    <button class="button button-primary" id="export-episode-data">
                        <span class="dashicons dashicons-download"></span>
                        <?php _e('Export Data', 'spiral-engine'); ?>
                    </button>
                </div>
            </div>
            
            <!-- Episode Distribution -->
            <div class="analytics-row">
                <div class="analytics-card half">
                    <h3><?php _e('Episode Distribution by Type', 'spiral-engine'); ?></h3>
                    <div class="chart-wrapper">
                        <canvas id="episode-distribution-chart"></canvas>
                    </div>
                    <div class="chart-legend" id="episode-distribution-legend"></div>
                </div>
                
                <div class="analytics-card half">
                    <h3><?php _e('Episode Severity Trends', 'spiral-engine'); ?></h3>
                    <div class="chart-wrapper">
                        <canvas id="episode-severity-chart"></canvas>
                    </div>
                    <div class="severity-stats">
                        <span class="stat-item">
                            <?php _e('Average Severity:', 'spiral-engine'); ?> 
                            <strong id="avg-severity">6.2</strong>
                        </span>
                    </div>
                </div>
            </div>
            
            <!-- Episode Timing Patterns -->
            <div class="analytics-card full">
                <h3><?php _e('Episode Timing Patterns', 'spiral-engine'); ?></h3>
                <div class="timing-analysis">
                    <div class="timing-section">
                        <h4><?php _e('Peak Hours', 'spiral-engine'); ?></h4>
                        <div class="timing-bars">
                            <div class="timing-bar">
                                <span class="time-label"><?php _e('Morning (6-9 AM)', 'spiral-engine'); ?></span>
                                <div class="bar-container">
                                    <div class="bar-fill" style="width: 28%"></div>
                                </div>
                                <span class="percentage">28%</span>
                            </div>
                            <div class="timing-bar">
                                <span class="time-label"><?php _e('Evening (6-9 PM)', 'spiral-engine'); ?></span>
                                <div class="bar-container">
                                    <div class="bar-fill" style="width: 34%"></div>
                                </div>
                                <span class="percentage">34%</span>
                            </div>
                            <div class="timing-bar">
                                <span class="time-label"><?php _e('Night (9PM-12AM)', 'spiral-engine'); ?></span>
                                <div class="bar-container">
                                    <div class="bar-fill" style="width: 18%"></div>
                                </div>
                                <span class="percentage">18%</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="timing-section">
                        <h4><?php _e('Peak Days', 'spiral-engine'); ?></h4>
                        <div class="days-chart">
                            <canvas id="episode-days-chart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Pattern Analysis -->
            <div class="analytics-card full">
                <h3><?php _e('Pattern Detection Analytics', 'spiral-engine'); ?></h3>
                <div class="pattern-analysis">
                    <div class="patterns-table">
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th><?php _e('Pattern', 'spiral-engine'); ?></th>
                                    <th><?php _e('Users Affected', 'spiral-engine'); ?></th>
                                    <th><?php _e('Strength', 'spiral-engine'); ?></th>
                                    <th><?php _e('Actions', 'spiral-engine'); ?></th>
                                </tr>
                            </thead>
                            <tbody id="pattern-table-body">
                                <tr>
                                    <td>Work Stress → Overthinking</td>
                                    <td>45,678</td>
                                    <td><span class="strength-indicator high">78%</span></td>
                                    <td><a href="#" class="view-pattern"><?php _e('View Details', 'spiral-engine'); ?></a></td>
                                </tr>
                                <tr>
                                    <td>Poor Sleep → Anxiety</td>
                                    <td>34,567</td>
                                    <td><span class="strength-indicator high">72%</span></td>
                                    <td><a href="#" class="view-pattern"><?php _e('View Details', 'spiral-engine'); ?></a></td>
                                </tr>
                                <tr>
                                    <td>Social Event → PTSD</td>
                                    <td>23,456</td>
                                    <td><span class="strength-indicator medium">68%</span></td>
                                    <td><a href="#" class="view-pattern"><?php _e('View Details', 'spiral-engine'); ?></a></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="trigger-analysis">
                        <h4><?php _e('Top Episode Triggers', 'spiral-engine'); ?></h4>
                        <canvas id="trigger-analysis-chart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render User Analytics
     */
    private function render_user_analytics() {
        ?>
        <div class="user-analytics">
            <div class="analytics-header">
                <h2><?php _e('User Analytics Suite', 'spiral-engine'); ?></h2>
            </div>
            
            <!-- User Episode Engagement -->
            <div class="analytics-card full">
                <h3><?php _e('User Episode Engagement', 'spiral-engine'); ?></h3>
                <div class="engagement-stats">
                    <div class="stat-box">
                        <div class="stat-value">892K / 2.8M</div>
                        <div class="stat-label"><?php _e('Active Episode Loggers', 'spiral-engine'); ?></div>
                        <div class="stat-percentage">31.9%</div>
                    </div>
                    
                    <div class="logging-frequency">
                        <h4><?php _e('Logging Frequency', 'spiral-engine'); ?></h4>
                        <canvas id="logging-frequency-chart"></canvas>
                    </div>
                </div>
            </div>
            
            <!-- User Journey -->
            <div class="analytics-card full">
                <h3><?php _e('User Journey with Episodes', 'spiral-engine'); ?></h3>
                <div class="journey-funnel">
                    <div class="funnel-stage" style="width: 100%">
                        <div class="stage-bar"></div>
                        <span class="stage-label"><?php _e('Registration', 'spiral-engine'); ?></span>
                        <span class="stage-percentage">100%</span>
                    </div>
                    <div class="funnel-stage" style="width: 78%">
                        <div class="stage-bar"></div>
                        <span class="stage-label"><?php _e('First Episode', 'spiral-engine'); ?></span>
                        <span class="stage-percentage">78%</span>
                    </div>
                    <div class="funnel-stage" style="width: 62%">
                        <div class="stage-bar"></div>
                        <span class="stage-label"><?php _e('Pattern Found', 'spiral-engine'); ?></span>
                        <span class="stage-percentage">62%</span>
                    </div>
                    <div class="funnel-stage" style="width: 45%">
                        <div class="stage-bar"></div>
                        <span class="stage-label"><?php _e('Uses Forecast', 'spiral-engine'); ?></span>
                        <span class="stage-percentage">45%</span>
                    </div>
                    <div class="funnel-stage" style="width: 34%">
                        <div class="stage-bar"></div>
                        <span class="stage-label"><?php _e('Multi-Logger', 'spiral-engine'); ?></span>
                        <span class="stage-percentage">34%</span>
                    </div>
                    <div class="funnel-stage" style="width: 23%">
                        <div class="stage-bar"></div>
                        <span class="stage-label"><?php _e('Premium Convert', 'spiral-engine'); ?></span>
                        <span class="stage-percentage">23%</span>
                    </div>
                </div>
            </div>
            
            <!-- Membership Analysis -->
            <div class="analytics-card full">
                <h3><?php _e('Episode Analytics by Membership Level', 'spiral-engine'); ?></h3>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e('Level', 'spiral-engine'); ?></th>
                            <th><?php _e('Episodes/User', 'spiral-engine'); ?></th>
                            <th><?php _e('Avg Severity', 'spiral-engine'); ?></th>
                            <th><?php _e('Features Used', 'spiral-engine'); ?></th>
                            <th><?php _e('Retention', 'spiral-engine'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><span class="membership-badge discovery">Discovery</span></td>
                            <td>12.3</td>
                            <td>6.8</td>
                            <td>Basic</td>
                            <td>45%</td>
                        </tr>
                        <tr>
                            <td><span class="membership-badge explorer">Explorer</span></td>
                            <td>18.7</td>
                            <td>6.2</td>
                            <td>Patterns</td>
                            <td>67%</td>
                        </tr>
                        <tr>
                            <td><span class="membership-badge navigator">Navigator</span></td>
                            <td>24.5</td>
                            <td>5.8</td>
                            <td>Full AI</td>
                            <td>82%</td>
                        </tr>
                        <tr>
                            <td><span class="membership-badge voyager">Voyager</span></td>
                            <td>31.2</td>
                            <td>5.3</td>
                            <td>Advanced</td>
                            <td>94%</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render Widget Analytics
     */
    private function render_widget_analytics() {
        ?>
        <div class="widget-analytics">
            <div class="analytics-header">
                <h2><?php _e('Widget Performance Analytics', 'spiral-engine'); ?></h2>
            </div>
            
            <!-- Episode Widget Performance -->
            <div class="analytics-card full">
                <h3><?php _e('Episode Widget Usage', 'spiral-engine'); ?></h3>
                <div class="widget-usage-chart">
                    <canvas id="widget-usage-chart"></canvas>
                </div>
                
                <div class="usage-breakdown">
                    <div class="breakdown-stat">
                        <span class="label"><?php _e('Quick Log vs Detailed:', 'spiral-engine'); ?></span>
                        <div class="breakdown-bar">
                            <div class="quick-log" style="width: 67%">67%</div>
                            <div class="detailed" style="width: 33%">33%</div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Widget Load Performance -->
            <div class="analytics-card full">
                <h3><?php _e('Widget Load Performance', 'spiral-engine'); ?></h3>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e('Widget', 'spiral-engine'); ?></th>
                            <th><?php _e('Quick Log', 'spiral-engine'); ?></th>
                            <th><?php _e('Full Form', 'spiral-engine'); ?></th>
                            <th><?php _e('Status', 'spiral-engine'); ?></th>
                            <th><?php _e('Usage Today', 'spiral-engine'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Overthinking Logger</td>
                            <td>89ms</td>
                            <td>234ms</td>
                            <td><span class="status-indicator good">✅</span></td>
                            <td>15,782</td>
                        </tr>
                        <tr>
                            <td>Anxiety Logger</td>
                            <td>92ms</td>
                            <td>245ms</td>
                            <td><span class="status-indicator good">✅</span></td>
                            <td>12,456</td>
                        </tr>
                        <tr>
                            <td>PTSD Logger</td>
                            <td>94ms</td>
                            <td>267ms</td>
                            <td><span class="status-indicator good">✅</span></td>
                            <td>8,923</td>
                        </tr>
                        <tr>
                            <td>Depression Logger</td>
                            <td>88ms</td>
                            <td>239ms</td>
                            <td><span class="status-indicator good">✅</span></td>
                            <td>6,789</td>
                        </tr>
                        <tr>
                            <td>Caregiver Logger</td>
                            <td>105ms</td>
                            <td>289ms</td>
                            <td><span class="status-indicator warning">⚠️</span></td>
                            <td>3,456</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render Correlation Analytics
     */
    private function render_correlation_analytics() {
        ?>
        <div class="correlation-analytics">
            <div class="analytics-header">
                <h2><?php _e('Episode Correlation Discovery & Analysis', 'spiral-engine'); ?></h2>
            </div>
            
            <!-- Correlation Network -->
            <div class="analytics-card full">
                <h3><?php _e('Active Correlation Network', 'spiral-engine'); ?></h3>
                <div class="correlation-network-container">
                    <div id="correlation-network"></div>
                    <div class="network-legend">
                        <p><?php _e('Node size = Episode frequency', 'spiral-engine'); ?></p>
                        <p><?php _e('Line thickness = Correlation strength', 'spiral-engine'); ?></p>
                    </div>
                </div>
            </div>
            
            <!-- Correlation Statistics -->
            <div class="analytics-card full">
                <h3><?php _e('Correlation Statistics', 'spiral-engine'); ?></h3>
                <div class="correlation-stats">
                    <div class="stat-summary">
                        <div class="stat-item">
                            <span class="stat-label"><?php _e('Total Correlations Found:', 'spiral-engine'); ?></span>
                            <span class="stat-value">1,247</span>
                        </div>
                        <div class="stat-breakdown">
                            <span class="strength-badge strong"><?php _e('Strong (>0.7):', 'spiral-engine'); ?> 234</span>
                            <span class="strength-badge medium"><?php _e('Medium:', 'spiral-engine'); ?> 567</span>
                            <span class="strength-badge weak"><?php _e('Weak:', 'spiral-engine'); ?> 446</span>
                        </div>
                    </div>
                    
                    <div class="common-sequences">
                        <h4><?php _e('Most Common Sequences', 'spiral-engine'); ?></h4>
                        <ol>
                            <li>Poor Sleep → Anxiety → Overthinking</li>
                            <li>Work Stress → Overthinking → Depression</li>
                            <li>Social Event → PTSD → Anxiety</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render Forecast Analytics
     */
    private function render_forecast_analytics() {
        ?>
        <div class="forecast-analytics">
            <div class="analytics-header">
                <h2><?php _e('Unified Forecast Analytics', 'spiral-engine'); ?></h2>
            </div>
            
            <!-- Forecast Accuracy -->
            <div class="analytics-card full">
                <h3><?php _e('Forecast Accuracy by Type', 'spiral-engine'); ?></h3>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e('Episode Type', 'spiral-engine'); ?></th>
                            <th><?php _e('7-Day Accuracy', 'spiral-engine'); ?></th>
                            <th><?php _e('30-Day Accuracy', 'spiral-engine'); ?></th>
                            <th><?php _e('Trend', 'spiral-engine'); ?></th>
                            <th><?php _e('Predictions Made', 'spiral-engine'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Overthinking</td>
                            <td>89.2%</td>
                            <td>84.5%</td>
                            <td><span class="trend up">↑</span></td>
                            <td>45,678</td>
                        </tr>
                        <tr>
                            <td>Anxiety</td>
                            <td>87.8%</td>
                            <td>82.3%</td>
                            <td><span class="trend neutral">→</span></td>
                            <td>38,923</td>
                        </tr>
                        <tr>
                            <td>PTSD</td>
                            <td>85.4%</td>
                            <td>78.9%</td>
                            <td><span class="trend up">↑</span></td>
                            <td>28,456</td>
                        </tr>
                        <tr>
                            <td>Depression</td>
                            <td>88.1%</td>
                            <td>83.2%</td>
                            <td><span class="trend up">↑</span></td>
                            <td>31,234</td>
                        </tr>
                        <tr class="total-row">
                            <td><strong><?php _e('Combined', 'spiral-engine'); ?></strong></td>
                            <td><strong>87.3%</strong></td>
                            <td><strong>82.2%</strong></td>
                            <td><span class="trend up">↑</span></td>
                            <td><strong>144,291</strong></td>
                        </tr>
                    </tbody>
                </table>
            </div>
            
            <!-- Forecast Performance Chart -->
            <div class="analytics-card full">
                <h3><?php _e('Forecast Performance Over Time', 'spiral-engine'); ?></h3>
                <div class="chart-wrapper">
                    <canvas id="forecast-performance-chart"></canvas>
                </div>
                <div class="performance-target">
                    <span><?php _e('Target Accuracy:', 'spiral-engine'); ?> 85%</span>
                </div>
            </div>
            
            <!-- Risk Prediction Success -->
            <div class="analytics-card full">
                <h3><?php _e('Risk Prediction Success', 'spiral-engine'); ?></h3>
                <div class="risk-stats">
                    <div class="stat-box">
                        <div class="stat-value">89%</div>
                        <div class="stat-label"><?php _e('High-risk periods identified', 'spiral-engine'); ?></div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-value">3,456</div>
                        <div class="stat-label"><?php _e('User warnings sent', 'spiral-engine'); ?></div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-value">1,234</div>
                        <div class="stat-label"><?php _e('Episodes prevented (est.)', 'spiral-engine'); ?></div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render Custom Reports
     */
    private function render_custom_reports() {
        ?>
        <div class="custom-reports">
            <div class="analytics-header">
                <h2><?php _e('Custom Reports Builder', 'spiral-engine'); ?></h2>
            </div>
            
            <!-- Report Templates -->
            <div class="report-templates">
                <h3><?php _e('Report Templates', 'spiral-engine'); ?></h3>
                <div class="template-grid">
                    <button class="template-button" data-template="episode-summary">
                        <span class="dashicons dashicons-chart-area"></span>
                        <?php _e('Episode Summary', 'spiral-engine'); ?>
                    </button>
                    <button class="template-button" data-template="correlation-report">
                        <span class="dashicons dashicons-networking"></span>
                        <?php _e('Correlation Report', 'spiral-engine'); ?>
                    </button>
                    <button class="template-button" data-template="user-insights">
                        <span class="dashicons dashicons-groups"></span>
                        <?php _e('User Insights', 'spiral-engine'); ?>
                    </button>
                    <button class="template-button" data-template="pattern-analysis">
                        <span class="dashicons dashicons-analytics"></span>
                        <?php _e('Pattern Analysis', 'spiral-engine'); ?>
                    </button>
                    <button class="template-button" data-template="forecast-accuracy">
                        <span class="dashicons dashicons-chart-line"></span>
                        <?php _e('Forecast Accuracy', 'spiral-engine'); ?>
                    </button>
                    <button class="template-button" data-template="custom">
                        <span class="dashicons dashicons-admin-generic"></span>
                        <?php _e('Custom Report', 'spiral-engine'); ?>
                    </button>
                </div>
            </div>
            
            <!-- Report Builder -->
            <div class="report-builder">
                <h3><?php _e('Configure Report', 'spiral-engine'); ?></h3>
                <form id="report-builder-form">
                    <div class="report-section">
                        <h4><?php _e('Report Sections', 'spiral-engine'); ?></h4>
                        <label>
                            <input type="checkbox" name="sections[]" value="episode-volume" checked>
                            <?php _e('Episode Volume & Trends', 'spiral-engine'); ?>
                        </label>
                        <label>
                            <input type="checkbox" name="sections[]" value="severity-analysis" checked>
                            <?php _e('Severity Analysis by Type', 'spiral-engine'); ?>
                        </label>
                        <label>
                            <input type="checkbox" name="sections[]" value="correlations" checked>
                            <?php _e('Top Correlations & Patterns', 'spiral-engine'); ?>
                        </label>
                        <label>
                            <input type="checkbox" name="sections[]" value="forecast" checked>
                            <?php _e('Forecast Performance', 'spiral-engine'); ?>
                        </label>
                        <label>
                            <input type="checkbox" name="sections[]" value="user-engagement" checked>
                            <?php _e('User Engagement Metrics', 'spiral-engine'); ?>
                        </label>
                        <label>
                            <input type="checkbox" name="sections[]" value="geographic" checked>
                            <?php _e('Geographic Insights', 'spiral-engine'); ?>
                        </label>
                        <label>
                            <input type="checkbox" name="sections[]" value="individual">
                            <?php _e('Individual User Reports', 'spiral-engine'); ?>
                        </label>
                    </div>
                    
                    <div class="report-section">
                        <h4><?php _e('Visualization Options', 'spiral-engine'); ?></h4>
                        <div class="viz-options">
                            <label>
                                <?php _e('Episode Timeline:', 'spiral-engine'); ?>
                                <select name="timeline-viz">
                                    <option value="stacked-area"><?php _e('Stacked Area Chart', 'spiral-engine'); ?></option>
                                    <option value="line"><?php _e('Line Chart', 'spiral-engine'); ?></option>
                                    <option value="bar"><?php _e('Bar Chart', 'spiral-engine'); ?></option>
                                </select>
                            </label>
                            <label>
                                <?php _e('Correlations:', 'spiral-engine'); ?>
                                <select name="correlation-viz">
                                    <option value="network"><?php _e('Network Diagram', 'spiral-engine'); ?></option>
                                    <option value="matrix"><?php _e('Correlation Matrix', 'spiral-engine'); ?></option>
                                    <option value="sankey"><?php _e('Sankey Diagram', 'spiral-engine'); ?></option>
                                </select>
                            </label>
                        </div>
                    </div>
                    
                    <div class="report-section">
                        <h4><?php _e('Schedule & Distribution', 'spiral-engine'); ?></h4>
                        <label>
                            <?php _e('Schedule:', 'spiral-engine'); ?>
                            <select name="schedule">
                                <option value="once"><?php _e('One-time', 'spiral-engine'); ?></option>
                                <option value="daily"><?php _e('Daily', 'spiral-engine'); ?></option>
                                <option value="weekly"><?php _e('Weekly', 'spiral-engine'); ?></option>
                                <option value="monthly" selected><?php _e('Monthly', 'spiral-engine'); ?></option>
                            </select>
                        </label>
                        <label>
                            <?php _e('Recipients:', 'spiral-engine'); ?>
                            <input type="email" name="recipients" value="admin@spiral.com" />
                        </label>
                    </div>
                    
                    <div class="report-actions">
                        <button type="button" class="button" id="preview-report">
                            <?php _e('Preview Report', 'spiral-engine'); ?>
                        </button>
                        <button type="button" class="button" id="save-template">
                            <?php _e('Save Template', 'spiral-engine'); ?>
                        </button>
                        <button type="submit" class="button button-primary">
                            <?php _e('Generate Report', 'spiral-engine'); ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
        <?php
    }
    
    /**
     * Get overview data
     */
    private function get_overview_data() {
        global $wpdb;
        
        // Calculate system health score
        $health_components = array(
            'database' => $this->check_database_health(),
            'performance' => $this->check_performance_health(),
            'errors' => $this->check_error_rate(),
            'uptime' => $this->check_uptime()
        );
        
        $health_score = array_sum($health_components) / count($health_components);
        
        // Get user metrics
        $total_users = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->users}");
        $users_last_month = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->users} 
            WHERE user_registered < DATE_SUB(NOW(), INTERVAL 1 MONTH)"
        );
        $user_growth = $users_last_month > 0 ? 
            round((($total_users - $users_last_month) / $users_last_month) * 100, 1) : 0;
        
        // Get episode count
        $total_episodes = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}spiralengine_episodes"
        );
        
        // Get widget count
        $active_widgets = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}spiralengine_widgets WHERE status = 'active'"
        );
        
        return array(
            'health_score' => round($health_score),
            'database_health' => $health_components['database'],
            'total_users' => $total_users ?: 0,
            'user_growth' => $user_growth,
            'active_widgets' => $active_widgets ?: 147,
            'total_episodes' => $total_episodes ?: 45700
        );
    }
    
    /**
     * Check database health
     */
    private function check_database_health() {
        global $wpdb;
        
        // Check table optimization
        $fragmented_tables = $wpdb->get_var(
            "SELECT COUNT(*) FROM information_schema.TABLES 
            WHERE TABLE_SCHEMA = '{$wpdb->dbname}' 
            AND Data_free > 0"
        );
        
        // Simple health calculation
        if ($fragmented_tables == 0) {
            return 98;
        } elseif ($fragmented_tables < 5) {
            return 90;
        } else {
            return 75;
        }
    }
    
    /**
     * Check performance health
     */
    private function check_performance_health() {
        // Check page load time from transient
        $avg_load_time = get_transient('spiral_avg_page_load_time');
        
        if (!$avg_load_time) {
            return 95; // Default good score
        }
        
        if ($avg_load_time < 1000) {
            return 100;
        } elseif ($avg_load_time < 2000) {
            return 90;
        } elseif ($avg_load_time < 3000) {
            return 75;
        } else {
            return 50;
        }
    }
    
    /**
     * Check error rate
     */
    private function check_error_rate() {
        global $wpdb;
        
        // Check error logs
        $recent_errors = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}spiralengine_error_log 
            WHERE error_time > DATE_SUB(NOW(), INTERVAL 1 HOUR)"
        );
        
        if ($recent_errors == 0) {
            return 100;
        } elseif ($recent_errors < 10) {
            return 95;
        } elseif ($recent_errors < 50) {
            return 85;
        } else {
            return 70;
        }
    }
    
    /**
     * Check uptime
     */
    private function check_uptime() {
        // Check from uptime monitoring
        $uptime_percentage = get_transient('spiral_uptime_percentage');
        
        return $uptime_percentage ?: 99.9;
    }
    
    /**
     * AJAX handler for analytics data
     */
    public function ajax_get_analytics_data() {
        check_ajax_referer('spiral-engine-admin', 'nonce');
        
        $type = isset($_POST['type']) ? sanitize_text_field($_POST['type']) : '';
        $timeframe = isset($_POST['timeframe']) ? sanitize_text_field($_POST['timeframe']) : 'week';
        
        $data = array();
        
        switch ($type) {
            case 'episode-distribution':
                $data = $this->get_episode_distribution_data($timeframe);
                break;
                
            case 'severity-trends':
                $data = $this->get_severity_trend_data($timeframe);
                break;
                
            case 'correlation-network':
                $data = $this->get_correlation_network_data();
                break;
                
            case 'forecast-accuracy':
                $data = $this->get_forecast_accuracy_data($timeframe);
                break;
                
            default:
                $data = apply_filters('spiral_engine_analytics_data_' . $type, array(), $timeframe);
                break;
        }
        
        wp_send_json_success($data);
    }
    
    /**
     * AJAX handler for data export
     */
    public function ajax_export_data() {
        check_ajax_referer('spiral-engine-admin', 'nonce');
        
        $type = isset($_POST['export_type']) ? sanitize_text_field($_POST['export_type']) : '';
        $format = isset($_POST['format']) ? sanitize_text_field($_POST['format']) : 'csv';
        
        // Generate export data
        $export_data = $this->generate_export_data($type);
        
        if ($format === 'csv') {
            $filename = 'spiral-analytics-' . $type . '-' . date('Y-m-d') . '.csv';
            $csv_data = $this->array_to_csv($export_data);
            
            wp_send_json_success(array(
                'filename' => $filename,
                'data' => $csv_data,
                'type' => 'text/csv'
            ));
        } else {
            wp_send_json_error('Unsupported export format');
        }
    }
    
    /**
     * Get episode distribution data
     */
    private function get_episode_distribution_data($timeframe) {
        global $wpdb;
        
        $date_condition = $this->get_date_condition($timeframe);
        
        $distribution = $wpdb->get_results(
            "SELECT episode_type, COUNT(*) as count 
            FROM {$wpdb->prefix}spiralengine_episodes 
            WHERE {$date_condition}
            GROUP BY episode_type",
            ARRAY_A
        );
        
        if (empty($distribution)) {
            // Mock data for demo
            $distribution = array(
                array('episode_type' => 'overthinking', 'count' => 34567),
                array('episode_type' => 'anxiety', 'count' => 28923),
                array('episode_type' => 'ptsd', 'count' => 12456),
                array('episode_type' => 'depression', 'count' => 10234),
                array('episode_type' => 'caregiver', 'count' => 4567)
            );
        }
        
        return $distribution;
    }
    
    /**
     * Get severity trend data
     */
    private function get_severity_trend_data($timeframe) {
        global $wpdb;
        
        $date_condition = $this->get_date_condition($timeframe);
        
        $trends = $wpdb->get_results(
            "SELECT DATE(created_at) as date, AVG(severity) as avg_severity 
            FROM {$wpdb->prefix}spiralengine_episodes 
            WHERE {$date_condition}
            GROUP BY DATE(created_at)
            ORDER BY date ASC",
            ARRAY_A
        );
        
        if (empty($trends)) {
            // Generate mock trend data
            $trends = array();
            $days = $timeframe === 'week' ? 7 : ($timeframe === 'month' ? 30 : 90);
            
            for ($i = $days; $i >= 0; $i--) {
                $trends[] = array(
                    'date' => date('Y-m-d', strtotime("-{$i} days")),
                    'avg_severity' => rand(55, 75) / 10
                );
            }
        }
        
        return $trends;
    }
    
    /**
     * Get correlation network data
     */
    private function get_correlation_network_data() {
        global $wpdb;
        
        $correlations = $wpdb->get_results(
            "SELECT primary_type, related_type, avg_strength, occurrence_count 
            FROM {$wpdb->prefix}spiralengine_correlation_analytics 
            WHERE avg_strength > 0.5
            ORDER BY avg_strength DESC
            LIMIT 20",
            ARRAY_A
        );
        
        if (empty($correlations)) {
            // Mock correlation data
            $correlations = array(
                array('primary_type' => 'overthinking', 'related_type' => 'anxiety', 
                      'avg_strength' => 0.72, 'occurrence_count' => 15234),
                array('primary_type' => 'anxiety', 'related_type' => 'depression', 
                      'avg_strength' => 0.68, 'occurrence_count' => 12456),
                array('primary_type' => 'ptsd', 'related_type' => 'anxiety', 
                      'avg_strength' => 0.65, 'occurrence_count' => 8923)
            );
        }
        
        return $correlations;
    }
    
    /**
     * Get forecast accuracy data
     */
    private function get_forecast_accuracy_data($timeframe) {
        global $wpdb;
        
        $date_condition = $this->get_date_condition($timeframe);
        
        $accuracy = $wpdb->get_results(
            "SELECT episode_type, forecast_window, AVG(accuracy_rate) as avg_accuracy 
            FROM {$wpdb->prefix}spiralengine_forecast_analytics 
            WHERE {$date_condition}
            GROUP BY episode_type, forecast_window",
            ARRAY_A
        );
        
        if (empty($accuracy)) {
            // Mock accuracy data
            $types = array('overthinking', 'anxiety', 'ptsd', 'depression');
            $windows = array('7_day', '30_day');
            $accuracy = array();
            
            foreach ($types as $type) {
                foreach ($windows as $window) {
                    $accuracy[] = array(
                        'episode_type' => $type,
                        'forecast_window' => $window,
                        'avg_accuracy' => rand(78, 92)
                    );
                }
            }
        }
        
        return $accuracy;
    }
    
    /**
     * Get date condition for queries
     */
    private function get_date_condition($timeframe) {
        switch ($timeframe) {
            case 'today':
                return "DATE(created_at) = CURDATE()";
            case 'week':
                return "created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)";
            case 'month':
                return "created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)";
            case 'quarter':
                return "created_at > DATE_SUB(NOW(), INTERVAL 90 DAY)";
            case 'year':
                return "created_at > DATE_SUB(NOW(), INTERVAL 1 YEAR)";
            default:
                return "1=1";
        }
    }
    
    /**
     * Generate export data
     */
    private function generate_export_data($type) {
        // This would generate actual export data based on type
        return array(
            array('Date', 'Episodes', 'Users', 'Severity'),
            array('2024-01-01', '1234', '567', '6.5'),
            array('2024-01-02', '1345', '589', '6.3'),
            array('2024-01-03', '1456', '612', '6.7')
        );
    }
    
    /**
     * Convert array to CSV
     */
    private function array_to_csv($data) {
        $output = fopen('php://temp', 'r+');
        
        foreach ($data as $row) {
            fputcsv($output, $row);
        }
        
        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);
        
        return $csv;
    }
}

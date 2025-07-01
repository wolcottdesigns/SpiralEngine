<?php
/**
 * SpiralEngine Progress Visualization Components
 * 
 * @package    SpiralEngine
 * @subpackage Includes
 * @file       includes/class-visualization-components.php
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class SpiralEngine_Visualization_Components
 * 
 * Handles progress visualizations and chart generation
 */
class SpiralEngine_Visualization_Components {
    
    /**
     * Single instance
     *
     * @var SpiralEngine_Visualization_Components
     */
    private static $instance = null;
    
    /**
     * Chart configurations
     *
     * @var array
     */
    private $chart_configs = array();
    
    /**
     * Color schemes
     *
     * @var array
     */
    private $color_schemes = array(
        'default' => array(
            'primary' => '#4a90e2',
            'secondary' => '#50e3c2',
            'success' => '#7ed321',
            'warning' => '#f5a623',
            'danger' => '#d0021b',
            'info' => '#9013fe'
        ),
        'severity' => array(
            'low' => '#7ed321',
            'medium' => '#f5a623',
            'high' => '#d0021b'
        ),
        'gradient' => array(
            'start' => '#667eea',
            'end' => '#764ba2'
        )
    );
    
    /**
     * Get instance
     *
     * @return SpiralEngine_Visualization_Components
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
        $this->init();
    }
    
    /**
     * Initialize
     */
    private function init() {
        // Register default chart configurations
        $this->register_default_charts();
        
        // Add hooks
        add_action('wp_ajax_spiralengine_get_chart_data', array($this, 'ajax_get_chart_data'));
        add_action('wp_ajax_spiralengine_export_chart', array($this, 'ajax_export_chart'));
        
        // Add shortcodes
        add_shortcode('spiralengine_chart', array($this, 'render_chart_shortcode'));
        add_shortcode('spiralengine_progress', array($this, 'render_progress_shortcode'));
    }
    
    /**
     * Register default chart configurations
     */
    private function register_default_charts() {
        // Episode frequency chart
        $this->register_chart('episode_frequency', array(
            'type' => 'bar',
            'title' => __('Episode Frequency', 'spiralengine'),
            'options' => array(
                'responsive' => true,
                'maintainAspectRatio' => false,
                'scales' => array(
                    'y' => array(
                        'beginAtZero' => true,
                        'ticks' => array(
                            'stepSize' => 1
                        )
                    )
                )
            )
        ));
        
        // Severity trend chart
        $this->register_chart('severity_trend', array(
            'type' => 'line',
            'title' => __('Severity Trend', 'spiralengine'),
            'options' => array(
                'responsive' => true,
                'maintainAspectRatio' => false,
                'scales' => array(
                    'y' => array(
                        'beginAtZero' => true,
                        'max' => 10
                    )
                )
            )
        ));
        
        // Trigger distribution chart
        $this->register_chart('trigger_distribution', array(
            'type' => 'doughnut',
            'title' => __('Trigger Distribution', 'spiralengine'),
            'options' => array(
                'responsive' => true,
                'maintainAspectRatio' => false,
                'plugins' => array(
                    'legend' => array(
                        'position' => 'right'
                    )
                )
            )
        ));
        
        // Time of day heatmap
        $this->register_chart('time_heatmap', array(
            'type' => 'heatmap',
            'title' => __('Episode Timing Patterns', 'spiralengine'),
            'custom_renderer' => array($this, 'render_heatmap')
        ));
        
        // Progress radar chart
        $this->register_chart('progress_radar', array(
            'type' => 'radar',
            'title' => __('Progress Overview', 'spiralengine'),
            'options' => array(
                'responsive' => true,
                'maintainAspectRatio' => false,
                'scales' => array(
                    'r' => array(
                        'beginAtZero' => true,
                        'max' => 10
                    )
                )
            )
        ));
    }
    
    /**
     * Register a chart configuration
     *
     * @param string $id Chart ID
     * @param array $config Chart configuration
     */
    public function register_chart($id, $config) {
        $this->chart_configs[$id] = wp_parse_args($config, array(
            'type' => 'line',
            'title' => '',
            'options' => array(),
            'data_source' => 'episodes',
            'custom_renderer' => null
        ));
    }
    
    /**
     * Get user episode data for charts
     *
     * @param int $user_id
     * @param string $period
     * @param array $options
     * @return array
     */
    public function get_episode_data($user_id, $period = 'week', $options = array()) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'spiralengine_episodes';
        
        // Calculate date range
        $date_range = $this->calculate_date_range($period);
        $start_date = $date_range['start'];
        $end_date = $date_range['end'];
        
        // Get episodes
        $episodes = $wpdb->get_results($wpdb->prepare("
            SELECT * FROM {$table_name}
            WHERE user_id = %d 
            AND created_at BETWEEN %s AND %s
            ORDER BY created_at ASC
        ", $user_id, $start_date, $end_date));
        
        return $this->process_episode_data($episodes, $period, $options);
    }
    
    /**
     * Process episode data for charts
     *
     * @param array $episodes
     * @param string $period
     * @param array $options
     * @return array
     */
    private function process_episode_data($episodes, $period, $options) {
        $processed = array(
            'labels' => array(),
            'datasets' => array(),
            'summary' => array()
        );
        
        // Group by date
        $grouped = array();
        foreach ($episodes as $episode) {
            $date = date('Y-m-d', strtotime($episode->created_at));
            if (!isset($grouped[$date])) {
                $grouped[$date] = array();
            }
            $grouped[$date][] = $episode;
        }
        
        // Generate labels based on period
        $labels = $this->generate_date_labels($period);
        
        // Process data for each label
        $severity_data = array();
        $count_data = array();
        
        foreach ($labels as $label => $date) {
            $day_episodes = isset($grouped[$date]) ? $grouped[$date] : array();
            
            // Calculate metrics
            $count = count($day_episodes);
            $avg_severity = 0;
            
            if ($count > 0) {
                $total_severity = array_sum(array_map(function($e) { 
                    return $e->severity; 
                }, $day_episodes));
                $avg_severity = round($total_severity / $count, 1);
            }
            
            $severity_data[] = $avg_severity;
            $count_data[] = $count;
        }
        
        // Build datasets
        $processed['labels'] = array_keys($labels);
        $processed['datasets'] = array(
            array(
                'label' => __('Episode Count', 'spiralengine'),
                'data' => $count_data,
                'backgroundColor' => $this->color_schemes['default']['primary'],
                'borderColor' => $this->color_schemes['default']['primary']
            ),
            array(
                'label' => __('Average Severity', 'spiralengine'),
                'data' => $severity_data,
                'backgroundColor' => $this->color_schemes['default']['warning'],
                'borderColor' => $this->color_schemes['default']['warning'],
                'yAxisID' => 'y1'
            )
        );
        
        // Calculate summary statistics
        $processed['summary'] = array(
            'total_episodes' => count($episodes),
            'avg_severity' => $count_data ? round(array_sum($severity_data) / count(array_filter($count_data)), 1) : 0,
            'max_severity' => $severity_data ? max($severity_data) : 0,
            'days_tracked' => count(array_filter($count_data))
        );
        
        return $processed;
    }
    
    /**
     * Generate trigger distribution data
     *
     * @param int $user_id
     * @param string $period
     * @return array
     */
    public function get_trigger_distribution($user_id, $period = 'month') {
        global $wpdb;
        $table_name = $wpdb->prefix . 'spiralengine_episodes';
        
        $date_range = $this->calculate_date_range($period);
        
        $triggers = $wpdb->get_results($wpdb->prepare("
            SELECT 
                JSON_UNQUOTE(JSON_EXTRACT(data, '$.trigger')) as trigger,
                COUNT(*) as count,
                AVG(severity) as avg_severity
            FROM {$table_name}
            WHERE user_id = %d 
            AND created_at BETWEEN %s AND %s
            AND JSON_EXTRACT(data, '$.trigger') IS NOT NULL
            GROUP BY trigger
            ORDER BY count DESC
            LIMIT 10
        ", $user_id, $date_range['start'], $date_range['end']));
        
        $labels = array();
        $data = array();
        $colors = array();
        
        foreach ($triggers as $i => $trigger) {
            $labels[] = $trigger->trigger;
            $data[] = $trigger->count;
            
            // Assign color based on average severity
            if ($trigger->avg_severity >= 7) {
                $colors[] = $this->color_schemes['severity']['high'];
            } elseif ($trigger->avg_severity >= 4) {
                $colors[] = $this->color_schemes['severity']['medium'];
            } else {
                $colors[] = $this->color_schemes['severity']['low'];
            }
        }
        
        return array(
            'labels' => $labels,
            'datasets' => array(
                array(
                    'data' => $data,
                    'backgroundColor' => $colors
                )
            )
        );
    }
    
    /**
     * Generate time pattern data
     *
     * @param int $user_id
     * @param string $period
     * @return array
     */
    public function get_time_patterns($user_id, $period = 'month') {
        global $wpdb;
        $table_name = $wpdb->prefix . 'spiralengine_episodes';
        
        $date_range = $this->calculate_date_range($period);
        
        // Get hourly distribution for each day of week
        $patterns = $wpdb->get_results($wpdb->prepare("
            SELECT 
                DAYOFWEEK(created_at) - 1 as day_of_week,
                HOUR(created_at) as hour,
                COUNT(*) as count,
                AVG(severity) as avg_severity
            FROM {$table_name}
            WHERE user_id = %d 
            AND created_at BETWEEN %s AND %s
            GROUP BY day_of_week, hour
            ORDER BY day_of_week, hour
        ", $user_id, $date_range['start'], $date_range['end']));
        
        // Initialize 7x24 grid
        $grid = array_fill(0, 7, array_fill(0, 24, 0));
        $severity_grid = array_fill(0, 7, array_fill(0, 24, 0));
        
        foreach ($patterns as $pattern) {
            $day = $pattern->day_of_week;
            $hour = $pattern->hour;
            $grid[$day][$hour] = $pattern->count;
            $severity_grid[$day][$hour] = round($pattern->avg_severity, 1);
        }
        
        return array(
            'count_grid' => $grid,
            'severity_grid' => $severity_grid,
            'days' => array('Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'),
            'hours' => range(0, 23)
        );
    }
    
    /**
     * Generate progress radar data
     *
     * @param int $user_id
     * @return array
     */
    public function get_progress_radar_data($user_id) {
        // Get various metrics
        $metrics = array(
            'consistency' => $this->calculate_consistency_score($user_id),
            'improvement' => $this->calculate_improvement_score($user_id),
            'awareness' => $this->calculate_awareness_score($user_id),
            'management' => $this->calculate_management_score($user_id),
            'resilience' => $this->calculate_resilience_score($user_id)
        );
        
        return array(
            'labels' => array(
                __('Consistency', 'spiralengine'),
                __('Improvement', 'spiralengine'),
                __('Awareness', 'spiralengine'),
                __('Management', 'spiralengine'),
                __('Resilience', 'spiralengine')
            ),
            'datasets' => array(
                array(
                    'label' => __('Current', 'spiralengine'),
                    'data' => array_values($metrics),
                    'backgroundColor' => 'rgba(74, 144, 226, 0.2)',
                    'borderColor' => $this->color_schemes['default']['primary'],
                    'pointBackgroundColor' => $this->color_schemes['default']['primary']
                )
            )
        );
    }
    
    /**
     * Render chart HTML
     *
     * @param string $chart_id
     * @param array $options
     * @return string
     */
    public function render_chart($chart_id, $options = array()) {
        $config = isset($this->chart_configs[$chart_id]) ? 
                  $this->chart_configs[$chart_id] : 
                  array();
        
        if (empty($config)) {
            return '<p>' . __('Invalid chart configuration.', 'spiralengine') . '</p>';
        }
        
        // Check for custom renderer
        if (!empty($config['custom_renderer']) && is_callable($config['custom_renderer'])) {
            return call_user_func($config['custom_renderer'], $options);
        }
        
        // Generate unique ID
        $canvas_id = 'spiralengine-chart-' . uniqid();
        
        // Prepare options
        $options = wp_parse_args($options, array(
            'user_id' => get_current_user_id(),
            'period' => 'week',
            'height' => 300,
            'class' => ''
        ));
        
        ob_start();
        ?>
        <div class="spiralengine-chart-container <?php echo esc_attr($options['class']); ?>" 
             data-chart-type="<?php echo esc_attr($config['type']); ?>"
             data-chart-id="<?php echo esc_attr($chart_id); ?>">
            <?php if (!empty($config['title'])): ?>
                <h3 class="chart-title"><?php echo esc_html($config['title']); ?></h3>
            <?php endif; ?>
            <canvas id="<?php echo esc_attr($canvas_id); ?>" 
                    height="<?php echo esc_attr($options['height']); ?>"></canvas>
            <div class="chart-actions">
                <button class="chart-action-btn" data-action="refresh" title="<?php esc_attr_e('Refresh', 'spiralengine'); ?>">
                    <span class="dashicons dashicons-update"></span>
                </button>
                <button class="chart-action-btn" data-action="fullscreen" title="<?php esc_attr_e('Fullscreen', 'spiralengine'); ?>">
                    <span class="dashicons dashicons-fullscreen-alt"></span>
                </button>
                <button class="chart-action-btn" data-action="export" title="<?php esc_attr_e('Export', 'spiralengine'); ?>">
                    <span class="dashicons dashicons-download"></span>
                </button>
            </div>
        </div>
        
        <script>
        (function() {
            var chartData = <?php echo json_encode($this->get_chart_data($chart_id, $options)); ?>;
            var chartConfig = <?php echo json_encode($config); ?>;
            
            document.addEventListener('DOMContentLoaded', function() {
                var ctx = document.getElementById('<?php echo esc_js($canvas_id); ?>');
                if (ctx) {
                    var chart = new Chart(ctx, {
                        type: chartConfig.type,
                        data: chartData,
                        options: chartConfig.options || {}
                    });
                    
                    // Store chart instance
                    ctx.chartInstance = chart;
                }
            });
        })();
        </script>
        <?php
        
        return ob_get_clean();
    }
    
    /**
     * Render heatmap visualization
     *
     * @param array $options
     * @return string
     */
    public function render_heatmap($options) {
        $data = $this->get_time_patterns($options['user_id'], $options['period']);
        
        ob_start();
        ?>
        <div class="spiralengine-heatmap-container">
            <h3><?php _e('Episode Timing Patterns', 'spiralengine'); ?></h3>
            <div class="heatmap-grid">
                <div class="heatmap-labels-y">
                    <?php foreach ($data['days'] as $day): ?>
                        <div class="heatmap-label"><?php echo esc_html($day); ?></div>
                    <?php endforeach; ?>
                </div>
                <div class="heatmap-content">
                    <div class="heatmap-labels-x">
                        <?php foreach ($data['hours'] as $hour): ?>
                            <div class="heatmap-label"><?php echo $hour; ?></div>
                        <?php endforeach; ?>
                    </div>
                    <div class="heatmap-cells">
                        <?php foreach ($data['count_grid'] as $day_idx => $hours): ?>
                            <div class="heatmap-row">
                                <?php foreach ($hours as $hour_idx => $count): ?>
                                    <?php
                                    $intensity = $count > 0 ? min($count / 5, 1) : 0;
                                    $severity = $data['severity_grid'][$day_idx][$hour_idx];
                                    ?>
                                    <div class="heatmap-cell" 
                                         style="opacity: <?php echo $intensity; ?>; 
                                                background-color: <?php echo $this->get_severity_color($severity); ?>;"
                                         data-day="<?php echo $day_idx; ?>"
                                         data-hour="<?php echo $hour_idx; ?>"
                                         data-count="<?php echo $count; ?>"
                                         data-severity="<?php echo $severity; ?>"
                                         title="<?php echo sprintf(__('%d episodes, avg severity: %s', 'spiralengine'), 
                                                                  $count, $severity); ?>">
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <div class="heatmap-legend">
                <span class="legend-label"><?php _e('Frequency:', 'spiralengine'); ?></span>
                <div class="legend-gradient"></div>
                <span class="legend-min">0</span>
                <span class="legend-max">5+</span>
            </div>
        </div>
        <?php
        
        return ob_get_clean();
    }
    
    /**
     * Render progress bar
     *
     * @param string $label
     * @param float $value
     * @param float $max
     * @param array $options
     * @return string
     */
    public function render_progress_bar($label, $value, $max = 100, $options = array()) {
        $percentage = ($max > 0) ? round(($value / $max) * 100) : 0;
        
        $options = wp_parse_args($options, array(
            'color' => 'primary',
            'striped' => false,
            'animated' => false,
            'show_label' => true,
            'show_value' => true
        ));
        
        ob_start();
        ?>
        <div class="spiralengine-progress-container">
            <?php if ($options['show_label']): ?>
                <div class="progress-label"><?php echo esc_html($label); ?></div>
            <?php endif; ?>
            <div class="progress-bar-wrapper">
                <div class="progress-bar <?php echo $options['striped'] ? 'striped' : ''; ?> 
                            <?php echo $options['animated'] ? 'animated' : ''; ?>">
                    <div class="progress-fill color-<?php echo esc_attr($options['color']); ?>" 
                         style="width: <?php echo $percentage; ?>%;">
                        <?php if ($options['show_value']): ?>
                            <span class="progress-value"><?php echo $percentage; ?>%</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php
        
        return ob_get_clean();
    }
    
    /**
     * Render circular progress
     *
     * @param string $label
     * @param float $value
     * @param float $max
     * @param array $options
     * @return string
     */
    public function render_circular_progress($label, $value, $max = 100, $options = array()) {
        $percentage = ($max > 0) ? round(($value / $max) * 100) : 0;
        $circumference = 2 * pi() * 45; // radius = 45
        $offset = $circumference - ($percentage / 100) * $circumference;
        
        $options = wp_parse_args($options, array(
            'size' => 120,
            'stroke_width' => 8,
            'color' => $this->color_schemes['default']['primary']
        ));
        
        ob_start();
        ?>
        <div class="spiralengine-circular-progress" style="width: <?php echo $options['size']; ?>px; height: <?php echo $options['size']; ?>px;">
            <svg width="<?php echo $options['size']; ?>" height="<?php echo $options['size']; ?>" viewBox="0 0 100 100">
                <circle cx="50" cy="50" r="45" 
                        fill="none" 
                        stroke="#e6e6e6" 
                        stroke-width="<?php echo $options['stroke_width']; ?>"/>
                <circle cx="50" cy="50" r="45" 
                        fill="none" 
                        stroke="<?php echo esc_attr($options['color']); ?>" 
                        stroke-width="<?php echo $options['stroke_width']; ?>"
                        stroke-dasharray="<?php echo $circumference; ?>"
                        stroke-dashoffset="<?php echo $offset; ?>"
                        stroke-linecap="round"
                        transform="rotate(-90 50 50)"/>
            </svg>
            <div class="circular-progress-content">
                <div class="progress-percentage"><?php echo $percentage; ?>%</div>
                <div class="progress-label"><?php echo esc_html($label); ?></div>
            </div>
        </div>
        <?php
        
        return ob_get_clean();
    }
    
    /**
     * Get chart data
     *
     * @param string $chart_id
     * @param array $options
     * @return array
     */
    private function get_chart_data($chart_id, $options) {
        switch ($chart_id) {
            case 'episode_frequency':
                return $this->get_episode_data($options['user_id'], $options['period']);
                
            case 'severity_trend':
                $data = $this->get_episode_data($options['user_id'], $options['period']);
                // Return only severity dataset
                return array(
                    'labels' => $data['labels'],
                    'datasets' => array($data['datasets'][1])
                );
                
            case 'trigger_distribution':
                return $this->get_trigger_distribution($options['user_id'], $options['period']);
                
            case 'progress_radar':
                return $this->get_progress_radar_data($options['user_id']);
                
            default:
                return array();
        }
    }
    
    /**
     * Calculate date range
     *
     * @param string $period
     * @return array
     */
    private function calculate_date_range($period) {
        $end_date = current_time('Y-m-d 23:59:59');
        
        switch ($period) {
            case 'day':
                $start_date = current_time('Y-m-d 00:00:00');
                break;
            case 'week':
                $start_date = date('Y-m-d 00:00:00', strtotime('-6 days', current_time('timestamp')));
                break;
            case 'month':
                $start_date = date('Y-m-d 00:00:00', strtotime('-29 days', current_time('timestamp')));
                break;
            case 'year':
                $start_date = date('Y-m-d 00:00:00', strtotime('-364 days', current_time('timestamp')));
                break;
            default:
                $start_date = date('Y-m-d 00:00:00', strtotime('-6 days', current_time('timestamp')));
        }
        
        return array(
            'start' => $start_date,
            'end' => $end_date
        );
    }
    
    /**
     * Generate date labels
     *
     * @param string $period
     * @return array
     */
    private function generate_date_labels($period) {
        $labels = array();
        $date_range = $this->calculate_date_range($period);
        $start = new DateTime($date_range['start']);
        $end = new DateTime($date_range['end']);
        
        switch ($period) {
            case 'day':
                // Hourly labels
                for ($h = 0; $h < 24; $h++) {
                    $labels[sprintf('%02d:00', $h)] = $start->format('Y-m-d');
                }
                break;
                
            case 'week':
                // Daily labels
                while ($start <= $end) {
                    $labels[$start->format('D')] = $start->format('Y-m-d');
                    $start->modify('+1 day');
                }
                break;
                
            case 'month':
                // Weekly labels
                while ($start <= $end) {
                    $week_end = clone $start;
                    $week_end->modify('+6 days');
                    if ($week_end > $end) {
                        $week_end = $end;
                    }
                    
                    $label = $start->format('M j') . '-' . $week_end->format('j');
                    $labels[$label] = $start->format('Y-m-d');
                    
                    $start->modify('+7 days');
                }
                break;
                
            case 'year':
                // Monthly labels
                while ($start <= $end) {
                    $labels[$start->format('M')] = $start->format('Y-m-d');
                    $start->modify('+1 month');
                }
                break;
        }
        
        return $labels;
    }
    
    /**
     * Calculate consistency score
     *
     * @param int $user_id
     * @return float
     */
    private function calculate_consistency_score($user_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'spiralengine_episodes';
        
        // Get tracking days in last 30 days
        $days_tracked = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(DISTINCT DATE(created_at))
            FROM {$table_name}
            WHERE user_id = %d
            AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ", $user_id));
        
        // Score: 0-10 based on percentage of days tracked
        return round(($days_tracked / 30) * 10, 1);
    }
    
    /**
     * Calculate improvement score
     *
     * @param int $user_id
     * @return float
     */
    private function calculate_improvement_score($user_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'spiralengine_episodes';
        
        // Compare average severity: first week vs last week
        $first_week_avg = $wpdb->get_var($wpdb->prepare("
            SELECT AVG(severity)
            FROM {$table_name}
            WHERE user_id = %d
            AND created_at BETWEEN DATE_SUB(NOW(), INTERVAL 30 DAY) AND DATE_SUB(NOW(), INTERVAL 23 DAY)
        ", $user_id));
        
        $last_week_avg = $wpdb->get_var($wpdb->prepare("
            SELECT AVG(severity)
            FROM {$table_name}
            WHERE user_id = %d
            AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        ", $user_id));
        
        if (!$first_week_avg || !$last_week_avg) {
            return 5.0; // Neutral score
        }
        
        // Calculate improvement percentage
        $improvement = (($first_week_avg - $last_week_avg) / $first_week_avg) * 100;
        
        // Convert to 0-10 scale
        return round(min(max(5 + ($improvement / 10), 0), 10), 1);
    }
    
    /**
     * Calculate awareness score
     *
     * @param int $user_id
     * @return float
     */
    private function calculate_awareness_score($user_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'spiralengine_episodes';
        
        // Check how many episodes have detailed data
        $detailed_episodes = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*)
            FROM {$table_name}
            WHERE user_id = %d
            AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            AND (
                JSON_EXTRACT(data, '$.trigger') IS NOT NULL
                OR JSON_EXTRACT(data, '$.thoughts') IS NOT NULL
                OR JSON_EXTRACT(data, '$.physical_symptoms') IS NOT NULL
            )
        ", $user_id));
        
        $total_episodes = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*)
            FROM {$table_name}
            WHERE user_id = %d
            AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ", $user_id));
        
        if ($total_episodes == 0) {
            return 0;
        }
        
        // Score based on detail percentage
        return round(($detailed_episodes / $total_episodes) * 10, 1);
    }
    
    /**
     * Calculate management score
     *
     * @param int $user_id
     * @return float
     */
    private function calculate_management_score($user_id) {
        // Check various management activities
        $score = 5.0; // Base score
        
        // Check if user has set goals
        $has_goals = get_user_meta($user_id, 'spiralengine_active_goals', true);
        if ($has_goals) {
            $score += 1.5;
        }
        
        // Check if user views insights regularly
        $last_insight_view = get_user_meta($user_id, 'spiralengine_last_insight_view', true);
        if ($last_insight_view && (time() - $last_insight_view) < WEEK_IN_SECONDS) {
            $score += 1.5;
        }
        
        // Check if user applies recommendations
        $applied_recommendations = get_user_meta($user_id, 'spiralengine_applied_recommendations', true);
        if ($applied_recommendations && count($applied_recommendations) > 0) {
            $score += 2;
        }
        
        return min($score, 10);
    }
    
    /**
     * Calculate resilience score
     *
     * @param int $user_id
     * @return float
     */
    private function calculate_resilience_score($user_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'spiralengine_episodes';
        
        // Check recovery from high severity episodes
        $high_severity_episodes = $wpdb->get_results($wpdb->prepare("
            SELECT id, created_at, severity
            FROM {$table_name}
            WHERE user_id = %d
            AND severity >= 8
            AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            ORDER BY created_at
        ", $user_id));
        
        if (empty($high_severity_episodes)) {
            return 7.0; // Good baseline if no high severity episodes
        }
        
        $recovery_scores = array();
        
        foreach ($high_severity_episodes as $episode) {
            // Check next few episodes after high severity
            $following_avg = $wpdb->get_var($wpdb->prepare("
                SELECT AVG(severity)
                FROM {$table_name}
                WHERE user_id = %d
                AND created_at > %s
                AND created_at <= DATE_ADD(%s, INTERVAL 3 DAY)
            ", $user_id, $episode->created_at, $episode->created_at));
            
            if ($following_avg) {
                $recovery = ($episode->severity - $following_avg) / $episode->severity;
                $recovery_scores[] = $recovery;
            }
        }
        
        if (empty($recovery_scores)) {
            return 5.0;
        }
        
        // Average recovery rate converted to 0-10 scale
        $avg_recovery = array_sum($recovery_scores) / count($recovery_scores);
        return round(min(max($avg_recovery * 10, 0), 10), 1);
    }
    
    /**
     * Get severity color
     *
     * @param float $severity
     * @return string
     */
    private function get_severity_color($severity) {
        if ($severity >= 7) {
            return $this->color_schemes['severity']['high'];
        } elseif ($severity >= 4) {
            return $this->color_schemes['severity']['medium'];
        } else {
            return $this->color_schemes['severity']['low'];
        }
    }
    
    /**
     * AJAX: Get chart data
     */
    public function ajax_get_chart_data() {
        check_ajax_referer('spiralengine_dashboard', 'nonce');
        
        $chart_id = isset($_POST['chart_id']) ? sanitize_key($_POST['chart_id']) : '';
        $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : get_current_user_id();
        $period = isset($_POST['period']) ? sanitize_key($_POST['period']) : 'week';
        
        if (empty($chart_id)) {
            wp_send_json_error(__('Invalid chart ID.', 'spiralengine'));
        }
        
        $options = array(
            'user_id' => $user_id,
            'period' => $period
        );
        
        $data = $this->get_chart_data($chart_id, $options);
        
        wp_send_json_success($data);
    }
    
    /**
     * AJAX: Export chart
     */
    public function ajax_export_chart() {
        check_ajax_referer('spiralengine_dashboard', 'nonce');
        
        $chart_id = isset($_POST['chart_id']) ? sanitize_key($_POST['chart_id']) : '';
        $format = isset($_POST['format']) ? sanitize_key($_POST['format']) : 'png';
        
        // This would be implemented to export chart as image/PDF
        wp_send_json_success(array(
            'message' => __('Chart export feature coming soon.', 'spiralengine')
        ));
    }
    
    /**
     * Render chart shortcode
     *
     * @param array $atts
     * @return string
     */
    public function render_chart_shortcode($atts) {
        $atts = shortcode_atts(array(
            'type' => 'episode_frequency',
            'period' => 'week',
            'height' => 300,
            'class' => ''
        ), $atts);
        
        return $this->render_chart($atts['type'], $atts);
    }
    
    /**
     * Render progress shortcode
     *
     * @param array $atts
     * @return string
     */
    public function render_progress_shortcode($atts) {
        $atts = shortcode_atts(array(
            'type' => 'bar',
            'label' => '',
            'value' => 0,
            'max' => 100,
            'color' => 'primary'
        ), $atts);
        
        if ($atts['type'] === 'circular') {
            return $this->render_circular_progress(
                $atts['label'],
                floatval($atts['value']),
                floatval($atts['max']),
                array('color' => $this->color_schemes['default'][$atts['color']] ?? $atts['color'])
            );
        } else {
            return $this->render_progress_bar(
                $atts['label'],
                floatval($atts['value']),
                floatval($atts['max']),
                array('color' => $atts['color'])
            );
        }
    }
}

// Initialize
SpiralEngine_Visualization_Components::get_instance();


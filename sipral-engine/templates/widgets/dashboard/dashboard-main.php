<?php
/**
 * Member Dashboard Main Template
 * 
 * @package     SpiralEngine
 * @subpackage  Templates/Widgets/Dashboard
 * @since       1.0.0
 * 
 * File: templates/widgets/dashboard/dashboard-main.php
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Template variables
$user_id = get_current_user_id();
$user = wp_get_current_user();
$dashboard_data = isset($dashboard_data) ? $dashboard_data : array();
$user_settings = isset($user_settings) ? $user_settings : array();
$layout = isset($layout) ? $layout : array();
?>

<div class="spiralengine-widget spiralengine-widget-dashboard" id="spiralengine-dashboard">
    
    <!-- Dashboard Header -->
    <div class="sp-dashboard-header">
        <div class="sp-header-content">
            <div class="sp-welcome-section">
                <h1 class="sp-dashboard-title">
                    <?php 
                    $greeting = $this->get_time_based_greeting();
                    printf(__('%s, %s!', 'spiralengine'), $greeting, esc_html($user->display_name)); 
                    ?>
                </h1>
                <p class="sp-dashboard-subtitle">
                    <?php _e('Your personalized journey to understanding', 'spiralengine'); ?>
                </p>
            </div>
            
            <div class="sp-header-actions">
                <button class="sp-button sp-button-secondary sp-customize-btn" id="sp-customize-dashboard">
                    <span class="dashicons dashicons-admin-generic"></span>
                    <?php _e('Customize', 'spiralengine'); ?>
                </button>
                <button class="sp-button sp-button-secondary sp-export-btn" id="sp-export-data">
                    <span class="dashicons dashicons-download"></span>
                    <?php _e('Export Data', 'spiralengine'); ?>
                </button>
            </div>
        </div>
    </div>
    
    <!-- Main Dashboard Grid -->
    <div class="sp-dashboard-grid" id="sp-dashboard-grid">
        
        <!-- SPIRAL Score Section -->
        <div class="sp-dashboard-widget sp-widget-full" data-widget="spiral-score">
            <div class="sp-widget-header">
                <h2 class="sp-widget-title">
                    <span class="dashicons dashicons-chart-line"></span>
                    <?php _e('Your SPIRAL Score', 'spiralengine'); ?>
                </h2>
                <div class="sp-widget-controls">
                    <a href="<?php echo home_url('/spiral-assessment/'); ?>" class="sp-link-action">
                        <?php _e('Take Assessment', 'spiralengine'); ?>
                    </a>
                </div>
            </div>
            <div class="sp-widget-content">
                <?php if ($dashboard_data['spiral_score']): ?>
                    <div class="sp-score-display">
                        <div class="sp-score-gauge" data-score="<?php echo esc_attr($dashboard_data['spiral_score']['current_score']); ?>">
                            <svg viewBox="0 0 200 100" class="sp-gauge-svg">
                                <path d="M 10 90 A 80 80 0 0 1 190 90" class="sp-gauge-bg" />
                                <path d="M 10 90 A 80 80 0 0 1 190 90" class="sp-gauge-fill" 
                                      stroke-dasharray="251.2" 
                                      stroke-dashoffset="<?php echo 251.2 * (1 - $dashboard_data['spiral_score']['current_score'] / 100); ?>" />
                            </svg>
                            <div class="sp-score-value">
                                <?php echo number_format($dashboard_data['spiral_score']['current_score'], 1); ?>
                            </div>
                            <div class="sp-score-label"><?php _e('Current Score', 'spiralengine'); ?></div>
                        </div>
                        
                        <?php if ($dashboard_data['spiral_score']['previous_score']): ?>
                            <div class="sp-score-change <?php echo $dashboard_data['spiral_score']['current_score'] > $dashboard_data['spiral_score']['previous_score'] ? 'positive' : 'negative'; ?>">
                                <span class="dashicons <?php echo $dashboard_data['spiral_score']['current_score'] > $dashboard_data['spiral_score']['previous_score'] ? 'dashicons-arrow-up-alt' : 'dashicons-arrow-down-alt'; ?>"></span>
                                <?php 
                                $change = abs($dashboard_data['spiral_score']['current_score'] - $dashboard_data['spiral_score']['previous_score']);
                                printf(__('%s points from last assessment', 'spiralengine'), number_format($change, 1));
                                ?>
                            </div>
                        <?php endif; ?>
                        
                        <div class="sp-score-components">
                            <?php foreach ($dashboard_data['spiral_score']['components'] as $component => $score): ?>
                                <div class="sp-component">
                                    <span class="sp-component-name"><?php echo esc_html($this->get_component_label($component)); ?></span>
                                    <div class="sp-component-bar">
                                        <div class="sp-component-fill" style="width: <?php echo $score; ?>%"></div>
                                    </div>
                                    <span class="sp-component-score"><?php echo $score; ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <!-- Score History Chart -->
                    <div class="sp-score-chart">
                        <canvas id="sp-score-history-chart"></canvas>
                    </div>
                <?php else: ?>
                    <div class="sp-no-data">
                        <p><?php _e('No SPIRAL assessment taken yet.', 'spiralengine'); ?></p>
                        <a href="<?php echo home_url('/spiral-assessment/'); ?>" class="sp-button sp-button-primary">
                            <?php _e('Take Your First Assessment', 'spiralengine'); ?>
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- User Stats Grid -->
        <div class="sp-dashboard-widget sp-widget-half" data-widget="user-stats">
            <div class="sp-widget-header">
                <h2 class="sp-widget-title">
                    <span class="dashicons dashicons-chart-bar"></span>
                    <?php _e('Your Progress', 'spiralengine'); ?>
                </h2>
            </div>
            <div class="sp-widget-content">
                <div class="sp-stats-grid">
                    <div class="sp-stat-item">
                        <div class="sp-stat-value"><?php echo number_format($dashboard_data['user_stats']['total_episodes']); ?></div>
                        <div class="sp-stat-label"><?php _e('Total Episodes', 'spiralengine'); ?></div>
                    </div>
                    <div class="sp-stat-item">
                        <div class="sp-stat-value"><?php echo number_format($dashboard_data['user_stats']['episodes_this_week']); ?></div>
                        <div class="sp-stat-label"><?php _e('This Week', 'spiralengine'); ?></div>
                    </div>
                    <div class="sp-stat-item">
                        <div class="sp-stat-value"><?php echo number_format($dashboard_data['user_stats']['current_streak']); ?></div>
                        <div class="sp-stat-label"><?php _e('Day Streak', 'spiralengine'); ?></div>
                    </div>
                    <div class="sp-stat-item">
                        <div class="sp-stat-value"><?php echo number_format($dashboard_data['user_stats']['active_days']); ?></div>
                        <div class="sp-stat-label"><?php _e('Active Days', 'spiralengine'); ?></div>
                    </div>
                </div>
                
                <?php if ($dashboard_data['user_stats']['current_streak'] >= 7): ?>
                    <div class="sp-streak-celebration">
                        <span class="dashicons dashicons-awards"></span>
                        <?php printf(__('Amazing! %d day streak!', 'spiralengine'), $dashboard_data['user_stats']['current_streak']); ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Episode Timeline -->
        <div class="sp-dashboard-widget sp-widget-full" data-widget="episode-timeline">
            <div class="sp-widget-header">
                <h2 class="sp-widget-title">
                    <span class="dashicons dashicons-calendar-alt"></span>
                    <?php _e('Episode Timeline', 'spiralengine'); ?>
                </h2>
                <div class="sp-widget-controls">
                    <select class="sp-timeline-filter" id="sp-timeline-type-filter">
                        <option value="all"><?php _e('All Episodes', 'spiralengine'); ?></option>
                        <?php 
                        $episode_types = $this->episode_aggregator->get_active_episode_types();
                        foreach ($episode_types as $type => $config): 
                        ?>
                            <option value="<?php echo esc_attr($type); ?>">
                                <?php echo esc_html($config['display_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <select class="sp-timeline-range" id="sp-timeline-range">
                        <option value="7"><?php _e('Last 7 days', 'spiralengine'); ?></option>
                        <option value="30" selected><?php _e('Last 30 days', 'spiralengine'); ?></option>
                        <option value="90"><?php _e('Last 90 days', 'spiralengine'); ?></option>
                    </select>
                </div>
            </div>
            <div class="sp-widget-content">
                <div class="sp-timeline-container" id="sp-episode-timeline">
                    <!-- Timeline will be loaded via AJAX -->
                    <div class="sp-loading">
                        <span class="sp-spinner"></span>
                        <?php _e('Loading timeline...', 'spiralengine'); ?>
                    </div>
                </div>
                
                <!-- Correlation Insights -->
                <?php if (!empty($dashboard_data['correlations'])): ?>
                    <div class="sp-correlation-insights">
                        <h3><?php _e('Pattern Insights', 'spiralengine'); ?></h3>
                        <div class="sp-insights-list">
                            <?php foreach ($dashboard_data['correlations'] as $insight): ?>
                                <div class="sp-insight-item <?php echo esc_attr($insight['severity']); ?>">
                                    <span class="<?php echo esc_attr($insight['icon']); ?>"></span>
                                    <div class="sp-insight-content">
                                        <p><?php echo esc_html($insight['message']); ?></p>
                                        <?php if (!empty($insight['action'])): ?>
                                            <a href="<?php echo esc_url($insight['action']['url']); ?>" 
                                               class="sp-insight-action">
                                                <?php echo esc_html($insight['action']['text']); ?>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Unified Forecast -->
        <div class="sp-dashboard-widget sp-widget-half" data-widget="unified-forecast">
            <div class="sp-widget-header">
                <h2 class="sp-widget-title">
                    <span class="dashicons dashicons-cloud"></span>
                    <?php _e('7-Day Outlook', 'spiralengine'); ?>
                </h2>
                <div class="sp-widget-controls">
                    <button class="sp-icon-button sp-refresh-forecast" title="<?php esc_attr_e('Refresh forecast', 'spiralengine'); ?>">
                        <span class="dashicons dashicons-update"></span>
                    </button>
                </div>
            </div>
            <div class="sp-widget-content">
                <?php if ($this->user_can_view_forecast($user_id)): ?>
                    <?php if ($dashboard_data['forecast']): ?>
                        <div class="sp-forecast-display">
                            <!-- Overall Risk Meter -->
                            <div class="sp-risk-meter">
                                <div class="sp-risk-gauge" data-risk="<?php echo esc_attr($dashboard_data['forecast']['overall_risk_score']); ?>">
                                    <svg viewBox="0 0 200 100" class="sp-gauge">
                                        <defs>
                                            <linearGradient id="riskGradient" x1="0%" y1="0%" x2="100%" y2="0%">
                                                <stop offset="0%" style="stop-color:#4CAF50" />
                                                <stop offset="50%" style="stop-color:#FFC107" />
                                                <stop offset="100%" style="stop-color:#F44336" />
                                            </linearGradient>
                                        </defs>
                                        <path d="M 10 90 A 80 80 0 0 1 190 90" class="sp-gauge-bg" />
                                        <path d="M 10 90 A 80 80 0 0 1 190 90" class="sp-gauge-fill" 
                                              stroke="url(#riskGradient)"
                                              stroke-dasharray="251.2" 
                                              stroke-dashoffset="<?php echo 251.2 * (1 - $dashboard_data['forecast']['overall_risk_score'] / 10); ?>" />
                                    </svg>
                                    <div class="sp-risk-value">
                                        <?php echo number_format($dashboard_data['forecast']['overall_risk_score'], 1); ?>
                                    </div>
                                </div>
                                <p class="sp-risk-label"><?php echo $this->get_risk_label($dashboard_data['forecast']['overall_risk_score']); ?></p>
                            </div>
                            
                            <!-- Episode Type Breakdown -->
                            <div class="sp-forecast-breakdown">
                                <h4><?php _e('Risk by Episode Type', 'spiralengine'); ?></h4>
                                <?php foreach ($dashboard_data['forecast']['episode_type_risks'] as $type => $risk): ?>
                                    <?php $type_config = $this->episode_aggregator->get_episode_type_config($type); ?>
                                    <div class="sp-forecast-type">
                                        <div class="sp-type-header">
                                            <span class="<?php echo esc_attr($type_config['icon']); ?>" 
                                                  style="color: <?php echo esc_attr($type_config['color']); ?>"></span>
                                            <span><?php echo esc_html($type_config['display_name']); ?></span>
                                            <span class="sp-type-risk"><?php echo number_format($risk['risk_score'], 1); ?></span>
                                        </div>
                                        <div class="sp-type-risk-bar">
                                            <div class="sp-risk-fill" 
                                                 style="width: <?php echo ($risk['risk_score'] * 10); ?>%; 
                                                        background-color: <?php echo esc_attr($type_config['color']); ?>">
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <!-- High Risk Periods -->
                            <?php if (!empty($dashboard_data['forecast']['high_risk_dates'])): ?>
                                <div class="sp-high-risk-periods">
                                    <h4><?php _e('Watch These Days', 'spiralengine'); ?></h4>
                                    <div class="sp-risk-calendar">
                                        <?php foreach ($dashboard_data['forecast']['high_risk_dates'] as $date => $risks): ?>
                                            <div class="sp-risk-day" data-date="<?php echo esc_attr($date); ?>">
                                                <span class="sp-day-name"><?php echo date('D', strtotime($date)); ?></span>
                                                <span class="sp-day-date"><?php echo date('M j', strtotime($date)); ?></span>
                                                <div class="sp-day-risks">
                                                    <?php foreach ($risks as $risk_type): ?>
                                                        <?php $config = $this->episode_aggregator->get_episode_type_config($risk_type); ?>
                                                        <span class="sp-risk-indicator" 
                                                              style="background-color: <?php echo esc_attr($config['color']); ?>"
                                                              title="<?php echo esc_attr($config['display_name']); ?>"></span>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="sp-no-data">
                            <p><?php _e('Not enough data for forecast yet.', 'spiralengine'); ?></p>
                            <p class="sp-help-text"><?php _e('Log more episodes to see your personalized outlook.', 'spiralengine'); ?></p>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="sp-upgrade-prompt">
                        <span class="dashicons dashicons-lock"></span>
                        <h4><?php _e('Unlock 7-Day Outlook', 'spiralengine'); ?></h4>
                        <p><?php _e('Upgrade to Navigator membership to see your personalized risk forecast.', 'spiralengine'); ?></p>
                        <a href="<?php echo home_url('/membership/'); ?>" class="sp-button sp-button-primary">
                            <?php _e('Upgrade Now', 'spiralengine'); ?>
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Widget Preview Cards -->
        <div class="sp-dashboard-widget sp-widget-full" data-widget="widget-previews">
            <div class="sp-widget-header">
                <h2 class="sp-widget-title">
                    <span class="dashicons dashicons-screenoptions"></span>
                    <?php _e('Your Tracking Tools', 'spiralengine'); ?>
                </h2>
            </div>
            <div class="sp-widget-content">
                <div class="sp-preview-widgets-grid" id="sp-preview-widgets">
                    <?php 
                    include SPIRALENGINE_PATH . 'templates/widgets/dashboard/widget-cards.php';
                    ?>
                </div>
            </div>
        </div>
        
        <!-- Achievements Section -->
        <div class="sp-dashboard-widget sp-widget-half" data-widget="achievements">
            <div class="sp-widget-header">
                <h2 class="sp-widget-title">
                    <span class="dashicons dashicons-awards"></span>
                    <?php _e('Achievements', 'spiralengine'); ?>
                </h2>
                <div class="sp-widget-controls">
                    <span class="sp-achievement-count">
                        <?php printf(__('%d of %d', 'spiralengine'), 
                            $dashboard_data['achievements']['earned_count'], 
                            $dashboard_data['achievements']['total']
                        ); ?>
                    </span>
                </div>
            </div>
            <div class="sp-widget-content">
                <?php if (!empty($dashboard_data['achievements']['earned'])): ?>
                    <div class="sp-achievements-earned">
                        <h4><?php _e('Recently Earned', 'spiralengine'); ?></h4>
                        <div class="sp-achievement-list">
                            <?php 
                            $recent = array_slice($dashboard_data['achievements']['earned'], 0, 3);
                            foreach ($recent as $achievement): 
                            ?>
                                <div class="sp-achievement-item earned">
                                    <div class="sp-achievement-icon">
                                        <img src="<?php echo esc_url($achievement->icon_url); ?>" 
                                             alt="<?php echo esc_attr($achievement->name); ?>">
                                    </div>
                                    <div class="sp-achievement-info">
                                        <h5><?php echo esc_html($achievement->name); ?></h5>
                                        <p><?php echo esc_html($achievement->description); ?></p>
                                        <span class="sp-earned-date">
                                            <?php echo human_time_diff(strtotime($achievement->earned_date)) . ' ' . __('ago', 'spiralengine'); ?>
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
                
                <div class="sp-achievements-available">
                    <h4><?php _e('Next Goals', 'spiralengine'); ?></h4>
                    <div class="sp-achievement-list">
                        <?php 
                        $next = array_slice($dashboard_data['achievements']['available'], 0, 2);
                        foreach ($next as $achievement): 
                        ?>
                            <div class="sp-achievement-item locked">
                                <div class="sp-achievement-icon">
                                    <img src="<?php echo esc_url($achievement->icon_url); ?>" 
                                         alt="<?php echo esc_attr($achievement->name); ?>"
                                         class="sp-grayscale">
                                </div>
                                <div class="sp-achievement-info">
                                    <h5><?php echo esc_html($achievement->name); ?></h5>
                                    <p><?php echo esc_html($achievement->hint); ?></p>
                                    <div class="sp-achievement-progress">
                                        <div class="sp-progress-bar">
                                            <div class="sp-progress-fill" 
                                                 style="width: <?php echo $this->get_achievement_progress($achievement->achievement_id, $user_id); ?>%">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <a href="<?php echo home_url('/achievements/'); ?>" class="sp-view-all-link">
                        <?php _e('View All Achievements', 'spiralengine'); ?> →
                    </a>
                </div>
            </div>
        </div>
        
    </div>
    
    <!-- Customization Panel (Hidden by default) -->
    <div class="sp-customize-panel" id="sp-customize-panel" style="display: none;">
        <div class="sp-customize-header">
            <h3><?php _e('Customize Dashboard', 'spiralengine'); ?></h3>
            <button class="sp-close-customize" id="sp-close-customize">
                <span class="dashicons dashicons-no"></span>
            </button>
        </div>
        <div class="sp-customize-content">
            <div class="sp-customize-section">
                <h4><?php _e('Widget Visibility', 'spiralengine'); ?></h4>
                <div class="sp-widget-toggles">
                    <?php 
                    $widgets = array(
                        'spiral-score' => __('SPIRAL Score', 'spiralengine'),
                        'user-stats' => __('Progress Stats', 'spiralengine'),
                        'episode-timeline' => __('Episode Timeline', 'spiralengine'),
                        'unified-forecast' => __('7-Day Outlook', 'spiralengine'),
                        'widget-previews' => __('Tracking Tools', 'spiralengine'),
                        'achievements' => __('Achievements', 'spiralengine')
                    );
                    
                    foreach ($widgets as $widget_id => $widget_name): 
                    ?>
                        <label class="sp-toggle-control">
                            <input type="checkbox" class="sp-widget-toggle" 
                                   data-widget="<?php echo esc_attr($widget_id); ?>"
                                   <?php checked(!in_array($widget_id, $user_settings['hidden_widgets'] ?? array())); ?>>
                            <span><?php echo esc_html($widget_name); ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <div class="sp-customize-section">
                <h4><?php _e('Layout Options', 'spiralengine'); ?></h4>
                <button class="sp-button sp-button-secondary" id="sp-reset-layout">
                    <?php _e('Reset to Default Layout', 'spiralengine'); ?>
                </button>
            </div>
            
            <div class="sp-customize-actions">
                <button class="sp-button sp-button-primary" id="sp-save-customization">
                    <?php _e('Save Changes', 'spiralengine'); ?>
                </button>
            </div>
        </div>
    </div>
    
    <!-- Export Modal -->
    <div class="sp-modal" id="sp-export-modal" style="display: none;">
        <div class="sp-modal-content">
            <div class="sp-modal-header">
                <h3><?php _e('Export Your Data', 'spiralengine'); ?></h3>
                <button class="sp-modal-close" data-modal="sp-export-modal">
                    <span class="dashicons dashicons-no"></span>
                </button>
            </div>
            <div class="sp-modal-body">
                <p><?php _e('Choose what data to export:', 'spiralengine'); ?></p>
                
                <div class="sp-export-options">
                    <label class="sp-checkbox-control">
                        <input type="checkbox" name="export_episodes" value="1" checked>
                        <span><?php _e('Episode Data', 'spiralengine'); ?></span>
                    </label>
                    <label class="sp-checkbox-control">
                        <input type="checkbox" name="export_assessments" value="1" checked>
                        <span><?php _e('SPIRAL Assessments', 'spiralengine'); ?></span>
                    </label>
                    <label class="sp-checkbox-control">
                        <input type="checkbox" name="export_correlations" value="1" checked>
                        <span><?php _e('Correlation Data', 'spiralengine'); ?></span>
                    </label>
                    <label class="sp-checkbox-control">
                        <input type="checkbox" name="export_achievements" value="1" checked>
                        <span><?php _e('Achievements', 'spiralengine'); ?></span>
                    </label>
                </div>
                
                <div class="sp-export-format">
                    <h4><?php _e('Export Format:', 'spiralengine'); ?></h4>
                    <label class="sp-radio-control">
                        <input type="radio" name="export_format" value="json" checked>
                        <span><?php _e('JSON (Recommended)', 'spiralengine'); ?></span>
                    </label>
                    <label class="sp-radio-control">
                        <input type="radio" name="export_format" value="csv">
                        <span><?php _e('CSV (Episodes only)', 'spiralengine'); ?></span>
                    </label>
                </div>
            </div>
            <div class="sp-modal-footer">
                <button class="sp-button sp-button-secondary" data-modal="sp-export-modal">
                    <?php _e('Cancel', 'spiralengine'); ?>
                </button>
                <button class="sp-button sp-button-primary" id="sp-confirm-export">
                    <?php _e('Export Data', 'spiralengine'); ?>
                </button>
            </div>
        </div>
    </div>
    
</div>

<?php
// Initialize score history chart data if available
if (!empty($dashboard_data['spiral_score']['history'])):
?>
<script>
var spiralScoreHistory = {
    labels: <?php echo json_encode(array_map(function($item) {
        return date('M j', strtotime($item->assessment_date));
    }, $dashboard_data['spiral_score']['history'])); ?>,
    data: <?php echo json_encode(array_map(function($item) {
        return $item->spiral_score;
    }, $dashboard_data['spiral_score']['history'])); ?>
};
</script>
<?php endif; ?>


<?php
// templates/widgets/episodes/overthinking-quick.php

/**
 * Overthinking Episode Quick Logger Template
 * 
 * Dashboard quick entry mode with 3 essential fields
 * Displays recent episodes and pattern alerts
 * 
 * @package SpiralEngine
 * @subpackage Templates/Widgets/Episodes
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get widget instance
$widget = new SpiralEngine_Widget_Overthinking();
$quick_fields = $widget->get_quick_log_fields();

// Check user permissions
$access_control = new SpiralEngine_Access_Control();
$can_quick_log = $access_control->can_use_feature(get_current_user_id(), 'quick_log');

if (!$can_quick_log) {
    echo '<div class="spiral-feature-locked">';
    echo '<p>' . __('Quick logging is available for all members. Please log in to continue.', 'spiral') . '</p>';
    echo '</div>';
    return;
}

// Get recent episodes for display
$recent_episodes = $widget->get_user_episodes(get_current_user_id(), array(
    'days' => 7,
    'limit' => 3
));

// Check for active patterns
$pattern_alerts = $widget->get_active_pattern_alerts(get_current_user_id());

// Get correlation summary if available
$correlation_summary = null;
if (class_exists('SPIRAL_Correlation_Engine') && $access_control->can_view_correlations(get_current_user_id())) {
    $correlation_engine = new SPIRAL_Correlation_Engine();
    $correlation_summary = $correlation_engine->get_quick_summary(get_current_user_id());
}

?>

<div class="spiral-widget spiral-overthinking-quick" data-widget-id="overthinking_logger" data-mode="quick">
    
    <!-- Quick Log Header -->
    <div class="spiral-quick-header">
        <h3 class="spiral-quick-title">
            <span class="dashicons dashicons-admin-page"></span>
            <?php _e('Quick Overthinking Log', 'spiral'); ?>
        </h3>
        <a href="<?php echo home_url('/log-overthinking-episode/'); ?>" class="spiral-detailed-link">
            <?php _e('Full Logger →', 'spiral'); ?>
        </a>
    </div>
    
    <!-- Pattern Alerts -->
    <?php if (!empty($pattern_alerts)): ?>
    <div class="spiral-pattern-alerts">
        <?php foreach ($pattern_alerts as $alert): ?>
        <div class="spiral-alert spiral-alert-<?php echo esc_attr($alert['type']); ?>">
            <span class="dashicons dashicons-<?php echo esc_attr($alert['icon']); ?>"></span>
            <span class="spiral-alert-text"><?php echo esc_html($alert['message']); ?></span>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    
    <!-- Quick Log Form -->
    <form id="spiral-overthinking-quick-form" class="spiral-quick-form">
        <?php wp_nonce_field('overthinking_quick_log', 'overthinking_quick_nonce'); ?>
        
        <!-- Severity Slider -->
        <div class="spiral-form-group">
            <label for="overthinking_severity" class="spiral-label">
                <?php echo esc_html($quick_fields['severity']['label']); ?>
                <span class="spiral-required">*</span>
            </label>
            <div class="spiral-slider-container">
                <input type="range" 
                       id="overthinking_severity" 
                       name="severity" 
                       min="<?php echo esc_attr($quick_fields['severity']['min']); ?>" 
                       max="<?php echo esc_attr($quick_fields['severity']['max']); ?>" 
                       value="5" 
                       class="spiral-slider" 
                       required>
                <div class="spiral-slider-labels">
                    <span class="spiral-min-label"><?php _e('Mild', 'spiral'); ?></span>
                    <span class="spiral-value-label">5</span>
                    <span class="spiral-max-label"><?php _e('Severe', 'spiral'); ?></span>
                </div>
            </div>
        </div>
        
        <!-- Primary Thought -->
        <div class="spiral-form-group">
            <label for="overthinking_primary_thought" class="spiral-label">
                <?php echo esc_html($quick_fields['primary_thought']['label']); ?>
                <span class="spiral-required">*</span>
                <span class="spiral-encryption-indicator" title="<?php esc_attr_e('This field is encrypted', 'spiral'); ?>">
                    <span class="dashicons dashicons-lock"></span>
                </span>
            </label>
            <textarea id="overthinking_primary_thought" 
                      name="primary_thought" 
                      class="spiral-textarea spiral-encrypted" 
                      maxlength="<?php echo esc_attr($quick_fields['primary_thought']['maxlength']); ?>" 
                      placeholder="<?php echo esc_attr($quick_fields['primary_thought']['placeholder']); ?>" 
                      required></textarea>
            <div class="spiral-char-count">
                <span class="spiral-char-current">0</span> / <?php echo esc_html($quick_fields['primary_thought']['maxlength']); ?>
            </div>
        </div>
        
        <!-- Duration -->
        <div class="spiral-form-group">
            <label for="overthinking_duration" class="spiral-label">
                <?php echo esc_html($quick_fields['duration']['label']); ?>
                <span class="spiral-required">*</span>
            </label>
            <select id="overthinking_duration" name="duration" class="spiral-select" required>
                <option value=""><?php _e('Select duration', 'spiral'); ?></option>
                <?php foreach ($quick_fields['duration']['options'] as $value => $label): ?>
                <option value="<?php echo esc_attr($value); ?>"><?php echo esc_html($label); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <!-- Submit Button -->
        <div class="spiral-form-actions">
            <button type="submit" class="spiral-button spiral-button-primary" id="spiral-quick-submit">
                <span class="spiral-button-text"><?php _e('Log Episode', 'spiral'); ?></span>
                <span class="spiral-button-loading" style="display: none;">
                    <span class="dashicons dashicons-update spin"></span>
                </span>
            </button>
        </div>
    </form>
    
    <!-- Recent Episodes -->
    <div class="spiral-recent-episodes">
        <h4 class="spiral-section-title">
            <?php _e('Recent Episodes', 'spiral'); ?>
            <span class="spiral-episode-count">(<?php echo count($recent_episodes); ?>)</span>
        </h4>
        
        <?php if (!empty($recent_episodes)): ?>
        <div class="spiral-episode-list">
            <?php foreach ($recent_episodes as $episode): ?>
            <div class="spiral-episode-item" data-episode-id="<?php echo esc_attr($episode['episode_id']); ?>">
                <div class="spiral-episode-header">
                    <span class="spiral-episode-time">
                        <?php echo human_time_diff(strtotime($episode['episode_date']), current_time('timestamp')) . ' ' . __('ago', 'spiral'); ?>
                    </span>
                    <span class="spiral-episode-severity spiral-severity-<?php echo esc_attr($episode['severity_score']); ?>">
                        <?php echo esc_html($episode['severity_score']); ?>/10
                    </span>
                </div>
                <div class="spiral-episode-content">
                    <span class="spiral-episode-type">
                        <?php 
                        $type_labels = array(
                            'rumination' => __('Rumination', 'spiral'),
                            'worry' => __('Worry', 'spiral'),
                            'analysis_paralysis' => __('Analysis Paralysis', 'spiral'),
                            'catastrophizing' => __('Catastrophizing', 'spiral'),
                            'mixed' => __('Mixed', 'spiral')
                        );
                        $type = $episode['metadata']['overthinking_type'] ?? 'unknown';
                        echo esc_html($type_labels[$type] ?? __('Unknown', 'spiral'));
                        ?>
                    </span>
                    <span class="spiral-episode-duration">
                        <?php echo esc_html($episode['duration_minutes'] ?? __('Unknown duration', 'spiral')); ?>
                    </span>
                </div>
                <?php if (isset($episode['correlation_indicator'])): ?>
                <div class="spiral-episode-correlation">
                    <span class="dashicons dashicons-networking"></span>
                    <span class="spiral-correlation-text"><?php echo esc_html($episode['correlation_indicator']); ?></span>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        
        <div class="spiral-view-all">
            <a href="<?php echo home_url('/my-overthinking-history/'); ?>" class="spiral-link">
                <?php _e('View all episodes →', 'spiral'); ?>
            </a>
        </div>
        <?php else: ?>
        <div class="spiral-no-episodes">
            <p><?php _e('No recent episodes logged.', 'spiral'); ?></p>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Correlation Summary (if available) -->
    <?php if ($correlation_summary && !empty($correlation_summary['correlations'])): ?>
    <div class="spiral-correlation-summary">
        <h4 class="spiral-section-title">
            <span class="dashicons dashicons-networking"></span>
            <?php _e('Pattern Connections', 'spiral'); ?>
        </h4>
        <div class="spiral-correlation-items">
            <?php foreach ($correlation_summary['correlations'] as $correlation): ?>
            <div class="spiral-correlation-item">
                <span class="spiral-correlation-type"><?php echo esc_html($correlation['type']); ?></span>
                <span class="spiral-correlation-strength"><?php echo esc_html($correlation['strength']); ?>%</span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Success Message Container -->
    <div id="spiral-quick-success" class="spiral-success-message" style="display: none;">
        <span class="dashicons dashicons-yes-alt"></span>
        <span class="spiral-success-text"></span>
    </div>
    
    <!-- Error Message Container -->
    <div id="spiral-quick-error" class="spiral-error-message" style="display: none;">
        <span class="dashicons dashicons-warning"></span>
        <span class="spiral-error-text"></span>
    </div>
    
</div>

<script type="text/javascript">
// Initialize quick logger when ready
jQuery(document).ready(function($) {
    if (typeof spiralEngineOverthinking !== 'undefined') {
        spiralEngineOverthinking.initQuickLogger();
    }
});
</script>

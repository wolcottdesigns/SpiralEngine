<?php
/**
 * Widget Preview Cards Template
 * 
 * @package     SpiralEngine
 * @subpackage  Templates/Widgets/Dashboard
 * @since       1.0.0
 * 
 * File: templates/widgets/dashboard/widget-cards.php
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Get available preview widgets
$preview_widgets = $dashboard_data['preview_widgets'] ?? array();
$user_membership = $dashboard_data['membership_level'] ?? 'seeker';

// Group widgets by category
$widget_categories = array(
    'loggers' => array(
        'title' => __('Episode Loggers', 'spiralengine'),
        'widgets' => array()
    ),
    'assessments' => array(
        'title' => __('Assessments', 'spiralengine'),
        'widgets' => array()
    ),
    'insights' => array(
        'title' => __('Insights & Forecasts', 'spiralengine'),
        'widgets' => array()
    ),
    'tools' => array(
        'title' => __('Additional Tools', 'spiralengine'),
        'widgets' => array()
    )
);

// Categorize widgets
foreach ($preview_widgets as $widget_id => $widget) {
    if (strpos($widget_id, '_logger') !== false) {
        $widget_categories['loggers']['widgets'][$widget_id] = $widget;
    } elseif (strpos($widget_id, 'assessment') !== false) {
        $widget_categories['assessments']['widgets'][$widget_id] = $widget;
    } elseif (in_array($widget_id, array('episode_correlations', 'unified_forecast', 'pattern_insights'))) {
        $widget_categories['insights']['widgets'][$widget_id] = $widget;
    } else {
        $widget_categories['tools']['widgets'][$widget_id] = $widget;
    }
}

// Membership levels for comparison
$membership_levels = array(
    'seeker' => 0,
    'explorer' => 1,
    'navigator' => 2,
    'legend' => 3
);

$user_level = $membership_levels[$user_membership] ?? 0;
?>

<div class="sp-widget-cards-container">
    
    <?php foreach ($widget_categories as $category_id => $category): ?>
        <?php if (!empty($category['widgets'])): ?>
            <div class="sp-widget-category" data-category="<?php echo esc_attr($category_id); ?>">
                <h3 class="sp-category-title"><?php echo esc_html($category['title']); ?></h3>
                
                <div class="sp-widget-cards-grid">
                    <?php foreach ($category['widgets'] as $widget_id => $widget): ?>
                        <?php
                        // Check if user has access
                        $has_access = true;
                        $required_level = 0;
                        
                        if (!empty($widget['membership_required'])) {
                            $required_membership = is_array($widget['membership_required']) 
                                ? $widget['membership_required'][0] 
                                : $widget['membership_required'];
                            $required_level = $membership_levels[$required_membership] ?? 0;
                            $has_access = $user_level >= $required_level;
                        }
                        
                        // Get widget-specific data
                        $widget_data = $this->preview_loader->get_widget_preview_data($widget_id, get_current_user_id());
                        ?>
                        
                        <div class="sp-preview-widget-card <?php echo !$has_access ? 'sp-locked' : ''; ?>" 
                             data-widget="<?php echo esc_attr($widget_id); ?>">
                            
                            <!-- Widget Header -->
                            <div class="sp-card-header">
                                <div class="sp-card-icon">
                                    <?php if (!empty($widget['icon'])): ?>
                                        <span class="<?php echo esc_attr($widget['icon']); ?>"></span>
                                    <?php else: ?>
                                        <span class="dashicons dashicons-admin-generic"></span>
                                    <?php endif; ?>
                                </div>
                                <h4 class="sp-card-title"><?php echo esc_html($widget['title']); ?></h4>
                                
                                <?php if (!$has_access): ?>
                                    <span class="sp-membership-badge <?php echo esc_attr($required_membership); ?>">
                                        <?php echo esc_html(ucfirst($required_membership)); ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Widget Content -->
                            <div class="sp-card-content">
                                <?php if ($has_access): ?>
                                    <?php $this->render_widget_preview_content($widget_id, $widget, $widget_data); ?>
                                <?php else: ?>
                                    <div class="sp-locked-content">
                                        <span class="dashicons dashicons-lock"></span>
                                        <p><?php printf(__('Upgrade to %s to unlock', 'spiralengine'), ucfirst($required_membership)); ?></p>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Widget Actions -->
                            <div class="sp-card-actions">
                                <?php if ($has_access): ?>
                                    <?php if ($widget_id === 'overthinking_logger'): ?>
                                        <button class="sp-button sp-button-primary sp-quick-log-btn" 
                                                data-episode-type="overthinking">
                                            <?php _e('Quick Log', 'spiralengine'); ?>
                                        </button>
                                    <?php elseif (strpos($widget_id, '_logger') !== false): ?>
                                        <?php 
                                        $episode_type = str_replace('_logger', '', $widget_id);
                                        ?>
                                        <button class="sp-button sp-button-primary sp-quick-log-btn" 
                                                data-episode-type="<?php echo esc_attr($episode_type); ?>">
                                            <?php _e('Quick Log', 'spiralengine'); ?>
                                        </button>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($widget['full_widget_url'])): ?>
                                        <a href="<?php echo esc_url($widget['full_widget_url']); ?>" 
                                           class="sp-button sp-button-secondary">
                                            <?php _e('Open Full', 'spiralengine'); ?> →
                                        </a>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <a href="<?php echo home_url('/membership/'); ?>" 
                                       class="sp-button sp-button-primary">
                                        <?php _e('Upgrade to Unlock', 'spiralengine'); ?>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    <?php endforeach; ?>
    
    <!-- Quick Log Modal -->
    <div class="sp-modal sp-quick-log-modal" id="sp-quick-log-modal" style="display: none;">
        <div class="sp-modal-content">
            <div class="sp-modal-header">
                <h3><?php _e('Quick Episode Log', 'spiralengine'); ?></h3>
                <button class="sp-modal-close" data-modal="sp-quick-log-modal">
                    <span class="dashicons dashicons-no"></span>
                </button>
            </div>
            <div class="sp-modal-body">
                <form id="sp-quick-log-form">
                    <input type="hidden" name="episode_type" id="sp-quick-log-type" value="">
                    
                    <div class="sp-form-group">
                        <label for="sp-quick-severity"><?php _e('Severity Level', 'spiralengine'); ?></label>
                        <div class="sp-severity-slider">
                            <input type="range" id="sp-quick-severity" name="severity" 
                                   min="1" max="10" value="5" class="sp-slider">
                            <div class="sp-severity-labels">
                                <span>1</span>
                                <span>5</span>
                                <span>10</span>
                            </div>
                            <div class="sp-severity-value">
                                <span id="sp-severity-display">5</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="sp-form-group">
                        <label for="sp-quick-thought"><?php _e('Primary Thought (Optional)', 'spiralengine'); ?></label>
                        <textarea id="sp-quick-thought" name="thought" 
                                  placeholder="<?php esc_attr_e('What triggered this episode?', 'spiralengine'); ?>"
                                  rows="3"></textarea>
                    </div>
                </form>
            </div>
            <div class="sp-modal-footer">
                <button class="sp-button sp-button-secondary" data-modal="sp-quick-log-modal">
                    <?php _e('Cancel', 'spiralengine'); ?>
                </button>
                <button class="sp-button sp-button-primary" id="sp-save-quick-log">
                    <?php _e('Log Episode', 'spiralengine'); ?>
                </button>
            </div>
        </div>
    </div>
    
</div>

<?php
// Helper method to render widget preview content (called from main class)
if (!function_exists('spiralengine_render_widget_preview')):
function spiralengine_render_widget_preview($widget_id, $widget, $data) {
    switch ($widget_id) {
        case 'overthinking_logger':
        case 'anxiety_logger':
        case 'ptsd_logger':
        case 'depression_logger':
            ?>
            <div class="sp-logger-preview">
                <?php if (!empty($data['last_episode'])): ?>
                    <div class="sp-last-episode">
                        <span class="sp-time-ago">
                            <?php echo human_time_diff(strtotime($data['last_episode']['episode_date'])) . ' ' . __('ago', 'spiralengine'); ?>
                        </span>
                        <div class="sp-severity-display">
                            <?php _e('Severity:', 'spiralengine'); ?> 
                            <span class="sp-severity-value"><?php echo $data['last_episode']['severity_score']; ?>/10</span>
                        </div>
                    </div>
                <?php else: ?>
                    <p class="sp-no-episodes"><?php _e('No episodes logged', 'spiralengine'); ?></p>
                <?php endif; ?>
                
                <div class="sp-week-stats">
                    <span class="sp-stat-value"><?php echo $data['week_count'] ?? 0; ?></span>
                    <span class="sp-stat-label"><?php _e('this week', 'spiralengine'); ?></span>
                </div>
            </div>
            <?php
            break;
            
        case 'spiral_assessment':
            ?>
            <div class="sp-assessment-preview">
                <?php if (!empty($data['last_score'])): ?>
                    <div class="sp-score-mini">
                        <span class="sp-score-value"><?php echo number_format($data['last_score'], 1); ?></span>
                        <span class="sp-score-label"><?php _e('Current Score', 'spiralengine'); ?></span>
                    </div>
                    <div class="sp-last-taken">
                        <?php 
                        printf(__('Last taken %s', 'spiralengine'), 
                            human_time_diff(strtotime($data['last_taken'])) . ' ' . __('ago', 'spiralengine')
                        ); 
                        ?>
                    </div>
                <?php else: ?>
                    <p><?php _e('No assessment taken yet', 'spiralengine'); ?></p>
                <?php endif; ?>
            </div>
            <?php
            break;
            
        case 'episode_correlations':
            ?>
            <div class="sp-correlations-preview">
                <?php if (!empty($data['correlation_count'])): ?>
                    <div class="sp-correlation-stats">
                        <span class="sp-stat-value"><?php echo $data['correlation_count']; ?></span>
                        <span class="sp-stat-label"><?php _e('patterns found', 'spiralengine'); ?></span>
                    </div>
                    <?php if (!empty($data['strongest_correlation'])): ?>
                        <p class="sp-correlation-hint">
                            <?php echo esc_html($data['strongest_correlation']); ?>
                        </p>
                    <?php endif; ?>
                <?php else: ?>
                    <p><?php _e('No patterns detected yet', 'spiralengine'); ?></p>
                <?php endif; ?>
            </div>
            <?php
            break;
            
        case 'unified_forecast':
            ?>
            <div class="sp-forecast-preview">
                <?php if (!empty($data['overall_risk'])): ?>
                    <div class="sp-risk-mini">
                        <span class="sp-risk-value"><?php echo number_format($data['overall_risk'], 1); ?></span>
                        <span class="sp-risk-label"><?php _e('Risk Level', 'spiralengine'); ?></span>
                    </div>
                    <p class="sp-forecast-summary"><?php echo esc_html($data['summary']); ?></p>
                <?php else: ?>
                    <p><?php _e('Building your forecast...', 'spiralengine'); ?></p>
                <?php endif; ?>
            </div>
            <?php
            break;
            
        case 'gratitude_journal':
            ?>
            <div class="sp-gratitude-preview">
                <div class="sp-gratitude-stats">
                    <span class="sp-stat-value"><?php echo $data['entry_count'] ?? 0; ?></span>
                    <span class="sp-stat-label"><?php _e('entries', 'spiralengine'); ?></span>
                </div>
                <?php if (!empty($data['streak'])): ?>
                    <div class="sp-gratitude-streak">
                        <span class="dashicons dashicons-star-filled"></span>
                        <?php printf(__('%d day streak!', 'spiralengine'), $data['streak']); ?>
                    </div>
                <?php endif; ?>
            </div>
            <?php
            break;
            
        case 'meditation_timer':
            ?>
            <div class="sp-meditation-preview">
                <div class="sp-meditation-stats">
                    <span class="sp-stat-value"><?php echo $data['total_minutes'] ?? 0; ?></span>
                    <span class="sp-stat-label"><?php _e('minutes this month', 'spiralengine'); ?></span>
                </div>
                <?php if (!empty($data['favorite_type'])): ?>
                    <p class="sp-meditation-favorite">
                        <?php printf(__('Favorite: %s', 'spiralengine'), $data['favorite_type']); ?>
                    </p>
                <?php endif; ?>
            </div>
            <?php
            break;
            
        default:
            ?>
            <div class="sp-widget-preview-default">
                <?php if (!empty($widget['description'])): ?>
                    <p><?php echo esc_html($widget['description']); ?></p>
                <?php else: ?>
                    <p><?php _e('Widget preview coming soon', 'spiralengine'); ?></p>
                <?php endif; ?>
            </div>
            <?php
            break;
    }
}
endif;
?>

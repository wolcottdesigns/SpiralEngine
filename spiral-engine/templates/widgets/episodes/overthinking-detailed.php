<?php
// templates/widgets/episodes/overthinking-detailed.php

/**
 * Overthinking Episode Detailed Logger Template
 * 
 * Full standalone logging experience with all fields
 * Includes biological factors, pattern detection, and correlation warnings
 * 
 * @package SpiralEngine
 * @subpackage Templates/Widgets/Episodes
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get widget instance and user data
$widget = new SpiralEngine_Widget_Overthinking();
$questions = $widget->get_episode_questions();
$user_id = get_current_user_id();
$membership = $widget->get_user_membership($user_id);

// Check user permissions
$access_control = new SpiralEngine_Access_Control();
if (!$access_control->can_use_feature($user_id, 'detailed_log')) {
    echo '<div class="spiral-feature-locked">';
    echo '<p>' . __('Please log in to use the Overthinking Logger.', 'spiral') . '</p>';
    echo '</div>';
    return;
}

// Get user gender for biological fields
$user_gender = get_user_meta($user_id, 'spiral_user_gender', true);

// Get recent patterns and correlations
$recent_patterns = $widget->get_recent_patterns($user_id);
$active_correlations = null;

if (class_exists('SPIRAL_Correlation_Engine') && $access_control->can_view_correlations($user_id)) {
    $correlation_engine = new SPIRAL_Correlation_Engine();
    $active_correlations = $correlation_engine->get_active_correlations($user_id, 'overthinking');
}

?>

<div class="spiral-widget spiral-overthinking-detailed" data-widget-id="overthinking_logger" data-mode="detailed">
    
    <!-- Widget Header -->
    <div class="spiral-widget-header">
        <div class="spiral-header-content">
            <h2 class="spiral-widget-title">
                <span class="dashicons dashicons-admin-page"></span>
                <?php _e('Overthinking Episode Logger', 'spiral'); ?>
            </h2>
            <p class="spiral-widget-description">
                <?php _e('Track your overthinking episodes to identify patterns and triggers', 'spiral'); ?>
            </p>
        </div>
        <div class="spiral-header-actions">
            <button type="button" class="spiral-button spiral-button-secondary" id="spiral-view-history">
                <span class="dashicons dashicons-backup"></span>
                <?php _e('View History', 'spiral'); ?>
            </button>
        </div>
    </div>
    
    <!-- Active Warnings/Correlations -->
    <?php if ($active_correlations && !empty($active_correlations['warnings'])): ?>
    <div class="spiral-correlation-warnings">
        <?php foreach ($active_correlations['warnings'] as $warning): ?>
        <div class="spiral-warning spiral-warning-<?php echo esc_attr($warning['severity']); ?>">
            <span class="dashicons dashicons-warning"></span>
            <div class="spiral-warning-content">
                <strong><?php echo esc_html($warning['title']); ?></strong>
                <p><?php echo esc_html($warning['message']); ?></p>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    
    <!-- Progress Indicator -->
    <div class="spiral-progress-bar">
        <div class="spiral-progress-track">
            <div class="spiral-progress-fill" style="width: 0%"></div>
        </div>
        <div class="spiral-progress-steps">
            <?php 
            $step_count = 0;
            foreach ($questions as $section_key => $section): 
                if (!$access_control->can_access_section($user_id, $section['membership'] ?? 'free')):
                    continue;
                endif;
                $step_count++;
            ?>
            <div class="spiral-step" data-step="<?php echo esc_attr($step_count); ?>" data-section="<?php echo esc_attr($section_key); ?>">
                <span class="spiral-step-dot"></span>
                <span class="spiral-step-label"><?php echo esc_html($section['title']); ?></span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <!-- Main Form -->
    <form id="spiral-overthinking-detailed-form" class="spiral-detailed-form">
        <?php wp_nonce_field('overthinking_detailed_log', 'overthinking_detailed_nonce'); ?>
        
        <?php 
        $section_number = 0;
        foreach ($questions as $section_key => $section): 
            // Check section access
            $has_access = $access_control->can_access_section($user_id, $section['membership'] ?? 'free');
            $section_class = $has_access ? '' : 'spiral-section-locked';
            $section_number++;
        ?>
        
        <div class="spiral-form-section <?php echo esc_attr($section_class); ?>" 
             data-section="<?php echo esc_attr($section_key); ?>"
             data-membership="<?php echo esc_attr($section['membership'] ?? 'free'); ?>">
            
            <h3 class="spiral-section-title">
                <span class="spiral-section-number"><?php echo $section_number; ?></span>
                <?php echo esc_html($section['title']); ?>
                <?php if (!$has_access): ?>
                <span class="spiral-lock-indicator">
                    <span class="dashicons dashicons-lock"></span>
                    <?php echo esc_html(ucfirst($section['membership'] ?? 'free') . '+'); ?>
                </span>
                <?php endif; ?>
            </h3>
            
            <?php if ($has_access): ?>
            <div class="spiral-section-fields">
                <?php foreach ($section['fields'] as $field_key => $field): 
                    // Check gender-specific fields
                    if (isset($field['gender_specific']) && $field['gender_specific'] !== $user_gender):
                        continue;
                    endif;
                    
                    // Build field ID and name
                    $field_id = 'overthinking_' . $field_key;
                    $field_name = 'overthinking_' . $field_key;
                ?>
                
                <div class="spiral-form-group" 
                     <?php if (isset($field['conditional'])): ?>
                     data-conditional="<?php echo esc_attr(json_encode($field['conditional'])); ?>"
                     style="display: none;"
                     <?php endif; ?>>
                    
                    <label for="<?php echo esc_attr($field_id); ?>" class="spiral-label">
                        <?php echo esc_html($field['label']); ?>
                        <?php if ($field['required'] ?? false): ?>
                        <span class="spiral-required">*</span>
                        <?php endif; ?>
                        <?php if ($field['encrypted'] ?? false): ?>
                        <span class="spiral-encryption-indicator" title="<?php esc_attr_e('This field is encrypted', 'spiral'); ?>">
                            <span class="dashicons dashicons-lock"></span>
                        </span>
                        <?php endif; ?>
                    </label>
                    
                    <?php if (isset($field['help'])): ?>
                    <p class="spiral-field-help"><?php echo esc_html($field['help']); ?></p>
                    <?php endif; ?>
                    
                    <?php
                    // Render field based on type
                    switch ($field['type']):
                        case 'slider':
                    ?>
                        <div class="spiral-slider-container">
                            <input type="range" 
                                   id="<?php echo esc_attr($field_id); ?>" 
                                   name="<?php echo esc_attr($field_name); ?>" 
                                   min="<?php echo esc_attr($field['min']); ?>" 
                                   max="<?php echo esc_attr($field['max']); ?>" 
                                   value="<?php echo esc_attr(($field['min'] + $field['max']) / 2); ?>" 
                                   class="spiral-slider"
                                   <?php if ($field['required'] ?? false) echo 'required'; ?>>
                            <div class="spiral-slider-labels">
                                <span class="spiral-min-label"><?php echo esc_html($field['min']); ?></span>
                                <span class="spiral-value-label"><?php echo esc_html(($field['min'] + $field['max']) / 2); ?></span>
                                <span class="spiral-max-label"><?php echo esc_html($field['max']); ?></span>
                            </div>
                        </div>
                    <?php
                        break;
                        
                        case 'select':
                    ?>
                        <select id="<?php echo esc_attr($field_id); ?>" 
                                name="<?php echo esc_attr($field_name); ?>" 
                                class="spiral-select"
                                <?php if ($field['required'] ?? false) echo 'required'; ?>>
                            <option value=""><?php _e('Please select...', 'spiral'); ?></option>
                            <?php foreach ($field['options'] as $value => $label): ?>
                            <option value="<?php echo esc_attr($value); ?>"><?php echo esc_html($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    <?php
                        break;
                        
                        case 'textarea':
                    ?>
                        <textarea id="<?php echo esc_attr($field_id); ?>" 
                                  name="<?php echo esc_attr($field_name); ?>" 
                                  class="spiral-textarea <?php if ($field['encrypted'] ?? false) echo 'spiral-encrypted'; ?>"
                                  placeholder="<?php echo esc_attr($field['placeholder'] ?? ''); ?>"
                                  <?php if (isset($field['maxlength'])) echo 'maxlength="' . esc_attr($field['maxlength']) . '"'; ?>
                                  <?php if ($field['required'] ?? false) echo 'required'; ?>></textarea>
                        <?php if (isset($field['maxlength'])): ?>
                        <div class="spiral-char-count">
                            <span class="spiral-char-current">0</span> / <?php echo esc_html($field['maxlength']); ?>
                        </div>
                        <?php endif; ?>
                    <?php
                        break;
                        
                        case 'checkbox_group':
                    ?>
                        <div class="spiral-checkbox-group">
                            <?php foreach ($field['options'] as $value => $label): ?>
                            <label class="spiral-checkbox-label">
                                <input type="checkbox" 
                                       name="<?php echo esc_attr($field_name); ?>[]" 
                                       value="<?php echo esc_attr($value); ?>"
                                       class="spiral-checkbox">
                                <span><?php echo esc_html($label); ?></span>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    <?php
                        break;
                    endswitch;
                    ?>
                </div>
                
                <?php endforeach; ?>
            </div>
            
            <?php else: ?>
            <!-- Locked Section Preview -->
            <div class="spiral-section-locked-content">
                <div class="spiral-blur-overlay">
                    <p class="spiral-locked-message">
                        <?php 
                        printf(
                            __('Upgrade to %s or higher to access %s', 'spiral'),
                            '<strong>' . ucfirst($section['membership'] ?? 'free') . '</strong>',
                            '<strong>' . esc_html($section['title']) . '</strong>'
                        );
                        ?>
                    </p>
                    <a href="<?php echo home_url('/pricing/'); ?>" class="spiral-button spiral-button-upgrade">
                        <?php _e('Upgrade Now', 'spiral'); ?>
                    </a>
                </div>
                
                <!-- Blurred preview of fields -->
                <div class="spiral-blurred-fields">
                    <?php foreach (array_slice($section['fields'], 0, 3) as $field): ?>
                    <div class="spiral-blurred-field">
                        <div class="spiral-blurred-label"></div>
                        <div class="spiral-blurred-input"></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            
        </div>
        
        <?php endforeach; ?>
        
        <!-- Form Actions -->
        <div class="spiral-form-actions">
            <button type="button" class="spiral-button spiral-button-secondary" id="spiral-save-draft">
                <span class="dashicons dashicons-welcome-write-blog"></span>
                <?php _e('Save Draft', 'spiral'); ?>
            </button>
            <button type="submit" class="spiral-button spiral-button-primary" id="spiral-submit-episode">
                <span class="spiral-button-text"><?php _e('Log Episode', 'spiral'); ?></span>
                <span class="spiral-button-loading" style="display: none;">
                    <span class="dashicons dashicons-update spin"></span>
                    <?php _e('Processing...', 'spiral'); ?>
                </span>
            </button>
        </div>
    </form>
    
    <!-- Pattern Insights (if any) -->
    <?php if (!empty($recent_patterns)): ?>
    <div class="spiral-pattern-insights">
        <h3 class="spiral-insights-title">
            <span class="dashicons dashicons-chart-line"></span>
            <?php _e('Recent Patterns', 'spiral'); ?>
        </h3>
        <div class="spiral-pattern-cards">
            <?php foreach ($recent_patterns as $pattern): ?>
            <div class="spiral-pattern-card spiral-pattern-<?php echo esc_attr($pattern['type']); ?>">
                <div class="spiral-pattern-icon">
                    <span class="dashicons dashicons-<?php echo esc_attr($pattern['icon']); ?>"></span>
                </div>
                <div class="spiral-pattern-content">
                    <h4><?php echo esc_html($pattern['title']); ?></h4>
                    <p><?php echo esc_html($pattern['description']); ?></p>
                    <div class="spiral-pattern-meta">
                        <span class="spiral-confidence">
                            <?php printf(__('Confidence: %s%%', 'spiral'), $pattern['confidence']); ?>
                        </span>
                        <span class="spiral-occurrences">
                            <?php printf(__('%d occurrences', 'spiral'), $pattern['count']); ?>
                        </span>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Success Modal -->
    <div id="spiral-success-modal" class="spiral-modal" style="display: none;">
        <div class="spiral-modal-content">
            <span class="spiral-modal-close">&times;</span>
            <div class="spiral-modal-body">
                <div class="spiral-success-icon">
                    <span class="dashicons dashicons-yes-alt"></span>
                </div>
                <h3><?php _e('Episode Logged Successfully!', 'spiral'); ?></h3>
                <div id="spiral-episode-insights"></div>
                <div class="spiral-modal-actions">
                    <button type="button" class="spiral-button spiral-button-secondary" id="spiral-log-another">
                        <?php _e('Log Another', 'spiral'); ?>
                    </button>
                    <a href="<?php echo home_url('/my-overthinking-history/'); ?>" class="spiral-button spiral-button-primary">
                        <?php _e('View History', 'spiral'); ?>
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Error Container -->
    <div id="spiral-error-container" class="spiral-error-message" style="display: none;">
        <span class="dashicons dashicons-warning"></span>
        <span class="spiral-error-text"></span>
    </div>
    
</div>

<!-- Auto-save indicator -->
<div class="spiral-autosave-indicator" style="display: none;">
    <span class="dashicons dashicons-cloud-saved"></span>
    <span><?php _e('Draft saved', 'spiral'); ?></span>
</div>

<script type="text/javascript">
// Initialize detailed logger when ready
jQuery(document).ready(function($) {
    if (typeof spiralEngineOverthinking !== 'undefined') {
        spiralEngineOverthinking.initDetailedLogger();
    }
});
</script>

<?php
/**
 * Overthinking Logger Widget
 *
 * @package     SpiralEngine
 * @subpackage  Widgets
 * @file        widgets/class-overthinking-logger.php
 * @since       1.0.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Overthinking Logger Widget Class
 *
 * Tracks overthinking episodes with detailed context
 *
 * @since 1.0.0
 */
class SpiralEngine_Widget_Overthinking_Logger extends SpiralEngine_Widget {
    
    /**
     * Initialize widget
     *
     * @since 1.0.0
     * @return void
     */
    protected function init() {
        $this->id = 'overthinking-logger';
        $this->name = __('Overthinking Logger', 'spiralengine');
        $this->description = __('Track and analyze overthinking episodes with triggers and patterns', 'spiralengine');
        $this->icon = 'dashicons-randomize';
        $this->min_tier = 'free';
        
        // Additional capabilities for this widget
        $this->capabilities = [
            'analyze_patterns',
            'set_reminders',
            'share_episodes'
        ];
        
        // Default settings
        $this->settings = [
            'quick_log_enabled' => true,
            'show_severity_labels' => true,
            'enable_voice_notes' => false,
            'default_reminder_time' => '21:00',
            'pattern_threshold' => 3
        ];
    }
    
    /**
     * Render widget form
     *
     * @since 1.0.0
     * @param array $args Form arguments
     * @return string HTML output
     */
    public function render_form($args = []) {
        $args = wp_parse_args($args, [
            'mode' => 'full',
            'show_recent' => true
        ]);
        
        $user_id = get_current_user_id();
        $tier = 'free'; // Default tier for now
        
        ob_start();
        ?>
        <div class="spiralengine-widget-form spiralengine-overthinking-form" data-widget="<?php echo esc_attr($this->id); ?>">
            <form id="spiralengine-overthinking-form" class="spiralengine-episode-form">
                <?php wp_nonce_field('spiralengine_' . $this->id . '_nonce', 'nonce'); ?>
                
                <?php if ($args['mode'] === 'quick'): ?>
                    <!-- Quick logging mode -->
                    <div class="spiralengine-quick-log">
                        <?php echo $this->render_field('text', [
                            'id' => 'overthinking_thought',
                            'name' => 'data[thought]',
                            'label' => __('What are you overthinking about?', 'spiralengine'),
                            'placeholder' => __('Brief description...', 'spiralengine'),
                            'required' => true,
                            'class' => 'spiralengine-quick-input'
                        ]); ?>
                        
                        <?php echo $this->render_field('range', [
                            'id' => 'overthinking_severity',
                            'name' => 'data[severity]',
                            'label' => __('Severity', 'spiralengine'),
                            'value' => '5',
                            'min' => '1',
                            'max' => '10',
                            'step' => '1',
                            'class' => 'spiralengine-severity-slider'
                        ]); ?>
                        
                        <button type="submit" class="button button-primary spiralengine-quick-save">
                            <?php _e('Quick Log', 'spiralengine'); ?>
                        </button>
                    </div>
                <?php else: ?>
                    <!-- Full form mode -->
                    <div class="spiralengine-form-section">
                        <h4><?php _e('Episode Details', 'spiralengine'); ?></h4>
                        
                        <?php echo $this->render_field('textarea', [
                            'id' => 'overthinking_thought',
                            'name' => 'data[thought]',
                            'label' => __('What are you overthinking about?', 'spiralengine'),
                            'placeholder' => __('Describe the thoughts that are looping in your mind...', 'spiralengine'),
                            'required' => true,
                            'rows' => 4
                        ]); ?>
                        
                        <?php echo $this->render_field('range', [
                            'id' => 'overthinking_severity',
                            'name' => 'data[severity]',
                            'label' => __('How severe is this episode?', 'spiralengine'),
                            'value' => '5',
                            'min' => '1',
                            'max' => '10',
                            'step' => '1',
                            'class' => 'spiralengine-severity-slider',
                            'description' => __('1 = Mild concern, 10 = Completely overwhelming', 'spiralengine')
                        ]); ?>
                        
                        <?php echo $this->render_field('select', [
                            'id' => 'overthinking_duration',
                            'name' => 'data[duration]',
                            'label' => __('How long have you been overthinking?', 'spiralengine'),
                            'options' => [
                                '' => __('Select duration...', 'spiralengine'),
                                'few-minutes' => __('A few minutes', 'spiralengine'),
                                '15-30-minutes' => __('15-30 minutes', 'spiralengine'),
                                '30-60-minutes' => __('30-60 minutes', 'spiralengine'),
                                '1-2-hours' => __('1-2 hours', 'spiralengine'),
                                '2-4-hours' => __('2-4 hours', 'spiralengine'),
                                'more-than-4' => __('More than 4 hours', 'spiralengine'),
                                'all-day' => __('All day', 'spiralengine'),
                                'multiple-days' => __('Multiple days', 'spiralengine')
                            ],
                            'required' => true
                        ]); ?>
                    </div>
                    
                    <div class="spiralengine-form-section">
                        <h4><?php _e('Triggers & Context', 'spiralengine'); ?></h4>
                        
                        <?php echo $this->render_field('text', [
                            'id' => 'overthinking_trigger',
                            'name' => 'data[trigger]',
                            'label' => __('What triggered this episode?', 'spiralengine'),
                            'placeholder' => __('e.g., Work email, social media, conversation...', 'spiralengine')
                        ]); ?>
                        
                        <?php echo $this->render_field('select', [
                            'id' => 'overthinking_category',
                            'name' => 'data[category]',
                            'label' => __('Category', 'spiralengine'),
                            'options' => [
                                '' => __('Select category...', 'spiralengine'),
                                'work' => __('Work/Career', 'spiralengine'),
                                'relationships' => __('Relationships', 'spiralengine'),
                                'health' => __('Health', 'spiralengine'),
                                'finances' => __('Finances', 'spiralengine'),
                                'future' => __('Future/Planning', 'spiralengine'),
                                'past' => __('Past Events', 'spiralengine'),
                                'social' => __('Social Situations', 'spiralengine'),
                                'decisions' => __('Decision Making', 'spiralengine'),
                                'performance' => __('Performance/Achievement', 'spiralengine'),
                                'other' => __('Other', 'spiralengine')
                            ]
                        ]); ?>
                        
                        <?php if (in_array($tier, ['silver', 'gold', 'platinum'])): ?>
                        <div class="spiralengine-field-group">
                            <label><?php _e('Physical Symptoms', 'spiralengine'); ?></label>
                            <div class="spiralengine-checkbox-group">
                                <?php
                                $symptoms = [
                                    'tension' => __('Muscle tension', 'spiralengine'),
                                    'headache' => __('Headache', 'spiralengine'),
                                    'fatigue' => __('Fatigue', 'spiralengine'),
                                    'restlessness' => __('Restlessness', 'spiralengine'),
                                    'stomach' => __('Stomach issues', 'spiralengine'),
                                    'heart-racing' => __('Heart racing', 'spiralengine'),
                                    'sweating' => __('Sweating', 'spiralengine'),
                                    'breathing' => __('Breathing difficulty', 'spiralengine')
                                ];
                                
                                foreach ($symptoms as $value => $label):
                                ?>
                                <label>
                                    <input type="checkbox" name="data[symptoms][]" value="<?php echo esc_attr($value); ?>">
                                    <?php echo esc_html($label); ?>
                                </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <?php if (in_array($tier, ['gold', 'platinum'])): ?>
                    <div class="spiralengine-form-section">
                        <h4><?php _e('Thought Pattern Analysis', 'spiralengine'); ?></h4>
                        
                        <div class="spiralengine-field-group">
                            <label><?php _e('Cognitive Distortions Present', 'spiralengine'); ?></label>
                            <div class="spiralengine-checkbox-group">
                                <?php
                                $distortions = [
                                    'all-or-nothing' => __('All-or-nothing thinking', 'spiralengine'),
                                    'overgeneralization' => __('Overgeneralization', 'spiralengine'),
                                    'mental-filter' => __('Mental filter', 'spiralengine'),
                                    'disqualifying' => __('Disqualifying the positive', 'spiralengine'),
                                    'jumping' => __('Jumping to conclusions', 'spiralengine'),
                                    'magnification' => __('Magnification/Minimization', 'spiralengine'),
                                    'emotional' => __('Emotional reasoning', 'spiralengine'),
                                    'should' => __('Should statements', 'spiralengine'),
                                    'labeling' => __('Labeling', 'spiralengine'),
                                    'personalization' => __('Personalization', 'spiralengine')
                                ];
                                
                                foreach ($distortions as $value => $label):
                                ?>
                                <label>
                                    <input type="checkbox" name="data[distortions][]" value="<?php echo esc_attr($value); ?>">
                                    <?php echo esc_html($label); ?>
                                </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <?php echo $this->render_field('textarea', [
                            'id' => 'overthinking_reframe',
                            'name' => 'data[reframe]',
                            'label' => __('Reframe your thoughts', 'spiralengine'),
                            'placeholder' => __('Try to write a more balanced perspective...', 'spiralengine'),
                            'rows' => 3,
                            'description' => __('Challenge your overthinking by writing a more realistic view', 'spiralengine')
                        ]); ?>
                    </div>
                    <?php endif; ?>
                    
                    <div class="spiralengine-form-section">
                        <h4><?php _e('Coping & Resolution', 'spiralengine'); ?></h4>
                        
                        <?php echo $this->render_field('select', [
                            'id' => 'overthinking_coping',
                            'name' => 'data[coping_strategy]',
                            'label' => __('What helped or might help?', 'spiralengine'),
                            'options' => [
                                '' => __('Select strategy...', 'spiralengine'),
                                'breathing' => __('Deep breathing', 'spiralengine'),
                                'exercise' => __('Physical exercise', 'spiralengine'),
                                'distraction' => __('Distraction activity', 'spiralengine'),
                                'talking' => __('Talking to someone', 'spiralengine'),
                                'writing' => __('Writing/Journaling', 'spiralengine'),
                                'meditation' => __('Meditation', 'spiralengine'),
                                'problem-solving' => __('Problem-solving', 'spiralengine'),
                                'acceptance' => __('Acceptance', 'spiralengine'),
                                'none-yet' => __('Nothing yet', 'spiralengine')
                            ]
                        ]); ?>
                        
                        <?php echo $this->render_field('textarea', [
                            'id' => 'overthinking_notes',
                            'name' => 'data[notes]',
                            'label' => __('Additional notes', 'spiralengine'),
                            'placeholder' => __('Any other thoughts or observations...', 'spiralengine'),
                            'rows' => 2
                        ]); ?>
                        
                        <?php if ($tier === 'platinum'): ?>
                        <?php echo $this->render_field('checkbox', [
                            'id' => 'overthinking_ai_analysis',
                            'name' => 'data[request_ai_analysis]',
                            'label' => __('Request AI analysis and suggestions', 'spiralengine'),
                            'value' => '1'
                        ]); ?>
                        <?php endif; ?>
                    </div>
                    
                    <div class="spiralengine-form-actions">
                        <button type="submit" class="button button-primary spiralengine-save-episode">
                            <span class="dashicons dashicons-saved"></span>
                            <?php _e('Save Episode', 'spiralengine'); ?>
                        </button>
                        
                        <?php if (in_array($tier, ['silver', 'gold', 'platinum'])): ?>
                        <button type="button" class="button spiralengine-save-template">
                            <span class="dashicons dashicons-star-empty"></span>
                            <?php _e('Save as Template', 'spiralengine'); ?>
                        </button>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </form>
            
            <?php if ($args['show_recent'] && $args['mode'] !== 'quick'): ?>
            <div class="spiralengine-recent-episodes">
                <h4><?php _e('Recent Overthinking Episodes', 'spiralengine'); ?></h4>
                <div class="spiralengine-episodes-list" data-widget="<?php echo esc_attr($this->id); ?>">
                    <div class="spiralengine-loading"><?php _e('Loading...', 'spiralengine'); ?></div>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php
        
        return ob_get_clean();
    }
    
    /**
     * Validate episode data
     *
     * @since 1.0.0
     * @param array $data Raw form data
     * @return array|WP_Error Validated data or error
     */
    public function validate_data($data) {
        $errors = [];
        $validated = [];
        
        // Validate thought/description
        if (empty($data['thought'])) {
            $errors['thought'] = __('Please describe what you are overthinking about.', 'spiralengine');
        } else {
            $validated['thought'] = sanitize_textarea_field($data['thought']);
        }
        
        // Validate severity
        $severity = isset($data['severity']) ? intval($data['severity']) : 0;
        if ($severity < 1 || $severity > 10) {
            $errors['severity'] = __('Severity must be between 1 and 10.', 'spiralengine');
        } else {
            $validated['severity'] = $severity;
        }
        
        // Validate duration
        if (!empty($data['duration'])) {
            $valid_durations = [
                'few-minutes', '15-30-minutes', '30-60-minutes', 
                '1-2-hours', '2-4-hours', 'more-than-4', 
                'all-day', 'multiple-days'
            ];
            if (in_array($data['duration'], $valid_durations)) {
                $validated['duration'] = $data['duration'];
            }
        }
        
        // Validate trigger
        if (!empty($data['trigger'])) {
            $validated['trigger'] = sanitize_text_field($data['trigger']);
        }
        
        // Validate category
        if (!empty($data['category'])) {
            $valid_categories = [
                'work', 'relationships', 'health', 'finances',
                'future', 'past', 'social', 'decisions',
                'performance', 'other'
            ];
            if (in_array($data['category'], $valid_categories)) {
                $validated['category'] = $data['category'];
            }
        }
        
        // Validate symptoms (array)
        if (!empty($data['symptoms']) && is_array($data['symptoms'])) {
            $valid_symptoms = [
                'tension', 'headache', 'fatigue', 'restlessness',
                'stomach', 'heart-racing', 'sweating', 'breathing'
            ];
            $validated['symptoms'] = array_intersect($data['symptoms'], $valid_symptoms);
        }
        
        // Validate distortions (array)
        if (!empty($data['distortions']) && is_array($data['distortions'])) {
            $valid_distortions = [
                'all-or-nothing', 'overgeneralization', 'mental-filter',
                'disqualifying', 'jumping', 'magnification',
                'emotional', 'should', 'labeling', 'personalization'
            ];
            $validated['distortions'] = array_intersect($data['distortions'], $valid_distortions);
        }
        
        // Validate reframe
        if (!empty($data['reframe'])) {
            $validated['reframe'] = sanitize_textarea_field($data['reframe']);
        }
        
        // Validate coping strategy
        if (!empty($data['coping_strategy'])) {
            $valid_strategies = [
                'breathing', 'exercise', 'distraction', 'talking',
                'writing', 'meditation', 'problem-solving',
                'acceptance', 'none-yet'
            ];
            if (in_array($data['coping_strategy'], $valid_strategies)) {
                $validated['coping_strategy'] = $data['coping_strategy'];
            }
        }
        
        // Validate notes
        if (!empty($data['notes'])) {
            $validated['notes'] = sanitize_textarea_field($data['notes']);
        }
        
        // Validate AI analysis request
        if (!empty($data['request_ai_analysis'])) {
            $validated['request_ai_analysis'] = true;
        }
        
        // Return errors if any
        if (!empty($errors)) {
            return new WP_Error('validation_failed', __('Please fix the errors below.', 'spiralengine'), $errors);
        }
        
        return $validated;
    }
    
    /**
     * Prepare metadata before saving
     *
     * @since 1.0.0
     * @param array $metadata Base metadata
     * @param array $data Episode data
     * @return array Modified metadata
     */
    protected function prepare_metadata($metadata, $data) {
        // Add widget-specific metadata
        $metadata['has_symptoms'] = !empty($data['symptoms']);
        $metadata['has_distortions'] = !empty($data['distortions']);
        $metadata['has_reframe'] = !empty($data['reframe']);
        
        // Calculate episode quality score
        $quality_score = 0;
        if (!empty($data['trigger'])) $quality_score += 20;
        if (!empty($data['category'])) $quality_score += 10;
        if (!empty($data['symptoms'])) $quality_score += 15;
        if (!empty($data['distortions'])) $quality_score += 20;
        if (!empty($data['reframe'])) $quality_score += 25;
        if (!empty($data['coping_strategy']) && $data['coping_strategy'] !== 'none-yet') $quality_score += 10;
        
        $metadata['quality_score'] = $quality_score;
        
        return $metadata;
    }
    
    /**
     * Render analytics for user
     *
     * @since 1.0.0
     * @param int $user_id User ID
     * @return string HTML output
     */
    public function render_analytics($user_id) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'spiralengine_episodes';
        $days = 30; // Last 30 days
        
        // Get episode count
        $episode_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} 
            WHERE user_id = %d 
            AND widget_id = %s 
            AND created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)",
            $user_id, $this->id, $days
        ));
        
        // Get average severity
        $avg_severity = $wpdb->get_var($wpdb->prepare(
            "SELECT AVG(severity) FROM {$table} 
            WHERE user_id = %d 
            AND widget_id = %s 
            AND created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)",
            $user_id, $this->id, $days
        ));
        
        // Get most common triggers
        $episodes = $wpdb->get_results($wpdb->prepare(
            "SELECT data FROM {$table} 
            WHERE user_id = %d 
            AND widget_id = %s 
            AND created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)
            ORDER BY created_at DESC
            LIMIT 100",
            $user_id, $this->id, $days
        ));
        
        $triggers = [];
        $categories = [];
        $coping_strategies = [];
        
        foreach ($episodes as $episode) {
            $data = json_decode($episode->data, true);
            
            if (!empty($data['trigger'])) {
                $triggers[] = $data['trigger'];
            }
            
            if (!empty($data['category'])) {
                $categories[$data['category']] = ($categories[$data['category']] ?? 0) + 1;
            }
            
            if (!empty($data['coping_strategy']) && $data['coping_strategy'] !== 'none-yet') {
                $coping_strategies[$data['coping_strategy']] = ($coping_strategies[$data['coping_strategy']] ?? 0) + 1;
            }
        }
        
        ob_start();
        ?>
        <div class="spiralengine-widget-analytics spiralengine-overthinking-analytics">
            <h3><?php echo esc_html($this->name); ?> <?php _e('Analytics', 'spiralengine'); ?></h3>
            
            <div class="spiralengine-stats-grid">
                <div class="stat-card">
                    <span class="stat-value"><?php echo intval($episode_count); ?></span>
                    <span class="stat-label"><?php _e('Episodes (30 days)', 'spiralengine'); ?></span>
                </div>
                
                <div class="stat-card">
                    <span class="stat-value"><?php echo number_format($avg_severity, 1); ?></span>
                    <span class="stat-label"><?php _e('Average Severity', 'spiralengine'); ?></span>
                </div>
                
                <div class="stat-card">
                    <span class="stat-value"><?php echo $episode_count > 0 ? round($episode_count / 30, 1) : 0; ?></span>
                    <span class="stat-label"><?php _e('Episodes per Day', 'spiralengine'); ?></span>
                </div>
            </div>
            
            <?php if (!empty($categories)): ?>
            <div class="spiralengine-chart-section">
                <h4><?php _e('Top Categories', 'spiralengine'); ?></h4>
                <div class="spiralengine-category-chart">
                    <?php
                    arsort($categories);
                    $top_categories = array_slice($categories, 0, 5, true);
                    $category_labels = [
                        'work' => __('Work/Career', 'spiralengine'),
                        'relationships' => __('Relationships', 'spiralengine'),
                        'health' => __('Health', 'spiralengine'),
                        'finances' => __('Finances', 'spiralengine'),
                        'future' => __('Future/Planning', 'spiralengine'),
                        'past' => __('Past Events', 'spiralengine'),
                        'social' => __('Social Situations', 'spiralengine'),
                        'decisions' => __('Decision Making', 'spiralengine'),
                        'performance' => __('Performance', 'spiralengine'),
                        'other' => __('Other', 'spiralengine')
                    ];
                    
                    foreach ($top_categories as $category => $count):
                        $percentage = ($count / array_sum($categories)) * 100;
                    ?>
                    <div class="category-bar">
                        <span class="category-label"><?php echo esc_html($category_labels[$category] ?? $category); ?></span>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: <?php echo $percentage; ?>%"></div>
                        </div>
                        <span class="category-count"><?php echo $count; ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($coping_strategies)): ?>
            <div class="spiralengine-chart-section">
                <h4><?php _e('Most Effective Coping Strategies', 'spiralengine'); ?></h4>
                <div class="spiralengine-strategy-list">
                    <?php
                    arsort($coping_strategies);
                    $strategy_labels = [
                        'breathing' => __('Deep breathing', 'spiralengine'),
                        'exercise' => __('Physical exercise', 'spiralengine'),
                        'distraction' => __('Distraction activity', 'spiralengine'),
                        'talking' => __('Talking to someone', 'spiralengine'),
                        'writing' => __('Writing/Journaling', 'spiralengine'),
                        'meditation' => __('Meditation', 'spiralengine'),
                        'problem-solving' => __('Problem-solving', 'spiralengine'),
                        'acceptance' => __('Acceptance', 'spiralengine')
                    ];
                    
                    foreach (array_slice($coping_strategies, 0, 5, true) as $strategy => $count):
                    ?>
                    <div class="strategy-item">
                        <span class="dashicons dashicons-yes-alt"></span>
                        <span class="strategy-name"><?php echo esc_html($strategy_labels[$strategy] ?? $strategy); ?></span>
                        <span class="strategy-count">(<?php echo $count; ?>x)</span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($triggers)): ?>
            <div class="spiralengine-chart-section">
                <h4><?php _e('Common Triggers', 'spiralengine'); ?></h4>
                <div class="spiralengine-trigger-cloud">
                    <?php
                    $trigger_counts = array_count_values($triggers);
                    arsort($trigger_counts);
                    $top_triggers = array_slice($trigger_counts, 0, 10, true);
                    
                    foreach ($top_triggers as $trigger => $count):
                        $size_class = 'small';
                        if ($count > 5) $size_class = 'medium';
                        if ($count > 10) $size_class = 'large';
                    ?>
                    <span class="trigger-tag trigger-<?php echo $size_class; ?>">
                        <?php echo esc_html($trigger); ?> (<?php echo $count; ?>)
                    </span>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="spiralengine-analytics-actions">
                <a href="#" class="button spiralengine-export-data" data-widget="<?php echo esc_attr($this->id); ?>">
                    <span class="dashicons dashicons-download"></span>
                    <?php _e('Export Data', 'spiralengine'); ?>
                </a>
            </div>
        </div>
        <?php
        
        return ob_get_clean();
    }
    
    /**
     * Get widget data schema
     *
     * @since 1.0.0
     * @return array Schema definition
     */
    public function get_schema() {
        return [
            'thought' => [
                'type' => 'string',
                'label' => __('Overthinking Description', 'spiralengine'),
                'required' => true
            ],
            'severity' => [
                'type' => 'integer',
                'label' => __('Severity', 'spiralengine'),
                'min' => 1,
                'max' => 10,
                'required' => true
            ],
            'duration' => [
                'type' => 'string',
                'label' => __('Duration', 'spiralengine'),
                'enum' => [
                    'few-minutes', '15-30-minutes', '30-60-minutes',
                    '1-2-hours', '2-4-hours', 'more-than-4',
                    'all-day', 'multiple-days'
                ]
            ],
            'trigger' => [
                'type' => 'string',
                'label' => __('Trigger', 'spiralengine')
            ],
            'category' => [
                'type' => 'string',
                'label' => __('Category', 'spiralengine'),
                'enum' => [
                    'work', 'relationships', 'health', 'finances',
                    'future', 'past', 'social', 'decisions',
                    'performance', 'other'
                ]
            ],
            'symptoms' => [
                'type' => 'array',
                'label' => __('Physical Symptoms', 'spiralengine'),
                'items' => [
                    'type' => 'string',
                    'enum' => [
                        'tension', 'headache', 'fatigue', 'restlessness',
                        'stomach', 'heart-racing', 'sweating', 'breathing'
                    ]
                ]
            ],
            'distortions' => [
                'type' => 'array',
                'label' => __('Cognitive Distortions', 'spiralengine'),
                'items' => [
                    'type' => 'string',
                    'enum' => [
                        'all-or-nothing', 'overgeneralization', 'mental-filter',
                        'disqualifying', 'jumping', 'magnification',
                        'emotional', 'should', 'labeling', 'personalization'
                    ]
                ]
            ],
            'reframe' => [
                'type' => 'string',
                'label' => __('Reframed Thoughts', 'spiralengine')
            ],
            'coping_strategy' => [
                'type' => 'string',
                'label' => __('Coping Strategy', 'spiralengine'),
                'enum' => [
                    'breathing', 'exercise', 'distraction', 'talking',
                    'writing', 'meditation', 'problem-solving',
                    'acceptance', 'none-yet'
                ]
            ],
            'notes' => [
                'type' => 'string',
                'label' => __('Additional Notes', 'spiralengine')
            ],
            'request_ai_analysis' => [
                'type' => 'boolean',
                'label' => __('AI Analysis Requested', 'spiralengine')
            ]
        ];
    }
}

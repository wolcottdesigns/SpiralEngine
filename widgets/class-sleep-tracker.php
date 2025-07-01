<?php
/**
 * Sleep Tracker Widget
 *
 * @package     SpiralEngine
 * @subpackage  Widgets
 * @file        widgets/class-sleep-tracker.php
 * @since       1.0.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Sleep Tracker Widget Class
 *
 * Tracks sleep patterns, quality, and influencing factors
 *
 * @since 1.0.0
 */
class SpiralEngine_Widget_Sleep_Tracker extends SpiralEngine_Widget {
    
    /**
     * Initialize widget
     *
     * @since 1.0.0
     * @return void
     */
    protected function init() {
        $this->id = 'sleep-tracker';
        $this->name = __('Sleep Tracker', 'spiralengine');
        $this->description = __('Track sleep patterns, quality, and identify factors affecting your rest', 'spiralengine');
        $this->icon = 'dashicons-moon';
        $this->min_tier = 'free';
        
        // Additional capabilities
        $this->capabilities = [
            'sleep_analysis',
            'dream_journal',
            'sleep_debt_tracking',
            'circadian_insights'
        ];
        
        // Default settings
        $this->settings = [
            'default_bedtime' => '22:00',
            'default_wake_time' => '07:00',
            'track_dreams' => true,
            'track_naps' => true,
            'sleep_goal_hours' => 8,
            'reminder_bedtime' => true,
            'reminder_time' => '21:30'
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
            'entry_type' => 'night', // 'night' or 'nap'
            'show_insights' => true
        ]);
        
        $user_id = get_current_user_id();
        $membership = new SpiralEngine_Membership($user_id);
        $tier = $membership->get_tier();
        
        ob_start();
        ?>
        <div class="spiralengine-widget-form spiralengine-sleep-form" data-widget="<?php echo esc_attr($this->id); ?>">
            <form id="spiralengine-sleep-form" class="spiralengine-episode-form">
                <?php wp_nonce_field('spiralengine_' . $this->id . '_nonce', 'nonce'); ?>
                
                <!-- Entry Type Toggle -->
                <div class="spiralengine-entry-type-toggle">
                    <label>
                        <input type="radio" name="data[sleep_type]" value="night" 
                               <?php checked($args['entry_type'], 'night'); ?>>
                        <span><?php _e('Night Sleep', 'spiralengine'); ?></span>
                    </label>
                    <label>
                        <input type="radio" name="data[sleep_type]" value="nap" 
                               <?php checked($args['entry_type'], 'nap'); ?>>
                        <span><?php _e('Nap', 'spiralengine'); ?></span>
                    </label>
                </div>
                
                <div class="spiralengine-form-section">
                    <h4><?php _e('Sleep Times', 'spiralengine'); ?></h4>
                    
                    <div class="spiralengine-time-inputs">
                        <?php echo $this->render_field('time', [
                            'id' => 'sleep_bedtime',
                            'name' => 'data[bedtime]',
                            'label' => __('Bedtime', 'spiralengine'),
                            'value' => $this->get_setting('default_bedtime'),
                            'required' => true
                        ]); ?>
                        
                        <?php echo $this->render_field('time', [
                            'id' => 'sleep_waketime',
                            'name' => 'data[wake_time]',
                            'label' => __('Wake time', 'spiralengine'),
                            'value' => $this->get_setting('default_wake_time'),
                            'required' => true
                        ]); ?>
                    </div>
                    
                    <!-- Sleep Duration Display -->
                    <div class="spiralengine-sleep-duration">
                        <span class="duration-label"><?php _e('Total sleep:', 'spiralengine'); ?></span>
                        <span class="duration-value" id="sleep-duration-display">--</span>
                    </div>
                    
                    <!-- Time to Fall Asleep -->
                    <?php echo $this->render_field('select', [
                        'id' => 'sleep_fall_asleep_time',
                        'name' => 'data[fall_asleep_time]',
                        'label' => __('Time to fall asleep', 'spiralengine'),
                        'options' => [
                            '' => __('Select...', 'spiralengine'),
                            '0-5' => __('0-5 minutes', 'spiralengine'),
                            '5-15' => __('5-15 minutes', 'spiralengine'),
                            '15-30' => __('15-30 minutes', 'spiralengine'),
                            '30-60' => __('30-60 minutes', 'spiralengine'),
                            '60+' => __('Over an hour', 'spiralengine')
                        ]
                    ]); ?>
                    
                    <!-- Wake-ups During Night -->
                    <?php echo $this->render_field('number', [
                        'id' => 'sleep_wake_ups',
                        'name' => 'data[wake_ups]',
                        'label' => __('Times woken up during sleep', 'spiralengine'),
                        'value' => '0',
                        'min' => '0',
                        'max' => '20'
                    ]); ?>
                </div>
                
                <div class="spiralengine-form-section">
                    <h4><?php _e('Sleep Quality', 'spiralengine'); ?></h4>
                    
                    <!-- Overall Quality Rating -->
                    <div class="spiralengine-quality-rating">
                        <label><?php _e('Rate your sleep quality', 'spiralengine'); ?></label>
                        <div class="quality-stars">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                            <label class="star-rating">
                                <input type="radio" name="data[quality_rating]" value="<?php echo $i; ?>" required>
                                <span class="star" data-rating="<?php echo $i; ?>">★</span>
                            </label>
                            <?php endfor; ?>
                        </div>
                    </div>
                    
                    <!-- How Refreshed -->
                    <?php echo $this->render_field('range', [
                        'id' => 'sleep_refreshed',
                        'name' => 'data[refreshed_level]',
                        'label' => __('How refreshed do you feel?', 'spiralengine'),
                        'value' => '5',
                        'min' => '1',
                        'max' => '10',
                        'step' => '1',
                        'description' => __('1 = Exhausted, 10 = Fully refreshed', 'spiralengine')
                    ]); ?>
                    
                    <?php if ($args['mode'] === 'full'): ?>
                    <!-- Sleep Issues -->
                    <div class="spiralengine-field-group">
                        <label><?php _e('Sleep issues experienced', 'spiralengine'); ?></label>
                        <div class="spiralengine-checkbox-group">
                            <?php
                            $issues = [
                                'difficulty-falling' => __('Difficulty falling asleep', 'spiralengine'),
                                'frequent-waking' => __('Frequent waking', 'spiralengine'),
                                'early-waking' => __('Waking too early', 'spiralengine'),
                                'restless' => __('Restless/Tossing', 'spiralengine'),
                                'nightmares' => __('Nightmares', 'spiralengine'),
                                'snoring' => __('Snoring (reported)', 'spiralengine'),
                                'breathing-issues' => __('Breathing issues', 'spiralengine'),
                                'leg-movements' => __('Leg movements', 'spiralengine'),
                                'too-hot' => __('Too hot', 'spiralengine'),
                                'too-cold' => __('Too cold', 'spiralengine'),
                                'noise' => __('Noise disturbance', 'spiralengine'),
                                'light' => __('Light disturbance', 'spiralengine')
                            ];
                            
                            foreach ($issues as $value => $label):
                            ?>
                            <label>
                                <input type="checkbox" name="data[issues][]" value="<?php echo esc_attr($value); ?>">
                                <?php echo esc_html($label); ?>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                
                <?php if (in_array($tier, ['silver', 'gold', 'platinum'])): ?>
                <div class="spiralengine-form-section">
                    <h4><?php _e('Factors & Environment', 'spiralengine'); ?></h4>
                    
                    <!-- Pre-Sleep Activities -->
                    <div class="spiralengine-field-group">
                        <label><?php _e('Activities before bed', 'spiralengine'); ?></label>
                        <div class="spiralengine-checkbox-group">
                            <?php
                            $activities = [
                                'screen-time' => __('Screen time', 'spiralengine'),
                                'reading' => __('Reading', 'spiralengine'),
                                'exercise' => __('Exercise', 'spiralengine'),
                                'meal' => __('Large meal', 'spiralengine'),
                                'alcohol' => __('Alcohol', 'spiralengine'),
                                'caffeine' => __('Caffeine', 'spiralengine'),
                                'meditation' => __('Meditation/Relaxation', 'spiralengine'),
                                'bath' => __('Bath/Shower', 'spiralengine'),
                                'work' => __('Working', 'spiralengine'),
                                'socializing' => __('Socializing', 'spiralengine')
                            ];
                            
                            foreach ($activities as $value => $label):
                            ?>
                            <label>
                                <input type="checkbox" name="data[pre_sleep_activities][]" value="<?php echo esc_attr($value); ?>">
                                <?php echo esc_html($label); ?>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <!-- Sleep Environment -->
                    <?php echo $this->render_field('select', [
                        'id' => 'sleep_environment',
                        'name' => 'data[environment_quality]',
                        'label' => __('Sleep environment quality', 'spiralengine'),
                        'options' => [
                            '' => __('Not tracked', 'spiralengine'),
                            'excellent' => __('Excellent', 'spiralengine'),
                            'good' => __('Good', 'spiralengine'),
                            'fair' => __('Fair', 'spiralengine'),
                            'poor' => __('Poor', 'spiralengine'),
                            'terrible' => __('Terrible', 'spiralengine')
                        ]
                    ]); ?>
                    
                    <!-- Stress Level Before Bed -->
                    <?php echo $this->render_field('range', [
                        'id' => 'sleep_stress',
                        'name' => 'data[stress_level]',
                        'label' => __('Stress level before bed', 'spiralengine'),
                        'value' => '5',
                        'min' => '1',
                        'max' => '10',
                        'step' => '1'
                    ]); ?>
                </div>
                <?php endif; ?>
                
                <?php if ($this->get_setting('track_dreams') && in_array($tier, ['gold', 'platinum'])): ?>
                <div class="spiralengine-form-section">
                    <h4><?php _e('Dreams & Sleep Content', 'spiralengine'); ?></h4>
                    
                    <?php echo $this->render_field('select', [
                        'id' => 'sleep_dream_recall',
                        'name' => 'data[dream_recall]',
                        'label' => __('Dream recall', 'spiralengine'),
                        'options' => [
                            '' => __('No dreams remembered', 'spiralengine'),
                            'vague' => __('Vague memories', 'spiralengine'),
                            'partial' => __('Partial recall', 'spiralengine'),
                            'clear' => __('Clear recall', 'spiralengine'),
                            'vivid' => __('Very vivid dreams', 'spiralengine')
                        ]
                    ]); ?>
                    
                    <?php echo $this->render_field('select', [
                        'id' => 'sleep_dream_type',
                        'name' => 'data[dream_type]',
                        'label' => __('Dream type', 'spiralengine'),
                        'options' => [
                            '' => __('Not applicable', 'spiralengine'),
                            'pleasant' => __('Pleasant', 'spiralengine'),
                            'neutral' => __('Neutral', 'spiralengine'),
                            'stressful' => __('Stressful', 'spiralengine'),
                            'nightmare' => __('Nightmare', 'spiralengine'),
                            'lucid' => __('Lucid dream', 'spiralengine'),
                            'recurring' => __('Recurring dream', 'spiralengine')
                        ]
                    ]); ?>
                    
                    <?php echo $this->render_field('textarea', [
                        'id' => 'sleep_dream_notes',
                        'name' => 'data[dream_notes]',
                        'label' => __('Dream notes (optional)', 'spiralengine'),
                        'placeholder' => __('Describe your dreams if you wish...', 'spiralengine'),
                        'rows' => 3
                    ]); ?>
                </div>
                <?php endif; ?>
                
                <?php if ($tier === 'platinum'): ?>
                <div class="spiralengine-form-section">
                    <h4><?php _e('Advanced Tracking', 'spiralengine'); ?></h4>
                    
                    <!-- Sleep Aids Used -->
                    <div class="spiralengine-field-group">
                        <label><?php _e('Sleep aids used', 'spiralengine'); ?></label>
                        <div class="spiralengine-checkbox-group">
                            <?php
                            $aids = [
                                'melatonin' => __('Melatonin', 'spiralengine'),
                                'prescription' => __('Prescription medication', 'spiralengine'),
                                'herbal' => __('Herbal supplements', 'spiralengine'),
                                'white-noise' => __('White noise', 'spiralengine'),
                                'weighted-blanket' => __('Weighted blanket', 'spiralengine'),
                                'sleep-mask' => __('Sleep mask', 'spiralengine'),
                                'earplugs' => __('Earplugs', 'spiralengine'),
                                'aromatherapy' => __('Aromatherapy', 'spiralengine'),
                                'cbd' => __('CBD', 'spiralengine'),
                                'other' => __('Other', 'spiralengine')
                            ];
                            
                            foreach ($aids as $value => $label):
                            ?>
                            <label>
                                <input type="checkbox" name="data[sleep_aids][]" value="<?php echo esc_attr($value); ?>">
                                <?php echo esc_html($label); ?>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <!-- Wearable Data -->
                    <?php echo $this->render_field('checkbox', [
                        'id' => 'sleep_wearable_data',
                        'name' => 'data[has_wearable_data]',
                        'label' => __('I have sleep data from a wearable device', 'spiralengine'),
                        'value' => '1'
                    ]); ?>
                    
                    <div class="spiralengine-wearable-fields" style="display: none;">
                        <?php echo $this->render_field('number', [
                            'id' => 'sleep_deep_minutes',
                            'name' => 'data[deep_sleep_minutes]',
                            'label' => __('Deep sleep (minutes)', 'spiralengine'),
                            'min' => '0',
                            'max' => '600'
                        ]); ?>
                        
                        <?php echo $this->render_field('number', [
                            'id' => 'sleep_rem_minutes',
                            'name' => 'data[rem_sleep_minutes]',
                            'label' => __('REM sleep (minutes)', 'spiralengine'),
                            'min' => '0',
                            'max' => '600'
                        ]); ?>
                        
                        <?php echo $this->render_field('number', [
                            'id' => 'sleep_hrv',
                            'name' => 'data[hrv_average]',
                            'label' => __('Average HRV', 'spiralengine'),
                            'min' => '0',
                            'max' => '200'
                        ]); ?>
                    </div>
                    
                    <!-- AI Analysis -->
                    <?php echo $this->render_field('checkbox', [
                        'id' => 'sleep_ai_analysis',
                        'name' => 'data[request_ai_analysis]',
                        'label' => __('Generate AI sleep quality analysis and recommendations', 'spiralengine'),
                        'value' => '1'
                    ]); ?>
                </div>
                <?php endif; ?>
                
                <!-- Additional Notes -->
                <?php echo $this->render_field('textarea', [
                    'id' => 'sleep_notes',
                    'name' => 'data[notes]',
                    'label' => __('Additional notes', 'spiralengine'),
                    'placeholder' => __('Any other observations about your sleep...', 'spiralengine'),
                    'rows' => 2
                ]); ?>
                
                <div class="spiralengine-form-actions">
                    <button type="submit" class="button button-primary spiralengine-save-episode">
                        <span class="dashicons dashicons-saved"></span>
                        <?php _e('Save Sleep Entry', 'spiralengine'); ?>
                    </button>
                    
                    <?php if ($args['show_insights']): ?>
                    <button type="button" class="button spiralengine-sleep-insights">
                        <span class="dashicons dashicons-chart-line"></span>
                        <?php _e('View Sleep Insights', 'spiralengine'); ?>
                    </button>
                    <?php endif; ?>
                </div>
            </form>
            
            <?php if ($args['show_insights'] && $args['mode'] === 'full'): ?>
            <div class="spiralengine-sleep-tips">
                <h4><?php _e('Sleep Tips', 'spiralengine'); ?></h4>
                <div class="tips-container">
                    <?php
                    $tips = [
                        __('Keep a consistent sleep schedule, even on weekends', 'spiralengine'),
                        __('Create a relaxing bedtime routine', 'spiralengine'),
                        __('Keep your bedroom cool, dark, and quiet', 'spiralengine'),
                        __('Avoid screens 1-2 hours before bed', 'spiralengine'),
                        __('Limit caffeine after 2 PM', 'spiralengine'),
                        __('Get sunlight exposure during the day', 'spiralengine')
                    ];
                    
                    $daily_tip = $tips[date('j') % count($tips)];
                    ?>
                    <div class="tip-of-day">
                        <span class="dashicons dashicons-lightbulb"></span>
                        <p><?php echo esc_html($daily_tip); ?></p>
                    </div>
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
        
        // Validate sleep type
        $validated['sleep_type'] = (!empty($data['sleep_type']) && $data['sleep_type'] === 'nap') ? 'nap' : 'night';
        
        // Validate times
        if (empty($data['bedtime'])) {
            $errors['bedtime'] = __('Please enter your bedtime.', 'spiralengine');
        } else {
            $validated['bedtime'] = sanitize_text_field($data['bedtime']);
        }
        
        if (empty($data['wake_time'])) {
            $errors['wake_time'] = __('Please enter your wake time.', 'spiralengine');
        } else {
            $validated['wake_time'] = sanitize_text_field($data['wake_time']);
        }
        
        // Calculate duration if both times are valid
        if (!empty($validated['bedtime']) && !empty($validated['wake_time'])) {
            $duration = $this->calculate_sleep_duration($validated['bedtime'], $validated['wake_time']);
            $validated['duration_minutes'] = $duration;
            $validated['duration_hours'] = round($duration / 60, 1);
        }
        
        // Validate quality rating
        $quality = isset($data['quality_rating']) ? intval($data['quality_rating']) : 0;
        if ($quality < 1 || $quality > 5) {
            $errors['quality_rating'] = __('Please rate your sleep quality.', 'spiralengine');
        } else {
            $validated['quality_rating'] = $quality;
            // Convert to severity scale (inverse for sleep - good sleep = low severity)
            $validated['severity'] = 11 - ($quality * 2);
        }
        
        // Validate refreshed level
        if (isset($data['refreshed_level'])) {
            $refreshed = intval($data['refreshed_level']);
            if ($refreshed >= 1 && $refreshed <= 10) {
                $validated['refreshed_level'] = $refreshed;
            }
        }
        
        // Validate fall asleep time
        if (!empty($data['fall_asleep_time'])) {
            $valid_times = ['0-5', '5-15', '15-30', '30-60', '60+'];
            if (in_array($data['fall_asleep_time'], $valid_times)) {
                $validated['fall_asleep_time'] = $data['fall_asleep_time'];
            }
        }
        
        // Validate wake ups
        if (isset($data['wake_ups'])) {
            $validated['wake_ups'] = max(0, intval($data['wake_ups']));
        }
        
        // Validate issues
        if (!empty($data['issues']) && is_array($data['issues'])) {
            $valid_issues = [
                'difficulty-falling', 'frequent-waking', 'early-waking', 'restless',
                'nightmares', 'snoring', 'breathing-issues', 'leg-movements',
                'too-hot', 'too-cold', 'noise', 'light'
            ];
            $validated['issues'] = array_intersect($data['issues'], $valid_issues);
        }
        
        // Validate pre-sleep activities
        if (!empty($data['pre_sleep_activities']) && is_array($data['pre_sleep_activities'])) {
            $valid_activities = [
                'screen-time', 'reading', 'exercise', 'meal', 'alcohol',
                'caffeine', 'meditation', 'bath', 'work', 'socializing'
            ];
            $validated['pre_sleep_activities'] = array_intersect($data['pre_sleep_activities'], $valid_activities);
        }
        
        // Validate environment quality
        if (!empty($data['environment_quality'])) {
            $valid_quality = ['excellent', 'good', 'fair', 'poor', 'terrible'];
            if (in_array($data['environment_quality'], $valid_quality)) {
                $validated['environment_quality'] = $data['environment_quality'];
            }
        }
        
        // Validate stress level
        if (isset($data['stress_level'])) {
            $stress = intval($data['stress_level']);
            if ($stress >= 1 && $stress <= 10) {
                $validated['stress_level'] = $stress;
            }
        }
        
        // Validate dream data
        if (!empty($data['dream_recall'])) {
            $valid_recall = ['vague', 'partial', 'clear', 'vivid'];
            if (in_array($data['dream_recall'], $valid_recall)) {
                $validated['dream_recall'] = $data['dream_recall'];
            }
        }
        
        if (!empty($data['dream_type'])) {
            $valid_types = ['pleasant', 'neutral', 'stressful', 'nightmare', 'lucid', 'recurring'];
            if (in_array($data['dream_type'], $valid_types)) {
                $validated['dream_type'] = $data['dream_type'];
            }
        }
        
        if (!empty($data['dream_notes'])) {
            $validated['dream_notes'] = sanitize_textarea_field($data['dream_notes']);
        }
        
        // Validate sleep aids
        if (!empty($data['sleep_aids']) && is_array($data['sleep_aids'])) {
            $valid_aids = [
                'melatonin', 'prescription', 'herbal', 'white-noise',
                'weighted-blanket', 'sleep-mask', 'earplugs',
                'aromatherapy', 'cbd', 'other'
            ];
            $validated['sleep_aids'] = array_intersect($data['sleep_aids'], $valid_aids);
        }
        
        // Validate wearable data
        $validated['has_wearable_data'] = !empty($data['has_wearable_data']);
        
        if ($validated['has_wearable_data']) {
            if (isset($data['deep_sleep_minutes'])) {
                $validated['deep_sleep_minutes'] = max(0, intval($data['deep_sleep_minutes']));
            }
            if (isset($data['rem_sleep_minutes'])) {
                $validated['rem_sleep_minutes'] = max(0, intval($data['rem_sleep_minutes']));
            }
            if (isset($data['hrv_average'])) {
                $validated['hrv_average'] = max(0, intval($data['hrv_average']));
            }
        }
        
        // Validate notes
        if (!empty($data['notes'])) {
            $validated['notes'] = sanitize_textarea_field($data['notes']);
        }
        
        // Validate AI analysis request
        $validated['request_ai_analysis'] = !empty($data['request_ai_analysis']);
        
        // Return errors if any
        if (!empty($errors)) {
            return new WP_Error('validation_failed', __('Please fix the errors below.', 'spiralengine'), $errors);
        }
        
        return $validated;
    }
    
    /**
     * Calculate sleep duration in minutes
     *
     * @since 1.0.0
     * @param string $bedtime Bedtime
     * @param string $wake_time Wake time
     * @return int Duration in minutes
     */
    private function calculate_sleep_duration($bedtime, $wake_time) {
        $bed_timestamp = strtotime($bedtime);
        $wake_timestamp = strtotime($wake_time);
        
        // If wake time is before bed time, assume it's the next day
        if ($wake_timestamp <= $bed_timestamp) {
            $wake_timestamp += 86400; // Add 24 hours
        }
        
        $duration_seconds = $wake_timestamp - $bed_timestamp;
        return round($duration_seconds / 60);
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
        // Add sleep quality score
        $quality_score = 0;
        
        // Base score from quality rating (0-40 points)
        $quality_score += $data['quality_rating'] * 8;
        
        // Refreshed level (0-20 points)
        if (isset($data['refreshed_level'])) {
            $quality_score += $data['refreshed_level'] * 2;
        }
        
        // Duration impact (0-20 points)
        if (isset($data['duration_hours'])) {
            if ($data['duration_hours'] >= 7 && $data['duration_hours'] <= 9) {
                $quality_score += 20;
            } elseif ($data['duration_hours'] >= 6 && $data['duration_hours'] <= 10) {
                $quality_score += 10;
            }
        }
        
        // Wake ups penalty (0-10 points)
        if (isset($data['wake_ups'])) {
            $quality_score -= min(10, $data['wake_ups'] * 2);
        }
        
        // Issues penalty (0-10 points)
        if (!empty($data['issues'])) {
            $quality_score -= min(10, count($data['issues']) * 2);
        }
        
        $metadata['sleep_quality_score'] = max(0, min(100, $quality_score));
        
        // Add sleep efficiency
        if (isset($data['duration_minutes']) && !empty($data['fall_asleep_time'])) {
            $fall_asleep_minutes = 0;
            switch ($data['fall_asleep_time']) {
                case '0-5': $fall_asleep_minutes = 3; break;
                case '5-15': $fall_asleep_minutes = 10; break;
                case '15-30': $fall_asleep_minutes = 22; break;
                case '30-60': $fall_asleep_minutes = 45; break;
                case '60+': $fall_asleep_minutes = 75; break;
            }
            
            $total_time_in_bed = $data['duration_minutes'] + $fall_asleep_minutes;
            $metadata['sleep_efficiency'] = round(($data['duration_minutes'] / $total_time_in_bed) * 100);
        }
        
        // Track negative factors
        $negative_factors = [];
        if (!empty($data['pre_sleep_activities'])) {
            $bad_activities = ['screen-time', 'exercise', 'meal', 'alcohol', 'caffeine', 'work'];
            $negative_factors = array_intersect($data['pre_sleep_activities'], $bad_activities);
        }
        $metadata['negative_factor_count'] = count($negative_factors);
        
        // Add day of week
        $metadata['day_of_week'] = strtolower(date('l'));
        $metadata['is_weekend'] = in_array(date('w'), [0, 6]);
        
        // Track if below recommended hours
        $metadata['sleep_debt'] = isset($data['duration_hours']) && $data['duration_hours'] < 7;
        
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
        $days = 30;
        
        // Get sleep data
        $sleep_data = $wpdb->get_results($wpdb->prepare(
            "SELECT data, metadata, created_at FROM {$table} 
            WHERE user_id = %d 
            AND widget_id = %s 
            AND created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)
            ORDER BY created_at DESC",
            $user_id, $this->id, $days
        ));
        
        // Process analytics
        $total_nights = 0;
        $total_naps = 0;
        $total_sleep_hours = 0;
        $quality_ratings = [];
        $sleep_times = [];
        $wake_times = [];
        $issues_count = [];
        $weekday_avg = ['total' => 0, 'count' => 0];
        $weekend_avg = ['total' => 0, 'count' => 0];
        $sleep_debt_days = 0;
        
        foreach ($sleep_data as $episode) {
            $data = json_decode($episode->data, true);
            $metadata = json_decode($episode->metadata, true);
            
            if ($data['sleep_type'] === 'nap') {
                $total_naps++;
                continue;
            }
            
            $total_nights++;
            
            // Track sleep hours
            if (isset($data['duration_hours'])) {
                $total_sleep_hours += $data['duration_hours'];
                
                if ($metadata['is_weekend']) {
                    $weekend_avg['total'] += $data['duration_hours'];
                    $weekend_avg['count']++;
                } else {
                    $weekday_avg['total'] += $data['duration_hours'];
                    $weekday_avg['count']++;
                }
                
                if ($data['duration_hours'] < 7) {
                    $sleep_debt_days++;
                }
            }
            
            // Track quality
            if (isset($data['quality_rating'])) {
                $quality_ratings[] = $data['quality_rating'];
            }
            
            // Track sleep/wake times
            if (!empty($data['bedtime'])) {
                $sleep_times[] = strtotime($data['bedtime']);
            }
            if (!empty($data['wake_time'])) {
                $wake_times[] = strtotime($data['wake_time']);
            }
            
            // Track issues
            if (!empty($data['issues'])) {
                foreach ($data['issues'] as $issue) {
                    $issues_count[$issue] = ($issues_count[$issue] ?? 0) + 1;
                }
            }
        }
        
        $avg_sleep_hours = $total_nights > 0 ? $total_sleep_hours / $total_nights : 0;
        $avg_quality = !empty($quality_ratings) ? array_sum($quality_ratings) / count($quality_ratings) : 0;
        $weekday_sleep = $weekday_avg['count'] > 0 ? $weekday_avg['total'] / $weekday_avg['count'] : 0;
        $weekend_sleep = $weekend_avg['count'] > 0 ? $weekend_avg['total'] / $weekend_avg['count'] : 0;
        
        // Calculate average bed/wake times
        $avg_bedtime = !empty($sleep_times) ? date('H:i', array_sum($sleep_times) / count($sleep_times)) : '--:--';
        $avg_waketime = !empty($wake_times) ? date('H:i', array_sum($wake_times) / count($wake_times)) : '--:--';
        
        ob_start();
        ?>
        <div class="spiralengine-widget-analytics spiralengine-sleep-analytics">
            <h3><?php echo esc_html($this->name); ?> <?php _e('Analytics', 'spiralengine'); ?></h3>
            
            <div class="spiralengine-stats-grid">
                <div class="stat-card">
                    <span class="stat-value"><?php echo number_format($avg_sleep_hours, 1); ?>h</span>
                    <span class="stat-label"><?php _e('Average Sleep', 'spiralengine'); ?></span>
                </div>
                
                <div class="stat-card">
                    <span class="stat-value"><?php echo number_format($avg_quality, 1); ?>/5</span>
                    <span class="stat-label"><?php _e('Average Quality', 'spiralengine'); ?></span>
                </div>
                
                <div class="stat-card">
                    <span class="stat-value"><?php echo $avg_bedtime; ?></span>
                    <span class="stat-label"><?php _e('Average Bedtime', 'spiralengine'); ?></span>
                </div>
                
                <div class="stat-card">
                    <span class="stat-value"><?php echo $avg_waketime; ?></span>
                    <span class="stat-label"><?php _e('Average Wake Time', 'spiralengine'); ?></span>
                </div>
            </div>
            
            <?php if ($weekday_avg['count'] > 0 && $weekend_avg['count'] > 0): ?>
            <div class="spiralengine-chart-section">
                <h4><?php _e('Weekday vs Weekend Sleep', 'spiralengine'); ?></h4>
                <div class="spiralengine-comparison-bars">
                    <div class="comparison-item">
                        <span class="comparison-label"><?php _e('Weekdays', 'spiralengine'); ?></span>
                        <div class="comparison-bar">
                            <div class="bar-fill weekday" style="width: <?php echo ($weekday_sleep / 12) * 100; ?>%"></div>
                            <span class="bar-value"><?php echo number_format($weekday_sleep, 1); ?>h</span>
                        </div>
                    </div>
                    <div class="comparison-item">
                        <span class="comparison-label"><?php _e('Weekends', 'spiralengine'); ?></span>
                        <div class="comparison-bar">
                            <div class="bar-fill weekend" style="width: <?php echo ($weekend_sleep / 12) * 100; ?>%"></div>
                            <span class="bar-value"><?php echo number_format($weekend_sleep, 1); ?>h</span>
                        </div>
                    </div>
                </div>
                
                <?php if (abs($weekend_sleep - $weekday_sleep) > 1): ?>
                <p class="spiralengine-insight">
                    <span class="dashicons dashicons-info"></span>
                    <?php 
                    printf(
                        __('You sleep %0.1f hours %s on weekends. Try to maintain consistent sleep times.', 'spiralengine'),
                        abs($weekend_sleep - $weekday_sleep),
                        $weekend_sleep > $weekday_sleep ? __('more', 'spiralengine') : __('less', 'spiralengine')
                    ); 
                    ?>
                </p>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($issues_count)): ?>
            <div class="spiralengine-chart-section">
                <h4><?php _e('Common Sleep Issues', 'spiralengine'); ?></h4>
                <div class="spiralengine-issues-grid">
                    <?php
                    arsort($issues_count);
                    $issue_labels = [
                        'difficulty-falling' => __('Difficulty falling asleep', 'spiralengine'),
                        'frequent-waking' => __('Frequent waking', 'spiralengine'),
                        'early-waking' => __('Waking too early', 'spiralengine'),
                        'restless' => __('Restless/Tossing', 'spiralengine'),
                        'nightmares' => __('Nightmares', 'spiralengine'),
                        'snoring' => __('Snoring', 'spiralengine'),
                        'breathing-issues' => __('Breathing issues', 'spiralengine'),
                        'leg-movements' => __('Leg movements', 'spiralengine'),
                        'too-hot' => __('Too hot', 'spiralengine'),
                        'too-cold' => __('Too cold', 'spiralengine'),
                        'noise' => __('Noise disturbance', 'spiralengine'),
                        'light' => __('Light disturbance', 'spiralengine')
                    ];
                    
                    foreach (array_slice($issues_count, 0, 6, true) as $issue => $count):
                        $percentage = ($count / $total_nights) * 100;
                    ?>
                    <div class="issue-card">
                        <span class="issue-name"><?php echo esc_html($issue_labels[$issue] ?? $issue); ?></span>
                        <span class="issue-frequency"><?php echo round($percentage); ?>%</span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="spiralengine-chart-section">
                <h4><?php _e('Sleep Insights', 'spiralengine'); ?></h4>
                <div class="spiralengine-insights-list">
                    <?php if ($sleep_debt_days > 0): ?>
                    <div class="insight-item warning">
                        <span class="dashicons dashicons-warning"></span>
                        <p><?php printf(__('You got less than 7 hours of sleep on %d out of %d nights (%d%%).', 'spiralengine'), 
                            $sleep_debt_days, $total_nights, round(($sleep_debt_days / $total_nights) * 100)); ?></p>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($avg_quality < 3): ?>
                    <div class="insight-item warning">
                        <span class="dashicons dashicons-flag"></span>
                        <p><?php _e('Your average sleep quality is below 3/5. Consider evaluating your sleep environment and habits.', 'spiralengine'); ?></p>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($total_naps > 5): ?>
                    <div class="insight-item info">
                        <span class="dashicons dashicons-clock"></span>
                        <p><?php printf(__('You took %d naps this month. Frequent napping might affect nighttime sleep.', 'spiralengine'), $total_naps); ?></p>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($avg_sleep_hours >= 7 && $avg_sleep_hours <= 9 && $avg_quality >= 4): ?>
                    <div class="insight-item success">
                        <span class="dashicons dashicons-awards"></span>
                        <p><?php _e('Great job! You\'re getting healthy amounts of quality sleep.', 'spiralengine'); ?></p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="spiralengine-chart-section">
                <h4><?php _e('Sleep Quality Calendar', 'spiralengine'); ?></h4>
                <div class="spiralengine-sleep-calendar" data-user="<?php echo $user_id; ?>">
                    <!-- Calendar will be populated via JavaScript -->
                    <div class="calendar-legend">
                        <span class="legend-item"><span class="quality-5"></span> <?php _e('Excellent', 'spiralengine'); ?></span>
                        <span class="legend-item"><span class="quality-4"></span> <?php _e('Good', 'spiralengine'); ?></span>
                        <span class="legend-item"><span class="quality-3"></span> <?php _e('Fair', 'spiralengine'); ?></span>
                        <span class="legend-item"><span class="quality-2"></span> <?php _e('Poor', 'spiralengine'); ?></span>
                        <span class="legend-item"><span class="quality-1"></span> <?php _e('Very Poor', 'spiralengine'); ?></span>
                    </div>
                </div>
            </div>
            
            <div class="spiralengine-analytics-actions">
                <a href="#" class="button spiralengine-export-sleep-data" data-widget="<?php echo esc_attr($this->id); ?>">
                    <span class="dashicons dashicons-download"></span>
                    <?php _e('Export Sleep Data', 'spiralengine'); ?>
                </a>
                
                <a href="#" class="button spiralengine-sleep-report" data-widget="<?php echo esc_attr($this->id); ?>">
                    <span class="dashicons dashicons-analytics"></span>
                    <?php _e('Generate Sleep Report', 'spiralengine'); ?>
                </a>
                
                <?php
                $membership = new SpiralEngine_Membership($user_id);
                if (in_array($membership->get_tier(), ['platinum'])):
                ?>
                <a href="#" class="button spiralengine-sleep-optimization" data-widget="<?php echo esc_attr($this->id); ?>">
                    <span class="dashicons dashicons-performance"></span>
                    <?php _e('Sleep Optimization Plan', 'spiralengine'); ?>
                </a>
                <?php endif; ?>
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
            'sleep_type' => [
                'type' => 'string',
                'label' => __('Sleep Type', 'spiralengine'),
                'enum' => ['night', 'nap'],
                'default' => 'night'
            ],
            'bedtime' => [
                'type' => 'string',
                'label' => __('Bedtime', 'spiralengine'),
                'format' => 'time',
                'required' => true
            ],
            'wake_time' => [
                'type' => 'string',
                'label' => __('Wake Time', 'spiralengine'),
                'format' => 'time',
                'required' => true
            ],
            'duration_minutes' => [
                'type' => 'integer',
                'label' => __('Duration (minutes)', 'spiralengine')
            ],
            'duration_hours' => [
                'type' => 'number',
                'label' => __('Duration (hours)', 'spiralengine')
            ],
            'quality_rating' => [
                'type' => 'integer',
                'label' => __('Quality Rating', 'spiralengine'),
                'min' => 1,
                'max' => 5,
                'required' => true
            ],
            'severity' => [
                'type' => 'integer',
                'label' => __('Severity', 'spiralengine'),
                'min' => 1,
                'max' => 10
            ],
            'refreshed_level' => [
                'type' => 'integer',
                'label' => __('Refreshed Level', 'spiralengine'),
                'min' => 1,
                'max' => 10
            ],
            'fall_asleep_time' => [
                'type' => 'string',
                'label' => __('Time to Fall Asleep', 'spiralengine'),
                'enum' => ['0-5', '5-15', '15-30', '30-60', '60+']
            ],
            'wake_ups' => [
                'type' => 'integer',
                'label' => __('Number of Wake-ups', 'spiralengine'),
                'min' => 0
            ],
            'issues' => [
                'type' => 'array',
                'label' => __('Sleep Issues', 'spiralengine'),
                'items' => [
                    'type' => 'string'
                ]
            ],
            'pre_sleep_activities' => [
                'type' => 'array',
                'label' => __('Pre-Sleep Activities', 'spiralengine'),
                'items' => [
                    'type' => 'string'
                ]
            ],
            'environment_quality' => [
                'type' => 'string',
                'label' => __('Environment Quality', 'spiralengine'),
                'enum' => ['excellent', 'good', 'fair', 'poor', 'terrible']
            ],
            'stress_level' => [
                'type' => 'integer',
                'label' => __('Stress Level', 'spiralengine'),
                'min' => 1,
                'max' => 10
            ],
            'dream_recall' => [
                'type' => 'string',
                'label' => __('Dream Recall', 'spiralengine'),
                'enum' => ['vague', 'partial', 'clear', 'vivid']
            ],
            'dream_type' => [
                'type' => 'string',
                'label' => __('Dream Type', 'spiralengine'),
                'enum' => ['pleasant', 'neutral', 'stressful', 'nightmare', 'lucid', 'recurring']
            ],
            'dream_notes' => [
                'type' => 'string',
                'label' => __('Dream Notes', 'spiralengine')
            ],
            'sleep_aids' => [
                'type' => 'array',
                'label' => __('Sleep Aids Used', 'spiralengine'),
                'items' => [
                    'type' => 'string'
                ]
            ],
            'has_wearable_data' => [
                'type' => 'boolean',
                'label' => __('Has Wearable Data', 'spiralengine')
            ],
            'deep_sleep_minutes' => [
                'type' => 'integer',
                'label' => __('Deep Sleep (minutes)', 'spiralengine'),
                'min' => 0
            ],
            'rem_sleep_minutes' => [
                'type' => 'integer',
                'label' => __('REM Sleep (minutes)', 'spiralengine'),
                'min' => 0
            ],
            'hrv_average' => [
                'type' => 'integer',
                'label' => __('Average HRV', 'spiralengine'),
                'min' => 0
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


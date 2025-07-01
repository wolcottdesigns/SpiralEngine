<?php
/**
 * Mood Tracker Widget
 *
 * @package     SpiralEngine
 * @subpackage  Widgets
 * @file        widgets/class-mood-tracker.php
 * @since       1.0.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Mood Tracker Widget Class
 *
 * Tracks daily mood patterns with environmental factors
 *
 * @since 1.0.0
 */
class SpiralEngine_Widget_Mood_Tracker extends SpiralEngine_Widget {
    
    /**
     * Initialize widget
     *
     * @since 1.0.0
     * @return void
     */
    protected function init() {
        $this->id = 'mood-tracker';
        $this->name = __('Mood Tracker', 'spiralengine');
        $this->description = __('Track your daily mood patterns and identify influencing factors', 'spiralengine');
        $this->icon = 'dashicons-smiley';
        $this->min_tier = 'free';
        
        // Additional capabilities
        $this->capabilities = [
            'mood_trends',
            'factor_analysis',
            'mood_predictions'
        ];
        
        // Default settings
        $this->settings = [
            'reminder_enabled' => true,
            'reminder_times' => ['09:00', '15:00', '21:00'],
            'show_weather_integration' => false,
            'enable_emoji_picker' => true,
            'track_energy_levels' => true
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
            'show_history' => true
        ]);
        
        $user_id = get_current_user_id();
        $membership = new SpiralEngine_Membership($user_id);
        $tier = $membership->get_tier();
        
        ob_start();
        ?>
        <div class="spiralengine-widget-form spiralengine-mood-form" data-widget="<?php echo esc_attr($this->id); ?>">
            <form id="spiralengine-mood-form" class="spiralengine-episode-form">
                <?php wp_nonce_field('spiralengine_' . $this->id . '_nonce', 'nonce'); ?>
                
                <div class="spiralengine-form-section">
                    <h4><?php _e('How are you feeling?', 'spiralengine'); ?></h4>
                    
                    <!-- Mood Scale -->
                    <div class="spiralengine-mood-scale">
                        <?php
                        $moods = [
                            1 => ['emoji' => '😢', 'label' => __('Very Bad', 'spiralengine')],
                            2 => ['emoji' => '😞', 'label' => __('Bad', 'spiralengine')],
                            3 => ['emoji' => '😕', 'label' => __('Not Good', 'spiralengine')],
                            4 => ['emoji' => '😐', 'label' => __('Neutral', 'spiralengine')],
                            5 => ['emoji' => '🙂', 'label' => __('Okay', 'spiralengine')],
                            6 => ['emoji' => '😊', 'label' => __('Good', 'spiralengine')],
                            7 => ['emoji' => '😄', 'label' => __('Very Good', 'spiralengine')],
                            8 => ['emoji' => '😁', 'label' => __('Great', 'spiralengine')],
                            9 => ['emoji' => '🤗', 'label' => __('Excellent', 'spiralengine')],
                            10 => ['emoji' => '🥳', 'label' => __('Amazing', 'spiralengine')]
                        ];
                        
                        foreach ($moods as $value => $mood):
                        ?>
                        <label class="mood-option">
                            <input type="radio" name="data[mood]" value="<?php echo $value; ?>" required>
                            <span class="mood-emoji"><?php echo $mood['emoji']; ?></span>
                            <span class="mood-label"><?php echo esc_html($mood['label']); ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Energy Level -->
                    <?php if ($this->get_setting('track_energy_levels')): ?>
                    <?php echo $this->render_field('range', [
                        'id' => 'mood_energy',
                        'name' => 'data[energy]',
                        'label' => __('Energy Level', 'spiralengine'),
                        'value' => '5',
                        'min' => '1',
                        'max' => '10',
                        'step' => '1',
                        'description' => __('1 = Exhausted, 10 = Fully energized', 'spiralengine')
                    ]); ?>
                    <?php endif; ?>
                    
                    <!-- Quick note -->
                    <?php echo $this->render_field('text', [
                        'id' => 'mood_note',
                        'name' => 'data[note]',
                        'label' => __('Quick note (optional)', 'spiralengine'),
                        'placeholder' => __('What\'s on your mind?', 'spiralengine')
                    ]); ?>
                </div>
                
                <?php if ($args['mode'] === 'full'): ?>
                <div class="spiralengine-form-section">
                    <h4><?php _e('Contributing Factors', 'spiralengine'); ?></h4>
                    
                    <!-- Sleep Quality -->
                    <?php echo $this->render_field('select', [
                        'id' => 'mood_sleep',
                        'name' => 'data[sleep_quality]',
                        'label' => __('Last night\'s sleep', 'spiralengine'),
                        'options' => [
                            '' => __('Not tracked', 'spiralengine'),
                            'terrible' => __('Terrible', 'spiralengine'),
                            'poor' => __('Poor', 'spiralengine'),
                            'fair' => __('Fair', 'spiralengine'),
                            'good' => __('Good', 'spiralengine'),
                            'excellent' => __('Excellent', 'spiralengine')
                        ]
                    ]); ?>
                    
                    <!-- Activities -->
                    <div class="spiralengine-field-group">
                        <label><?php _e('Activities today', 'spiralengine'); ?></label>
                        <div class="spiralengine-checkbox-group">
                            <?php
                            $activities = [
                                'exercise' => __('Exercise', 'spiralengine'),
                                'work' => __('Work', 'spiralengine'),
                                'social' => __('Socializing', 'spiralengine'),
                                'outdoors' => __('Time outdoors', 'spiralengine'),
                                'creative' => __('Creative activities', 'spiralengine'),
                                'relaxation' => __('Relaxation', 'spiralengine'),
                                'family' => __('Family time', 'spiralengine'),
                                'hobbies' => __('Hobbies', 'spiralengine')
                            ];
                            
                            foreach ($activities as $value => $label):
                            ?>
                            <label>
                                <input type="checkbox" name="data[activities][]" value="<?php echo esc_attr($value); ?>">
                                <?php echo esc_html($label); ?>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <?php if (in_array($tier, ['silver', 'gold', 'platinum'])): ?>
                    <!-- Emotions -->
                    <div class="spiralengine-field-group">
                        <label><?php _e('Emotions experienced', 'spiralengine'); ?></label>
                        <div class="spiralengine-checkbox-group spiralengine-emotion-grid">
                            <?php
                            $emotions = [
                                'happy' => __('Happy', 'spiralengine'),
                                'sad' => __('Sad', 'spiralengine'),
                                'anxious' => __('Anxious', 'spiralengine'),
                                'calm' => __('Calm', 'spiralengine'),
                                'angry' => __('Angry', 'spiralengine'),
                                'grateful' => __('Grateful', 'spiralengine'),
                                'excited' => __('Excited', 'spiralengine'),
                                'bored' => __('Bored', 'spiralengine'),
                                'stressed' => __('Stressed', 'spiralengine'),
                                'content' => __('Content', 'spiralengine'),
                                'frustrated' => __('Frustrated', 'spiralengine'),
                                'hopeful' => __('Hopeful', 'spiralengine')
                            ];
                            
                            foreach ($emotions as $value => $label):
                            ?>
                            <label>
                                <input type="checkbox" name="data[emotions][]" value="<?php echo esc_attr($value); ?>">
                                <?php echo esc_html($label); ?>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <!-- Social Interactions -->
                    <?php echo $this->render_field('select', [
                        'id' => 'mood_social',
                        'name' => 'data[social_interaction]',
                        'label' => __('Social interaction level', 'spiralengine'),
                        'options' => [
                            '' => __('Not tracked', 'spiralengine'),
                            'none' => __('No interaction', 'spiralengine'),
                            'minimal' => __('Minimal', 'spiralengine'),
                            'moderate' => __('Moderate', 'spiralengine'),
                            'high' => __('High', 'spiralengine'),
                            'overwhelming' => __('Overwhelming', 'spiralengine')
                        ]
                    ]); ?>
                    <?php endif; ?>
                    
                    <?php if (in_array($tier, ['gold', 'platinum'])): ?>
                    <!-- External Factors -->
                    <div class="spiralengine-field-group">
                        <label><?php _e('External factors', 'spiralengine'); ?></label>
                        <div class="spiralengine-checkbox-group">
                            <?php
                            $factors = [
                                'weather' => __('Weather', 'spiralengine'),
                                'work-stress' => __('Work stress', 'spiralengine'),
                                'relationship' => __('Relationship issues', 'spiralengine'),
                                'health' => __('Health concerns', 'spiralengine'),
                                'financial' => __('Financial stress', 'spiralengine'),
                                'news' => __('News/Current events', 'spiralengine'),
                                'hormonal' => __('Hormonal changes', 'spiralengine'),
                                'medication' => __('Medication effects', 'spiralengine')
                            ];
                            
                            foreach ($factors as $value => $label):
                            ?>
                            <label>
                                <input type="checkbox" name="data[factors][]" value="<?php echo esc_attr($value); ?>">
                                <?php echo esc_html($label); ?>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <!-- Detailed Notes -->
                    <?php echo $this->render_field('textarea', [
                        'id' => 'mood_journal',
                        'name' => 'data[journal]',
                        'label' => __('Mood journal entry', 'spiralengine'),
                        'placeholder' => __('Describe your day, thoughts, or anything affecting your mood...', 'spiralengine'),
                        'rows' => 4
                    ]); ?>
                    <?php endif; ?>
                </div>
                
                <?php if ($tier === 'platinum'): ?>
                <div class="spiralengine-form-section">
                    <h4><?php _e('Advanced Tracking', 'spiralengine'); ?></h4>
                    
                    <!-- Gratitude -->
                    <?php echo $this->render_field('textarea', [
                        'id' => 'mood_gratitude',
                        'name' => 'data[gratitude]',
                        'label' => __('Things I\'m grateful for', 'spiralengine'),
                        'placeholder' => __('List 3 things you\'re grateful for today...', 'spiralengine'),
                        'rows' => 2
                    ]); ?>
                    
                    <!-- Goals Progress -->
                    <?php echo $this->render_field('range', [
                        'id' => 'mood_goals',
                        'name' => 'data[goal_progress]',
                        'label' => __('Progress toward goals', 'spiralengine'),
                        'value' => '5',
                        'min' => '1',
                        'max' => '10',
                        'step' => '1',
                        'description' => __('How well did you work toward your goals today?', 'spiralengine')
                    ]); ?>
                    
                    <!-- AI Analysis -->
                    <?php echo $this->render_field('checkbox', [
                        'id' => 'mood_ai_insights',
                        'name' => 'data[request_ai_insights]',
                        'label' => __('Generate AI mood insights and recommendations', 'spiralengine'),
                        'value' => '1'
                    ]); ?>
                </div>
                <?php endif; ?>
                <?php endif; ?>
                
                <div class="spiralengine-form-actions">
                    <button type="submit" class="button button-primary spiralengine-save-episode">
                        <span class="dashicons dashicons-saved"></span>
                        <?php _e('Save Mood Entry', 'spiralengine'); ?>
                    </button>
                    
                    <?php if ($args['mode'] === 'full'): ?>
                    <button type="button" class="button spiralengine-mood-history">
                        <span class="dashicons dashicons-chart-line"></span>
                        <?php _e('View Mood History', 'spiralengine'); ?>
                    </button>
                    <?php endif; ?>
                </div>
            </form>
            
            <?php if ($args['show_history'] && $args['mode'] === 'full'): ?>
            <div class="spiralengine-mood-calendar">
                <h4><?php _e('Mood Calendar', 'spiralengine'); ?></h4>
                <div class="spiralengine-calendar-container" data-widget="<?php echo esc_attr($this->id); ?>">
                    <div class="spiralengine-loading"><?php _e('Loading calendar...', 'spiralengine'); ?></div>
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
        
        // Validate mood (required)
        $mood = isset($data['mood']) ? intval($data['mood']) : 0;
        if ($mood < 1 || $mood > 10) {
            $errors['mood'] = __('Please select your mood.', 'spiralengine');
        } else {
            $validated['mood'] = $mood;
            $validated['severity'] = 11 - $mood; // Convert to severity scale
        }
        
        // Validate energy level
        if (isset($data['energy'])) {
            $energy = intval($data['energy']);
            if ($energy >= 1 && $energy <= 10) {
                $validated['energy'] = $energy;
            }
        }
        
        // Validate note
        if (!empty($data['note'])) {
            $validated['note'] = sanitize_text_field($data['note']);
        }
        
        // Validate sleep quality
        if (!empty($data['sleep_quality'])) {
            $valid_sleep = ['terrible', 'poor', 'fair', 'good', 'excellent'];
            if (in_array($data['sleep_quality'], $valid_sleep)) {
                $validated['sleep_quality'] = $data['sleep_quality'];
            }
        }
        
        // Validate activities
        if (!empty($data['activities']) && is_array($data['activities'])) {
            $valid_activities = [
                'exercise', 'work', 'social', 'outdoors',
                'creative', 'relaxation', 'family', 'hobbies'
            ];
            $validated['activities'] = array_intersect($data['activities'], $valid_activities);
        }
        
        // Validate emotions
        if (!empty($data['emotions']) && is_array($data['emotions'])) {
            $valid_emotions = [
                'happy', 'sad', 'anxious', 'calm', 'angry', 'grateful',
                'excited', 'bored', 'stressed', 'content', 'frustrated', 'hopeful'
            ];
            $validated['emotions'] = array_intersect($data['emotions'], $valid_emotions);
        }
        
        // Validate social interaction
        if (!empty($data['social_interaction'])) {
            $valid_social = ['none', 'minimal', 'moderate', 'high', 'overwhelming'];
            if (in_array($data['social_interaction'], $valid_social)) {
                $validated['social_interaction'] = $data['social_interaction'];
            }
        }
        
        // Validate external factors
        if (!empty($data['factors']) && is_array($data['factors'])) {
            $valid_factors = [
                'weather', 'work-stress', 'relationship', 'health',
                'financial', 'news', 'hormonal', 'medication'
            ];
            $validated['factors'] = array_intersect($data['factors'], $valid_factors);
        }
        
        // Validate journal entry
        if (!empty($data['journal'])) {
            $validated['journal'] = sanitize_textarea_field($data['journal']);
        }
        
        // Validate gratitude
        if (!empty($data['gratitude'])) {
            $validated['gratitude'] = sanitize_textarea_field($data['gratitude']);
        }
        
        // Validate goal progress
        if (isset($data['goal_progress'])) {
            $progress = intval($data['goal_progress']);
            if ($progress >= 1 && $progress <= 10) {
                $validated['goal_progress'] = $progress;
            }
        }
        
        // Validate AI insights request
        if (!empty($data['request_ai_insights'])) {
            $validated['request_ai_insights'] = true;
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
        // Add time of day
        $hour = intval(date('H'));
        if ($hour >= 5 && $hour < 12) {
            $metadata['time_of_day'] = 'morning';
        } elseif ($hour >= 12 && $hour < 17) {
            $metadata['time_of_day'] = 'afternoon';
        } elseif ($hour >= 17 && $hour < 21) {
            $metadata['time_of_day'] = 'evening';
        } else {
            $metadata['time_of_day'] = 'night';
        }
        
        // Add day of week
        $metadata['day_of_week'] = strtolower(date('l'));
        
        // Add completeness score
        $completeness = 0;
        if (!empty($data['note'])) $completeness += 10;
        if (!empty($data['sleep_quality'])) $completeness += 15;
        if (!empty($data['activities'])) $completeness += 20;
        if (!empty($data['emotions'])) $completeness += 20;
        if (!empty($data['social_interaction'])) $completeness += 10;
        if (!empty($data['factors'])) $completeness += 10;
        if (!empty($data['journal'])) $completeness += 15;
        
        $metadata['completeness_score'] = $completeness;
        
        // Add mood category
        if ($data['mood'] <= 3) {
            $metadata['mood_category'] = 'negative';
        } elseif ($data['mood'] <= 6) {
            $metadata['mood_category'] = 'neutral';
        } else {
            $metadata['mood_category'] = 'positive';
        }
        
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
        
        // Get mood data for last 30 days
        $moods = $wpdb->get_results($wpdb->prepare(
            "SELECT data, metadata, created_at FROM {$table} 
            WHERE user_id = %d 
            AND widget_id = %s 
            AND created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)
            ORDER BY created_at ASC",
            $user_id, $this->id, $days
        ));
        
        // Calculate statistics
        $mood_values = [];
        $mood_by_day = [];
        $emotions_count = [];
        $activities_impact = [];
        $time_patterns = [];
        
        foreach ($moods as $episode) {
            $data = json_decode($episode->data, true);
            $metadata = json_decode($episode->metadata, true);
            
            $mood_values[] = $data['mood'];
            
            // Group by day
            $day = date('Y-m-d', strtotime($episode->created_at));
            if (!isset($mood_by_day[$day])) {
                $mood_by_day[$day] = [];
            }
            $mood_by_day[$day][] = $data['mood'];
            
            // Count emotions
            if (!empty($data['emotions'])) {
                foreach ($data['emotions'] as $emotion) {
                    $emotions_count[$emotion] = ($emotions_count[$emotion] ?? 0) + 1;
                }
            }
            
            // Track activities impact
            if (!empty($data['activities'])) {
                foreach ($data['activities'] as $activity) {
                    if (!isset($activities_impact[$activity])) {
                        $activities_impact[$activity] = [];
                    }
                    $activities_impact[$activity][] = $data['mood'];
                }
            }
            
            // Time patterns
            if (!empty($metadata['time_of_day'])) {
                if (!isset($time_patterns[$metadata['time_of_day']])) {
                    $time_patterns[$metadata['time_of_day']] = [];
                }
                $time_patterns[$metadata['time_of_day']][] = $data['mood'];
            }
        }
        
        $avg_mood = !empty($mood_values) ? array_sum($mood_values) / count($mood_values) : 0;
        $mood_trend = $this->calculate_trend($mood_by_day);
        
        ob_start();
        ?>
        <div class="spiralengine-widget-analytics spiralengine-mood-analytics">
            <h3><?php echo esc_html($this->name); ?> <?php _e('Analytics', 'spiralengine'); ?></h3>
            
            <div class="spiralengine-stats-grid">
                <div class="stat-card">
                    <span class="stat-value"><?php echo number_format($avg_mood, 1); ?>/10</span>
                    <span class="stat-label"><?php _e('Average Mood', 'spiralengine'); ?></span>
                </div>
                
                <div class="stat-card">
                    <span class="stat-value <?php echo $mood_trend > 0 ? 'positive' : ($mood_trend < 0 ? 'negative' : ''); ?>">
                        <?php if ($mood_trend > 0): ?>
                            <span class="dashicons dashicons-arrow-up-alt"></span>
                        <?php elseif ($mood_trend < 0): ?>
                            <span class="dashicons dashicons-arrow-down-alt"></span>
                        <?php else: ?>
                            <span class="dashicons dashicons-minus"></span>
                        <?php endif; ?>
                        <?php echo abs($mood_trend); ?>%
                    </span>
                    <span class="stat-label"><?php _e('Mood Trend', 'spiralengine'); ?></span>
                </div>
                
                <div class="stat-card">
                    <span class="stat-value"><?php echo count($mood_values); ?></span>
                    <span class="stat-label"><?php _e('Entries (30 days)', 'spiralengine'); ?></span>
                </div>
            </div>
            
            <?php if (!empty($mood_by_day)): ?>
            <div class="spiralengine-chart-section">
                <h4><?php _e('Mood Over Time', 'spiralengine'); ?></h4>
                <canvas id="mood-chart-<?php echo $user_id; ?>" class="spiralengine-chart"></canvas>
                <script>
                jQuery(document).ready(function($) {
                    var ctx = document.getElementById('mood-chart-<?php echo $user_id; ?>').getContext('2d');
                    var chart = new Chart(ctx, {
                        type: 'line',
                        data: {
                            labels: <?php echo json_encode(array_keys($mood_by_day)); ?>,
                            datasets: [{
                                label: '<?php _e('Daily Mood', 'spiralengine'); ?>',
                                data: <?php echo json_encode(array_map(function($moods) {
                                    return round(array_sum($moods) / count($moods), 1);
                                }, array_values($mood_by_day))); ?>,
                                borderColor: '#4CAF50',
                                backgroundColor: 'rgba(76, 175, 80, 0.1)',
                                tension: 0.2
                            }]
                        },
                        options: {
                            responsive: true,
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    max: 10
                                }
                            }
                        }
                    });
                });
                </script>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($emotions_count)): ?>
            <div class="spiralengine-chart-section">
                <h4><?php _e('Most Common Emotions', 'spiralengine'); ?></h4>
                <div class="spiralengine-emotion-bubbles">
                    <?php
                    arsort($emotions_count);
                    $top_emotions = array_slice($emotions_count, 0, 6, true);
                    $max_count = max($emotions_count);
                    
                    $emotion_labels = [
                        'happy' => __('Happy', 'spiralengine'),
                        'sad' => __('Sad', 'spiralengine'),
                        'anxious' => __('Anxious', 'spiralengine'),
                        'calm' => __('Calm', 'spiralengine'),
                        'angry' => __('Angry', 'spiralengine'),
                        'grateful' => __('Grateful', 'spiralengine'),
                        'excited' => __('Excited', 'spiralengine'),
                        'bored' => __('Bored', 'spiralengine'),
                        'stressed' => __('Stressed', 'spiralengine'),
                        'content' => __('Content', 'spiralengine'),
                        'frustrated' => __('Frustrated', 'spiralengine'),
                        'hopeful' => __('Hopeful', 'spiralengine')
                    ];
                    
                    foreach ($top_emotions as $emotion => $count):
                        $size = 50 + (($count / $max_count) * 50);
                    ?>
                    <div class="emotion-bubble" style="width: <?php echo $size; ?>px; height: <?php echo $size; ?>px;">
                        <span class="emotion-name"><?php echo esc_html($emotion_labels[$emotion] ?? $emotion); ?></span>
                        <span class="emotion-count"><?php echo $count; ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($activities_impact)): ?>
            <div class="spiralengine-chart-section">
                <h4><?php _e('Activities Impact on Mood', 'spiralengine'); ?></h4>
                <div class="spiralengine-impact-list">
                    <?php
                    $activity_labels = [
                        'exercise' => __('Exercise', 'spiralengine'),
                        'work' => __('Work', 'spiralengine'),
                        'social' => __('Socializing', 'spiralengine'),
                        'outdoors' => __('Time outdoors', 'spiralengine'),
                        'creative' => __('Creative activities', 'spiralengine'),
                        'relaxation' => __('Relaxation', 'spiralengine'),
                        'family' => __('Family time', 'spiralengine'),
                        'hobbies' => __('Hobbies', 'spiralengine')
                    ];
                    
                    $activity_averages = [];
                    foreach ($activities_impact as $activity => $moods) {
                        $activity_averages[$activity] = array_sum($moods) / count($moods);
                    }
                    arsort($activity_averages);
                    
                    foreach ($activity_averages as $activity => $avg_mood):
                        $impact = $avg_mood - $avg_mood;
                        $impact_class = $impact > 0.5 ? 'positive' : ($impact < -0.5 ? 'negative' : 'neutral');
                    ?>
                    <div class="impact-item <?php echo $impact_class; ?>">
                        <span class="activity-name"><?php echo esc_html($activity_labels[$activity] ?? $activity); ?></span>
                        <span class="activity-impact">
                            <?php echo number_format($avg_mood, 1); ?>/10
                            <?php if (abs($impact) > 0.5): ?>
                                (<?php echo $impact > 0 ? '+' : ''; ?><?php echo number_format($impact, 1); ?>)
                            <?php endif; ?>
                        </span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($time_patterns)): ?>
            <div class="spiralengine-chart-section">
                <h4><?php _e('Mood by Time of Day', 'spiralengine'); ?></h4>
                <div class="spiralengine-time-patterns">
                    <?php
                    $time_labels = [
                        'morning' => __('Morning', 'spiralengine'),
                        'afternoon' => __('Afternoon', 'spiralengine'),
                        'evening' => __('Evening', 'spiralengine'),
                        'night' => __('Night', 'spiralengine')
                    ];
                    
                    foreach (['morning', 'afternoon', 'evening', 'night'] as $time):
                        if (!isset($time_patterns[$time])) continue;
                        $time_avg = array_sum($time_patterns[$time]) / count($time_patterns[$time]);
                    ?>
                    <div class="time-block">
                        <span class="time-label"><?php echo esc_html($time_labels[$time]); ?></span>
                        <div class="time-bar">
                            <div class="time-fill" style="width: <?php echo ($time_avg / 10) * 100; ?>%"></div>
                        </div>
                        <span class="time-value"><?php echo number_format($time_avg, 1); ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="spiralengine-analytics-actions">
                <a href="#" class="button spiralengine-export-mood-data" data-widget="<?php echo esc_attr($this->id); ?>">
                    <span class="dashicons dashicons-download"></span>
                    <?php _e('Export Mood Data', 'spiralengine'); ?>
                </a>
                
                <?php
                $membership = new SpiralEngine_Membership($user_id);
                if (in_array($membership->get_tier(), ['gold', 'platinum'])):
                ?>
                <a href="#" class="button spiralengine-mood-insights" data-widget="<?php echo esc_attr($this->id); ?>">
                    <span class="dashicons dashicons-lightbulb"></span>
                    <?php _e('Get Mood Insights', 'spiralengine'); ?>
                </a>
                <?php endif; ?>
            </div>
        </div>
        <?php
        
        return ob_get_clean();
    }
    
    /**
     * Calculate mood trend
     *
     * @since 1.0.0
     * @param array $mood_by_day Mood data grouped by day
     * @return float Trend percentage
     */
    private function calculate_trend($mood_by_day) {
        if (count($mood_by_day) < 7) {
            return 0;
        }
        
        // Get first week and last week averages
        $days = array_keys($mood_by_day);
        $first_week = array_slice($days, 0, 7);
        $last_week = array_slice($days, -7);
        
        $first_week_moods = [];
        $last_week_moods = [];
        
        foreach ($first_week as $day) {
            $first_week_moods = array_merge($first_week_moods, $mood_by_day[$day]);
        }
        
        foreach ($last_week as $day) {
            $last_week_moods = array_merge($last_week_moods, $mood_by_day[$day]);
        }
        
        $first_avg = array_sum($first_week_moods) / count($first_week_moods);
        $last_avg = array_sum($last_week_moods) / count($last_week_moods);
        
        if ($first_avg == 0) {
            return 0;
        }
        
        return round((($last_avg - $first_avg) / $first_avg) * 100, 1);
    }
    
    /**
     * Get widget data schema
     *
     * @since 1.0.0
     * @return array Schema definition
     */
    public function get_schema() {
        return [
            'mood' => [
                'type' => 'integer',
                'label' => __('Mood Rating', 'spiralengine'),
                'min' => 1,
                'max' => 10,
                'required' => true
            ],
            'severity' => [
                'type' => 'integer',
                'label' => __('Severity', 'spiralengine'),
                'min' => 1,
                'max' => 10,
                'required' => true
            ],
            'energy' => [
                'type' => 'integer',
                'label' => __('Energy Level', 'spiralengine'),
                'min' => 1,
                'max' => 10
            ],
            'note' => [
                'type' => 'string',
                'label' => __('Quick Note', 'spiralengine')
            ],
            'sleep_quality' => [
                'type' => 'string',
                'label' => __('Sleep Quality', 'spiralengine'),
                'enum' => ['terrible', 'poor', 'fair', 'good', 'excellent']
            ],
            'activities' => [
                'type' => 'array',
                'label' => __('Activities', 'spiralengine'),
                'items' => [
                    'type' => 'string',
                    'enum' => ['exercise', 'work', 'social', 'outdoors', 'creative', 'relaxation', 'family', 'hobbies']
                ]
            ],
            'emotions' => [
                'type' => 'array',
                'label' => __('Emotions', 'spiralengine'),
                'items' => [
                    'type' => 'string',
                    'enum' => ['happy', 'sad', 'anxious', 'calm', 'angry', 'grateful', 'excited', 'bored', 'stressed', 'content', 'frustrated', 'hopeful']
                ]
            ],
            'social_interaction' => [
                'type' => 'string',
                'label' => __('Social Interaction', 'spiralengine'),
                'enum' => ['none', 'minimal', 'moderate', 'high', 'overwhelming']
            ],
            'factors' => [
                'type' => 'array',
                'label' => __('External Factors', 'spiralengine'),
                'items' => [
                    'type' => 'string',
                    'enum' => ['weather', 'work-stress', 'relationship', 'health', 'financial', 'news', 'hormonal', 'medication']
                ]
            ],
            'journal' => [
                'type' => 'string',
                'label' => __('Journal Entry', 'spiralengine')
            ],
            'gratitude' => [
                'type' => 'string',
                'label' => __('Gratitude', 'spiralengine')
            ],
            'goal_progress' => [
                'type' => 'integer',
                'label' => __('Goal Progress', 'spiralengine'),
                'min' => 1,
                'max' => 10
            ],
            'request_ai_insights' => [
                'type' => 'boolean',
                'label' => __('AI Insights Requested', 'spiralengine')
            ]
        ];
    }
}

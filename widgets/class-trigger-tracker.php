<?php
/**
 * Trigger Tracker Widget
 * 
 * @package    SpiralEngine
 * @subpackage Widgets
 * @file       widgets/class-trigger-tracker.php
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Trigger Tracker Widget Class
 * 
 * Helps users identify and track mental health triggers
 * 
 * @since 1.0.0
 */
class SpiralEngine_Widget_Trigger_Tracker extends SpiralEngine_Widget {
    
    /**
     * Widget fields configuration
     * 
     * @var array
     */
    protected $fields = array();
    
    /**
     * Widget color
     * 
     * @var string
     */
    protected $color = '#FF9800';
    
    /**
     * Widget category
     * 
     * @var string
     */
    protected $category = 'tracking';
    
    /**
     * Tier features configuration
     * 
     * @var array
     */
    protected $tier_features = array();
    
    /**
     * Constructor
     */
    public function __construct() {
        parent::__construct();
    }
    
    /**
     * Initialize widget
     */
    protected function init() {
        $this->id = 'trigger-tracker';
        $this->name = __('Trigger Tracker', 'spiralengine');
        $this->description = __('Identify and track your mental health triggers', 'spiralengine');
        $this->icon = 'dashicons-warning';
        $this->color = '#FF9800';
        $this->category = 'tracking';
        $this->tier_features = array(
            'free' => array(
                'basic_tracking' => true,
                'trigger_categories' => true,
                'intensity_rating' => true,
                'max_daily_entries' => 3
            ),
            'silver' => array(
                'basic_tracking' => true,
                'trigger_categories' => true,
                'intensity_rating' => true,
                'environmental_factors' => true,
                'coping_response' => true,
                'time_tracking' => true,
                'max_daily_entries' => 10
            ),
            'gold' => array(
                'basic_tracking' => true,
                'trigger_categories' => true,
                'intensity_rating' => true,
                'environmental_factors' => true,
                'coping_response' => true,
                'time_tracking' => true,
                'pattern_analysis' => true,
                'trigger_chains' => true,
                'prevention_planning' => true,
                'max_daily_entries' => 'unlimited'
            ),
            'platinum' => array(
                'basic_tracking' => true,
                'trigger_categories' => true,
                'intensity_rating' => true,
                'environmental_factors' => true,
                'coping_response' => true,
                'time_tracking' => true,
                'pattern_analysis' => true,
                'trigger_chains' => true,
                'prevention_planning' => true,
                'ai_pattern_detection' => true,
                'predictive_alerts' => true,
                'max_daily_entries' => 'unlimited'
            )
        );
        
        // Initialize fields
        $this->fields = array(
            'trigger_category' => array(
                'label' => __('Trigger Category', 'spiralengine'),
                'type' => 'select',
                'options' => array(
                    '' => __('-- Select Category --', 'spiralengine'),
                    'interpersonal' => __('Interpersonal (People/Relationships)', 'spiralengine'),
                    'environmental' => __('Environmental (Places/Situations)', 'spiralengine'),
                    'internal' => __('Internal (Thoughts/Feelings)', 'spiralengine'),
                    'physical' => __('Physical (Body/Health)', 'spiralengine'),
                    'temporal' => __('Temporal (Time-related)', 'spiralengine'),
                    'sensory' => __('Sensory (Sights/Sounds/Smells)', 'spiralengine'),
                    'digital' => __('Digital (Social Media/News)', 'spiralengine'),
                    'financial' => __('Financial (Money/Work)', 'spiralengine'),
                    'other' => __('Other', 'spiralengine')
                ),
                'required' => true,
                'tier' => 'free'
            ),
            'trigger_description' => array(
                'label' => __('Describe the Trigger', 'spiralengine'),
                'type' => 'textarea',
                'rows' => 3,
                'required' => true,
                'placeholder' => __('What specifically triggered you?', 'spiralengine'),
                'tier' => 'free'
            ),
            'intensity' => array(
                'label' => __('Trigger Intensity', 'spiralengine'),
                'type' => 'range',
                'min' => 1,
                'max' => 10,
                'default' => 5,
                'required' => true,
                'description' => __('1 = Mild, 10 = Severe', 'spiralengine'),
                'tier' => 'free'
            ),
            'emotional_response' => array(
                'label' => __('Emotional Response', 'spiralengine'),
                'type' => 'checkbox',
                'options' => array(
                    'anxiety' => __('Anxiety/Worry', 'spiralengine'),
                    'anger' => __('Anger/Irritation', 'spiralengine'),
                    'sadness' => __('Sadness/Depression', 'spiralengine'),
                    'fear' => __('Fear/Panic', 'spiralengine'),
                    'shame' => __('Shame/Guilt', 'spiralengine'),
                    'overwhelm' => __('Overwhelm', 'spiralengine'),
                    'numbness' => __('Numbness/Disconnection', 'spiralengine'),
                    'frustration' => __('Frustration', 'spiralengine')
                ),
                'required' => true,
                'tier' => 'free'
            ),
            'physical_response' => array(
                'label' => __('Physical Response', 'spiralengine'),
                'type' => 'checkbox',
                'options' => array(
                    'racing_heart' => __('Racing Heart', 'spiralengine'),
                    'sweating' => __('Sweating', 'spiralengine'),
                    'shaking' => __('Shaking/Trembling', 'spiralengine'),
                    'nausea' => __('Nausea', 'spiralengine'),
                    'headache' => __('Headache', 'spiralengine'),
                    'muscle_tension' => __('Muscle Tension', 'spiralengine'),
                    'breathing_difficulty' => __('Breathing Difficulty', 'spiralengine'),
                    'fatigue' => __('Sudden Fatigue', 'spiralengine')
                ),
                'tier' => 'silver'
            ),
            'location' => array(
                'label' => __('Where did this happen?', 'spiralengine'),
                'type' => 'text',
                'placeholder' => __('Home, work, store, etc.', 'spiralengine'),
                'tier' => 'silver'
            ),
            'time_of_day' => array(
                'label' => __('Time of Day', 'spiralengine'),
                'type' => 'select',
                'options' => array(
                    '' => __('-- Select Time --', 'spiralengine'),
                    'early_morning' => __('Early Morning (5-8 AM)', 'spiralengine'),
                    'morning' => __('Morning (8-12 PM)', 'spiralengine'),
                    'afternoon' => __('Afternoon (12-5 PM)', 'spiralengine'),
                    'evening' => __('Evening (5-9 PM)', 'spiralengine'),
                    'night' => __('Night (9 PM-12 AM)', 'spiralengine'),
                    'late_night' => __('Late Night (12-5 AM)', 'spiralengine')
                ),
                'tier' => 'silver'
            ),
            'environmental_factors' => array(
                'label' => __('Environmental Factors', 'spiralengine'),
                'type' => 'checkbox',
                'options' => array(
                    'crowded' => __('Crowded Space', 'spiralengine'),
                    'noise' => __('Loud Noise', 'spiralengine'),
                    'lighting' => __('Bright/Dim Lighting', 'spiralengine'),
                    'temperature' => __('Temperature (Hot/Cold)', 'spiralengine'),
                    'confined' => __('Confined Space', 'spiralengine'),
                    'social_pressure' => __('Social Pressure', 'spiralengine'),
                    'alone' => __('Being Alone', 'spiralengine'),
                    'unfamiliar' => __('Unfamiliar Environment', 'spiralengine')
                ),
                'tier' => 'silver'
            ),
            'coping_response' => array(
                'label' => __('How did you cope?', 'spiralengine'),
                'type' => 'textarea',
                'rows' => 3,
                'placeholder' => __('Describe what you did to manage the trigger', 'spiralengine'),
                'tier' => 'silver'
            ),
            'coping_effectiveness' => array(
                'label' => __('How effective was your coping?', 'spiralengine'),
                'type' => 'range',
                'min' => 1,
                'max' => 5,
                'default' => 3,
                'description' => __('1 = Not effective, 5 = Very effective', 'spiralengine'),
                'tier' => 'silver'
            ),
            'trigger_chain' => array(
                'label' => __('Was this part of a trigger chain?', 'spiralengine'),
                'type' => 'radio',
                'options' => array(
                    'no' => __('No - standalone trigger', 'spiralengine'),
                    'start' => __('Yes - this started a chain', 'spiralengine'),
                    'middle' => __('Yes - part of ongoing chain', 'spiralengine'),
                    'end' => __('Yes - end result of chain', 'spiralengine')
                ),
                'default' => 'no',
                'tier' => 'gold'
            ),
            'related_triggers' => array(
                'label' => __('Related Triggers', 'spiralengine'),
                'type' => 'textarea',
                'rows' => 2,
                'placeholder' => __('List any related or connected triggers', 'spiralengine'),
                'tier' => 'gold'
            ),
            'warning_signs' => array(
                'label' => __('Early Warning Signs', 'spiralengine'),
                'type' => 'textarea',
                'rows' => 3,
                'placeholder' => __('What warning signs did you notice before the trigger?', 'spiralengine'),
                'tier' => 'gold'
            ),
            'prevention_plan' => array(
                'label' => __('Prevention Ideas', 'spiralengine'),
                'type' => 'textarea',
                'rows' => 3,
                'placeholder' => __('How could you prevent or minimize this trigger in the future?', 'spiralengine'),
                'tier' => 'gold'
            ),
            'support_needed' => array(
                'label' => __('Support Needed', 'spiralengine'),
                'type' => 'checkbox',
                'options' => array(
                    'professional' => __('Professional Support', 'spiralengine'),
                    'medication' => __('Medication Review', 'spiralengine'),
                    'therapy' => __('Therapy Discussion', 'spiralengine'),
                    'social' => __('Social Support', 'spiralengine'),
                    'environmental' => __('Environmental Changes', 'spiralengine'),
                    'lifestyle' => __('Lifestyle Adjustments', 'spiralengine')
                ),
                'tier' => 'platinum'
            ),
            'notes' => array(
                'label' => __('Additional Notes', 'spiralengine'),
                'type' => 'textarea',
                'rows' => 4,
                'placeholder' => __('Any other observations or thoughts', 'spiralengine'),
                'tier' => 'free'
            )
        );
    }
    
    /**
     * Get widget schema
     * 
     * @return array Widget schema
     */
    public function get_schema() {
        return array(
            'fields' => $this->fields,
            'capabilities' => array(
                'basic_tracking' => true,
                'trigger_categories' => true,
                'intensity_rating' => true,
                'environmental_factors' => true,
                'coping_response' => true,
                'time_tracking' => true,
                'pattern_analysis' => true,
                'trigger_chains' => true,
                'prevention_planning' => true,
                'ai_pattern_detection' => true,
                'predictive_alerts' => true
            ),
            'analytics' => array(
                'track_categories' => true,
                'track_emotions' => true,
                'track_patterns' => true,
                'track_coping' => true
            ),
            'tier_features' => $this->tier_features
        );
    }
    
    /**
     * Render form
     * 
     * @param array $args Form arguments
     * @return string HTML output
     */
    public function render_form($args = []) {
        // Get user data if editing
        $values = isset($args['values']) ? $args['values'] : array();
        
        ob_start();
        ?>
        <div class="spiralengine-widget-form spiralengine-trigger-tracker-form">
            <?php foreach ($this->fields as $field_id => $field): ?>
                <?php if (!$this->can_use_feature_by_field($field)): continue; endif; ?>
                
                <div class="spiralengine-form-group">
                    <?php if ($field['type'] !== 'hidden'): ?>
                        <label for="<?php echo esc_attr($field_id); ?>">
                            <?php echo esc_html($field['label']); ?>
                            <?php if (!empty($field['required'])): ?>
                                <span class="required">*</span>
                            <?php endif; ?>
                        </label>
                    <?php endif; ?>
                    
                    <?php
                    $value = isset($values[$field_id]) ? $values[$field_id] : (isset($field['default']) ? $field['default'] : '');
                    $this->render_field($field_id, $field, $value);
                    ?>
                    
                    <?php if (!empty($field['description'])): ?>
                        <p class="description"><?php echo esc_html($field['description']); ?></p>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // Handle range inputs
            $('.spiralengine-range-input').on('input', function() {
                $(this).siblings('.range-value').text($(this).val());
            });
            
            // Handle coping effectiveness visibility
            $('textarea[name="coping_response"]').on('input', function() {
                var $effectivenessGroup = $('[name="coping_effectiveness"]').closest('.spiralengine-form-group');
                if ($(this).val().trim()) {
                    $effectivenessGroup.show();
                } else {
                    $effectivenessGroup.hide();
                }
            }).trigger('input');
            
            // Handle trigger chain visibility
            $('input[name="trigger_chain"]').on('change', function() {
                var $relatedGroup = $('[name="related_triggers"]').closest('.spiralengine-form-group');
                if ($(this).val() !== 'no') {
                    $relatedGroup.show();
                } else {
                    $relatedGroup.hide();
                }
            }).filter(':checked').trigger('change');
        });
        </script>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Validate widget data
     * 
     * @param array $data Data to validate
     * @return array Validation result
     */
    public function validate_data($data) {
        $errors = array();
        
        // Get user tier at runtime
        $tier = 'free'; // Default
        if (method_exists($this, 'get_user_tier')) {
            $tier = $this->get_user_tier();
        }
        
        // Check daily limit
        $daily_limit = $this->get_daily_limit($tier);
        if ($daily_limit !== 'unlimited') {
            $today_count = $this->get_today_entry_count();
            if ($today_count >= $daily_limit) {
                $errors[] = sprintf(
                    __('You have reached your daily trigger tracking limit of %d. Upgrade to track more triggers.', 'spiralengine'),
                    $daily_limit
                );
                return array('valid' => false, 'errors' => $errors);
            }
        }
        
        // Validate trigger category
        if (empty($data['trigger_category'])) {
            $errors[] = __('Please select a trigger category.', 'spiralengine');
        }
        
        // Validate trigger description
        if (empty($data['trigger_description'])) {
            $errors[] = __('Please describe the trigger.', 'spiralengine');
        }
        
        // Validate intensity
        if (empty($data['intensity']) || $data['intensity'] < 1 || $data['intensity'] > 10) {
            $errors[] = __('Trigger intensity is required (1-10).', 'spiralengine');
        }
        
        // Validate emotional response
        if (empty($data['emotional_response']) || !is_array($data['emotional_response'])) {
            $errors[] = __('Please select at least one emotional response.', 'spiralengine');
        }
        
        // Validate coping effectiveness if coping response provided
        if (!empty($data['coping_response']) && empty($data['coping_effectiveness'])) {
            $errors[] = __('Please rate the effectiveness of your coping response.', 'spiralengine');
        }
        
        return array(
            'valid' => empty($errors),
            'errors' => $errors
        );
    }
    
    /**
     * Process widget data before saving
     * 
     * @param array $data Data to process
     * @return array Processed data
     */
    public function process_data($data) {
        global $wpdb;
        
        // Calculate severity (equals intensity for triggers)
        $severity = intval($data['intensity']);
        
        // Build processed data
        $processed = array(
            'widget_id' => $this->id,
            'user_id' => get_current_user_id(),
            'data' => $data,
            'severity' => $severity,
            'created_at' => current_time('mysql'),
            'calculated' => array(
                'trigger_date' => current_time('Y-m-d'),
                'trigger_time' => current_time('H:i:s'),
                'day_of_week' => current_time('l'),
                'emotional_count' => is_array($data['emotional_response']) ? count($data['emotional_response']) : 0,
                'physical_count' => isset($data['physical_response']) && is_array($data['physical_response']) ? count($data['physical_response']) : 0
            )
        );
        
        // Calculate overall impact score
        $impact_score = $severity;
        if ($processed['calculated']['emotional_count'] > 3) {
            $impact_score += 1;
        }
        if ($processed['calculated']['physical_count'] > 3) {
            $impact_score += 1;
        }
        $processed['calculated']['impact_score'] = min($impact_score, 10);
        
        // Track if this is a recurring trigger
        $processed['calculated']['is_recurring'] = $this->is_recurring_trigger($data['trigger_description']);
        
        return array(
            'data' => $processed,
            'severity' => $severity
        );
    }
    
    /**
     * Check if trigger description is recurring
     * 
     * @param string $description Trigger description
     * @return bool Is recurring
     */
    private function is_recurring_trigger($description) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'spiralengine_episodes';
        $user_id = get_current_user_id();
        
        // Don't run queries during initialization
        if (!$user_id || !$wpdb) {
            return false;
        }
        
        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table}'") != $table) {
            return false;
        }
        
        // Simple similarity check - look for similar triggers in last 30 days
        $similar_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} 
            WHERE user_id = %d 
            AND widget_id = %s 
            AND created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
            AND JSON_EXTRACT(data, '$.trigger_description') LIKE %s",
            $user_id,
            $this->id,
            '%' . $wpdb->esc_like(substr($description, 0, 20)) . '%'
        ));
        
        return intval($similar_count) > 0;
    }
    
    /**
     * Render analytics for the widget
     * 
     * @param int $user_id User ID
     * @return string HTML output
     */
    public function render_analytics($user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        $episodes = $this->get_episodes($user_id, array(
            'limit' => 50,
            'orderby' => 'created_at',
            'order' => 'DESC'
        ));
        
        if (empty($episodes)) {
            return '<p class="spiralengine-no-data">' . __('No triggers tracked yet.', 'spiralengine') . '</p>';
        }
        
        // Analyze trigger data
        $total_triggers = count($episodes);
        $category_counts = array();
        $emotion_counts = array();
        $location_counts = array();
        $time_patterns = array();
        $intensity_sum = 0;
        $coping_scores = array();
        $recurring_count = 0;
        
        foreach ($episodes as $episode) {
            $data = $episode['data'];
            
            // Category analysis
            $category = $data['trigger_category'] ?? 'unknown';
            $category_counts[$category] = ($category_counts[$category] ?? 0) + 1;
            
            // Emotion analysis
            if (!empty($data['emotional_response']) && is_array($data['emotional_response'])) {
                foreach ($data['emotional_response'] as $emotion) {
                    $emotion_counts[$emotion] = ($emotion_counts[$emotion] ?? 0) + 1;
                }
            }
            
            // Location analysis
            if (!empty($data['location'])) {
                $location = strtolower(trim($data['location']));
                $location_counts[$location] = ($location_counts[$location] ?? 0) + 1;
            }
            
            // Time patterns
            if (!empty($data['time_of_day'])) {
                $time_patterns[$data['time_of_day']] = ($time_patterns[$data['time_of_day']] ?? 0) + 1;
            }
            
            // Intensity tracking
            $intensity_sum += intval($data['intensity'] ?? 5);
            
            // Coping effectiveness
            if (!empty($data['coping_effectiveness'])) {
                $coping_scores[] = intval($data['coping_effectiveness']);
            }
            
            // Recurring triggers
            if (!empty($data['calculated']['is_recurring'])) {
                $recurring_count++;
            }
        }
        
        $avg_intensity = round($intensity_sum / $total_triggers, 1);
        $avg_coping = !empty($coping_scores) ? round(array_sum($coping_scores) / count($coping_scores), 1) : 0;
        
        // Sort for top items
        arsort($category_counts);
        arsort($emotion_counts);
        arsort($location_counts);
        
        // Get user tier for feature checks
        $tier = method_exists($this, 'get_user_tier') ? $this->get_user_tier() : 'free';
        
        ob_start();
        ?>
        <div class="spiralengine-analytics-grid">
            <div class="spiralengine-stat-card">
                <h4><?php _e('Total Triggers', 'spiralengine'); ?></h4>
                <div class="spiralengine-stat-value"><?php echo $total_triggers; ?></div>
                <div class="spiralengine-stat-label"><?php _e('Last 50 entries', 'spiralengine'); ?></div>
            </div>
            
            <div class="spiralengine-stat-card">
                <h4><?php _e('Average Intensity', 'spiralengine'); ?></h4>
                <div class="spiralengine-stat-value"><?php echo $avg_intensity; ?>/10</div>
                <div class="spiralengine-stat-label">
                    <?php 
                    if ($avg_intensity >= 7) {
                        echo '🔴 ' . __('High', 'spiralengine');
                    } elseif ($avg_intensity >= 4) {
                        echo '🟡 ' . __('Moderate', 'spiralengine');
                    } else {
                        echo '🟢 ' . __('Low', 'spiralengine');
                    }
                    ?>
                </div>
            </div>
            
            <div class="spiralengine-stat-card">
                <h4><?php _e('Coping Effectiveness', 'spiralengine'); ?></h4>
                <div class="spiralengine-stat-value"><?php echo $avg_coping ?: 'N/A'; ?></div>
                <div class="spiralengine-stat-label"><?php _e('Average rating (1-5)', 'spiralengine'); ?></div>
            </div>
            
            <div class="spiralengine-stat-card">
                <h4><?php _e('Recurring Triggers', 'spiralengine'); ?></h4>
                <div class="spiralengine-stat-value"><?php echo round(($recurring_count / $total_triggers) * 100); ?>%</div>
                <div class="spiralengine-stat-label"><?php _e('Identified patterns', 'spiralengine'); ?></div>
            </div>
        </div>
        
        <div class="spiralengine-analytics-section">
            <h4><?php _e('Top Trigger Categories', 'spiralengine'); ?></h4>
            <ul class="spiralengine-category-list">
                <?php 
                $top_categories = array_slice($category_counts, 0, 5, true);
                foreach ($top_categories as $category => $count): 
                    $percentage = round(($count / $total_triggers) * 100);
                ?>
                    <li>
                        <strong><?php echo ucfirst($category); ?>:</strong> 
                        <?php echo $count; ?> (<?php echo $percentage; ?>%)
                    </li>
                <?php endforeach; ?>
            </ul>
            
            <h4><?php _e('Common Emotional Responses', 'spiralengine'); ?></h4>
            <ul class="spiralengine-emotion-list">
                <?php 
                $top_emotions = array_slice($emotion_counts, 0, 5, true);
                foreach ($top_emotions as $emotion => $count): 
                ?>
                    <li><?php echo ucfirst(str_replace('_', ' ', $emotion)); ?>: <?php echo $count; ?></li>
                <?php endforeach; ?>
            </ul>
            
            <?php if (!empty($location_counts)): ?>
                <h4><?php _e('Common Locations', 'spiralengine'); ?></h4>
                <ul class="spiralengine-location-list">
                    <?php 
                    $top_locations = array_slice($location_counts, 0, 3, true);
                    foreach ($top_locations as $location => $count): 
                    ?>
                        <li><?php echo ucfirst($location); ?>: <?php echo $count; ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
        
        <?php if (!empty($this->tier_features[$tier]['pattern_analysis'])): ?>
            <div class="spiralengine-pattern-analysis">
                <h4><?php _e('Trigger Patterns', 'spiralengine'); ?></h4>
                <?php if (!empty($time_patterns)): ?>
                    <p><?php _e('Most triggers occur during:', 'spiralengine'); ?></p>
                    <ul>
                        <?php 
                        arsort($time_patterns);
                        $top_time = key($time_patterns);
                        ?>
                        <li><?php echo str_replace('_', ' ', $top_time); ?></li>
                    </ul>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($this->tier_features[$tier]['ai_pattern_detection'])): ?>
            <div class="spiralengine-ai-insights">
                <h4><?php _e('AI Pattern Detection', 'spiralengine'); ?></h4>
                <p class="spiralengine-ai-placeholder"><?php _e('AI will analyze your trigger patterns and provide personalized insights and predictions.', 'spiralengine'); ?></p>
            </div>
        <?php endif; ?>
        
        <?php
        return ob_get_clean();
    }
    
    /**
     * Get daily entry limit for tier
     * 
     * @param string $tier Membership tier
     * @return mixed Integer limit or 'unlimited'
     */
    private function get_daily_limit($tier) {
        $features = $this->tier_features[$tier] ?? $this->tier_features['free'];
        return $features['max_daily_entries'] ?? 3;
    }
    
    /**
     * Get today's entry count for current user
     * 
     * @return int Number of entries today
     */
    private function get_today_entry_count() {
        global $wpdb;
        
        $table = $wpdb->prefix . 'spiralengine_episodes';
        $user_id = get_current_user_id();
        
        // Don't run queries if no user
        if (!$user_id || !$wpdb) {
            return 0;
        }
        
        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table}'") != $table) {
            return 0;
        }
        
        $today = current_time('Y-m-d');
        
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} 
            WHERE user_id = %d 
            AND widget_id = %s 
            AND DATE(created_at) = %s",
            $user_id,
            $this->id,
            $today
        ));
        
        return intval($count);
    }
}

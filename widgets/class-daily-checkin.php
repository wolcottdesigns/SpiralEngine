<?php
/**
 * Daily Check-In Widget
 * 
 * @package    SpiralEngine
 * @subpackage Widgets
 * @file       widgets/class-daily-checkin.php
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Daily Check-In Widget Class
 * 
 * Provides daily mental health check-ins with comprehensive tracking
 * 
 * @since 1.0.0
 */
class SpiralEngine_Widget_Daily_Checkin extends SpiralEngine_Widget {
    
    /**
     * Widget fields configuration
     * 
     * @var array
     */
    protected $fields = array();
    
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
     *
     * @return void
     */
    protected function init() {
        $this->id = 'daily-checkin';
        $this->name = __('Daily Check-In', 'spiralengine');
        $this->description = __('Comprehensive daily mental health check-in', 'spiralengine');
        $this->icon = 'dashicons-calendar-alt';
        $this->min_tier = 'free';
        $this->version = '1.0.0';
        
        // Define tier features
        $this->tier_features = array(
            'free' => array(
                'basic_checkin' => true,
                'mood_rating' => true,
                'energy_level' => true,
                'max_daily_checkins' => 1
            ),
            'silver' => array(
                'basic_checkin' => true,
                'mood_rating' => true,
                'energy_level' => true,
                'sleep_quality' => true,
                'accomplishments' => true,
                'challenges' => true,
                'max_daily_checkins' => 2
            ),
            'gold' => array(
                'basic_checkin' => true,
                'mood_rating' => true,
                'energy_level' => true,
                'sleep_quality' => true,
                'accomplishments' => true,
                'challenges' => true,
                'gratitude' => true,
                'goals_progress' => true,
                'social_interaction' => true,
                'max_daily_checkins' => 3
            ),
            'platinum' => array(
                'basic_checkin' => true,
                'mood_rating' => true,
                'energy_level' => true,
                'sleep_quality' => true,
                'accomplishments' => true,
                'challenges' => true,
                'gratitude' => true,
                'goals_progress' => true,
                'social_interaction' => true,
                'ai_insights' => true,
                'therapist_notes' => true,
                'max_daily_checkins' => 'unlimited'
            )
        );
        
        // Define fields
        $this->fields = array(
            'overall_mood' => array(
                'label' => __('Overall Mood Today', 'spiralengine'),
                'type' => 'range',
                'min' => 1,
                'max' => 10,
                'default' => 5,
                'required' => true,
                'description' => __('1 = Very Low, 10 = Excellent', 'spiralengine'),
                'tier' => 'free'
            ),
            'energy_level' => array(
                'label' => __('Energy Level', 'spiralengine'),
                'type' => 'range',
                'min' => 1,
                'max' => 10,
                'default' => 5,
                'required' => true,
                'description' => __('1 = Exhausted, 10 = Highly Energetic', 'spiralengine'),
                'tier' => 'free'
            ),
            'checkin_time' => array(
                'label' => __('Check-in Time', 'spiralengine'),
                'type' => 'select',
                'options' => array(
                    'morning' => __('Morning (6AM-12PM)', 'spiralengine'),
                    'afternoon' => __('Afternoon (12PM-6PM)', 'spiralengine'),
                    'evening' => __('Evening (6PM-12AM)', 'spiralengine'),
                    'night' => __('Night (12AM-6AM)', 'spiralengine')
                ),
                'required' => true,
                'tier' => 'free'
            ),
            'sleep_quality' => array(
                'label' => __('Last Night\'s Sleep Quality', 'spiralengine'),
                'type' => 'range',
                'min' => 1,
                'max' => 5,
                'default' => 3,
                'description' => __('Rate your sleep quality', 'spiralengine'),
                'tier' => 'silver'
            ),
            'accomplishments' => array(
                'label' => __('Today\'s Accomplishments', 'spiralengine'),
                'type' => 'textarea',
                'rows' => 3,
                'placeholder' => __('What did you accomplish today?', 'spiralengine'),
                'tier' => 'silver'
            ),
            'challenges' => array(
                'label' => __('Today\'s Challenges', 'spiralengine'),
                'type' => 'textarea',
                'rows' => 3,
                'placeholder' => __('What challenges did you face?', 'spiralengine'),
                'tier' => 'silver'
            ),
            'gratitude' => array(
                'label' => __('Gratitude List', 'spiralengine'),
                'type' => 'textarea',
                'rows' => 3,
                'placeholder' => __('List 3 things you\'re grateful for', 'spiralengine'),
                'tier' => 'gold'
            ),
            'goals_progress' => array(
                'label' => __('Progress on Goals', 'spiralengine'),
                'type' => 'textarea',
                'rows' => 3,
                'placeholder' => __('How did you progress on your goals today?', 'spiralengine'),
                'tier' => 'gold'
            ),
            'social_interaction' => array(
                'label' => __('Social Interaction Quality', 'spiralengine'),
                'type' => 'select',
                'options' => array(
                    '' => __('-- Select --', 'spiralengine'),
                    'none' => __('No social interaction', 'spiralengine'),
                    'minimal' => __('Minimal interaction', 'spiralengine'),
                    'moderate' => __('Moderate interaction', 'spiralengine'),
                    'good' => __('Good quality interaction', 'spiralengine'),
                    'excellent' => __('Excellent social connection', 'spiralengine')
                ),
                'tier' => 'gold'
            ),
            'therapist_notes' => array(
                'label' => __('Notes for Therapist', 'spiralengine'),
                'type' => 'textarea',
                'rows' => 4,
                'placeholder' => __('Any notes you want to share with your therapist', 'spiralengine'),
                'tier' => 'platinum'
            ),
            'self_care' => array(
                'label' => __('Self-Care Activities', 'spiralengine'),
                'type' => 'checkbox',
                'options' => array(
                    'exercise' => __('Exercise/Physical Activity', 'spiralengine'),
                    'meditation' => __('Meditation/Mindfulness', 'spiralengine'),
                    'healthy_eating' => __('Healthy Eating', 'spiralengine'),
                    'hydration' => __('Stayed Hydrated', 'spiralengine'),
                    'nature' => __('Time in Nature', 'spiralengine'),
                    'creative' => __('Creative Activities', 'spiralengine'),
                    'social' => __('Social Connection', 'spiralengine'),
                    'rest' => __('Rest/Relaxation', 'spiralengine')
                ),
                'tier' => 'gold'
            ),
            'symptoms_check' => array(
                'label' => __('Symptom Check', 'spiralengine'),
                'type' => 'checkbox',
                'options' => array(
                    'anxiety' => __('Anxiety', 'spiralengine'),
                    'depression' => __('Depression', 'spiralengine'),
                    'irritability' => __('Irritability', 'spiralengine'),
                    'fatigue' => __('Fatigue', 'spiralengine'),
                    'focus_issues' => __('Focus/Concentration Issues', 'spiralengine'),
                    'physical_pain' => __('Physical Pain', 'spiralengine'),
                    'appetite_changes' => __('Appetite Changes', 'spiralengine'),
                    'sleep_issues' => __('Sleep Issues', 'spiralengine')
                ),
                'tier' => 'silver'
            ),
            'notes' => array(
                'label' => __('Additional Notes', 'spiralengine'),
                'type' => 'textarea',
                'rows' => 4,
                'placeholder' => __('Any other thoughts about your day?', 'spiralengine'),
                'tier' => 'free'
            )
        );
    }
    
    /**
     * Get widget configuration
     * 
     * @return array Configuration array
     */
    public function get_config() {
        // Get base config without schema to avoid recursion
        $config = [
            'id'           => $this->id,
            'name'         => $this->name,
            'description'  => $this->description,
            'version'      => $this->version,
            'icon'         => $this->icon,
            'min_tier'     => $this->min_tier,
            'capabilities' => $this->capabilities,
            'settings'     => $this->settings,
            'fields'       => $this->fields,
            'tier_features' => $this->tier_features
        ];
        
        return $config;
    }
    
    /**
     * Get widget data schema
     *
     * @return array Schema definition
     */
    public function get_schema() {
        $schema = [];
        
        // Build schema from fields without calling get_config()
        foreach ($this->fields as $field_id => $field) {
            $schema[$field_id] = [
                'type' => $field['type'],
                'label' => $field['label'],
                'required' => $field['required'] ?? false,
                'tier' => $field['tier'] ?? 'free',
                'options' => $field['options'] ?? null,
                'min' => $field['min'] ?? null,
                'max' => $field['max'] ?? null,
                'default' => $field['default'] ?? null
            ];
        }
        
        return $schema;
    }
    
    /**
     * Validate widget data
     * 
     * @param array $data Data to validate
     * @return array|WP_Error Validated data or error
     */
    public function validate_data($data) {
        $errors = array();
        $validated = array();
        $tier = $this->get_user_tier();
        
        // Check daily limit
        $daily_limit = $this->get_daily_limit($tier);
        if ($daily_limit !== 'unlimited') {
            $today_count = $this->get_today_checkin_count();
            if ($today_count >= $daily_limit) {
                $error_message = sprintf(
                    __('You have reached your daily check-in limit of %d. Upgrade to increase your limit.', 'spiralengine'),
                    $daily_limit
                );
                return new WP_Error('daily_limit_reached', $error_message);
            }
        }
        
        // Validate overall mood
        if (empty($data['overall_mood']) || $data['overall_mood'] < 1 || $data['overall_mood'] > 10) {
            $errors[] = __('Overall mood rating is required (1-10).', 'spiralengine');
        } else {
            $validated['overall_mood'] = intval($data['overall_mood']);
        }
        
        // Validate energy level
        if (empty($data['energy_level']) || $data['energy_level'] < 1 || $data['energy_level'] > 10) {
            $errors[] = __('Energy level rating is required (1-10).', 'spiralengine');
        } else {
            $validated['energy_level'] = intval($data['energy_level']);
        }
        
        // Validate check-in time
        $valid_times = array('morning', 'afternoon', 'evening', 'night');
        if (empty($data['checkin_time']) || !in_array($data['checkin_time'], $valid_times)) {
            $errors[] = __('Please select a valid check-in time.', 'spiralengine');
        } else {
            $validated['checkin_time'] = $data['checkin_time'];
        }
        
        // Validate sleep quality if provided
        if (!empty($data['sleep_quality'])) {
            $sleep_quality = intval($data['sleep_quality']);
            if ($sleep_quality < 1 || $sleep_quality > 5) {
                $errors[] = __('Sleep quality must be between 1 and 5.', 'spiralengine');
            } else {
                $validated['sleep_quality'] = $sleep_quality;
            }
        }
        
        // Validate text fields
        $text_fields = ['accomplishments', 'challenges', 'gratitude', 'goals_progress', 'therapist_notes', 'notes'];
        foreach ($text_fields as $field) {
            if (!empty($data[$field])) {
                $validated[$field] = sanitize_textarea_field($data[$field]);
            }
        }
        
        // Validate social interaction
        if (!empty($data['social_interaction'])) {
            $valid_interactions = ['none', 'minimal', 'moderate', 'good', 'excellent'];
            if (in_array($data['social_interaction'], $valid_interactions)) {
                $validated['social_interaction'] = $data['social_interaction'];
            }
        }
        
        // Validate checkboxes (arrays)
        if (!empty($data['self_care']) && is_array($data['self_care'])) {
            $valid_selfcare = ['exercise', 'meditation', 'healthy_eating', 'hydration', 'nature', 'creative', 'social', 'rest'];
            $validated['self_care'] = array_intersect($data['self_care'], $valid_selfcare);
        }
        
        if (!empty($data['symptoms_check']) && is_array($data['symptoms_check'])) {
            $valid_symptoms = ['anxiety', 'depression', 'irritability', 'fatigue', 'focus_issues', 'physical_pain', 'appetite_changes', 'sleep_issues'];
            $validated['symptoms_check'] = array_intersect($data['symptoms_check'], $valid_symptoms);
        }
        
        if (!empty($errors)) {
            return new WP_Error('validation_failed', implode(' ', $errors), $errors);
        }
        
        return $validated;
    }
    
    /**
     * Process widget data before saving
     * 
     * @param array $data Data to process
     * @return int|false Episode ID or false on failure
     */
    public function process_data($data) {
        // Validate data first
        $validated = $this->validate_data($data);
        if (is_wp_error($validated)) {
            return false;
        }
        
        // Calculate severity based on mood and energy
        $mood = intval($validated['overall_mood']);
        $energy = intval($validated['energy_level']);
        
        // Inverse scale - lower mood/energy = higher severity
        $severity = 11 - round(($mood + $energy) / 2);
        
        // Add severity to data for parent process_data
        $validated['severity'] = $severity;
        
        // Add calculated fields
        $validated['calculated'] = array(
            'wellness_score' => ($mood + $energy) / 2,
            'checkin_date' => current_time('Y-m-d'),
            'checkin_hour' => current_time('H'),
            'day_of_week' => current_time('l')
        );
        
        // Count symptoms if provided
        if (!empty($validated['symptoms_check']) && is_array($validated['symptoms_check'])) {
            $validated['calculated']['symptom_count'] = count($validated['symptoms_check']);
        }
        
        // Count self-care activities
        if (!empty($validated['self_care']) && is_array($validated['self_care'])) {
            $validated['calculated']['selfcare_count'] = count($validated['self_care']);
        }
        
        // Call parent process_data to save
        return parent::process_data($validated);
    }
    
    /**
     * Render widget form
     *
     * @param array $args Form arguments
     * @return string HTML output
     */
    public function render_form($args = []) {
        $fields = $this->fields;
        
        // Add check-in count display
        $tier = $this->get_user_tier();
        $limit = $this->get_daily_limit($tier);
        $count = $this->get_today_checkin_count();
        
        ob_start();
        
        if ($limit !== 'unlimited') {
            $remaining = $limit - $count;
            $message = sprintf(
                __('Check-ins today: %d/%d', 'spiralengine'),
                $count,
                $limit
            );
            
            if ($remaining <= 0) {
                $message .= ' - ' . __('Daily limit reached. Upgrade for more check-ins!', 'spiralengine');
            }
            
            echo '<div class="spiralengine-checkin-limit">' . esc_html($message) . '</div>';
        }
        ?>
        
        <form class="spiralengine-widget-form" id="spiralengine-<?php echo esc_attr($this->id); ?>-form" data-widget="<?php echo esc_attr($this->id); ?>">
            <?php wp_nonce_field('spiralengine_' . $this->id . '_nonce', 'nonce'); ?>
            
            <div class="spiralengine-form-fields">
                <?php foreach ($fields as $field_id => $field) : ?>
                    <?php if ($this->can_use_feature($field['tier'] ?? 'free')) : ?>
                        <div class="spiralengine-field-wrapper spiralengine-field-<?php echo esc_attr($field_id); ?>">
                            <?php
                            // Handle checkbox groups differently
                            if ($field['type'] === 'checkbox' && isset($field['options'])) {
                                echo '<label class="spiralengine-field-label">' . esc_html($field['label']) . '</label>';
                                echo '<div class="spiralengine-checkbox-group">';
                                foreach ($field['options'] as $value => $label) {
                                    $field_name = $field_id . '[]';
                                    $field_id_attr = $this->id . '_' . $field_id . '_' . $value;
                                    echo '<label for="' . esc_attr($field_id_attr) . '">';
                                    echo '<input type="checkbox" id="' . esc_attr($field_id_attr) . '" ';
                                    echo 'name="' . esc_attr($field_name) . '" ';
                                    echo 'value="' . esc_attr($value) . '"> ';
                                    echo esc_html($label);
                                    echo '</label>';
                                }
                                echo '</div>';
                            } else {
                                echo $this->render_field($field['type'], array_merge($field, [
                                    'id' => $this->id . '_' . $field_id,
                                    'name' => $field_id,
                                    'value' => $args[$field_id] ?? $field['default'] ?? ''
                                ]));
                            }
                            ?>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
            
            <div class="spiralengine-form-actions">
                <button type="submit" class="button button-primary spiralengine-save-button">
                    <?php _e('Save Check-In', 'spiralengine'); ?>
                </button>
                <button type="button" class="button spiralengine-cancel-button">
                    <?php _e('Cancel', 'spiralengine'); ?>
                </button>
            </div>
        </form>
        <?php
        return ob_get_clean();
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
        
        // For now, return a simple placeholder
        ob_start();
        ?>
        <div class="spiralengine-widget-analytics">
            <h3><?php echo esc_html($this->name); ?> <?php _e('Analytics', 'spiralengine'); ?></h3>
            <p><?php _e('Analytics for your daily check-ins will be displayed here.', 'spiralengine'); ?></p>
            
            <div class="spiralengine-analytics-summary">
                <div class="stat-box">
                    <span class="stat-label"><?php _e('Total Check-ins', 'spiralengine'); ?></span>
                    <span class="stat-value">0</span>
                </div>
                <div class="stat-box">
                    <span class="stat-label"><?php _e('Average Mood', 'spiralengine'); ?></span>
                    <span class="stat-value">0</span>
                </div>
                <div class="stat-box">
                    <span class="stat-label"><?php _e('Average Energy', 'spiralengine'); ?></span>
                    <span class="stat-value">0</span>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Get daily check-in limit for tier
     * 
     * @param string $tier Membership tier
     * @return mixed Integer limit or 'unlimited'
     */
    private function get_daily_limit($tier) {
        $features = isset($this->tier_features[$tier]) ? $this->tier_features[$tier] : $this->tier_features['free'];
        return isset($features['max_daily_checkins']) ? $features['max_daily_checkins'] : 1;
    }
    
    /**
     * Get today's check-in count for current user
     * 
     * @return int Number of check-ins today
     */
    private function get_today_checkin_count() {
        global $wpdb;
        
        $table = $wpdb->prefix . 'spiralengine_episodes';
        $user_id = get_current_user_id();
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
    
    /**
     * Get user tier
     *
     * @return string
     */
    protected function get_user_tier() {
        $user_id = get_current_user_id();
        if (!$user_id) {
            return 'free';
        }
        
        $tier = get_user_meta($user_id, 'spiralengine_membership_tier', true);
        return !empty($tier) ? $tier : 'free';
    }
    
    /**
     * Check if user can use a tier feature
     *
     * @param string $required_tier Required tier
     * @return bool
     */
    protected function can_use_feature($required_tier = 'free') {
        if ($required_tier === 'free') {
            return true;
        }
        
        $user_id = get_current_user_id();
        if (!$user_id) {
            return false;
        }
        
        // Get user's tier
        $user_tier = get_user_meta($user_id, 'spiralengine_membership_tier', true);
        if (empty($user_tier)) {
            $user_tier = 'free';
        }
        
        // Define tier hierarchy
        $tier_hierarchy = array(
            'free' => 0,
            'bronze' => 1,
            'silver' => 2,
            'gold' => 3,
            'platinum' => 4
        );
        
        $user_tier_level = isset($tier_hierarchy[$user_tier]) ? $tier_hierarchy[$user_tier] : 0;
        $required_tier_level = isset($tier_hierarchy[$required_tier]) ? $tier_hierarchy[$required_tier] : 0;
        
        return $user_tier_level >= $required_tier_level;
    }
}

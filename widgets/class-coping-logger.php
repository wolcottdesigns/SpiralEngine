<?php
/**
 * Coping Skills Logger Widget
 * 
 * @package    SpiralEngine
 * @subpackage Widgets
 * @file       widgets/class-coping-logger.php
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Coping Skills Logger Widget Class
 * 
 * Tracks coping strategies and their effectiveness
 * 
 * @since 1.0.0
 */
class SpiralEngine_Widget_Coping_Logger extends SpiralEngine_Widget {
    
    /**
     * Widget fields configuration
     * 
     * @var array
     */
    protected $fields = array();
    
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
        $this->id = 'coping-logger';
        $this->name = __('Coping Skills Logger', 'spiralengine');
        $this->description = __('Track coping strategies and their effectiveness', 'spiralengine');
        $this->icon = 'dashicons-shield';
        $this->min_tier = 'free';
        $this->version = '1.0.0';
        
        // Define fields once during init
        $this->fields = array(
            'skill_category' => array(
                'label' => __('Coping Skill Category', 'spiralengine'),
                'type' => 'select',
                'options' => array(
                    '' => __('-- Select Category --', 'spiralengine'),
                    'grounding' => __('Grounding Techniques', 'spiralengine'),
                    'breathing' => __('Breathing Exercises', 'spiralengine'),
                    'mindfulness' => __('Mindfulness/Meditation', 'spiralengine'),
                    'physical' => __('Physical Activity', 'spiralengine'),
                    'creative' => __('Creative Expression', 'spiralengine'),
                    'social' => __('Social Support', 'spiralengine'),
                    'distraction' => __('Healthy Distraction', 'spiralengine'),
                    'self_soothing' => __('Self-Soothing', 'spiralengine'),
                    'cognitive' => __('Cognitive Techniques', 'spiralengine'),
                    'professional' => __('Professional Tools', 'spiralengine'),
                    'other' => __('Other', 'spiralengine')
                ),
                'required' => true,
                'tier' => 'free'
            ),
            'skill_used' => array(
                'label' => __('Specific Skill Used', 'spiralengine'),
                'type' => 'text',
                'required' => true,
                'placeholder' => __('e.g., 5-4-3-2-1 grounding, box breathing', 'spiralengine'),
                'tier' => 'free'
            ),
            'effectiveness' => array(
                'label' => __('How Effective Was It?', 'spiralengine'),
                'type' => 'range',
                'min' => 1,
                'max' => 10,
                'default' => 5,
                'required' => true,
                'description' => __('1 = Not effective, 10 = Very effective', 'spiralengine'),
                'tier' => 'free'
            ),
            'would_use_again' => array(
                'label' => __('Would You Use This Again?', 'spiralengine'),
                'type' => 'radio',
                'options' => array(
                    'yes' => __('Yes, definitely', 'spiralengine'),
                    'maybe' => __('Maybe, with modifications', 'spiralengine'),
                    'situation' => __('Only in certain situations', 'spiralengine'),
                    'no' => __('No, wasn\'t helpful', 'spiralengine')
                ),
                'default' => 'yes',
                'tier' => 'free'
            ),
            'notes' => array(
                'label' => __('Additional Notes', 'spiralengine'),
                'type' => 'textarea',
                'rows' => 3,
                'placeholder' => __('Any other observations or thoughts', 'spiralengine'),
                'tier' => 'free'
            ),
            'situation' => array(
                'label' => __('What Situation Did You Use It For?', 'spiralengine'),
                'type' => 'textarea',
                'rows' => 2,
                'placeholder' => __('Briefly describe the situation', 'spiralengine'),
                'tier' => 'silver'
            ),
            'mood_before' => array(
                'label' => __('Mood Before Using Skill', 'spiralengine'),
                'type' => 'range',
                'min' => 1,
                'max' => 10,
                'default' => 5,
                'description' => __('1 = Very distressed, 10 = Calm', 'spiralengine'),
                'tier' => 'silver'
            ),
            'mood_after' => array(
                'label' => __('Mood After Using Skill', 'spiralengine'),
                'type' => 'range',
                'min' => 1,
                'max' => 10,
                'default' => 5,
                'description' => __('1 = Very distressed, 10 = Calm', 'spiralengine'),
                'tier' => 'silver'
            ),
            'time_taken' => array(
                'label' => __('How Long Did You Use It?', 'spiralengine'),
                'type' => 'select',
                'options' => array(
                    '' => __('-- Select Duration --', 'spiralengine'),
                    'under_1' => __('Less than 1 minute', 'spiralengine'),
                    '1_5' => __('1-5 minutes', 'spiralengine'),
                    '5_10' => __('5-10 minutes', 'spiralengine'),
                    '10_20' => __('10-20 minutes', 'spiralengine'),
                    '20_30' => __('20-30 minutes', 'spiralengine'),
                    '30_60' => __('30-60 minutes', 'spiralengine'),
                    'over_60' => __('Over 1 hour', 'spiralengine')
                ),
                'tier' => 'silver'
            ),
            'skill_source' => array(
                'label' => __('Where Did You Learn This?', 'spiralengine'),
                'type' => 'select',
                'options' => array(
                    '' => __('-- Select Source --', 'spiralengine'),
                    'therapist' => __('Therapist/Counselor', 'spiralengine'),
                    'app' => __('This App', 'spiralengine'),
                    'book' => __('Book/Article', 'spiralengine'),
                    'video' => __('Video/Online', 'spiralengine'),
                    'group' => __('Support Group', 'spiralengine'),
                    'friend' => __('Friend/Family', 'spiralengine'),
                    'self' => __('Self-Developed', 'spiralengine'),
                    'other' => __('Other', 'spiralengine')
                ),
                'tier' => 'silver'
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
            'fields'       => $this->fields
        ];
        
        return $config;
    }
    
    /**
     * Render widget form
     *
     * @param array $args Form arguments
     * @return string HTML output
     */
    public function render_form($args = []) {
        $fields = $this->fields;
        
        ob_start();
        ?>
        <form class="spiralengine-widget-form" id="spiralengine-<?php echo esc_attr($this->id); ?>-form" data-widget="<?php echo esc_attr($this->id); ?>">
            <?php wp_nonce_field('spiralengine_' . $this->id . '_nonce', 'nonce'); ?>
            
            <div class="spiralengine-form-fields">
                <?php foreach ($fields as $field_id => $field) : ?>
                    <?php if ($this->can_use_feature($field['tier'] ?? 'free')) : ?>
                        <div class="spiralengine-field-wrapper spiralengine-field-<?php echo esc_attr($field_id); ?>">
                            <?php echo $this->render_field($field['type'], array_merge($field, [
                                'id' => $this->id . '_' . $field_id,
                                'name' => $field_id,
                                'value' => $args[$field_id] ?? $field['default'] ?? ''
                            ])); ?>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
            
            <div class="spiralengine-form-actions">
                <button type="submit" class="button button-primary spiralengine-save-button">
                    <?php _e('Save Episode', 'spiralengine'); ?>
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
        $schema = $this->get_schema();
        
        foreach ($schema as $field_id => $field_schema) {
            // Skip fields user doesn't have access to
            if (!$this->can_use_feature($field_schema['tier'])) {
                continue;
            }
            
            $value = isset($data[$field_id]) ? $data[$field_id] : null;
            
            // Check required fields
            if ($field_schema['required'] && empty($value)) {
                $errors[] = sprintf(__('%s is required.', 'spiralengine'), $field_schema['label']);
                continue;
            }
            
            // Validate based on type
            switch ($field_schema['type']) {
                case 'range':
                case 'number':
                    if (!empty($value)) {
                        $value = intval($value);
                        if ($field_schema['min'] !== null && $value < $field_schema['min']) {
                            $errors[] = sprintf(__('%s must be at least %d.', 'spiralengine'), $field_schema['label'], $field_schema['min']);
                        }
                        if ($field_schema['max'] !== null && $value > $field_schema['max']) {
                            $errors[] = sprintf(__('%s must be at most %d.', 'spiralengine'), $field_schema['label'], $field_schema['max']);
                        }
                    }
                    break;
                    
                case 'select':
                case 'radio':
                    if (!empty($value) && $field_schema['options'] && !array_key_exists($value, $field_schema['options'])) {
                        $errors[] = sprintf(__('Invalid value for %s.', 'spiralengine'), $field_schema['label']);
                    }
                    break;
                    
                case 'text':
                case 'textarea':
                    if (!empty($value)) {
                        $value = sanitize_text_field($value);
                    }
                    break;
            }
            
            if (!empty($value) || $value === 0 || $value === '0') {
                $validated[$field_id] = $value;
            }
        }
        
        // Additional validation for mood ratings
        if (!empty($validated['mood_before']) && empty($validated['mood_after'])) {
            $errors[] = __('Please also rate your mood after using the skill.', 'spiralengine');
        }
        if (!empty($validated['mood_after']) && empty($validated['mood_before'])) {
            $errors[] = __('Please also rate your mood before using the skill.', 'spiralengine');
        }
        
        if (!empty($errors)) {
            return new WP_Error('validation_failed', implode(' ', $errors), $errors);
        }
        
        return $validated;
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
            <p><?php _e('Analytics for this widget will be displayed here.', 'spiralengine'); ?></p>
            
            <div class="spiralengine-analytics-summary">
                <div class="stat-box">
                    <span class="stat-label"><?php _e('Total Episodes', 'spiralengine'); ?></span>
                    <span class="stat-value">0</span>
                </div>
                <div class="stat-box">
                    <span class="stat-label"><?php _e('Average Effectiveness', 'spiralengine'); ?></span>
                    <span class="stat-value">0</span>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
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
    
    /**
     * Process widget data before saving
     * 
     * @param array $data Data to process
     * @return array Processed data
     */
    public function process_data($data) {
        // Get validated data first
        $validated = $this->validate_data($data);
        if (is_wp_error($validated)) {
            return false;
        }
        
        // Calculate severity (inverse of effectiveness for coping skills)
        $effectiveness = isset($validated['effectiveness']) ? intval($validated['effectiveness']) : 5;
        $severity = 11 - $effectiveness; // Higher effectiveness = lower severity
        
        // Add severity to data for parent process_data
        $validated['severity'] = $severity;
        
        // Add calculated fields
        $validated['calculated'] = array(
            'skill_date' => current_time('Y-m-d'),
            'skill_time' => current_time('H:i:s'),
            'day_of_week' => current_time('l')
        );
        
        // Calculate mood improvement if both ratings provided
        if (!empty($validated['mood_before']) && !empty($validated['mood_after'])) {
            $mood_improvement = intval($validated['mood_after']) - intval($validated['mood_before']);
            $validated['calculated']['mood_improvement'] = $mood_improvement;
            $validated['calculated']['mood_improvement_percent'] = round(($mood_improvement / intval($validated['mood_before'])) * 100);
        }
        
        // Call parent process_data to save
        return parent::process_data($validated);
    }
}

<?php
/**
 * Goal Setting Widget
 * 
 * @package    SpiralEngine
 * @subpackage Widgets
 * @file       widgets/class-goal-setting.php
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Goal Setting Widget Class
 * 
 * Helps users set and track mental health goals
 * 
 * @since 1.0.0
 */
class SpiralEngine_Widget_Goal_Setting extends SpiralEngine_Widget {
    
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
        $this->id = 'goal-setting';
        $this->name = __('Goal Setting', 'spiralengine');
        $this->description = __('Set and track your mental health goals', 'spiralengine');
        $this->icon = 'dashicons-flag';
        $this->min_tier = 'free';
        $this->version = '1.0.0';
        
        // Define tier features
        $this->tier_features = array(
            'free' => array(
                'basic_goals' => true,
                'goal_tracking' => true,
                'max_active_goals' => 3
            ),
            'silver' => array(
                'basic_goals' => true,
                'goal_tracking' => true,
                'smart_goals' => true,
                'milestone_tracking' => true,
                'goal_categories' => true,
                'max_active_goals' => 5
            ),
            'gold' => array(
                'basic_goals' => true,
                'goal_tracking' => true,
                'smart_goals' => true,
                'milestone_tracking' => true,
                'goal_categories' => true,
                'goal_templates' => true,
                'accountability_partners' => true,
                'reminder_system' => true,
                'max_active_goals' => 10
            ),
            'platinum' => array(
                'basic_goals' => true,
                'goal_tracking' => true,
                'smart_goals' => true,
                'milestone_tracking' => true,
                'goal_categories' => true,
                'goal_templates' => true,
                'accountability_partners' => true,
                'reminder_system' => true,
                'ai_goal_suggestions' => true,
                'therapist_collaboration' => true,
                'max_active_goals' => 'unlimited'
            )
        );
        
        // Define fields
        $this->fields = array(
            'action_type' => array(
                'label' => __('Action', 'spiralengine'),
                'type' => 'select',
                'options' => array(
                    '' => __('-- Select Action --', 'spiralengine'),
                    'new_goal' => __('Set New Goal', 'spiralengine'),
                    'update_progress' => __('Update Goal Progress', 'spiralengine'),
                    'complete_goal' => __('Mark Goal Complete', 'spiralengine'),
                    'pause_goal' => __('Pause Goal', 'spiralengine'),
                    'reflect' => __('Goal Reflection', 'spiralengine')
                ),
                'required' => true,
                'tier' => 'free'
            ),
            'goal_title' => array(
                'label' => __('Goal Title', 'spiralengine'),
                'type' => 'text',
                'required' => true,
                'placeholder' => __('What do you want to achieve?', 'spiralengine'),
                'conditional' => array(
                    'field' => 'action_type',
                    'value' => 'new_goal'
                ),
                'tier' => 'free'
            ),
            'existing_goal' => array(
                'label' => __('Select Goal', 'spiralengine'),
                'type' => 'select',
                'options_callback' => array($this, 'get_active_goals_options'),
                'required' => true,
                'conditional' => array(
                    'field' => 'action_type',
                    'value' => array('update_progress', 'complete_goal', 'pause_goal')
                ),
                'tier' => 'free'
            ),
            'goal_category' => array(
                'label' => __('Goal Category', 'spiralengine'),
                'type' => 'select',
                'options' => array(
                    '' => __('-- Select Category --', 'spiralengine'),
                    'mental_health' => __('Mental Health', 'spiralengine'),
                    'physical_health' => __('Physical Health', 'spiralengine'),
                    'relationships' => __('Relationships', 'spiralengine'),
                    'career' => __('Career/Work', 'spiralengine'),
                    'personal_growth' => __('Personal Growth', 'spiralengine'),
                    'habits' => __('Habits & Routines', 'spiralengine'),
                    'therapy' => __('Therapy Goals', 'spiralengine'),
                    'recovery' => __('Recovery', 'spiralengine'),
                    'other' => __('Other', 'spiralengine')
                ),
                'conditional' => array(
                    'field' => 'action_type',
                    'value' => 'new_goal'
                ),
                'tier' => 'silver'
            ),
            'goal_description' => array(
                'label' => __('Goal Description', 'spiralengine'),
                'type' => 'textarea',
                'rows' => 3,
                'placeholder' => __('Describe your goal in detail', 'spiralengine'),
                'conditional' => array(
                    'field' => 'action_type',
                    'value' => 'new_goal'
                ),
                'tier' => 'free'
            ),
            'goal_type' => array(
                'label' => __('Goal Type', 'spiralengine'),
                'type' => 'radio',
                'options' => array(
                    'outcome' => __('Outcome Goal (achieve a result)', 'spiralengine'),
                    'process' => __('Process Goal (maintain a practice)', 'spiralengine'),
                    'habit' => __('Habit Goal (build/break a habit)', 'spiralengine')
                ),
                'default' => 'outcome',
                'conditional' => array(
                    'field' => 'action_type',
                    'value' => 'new_goal'
                ),
                'tier' => 'silver'
            ),
            'target_date' => array(
                'label' => __('Target Completion Date', 'spiralengine'),
                'type' => 'date',
                'conditional' => array(
                    'field' => 'action_type',
                    'value' => 'new_goal'
                ),
                'tier' => 'free'
            ),
            'motivation' => array(
                'label' => __('Why is this goal important to you?', 'spiralengine'),
                'type' => 'textarea',
                'rows' => 3,
                'placeholder' => __('Your motivation and reasons', 'spiralengine'),
                'conditional' => array(
                    'field' => 'action_type',
                    'value' => 'new_goal'
                ),
                'tier' => 'free'
            ),
            'obstacles' => array(
                'label' => __('Potential Obstacles', 'spiralengine'),
                'type' => 'textarea',
                'rows' => 2,
                'placeholder' => __('What might get in your way?', 'spiralengine'),
                'conditional' => array(
                    'field' => 'action_type',
                    'value' => 'new_goal'
                ),
                'tier' => 'silver'
            ),
            'strategies' => array(
                'label' => __('Success Strategies', 'spiralengine'),
                'type' => 'textarea',
                'rows' => 3,
                'placeholder' => __('How will you overcome obstacles?', 'spiralengine'),
                'conditional' => array(
                    'field' => 'action_type',
                    'value' => 'new_goal'
                ),
                'tier' => 'silver'
            ),
            'accountability_partner' => array(
                'label' => __('Accountability Partner', 'spiralengine'),
                'type' => 'text',
                'placeholder' => __('Who will help keep you accountable?', 'spiralengine'),
                'conditional' => array(
                    'field' => 'action_type',
                    'value' => 'new_goal'
                ),
                'tier' => 'gold'
            ),
            'reminder_frequency' => array(
                'label' => __('Reminder Frequency', 'spiralengine'),
                'type' => 'select',
                'options' => array(
                    '' => __('No reminders', 'spiralengine'),
                    'daily' => __('Daily', 'spiralengine'),
                    'weekly' => __('Weekly', 'spiralengine'),
                    'biweekly' => __('Bi-weekly', 'spiralengine'),
                    'monthly' => __('Monthly', 'spiralengine')
                ),
                'conditional' => array(
                    'field' => 'action_type',
                    'value' => 'new_goal'
                ),
                'tier' => 'gold'
            ),
            'progress_update' => array(
                'label' => __('Progress Update', 'spiralengine'),
                'type' => 'range',
                'min' => 0,
                'max' => 100,
                'default' => 0,
                'description' => __('Percentage complete', 'spiralengine'),
                'conditional' => array(
                    'field' => 'action_type',
                    'value' => 'update_progress'
                ),
                'tier' => 'free'
            ),
            'progress_notes' => array(
                'label' => __('Progress Notes', 'spiralengine'),
                'type' => 'textarea',
                'rows' => 3,
                'placeholder' => __('What progress have you made?', 'spiralengine'),
                'conditional' => array(
                    'field' => 'action_type',
                    'value' => 'update_progress'
                ),
                'tier' => 'free'
            ),
            'completion_reflection' => array(
                'label' => __('Completion Reflection', 'spiralengine'),
                'type' => 'textarea',
                'rows' => 4,
                'placeholder' => __('How do you feel about completing this goal?', 'spiralengine'),
                'conditional' => array(
                    'field' => 'action_type',
                    'value' => 'complete_goal'
                ),
                'tier' => 'free'
            ),
            'lessons_learned' => array(
                'label' => __('Lessons Learned', 'spiralengine'),
                'type' => 'textarea',
                'rows' => 3,
                'placeholder' => __('What did you learn from pursuing this goal?', 'spiralengine'),
                'conditional' => array(
                    'field' => 'action_type',
                    'value' => array('complete_goal', 'reflect')
                ),
                'tier' => 'silver'
            ),
            'pause_reason' => array(
                'label' => __('Reason for Pausing', 'spiralengine'),
                'type' => 'textarea',
                'rows' => 2,
                'placeholder' => __('Why are you pausing this goal?', 'spiralengine'),
                'conditional' => array(
                    'field' => 'action_type',
                    'value' => 'pause_goal'
                ),
                'tier' => 'free'
            ),
            'resume_date' => array(
                'label' => __('Expected Resume Date', 'spiralengine'),
                'type' => 'date',
                'conditional' => array(
                    'field' => 'action_type',
                    'value' => 'pause_goal'
                ),
                'tier' => 'silver'
            ),
            'share_with_therapist' => array(
                'label' => __('Share with Therapist?', 'spiralengine'),
                'type' => 'radio',
                'options' => array(
                    'yes' => __('Yes, share this goal', 'spiralengine'),
                    'no' => __('No, keep private', 'spiralengine')
                ),
                'default' => 'no',
                'tier' => 'platinum'
            ),
            'notes' => array(
                'label' => __('Additional Notes', 'spiralengine'),
                'type' => 'textarea',
                'rows' => 3,
                'placeholder' => __('Any other thoughts or notes', 'spiralengine'),
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
                        <div class="spiralengine-field-wrapper spiralengine-field-<?php echo esc_attr($field_id); ?>" 
                             <?php if (!empty($field['conditional'])): ?>
                             data-conditional-field="<?php echo esc_attr($field['conditional']['field']); ?>"
                             data-conditional-value="<?php echo esc_attr(is_array($field['conditional']['value']) ? implode(',', $field['conditional']['value']) : $field['conditional']['value']); ?>"
                             style="display: none;"
                             <?php endif; ?>>
                            <?php
                            // Handle special field types
                            if ($field['type'] === 'custom' && isset($field['render_callback'])) {
                                echo call_user_func($field['render_callback'], $field, $args[$field_id] ?? null);
                            } elseif (isset($field['options_callback'])) {
                                // Handle dynamic options
                                $field['options'] = call_user_func($field['options_callback']);
                                echo $this->render_field($field['type'], array_merge($field, [
                                    'id' => $this->id . '_' . $field_id,
                                    'name' => $field_id,
                                    'value' => $args[$field_id] ?? $field['default'] ?? ''
                                ]));
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
                    <?php _e('Save Goal Action', 'spiralengine'); ?>
                </button>
                <button type="button" class="button spiralengine-cancel-button">
                    <?php _e('Cancel', 'spiralengine'); ?>
                </button>
            </div>
        </form>
        
        <script>
        jQuery(document).ready(function($) {
            // Handle conditional fields
            $('#goal-setting_action_type').on('change', function() {
                var selectedValue = $(this).val();
                $('.spiralengine-field-wrapper[data-conditional-field="action_type"]').each(function() {
                    var conditionalValues = $(this).data('conditional-value').toString().split(',');
                    if (conditionalValues.includes(selectedValue)) {
                        $(this).show();
                    } else {
                        $(this).hide();
                    }
                });
            });
        });
        </script>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Get active goals options
     * 
     * @return array Options array
     */
    public function get_active_goals_options() {
        $goals = $this->get_user_active_goals();
        $options = array('' => __('-- Select Goal --', 'spiralengine'));
        
        foreach ($goals as $goal_id => $goal) {
            $progress = isset($goal['progress']) ? ' (' . $goal['progress'] . '%)' : '';
            $options[$goal_id] = $goal['title'] . $progress;
        }
        
        return $options;
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
        
        // Validate action type
        if (empty($data['action_type'])) {
            $errors[] = __('Please select an action.', 'spiralengine');
        } else {
            $validated['action_type'] = $data['action_type'];
        }
        
        // Validate based on action type
        switch ($data['action_type'] ?? '') {
            case 'new_goal':
                // Check goal limit
                $tier = $this->get_user_tier();
                $max_goals = $this->tier_features[$tier]['max_active_goals'] ?? 3;
                if ($max_goals !== 'unlimited') {
                    $active_goals = count($this->get_user_active_goals());
                    if ($active_goals >= $max_goals) {
                        $errors[] = sprintf(
                            __('You have reached your limit of %d active goals. Complete or pause a goal to add new ones.', 'spiralengine'),
                            $max_goals
                        );
                    }
                }
                
                if (empty($data['goal_title'])) {
                    $errors[] = __('Please enter a goal title.', 'spiralengine');
                } else {
                    $validated['goal_title'] = sanitize_text_field($data['goal_title']);
                }
                
                if (empty($data['motivation'])) {
                    $errors[] = __('Please describe why this goal is important to you.', 'spiralengine');
                } else {
                    $validated['motivation'] = sanitize_textarea_field($data['motivation']);
                }
                
                // Validate optional fields
                if (!empty($data['goal_description'])) {
                    $validated['goal_description'] = sanitize_textarea_field($data['goal_description']);
                }
                if (!empty($data['goal_category'])) {
                    $validated['goal_category'] = sanitize_text_field($data['goal_category']);
                }
                if (!empty($data['goal_type'])) {
                    $validated['goal_type'] = sanitize_text_field($data['goal_type']);
                }
                if (!empty($data['target_date'])) {
                    $validated['target_date'] = sanitize_text_field($data['target_date']);
                }
                if (!empty($data['obstacles'])) {
                    $validated['obstacles'] = sanitize_textarea_field($data['obstacles']);
                }
                if (!empty($data['strategies'])) {
                    $validated['strategies'] = sanitize_textarea_field($data['strategies']);
                }
                if (!empty($data['accountability_partner'])) {
                    $validated['accountability_partner'] = sanitize_text_field($data['accountability_partner']);
                }
                if (!empty($data['reminder_frequency'])) {
                    $validated['reminder_frequency'] = sanitize_text_field($data['reminder_frequency']);
                }
                break;
                
            case 'update_progress':
                if (empty($data['existing_goal'])) {
                    $errors[] = __('Please select a goal.', 'spiralengine');
                } else {
                    $validated['existing_goal'] = sanitize_text_field($data['existing_goal']);
                }
                if (isset($data['progress_update'])) {
                    $validated['progress_update'] = intval($data['progress_update']);
                }
                if (!empty($data['progress_notes'])) {
                    $validated['progress_notes'] = sanitize_textarea_field($data['progress_notes']);
                }
                break;
                
            case 'complete_goal':
                if (empty($data['existing_goal'])) {
                    $errors[] = __('Please select a goal.', 'spiralengine');
                } else {
                    $validated['existing_goal'] = sanitize_text_field($data['existing_goal']);
                }
                if (!empty($data['completion_reflection'])) {
                    $validated['completion_reflection'] = sanitize_textarea_field($data['completion_reflection']);
                }
                if (!empty($data['lessons_learned'])) {
                    $validated['lessons_learned'] = sanitize_textarea_field($data['lessons_learned']);
                }
                break;
                
            case 'pause_goal':
                if (empty($data['existing_goal'])) {
                    $errors[] = __('Please select a goal.', 'spiralengine');
                } else {
                    $validated['existing_goal'] = sanitize_text_field($data['existing_goal']);
                }
                if (!empty($data['pause_reason'])) {
                    $validated['pause_reason'] = sanitize_textarea_field($data['pause_reason']);
                }
                if (!empty($data['resume_date'])) {
                    $validated['resume_date'] = sanitize_text_field($data['resume_date']);
                }
                break;
                
            case 'reflect':
                if (!empty($data['lessons_learned'])) {
                    $validated['lessons_learned'] = sanitize_textarea_field($data['lessons_learned']);
                }
                break;
        }
        
        // Validate common fields
        if (!empty($data['notes'])) {
            $validated['notes'] = sanitize_textarea_field($data['notes']);
        }
        if (!empty($data['share_with_therapist'])) {
            $validated['share_with_therapist'] = sanitize_text_field($data['share_with_therapist']);
        }
        
        // Add severity based on action type
        $severity_map = [
            'new_goal' => 3,      // Low severity - positive action
            'update_progress' => 4, // Low-moderate severity
            'complete_goal' => 2,  // Very low severity - achievement
            'pause_goal' => 6,     // Moderate-high severity
            'reflect' => 5         // Neutral
        ];
        $validated['severity'] = $severity_map[$validated['action_type']] ?? 5;
        
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
        
        // Process based on action type
        switch ($validated['action_type']) {
            case 'new_goal':
                $goal_id = $this->create_new_goal($validated);
                $validated['calculated']['goal_id'] = $goal_id;
                break;
                
            case 'update_progress':
                if (!empty($validated['existing_goal']) && isset($validated['progress_update'])) {
                    $this->update_goal_progress(
                        $validated['existing_goal'], 
                        $validated['progress_update'], 
                        $validated['progress_notes'] ?? ''
                    );
                    $validated['calculated']['progress'] = $validated['progress_update'];
                }
                break;
                
            case 'complete_goal':
                if (!empty($validated['existing_goal'])) {
                    $this->complete_goal(
                        $validated['existing_goal'], 
                        $validated['completion_reflection'] ?? ''
                    );
                    $validated['calculated']['completed'] = true;
                }
                break;
                
            case 'pause_goal':
                if (!empty($validated['existing_goal'])) {
                    $this->pause_goal(
                        $validated['existing_goal'], 
                        $validated['pause_reason'] ?? '', 
                        $validated['resume_date'] ?? ''
                    );
                    $validated['calculated']['paused'] = true;
                }
                break;
        }
        
        // Add calculated fields
        $validated['calculated']['action_date'] = current_time('Y-m-d');
        $validated['calculated']['action_time'] = current_time('H:i:s');
        
        // Call parent process_data to save
        return parent::process_data($validated);
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
            <p><?php _e('Analytics for your goals will be displayed here.', 'spiralengine'); ?></p>
            
            <div class="spiralengine-analytics-summary">
                <div class="stat-box">
                    <span class="stat-label"><?php _e('Active Goals', 'spiralengine'); ?></span>
                    <span class="stat-value">0</span>
                </div>
                <div class="stat-box">
                    <span class="stat-label"><?php _e('Completed Goals', 'spiralengine'); ?></span>
                    <span class="stat-value">0</span>
                </div>
                <div class="stat-box">
                    <span class="stat-label"><?php _e('Success Rate', 'spiralengine'); ?></span>
                    <span class="stat-value">0%</span>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Get user's active goals
     * 
     * @return array Active goals
     */
    private function get_user_active_goals() {
        $user_id = get_current_user_id();
        $goals = get_user_meta($user_id, 'spiralengine_active_goals', true);
        return is_array($goals) ? $goals : array();
    }
    
    /**
     * Create new goal
     * 
     * @param array $data Goal data
     * @return string Goal ID
     */
    private function create_new_goal($data) {
        $user_id = get_current_user_id();
        $goals = $this->get_user_active_goals();
        
        $goal_id = 'goal_' . time() . '_' . rand(1000, 9999);
        
        $goals[$goal_id] = array(
            'title' => $data['goal_title'] ?? '',
            'description' => $data['goal_description'] ?? '',
            'category' => $data['goal_category'] ?? 'other',
            'type' => $data['goal_type'] ?? 'outcome',
            'motivation' => $data['motivation'] ?? '',
            'target_date' => $data['target_date'] ?? '',
            'created_date' => current_time('Y-m-d'),
            'status' => 'active',
            'progress' => 0,
            'milestones' => $data['milestones'] ?? array(),
            'smart_criteria' => $data['smart_criteria'] ?? array(),
            'updates' => array()
        );
        
        update_user_meta($user_id, 'spiralengine_active_goals', $goals);
        
        return $goal_id;
    }
    
    /**
     * Update goal progress
     * 
     * @param string $goal_id Goal ID
     * @param int $progress Progress percentage
     * @param string $notes Progress notes
     */
    private function update_goal_progress($goal_id, $progress, $notes = '') {
        $user_id = get_current_user_id();
        $goals = $this->get_user_active_goals();
        
        if (isset($goals[$goal_id])) {
            $goals[$goal_id]['progress'] = intval($progress);
            $goals[$goal_id]['last_update'] = current_time('Y-m-d');
            $goals[$goal_id]['updates'][] = array(
                'date' => current_time('Y-m-d'),
                'progress' => intval($progress),
                'notes' => $notes
            );
            
            update_user_meta($user_id, 'spiralengine_active_goals', $goals);
        }
    }
    
    /**
     * Complete a goal
     * 
     * @param string $goal_id Goal ID
     * @param string $reflection Completion reflection
     */
    private function complete_goal($goal_id, $reflection = '') {
        $user_id = get_current_user_id();
        $goals = $this->get_user_active_goals();
        $completed = get_user_meta($user_id, 'spiralengine_completed_goals', true) ?: array();
        
        if (isset($goals[$goal_id])) {
            $goal = $goals[$goal_id];
            $goal['status'] = 'completed';
            $goal['progress'] = 100;
            $goal['completion_date'] = current_time('Y-m-d');
            $goal['completion_reflection'] = $reflection;
            
            // Move to completed
            $completed[$goal_id] = $goal;
            unset($goals[$goal_id]);
            
            update_user_meta($user_id, 'spiralengine_active_goals', $goals);
            update_user_meta($user_id, 'spiralengine_completed_goals', $completed);
        }
    }
    
    /**
     * Pause a goal
     * 
     * @param string $goal_id Goal ID
     * @param string $reason Pause reason
     * @param string $resume_date Expected resume date
     */
    private function pause_goal($goal_id, $reason = '', $resume_date = '') {
        $user_id = get_current_user_id();
        $goals = $this->get_user_active_goals();
        
        if (isset($goals[$goal_id])) {
            $goals[$goal_id]['status'] = 'paused';
            $goals[$goal_id]['pause_date'] = current_time('Y-m-d');
            $goals[$goal_id]['pause_reason'] = $reason;
            $goals[$goal_id]['resume_date'] = $resume_date;
            
            update_user_meta($user_id, 'spiralengine_active_goals', $goals);
        }
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

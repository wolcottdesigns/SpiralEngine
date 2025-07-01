<?php
/**
 * Medication Tracker Widget
 * 
 * @package    SpiralEngine
 * @subpackage Widgets
 * @file       widgets/class-medication-tracker.php
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Medication Tracker Widget Class
 * 
 * Tracks medication adherence and side effects
 * 
 * @since 1.0.0
 */
class SpiralEngine_Widget_Medication_Tracker extends SpiralEngine_Widget {
    
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
        $this->id = 'medication-tracker';
        $this->name = __('Medication Tracker', 'spiralengine');
        $this->description = __('Track medication adherence and monitor side effects', 'spiralengine');
        $this->icon = 'dashicons-heart';
        $this->min_tier = 'free';
        $this->version = '1.0.0';
        
        // Define tier features
        $this->tier_features = array(
            'free' => array(
                'basic_tracking' => true,
                'adherence_tracking' => true,
                'max_medications' => 2
            ),
            'silver' => array(
                'basic_tracking' => true,
                'adherence_tracking' => true,
                'side_effect_tracking' => true,
                'reminder_notes' => true,
                'mood_correlation' => true,
                'max_medications' => 5
            ),
            'gold' => array(
                'basic_tracking' => true,
                'adherence_tracking' => true,
                'side_effect_tracking' => true,
                'reminder_notes' => true,
                'mood_correlation' => true,
                'efficacy_tracking' => true,
                'interaction_warnings' => true,
                'refill_tracking' => true,
                'max_medications' => 10
            ),
            'platinum' => array(
                'basic_tracking' => true,
                'adherence_tracking' => true,
                'side_effect_tracking' => true,
                'reminder_notes' => true,
                'mood_correlation' => true,
                'efficacy_tracking' => true,
                'interaction_warnings' => true,
                'refill_tracking' => true,
                'prescriber_reports' => true,
                'pharmacy_integration' => true,
                'max_medications' => 'unlimited'
            )
        );
        
        // Define fields
        $this->fields = array(
            'action_type' => array(
                'label' => __('Action', 'spiralengine'),
                'type' => 'select',
                'options' => array(
                    '' => __('-- Select Action --', 'spiralengine'),
                    'take_medication' => __('Record Medication Taken', 'spiralengine'),
                    'skip_dose' => __('Record Skipped Dose', 'spiralengine'),
                    'side_effect' => __('Report Side Effect', 'spiralengine'),
                    'add_medication' => __('Add New Medication', 'spiralengine'),
                    'update_medication' => __('Update Medication', 'spiralengine'),
                    'efficacy_review' => __('Efficacy Review', 'spiralengine')
                ),
                'required' => true,
                'tier' => 'free'
            ),
            'medication_name' => array(
                'label' => __('Medication Name', 'spiralengine'),
                'type' => 'text',
                'required' => true,
                'placeholder' => __('Enter medication name', 'spiralengine'),
                'conditional' => array(
                    'field' => 'action_type',
                    'value' => 'add_medication'
                ),
                'tier' => 'free'
            ),
            'existing_medication' => array(
                'label' => __('Select Medication', 'spiralengine'),
                'type' => 'select',
                'options_callback' => array($this, 'get_medications_options'),
                'required' => true,
                'conditional' => array(
                    'field' => 'action_type',
                    'value' => array('take_medication', 'skip_dose', 'side_effect', 'update_medication', 'efficacy_review')
                ),
                'tier' => 'free'
            ),
            'dosage' => array(
                'label' => __('Dosage', 'spiralengine'),
                'type' => 'text',
                'placeholder' => __('e.g., 50mg, 2 tablets', 'spiralengine'),
                'conditional' => array(
                    'field' => 'action_type',
                    'value' => array('add_medication', 'update_medication')
                ),
                'tier' => 'free'
            ),
            'frequency' => array(
                'label' => __('Frequency', 'spiralengine'),
                'type' => 'select',
                'options' => array(
                    '' => __('-- Select Frequency --', 'spiralengine'),
                    'as_needed' => __('As Needed (PRN)', 'spiralengine'),
                    'once_daily' => __('Once Daily', 'spiralengine'),
                    'twice_daily' => __('Twice Daily', 'spiralengine'),
                    'three_daily' => __('Three Times Daily', 'spiralengine'),
                    'four_daily' => __('Four Times Daily', 'spiralengine'),
                    'every_other_day' => __('Every Other Day', 'spiralengine'),
                    'weekly' => __('Weekly', 'spiralengine'),
                    'biweekly' => __('Bi-weekly', 'spiralengine'),
                    'monthly' => __('Monthly', 'spiralengine')
                ),
                'conditional' => array(
                    'field' => 'action_type',
                    'value' => array('add_medication', 'update_medication')
                ),
                'tier' => 'free'
            ),
            'medication_type' => array(
                'label' => __('Medication Type', 'spiralengine'),
                'type' => 'select',
                'options' => array(
                    '' => __('-- Select Type --', 'spiralengine'),
                    'antidepressant' => __('Antidepressant', 'spiralengine'),
                    'anti_anxiety' => __('Anti-Anxiety', 'spiralengine'),
                    'mood_stabilizer' => __('Mood Stabilizer', 'spiralengine'),
                    'antipsychotic' => __('Antipsychotic', 'spiralengine'),
                    'stimulant' => __('Stimulant', 'spiralengine'),
                    'sleep_aid' => __('Sleep Aid', 'spiralengine'),
                    'supplement' => __('Supplement/Vitamin', 'spiralengine'),
                    'other' => __('Other', 'spiralengine')
                ),
                'conditional' => array(
                    'field' => 'action_type',
                    'value' => array('add_medication', 'update_medication')
                ),
                'tier' => 'silver'
            ),
            'prescriber' => array(
                'label' => __('Prescriber', 'spiralengine'),
                'type' => 'text',
                'placeholder' => __('Doctor/Provider name', 'spiralengine'),
                'conditional' => array(
                    'field' => 'action_type',
                    'value' => array('add_medication', 'update_medication')
                ),
                'tier' => 'silver'
            ),
            'start_date' => array(
                'label' => __('Start Date', 'spiralengine'),
                'type' => 'date',
                'conditional' => array(
                    'field' => 'action_type',
                    'value' => 'add_medication'
                ),
                'tier' => 'free'
            ),
            'time_taken' => array(
                'label' => __('Time Taken', 'spiralengine'),
                'type' => 'select',
                'options' => array(
                    '' => __('-- Select Time --', 'spiralengine'),
                    'morning' => __('Morning', 'spiralengine'),
                    'noon' => __('Noon', 'spiralengine'),
                    'afternoon' => __('Afternoon', 'spiralengine'),
                    'evening' => __('Evening', 'spiralengine'),
                    'bedtime' => __('Bedtime', 'spiralengine'),
                    'as_needed' => __('As Needed', 'spiralengine')
                ),
                'conditional' => array(
                    'field' => 'action_type',
                    'value' => 'take_medication'
                ),
                'tier' => 'free'
            ),
            'skip_reason' => array(
                'label' => __('Reason for Skipping', 'spiralengine'),
                'type' => 'select',
                'options' => array(
                    '' => __('-- Select Reason --', 'spiralengine'),
                    'forgot' => __('Forgot', 'spiralengine'),
                    'side_effects' => __('Side Effects', 'spiralengine'),
                    'feeling_better' => __('Feeling Better', 'spiralengine'),
                    'ran_out' => __('Ran Out', 'spiralengine'),
                    'cost' => __('Cost/Insurance Issues', 'spiralengine'),
                    'traveling' => __('Traveling', 'spiralengine'),
                    'other' => __('Other', 'spiralengine')
                ),
                'conditional' => array(
                    'field' => 'action_type',
                    'value' => 'skip_dose'
                ),
                'tier' => 'free'
            ),
            'side_effect_type' => array(
                'label' => __('Side Effect Type', 'spiralengine'),
                'type' => 'checkbox',
                'options' => array(
                    'nausea' => __('Nausea/Stomach Upset', 'spiralengine'),
                    'headache' => __('Headache', 'spiralengine'),
                    'dizziness' => __('Dizziness', 'spiralengine'),
                    'fatigue' => __('Fatigue/Drowsiness', 'spiralengine'),
                    'insomnia' => __('Insomnia', 'spiralengine'),
                    'weight_change' => __('Weight Change', 'spiralengine'),
                    'appetite_change' => __('Appetite Change', 'spiralengine'),
                    'mood_change' => __('Mood Changes', 'spiralengine'),
                    'sexual' => __('Sexual Side Effects', 'spiralengine'),
                    'tremor' => __('Tremor/Shaking', 'spiralengine'),
                    'dry_mouth' => __('Dry Mouth', 'spiralengine'),
                    'other' => __('Other', 'spiralengine')
                ),
                'conditional' => array(
                    'field' => 'action_type',
                    'value' => 'side_effect'
                ),
                'tier' => 'silver'
            ),
            'side_effect_severity' => array(
                'label' => __('Side Effect Severity', 'spiralengine'),
                'type' => 'range',
                'min' => 1,
                'max' => 10,
                'default' => 5,
                'description' => __('1 = Mild, 10 = Severe', 'spiralengine'),
                'conditional' => array(
                    'field' => 'action_type',
                    'value' => 'side_effect'
                ),
                'tier' => 'silver'
            ),
            'side_effect_description' => array(
                'label' => __('Describe Side Effect', 'spiralengine'),
                'type' => 'textarea',
                'rows' => 3,
                'placeholder' => __('Provide details about the side effect', 'spiralengine'),
                'conditional' => array(
                    'field' => 'action_type',
                    'value' => 'side_effect'
                ),
                'tier' => 'silver'
            ),
            'mood_before' => array(
                'label' => __('Mood Before Medication', 'spiralengine'),
                'type' => 'range',
                'min' => 1,
                'max' => 10,
                'default' => 5,
                'description' => __('Rate your mood', 'spiralengine'),
                'conditional' => array(
                    'field' => 'action_type',
                    'value' => 'take_medication'
                ),
                'tier' => 'silver'
            ),
            'mood_after' => array(
                'label' => __('Mood After Medication', 'spiralengine'),
                'type' => 'range',
                'min' => 1,
                'max' => 10,
                'default' => 5,
                'description' => __('Rate your mood 1-2 hours later', 'spiralengine'),
                'conditional' => array(
                    'field' => 'action_type',
                    'value' => 'take_medication'
                ),
                'tier' => 'silver'
            ),
            'efficacy_rating' => array(
                'label' => __('How Well Is This Medication Working?', 'spiralengine'),
                'type' => 'range',
                'min' => 1,
                'max' => 10,
                'default' => 5,
                'description' => __('1 = Not working, 10 = Very effective', 'spiralengine'),
                'conditional' => array(
                    'field' => 'action_type',
                    'value' => 'efficacy_review'
                ),
                'tier' => 'gold'
            ),
            'efficacy_notes' => array(
                'label' => __('Efficacy Notes', 'spiralengine'),
                'type' => 'textarea',
                'rows' => 3,
                'placeholder' => __('Describe how this medication is working for you', 'spiralengine'),
                'conditional' => array(
                    'field' => 'action_type',
                    'value' => 'efficacy_review'
                ),
                'tier' => 'gold'
            ),
            'refill_date' => array(
                'label' => __('Next Refill Date', 'spiralengine'),
                'type' => 'date',
                'conditional' => array(
                    'field' => 'action_type',
                    'value' => array('add_medication', 'update_medication')
                ),
                'tier' => 'gold'
            ),
            'discontinue' => array(
                'label' => __('Discontinue Medication?', 'spiralengine'),
                'type' => 'radio',
                'options' => array(
                    'no' => __('No, continue medication', 'spiralengine'),
                    'yes' => __('Yes, discontinue', 'spiralengine')
                ),
                'default' => 'no',
                'conditional' => array(
                    'field' => 'action_type',
                    'value' => 'update_medication'
                ),
                'tier' => 'silver'
            ),
            'discontinue_reason' => array(
                'label' => __('Reason for Discontinuing', 'spiralengine'),
                'type' => 'textarea',
                'rows' => 2,
                'placeholder' => __('Why are you stopping this medication?', 'spiralengine'),
                'conditional' => array(
                    'field' => 'discontinue',
                    'value' => 'yes'
                ),
                'tier' => 'silver'
            ),
            'share_with_prescriber' => array(
                'label' => __('Share with Prescriber?', 'spiralengine'),
                'type' => 'radio',
                'options' => array(
                    'yes' => __('Yes, include in report', 'spiralengine'),
                    'no' => __('No, keep private', 'spiralengine')
                ),
                'default' => 'yes',
                'tier' => 'platinum'
            ),
            'notes' => array(
                'label' => __('Additional Notes', 'spiralengine'),
                'type' => 'textarea',
                'rows' => 3,
                'placeholder' => __('Any other observations', 'spiralengine'),
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
                            // Handle dynamic options
                            if (isset($field['options_callback'])) {
                                $field['options'] = call_user_func($field['options_callback']);
                            }
                            
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
                    <?php _e('Save Medication Entry', 'spiralengine'); ?>
                </button>
                <button type="button" class="button spiralengine-cancel-button">
                    <?php _e('Cancel', 'spiralengine'); ?>
                </button>
            </div>
        </form>
        
        <script>
        jQuery(document).ready(function($) {
            // Handle conditional fields
            function handleConditionalFields() {
                // Handle action type changes
                $('#medication-tracker_action_type').on('change', function() {
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
                
                // Handle discontinue radio changes
                $('input[name="discontinue"]').on('change', function() {
                    var selectedValue = $(this).val();
                    $('.spiralengine-field-wrapper[data-conditional-field="discontinue"]').each(function() {
                        var conditionalValue = $(this).data('conditional-value');
                        if (conditionalValue === selectedValue) {
                            $(this).show();
                        } else {
                            $(this).hide();
                        }
                    });
                });
            }
            
            handleConditionalFields();
        });
        </script>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Get medications options
     * 
     * @return array Options array
     */
    public function get_medications_options() {
        $medications = $this->get_user_medications();
        $options = array('' => __('-- Select Medication --', 'spiralengine'));
        
        foreach ($medications as $med_id => $medication) {
            if ($medication['status'] === 'active') {
                $options[$med_id] = $medication['name'] . ' (' . $medication['dosage'] . ')';
            }
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
            case 'add_medication':
                // Check medication limit
                $tier = $this->get_user_tier();
                $max_meds = $this->tier_features[$tier]['max_medications'] ?? 2;
                if ($max_meds !== 'unlimited') {
                    $active_meds = $this->count_active_medications();
                    if ($active_meds >= $max_meds) {
                        $errors[] = sprintf(
                            __('You have reached your limit of %d medications. Upgrade to track more.', 'spiralengine'),
                            $max_meds
                        );
                    }
                }
                
                if (empty($data['medication_name'])) {
                    $errors[] = __('Please enter the medication name.', 'spiralengine');
                } else {
                    $validated['medication_name'] = sanitize_text_field($data['medication_name']);
                }
                
                if (empty($data['dosage'])) {
                    $errors[] = __('Please enter the dosage.', 'spiralengine');
                } else {
                    $validated['dosage'] = sanitize_text_field($data['dosage']);
                }
                
                if (empty($data['frequency'])) {
                    $errors[] = __('Please select the frequency.', 'spiralengine');
                } else {
                    $validated['frequency'] = $data['frequency'];
                }
                
                // Optional fields
                if (!empty($data['medication_type'])) {
                    $validated['medication_type'] = $data['medication_type'];
                }
                if (!empty($data['prescriber'])) {
                    $validated['prescriber'] = sanitize_text_field($data['prescriber']);
                }
                if (!empty($data['start_date'])) {
                    $validated['start_date'] = sanitize_text_field($data['start_date']);
                }
                if (!empty($data['refill_date'])) {
                    $validated['refill_date'] = sanitize_text_field($data['refill_date']);
                }
                break;
                
            case 'take_medication':
                if (empty($data['existing_medication'])) {
                    $errors[] = __('Please select a medication.', 'spiralengine');
                } else {
                    $validated['existing_medication'] = sanitize_text_field($data['existing_medication']);
                }
                if (!empty($data['time_taken'])) {
                    $validated['time_taken'] = $data['time_taken'];
                }
                if (isset($data['mood_before'])) {
                    $validated['mood_before'] = intval($data['mood_before']);
                }
                if (isset($data['mood_after'])) {
                    $validated['mood_after'] = intval($data['mood_after']);
                }
                break;
                
            case 'skip_dose':
                if (empty($data['existing_medication'])) {
                    $errors[] = __('Please select a medication.', 'spiralengine');
                } else {
                    $validated['existing_medication'] = sanitize_text_field($data['existing_medication']);
                }
                if (empty($data['skip_reason'])) {
                    $errors[] = __('Please select a reason for skipping.', 'spiralengine');
                } else {
                    $validated['skip_reason'] = $data['skip_reason'];
                }
                break;
                
            case 'side_effect':
                if (empty($data['existing_medication'])) {
                    $errors[] = __('Please select a medication.', 'spiralengine');
                } else {
                    $validated['existing_medication'] = sanitize_text_field($data['existing_medication']);
                }
                if (empty($data['side_effect_type']) || !is_array($data['side_effect_type'])) {
                    $errors[] = __('Please select at least one side effect type.', 'spiralengine');
                } else {
                    $validated['side_effect_type'] = $data['side_effect_type'];
                }
                if (empty($data['side_effect_severity'])) {
                    $errors[] = __('Please rate the severity of the side effect.', 'spiralengine');
                } else {
                    $validated['side_effect_severity'] = intval($data['side_effect_severity']);
                }
                if (!empty($data['side_effect_description'])) {
                    $validated['side_effect_description'] = sanitize_textarea_field($data['side_effect_description']);
                }
                break;
                
            case 'update_medication':
                if (empty($data['existing_medication'])) {
                    $errors[] = __('Please select a medication.', 'spiralengine');
                } else {
                    $validated['existing_medication'] = sanitize_text_field($data['existing_medication']);
                }
                // Transfer update fields
                if (!empty($data['dosage'])) {
                    $validated['dosage'] = sanitize_text_field($data['dosage']);
                }
                if (!empty($data['frequency'])) {
                    $validated['frequency'] = $data['frequency'];
                }
                if (!empty($data['refill_date'])) {
                    $validated['refill_date'] = sanitize_text_field($data['refill_date']);
                }
                if (!empty($data['discontinue'])) {
                    $validated['discontinue'] = $data['discontinue'];
                }
                if (!empty($data['discontinue_reason'])) {
                    $validated['discontinue_reason'] = sanitize_textarea_field($data['discontinue_reason']);
                }
                break;
                
            case 'efficacy_review':
                if (empty($data['existing_medication'])) {
                    $errors[] = __('Please select a medication.', 'spiralengine');
                } else {
                    $validated['existing_medication'] = sanitize_text_field($data['existing_medication']);
                }
                if (isset($data['efficacy_rating'])) {
                    $validated['efficacy_rating'] = intval($data['efficacy_rating']);
                }
                if (!empty($data['efficacy_notes'])) {
                    $validated['efficacy_notes'] = sanitize_textarea_field($data['efficacy_notes']);
                }
                break;
        }
        
        // Common fields
        if (!empty($data['notes'])) {
            $validated['notes'] = sanitize_textarea_field($data['notes']);
        }
        if (!empty($data['share_with_prescriber'])) {
            $validated['share_with_prescriber'] = $data['share_with_prescriber'];
        }
        
        // Calculate severity
        $severity_map = [
            'add_medication' => 3,      // Low severity - positive action
            'take_medication' => 2,     // Very low severity - good adherence
            'skip_dose' => 7,          // High severity - missed dose
            'side_effect' => 5,        // Will be overridden by actual severity
            'update_medication' => 5,   // Moderate
            'efficacy_review' => 5      // Will be calculated based on rating
        ];
        
        $validated['severity'] = $severity_map[$validated['action_type']] ?? 5;
        
        // Override severity for specific cases
        if ($validated['action_type'] === 'side_effect' && isset($validated['side_effect_severity'])) {
            $validated['severity'] = $validated['side_effect_severity'];
        } elseif ($validated['action_type'] === 'efficacy_review' && isset($validated['efficacy_rating'])) {
            $validated['severity'] = 11 - $validated['efficacy_rating']; // Inverse
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
        
        // Add calculated fields
        $validated['calculated'] = array(
            'action_date' => current_time('Y-m-d'),
            'action_time' => current_time('H:i:s'),
            'day_of_week' => current_time('l')
        );
        
        // Process based on action type
        switch ($validated['action_type']) {
            case 'add_medication':
                $med_id = $this->add_medication($validated);
                $validated['calculated']['medication_id'] = $med_id;
                break;
                
            case 'take_medication':
                $this->record_dose_taken($validated['existing_medication'], $validated);
                $validated['calculated']['adherence'] = true;
                
                // Calculate mood impact if provided
                if (!empty($validated['mood_before']) && !empty($validated['mood_after'])) {
                    $mood_change = intval($validated['mood_after']) - intval($validated['mood_before']);
                    $validated['calculated']['mood_change'] = $mood_change;
                }
                break;
                
            case 'skip_dose':
                $this->record_dose_skipped($validated['existing_medication'], $validated['skip_reason']);
                $validated['calculated']['adherence'] = false;
                break;
                
            case 'side_effect':
                $this->record_side_effect($validated['existing_medication'], $validated);
                $validated['calculated']['side_effect_count'] = count($validated['side_effect_type'] ?? array());
                break;
                
            case 'update_medication':
                $this->update_medication($validated['existing_medication'], $validated);
                if (($validated['discontinue'] ?? '') === 'yes') {
                    $validated['severity'] = 6; // Moderate-high severity - discontinuing
                }
                break;
                
            case 'efficacy_review':
                $this->record_efficacy_review($validated['existing_medication'], $validated);
                break;
        }
        
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
            <p><?php _e('Analytics for your medications will be displayed here.', 'spiralengine'); ?></p>
            
            <div class="spiralengine-analytics-summary">
                <div class="stat-box">
                    <span class="stat-label"><?php _e('Active Medications', 'spiralengine'); ?></span>
                    <span class="stat-value">0</span>
                </div>
                <div class="stat-box">
                    <span class="stat-label"><?php _e('Adherence Rate', 'spiralengine'); ?></span>
                    <span class="stat-value">0%</span>
                </div>
                <div class="stat-box">
                    <span class="stat-label"><?php _e('Side Effects', 'spiralengine'); ?></span>
                    <span class="stat-value">0</span>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Get user medications
     * 
     * @return array Medications
     */
    private function get_user_medications() {
        $user_id = get_current_user_id();
        $medications = get_user_meta($user_id, 'spiralengine_medications', true);
        return is_array($medications) ? $medications : array();
    }
    
    /**
     * Count active medications
     * 
     * @return int Active medication count
     */
    private function count_active_medications() {
        $medications = $this->get_user_medications();
        $active = 0;
        
        foreach ($medications as $med) {
            if (($med['status'] ?? '') === 'active') {
                $active++;
            }
        }
        
        return $active;
    }
    
    /**
     * Add new medication
     * 
     * @param array $data Medication data
     * @return string Medication ID
     */
    private function add_medication($data) {
        $user_id = get_current_user_id();
        $medications = $this->get_user_medications();
        
        $med_id = 'med_' . time() . '_' . rand(1000, 9999);
        
        $medications[$med_id] = array(
            'name' => $data['medication_name'] ?? '',
            'dosage' => $data['dosage'] ?? '',
            'frequency' => $data['frequency'] ?? '',
            'type' => $data['medication_type'] ?? 'other',
            'prescriber' => $data['prescriber'] ?? '',
            'start_date' => $data['start_date'] ?? current_time('Y-m-d'),
            'refill_date' => $data['refill_date'] ?? '',
            'status' => 'active',
            'doses_taken' => 0,
            'doses_skipped' => 0,
            'side_effects' => array(),
            'efficacy_reviews' => array()
        );
        
        update_user_meta($user_id, 'spiralengine_medications', $medications);
        
        return $med_id;
    }
    
    /**
     * Record dose taken
     * 
     * @param string $med_id Medication ID
     * @param array $data Dose data
     */
    private function record_dose_taken($med_id, $data) {
        $user_id = get_current_user_id();
        $medications = $this->get_user_medications();
        
        if (isset($medications[$med_id])) {
            $medications[$med_id]['doses_taken']++;
            $medications[$med_id]['last_taken'] = current_time('Y-m-d H:i:s');
            
            // Track dose history
            if (!isset($medications[$med_id]['dose_history'])) {
                $medications[$med_id]['dose_history'] = array();
            }
            
            $medications[$med_id]['dose_history'][] = array(
                'date' => current_time('Y-m-d'),
                'time' => $data['time_taken'] ?? current_time('H:i'),
                'mood_before' => $data['mood_before'] ?? null,
                'mood_after' => $data['mood_after'] ?? null,
                'notes' => $data['notes'] ?? ''
            );
            
            // Keep only last 90 days of history
            $medications[$med_id]['dose_history'] = array_slice($medications[$med_id]['dose_history'], -90);
            
            update_user_meta($user_id, 'spiralengine_medications', $medications);
        }
    }
    
    /**
     * Record dose skipped
     * 
     * @param string $med_id Medication ID
     * @param string $reason Skip reason
     */
    private function record_dose_skipped($med_id, $reason) {
        $user_id = get_current_user_id();
        $medications = $this->get_user_medications();
        
        if (isset($medications[$med_id])) {
            $medications[$med_id]['doses_skipped']++;
            
            if (!isset($medications[$med_id]['skip_history'])) {
                $medications[$med_id]['skip_history'] = array();
            }
            
            $medications[$med_id]['skip_history'][] = array(
                'date' => current_time('Y-m-d'),
                'reason' => $reason
            );
            
            update_user_meta($user_id, 'spiralengine_medications', $medications);
        }
    }
    
    /**
     * Record side effect
     * 
     * @param string $med_id Medication ID
     * @param array $data Side effect data
     */
    private function record_side_effect($med_id, $data) {
        $user_id = get_current_user_id();
        $medications = $this->get_user_medications();
        
        if (isset($medications[$med_id])) {
            $medications[$med_id]['side_effects'][] = array(
                'date' => current_time('Y-m-d'),
                'types' => $data['side_effect_type'] ?? array(),
                'severity' => $data['side_effect_severity'] ?? 5,
                'description' => $data['side_effect_description'] ?? ''
            );
            
            update_user_meta($user_id, 'spiralengine_medications', $medications);
        }
    }
    
    /**
     * Update medication
     * 
     * @param string $med_id Medication ID
     * @param array $data Update data
     */
    private function update_medication($med_id, $data) {
        $user_id = get_current_user_id();
        $medications = $this->get_user_medications();
        
        if (isset($medications[$med_id])) {
            if (!empty($data['dosage'])) {
                $medications[$med_id]['dosage'] = $data['dosage'];
            }
            if (!empty($data['frequency'])) {
                $medications[$med_id]['frequency'] = $data['frequency'];
            }
            if (!empty($data['refill_date'])) {
                $medications[$med_id]['refill_date'] = $data['refill_date'];
            }
            
            if (($data['discontinue'] ?? '') === 'yes') {
                $medications[$med_id]['status'] = 'discontinued';
                $medications[$med_id]['end_date'] = current_time('Y-m-d');
                $medications[$med_id]['discontinue_reason'] = $data['discontinue_reason'] ?? '';
            }
            
            update_user_meta($user_id, 'spiralengine_medications', $medications);
        }
    }
    
    /**
     * Record efficacy review
     * 
     * @param string $med_id Medication ID
     * @param array $data Review data
     */
    private function record_efficacy_review($med_id, $data) {
        $user_id = get_current_user_id();
        $medications = $this->get_user_medications();
        
        if (isset($medications[$med_id])) {
            $medications[$med_id]['efficacy_reviews'][] = array(
                'date' => current_time('Y-m-d'),
                'rating' => $data['efficacy_rating'] ?? 5,
                'notes' => $data['efficacy_notes'] ?? ''
            );
            
            update_user_meta($user_id, 'spiralengine_medications', $medications);
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

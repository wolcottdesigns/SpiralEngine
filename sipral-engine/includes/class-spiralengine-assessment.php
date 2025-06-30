<?php
// includes/class-spiralengine-assessment.php

/**
 * SPIRAL Engine Assessment Class
 * 
 * Implements SPIRAL assessment from the Member Signup plan:
 * - 6 questions exactly as specified
 * - Scoring system (0-18)
 * - Risk levels: Low (0-6), Medium (7-12), High (13-18)
 * - Crisis detection
 * - Assessment history
 */
class SpiralEngine_Assessment {
    
    /**
     * Instance of this class
     */
    private static $instance = null;
    
    /**
     * The 6 sacred SPIRAL questions - NEVER CHANGE THESE!
     */
    private $questions = array(
        'acceleration' => array(
            'question' => 'How much are you experiencing feelings of acceleration or speeding up?',
            'description' => 'Racing thoughts, feeling like everything is moving too fast'
        ),
        'catastrophizing' => array(
            'question' => 'How much are you having catastrophizing or worst-case scenario thoughts?',
            'description' => 'Imagining terrible outcomes, expecting the worst'
        ),
        'loss_of_control' => array(
            'question' => 'How much do you feel a loss of control?',
            'description' => 'Feeling powerless, unable to influence outcomes'
        ),
        'physical_activation' => array(
            'question' => 'How much physical activation are you experiencing?',
            'description' => 'Heart racing, sweating, tension, restlessness, shaking'
        ),
        'time_distortion' => array(
            'question' => 'How much time distortion are you experiencing?',
            'description' => 'Time feeling stretched or compressed, losing track of time'
        ),
        'compulsion' => array(
            'question' => 'How strong is your compulsion to act or do something?',
            'description' => 'Urgent need to take action, fix things, or escape'
        )
    );
    
    /**
     * Response scale (0-3 for each question)
     */
    private $response_scale = array(
        0 => 'Not at all',
        1 => 'A little',
        2 => 'Moderately',
        3 => 'Extremely'
    );
    
    /**
     * Get instance of this class
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->init_hooks();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // AJAX endpoints
        add_action('wp_ajax_spiralengine_save_assessment', array($this, 'ajax_save_assessment'));
        add_action('wp_ajax_nopriv_spiralengine_save_assessment', array($this, 'ajax_save_assessment'));
        add_action('wp_ajax_spiralengine_get_assessment_history', array($this, 'ajax_get_assessment_history'));
        
        // Shortcodes
        add_shortcode('spiralengine_assessment', array($this, 'render_assessment_form'));
        add_shortcode('spiralengine_assessment_results', array($this, 'render_assessment_results'));
        
        // Daily check-in reminder
        add_action('spiralengine_daily_cron', array($this, 'send_daily_reminders'));
        
        // Crisis intervention
        add_action('spiralengine_assessment_completed', array($this, 'check_crisis_intervention'), 10, 2);
    }
    
    /**
     * Get assessment questions
     */
    public function get_questions() {
        return $this->questions;
    }
    
    /**
     * Get response scale
     */
    public function get_response_scale() {
        return $this->response_scale;
    }
    
    /**
     * Calculate risk level from score
     */
    public function calculate_risk_level($total_score) {
        if ($total_score >= 13) {
            return 'high';
        } elseif ($total_score >= 7) {
            return 'medium';
        } else {
            return 'low';
        }
    }
    
    /**
     * Get risk level label
     */
    public function get_risk_level_label($risk_level) {
        $labels = array(
            'low' => __('Low Intensity', 'spiral-engine'),
            'medium' => __('Medium Intensity', 'spiral-engine'),
            'high' => __('High Intensity', 'spiral-engine')
        );
        
        return isset($labels[$risk_level]) ? $labels[$risk_level] : $risk_level;
    }
    
    /**
     * Get risk level color
     */
    public function get_risk_level_color($risk_level) {
        $colors = array(
            'low' => '#4CAF50',    // Green
            'medium' => '#FF9800', // Orange
            'high' => '#F44336'    // Red
        );
        
        return isset($colors[$risk_level]) ? $colors[$risk_level] : '#757575';
    }
    
    /**
     * Save assessment
     */
    public function save_assessment($user_id, $responses, $context = 'daily_checkin') {
        global $wpdb;
        
        // Validate responses
        $validated_responses = $this->validate_responses($responses);
        if (!$validated_responses) {
            return new WP_Error('invalid_responses', __('Invalid assessment responses', 'spiral-engine'));
        }
        
        // Calculate total score
        $total_score = array_sum($validated_responses);
        
        // Calculate risk level
        $risk_level = $this->calculate_risk_level($total_score);
        
        // Prepare data
        $assessment_data = array(
            'responses' => $validated_responses,
            'total_score' => $total_score,
            'risk_level' => $risk_level,
            'context' => $context,
            'questions_version' => '1.0',
            'user_factors' => $this->get_user_factors($user_id)
        );
        
        // Save to database
        $result = $wpdb->insert(
            $wpdb->prefix . 'spiralengine_assessments',
            array(
                'user_id' => $user_id,
                'responses' => json_encode($validated_responses),
                'total_score' => $total_score,
                'risk_level' => $risk_level,
                'context' => $context,
                'metadata' => json_encode($assessment_data),
                'created_at' => current_time('mysql')
            ),
            array('%d', '%s', '%d', '%s', '%s', '%s', '%s')
        );
        
        if ($result === false) {
            return new WP_Error('save_failed', __('Failed to save assessment', 'spiral-engine'));
        }
        
        $assessment_id = $wpdb->insert_id;
        
        // Update user meta
        update_user_meta($user_id, 'spiralengine_last_assessment_date', current_time('mysql'));
        update_user_meta($user_id, 'spiralengine_last_assessment_score', $total_score);
        update_user_meta($user_id, 'spiralengine_risk_level', $risk_level);
        
        // Increment assessment count
        $count = (int) get_user_meta($user_id, 'spiralengine_assessment_count', true);
        update_user_meta($user_id, 'spiralengine_assessment_count', $count + 1);
        
        // Trigger actions
        do_action('spiralengine_assessment_completed', $user_id, $assessment_data);
        
        // Check journey markers
        do_action('spiralengine_check_journey_markers', $user_id, 'assessment_completed', $assessment_data);
        
        return array(
            'assessment_id' => $assessment_id,
            'total_score' => $total_score,
            'risk_level' => $risk_level,
            'data' => $assessment_data
        );
    }
    
    /**
     * Validate responses
     */
    private function validate_responses($responses) {
        $validated = array();
        
        foreach ($this->questions as $key => $question) {
            if (!isset($responses[$key])) {
                return false;
            }
            
            $value = intval($responses[$key]);
            if ($value < 0 || $value > 3) {
                return false;
            }
            
            $validated[$key] = $value;
        }
        
        return $validated;
    }
    
    /**
     * Get user factors for context
     */
    private function get_user_factors($user_id) {
        $factors = array(
            'time_of_day' => current_time('H:i'),
            'day_of_week' => current_time('l'),
            'consecutive_days' => $this->get_consecutive_days($user_id)
        );
        
        // Add biological factors if tracked
        $biological_sex = get_user_meta($user_id, 'spiralengine_biological_sex', true);
        if ($biological_sex) {
            $factors['biological_sex'] = $biological_sex;
            
            if ($biological_sex === 'female' && get_user_meta($user_id, 'spiralengine_track_menstrual', true)) {
                $factors['menstrual_tracking'] = true;
                $factors['cycle_day'] = get_user_meta($user_id, 'spiralengine_cycle_day', true);
            }
        }
        
        return $factors;
    }
    
    /**
     * Get consecutive days of assessments
     */
    private function get_consecutive_days($user_id) {
        global $wpdb;
        
        $days = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT DATE(created_at)) 
             FROM {$wpdb->prefix}spiralengine_assessments 
             WHERE user_id = %d 
             AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)",
            $user_id
        ));
        
        return intval($days);
    }
    
    /**
     * Get assessment history
     */
    public function get_assessment_history($user_id, $limit = 30, $offset = 0) {
        global $wpdb;
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}spiralengine_assessments 
             WHERE user_id = %d 
             ORDER BY created_at DESC 
             LIMIT %d OFFSET %d",
            $user_id, $limit, $offset
        ));
        
        $history = array();
        foreach ($results as $result) {
            $history[] = array(
                'id' => $result->id,
                'responses' => json_decode($result->responses, true),
                'total_score' => $result->total_score,
                'risk_level' => $result->risk_level,
                'context' => $result->context,
                'metadata' => json_decode($result->metadata, true),
                'created_at' => $result->created_at
            );
        }
        
        return $history;
    }
    
    /**
     * Get assessment trends
     */
    public function get_assessment_trends($user_id, $days = 30) {
        global $wpdb;
        
        $start_date = date('Y-m-d', strtotime("-{$days} days"));
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT DATE(created_at) as date, AVG(total_score) as avg_score, COUNT(*) as count 
             FROM {$wpdb->prefix}spiralengine_assessments 
             WHERE user_id = %d AND created_at >= %s 
             GROUP BY DATE(created_at) 
             ORDER BY date ASC",
            $user_id, $start_date
        ));
        
        return $results;
    }
    
    /**
     * Check crisis intervention
     */
    public function check_crisis_intervention($user_id, $assessment_data) {
        if ($assessment_data['risk_level'] === 'high') {
            // Increment crisis count
            $crisis_count = (int) get_user_meta($user_id, 'spiralengine_crisis_resources_shown', true);
            update_user_meta($user_id, 'spiralengine_crisis_resources_shown', $crisis_count + 1);
            
            // Trigger crisis intervention
            do_action('spiralengine_crisis_intervention_needed', $user_id, $assessment_data);
            
            // Notify admins if enabled
            if (get_option('spiralengine_notify_admin_crisis', true)) {
                $this->notify_admin_crisis($user_id, $assessment_data);
            }
            
            // Log the event
            do_action('spiralengine_privacy_event', $user_id, 'crisis_intervention_triggered', array(
                'score' => $assessment_data['total_score']
            ));
        }
    }
    
    /**
     * Notify admin of crisis
     */
    private function notify_admin_crisis($user_id, $assessment_data) {
        $user = get_userdata($user_id);
        $admin_email = get_option('admin_email');
        
        $subject = sprintf(__('[URGENT] High Risk Assessment - %s', 'spiral-engine'), $user->display_name);
        $message = sprintf(
            __("A user has completed an assessment with a high risk score.\n\nUser: %s (%s)\nScore: %d/18\nRisk Level: High\nTime: %s\n\nPlease review this assessment and consider reaching out to offer support.\n\nView user profile: %s", 'spiral-engine'),
            $user->display_name,
            $user->user_email,
            $assessment_data['total_score'],
            current_time('mysql'),
            admin_url('user-edit.php?user_id=' . $user_id)
        );
        
        wp_mail($admin_email, $subject, $message);
    }
    
    /**
     * Render assessment form
     */
    public function render_assessment_form($atts = array()) {
        $atts = shortcode_atts(array(
            'context' => 'daily_checkin',
            'show_progress' => true,
            'show_descriptions' => true,
            'ajax' => true
        ), $atts);
        
        if (!is_user_logged_in()) {
            return '<p>' . __('Please log in to complete the assessment.', 'spiral-engine') . '</p>';
        }
        
        ob_start();
        ?>
        <div class="spiralengine-assessment-container" data-context="<?php echo esc_attr($atts['context']); ?>">
            <form id="spiralengine-assessment-form" class="spiralengine-assessment-form">
                <?php wp_nonce_field('spiralengine_assessment', 'assessment_nonce'); ?>
                
                <?php if ($atts['show_progress']) : ?>
                <div class="spiralengine-progress-bar">
                    <div class="spiralengine-progress-fill" style="width: 0%;"></div>
                    <span class="spiralengine-progress-text">0 / 6</span>
                </div>
                <?php endif; ?>
                
                <div class="spiralengine-questions">
                    <?php 
                    $index = 0;
                    foreach ($this->questions as $key => $question) : 
                        $index++;
                    ?>
                    <div class="spiralengine-question" data-question="<?php echo esc_attr($key); ?>" data-index="<?php echo $index; ?>">
                        <h3 class="spiralengine-question-title">
                            <?php echo esc_html($question['question']); ?>
                        </h3>
                        
                        <?php if ($atts['show_descriptions']) : ?>
                        <p class="spiralengine-question-description">
                            <?php echo esc_html($question['description']); ?>
                        </p>
                        <?php endif; ?>
                        
                        <div class="spiralengine-response-scale">
                            <?php foreach ($this->response_scale as $value => $label) : ?>
                            <label class="spiralengine-response-option">
                                <input type="radio" name="responses[<?php echo esc_attr($key); ?>]" value="<?php echo esc_attr($value); ?>" required />
                                <span class="spiralengine-response-card" data-value="<?php echo esc_attr($value); ?>">
                                    <span class="spiralengine-response-value"><?php echo esc_html($value); ?></span>
                                    <span class="spiralengine-response-label"><?php echo esc_html($label); ?></span>
                                </span>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="spiralengine-assessment-navigation">
                    <button type="button" class="spiralengine-prev-button" style="display: none;">
                        <?php _e('Previous', 'spiral-engine'); ?>
                    </button>
                    <button type="button" class="spiralengine-next-button">
                        <?php _e('Next', 'spiral-engine'); ?>
                    </button>
                    <button type="submit" class="spiralengine-submit-button" style="display: none;">
                        <?php _e('Complete Assessment', 'spiral-engine'); ?>
                    </button>
                </div>
            </form>
            
            <div class="spiralengine-assessment-results" style="display: none;">
                <!-- Results will be displayed here -->
            </div>
        </div>
        
        <?php if ($atts['ajax']) : ?>
        <script>
        jQuery(document).ready(function($) {
            var currentQuestion = 0;
            var totalQuestions = $('.spiralengine-question').length;
            var responses = {};
            
            function showQuestion(index) {
                $('.spiralengine-question').hide();
                $('.spiralengine-question').eq(index).show();
                
                // Update progress
                var progress = ((index + 1) / totalQuestions) * 100;
                $('.spiralengine-progress-fill').css('width', progress + '%');
                $('.spiralengine-progress-text').text((index + 1) + ' / ' + totalQuestions);
                
                // Update navigation
                $('.spiralengine-prev-button').toggle(index > 0);
                $('.spiralengine-next-button').toggle(index < totalQuestions - 1);
                $('.spiralengine-submit-button').toggle(index === totalQuestions - 1);
            }
            
            // Initialize
            showQuestion(0);
            
            // Handle response selection
            $('.spiralengine-response-option input').on('change', function() {
                var question = $(this).closest('.spiralengine-question').data('question');
                responses[question] = $(this).val();
                
                // Auto-advance on selection (optional)
                if ($('.spiralengine-response-option input:checked').length < totalQuestions) {
                    setTimeout(function() {
                        $('.spiralengine-next-button').click();
                    }, 300);
                }
            });
            
            // Navigation
            $('.spiralengine-next-button').on('click', function() {
                if (currentQuestion < totalQuestions - 1) {
                    currentQuestion++;
                    showQuestion(currentQuestion);
                }
            });
            
            $('.spiralengine-prev-button').on('click', function() {
                if (currentQuestion > 0) {
                    currentQuestion--;
                    showQuestion(currentQuestion);
                }
            });
            
            // Form submission
            $('#spiralengine-assessment-form').on('submit', function(e) {
                e.preventDefault();
                
                var $form = $(this);
                var formData = $form.serialize();
                
                $.ajax({
                    url: spiralengine_ajax.ajax_url,
                    type: 'POST',
                    data: formData + '&action=spiralengine_save_assessment',
                    beforeSend: function() {
                        $('.spiralengine-submit-button').prop('disabled', true).text('<?php _e('Saving...', 'spiral-engine'); ?>');
                    },
                    success: function(response) {
                        if (response.success) {
                            // Show results
                            showResults(response.data);
                        } else {
                            alert(response.data || '<?php _e('An error occurred', 'spiral-engine'); ?>');
                            $('.spiralengine-submit-button').prop('disabled', false).text('<?php _e('Complete Assessment', 'spiral-engine'); ?>');
                        }
                    },
                    error: function() {
                        alert('<?php _e('An error occurred. Please try again.', 'spiral-engine'); ?>');
                        $('.spiralengine-submit-button').prop('disabled', false).text('<?php _e('Complete Assessment', 'spiral-engine'); ?>');
                    }
                });
            });
            
            function showResults(data) {
                $('.spiralengine-assessment-form').fadeOut(function() {
                    var resultsHtml = '<h2><?php _e('Assessment Complete', 'spiral-engine'); ?></h2>';
                    resultsHtml += '<div class="spiralengine-score-display">';
                    resultsHtml += '<div class="spiralengine-score-circle" style="border-color: ' + data.risk_color + ';">';
                    resultsHtml += '<span class="spiralengine-score-number">' + data.total_score + '</span>';
                    resultsHtml += '<span class="spiralengine-score-total">/18</span>';
                    resultsHtml += '</div>';
                    resultsHtml += '<p class="spiralengine-risk-level" style="color: ' + data.risk_color + ';">' + data.risk_label + '</p>';
                    resultsHtml += '</div>';
                    
                    if (data.show_crisis_resources) {
                        resultsHtml += '<div class="spiralengine-crisis-resources">';
                        resultsHtml += data.crisis_resources;
                        resultsHtml += '</div>';
                    }
                    
                    resultsHtml += '<div class="spiralengine-next-steps">';
                    resultsHtml += '<p><?php _e('Thank you for completing your assessment. Your responses have been saved.', 'spiral-engine'); ?></p>';
                    resultsHtml += '<a href="' + data.dashboard_url + '" class="button"><?php _e('View Dashboard', 'spiral-engine'); ?></a>';
                    resultsHtml += '</div>';
                    
                    $('.spiralengine-assessment-results').html(resultsHtml).fadeIn();
                });
            }
        });
        </script>
        <?php endif; ?>
        
        <?php
        return ob_get_clean();
    }
    
    /**
     * Render assessment results
     */
    public function render_assessment_results($atts = array()) {
        $atts = shortcode_atts(array(
            'user_id' => get_current_user_id(),
            'days' => 30,
            'show_chart' => true
        ), $atts);
        
        if (!$atts['user_id']) {
            return '<p>' . __('No user specified.', 'spiral-engine') . '</p>';
        }
        
        $history = $this->get_assessment_history($atts['user_id'], $atts['days']);
        $trends = $this->get_assessment_trends($atts['user_id'], $atts['days']);
        
        ob_start();
        ?>
        <div class="spiralengine-assessment-results-container">
            <?php if (!empty($history)) : ?>
                <?php $latest = $history[0]; ?>
                <div class="spiralengine-latest-assessment">
                    <h3><?php _e('Latest Assessment', 'spiral-engine'); ?></h3>
                    <p><?php printf(__('Score: %d/18 (%s)', 'spiral-engine'), $latest['total_score'], $this->get_risk_level_label($latest['risk_level'])); ?></p>
                    <p><?php printf(__('Date: %s', 'spiral-engine'), date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($latest['created_at']))); ?></p>
                </div>
                
                <?php if ($atts['show_chart'] && !empty($trends)) : ?>
                <div class="spiralengine-trends-chart">
                    <h3><?php _e('30-Day Trend', 'spiral-engine'); ?></h3>
                    <canvas id="spiralengine-trends-canvas"></canvas>
                    <script>
                    jQuery(document).ready(function($) {
                        var ctx = document.getElementById('spiralengine-trends-canvas').getContext('2d');
                        var chart = new Chart(ctx, {
                            type: 'line',
                            data: {
                                labels: <?php echo json_encode(array_map(function($item) { return date_i18n('M j', strtotime($item->date)); }, $trends)); ?>,
                                datasets: [{
                                    label: '<?php _e('Average Score', 'spiral-engine'); ?>',
                                    data: <?php echo json_encode(array_map(function($item) { return round($item->avg_score, 1); }, $trends)); ?>,
                                    borderColor: '#2196F3',
                                    tension: 0.1
                                }]
                            },
                            options: {
                                scales: {
                                    y: {
                                        beginAtZero: true,
                                        max: 18
                                    }
                                }
                            }
                        });
                    });
                    </script>
                </div>
                <?php endif; ?>
            <?php else : ?>
                <p><?php _e('No assessments found.', 'spiral-engine'); ?></p>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * AJAX: Save assessment
     */
    public function ajax_save_assessment() {
        check_ajax_referer('spiralengine_assessment', 'assessment_nonce');
        
        $user_id = get_current_user_id();
        if (!$user_id) {
            wp_send_json_error(__('You must be logged in', 'spiral-engine'));
        }
        
        if (!isset($_POST['responses'])) {
            wp_send_json_error(__('No responses provided', 'spiral-engine'));
        }
        
        $responses = $_POST['responses'];
        $context = isset($_POST['context']) ? sanitize_text_field($_POST['context']) : 'daily_checkin';
        
        $result = $this->save_assessment($user_id, $responses, $context);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        // Prepare response
        $response = array(
            'assessment_id' => $result['assessment_id'],
            'total_score' => $result['total_score'],
            'risk_level' => $result['risk_level'],
            'risk_label' => $this->get_risk_level_label($result['risk_level']),
            'risk_color' => $this->get_risk_level_color($result['risk_level']),
            'dashboard_url' => home_url('/member-dashboard/'),
            'show_crisis_resources' => ($result['risk_level'] === 'high')
        );
        
        // Add crisis resources if needed
        if ($response['show_crisis_resources']) {
            ob_start();
            do_action('spiralengine_render_crisis_resources', $user_id, $result);
            $response['crisis_resources'] = ob_get_clean();
        }
        
        wp_send_json_success($response);
    }
    
    /**
     * AJAX: Get assessment history
     */
    public function ajax_get_assessment_history() {
        check_ajax_referer('spiralengine_ajax_nonce', 'nonce');
        
        $user_id = get_current_user_id();
        if (!$user_id) {
            wp_send_json_error(__('You must be logged in', 'spiral-engine'));
        }
        
        $limit = isset($_POST['limit']) ? intval($_POST['limit']) : 30;
        $offset = isset($_POST['offset']) ? intval($_POST['offset']) : 0;
        
        $history = $this->get_assessment_history($user_id, $limit, $offset);
        
        wp_send_json_success($history);
    }
    
    /**
     * Send daily reminders
     */
    public function send_daily_reminders() {
        // Get users who haven't assessed today
        global $wpdb;
        
        $users = $wpdb->get_results(
            "SELECT DISTINCT u.ID, u.user_email, u.display_name
             FROM {$wpdb->users} u
             LEFT JOIN {$wpdb->usermeta} um ON u.ID = um.user_id AND um.meta_key = 'spiralengine_last_assessment_date'
             WHERE um.meta_value IS NULL 
             OR DATE(um.meta_value) < CURDATE()
             AND EXISTS (
                 SELECT 1 FROM {$wpdb->usermeta} um2 
                 WHERE um2.user_id = u.ID 
                 AND um2.meta_key = 'spiralengine_daily_reminder' 
                 AND um2.meta_value = '1'
             )"
        );
        
        foreach ($users as $user) {
            $this->send_reminder_email($user);
        }
    }
    
    /**
     * Send reminder email
     */
    private function send_reminder_email($user) {
        $subject = __('Daily Check-in Reminder', 'spiral-engine');
        $assessment_url = home_url('/daily-assessment/');
        
        $message = sprintf(
            __("Hi %s,\n\nIt's time for your daily SPIRAL assessment. Taking a few moments to check in with yourself can help you track your wellness journey.\n\nComplete your assessment: %s\n\nBest regards,\nThe SPIRAL Engine Team", 'spiral-engine'),
            $user->display_name,
            $assessment_url
        );
        
        wp_mail($user->user_email, $subject, $message);
    }
    
    /**
     * Get pattern analysis for user
     */
    public function get_pattern_analysis($user_id, $days = 30) {
        global $wpdb;
        
        $start_date = date('Y-m-d', strtotime("-{$days} days"));
        
        // Get all assessments in period
        $assessments = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}spiralengine_assessments 
             WHERE user_id = %d AND created_at >= %s 
             ORDER BY created_at ASC",
            $user_id, $start_date
        ));
        
        if (count($assessments) < 3) {
            return null; // Not enough data
        }
        
        $patterns = array(
            'average_score' => 0,
            'trend' => 'stable',
            'volatility' => 'low',
            'peak_times' => array(),
            'triggers' => array()
        );
        
        // Calculate average
        $scores = array_map(function($a) { return $a->total_score; }, $assessments);
        $patterns['average_score'] = round(array_sum($scores) / count($scores), 1);
        
        // Determine trend
        $first_week_avg = array_sum(array_slice($scores, 0, 7)) / min(7, count($scores));
        $last_week_avg = array_sum(array_slice($scores, -7)) / min(7, count($scores));
        
        if ($last_week_avg < $first_week_avg - 2) {
            $patterns['trend'] = 'improving';
        } elseif ($last_week_avg > $first_week_avg + 2) {
            $patterns['trend'] = 'worsening';
        }
        
        // Calculate volatility
        $std_dev = $this->calculate_std_deviation($scores);
        if ($std_dev > 4) {
            $patterns['volatility'] = 'high';
        } elseif ($std_dev > 2) {
            $patterns['volatility'] = 'medium';
        }
        
        // Find peak times
        $time_scores = array();
        foreach ($assessments as $assessment) {
            $hour = date('H', strtotime($assessment->created_at));
            if (!isset($time_scores[$hour])) {
                $time_scores[$hour] = array();
            }
            $time_scores[$hour][] = $assessment->total_score;
        }
        
        foreach ($time_scores as $hour => $scores) {
            $avg = array_sum($scores) / count($scores);
            if ($avg > $patterns['average_score'] + 2) {
                $patterns['peak_times'][] = $hour;
            }
        }
        
        return $patterns;
    }
    
    /**
     * Calculate standard deviation
     */
    private function calculate_std_deviation($array) {
        $num_of_elements = count($array);
        $variance = 0.0;
        $average = array_sum($array) / $num_of_elements;
        
        foreach ($array as $i) {
            $variance += pow(($i - $average), 2);
        }
        
        return sqrt($variance / $num_of_elements);
    }
    
    /**
     * Get question-specific insights
     */
    public function get_question_insights($user_id, $days = 30) {
        global $wpdb;
        
        $start_date = date('Y-m-d', strtotime("-{$days} days"));
        
        $assessments = $wpdb->get_results($wpdb->prepare(
            "SELECT responses FROM {$wpdb->prefix}spiralengine_assessments 
             WHERE user_id = %d AND created_at >= %s",
            $user_id, $start_date
        ));
        
        if (empty($assessments)) {
            return null;
        }
        
        $question_totals = array();
        $count = 0;
        
        foreach ($assessments as $assessment) {
            $responses = json_decode($assessment->responses, true);
            foreach ($responses as $question => $score) {
                if (!isset($question_totals[$question])) {
                    $question_totals[$question] = 0;
                }
                $question_totals[$question] += $score;
            }
            $count++;
        }
        
        $insights = array();
        foreach ($question_totals as $question => $total) {
            $average = round($total / $count, 1);
            $insights[$question] = array(
                'average' => $average,
                'severity' => $this->get_severity_label($average),
                'question_text' => $this->questions[$question]['question']
            );
        }
        
        // Sort by average score (highest first)
        uasort($insights, function($a, $b) {
            return $b['average'] <=> $a['average'];
        });
        
        return $insights;
    }
    
    /**
     * Get severity label
     */
    private function get_severity_label($average) {
        if ($average >= 2.5) {
            return __('Severe', 'spiral-engine');
        } elseif ($average >= 1.5) {
            return __('Moderate', 'spiral-engine');
        } elseif ($average >= 0.5) {
            return __('Mild', 'spiral-engine');
        } else {
            return __('Minimal', 'spiral-engine');
        }
    }
}

// Initialize the class
SpiralEngine_Assessment::get_instance();

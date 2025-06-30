<?php
// includes/class-spiralengine-crisis.php

/**
 * SPIRAL Engine Crisis Intervention Class
 * 
 * Creates crisis system from master plans:
 * - Score detection (13+)
 * - Resource management
 * - Admin notifications
 * - Support widget
 */
class SpiralEngine_Crisis {
    
    /**
     * Instance of this class
     */
    private static $instance = null;
    
    /**
     * Crisis threshold score
     */
    private $crisis_threshold = 13;
    
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
        // Crisis detection
        add_action('spiralengine_assessment_completed', array($this, 'check_crisis_indicators'), 20, 2);
        add_action('spiralengine_crisis_intervention_needed', array($this, 'handle_crisis_intervention'), 10, 2);
        
        // Crisis resources rendering
        add_action('spiralengine_render_crisis_resources', array($this, 'render_crisis_resources'), 10, 2);
        
        // Admin interface
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('wp_dashboard_setup', array($this, 'add_dashboard_widget'));
        
        // AJAX handlers
        add_action('wp_ajax_spiralengine_acknowledge_crisis', array($this, 'ajax_acknowledge_crisis'));
        add_action('wp_ajax_spiralengine_update_crisis_resources', array($this, 'ajax_update_crisis_resources'));
        add_action('wp_ajax_spiralengine_contact_crisis_user', array($this, 'ajax_contact_crisis_user'));
        
        // Shortcodes
        add_shortcode('spiralengine_crisis_resources', array($this, 'crisis_resources_shortcode'));
        add_shortcode('spiralengine_safety_plan', array($this, 'safety_plan_shortcode'));
        
        // Crisis widget in member dashboard
        add_action('spiralengine_dashboard_widgets', array($this, 'add_crisis_widget'));
        
        // Daily crisis check
        add_action('spiralengine_daily_cron', array($this, 'daily_crisis_check'));
        
        // Scripts and styles
        add_action('wp_enqueue_scripts', array($this, 'enqueue_crisis_scripts'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
    }
    
    /**
     * Check crisis indicators
     */
    public function check_crisis_indicators($user_id, $assessment_data) {
        // Check total score
        if ($assessment_data['total_score'] >= $this->crisis_threshold) {
            $this->trigger_crisis_intervention($user_id, $assessment_data, 'high_score');
            return;
        }
        
        // Check specific high responses
        $critical_questions = array('catastrophizing', 'loss_of_control');
        foreach ($critical_questions as $question) {
            if (isset($assessment_data['responses'][$question]) && $assessment_data['responses'][$question] >= 3) {
                $this->trigger_crisis_intervention($user_id, $assessment_data, 'critical_response');
                return;
            }
        }
        
        // Check pattern escalation
        if ($this->check_escalation_pattern($user_id)) {
            $this->trigger_crisis_intervention($user_id, $assessment_data, 'escalation_pattern');
            return;
        }
        
        // Check keywords in notes (if any)
        if (isset($assessment_data['notes']) && $this->contains_crisis_keywords($assessment_data['notes'])) {
            $this->trigger_crisis_intervention($user_id, $assessment_data, 'crisis_keywords');
            return;
        }
    }
    
    /**
     * Trigger crisis intervention
     */
    private function trigger_crisis_intervention($user_id, $assessment_data, $trigger_reason) {
        global $wpdb;
        
        // Record crisis event
        $crisis_id = $wpdb->insert(
            $wpdb->prefix . 'spiralengine_crisis_events',
            array(
                'user_id' => $user_id,
                'assessment_id' => $assessment_data['assessment_id'] ?? null,
                'trigger_reason' => $trigger_reason,
                'score' => $assessment_data['total_score'],
                'status' => 'active',
                'created_at' => current_time('mysql')
            )
        );
        
        // Update user crisis status
        update_user_meta($user_id, 'spiralengine_crisis_status', 'active');
        update_user_meta($user_id, 'spiralengine_last_crisis_date', current_time('mysql'));
        
        // Trigger action for other components
        do_action('spiralengine_crisis_intervention_needed', $user_id, array(
            'crisis_id' => $wpdb->insert_id,
            'assessment_data' => $assessment_data,
            'trigger_reason' => $trigger_reason
        ));
    }
    
    /**
     * Handle crisis intervention
     */
    public function handle_crisis_intervention($user_id, $crisis_data) {
        // Show immediate resources to user
        set_transient('spiralengine_show_crisis_resources_' . $user_id, true, HOUR_IN_SECONDS);
        
        // Notify administrators
        if (get_option('spiralengine_crisis_admin_notify', true)) {
            $this->notify_crisis_team($user_id, $crisis_data);
        }
        
        // Create safety plan reminder
        update_user_meta($user_id, 'spiralengine_needs_safety_plan', true);
        
        // Log the intervention
        $this->log_crisis_intervention($user_id, $crisis_data);
        
        // Check if emergency contact should be notified
        if ($this->should_notify_emergency_contact($user_id, $crisis_data)) {
            $this->notify_emergency_contact($user_id, $crisis_data);
        }
    }
    
    /**
     * Check escalation pattern
     */
    private function check_escalation_pattern($user_id) {
        global $wpdb;
        
        // Get last 3 assessments
        $recent_scores = $wpdb->get_col($wpdb->prepare(
            "SELECT total_score FROM {$wpdb->prefix}spiralengine_assessments 
             WHERE user_id = %d 
             ORDER BY created_at DESC 
             LIMIT 3",
            $user_id
        ));
        
        if (count($recent_scores) < 3) {
            return false;
        }
        
        // Check if scores are increasing
        $escalating = true;
        for ($i = 0; $i < count($recent_scores) - 1; $i++) {
            if ($recent_scores[$i] <= $recent_scores[$i + 1]) {
                $escalating = false;
                break;
            }
        }
        
        // Also check if current score is significantly higher than average
        $avg_score = array_sum($recent_scores) / count($recent_scores);
        return $escalating && $recent_scores[0] > $avg_score * 1.5;
    }
    
    /**
     * Contains crisis keywords
     */
    private function contains_crisis_keywords($text) {
        $keywords = get_option('spiralengine_crisis_keywords', array(
            'suicide', 'kill myself', 'end it all', 'not worth living',
            'hurt myself', 'self harm', 'cutting', 'overdose',
            'no hope', 'no point', 'better off dead', 'want to die'
        ));
        
        $text_lower = strtolower($text);
        
        foreach ($keywords as $keyword) {
            if (strpos($text_lower, strtolower($keyword)) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Render crisis resources
     */
    public function render_crisis_resources($user_id = null, $context = array()) {
        $resources = $this->get_crisis_resources();
        $user_region = get_user_meta($user_id, 'spiralengine_region', true) ?: 'usa';
        
        ?>
        <div class="spiralengine-crisis-resources" id="crisis-resources">
            <div class="crisis-header">
                <h2><?php _e('You\'re Not Alone - Help is Available', 'spiral-engine'); ?></h2>
                <p><?php _e('Your assessment indicates you may be experiencing significant distress. Please reach out for support.', 'spiral-engine'); ?></p>
            </div>
            
            <!-- Immediate Help -->
            <div class="crisis-immediate-help">
                <h3><?php _e('Need Immediate Help?', 'spiral-engine'); ?></h3>
                
                <?php if (isset($resources[$user_region])) : ?>
                    <?php foreach ($resources[$user_region]['hotlines'] as $hotline) : ?>
                    <div class="crisis-hotline">
                        <h4><?php echo esc_html($hotline['name']); ?></h4>
                        <p class="hotline-number">
                            <a href="tel:<?php echo esc_attr($hotline['number']); ?>" class="crisis-phone">
                                <span class="dashicons dashicons-phone"></span>
                                <?php echo esc_html($hotline['number']); ?>
                            </a>
                        </p>
                        <?php if (isset($hotline['text'])) : ?>
                        <p class="hotline-text">
                            <?php printf(__('Text %s to %s', 'spiral-engine'), 
                                '<strong>' . esc_html($hotline['text']['keyword']) . '</strong>', 
                                '<strong>' . esc_html($hotline['text']['number']) . '</strong>'
                            ); ?>
                        </p>
                        <?php endif; ?>
                        <?php if (isset($hotline['chat'])) : ?>
                        <p class="hotline-chat">
                            <a href="<?php echo esc_url($hotline['chat']); ?>" target="_blank" class="button button-secondary">
                                <?php _e('Online Chat', 'spiral-engine'); ?>
                            </a>
                        </p>
                        <?php endif; ?>
                        <p class="hotline-hours"><?php echo esc_html($hotline['hours']); ?></p>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
                
                <div class="crisis-emergency">
                    <p><strong><?php _e('If you are in immediate danger:', 'spiral-engine'); ?></strong></p>
                    <a href="tel:911" class="crisis-emergency-button">
                        <?php _e('Call Emergency Services (911)', 'spiral-engine'); ?>
                    </a>
                </div>
            </div>
            
            <!-- Coping Strategies -->
            <div class="crisis-coping">
                <h3><?php _e('Immediate Coping Strategies', 'spiral-engine'); ?></h3>
                <ul>
                    <li><?php _e('Take slow, deep breaths (4 counts in, 6 counts out)', 'spiral-engine'); ?></li>
                    <li><?php _e('Ground yourself: Name 5 things you can see, 4 you can touch, 3 you can hear, 2 you can smell, 1 you can taste', 'spiral-engine'); ?></li>
                    <li><?php _e('Call a trusted friend or family member', 'spiral-engine'); ?></li>
                    <li><?php _e('Go to a safe, comfortable space', 'spiral-engine'); ?></li>
                    <li><?php _e('Engage in a calming activity (music, drawing, walking)', 'spiral-engine'); ?></li>
                </ul>
            </div>
            
            <!-- Safety Plan -->
            <?php if ($user_id && is_user_logged_in()) : ?>
            <div class="crisis-safety-plan">
                <h3><?php _e('Your Safety Plan', 'spiral-engine'); ?></h3>
                <?php 
                $has_safety_plan = get_user_meta($user_id, 'spiralengine_safety_plan', true);
                if ($has_safety_plan) : 
                ?>
                    <p><?php _e('You have a safety plan in place.', 'spiral-engine'); ?></p>
                    <a href="<?php echo home_url('/my-safety-plan/'); ?>" class="button">
                        <?php _e('View My Safety Plan', 'spiral-engine'); ?>
                    </a>
                <?php else : ?>
                    <p><?php _e('Creating a safety plan can help you prepare for difficult moments.', 'spiral-engine'); ?></p>
                    <a href="<?php echo home_url('/create-safety-plan/'); ?>" class="button button-primary">
                        <?php _e('Create a Safety Plan', 'spiral-engine'); ?>
                    </a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <!-- Additional Resources -->
            <div class="crisis-additional">
                <h3><?php _e('Additional Resources', 'spiral-engine'); ?></h3>
                <ul>
                    <li><a href="https://www.crisistextline.org/" target="_blank"><?php _e('Crisis Text Line', 'spiral-engine'); ?></a></li>
                    <li><a href="https://suicidepreventionlifeline.org/" target="_blank"><?php _e('National Suicide Prevention Lifeline', 'spiral-engine'); ?></a></li>
                    <li><a href="https://www.nami.org/help" target="_blank"><?php _e('NAMI (National Alliance on Mental Illness)', 'spiral-engine'); ?></a></li>
                    <li><a href="https://www.samhsa.gov/find-help" target="_blank"><?php _e('SAMHSA Treatment Locator', 'spiral-engine'); ?></a></li>
                </ul>
            </div>
            
            <!-- Follow-up Actions -->
            <?php if ($user_id && is_user_logged_in()) : ?>
            <div class="crisis-followup">
                <h3><?php _e('Next Steps', 'spiral-engine'); ?></h3>
                <p><?php _e('We care about your wellbeing. Here are some recommended actions:', 'spiral-engine'); ?></p>
                <ul>
                    <li><?php _e('Schedule an appointment with a mental health professional', 'spiral-engine'); ?></li>
                    <li><?php _e('Share your results with a trusted healthcare provider', 'spiral-engine'); ?></li>
                    <li><?php _e('Consider joining a support group', 'spiral-engine'); ?></li>
                    <li><?php _e('Continue daily check-ins to track your progress', 'spiral-engine'); ?></li>
                </ul>
                
                <div class="crisis-acknowledge">
                    <label>
                        <input type="checkbox" id="crisis-resources-seen" />
                        <?php _e('I have reviewed these resources and will seek help if needed', 'spiral-engine'); ?>
                    </label>
                    <button id="acknowledge-crisis" class="button" disabled>
                        <?php _e('Continue to Dashboard', 'spiral-engine'); ?>
                    </button>
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <style>
        .spiralengine-crisis-resources {
            background: #fff3cd;
            border: 2px solid #ffc107;
            border-radius: 8px;
            padding: 30px;
            margin: 20px 0;
            max-width: 800px;
        }
        
        .crisis-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .crisis-header h2 {
            color: #721c24;
            font-size: 28px;
            margin-bottom: 10px;
        }
        
        .crisis-immediate-help {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            border-radius: 5px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .crisis-hotline {
            background: white;
            padding: 15px;
            margin-bottom: 15px;
            border-radius: 5px;
        }
        
        .crisis-hotline h4 {
            margin: 0 0 10px 0;
            color: #721c24;
        }
        
        .hotline-number a {
            font-size: 24px;
            font-weight: bold;
            color: #dc3545;
            text-decoration: none;
        }
        
        .crisis-emergency-button {
            display: inline-block;
            background: #dc3545;
            color: white;
            padding: 15px 30px;
            font-size: 18px;
            font-weight: bold;
            text-decoration: none;
            border-radius: 5px;
            margin-top: 10px;
        }
        
        .crisis-coping,
        .crisis-safety-plan,
        .crisis-additional,
        .crisis-followup {
            margin-top: 30px;
        }
        
        .crisis-acknowledge {
            margin-top: 20px;
            padding: 20px;
            background: #e3f2fd;
            border-radius: 5px;
        }
        
        .crisis-acknowledge label {
            display: block;
            margin-bottom: 10px;
        }
        
        .crisis-acknowledge button:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            $('#crisis-resources-seen').on('change', function() {
                $('#acknowledge-crisis').prop('disabled', !this.checked);
            });
            
            $('#acknowledge-crisis').on('click', function() {
                $.ajax({
                    url: spiralengine_ajax.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'spiralengine_acknowledge_crisis',
                        nonce: spiralengine_ajax.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            window.location.href = response.data.redirect_url;
                        }
                    }
                });
            });
        });
        </script>
        <?php
    }
    
    /**
     * Get crisis resources by region
     */
    private function get_crisis_resources() {
        return array(
            'usa' => array(
                'hotlines' => array(
                    array(
                        'name' => __('988 Suicide & Crisis Lifeline', 'spiral-engine'),
                        'number' => '988',
                        'text' => array('keyword' => 'HELLO', 'number' => '741741'),
                        'chat' => 'https://988lifeline.org/chat',
                        'hours' => __('24/7, Free and Confidential', 'spiral-engine')
                    ),
                    array(
                        'name' => __('Crisis Text Line', 'spiral-engine'),
                        'number' => '741741',
                        'text' => array('keyword' => 'HOME', 'number' => '741741'),
                        'hours' => __('24/7 Text Support', 'spiral-engine')
                    )
                )
            ),
            'uk' => array(
                'hotlines' => array(
                    array(
                        'name' => __('Samaritans', 'spiral-engine'),
                        'number' => '116 123',
                        'email' => 'jo@samaritans.org',
                        'hours' => __('24/7, Free from any phone', 'spiral-engine')
                    )
                )
            ),
            'canada' => array(
                'hotlines' => array(
                    array(
                        'name' => __('Talk Suicide Canada', 'spiral-engine'),
                        'number' => '1-833-456-4566',
                        'text' => array('keyword' => 'CONNECT', 'number' => '686868'),
                        'hours' => __('24/7 Support', 'spiral-engine')
                    )
                )
            ),
            'australia' => array(
                'hotlines' => array(
                    array(
                        'name' => __('Lifeline Australia', 'spiral-engine'),
                        'number' => '13 11 14',
                        'text' => array('number' => '0477 13 11 14'),
                        'chat' => 'https://www.lifeline.org.au/crisis-chat/',
                        'hours' => __('24/7 Crisis Support', 'spiral-engine')
                    )
                )
            )
        );
    }
    
    /**
     * Notify crisis team
     */
    private function notify_crisis_team($user_id, $crisis_data) {
        $user = get_userdata($user_id);
        $admin_emails = $this->get_crisis_team_emails();
        
        $subject = sprintf(
            __('[URGENT] Crisis Intervention Needed - %s', 'spiral-engine'),
            $user->display_name
        );
        
        $message = $this->build_crisis_notification_email($user, $crisis_data);
        
        // Send to each crisis team member
        foreach ($admin_emails as $email) {
            wp_mail($email, $subject, $message, array('Content-Type: text/html; charset=UTF-8'));
        }
        
        // Log notification
        $this->log_crisis_notification($user_id, $crisis_data, $admin_emails);
    }
    
    /**
     * Get crisis team emails
     */
    private function get_crisis_team_emails() {
        $emails = get_option('spiralengine_crisis_team_emails', array());
        
        if (empty($emails)) {
            // Default to admin email
            $emails = array(get_option('admin_email'));
        }
        
        // Add users with crisis_responder role
        $crisis_responders = get_users(array(
            'role' => 'spiralengine_crisis_responder',
            'fields' => 'user_email'
        ));
        
        foreach ($crisis_responders as $responder_email) {
            $emails[] = $responder_email;
        }
        
        return array_unique($emails);
    }
    
    /**
     * Build crisis notification email
     */
    private function build_crisis_notification_email($user, $crisis_data) {
        ob_start();
        ?>
        <html>
        <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
            <div style="background: #f8d7da; padding: 20px; border-radius: 5px; margin-bottom: 20px;">
                <h2 style="color: #721c24; margin: 0;"><?php _e('Crisis Intervention Alert', 'spiral-engine'); ?></h2>
            </div>
            
            <h3><?php _e('User Information', 'spiral-engine'); ?></h3>
            <table style="width: 100%; border-collapse: collapse;">
                <tr>
                    <td style="padding: 5px; border-bottom: 1px solid #ddd;"><strong><?php _e('Name:', 'spiral-engine'); ?></strong></td>
                    <td style="padding: 5px; border-bottom: 1px solid #ddd;"><?php echo esc_html($user->display_name); ?></td>
                </tr>
                <tr>
                    <td style="padding: 5px; border-bottom: 1px solid #ddd;"><strong><?php _e('Email:', 'spiral-engine'); ?></strong></td>
                    <td style="padding: 5px; border-bottom: 1px solid #ddd;"><?php echo esc_html($user->user_email); ?></td>
                </tr>
                <tr>
                    <td style="padding: 5px; border-bottom: 1px solid #ddd;"><strong><?php _e('User ID:', 'spiral-engine'); ?></strong></td>
                    <td style="padding: 5px; border-bottom: 1px solid #ddd;"><?php echo esc_html($user->ID); ?></td>
                </tr>
            </table>
            
            <h3><?php _e('Crisis Details', 'spiral-engine'); ?></h3>
            <table style="width: 100%; border-collapse: collapse;">
                <tr>
                    <td style="padding: 5px; border-bottom: 1px solid #ddd;"><strong><?php _e('Assessment Score:', 'spiral-engine'); ?></strong></td>
                    <td style="padding: 5px; border-bottom: 1px solid #ddd;">
                        <?php echo esc_html($crisis_data['assessment_data']['total_score']); ?>/18
                        <span style="color: #dc3545; font-weight: bold;">
                            (<?php echo esc_html($crisis_data['assessment_data']['risk_level']); ?>)
                        </span>
                    </td>
                </tr>
                <tr>
                    <td style="padding: 5px; border-bottom: 1px solid #ddd;"><strong><?php _e('Trigger:', 'spiral-engine'); ?></strong></td>
                    <td style="padding: 5px; border-bottom: 1px solid #ddd;"><?php echo esc_html($crisis_data['trigger_reason']); ?></td>
                </tr>
                <tr>
                    <td style="padding: 5px; border-bottom: 1px solid #ddd;"><strong><?php _e('Time:', 'spiral-engine'); ?></strong></td>
                    <td style="padding: 5px; border-bottom: 1px solid #ddd;"><?php echo current_time('mysql'); ?></td>
                </tr>
            </table>
            
            <h3><?php _e('Required Actions', 'spiral-engine'); ?></h3>
            <ol>
                <li><?php _e('Review user\'s recent assessment history', 'spiral-engine'); ?></li>
                <li><?php _e('Consider reaching out to the user directly', 'spiral-engine'); ?></li>
                <li><?php _e('Document any interventions taken', 'spiral-engine'); ?></li>
                <li><?php _e('Follow your organization\'s crisis response protocol', 'spiral-engine'); ?></li>
            </ol>
            
            <div style="margin-top: 30px; padding: 20px; background: #e3f2fd; border-radius: 5px;">
                <h4 style="margin-top: 0;"><?php _e('Quick Actions', 'spiral-engine'); ?></h4>
                <p>
                    <a href="<?php echo admin_url('user-edit.php?user_id=' . $user->ID . '#spiralengine-crisis'); ?>" 
                       style="display: inline-block; padding: 10px 20px; background: #2196F3; color: white; text-decoration: none; border-radius: 3px;">
                        <?php _e('View User Profile', 'spiral-engine'); ?>
                    </a>
                    <a href="<?php echo admin_url('admin.php?page=spiralengine-crisis&user_id=' . $user->ID); ?>" 
                       style="display: inline-block; padding: 10px 20px; background: #4CAF50; color: white; text-decoration: none; border-radius: 3px; margin-left: 10px;">
                        <?php _e('Crisis Dashboard', 'spiral-engine'); ?>
                    </a>
                </p>
            </div>
            
            <p style="margin-top: 30px; color: #666; font-size: 12px;">
                <?php _e('This is an automated alert from the SPIRAL Engine crisis detection system. Please respond according to your organization\'s protocols.', 'spiral-engine'); ?>
            </p>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Log crisis notification
     */
    private function log_crisis_notification($user_id, $crisis_data, $notified_emails) {
        global $wpdb;
        
        $wpdb->insert(
            $wpdb->prefix . 'spiralengine_crisis_notifications',
            array(
                'user_id' => $user_id,
                'crisis_id' => $crisis_data['crisis_id'],
                'notified_emails' => json_encode($notified_emails),
                'notification_type' => 'admin_alert',
                'sent_at' => current_time('mysql')
            )
        );
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_submenu_page(
            'spiralengine',
            __('Crisis Management', 'spiral-engine'),
            __('Crisis Management', 'spiral-engine'),
            'edit_users',
            'spiralengine-crisis',
            array($this, 'render_admin_page')
        );
    }
    
    /**
     * Render admin page
     */
    public function render_admin_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('Crisis Management Center', 'spiral-engine'); ?></h1>
            
            <?php
            // Show specific user crisis if requested
            if (isset($_GET['user_id'])) {
                $this->render_user_crisis_details(intval($_GET['user_id']));
                return;
            }
            ?>
            
            <!-- Crisis Statistics -->
            <div class="spiralengine-crisis-stats">
                <?php $this->render_crisis_statistics(); ?>
            </div>
            
            <!-- Active Crisis Events -->
            <h2><?php _e('Active Crisis Events', 'spiral-engine'); ?></h2>
            <?php $this->render_active_crisis_table(); ?>
            
            <!-- Crisis Configuration -->
            <h2><?php _e('Crisis Configuration', 'spiral-engine'); ?></h2>
            <form method="post" action="options.php">
                <?php settings_fields('spiralengine_crisis_settings'); ?>
                
                <table class="form-table">
                    <tr>
                        <th><?php _e('Crisis Team Emails', 'spiral-engine'); ?></th>
                        <td>
                            <textarea name="spiralengine_crisis_team_emails" rows="5" cols="50"><?php 
                                $emails = get_option('spiralengine_crisis_team_emails', array());
                                echo esc_textarea(implode("\n", $emails));
                            ?></textarea>
                            <p class="description"><?php _e('One email per line. These addresses will receive crisis alerts.', 'spiral-engine'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th><?php _e('Crisis Threshold Score', 'spiral-engine'); ?></th>
                        <td>
                            <input type="number" name="spiralengine_crisis_threshold" value="<?php echo esc_attr(get_option('spiralengine_crisis_threshold', 13)); ?>" min="1" max="18" />
                            <p class="description"><?php _e('Assessment scores at or above this value trigger crisis intervention.', 'spiral-engine'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th><?php _e('Admin Notifications', 'spiral-engine'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="spiralengine_crisis_admin_notify" value="1" <?php checked(get_option('spiralengine_crisis_admin_notify', true)); ?> />
                                <?php _e('Send email alerts to crisis team', 'spiral-engine'); ?>
                            </label>
                        </td>
                    </tr>
                    
                    <tr>
                        <th><?php _e('Auto-Response', 'spiral-engine'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="spiralengine_crisis_auto_response" value="1" <?php checked(get_option('spiralengine_crisis_auto_response', true)); ?> />
                                <?php _e('Automatically show crisis resources to high-risk users', 'spiral-engine'); ?>
                            </label>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(); ?>
            </form>
            
            <!-- Crisis Resources Management -->
            <h2><?php _e('Crisis Resources', 'spiral-engine'); ?></h2>
            <div class="spiralengine-crisis-resources-admin">
                <p><?php _e('Manage crisis hotlines and resources shown to users.', 'spiral-engine'); ?></p>
                <a href="<?php echo admin_url('admin.php?page=spiralengine-crisis-resources'); ?>" class="button">
                    <?php _e('Manage Resources', 'spiral-engine'); ?>
                </a>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render crisis statistics
     */
    private function render_crisis_statistics() {
        global $wpdb;
        
        // Get statistics
        $active_crises = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}spiralengine_crisis_events 
             WHERE status = 'active'"
        );
        
        $today_crises = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}spiralengine_crisis_events 
             WHERE DATE(created_at) = CURDATE()"
        );
        
        $week_crises = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}spiralengine_crisis_events 
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
        );
        
        $resolved_rate = $wpdb->get_var(
            "SELECT ROUND(100 * SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) / COUNT(*), 1)
             FROM {$wpdb->prefix}spiralengine_crisis_events 
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)"
        ) ?: 0;
        
        ?>
        <div class="spiralengine-stats-grid">
            <div class="stat-box crisis-active">
                <h3><?php echo number_format($active_crises); ?></h3>
                <p><?php _e('Active Crises', 'spiral-engine'); ?></p>
            </div>
            
            <div class="stat-box">
                <h3><?php echo number_format($today_crises); ?></h3>
                <p><?php _e('Today', 'spiral-engine'); ?></p>
            </div>
            
            <div class="stat-box">
                <h3><?php echo number_format($week_crises); ?></h3>
                <p><?php _e('This Week', 'spiral-engine'); ?></p>
            </div>
            
            <div class="stat-box">
                <h3><?php echo $resolved_rate; ?>%</h3>
                <p><?php _e('Resolution Rate', 'spiral-engine'); ?></p>
            </div>
        </div>
        
        <style>
        .spiralengine-stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }
        
        .stat-box {
            background: #fff;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 5px;
            text-align: center;
        }
        
        .stat-box.crisis-active {
            background: #f8d7da;
            border-color: #f5c6cb;
        }
        
        .stat-box h3 {
            font-size: 32px;
            margin: 0;
            color: #333;
        }
        
        .stat-box p {
            margin: 5px 0 0 0;
            color: #666;
        }
        </style>
        <?php
    }
    
    /**
     * Render active crisis table
     */
    private function render_active_crisis_table() {
        global $wpdb;
        
        $active_crises = $wpdb->get_results(
            "SELECT c.*, u.display_name, u.user_email 
             FROM {$wpdb->prefix}spiralengine_crisis_events c
             JOIN {$wpdb->users} u ON c.user_id = u.ID
             WHERE c.status = 'active'
             ORDER BY c.created_at DESC
             LIMIT 20"
        );
        
        if (empty($active_crises)) {
            echo '<p>' . __('No active crisis events.', 'spiral-engine') . '</p>';
            return;
        }
        
        ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php _e('User', 'spiral-engine'); ?></th>
                    <th><?php _e('Score', 'spiral-engine'); ?></th>
                    <th><?php _e('Trigger', 'spiral-engine'); ?></th>
                    <th><?php _e('Time', 'spiral-engine'); ?></th>
                    <th><?php _e('Status', 'spiral-engine'); ?></th>
                    <th><?php _e('Actions', 'spiral-engine'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($active_crises as $crisis) : ?>
                <tr>
                    <td>
                        <strong><?php echo esc_html($crisis->display_name); ?></strong><br>
                        <small><?php echo esc_html($crisis->user_email); ?></small>
                    </td>
                    <td>
                        <span style="color: #dc3545; font-weight: bold;">
                            <?php echo esc_html($crisis->score); ?>/18
                        </span>
                    </td>
                    <td><?php echo esc_html($crisis->trigger_reason); ?></td>
                    <td><?php echo human_time_diff(strtotime($crisis->created_at), current_time('timestamp')) . ' ' . __('ago', 'spiral-engine'); ?></td>
                    <td>
                        <span class="crisis-status-<?php echo esc_attr($crisis->status); ?>">
                            <?php echo esc_html(ucfirst($crisis->status)); ?>
                        </span>
                    </td>
                    <td>
                        <a href="<?php echo admin_url('admin.php?page=spiralengine-crisis&user_id=' . $crisis->user_id); ?>" class="button button-small">
                            <?php _e('View', 'spiral-engine'); ?>
                        </a>
                        <button class="button button-small spiralengine-contact-user" data-user-id="<?php echo esc_attr($crisis->user_id); ?>">
                            <?php _e('Contact', 'spiral-engine'); ?>
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }
    
    /**
     * Render user crisis details
     */
    private function render_user_crisis_details($user_id) {
        $user = get_userdata($user_id);
        if (!$user) {
            echo '<p>' . __('User not found.', 'spiral-engine') . '</p>';
            return;
        }
        
        ?>
        <h2><?php printf(__('Crisis Details for %s', 'spiral-engine'), esc_html($user->display_name)); ?></h2>
        
        <div class="spiralengine-crisis-user-details">
            <!-- User Info -->
            <div class="user-info-box">
                <h3><?php _e('User Information', 'spiral-engine'); ?></h3>
                <?php $this->render_user_crisis_info($user); ?>
            </div>
            
            <!-- Recent Assessments -->
            <div class="assessments-box">
                <h3><?php _e('Recent Assessments', 'spiral-engine'); ?></h3>
                <?php $this->render_user_recent_assessments($user_id); ?>
            </div>
            
            <!-- Crisis History -->
            <div class="crisis-history-box">
                <h3><?php _e('Crisis History', 'spiral-engine'); ?></h3>
                <?php $this->render_user_crisis_history($user_id); ?>
            </div>
            
            <!-- Actions -->
            <div class="crisis-actions-box">
                <h3><?php _e('Actions', 'spiral-engine'); ?></h3>
                <?php $this->render_crisis_actions($user_id); ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Add dashboard widget
     */
    public function add_dashboard_widget() {
        if (current_user_can('edit_users')) {
            wp_add_dashboard_widget(
                'spiralengine_crisis_widget',
                __('SPIRAL Engine Crisis Alerts', 'spiral-engine'),
                array($this, 'render_dashboard_widget')
            );
        }
    }
    
    /**
     * Render dashboard widget
     */
    public function render_dashboard_widget() {
        global $wpdb;
        
        $active_crises = $wpdb->get_results(
            "SELECT c.*, u.display_name 
             FROM {$wpdb->prefix}spiralengine_crisis_events c
             JOIN {$wpdb->users} u ON c.user_id = u.ID
             WHERE c.status = 'active'
             ORDER BY c.created_at DESC
             LIMIT 5"
        );
        
        if (empty($active_crises)) {
            echo '<p>' . __('No active crisis events.', 'spiral-engine') . '</p>';
            return;
        }
        
        ?>
        <ul class="spiralengine-crisis-widget-list">
            <?php foreach ($active_crises as $crisis) : ?>
            <li>
                <strong><?php echo esc_html($crisis->display_name); ?></strong>
                <span style="color: #dc3545;">(Score: <?php echo esc_html($crisis->score); ?>)</span>
                <br>
                <small><?php echo human_time_diff(strtotime($crisis->created_at), current_time('timestamp')) . ' ' . __('ago', 'spiral-engine'); ?></small>
                <a href="<?php echo admin_url('admin.php?page=spiralengine-crisis&user_id=' . $crisis->user_id); ?>" style="float: right;">
                    <?php _e('View', 'spiral-engine'); ?>
                </a>
            </li>
            <?php endforeach; ?>
        </ul>
        
        <p class="submit">
            <a href="<?php echo admin_url('admin.php?page=spiralengine-crisis'); ?>" class="button button-primary">
                <?php _e('View All Crisis Events', 'spiral-engine'); ?>
            </a>
        </p>
        <?php
    }
    
    /**
     * Enqueue crisis scripts
     */
    public function enqueue_crisis_scripts() {
        if (is_user_logged_in()) {
            $user_id = get_current_user_id();
            $show_resources = get_transient('spiralengine_show_crisis_resources_' . $user_id);
            
            if ($show_resources) {
                wp_enqueue_script(
                    'spiralengine-crisis',
                    SPIRALENGINE_URL . 'assets/js/crisis.js',
                    array('jquery'),
                    SPIRALENGINE_VERSION,
                    true
                );
                
                wp_localize_script('spiralengine-crisis', 'spiralengine_crisis', array(
                    'ajax_url' => admin_url('admin-ajax.php'),
                    'nonce' => wp_create_nonce('spiralengine_crisis_nonce'),
                    'show_resources' => true
                ));
            }
        }
    }
    
    /**
     * AJAX: Acknowledge crisis resources
     */
    public function ajax_acknowledge_crisis() {
        check_ajax_referer('spiralengine_crisis_nonce', 'nonce');
        
        $user_id = get_current_user_id();
        if (!$user_id) {
            wp_send_json_error(__('Not logged in', 'spiral-engine'));
        }
        
        // Clear the transient
        delete_transient('spiralengine_show_crisis_resources_' . $user_id);
        
        // Update user meta
        update_user_meta($user_id, 'spiralengine_crisis_acknowledged', current_time('mysql'));
        
        // Update crisis event status
        global $wpdb;
        $wpdb->update(
            $wpdb->prefix . 'spiralengine_crisis_events',
            array(
                'status' => 'acknowledged',
                'acknowledged_at' => current_time('mysql')
            ),
            array(
                'user_id' => $user_id,
                'status' => 'active'
            )
        );
        
        wp_send_json_success(array(
            'redirect_url' => home_url('/member-dashboard/')
        ));
    }
    
    /**
     * Daily crisis check
     */
    public function daily_crisis_check() {
        global $wpdb;
        
        // Check for users with persistent high scores
        $persistent_crisis_users = $wpdb->get_results(
            "SELECT user_id, AVG(total_score) as avg_score, COUNT(*) as assessment_count
             FROM {$wpdb->prefix}spiralengine_assessments
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
             GROUP BY user_id
             HAVING avg_score >= 11 AND assessment_count >= 3"
        );
        
        foreach ($persistent_crisis_users as $user_data) {
            // Check if already flagged
            $recent_flag = get_user_meta($user_data->user_id, 'spiralengine_persistent_crisis_flagged', true);
            
            if (!$recent_flag || (time() - strtotime($recent_flag)) > WEEK_IN_SECONDS) {
                // Flag user and notify
                update_user_meta($user_data->user_id, 'spiralengine_persistent_crisis_flagged', current_time('mysql'));
                
                // Send special notification
                $this->notify_persistent_crisis($user_data->user_id, $user_data->avg_score);
            }
        }
    }
    
    /**
     * Crisis resources shortcode
     */
    public function crisis_resources_shortcode($atts) {
        $atts = shortcode_atts(array(
            'region' => 'usa',
            'show_all' => false
        ), $atts);
        
        ob_start();
        $this->render_crisis_resources(null, array('region' => $atts['region']));
        return ob_get_clean();
    }
    
    /**
     * Should notify emergency contact
     */
    private function should_notify_emergency_contact($user_id, $crisis_data) {
        // Check if user has emergency contact
        $emergency_contact = get_user_meta($user_id, 'spiralengine_emergency_contact', true);
        if (!$emergency_contact) {
            return false;
        }
        
        // Check if user has granted permission
        $permission = get_user_meta($user_id, 'spiralengine_emergency_contact_permission', true);
        if (!$permission) {
            return false;
        }
        
        // Check severity
        return $crisis_data['assessment_data']['total_score'] >= 16;
    }
    
    /**
     * Log crisis intervention
     */
    private function log_crisis_intervention($user_id, $crisis_data) {
        global $wpdb;
        
        $wpdb->insert(
            $wpdb->prefix . 'spiralengine_crisis_log',
            array(
                'user_id' => $user_id,
                'crisis_id' => $crisis_data['crisis_id'],
                'action' => 'intervention_triggered',
                'details' => json_encode($crisis_data),
                'performed_by' => get_current_user_id() ?: 0,
                'timestamp' => current_time('mysql')
            )
        );
    }
}

// Initialize the class
SpiralEngine_Crisis::get_instance();

<?php
// includes/class-spiralengine-users.php

/**
 * SPIRAL Engine User Management Class
 * 
 * Implements the complete user management system from the User Management Center plan
 * including journey markers, support access, and user extensions
 */
class SpiralEngine_Users {
    
    /**
     * Instance of this class
     */
    private static $instance = null;
    
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
        // User registration
        add_action('user_register', array($this, 'on_user_register'), 10, 1);
        add_action('spiralengine_check_journey_markers', array($this, 'check_journey_markers'), 10, 3);
        
        // User meta extensions
        add_action('show_user_profile', array($this, 'add_user_fields'));
        add_action('edit_user_profile', array($this, 'add_user_fields'));
        add_action('personal_options_update', array($this, 'save_user_fields'));
        add_action('edit_user_profile_update', array($this, 'save_user_fields'));
        
        // Support access hooks
        add_action('init', array($this, 'handle_support_session'));
        
        // Journey marker checks
        add_action('wp_login', array($this, 'check_login_markers'), 10, 2);
        add_action('spiralengine_assessment_completed', array($this, 'check_assessment_markers'), 10, 2);
        
        // AJAX endpoints
        add_action('wp_ajax_spiralengine_grant_support_access', array($this, 'ajax_grant_support_access'));
        add_action('wp_ajax_spiralengine_revoke_support_access', array($this, 'ajax_revoke_support_access'));
    }
    
    /**
     * Handle new user registration
     */
    public function on_user_register($user_id) {
        // Initialize user meta fields
        $this->initialize_user_meta($user_id);
        
        // Grant "First Steps" journey marker
        $this->grant_journey_marker($user_id, 'first_steps');
        
        // Initialize compass points
        update_user_meta($user_id, 'spiralengine_compass_points', 500); // First Steps marker gives 500 points
        update_user_meta($user_id, 'spiralengine_lifetime_points', 500);
        update_user_meta($user_id, 'spiralengine_journey_level', 'Beginning');
        
        // Set support access preferences
        update_user_meta($user_id, 'spiralengine_support_access_enabled', false);
        update_user_meta($user_id, 'spiralengine_support_access_duration', 'one_time');
        update_user_meta($user_id, 'spiralengine_support_email_notify', true);
        
        // Initialize tracking preferences
        update_user_meta($user_id, 'spiralengine_last_assessment_score', 0);
        update_user_meta($user_id, 'spiralengine_risk_level', 'unknown');
        update_user_meta($user_id, 'spiralengine_assessment_count', 0);
        
        // Trigger welcome actions
        do_action('spiralengine_user_registered', $user_id);
    }
    
    /**
     * Initialize user meta fields
     */
    private function initialize_user_meta($user_id) {
        $default_meta = array(
            'spiralengine_biological_sex' => '',
            'spiralengine_track_menstrual' => false,
            'spiralengine_health_consent_date' => current_time('mysql'),
            'spiralengine_registration_source' => 'signup_widget',
            'spiralengine_journey_started' => current_time('mysql'),
            'spiralengine_last_activity' => current_time('mysql'),
            'spiralengine_login_streak' => 0,
            'spiralengine_last_login_date' => '',
            'spiralengine_timezone' => get_option('timezone_string', 'UTC'),
            'spiralengine_preferred_language' => get_locale(),
            'spiralengine_emergency_contact' => '',
            'spiralengine_therapist_info' => '',
            'spiralengine_medication_tracking' => false,
            'spiralengine_crisis_resources_shown' => 0,
            'spiralengine_member_notes' => ''
        );
        
        foreach ($default_meta as $key => $value) {
            if (!get_user_meta($user_id, $key, true)) {
                update_user_meta($user_id, $key, $value);
            }
        }
    }
    
    /**
     * Add custom user fields to profile
     */
    public function add_user_fields($user) {
        $support_enabled = get_user_meta($user->ID, 'spiralengine_support_access_enabled', true);
        $support_duration = get_user_meta($user->ID, 'spiralengine_support_access_duration', true);
        $compass_points = get_user_meta($user->ID, 'spiralengine_compass_points', true);
        $journey_level = get_user_meta($user->ID, 'spiralengine_journey_level', true);
        $biological_sex = get_user_meta($user->ID, 'spiralengine_biological_sex', true);
        $track_menstrual = get_user_meta($user->ID, 'spiralengine_track_menstrual', true);
        ?>
        <h3><?php _e('SPIRAL Engine Settings', 'spiral-engine'); ?></h3>
        
        <table class="form-table">
            <!-- Journey Information -->
            <tr>
                <th><label><?php _e('Journey Status', 'spiral-engine'); ?></label></th>
                <td>
                    <p><strong>Compass Points:</strong> <?php echo esc_html($compass_points ?: 0); ?></p>
                    <p><strong>Journey Level:</strong> <?php echo esc_html($journey_level ?: 'Beginning'); ?></p>
                </td>
            </tr>
            
            <!-- Support Access Settings -->
            <tr>
                <th><label for="spiralengine_support_access"><?php _e('Admin Support Access', 'spiral-engine'); ?></label></th>
                <td>
                    <label>
                        <input type="checkbox" name="spiralengine_support_access_enabled" id="spiralengine_support_access" value="1" <?php checked($support_enabled, true); ?> />
                        <?php _e('Allow administrators to access my account for support purposes', 'spiral-engine'); ?>
                    </label>
                    <p class="description"><?php _e('When enabled, administrators can view your dashboard to help with support issues. All actions are logged.', 'spiral-engine'); ?></p>
                    
                    <div id="support-access-options" style="margin-top: 10px; <?php echo $support_enabled ? '' : 'display:none;'; ?>">
                        <label><?php _e('Access Duration:', 'spiral-engine'); ?></label><br/>
                        <label><input type="radio" name="spiralengine_support_duration" value="one_time" <?php checked($support_duration, 'one_time'); ?> /> <?php _e('One-time (expires after logout)', 'spiral-engine'); ?></label><br/>
                        <label><input type="radio" name="spiralengine_support_duration" value="24_hours" <?php checked($support_duration, '24_hours'); ?> /> <?php _e('24 hours', 'spiral-engine'); ?></label><br/>
                        <label><input type="radio" name="spiralengine_support_duration" value="7_days" <?php checked($support_duration, '7_days'); ?> /> <?php _e('7 days', 'spiral-engine'); ?></label><br/>
                        <label><input type="radio" name="spiralengine_support_duration" value="until_revoked" <?php checked($support_duration, 'until_revoked'); ?> /> <?php _e('Until revoked', 'spiral-engine'); ?></label>
                    </div>
                </td>
            </tr>
            
            <!-- Health Tracking Preferences -->
            <tr>
                <th><label for="spiralengine_biological_sex"><?php _e('Biological Sex', 'spiral-engine'); ?></label></th>
                <td>
                    <select name="spiralengine_biological_sex" id="spiralengine_biological_sex">
                        <option value=""><?php _e('Prefer not to say', 'spiral-engine'); ?></option>
                        <option value="female" <?php selected($biological_sex, 'female'); ?>><?php _e('Female', 'spiral-engine'); ?></option>
                        <option value="male" <?php selected($biological_sex, 'male'); ?>><?php _e('Male', 'spiral-engine'); ?></option>
                        <option value="other" <?php selected($biological_sex, 'other'); ?>><?php _e('Other', 'spiral-engine'); ?></option>
                    </select>
                    <p class="description"><?php _e('This helps us provide relevant health tracking options. You can change this anytime.', 'spiral-engine'); ?></p>
                    
                    <div id="menstrual-tracking-option" style="margin-top: 10px; <?php echo $biological_sex === 'female' ? '' : 'display:none;'; ?>">
                        <label>
                            <input type="checkbox" name="spiralengine_track_menstrual" value="1" <?php checked($track_menstrual, true); ?> />
                            <?php _e('Track menstrual cycle data for better insights', 'spiral-engine'); ?>
                        </label>
                    </div>
                </td>
            </tr>
        </table>
        
        <script>
        jQuery(document).ready(function($) {
            $('#spiralengine_support_access').change(function() {
                $('#support-access-options').toggle(this.checked);
            });
            
            $('#spiralengine_biological_sex').change(function() {
                if ($(this).val() === 'female') {
                    $('#menstrual-tracking-option').show();
                } else {
                    $('#menstrual-tracking-option').hide();
                    $('input[name="spiralengine_track_menstrual"]').prop('checked', false);
                }
            });
        });
        </script>
        <?php
    }
    
    /**
     * Save custom user fields
     */
    public function save_user_fields($user_id) {
        if (!current_user_can('edit_user', $user_id)) {
            return false;
        }
        
        // Support access settings
        $support_enabled = isset($_POST['spiralengine_support_access_enabled']) ? true : false;
        update_user_meta($user_id, 'spiralengine_support_access_enabled', $support_enabled);
        
        if (isset($_POST['spiralengine_support_duration'])) {
            update_user_meta($user_id, 'spiralengine_support_duration', sanitize_text_field($_POST['spiralengine_support_duration']));
        }
        
        // Health tracking settings
        if (isset($_POST['spiralengine_biological_sex'])) {
            update_user_meta($user_id, 'spiralengine_biological_sex', sanitize_text_field($_POST['spiralengine_biological_sex']));
        }
        
        $track_menstrual = isset($_POST['spiralengine_track_menstrual']) ? true : false;
        update_user_meta($user_id, 'spiralengine_track_menstrual', $track_menstrual);
    }
    
    /**
     * Journey Markers System
     */
    public function grant_journey_marker($user_id, $marker_slug, $granted_by = null) {
        global $wpdb;
        
        // Get marker details
        $marker = $this->get_journey_marker_by_slug($marker_slug);
        if (!$marker) {
            return false;
        }
        
        // Check if already earned
        $earned = get_user_meta($user_id, 'spiralengine_marker_' . $marker_slug, true);
        if ($earned) {
            return false;
        }
        
        // Grant marker
        update_user_meta($user_id, 'spiralengine_marker_' . $marker_slug, array(
            'earned_at' => current_time('mysql'),
            'granted_by' => $granted_by,
            'points' => $marker['points']
        ));
        
        // Update compass points
        $current_points = (int) get_user_meta($user_id, 'spiralengine_compass_points', true);
        $lifetime_points = (int) get_user_meta($user_id, 'spiralengine_lifetime_points', true);
        
        update_user_meta($user_id, 'spiralengine_compass_points', $current_points + $marker['points']);
        update_user_meta($user_id, 'spiralengine_lifetime_points', $lifetime_points + $marker['points']);
        
        // Update journey level
        $this->update_journey_level($user_id, $lifetime_points + $marker['points']);
        
        // Trigger celebration
        do_action('spiralengine_journey_marker_earned', $user_id, $marker);
        
        // Log the achievement
        $this->log_user_activity($user_id, 'journey_marker_earned', array(
            'marker' => $marker_slug,
            'points' => $marker['points']
        ));
        
        return true;
    }
    
    /**
     * Get journey marker by slug
     */
    private function get_journey_marker_by_slug($slug) {
        $markers = $this->get_all_journey_markers();
        return isset($markers[$slug]) ? $markers[$slug] : null;
    }
    
    /**
     * Get all journey markers (NO membership checks!)
     */
    private function get_all_journey_markers() {
        return array(
            'first_steps' => array(
                'name' => __('First Steps', 'spiral-engine'),
                'description' => __('Started your wellness journey', 'spiral-engine'),
                'icon' => 'footsteps.svg',
                'points' => 500,
                'category' => 'beginning',
                'auto_grant' => true
            ),
            'steady_traveler' => array(
                'name' => __('Steady Traveler', 'spiral-engine'),
                'description' => __('Maintained a 7-day journey', 'spiral-engine'),
                'icon' => 'compass.svg',
                'points' => 100,
                'category' => 'finding_path',
                'criteria' => array(
                    'type' => 'consecutive_days',
                    'value' => 7
                )
            ),
            'seasoned_wanderer' => array(
                'name' => __('Seasoned Wanderer', 'spiral-engine'),
                'description' => __('Completed 50 assessments', 'spiral-engine'),
                'icon' => 'backpack.svg',
                'points' => 300,
                'category' => 'finding_path',
                'criteria' => array(
                    'type' => 'total_assessments',
                    'value' => 50
                )
            ),
            'storm_weathered' => array(
                'name' => __('Storm Weathered', 'spiral-engine'),
                'description' => __('Overcame a challenging period', 'spiral-engine'),
                'icon' => 'mountain-peak.svg',
                'points' => 300,
                'category' => 'climbing_higher',
                'criteria' => array(
                    'type' => 'improvement_shown',
                    'days' => 30
                )
            ),
            'horizon_seeker' => array(
                'name' => __('Horizon Seeker', 'spiral-engine'),
                'description' => __('6 months on your journey', 'spiral-engine'),
                'icon' => 'sunrise.svg',
                'points' => 400,
                'category' => 'vista_points',
                'criteria' => array(
                    'type' => 'journey_duration',
                    'value' => 180
                )
            ),
            'lighthouse_keeper' => array(
                'name' => __('Lighthouse Keeper', 'spiral-engine'),
                'description' => __('Helped guide 10 fellow travelers', 'spiral-engine'),
                'icon' => 'lighthouse.svg',
                'points' => 500,
                'category' => 'guide_spirit',
                'criteria' => array(
                    'type' => 'helped_others',
                    'value' => 10
                )
            )
        );
    }
    
    /**
     * Check journey markers for user action
     */
    public function check_journey_markers($user_id, $trigger_type, $trigger_data) {
        $markers = $this->get_all_journey_markers();
        
        foreach ($markers as $slug => $marker) {
            if (isset($marker['criteria']) && !get_user_meta($user_id, 'spiralengine_marker_' . $slug, true)) {
                if ($this->meets_journey_criteria($user_id, $marker['criteria'], $trigger_data)) {
                    $this->grant_journey_marker($user_id, $slug);
                }
            }
        }
    }
    
    /**
     * Check if user meets journey criteria
     */
    private function meets_journey_criteria($user_id, $criteria, $trigger_data) {
        switch ($criteria['type']) {
            case 'consecutive_days':
                $streak = (int) get_user_meta($user_id, 'spiralengine_login_streak', true);
                return $streak >= $criteria['value'];
                
            case 'total_assessments':
                $count = (int) get_user_meta($user_id, 'spiralengine_assessment_count', true);
                return $count >= $criteria['value'];
                
            case 'improvement_shown':
                return $this->check_user_improvement($user_id, $criteria['days']);
                
            case 'journey_duration':
                $registration = get_user_meta($user_id, 'spiralengine_journey_started', true);
                $days = (time() - strtotime($registration)) / (60 * 60 * 24);
                return $days >= $criteria['value'];
                
            case 'helped_others':
                $helped = (int) get_user_meta($user_id, 'spiralengine_users_helped', true);
                return $helped >= $criteria['value'];
                
            default:
                return false;
        }
    }
    
    /**
     * Update user's journey level based on points
     */
    private function update_journey_level($user_id, $total_points) {
        $level = 'Beginning';
        
        if ($total_points >= 10000) {
            $level = 'Guide Spirit';
        } elseif ($total_points >= 5000) {
            $level = 'Vista Points';
        } elseif ($total_points >= 2500) {
            $level = 'Climbing Higher';
        } elseif ($total_points >= 1000) {
            $level = 'Finding Path';
        }
        
        update_user_meta($user_id, 'spiralengine_journey_level', $level);
    }
    
    /**
     * Support Access System
     */
    public function request_support_access($user_id, $admin_id, $reason, $duration = '24_hours') {
        // Check if user has granted permission
        $permission = get_user_meta($user_id, 'spiralengine_support_access_enabled', true);
        
        if (!$permission) {
            return new WP_Error('no_permission', __('User has not granted support access permission', 'spiral-engine'));
        }
        
        // Generate secure access token
        $token = wp_generate_password(32, false);
        $token_hash = wp_hash($token);
        
        // Calculate expiry
        $expires_at = $this->calculate_support_expiry($duration);
        
        // Store access request
        global $wpdb;
        $wpdb->insert(
            $wpdb->prefix . 'spiralengine_support_access',
            array(
                'user_id' => $user_id,
                'admin_id' => $admin_id,
                'token_hash' => $token_hash,
                'reason' => $reason,
                'duration' => $duration,
                'expires_at' => $expires_at,
                'created_at' => current_time('mysql')
            )
        );
        
        // Log access request
        $this->log_support_access($user_id, $admin_id, 'access_requested', array(
            'reason' => $reason,
            'duration' => $duration
        ));
        
        // Notify user if enabled
        if (get_user_meta($user_id, 'spiralengine_support_email_notify', true)) {
            $this->notify_user_support_access($user_id, $admin_id, $reason, $duration);
        }
        
        return array(
            'success' => true,
            'token' => $token,
            'expires_at' => $expires_at
        );
    }
    
    /**
     * Calculate support access expiry
     */
    private function calculate_support_expiry($duration) {
        $expires = time();
        
        switch ($duration) {
            case 'one_time':
                $expires += 8 * 60 * 60; // 8 hours
                break;
            case '24_hours':
                $expires += 24 * 60 * 60;
                break;
            case '7_days':
                $expires += 7 * 24 * 60 * 60;
                break;
            case 'until_revoked':
                $expires += 365 * 24 * 60 * 60; // 1 year max
                break;
        }
        
        return date('Y-m-d H:i:s', $expires);
    }
    
    /**
     * Handle support session
     */
    public function handle_support_session() {
        if (isset($_SESSION['spiralengine_support_session'])) {
            $session = $_SESSION['spiralengine_support_session'];
            
            // Check if expired
            if (strtotime($session['expires']) < time()) {
                unset($_SESSION['spiralengine_support_session']);
                return;
            }
            
            // Add banner to admin
            add_action('admin_notices', array($this, 'show_support_session_notice'));
        }
    }
    
    /**
     * Show support session notice
     */
    public function show_support_session_notice() {
        if (!isset($_SESSION['spiralengine_support_session'])) {
            return;
        }
        
        $session = $_SESSION['spiralengine_support_session'];
        $user = get_userdata($session['user_id']);
        ?>
        <div class="notice notice-warning">
            <p>
                <strong><?php _e('Support Session Active', 'spiral-engine'); ?></strong>
                <?php printf(
                    __('You are viewing %s\'s account for support purposes. All actions are being logged.', 'spiral-engine'),
                    esc_html($user->display_name)
                ); ?>
                <a href="<?php echo wp_nonce_url(admin_url('admin-post.php?action=end_support_session'), 'end_support_session'); ?>" class="button button-small">
                    <?php _e('End Session', 'spiral-engine'); ?>
                </a>
            </p>
        </div>
        <?php
    }
    
    /**
     * Log user activity
     */
    private function log_user_activity($user_id, $action, $data = array()) {
        global $wpdb;
        
        $wpdb->insert(
            $wpdb->prefix . 'spiralengine_user_activity',
            array(
                'user_id' => $user_id,
                'action' => $action,
                'data' => json_encode($data),
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'created_at' => current_time('mysql')
            )
        );
    }
    
    /**
     * Log support access activity
     */
    private function log_support_access($user_id, $admin_id, $action, $data = array()) {
        global $wpdb;
        
        $wpdb->insert(
            $wpdb->prefix . 'spiralengine_support_log',
            array(
                'user_id' => $user_id,
                'admin_id' => $admin_id,
                'action' => $action,
                'details' => json_encode($data),
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
                'created_at' => current_time('mysql')
            )
        );
    }
    
    /**
     * Check login markers
     */
    public function check_login_markers($user_login, $user) {
        $this->update_login_streak($user->ID);
        do_action('spiralengine_check_journey_markers', $user->ID, 'daily_login', array());
    }
    
    /**
     * Update login streak
     */
    private function update_login_streak($user_id) {
        $last_login = get_user_meta($user_id, 'spiralengine_last_login_date', true);
        $today = date('Y-m-d');
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        
        $current_streak = (int) get_user_meta($user_id, 'spiralengine_login_streak', true);
        
        if ($last_login === $yesterday) {
            // Continue streak
            $current_streak++;
        } elseif ($last_login !== $today) {
            // Reset streak
            $current_streak = 1;
        }
        
        update_user_meta($user_id, 'spiralengine_login_streak', $current_streak);
        update_user_meta($user_id, 'spiralengine_last_login_date', $today);
        update_user_meta($user_id, 'spiralengine_last_activity', current_time('mysql'));
    }
    
    /**
     * Check assessment markers
     */
    public function check_assessment_markers($user_id, $assessment_data) {
        // Update assessment count
        $count = (int) get_user_meta($user_id, 'spiralengine_assessment_count', true);
        update_user_meta($user_id, 'spiralengine_assessment_count', $count + 1);
        
        // Update last score and risk level
        update_user_meta($user_id, 'spiralengine_last_assessment_score', $assessment_data['total_score']);
        update_user_meta($user_id, 'spiralengine_risk_level', $assessment_data['risk_level']);
        
        // Check for journey markers
        do_action('spiralengine_check_journey_markers', $user_id, 'assessment_completed', $assessment_data);
    }
    
    /**
     * Check user improvement
     */
    private function check_user_improvement($user_id, $days) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'spiralengine_assessments';
        $days_ago = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        // Get assessments from the period
        $assessments = $wpdb->get_results($wpdb->prepare(
            "SELECT total_score FROM {$table_name} 
             WHERE user_id = %d AND created_at >= %s 
             ORDER BY created_at ASC",
            $user_id, $days_ago
        ));
        
        if (count($assessments) < 2) {
            return false;
        }
        
        // Check if there's improvement
        $first_score = $assessments[0]->total_score;
        $last_score = $assessments[count($assessments) - 1]->total_score;
        
        // Improvement means lower score in SPIRAL assessment
        return $last_score < $first_score && $first_score >= 13; // Was high risk and improved
    }
    
    /**
     * Get user's journey markers
     */
    public function get_user_journey_markers($user_id) {
        $all_markers = $this->get_all_journey_markers();
        $user_markers = array();
        
        foreach ($all_markers as $slug => $marker) {
            $earned = get_user_meta($user_id, 'spiralengine_marker_' . $slug, true);
            if ($earned) {
                $marker['earned'] = true;
                $marker['earned_data'] = $earned;
            } else {
                $marker['earned'] = false;
            }
            $user_markers[$slug] = $marker;
        }
        
        return $user_markers;
    }
    
    /**
     * Get user risk level
     */
    public function get_user_risk_level($user_id) {
        $last_score = (int) get_user_meta($user_id, 'spiralengine_last_assessment_score', true);
        
        if ($last_score >= 13) {
            return 'high';
        } elseif ($last_score >= 7) {
            return 'medium';
        } elseif ($last_score > 0) {
            return 'low';
        }
        
        return 'unknown';
    }
    
    /**
     * Notify user of support access
     */
    private function notify_user_support_access($user_id, $admin_id, $reason, $duration) {
        $user = get_userdata($user_id);
        $admin = get_userdata($admin_id);
        
        $subject = __('Admin Support Access Granted', 'spiral-engine');
        $message = sprintf(
            __("Hello %s,\n\nAn administrator (%s) has been granted support access to your account.\n\nReason: %s\nDuration: %s\n\nAll actions taken during this session will be logged. You can revoke this access at any time from your profile settings.\n\nBest regards,\nThe SPIRAL Engine Team", 'spiral-engine'),
            $user->display_name,
            $admin->display_name,
            $reason,
            $this->get_duration_label($duration)
        );
        
        wp_mail($user->user_email, $subject, $message);
    }
    
    /**
     * Get duration label
     */
    private function get_duration_label($duration) {
        $labels = array(
            'one_time' => __('One-time (expires after logout)', 'spiral-engine'),
            '24_hours' => __('24 hours', 'spiral-engine'),
            '7_days' => __('7 days', 'spiral-engine'),
            'until_revoked' => __('Until revoked', 'spiral-engine')
        );
        
        return isset($labels[$duration]) ? $labels[$duration] : $duration;
    }
    
    /**
     * AJAX: Grant support access
     */
    public function ajax_grant_support_access() {
        check_ajax_referer('spiralengine_admin_nonce', 'nonce');
        
        if (!current_user_can('edit_users')) {
            wp_die(__('Insufficient permissions', 'spiral-engine'));
        }
        
        $user_id = intval($_POST['user_id']);
        $reason = sanitize_text_field($_POST['reason']);
        $duration = sanitize_text_field($_POST['duration']);
        
        $result = $this->request_support_access($user_id, get_current_user_id(), $reason, $duration);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        } else {
            wp_send_json_success($result);
        }
    }
    
    /**
     * AJAX: Revoke support access
     */
    public function ajax_revoke_support_access() {
        check_ajax_referer('spiralengine_admin_nonce', 'nonce');
        
        $user_id = get_current_user_id();
        
        // Clear all active support sessions for this user
        global $wpdb;
        $wpdb->update(
            $wpdb->prefix . 'spiralengine_support_access',
            array('expires_at' => current_time('mysql')),
            array('user_id' => $user_id)
        );
        
        // Log the revocation
        $this->log_user_activity($user_id, 'support_access_revoked', array());
        
        wp_send_json_success();
    }
}

// Initialize the class
SpiralEngine_Users::get_instance();


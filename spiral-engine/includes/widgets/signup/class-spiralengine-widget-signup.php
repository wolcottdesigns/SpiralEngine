<?php
// includes/widgets/signup/class-spiralengine-widget-signup.php

/**
 * Spiral Engine Enhanced Signup Widget
 * 
 * Handles the three-step signup process with account creation,
 * SPIRAL assessment, and review/confirmation.
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class SpiralEngine_Widget_Signup extends WP_Widget {
    
    /**
     * Widget ID
     */
    const WIDGET_ID = 'spiralengine_signup_enhanced';
    
    /**
     * Constructor
     */
    public function __construct() {
        parent::__construct(
            self::WIDGET_ID,
            __('Spiral Engine Signup', 'spiralengine'),
            array(
                'description' => __('Enhanced three-step signup widget with SPIRAL assessment', 'spiralengine'),
                'classname' => 'spiralengine-widget-signup'
            )
        );
        
        // Register AJAX handlers
        add_action('wp_ajax_nopriv_spiralengine_check_username', array($this, 'ajax_check_username'));
        add_action('wp_ajax_nopriv_spiralengine_check_email', array($this, 'ajax_check_email'));
        add_action('wp_ajax_nopriv_spiralengine_signup_register', array($this, 'ajax_register_user'));
        add_action('wp_ajax_nopriv_spiralengine_update_health_fields', array($this, 'ajax_update_health_fields'));
        
        // Enqueue scripts and styles
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
    }
    
    /**
     * Enqueue widget assets
     */
    public function enqueue_assets() {
        if (is_active_widget(false, false, $this->id_base)) {
            // Main styles
            wp_enqueue_style(
                'spiralengine-signup-widget',
                SPIRALENGINE_URL . 'assets/css/spiralengine-signup-widget.css',
                array(),
                SPIRALENGINE_VERSION
            );
            
            // Mobile styles
            wp_enqueue_style(
                'spiralengine-signup-mobile',
                SPIRALENGINE_URL . 'assets/css/spiralengine-signup-mobile.css',
                array('spiralengine-signup-widget'),
                SPIRALENGINE_VERSION,
                '(max-width: 768px)'
            );
            
            // JavaScript
            wp_enqueue_script(
                'spiralengine-signup-widget',
                SPIRALENGINE_URL . 'assets/js/spiralengine-signup-widget.js',
                array('jquery'),
                SPIRALENGINE_VERSION,
                true
            );
            
            // Localize script
            wp_localize_script('spiralengine-signup-widget', 'spiralengine_signup', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('spiralengine_signup_nonce'),
                'messages' => array(
                    'username_checking' => __('Checking username...', 'spiralengine'),
                    'email_checking' => __('Checking email...', 'spiralengine'),
                    'registering' => __('Creating your account...', 'spiralengine'),
                    'error_generic' => __('An error occurred. Please try again.', 'spiralengine'),
                    'crisis_message' => __('Your responses indicate you may benefit from immediate support. Please consider reaching out to a mental health professional.', 'spiralengine')
                )
            ));
        }
    }
    
    /**
     * Widget frontend output
     */
    public function widget($args, $instance) {
        // Check if user is already logged in
        if (is_user_logged_in()) {
            echo '<div class="spiralengine-signup-notice">';
            echo '<p>' . __('You are already logged in.', 'spiralengine') . '</p>';
            echo '<a href="' . esc_url(get_permalink(get_option('spiralengine_dashboard_page'))) . '" class="spiralengine-button">' . __('Go to Dashboard', 'spiralengine') . '</a>';
            echo '</div>';
            return;
        }
        
        echo $args['before_widget'];
        
        if (!empty($instance['title'])) {
            echo $args['before_title'] . apply_filters('widget_title', $instance['title']) . $args['after_title'];
        }
        
        // Widget mode: preview or full
        $mode = isset($instance['mode']) ? $instance['mode'] : 'full';
        
        ?>
        <div class="spiralengine-signup-container" data-mode="<?php echo esc_attr($mode); ?>">
            <?php if ($mode === 'preview'): ?>
                <div class="spiralengine-signup-preview">
                    <h3><?php _e('Start Your Journey', 'spiralengine'); ?></h3>
                    <p><?php _e('Create your account and complete the SPIRAL assessment to begin your personalized wellness journey.', 'spiralengine'); ?></p>
                    <button class="spiralengine-button spiralengine-button-primary" onclick="window.location.href='<?php echo esc_url(get_permalink(get_option('spiralengine_signup_page'))); ?>'">
                        <?php _e('Get Started', 'spiralengine'); ?>
                    </button>
                </div>
            <?php else: ?>
                <form id="spiralengine-signup-form" class="spiralengine-signup-form" method="post">
                    <?php wp_nonce_field('spiralengine_signup', 'spiralengine_signup_nonce'); ?>
                    
                    <!-- Progress Bar -->
                    <div class="spiralengine-progress-bar">
                        <div class="spiralengine-progress-track">
                            <div class="spiralengine-progress-fill" style="width: 33%;"></div>
                        </div>
                        <div class="spiralengine-progress-steps">
                            <div class="spiralengine-progress-step active" data-step="1">
                                <span class="step-number">1</span>
                                <span class="step-label"><?php _e('Account', 'spiralengine'); ?></span>
                            </div>
                            <div class="spiralengine-progress-step" data-step="2">
                                <span class="step-number">2</span>
                                <span class="step-label"><?php _e('Assessment', 'spiralengine'); ?></span>
                            </div>
                            <div class="spiralengine-progress-step" data-step="3">
                                <span class="step-number">3</span>
                                <span class="step-label"><?php _e('Review', 'spiralengine'); ?></span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Step 1: Account Creation -->
                    <?php include SPIRALENGINE_PATH . 'templates/widgets/signup/step-account.php'; ?>
                    
                    <!-- Step 2: SPIRAL Assessment -->
                    <?php include SPIRALENGINE_PATH . 'templates/widgets/signup/step-assessment.php'; ?>
                    
                    <!-- Step 3: Review -->
                    <?php include SPIRALENGINE_PATH . 'templates/widgets/signup/step-review.php'; ?>
                    
                    <!-- Success Animation -->
                    <?php include SPIRALENGINE_PATH . 'templates/widgets/signup/success-animation.php'; ?>
                </form>
            <?php endif; ?>
        </div>
        <?php
        
        echo $args['after_widget'];
    }
    
    /**
     * Widget backend form
     */
    public function form($instance) {
        $title = !empty($instance['title']) ? $instance['title'] : __('Join Our Community', 'spiralengine');
        $mode = !empty($instance['mode']) ? $instance['mode'] : 'full';
        ?>
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('title')); ?>">
                <?php _e('Title:', 'spiralengine'); ?>
            </label>
            <input class="widefat" id="<?php echo esc_attr($this->get_field_id('title')); ?>" 
                   name="<?php echo esc_attr($this->get_field_name('title')); ?>" 
                   type="text" value="<?php echo esc_attr($title); ?>">
        </p>
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('mode')); ?>">
                <?php _e('Display Mode:', 'spiralengine'); ?>
            </label>
            <select class="widefat" id="<?php echo esc_attr($this->get_field_id('mode')); ?>" 
                    name="<?php echo esc_attr($this->get_field_name('mode')); ?>">
                <option value="full" <?php selected($mode, 'full'); ?>><?php _e('Full Form', 'spiralengine'); ?></option>
                <option value="preview" <?php selected($mode, 'preview'); ?>><?php _e('Preview Only', 'spiralengine'); ?></option>
            </select>
        </p>
        <?php
    }
    
    /**
     * Save widget settings
     */
    public function update($new_instance, $old_instance) {
        $instance = array();
        $instance['title'] = (!empty($new_instance['title'])) ? sanitize_text_field($new_instance['title']) : '';
        $instance['mode'] = (!empty($new_instance['mode'])) ? sanitize_text_field($new_instance['mode']) : 'full';
        return $instance;
    }
    
    /**
     * AJAX: Check username availability
     */
    public function ajax_check_username() {
        check_ajax_referer('spiralengine_signup_nonce', 'nonce');
        
        $username = sanitize_user($_POST['username']);
        
        if (strlen($username) < 3 || strlen($username) > 20) {
            wp_send_json_error(array('message' => __('Username must be 3-20 characters', 'spiralengine')));
        }
        
        if (username_exists($username)) {
            wp_send_json_error(array('message' => __('Username already taken', 'spiralengine')));
        }
        
        wp_send_json_success(array('message' => __('Username available', 'spiralengine')));
    }
    
    /**
     * AJAX: Check email availability
     */
    public function ajax_check_email() {
        check_ajax_referer('spiralengine_signup_nonce', 'nonce');
        
        $email = sanitize_email($_POST['email']);
        
        if (!is_email($email)) {
            wp_send_json_error(array('message' => __('Invalid email address', 'spiralengine')));
        }
        
        if (email_exists($email)) {
            wp_send_json_error(array('message' => __('Email already registered', 'spiralengine')));
        }
        
        wp_send_json_success(array('message' => __('Email available', 'spiralengine')));
    }
    
    /**
     * AJAX: Update health tracking fields
     */
    public function ajax_update_health_fields() {
        check_ajax_referer('spiralengine_signup_nonce', 'nonce');
        
        $gender = sanitize_text_field($_POST['gender']);
        $show_tracking = ($gender === 'female');
        
        wp_send_json_success(array('show_tracking' => $show_tracking));
    }
    
    /**
     * AJAX: Register user
     */
    public function ajax_register_user() {
        check_ajax_referer('spiralengine_signup_nonce', 'nonce');
        
        // Validate all fields
        $username = sanitize_user($_POST['username']);
        $email = sanitize_email($_POST['email']);
        $password = $_POST['password'];
        $first_name = sanitize_text_field($_POST['first_name']);
        $last_name = sanitize_text_field($_POST['last_name']);
        $gender = sanitize_text_field($_POST['gender']);
        $track_wellness = isset($_POST['track_wellness']) ? 1 : 0;
        
        // SPIRAL assessment scores
        $assessment_scores = array(
            'acceleration' => intval($_POST['spiral_acceleration']),
            'catastrophizing' => intval($_POST['spiral_catastrophizing']),
            'loss_of_control' => intval($_POST['spiral_loss_of_control']),
            'physical_activation' => intval($_POST['spiral_physical_activation']),
            'time_distortion' => intval($_POST['spiral_time_distortion']),
            'compulsion' => intval($_POST['spiral_compulsion'])
        );
        
        $total_score = array_sum($assessment_scores);
        
        // Create user
        $user_id = wp_create_user($username, $password, $email);
        
        if (is_wp_error($user_id)) {
            wp_send_json_error(array('message' => $user_id->get_error_message()));
        }
        
        // Update user meta
        update_user_meta($user_id, 'first_name', $first_name);
        update_user_meta($user_id, 'last_name', $last_name);
        update_user_meta($user_id, 'spiralengine_gender', $gender);
        update_user_meta($user_id, 'spiralengine_track_wellness', $track_wellness);
        update_user_meta($user_id, 'spiralengine_health_consent_date', current_time('mysql'));
        
        // Store SPIRAL assessment (encrypted)
        $this->store_spiral_assessment($user_id, $assessment_scores, $total_score);
        
        // Assign Discovery membership (MemberPress)
        if (function_exists('mepr_get_membership')) {
            $discovery_membership_id = get_option('spiralengine_discovery_membership_id');
            if ($discovery_membership_id) {
                $membership = new MeprMembership($discovery_membership_id);
                $membership->add_member($user_id);
            }
        }
        
        // Send welcome email
        $this->send_welcome_email($user_id, $email, $first_name);
        
        // Auto-login user
        wp_set_current_user($user_id);
        wp_set_auth_cookie($user_id);
        
        // Check for crisis intervention
        $show_crisis = ($total_score >= 13);
        
        wp_send_json_success(array(
            'message' => __('Registration successful!', 'spiralengine'),
            'redirect' => get_permalink(get_option('spiralengine_dashboard_page')),
            'show_crisis' => $show_crisis
        ));
    }
    
    /**
     * Store SPIRAL assessment data (encrypted)
     */
    private function store_spiral_assessment($user_id, $scores, $total_score) {
        $assessment_data = array(
            'scores' => $scores,
            'total_score' => $total_score,
            'intensity' => $this->calculate_intensity($total_score),
            'timestamp' => current_time('mysql')
        );
        
        // Encrypt and store
        $encrypted_data = $this->encrypt_data(json_encode($assessment_data));
        update_user_meta($user_id, 'spiralengine_initial_assessment', $encrypted_data);
        
        // Store current scores for quick access
        update_user_meta($user_id, 'spiralengine_current_score', $total_score);
        update_user_meta($user_id, 'spiralengine_current_intensity', $assessment_data['intensity']);
    }
    
    /**
     * Calculate intensity level
     */
    private function calculate_intensity($score) {
        if ($score <= 6) return 'low';
        if ($score <= 12) return 'medium';
        return 'high';
    }
    
    /**
     * Encrypt sensitive data
     */
    private function encrypt_data($data) {
        // Use WordPress salts for encryption
        $key = wp_salt('auth');
        $encrypted = openssl_encrypt($data, 'AES-256-CBC', $key, 0, substr($key, 0, 16));
        return base64_encode($encrypted);
    }
    
    /**
     * Send welcome email
     */
    private function send_welcome_email($user_id, $email, $first_name) {
        $subject = __('Welcome to Your SPIRAL Journey!', 'spiralengine');
        
        $message = sprintf(
            __('Hi %s,\n\nWelcome to the SPIRAL Engine community! Your account has been created successfully.\n\n', 'spiralengine'),
            $first_name ?: __('there', 'spiralengine')
        );
        
        $message .= __('You can access your personal dashboard at any time to:\n', 'spiralengine');
        $message .= __('- Track your daily progress\n', 'spiralengine');
        $message .= __('- Access personalized resources\n', 'spiralengine');
        $message .= __('- Monitor your wellness patterns\n', 'spiralengine');
        $message .= __('- Connect with our supportive community\n\n', 'spiralengine');
        
        $message .= sprintf(
            __('Login here: %s\n\n', 'spiralengine'),
            get_permalink(get_option('spiralengine_dashboard_page'))
        );
        
        $message .= __('Best regards,\nThe SPIRAL Engine Team', 'spiralengine');
        
        wp_mail($email, $subject, $message);
    }
}

// Register widget
function spiralengine_register_signup_widget() {
    register_widget('SpiralEngine_Widget_Signup');
}
add_action('widgets_init', 'spiralengine_register_signup_widget');

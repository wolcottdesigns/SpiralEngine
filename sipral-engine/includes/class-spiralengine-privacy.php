<?php
// includes/class-spiralengine-privacy.php

/**
 * SPIRAL Engine Privacy Management Class
 * 
 * Implements privacy system from Security Command Center specifications:
 * - GDPR compliance features
 * - Consent tracking system
 * - Data export functionality
 * - Right to be forgotten
 * - Cookie consent
 * - Privacy policy versioning
 */
class SpiralEngine_Privacy {
    
    /**
     * Instance of this class
     */
    private static $instance = null;
    
    /**
     * Privacy features enabled status
     */
    private $enabled_features = array();
    
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
        $this->load_privacy_settings();
        $this->init_hooks();
    }
    
    /**
     * Load privacy settings
     */
    private function load_privacy_settings() {
        $this->enabled_features = get_option('spiralengine_privacy_features', array(
            'consent_management' => true,
            'data_encryption' => true,
            'audit_logging' => true,
            'cookie_consent' => true,
            'right_to_erasure' => true,
            'data_portability' => true,
            'access_requests' => true,
            'ip_anonymization' => false,
            'email_encryption' => true,
            'advanced_tracking_protection' => false
        ));
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Privacy page
        add_action('init', array($this, 'register_privacy_page'));
        
        // Cookie consent
        if ($this->is_feature_enabled('cookie_consent')) {
            add_action('wp_footer', array($this, 'render_cookie_banner'));
            add_action('wp_enqueue_scripts', array($this, 'enqueue_cookie_scripts'));
        }
        
        // Privacy requests
        add_action('admin_post_spiralengine_privacy_request', array($this, 'handle_privacy_request'));
        add_action('admin_post_nopriv_spiralengine_privacy_request', array($this, 'handle_privacy_request'));
        
        // AJAX endpoints
        add_action('wp_ajax_spiralengine_update_consent', array($this, 'ajax_update_consent'));
        add_action('wp_ajax_nopriv_spiralengine_update_consent', array($this, 'ajax_update_consent'));
        add_action('wp_ajax_spiralengine_export_user_data', array($this, 'ajax_export_user_data'));
        add_action('wp_ajax_spiralengine_delete_user_data', array($this, 'ajax_delete_user_data'));
        
        // WordPress privacy hooks
        add_filter('wp_privacy_personal_data_exporters', array($this, 'register_data_exporter'), 10);
        add_filter('wp_privacy_personal_data_erasers', array($this, 'register_data_eraser'), 10);
        
        // Consent checks
        add_filter('spiralengine_can_process_data', array($this, 'check_consent_filter'), 10, 3);
        
        // Audit logging
        if ($this->is_feature_enabled('audit_logging')) {
            add_action('spiralengine_privacy_event', array($this, 'log_privacy_event'), 10, 3);
        }
        
        // Regional compliance
        add_action('init', array($this, 'apply_regional_compliance'));
        
        // Data retention
        add_action('spiralengine_daily_cron', array($this, 'process_data_retention'));
    }
    
    /**
     * Check if feature is enabled
     */
    public function is_feature_enabled($feature) {
        return isset($this->enabled_features[$feature]) 
               && $this->enabled_features[$feature] === true;
    }
    
    /**
     * Register privacy page
     */
    public function register_privacy_page() {
        // Register shortcode for privacy portal
        add_shortcode('spiralengine_privacy_portal', array($this, 'render_privacy_portal'));
    }
    
    /**
     * Render privacy portal
     */
    public function render_privacy_portal() {
        if (!is_user_logged_in()) {
            return '<p>' . __('Please log in to access your privacy settings.', 'spiral-engine') . '</p>';
        }
        
        ob_start();
        include SPIRALENGINE_PATH . 'templates/privacy-portal.php';
        return ob_get_clean();
    }
    
    /**
     * Consent Management System
     */
    public function check_consent($user_id, $purpose) {
        if (!$this->is_feature_enabled('consent_management')) {
            return true; // If disabled, assume consent
        }
        
        // Essential services always allowed
        if ($purpose === 'essential') {
            return true;
        }
        
        $consent = $this->get_user_consent($user_id, $purpose);
        return $consent && $consent->consent_given;
    }
    
    /**
     * Get user consent
     */
    public function get_user_consent($user_id, $purpose) {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}spiralengine_privacy_consent 
             WHERE user_id = %d AND consent_type = %s AND withdrawn_at IS NULL 
             ORDER BY timestamp DESC LIMIT 1",
            $user_id, $purpose
        ));
    }
    
    /**
     * Record user consent
     */
    public function record_consent($user_id, $purpose, $granted, $consent_text = '') {
        global $wpdb;
        
        // Withdraw any existing consent first
        if (!$granted) {
            $wpdb->update(
                $wpdb->prefix . 'spiralengine_privacy_consent',
                array('withdrawn_at' => current_time('mysql')),
                array('user_id' => $user_id, 'consent_type' => $purpose, 'withdrawn_at' => null)
            );
        }
        
        // Record new consent
        $wpdb->insert(
            $wpdb->prefix . 'spiralengine_privacy_consent',
            array(
                'user_id' => $user_id,
                'consent_type' => $purpose,
                'consent_given' => $granted ? 1 : 0,
                'consent_text' => $consent_text,
                'consent_version' => $this->get_consent_version($purpose),
                'ip_address' => $this->get_anonymized_ip(),
                'timestamp' => current_time('mysql')
            )
        );
        
        // Log the event
        do_action('spiralengine_privacy_event', $user_id, 'consent_updated', array(
            'purpose' => $purpose,
            'granted' => $granted
        ));
        
        return true;
    }
    
    /**
     * Get consent version
     */
    private function get_consent_version($purpose) {
        $versions = get_option('spiralengine_consent_versions', array());
        return isset($versions[$purpose]) ? $versions[$purpose] : '1.0';
    }
    
    /**
     * Get anonymized IP
     */
    private function get_anonymized_ip() {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        
        if ($this->is_feature_enabled('ip_anonymization')) {
            // Anonymize IPv4 or IPv6
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                $parts = explode('.', $ip);
                $parts[3] = '0';
                $ip = implode('.', $parts);
            } elseif (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                $parts = explode(':', $ip);
                $parts[7] = '0000';
                $ip = implode(':', $parts);
            }
        }
        
        return $ip;
    }
    
    /**
     * Cookie Consent Management
     */
    public function render_cookie_banner() {
        if (!is_user_logged_in() && !isset($_COOKIE['spiralengine_cookie_consent'])) {
            ?>
            <div id="spiralengine-cookie-banner" class="spiralengine-cookie-banner" style="display:none;">
                <div class="spiralengine-cookie-content">
                    <p><?php _e('We use cookies to improve your experience. By using our site, you agree to our use of cookies.', 'spiral-engine'); ?></p>
                    <div class="spiralengine-cookie-buttons">
                        <button id="spiralengine-accept-all" class="button"><?php _e('Accept All', 'spiral-engine'); ?></button>
                        <button id="spiralengine-accept-essential" class="button"><?php _e('Essential Only', 'spiral-engine'); ?></button>
                        <button id="spiralengine-cookie-settings" class="button"><?php _e('Cookie Settings', 'spiral-engine'); ?></button>
                    </div>
                </div>
                
                <div id="spiralengine-cookie-details" style="display:none;">
                    <h3><?php _e('Cookie Preferences', 'spiral-engine'); ?></h3>
                    
                    <div class="spiralengine-cookie-category">
                        <label>
                            <input type="checkbox" checked disabled />
                            <strong><?php _e('Essential Cookies', 'spiral-engine'); ?></strong>
                            <p><?php _e('Required for basic site functionality', 'spiral-engine'); ?></p>
                        </label>
                    </div>
                    
                    <div class="spiralengine-cookie-category">
                        <label>
                            <input type="checkbox" id="spiralengine-analytics-cookies" />
                            <strong><?php _e('Analytics Cookies', 'spiral-engine'); ?></strong>
                            <p><?php _e('Help us improve your experience', 'spiral-engine'); ?></p>
                        </label>
                    </div>
                    
                    <button id="spiralengine-save-preferences" class="button"><?php _e('Save Preferences', 'spiral-engine'); ?></button>
                </div>
            </div>
            <?php
        }
    }
    
    /**
     * Enqueue cookie scripts
     */
    public function enqueue_cookie_scripts() {
        wp_enqueue_script(
            'spiralengine-cookies',
            SPIRALENGINE_URL . 'assets/js/cookies.js',
            array('jquery'),
            SPIRALENGINE_VERSION,
            true
        );
        
        wp_localize_script('spiralengine-cookies', 'spiralengine_cookie', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('spiralengine_cookie_nonce')
        ));
    }
    
    /**
     * Data Subject Rights
     */
    public function handle_privacy_request() {
        if (!isset($_POST['request_type']) || !wp_verify_nonce($_POST['nonce'], 'spiralengine_privacy_request')) {
            wp_die(__('Invalid request', 'spiral-engine'));
        }
        
        $user_id = get_current_user_id();
        if (!$user_id) {
            wp_die(__('You must be logged in', 'spiral-engine'));
        }
        
        $request_type = sanitize_text_field($_POST['request_type']);
        
        switch ($request_type) {
            case 'access':
                $this->process_access_request($user_id);
                break;
                
            case 'deletion':
                $this->process_deletion_request($user_id);
                break;
                
            case 'portability':
                $this->process_portability_request($user_id);
                break;
                
            case 'correction':
                $this->process_correction_request($user_id);
                break;
                
            default:
                wp_die(__('Invalid request type', 'spiral-engine'));
        }
    }
    
    /**
     * Process access request
     */
    private function process_access_request($user_id) {
        global $wpdb;
        
        // Log the request
        $this->log_privacy_request($user_id, 'access', 'pending');
        
        // Collect all user data
        $user_data = $this->collect_user_data($user_id);
        
        // Generate report
        $report = $this->generate_data_report($user_data);
        
        // Save report
        $file_path = $this->save_data_report($user_id, $report);
        
        // Update request status
        $this->update_privacy_request($user_id, 'access', 'completed', $file_path);
        
        // Send email with download link
        $this->send_data_access_email($user_id, $file_path);
        
        // Log completion
        do_action('spiralengine_privacy_event', $user_id, 'access_request_completed', array());
        
        wp_redirect(add_query_arg('privacy_request', 'access_completed', wp_get_referer()));
        exit;
    }
    
    /**
     * Process deletion request
     */
    private function process_deletion_request($user_id) {
        if (!$this->is_feature_enabled('right_to_erasure')) {
            wp_die(__('This feature is not enabled', 'spiral-engine'));
        }
        
        // Log the request
        $this->log_privacy_request($user_id, 'deletion', 'pending');
        
        // Schedule deletion (30-day grace period)
        wp_schedule_single_event(time() + (30 * DAY_IN_SECONDS), 'spiralengine_delete_user_data', array($user_id));
        
        // Update user meta
        update_user_meta($user_id, 'spiralengine_deletion_scheduled', time() + (30 * DAY_IN_SECONDS));
        
        // Send confirmation email
        $this->send_deletion_scheduled_email($user_id);
        
        // Log event
        do_action('spiralengine_privacy_event', $user_id, 'deletion_scheduled', array());
        
        wp_redirect(add_query_arg('privacy_request', 'deletion_scheduled', wp_get_referer()));
        exit;
    }
    
    /**
     * Collect all user data
     */
    private function collect_user_data($user_id) {
        global $wpdb;
        
        $data = array();
        
        // Basic user info
        $user = get_userdata($user_id);
        $data['user_info'] = array(
            'username' => $user->user_login,
            'email' => $user->user_email,
            'display_name' => $user->display_name,
            'registered' => $user->user_registered
        );
        
        // User meta
        $data['user_meta'] = get_user_meta($user_id);
        
        // Assessments
        $data['assessments'] = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}spiralengine_assessments WHERE user_id = %d",
            $user_id
        ), ARRAY_A);
        
        // Journey markers
        $data['journey_markers'] = array();
        $markers = SpiralEngine_Users::get_instance()->get_user_journey_markers($user_id);
        foreach ($markers as $marker) {
            if ($marker['earned']) {
                $data['journey_markers'][] = $marker;
            }
        }
        
        // Consent history
        $data['consent_history'] = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}spiralengine_privacy_consent WHERE user_id = %d",
            $user_id
        ), ARRAY_A);
        
        // Activity logs
        $data['activity_logs'] = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}spiralengine_user_activity WHERE user_id = %d",
            $user_id
        ), ARRAY_A);
        
        // Apply filters for extensions
        $data = apply_filters('spiralengine_collect_user_data', $data, $user_id);
        
        return $data;
    }
    
    /**
     * Generate data report
     */
    private function generate_data_report($data) {
        $report = array(
            'generated_at' => current_time('mysql'),
            'format_version' => '1.0',
            'data' => $data
        );
        
        return json_encode($report, JSON_PRETTY_PRINT);
    }
    
    /**
     * Save data report
     */
    private function save_data_report($user_id, $report) {
        $upload_dir = wp_upload_dir();
        $privacy_dir = $upload_dir['basedir'] . '/spiralengine-privacy';
        
        if (!file_exists($privacy_dir)) {
            wp_mkdir_p($privacy_dir);
            
            // Add .htaccess to protect directory
            file_put_contents($privacy_dir . '/.htaccess', 'deny from all');
        }
        
        $filename = 'user-data-' . $user_id . '-' . time() . '.json';
        $file_path = $privacy_dir . '/' . $filename;
        
        file_put_contents($file_path, $report);
        
        return $file_path;
    }
    
    /**
     * Log privacy request
     */
    private function log_privacy_request($user_id, $type, $status) {
        global $wpdb;
        
        $wpdb->insert(
            $wpdb->prefix . 'spiralengine_privacy_requests',
            array(
                'user_id' => $user_id,
                'request_type' => $type,
                'status' => $status,
                'requested_at' => current_time('mysql')
            )
        );
    }
    
    /**
     * Update privacy request
     */
    private function update_privacy_request($user_id, $type, $status, $file_path = null) {
        global $wpdb;
        
        $wpdb->update(
            $wpdb->prefix . 'spiralengine_privacy_requests',
            array(
                'status' => $status,
                'completed_at' => current_time('mysql'),
                'data_file' => $file_path
            ),
            array(
                'user_id' => $user_id,
                'request_type' => $type,
                'status' => 'pending'
            )
        );
    }
    
    /**
     * Log privacy event
     */
    public function log_privacy_event($user_id, $action, $details = array()) {
        if (!$this->is_feature_enabled('audit_logging')) {
            return;
        }
        
        global $wpdb;
        
        $wpdb->insert(
            $wpdb->prefix . 'spiralengine_privacy_audit',
            array(
                'user_id' => $user_id,
                'action' => $action,
                'details' => json_encode($details),
                'ip_address' => $this->get_anonymized_ip(),
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'timestamp' => current_time('mysql')
            )
        );
    }
    
    /**
     * Apply regional compliance
     */
    public function apply_regional_compliance() {
        $region = $this->detect_user_region();
        $settings = $this->get_regional_settings($region);
        
        // Apply settings
        foreach ($settings as $key => $value) {
            add_filter('spiralengine_privacy_' . $key, function() use ($value) {
                return $value;
            });
        }
        
        // Special handling for CCPA
        if ($region === 'california') {
            add_action('wp_footer', array($this, 'render_ccpa_link'));
        }
    }
    
    /**
     * Detect user region
     */
    private function detect_user_region() {
        // Simple GeoIP detection (you'd implement proper GeoIP here)
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        
        // For now, check if user has set their region
        if (is_user_logged_in()) {
            $user_region = get_user_meta(get_current_user_id(), 'spiralengine_region', true);
            if ($user_region) {
                return $user_region;
            }
        }
        
        // Default to US for now
        return 'usa';
    }
    
    /**
     * Get regional settings
     */
    private function get_regional_settings($region) {
        $settings = array(
            'eu' => array(
                'cookie_consent' => 'explicit',
                'data_retention' => '2_years',
                'consent_age' => 16,
                'right_to_delete' => true,
                'strict_consent' => true
            ),
            'usa' => array(
                'cookie_consent' => 'implied',
                'data_retention' => '3_years',
                'consent_age' => 13,
                'right_to_delete' => true,
                'do_not_sell' => true
            ),
            'california' => array(
                'cookie_consent' => 'explicit',
                'data_retention' => '3_years',
                'consent_age' => 13,
                'right_to_delete' => true,
                'do_not_sell' => true,
                'ccpa_link' => true
            ),
            'canada' => array(
                'cookie_consent' => 'explicit',
                'data_retention' => '2_years',
                'consent_age' => 13,
                'right_to_delete' => true
            )
        );
        
        return isset($settings[$region]) ? $settings[$region] : $settings['usa'];
    }
    
    /**
     * Render CCPA link
     */
    public function render_ccpa_link() {
        ?>
        <div class="spiralengine-ccpa-link" style="text-align: center; padding: 10px;">
            <a href="<?php echo esc_url(add_query_arg('ccpa', 'do-not-sell', home_url('/privacy'))); ?>">
                <?php _e('Do Not Sell My Personal Information', 'spiral-engine'); ?>
            </a>
        </div>
        <?php
    }
    
    /**
     * Process data retention
     */
    public function process_data_retention() {
        global $wpdb;
        
        // Get retention settings
        $retention_periods = get_option('spiralengine_data_retention', array(
            'assessments' => 730, // 2 years in days
            'ai_conversations' => 365, // 1 year
            'analytics' => 780, // 26 months
            'audit_logs' => 365, // 1 year
            'deleted_users' => 30 // 30 days
        ));
        
        foreach ($retention_periods as $data_type => $days) {
            $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$days} days"));
            
            switch ($data_type) {
                case 'assessments':
                    $wpdb->query($wpdb->prepare(
                        "DELETE FROM {$wpdb->prefix}spiralengine_assessments WHERE created_at < %s",
                        $cutoff_date
                    ));
                    break;
                    
                case 'audit_logs':
                    $wpdb->query($wpdb->prepare(
                        "DELETE FROM {$wpdb->prefix}spiralengine_privacy_audit WHERE timestamp < %s",
                        $cutoff_date
                    ));
                    break;
                    
                // Add more data types as needed
            }
        }
        
        // Log retention processing
        do_action('spiralengine_privacy_event', 0, 'data_retention_processed', array(
            'types' => array_keys($retention_periods)
        ));
    }
    
    /**
     * AJAX: Update consent
     */
    public function ajax_update_consent() {
        check_ajax_referer('spiralengine_cookie_nonce', 'nonce');
        
        $user_id = get_current_user_id();
        if (!$user_id) {
            // For non-logged-in users, set cookie
            $consent = array(
                'essential' => true,
                'analytics' => isset($_POST['analytics']) && $_POST['analytics'] === 'true',
                'timestamp' => time()
            );
            
            setcookie('spiralengine_cookie_consent', json_encode($consent), time() + (365 * DAY_IN_SECONDS), '/');
        } else {
            // For logged-in users, save to database
            $this->record_consent($user_id, 'analytics', $_POST['analytics'] === 'true');
        }
        
        wp_send_json_success();
    }
    
    /**
     * AJAX: Export user data
     */
    public function ajax_export_user_data() {
        check_ajax_referer('spiralengine_privacy_nonce', 'nonce');
        
        $user_id = get_current_user_id();
        if (!$user_id) {
            wp_send_json_error(__('You must be logged in', 'spiral-engine'));
        }
        
        // Check rate limiting (one request per day)
        $last_request = get_user_meta($user_id, 'spiralengine_last_export_request', true);
        if ($last_request && (time() - $last_request) < DAY_IN_SECONDS) {
            wp_send_json_error(__('You can only request data export once per day', 'spiral-engine'));
        }
        
        update_user_meta($user_id, 'spiralengine_last_export_request', time());
        
        // Process the request
        $this->process_access_request($user_id);
        
        wp_send_json_success(array(
            'message' => __('Your data export has been prepared. Check your email for the download link.', 'spiral-engine')
        ));
    }
    
    /**
     * AJAX: Delete user data
     */
    public function ajax_delete_user_data() {
        check_ajax_referer('spiralengine_privacy_nonce', 'nonce');
        
        $user_id = get_current_user_id();
        if (!$user_id) {
            wp_send_json_error(__('You must be logged in', 'spiral-engine'));
        }
        
        // Require password confirmation
        if (!isset($_POST['password']) || !wp_check_password($_POST['password'], wp_get_current_user()->user_pass, $user_id)) {
            wp_send_json_error(__('Invalid password', 'spiral-engine'));
        }
        
        // Process deletion request
        $this->process_deletion_request($user_id);
        
        wp_send_json_success(array(
            'message' => __('Your deletion request has been received. Your account will be deleted in 30 days.', 'spiral-engine')
        ));
    }
    
    /**
     * Send data access email
     */
    private function send_data_access_email($user_id, $file_path) {
        $user = get_userdata($user_id);
        $download_url = $this->generate_secure_download_url($user_id, $file_path);
        
        $subject = __('Your SPIRAL Engine Data Export is Ready', 'spiral-engine');
        $message = sprintf(
            __("Hello %s,\n\nYour data export has been prepared. You can download your data using the following secure link:\n\n%s\n\nThis link will expire in 48 hours.\n\nBest regards,\nThe SPIRAL Engine Team", 'spiral-engine'),
            $user->display_name,
            $download_url
        );
        
        wp_mail($user->user_email, $subject, $message);
    }
    
    /**
     * Send deletion scheduled email
     */
    private function send_deletion_scheduled_email($user_id) {
        $user = get_userdata($user_id);
        $cancel_url = add_query_arg(array(
            'action' => 'cancel_deletion',
            'user' => $user_id,
            'key' => wp_generate_password(20, false)
        ), home_url('/privacy'));
        
        $subject = __('Account Deletion Scheduled - SPIRAL Engine', 'spiral-engine');
        $message = sprintf(
            __("Hello %s,\n\nYour account deletion request has been received. Your account and all associated data will be permanently deleted in 30 days.\n\nIf you change your mind, you can cancel this request by clicking the following link:\n\n%s\n\nBest regards,\nThe SPIRAL Engine Team", 'spiral-engine'),
            $user->display_name,
            $cancel_url
        );
        
        wp_mail($user->user_email, $subject, $message);
    }
    
    /**
     * Generate secure download URL
     */
    private function generate_secure_download_url($user_id, $file_path) {
        $token = wp_generate_password(32, false);
        
        // Store token with expiry
        set_transient('spiralengine_download_' . $token, array(
            'user_id' => $user_id,
            'file_path' => $file_path
        ), 48 * HOUR_IN_SECONDS);
        
        return add_query_arg(array(
            'action' => 'spiralengine_download_data',
            'token' => $token
        ), admin_url('admin-post.php'));
    }
    
    /**
     * Register data exporter
     */
    public function register_data_exporter($exporters) {
        $exporters['spiralengine'] = array(
            'exporter_friendly_name' => __('SPIRAL Engine Data', 'spiral-engine'),
            'callback' => array($this, 'data_exporter_callback')
        );
        return $exporters;
    }
    
    /**
     * Data exporter callback
     */
    public function data_exporter_callback($email_address, $page = 1) {
        $user = get_user_by('email', $email_address);
        
        if (!$user) {
            return array(
                'data' => array(),
                'done' => true
            );
        }
        
        $export_items = array();
        $user_data = $this->collect_user_data($user->ID);
        
        // Format for WordPress exporter
        foreach ($user_data as $group_name => $group_data) {
            $group_label = ucfirst(str_replace('_', ' ', $group_name));
            
            if (is_array($group_data)) {
                $data = array();
                foreach ($group_data as $key => $value) {
                    if (!is_array($value)) {
                        $data[] = array(
                            'name' => ucfirst(str_replace('_', ' ', $key)),
                            'value' => $value
                        );
                    }
                }
                
                if (!empty($data)) {
                    $export_items[] = array(
                        'group_id' => 'spiralengine_' . $group_name,
                        'group_label' => $group_label,
                        'item_id' => 'spiralengine_' . $group_name . '_' . $user->ID,
                        'data' => $data
                    );
                }
            }
        }
        
        return array(
            'data' => $export_items,
            'done' => true
        );
    }
    
    /**
     * Register data eraser
     */
    public function register_data_eraser($erasers) {
        $erasers['spiralengine'] = array(
            'eraser_friendly_name' => __('SPIRAL Engine Data', 'spiral-engine'),
            'callback' => array($this, 'data_eraser_callback')
        );
        return $erasers;
    }
    
    /**
     * Data eraser callback
     */
    public function data_eraser_callback($email_address, $page = 1) {
        global $wpdb;
        
        $user = get_user_by('email', $email_address);
        
        if (!$user) {
            return array(
                'items_removed' => false,
                'items_retained' => false,
                'messages' => array(),
                'done' => true
            );
        }
        
        $items_removed = 0;
        $items_retained = 0;
        $messages = array();
        
        // Delete assessments
        $deleted = $wpdb->delete(
            $wpdb->prefix . 'spiralengine_assessments',
            array('user_id' => $user->ID)
        );
        $items_removed += $deleted;
        
        // Delete journey markers
        $meta_keys = $wpdb->get_col($wpdb->prepare(
            "SELECT meta_key FROM {$wpdb->usermeta} 
             WHERE user_id = %d AND meta_key LIKE 'spiralengine_marker_%'",
            $user->ID
        ));
        
        foreach ($meta_keys as $key) {
            delete_user_meta($user->ID, $key);
            $items_removed++;
        }
        
        // Delete privacy consent records
        $deleted = $wpdb->delete(
            $wpdb->prefix . 'spiralengine_privacy_consent',
            array('user_id' => $user->ID)
        );
        $items_removed += $deleted;
        
        // Delete activity logs
        $deleted = $wpdb->delete(
            $wpdb->prefix . 'spiralengine_user_activity',
            array('user_id' => $user->ID)
        );
        $items_removed += $deleted;
        
        // Some data might need to be retained for legal reasons
        $items_retained += $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}spiralengine_transactions 
             WHERE user_id = %d",
            $user->ID
        ));
        
        if ($items_retained > 0) {
            $messages[] = __('Some financial records were retained for legal compliance.', 'spiral-engine');
        }
        
        return array(
            'items_removed' => $items_removed > 0,
            'items_retained' => $items_retained > 0,
            'messages' => $messages,
            'done' => true
        );
    }
    
    /**
     * Check consent filter
     */
    public function check_consent_filter($can_process, $user_id, $purpose) {
        return $this->check_consent($user_id, $purpose);
    }
    
    /**
     * Get privacy health score
     */
    public function get_privacy_health_score() {
        $score = 0;
        $max_score = 0;
        
        // Check enabled features
        $feature_weights = array(
            'consent_management' => 15,
            'data_encryption' => 20,
            'audit_logging' => 10,
            'cookie_consent' => 10,
            'right_to_erasure' => 15,
            'data_portability' => 10,
            'access_requests' => 10,
            'ip_anonymization' => 5,
            'email_encryption' => 5
        );
        
        foreach ($feature_weights as $feature => $weight) {
            $max_score += $weight;
            if ($this->is_feature_enabled($feature)) {
                $score += $weight;
            }
        }
        
        return round(($score / $max_score) * 100);
    }
    
    /**
     * Get compliance status by region
     */
    public function get_compliance_status($region) {
        $requirements = $this->get_regional_requirements($region);
        $compliant = true;
        
        foreach ($requirements as $requirement => $required) {
            if ($required && !$this->is_feature_enabled($requirement)) {
                $compliant = false;
                break;
            }
        }
        
        return $compliant;
    }
    
    /**
     * Get regional requirements
     */
    private function get_regional_requirements($region) {
        $requirements = array(
            'gdpr' => array(
                'consent_management' => true,
                'right_to_erasure' => true,
                'data_portability' => true,
                'access_requests' => true,
                'audit_logging' => true
            ),
            'ccpa' => array(
                'right_to_erasure' => true,
                'access_requests' => true,
                'do_not_sell' => true
            ),
            'pipeda' => array(
                'consent_management' => true,
                'access_requests' => true,
                'right_to_erasure' => true
            )
        );
        
        return isset($requirements[$region]) ? $requirements[$region] : array();
    }
}

// Initialize the class
SpiralEngine_Privacy::get_instance();

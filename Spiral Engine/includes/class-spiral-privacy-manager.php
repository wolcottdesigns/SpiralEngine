<?php
// includes/class-spiral-privacy-manager.php

/**
 * SpiralEngine Privacy Manager Class
 *
 * Handles GDPR/CCPA compliance, consent management, privacy rights,
 * audit logging, and data protection controls.
 *
 * @package    SpiralEngine
 * @subpackage SpiralEngine/includes
 */

if (!defined('ABSPATH')) {
    exit;
}

class Spiral_Privacy_Manager {
    
    /**
     * Enabled privacy features
     *
     * @var array
     */
    private $enabled_features = array();
    
    /**
     * Encryption handler instance
     *
     * @var object
     */
    private $encryption;
    
    /**
     * Compliance helper instance
     *
     * @var object
     */
    private $compliance;
    
    /**
     * Database instance
     *
     * @var SpiralEngine_Database
     */
    private $db;
    
    /**
     * Privacy settings
     *
     * @var array
     */
    private $privacy_settings = array();
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->load_privacy_settings();
        $this->initialize_components();
        $this->register_privacy_hooks();
    }
    
    /**
     * Load privacy settings from database
     */
    private function load_privacy_settings() {
        $this->privacy_settings = get_option('spiralengine_privacy_settings', array(
            'consent_management' => true,
            'data_encryption' => true,
            'audit_logging' => true,
            'cookie_consent' => true,
            'right_to_erasure' => true,
            'data_portability' => true,
            'access_requests' => true,
            'ip_anonymization' => false,
            'email_encryption' => true,
            'tracking_protection' => false,
            'development_mode' => false
        ));
        
        $this->enabled_features = $this->privacy_settings;
    }
    
    /**
     * Initialize component systems
     */
    private function initialize_components() {
        // Get database instance
        $this->db = SpiralEngine_Database::getInstance();
        
        // Initialize sub-components
        $this->encryption = new SpiralEngine_Flexible_Encryption();
        $this->compliance = new SpiralEngine_Global_Compliance();
    }
    
    /**
     * Register WordPress hooks
     */
    private function register_privacy_hooks() {
        // Privacy page hooks
        add_action('init', array($this, 'register_privacy_endpoints'));
        add_action('template_redirect', array($this, 'handle_privacy_requests'));
        
        // Consent hooks
        add_action('init', array($this, 'check_cookie_consent'));
        add_action('wp_footer', array($this, 'display_consent_banner'));
        
        // Data protection hooks
        add_filter('spiralengine_save_data', array($this, 'maybe_encrypt_data'), 10, 2);
        add_filter('spiralengine_retrieve_data', array($this, 'maybe_decrypt_data'), 10, 2);
        
        // Audit hooks
        add_action('spiralengine_audit_log', array($this, 'log_privacy_event'), 10, 2);
        
        // User data hooks
        add_action('user_register', array($this, 'handle_new_user_consent'));
        add_action('delete_user', array($this, 'handle_user_deletion'));
        
        // AJAX handlers
        add_action('wp_ajax_spiralengine_privacy_request', array($this, 'handle_ajax_privacy_request'));
        add_action('wp_ajax_spiralengine_update_consent', array($this, 'handle_ajax_consent_update'));
        
        // Cron jobs
        add_action('spiralengine_daily_privacy_tasks', array($this, 'run_daily_privacy_tasks'));
        
        // Admin hooks
        add_action('admin_menu', array($this, 'add_privacy_menu'));
        add_action('admin_init', array($this, 'register_privacy_settings_fields'));
    }
    
    /**
     * Check user consent for specific purpose
     *
     * @param int $user_id User ID
     * @param string $purpose Consent purpose
     * @return bool Has consent
     */
    public function check_consent($user_id, $purpose) {
        // Always respect user choices
        if (!$this->is_feature_enabled('consent_management')) {
            return true; // If disabled, assume consent
        }
        
        // Special handling for essential services
        if ($purpose === 'essential') {
            return true;
        }
        
        $consent = $this->get_user_consent($user_id, $purpose);
        
        return $consent && $consent->consent_given && !$consent->withdrawn_at;
    }
    
    /**
     * Get user consent record
     *
     * @param int $user_id User ID
     * @param string $purpose Consent purpose
     * @return object|null Consent record
     */
    private function get_user_consent($user_id, $purpose) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'spiralengine_privacy_consent';
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name 
            WHERE user_id = %d AND consent_type = %s 
            ORDER BY timestamp DESC LIMIT 1",
            $user_id,
            $purpose
        ));
    }
    
    /**
     * Handle privacy request
     *
     * @param string $request_type Type of request
     * @param int $user_id User ID
     * @return array Request result
     */
    public function handle_privacy_request($request_type, $user_id) {
        // Verify user identity
        if (!$this->verify_user_identity($user_id)) {
            return array(
                'success' => false,
                'error' => 'Identity verification failed'
            );
        }
        
        // Create request record
        $request_id = $this->create_privacy_request($user_id, $request_type);
        
        // Process based on request type
        switch ($request_type) {
            case 'access':
                return $this->generate_user_data_export($user_id, $request_id);
                
            case 'deletion':
                return $this->process_deletion_request($user_id, $request_id);
                
            case 'portability':
                return $this->export_portable_data($user_id, $request_id);
                
            case 'correction':
                return $this->initiate_correction_flow($user_id, $request_id);
                
            case 'restriction':
                return $this->restrict_processing($user_id, $request_id);
                
            default:
                return array(
                    'success' => false,
                    'error' => 'Invalid request type'
                );
        }
    }
    
    /**
     * Generate user data export
     *
     * @param int $user_id User ID
     * @param int $request_id Request ID
     * @return array Export result
     */
    private function generate_user_data_export($user_id, $request_id) {
        $export_data = array(
            'user_info' => $this->get_user_info($user_id),
            'assessment_data' => $this->get_assessment_data($user_id),
            'ai_conversations' => $this->get_ai_conversations($user_id),
            'analytics_data' => $this->get_analytics_data($user_id),
            'consent_history' => $this->get_consent_history($user_id),
            'data_categories' => $this->get_data_categories($user_id),
            'processing_purposes' => $this->get_processing_purposes(),
            'third_party_sharing' => $this->get_third_party_sharing($user_id)
        );
        
        // Create export file
        $file_path = $this->create_export_file($export_data, $user_id);
        
        // Update request status
        $this->update_request_status($request_id, 'completed', array('file' => $file_path));
        
        // Send notification
        $this->send_export_notification($user_id, $file_path);
        
        // Log event
        $this->log_privacy_event('data_export', array(
            'user_id' => $user_id,
            'request_id' => $request_id,
            'file_size' => filesize($file_path)
        ));
        
        return array(
            'success' => true,
            'file_url' => $this->get_secure_download_url($file_path),
            'expires' => time() + DAY_IN_SECONDS
        );
    }
    
    /**
     * Process deletion request
     *
     * @param int $user_id User ID
     * @param int $request_id Request ID
     * @return array Deletion result
     */
    private function process_deletion_request($user_id, $request_id) {
        // Check if user can be deleted
        $can_delete = $this->check_deletion_eligibility($user_id);
        if (!$can_delete['eligible']) {
            return array(
                'success' => false,
                'error' => $can_delete['reason']
            );
        }
        
        // Create deletion plan
        $deletion_plan = array(
            'user_account' => true,
            'assessment_data' => true,
            'ai_conversations' => true,
            'analytics_data' => true,
            'uploaded_files' => true,
            'audit_logs' => false, // Keep for legal compliance
            'financial_records' => false // Keep for legal requirements
        );
        
        // Execute deletion
        foreach ($deletion_plan as $data_type => $should_delete) {
            if ($should_delete) {
                $this->delete_user_data($user_id, $data_type);
            }
        }
        
        // Anonymize retained data
        $this->anonymize_retained_data($user_id);
        
        // Update request status
        $this->update_request_status($request_id, 'completed');
        
        // Log event
        $this->log_privacy_event('data_deletion', array(
            'user_id' => $user_id,
            'request_id' => $request_id,
            'data_types' => array_keys(array_filter($deletion_plan))
        ));
        
        // Delete WordPress user
        require_once(ABSPATH . 'wp-admin/includes/user.php');
        wp_delete_user($user_id);
        
        return array(
            'success' => true,
            'message' => 'Your data has been successfully deleted'
        );
    }
    
    /**
     * Export portable data
     *
     * @param int $user_id User ID
     * @param int $request_id Request ID
     * @return array Export result
     */
    private function export_portable_data($user_id, $request_id) {
        // Get portable data in machine-readable format
        $portable_data = array(
            'format' => 'json',
            'version' => '1.0',
            'exported_at' => current_time('c'),
            'data' => array(
                'profile' => $this->get_portable_profile($user_id),
                'assessments' => $this->get_portable_assessments($user_id),
                'progress' => $this->get_portable_progress($user_id)
            )
        );
        
        // Create JSON file
        $json_file = $this->create_json_export($portable_data, $user_id);
        
        // Also create CSV for assessments
        $csv_file = $this->create_csv_export($portable_data['data']['assessments'], $user_id);
        
        // Update request status
        $this->update_request_status($request_id, 'completed', array(
            'files' => array($json_file, $csv_file)
        ));
        
        return array(
            'success' => true,
            'files' => array(
                'json' => $this->get_secure_download_url($json_file),
                'csv' => $this->get_secure_download_url($csv_file)
            )
        );
    }
    
    /**
     * Check if feature is enabled
     *
     * @param string $feature Feature key
     * @return bool Is enabled
     */
    public function is_feature_enabled($feature) {
        return isset($this->enabled_features[$feature]) 
               && $this->enabled_features[$feature] === true;
    }
    
    /**
     * Maybe encrypt data
     *
     * @param mixed $data Data to encrypt
     * @param string $context Data context
     * @return mixed Encrypted or original data
     */
    public function maybe_encrypt_data($data, $context) {
        if (!$this->is_feature_enabled('data_encryption')) {
            return $data;
        }
        
        return $this->encryption->maybe_encrypt($data, $context);
    }
    
    /**
     * Maybe decrypt data
     *
     * @param mixed $data Data to decrypt
     * @param string $context Data context
     * @return mixed Decrypted or original data
     */
    public function maybe_decrypt_data($data, $context) {
        if (!$this->is_feature_enabled('data_encryption')) {
            return $data;
        }
        
        return $this->encryption->maybe_decrypt($data, $context);
    }
    
    /**
     * Log privacy event
     *
     * @param string $action Action performed
     * @param array $details Event details
     */
    public function log_privacy_event($action, $details = array()) {
        if (!$this->is_feature_enabled('audit_logging')) {
            return;
        }
        
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'spiralengine_privacy_audit';
        
        $wpdb->insert(
            $table_name,
            array(
                'user_id' => get_current_user_id() ?: null,
                'action' => $action,
                'details' => json_encode($details),
                'ip_address' => $this->get_anonymized_ip(),
                'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
                'timestamp' => current_time('mysql')
            ),
            array('%d', '%s', '%s', '%s', '%s', '%s')
        );
    }
    
    /**
     * Get anonymized IP address
     *
     * @return string Anonymized IP
     */
    private function get_anonymized_ip() {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        
        if ($this->is_feature_enabled('ip_anonymization')) {
            // Anonymize IP by removing last octet
            $parts = explode('.', $ip);
            if (count($parts) === 4) {
                $parts[3] = '0';
                $ip = implode('.', $parts);
            }
        }
        
        return $ip;
    }
    
    /**
     * Check cookie consent
     */
    public function check_cookie_consent() {
        if (!$this->is_feature_enabled('cookie_consent')) {
            return;
        }
        
        // Check if user has made consent choice
        if (!isset($_COOKIE['spiral_consent'])) {
            // Set flag to show banner
            add_action('wp_footer', array($this, 'display_consent_banner'));
        }
    }
    
    /**
     * Display consent banner
     */
    public function display_consent_banner() {
        if (!$this->is_feature_enabled('cookie_consent')) {
            return;
        }
        
        // Check if banner should be displayed
        if (isset($_COOKIE['spiral_consent'])) {
            return;
        }
        
        // Get user region for compliance
        $region = $this->compliance->get_user_region();
        $settings = $this->compliance->apply_regional_settings($region);
        
        // Only show explicit consent for regions that require it
        if ($settings['cookie_consent'] !== 'explicit') {
            // Set implied consent cookie
            setcookie('spiral_consent', 'implied', time() + YEAR_IN_SECONDS, '/');
            return;
        }
        
        // Load consent banner template
        include SPIRALENGINE_PLUGIN_DIR . 'templates/privacy/consent-banner.php';
    }
    
    /**
     * Handle new user consent
     *
     * @param int $user_id New user ID
     */
    public function handle_new_user_consent($user_id) {
        // Get registration consent data
        $consent_given = isset($_POST['privacy_consent']) && $_POST['privacy_consent'] === 'yes';
        
        if ($consent_given) {
            // Record initial consents
            $consent_types = array('essential', 'analytics', 'communication');
            
            foreach ($consent_types as $type) {
                if (isset($_POST['consent_' . $type])) {
                    $this->record_consent($user_id, $type, true);
                }
            }
        }
    }
    
    /**
     * Record user consent
     *
     * @param int $user_id User ID
     * @param string $type Consent type
     * @param bool $given Consent given
     * @return bool Success
     */
    public function record_consent($user_id, $type, $given) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'spiralengine_privacy_consent';
        
        $result = $wpdb->insert(
            $table_name,
            array(
                'user_id' => $user_id,
                'consent_type' => $type,
                'consent_given' => $given ? 1 : 0,
                'consent_text' => $this->get_consent_text($type),
                'consent_version' => $this->get_consent_version(),
                'ip_address' => $this->get_anonymized_ip(),
                'timestamp' => current_time('mysql')
            ),
            array('%d', '%s', '%d', '%s', '%s', '%s', '%s')
        );
        
        // Log consent change
        $this->log_privacy_event('consent_' . ($given ? 'given' : 'withdrawn'), array(
            'user_id' => $user_id,
            'consent_type' => $type
        ));
        
        return $result !== false;
    }
    
    /**
     * Handle user deletion
     *
     * @param int $user_id User being deleted
     */
    public function handle_user_deletion($user_id) {
        // Create deletion record
        $this->log_privacy_event('user_deleted', array(
            'user_id' => $user_id,
            'deleted_by' => get_current_user_id()
        ));
        
        // Clean up user data
        $this->cleanup_user_data($user_id);
    }
    
    /**
     * Run daily privacy tasks
     */
    public function run_daily_privacy_tasks() {
        // Process pending privacy requests
        $this->process_pending_requests();
        
        // Clean up expired data
        $this->cleanup_expired_data();
        
        // Check retention policies
        $this->enforce_retention_policies();
        
        // Generate compliance report
        if (date('j') === '1') { // First day of month
            $this->generate_monthly_compliance_report();
        }
    }
    
    /**
     * Process pending privacy requests
     */
    private function process_pending_requests() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'spiralengine_privacy_requests';
        
        // Get pending requests older than 1 hour
        $pending_requests = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name 
            WHERE status = 'pending' 
            AND requested_at < %s",
            date('Y-m-d H:i:s', strtotime('-1 hour'))
        ));
        
        foreach ($pending_requests as $request) {
            // Reprocess request
            $this->handle_privacy_request($request->request_type, $request->user_id);
        }
    }
    
    /**
     * Clean up expired data
     */
    private function cleanup_expired_data() {
        global $wpdb;
        
        // Clean up old audit logs (keep 1 year)
        if ($this->is_feature_enabled('auto_deletion')) {
            $table_name = $wpdb->prefix . 'spiralengine_privacy_audit';
            
            $wpdb->query($wpdb->prepare(
                "DELETE FROM $table_name WHERE timestamp < %s",
                date('Y-m-d H:i:s', strtotime('-1 year'))
            ));
        }
        
        // Clean up expired export files
        $this->cleanup_export_files();
    }
    
    /**
     * Enforce data retention policies
     */
    private function enforce_retention_policies() {
        $retention_policies = array(
            'assessment_data' => 2 * YEAR_IN_SECONDS,
            'ai_conversations' => YEAR_IN_SECONDS,
            'analytics_data' => 26 * MONTH_IN_SECONDS,
            'audit_logs' => YEAR_IN_SECONDS,
            'support_tickets' => 3 * YEAR_IN_SECONDS
        );
        
        foreach ($retention_policies as $data_type => $retention_period) {
            $this->cleanup_old_data($data_type, $retention_period);
        }
    }
    
    /**
     * Handle AJAX privacy request
     */
    public function handle_ajax_privacy_request() {
        check_ajax_referer('spiralengine_privacy_request', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_die('Unauthorized');
        }
        
        $request_type = sanitize_text_field($_POST['request_type']);
        $user_id = get_current_user_id();
        
        $result = $this->handle_privacy_request($request_type, $user_id);
        
        wp_send_json($result);
    }
    
    /**
     * Handle AJAX consent update
     */
    public function handle_ajax_consent_update() {
        check_ajax_referer('spiralengine_consent_update', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_die('Unauthorized');
        }
        
        $consent_type = sanitize_text_field($_POST['consent_type']);
        $consent_given = $_POST['consent_given'] === 'true';
        $user_id = get_current_user_id();
        
        $result = $this->record_consent($user_id, $consent_type, $consent_given);
        
        wp_send_json(array(
            'success' => $result,
            'consent_type' => $consent_type,
            'consent_given' => $consent_given
        ));
    }
    
    /**
     * Verify user identity
     *
     * @param int $user_id User ID
     * @return bool Verified
     */
    private function verify_user_identity($user_id) {
        // For logged-in users, verify they are requesting their own data
        if (is_user_logged_in()) {
            return get_current_user_id() === $user_id;
        }
        
        // For non-logged-in users, verify via email token
        if (isset($_GET['privacy_token'])) {
            $token = sanitize_text_field($_GET['privacy_token']);
            $stored_token = get_user_meta($user_id, 'privacy_verification_token', true);
            
            return $token === $stored_token;
        }
        
        return false;
    }
    
    /**
     * Create privacy request record
     *
     * @param int $user_id User ID
     * @param string $request_type Request type
     * @return int Request ID
     */
    private function create_privacy_request($user_id, $request_type) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'spiralengine_privacy_requests';
        
        $wpdb->insert(
            $table_name,
            array(
                'user_id' => $user_id,
                'request_type' => $request_type,
                'status' => 'pending',
                'requested_at' => current_time('mysql')
            ),
            array('%d', '%s', '%s', '%s')
        );
        
        return $wpdb->insert_id;
    }
    
    /**
     * Update request status
     *
     * @param int $request_id Request ID
     * @param string $status New status
     * @param array $data Additional data
     */
    private function update_request_status($request_id, $status, $data = array()) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'spiralengine_privacy_requests';
        
        $update_data = array(
            'status' => $status,
            'completed_at' => $status === 'completed' ? current_time('mysql') : null
        );
        
        if (isset($data['file'])) {
            $update_data['data_file'] = $data['file'];
        }
        
        if (!empty($data)) {
            $update_data['notes'] = json_encode($data);
        }
        
        $wpdb->update(
            $table_name,
            $update_data,
            array('request_id' => $request_id),
            array('%s', '%s', '%s', '%s'),
            array('%d')
        );
    }
    
    /**
     * Get user info for export
     *
     * @param int $user_id User ID
     * @return array User info
     */
    private function get_user_info($user_id) {
        $user = get_userdata($user_id);
        
        if (!$user) {
            return array();
        }
        
        return array(
            'username' => $user->user_login,
            'email' => $user->user_email,
            'display_name' => $user->display_name,
            'registered' => $user->user_registered,
            'roles' => $user->roles,
            'meta' => get_user_meta($user_id)
        );
    }
    
    /**
     * Get assessment data for export
     *
     * @param int $user_id User ID
     * @return array Assessment data
     */
    private function get_assessment_data($user_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'spiralengine_assessments';
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name WHERE user_id = %d",
            $user_id
        ), ARRAY_A);
    }
    
    /**
     * Get AI conversations for export
     *
     * @param int $user_id User ID
     * @return array AI conversations
     */
    private function get_ai_conversations($user_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'spiralengine_ai_conversations';
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name WHERE user_id = %d",
            $user_id
        ), ARRAY_A);
    }
    
    /**
     * Get analytics data for export
     *
     * @param int $user_id User ID
     * @return array Analytics data
     */
    private function get_analytics_data($user_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'spiralengine_analytics';
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name WHERE user_id = %d",
            $user_id
        ), ARRAY_A);
    }
    
    /**
     * Get consent history
     *
     * @param int $user_id User ID
     * @return array Consent history
     */
    private function get_consent_history($user_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'spiralengine_privacy_consent';
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name WHERE user_id = %d ORDER BY timestamp DESC",
            $user_id
        ), ARRAY_A);
    }
    
    /**
     * Get data categories
     *
     * @param int $user_id User ID
     * @return array Data categories
     */
    private function get_data_categories($user_id) {
        return array(
            'personal_info' => 'Name, email, username',
            'health_data' => 'Mental health assessments and tracking',
            'usage_data' => 'Feature usage and interaction patterns',
            'technical_data' => 'IP address, browser information',
            'preferences' => 'Settings and customizations'
        );
    }
    
    /**
     * Get processing purposes
     *
     * @return array Processing purposes
     */
    private function get_processing_purposes() {
        return array(
            'service_delivery' => 'Providing mental health tracking services',
            'personalization' => 'Customizing experience and recommendations',
            'analytics' => 'Improving our services',
            'communication' => 'Sending updates and notifications',
            'legal_compliance' => 'Meeting legal obligations'
        );
    }
    
    /**
     * Get third party sharing info
     *
     * @param int $user_id User ID
     * @return array Sharing info
     */
    private function get_third_party_sharing($user_id) {
        // In this implementation, we don't share with third parties
        return array(
            'shared' => false,
            'parties' => array(),
            'purposes' => array()
        );
    }
    
    /**
     * Create export file
     *
     * @param array $data Export data
     * @param int $user_id User ID
     * @return string File path
     */
    private function create_export_file($data, $user_id) {
        $upload_dir = wp_upload_dir();
        $export_dir = $upload_dir['basedir'] . '/spiralengine-exports/';
        
        // Create directory if it doesn't exist
        if (!file_exists($export_dir)) {
            wp_mkdir_p($export_dir);
            
            // Add .htaccess to prevent direct access
            file_put_contents($export_dir . '.htaccess', 'Deny from all');
        }
        
        // Generate filename
        $filename = 'user-data-' . $user_id . '-' . time() . '.json';
        $file_path = $export_dir . $filename;
        
        // Save data as JSON
        file_put_contents($file_path, json_encode($data, JSON_PRETTY_PRINT));
        
        return $file_path;
    }
    
    /**
     * Send export notification
     *
     * @param int $user_id User ID
     * @param string $file_path Export file path
     */
    private function send_export_notification($user_id, $file_path) {
        $user = get_userdata($user_id);
        $download_url = $this->get_secure_download_url($file_path);
        
        $subject = __('Your Data Export is Ready', 'spiral-engine');
        $message = sprintf(
            __('Your data export has been completed. You can download it from the following link (expires in 24 hours): %s', 'spiral-engine'),
            $download_url
        );
        
        wp_mail($user->user_email, $subject, $message);
    }
    
    /**
     * Get secure download URL
     *
     * @param string $file_path File path
     * @return string Secure URL
     */
    private function get_secure_download_url($file_path) {
        $token = wp_generate_password(32, false);
        $expires = time() + DAY_IN_SECONDS;
        
        // Store download token
        set_transient('spiralengine_download_' . $token, array(
            'file' => $file_path,
            'expires' => $expires
        ), DAY_IN_SECONDS);
        
        return add_query_arg(array(
            'spiralengine_action' => 'download_export',
            'token' => $token
        ), home_url());
    }
    
    /**
     * Check deletion eligibility
     *
     * @param int $user_id User ID
     * @return array Eligibility status
     */
    private function check_deletion_eligibility($user_id) {
        // Check for active subscriptions
        if ($this->has_active_subscription($user_id)) {
            return array(
                'eligible' => false,
                'reason' => 'Please cancel your subscription before deleting your account'
            );
        }
        
        // Check for recent transactions
        if ($this->has_recent_transactions($user_id)) {
            return array(
                'eligible' => false,
                'reason' => 'Account cannot be deleted due to recent transactions'
            );
        }
        
        return array('eligible' => true);
    }
    
    /**
     * Delete user data by type
     *
     * @param int $user_id User ID
     * @param string $data_type Data type to delete
     */
    private function delete_user_data($user_id, $data_type) {
        global $wpdb;
        
        $tables = array(
            'user_account' => null, // Handled separately
            'assessment_data' => 'spiralengine_assessments',
            'ai_conversations' => 'spiralengine_ai_conversations',
            'analytics_data' => 'spiralengine_analytics',
            'uploaded_files' => null // Handled separately
        );
        
        if (isset($tables[$data_type]) && $tables[$data_type]) {
            $table_name = $wpdb->prefix . $tables[$data_type];
            $wpdb->delete($table_name, array('user_id' => $user_id), array('%d'));
        }
        
        // Handle special cases
        if ($data_type === 'uploaded_files') {
            $this->delete_user_files($user_id);
        }
    }
    
    /**
     * Anonymize retained data
     *
     * @param int $user_id User ID
     */
    private function anonymize_retained_data($user_id) {
        global $wpdb;
        
        // Anonymize audit logs
        $table_name = $wpdb->prefix . 'spiralengine_privacy_audit';
        
        $wpdb->update(
            $table_name,
            array(
                'user_id' => 0,
                'ip_address' => '0.0.0.0'
            ),
            array('user_id' => $user_id),
            array('%d', '%s'),
            array('%d')
        );
    }
    
    /**
     * Get consent text
     *
     * @param string $type Consent type
     * @return string Consent text
     */
    private function get_consent_text($type) {
        $texts = array(
            'essential' => 'I consent to the processing of my data for essential service functionality',
            'analytics' => 'I consent to the processing of my data for analytics and service improvement',
            'ai_processing' => 'I consent to AI processing of my assessment data for personalized insights',
            'communication' => 'I consent to receiving service updates and notifications'
        );
        
        return isset($texts[$type]) ? $texts[$type] : '';
    }
    
    /**
     * Get consent version
     *
     * @return string Version
     */
    private function get_consent_version() {
        return '1.0';
    }
    
    /**
     * Create JSON export
     *
     * @param array $data Data to export
     * @param int $user_id User ID
     * @return string File path
     */
    private function create_json_export($data, $user_id) {
        $upload_dir = wp_upload_dir();
        $export_dir = $upload_dir['basedir'] . '/spiralengine-exports/';
        
        wp_mkdir_p($export_dir);
        
        $filename = 'portable-data-' . $user_id . '-' . time() . '.json';
        $file_path = $export_dir . $filename;
        
        file_put_contents($file_path, json_encode($data, JSON_PRETTY_PRINT));
        
        return $file_path;
    }
    
    /**
     * Create CSV export
     *
     * @param array $data Data to export
     * @param int $user_id User ID
     * @return string File path
     */
    private function create_csv_export($data, $user_id) {
        $upload_dir = wp_upload_dir();
        $export_dir = $upload_dir['basedir'] . '/spiralengine-exports/';
        
        wp_mkdir_p($export_dir);
        
        $filename = 'assessments-' . $user_id . '-' . time() . '.csv';
        $file_path = $export_dir . $filename;
        
        $fp = fopen($file_path, 'w');
        
        // Write headers
        if (!empty($data)) {
            fputcsv($fp, array_keys($data[0]));
            
            // Write data
            foreach ($data as $row) {
                fputcsv($fp, $row);
            }
        }
        
        fclose($fp);
        
        return $file_path;
    }
    
    /**
     * Cleanup user data
     *
     * @param int $user_id User ID
     */
    private function cleanup_user_data($user_id) {
        // This is called when a user is deleted through WordPress
        // Perform any additional cleanup needed
        do_action('spiralengine_cleanup_user_data', $user_id);
    }
    
    /**
     * Cleanup export files
     */
    private function cleanup_export_files() {
        $upload_dir = wp_upload_dir();
        $export_dir = $upload_dir['basedir'] . '/spiralengine-exports/';
        
        if (!is_dir($export_dir)) {
            return;
        }
        
        // Delete files older than 48 hours
        $files = glob($export_dir . '*.{json,csv}', GLOB_BRACE);
        $now = time();
        
        foreach ($files as $file) {
            if ($now - filemtime($file) > 2 * DAY_IN_SECONDS) {
                unlink($file);
            }
        }
    }
    
    /**
     * Has active subscription
     *
     * @param int $user_id User ID
     * @return bool Has subscription
     */
    private function has_active_subscription($user_id) {
        // Check MemberPress subscriptions
        if (class_exists('MeprUser')) {
            $user = new MeprUser($user_id);
            return $user->has_active_subscription();
        }
        
        return false;
    }
    
    /**
     * Has recent transactions
     *
     * @param int $user_id User ID
     * @return bool Has recent transactions
     */
    private function has_recent_transactions($user_id) {
        // Check for transactions in last 90 days
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'spiralengine_transactions';
        
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name 
            WHERE user_id = %d 
            AND created_at > %s",
            $user_id,
            date('Y-m-d H:i:s', strtotime('-90 days'))
        ));
        
        return $count > 0;
    }
    
    /**
     * Delete user files
     *
     * @param int $user_id User ID
     */
    private function delete_user_files($user_id) {
        $upload_dir = wp_upload_dir();
        $user_dir = $upload_dir['basedir'] . '/spiralengine-user-files/' . $user_id;
        
        if (is_dir($user_dir)) {
            $this->delete_directory($user_dir);
        }
    }
    
    /**
     * Delete directory recursively
     *
     * @param string $dir Directory path
     */
    private function delete_directory($dir) {
        if (!is_dir($dir)) {
            return;
        }
        
        $files = array_diff(scandir($dir), array('.', '..'));
        
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->delete_directory($path) : unlink($path);
        }
        
        rmdir($dir);
    }
    
    /**
     * Initiate correction flow
     *
     * @param int $user_id User ID
     * @param int $request_id Request ID
     * @return array Flow result
     */
    private function initiate_correction_flow($user_id, $request_id) {
        // Create correction form URL
        $correction_url = add_query_arg(array(
            'spiralengine_action' => 'data_correction',
            'user_id' => $user_id,
            'request_id' => $request_id,
            'token' => wp_generate_password(32, false)
        ), admin_url('admin.php?page=spiralengine-privacy'));
        
        // Send notification with correction link
        $user = get_userdata($user_id);
        wp_mail(
            $user->user_email,
            __('Data Correction Request', 'spiral-engine'),
            sprintf(__('Please visit the following link to correct your data: %s', 'spiral-engine'), $correction_url)
        );
        
        return array(
            'success' => true,
            'message' => 'Correction instructions have been sent to your email'
        );
    }
    
    /**
     * Restrict processing
     *
     * @param int $user_id User ID
     * @param int $request_id Request ID
     * @return array Restriction result
     */
    private function restrict_processing($user_id, $request_id) {
        // Add user to restricted processing list
        update_user_meta($user_id, 'spiralengine_processing_restricted', true);
        
        // Update request status
        $this->update_request_status($request_id, 'completed');
        
        // Log event
        $this->log_privacy_event('processing_restricted', array(
            'user_id' => $user_id,
            'request_id' => $request_id
        ));
        
        return array(
            'success' => true,
            'message' => 'Your data processing has been restricted'
        );
    }
    
    /**
     * Get portable profile
     *
     * @param int $user_id User ID
     * @return array Portable profile
     */
    private function get_portable_profile($user_id) {
        $user = get_userdata($user_id);
        
        return array(
            'email' => $user->user_email,
            'display_name' => $user->display_name,
            'registered_date' => $user->user_registered
        );
    }
    
    /**
     * Get portable assessments
     *
     * @param int $user_id User ID
     * @return array Portable assessments
     */
    private function get_portable_assessments($user_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'spiralengine_assessments';
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT assessment_type, score, responses, completed_at 
            FROM $table_name 
            WHERE user_id = %d 
            ORDER BY completed_at DESC",
            $user_id
        ), ARRAY_A);
    }
    
    /**
     * Get portable progress
     *
     * @param int $user_id User ID
     * @return array Portable progress
     */
    private function get_portable_progress($user_id) {
        // Get user's progress data
        return array(
            'total_assessments' => $this->get_total_assessments($user_id),
            'achievements' => $this->get_user_achievements($user_id),
            'streak_days' => $this->get_user_streak($user_id)
        );
    }
    
    /**
     * Get total assessments
     *
     * @param int $user_id User ID
     * @return int Total count
     */
    private function get_total_assessments($user_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'spiralengine_assessments';
        
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE user_id = %d",
            $user_id
        ));
    }
    
    /**
     * Get user achievements
     *
     * @param int $user_id User ID
     * @return array Achievements
     */
    private function get_user_achievements($user_id) {
        return get_user_meta($user_id, 'spiralengine_achievements', true) ?: array();
    }
    
    /**
     * Get user streak
     *
     * @param int $user_id User ID
     * @return int Streak days
     */
    private function get_user_streak($user_id) {
        return (int) get_user_meta($user_id, 'spiralengine_streak_days', true);
    }
    
    /**
     * Cleanup old data
     *
     * @param string $data_type Data type
     * @param int $retention_period Retention period in seconds
     */
    private function cleanup_old_data($data_type, $retention_period) {
        global $wpdb;
        
        $table_map = array(
            'assessment_data' => 'spiralengine_assessments',
            'ai_conversations' => 'spiralengine_ai_conversations',
            'analytics_data' => 'spiralengine_analytics'
        );
        
        if (!isset($table_map[$data_type])) {
            return;
        }
        
        $table_name = $wpdb->prefix . $table_map[$data_type];
        $cutoff_date = date('Y-m-d H:i:s', time() - $retention_period);
        
        $wpdb->query($wpdb->prepare(
            "DELETE FROM $table_name WHERE created_at < %s",
            $cutoff_date
        ));
    }
    
    /**
     * Generate monthly compliance report
     */
    private function generate_monthly_compliance_report() {
        $report = array(
            'month' => date('F Y', strtotime('-1 month')),
            'privacy_requests' => $this->get_monthly_request_stats(),
            'consent_stats' => $this->get_monthly_consent_stats(),
            'data_breaches' => $this->get_monthly_breach_count(),
            'compliance_score' => $this->calculate_compliance_score()
        );
        
        // Save report
        update_option('spiralengine_compliance_report_' . date('Y_m'), $report);
        
        // Notify administrators
        $this->send_compliance_report($report);
    }
    
    /**
     * Get monthly request stats
     *
     * @return array Request statistics
     */
    private function get_monthly_request_stats() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'spiralengine_privacy_requests';
        $start_date = date('Y-m-01', strtotime('-1 month'));
        $end_date = date('Y-m-t', strtotime('-1 month'));
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT request_type, COUNT(*) as count, 
            AVG(TIMESTAMPDIFF(HOUR, requested_at, completed_at)) as avg_response_hours
            FROM $table_name 
            WHERE requested_at BETWEEN %s AND %s
            GROUP BY request_type",
            $start_date,
            $end_date
        ), ARRAY_A);
    }
    
    /**
     * Get monthly consent stats
     *
     * @return array Consent statistics
     */
    private function get_monthly_consent_stats() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'spiralengine_privacy_consent';
        $start_date = date('Y-m-01', strtotime('-1 month'));
        $end_date = date('Y-m-t', strtotime('-1 month'));
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT consent_type, 
            SUM(consent_given = 1) as given,
            SUM(consent_given = 0) as withdrawn
            FROM $table_name 
            WHERE timestamp BETWEEN %s AND %s
            GROUP BY consent_type",
            $start_date,
            $end_date
        ), ARRAY_A);
    }
    
    /**
     * Get monthly breach count
     *
     * @return int Breach count
     */
    private function get_monthly_breach_count() {
        // In a real implementation, this would check incident logs
        return 0;
    }
    
    /**
     * Calculate compliance score
     *
     * @return int Compliance score (0-100)
     */
    private function calculate_compliance_score() {
        $score = 100;
        
        // Check various compliance factors
        if (!$this->is_feature_enabled('consent_management')) {
            $score -= 20;
        }
        
        if (!$this->is_feature_enabled('data_encryption')) {
            $score -= 15;
        }
        
        if (!$this->is_feature_enabled('audit_logging')) {
            $score -= 10;
        }
        
        // Check response times
        $avg_response = $this->get_average_response_time();
        if ($avg_response > 24) {
            $score -= 10;
        }
        
        return max(0, $score);
    }
    
    /**
     * Get average response time
     *
     * @return float Average hours
     */
    private function get_average_response_time() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'spiralengine_privacy_requests';
        
        return (float) $wpdb->get_var(
            "SELECT AVG(TIMESTAMPDIFF(HOUR, requested_at, completed_at)) 
            FROM $table_name 
            WHERE completed_at IS NOT NULL"
        ) ?: 0;
    }
    
    /**
     * Send compliance report
     *
     * @param array $report Report data
     */
    private function send_compliance_report($report) {
        $admins = get_users(array('role' => 'administrator'));
        
        foreach ($admins as $admin) {
            wp_mail(
                $admin->user_email,
                sprintf(__('SpiralEngine Compliance Report - %s', 'spiral-engine'), $report['month']),
                $this->format_compliance_report($report),
                array('Content-Type: text/html; charset=UTF-8')
            );
        }
    }
    
    /**
     * Format compliance report
     *
     * @param array $report Report data
     * @return string Formatted report
     */
    private function format_compliance_report($report) {
        ob_start();
        include SPIRALENGINE_PLUGIN_DIR . 'templates/emails/compliance-report.php';
        return ob_get_clean();
    }
    
    /**
     * Add privacy menu
     */
    public function add_privacy_menu() {
        add_submenu_page(
            'spiral-engine',
            __('Privacy Center', 'spiral-engine'),
            __('Privacy', 'spiral-engine'),
            'manage_options',
            'spiralengine-privacy',
            array($this, 'render_privacy_page')
        );
    }
    
    /**
     * Render privacy page
     */
    public function render_privacy_page() {
        include SPIRALENGINE_PLUGIN_DIR . 'admin/views/privacy-center.php';
    }
    
    /**
     * Register privacy settings fields
     */
    public function register_privacy_settings_fields() {
        register_setting('spiralengine_privacy_settings', 'spiralengine_privacy_settings');
        
        add_settings_section(
            'spiralengine_privacy_main',
            __('Privacy Settings', 'spiral-engine'),
            array($this, 'privacy_section_callback'),
            'spiralengine-privacy'
        );
        
        // Add individual settings fields
        $fields = array(
            'consent_management' => __('Consent Management', 'spiral-engine'),
            'data_encryption' => __('Data Encryption', 'spiral-engine'),
            'audit_logging' => __('Audit Logging', 'spiral-engine'),
            'cookie_consent' => __('Cookie Consent', 'spiral-engine'),
            'ip_anonymization' => __('IP Anonymization', 'spiral-engine')
        );
        
        foreach ($fields as $key => $label) {
            add_settings_field(
                'spiralengine_' . $key,
                $label,
                array($this, 'render_checkbox_field'),
                'spiralengine-privacy',
                'spiralengine_privacy_main',
                array('key' => $key)
            );
        }
    }
    
    /**
     * Privacy section callback
     */
    public function privacy_section_callback() {
        echo '<p>' . __('Configure privacy and data protection settings.', 'spiral-engine') . '</p>';
    }
    
    /**
     * Render checkbox field
     *
     * @param array $args Field arguments
     */
    public function render_checkbox_field($args) {
        $key = $args['key'];
        $value = isset($this->privacy_settings[$key]) ? $this->privacy_settings[$key] : false;
        
        printf(
            '<input type="checkbox" name="spiralengine_privacy_settings[%s]" value="1" %s />',
            esc_attr($key),
            checked($value, true, false)
        );
    }
}

/**
 * Flexible Encryption sub-class
 */
class SpiralEngine_Flexible_Encryption {
    
    /**
     * Encryption enabled flag
     *
     * @var bool
     */
    private $encryption_enabled;
    
    /**
     * Encryption level
     *
     * @var string
     */
    private $encryption_level;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->encryption_enabled = get_option('spiralengine_encryption_enabled', true);
        $this->encryption_level = get_option('spiralengine_encryption_level', 'balanced');
    }
    
    /**
     * Maybe encrypt data
     *
     * @param mixed $data Data to encrypt
     * @param string $data_type Data type
     * @return mixed Encrypted or original data
     */
    public function maybe_encrypt($data, $data_type) {
        // Allow admins to disable encryption for development
        if (!$this->encryption_enabled) {
            return $data;
        }
        
        // Different encryption levels based on data type
        $should_encrypt = $this->should_encrypt_type($data_type);
        
        if ($should_encrypt) {
            return $this->encrypt($data);
        }
        
        return $data;
    }
    
    /**
     * Maybe decrypt data
     *
     * @param mixed $data Data to decrypt
     * @param string $data_type Data type
     * @return mixed Decrypted or original data
     */
    public function maybe_decrypt($data, $data_type) {
        if (!$this->encryption_enabled) {
            return $data;
        }
        
        if ($this->is_encrypted($data)) {
            return $this->decrypt($data);
        }
        
        return $data;
    }
    
    /**
     * Should encrypt type
     *
     * @param string $data_type Data type
     * @return bool Should encrypt
     */
    private function should_encrypt_type($data_type) {
        $encryption_map = array(
            'assessment' => true,
            'health_data' => true,
            'ai_conversation' => true,
            'user_preference' => false,
            'analytics' => false,
            'system_log' => false
        );
        
        // Override based on admin settings
        if ($this->encryption_level === 'maximum') {
            return true;
        } elseif ($this->encryption_level === 'minimum') {
            return in_array($data_type, array('assessment', 'health_data'));
        }
        
        return isset($encryption_map[$data_type]) ? $encryption_map[$data_type] : false;
    }
    
    /**
     * Encrypt data
     *
     * @param mixed $data Data to encrypt
     * @return string Encrypted data
     */
    private function encrypt($data) {
        $key = $this->get_encryption_key();
        $data = serialize($data);
        
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-cbc'));
        $encrypted = openssl_encrypt($data, 'aes-256-cbc', $key, 0, $iv);
        
        return base64_encode($encrypted . '::' . $iv);
    }
    
    /**
     * Decrypt data
     *
     * @param string $data Encrypted data
     * @return mixed Decrypted data
     */
    private function decrypt($data) {
        $key = $this->get_encryption_key();
        
        list($encrypted_data, $iv) = explode('::', base64_decode($data), 2);
        $decrypted = openssl_decrypt($encrypted_data, 'aes-256-cbc', $key, 0, $iv);
        
        return unserialize($decrypted);
    }
    
    /**
     * Check if data is encrypted
     *
     * @param mixed $data Data to check
     * @return bool Is encrypted
     */
    private function is_encrypted($data) {
        if (!is_string($data)) {
            return false;
        }
        
        // Check if it's a valid base64 string with our format
        if (base64_encode(base64_decode($data, true)) !== $data) {
            return false;
        }
        
        $decoded = base64_decode($data);
        return strpos($decoded, '::') !== false;
    }
    
    /**
     * Get encryption key
     *
     * @return string Encryption key
     */
    private function get_encryption_key() {
        $key = get_option('spiralengine_encryption_key');
        
        if (!$key) {
            $key = wp_generate_password(32, true, true);
            update_option('spiralengine_encryption_key', $key);
        }
        
        return $key;
    }
}

/**
 * Global Compliance sub-class
 */
class SpiralEngine_Global_Compliance {
    
    /**
     * Get user region
     *
     * @param int $user_id User ID (optional)
     * @return string Region code
     */
    public function get_user_region($user_id = null) {
        // First check if user has set their region
        if ($user_id) {
            $saved_region = get_user_meta($user_id, 'spiralengine_region', true);
            if ($saved_region) {
                return $saved_region;
            }
        }
        
        // Detect from IP
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $country = $this->geolocate_ip($ip);
        
        // Map to compliance regions
        $region_map = array(
            'US' => 'usa',
            'CA' => 'canada',
            'GB' => 'uk',
            'DE' => 'eu', 'FR' => 'eu', 'IT' => 'eu', 'ES' => 'eu',
            'NL' => 'eu', 'BE' => 'eu', 'AT' => 'eu', 'SE' => 'eu',
            'DK' => 'eu', 'FI' => 'eu', 'IE' => 'eu', 'PT' => 'eu',
            'PL' => 'eu', 'CZ' => 'eu', 'HU' => 'eu', 'RO' => 'eu',
            'BG' => 'eu', 'HR' => 'eu', 'SK' => 'eu', 'SI' => 'eu',
            'EE' => 'eu', 'LV' => 'eu', 'LT' => 'eu', 'LU' => 'eu',
            'MT' => 'eu', 'CY' => 'eu', 'GR' => 'eu'
        );
        
        return isset($region_map[$country]) ? $region_map[$country] : 'other';
    }
    
    /**
     * Apply regional settings
     *
     * @param string $region Region code
     * @return array Regional settings
     */
    public function apply_regional_settings($region) {
        $settings = array(
            'eu' => array(
                'cookie_consent' => 'explicit',
                'data_retention' => '2_years',
                'consent_age' => 16,
                'right_to_delete' => true,
                'right_to_portability' => true,
                'right_to_access' => true,
                'right_to_correction' => true,
                'privacy_by_design' => true
            ),
            'usa' => array(
                'cookie_consent' => 'implied',
                'data_retention' => '3_years',
                'consent_age' => 13,
                'right_to_delete' => true,
                'do_not_sell' => true,
                'opt_out_rights' => true
            ),
            'canada' => array(
                'cookie_consent' => 'explicit',
                'data_retention' => '2_years',
                'consent_age' => 13,
                'right_to_delete' => true,
                'right_to_access' => true
            ),
            'uk' => array(
                'cookie_consent' => 'explicit',
                'data_retention' => '2_years',
                'consent_age' => 13,
                'right_to_delete' => true,
                'right_to_portability' => true,
                'right_to_access' => true
            ),
            'other' => array(
                'cookie_consent' => 'implied',
                'data_retention' => '3_years',
                'consent_age' => 13,
                'right_to_delete' => false,
                'basic_privacy' => true
            )
        );
        
        return isset($settings[$region]) ? $settings[$region] : $settings['other'];
    }
    
    /**
     * Geolocate IP address
     *
     * @param string $ip IP address
     * @return string Country code
     */
    private function geolocate_ip($ip) {
        // Check cache first
        $cached = get_transient('spiralengine_geo_' . md5($ip));
        if ($cached !== false) {
            return $cached;
        }
        
        // In production, use a geolocation service
        // For now, return US as default
        $country = 'US';
        
        // Cache for 1 week
        set_transient('spiralengine_geo_' . md5($ip), $country, WEEK_IN_SECONDS);
        
        return $country;
    }
}

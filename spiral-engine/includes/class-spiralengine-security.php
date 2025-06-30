<?php
/**
 * Spiral Engine Security Utilities
 * 
 * Handles all security operations including nonce verification, sanitization,
 * encryption, permissions, and rate limiting
 * 
 * @package    SpiralEngine
 * @subpackage Security
 * @since      1.0.0
 */

// includes/class-spiralengine-security.php

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Security utilities class
 */
class SpiralEngine_Security {
    
    /**
     * Singleton instance
     * 
     * @var SpiralEngine_Security
     */
    private static $instance = null;
    
    /**
     * Encryption cipher
     * 
     * @var string
     */
    private $cipher = 'AES-256-CBC';
    
    /**
     * Rate limit storage
     * 
     * @var array
     */
    private $rate_limits = array();
    
    /**
     * Security features enabled
     * 
     * @var array
     */
    private $enabled_features = array();
    
    /**
     * Get singleton instance
     * 
     * @return SpiralEngine_Security
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
        $this->load_security_settings();
        $this->init_security_features();
    }
    
    /**
     * Load security settings
     */
    private function load_security_settings() {
        $this->enabled_features = array(
            'encryption' => get_option('spiralengine_encryption_enabled', true),
            'audit_logging' => get_option('spiralengine_audit_logging', 'standard'),
            'rate_limiting' => get_option('spiralengine_rate_limiting', true),
            'ip_whitelist' => get_option('spiralengine_ip_whitelist', false),
            'two_factor' => get_option('spiralengine_two_factor', false),
            'session_management' => get_option('spiralengine_session_management', true)
        );
    }
    
    /**
     * Initialize security features
     */
    private function init_security_features() {
        // Add security headers
        add_action('send_headers', array($this, 'add_security_headers'));
        
        // Session security
        if ($this->enabled_features['session_management']) {
            add_action('init', array($this, 'secure_session_init'));
        }
        
        // Login security
        add_filter('authenticate', array($this, 'check_login_attempts'), 30, 3);
        add_action('wp_login_failed', array($this, 'log_failed_login'));
    }
    
    /**
     * Verify nonce for AJAX/form submissions
     * 
     * @param string $nonce Nonce to verify
     * @param string $action Action name
     * @return bool True if valid, dies on failure
     */
    public function verify_nonce($nonce, $action = 'spiralengine_nonce') {
        if (!wp_verify_nonce($nonce, $action)) {
            // Log the failed nonce verification
            $this->log_security_event('nonce_verification_failed', array(
                'action' => $action,
                'ip' => $this->get_client_ip()
            ));
            
            wp_die(
                __('Security check failed. Please refresh the page and try again.', 'spiral-engine'),
                __('Security Error', 'spiral-engine'),
                array('response' => 403)
            );
        }
        
        return true;
    }
    
    /**
     * Sanitize input with multiple sanitization types
     * 
     * @param mixed $input Input to sanitize
     * @param string $type Type of sanitization
     * @return mixed Sanitized input
     */
    public function sanitize_input($input, $type = 'text') {
        if (is_array($input)) {
            return array_map(function($item) use ($type) {
                return $this->sanitize_input($item, $type);
            }, $input);
        }
        
        switch ($type) {
            case 'text':
                return sanitize_text_field($input);
                
            case 'textarea':
                return sanitize_textarea_field($input);
                
            case 'email':
                return sanitize_email($input);
                
            case 'url':
                return esc_url_raw($input);
                
            case 'int':
                return absint($input);
                
            case 'float':
                return floatval($input);
                
            case 'key':
                return sanitize_key($input);
                
            case 'html':
                return wp_kses_post($input);
                
            case 'none':
                return $input;
                
            case 'json':
                $decoded = json_decode($input, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    return $decoded;
                }
                return null;
                
            default:
                return sanitize_text_field($input);
        }
    }
    
    /**
     * Escape output for safe HTML display
     * 
     * @param mixed $output Output to escape
     * @param string $context Context for escaping
     * @return string Escaped output
     */
    public function escape_output($output, $context = 'html') {
        switch ($context) {
            case 'html':
                return esc_html($output);
                
            case 'attr':
                return esc_attr($output);
                
            case 'url':
                return esc_url($output);
                
            case 'js':
                return esc_js($output);
                
            case 'textarea':
                return esc_textarea($output);
                
            case 'none':
                return $output;
                
            default:
                return esc_html($output);
        }
    }
    
    /**
     * Encrypt data using WordPress salts
     * 
     * @param mixed $data Data to encrypt
     * @return string|false Encrypted data or false on failure
     */
    public function encrypt_data($data) {
        if (!$this->enabled_features['encryption']) {
            return $data;
        }
        
        // Serialize if not string
        if (!is_string($data)) {
            $data = serialize($data);
        }
        
        // Generate encryption key from WordPress salts
        $key = $this->get_encryption_key();
        
        // Generate IV
        $iv_length = openssl_cipher_iv_length($this->cipher);
        $iv = openssl_random_pseudo_bytes($iv_length);
        
        // Encrypt
        $encrypted = openssl_encrypt($data, $this->cipher, $key, OPENSSL_RAW_DATA, $iv);
        
        if ($encrypted === false) {
            $this->log_security_event('encryption_failed', array(
                'error' => openssl_error_string()
            ));
            return false;
        }
        
        // Combine IV and encrypted data
        $combined = base64_encode($iv . $encrypted);
        
        // Add HMAC for authentication
        $hmac = hash_hmac('sha256', $combined, $key);
        
        return $hmac . ':' . $combined;
    }
    
    /**
     * Decrypt data using WordPress salts
     * 
     * @param string $encrypted_data Encrypted data
     * @return mixed|false Decrypted data or false on failure
     */
    public function decrypt_data($encrypted_data) {
        if (!$this->enabled_features['encryption'] || empty($encrypted_data)) {
            return $encrypted_data;
        }
        
        // Split HMAC and data
        $parts = explode(':', $encrypted_data, 2);
        if (count($parts) !== 2) {
            return false;
        }
        
        list($hmac, $combined) = $parts;
        
        // Get encryption key
        $key = $this->get_encryption_key();
        
        // Verify HMAC
        $calculated_hmac = hash_hmac('sha256', $combined, $key);
        if (!hash_equals($hmac, $calculated_hmac)) {
            $this->log_security_event('decryption_failed', array(
                'reason' => 'HMAC verification failed'
            ));
            return false;
        }
        
        // Decode
        $decoded = base64_decode($combined);
        if ($decoded === false) {
            return false;
        }
        
        // Extract IV
        $iv_length = openssl_cipher_iv_length($this->cipher);
        $iv = substr($decoded, 0, $iv_length);
        $encrypted = substr($decoded, $iv_length);
        
        // Decrypt
        $decrypted = openssl_decrypt($encrypted, $this->cipher, $key, OPENSSL_RAW_DATA, $iv);
        
        if ($decrypted === false) {
            $this->log_security_event('decryption_failed', array(
                'error' => openssl_error_string()
            ));
            return false;
        }
        
        // Try to unserialize
        $unserialized = @unserialize($decrypted);
        if ($unserialized !== false) {
            return $unserialized;
        }
        
        return $decrypted;
    }
    
    /**
     * Check user permission for capability
     * 
     * @param string $capability Capability to check
     * @param int $user_id Optional user ID
     * @return bool Has permission
     */
    public function check_user_permission($capability, $user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        if (!$user_id) {
            return false;
        }
        
        $user = get_user_by('id', $user_id);
        if (!$user) {
            return false;
        }
        
        // Log permission check if audit level is comprehensive
        if ($this->enabled_features['audit_logging'] === 'comprehensive') {
            $this->log_security_event('permission_check', array(
                'user_id' => $user_id,
                'capability' => $capability,
                'result' => $user->has_cap($capability)
            ));
        }
        
        return $user->has_cap($capability);
    }
    
    /**
     * Rate limit check for API/form spam prevention
     * 
     * @param string $action Action identifier
     * @param int $limit Maximum attempts
     * @param int $window Time window in seconds
     * @return bool True if allowed, false if rate limited
     */
    public function rate_limit_check($action, $limit = 60, $window = 60) {
        if (!$this->enabled_features['rate_limiting']) {
            return true;
        }
        
        $key = $this->get_rate_limit_key($action);
        $current_time = time();
        
        // Get current attempts
        $attempts = get_transient($key);
        if ($attempts === false) {
            $attempts = array();
        }
        
        // Remove old attempts outside the window
        $attempts = array_filter($attempts, function($timestamp) use ($current_time, $window) {
            return ($current_time - $timestamp) < $window;
        });
        
        // Check if limit exceeded
        if (count($attempts) >= $limit) {
            $this->log_security_event('rate_limit_exceeded', array(
                'action' => $action,
                'limit' => $limit,
                'window' => $window,
                'ip' => $this->get_client_ip()
            ));
            return false;
        }
        
        // Add current attempt
        $attempts[] = $current_time;
        
        // Store attempts
        set_transient($key, $attempts, $window);
        
        return true;
    }
    
    /**
     * Add security headers
     */
    public function add_security_headers() {
        // Only add on frontend
        if (is_admin()) {
            return;
        }
        
        // Content Security Policy
        header("Content-Security-Policy: default-src 'self' https:; script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; style-src 'self' 'unsafe-inline' https:;");
        
        // X-Frame-Options
        header('X-Frame-Options: SAMEORIGIN');
        
        // X-Content-Type-Options
        header('X-Content-Type-Options: nosniff');
        
        // X-XSS-Protection
        header('X-XSS-Protection: 1; mode=block');
        
        // Referrer Policy
        header('Referrer-Policy: strict-origin-when-cross-origin');
        
        // Feature Policy
        header("Feature-Policy: geolocation 'self'; microphone 'none'; camera 'none'");
        
        // Strict Transport Security (if HTTPS)
        if (is_ssl()) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }
    }
    
    /**
     * Secure session initialization
     */
    public function secure_session_init() {
        if (!session_id()) {
            // Set secure session parameters
            ini_set('session.cookie_httponly', 1);
            ini_set('session.use_only_cookies', 1);
            
            if (is_ssl()) {
                ini_set('session.cookie_secure', 1);
            }
            
            // Set session name
            session_name('spiralengine_session');
            
            // Start session
            session_start();
            
            // Regenerate session ID periodically
            if (!isset($_SESSION['spiralengine_session_regenerated'])) {
                session_regenerate_id(true);
                $_SESSION['spiralengine_session_regenerated'] = time();
            } elseif (time() - $_SESSION['spiralengine_session_regenerated'] > 1800) {
                // Regenerate every 30 minutes
                session_regenerate_id(true);
                $_SESSION['spiralengine_session_regenerated'] = time();
            }
        }
    }
    
    /**
     * Check login attempts
     * 
     * @param mixed $user User object or error
     * @param string $username Username
     * @param string $password Password
     * @return mixed User object or error
     */
    public function check_login_attempts($user, $username, $password) {
        // Skip if already failed
        if (is_wp_error($user)) {
            return $user;
        }
        
        $ip = $this->get_client_ip();
        $attempts_key = 'spiralengine_login_attempts_' . md5($ip);
        $lockout_key = 'spiralengine_login_lockout_' . md5($ip);
        
        // Check if locked out
        if (get_transient($lockout_key)) {
            return new WP_Error(
                'spiralengine_lockout',
                __('Too many failed login attempts. Please try again later.', 'spiral-engine')
            );
        }
        
        return $user;
    }
    
    /**
     * Log failed login attempt
     * 
     * @param string $username Username
     */
    public function log_failed_login($username) {
        $ip = $this->get_client_ip();
        $attempts_key = 'spiralengine_login_attempts_' . md5($ip);
        $lockout_key = 'spiralengine_login_lockout_' . md5($ip);
        
        // Get current attempts
        $attempts = get_transient($attempts_key);
        if ($attempts === false) {
            $attempts = 0;
        }
        
        $attempts++;
        
        // Set attempts with 15 minute expiry
        set_transient($attempts_key, $attempts, 900);
        
        // Lock out after 5 attempts
        if ($attempts >= 5) {
            set_transient($lockout_key, true, 1800); // 30 minute lockout
            delete_transient($attempts_key);
            
            $this->log_security_event('login_lockout', array(
                'username' => $username,
                'ip' => $ip,
                'attempts' => $attempts
            ));
        } else {
            $this->log_security_event('login_failed', array(
                'username' => $username,
                'ip' => $ip,
                'attempt' => $attempts
            ));
        }
    }
    
    /**
     * Log security event
     * 
     * @param string $event_type Event type
     * @param array $details Event details
     */
    private function log_security_event($event_type, $details = array()) {
        // Check if logging is enabled
        $logging_level = $this->enabled_features['audit_logging'];
        if ($logging_level === false || $logging_level === 'none') {
            return;
        }
        
        // Determine if we should log this event
        $should_log = false;
        $critical_events = array('login_lockout', 'rate_limit_exceeded', 'encryption_failed', 'decryption_failed');
        $standard_events = array('login_failed', 'nonce_verification_failed', 'permission_denied');
        
        if ($logging_level === 'comprehensive') {
            $should_log = true;
        } elseif ($logging_level === 'standard' && (in_array($event_type, $critical_events) || in_array($event_type, $standard_events))) {
            $should_log = true;
        } elseif ($logging_level === 'minimal' && in_array($event_type, $critical_events)) {
            $should_log = true;
        }
        
        if (!$should_log) {
            return;
        }
        
        // Log to database
        global $wpdb;
        $wpdb->insert(
            $wpdb->prefix . 'spiralengine_privacy_audit',
            array(
                'user_id' => get_current_user_id(),
                'action' => 'security_' . $event_type,
                'details' => json_encode($details),
                'ip_address' => $this->get_client_ip(),
                'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? substr($_SERVER['HTTP_USER_AGENT'], 0, 255) : '',
                'timestamp' => current_time('mysql', true)
            ),
            array('%d', '%s', '%s', '%s', '%s', '%s')
        );
    }
    
    /**
     * Get encryption key from WordPress salts
     * 
     * @return string Encryption key
     */
    private function get_encryption_key() {
        $key = wp_salt('secure_auth') . wp_salt('auth');
        return substr(hash('sha256', $key), 0, 32);
    }
    
    /**
     * Get rate limit key
     * 
     * @param string $action Action identifier
     * @return string Rate limit key
     */
    private function get_rate_limit_key($action) {
        $user_id = get_current_user_id();
        $ip = $this->get_client_ip();
        
        if ($user_id) {
            return 'spiralengine_rate_' . $action . '_user_' . $user_id;
        } else {
            return 'spiralengine_rate_' . $action . '_ip_' . md5($ip);
        }
    }
    
    /**
     * Get client IP address
     * 
     * @return string IP address
     */
    private function get_client_ip() {
        $ip_keys = array('HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR');
        
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    
                    if (filter_var($ip, FILTER_VALIDATE_IP, 
                        FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }
        
        return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0';
    }
    
    /**
     * Generate secure random token
     * 
     * @param int $length Token length
     * @return string Random token
     */
    public function generate_token($length = 32) {
        return bin2hex(random_bytes($length / 2));
    }
    
    /**
     * Validate data against rules
     * 
     * @param array $data Data to validate
     * @param array $rules Validation rules
     * @return array Validation result
     */
    public function validate_data($data, $rules) {
        $errors = array();
        $validated = array();
        
        foreach ($rules as $field => $rule) {
            $value = isset($data[$field]) ? $data[$field] : null;
            
            // Required check
            if (isset($rule['required']) && $rule['required'] && empty($value)) {
                $errors[$field] = sprintf(__('%s is required', 'spiral-engine'), $field);
                continue;
            }
            
            // Skip if not required and empty
            if (empty($value) && (!isset($rule['required']) || !$rule['required'])) {
                continue;
            }
            
            // Type validation
            if (isset($rule['type'])) {
                $is_valid = true;
                
                switch ($rule['type']) {
                    case 'email':
                        $is_valid = is_email($value);
                        break;
                    case 'url':
                        $is_valid = filter_var($value, FILTER_VALIDATE_URL);
                        break;
                    case 'int':
                        $is_valid = filter_var($value, FILTER_VALIDATE_INT) !== false;
                        break;
                    case 'float':
                        $is_valid = filter_var($value, FILTER_VALIDATE_FLOAT) !== false;
                        break;
                    case 'boolean':
                        $is_valid = is_bool($value) || in_array($value, array('true', 'false', '1', '0', 1, 0));
                        break;
                }
                
                if (!$is_valid) {
                    $errors[$field] = sprintf(__('%s must be a valid %s', 'spiral-engine'), $field, $rule['type']);
                    continue;
                }
            }
            
            // Min length
            if (isset($rule['min_length']) && strlen($value) < $rule['min_length']) {
                $errors[$field] = sprintf(__('%s must be at least %d characters', 'spiral-engine'), $field, $rule['min_length']);
                continue;
            }
            
            // Max length
            if (isset($rule['max_length']) && strlen($value) > $rule['max_length']) {
                $errors[$field] = sprintf(__('%s must not exceed %d characters', 'spiral-engine'), $field, $rule['max_length']);
                continue;
            }
            
            // Custom validation callback
            if (isset($rule['callback']) && is_callable($rule['callback'])) {
                $result = call_user_func($rule['callback'], $value);
                if ($result !== true) {
                    $errors[$field] = is_string($result) ? $result : sprintf(__('%s is invalid', 'spiral-engine'), $field);
                    continue;
                }
            }
            
            // Sanitize and add to validated data
            $sanitize_type = isset($rule['sanitize']) ? $rule['sanitize'] : 'text';
            $validated[$field] = $this->sanitize_input($value, $sanitize_type);
        }
        
        return array(
            'valid' => empty($errors),
            'errors' => $errors,
            'data' => $validated
        );
    }
}

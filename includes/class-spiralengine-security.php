<?php
/**
 * SpiralEngine Security System
 *
 * Handles nonce verification, rate limiting, IP tracking, and security monitoring
 *
 * @package SpiralEngine
 * @since 1.0.0
 */

// includes/class-spiralengine-security.php

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Security system class
 */
class SpiralEngine_Security {
    
    /**
     * WordPress database object
     *
     * @var wpdb
     */
    private $wpdb;
    
    /**
     * Rate limit cache
     *
     * @var array
     */
    private $rate_limit_cache = [];
    
    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        
        // Initialize hooks
        $this->init_hooks();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Login hooks
        add_action('wp_login', [$this, 'log_successful_login'], 10, 2);
        add_action('wp_login_failed', [$this, 'log_failed_login']);
        
        // Security headers
        add_action('send_headers', [$this, 'add_security_headers']);
        
        // Rate limiting
        add_action('init', [$this, 'check_rate_limits']);
        
        // Admin security
        if (is_admin()) {
            add_action('admin_init', [$this, 'check_admin_security']);
        }
    }
    
    /**
     * Verify nonce
     *
     * @param string $nonce Nonce value
     * @param string $action Action name
     * @return bool Whether valid
     */
    public function verify_nonce(string $nonce, string $action = 'spiralengine_nonce'): bool {
        return wp_verify_nonce($nonce, $action);
    }
    
    /**
     * Create nonce
     *
     * @param string $action Action name
     * @return string Nonce value
     */
    public function create_nonce(string $action = 'spiralengine_nonce'): string {
        return wp_create_nonce($action);
    }
    
    /**
     * Check rate limit
     *
     * @param string $action Action being performed
     * @param string $identifier User identifier (IP or user ID)
     * @return bool Whether allowed
     */
    public function check_rate_limit(string $action, string $identifier = ''): bool {
        if (!get_option('spiralengine_rate_limit_enabled', true)) {
            return true;
        }
        
        if (!$identifier) {
            $identifier = $this->get_rate_limit_identifier();
        }
        
        $key = $action . '_' . $identifier;
        $window = get_option('spiralengine_rate_limit_window', 3600);
        $max_requests = get_option('spiralengine_rate_limit_requests', 100);
        
        // Get current count
        $transient_key = 'spiralengine_rate_' . md5($key);
        $current = get_transient($transient_key) ?: 0;
        
        if ($current >= $max_requests) {
            // Log rate limit violation
            $this->log_security_event('rate_limit_exceeded', [
                'action' => $action,
                'identifier' => $identifier,
                'requests' => $current
            ]);
            
            return false;
        }
        
        // Increment counter
        set_transient($transient_key, $current + 1, $window);
        
        return true;
    }
    
    /**
     * Get rate limit identifier
     *
     * @return string Identifier
     */
    private function get_rate_limit_identifier(): string {
        $user_id = get_current_user_id();
        
        if ($user_id) {
            return 'user_' . $user_id;
        }
        
        return 'ip_' . $this->get_ip_address();
    }
    
    /**
     * Get IP address
     *
     * @return string IP address
     */
    public function get_ip_address(): string {
        $ip_keys = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'];
        
        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                // Handle comma-separated IPs
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        
        return '127.0.0.1';
    }
    
    /**
     * Check if IP is whitelisted
     *
     * @param string $ip IP address
     * @return bool Whether whitelisted
     */
    public function is_ip_whitelisted(string $ip = ''): bool {
        if (!get_option('spiralengine_enable_ip_whitelist', false)) {
            return true;
        }
        
        if (!$ip) {
            $ip = $this->get_ip_address();
        }
        
        $whitelist = get_option('spiralengine_ip_whitelist', []);
        
        foreach ($whitelist as $allowed_ip) {
            // Support CIDR notation
            if (strpos($allowed_ip, '/') !== false) {
                if ($this->ip_in_range($ip, $allowed_ip)) {
                    return true;
                }
            } elseif ($ip === $allowed_ip) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Check if IP is in CIDR range
     *
     * @param string $ip IP address
     * @param string $range CIDR range
     * @return bool Whether in range
     */
    private function ip_in_range(string $ip, string $range): bool {
        list($subnet, $bits) = explode('/', $range);
        $ip = ip2long($ip);
        $subnet = ip2long($subnet);
        $mask = -1 << (32 - $bits);
        $subnet &= $mask;
        
        return ($ip & $mask) == $subnet;
    }
    
    /**
     * Log security event
     *
     * @param string $event_type Event type
     * @param array $details Event details
     */
    public function log_security_event(string $event_type, array $details = []) {
        $details['ip_address'] = $this->get_ip_address();
        $details['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
        $details['timestamp'] = current_time('mysql');
        
        // Log to admin log
        SpiralEngine_Core::log_admin_action('security_' . $event_type, 'security', 0, $details);
        
        // Check if suspicious
        if ($this->is_suspicious_activity($event_type, $details)) {
            $this->handle_suspicious_activity($event_type, $details);
        }
    }
    
    /**
     * Check if activity is suspicious
     *
     * @param string $event_type Event type
     * @param array $details Event details
     * @return bool Whether suspicious
     */
    private function is_suspicious_activity(string $event_type, array $details): bool {
        $suspicious_events = [
            'rate_limit_exceeded',
            'invalid_nonce',
            'unauthorized_access',
            'multiple_failed_logins',
            'sql_injection_attempt',
            'xss_attempt'
        ];
        
        if (in_array($event_type, $suspicious_events)) {
            return true;
        }
        
        // Check for patterns
        if ($event_type === 'failed_login') {
            // Check failed login count
            $ip = $details['ip_address'];
            $count = $this->get_failed_login_count($ip);
            
            if ($count >= 5) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Handle suspicious activity
     *
     * @param string $event_type Event type
     * @param array $details Event details
     */
    private function handle_suspicious_activity(string $event_type, array $details) {
        $ip = $details['ip_address'];
        
        // Auto-block if enabled
        if (get_option('spiralengine_auto_block_suspicious', true)) {
            $this->block_ip($ip, 'suspicious_activity', $event_type);
        }
        
        // Send alert
        $this->send_security_alert($event_type, $details);
        
        // Fire action
        do_action('spiralengine_suspicious_activity', $event_type, $details);
    }
    
    /**
     * Block IP address
     *
     * @param string $ip IP address
     * @param string $reason Block reason
     * @param string $details Additional details
     */
    public function block_ip(string $ip, string $reason = 'manual', string $details = '') {
        $blocked_ips = get_option('spiralengine_blocked_ips', []);
        
        if (!isset($blocked_ips[$ip])) {
            $blocked_ips[$ip] = [
                'blocked_at' => current_time('mysql'),
                'reason' => $reason,
                'details' => $details,
                'expires' => date('Y-m-d H:i:s', strtotime('+24 hours'))
            ];
            
            update_option('spiralengine_blocked_ips', $blocked_ips);
            
            // Log blocking
            $this->log_security_event('ip_blocked', [
                'blocked_ip' => $ip,
                'reason' => $reason,
                'details' => $details
            ]);
        }
    }
    
    /**
     * Unblock IP address
     *
     * @param string $ip IP address
     */
    public function unblock_ip(string $ip) {
        $blocked_ips = get_option('spiralengine_blocked_ips', []);
        
        if (isset($blocked_ips[$ip])) {
            unset($blocked_ips[$ip]);
            update_option('spiralengine_blocked_ips', $blocked_ips);
            
            // Log unblocking
            $this->log_security_event('ip_unblocked', [
                'unblocked_ip' => $ip
            ]);
        }
    }
    
    /**
     * Check if IP is blocked
     *
     * @param string $ip IP address
     * @return bool Whether blocked
     */
    public function is_ip_blocked(string $ip = ''): bool {
        if (!$ip) {
            $ip = $this->get_ip_address();
        }
        
        $blocked_ips = get_option('spiralengine_blocked_ips', []);
        
        if (isset($blocked_ips[$ip])) {
            // Check if expired
            if (!empty($blocked_ips[$ip]['expires'])) {
                if (strtotime($blocked_ips[$ip]['expires']) < time()) {
                    $this->unblock_ip($ip);
                    return false;
                }
            }
            
            return true;
        }
        
        return false;
    }
    
    /**
     * Log successful login
     *
     * @param string $user_login Username
     * @param WP_User $user User object
     */
    public function log_successful_login(string $user_login, $user) {
        $this->log_security_event('successful_login', [
            'user_id' => $user->ID,
            'username' => $user_login
        ]);
        
        // Clear failed login attempts
        $this->clear_failed_login_attempts($this->get_ip_address());
    }
    
    /**
     * Log failed login
     *
     * @param string $username Username attempted
     */
    public function log_failed_login(string $username) {
        $this->log_security_event('failed_login', [
            'username' => $username
        ]);
        
        // Increment failed login count
        $this->increment_failed_login_count($this->get_ip_address());
    }
    
    /**
     * Get failed login count
     *
     * @param string $ip IP address
     * @return int Failed login count
     */
    private function get_failed_login_count(string $ip): int {
        $transient_key = 'spiralengine_failed_logins_' . md5($ip);
        return intval(get_transient($transient_key));
    }
    
    /**
     * Increment failed login count
     *
     * @param string $ip IP address
     */
    private function increment_failed_login_count(string $ip) {
        $transient_key = 'spiralengine_failed_logins_' . md5($ip);
        $count = $this->get_failed_login_count($ip);
        set_transient($transient_key, $count + 1, 3600); // 1 hour window
    }
    
    /**
     * Clear failed login attempts
     *
     * @param string $ip IP address
     */
    private function clear_failed_login_attempts(string $ip) {
        $transient_key = 'spiralengine_failed_logins_' . md5($ip);
        delete_transient($transient_key);
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
        header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline';");
        
        // Other security headers
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('X-XSS-Protection: 1; mode=block');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        
        // HSTS if SSL
        if (is_ssl()) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }
    }
    
    /**
     * Check rate limits
     */
    public function check_rate_limits() {
        // Skip for admin users
        if (current_user_can('manage_options')) {
            return;
        }
        
        // Check if IP is blocked
        if ($this->is_ip_blocked()) {
            wp_die(__('Your IP address has been blocked due to suspicious activity.', 'spiralengine'), 403);
        }
        
        // Check general rate limit
        if (!$this->check_rate_limit('general_request')) {
            wp_die(__('Rate limit exceeded. Please try again later.', 'spiralengine'), 429);
        }
    }
    
    /**
     * Check admin security
     */
    public function check_admin_security() {
        // Check IP whitelist for admin area if enabled
        if (get_option('spiralengine_admin_ip_whitelist_enabled', false)) {
            if (!$this->is_ip_whitelisted()) {
                wp_die(__('Access denied. Your IP is not whitelisted for admin access.', 'spiralengine'), 403);
            }
        }
        
        // Check for two-factor authentication if enabled
        if (get_option('spiralengine_enable_2fa', false)) {
            // Implementation for 2FA check
            do_action('spiralengine_check_2fa');
        }
    }
    
    /**
     * Send security alert
     *
     * @param string $event_type Event type
     * @param array $details Event details
     */
    private function send_security_alert(string $event_type, array $details) {
        $admin_email = get_option('spiralengine_security_alert_email', get_option('admin_email'));
        
        $subject = sprintf('[SpiralEngine Security] %s detected', ucfirst(str_replace('_', ' ', $event_type)));
        
        $message = "Security event detected on your SpiralEngine installation:\n\n";
        $message .= "Event Type: $event_type\n";
        $message .= "Time: " . current_time('mysql') . "\n";
        $message .= "IP Address: " . ($details['ip_address'] ?? 'Unknown') . "\n";
        
        if (!empty($details['user_agent'])) {
            $message .= "User Agent: " . $details['user_agent'] . "\n";
        }
        
        $message .= "\nDetails:\n";
        foreach ($details as $key => $value) {
            if (!in_array($key, ['ip_address', 'user_agent', 'timestamp'])) {
                $message .= ucfirst(str_replace('_', ' ', $key)) . ": " . (is_array($value) ? json_encode($value) : $value) . "\n";
            }
        }
        
        $message .= "\nPlease review your security settings and logs.";
        
        wp_mail($admin_email, $subject, $message);
    }
    
    /**
     * Validate input
     *
     * @param mixed $input Input to validate
     * @param string $type Validation type
     * @return mixed Sanitized input
     */
    public function validate_input($input, string $type = 'text') {
        switch ($type) {
            case 'email':
                return sanitize_email($input);
                
            case 'url':
                return esc_url_raw($input);
                
            case 'int':
                return intval($input);
                
            case 'float':
                return floatval($input);
                
            case 'bool':
                return filter_var($input, FILTER_VALIDATE_BOOLEAN);
                
            case 'html':
                return wp_kses_post($input);
                
            case 'text':
            default:
                return sanitize_text_field($input);
        }
    }
    
    /**
     * Generate secure token
     *
     * @param int $length Token length
     * @return string Secure token
     */
    public function generate_token(int $length = 32): string {
        return bin2hex(random_bytes($length / 2));
    }
    
    /**
     * Hash password
     *
     * @param string $password Password to hash
     * @return string Hashed password
     */
    public function hash_password(string $password): string {
        return wp_hash_password($password);
    }
    
    /**
     * Verify password
     *
     * @param string $password Password to check
     * @param string $hash Hash to check against
     * @return bool Whether matches
     */
    public function verify_password(string $password, string $hash): bool {
        return wp_check_password($password, $hash);
    }
    
    /**
     * Encrypt data
     *
     * @param mixed $data Data to encrypt
     * @param string $key Encryption key
     * @return string Encrypted data
     */
    public function encrypt($data, string $key = ''): string {
        if (!$key) {
            $key = $this->get_encryption_key();
        }
        
        $data = serialize($data);
        $iv = openssl_random_pseudo_bytes(16);
        $encrypted = openssl_encrypt($data, 'AES-256-CBC', $key, 0, $iv);
        
        return base64_encode($iv . $encrypted);
    }
    
    /**
     * Decrypt data
     *
     * @param string $encrypted Encrypted data
     * @param string $key Decryption key
     * @return mixed Decrypted data
     */
    public function decrypt(string $encrypted, string $key = '') {
        if (!$key) {
            $key = $this->get_encryption_key();
        }
        
        $data = base64_decode($encrypted);
        $iv = substr($data, 0, 16);
        $encrypted = substr($data, 16);
        
        $decrypted = openssl_decrypt($encrypted, 'AES-256-CBC', $key, 0, $iv);
        
        return unserialize($decrypted);
    }
    
    /**
     * Get encryption key
     *
     * @return string Encryption key
     */
    private function get_encryption_key(): string {
        $key = get_option('spiralengine_encryption_key');
        
        if (!$key) {
            $key = $this->generate_token(32);
            update_option('spiralengine_encryption_key', $key);
        }
        
        return $key;
    }
}

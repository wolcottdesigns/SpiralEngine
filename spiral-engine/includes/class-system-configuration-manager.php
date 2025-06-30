<?php
/**
 * System Configuration Manager
 * 
 * Central hub for core system configuration, regional settings, and global platform settings
 * Manages email configuration, regional preferences, and enforces system-wide standards
 * 
 * @package    SpiralEngine
 * @subpackage System
 * @since      1.0.0
 */

// includes/class-system-configuration-manager.php

if (!defined('ABSPATH')) {
    exit;
}

class System_Configuration_Manager {
    
    /**
     * Time manager instance
     */
    private $time_manager;
    
    /**
     * Configuration sections
     */
    private $config_sections = array(
        'regional' => 'Regional_Settings_Handler',
        'email' => 'Email_Configuration_Handler',
        'backup' => 'Backup_Manager',
        'maintenance' => 'Maintenance_Tools',
        'integration' => 'Integration_Hub',
        'developer' => 'Developer_Tools',
        'timezone' => 'Time_Zone_Manager'
    );
    
    /**
     * Current configuration cache
     */
    private $config_cache = array();
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->time_manager = new SPIRALENGINE_Time_Zone_Manager();
        $this->load_configurations();
        $this->register_hooks();
        $this->enforce_time_settings();
    }
    
    /**
     * Load all configurations
     */
    private function load_configurations() {
        // Load regional settings
        $this->config_cache['regional'] = get_option('spiralengine_regional_settings', array(
            'default_region' => 'us',
            'default_language' => 'en_US',
            'date_format' => 'm/d/Y',
            'currency_symbol' => '$',
            'currency_position' => 'before'
        ));
        
        // Load email settings
        $this->config_cache['email'] = get_option('spiralengine_email_settings', array(
            'from_email' => get_option('admin_email'),
            'from_name' => get_bloginfo('name'),
            'email_method' => 'wp_mail',
            'smtp_settings' => array()
        ));
        
        // Load system settings
        $this->config_cache['system'] = get_option('spiralengine_system_settings', array(
            'platform_name' => 'SPIRAL Engine Wellness Platform',
            'tagline' => 'Your Journey to Mental Wellness',
            'maintenance_mode' => false,
            'debug_mode' => false
        ));
    }
    
    /**
     * Register hooks
     */
    private function register_hooks() {
        // Settings pages
        add_action('admin_menu', array($this, 'add_settings_pages'));
        add_action('admin_init', array($this, 'register_settings'));
        
        // AJAX handlers
        add_action('wp_ajax_spiralengine_save_config', array($this, 'ajax_save_config'));
        add_action('wp_ajax_spiralengine_test_email', array($this, 'ajax_test_email'));
        
        // Apply regional settings
        add_filter('date_format', array($this, 'apply_date_format'));
        add_filter('time_format', array($this, 'apply_time_format'));
    }
    
    /**
     * Enforce 12-hour time format across the system
     */
    private function enforce_time_settings() {
        // Override WordPress time format
        add_filter('option_time_format', array($this, 'force_12_hour_format'));
        
        // Override date format to include time
        add_filter('option_date_format', array($this, 'enhance_date_format'));
        
        // Add timezone detection script
        add_action('wp_enqueue_scripts', array($this, 'enqueue_timezone_scripts'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_timezone_scripts'));
    }
    
    /**
     * Force 12-hour time format
     * 
     * @param string $format Time format
     * @return string Modified format
     */
    public function force_12_hour_format($format) {
        return 'g:i A'; // 12-hour format with AM/PM
    }
    
    /**
     * Enhance date format to include time
     * 
     * @param string $format Date format
     * @return string Modified format
     */
    public function enhance_date_format($format) {
        // If format doesn't include time, add it
        if (strpos($format, 'g:i') === false && strpos($format, 'h:i') === false) {
            $format .= ' g:i A';
        }
        return $format;
    }
    
    /**
     * Get configuration value
     * 
     * @param string $section Configuration section
     * @param string $key Configuration key
     * @param mixed $default Default value
     * @return mixed Configuration value
     */
    public function get_config($section, $key = null, $default = null) {
        if (!isset($this->config_cache[$section])) {
            return $default;
        }
        
        if ($key === null) {
            return $this->config_cache[$section];
        }
        
        return isset($this->config_cache[$section][$key]) 
            ? $this->config_cache[$section][$key] 
            : $default;
    }
    
    /**
     * Set configuration value
     * 
     * @param string $section Configuration section
     * @param string $key Configuration key
     * @param mixed $value Configuration value
     * @return bool Success
     */
    public function set_config($section, $key, $value) {
        if (!isset($this->config_cache[$section])) {
            $this->config_cache[$section] = array();
        }
        
        $this->config_cache[$section][$key] = $value;
        
        // Save to database
        return update_option('spiralengine_' . $section . '_settings', $this->config_cache[$section]);
    }
    
    /**
     * Get regional settings
     * 
     * @return array Regional settings
     */
    public function get_regional_settings() {
        return $this->get_config('regional', null, array());
    }
    
    /**
     * Get email configuration
     * 
     * @return array Email settings
     */
    public function get_email_config() {
        return $this->get_config('email', null, array());
    }
    
    /**
     * Apply regional date format
     * 
     * @param string $format Date format
     * @return string Modified format
     */
    public function apply_date_format($format) {
        $regional = $this->get_regional_settings();
        
        if (isset($regional['date_format'])) {
            return $regional['date_format'] . ' g:i A';
        }
        
        return $format;
    }
    
    /**
     * Apply regional time format (always 12-hour)
     * 
     * @param string $format Time format
     * @return string Modified format
     */
    public function apply_time_format($format) {
        return 'g:i A'; // Always 12-hour with AM/PM
    }
    
    /**
     * Format currency
     * 
     * @param float $amount Amount
     * @param string $currency Currency code
     * @return string Formatted currency
     */
    public function format_currency($amount, $currency = null) {
        $regional = $this->get_regional_settings();
        
        $symbol = $regional['currency_symbol'] ?? '$';
        $position = $regional['currency_position'] ?? 'before';
        $decimals = $regional['currency_decimals'] ?? 2;
        $decimal_sep = $regional['decimal_separator'] ?? '.';
        $thousands_sep = $regional['thousands_separator'] ?? ',';
        
        $formatted_amount = number_format($amount, $decimals, $decimal_sep, $thousands_sep);
        
        if ($position === 'before') {
            return $symbol . $formatted_amount;
        } else {
            return $formatted_amount . $symbol;
        }
    }
    
    /**
     * Get available languages
     * 
     * @return array Language list
     */
    public function get_available_languages() {
        return array(
            'en_US' => array(
                'name' => 'English (US)',
                'native' => 'English',
                'rtl' => false
            ),
            'en_GB' => array(
                'name' => 'English (UK)',
                'native' => 'English',
                'rtl' => false
            ),
            'es_ES' => array(
                'name' => 'Spanish',
                'native' => 'Español',
                'rtl' => false
            ),
            'fr_FR' => array(
                'name' => 'French',
                'native' => 'Français',
                'rtl' => false
            ),
            'de_DE' => array(
                'name' => 'German',
                'native' => 'Deutsch',
                'rtl' => false
            )
        );
    }
    
    /**
     * Get available regions
     * 
     * @return array Region list
     */
    public function get_available_regions() {
        return array(
            'us' => array(
                'name' => 'United States',
                'languages' => array('en_US'),
                'currency' => 'USD',
                'timezones' => array('America/New_York', 'America/Chicago', 'America/Denver', 'America/Los_Angeles')
            ),
            'uk' => array(
                'name' => 'United Kingdom',
                'languages' => array('en_GB'),
                'currency' => 'GBP',
                'timezones' => array('Europe/London')
            ),
            'ca' => array(
                'name' => 'Canada',
                'languages' => array('en_US', 'fr_FR'),
                'currency' => 'CAD',
                'timezones' => array('America/Toronto', 'America/Vancouver')
            ),
            'au' => array(
                'name' => 'Australia',
                'languages' => array('en_GB'),
                'currency' => 'AUD',
                'timezones' => array('Australia/Sydney', 'Australia/Melbourne', 'Australia/Perth')
            )
        );
    }
    
    /**
     * Send test email
     * 
     * @param string $to Recipient email
     * @param string $subject Email subject
     * @param string $message Email message
     * @return bool Success
     */
    public function send_test_email($to, $subject = null, $message = null) {
        $email_config = $this->get_email_config();
        
        if ($subject === null) {
            $subject = __('Test Email from SPIRAL Engine', 'spiral-engine');
        }
        
        if ($message === null) {
            $message = sprintf(
                __('This is a test email from %s sent at %s', 'spiral-engine'),
                get_bloginfo('name'),
                $this->time_manager->format_user_time(current_time('mysql'), get_current_user_id(), true)
            );
        }
        
        // Set headers
        $headers = array(
            'From: ' . $email_config['from_name'] . ' <' . $email_config['from_email'] . '>',
            'Content-Type: text/html; charset=UTF-8'
        );
        
        // Apply email method
        if ($email_config['email_method'] === 'smtp' && !empty($email_config['smtp_settings'])) {
            // Configure SMTP if needed
            add_action('phpmailer_init', array($this, 'configure_smtp'));
        }
        
        $result = wp_mail($to, $subject, $message, $headers);
        
        // Remove SMTP configuration
        remove_action('phpmailer_init', array($this, 'configure_smtp'));
        
        return $result;
    }
    
    /**
     * Configure SMTP settings
     * 
     * @param PHPMailer $phpmailer PHPMailer instance
     */
    public function configure_smtp($phpmailer) {
        $smtp = $this->get_config('email', 'smtp_settings', array());
        
        if (empty($smtp['host'])) {
            return;
        }
        
        $phpmailer->isSMTP();
        $phpmailer->Host = $smtp['host'];
        $phpmailer->Port = $smtp['port'] ?? 587;
        $phpmailer->SMTPAuth = true;
        $phpmailer->Username = $smtp['username'] ?? '';
        $phpmailer->Password = $smtp['password'] ?? '';
        $phpmailer->SMTPSecure = $smtp['encryption'] ?? 'tls';
    }
    
    /**
     * Get system status
     * 
     * @return array System status information
     */
    public function get_system_status() {
        global $wpdb, $wp_version;
        
        return array(
            'php_version' => PHP_VERSION,
            'wp_version' => $wp_version,
            'mysql_version' => $wpdb->db_version(),
            'plugin_version' => SPIRALENGINE_VERSION,
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'timezone' => wp_timezone_string(),
            'debug_mode' => WP_DEBUG,
            'multisite' => is_multisite()
        );
    }
    
    /**
     * Check system requirements
     * 
     * @return array Requirements check results
     */
    public function check_requirements() {
        $requirements = array();
        
        // PHP Version
        $requirements['php'] = array(
            'label' => __('PHP Version', 'spiral-engine'),
            'required' => '8.0',
            'current' => PHP_VERSION,
            'passed' => version_compare(PHP_VERSION, '8.0', '>=')
        );
        
        // WordPress Version
        global $wp_version;
        $requirements['wordpress'] = array(
            'label' => __('WordPress Version', 'spiral-engine'),
            'required' => '6.0',
            'current' => $wp_version,
            'passed' => version_compare($wp_version, '6.0', '>=')
        );
        
        // Memory Limit
        $memory_limit = $this->parse_size(ini_get('memory_limit'));
        $requirements['memory'] = array(
            'label' => __('Memory Limit', 'spiral-engine'),
            'required' => '256M',
            'current' => ini_get('memory_limit'),
            'passed' => $memory_limit >= 256 * 1024 * 1024
        );
        
        // Database
        global $wpdb;
        $requirements['mysql'] = array(
            'label' => __('MySQL Version', 'spiral-engine'),
            'required' => '5.7',
            'current' => $wpdb->db_version(),
            'passed' => version_compare($wpdb->db_version(), '5.7', '>=')
        );
        
        // Required PHP Extensions
        $required_extensions = array('json', 'mbstring', 'mysqli', 'openssl');
        foreach ($required_extensions as $ext) {
            $requirements['ext_' . $ext] = array(
                'label' => sprintf(__('PHP Extension: %s', 'spiral-engine'), $ext),
                'required' => __('Installed', 'spiral-engine'),
                'current' => extension_loaded($ext) ? __('Installed', 'spiral-engine') : __('Missing', 'spiral-engine'),
                'passed' => extension_loaded($ext)
            );
        }
        
        return $requirements;
    }
    
    /**
     * Parse size string to bytes
     * 
     * @param string $size Size string
     * @return int Size in bytes
     */
    private function parse_size($size) {
        $unit = preg_replace('/[^bkmgtpezy]/i', '', $size);
        $size = preg_replace('/[^0-9\.]/', '', $size);
        
        if ($unit) {
            return round($size * pow(1024, stripos('bkmgtpezy', $unit[0])));
        } else {
            return round($size);
        }
    }
    
    /**
     * Enqueue timezone scripts
     */
    public function enqueue_timezone_scripts() {
        wp_enqueue_script(
            'spiralengine-config',
            SPIRALENGINE_PLUGIN_URL . 'assets/js/spiralengine-config.js',
            array('jquery'),
            SPIRALENGINE_VERSION,
            true
        );
        
        wp_localize_script('spiralengine-config', 'spiralengine_config', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('spiralengine_config'),
            'regional' => $this->get_regional_settings(),
            'i18n' => array(
                'saving' => __('Saving...', 'spiral-engine'),
                'saved' => __('Settings saved', 'spiral-engine'),
                'error' => __('An error occurred', 'spiral-engine')
            )
        ));
    }
    
    /**
     * AJAX save configuration
     */
    public function ajax_save_config() {
        check_ajax_referer('spiralengine_config', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized', 'spiral-engine'));
        }
        
        $section = sanitize_key($_POST['section']);
        $config = wp_unslash($_POST['config']);
        
        // Validate and sanitize based on section
        $sanitized = $this->sanitize_config($section, $config);
        
        if ($this->save_config($section, $sanitized)) {
            wp_send_json_success(array(
                'message' => __('Configuration saved successfully', 'spiral-engine')
            ));
        } else {
            wp_send_json_error(__('Failed to save configuration', 'spiral-engine'));
        }
    }
    
    /**
     * Sanitize configuration data
     * 
     * @param string $section Configuration section
     * @param array $config Raw configuration
     * @return array Sanitized configuration
     */
    private function sanitize_config($section, $config) {
        $sanitized = array();
        
        switch ($section) {
            case 'regional':
                $sanitized['default_region'] = sanitize_key($config['default_region'] ?? 'us');
                $sanitized['default_language'] = sanitize_key($config['default_language'] ?? 'en_US');
                $sanitized['date_format'] = sanitize_text_field($config['date_format'] ?? 'm/d/Y');
                $sanitized['currency_symbol'] = sanitize_text_field($config['currency_symbol'] ?? '$');
                $sanitized['currency_position'] = sanitize_key($config['currency_position'] ?? 'before');
                break;
                
            case 'email':
                $sanitized['from_email'] = sanitize_email($config['from_email'] ?? '');
                $sanitized['from_name'] = sanitize_text_field($config['from_name'] ?? '');
                $sanitized['email_method'] = sanitize_key($config['email_method'] ?? 'wp_mail');
                
                if (isset($config['smtp_settings'])) {
                    $sanitized['smtp_settings'] = array(
                        'host' => sanitize_text_field($config['smtp_settings']['host'] ?? ''),
                        'port' => absint($config['smtp_settings']['port'] ?? 587),
                        'encryption' => sanitize_key($config['smtp_settings']['encryption'] ?? 'tls'),
                        'username' => sanitize_text_field($config['smtp_settings']['username'] ?? ''),
                        'password' => $config['smtp_settings']['password'] ?? ''
                    );
                }
                break;
                
            default:
                $sanitized = array_map('sanitize_text_field', $config);
        }
        
        return $sanitized;
    }
    
    /**
     * Save configuration
     * 
     * @param string $section Configuration section
     * @param array $config Configuration data
     * @return bool Success
     */
    private function save_config($section, $config) {
        $this->config_cache[$section] = $config;
        return update_option('spiralengine_' . $section . '_settings', $config);
    }
    
    /**
     * Display formatted time for admin
     * 
     * @param string $utc_time UTC timestamp
     * @param string $format Date format (optional)
     * @param int $user_id User ID (optional)
     * @return string Formatted time
     */
    public function display_time($utc_time, $format = null, $user_id = null) {
        return $this->time_manager->format_user_time($utc_time, $user_id, true);
    }
    
    /**
     * AJAX test email
     */
    public function ajax_test_email() {
        check_ajax_referer('spiralengine_config', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized', 'spiral-engine'));
        }
        
        $to = sanitize_email($_POST['email']);
        
        if (!is_email($to)) {
            wp_send_json_error(__('Invalid email address', 'spiral-engine'));
        }
        
        if ($this->send_test_email($to)) {
            wp_send_json_success(array(
                'message' => sprintf(__('Test email sent to %s', 'spiral-engine'), $to)
            ));
        } else {
            wp_send_json_error(__('Failed to send test email', 'spiral-engine'));
        }
    }
}

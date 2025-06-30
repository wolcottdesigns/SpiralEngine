<?php
/**
 * Time Zone Management System
 * 
 * Handles all timezone conversions, user timezone detection, and time display formatting
 * CRITICAL: All times stored in UTC, displayed in user's local time with 12-hour AM/PM format
 * 
 * @package    SpiralEngine
 * @subpackage System
 * @since      1.0.0
 */

// includes/class-spiralengine-time-zone-manager.php

if (!defined('ABSPATH')) {
    exit;
}

class SPIRALENGINE_Time_Zone_Manager {
    
    /**
     * User timezone cache
     */
    private $user_timezone_cache = array();
    
    /**
     * Timezone offsets cache
     */
    private $timezone_offsets = array();
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->init_hooks();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // AJAX handlers for timezone detection
        add_action('wp_ajax_spiralengine_save_timezone', array($this, 'ajax_save_timezone'));
        add_action('wp_ajax_nopriv_spiralengine_save_timezone', array($this, 'ajax_save_timezone'));
        
        // Add timezone detection script
        add_action('wp_enqueue_scripts', array($this, 'enqueue_timezone_script'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_timezone_script'));
    }
    
    /**
     * Format time for user display
     * ALWAYS returns 12-hour format with AM/PM
     * 
     * @param string $utc_time UTC timestamp
     * @param int $user_id User ID (optional)
     * @param bool $include_timezone Include timezone abbreviation
     * @return string Formatted time
     */
    public function format_user_time($utc_time, $user_id = null, $include_timezone = false) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        // Get user's timezone
        $timezone = $this->get_user_timezone($user_id);
        
        try {
            // Create DateTime object in UTC
            $dt = new DateTime($utc_time, new DateTimeZone('UTC'));
            
            // Convert to user's timezone
            $dt->setTimezone(new DateTimeZone($timezone));
            
            // Format time - ALWAYS 12-hour with AM/PM
            $formatted = $dt->format('F j, Y g:i A');
            
            // Add timezone abbreviation if requested
            if ($include_timezone) {
                $formatted .= ' ' . $dt->format('T');
            }
            
            return $formatted;
            
        } catch (Exception $e) {
            // Fallback to server time if conversion fails
            error_log('SPIRALENGINE Time Conversion Error: ' . $e->getMessage());
            return date('F j, Y g:i A', strtotime($utc_time));
        }
    }
    
    /**
     * Get user's timezone with caching
     * 
     * @param int $user_id User ID
     * @return string Timezone identifier
     */
    public function get_user_timezone($user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        // Check cache first
        if (isset($this->user_timezone_cache[$user_id])) {
            return $this->user_timezone_cache[$user_id];
        }
        
        // Get from user meta
        $timezone = get_user_meta($user_id, 'spiralengine_timezone', true);
        
        // If not set, try to detect
        if (empty($timezone)) {
            $timezone = $this->detect_user_timezone();
            
            // Save for future use
            if ($timezone && $user_id) {
                update_user_meta($user_id, 'spiralengine_timezone', $timezone);
            }
        }
        
        // Default to system timezone if all else fails
        if (empty($timezone)) {
            $timezone = get_option('spiralengine_default_timezone', 'America/New_York');
        }
        
        // Cache the result
        $this->user_timezone_cache[$user_id] = $timezone;
        
        return $timezone;
    }
    
    /**
     * Detect user's timezone via JavaScript
     * 
     * @return string|false Timezone identifier or false
     */
    public function detect_user_timezone() {
        // This is called via AJAX from JavaScript
        if (isset($_POST['timezone'])) {
            $timezone = sanitize_text_field($_POST['timezone']);
            
            // Validate timezone
            if (in_array($timezone, timezone_identifiers_list())) {
                return $timezone;
            }
        }
        
        // Try to detect from IP
        return $this->detect_timezone_from_ip();
    }
    
    /**
     * Detect timezone from IP address
     * 
     * @return string|false Timezone identifier or false
     */
    private function detect_timezone_from_ip() {
        $ip = $_SERVER['REMOTE_ADDR'];
        
        // Skip for local IPs
        if (in_array($ip, array('127.0.0.1', '::1'))) {
            return false;
        }
        
        // Use transient cache
        $cache_key = 'spiralengine_tz_' . md5($ip);
        $cached = get_transient($cache_key);
        
        if ($cached !== false) {
            return $cached;
        }
        
        // Attempt IP geolocation (implement or use service)
        $timezone = false; // Implement actual geolocation here
        
        // Cache result for 24 hours
        if ($timezone) {
            set_transient($cache_key, $timezone, DAY_IN_SECONDS);
        }
        
        return $timezone;
    }
    
    /**
     * Convert local time to UTC for storage
     * 
     * @param string $local_time Local time string
     * @param string $timezone Timezone identifier
     * @return string UTC timestamp
     */
    public function convert_to_utc($local_time, $timezone) {
        try {
            $dt = new DateTime($local_time, new DateTimeZone($timezone));
            $dt->setTimezone(new DateTimeZone('UTC'));
            return $dt->format('Y-m-d H:i:s');
        } catch (Exception $e) {
            error_log('SPIRALENGINE UTC Conversion Error: ' . $e->getMessage());
            return $local_time;
        }
    }
    
    /**
     * Get relative time (e.g., "2 hours ago")
     * 
     * @param string $utc_time UTC timestamp
     * @param int $user_id User ID
     * @return string Relative time string
     */
    public function get_relative_time($utc_time, $user_id = null) {
        $timezone = $this->get_user_timezone($user_id);
        
        try {
            $now = new DateTime('now', new DateTimeZone($timezone));
            $then = new DateTime($utc_time, new DateTimeZone('UTC'));
            $then->setTimezone(new DateTimeZone($timezone));
            
            $diff = $now->diff($then);
            
            if ($diff->days > 7) {
                // Show actual date for older items
                return $then->format('F j, Y g:i A');
            } elseif ($diff->days > 0) {
                return $diff->days . ' day' . ($diff->days > 1 ? 's' : '') . ' ago';
            } elseif ($diff->h > 0) {
                return $diff->h . ' hour' . ($diff->h > 1 ? 's' : '') . ' ago';
            } elseif ($diff->i > 0) {
                return $diff->i . ' minute' . ($diff->i > 1 ? 's' : '') . ' ago';
            } else {
                return 'Just now';
            }
        } catch (Exception $e) {
            return $this->format_user_time($utc_time, $user_id);
        }
    }
    
    /**
     * Format time for specific timezone display
     * 
     * @param string $utc_time UTC timestamp
     * @param string $timezone Timezone identifier
     * @param string $format Date format (will be forced to 12-hour)
     * @return string Formatted time
     */
    public function format_time_for_timezone($utc_time, $timezone, $format = 'F j, Y g:i A') {
        try {
            $dt = new DateTime($utc_time, new DateTimeZone('UTC'));
            $dt->setTimezone(new DateTimeZone($timezone));
            
            // Ensure 12-hour format
            $format = str_replace(array('H', 'G'), array('h', 'g'), $format);
            if (strpos($format, 'A') === false && strpos($format, 'a') === false) {
                $format .= ' A';
            }
            
            return $dt->format($format);
        } catch (Exception $e) {
            return date($format, strtotime($utc_time));
        }
    }
    
    /**
     * Get timezone offset from UTC
     * 
     * @param string $timezone Timezone identifier
     * @return int Offset in seconds
     */
    public function get_timezone_offset($timezone) {
        if (isset($this->timezone_offsets[$timezone])) {
            return $this->timezone_offsets[$timezone];
        }
        
        try {
            $tz = new DateTimeZone($timezone);
            $dt = new DateTime('now', $tz);
            $offset = $tz->getOffset($dt);
            
            // Cache the offset
            $this->timezone_offsets[$timezone] = $offset;
            
            return $offset;
        } catch (Exception $e) {
            return 0;
        }
    }
    
    /**
     * AJAX handler to save user timezone
     */
    public function ajax_save_timezone() {
        check_ajax_referer('spiralengine_nonce', 'nonce');
        
        $timezone = sanitize_text_field($_POST['timezone']);
        $user_id = get_current_user_id();
        
        if ($user_id && in_array($timezone, timezone_identifiers_list())) {
            update_user_meta($user_id, 'spiralengine_timezone', $timezone);
            
            // Clear cache
            unset($this->user_timezone_cache[$user_id]);
            
            wp_send_json_success(array(
                'timezone' => $timezone,
                'message' => __('Timezone updated successfully', 'spiral-engine')
            ));
        } else {
            wp_send_json_error('Invalid timezone');
        }
    }
    
    /**
     * Enqueue timezone detection script
     */
    public function enqueue_timezone_script() {
        wp_enqueue_script(
            'spiralengine-timezone',
            SPIRALENGINE_PLUGIN_URL . 'assets/js/spiralengine-timezone.js',
            array('jquery'),
            SPIRALENGINE_VERSION,
            true
        );
        
        wp_localize_script('spiralengine-timezone', 'spiralengine_timezone', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('spiralengine_nonce'),
            'detected' => $this->get_user_timezone()
        ));
    }
    
    /**
     * Get list of common timezones for dropdown
     * 
     * @return array Timezone list
     */
    public function get_timezone_list() {
        $zones = array(
            'America/New_York' => 'Eastern Time (US & Canada)',
            'America/Chicago' => 'Central Time (US & Canada)',
            'America/Denver' => 'Mountain Time (US & Canada)',
            'America/Los_Angeles' => 'Pacific Time (US & Canada)',
            'America/Phoenix' => 'Arizona',
            'America/Anchorage' => 'Alaska',
            'Pacific/Honolulu' => 'Hawaii',
            'Europe/London' => 'London',
            'Europe/Paris' => 'Paris',
            'Europe/Berlin' => 'Berlin',
            'Asia/Tokyo' => 'Tokyo',
            'Asia/Shanghai' => 'Beijing',
            'Australia/Sydney' => 'Sydney',
            'Australia/Melbourne' => 'Melbourne'
        );
        
        return apply_filters('spiralengine_timezone_list', $zones);
    }
    
    /**
     * Display timezone selector
     * 
     * @param string $selected Selected timezone
     * @param string $name Field name
     * @param string $id Field ID
     * @return string HTML output
     */
    public function display_timezone_selector($selected = '', $name = 'timezone', $id = 'timezone') {
        $zones = $this->get_timezone_list();
        
        $output = '<select name="' . esc_attr($name) . '" id="' . esc_attr($id) . '" class="spiralengine-timezone-select">';
        
        foreach ($zones as $value => $label) {
            $output .= '<option value="' . esc_attr($value) . '"';
            if ($selected === $value) {
                $output .= ' selected="selected"';
            }
            $output .= '>' . esc_html($label) . '</option>';
        }
        
        $output .= '</select>';
        
        return $output;
    }
}

<?php
/**
 * Spiral Engine Database Handler
 * 
 * Manages all database operations for the Spiral Engine plugin
 * 
 * @package    SpiralEngine
 * @subpackage Database
 * @since      1.0.0
 */

// includes/class-spiralengine-database.php

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Database handler class
 */
class SpiralEngine_Database {
    
    /**
     * WordPress database object
     * 
     * @var wpdb
     */
    private $wpdb;
    
    /**
     * Database version
     * 
     * @var string
     */
    private $db_version = '1.0.0';
    
    /**
     * Table names cache
     * 
     * @var array
     */
    private $tables = array();
    
    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->init_table_names();
    }
    
    /**
     * Initialize table names with proper prefixes
     */
    private function init_table_names() {
        $this->tables = array(
            // System Tools Center tables
            'assessments' => $this->wpdb->prefix . 'spiralengine_assessments',
            'episodes' => $this->wpdb->prefix . 'spiralengine_episodes',
            'correlations' => $this->wpdb->prefix . 'spiralengine_correlations',
            'widgets' => $this->wpdb->prefix . 'spiralengine_widgets',
            'widget_access' => $this->wpdb->prefix . 'spiralengine_widget_access',
            'user_settings' => $this->wpdb->prefix . 'spiralengine_user_settings',
            'insights' => $this->wpdb->prefix . 'spiralengine_insights',
            'system_health' => $this->wpdb->prefix . 'spiralengine_system_health',
            
            // Master Control Center tables
            'master_control' => $this->wpdb->prefix . 'spiralengine_master_control',
            'system_modes' => $this->wpdb->prefix . 'spiralengine_system_modes',
            'resource_limits' => $this->wpdb->prefix . 'spiralengine_resource_limits',
            'emergency_protocols' => $this->wpdb->prefix . 'spiralengine_emergency_protocols',
            'center_commands' => $this->wpdb->prefix . 'spiralengine_center_commands',
            'health_metrics' => $this->wpdb->prefix . 'spiralengine_health_metrics',
            
            // Security Command Center tables
            'privacy_consent' => $this->wpdb->prefix . 'spiralengine_privacy_consent',
            'privacy_requests' => $this->wpdb->prefix . 'spiralengine_privacy_requests',
            'privacy_audit' => $this->wpdb->prefix . 'spiralengine_privacy_audit'
        );
    }
    
    /**
     * Get table name
     * 
     * @param string $table Table identifier
     * @return string Full table name with prefix
     */
    public function get_table_name($table) {
        return isset($this->tables[$table]) ? $this->tables[$table] : false;
    }
    
    /**
     * Get all table names
     * 
     * @return array
     */
    public function get_all_table_names() {
        return $this->tables;
    }
    
    /**
     * Save assessment data
     * 
     * @param array $data Assessment data
     * @return int|false Assessment ID or false on failure
     */
    public function save_assessment($data) {
        // Sanitize data
        $sanitized_data = array(
            'user_id' => absint($data['user_id']),
            'assessment_type' => sanitize_text_field($data['assessment_type']),
            'responses' => wp_json_encode($data['responses']),
            'results' => wp_json_encode($data['results']),
            'created_at' => current_time('mysql', true),
            'updated_at' => current_time('mysql', true)
        );
        
        // Use transient caching for expensive operations
        $cache_key = 'spiralengine_assessment_' . $sanitized_data['user_id'] . '_' . $sanitized_data['assessment_type'];
        delete_transient($cache_key);
        
        // Insert using prepared statement
        $result = $this->wpdb->insert(
            $this->tables['assessments'],
            $sanitized_data,
            array('%d', '%s', '%s', '%s', '%s', '%s')
        );
        
        if ($result) {
            return $this->wpdb->insert_id;
        }
        
        return false;
    }
    
    /**
     * Get episodes for a user
     * 
     * @param int $user_id User ID
     * @param array $args Query arguments
     * @return array Episodes
     */
    public function get_episodes($user_id, $args = array()) {
        $defaults = array(
            'episode_type' => '',
            'status' => '',
            'limit' => 50,
            'offset' => 0,
            'orderby' => 'created_at',
            'order' => 'DESC'
        );
        
        $args = wp_parse_args($args, $defaults);
        
        // Build query
        $query = "SELECT * FROM {$this->tables['episodes']} WHERE user_id = %d";
        $query_args = array($user_id);
        
        if (!empty($args['episode_type'])) {
            $query .= " AND episode_type = %s";
            $query_args[] = $args['episode_type'];
        }
        
        if (!empty($args['status'])) {
            $query .= " AND status = %s";
            $query_args[] = $args['status'];
        }
        
        // Add ordering
        $allowed_orderby = array('created_at', 'updated_at', 'severity');
        $orderby = in_array($args['orderby'], $allowed_orderby) ? $args['orderby'] : 'created_at';
        $order = strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';
        $query .= " ORDER BY {$orderby} {$order}";
        
        // Add limit
        $query .= " LIMIT %d OFFSET %d";
        $query_args[] = $args['limit'];
        $query_args[] = $args['offset'];
        
        // Prepare and execute
        $prepared_query = $this->wpdb->prepare($query, $query_args);
        $results = $this->wpdb->get_results($prepared_query, ARRAY_A);
        
        // Decode JSON fields
        foreach ($results as &$episode) {
            $episode['episode_data'] = json_decode($episode['episode_data'], true);
            $episode['triggers'] = json_decode($episode['triggers'], true);
            $episode['context'] = json_decode($episode['context'], true);
        }
        
        return $results;
    }
    
    /**
     * Save episode data
     * 
     * @param array $data Episode data
     * @return int|false Episode ID or false on failure
     */
    public function save_episode($data) {
        $sanitized_data = array(
            'user_id' => absint($data['user_id']),
            'episode_type' => sanitize_text_field($data['episode_type']),
            'severity' => isset($data['severity']) ? absint($data['severity']) : 5,
            'status' => sanitize_text_field($data['status']),
            'episode_data' => wp_json_encode($data['episode_data']),
            'triggers' => wp_json_encode($data['triggers']),
            'context' => wp_json_encode($data['context']),
            'created_at' => current_time('mysql', true),
            'updated_at' => current_time('mysql', true)
        );
        
        $result = $this->wpdb->insert(
            $this->tables['episodes'],
            $sanitized_data,
            array('%d', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s')
        );
        
        if ($result) {
            // Clear related cache
            $this->clear_user_cache($data['user_id']);
            return $this->wpdb->insert_id;
        }
        
        return false;
    }
    
    /**
     * Get user settings
     * 
     * @param int $user_id User ID
     * @param string $setting_key Optional specific setting key
     * @return mixed Settings array or specific setting value
     */
    public function get_user_settings($user_id, $setting_key = '') {
        // Check transient cache first
        $cache_key = 'spiralengine_user_settings_' . $user_id;
        $cached_settings = get_transient($cache_key);
        
        if ($cached_settings !== false && empty($setting_key)) {
            return $cached_settings;
        }
        
        $query = $this->wpdb->prepare(
            "SELECT setting_key, setting_value FROM {$this->tables['user_settings']} WHERE user_id = %d",
            $user_id
        );
        
        $results = $this->wpdb->get_results($query, ARRAY_A);
        
        $settings = array();
        foreach ($results as $row) {
            $settings[$row['setting_key']] = maybe_unserialize($row['setting_value']);
        }
        
        // Cache for 1 hour
        set_transient($cache_key, $settings, HOUR_IN_SECONDS);
        
        if (!empty($setting_key)) {
            return isset($settings[$setting_key]) ? $settings[$setting_key] : null;
        }
        
        return $settings;
    }
    
    /**
     * Save user setting
     * 
     * @param int $user_id User ID
     * @param string $setting_key Setting key
     * @param mixed $setting_value Setting value
     * @return bool Success
     */
    public function save_user_setting($user_id, $setting_key, $setting_value) {
        $data = array(
            'user_id' => absint($user_id),
            'setting_key' => sanitize_key($setting_key),
            'setting_value' => maybe_serialize($setting_value),
            'updated_at' => current_time('mysql', true)
        );
        
        // Try update first
        $where = array(
            'user_id' => $user_id,
            'setting_key' => $setting_key
        );
        
        $updated = $this->wpdb->update(
            $this->tables['user_settings'],
            $data,
            $where,
            array('%d', '%s', '%s', '%s'),
            array('%d', '%s')
        );
        
        // If no rows updated, insert
        if ($updated === 0) {
            $data['created_at'] = current_time('mysql', true);
            $result = $this->wpdb->insert(
                $this->tables['user_settings'],
                $data,
                array('%d', '%s', '%s', '%s', '%s')
            );
        } else {
            $result = true;
        }
        
        // Clear cache
        delete_transient('spiralengine_user_settings_' . $user_id);
        
        return $result !== false;
    }
    
    /**
     * Get widgets
     * 
     * @param array $args Query arguments
     * @return array Widgets
     */
    public function get_widgets($args = array()) {
        $defaults = array(
            'widget_type' => '',
            'status' => 'active',
            'category' => '',
            'limit' => 100,
            'offset' => 0
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $query = "SELECT * FROM {$this->tables['widgets']} WHERE 1=1";
        $query_args = array();
        
        if (!empty($args['widget_type'])) {
            $query .= " AND widget_type = %s";
            $query_args[] = $args['widget_type'];
        }
        
        if (!empty($args['status'])) {
            $query .= " AND status = %s";
            $query_args[] = $args['status'];
        }
        
        if (!empty($args['category'])) {
            $query .= " AND category = %s";
            $query_args[] = $args['category'];
        }
        
        $query .= " ORDER BY display_order ASC, widget_name ASC";
        $query .= " LIMIT %d OFFSET %d";
        $query_args[] = $args['limit'];
        $query_args[] = $args['offset'];
        
        if (!empty($query_args)) {
            $query = $this->wpdb->prepare($query, $query_args);
        }
        
        $results = $this->wpdb->get_results($query, ARRAY_A);
        
        // Decode JSON fields
        foreach ($results as &$widget) {
            $widget['configuration'] = json_decode($widget['configuration'], true);
            $widget['permissions'] = json_decode($widget['permissions'], true);
            $widget['preview_settings'] = json_decode($widget['preview_settings'], true);
        }
        
        return $results;
    }
    
    /**
     * Save system health metric
     * 
     * @param string $metric_type Metric type
     * @param string $metric_key Metric key
     * @param float $metric_value Metric value
     * @param array $thresholds Optional thresholds
     * @return bool Success
     */
    public function save_health_metric($metric_type, $metric_key, $metric_value, $thresholds = array()) {
        $status = 'healthy';
        
        // Determine status based on thresholds
        if (!empty($thresholds)) {
            if (isset($thresholds['critical']) && $metric_value >= $thresholds['critical']) {
                $status = 'critical';
            } elseif (isset($thresholds['warning']) && $metric_value >= $thresholds['warning']) {
                $status = 'warning';
            }
        }
        
        $data = array(
            'metric_type' => sanitize_text_field($metric_type),
            'metric_key' => sanitize_key($metric_key),
            'metric_value' => floatval($metric_value),
            'threshold_warning' => isset($thresholds['warning']) ? floatval($thresholds['warning']) : null,
            'threshold_critical' => isset($thresholds['critical']) ? floatval($thresholds['critical']) : null,
            'status' => $status,
            'recorded_at' => current_time('mysql', true)
        );
        
        $result = $this->wpdb->insert(
            $this->tables['health_metrics'],
            $data,
            array('%s', '%s', '%f', '%f', '%f', '%s', '%s')
        );
        
        return $result !== false;
    }
    
    /**
     * Get latest health metrics
     * 
     * @param string $metric_type Optional metric type filter
     * @return array Metrics
     */
    public function get_latest_health_metrics($metric_type = '') {
        $query = "SELECT DISTINCT ON (metric_key) * FROM {$this->tables['health_metrics']}";
        
        if (!empty($metric_type)) {
            $query .= $this->wpdb->prepare(" WHERE metric_type = %s", $metric_type);
        }
        
        $query .= " ORDER BY metric_key, recorded_at DESC";
        
        return $this->wpdb->get_results($query, ARRAY_A);
    }
    
    /**
     * Log privacy audit event
     * 
     * @param string $action Action performed
     * @param array $details Event details
     * @param int $user_id Optional user ID
     * @return bool Success
     */
    public function log_privacy_audit($action, $details = array(), $user_id = null) {
        $data = array(
            'user_id' => $user_id ? absint($user_id) : get_current_user_id(),
            'action' => sanitize_text_field($action),
            'details' => wp_json_encode($details),
            'ip_address' => $this->get_client_ip(),
            'user_agent' => substr($_SERVER['HTTP_USER_AGENT'], 0, 255),
            'timestamp' => current_time('mysql', true)
        );
        
        $result = $this->wpdb->insert(
            $this->tables['privacy_audit'],
            $data,
            array('%d', '%s', '%s', '%s', '%s', '%s')
        );
        
        return $result !== false;
    }
    
    /**
     * Save consent record
     * 
     * @param int $user_id User ID
     * @param string $consent_type Type of consent
     * @param bool $given Whether consent was given
     * @param string $consent_text The consent text shown
     * @return bool Success
     */
    public function save_consent($user_id, $consent_type, $given, $consent_text) {
        $data = array(
            'user_id' => absint($user_id),
            'consent_type' => sanitize_text_field($consent_type),
            'consent_given' => $given ? 1 : 0,
            'consent_text' => wp_kses_post($consent_text),
            'consent_version' => get_option('spiralengine_consent_version', '1.0'),
            'ip_address' => $this->get_client_ip(),
            'timestamp' => current_time('mysql', true)
        );
        
        $result = $this->wpdb->insert(
            $this->tables['privacy_consent'],
            $data,
            array('%d', '%s', '%d', '%s', '%s', '%s', '%s')
        );
        
        if ($result) {
            // Log the consent action
            $this->log_privacy_audit('consent_updated', array(
                'consent_type' => $consent_type,
                'given' => $given
            ), $user_id);
        }
        
        return $result !== false;
    }
    
    /**
     * Optimize database tables
     */
    public function optimize_tables() {
        foreach ($this->tables as $table) {
            $this->wpdb->query("OPTIMIZE TABLE {$table}");
        }
    }
    
    /**
     * Clean up expired sessions
     */
    public function cleanup_expired_sessions() {
        // Clean up sessions older than 24 hours
        $expiry_time = date('Y-m-d H:i:s', strtotime('-24 hours'));
        
        $this->wpdb->query(
            $this->wpdb->prepare(
                "DELETE FROM {$this->tables['user_settings']} 
                WHERE setting_key LIKE %s AND updated_at < %s",
                'session_%',
                $expiry_time
            )
        );
    }
    
    /**
     * Clean up old logs
     * 
     * @param int $days Days to keep logs
     */
    public function cleanup_old_logs($days = 365) {
        $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        // Clean privacy audit logs
        $this->wpdb->query(
            $this->wpdb->prepare(
                "DELETE FROM {$this->tables['privacy_audit']} WHERE timestamp < %s",
                $cutoff_date
            )
        );
        
        // Clean old health metrics
        $this->wpdb->query(
            $this->wpdb->prepare(
                "DELETE FROM {$this->tables['health_metrics']} WHERE recorded_at < %s",
                $cutoff_date
            )
        );
    }
    
    /**
     * Clear user-specific cache
     * 
     * @param int $user_id User ID
     */
    private function clear_user_cache($user_id) {
        delete_transient('spiralengine_user_settings_' . $user_id);
        delete_transient('spiralengine_user_episodes_' . $user_id);
        delete_transient('spiralengine_user_assessments_' . $user_id);
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
}

<?php
// includes/tools/class-spiralengine-import-export.php

/**
 * Spiral Engine Import/Export Manager
 * 
 * Handles data import and export functionality with format support,
 * validation, and bulk operations.
 *
 * @package    Spiral_Engine
 * @subpackage Spiral_Engine/tools
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class SPIRALENGINE_Import_Export {
    
    /**
     * Time manager instance
     */
    private $time_manager;
    
    /**
     * Supported export formats
     */
    private $export_formats = array(
        'csv' => 'CSV (Comma Separated)',
        'json' => 'JSON (JavaScript Object Notation)',
        'xml' => 'XML (Extensible Markup Language)',
        'xlsx' => 'Excel Spreadsheet'
    );
    
    /**
     * Supported import formats
     */
    private $import_formats = array(
        'csv' => array('text/csv', 'application/csv'),
        'json' => array('application/json', 'text/json'),
        'xml' => array('application/xml', 'text/xml'),
        'xlsx' => array('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
    );
    
    /**
     * Export data types
     */
    private $data_types = array(
        'users' => 'User Data',
        'assessments' => 'Assessment Data',
        'episodes' => 'Episode Logs',
        'widgets' => 'Widget Configurations',
        'settings' => 'System Settings',
        'analytics' => 'Analytics Data',
        'ai_data' => 'AI Configuration & Data'
    );
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->time_manager = new SPIRALENGINE_Time_Zone_Manager();
        $this->init_hooks();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // AJAX handlers
        add_action('wp_ajax_spiralengine_export_data', array($this, 'ajax_export_data'));
        add_action('wp_ajax_spiralengine_import_data', array($this, 'ajax_import_data'));
        add_action('wp_ajax_spiralengine_validate_import', array($this, 'ajax_validate_import'));
        add_action('wp_ajax_spiralengine_get_export_progress', array($this, 'ajax_get_export_progress'));
        add_action('wp_ajax_spiralengine_get_import_progress', array($this, 'ajax_get_import_progress'));
        
        // Scheduled exports
        add_action('spiralengine_scheduled_export', array($this, 'run_scheduled_export'));
    }
    
    /**
     * Export data
     */
    public function export_data($type, $format = 'csv', $options = array()) {
        // Start export session
        $export_id = $this->generate_export_id();
        $this->update_export_progress($export_id, 0, 'Initializing export...');
        
        try {
            // Get data based on type
            $data = $this->get_export_data($type, $options);
            
            if (empty($data)) {
                throw new Exception(__('No data to export', 'spiralengine'));
            }
            
            $this->update_export_progress($export_id, 30, 'Data retrieved, formatting...');
            
            // Format data
            $formatted_data = $this->format_export_data($data, $format, $type);
            
            $this->update_export_progress($export_id, 60, 'Creating export file...');
            
            // Create export file
            $file_path = $this->create_export_file($formatted_data, $format, $type, $export_id);
            
            if (!$file_path) {
                throw new Exception(__('Could not create export file', 'spiralengine'));
            }
            
            $this->update_export_progress($export_id, 90, 'Finalizing export...');
            
            // Get download URL
            $download_url = $this->get_export_download_url($export_id);
            
            // Log export
            $this->log_export($export_id, $type, $format, count($data));
            
            $this->update_export_progress($export_id, 100, 'Export complete');
            
            return array(
                'success' => true,
                'export_id' => $export_id,
                'download_url' => $download_url,
                'file_size' => filesize($file_path),
                'record_count' => count($data),
                'format' => $format
            );
            
        } catch (Exception $e) {
            $this->update_export_progress($export_id, -1, $e->getMessage());
            return new WP_Error('export_error', $e->getMessage());
        }
    }
    
    /**
     * Import data
     */
    public function import_data($file_path, $type, $options = array()) {
        // Start import session
        $import_id = $this->generate_import_id();
        $this->update_import_progress($import_id, 0, 'Initializing import...');
        
        try {
            // Detect format
            $format = $this->detect_file_format($file_path);
            if (!$format) {
                throw new Exception(__('Unsupported file format', 'spiralengine'));
            }
            
            $this->update_import_progress($import_id, 10, 'Reading file...');
            
            // Parse file
            $data = $this->parse_import_file($file_path, $format);
            
            if (empty($data)) {
                throw new Exception(__('No data found in file', 'spiralengine'));
            }
            
            $this->update_import_progress($import_id, 30, 'Validating data...');
            
            // Validate data
            $validation = $this->validate_import_data($data, $type);
            if (!$validation['valid']) {
                throw new Exception(implode(', ', $validation['errors']));
            }
            
            // Create backup before import if enabled
            if (!empty($options['create_backup'])) {
                $this->update_import_progress($import_id, 40, 'Creating backup...');
                $this->create_pre_import_backup($type);
            }
            
            $this->update_import_progress($import_id, 50, 'Importing data...');
            
            // Process import
            $result = $this->process_import($data, $type, $options, $import_id);
            
            $this->update_import_progress($import_id, 90, 'Finalizing import...');
            
            // Log import
            $this->log_import($import_id, $type, $format, $result);
            
            $this->update_import_progress($import_id, 100, 'Import complete');
            
            return array(
                'success' => true,
                'import_id' => $import_id,
                'imported' => $result['imported'],
                'skipped' => $result['skipped'],
                'errors' => $result['errors'],
                'total' => count($data)
            );
            
        } catch (Exception $e) {
            $this->update_import_progress($import_id, -1, $e->getMessage());
            return new WP_Error('import_error', $e->getMessage());
        }
    }
    
    /**
     * Get export data based on type
     */
    private function get_export_data($type, $options = array()) {
        global $wpdb;
        
        switch ($type) {
            case 'users':
                return $this->export_user_data($options);
                
            case 'assessments':
                return $this->export_assessment_data($options);
                
            case 'episodes':
                return $this->export_episode_data($options);
                
            case 'widgets':
                return $this->export_widget_data($options);
                
            case 'settings':
                return $this->export_settings_data($options);
                
            case 'analytics':
                return $this->export_analytics_data($options);
                
            case 'ai_data':
                return $this->export_ai_data($options);
                
            default:
                return apply_filters('spiralengine_export_data_' . $type, array(), $options);
        }
    }
    
    /**
     * Export user data
     */
    private function export_user_data($options = array()) {
        global $wpdb;
        
        $users = array();
        
        // Base query
        $query = "SELECT u.*, um.meta_key, um.meta_value 
                  FROM {$wpdb->users} u
                  LEFT JOIN {$wpdb->usermeta} um ON u.ID = um.user_id
                  WHERE um.meta_key LIKE 'spiralengine_%'";
        
        // Add date filter if specified
        if (!empty($options['date_from'])) {
            $query .= $wpdb->prepare(" AND u.user_registered >= %s", $options['date_from']);
        }
        
        if (!empty($options['date_to'])) {
            $query .= $wpdb->prepare(" AND u.user_registered <= %s", $options['date_to']);
        }
        
        $results = $wpdb->get_results($query);
        
        // Group by user
        $user_data = array();
        foreach ($results as $row) {
            if (!isset($user_data[$row->ID])) {
                $user_data[$row->ID] = array(
                    'ID' => $row->ID,
                    'username' => $row->user_login,
                    'email' => $row->user_email,
                    'registered' => $this->time_manager->format_user_time($row->user_registered),
                    'registered_utc' => $row->user_registered,
                    'display_name' => $row->display_name,
                    'meta' => array()
                );
            }
            
            // Add meta data
            if ($row->meta_key) {
                $key = str_replace('spiralengine_', '', $row->meta_key);
                $user_data[$row->ID]['meta'][$key] = maybe_unserialize($row->meta_value);
            }
        }
        
        // Handle privacy settings
        if (!empty($options['anonymize'])) {
            foreach ($user_data as &$user) {
                $user['email'] = 'user' . $user['ID'] . '@anonymized.com';
                $user['username'] = 'user_' . $user['ID'];
                $user['display_name'] = 'User ' . $user['ID'];
            }
        }
        
        return array_values($user_data);
    }
    
    /**
     * Export assessment data
     */
    private function export_assessment_data($options = array()) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'spiralengine_assessments';
        
        $query = "SELECT a.*, u.user_login, u.user_email 
                  FROM $table a
                  LEFT JOIN {$wpdb->users} u ON a.user_id = u.ID
                  WHERE 1=1";
        
        // Add filters
        if (!empty($options['user_id'])) {
            $query .= $wpdb->prepare(" AND a.user_id = %d", $options['user_id']);
        }
        
        if (!empty($options['assessment_type'])) {
            $query .= $wpdb->prepare(" AND a.assessment_type = %s", $options['assessment_type']);
        }
        
        if (!empty($options['date_from'])) {
            $query .= $wpdb->prepare(" AND a.created_at >= %s", $options['date_from']);
        }
        
        if (!empty($options['date_to'])) {
            $query .= $wpdb->prepare(" AND a.created_at <= %s", $options['date_to']);
        }
        
        $results = $wpdb->get_results($query, ARRAY_A);
        
        // Format data
        foreach ($results as &$row) {
            // Convert times to user timezone
            $row['created_at_display'] = $this->time_manager->format_user_time($row['created_at']);
            $row['completed_at_display'] = $row['completed_at'] ? $this->time_manager->format_user_time($row['completed_at']) : '';
            
            // Decode JSON fields
            $row['responses'] = json_decode($row['responses'], true);
            $row['results'] = json_decode($row['results'], true);
            
            // Handle privacy
            if (!empty($options['anonymize'])) {
                $row['user_login'] = 'user_' . $row['user_id'];
                $row['user_email'] = 'user' . $row['user_id'] . '@anonymized.com';
            }
        }
        
        return $results;
    }
    
    /**
     * Export episode data
     */
    private function export_episode_data($options = array()) {
        global $wpdb;
        
        $tables = array(
            'overthinking' => $wpdb->prefix . 'spiralengine_episodes_overthinking',
            'anxiety' => $wpdb->prefix . 'spiralengine_episodes_anxiety',
            'depression' => $wpdb->prefix . 'spiralengine_episodes_depression',
            'ptsd' => $wpdb->prefix . 'spiralengine_episodes_ptsd'
        );
        
        $all_episodes = array();
        
        foreach ($tables as $type => $table) {
            // Skip if specific type requested and this isn't it
            if (!empty($options['episode_type']) && $options['episode_type'] !== $type) {
                continue;
            }
            
            // Check if table exists
            if ($wpdb->get_var("SHOW TABLES LIKE '$table'") != $table) {
                continue;
            }
            
            $query = "SELECT e.*, u.user_login 
                      FROM $table e
                      LEFT JOIN {$wpdb->users} u ON e.user_id = u.ID
                      WHERE 1=1";
            
            // Add filters
            if (!empty($options['user_id'])) {
                $query .= $wpdb->prepare(" AND e.user_id = %d", $options['user_id']);
            }
            
            if (!empty($options['date_from'])) {
                $query .= $wpdb->prepare(" AND e.episode_date >= %s", $options['date_from']);
            }
            
            if (!empty($options['date_to'])) {
                $query .= $wpdb->prepare(" AND e.episode_date <= %s", $options['date_to']);
            }
            
            $episodes = $wpdb->get_results($query, ARRAY_A);
            
            foreach ($episodes as &$episode) {
                $episode['episode_type'] = $type;
                $episode['episode_datetime_display'] = $this->time_manager->format_user_time($episode['episode_date'] . ' ' . $episode['episode_time']);
                
                // Decode JSON fields
                $episode['triggers'] = json_decode($episode['triggers'], true);
                $episode['symptoms'] = json_decode($episode['symptoms'], true);
                $episode['coping_strategies'] = json_decode($episode['coping_strategies'], true);
                
                // Handle privacy
                if (!empty($options['anonymize'])) {
                    $episode['user_login'] = 'user_' . $episode['user_id'];
                    $episode['thoughts'] = '[REDACTED]';
                    $episode['notes'] = '[REDACTED]';
                }
                
                $all_episodes[] = $episode;
            }
        }
        
        // Sort by date
        usort($all_episodes, function($a, $b) {
            return strtotime($b['episode_date']) - strtotime($a['episode_date']);
        });
        
        return $all_episodes;
    }
    
    /**
     * Format export data
     */
    private function format_export_data($data, $format, $type) {
        switch ($format) {
            case 'csv':
                return $this->format_as_csv($data, $type);
                
            case 'json':
                return $this->format_as_json($data, $type);
                
            case 'xml':
                return $this->format_as_xml($data, $type);
                
            case 'xlsx':
                return $this->format_as_xlsx($data, $type);
                
            default:
                return $data;
        }
    }
    
    /**
     * Format as CSV
     */
    private function format_as_csv($data, $type) {
        if (empty($data)) {
            return '';
        }
        
        $output = '';
        $headers_written = false;
        
        foreach ($data as $row) {
            // Flatten nested data
            $flat_row = $this->flatten_array($row);
            
            // Write headers
            if (!$headers_written) {
                $output .= implode(',', array_map(array($this, 'csv_escape'), array_keys($flat_row))) . "\n";
                $headers_written = true;
            }
            
            // Write data
            $output .= implode(',', array_map(array($this, 'csv_escape'), array_values($flat_row))) . "\n";
        }
        
        return $output;
    }
    
    /**
     * CSV escape helper
     */
    private function csv_escape($value) {
        if (is_array($value) || is_object($value)) {
            $value = json_encode($value);
        }
        
        // Escape quotes and wrap in quotes if needed
        if (strpos($value, ',') !== false || strpos($value, '"') !== false || strpos($value, "\n") !== false) {
            $value = '"' . str_replace('"', '""', $value) . '"';
        }
        
        return $value;
    }
    
    /**
     * Format as JSON
     */
    private function format_as_json($data, $type) {
        $export_data = array(
            'type' => $type,
            'version' => SPIRALENGINE_VERSION,
            'exported_at' => current_time('mysql'),
            'exported_at_utc' => gmdate('Y-m-d H:i:s'),
            'record_count' => count($data),
            'data' => $data
        );
        
        return json_encode($export_data, JSON_PRETTY_PRINT);
    }
    
    /**
     * Format as XML
     */
    private function format_as_xml($data, $type) {
        $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><spiralengine></spiralengine>');
        
        // Add metadata
        $xml->addChild('type', $type);
        $xml->addChild('version', SPIRALENGINE_VERSION);
        $xml->addChild('exported_at', current_time('mysql'));
        $xml->addChild('record_count', count($data));
        
        // Add data
        $data_node = $xml->addChild('data');
        
        foreach ($data as $item) {
            $record = $data_node->addChild('record');
            $this->array_to_xml($item, $record);
        }
        
        return $xml->asXML();
    }
    
    /**
     * Convert array to XML
     */
    private function array_to_xml($data, &$xml) {
        foreach ($data as $key => $value) {
            if (is_numeric($key)) {
                $key = 'item_' . $key;
            }
            
            if (is_array($value)) {
                $subnode = $xml->addChild($key);
                $this->array_to_xml($value, $subnode);
            } else {
                $xml->addChild($key, htmlspecialchars($value));
            }
        }
    }
    
    /**
     * Create export file
     */
    private function create_export_file($data, $format, $type, $export_id) {
        $upload_dir = wp_upload_dir();
        $export_dir = $upload_dir['basedir'] . '/spiralengine-exports/';
        
        // Create directory if needed
        if (!wp_mkdir_p($export_dir)) {
            throw new Exception(__('Could not create export directory', 'spiralengine'));
        }
        
        // Generate filename
        $filename = sprintf(
            'spiralengine-%s-%s-%s.%s',
            $type,
            date('Y-m-d-His'),
            $export_id,
            $format
        );
        
        $file_path = $export_dir . $filename;
        
        // Write file
        if ($format === 'xlsx') {
            // Handle Excel format separately
            $this->create_xlsx_file($data, $file_path, $type);
        } else {
            file_put_contents($file_path, $data);
        }
        
        return $file_path;
    }
    
    /**
     * Process import
     */
    private function process_import($data, $type, $options, $import_id) {
        $result = array(
            'imported' => 0,
            'skipped' => 0,
            'errors' => array()
        );
        
        $total = count($data);
        $processed = 0;
        
        foreach ($data as $index => $item) {
            try {
                // Update progress
                $processed++;
                $progress = 50 + (40 * $processed / $total);
                $this->update_import_progress($import_id, $progress, sprintf('Processing record %d of %d', $processed, $total));
                
                // Process based on type
                switch ($type) {
                    case 'users':
                        $this->import_user($item, $options);
                        break;
                        
                    case 'assessments':
                        $this->import_assessment($item, $options);
                        break;
                        
                    case 'episodes':
                        $this->import_episode($item, $options);
                        break;
                        
                    case 'widgets':
                        $this->import_widget($item, $options);
                        break;
                        
                    case 'settings':
                        $this->import_setting($item, $options);
                        break;
                        
                    default:
                        do_action('spiralengine_import_item_' . $type, $item, $options);
                }
                
                $result['imported']++;
                
            } catch (Exception $e) {
                $result['errors'][] = sprintf(
                    __('Row %d: %s', 'spiralengine'),
                    $index + 1,
                    $e->getMessage()
                );
                $result['skipped']++;
            }
        }
        
        return $result;
    }
    
    /**
     * Import user
     */
    private function import_user($data, $options) {
        // Check if user exists
        $user = get_user_by('email', $data['email']);
        
        if ($user && empty($options['overwrite'])) {
            throw new Exception(__('User already exists', 'spiralengine'));
        }
        
        if (!$user) {
            // Create new user
            $user_data = array(
                'user_login' => $data['username'],
                'user_email' => $data['email'],
                'display_name' => $data['display_name'],
                'user_registered' => $data['registered_utc'] ?? current_time('mysql')
            );
            
            $user_id = wp_insert_user($user_data);
            
            if (is_wp_error($user_id)) {
                throw new Exception($user_id->get_error_message());
            }
        } else {
            $user_id = $user->ID;
        }
        
        // Update meta
        if (!empty($data['meta'])) {
            foreach ($data['meta'] as $key => $value) {
                update_user_meta($user_id, 'spiralengine_' . $key, $value);
            }
        }
    }
    
    /**
     * Validate import data
     */
    private function validate_import_data($data, $type) {
        $errors = array();
        
        if (empty($data)) {
            $errors[] = __('No data to import', 'spiralengine');
            return array('valid' => false, 'errors' => $errors);
        }
        
        // Type-specific validation
        switch ($type) {
            case 'users':
                foreach ($data as $index => $user) {
                    if (empty($user['email'])) {
                        $errors[] = sprintf(__('Row %d: Email is required', 'spiralengine'), $index + 1);
                    } elseif (!is_email($user['email'])) {
                        $errors[] = sprintf(__('Row %d: Invalid email format', 'spiralengine'), $index + 1);
                    }
                    
                    if (empty($user['username'])) {
                        $errors[] = sprintf(__('Row %d: Username is required', 'spiralengine'), $index + 1);
                    }
                }
                break;
                
            case 'assessments':
                foreach ($data as $index => $assessment) {
                    if (empty($assessment['user_id']) && empty($assessment['user_email'])) {
                        $errors[] = sprintf(__('Row %d: User identifier required', 'spiralengine'), $index + 1);
                    }
                    
                    if (empty($assessment['assessment_type'])) {
                        $errors[] = sprintf(__('Row %d: Assessment type required', 'spiralengine'), $index + 1);
                    }
                }
                break;
        }
        
        return array(
            'valid' => empty($errors),
            'errors' => $errors
        );
    }
    
    /**
     * AJAX handlers
     */
    public function ajax_export_data() {
        check_ajax_referer('spiralengine_export', 'nonce');
        
        if (!current_user_can('export')) {
            wp_die(__('Insufficient permissions', 'spiralengine'));
        }
        
        $type = sanitize_text_field($_POST['type']);
        $format = sanitize_text_field($_POST['format']);
        $options = $_POST['options'] ?? array();
        
        $result = $this->export_data($type, $format, $options);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        } else {
            wp_send_json_success($result);
        }
    }
    
    public function ajax_import_data() {
        check_ajax_referer('spiralengine_import', 'nonce');
        
        if (!current_user_can('import')) {
            wp_die(__('Insufficient permissions', 'spiralengine'));
        }
        
        if (empty($_FILES['import_file'])) {
            wp_send_json_error(__('No file uploaded', 'spiralengine'));
        }
        
        $file = $_FILES['import_file'];
        $type = sanitize_text_field($_POST['type']);
        $options = $_POST['options'] ?? array();
        
        // Validate file upload
        if ($file['error'] !== UPLOAD_ERR_OK) {
            wp_send_json_error(__('File upload failed', 'spiralengine'));
        }
        
        $result = $this->import_data($file['tmp_name'], $type, $options);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        } else {
            wp_send_json_success($result);
        }
    }
    
    public function ajax_validate_import() {
        check_ajax_referer('spiralengine_import', 'nonce');
        
        if (!current_user_can('import')) {
            wp_die(__('Insufficient permissions', 'spiralengine'));
        }
        
        if (empty($_FILES['import_file'])) {
            wp_send_json_error(__('No file uploaded', 'spiralengine'));
        }
        
        $file = $_FILES['import_file'];
        $type = sanitize_text_field($_POST['type']);
        
        // Detect format
        $format = $this->detect_file_format($file['tmp_name']);
        if (!$format) {
            wp_send_json_error(__('Unsupported file format', 'spiralengine'));
        }
        
        // Parse file
        try {
            $data = $this->parse_import_file($file['tmp_name'], $format);
            $validation = $this->validate_import_data($data, $type);
            
            wp_send_json_success(array(
                'valid' => $validation['valid'],
                'errors' => $validation['errors'],
                'record_count' => count($data),
                'format' => $format,
                'preview' => array_slice($data, 0, 5) // First 5 records
            ));
            
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * Helper methods
     */
    private function generate_export_id() {
        return wp_generate_password(12, false);
    }
    
    private function generate_import_id() {
        return wp_generate_password(12, false);
    }
    
    private function update_export_progress($export_id, $progress, $message = '') {
        set_transient('spiralengine_export_' . $export_id, array(
            'progress' => $progress,
            'message' => $message,
            'timestamp' => time()
        ), HOUR_IN_SECONDS);
    }
    
    private function update_import_progress($import_id, $progress, $message = '') {
        set_transient('spiralengine_import_' . $import_id, array(
            'progress' => $progress,
            'message' => $message,
            'timestamp' => time()
        ), HOUR_IN_SECONDS);
    }
    
    private function flatten_array($array, $prefix = '') {
        $result = array();
        
        foreach ($array as $key => $value) {
            $new_key = $prefix ? $prefix . '_' . $key : $key;
            
            if (is_array($value)) {
                $result = array_merge($result, $this->flatten_array($value, $new_key));
            } else {
                $result[$new_key] = $value;
            }
        }
        
        return $result;
    }
    
    private function detect_file_format($file_path) {
        $mime_type = mime_content_type($file_path);
        
        foreach ($this->import_formats as $format => $mime_types) {
            if (in_array($mime_type, $mime_types)) {
                return $format;
            }
        }
        
        // Try by extension
        $extension = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
        if (array_key_exists($extension, $this->import_formats)) {
            return $extension;
        }
        
        return false;
    }
    
    private function parse_import_file($file_path, $format) {
        switch ($format) {
            case 'csv':
                return $this->parse_csv_file($file_path);
                
            case 'json':
                return $this->parse_json_file($file_path);
                
            case 'xml':
                return $this->parse_xml_file($file_path);
                
            case 'xlsx':
                return $this->parse_xlsx_file($file_path);
                
            default:
                throw new Exception(__('Unsupported format', 'spiralengine'));
        }
    }
    
    private function parse_csv_file($file_path) {
        $data = array();
        $headers = array();
        $row_num = 0;
        
        if (($handle = fopen($file_path, 'r')) !== false) {
            while (($row = fgetcsv($handle, 0, ',')) !== false) {
                if ($row_num === 0) {
                    $headers = array_map('trim', $row);
                } else {
                    $data[] = array_combine($headers, array_map('trim', $row));
                }
                $row_num++;
            }
            fclose($handle);
        }
        
        return $data;
    }
    
    private function parse_json_file($file_path) {
        $content = file_get_contents($file_path);
        $data = json_decode($content, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception(__('Invalid JSON format', 'spiralengine'));
        }
        
        // Check if data is wrapped
        if (isset($data['data']) && is_array($data['data'])) {
            return $data['data'];
        }
        
        return $data;
    }
    
    private function get_export_download_url($export_id) {
        return admin_url('admin-ajax.php?action=spiralengine_download_export&export_id=' . $export_id . '&nonce=' . wp_create_nonce('spiralengine_export_download'));
    }
    
    private function log_export($export_id, $type, $format, $count) {
        global $wpdb;
        
        $wpdb->insert(
            $wpdb->prefix . 'spiralengine_export_log',
            array(
                'export_id' => $export_id,
                'type' => $type,
                'format' => $format,
                'record_count' => $count,
                'user_id' => get_current_user_id(),
                'created_at' => current_time('mysql')
            ),
            array('%s', '%s', '%s', '%d', '%d', '%s')
        );
    }
    
    private function log_import($import_id, $type, $format, $result) {
        global $wpdb;
        
        $wpdb->insert(
            $wpdb->prefix . 'spiralengine_import_log',
            array(
                'import_id' => $import_id,
                'type' => $type,
                'format' => $format,
                'imported' => $result['imported'],
                'skipped' => $result['skipped'],
                'errors' => json_encode($result['errors']),
                'user_id' => get_current_user_id(),
                'created_at' => current_time('mysql')
            ),
            array('%s', '%s', '%s', '%d', '%d', '%s', '%d', '%s')
        );
    }
}

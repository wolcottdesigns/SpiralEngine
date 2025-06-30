<?php
// includes/tools/class-spiralengine-backup.php

/**
 * Spiral Engine Backup System
 * 
 * Implements comprehensive backup and restore functionality with encryption,
 * scheduling, and multiple storage options.
 *
 * @package    Spiral_Engine
 * @subpackage Spiral_Engine/tools
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class SPIRALENGINE_Backup {
    
    /**
     * Backup configuration
     */
    private $config;
    
    /**
     * Time manager instance
     */
    private $time_manager;
    
    /**
     * Encryption instance
     */
    private $encryption;
    
    /**
     * Supported backup types
     */
    private $backup_types = array(
        'full' => 'Full System Backup',
        'database' => 'Database Only',
        'files' => 'Files Only',
        'config' => 'Configuration Only',
        'selective' => 'Selective Backup'
    );
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->time_manager = new SPIRALENGINE_Time_Zone_Manager();
        $this->encryption = new SPIRALENGINE_Flexible_Encryption();
        $this->load_config();
        $this->init_hooks();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Schedule backups
        add_action('spiralengine_scheduled_backup', array($this, 'run_scheduled_backup'));
        
        // Admin actions
        add_action('wp_ajax_spiralengine_manual_backup', array($this, 'ajax_manual_backup'));
        add_action('wp_ajax_spiralengine_restore_backup', array($this, 'ajax_restore_backup'));
        add_action('wp_ajax_spiralengine_delete_backup', array($this, 'ajax_delete_backup'));
        add_action('wp_ajax_spiralengine_verify_backup', array($this, 'ajax_verify_backup'));
        add_action('wp_ajax_spiralengine_download_backup', array($this, 'ajax_download_backup'));
        
        // Register cron schedules
        add_filter('cron_schedules', array($this, 'add_backup_schedules'));
    }
    
    /**
     * Load backup configuration
     */
    private function load_config() {
        $this->config = array(
            'backup_location' => get_option('spiralengine_backup_location', wp_upload_dir()['basedir'] . '/spiralengine-backups/'),
            'encryption_enabled' => get_option('spiralengine_backup_encryption', true),
            'compression_enabled' => get_option('spiralengine_backup_compression', true),
            'retention_days' => get_option('spiralengine_backup_retention', 30),
            'cloud_storage' => get_option('spiralengine_backup_cloud', array()),
            'schedule' => get_option('spiralengine_backup_schedule', array(
                'full' => array('frequency' => 'weekly', 'time' => '02:00'),
                'database' => array('frequency' => 'daily', 'time' => '02:00'),
                'files' => array('frequency' => 'daily', 'time' => '03:00')
            ))
        );
    }
    
    /**
     * Add custom cron schedules
     */
    public function add_backup_schedules($schedules) {
        $schedules['every_6_hours'] = array(
            'interval' => 6 * HOUR_IN_SECONDS,
            'display' => __('Every 6 hours', 'spiralengine')
        );
        
        $schedules['every_12_hours'] = array(
            'interval' => 12 * HOUR_IN_SECONDS,
            'display' => __('Every 12 hours', 'spiralengine')
        );
        
        return $schedules;
    }
    
    /**
     * Create backup
     */
    public function create_backup($type = 'full', $options = array()) {
        global $wpdb;
        
        // Initialize backup process
        $backup_id = $this->generate_backup_id();
        $backup_dir = $this->config['backup_location'] . $backup_id . '/';
        
        // Create backup directory
        if (!wp_mkdir_p($backup_dir)) {
            return new WP_Error('backup_dir_error', __('Could not create backup directory', 'spiralengine'));
        }
        
        // Start backup metadata
        $metadata = array(
            'id' => $backup_id,
            'type' => $type,
            'created_at' => current_time('mysql'),
            'created_at_utc' => gmdate('Y-m-d H:i:s'),
            'user_id' => get_current_user_id(),
            'wordpress_version' => get_bloginfo('version'),
            'plugin_version' => SPIRALENGINE_VERSION,
            'site_url' => get_site_url(),
            'components' => array()
        );
        
        try {
            // Backup based on type
            switch ($type) {
                case 'full':
                    $this->backup_database($backup_dir, $metadata);
                    $this->backup_files($backup_dir, $metadata);
                    $this->backup_config($backup_dir, $metadata);
                    break;
                    
                case 'database':
                    $this->backup_database($backup_dir, $metadata);
                    break;
                    
                case 'files':
                    $this->backup_files($backup_dir, $metadata);
                    break;
                    
                case 'config':
                    $this->backup_config($backup_dir, $metadata);
                    break;
                    
                case 'selective':
                    $this->backup_selective($backup_dir, $metadata, $options);
                    break;
            }
            
            // Create manifest file
            $this->create_manifest($backup_dir, $metadata);
            
            // Compress backup if enabled
            if ($this->config['compression_enabled']) {
                $this->compress_backup($backup_dir, $backup_id);
            }
            
            // Encrypt backup if enabled
            if ($this->config['encryption_enabled']) {
                $this->encrypt_backup($backup_dir, $backup_id);
            }
            
            // Upload to cloud storage if configured
            if (!empty($this->config['cloud_storage'])) {
                $this->upload_to_cloud($backup_id);
            }
            
            // Log successful backup
            $this->log_backup($backup_id, 'success', $metadata);
            
            // Clean old backups
            $this->cleanup_old_backups();
            
            return array(
                'success' => true,
                'backup_id' => $backup_id,
                'metadata' => $metadata,
                'size' => $this->get_backup_size($backup_id)
            );
            
        } catch (Exception $e) {
            // Log error
            $this->log_backup($backup_id, 'error', array('message' => $e->getMessage()));
            
            // Clean up failed backup
            $this->delete_backup($backup_id);
            
            return new WP_Error('backup_error', $e->getMessage());
        }
    }
    
    /**
     * Backup database
     */
    private function backup_database($backup_dir, &$metadata) {
        global $wpdb;
        
        $tables_file = $backup_dir . 'database.sql';
        $handle = fopen($tables_file, 'w');
        
        if (!$handle) {
            throw new Exception(__('Could not create database backup file', 'spiralengine'));
        }
        
        // Write header
        fwrite($handle, "-- SPIRAL Engine Database Backup\n");
        fwrite($handle, "-- Generated: " . current_time('mysql') . "\n");
        fwrite($handle, "-- --------------------------------------------------------\n\n");
        
        // Get all tables
        $tables = $wpdb->get_results("SHOW TABLES", ARRAY_N);
        
        foreach ($tables as $table) {
            $table_name = $table[0];
            
            // Skip non-WordPress tables unless they're ours
            if (strpos($table_name, $wpdb->prefix) !== 0 && strpos($table_name, 'spiralengine_') === false) {
                continue;
            }
            
            // Table structure
            $create_table = $wpdb->get_row("SHOW CREATE TABLE `$table_name`", ARRAY_N);
            fwrite($handle, "\n-- Table structure for table `$table_name`\n");
            fwrite($handle, "DROP TABLE IF EXISTS `$table_name`;\n");
            fwrite($handle, $create_table[1] . ";\n\n");
            
            // Table data
            $rows = $wpdb->get_results("SELECT * FROM `$table_name`", ARRAY_A);
            
            if (!empty($rows)) {
                fwrite($handle, "-- Data for table `$table_name`\n");
                
                foreach ($rows as $row) {
                    $values = array();
                    foreach ($row as $value) {
                        $values[] = is_null($value) ? 'NULL' : "'" . $wpdb->_real_escape($value) . "'";
                    }
                    
                    fwrite($handle, "INSERT INTO `$table_name` VALUES (" . implode(',', $values) . ");\n");
                }
            }
        }
        
        fclose($handle);
        
        $metadata['components']['database'] = array(
            'file' => 'database.sql',
            'tables_count' => count($tables),
            'size' => filesize($tables_file)
        );
    }
    
    /**
     * Backup files
     */
    private function backup_files($backup_dir, &$metadata) {
        $upload_dir = wp_upload_dir();
        $files_to_backup = array(
            'uploads/spiralengine' => $upload_dir['basedir'] . '/spiralengine',
            'plugin' => SPIRALENGINE_PLUGIN_DIR
        );
        
        foreach ($files_to_backup as $key => $source) {
            if (!file_exists($source)) {
                continue;
            }
            
            $destination = $backup_dir . 'files/' . $key;
            wp_mkdir_p(dirname($destination));
            
            $this->copy_directory($source, $destination);
            
            $metadata['components']['files'][$key] = array(
                'source' => $source,
                'destination' => 'files/' . $key,
                'count' => $this->count_files($source)
            );
        }
    }
    
    /**
     * Backup configuration
     */
    private function backup_config($backup_dir, &$metadata) {
        $config_data = array(
            'options' => $this->get_spiralengine_options(),
            'settings' => $this->get_all_settings(),
            'widgets' => $this->get_widget_configurations(),
            'user_meta' => $this->get_user_meta_keys()
        );
        
        $config_file = $backup_dir . 'config.json';
        file_put_contents($config_file, json_encode($config_data, JSON_PRETTY_PRINT));
        
        $metadata['components']['config'] = array(
            'file' => 'config.json',
            'options_count' => count($config_data['options']),
            'size' => filesize($config_file)
        );
    }
    
    /**
     * Selective backup
     */
    private function backup_selective($backup_dir, &$metadata, $options) {
        if (!empty($options['user_data']) && $options['user_data']) {
            $this->backup_user_data($backup_dir, $metadata);
        }
        
        if (!empty($options['widget_configs']) && $options['widget_configs']) {
            $this->backup_widget_configs($backup_dir, $metadata);
        }
        
        if (!empty($options['ai_data']) && $options['ai_data']) {
            $this->backup_ai_data($backup_dir, $metadata);
        }
        
        if (!empty($options['analytics']) && $options['analytics']) {
            $this->backup_analytics($backup_dir, $metadata);
        }
    }
    
    /**
     * Restore backup
     */
    public function restore_backup($backup_id, $options = array()) {
        global $wpdb;
        
        // Verify backup exists
        $backup_path = $this->get_backup_path($backup_id);
        if (!$backup_path) {
            return new WP_Error('backup_not_found', __('Backup not found', 'spiralengine'));
        }
        
        // Read manifest
        $manifest = $this->read_manifest($backup_id);
        if (!$manifest) {
            return new WP_Error('manifest_error', __('Could not read backup manifest', 'spiralengine'));
        }
        
        // Create restore point
        $restore_point = $this->create_restore_point();
        
        try {
            // Begin transaction
            $wpdb->query('START TRANSACTION');
            
            // Decrypt if needed
            if ($this->is_encrypted($backup_id)) {
                $this->decrypt_backup($backup_id);
            }
            
            // Decompress if needed
            if ($this->is_compressed($backup_id)) {
                $this->decompress_backup($backup_id);
            }
            
            // Restore components
            if (empty($options) || in_array('database', $options)) {
                $this->restore_database($backup_id);
            }
            
            if (empty($options) || in_array('files', $options)) {
                $this->restore_files($backup_id);
            }
            
            if (empty($options) || in_array('config', $options)) {
                $this->restore_config($backup_id);
            }
            
            // Commit transaction
            $wpdb->query('COMMIT');
            
            // Clear caches
            $this->clear_all_caches();
            
            // Log successful restore
            $this->log_restore($backup_id, 'success');
            
            return array(
                'success' => true,
                'backup_id' => $backup_id,
                'restore_point' => $restore_point,
                'message' => __('Backup restored successfully', 'spiralengine')
            );
            
        } catch (Exception $e) {
            // Rollback transaction
            $wpdb->query('ROLLBACK');
            
            // Log error
            $this->log_restore($backup_id, 'error', array('message' => $e->getMessage()));
            
            return new WP_Error('restore_error', $e->getMessage());
        }
    }
    
    /**
     * Get backup list
     */
    public function get_backups($type = null, $limit = 50) {
        $backups = array();
        $backup_dir = $this->config['backup_location'];
        
        if (!is_dir($backup_dir)) {
            return $backups;
        }
        
        $files = scandir($backup_dir, SCANDIR_SORT_DESCENDING);
        $count = 0;
        
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            
            $manifest = $this->read_manifest($file);
            if (!$manifest) {
                continue;
            }
            
            // Filter by type if specified
            if ($type && $manifest['type'] !== $type) {
                continue;
            }
            
            // Format times for user
            $manifest['created_at_display'] = $this->time_manager->format_user_time($manifest['created_at_utc']);
            $manifest['size'] = $this->get_backup_size($file);
            $manifest['size_display'] = size_format($manifest['size']);
            
            $backups[] = $manifest;
            
            if (++$count >= $limit) {
                break;
            }
        }
        
        return $backups;
    }
    
    /**
     * Delete backup
     */
    public function delete_backup($backup_id) {
        $backup_path = $this->get_backup_path($backup_id);
        
        if (!$backup_path) {
            return false;
        }
        
        // Delete from cloud if exists
        if ($this->exists_in_cloud($backup_id)) {
            $this->delete_from_cloud($backup_id);
        }
        
        // Delete local files
        $this->delete_directory($backup_path);
        
        // Log deletion
        $this->log_backup($backup_id, 'deleted');
        
        return true;
    }
    
    /**
     * Verify backup integrity
     */
    public function verify_backup($backup_id) {
        $manifest = $this->read_manifest($backup_id);
        
        if (!$manifest) {
            return array(
                'valid' => false,
                'error' => __('Manifest not found', 'spiralengine')
            );
        }
        
        $errors = array();
        $backup_path = $this->get_backup_path($backup_id);
        
        // Verify components
        foreach ($manifest['components'] as $component => $data) {
            if (isset($data['file'])) {
                $file_path = $backup_path . '/' . $data['file'];
                if (!file_exists($file_path)) {
                    $errors[] = sprintf(__('Missing file: %s', 'spiralengine'), $data['file']);
                }
            }
        }
        
        // Verify checksums if available
        if (isset($manifest['checksums'])) {
            foreach ($manifest['checksums'] as $file => $checksum) {
                $file_path = $backup_path . '/' . $file;
                if (file_exists($file_path) && md5_file($file_path) !== $checksum) {
                    $errors[] = sprintf(__('Checksum mismatch: %s', 'spiralengine'), $file);
                }
            }
        }
        
        return array(
            'valid' => empty($errors),
            'errors' => $errors,
            'backup_id' => $backup_id
        );
    }
    
    /**
     * Schedule automatic backups
     */
    public function schedule_backups() {
        foreach ($this->config['schedule'] as $type => $schedule) {
            $hook = 'spiralengine_scheduled_backup_' . $type;
            
            // Clear existing schedule
            wp_clear_scheduled_hook($hook);
            
            // Schedule new
            if ($schedule['frequency'] !== 'disabled') {
                $timestamp = $this->get_next_scheduled_time($schedule['time'], $schedule['frequency']);
                wp_schedule_event($timestamp, $schedule['frequency'], $hook);
            }
        }
    }
    
    /**
     * Run scheduled backup
     */
    public function run_scheduled_backup($type = 'full') {
        // Extract type from current filter if not provided
        if (strpos(current_filter(), 'spiralengine_scheduled_backup_') === 0) {
            $type = str_replace('spiralengine_scheduled_backup_', '', current_filter());
        }
        
        // Create backup
        $result = $this->create_backup($type);
        
        // Send notification if enabled
        if (!is_wp_error($result) && get_option('spiralengine_backup_notifications', true)) {
            $this->send_backup_notification($result);
        }
        
        return $result;
    }
    
    /**
     * AJAX handlers
     */
    public function ajax_manual_backup() {
        check_ajax_referer('spiralengine_backup', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'spiralengine'));
        }
        
        $type = sanitize_text_field($_POST['type'] ?? 'full');
        $options = $_POST['options'] ?? array();
        
        $result = $this->create_backup($type, $options);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        } else {
            wp_send_json_success($result);
        }
    }
    
    public function ajax_restore_backup() {
        check_ajax_referer('spiralengine_backup', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'spiralengine'));
        }
        
        $backup_id = sanitize_text_field($_POST['backup_id']);
        $options = $_POST['options'] ?? array();
        
        $result = $this->restore_backup($backup_id, $options);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        } else {
            wp_send_json_success($result);
        }
    }
    
    public function ajax_delete_backup() {
        check_ajax_referer('spiralengine_backup', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'spiralengine'));
        }
        
        $backup_id = sanitize_text_field($_POST['backup_id']);
        
        if ($this->delete_backup($backup_id)) {
            wp_send_json_success(__('Backup deleted successfully', 'spiralengine'));
        } else {
            wp_send_json_error(__('Could not delete backup', 'spiralengine'));
        }
    }
    
    public function ajax_verify_backup() {
        check_ajax_referer('spiralengine_backup', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'spiralengine'));
        }
        
        $backup_id = sanitize_text_field($_POST['backup_id']);
        $result = $this->verify_backup($backup_id);
        
        wp_send_json_success($result);
    }
    
    public function ajax_download_backup() {
        check_ajax_referer('spiralengine_backup', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'spiralengine'));
        }
        
        $backup_id = sanitize_text_field($_GET['backup_id']);
        $backup_path = $this->get_backup_path($backup_id);
        
        if (!$backup_path) {
            wp_die(__('Backup not found', 'spiralengine'));
        }
        
        // Create zip for download
        $zip_path = $this->create_download_zip($backup_id);
        
        if (!$zip_path) {
            wp_die(__('Could not create download file', 'spiralengine'));
        }
        
        // Send file
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="spiralengine-backup-' . $backup_id . '.zip"');
        header('Content-Length: ' . filesize($zip_path));
        readfile($zip_path);
        
        // Clean up
        unlink($zip_path);
        exit;
    }
    
    /**
     * Helper methods
     */
    private function generate_backup_id() {
        return date('Y-m-d-His') . '-' . wp_generate_password(8, false);
    }
    
    private function get_backup_path($backup_id) {
        $path = $this->config['backup_location'] . $backup_id;
        return is_dir($path) ? $path : false;
    }
    
    private function get_backup_size($backup_id) {
        $path = $this->get_backup_path($backup_id);
        return $path ? $this->get_directory_size($path) : 0;
    }
    
    private function get_directory_size($path) {
        $size = 0;
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS)
        );
        
        foreach ($files as $file) {
            $size += $file->getSize();
        }
        
        return $size;
    }
    
    private function copy_directory($source, $destination) {
        if (!is_dir($destination)) {
            wp_mkdir_p($destination);
        }
        
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        
        foreach ($iterator as $item) {
            $dest_path = $destination . '/' . $iterator->getSubPathName();
            
            if ($item->isDir()) {
                wp_mkdir_p($dest_path);
            } else {
                copy($item, $dest_path);
            }
        }
    }
    
    private function delete_directory($path) {
        if (!is_dir($path)) {
            return;
        }
        
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        
        foreach ($files as $file) {
            if ($file->isDir()) {
                rmdir($file->getRealPath());
            } else {
                unlink($file->getRealPath());
            }
        }
        
        rmdir($path);
    }
    
    private function count_files($path) {
        if (!is_dir($path)) {
            return 0;
        }
        
        $count = 0;
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS)
        );
        
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $count++;
            }
        }
        
        return $count;
    }
    
    private function create_manifest($backup_dir, $metadata) {
        $manifest_file = $backup_dir . 'manifest.json';
        
        // Add checksums
        $metadata['checksums'] = array();
        foreach ($metadata['components'] as $component => $data) {
            if (isset($data['file'])) {
                $file_path = $backup_dir . $data['file'];
                if (file_exists($file_path)) {
                    $metadata['checksums'][$data['file']] = md5_file($file_path);
                }
            }
        }
        
        file_put_contents($manifest_file, json_encode($metadata, JSON_PRETTY_PRINT));
    }
    
    private function read_manifest($backup_id) {
        $manifest_file = $this->get_backup_path($backup_id) . '/manifest.json';
        
        if (!file_exists($manifest_file)) {
            return false;
        }
        
        return json_decode(file_get_contents($manifest_file), true);
    }
    
    private function get_spiralengine_options() {
        global $wpdb;
        
        $options = $wpdb->get_results(
            "SELECT option_name, option_value 
             FROM {$wpdb->options} 
             WHERE option_name LIKE 'spiralengine_%'",
            ARRAY_A
        );
        
        $result = array();
        foreach ($options as $option) {
            $result[$option['option_name']] = maybe_unserialize($option['option_value']);
        }
        
        return $result;
    }
    
    private function cleanup_old_backups() {
        $retention_days = $this->config['retention_days'];
        if (!$retention_days) {
            return;
        }
        
        $cutoff_date = strtotime("-{$retention_days} days");
        $backups = $this->get_backups();
        
        foreach ($backups as $backup) {
            $backup_date = strtotime($backup['created_at_utc']);
            if ($backup_date < $cutoff_date) {
                $this->delete_backup($backup['id']);
            }
        }
    }
    
    private function log_backup($backup_id, $status, $data = array()) {
        global $wpdb;
        
        $wpdb->insert(
            $wpdb->prefix . 'spiralengine_backup_log',
            array(
                'backup_id' => $backup_id,
                'status' => $status,
                'data' => json_encode($data),
                'created_at' => current_time('mysql')
            ),
            array('%s', '%s', '%s', '%s')
        );
    }
}

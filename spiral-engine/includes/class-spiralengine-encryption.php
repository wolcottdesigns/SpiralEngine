<?php
// includes/class-spiralengine-encryption.php

/**
 * SPIRAL Engine Encryption Class
 * 
 * Implements encryption from Security Command Center:
 * - Data encryption methods
 * - Key management
 * - Sensitive field protection
 */
class SpiralEngine_Encryption {
    
    /**
     * Instance of this class
     */
    private static $instance = null;
    
    /**
     * Encryption method
     */
    private $cipher_method = 'AES-256-CBC';
    
    /**
     * Fields that should be encrypted
     */
    private $encrypted_fields = array(
        'assessment_responses',
        'emergency_contact',
        'therapist_info',
        'member_notes',
        'health_data',
        'crisis_notes',
        'ai_conversation_history',
        'personal_journal_entries'
    );
    
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
        $this->init_hooks();
        $this->ensure_keys_exist();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Encrypt data before saving
        add_filter('spiralengine_before_save_user_meta', array($this, 'encrypt_user_meta'), 10, 3);
        add_filter('spiralengine_before_save_assessment', array($this, 'encrypt_assessment_data'), 10, 2);
        
        // Decrypt data after retrieving
        add_filter('spiralengine_after_get_user_meta', array($this, 'decrypt_user_meta'), 10, 3);
        add_filter('spiralengine_after_get_assessment', array($this, 'decrypt_assessment_data'), 10, 2);
        
        // Database encryption for sensitive tables
        add_action('spiralengine_create_tables', array($this, 'add_encryption_columns'));
        
        // Key rotation
        add_action('spiralengine_monthly_cron', array($this, 'check_key_rotation'));
        
        // Admin notices
        add_action('admin_notices', array($this, 'encryption_status_notice'));
    }
    
    /**
     * Ensure encryption keys exist
     */
    private function ensure_keys_exist() {
        // Check for master key
        if (!defined('SPIRALENGINE_ENCRYPTION_KEY')) {
            $this->generate_master_key();
        }
        
        // Check for data key
        $data_key = get_option('spiralengine_data_encryption_key');
        if (!$data_key) {
            $this->generate_data_key();
        }
    }
    
    /**
     * Generate master key (should be in wp-config.php)
     */
    private function generate_master_key() {
        // This should ideally be set in wp-config.php
        if (!defined('SPIRALENGINE_ENCRYPTION_KEY')) {
            // Generate a key and show admin notice
            $key = base64_encode(openssl_random_pseudo_bytes(32));
            set_transient('spiralengine_show_encryption_setup', $key, HOUR_IN_SECONDS);
        }
    }
    
    /**
     * Generate data encryption key
     */
    private function generate_data_key() {
        $key = openssl_random_pseudo_bytes(32);
        $encrypted_key = $this->encrypt_with_master_key($key);
        
        update_option('spiralengine_data_encryption_key', $encrypted_key);
        update_option('spiralengine_key_generated', current_time('mysql'));
        update_option('spiralengine_key_version', 1);
        
        // Log key generation
        $this->log_encryption_event('data_key_generated', array(
            'version' => 1,
            'timestamp' => current_time('mysql')
        ));
    }
    
    /**
     * Get data encryption key
     */
    private function get_data_key() {
        $encrypted_key = get_option('spiralengine_data_encryption_key');
        
        if (!$encrypted_key) {
            $this->generate_data_key();
            $encrypted_key = get_option('spiralengine_data_encryption_key');
        }
        
        return $this->decrypt_with_master_key($encrypted_key);
    }
    
    /**
     * Encrypt data
     */
    public function encrypt($data) {
        if (empty($data)) {
            return $data;
        }
        
        $key = $this->get_data_key();
        
        if (!$key) {
            return $data; // Fallback if encryption not available
        }
        
        // Generate IV
        $iv_length = openssl_cipher_iv_length($this->cipher_method);
        $iv = openssl_random_pseudo_bytes($iv_length);
        
        // Encrypt
        $encrypted = openssl_encrypt(
            serialize($data),
            $this->cipher_method,
            $key,
            OPENSSL_RAW_DATA,
            $iv
        );
        
        if ($encrypted === false) {
            $this->log_encryption_error('encryption_failed', openssl_error_string());
            return $data;
        }
        
        // Combine IV and encrypted data
        $combined = base64_encode($iv . $encrypted);
        
        // Add HMAC for integrity
        $hmac = $this->generate_hmac($combined, $key);
        
        return json_encode(array(
            'data' => $combined,
            'hmac' => $hmac,
            'version' => get_option('spiralengine_key_version', 1)
        ));
    }
    
    /**
     * Decrypt data
     */
    public function decrypt($encrypted_data) {
        if (empty($encrypted_data) || !is_string($encrypted_data)) {
            return $encrypted_data;
        }
        
        // Try to decode JSON
        $encrypted_array = json_decode($encrypted_data, true);
        
        if (!$encrypted_array || !isset($encrypted_array['data'])) {
            return $encrypted_data; // Not encrypted
        }
        
        // Verify HMAC
        $key = $this->get_data_key();
        
        if (!$this->verify_hmac($encrypted_array['data'], $encrypted_array['hmac'], $key)) {
            $this->log_encryption_error('hmac_verification_failed', array(
                'data_sample' => substr($encrypted_array['data'], 0, 20) . '...'
            ));
            return false;
        }
        
        // Decode data
        $combined = base64_decode($encrypted_array['data']);
        
        // Extract IV and encrypted data
        $iv_length = openssl_cipher_iv_length($this->cipher_method);
        $iv = substr($combined, 0, $iv_length);
        $encrypted = substr($combined, $iv_length);
        
        // Decrypt
        $decrypted = openssl_decrypt(
            $encrypted,
            $this->cipher_method,
            $key,
            OPENSSL_RAW_DATA,
            $iv
        );
        
        if ($decrypted === false) {
            $this->log_encryption_error('decryption_failed', openssl_error_string());
            return false;
        }
        
        return unserialize($decrypted);
    }
    
    /**
     * Encrypt with master key
     */
    private function encrypt_with_master_key($data) {
        if (!defined('SPIRALENGINE_ENCRYPTION_KEY')) {
            return base64_encode($data); // Fallback
        }
        
        $key = base64_decode(SPIRALENGINE_ENCRYPTION_KEY);
        $iv_length = openssl_cipher_iv_length($this->cipher_method);
        $iv = openssl_random_pseudo_bytes($iv_length);
        
        $encrypted = openssl_encrypt(
            $data,
            $this->cipher_method,
            $key,
            OPENSSL_RAW_DATA,
            $iv
        );
        
        return base64_encode($iv . $encrypted);
    }
    
    /**
     * Decrypt with master key
     */
    private function decrypt_with_master_key($encrypted_data) {
        if (!defined('SPIRALENGINE_ENCRYPTION_KEY')) {
            return base64_decode($encrypted_data); // Fallback
        }
        
        $key = base64_decode(SPIRALENGINE_ENCRYPTION_KEY);
        $combined = base64_decode($encrypted_data);
        
        $iv_length = openssl_cipher_iv_length($this->cipher_method);
        $iv = substr($combined, 0, $iv_length);
        $encrypted = substr($combined, $iv_length);
        
        return openssl_decrypt(
            $encrypted,
            $this->cipher_method,
            $key,
            OPENSSL_RAW_DATA,
            $iv
        );
    }
    
    /**
     * Generate HMAC
     */
    private function generate_hmac($data, $key) {
        return hash_hmac('sha256', $data, $key);
    }
    
    /**
     * Verify HMAC
     */
    private function verify_hmac($data, $hmac, $key) {
        $calculated_hmac = $this->generate_hmac($data, $key);
        return hash_equals($calculated_hmac, $hmac);
    }
    
    /**
     * Encrypt user meta
     */
    public function encrypt_user_meta($value, $key, $user_id) {
        if ($this->should_encrypt_field($key)) {
            return $this->encrypt($value);
        }
        return $value;
    }
    
    /**
     * Decrypt user meta
     */
    public function decrypt_user_meta($value, $key, $user_id) {
        if ($this->should_encrypt_field($key)) {
            $decrypted = $this->decrypt($value);
            return $decrypted !== false ? $decrypted : $value;
        }
        return $value;
    }
    
    /**
     * Encrypt assessment data
     */
    public function encrypt_assessment_data($data, $user_id) {
        if (isset($data['responses'])) {
            $data['responses'] = $this->encrypt($data['responses']);
        }
        if (isset($data['metadata'])) {
            $data['metadata'] = $this->encrypt($data['metadata']);
        }
        return $data;
    }
    
    /**
     * Decrypt assessment data
     */
    public function decrypt_assessment_data($data, $user_id) {
        if (isset($data['responses']) && is_string($data['responses'])) {
            $decrypted = $this->decrypt($data['responses']);
            if ($decrypted !== false) {
                $data['responses'] = $decrypted;
            }
        }
        if (isset($data['metadata']) && is_string($data['metadata'])) {
            $decrypted = $this->decrypt($data['metadata']);
            if ($decrypted !== false) {
                $data['metadata'] = $decrypted;
            }
        }
        return $data;
    }
    
    /**
     * Check if field should be encrypted
     */
    private function should_encrypt_field($field_key) {
        // Check if encryption is enabled
        if (!get_option('spiralengine_encryption_enabled', true)) {
            return false;
        }
        
        // Check if field is in encrypted fields list
        foreach ($this->encrypted_fields as $encrypted_field) {
            if (strpos($field_key, $encrypted_field) !== false) {
                return true;
            }
        }
        
        // Check custom encrypted fields
        $custom_fields = get_option('spiralengine_custom_encrypted_fields', array());
        return in_array($field_key, $custom_fields);
    }
    
    /**
     * Check key rotation
     */
    public function check_key_rotation() {
        $last_rotation = get_option('spiralengine_last_key_rotation');
        $rotation_interval = get_option('spiralengine_key_rotation_days', 90);
        
        if (!$last_rotation) {
            update_option('spiralengine_last_key_rotation', current_time('mysql'));
            return;
        }
        
        $days_since_rotation = (time() - strtotime($last_rotation)) / DAY_IN_SECONDS;
        
        if ($days_since_rotation >= $rotation_interval) {
            $this->rotate_encryption_keys();
        }
    }
    
    /**
     * Rotate encryption keys
     */
    public function rotate_encryption_keys() {
        // This is a complex operation that should be done carefully
        // 1. Generate new key
        // 2. Re-encrypt all data with new key
        // 3. Keep old key for decryption during transition
        // 4. Remove old key after all data is re-encrypted
        
        $this->log_encryption_event('key_rotation_started', array(
            'timestamp' => current_time('mysql')
        ));
        
        // Schedule batch re-encryption
        wp_schedule_single_event(time() + 60, 'spiralengine_batch_reencrypt');
        
        update_option('spiralengine_key_rotation_in_progress', true);
    }
    
    /**
     * Encrypt file
     */
    public function encrypt_file($file_path) {
        if (!file_exists($file_path)) {
            return false;
        }
        
        $content = file_get_contents($file_path);
        if ($content === false) {
            return false;
        }
        
        $encrypted = $this->encrypt($content);
        
        $encrypted_path = $file_path . '.enc';
        if (file_put_contents($encrypted_path, $encrypted) !== false) {
            unlink($file_path); // Remove unencrypted file
            return $encrypted_path;
        }
        
        return false;
    }
    
    /**
     * Decrypt file
     */
    public function decrypt_file($encrypted_path) {
        if (!file_exists($encrypted_path)) {
            return false;
        }
        
        $encrypted_content = file_get_contents($encrypted_path);
        if ($encrypted_content === false) {
            return false;
        }
        
        $decrypted = $this->decrypt($encrypted_content);
        if ($decrypted === false) {
            return false;
        }
        
        $decrypted_path = str_replace('.enc', '', $encrypted_path);
        if (file_put_contents($decrypted_path, $decrypted) !== false) {
            return $decrypted_path;
        }
        
        return false;
    }
    
    /**
     * Encrypt database field
     */
    public function encrypt_db_field($table, $column, $where_column, $where_value) {
        global $wpdb;
        
        $value = $wpdb->get_var($wpdb->prepare(
            "SELECT {$column} FROM {$table} WHERE {$where_column} = %s",
            $where_value
        ));
        
        if ($value) {
            $encrypted = $this->encrypt($value);
            $wpdb->update(
                $table,
                array($column => $encrypted),
                array($where_column => $where_value)
            );
        }
    }
    
    /**
     * Get encryption status
     */
    public function get_encryption_status() {
        $status = array(
            'enabled' => get_option('spiralengine_encryption_enabled', true),
            'master_key_set' => defined('SPIRALENGINE_ENCRYPTION_KEY'),
            'data_key_exists' => get_option('spiralengine_data_encryption_key') !== false,
            'key_version' => get_option('spiralengine_key_version', 0),
            'last_rotation' => get_option('spiralengine_last_key_rotation', 'Never'),
            'rotation_in_progress' => get_option('spiralengine_key_rotation_in_progress', false),
            'encrypted_fields_count' => count($this->encrypted_fields),
            'cipher_method' => $this->cipher_method,
            'openssl_available' => function_exists('openssl_encrypt')
        );
        
        // Calculate health score
        $health_score = 0;
        if ($status['enabled']) $health_score += 25;
        if ($status['master_key_set']) $health_score += 25;
        if ($status['data_key_exists']) $health_score += 25;
        if ($status['openssl_available']) $health_score += 25;
        
        $status['health_score'] = $health_score;
        
        return $status;
    }
    
    /**
     * Encryption status notice
     */
    public function encryption_status_notice() {
        // Check if we need to show setup notice
        $setup_key = get_transient('spiralengine_show_encryption_setup');
        
        if ($setup_key && current_user_can('manage_options')) {
            ?>
            <div class="notice notice-warning is-dismissible">
                <p><strong><?php _e('SPIRAL Engine Encryption Setup Required', 'spiral-engine'); ?></strong></p>
                <p><?php _e('Please add the following line to your wp-config.php file:', 'spiral-engine'); ?></p>
                <code>define('SPIRALENGINE_ENCRYPTION_KEY', '<?php echo esc_html($setup_key); ?>');</code>
                <p><?php _e('This key is essential for data encryption. Keep it safe and never share it.', 'spiral-engine'); ?></p>
            </div>
            <?php
        }
        
        // Check encryption health
        $status = $this->get_encryption_status();
        
        if ($status['health_score'] < 100 && current_user_can('manage_options')) {
            $screen = get_current_screen();
            if ($screen && strpos($screen->id, 'spiralengine') !== false) {
                ?>
                <div class="notice notice-info">
                    <p>
                        <?php printf(
                            __('Encryption Health: %d%% - %s', 'spiral-engine'),
                            $status['health_score'],
                            $status['health_score'] === 100 ? __('Optimal', 'spiral-engine') : __('Needs attention', 'spiral-engine')
                        ); ?>
                        <a href="<?php echo admin_url('admin.php?page=spiralengine-security#encryption'); ?>">
                            <?php _e('View Details', 'spiral-engine'); ?>
                        </a>
                    </p>
                </div>
                <?php
            }
        }
    }
    
    /**
     * Log encryption event
     */
    private function log_encryption_event($event_type, $details = array()) {
        if (!get_option('spiralengine_log_encryption_events', true)) {
            return;
        }
        
        global $wpdb;
        
        $wpdb->insert(
            $wpdb->prefix . 'spiralengine_encryption_log',
            array(
                'event_type' => $event_type,
                'details' => json_encode($details),
                'user_id' => get_current_user_id(),
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
                'timestamp' => current_time('mysql')
            )
        );
    }
    
    /**
     * Log encryption error
     */
    private function log_encryption_error($error_type, $error_details) {
        if (WP_DEBUG) {
            error_log('SPIRAL Engine Encryption Error: ' . $error_type . ' - ' . print_r($error_details, true));
        }
        
        $this->log_encryption_event('encryption_error', array(
            'error_type' => $error_type,
            'details' => $error_details
        ));
    }
    
    /**
     * Test encryption
     */
    public function test_encryption() {
        $test_data = array(
            'test' => 'data',
            'timestamp' => current_time('mysql'),
            'random' => wp_generate_password(32)
        );
        
        $encrypted = $this->encrypt($test_data);
        
        if (!$encrypted || $encrypted === $test_data) {
            return array(
                'success' => false,
                'error' => 'Encryption failed'
            );
        }
        
        $decrypted = $this->decrypt($encrypted);
        
        if ($decrypted !== $test_data) {
            return array(
                'success' => false,
                'error' => 'Decryption failed or data mismatch'
            );
        }
        
        return array(
            'success' => true,
            'encrypted_length' => strlen($encrypted),
            'original_length' => strlen(serialize($test_data))
        );
    }
    
    /**
     * Export encryption keys (for backup)
     */
    public function export_keys($password) {
        if (!current_user_can('manage_options')) {
            return false;
        }
        
        $keys = array(
            'data_key' => get_option('spiralengine_data_encryption_key'),
            'key_version' => get_option('spiralengine_key_version'),
            'exported_at' => current_time('mysql'),
            'site_url' => home_url()
        );
        
        // Encrypt the export with the provided password
        $export_key = hash('sha256', $password);
        $iv = openssl_random_pseudo_bytes(16);
        
        $encrypted_export = openssl_encrypt(
            json_encode($keys),
            'AES-256-CBC',
            $export_key,
            OPENSSL_RAW_DATA,
            $iv
        );
        
        $export_data = base64_encode($iv . $encrypted_export);
        
        $this->log_encryption_event('keys_exported', array(
            'user_id' => get_current_user_id()
        ));
        
        return $export_data;
    }
    
    /**
     * Get encrypted fields list
     */
    public function get_encrypted_fields() {
        return array_merge(
            $this->encrypted_fields,
            get_option('spiralengine_custom_encrypted_fields', array())
        );
    }
    
    /**
     * Add field to encryption list
     */
    public function add_encrypted_field($field_name) {
        $custom_fields = get_option('spiralengine_custom_encrypted_fields', array());
        
        if (!in_array($field_name, $custom_fields)) {
            $custom_fields[] = $field_name;
            update_option('spiralengine_custom_encrypted_fields', $custom_fields);
            
            $this->log_encryption_event('field_added_to_encryption', array(
                'field' => $field_name
            ));
            
            return true;
        }
        
        return false;
    }
}

// Initialize the class
SpiralEngine_Encryption::get_instance();

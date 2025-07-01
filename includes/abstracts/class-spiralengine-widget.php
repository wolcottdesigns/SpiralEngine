<?php
/**
 * SpiralEngine Widget Abstract Base Class
 *
 * @package     SpiralEngine
 * @subpackage  Abstracts
 * @file        includes/abstracts/class-spiralengine-widget.php
 * @since       1.0.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Abstract Widget Class
 *
 * Base class for all SpiralEngine widgets. Provides common functionality
 * and enforces implementation of required methods.
 *
 * @since 1.0.0
 */
abstract class SpiralEngine_Widget {
    
    /**
     * Widget ID
     *
     * @var string
     */
    protected $id;
    
    /**
     * Widget name
     *
     * @var string
     */
    protected $name;
    
    /**
     * Widget description
     *
     * @var string
     */
    protected $description;
    
    /**
     * Widget version
     *
     * @var string
     */
    protected $version = '1.0.0';
    
    /**
     * Widget icon
     *
     * @var string
     */
    protected $icon = 'dashicons-chart-area';
    
    /**
     * Minimum tier required
     *
     * @var string
     */
    protected $min_tier = 'free';
    
    /**
     * Widget capabilities
     *
     * @var array
     */
    protected $capabilities = [];
    
    /**
     * Widget settings
     *
     * @var array
     */
    protected $settings = [];
    
    /**
     * Static flag to prevent hook duplication
     *
     * @var array
     */
    protected static $hooks_registered = [];
    
    /**
     * Constructor
     */
    public function __construct() {
        // Initialize widget properties
        $this->init();
        
        // Setup hooks only once per widget type
        $this->setup_hooks();
    }
    
    /**
     * Initialize widget
     *
     * Child classes should set widget properties here
     *
     * @since 1.0.0
     * @return void
     */
    abstract protected function init();
    
    /**
     * Setup WordPress hooks
     *
     * @since 1.0.0
     * @return void
     */
    protected function setup_hooks() {
        // Prevent duplicate hook registration
        if (!empty($this->id) && !isset(self::$hooks_registered[$this->id])) {
            // Mark as registered
            self::$hooks_registered[$this->id] = true;
            
            // AJAX handlers
            add_action('wp_ajax_spiralengine_save_' . $this->id, [$this, 'ajax_save_episode']);
            add_action('wp_ajax_spiralengine_get_' . $this->id . '_data', [$this, 'ajax_get_data']);
            
            // Widget-specific hooks
            $this->register_hooks();
        }
    }
    
    /**
     * Register widget-specific hooks
     *
     * Override in child classes to add custom hooks
     *
     * @since 1.0.0
     * @return void
     */
    protected function register_hooks() {
        // Override in child classes if needed
    }
    
    /**
     * Get widget ID
     *
     * @since 1.0.0
     * @return string
     */
    public function get_id() {
        return $this->id;
    }
    
    /**
     * Get widget configuration
     *
     * @since 1.0.0
     * @return array
     */
    public function get_config() {
        return [
            'id'           => $this->id,
            'name'         => $this->name,
            'description'  => $this->description,
            'version'      => $this->version,
            'icon'         => $this->icon,
            'min_tier'     => $this->min_tier,
            'capabilities' => $this->capabilities,
            'settings'     => $this->settings
        ];
    }
    
    /**
     * Render widget form
     *
     * @since 1.0.0
     * @param array $args Form arguments
     * @return string HTML output
     */
    abstract public function render_form($args = []);
    
    /**
     * Validate episode data
     *
     * @since 1.0.0
     * @param array $data Raw form data
     * @return array Validated data or WP_Error on failure
     */
    abstract public function validate_data($data);
    
    /**
     * Process and save episode data
     *
     * @since 1.0.0
     * @param array $data Validated data
     * @return bool|int Episode ID on success, false on failure
     */
    public function process_data($data) {
        // Get current user
        $user_id = get_current_user_id();
        if (!$user_id) {
            return false;
        }
        
        // Check user has access to this widget
        if (!$this->user_can_access($user_id)) {
            return false;
        }
        
        // Get severity from data (default to 5 if not set)
        $severity = isset($data['severity']) ? intval($data['severity']) : 5;
        
        // Prepare metadata
        $metadata = [
            'ip_address' => $this->get_client_ip(),
            'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '',
            'form_version' => $this->version
        ];
        
        // Allow widgets to modify metadata
        $metadata = $this->prepare_metadata($metadata, $data);
        
        // Save episode using global wpdb
        global $wpdb;
        $table = $wpdb->prefix . 'spiralengine_episodes';
        
        $result = $wpdb->insert(
            $table,
            [
                'user_id' => $user_id,
                'widget_id' => $this->id,
                'severity' => $severity,
                'data' => json_encode($data),
                'metadata' => json_encode($metadata),
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql')
            ],
            ['%d', '%s', '%d', '%s', '%s', '%s', '%s']
        );
        
        return $result ? $wpdb->insert_id : false;
    }
    
    /**
     * Prepare metadata before saving
     *
     * Override to add widget-specific metadata
     *
     * @since 1.0.0
     * @param array $metadata Base metadata
     * @param array $data Episode data
     * @return array Modified metadata
     */
    protected function prepare_metadata($metadata, $data) {
        return $metadata;
    }
    
    /**
     * Render analytics for user
     *
     * @since 1.0.0
     * @param int $user_id User ID
     * @return string HTML output
     */
    abstract public function render_analytics($user_id);
    
    /**
     * Get widget data schema
     *
     * @since 1.0.0
     * @return array Schema definition
     */
    abstract public function get_schema();
    
    /**
     * Get widget capabilities
     *
     * @since 1.0.0
     * @return array List of capabilities
     */
    public function get_capabilities() {
        return array_merge([
            'track',    // Can track episodes
            'view',     // Can view own data
            'export',   // Can export data
            'delete'    // Can delete episodes
        ], $this->capabilities);
    }
    
    /**
     * Check if user can access widget
     *
     * @since 1.0.0
     * @param int|null $user_id User ID (null for current user)
     * @return bool
     */
    public function user_can_access($user_id = null) {
        if (is_null($user_id)) {
            $user_id = get_current_user_id();
        }
        
        if (!$user_id) {
            return false;
        }
        
        // Check if widget is enabled globally
        if (!$this->is_enabled()) {
            return false;
        }
        
        // For now, allow all logged-in users
        // TODO: Check user tier when membership system is ready
        return true;
    }
    
    /**
     * Check if widget is enabled
     *
     * @since 1.0.0
     * @return bool
     */
    public function is_enabled() {
        $enabled_widgets = get_option('spiralengine_enabled_widgets', []);
        
        // If no widgets are explicitly enabled, enable all by default
        if (empty($enabled_widgets)) {
            return true;
        }
        
        return in_array($this->id, $enabled_widgets, true);
    }
    
    /**
     * Enable widget
     *
     * @since 1.0.0
     * @return bool
     */
    public function enable() {
        $enabled_widgets = get_option('spiralengine_enabled_widgets', []);
        if (!in_array($this->id, $enabled_widgets, true)) {
            $enabled_widgets[] = $this->id;
            return update_option('spiralengine_enabled_widgets', $enabled_widgets);
        }
        return true;
    }
    
    /**
     * Disable widget
     *
     * @since 1.0.0
     * @return bool
     */
    public function disable() {
        $enabled_widgets = get_option('spiralengine_enabled_widgets', []);
        $enabled_widgets = array_diff($enabled_widgets, [$this->id]);
        return update_option('spiralengine_enabled_widgets', array_values($enabled_widgets));
    }
    
    /**
     * Get widget settings
     *
     * @since 1.0.0
     * @param string|null $key Specific setting key
     * @return mixed
     */
    public function get_setting($key = null) {
        $settings = get_option('spiralengine_widget_' . $this->id . '_settings', $this->settings);
        
        if (is_null($key)) {
            return $settings;
        }
        
        return isset($settings[$key]) ? $settings[$key] : null;
    }
    
    /**
     * Update widget settings
     *
     * @since 1.0.0
     * @param array $settings New settings
     * @return bool
     */
    public function update_settings($settings) {
        return update_option('spiralengine_widget_' . $this->id . '_settings', $settings);
    }
    
    /**
     * AJAX handler for saving episodes
     *
     * @since 1.0.0
     * @return void
     */
    public function ajax_save_episode() {
        // Verify nonce
        if (!check_ajax_referer('spiralengine_' . $this->id . '_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => __('Security check failed.', 'spiralengine')]);
        }
        
        // Check user can access
        if (!$this->user_can_access()) {
            wp_send_json_error(['message' => __('Access denied.', 'spiralengine')]);
        }
        
        // Get and validate data
        $data = isset($_POST['data']) ? $_POST['data'] : [];
        $validated = $this->validate_data($data);
        
        if (is_wp_error($validated)) {
            wp_send_json_error([
                'message' => $validated->get_error_message(),
                'errors' => $validated->get_error_data()
            ]);
        }
        
        // Process data
        $episode_id = $this->process_data($validated);
        
        if ($episode_id) {
            wp_send_json_success([
                'message' => __('Episode saved successfully.', 'spiralengine'),
                'episode_id' => $episode_id
            ]);
        } else {
            wp_send_json_error(['message' => __('Failed to save episode.', 'spiralengine')]);
        }
    }
    
    /**
     * AJAX handler for getting widget data
     *
     * @since 1.0.0
     * @return void
     */
    public function ajax_get_data() {
        // Verify nonce
        if (!check_ajax_referer('spiralengine_' . $this->id . '_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => __('Security check failed.', 'spiralengine')]);
        }
        
        // Check user can access
        if (!$this->user_can_access()) {
            wp_send_json_error(['message' => __('Access denied.', 'spiralengine')]);
        }
        
        global $wpdb;
        $user_id = get_current_user_id();
        $table = $wpdb->prefix . 'spiralengine_episodes';
        
        $limit = isset($_POST['limit']) ? intval($_POST['limit']) : 10;
        $offset = isset($_POST['offset']) ? intval($_POST['offset']) : 0;
        
        // Get episodes
        $episodes = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} 
            WHERE user_id = %d AND widget_id = %s 
            ORDER BY created_at DESC 
            LIMIT %d OFFSET %d",
            $user_id, $this->id, $limit, $offset
        ));
        
        wp_send_json_success([
            'episodes' => $episodes,
            'has_more' => count($episodes) === $limit
        ]);
    }
    
    /**
     * Render a form field
     *
     * @since 1.0.0
     * @param string $type Field type
     * @param array $config Field configuration
     * @return string HTML output
     */
    protected function render_field($type, $config) {
        $config = wp_parse_args($config, [
            'id' => '',
            'name' => '',
            'label' => '',
            'value' => '',
            'placeholder' => '',
            'required' => false,
            'class' => '',
            'description' => '',
            'options' => [],
            'min' => '',
            'max' => '',
            'step' => '',
            'rows' => 4
        ]);
        
        $output = '<div class="spiralengine-field spiralengine-field-' . esc_attr($type) . '">';
        
        // Label
        if ($config['label'] && $type !== 'checkbox') {
            $output .= '<label for="' . esc_attr($config['id']) . '">';
            $output .= esc_html($config['label']);
            if ($config['required']) {
                $output .= ' <span class="required">*</span>';
            }
            $output .= '</label>';
        }
        
        // Field
        switch ($type) {
            case 'text':
            case 'email':
            case 'url':
            case 'number':
            case 'date':
            case 'time':
                $output .= $this->render_input_field($type, $config);
                break;
                
            case 'textarea':
                $output .= $this->render_textarea_field($config);
                break;
                
            case 'select':
                $output .= $this->render_select_field($config);
                break;
                
            case 'radio':
                $output .= $this->render_radio_field($config);
                break;
                
            case 'checkbox':
                $output .= $this->render_checkbox_field($config);
                break;
                
            case 'range':
                $output .= $this->render_range_field($config);
                break;
                
            default:
                $output .= apply_filters('spiralengine_widget_render_field_' . $type, '', $config, $this);
                break;
        }
        
        // Description
        if ($config['description']) {
            $output .= '<p class="description">' . esc_html($config['description']) . '</p>';
        }
        
        $output .= '</div>';
        
        return $output;
    }
    
    /**
     * Render input field
     *
     * @since 1.0.0
     * @param string $type Input type
     * @param array $config Field configuration
     * @return string HTML output
     */
    protected function render_input_field($type, $config) {
        $attributes = [
            'type="' . esc_attr($type) . '"',
            'id="' . esc_attr($config['id']) . '"',
            'name="' . esc_attr($config['name']) . '"',
            'value="' . esc_attr($config['value']) . '"',
            'class="spiralengine-input ' . esc_attr($config['class']) . '"'
        ];
        
        if ($config['placeholder']) {
            $attributes[] = 'placeholder="' . esc_attr($config['placeholder']) . '"';
        }
        
        if ($config['required']) {
            $attributes[] = 'required';
        }
        
        if ($type === 'number' || $type === 'range') {
            if ($config['min'] !== '') {
                $attributes[] = 'min="' . esc_attr($config['min']) . '"';
            }
            if ($config['max'] !== '') {
                $attributes[] = 'max="' . esc_attr($config['max']) . '"';
            }
            if ($config['step'] !== '') {
                $attributes[] = 'step="' . esc_attr($config['step']) . '"';
            }
        }
        
        return '<input ' . implode(' ', $attributes) . '>';
    }
    
    /**
     * Render textarea field
     *
     * @since 1.0.0
     * @param array $config Field configuration
     * @return string HTML output
     */
    protected function render_textarea_field($config) {
        $attributes = [
            'id="' . esc_attr($config['id']) . '"',
            'name="' . esc_attr($config['name']) . '"',
            'class="spiralengine-textarea ' . esc_attr($config['class']) . '"',
            'rows="' . esc_attr($config['rows']) . '"'
        ];
        
        if ($config['placeholder']) {
            $attributes[] = 'placeholder="' . esc_attr($config['placeholder']) . '"';
        }
        
        if ($config['required']) {
            $attributes[] = 'required';
        }
        
        return '<textarea ' . implode(' ', $attributes) . '>' . esc_textarea($config['value']) . '</textarea>';
    }
    
    /**
     * Render select field
     *
     * @since 1.0.0
     * @param array $config Field configuration
     * @return string HTML output
     */
    protected function render_select_field($config) {
        $attributes = [
            'id="' . esc_attr($config['id']) . '"',
            'name="' . esc_attr($config['name']) . '"',
            'class="spiralengine-select ' . esc_attr($config['class']) . '"'
        ];
        
        if ($config['required']) {
            $attributes[] = 'required';
        }
        
        $output = '<select ' . implode(' ', $attributes) . '>';
        
        foreach ($config['options'] as $value => $label) {
            $selected = selected($config['value'], $value, false);
            $output .= '<option value="' . esc_attr($value) . '" ' . $selected . '>' . esc_html($label) . '</option>';
        }
        
        $output .= '</select>';
        
        return $output;
    }
    
    /**
     * Render radio field
     *
     * @since 1.0.0
     * @param array $config Field configuration
     * @return string HTML output
     */
    protected function render_radio_field($config) {
        $output = '<div class="spiralengine-radio-group">';
        
        foreach ($config['options'] as $value => $label) {
            $field_id = $config['id'] . '_' . $value;
            $checked = checked($config['value'], $value, false);
            
            $output .= '<label for="' . esc_attr($field_id) . '">';
            $output .= '<input type="radio" id="' . esc_attr($field_id) . '" ';
            $output .= 'name="' . esc_attr($config['name']) . '" ';
            $output .= 'value="' . esc_attr($value) . '" ';
            $output .= $checked;
            if ($config['required'] && empty($config['value'])) {
                $output .= ' required';
            }
            $output .= '> ';
            $output .= esc_html($label);
            $output .= '</label>';
        }
        
        $output .= '</div>';
        
        return $output;
    }
    
    /**
     * Render checkbox field
     *
     * @since 1.0.0
     * @param array $config Field configuration
     * @return string HTML output
     */
    protected function render_checkbox_field($config) {
        $checked = checked($config['value'], '1', false);
        
        $output = '<label for="' . esc_attr($config['id']) . '">';
        $output .= '<input type="checkbox" id="' . esc_attr($config['id']) . '" ';
        $output .= 'name="' . esc_attr($config['name']) . '" ';
        $output .= 'value="1" ';
        $output .= 'class="spiralengine-checkbox ' . esc_attr($config['class']) . '" ';
        $output .= $checked . '> ';
        $output .= esc_html($config['label']);
        $output .= '</label>';
        
        return $output;
    }
    
    /**
     * Render range field
     *
     * @since 1.0.0
     * @param array $config Field configuration
     * @return string HTML output
     */
    protected function render_range_field($config) {
        $output = '<div class="spiralengine-range-wrapper">';
        $output .= $this->render_input_field('range', $config);
        $output .= '<span class="spiralengine-range-value">' . esc_html($config['value']) . '</span>';
        $output .= '</div>';
        
        return $output;
    }
    
    /**
     * Get client IP address
     *
     * @since 1.0.0
     * @return string
     */
    protected function get_client_ip() {
        $ip_keys = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'];
        
        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                // Handle comma-separated IPs
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                return filter_var($ip, FILTER_VALIDATE_IP) ?: '0.0.0.0';
            }
        }
        
        return '0.0.0.0';
    }
}

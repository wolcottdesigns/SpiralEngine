<?php
/**
 * SpiralEngine Widget Loader Class
 *
 * @package     SpiralEngine
 * @subpackage  Core
 * @file        includes/class-spiralengine-widget-loader.php
 * @since       1.0.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Widget Loader Class
 *
 * Handles discovery, loading, and management of all widgets
 *
 * @since 1.0.0
 */
class SpiralEngine_Widget_Loader {
    
    /**
     * Loaded widgets
     *
     * @var array
     */
    private $widgets = [];
    
    /**
     * Widget errors
     *
     * @var array
     */
    private $errors = [];
    
    /**
     * Widget directories
     *
     * @var array
     */
    private $widget_dirs = [];
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->setup_widget_dirs();
        $this->load_widgets();
        $this->setup_hooks();
    }
    
    /**
     * Setup widget directories
     *
     * @since 1.0.0
     * @return void
     */
    private function setup_widget_dirs() {
        // Core widgets directory
        $this->widget_dirs[] = SPIRALENGINE_PLUGIN_DIR . 'widgets/';
        
        // Allow themes to add widgets
        $theme_dir = get_template_directory() . '/spiralengine-widgets/';
        if (is_dir($theme_dir)) {
            $this->widget_dirs[] = $theme_dir;
        }
        
        // Allow child themes to add widgets
        if (is_child_theme()) {
            $child_theme_dir = get_stylesheet_directory() . '/spiralengine-widgets/';
            if (is_dir($child_theme_dir)) {
                $this->widget_dirs[] = $child_theme_dir;
            }
        }
        
        // Allow plugins to register additional widget directories
        $this->widget_dirs = apply_filters('spiralengine_widget_directories', $this->widget_dirs);
    }
    
    /**
     * Load all widgets
     *
     * @since 1.0.0
     * @return void
     */
    private function load_widgets() {
        foreach ($this->widget_dirs as $dir) {
            if (!is_dir($dir)) {
                continue;
            }
            
            $files = glob($dir . 'class-*.php');
            if (!$files) {
                continue;
            }
            
            foreach ($files as $file) {
                $this->load_widget_file($file);
            }
        }
        
        // Allow direct widget registration
        do_action('spiralengine_register_widgets', $this);
    }
    
    /**
     * Load a widget file
     *
     * @since 1.0.0
     * @param string $file Widget file path
     * @return bool
     */
    private function load_widget_file($file) {
        if (!file_exists($file)) {
            return false;
        }
        
        // Extract class name from filename
        $filename = basename($file, '.php');
        $class_parts = explode('-', $filename);
        
        // Build expected class name
        $class_name = 'SpiralEngine_Widget_';
        for ($i = 1; $i < count($class_parts); $i++) {
            $class_name .= ucfirst($class_parts[$i]);
            if ($i < count($class_parts) - 1) {
                $class_name .= '_';
            }
        }
        
        // Include file
        require_once $file;
        
        // Check if class exists
        if (!class_exists($class_name)) {
            $this->errors[] = sprintf(
                __('Widget class %s not found in file %s', 'spiralengine'),
                $class_name,
                $file
            );
            return false;
        }
        
        // Check if class extends our base widget
        if (!is_subclass_of($class_name, 'SpiralEngine_Widget')) {
            $this->errors[] = sprintf(
                __('Widget class %s must extend SpiralEngine_Widget', 'spiralengine'),
                $class_name
            );
            return false;
        }
        
        try {
            // Instantiate widget
            $widget = new $class_name();
            
            // Register widget
            $this->register_widget($widget);
            
            return true;
        } catch (Exception $e) {
            $this->errors[] = sprintf(
                __('Error loading widget %s: %s', 'spiralengine'),
                $class_name,
                $e->getMessage()
            );
            return false;
        }
    }
    
    /**
     * Register a widget
     *
     * @since 1.0.0
     * @param SpiralEngine_Widget $widget Widget instance
     * @return bool
     */
    public function register_widget(SpiralEngine_Widget $widget) {
        $widget_id = $widget->get_id();
        
        // Check if widget already registered
        if (isset($this->widgets[$widget_id])) {
            $this->errors[] = sprintf(
                __('Widget with ID %s is already registered', 'spiralengine'),
                $widget_id
            );
            return false;
        }
        
        // Validate widget
        if (!$this->validate_widget($widget)) {
            return false;
        }
        
        // Store widget
        $this->widgets[$widget_id] = $widget;
        
        // Fire action
        do_action('spiralengine_widget_registered', $widget_id, $widget);
        
        return true;
    }
    
    /**
     * Validate widget
     *
     * @since 1.0.0
     * @param SpiralEngine_Widget $widget Widget instance
     * @return bool
     */
    private function validate_widget($widget) {
        $config = $widget->get_config();
        
        // Check required properties
        $required = ['id', 'name', 'description', 'version'];
        foreach ($required as $prop) {
            if (empty($config[$prop])) {
                $this->errors[] = sprintf(
                    __('Widget is missing required property: %s', 'spiralengine'),
                    $prop
                );
                return false;
            }
        }
        
        // Validate widget ID
        if (!preg_match('/^[a-z0-9_-]+$/', $config['id'])) {
            $this->errors[] = sprintf(
                __('Widget ID %s contains invalid characters. Use only lowercase letters, numbers, hyphens, and underscores.', 'spiralengine'),
                $config['id']
            );
            return false;
        }
        
        // Validate schema
        $schema = $widget->get_schema();
        if (!is_array($schema)) {
            $this->errors[] = sprintf(
                __('Widget %s must return an array from get_schema()', 'spiralengine'),
                $config['id']
            );
            return false;
        }
        
        return true;
    }
    
    /**
     * Setup hooks
     *
     * @since 1.0.0
     * @return void
     */
    private function setup_hooks() {
        // Admin hooks
        add_action('admin_init', [$this, 'handle_widget_activation']);
        add_action('wp_ajax_spiralengine_toggle_widget', [$this, 'ajax_toggle_widget']);
        
        // Widget display hooks
        add_action('spiralengine_dashboard_widgets', [$this, 'display_dashboard_widgets']);
        add_filter('spiralengine_available_widgets', [$this, 'get_available_widgets']);
        
        // Widget settings
        add_action('spiralengine_widget_settings', [$this, 'render_widget_settings'], 10, 1);
    }
    
    /**
     * Get all widgets
     *
     * @since 1.0.0
     * @return array
     */
    public function get_widgets() {
        return $this->widgets;
    }
    
    /**
     * Get widget by ID
     *
     * @since 1.0.0
     * @param string $widget_id Widget ID
     * @return SpiralEngine_Widget|null
     */
    public function get_widget($widget_id) {
        return $this->widgets[$widget_id] ?? null;
    }
    
    /**
     * Get enabled widgets
     *
     * @since 1.0.0
     * @return array
     */
    public function get_enabled_widgets() {
        $enabled = [];
        
        foreach ($this->widgets as $id => $widget) {
            if ($widget->is_enabled()) {
                $enabled[$id] = $widget;
            }
        }
        
        return $enabled;
    }
    
    /**
     * Get widgets available to user
     *
     * @since 1.0.0
     * @param int|null $user_id User ID
     * @return array
     */
    public function get_user_widgets($user_id = null) {
        $available = [];
        
        foreach ($this->get_enabled_widgets() as $id => $widget) {
            if ($widget->user_can_access($user_id)) {
                $available[$id] = $widget;
            }
        }
        
        return $available;
    }
    
    /**
     * Get widget errors
     *
     * @since 1.0.0
     * @return array
     */
    public function get_errors() {
        return $this->errors;
    }
    
    /**
     * Handle widget activation from admin
     *
     * @since 1.0.0
     * @return void
     */
    public function handle_widget_activation() {
        if (!isset($_GET['spiralengine_widget_action'])) {
            return;
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // Verify nonce
        if (!wp_verify_nonce($_GET['_wpnonce'] ?? '', 'spiralengine_widget_action')) {
            wp_die(__('Security check failed.', 'spiralengine'));
        }
        
        $widget_id = sanitize_key($_GET['widget_id'] ?? '');
        $action = sanitize_key($_GET['spiralengine_widget_action']);
        
        $widget = $this->get_widget($widget_id);
        if (!$widget) {
            wp_die(__('Invalid widget.', 'spiralengine'));
        }
        
        $success = false;
        $message = '';
        
        switch ($action) {
            case 'enable':
                $success = $widget->enable();
                $message = $success 
                    ? __('Widget enabled successfully.', 'spiralengine')
                    : __('Failed to enable widget.', 'spiralengine');
                break;
                
            case 'disable':
                $success = $widget->disable();
                $message = $success
                    ? __('Widget disabled successfully.', 'spiralengine')
                    : __('Failed to disable widget.', 'spiralengine');
                break;
        }
        
        // Store message
        if ($message) {
            add_settings_error(
                'spiralengine_widgets',
                'widget_action',
                $message,
                $success ? 'success' : 'error'
            );
        }
        
        // Redirect back
        wp_safe_redirect(remove_query_arg(['spiralengine_widget_action', 'widget_id', '_wpnonce']));
        exit;
    }
    
    /**
     * AJAX handler for toggling widget
     *
     * @since 1.0.0
     * @return void
     */
    public function ajax_toggle_widget() {
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied.', 'spiralengine')]);
        }
        
        // Verify nonce
        if (!check_ajax_referer('spiralengine_admin_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => __('Security check failed.', 'spiralengine')]);
        }
        
        $widget_id = sanitize_key($_POST['widget_id'] ?? '');
        $widget = $this->get_widget($widget_id);
        
        if (!$widget) {
            wp_send_json_error(['message' => __('Invalid widget.', 'spiralengine')]);
        }
        
        // Toggle state
        $enabled = $widget->is_enabled();
        $success = $enabled ? $widget->disable() : $widget->enable();
        
        if ($success) {
            wp_send_json_success([
                'enabled' => !$enabled,
                'message' => !$enabled 
                    ? __('Widget enabled successfully.', 'spiralengine')
                    : __('Widget disabled successfully.', 'spiralengine')
            ]);
        } else {
            wp_send_json_error(['message' => __('Failed to update widget status.', 'spiralengine')]);
        }
    }
    
    /**
     * Display dashboard widgets
     *
     * @since 1.0.0
     * @return void
     */
    public function display_dashboard_widgets() {
        $widgets = $this->get_user_widgets();
        
        if (empty($widgets)) {
            echo '<p class="spiralengine-no-widgets">' . __('No tracking widgets available for your membership tier.', 'spiralengine') . '</p>';
            return;
        }
        
        echo '<div class="spiralengine-widget-grid">';
        
        foreach ($widgets as $widget) {
            $config = $widget->get_config();
            ?>
            <div class="spiralengine-widget-card" data-widget-id="<?php echo esc_attr($config['id']); ?>">
                <div class="widget-header">
                    <span class="widget-icon">
                        <span class="dashicons <?php echo esc_attr($config['icon']); ?>"></span>
                    </span>
                    <h3><?php echo esc_html($config['name']); ?></h3>
                </div>
                <div class="widget-body">
                    <p class="widget-description"><?php echo esc_html($config['description']); ?></p>
                    <button class="button button-primary spiralengine-track-button" 
                            data-widget="<?php echo esc_attr($config['id']); ?>">
                        <?php _e('Track Episode', 'spiralengine'); ?>
                    </button>
                </div>
            </div>
            <?php
        }
        
        echo '</div>';
    }
    
    /**
     * Get available widgets for filter
     *
     * @since 1.0.0
     * @param array $widgets Current widgets
     * @return array
     */
    public function get_available_widgets($widgets = []) {
        foreach ($this->widgets as $id => $widget) {
            $config = $widget->get_config();
            $widgets[$id] = $config['name'];
        }
        
        return $widgets;
    }
    
    /**
     * Render widget settings
     *
     * @since 1.0.0
     * @param string $widget_id Widget ID
     * @return void
     */
    public function render_widget_settings($widget_id) {
        $widget = $this->get_widget($widget_id);
        if (!$widget) {
            return;
        }
        
        $config = $widget->get_config();
        $settings = $widget->get_setting();
        ?>
        <div class="spiralengine-widget-settings" data-widget="<?php echo esc_attr($widget_id); ?>">
            <h3><?php echo esc_html($config['name']); ?> <?php _e('Settings', 'spiralengine'); ?></h3>
            
            <table class="form-table">
                <tr>
                    <th scope="row"><?php _e('Status', 'spiralengine'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" 
                                   name="spiralengine_widget_<?php echo esc_attr($widget_id); ?>_enabled"
                                   value="1"
                                   <?php checked($widget->is_enabled()); ?>>
                            <?php _e('Enable this widget', 'spiralengine'); ?>
                        </label>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><?php _e('Minimum Tier', 'spiralengine'); ?></th>
                    <td>
                        <select name="spiralengine_widget_<?php echo esc_attr($widget_id); ?>_min_tier">
                            <?php
                            $tiers = ['free' => 'Free', 'silver' => 'Silver', 'gold' => 'Gold', 'platinum' => 'Platinum'];
                            foreach ($tiers as $value => $label) {
                                printf(
                                    '<option value="%s" %s>%s</option>',
                                    esc_attr($value),
                                    selected($config['min_tier'], $value, false),
                                    esc_html($label)
                                );
                            }
                            ?>
                        </select>
                    </td>
                </tr>
                
                <?php
                // Allow widgets to add custom settings
                do_action('spiralengine_widget_settings_fields', $widget_id, $settings, $widget);
                ?>
            </table>
        </div>
        <?php
    }
    
    /**
     * Get widget statistics
     *
     * @since 1.0.0
     * @return array
     */
    public function get_widget_stats() {
        global $wpdb;
        
        $stats = [];
        $table = $wpdb->prefix . 'spiralengine_episodes';
        
        foreach ($this->widgets as $widget_id => $widget) {
            $count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE widget_id = %s",
                $widget_id
            ));
            
            $stats[$widget_id] = [
                'name' => $widget->get_config()['name'],
                'total_episodes' => intval($count),
                'enabled' => $widget->is_enabled()
            ];
        }
        
        return $stats;
    }
    
    /**
     * Process widget form submission
     *
     * @since 1.0.0
     * @param string $widget_id Widget ID
     * @param array $data Form data
     * @return int|WP_Error Episode ID or error
     */
    public function process_widget_form($widget_id, $data) {
        $widget = $this->get_widget($widget_id);
        
        if (!$widget) {
            return new WP_Error('invalid_widget', __('Invalid widget ID.', 'spiralengine'));
        }
        
        if (!$widget->user_can_access()) {
            return new WP_Error('access_denied', __('You do not have access to this widget.', 'spiralengine'));
        }
        
        // Validate data
        $validated = $widget->validate_data($data);
        if (is_wp_error($validated)) {
            return $validated;
        }
        
        // Process data
        $episode_id = $widget->process_data($validated);
        
        if (!$episode_id) {
            return new WP_Error('save_failed', __('Failed to save episode.', 'spiralengine'));
        }
        
        // Fire action
        do_action('spiralengine_episode_saved', $episode_id, $widget_id, $validated);
        
        return $episode_id;
    }
    
    /**
     * Render widget form
     *
     * @since 1.0.0
     * @param string $widget_id Widget ID
     * @param array $args Form arguments
     * @return string HTML output
     */
    public function render_widget_form($widget_id, $args = []) {
        $widget = $this->get_widget($widget_id);
        
        if (!$widget) {
            return '<p class="error">' . __('Invalid widget.', 'spiralengine') . '</p>';
        }
        
        if (!$widget->user_can_access()) {
            return '<p class="error">' . __('You do not have access to this widget.', 'spiralengine') . '</p>';
        }
        
        return $widget->render_form($args);
    }
    
    /**
     * Get widget analytics
     *
     * @since 1.0.0
     * @param string $widget_id Widget ID
     * @param int $user_id User ID
     * @return string HTML output
     */
    public function get_widget_analytics($widget_id, $user_id) {
        $widget = $this->get_widget($widget_id);
        
        if (!$widget) {
            return '<p class="error">' . __('Invalid widget.', 'spiralengine') . '</p>';
        }
        
        return $widget->render_analytics($user_id);
    }
}

